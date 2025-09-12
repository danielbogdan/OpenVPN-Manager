<?php
require __DIR__ . '/config.php';

use App\OpenVPNManager;
use App\DB;

echo "=== TESTING REFRESH SESSIONS METHOD ===\n\n";

try {
    $pdo = DB::pdo();
    
    // Check sessions before
    echo "1. Sessions before refresh:\n";
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE tenant_id = ?");
    $stmt->execute([3]);
    $sessions = $stmt->fetchAll();
    echo "Found " . count($sessions) . " sessions\n";
    
    // Call refreshSessions
    echo "\n2. Calling refreshSessions(3)...\n";
    OpenVPNManager::refreshSessions(3);
    echo "✅ refreshSessions completed\n";
    
    // Check sessions after
    echo "\n3. Sessions after refresh:\n";
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE tenant_id = ?");
    $stmt->execute([3]);
    $sessions = $stmt->fetchAll();
    echo "Found " . count($sessions) . " sessions\n";
    
    foreach ($sessions as $session) {
        echo "- " . $session['common_name'] . " from " . $session['real_address'] . " (IP: " . $session['virtual_address'] . ")\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
