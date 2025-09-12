<?php
require __DIR__ . '/../config.php';

use App\ClientAuth;
use App\DB;

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Check if client is logged in
if (!ClientAuth::isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

try {
    $pdo = DB::pdo();
    $clientUserId = $_SESSION['client_user_id'];
    
    // Update last activity timestamp
    $stmt = $pdo->prepare("UPDATE client_users SET last_activity = NOW() WHERE id = ?");
    $stmt->execute([$clientUserId]);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'timestamp' => date('Y-m-d H:i:s')]);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
