<?php
require __DIR__ . '/../config.php';

use App\DB;
use App\OpenVPNManager;

// Set JSON header for API responses
header('Content-Type: application/json');

try {
    $pdo = DB::pdo();
    
    // Get all active tenants
    $tenants = $pdo->query("SELECT id, name, docker_container FROM tenants WHERE status = 'active'")->fetchAll();
    
    $cleanedSessions = 0;
    $refreshedTenants = 0;
    
    foreach ($tenants as $tenant) {
        try {
            // Refresh sessions for this tenant (this will clean up old sessions)
            OpenVPNManager::refreshSessions($tenant['id']);
            $refreshedTenants++;
            
            // Count cleaned sessions
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sessions WHERE tenant_id = ?");
            $stmt->execute([$tenant['id']]);
            $sessionCount = $stmt->fetch()['count'];
            
            echo "Tenant {$tenant['name']} (ID: {$tenant['id']}): {$sessionCount} active sessions\n";
            
        } catch (\Throwable $e) {
            error_log("Failed to refresh sessions for tenant {$tenant['id']}: " . $e->getMessage());
        }
    }
    
    // Clean up sessions older than 5 minutes (extra cleanup)
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE last_seen < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stmt->execute();
    $cleanedSessions = $stmt->rowCount();
    
    $response = [
        'success' => true,
        'message' => "Session cleanup completed",
        'refreshed_tenants' => $refreshedTenants,
        'cleaned_sessions' => $cleanedSessions,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // If called via AJAX, return JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode($response);
    } else {
        // If called via CLI or direct access, show human-readable output
        echo "=== SESSION CLEANUP COMPLETED ===\n";
        echo "Refreshed tenants: {$response['refreshed_tenants']}\n";
        echo "Cleaned old sessions: {$response['cleaned_sessions']}\n";
        echo "Timestamp: {$response['timestamp']}\n";
    }
    
} catch (\Throwable $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        http_response_code(500);
        echo json_encode($response);
    } else {
        echo "❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
