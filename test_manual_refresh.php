<?php
require __DIR__ . '/config.php';

use App\OpenVPNManager;
use App\DB;

echo "=== MANUAL SESSION REFRESH TEST ===\n\n";

try {
    // Test with tenant ID 3 (Maggots DC)
    $tenantId = 3;
    
    echo "1. Testing session refresh for tenant ID: $tenantId\n";
    
    // Get current sessions before refresh
    $pdo = DB::pdo();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sessions WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $beforeCount = $stmt->fetch()['count'];
    echo "   Sessions before refresh: $beforeCount\n";
    
    // Perform refresh
    OpenVPNManager::refreshSessions($tenantId);
    echo "   ✅ Refresh completed\n";
    
    // Get sessions after refresh
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sessions WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $afterCount = $stmt->fetch()['count'];
    echo "   Sessions after refresh: $afterCount\n";
    
    // Show session details
    if ($afterCount > 0) {
        $sessions = $pdo->prepare("SELECT * FROM sessions WHERE tenant_id = ? ORDER BY last_seen DESC");
        $sessions->execute([$tenantId]);
        $sessionData = $sessions->fetchAll();
        
        echo "\n2. Session Details:\n";
        foreach ($sessionData as $session) {
            echo "   - ID: {$session['id']}\n";
            echo "     Common Name: {$session['common_name']}\n";
            echo "     Real Address: {$session['real_address']}\n";
            echo "     Virtual Address: {$session['virtual_address']}\n";
            echo "     Bytes Received: {$session['bytes_received']}\n";
            echo "     Bytes Sent: {$session['bytes_sent']}\n";
            echo "     Since: {$session['since']}\n";
            echo "     Last Seen: {$session['last_seen']}\n";
            echo "     Location: {$session['geo_country']}, {$session['geo_city']}\n";
            echo "\n";
        }
    }
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
