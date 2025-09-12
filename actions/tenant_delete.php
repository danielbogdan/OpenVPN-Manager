<?php
require __DIR__.'/../config.php';

use App\Auth;
use App\OpenVPNManager;

Auth::require();

if (!Auth::verifyCsrf($_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('Bad CSRF');
}

$tenantId = (int)($_POST['id'] ?? 0);
if ($tenantId > 0) {
    try {
        OpenVPNManager::deleteTenant($tenantId);
        header('Location: /dashboard.php');
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo "Eroare la ștergere: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        exit;
    }
}

header('Location: /dashboard.php');
