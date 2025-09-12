<?php
require __DIR__ . '/config.php';

use App\DB;

try {
    $pdo = DB::pdo();
    
    echo "Adding unique key to sessions table...\n";
    
    // Add unique key for tenant_id + common_name to enable UPSERT
    $pdo->exec("ALTER TABLE sessions ADD UNIQUE KEY unique_tenant_user (tenant_id, common_name)");
    
    echo "✅ Unique key added successfully!\n";
    echo "Sessions table now supports UPSERT operations.\n";
    
} catch (\Throwable $e) {
    if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
        echo "✅ Unique key already exists!\n";
    } else {
        echo "❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
