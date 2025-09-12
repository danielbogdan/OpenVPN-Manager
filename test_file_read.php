<?php
require __DIR__ . '/config.php';

use App\DockerCLI;

echo "=== TESTING FILE READING ===\n\n";

try {
    // Test 1: Check if container exists
    echo "1. Testing container existence...\n";
    $exists = DockerCLI::existsContainer('vpn_tenant_3');
    echo "Container vpn_tenant_3 exists: " . ($exists ? 'YES' : 'NO') . "\n\n";
    
    // Test 2: Test file existence
    echo "2. Testing file existence...\n";
    $out = DockerCLI::exec('vpn_tenant_3', 'test -f /tmp/openvpn-status.log && echo "EXISTS" || echo "NOT_FOUND"');
    echo "File check result: " . implode("\n", $out) . "\n\n";
    
    // Test 3: Test file reading
    echo "3. Testing file reading...\n";
    $out = DockerCLI::exec('vpn_tenant_3', 'cat /tmp/openvpn-status.log');
    $raw = implode("\n", $out);
    echo "File content length: " . strlen($raw) . " characters\n";
    echo "File content preview (first 200 chars):\n";
    echo substr($raw, 0, 200) . "...\n\n";
    
    // Test 4: Test the exact command used in refreshSessions
    echo "4. Testing exact refreshSessions command...\n";
    $out = DockerCLI::exec('vpn_tenant_3', "test -f /tmp/openvpn-status.log && cat /tmp/openvpn-status.log || true");
    $raw = implode("\n", $out);
    echo "Command result length: " . strlen($raw) . " characters\n";
    echo "Is empty: " . (empty($raw) ? 'YES' : 'NO') . "\n";
    
    if (!empty($raw)) {
        echo "First line: " . substr($raw, 0, 100) . "\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
