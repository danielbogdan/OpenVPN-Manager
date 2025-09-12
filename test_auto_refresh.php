<?php
require __DIR__ . '/config.php';

use App\OpenVPNManager;
use App\DB;

echo "=== TESTING AUTO-REFRESH FUNCTIONALITY ===\n\n";

try {
    $pdo = DB::pdo();
    
    // Test with tenant ID 3
    $tenantId = 3;
    
    echo "1. Current database sessions:\n";
    $sessions = $pdo->prepare("SELECT * FROM sessions WHERE tenant_id = ?");
    $sessions->execute([$tenantId]);
    $sessionData = $sessions->fetchAll();
    
    if (empty($sessionData)) {
        echo "   No sessions found\n";
    } else {
        foreach ($sessionData as $session) {
            echo "   - {$session['common_name']} from {$session['real_address']} (Last seen: {$session['last_seen']})\n";
        }
    }
    echo "\n";
    
    echo "2. Testing session refresh:\n";
    OpenVPNManager::refreshSessions($tenantId);
    echo "   ✅ Refresh completed\n\n";
    
    echo "3. Sessions after refresh:\n";
    $sessions->execute([$tenantId]);
    $sessionData = $sessions->fetchAll();
    
    if (empty($sessionData)) {
        echo "   ✅ No sessions found (correctly cleaned up)\n";
    } else {
        foreach ($sessionData as $session) {
            echo "   - {$session['common_name']} from {$session['real_address']} (Last seen: {$session['last_seen']})\n";
        }
    }
    echo "\n";
    
    echo "4. Testing get_live_sessions endpoint (simulating authenticated request):\n";
    
    // Simulate the endpoint logic directly since we can't authenticate via HTTP
    try {
        // Get updated session data (only active sessions from last 2 minutes)
        $stmt = $pdo->prepare("
            SELECT s.*, vu.username, vu.email
            FROM sessions s
            LEFT JOIN vpn_users vu ON s.user_id = vu.id
            WHERE s.tenant_id = ? AND s.last_seen >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
            ORDER BY s.last_seen DESC
        ");
        $stmt->execute([$tenantId]);
        $sessions = $stmt->fetchAll();
        
        // Get tenant statistics
        $stats = [
            'active_users' => count($sessions),
            'total_traffic' => 0,
            'downloaded' => 0,
            'uploaded' => 0
        ];
        
        foreach ($sessions as $session) {
            $stats['total_traffic'] += $session['bytes_received'] + $session['bytes_sent'];
            $stats['downloaded'] += $session['bytes_received'];
            $stats['uploaded'] += $session['bytes_sent'];
        }
        
        echo "   ✅ Endpoint logic working\n";
        echo "   Sessions returned: " . count($sessions) . "\n";
        echo "   Stats: " . json_encode($stats) . "\n";
        
    } catch (\Throwable $e) {
        echo "   ❌ Endpoint logic error: " . $e->getMessage() . "\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
