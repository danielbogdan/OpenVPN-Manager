<?php
require __DIR__ . '/config.php';

use App\DockerCLI;

echo "=== OPENVPN STATUS MONITOR ===\n\n";

$containerName = 'vpn_tenant_3';
$statusFile = '/tmp/openvpn-status.log';

echo "Monitoring OpenVPN status file: $statusFile\n";
echo "Container: $containerName\n\n";

for ($i = 0; $i < 10; $i++) {
    echo "--- Check " . ($i + 1) . " ---\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n";
    
    // Get status file content
    $content = DockerCLI::exec($containerName, "test -f $statusFile && cat $statusFile || echo 'FILE_NOT_FOUND'");
    $raw = implode("\n", $content);
    
    if ($raw === 'FILE_NOT_FOUND') {
        echo "❌ Status file not found\n";
    } else {
        echo "✅ Status file found\n";
        
        // Parse the content
        $lines = explode("\n", $raw);
        $hasClients = false;
        $hasRoutes = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'CLIENT_LIST,') === 0) {
                $hasClients = true;
                echo "   📱 Client: $line\n";
            }
            if (strpos($line, 'ROUTING_TABLE,') === 0) {
                $hasRoutes = true;
                echo "   🛣️  Route: $line\n";
            }
        }
        
        if (!$hasClients) {
            echo "   ⚠️  No active clients found\n";
        }
        if (!$hasRoutes) {
            echo "   ⚠️  No active routes found\n";
        }
    }
    
    echo "\n";
    sleep(2);
}

echo "Monitoring completed.\n";
