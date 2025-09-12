<?php
require __DIR__ . '/config.php';

use App\OpenVPNManager;
use App\DB;

echo "Testing session refresh for tenant 2...\n";

try {
    $pdo = DB::pdo();
    
    // Check if tenant exists
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
    echo "Refreshing sessions...\n";
    OpenVPNManager::refreshSessions(2);
    echo "Session refresh completed successfully!\n";
    
    // Check if sessions were created
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE tenant_id = ?");
    $stmt->execute([2]);
    $sessions = $stmt->fetchAll();
    
    echo "Found " . count($sessions) . " sessions:\n";
    foreach ($sessions as $session) {
        echo "- " . $session['common_name'] . " from " . $session['real_address'] . " (IP: " . $session['virtual_address'] . ")\n";
        echo "  Connected since: " . $session['since'] . "\n";
        echo "  Last seen: " . $session['last_seen'] . "\n";
        echo "  Bytes received: " . $session['bytes_received'] . "\n";
        echo "  Bytes sent: " . $session['bytes_sent'] . "\n";
        echo "  Country: " . $session['geo_country'] . "\n";
        echo "  City: " . $session['geo_city'] . "\n";
        echo "\n";
    }
    
    if (count($sessions) === 0) {
        echo "No sessions found. This could mean:\n";
        echo "1. No clients are currently connected\n";
        echo "2. Status file is empty or not being written\n";
        echo "3. There's an issue with the session parsing\n";
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
