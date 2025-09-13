<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use App\Analytics;
use App\OpenVPNManager;

// Debug traffic data for tenant 7
$tenantId = 7;

echo "=== DEBUGGING TRAFFIC DATA FOR TENANT $tenantId ===\n\n";

// Get tenant info
$tenant = OpenVPNManager::getTenant($tenantId);
if (!$tenant) {
    echo "ERROR: Tenant not found\n";
    exit(1);
}

echo "Tenant: " . $tenant['name'] . "\n";
echo "Container: " . $tenant['docker_container'] . "\n\n";

// Test container traffic data
$containerName = $tenant['docker_container'];
if ($containerName) {
    echo "=== TESTING CONTAINER TRAFFIC ===\n";
    
    // Test /proc/net/dev
    $cmd = "docker exec {$containerName} cat /proc/net/dev 2>/dev/null";
    $output = shell_exec($cmd);
    echo "Container /proc/net/dev output:\n";
    echo $output . "\n";
    
    // Test netstat
    $cmd = "docker exec {$containerName} netstat -an 2>/dev/null | grep ESTABLISHED | head -10";
    $output = shell_exec($cmd);
    echo "Container netstat ESTABLISHED connections:\n";
    echo $output . "\n";
}

// Test analytics data
echo "=== TESTING ANALYTICS DATA ===\n";
try {
    $data = Analytics::getDashboardData($tenantId, 72);
    
    echo "Application Breakdown:\n";
    foreach ($data['application_breakdown'] as $app) {
        echo "- " . $app['application_type'] . ": " . number_format($app['total_bytes'] / 1024 / 1024, 2) . " MB\n";
    }
    
    echo "\nTop Destinations:\n";
    $totalDestinations = 0;
    foreach ($data['top_destinations'] as $dest) {
        $mb = number_format($dest['total_bytes'] / 1024 / 1024, 2);
        echo "- " . $dest['domain'] . " (" . $dest['application_type'] . "): " . $mb . " MB (" . $dest['connection_count'] . " connections)\n";
        $totalDestinations += $dest['total_bytes'];
    }
    
    echo "\nSummary:\n";
    echo "Total Application Traffic: " . number_format($data['summary']['total_traffic'] / 1024 / 1024, 2) . " MB\n";
    echo "Total Destinations Traffic: " . number_format($totalDestinations / 1024 / 1024, 2) . " MB\n";
    echo "Difference: " . number_format(($data['summary']['total_traffic'] - $totalDestinations) / 1024 / 1024, 2) . " MB\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
