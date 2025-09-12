<?php
require __DIR__ . '/config.php';

use App\DockerCLI;

echo "=== TESTING PARSING LOGIC ===\n\n";

// Test the exact lines from the status file
$testLines = [
    'HEADER,CLIENT_LIST,Common Name,Real Address,Virtual Address,Virtual IPv6 Address,Bytes Received,Bytes Sent,Connected Since,Connected Since (time_t),Username,Client ID,Peer ID',
    'CLIENT_LIST,daniel.bogdan,128.127.118.115:60402,10.10.0.6,,7606916,9348751,Fri Sep 12 21:32:40 2025,1757712760,UNDEF,0,0',
    'HEADER,ROUTING_TABLE,Virtual Address,Common Name,Real Address,Last Ref,Last Ref (time_t)',
    'ROUTING_TABLE,10.10.0.6,daniel.bogdan,128.127.118.115:60402,Fri Sep 12 21:40:50 2025,1757713250'
];

$stage = '';
$clients = [];
$routes = [];

foreach ($testLines as $lineNum => $line) {
    $line = trim($line);
    echo "Line " . ($lineNum + 1) . ": '$line'\n";
    
    // Test the current parsing logic
    if (strpos($line, 'HEADER,CLIENT_LIST,Common Name,Real Address,') === 0) { 
        $stage = 'clients'; 
        echo "  -> ✅ Entering clients stage\n";
        continue; 
    }
    if (strpos($line, 'HEADER,ROUTING_TABLE,Virtual Address,Common Name,') === 0) { 
        $stage = 'routes';  
        echo "  -> ✅ Entering routes stage\n";
        continue; 
    }
    
    if ($stage === 'clients' && strpos($line, 'CLIENT_LIST,') === 0) {
        echo "  -> ✅ Processing client line\n";
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
            echo "  -> ✅ Added client: $cn from $real (since: $since)\n";
        } else {
            echo "  -> ❌ Not enough parts for client line\n";
        }
    }
    
    if ($stage === 'routes' && strpos($line, 'ROUTING_TABLE,') === 0) {
        echo "  -> ✅ Processing route line\n";
        $parts = explode(',', $line);
        echo "  -> Parts count: " . count($parts) . "\n";
        if (count($parts) >= 3) {
            $vip = $parts[1];
            $cn = $parts[2];
            $routes[$cn] = $vip;
            echo "  -> ✅ Added route: $cn -> $vip\n";
        } else {
            echo "  -> ❌ Not enough parts for route line\n";
        }
    }
}

echo "\n=== RESULTS ===\n";
echo "Clients found: " . count($clients) . "\n";
foreach ($clients as $cn => $c) {
    echo "- $cn: " . $c['real'] . " (since: " . $c['since'] . ")\n";
}

echo "\nRoutes found: " . count($routes) . "\n";
foreach ($routes as $cn => $vip) {
    echo "- $cn -> $vip\n";
}
