<?php
require __DIR__.'/../config.php';
use App\Auth; 
use App\OpenVPNManager;
use App\DB;

Auth::require();
if (!Auth::verifyCsrf($_POST['csrf']??'')) { http_response_code(403); exit('Bad CSRF'); }

$tenantId = (int)($_POST['tenant_id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$nopass = isset($_POST['nopass']);

if ($tenantId && $username && $email) {
    try {
        // Create the certificate
        OpenVPNManager::createUserCert($tenantId, $username, $nopass);
        
        // Update user email (now mandatory)
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("UPDATE vpn_users SET email = ? WHERE tenant_id = ? AND username = ?");
        $stmt->execute([$email, $tenantId, $username]);
        
        header("Location: /tenant.php?id=$tenantId&success=user_created");
    } catch (\Throwable $e) {
        header("Location: /tenant.php?id=$tenantId&error=" . urlencode($e->getMessage()));
    }
} else {
    header("Location: /tenant.php?id=$tenantId&error=" . urlencode("Username and email are required"));
}
