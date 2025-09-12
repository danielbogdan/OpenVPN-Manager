<?php
require __DIR__.'/../config.php';
use App\Auth; use App\DB;

Auth::require();
if (!Auth::verifyCsrf($_POST['csrf']??'')) { http_response_code(403); exit('Bad CSRF'); }
$id = (int)($_POST['id'] ?? 0);
$tenantId = (int)($_POST['tenant_id'] ?? 0);
$pdo = DB::pdo();
$pdo->prepare("DELETE FROM tenant_networks WHERE id=? AND tenant_id=?")->execute([$id,$tenantId]);
header("Location: /tenant.php?id=$tenantId");
