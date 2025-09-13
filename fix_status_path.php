<?php
require __DIR__ . '/config.php';

use App\DB;

echo "=== FIX STATUS PATH FOR ALL TENANTS ===\n\n";

$pdo = DB::pdo();

// Get all tenants
$tenants = $pdo->query("SELECT id, name, status_path FROM tenants ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($tenants) . " tenant(s):\n\n";

foreach ($tenants as $tenant) {
    echo "🔍 Tenant {$tenant['id']} ({$tenant['name']}):\n";
    echo "   Current status_path: " . ($tenant['status_path'] ?: 'NULL') . "\n";
    
    // Update to the correct path
    $correctPath = '/tmp/openvpn-status.log';
    $stmt = $pdo->prepare("UPDATE tenants SET status_path = ? WHERE id = ?");
    $stmt->execute([$correctPath, $tenant['id']]);
    
    echo "   ✅ Updated to: $correctPath\n\n";
}

echo "=== STATUS PATH FIX COMPLETED ===\n\n";

// Verify the changes
echo "📋 Verification - Current tenant status paths:\n";
$updatedTenants = $pdo->query("SELECT id, name, status_path FROM tenants ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($updatedTenants as $tenant) {
    echo "  - Tenant {$tenant['id']} ({$tenant['name']}): {$tenant['status_path']}\n";
}

echo "\n🎯 Now run the test again to see if sessions are populated!\n";
echo "Command: docker exec ovpnadmin_web php /var/www/html/test_refresh.php\n";