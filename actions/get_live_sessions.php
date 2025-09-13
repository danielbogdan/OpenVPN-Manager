<?php
require __DIR__ . '/../config.php';

use App\Auth;
use App\DB;
use App\OpenVPNManager;

// Set JSON header
header('Content-Type: application/json');

try {
    Auth::require();
    
    $tenantId = (int)($_GET['tenant_id'] ?? 0);
    if (!$tenantId) {
        throw new \InvalidArgumentException('Tenant ID required');
    }
    
    // Get session data (only active sessions from last 2 minutes)
    // Note: We don't call refreshSessions here as it can cause data loss
    // Sessions are refreshed by the background monitor script
    $pdo = DB::pdo();
    $stmt = $pdo->prepare("
        SELECT s.*, vu.username, vu.email
        FROM sessions s
        LEFT JOIN vpn_users vu ON s.user_id = vu.id
        WHERE s.tenant_id = ? AND s.last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY s.last_seen DESC
    ");
    $stmt->execute([$tenantId]);
    $sessions = $stmt->fetchAll();
    
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
    
    echo json_encode([
        'success' => true,
        'sessions' => $sessions,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
