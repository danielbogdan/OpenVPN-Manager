<?php
require __DIR__.'/../config.php';

use App\Auth;
use App\ClientAuth;

Auth::require();
if (!Auth::verifyCsrf($_POST['csrf']??'')) { http_response_code(403); exit('Bad CSRF'); }

$tenantId = (int)($_POST['tenant_id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$email = trim($_POST['email'] ?? '');
$fullName = trim($_POST['full_name'] ?? '');

if ($tenantId && $username && $password) {
    try {
        $success = ClientAuth::createClientUser($tenantId, $username, $password, $email ?: null, $fullName ?: null);
        
        if ($success) {
            header("Location: /tenant.php?id=$tenantId&success=client_user_created");
        } else {
            header("Location: /tenant.php?id=$tenantId&error=Failed to create client user (username may already exist)");
        }
    } catch (\Throwable $e) {
        header("Location: /tenant.php?id=$tenantId&error=" . urlencode($e->getMessage()));
    }
} else {
    header("Location: /tenant.php?id=$tenantId&error=Missing required parameters");
}
