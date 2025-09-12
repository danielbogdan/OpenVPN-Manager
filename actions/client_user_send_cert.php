<?php
require __DIR__.'/../config.php';
use App\ClientAuth;
use App\OpenVPNManager;
use App\DB;
use App\EmailService;

ClientAuth::require();

$tenantId = ClientAuth::getCurrentTenantId();
$userId = (int)($_POST['id'] ?? 0);

if (!$tenantId) {
    header("Location: /client/users.php?error=" . urlencode("Tenant ID not found in session."));
    exit;
}

if ($userId) {
    try {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT id, username, email FROM vpn_users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$userId, $tenantId]);
        $user = $stmt->fetch();

        if ($user) {
            if (empty($user['email'])) {
                header("Location: /client/users.php?error=" . urlencode("User has no email address configured."));
                exit;
            }

            // Get certificate content
            $certificateContent = OpenVPNManager::exportOvpn($tenantId, $user['username']);
            
            // Send email using the same method as admin
            EmailService::sendCertificate(
                $tenantId,
                $user['id'],
                $user['email'],
                $certificateContent,
                $user['username'],
                'Client Portal' // We don't have tenant name in client context
            );
            
            header("Location: /client/users.php?success=email_sent");
        } else {
            header("Location: /client/users.php?error=" . urlencode("VPN user not found or does not belong to this tenant."));
        }
    } catch (\Throwable $e) {
        header("Location: /client/users.php?error=" . urlencode($e->getMessage()));
    }
} else {
    header("Location: /client/users.php?error=" . urlencode("User ID is required."));
}
