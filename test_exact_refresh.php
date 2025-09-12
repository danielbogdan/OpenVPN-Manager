<?php
require __DIR__ . '/config.php';

use App\OpenVPNManager;
use App\DB;
use App\DockerCLI;

echo "=== TESTING EXACT REFRESH SESSIONS LOGIC ===\n\n";

try {
    $pdo = DB::pdo();
    $tenantId = 3;
    
    // Get tenant (exact same as refreshSessions)
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        echo "❌ Tenant not found!\n";
        exit(1);
    }
    
    echo "✅ Tenant found: " . $tenant['name'] . "\n";
    echo "   Container: " . $tenant['docker_container'] . "\n\n";
    
    // Get status file (exact same as refreshSessions)
    $statusFile = '/tmp/openvpn-status.log';
    $out = DockerCLI::exec($tenant['docker_container'], "test -f " . escapeshellarg($statusFile) . " && cat " . escapeshellarg($statusFile) . " || true");
    $raw = implode("\n", $out);
    
    echo "Status file content length: " . strlen($raw) . " characters\n";
    echo "Is empty: " . (empty($raw) ? 'YES' : 'NO') . "\n\n";
    
    if (empty($raw)) {
        echo "❌ Status file is empty, stopping here\n";
        exit(1);
    }
    
    // Delete existing sessions (exact same as refreshSessions)
    echo "Deleting existing sessions...\n";
    $pdo->prepare("DELETE FROM sessions WHERE tenant_id=?")->execute([$tenantId]);
    
    // Parse the file (exact same logic as refreshSessions)
    $lines = explode("\n", $raw);
    $stage = '';
    $clients = [];
    $routes = [];
    
    echo "Parsing " . count($lines) . " lines...\n";
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        
        if (strpos($line, 'HEADER,CLIENT_LIST,Common Name,Real Address,') === 0) { 
            $stage = 'clients'; 
            echo "Line " . ($lineNum + 1) . ": Entering clients stage\n";
            continue; 
        }
        if (strpos($line, 'HEADER,ROUTING_TABLE,Virtual Address,Common Name,') === 0) { 
            $stage = 'routes';  
            echo "Line " . ($lineNum + 1) . ": Entering routes stage\n";
            continue; 
        }
        if (strpos($line, 'GLOBAL_STATS,') === 0 || $line === 'END') { 
            $stage = ''; 
            echo "Line " . ($lineNum + 1) . ": Exiting stage\n";
            continue; 
        }
        
        if ($stage === 'clients' && strpos($line, 'CLIENT_LIST,') === 0) {
            echo "Line " . ($lineNum + 1) . ": Processing client line\n";
            $parts = explode(',', $line);
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
                echo "  -> Added client: $cn from $real\n";
            }
        }
        
        if ($stage === 'routes' && strpos($line, 'ROUTING_TABLE,') === 0) {
            echo "Line " . ($lineNum + 1) . ": Processing route line\n";
            $parts = explode(',', $line);
            if (count($parts) >= 3) {
                $vip = $parts[1];
                $cn = $parts[2];
                $routes[$cn] = $vip;
                echo "  -> Added route: $cn -> $vip\n";
            }
        }
    }
    
    echo "\nParsing results:\n";
    echo "Clients found: " . count($clients) . "\n";
    echo "Routes found: " . count($routes) . "\n\n";
    
    // Insert sessions (exact same logic as refreshSessions)
    echo "Inserting sessions...\n";
    foreach ($clients as $cn => $c) {
        [$ip] = explode(':', $c['real'], 2);
        [$country, $city] = \App\GeoIP::lookup($ip);
        
        $vip = $routes[$cn] ?? null;
        $since = $c['since'] ? date('Y-m-d H:i:s', $c['since']) : null;
        
        // Get user_id for this common_name
        $userStmt = $pdo->prepare("SELECT id FROM vpn_users WHERE tenant_id = ? AND username = ?");
        $userStmt->execute([$tenantId, $cn]);
        $user = $userStmt->fetch();
        $userId = $user ? $user['id'] : null;
        
        echo "Inserting session for $cn:\n";
        echo "  - user_id: $userId\n";
        echo "  - real_address: " . $c['real'] . "\n";
        echo "  - virtual_address: $vip\n";
        echo "  - country: $country, city: $city\n";
        
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO sessions(tenant_id,user_id,common_name,real_address,virtual_address,
                 bytes_received,bytes_sent,since,geo_country,geo_city,last_seen)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW())"
            );
            $result = $stmt->execute([$tenantId, $userId, $cn, $c['real'], $vip, $c['br'], $c['bs'], $since, $country, $city]);
            echo "  -> Insert result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
        } catch (\Throwable $e) {
            echo "  -> Insert error: " . $e->getMessage() . "\n";
        }
    }
    
    // Check final sessions
    echo "\nFinal sessions check:\n";
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $sessions = $stmt->fetchAll();
    echo "Found " . count($sessions) . " sessions in database\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
