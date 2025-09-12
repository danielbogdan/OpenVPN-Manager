<?php
require __DIR__ . '/config.php';

use App\DB;
use App\OpenVPNManager;
use App\DockerCLI;

echo "=== DEBUGGING SESSION REFRESH ISSUE ===\n\n";

try {
    $pdo = DB::pdo();
    
    // 1. Check tenants
    echo "1. CHECKING TENANTS:\n";
    $tenants = $pdo->query("SELECT id, name, docker_container, status FROM tenants")->fetchAll();
    foreach ($tenants as $tenant) {
        echo "   - Tenant {$tenant['id']}: {$tenant['name']} (Container: {$tenant['docker_container']}, Status: {$tenant['status']})\n";
    }
    echo "\n";
    
    // 2. Check VPN users
    echo "2. CHECKING VPN USERS:\n";
    $vpnUsers = $pdo->query("SELECT id, tenant_id, username, email, status FROM vpn_users")->fetchAll();
    foreach ($vpnUsers as $user) {
        echo "   - User {$user['id']}: {$user['username']} (Tenant: {$user['tenant_id']}, Status: {$user['status']})\n";
    }
    echo "\n";
    
    // 3. Check current sessions
    echo "3. CURRENT SESSIONS:\n";
    $sessions = $pdo->query("SELECT * FROM sessions")->fetchAll();
    if (empty($sessions)) {
        echo "   - No sessions found\n";
    } else {
        foreach ($sessions as $session) {
            echo "   - Session {$session['id']}: {$session['common_name']} from {$session['real_address']} (Last seen: {$session['last_seen']})\n";
        }
    }
    echo "\n";
    
    // 4. Check Docker containers
    echo "4. CHECKING DOCKER CONTAINERS:\n";
    $containers = DockerCLI::run("docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'");
    foreach ($containers as $container) {
        if (strpos($container, 'vpn_tenant') !== false) {
            echo "   - $container\n";
        }
    }
    echo "\n";
    
    // 5. Check OpenVPN status files for each tenant
    echo "5. CHECKING OPENVPN STATUS FILES:\n";
    foreach ($tenants as $tenant) {
        if ($tenant['docker_container']) {
            echo "   - Tenant {$tenant['name']} (Container: {$tenant['docker_container']}):\n";
            
            // Check if container is running
            $containerStatus = DockerCLI::run("docker inspect --format='{{.State.Status}}' {$tenant['docker_container']} 2>/dev/null || echo 'not_found'");
            $status = trim($containerStatus[0] ?? 'unknown');
            echo "     Container status: $status\n";
            
            if ($status === 'running') {
                // Check status file
                $statusFile = '/tmp/openvpn-status.log';
                $statusContent = DockerCLI::exec($tenant['docker_container'], "test -f $statusFile && cat $statusFile || echo 'FILE_NOT_FOUND'");
                $content = implode("\n", $statusContent);
                
                if ($content === 'FILE_NOT_FOUND') {
                    echo "     Status file: NOT FOUND\n";
                } else {
                    echo "     Status file: FOUND\n";
                    echo "     Content preview:\n";
                    $lines = explode("\n", $content);
                    foreach (array_slice($lines, 0, 5) as $line) {
                        echo "       $line\n";
                    }
                    if (count($lines) > 5) {
                        echo "       ... (" . (count($lines) - 5) . " more lines)\n";
                    }
                }
            }
            echo "\n";
        }
    }
    
    // 6. Try manual session refresh
    echo "6. TESTING MANUAL SESSION REFRESH:\n";
    foreach ($tenants as $tenant) {
        if ($tenant['docker_container']) {
            echo "   - Refreshing sessions for tenant {$tenant['id']} ({$tenant['name']}):\n";
            try {
                OpenVPNManager::refreshSessions($tenant['id']);
                echo "     ✅ Refresh completed successfully\n";
                
                // Check sessions after refresh
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sessions WHERE tenant_id = ?");
                $stmt->execute([$tenant['id']]);
                $count = $stmt->fetch()['count'];
                echo "     Sessions found: $count\n";
                
            } catch (\Throwable $e) {
                echo "     ❌ Error: " . $e->getMessage() . "\n";
            }
        }
    }
    echo "\n";
    
    // 7. Final session check
    echo "7. FINAL SESSION CHECK:\n";
    $finalSessions = $pdo->query("SELECT * FROM sessions")->fetchAll();
    if (empty($finalSessions)) {
        echo "   - Still no sessions found\n";
    } else {
        foreach ($finalSessions as $session) {
            echo "   - Session {$session['id']}: {$session['common_name']} from {$session['real_address']} (Last seen: {$session['last_seen']})\n";
        }
    }
    
} catch (\Throwable $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
