<?php
require __DIR__.'/../config.php';
use App\ClientAuth; 
use App\OpenVPNManager;
use App\DB;

ClientAuth::require();

$userId = (int)($_POST['id'] ?? 0);
$tenantId = ClientAuth::getCurrentTenantId();

if ($userId && $tenantId) {
    try {
        // Get user info
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT username FROM vpn_users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$userId, $tenantId]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Revoke the certificate
            OpenVPNManager::revokeUserCert($tenantId, $user['username']);
            header("Location: /client/users.php?success=user_revoked");
        } else {
            header("Location: /client/users.php?error=" . urlencode("User not found"));
        }
    } catch (\Throwable $e) {
        header("Location: /client/users.php?error=" . urlencode($e->getMessage()));
    }
} else {
    header("Location: /client/users.php?error=" . urlencode("Invalid request"));
}
