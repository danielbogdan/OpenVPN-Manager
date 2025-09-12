<?php
require __DIR__.'/../config.php';
use App\Auth; 
use App\OpenVPNManager;
use App\DB;

Auth::require();
if (!Auth::verifyCsrf($_POST['csrf']??'')) { http_response_code(403); exit('Bad CSRF'); }

$tenantId = (int)($_POST['tenant_id'] ?? 0);
if (!$tenantId) { http_response_code(400); exit('Invalid tenant ID'); }

// Get current NAT status
$pdo = DB::pdo();
$stmt = $pdo->prepare("SELECT nat_enabled FROM tenants WHERE id = ?");
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch();

if (!$tenant) { http_response_code(404); exit('Tenant not found'); }

// Toggle the NAT status
$newNatStatus = !$tenant['nat_enabled'];
OpenVPNManager::toggleNat($tenantId, $newNatStatus);

header("Location: /tenant.php?id=$tenantId&success=" . urlencode("NAT " . ($newNatStatus ? "enabled" : "disabled")));
