<?php
require __DIR__ . '/config.php';

use App\DockerCLI;
use App\DB;

echo "=== OPENVPN STATUS MONITOR ===\n\n";

$statusFile = '/tmp/openvpn-status.log';

// Get all tenant containers dynamically
$pdo = DB::getInstance();
$tenants = $pdo->query("SELECT id, name FROM tenants ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

if (empty($tenants)) {
    echo "❌ No tenants found in database\n";
    exit(1);
}

echo "Found " . count($tenants) . " tenant(s):\n";
foreach ($tenants as $tenant) {
    echo "  - Tenant {$tenant['id']}: {$tenant['name']} (vpn_tenant_{$tenant['id']})\n";
}
echo "\n";

for ($i = 0; $i < 10; $i++) {
    echo "--- Check " . ($i + 1) . " ---\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($tenants as $tenant) {
        $containerName = "vpn_tenant_{$tenant['id']}";
        echo "🔍 Checking Tenant {$tenant['id']} ({$tenant['name']}) - Container: $containerName\n";
        
        // Get status file content
        $content = DockerCLI::exec($containerName, "test -f $statusFile && cat $statusFile || echo 'FILE_NOT_FOUND'");
        $raw = implode("\n", $content);
        
        if ($raw === 'FILE_NOT_FOUND') {
            echo "   ❌ Status file not found\n";
        } else {
            echo "   ✅ Status file found\n";
            
            // Parse the content
            $lines = explode("\n", $raw);
            $hasClients = false;
            $hasRoutes = false;
            $clientCount = 0;
            $routeCount = 0;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, 'CLIENT_LIST,') === 0) {
                    $hasClients = true;
                    $clientCount++;
                    echo "      📱 Client: $line\n";
                }
                if (strpos($line, 'ROUTING_TABLE,') === 0) {
                    $hasRoutes = true;
                    $routeCount++;
                    echo "      🛣️  Route: $line\n";
                }
            }
            
            if (!$hasClients) {
                echo "      ⚠️  No active clients found\n";
            } else {
                echo "      📊 Total clients: $clientCount\n";
            }
            if (!$hasRoutes) {
                echo "      ⚠️  No active routes found\n";
            } else {
                echo "      📊 Total routes: $routeCount\n";
            }
        }
        echo "\n";
    }
    
    echo "--- End of check " . ($i + 1) . " ---\n\n";
    sleep(2);
}

echo "Monitoring completed.\n";
