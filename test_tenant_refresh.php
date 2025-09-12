<?php
require __DIR__ . '/config.php';

use App\DB;
use App\OpenVPNManager;

echo "=== TESTING TENANT REFRESH ===\n\n";

try {
    $pdo = DB::pdo();
    
    // Get all tenants
    $tenants = $pdo->query("SELECT id, name, docker_container FROM tenants")->fetchAll();
    
    foreach ($tenants as $tenant) {
        echo "Testing tenant: {$tenant['name']} (ID: {$tenant['id']}, Container: {$tenant['docker_container']})\n";
        
        try {
            OpenVPNManager::refreshSessions($tenant['id']);
            echo "  ✅ Refresh completed\n";
            
            // Check sessions
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sessions WHERE tenant_id = ?");
            $stmt->execute([$tenant['id']]);
            $count = $stmt->fetch()['count'];
            echo "  Sessions found: $count\n";
            
            if ($count > 0) {
                $sessions = $pdo->prepare("SELECT * FROM sessions WHERE tenant_id = ?");
                $sessions->execute([$tenant['id']]);
                $sessionData = $sessions->fetchAll();
                foreach ($sessionData as $session) {
                    echo "    - {$session['common_name']} from {$session['real_address']}\n";
                }
            }
            
        } catch (\Throwable $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
}
