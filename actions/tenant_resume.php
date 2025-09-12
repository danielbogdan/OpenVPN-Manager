<?php
require __DIR__ . '/../config.php';

use App\Auth;
use App\OpenVPNManager;

Auth::require();

// CSRF
if (!Auth::verifyCsrf($_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('Bad CSRF');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID invalid');
}

try {
    OpenVPNManager::resumeTenant($id);
} catch (Throwable $e) {
    // opțional: log
    // error_log("resumeTenant($id) failed: ".$e->getMessage());
    http_response_code(500);
    exit('Eroare: ' . $e->getMessage());
}

// Redirect back to tenants page with success message
header('Location: /tenants.php?success=Tenant resumed successfully');
exit;
