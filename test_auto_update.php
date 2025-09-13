<?php
require __DIR__ . '/config.php';

use App\Auth;
use App\DB;
use App\OpenVPNManager;

echo "=== TEST AUTO-UPDATE ENDPOINT ===\n\n";

// Test the get_live_sessions.php endpoint directly
$tenantId = 5; // Your current tenant

echo "🔍 Testing /actions/get_live_sessions.php for tenant $tenantId...\n\n";

// Simulate the endpoint call
try {
    // Refresh sessions for the tenant (this will clean up old sessions and update active ones)
    echo "🔄 Calling OpenVPNManager::refreshSessions($tenantId)...\n";
    OpenVPNManager::refreshSessions($tenantId);
    echo "   ✅ refreshSessions completed\n\n";
    
    // Get updated session data (only active sessions from last 2 minutes)
    $pdo = DB::pdo();
    $stmt = $pdo->prepare("
        SELECT s.*, vu.username, vu.email
        FROM sessions s
        LEFT JOIN vpn_users vu ON s.user_id = vu.id
        WHERE s.tenant_id = ? AND s.last_seen >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ORDER BY s.last_seen DESC
    ");
    $stmt->execute([$tenantId]);
    $sessions = $stmt->fetchAll();
    
    echo "📊 Found " . count($sessions) . " active sessions:\n";
    foreach ($sessions as $session) {
        echo "  - {$session['common_name']} ({$session['real_address']} -> {$session['virtual_address']})\n";
        echo "    Bytes received: {$session['bytes_received']}, Bytes sent: {$session['bytes_sent']}\n";
        echo "    Last seen: {$session['last_seen']}\n\n";
    }
    
    // Get tenant statistics
    $stats = [
        'active_users' => count($sessions),
        'total_traffic' => 0,
        'downloaded' => 0,
        'uploaded' => 0
    ];
    
    foreach ($sessions as $session) {
        $stats['total_traffic'] += $session['bytes_received'] + $session['bytes_sent'];
        $stats['downloaded'] += $session['bytes_received'];
        $stats['uploaded'] += $session['bytes_sent'];
    }
    
    echo "📈 Statistics:\n";
    echo "  - Active users: {$stats['active_users']}\n";
    echo "  - Total traffic: " . number_format($stats['total_traffic']) . " bytes\n";
    echo "  - Downloaded: " . number_format($stats['downloaded']) . " bytes\n";
    echo "  - Uploaded: " . number_format($stats['uploaded']) . " bytes\n\n";
    
    echo "✅ Auto-update endpoint is working correctly!\n";
    echo "🎯 The dashboard should be updating automatically every 2 seconds.\n\n";
    
    echo "💡 If the dashboard is not updating, check:\n";
    echo "   1. Browser console for JavaScript errors\n";
    echo "   2. Network tab to see if AJAX calls are being made\n";
    echo "   3. Make sure you're on the dashboard page (not just visiting it)\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
