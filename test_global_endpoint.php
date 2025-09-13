<?php
require __DIR__ . '/config.php';

use App\Auth;
use App\DB;

echo "=== TESTING GLOBAL SESSIONS ENDPOINT ===\n\n";

try {
    echo "1. Testing Auth::require()...\n";
    Auth::require();
    echo "   ✅ Auth passed\n\n";
    
    echo "2. Testing DB connection...\n";
    $pdo = DB::pdo();
    echo "   ✅ DB connected\n\n";
    
    echo "3. Testing tenant query...\n";
    $tenants = $pdo->query("SELECT id FROM tenants ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    echo "   ✅ Found " . count($tenants) . " tenants: " . implode(', ', $tenants) . "\n\n";
    
    echo "4. Testing sessions query...\n";
    $stmt = $pdo->prepare("
        SELECT 
            s.tenant_id,
            s.common_name,
            s.real_address,
            s.virtual_address,
            s.geo_country,
            s.geo_city,
            s.last_seen
        FROM sessions s
        WHERE s.last_seen >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        AND s.geo_country IS NOT NULL
        ORDER BY s.last_seen DESC
        LIMIT 5
    ");
    $stmt->execute();
    $sessions = $stmt->fetchAll();
    echo "   ✅ Found " . count($sessions) . " active sessions\n";
    
    foreach ($sessions as $session) {
        echo "     - Tenant {$session['tenant_id']}: {$session['common_name']} ({$session['geo_country']})\n";
    }
    
    echo "\n5. Testing JSON output...\n";
    $result = [
        'success' => true,
        'sessions' => $sessions,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    echo "   ✅ JSON: " . json_encode($result) . "\n\n";
    
    echo "✅ All tests passed! The endpoint should work.\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
