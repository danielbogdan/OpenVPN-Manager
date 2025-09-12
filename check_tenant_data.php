<?php
require __DIR__ . '/config.php';

use App\DB;

echo "=== CHECKING TENANT DATA ===\n\n";

try {
    $pdo = DB::pdo();
    
    // Check tenant data
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([3]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        echo "❌ Tenant 3 not found!\n";
        exit(1);
    }
    
    echo "Tenant data:\n";
    foreach ($tenant as $key => $value) {
        echo "- $key: " . ($value ?? 'NULL') . "\n";
    }
    
    // Check what status_path is being used
    $statusFile = $tenant['status_path'] ?: '/tmp/openvpn-status.log';
    echo "\nStatus file path being used: $statusFile\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
