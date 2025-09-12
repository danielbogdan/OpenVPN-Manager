<?php
require __DIR__ . '/config.php';

use App\DockerCLI;

echo "Adding status logging to existing tenant 2...\n";

try {
    // Add status logging to the existing OpenVPN configuration
    $commands = [
        'echo "status /etc/openvpn/openvpn-status.log" >> /etc/openvpn/openvpn.conf',
        'echo "status-version 2" >> /etc/openvpn/openvpn.conf'
    ];
    
    foreach ($commands as $cmd) {
        echo "Running: $cmd\n";
        $result = DockerCLI::exec('vpn_tenant_2', $cmd);
        echo "Result: " . implode("\n", $result) . "\n";
    }
    
    echo "Status logging added successfully!\n";
    echo "You may need to restart the OpenVPN container for the changes to take effect.\n";
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
