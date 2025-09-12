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
        $success = ClientAuth::deleteClientUser($userId, $tenantId);
        
        if ($success) {
            header("Location: /tenant.php?id=$tenantId&success=client_user_deleted");
        } else {
            header("Location: /tenant.php?id=$tenantId&error=Failed to delete client user");
        }
    } catch (\Throwable $e) {
        header("Location: /tenant.php?id=$tenantId&error=" . urlencode($e->getMessage()));
    }
} else {
    header("Location: /tenant.php?id=$tenantId&error=Missing required parameters");
}
