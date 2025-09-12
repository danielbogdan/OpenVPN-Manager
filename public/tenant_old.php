<?php
require __DIR__.'/../config.php';
use App\Auth;
use App\DB;

Auth::require();
$pdo = DB::pdo();
$id = (int)($_GET['id'] ?? 0);
$t = $pdo->prepare("SELECT * FROM tenants WHERE id=?"); $t->execute([$id]); $tenant = $t->fetch();
if (!$tenant) { http_response_code(404); echo "Tenant not found"; exit; }

$nets = $pdo->prepare("SELECT * FROM tenant_networks WHERE tenant_id=?"); $nets->execute([$id]); $nets=$nets->fetchAll();
$users = $pdo->prepare("SELECT * FROM vpn_users WHERE tenant_id=? ORDER BY id DESC"); $users->execute([$id]); $users=$users->fetchAll();
$sessions = $pdo->prepare("SELECT * FROM sessions WHERE tenant_id=? ORDER BY last_seen DESC"); $sessions->execute([$id]); $sessions=$sessions->fetchAll();
$csrf = \App\Auth::csrf();

// Handle flash messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!doctype html><html><head>
<meta charset="utf-8"><title>Tenant <?=$tenant['name']?></title>
<link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
</head><body>
<header>
  <nav class="main-nav">
    <a href="/dashboard.php" class="nav-brand">
      <span class="nav-brand-icon">🔐</span>
      OpenVPN Admin
    </a>
    
    <div class="nav-links">
      <a href="/dashboard.php" class="nav-link">
        <span class="nav-link-icon">🏠</span>
        Dashboard
      </a>
      <a href="/email_config.php" class="nav-link">
        <span class="nav-link-icon">📧</span>
        Email Config
      </a>
      <a href="/logout.php" class="nav-link">
        <span class="nav-link-icon">🚪</span>
        Logout
      </a>
    </div>
  </nav>
</header>
<main>
  <?php if ($success): ?>
    <div class="flash">
      <?php
      switch ($success) {
          case 'user_created':
              echo '✅ User created successfully!';
              break;
          case 'email_sent':
              echo '📧 Certificate sent via email successfully!';
              break;
          default:
              echo '✅ ' . htmlspecialchars($success);
      }
      ?>
    </div>
  <?php endif; ?>
  
  <?php if ($error): ?>
    <div class="flash err">
      ❌ <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>
  <div class="card">
    <h2><?=$tenant['name']?> — Instanță</h2>
    <p>Public IP: <b><?=$tenant['public_ip']?></b> · Port: <b><?=$tenant['listen_port']?></b> · Subnet principal: <b><?=$tenant['subnet_cidr']?></b> · NAT: <b><?=$tenant['nat_enabled']?'ON':'OFF'?></b></p>
    <div style="margin-top:12px; display:flex; gap:12px; align-items:center;">
      <form method="post" action="/actions/tenant_toggle_nat.php" style="display:inline">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="tenant_id" value="<?=$tenant['id']?>">
        <label>Activează NAT <input type="checkbox" name="nat" <?=$tenant['nat_enabled']?'checked':''?> onchange="this.form.submit()"></label>
      </form>
      <a href="/analytics.php?id=<?=$tenant['id']?>" class="btn" style="background:#3B82F6; color:white; text-decoration:none;">📊 Analytics</a>
    </div>
  </div>

  <div class="card">
    <h3>Subrețele /26 suplimentare</h3>
    <table>
      <tr><th>Subnet</th><th></th></tr>
      <?php foreach($nets as $n): ?>
      <tr><td><?=$n['subnet_cidr']?></td>
          <td>
            <form method="post" action="/actions/tenant_del_net.php" onsubmit="return confirm('Șterg această subrețea?');">
              <input type="hidden" name="csrf" value="<?=$csrf?>">
              <input type="hidden" name="id" value="<?=$n['id']?>">
              <input type="hidden" name="tenant_id" value="<?=$tenant['id']?>">
              <button class="btn">Șterge</button>
            </form>
          </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <form method="post" action="/actions/tenant_add_net.php" style="margin-top:10px">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="tenant_id" value="<?=$tenant['id']?>">
      <label>Adaugă subnet (/26) <input name="subnet" placeholder="ex. 10.21.0.0/26"></label>
      <button class="btn">Adaugă</button>
    </form>
  </div>

  <div class="card">
    <h3>Utilizatori VPN (certificate)</h3>
    <table>
      <tr><th>User</th><th>Email</th><th>Status</th><th>Acțiuni</th></tr>
      <?php foreach($users as $u): ?>
      <tr>
        <td><?=App\Util::h($u['username'])?></td>
        <td><?=App\Util::h($u['email'] ?? '')?></td>
        <td><?=$u['status']?></td>
        <td>
          <form method="post" action="/actions/user_download_ovpn.php" style="display:inline">
            <input type="hidden" name="csrf" value="<?=$csrf?>">
            <input type="hidden" name="tenant_id" value="<?=$tenant['id']?>">
            <input type="hidden" name="username" value="<?=$u['username']?>">
            <button class="btn">Descarcă .ovpn</button>
          </form>
          <?php if($u['email'] && $u['status']==='active'): ?>
          <form method="post" action="/actions/user_send_cert.php" style="display:inline" onsubmit="return confirm('Trimite certificatul prin email?');">
            <input type="hidden" name="csrf" value="<?=$csrf?>">
            <input type="hidden" name="tenant_id" value="<?=$tenant['id']?>">
            <input type="hidden" name="username" value="<?=$u['username']?>">
            <button class="btn" style="background:#10B981; color:white;">📧 Trimite Email</button>
          </form>
          <?php endif; ?>
          <?php if($u['status']==='active'): ?>
          <form method="post" action="/actions/user_revoke_cert.php" style="display:inline" onsubmit="return confirm('Revoc certificatul?');">
            <input type="hidden" name="csrf" value="<?=$csrf?>">
            <input type="hidden" name="tenant_id" value="<?=$tenant['id']?>">
            <input type="hidden" name="username" value="<?=$u['username']?>">
            <button class="btn danger">Revocă</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <form method="post" action="/actions/user_create_cert.php" style="margin-top:10px">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="tenant_id" value="<?=$tenant['id']?>">
      <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
        <label>User nou <span style="color: #ef4444;">*</span> <input name="username" required placeholder="ex. dan.popescu"></label>
        <label>Email <span style="color: #ef4444;">*</span> <input name="email" type="email" required placeholder="user@example.com"></label>
        <label><input type="checkbox" name="nopass" checked> fără parolă</label>
        <button class="btn">Generează</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h3>Conexiuni active</h3>
    <form method="post" action="/actions/tenant_refresh_status.php">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="tenant_id" value="<?=$tenant['id']?>">
      <button class="btn">Refresh status</button>
    </form>
    <table>
      <tr><th>User</th><th>IP public</th><th>IP VPN</th><th>Țară/Oras</th><th>RX</th><th>TX</th><th>Din</th></tr>
      <?php foreach($sessions as $s): ?>
      <tr>
        <td><?=App\Util::h($s['common_name'])?></td>
        <td><?=App\Util::h($s['real_address'])?></td>
        <td><?=App\Util::h($s['virtual_address'])?></td>
        <td><?=App\Util::h(($s['geo_country']??'').' '.($s['geo_city']??''))?></td>
        <td><?=number_format((int)$s['bytes_received'])?></td>
        <td><?=number_format((int)$s['bytes_sent'])?></td>
        <td><?=$s['since']?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</main>
</body></html>
