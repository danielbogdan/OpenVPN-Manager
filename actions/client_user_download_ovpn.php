<?php
require __DIR__.'/../config.php';
use App\ClientAuth;
use App\OpenVPNManager;
use App\DB;

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
        $stmt = $pdo->prepare("SELECT username FROM vpn_users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$userId, $tenantId]);
        $user = $stmt->fetch();

        if ($user) {
            $ovpn = OpenVPNManager::exportOvpn($tenantId, $user['username']);
            $filename = $user['username'] . '.ovpn';
            
            header('Content-Type: application/x-openvpn-profile');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            echo $ovpn;
            exit;
        } else {
            header("Location: /client/users.php?error=" . urlencode("VPN user not found or does not belong to this tenant."));
        }
    } catch (\Throwable $e) {
        header("Location: /client/users.php?error=" . urlencode($e->getMessage()));
    }
} else {
    header("Location: /client/users.php?error=" . urlencode("User ID is required."));
}
