<?php
require __DIR__.'/../config.php';
use App\Auth; use App\OpenVPNManager;

Auth::require();
if (!Auth::verifyCsrf($_POST['csrf']??'')) { http_response_code(403); exit('Bad CSRF'); }
$tenantId = (int)($_POST['tenant_id'] ?? 0);
$username = trim($_POST['username'] ?? '');
if (!$tenantId || !$username) { http_response_code(400); exit('Bad req'); }

$ovpn = OpenVPNManager::exportOvpn($tenantId, $username);
header('Content-Type: application/x-openvpn-profile');
header('Content-Disposition: attachment; filename="'.$username.'_tenant'.$tenantId.'.ovpn"');
echo $ovpn;
