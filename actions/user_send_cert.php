<?php
require __DIR__ . '/../config.php';

use App\Auth;
use App\DB;
use App\OpenVPNManager;
use App\EmailService;

Auth::require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed";
    exit;
}

$csrf = $_POST['csrf'] ?? '';
if (!Auth::verifyCsrf($csrf)) {
    http_response_code(403);
    echo "Invalid CSRF token";
    exit;
}

$tenantId = (int)($_POST['tenant_id'] ?? 0);
$username = trim($_POST['username'] ?? '');

if (!$tenantId || !$username) {
    http_response_code(400);
    echo "Missing required parameters";
    exit;
}

try {
    $pdo = DB::pdo();
    
    // Get user info
    $stmt = $pdo->prepare("
        SELECT vu.*, t.name as tenant_name 
        FROM vpn_users vu 
        JOIN tenants t ON vu.tenant_id = t.id 
        WHERE vu.tenant_id = ? AND vu.username = ? AND vu.status = 'active'
    ");
    $stmt->execute([$tenantId, $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new \RuntimeException("User not found or inactive");
    }
    
    if (!$user['email']) {
        throw new \RuntimeException("User has no email address configured");
    }
    
    // Get certificate content
    $certificateContent = OpenVPNManager::exportOvpn($tenantId, $username);
    
    // Send email
    EmailService::sendCertificate(
        $tenantId,
        $user['id'],
        $user['email'],
        $certificateContent,
        $username,
        $user['tenant_name']
    );
    
    // Redirect back with success message
    header("Location: /tenant.php?id={$tenantId}&success=email_sent&username=" . urlencode($username));
    exit;
    
} catch (\Throwable $e) {
    // Redirect back with error message
    header("Location: /tenant.php?id={$tenantId}&error=" . urlencode($e->getMessage()) . "&username=" . urlencode($username));
    exit;
}
