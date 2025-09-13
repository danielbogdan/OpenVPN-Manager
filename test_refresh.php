<?php
require __DIR__ . '/config.php';

use App\DB;
use App\OpenVPNManager;

echo "=== TEST REFRESH SESSIONS ===\n\n";

// Check current sessions count
$pdo = DB::pdo();
$beforeCount = $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn();
echo "📊 Sessions in database BEFORE refresh: $beforeCount\n\n";

// Get tenant info
$tenants = $pdo->query("SELECT id, name FROM tenants ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

foreach ($tenants as $tenant) {
    echo "🔄 Testing refreshSessions for Tenant {$tenant['id']} ({$tenant['name']})...\n";
    
    try {
        // Test if we can get tenant info
        $t = OpenVPNManager::getTenant($tenant['id']);
        echo "   ✅ Tenant found: {$t['name']}\n";
        echo "   📁 Container: {$t['docker_container']}\n";
        echo "   📄 Status file: " . ($t['status_path'] ?: '/tmp/openvpn-status.log') . "\n";
        
        // Test if container exists
        $containerExists = \App\DockerCLI::existsContainer($t['docker_container']);
        echo "   🐳 Container exists: " . ($containerExists ? "YES" : "NO") . "\n";
        
        if ($containerExists) {
            // Test if status file exists
            $statusFile = $t['status_path'] ?: '/tmp/openvpn-status.log';
            $fileCheck = \App\DockerCLI::exec($t['docker_container'], "test -f " . escapeshellarg($statusFile) . " && echo 'EXISTS' || echo 'NOT_FOUND'");
            $fileExists = implode('', $fileCheck) === 'EXISTS';
            echo "   📄 Status file exists: " . ($fileExists ? "YES" : "NO") . "\n";
            
            if ($fileExists) {
                // Try to read a small sample
                $sample = \App\DockerCLI::exec($t['docker_container'], "head -5 " . escapeshellarg($statusFile));
                echo "   📝 File sample (first 5 lines):\n";
                foreach ($sample as $line) {
                    echo "      " . $line . "\n";
                }
            }
        }
        
        // Now try refreshSessions
        echo "   🔄 Calling refreshSessions...\n";
        OpenVPNManager::refreshSessions($tenant['id']);
        echo "   ✅ refreshSessions completed successfully\n";
        
    } catch (\Throwable $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
        echo "   📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
    echo "\n";
}

// Check sessions count after
$afterCount = $pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn();
echo "📊 Sessions in database AFTER refresh: $afterCount\n";
echo "📈 Difference: " . ($afterCount - $beforeCount) . " new sessions\n\n";

// Show current sessions
echo "📋 Current sessions:\n";
$sessions = $pdo->query("SELECT tenant_id, common_name, real_address, virtual_address, last_seen FROM sessions ORDER BY last_seen DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($sessions as $session) {
    echo "  - Tenant {$session['tenant_id']}: {$session['common_name']} ({$session['real_address']} -> {$session['virtual_address']}) - {$session['last_seen']}\n";
}