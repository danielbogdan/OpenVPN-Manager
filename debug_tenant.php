<?php
require __DIR__ . '/config.php';

use App\DB;

echo "=== DEBUGGING TENANT DATA ===\n\n";

try {
    $pdo = DB::pdo();
    
    // Check all tenants
    echo "1. Checking all tenants...\n";
    $stmt = $pdo->query("SELECT * FROM tenants ORDER BY id");
    $tenants = $stmt->fetchAll();
    
    echo "Found " . count($tenants) . " tenants:\n";
    foreach ($tenants as $tenant) {
        echo "- ID: " . $tenant['id'] . ", Name: " . $tenant['name'] . ", Container: " . $tenant['docker_container'] . "\n";
    }
    
    // Check specific tenant
    echo "\n2. Checking tenant ID 2 specifically...\n";
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([2]);
    $tenant = $stmt->fetch();
    
    if ($tenant) {
        echo "✅ Tenant 2 found:\n";
        echo "   ID: " . $tenant['id'] . "\n";
        echo "   Name: " . $tenant['name'] . "\n";
        echo "   Container: " . $tenant['docker_container'] . "\n";
        echo "   Volume: " . $tenant['docker_volume'] . "\n";
        echo "   Network: " . $tenant['docker_network'] . "\n";
        echo "   Status: " . $tenant['status'] . "\n";
    } else {
        echo "❌ Tenant 2 not found!\n";
    }
    
    // Check if container exists
    if ($tenant && $tenant['docker_container']) {
        echo "\n3. Checking if container exists...\n";
        $result = shell_exec("docker ps --filter name=" . escapeshellarg($tenant['docker_container']) . " --format '{{.Names}}'");
        if (trim($result)) {
            echo "✅ Container " . $tenant['docker_container'] . " is running\n";
        } else {
            echo "❌ Container " . $tenant['docker_container'] . " is not running\n";
        }
    }
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
