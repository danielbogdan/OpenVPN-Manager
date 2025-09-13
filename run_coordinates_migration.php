<?php
require __DIR__ . '/config.php';

use App\DB;

echo "=== ADDING COORDINATES TO SESSIONS TABLE ===\n\n";

try {
    $pdo = DB::pdo();
    
    echo "1. Adding geo_lat and geo_lon columns...\n";
    $pdo->exec("ALTER TABLE sessions 
                ADD COLUMN geo_lat DECIMAL(10, 8) DEFAULT NULL,
                ADD COLUMN geo_lon DECIMAL(11, 8) DEFAULT NULL");
    echo "   ✅ Columns added successfully\n\n";
    
    echo "2. Adding coordinate index...\n";
    $pdo->exec("CREATE INDEX idx_geo_coords ON sessions (geo_lat, geo_lon)");
    echo "   ✅ Index created successfully\n\n";
    
    echo "3. Verifying table structure...\n";
    $stmt = $pdo->query("DESCRIBE sessions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('geo_lat', $columns) && in_array('geo_lon', $columns)) {
        echo "   ✅ geo_lat and geo_lon columns confirmed\n\n";
    } else {
        echo "   ❌ Columns not found\n\n";
        exit(1);
    }
    
    echo "4. Checking existing sessions...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM sessions");
    $count = $stmt->fetchColumn();
    echo "   📊 Found {$count} existing sessions\n\n";
    
    echo "✅ Migration completed successfully!\n";
    echo "🎯 Next steps:\n";
    echo "   - The system will now collect precise coordinates for new sessions\n";
    echo "   - Existing sessions will get coordinates on their next update\n";
    echo "   - The Global Connections map will show exact city locations\n\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
