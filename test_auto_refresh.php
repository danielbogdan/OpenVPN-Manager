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
    
    echo "4. Testing get_live_sessions endpoint:\n";
    $url = "http://localhost/actions/get_live_sessions.php?tenant_id=$tenantId";
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Content-Type: application/json',
            'timeout' => 10
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    if ($response === false) {
        echo "   ❌ Failed to call endpoint\n";
    } else {
        $data = json_decode($response, true);
        if ($data && isset($data['success'])) {
            echo "   ✅ Endpoint working\n";
            echo "   Sessions returned: " . count($data['sessions']) . "\n";
            echo "   Stats: " . json_encode($data['stats']) . "\n";
        } else {
            echo "   ❌ Endpoint returned error: " . $response . "\n";
        }
    }
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
