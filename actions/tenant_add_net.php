<?php
require __DIR__.'/../config.php';
use App\Auth; use App\OpenVPNManager;

Auth::require();
if (!Auth::verifyCsrf($_POST['csrf']??'')) { http_response_code(403); exit('Bad CSRF'); }
$tenantId = (int)($_POST['tenant_id'] ?? 0);
$subnet = trim($_POST['subnet'] ?? '');
if (!$tenantId || !$subnet) { header("Location: /tenant.php?id=$tenantId"); exit; }
OpenVPNManager::addSubnet($tenantId, $subnet);
header("Location: /tenant.php?id=$tenantId");
