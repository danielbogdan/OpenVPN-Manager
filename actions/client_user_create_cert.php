<?php
require __DIR__.'/../config.php';
use App\ClientAuth; 
use App\OpenVPNManager;
use App\DB;

ClientAuth::require();

$tenantId = ClientAuth::getCurrentTenantId();
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
        
        header("Location: /client/users.php?success=user_created");
    } catch (\Throwable $e) {
        header("Location: /client/users.php?error=" . urlencode($e->getMessage()));
    }
} else {
    header("Location: /client/users.php?error=" . urlencode("Username and email are required"));
}
