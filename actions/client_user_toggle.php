<?php
require __DIR__.'/../config.php';

use App\Auth;
use App\ClientAuth;

Auth::require();
if (!Auth::verifyCsrf($_POST['csrf']??'')) { http_response_code(403); exit('Bad CSRF'); }

$tenantId = (int)($_POST['tenant_id'] ?? 0);
$userId = (int)($_POST['user_id'] ?? 0);

if ($tenantId && $userId) {
    try {
        // Get current status
        $pdo = \App\DB::pdo();
        $stmt = $pdo->prepare("SELECT is_active FROM client_users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$userId, $tenantId]);
        $user = $stmt->fetch();
        
        if ($user) {
            $newStatus = !$user['is_active'];
            $success = ClientAuth::updateClientUser($userId, $tenantId, ['is_active' => $newStatus]);
            
            if ($success) {
                $statusText = $newStatus ? 'activated' : 'deactivated';
                header("Location: /tenant.php?id=$tenantId&success=client_user_$statusText");
            } else {
                header("Location: /tenant.php?id=$tenantId&error=Failed to update client user");
            }
        } else {
            header("Location: /tenant.php?id=$tenantId&error=Client user not found");
        }
    } catch (\Throwable $e) {
        header("Location: /tenant.php?id=$tenantId&error=" . urlencode($e->getMessage()));
    }
} else {
    header("Location: /tenant.php?id=$tenantId&error=Missing required parameters");
}
