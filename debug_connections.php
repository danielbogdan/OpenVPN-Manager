<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use App\OpenVPNManager;

// Debug connections for tenant 7
$tenantId = 7;

echo "=== DEBUGGING CONNECTIONS FOR TENANT $tenantId ===\n\n";

// Get tenant info
$tenant = OpenVPNManager::getTenant($tenantId);
if (!$tenant) {
    echo "ERROR: Tenant not found\n";
    exit(1);
}

echo "Tenant: " . $tenant['name'] . "\n";
echo "Container: " . $tenant['docker_container'] . "\n\n";

$containerName = $tenant['docker_container'];
if ($containerName) {
    echo "=== TESTING CONTAINER CONNECTIONS ===\n";
    
    // Test if container is running
    $cmd = "docker ps --filter name={$containerName} --format '{{.Names}}'";
    $output = shell_exec($cmd);
    echo "Container running: " . (trim($output) === $containerName ? "YES" : "NO") . "\n";
    
    // Test netstat command
    $cmd = "docker exec {$containerName} netstat -an 2>/dev/null";
    $output = shell_exec($cmd);
    echo "Netstat output (first 10 lines):\n";
    $lines = explode("\n", $output);
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        echo "  " . $lines[$i] . "\n";
    }
    
    // Test ESTABLISHED connections specifically
    $cmd = "docker exec {$containerName} netstat -an 2>/dev/null | grep ESTABLISHED";
    $output = shell_exec($cmd);
    echo "\nESTABLISHED connections:\n";
    if (empty(trim($output))) {
        echo "  No ESTABLISHED connections found\n";
    } else {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            echo "  " . $line . "\n";
        }
    }
    
    // Test /proc/net/dev
    $cmd = "docker exec {$containerName} cat /proc/net/dev 2>/dev/null";
    $output = shell_exec($cmd);
    echo "\n/proc/net/dev output:\n";
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        if (strpos($line, 'tun0') !== false || strpos($line, 'eth0') !== false) {
            echo "  " . $line . "\n";
        }
    }
    
    // Test if we can execute commands in container
    $cmd = "docker exec {$containerName} ls /proc/net/ 2>/dev/null";
    $output = shell_exec($cmd);
    echo "\nAvailable /proc/net files:\n";
    $lines = explode("\n", trim($output));
    foreach ($lines as $line) {
        if (!empty($line)) {
            echo "  " . $line . "\n";
        }
    }
}

echo "\n=== DEBUG COMPLETE ===\n";
