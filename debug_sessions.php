<?php
require __DIR__ . '/config.php';

use App\OpenVPNManager;
use App\DB;
use App\DockerCLI;

echo "=== DEBUGGING SESSION TRACKING ===\n\n";

try {
    $pdo = DB::pdo();
    
    // 1. Check tenant
    echo "1. Checking tenant...\n";
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([2]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        echo "❌ Tenant 2 not found!\n";
        exit(1);
    }
    
    echo "✅ Tenant found: " . $tenant['name'] . "\n";
    echo "   Container: " . $tenant['docker_container'] . "\n\n";
    
    // 2. Check status file
    echo "2. Checking status file...\n";
    $statusFile = '/tmp/openvpn-status.log';
    $out = DockerCLI::exec($tenant['docker_container'], "test -f " . escapeshellarg($statusFile) . " && cat " . escapeshellarg($statusFile) . " || echo 'FILE_NOT_FOUND'");
    $raw = implode("\n", $out);
    
    if ($raw === 'FILE_NOT_FOUND') {
        echo "❌ Status file not found!\n";
        exit(1);
    }
    
    echo "✅ Status file found, content:\n";
    echo "---\n" . $raw . "\n---\n\n";
    
    // 3. Parse the status file manually
    echo "3. Parsing status file...\n";
    $lines = explode("\n", $raw);
    $stage = '';
    $clients = [];
    $routes = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === 'CLIENT_LIST,Common Name,Real Address,Virtual Address,Virtual IPv6 Address,Bytes Received,Bytes Sent,Connected Since,Connected Since (time_t),Username,Client ID,Peer ID') { 
            $stage = 'clients'; 
            continue; 
        }
        if ($line === 'ROUTING_TABLE,Virtual Address,Common Name,Real Address,Last Ref,Last Ref (time_t)') { 
            $stage = 'routes';  
            continue; 
        }
        if ($line === 'GLOBAL_STATS,Max bcast/mcast queue length,0' || $line === 'END') { 
            $stage = ''; 
            continue; 
        }
        
        if ($stage === 'clients' && strpos($line, 'CLIENT_LIST,') === 0) {
            $parts = explode(',', $line);
            if (count($parts) >= 8) {
                $cn = $parts[1];
                $real = $parts[2];
                $br = (int)$parts[5];
                $bs = (int)$parts[6];
                $since = $parts[7];
                $clients[$cn] = [
                    'real' => $real,
                    'br' => $br,
                    'bs' => $bs,
                    'since' => strtotime($since) ?: null
                ];
                echo "✅ Found client: $cn from $real (since: $since)\n";
            }
        }
        
        if ($stage === 'routes' && strpos($line, 'ROUTING_TABLE,') === 0) {
            $parts = explode(',', $line);
            if (count($parts) >= 3) {
                $vip = $parts[1];
                $cn = $parts[2];
                $routes[$cn] = $vip;
                echo "✅ Found route: $cn -> $vip\n";
            }
        }
    }
    
    echo "\n4. Found " . count($clients) . " clients and " . count($routes) . " routes\n\n";
    
    // 5. Check if VPN user exists
    echo "5. Checking VPN users...\n";
    foreach ($clients as $cn => $c) {
        $userStmt = $pdo->prepare("SELECT id FROM vpn_users WHERE tenant_id = ? AND username = ?");
        $userStmt->execute([2, $cn]);
        $user = $userStmt->fetch();
        $userId = $user ? $user['id'] : null;
        
        if ($userId) {
            echo "✅ VPN user found for $cn (ID: $userId)\n";
        } else {
            echo "❌ VPN user NOT found for $cn\n";
        }
    }
    
    // 6. Try to refresh sessions
    echo "\n6. Refreshing sessions...\n";
    OpenVPNManager::refreshSessions(2);
    echo "✅ Session refresh completed\n";
    
    // 7. Check sessions in database
    echo "\n7. Checking sessions in database...\n";
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE tenant_id = ?");
    $stmt->execute([2]);
    $sessions = $stmt->fetchAll();
    
    echo "Found " . count($sessions) . " sessions in database:\n";
    foreach ($sessions as $session) {
        echo "- " . $session['common_name'] . " from " . $session['real_address'] . " (IP: " . $session['virtual_address'] . ")\n";
        echo "  Connected since: " . $session['since'] . "\n";
        echo "  Last seen: " . $session['last_seen'] . "\n";
        echo "  User ID: " . $session['user_id'] . "\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
