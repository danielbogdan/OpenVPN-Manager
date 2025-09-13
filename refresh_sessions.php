<?php
require __DIR__ . '/config.php';

use App\DB;
use App\OpenVPNManager;

echo "=== REFRESH SESSIONS FOR ALL TENANTS ===\n\n";

// Get all tenant containers dynamically
$pdo = DB::pdo();
$tenants = $pdo->query("SELECT id, name FROM tenants ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

if (empty($tenants)) {
    echo "❌ No tenants found in database\n";
    exit(1);
}

echo "Found " . count($tenants) . " tenant(s):\n";
foreach ($tenants as $tenant) {
    echo "  - Tenant {$tenant['id']}: {$tenant['name']}\n";
}
echo "\n";

foreach ($tenants as $tenant) {
    echo "🔄 Refreshing sessions for Tenant {$tenant['id']} ({$tenant['name']})...\n";
    
    try {
        OpenVPNManager::refreshSessions($tenant['id']);
        echo "   ✅ Sessions refreshed successfully\n";
        
        // Check how many sessions were updated
        $sessionCount = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE tenant_id = ? AND last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $sessionCount->execute([$tenant['id']]);
        $count = $sessionCount->fetchColumn();
        echo "   📊 Active sessions in database: $count\n";
        
    } catch (\Throwable $e) {
        echo "   ❌ Error refreshing sessions: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "=== SESSION REFRESH COMPLETED ===\n";

// Show final session summary
echo "\n📊 FINAL SESSION SUMMARY:\n";
$allSessions = $pdo->query("
    SELECT 
        t.id as tenant_id,
        t.name as tenant_name,
        COUNT(s.id) as session_count,
        GROUP_CONCAT(DISTINCT s.common_name) as users
    FROM tenants t
    LEFT JOIN sessions s ON t.id = s.tenant_id AND s.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    GROUP BY t.id, t.name
    ORDER BY t.id
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($allSessions as $row) {
    echo "  - Tenant {$row['tenant_id']} ({$row['tenant_name']}): {$row['session_count']} active sessions";
    if ($row['users']) {
        echo " - Users: {$row['users']}";
    }
    echo "\n";
}
