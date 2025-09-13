<?php
require __DIR__ . '/config.php';

use App\DB;

echo "=== CHECK ALL CLIENT USERS ===\n\n";

$pdo = DB::pdo();

// Get all tenants
echo "📋 All tenants:\n";
$tenants = $pdo->query("SELECT id, name FROM tenants ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($tenants as $tenant) {
    echo "  - Tenant {$tenant['id']}: {$tenant['name']}\n";
}
echo "\n";

// Get all client users
echo "👥 All client users:\n";
$clientUsers = $pdo->query("
    SELECT 
        cu.id,
        cu.username,
        cu.tenant_id,
        cu.is_active,
        cu.last_activity,
        cu.last_login,
        cu.last_login_ip,
        t.name as tenant_name
    FROM client_users cu
    LEFT JOIN tenants t ON cu.tenant_id = t.id
    ORDER BY cu.tenant_id, cu.id
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($clientUsers)) {
    echo "  ❌ No client users found in database\n";
} else {
    foreach ($clientUsers as $user) {
        $status = $user['is_active'] ? "🟢 ACTIVE" : "🔴 INACTIVE";
        echo "  - User {$user['id']}: {$user['username']} (Tenant {$user['tenant_id']} - {$user['tenant_name']}) $status\n";
        echo "    Last activity: " . ($user['last_activity'] ?: 'Never') . "\n";
        echo "    Last login: " . ($user['last_login'] ?: 'Never') . "\n";
        echo "    Last login IP: " . ($user['last_login_ip'] ?: 'None') . "\n\n";
    }
}

// Test the exact query from get_client_status.php
echo "🔍 Testing get_client_status.php query:\n";
$stmt = $pdo->query("
    SELECT 
        cu.id,
        cu.username,
        cu.last_activity,
        cu.last_login,
        cu.last_login_ip,
        cu.tenant_id,
        t.name as tenant_name
    FROM client_users cu
    JOIN tenants t ON cu.tenant_id = t.id
    WHERE cu.is_active = 1
    ORDER BY cu.tenant_id, cu.id
");

$activeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($activeUsers) . " active client users:\n";
foreach ($activeUsers as $user) {
    echo "  - {$user['username']} (Tenant {$user['tenant_id']} - {$user['tenant_name']})\n";
}

echo "\n🎯 ANALYSIS:\n";
echo "If get_client_status.php only shows tenant 7, it means:\n";
echo "1. Only tenant 7 has active client users (is_active = 1)\n";
echo "2. Other tenants have no client users or inactive client users\n";
echo "3. The auto-update system is working correctly\n";
