<?php
require __DIR__ . '/config.php';

use App\OpenVPNManager;
use App\DB;
use App\DockerCLI;

echo "=== FULL DEBUG SESSION REFRESH ===\n\n";

try {
    $pdo = DB::pdo();
    
    // Get tenant
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([3]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        echo "❌ Tenant 3 not found!\n";
        exit(1);
    }
    
    echo "✅ Tenant found: " . $tenant['name'] . "\n";
    echo "   Container: " . $tenant['docker_container'] . "\n\n";
    
    // Get status file content
    $statusFile = '/tmp/openvpn-status.log';
    $out = DockerCLI::exec($tenant['docker_container'], "test -f " . escapeshellarg($statusFile) . " && cat " . escapeshellarg($statusFile) . " || echo 'FILE_NOT_FOUND'");
    $raw = implode("\n", $out);
    
    if ($raw === 'FILE_NOT_FOUND') {
        echo "❌ Status file not found!\n";
        exit(1);
    }
    
    echo "✅ Status file content:\n";
    echo "---\n" . $raw . "\n---\n\n";
    
    // Parse manually step by step
    $lines = explode("\n", $raw);
    $stage = '';
    $clients = [];
    $routes = [];
    
    echo "=== PARSING ===\n";
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        echo "Line " . ($lineNum + 1) . ": '$line'\n";
        
        if (strpos($line, 'HEADER,CLIENT_LIST,Common Name,Real Address,') === 0) { 
            $stage = 'clients'; 
            echo "  -> Entering clients stage\n";
            continue; 
        }
        if (strpos($line, 'HEADER,ROUTING_TABLE,Virtual Address,Common Name,') === 0) { 
            $stage = 'routes';  
            echo "  -> Entering routes stage\n";
            continue; 
        }
        if (strpos($line, 'GLOBAL_STATS,') === 0 || $line === 'END') { 
            $stage = ''; 
            echo "  -> Exiting stage\n";
            continue; 
        }
        
        if ($stage === 'clients' && strpos($line, 'CLIENT_LIST,') === 0) {
            echo "  -> Processing client line\n";
            $parts = explode(',', $line);
            echo "  -> Parts count: " . count($parts) . "\n";
            if (count($parts) >= 8) {
                $cn = $parts[1];
                $real = $parts[2];
                $br = (int)$parts[5];
                $bs = (int)$parts[6];
                $since = $parts[7];
                $clients[$cn] = [
                    'real' => $real,
                    'br' => $br,
                    'bs' => $bs,
                    'since' => strtotime($since) ?: null
                ];
                echo "  -> Added client: $cn from $real (since: $since)\n";
            } else {
                echo "  -> Not enough parts for client line\n";
            }
        }
        
        if ($stage === 'routes' && strpos($line, 'ROUTING_TABLE,') === 0) {
            echo "  -> Processing route line\n";
            $parts = explode(',', $line);
            echo "  -> Parts count: " . count($parts) . "\n";
            if (count($parts) >= 3) {
                $vip = $parts[1];
                $cn = $parts[2];
                $routes[$cn] = $vip;
                echo "  -> Added route: $cn -> $vip\n";
            } else {
                echo "  -> Not enough parts for route line\n";
            }
        }
    }
    
    echo "\n=== PARSING RESULTS ===\n";
    echo "Clients found: " . count($clients) . "\n";
    foreach ($clients as $cn => $c) {
        echo "- $cn: " . $c['real'] . " (since: " . $c['since'] . ")\n";
    }
    
    echo "\nRoutes found: " . count($routes) . "\n";
    foreach ($routes as $cn => $vip) {
        echo "- $cn -> $vip\n";
    }
    
    // Check if VPN user exists
    echo "\n=== VPN USER CHECK ===\n";
    foreach ($clients as $cn => $c) {
        $userStmt = $pdo->prepare("SELECT id FROM vpn_users WHERE tenant_id = ? AND username = ?");
        $userStmt->execute([3, $cn]);
        $user = $userStmt->fetch();
        $userId = $user ? $user['id'] : null;
        
        if ($userId) {
            echo "✅ VPN user found for $cn (ID: $userId)\n";
        } else {
            echo "❌ VPN user NOT found for $cn\n";
        }
    }
    
    // Try to insert sessions manually
    echo "\n=== MANUAL SESSION INSERTION ===\n";
    foreach ($clients as $cn => $c) {
        [$ip] = explode(':', $c['real'], 2);
        [$country, $city] = \App\GeoIP::lookup($ip);
        
        $vip = $routes[$cn] ?? null;
        $since = $c['since'] ? date('Y-m-d H:i:s', $c['since']) : null;
        
        // Get user_id for this common_name
        $userStmt = $pdo->prepare("SELECT id FROM vpn_users WHERE tenant_id = ? AND username = ?");
        $userStmt->execute([3, $cn]);
        $user = $userStmt->fetch();
        $userId = $user ? $user['id'] : null;
        
        echo "Inserting session for $cn:\n";
        echo "  - tenant_id: 3\n";
        echo "  - user_id: $userId\n";
        echo "  - common_name: $cn\n";
        echo "  - real_address: " . $c['real'] . "\n";
        echo "  - virtual_address: $vip\n";
        echo "  - bytes_received: " . $c['br'] . "\n";
        echo "  - bytes_sent: " . $c['bs'] . "\n";
        echo "  - since: $since\n";
        echo "  - country: $country\n";
        echo "  - city: $city\n";
        
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO sessions(tenant_id,user_id,common_name,real_address,virtual_address,
                 bytes_received,bytes_sent,since,geo_country,geo_city,last_seen)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW())"
            );
            $stmt->execute([3, $userId, $cn, $c['real'], $vip, $c['br'], $c['bs'], $since, $country, $city]);
            echo "  -> ✅ Inserted successfully\n";
        } catch (\Throwable $e) {
            echo "  -> ❌ Insert failed: " . $e->getMessage() . "\n";
        }
    }
    
    // Check sessions in database
    echo "\n=== FINAL SESSIONS CHECK ===\n";
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE tenant_id = ?");
    $stmt->execute([3]);
    $sessions = $stmt->fetchAll();
    
    echo "Found " . count($sessions) . " sessions in database:\n";
    foreach ($sessions as $session) {
        echo "- " . $session['common_name'] . " from " . $session['real_address'] . " (IP: " . $session['virtual_address'] . ")\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
