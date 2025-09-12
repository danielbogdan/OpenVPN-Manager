<?php
require __DIR__ . '/config.php';

use App\DB;

echo "=== TESTING DATABASE INSERTION ===\n\n";

try {
    $pdo = DB::pdo();
    
    // Test direct insertion
    echo "1. Testing direct session insertion...\n";
    
    $stmt = $pdo->prepare(
        "INSERT INTO sessions(tenant_id,user_id,common_name,real_address,virtual_address,
         bytes_received,bytes_sent,since,geo_country,geo_city,last_seen)
         VALUES (?,?,?,?,?,?,?,?,?,?,NOW())"
    );
    
    $result = $stmt->execute([
        3,                          // tenant_id
        2,                          // user_id (daniel.bogdan)
        'daniel.bogdan',            // common_name
        '128.127.118.115:60402',    // real_address
        '10.10.0.6',               // virtual_address
        13443514,                  // bytes_received
        17046650,                  // bytes_sent
        '2025-09-12 21:32:40',     // since
        'Romania',                 // geo_country
        'Bucharest',               // geo_city
    ]);
    
    if ($result) {
        echo "✅ Direct insertion successful\n";
    } else {
        echo "❌ Direct insertion failed\n";
    }
    
    // Check if session was inserted
    echo "\n2. Checking sessions in database...\n";
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE tenant_id = ?");
    $stmt->execute([3]);
    $sessions = $stmt->fetchAll();
    
    echo "Found " . count($sessions) . " sessions:\n";
    foreach ($sessions as $session) {
        echo "- " . $session['common_name'] . " from " . $session['real_address'] . " (IP: " . $session['virtual_address'] . ")\n";
    }
    
    // Check table structure
    echo "\n3. Checking sessions table structure...\n";
    $stmt = $pdo->query("DESCRIBE sessions");
    $columns = $stmt->fetchAll();
    
    echo "Sessions table columns:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
