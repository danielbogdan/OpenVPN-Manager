<?php
require __DIR__ . '/config.php';

use App\Auth;
use App\DB;

// Start session and authenticate
session_start();
Auth::require();

$pdo = DB::pdo();

// Get tenant summary information (same as dashboard)
$tenantStats = $pdo->query("
  SELECT 
    t.id,
    t.name,
    COUNT(DISTINCT vu.id) as vpn_users,
    COUNT(DISTINCT cu.id) as client_users,
    COUNT(DISTINCT s.id) as active_sessions
  FROM tenants t
  LEFT JOIN vpn_users vu ON t.id = vu.tenant_id
  LEFT JOIN client_users cu ON t.id = cu.tenant_id
  LEFT JOIN sessions s ON t.id = s.tenant_id AND s.last_seen >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
  GROUP BY t.id, t.name
  ORDER BY t.name
")->fetchAll();

echo "=== DASHBOARD HTML STRUCTURE CHECK ===\n\n";

echo "Tenant Stats:\n";
foreach ($tenantStats as $tenant) {
    echo "- Tenant ID: {$tenant['id']}, Name: {$tenant['name']}\n";
    echo "  VPN Users: {$tenant['vpn_users']}, Client Users: {$tenant['client_users']}, Active Sessions: {$tenant['active_sessions']}\n";
}

echo "\nExpected HTML structure:\n";
foreach ($tenantStats as $tenant) {
    echo "<div class=\"tenant-overview-item\" data-tenant-id=\"{$tenant['id']}\">\n";
    echo "  <div class=\"tenant-overview-info\">\n";
    echo "    <div class=\"tenant-overview-name\">{$tenant['name']}</div>\n";
    echo "    <div class=\"tenant-overview-stats\">\n";
    echo "      <span class=\"tenant-stat\">👥 VPN Profile Users: {$tenant['vpn_users']}</span>\n";
    echo "      <span class=\"tenant-stat\">👨‍💼 Portal Client Users: {$tenant['client_users']}</span>\n";
    echo "      <span class=\"tenant-stat\">🟢 Active Sessions: <span class=\"session-count\">{$tenant['active_sessions']}</span></span>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "</div>\n";
}

echo "\nKey attributes to look for:\n";
echo "- data-tenant-id=\"{$tenantStats[0]['id']}\" (on tenant-overview-item)\n";
echo "- class=\"session-count\" (on the active sessions span)\n";
