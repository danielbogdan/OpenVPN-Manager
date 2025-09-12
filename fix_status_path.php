<?php
require __DIR__ . '/config.php';

use App\DB;

echo "=== FIXING STATUS PATH ===\n\n";

try {
    $pdo = DB::pdo();
    
    // Update the status_path for tenant 3
    echo "Updating status_path for tenant 3...\n";
    $stmt = $pdo->prepare("UPDATE tenants SET status_path = ? WHERE id = ?");
    $result = $stmt->execute(['/tmp/openvpn-status.log', 3]);
    
    if ($result) {
        echo "✅ Status path updated successfully\n";
    } else {
        echo "❌ Failed to update status path\n";
    }
    
    // Verify the update
    echo "\nVerifying update...\n";
    $stmt = $pdo->prepare("SELECT status_path FROM tenants WHERE id = ?");
    $stmt->execute([3]);
    $statusPath = $stmt->fetchColumn();
    
    echo "New status_path: $statusPath\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
