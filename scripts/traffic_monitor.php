<?php
#!/usr/bin/env php
<?php
/**
 * Traffic monitoring script for OpenVPN Manager
 * 
 * This script should be run as a cron job every few minutes to collect
 * traffic statistics from OpenVPN containers and store them in the database.
 * 
 * Usage: php scripts/traffic_monitor.php [tenant_id]
 * 
 * If no tenant_id is provided, it will monitor all active tenants.
 */

require __DIR__ . '/../config.php';

use App\DB;
use App\OpenVPNManager;
use App\TrafficMonitor;
use App\DockerCLI;

class TrafficMonitorScript
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = DB::pdo();
    }
    
    public function run(?int $tenantId = null): void
    {
        echo "Starting traffic monitoring...\n";
        
        if ($tenantId) {
            $this->monitorTenant($tenantId);
        } else {
            $this->monitorAllTenants();
        }
        
        // Clean up old logs
        TrafficMonitor::cleanupOldLogs();
        
        echo "Traffic monitoring completed.\n";
    }
    
    private function monitorAllTenants(): void
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tenants WHERE status = 'running'");
        $stmt->execute();
        $tenants = $stmt->fetchAll();
        
        foreach ($tenants as $tenant) {
            echo "Monitoring tenant: {$tenant['name']} (ID: {$tenant['id']})\n";
            $this->monitorTenant($tenant['id']);
        }
    }
    
    private function monitorTenant(int $tenantId): void
    {
        try {
            $tenant = OpenVPNManager::getTenant($tenantId);
            if (!$tenant) {
                echo "Tenant {$tenantId} not found\n";
                return;
            }
            
            // Get active sessions
            $sessions = $this->getActiveSessions($tenantId);
            
            foreach ($sessions as $session) {
                $this->processSessionTraffic($tenantId, $session);
            }
            
        } catch (\Throwable $e) {
            echo "Error monitoring tenant {$tenantId}: " . $e->getMessage() . "\n";
        }
    }
    
    private function getActiveSessions(int $tenantId): array
    {
        $cutoffTime = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        $stmt = $this->pdo->prepare("
            SELECT s.*, vu.id as user_id
            FROM sessions s
            JOIN vpn_users vu ON s.tenant_id = vu.tenant_id AND s.common_name = vu.username
            WHERE s.tenant_id = ? AND s.last_seen >= ?
        ");
        $stmt->execute([$tenantId, $cutoffTime]);
        return $stmt->fetchAll();
    }
    
    private function processSessionTraffic(int $tenantId, array $session): void
    {
        try {
            // Get current traffic stats from OpenVPN status
            $trafficStats = $this->getOpenVPNTrafficStats($tenantId, $session['common_name']);
            
            if ($trafficStats) {
                // Calculate traffic difference (since last check)
                $lastStats = $this->getLastTrafficStats($tenantId, $session['user_id']);
                
                $bytesIn = max(0, $trafficStats['bytes_received'] - ($lastStats['bytes_received'] ?? 0));
                $bytesOut = max(0, $trafficStats['bytes_sent'] - ($lastStats['bytes_sent'] ?? 0));
                
                if ($bytesIn > 0 || $bytesOut > 0) {
                    // Log the traffic
                    TrafficMonitor::logTraffic(
                        $tenantId,
                        $session['user_id'],
                        $bytesIn,
                        $bytesOut,
                        'TCP', // Default protocol
                        $this->getDestinationFromSession($session),
                        null, // destination_port
                        $session['real_address'],
                        null, // source_port
                        $this->getDomainFromDestination($session)
                    );
                    
                    // Update last stats
                    $this->updateLastTrafficStats($tenantId, $session['user_id'], $trafficStats);
                }
            }
            
        } catch (\Throwable $e) {
            echo "Error processing session {$session['common_name']}: " . $e->getMessage() . "\n";
        }
    }
    
    private function getOpenVPNTrafficStats(int $tenantId, string $username): ?array
    {
        try {
            $tenant = OpenVPNManager::getTenant($tenantId);
            $container = $tenant['docker_container'];
            
            // Get OpenVPN status
            $statusFile = $tenant['status_path'] ?: '/etc/openvpn/openvpn-status.log';
            $output = DockerCLI::exec($container, "cat " . escapeshellarg($statusFile) . " 2>/dev/null || true");
            $content = implode("\n", $output);
            
            // Parse the status file
            $lines = explode("\n", $content);
            $stage = '';
            
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === 'OpenVPN CLIENT LIST') { 
                    $stage = 'clients'; 
                    continue; 
                }
                if ($line === 'ROUTING TABLE' || $line === 'GLOBAL STATS' || $line === 'END') { 
                    $stage = ''; 
                    continue; 
                }
                
                if ($stage === 'clients' && strpos($line, ',') !== false && stripos($line, 'Common Name') === false) {
                    $parts = array_map('trim', explode(',', $line));
                    if (count($parts) >= 5 && $parts[0] === $username) {
                        return [
                            'bytes_received' => (int)$parts[2],
                            'bytes_sent' => (int)$parts[3]
                        ];
                    }
                }
            }
            
        } catch (\Throwable $e) {
            echo "Error getting OpenVPN stats: " . $e->getMessage() . "\n";
        }
        
        return null;
    }
    
    private function getLastTrafficStats(int $tenantId, int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT bytes_received, bytes_sent 
            FROM sessions 
            WHERE tenant_id = ? AND common_name = (
                SELECT username FROM vpn_users WHERE id = ?
            )
            ORDER BY last_seen DESC 
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $userId]);
        return $stmt->fetch() ?: [];
    }
    
    private function updateLastTrafficStats(int $tenantId, int $userId, array $stats): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE sessions 
            SET bytes_received = ?, bytes_sent = ?, last_seen = NOW()
            WHERE tenant_id = ? AND common_name = (
                SELECT username FROM vpn_users WHERE id = ?
            )
        ");
        $stmt->execute([$stats['bytes_received'], $stats['bytes_sent'], $tenantId, $userId]);
    }
    
    private function getDestinationFromSession(array $session): ?string
    {
        // This is a simplified approach - in a real implementation,
        // you would need to monitor actual network connections
        // For now, we'll use a placeholder
        return null;
    }
    
    private function getDomainFromDestination(array $session): ?string
    {
        // This is a simplified approach - in a real implementation,
        // you would need to monitor actual DNS queries and connections
        // For now, we'll use a placeholder
        return null;
    }
}

// Run the script
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

$tenantId = isset($argv[1]) ? (int)$argv[1] : null;
$monitor = new TrafficMonitorScript();
$monitor->run($tenantId);
