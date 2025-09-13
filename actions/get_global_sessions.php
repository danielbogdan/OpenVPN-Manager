<?php
require __DIR__ . '/../config.php';

use App\Auth;
use App\DB;
use App\OpenVPNManager;

// Set JSON header
header('Content-Type: application/json');

try {
    Auth::require();
    
    $pdo = DB::pdo();
    
    // Get all tenants
    $tenants = $pdo->query("SELECT id FROM tenants ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    
    // Refresh sessions for all tenants
    foreach ($tenants as $tenantId) {
        try {
            OpenVPNManager::refreshSessions($tenantId);
        } catch (\Throwable $e) {
            error_log("Failed to refresh sessions for tenant $tenantId: " . $e->getMessage());
        }
    }
    
    // Get all active sessions from all tenants (last 2 minutes)
    $stmt = $pdo->prepare("
        SELECT 
            s.tenant_id,
            s.common_name,
            s.real_address,
            s.virtual_address,
            s.geo_country,
            s.geo_city,
            s.bytes_received,
            s.bytes_sent,
            s.last_seen,
            t.name as tenant_name
        FROM sessions s
        LEFT JOIN tenants t ON s.tenant_id = t.id
        WHERE s.last_seen >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        AND s.geo_country IS NOT NULL
        ORDER BY s.last_seen DESC
    ");
    $stmt->execute();
    $sessions = $stmt->fetchAll();
    
    // Get global statistics
    $stats = [
        'total_connections' => count($sessions),
        'total_tenants' => count($tenants),
        'countries' => [],
        'total_traffic' => 0
    ];
    
    foreach ($sessions as $session) {
        $stats['total_traffic'] += $session['bytes_received'] + $session['bytes_sent'];
        
        $country = $session['geo_country'];
        if (!isset($stats['countries'][$country])) {
            $stats['countries'][$country] = [
                'count' => 0,
                'tenants' => []
            ];
        }
        $stats['countries'][$country]['count']++;
        if (!in_array($session['tenant_id'], $stats['countries'][$country]['tenants'])) {
            $stats['countries'][$country]['tenants'][] = $session['tenant_id'];
        }
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
