<?php
require __DIR__ . '/config.php';

use App\DockerCLI;

echo "=== TESTING OPENVPN STATUS ===\n\n";

// Check if container exists and is running
$containerName = 'vpn_tenant_3';
echo "1. Checking container: $containerName\n";

$status = DockerCLI::run("docker inspect --format='{{.State.Status}}' $containerName 2>/dev/null || echo 'not_found'");
echo "   Container status: " . trim($status[0] ?? 'unknown') . "\n\n";

// Check status file
echo "2. Checking OpenVPN status file:\n";
$statusFile = '/tmp/openvpn-status.log';
$content = DockerCLI::exec($containerName, "test -f $statusFile && cat $statusFile || echo 'FILE_NOT_FOUND'");
$raw = implode("\n", $content);

if ($raw === 'FILE_NOT_FOUND') {
    echo "   ❌ Status file not found\n";
} else {
    echo "   ✅ Status file found\n";
    echo "   Content:\n";
    echo "   " . str_replace("\n", "\n   ", $raw) . "\n";
}

// Check if OpenVPN process is running
echo "\n3. Checking OpenVPN process:\n";
$processes = DockerCLI::exec($containerName, "ps aux | grep openvpn | grep -v grep || echo 'NO_OPENVPN_PROCESS'");
$process = implode("\n", $processes);
if ($process === 'NO_OPENVPN_PROCESS') {
    echo "   ❌ No OpenVPN process found\n";
} else {
    echo "   ✅ OpenVPN process found:\n";
    echo "   " . str_replace("\n", "\n   ", $process) . "\n";
}

// Check network interfaces
echo "\n4. Checking network interfaces:\n";
$interfaces = DockerCLI::exec($containerName, "ip addr show | grep -E '(tun|tap)' || echo 'NO_TUN_INTERFACES'");
$iface = implode("\n", $interfaces);
if ($iface === 'NO_TUN_INTERFACES') {
    echo "   ❌ No TUN/TAP interfaces found\n";
} else {
    echo "   ✅ TUN/TAP interfaces found:\n";
    echo "   " . str_replace("\n", "\n   ", $iface) . "\n";
}
