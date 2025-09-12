<?php
require __DIR__ . '/../config.php';

use App\Auth;
use App\DB;

// Check if admin is logged in
Auth::require();

try {
    $pdo = DB::pdo();
    
    // Get all client users with their current status
    $stmt = $pdo->query("
        SELECT 
            cu.id,
            cu.username,
            cu.last_activity,
            cu.last_login,
            cu.last_login_ip,
            cu.tenant_id,
            t.name as tenant_name
        FROM client_users cu
        JOIN tenants t ON cu.tenant_id = t.id
        WHERE cu.is_active = 1
        ORDER BY cu.tenant_id, cu.id
    ");
    
    $clientUsers = $stmt->fetchAll();
    
    $statusData = [];
    foreach ($clientUsers as $user) {
        // Check if user is currently active (active within last 10 seconds)
        $isActive = false;
        if ($user['last_activity']) {
            $stmt = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) < 10");
            $stmt->execute([$user['last_activity']]);
            $isActive = $stmt->fetchColumn();
        }
        
        // Convert UTC time to local timezone
        $lastLoginFormatted = null;
        if ($user['last_login']) {
            $utcTime = new DateTime($user['last_login'], new DateTimeZone('UTC'));
            $utcTime->setTimezone(new DateTimeZone('Europe/Bucharest'));
            $lastLoginFormatted = $utcTime->format('M j, Y H:i');
        }
        
        $statusData[] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'tenant_id' => $user['tenant_id'],
            'tenant_name' => $user['tenant_name'],
            'is_active' => $isActive,
            'last_activity' => $user['last_activity'],
            'last_login' => $user['last_login'],
            'last_login_ip' => $user['last_login_ip'],
            'last_login_formatted' => $lastLoginFormatted
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'client_users' => $statusData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
