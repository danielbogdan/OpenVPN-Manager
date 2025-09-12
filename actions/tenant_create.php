<?php
require __DIR__ . '/../config.php';

use App\Auth;
use App\DB;
use App\OpenVPNManager;

Auth::require();

if (!Auth::verifyCsrf($_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('Bad CSRF');
}

$name = trim($_POST['name'] ?? '');
$nat  = isset($_POST['nat']);

if ($name === '') {
    header('Location: /dashboard.php');
    exit;
}

try {
    // IMPORTANT: nu mai inserăm nimic aici.
    // OpenVPNManager::initTenant se ocupă de:
    //  - INSERT stub (pentru AUTO_INCREMENT)
    //  - creare resurse Docker
    //  - update rând cu numele reale ale resurselor
    $tenantId = OpenVPNManager::initTenant(
        0,                // ignorat de manager (compat)
        $name,
        null,             // public IP auto
        null,             // port auto
        null,             // subnet auto
        $nat
    );

    header('Location: /tenant.php?id=' . $tenantId);
    exit;
} catch (\Throwable $e) {
    // Dacă apare o eroare, afișeaz-o clar în browser.
    // (opțional poți pune un „flash” în sesiune și redirect la /dashboard.php)
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Eroare creare tenant:\n" . $e->getMessage();
    exit;
}
