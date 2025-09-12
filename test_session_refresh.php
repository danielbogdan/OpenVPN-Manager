<?php
require __DIR__ . '/config.php';

use App\OpenVPNManager;
use App\DB;

echo "Testing session refresh for tenant 2...\n";

try {
    // First, let's check if the status file exists
    $pdo = DB::pdo();
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([2]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        echo "Tenant 2 not found!\n";
        exit(1);
    }
    
    echo "Tenant found: " . $tenant['name'] . "\n";
    echo "Container: " . $tenant['docker_container'] . "\n";
    
    // Try to refresh sessions
    OpenVPNManager::refreshSessions(2);
    echo "Session refresh completed successfully!\n";
    
    // Check if sessions were created
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE tenant_id = ?");
    $stmt->execute([2]);
    $sessions = $stmt->fetchAll();
    
    echo "Found " . count($sessions) . " sessions:\n";
    foreach ($sessions as $session) {
        echo "- " . $session['common_name'] . " from " . $session['real_address'] . " (IP: " . $session['virtual_address'] . ")\n";
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
