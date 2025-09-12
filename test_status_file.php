<?php
require __DIR__ . '/config.php';

use App\DockerCLI;

echo "Testing OpenVPN status file...\n";

try {
    // Check if the status file exists
    echo "Checking if status file exists...\n";
    $result = DockerCLI::exec('vpn_tenant_2', 'test -f /tmp/openvpn-status.log && echo "EXISTS" || echo "NOT_FOUND"');
    echo "Status file check: " . implode("\n", $result) . "\n";
    
    // If it exists, show its contents
    if (in_array('EXISTS', $result)) {
        echo "\nStatus file contents:\n";
        echo "===================\n";
        $content = DockerCLI::exec('vpn_tenant_2', 'cat /tmp/openvpn-status.log');
        echo implode("\n", $content) . "\n";
    } else {
        echo "Status file not found. This might be because:\n";
        echo "1. No clients are connected\n";
        echo "2. OpenVPN server needs to be restarted\n";
        echo "3. Status logging is not properly configured\n";
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
