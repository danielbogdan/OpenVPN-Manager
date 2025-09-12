<?php
require __DIR__ . '/../config.php';

use App\Auth;
use App\DB;
use App\Util;

Auth::require();

$pdo     = DB::pdo();
$tenants = $pdo->query("SELECT * FROM tenants ORDER BY id DESC")->fetchAll();
$csrf    = Auth::csrf();
?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>OpenVPN Admin · Dashboard</title>
  <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
</head>
<body>
<header>
  <nav class="main-nav">
    <a href="/dashboard.php" class="nav-brand">
      <span class="nav-brand-icon">🔐</span>
      OpenVPN Admin
    </a>
    
    <div class="nav-links">
      <a href="/dashboard.php" class="nav-link active">
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
  <div class="card">
    <h2>Clienți (tenants)</h2>

    <?php if (empty($tenants)): ?>
      <p>Nu există încă niciun client. Creează primul client mai jos.</p>
    <?php else: ?>
      <table>
        <thead>
        <tr>
          <th>ID</th>
          <th>Nume</th>
          <th>Public IP</th>
          <th>Port</th>
          <th>Subnet</th>
          <th>NAT</th>
          <th>Status</th>
          <th style="width:280px">Acțiuni</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tenants as $t): ?>
          <?php
            $status = $t['status'] ?? 'running';
            $statusLabel = $status === 'paused' ? 'PAUSED' : 'RUNNING';
            $statusClass = $status === 'paused' ? 'status-offline' : 'status-online';
          ?>
          <tr>
            <td><?= (int)$t['id'] ?></td>
            <td><?= Util::h($t['name']) ?></td>
            <td><?= Util::h($t['public_ip']) ?></td>
            <td><?= (int)$t['listen_port'] ?></td>
            <td><?= Util::h($t['subnet_cidr']) ?></td>
            <td><?= $t['nat_enabled'] ? 'ON' : 'OFF' ?></td>
            <td class="<?= $statusClass ?>"><?= $statusLabel ?></td>
            <td>
              <a class="btn" href="/tenant.php?id=<?= (int)$t['id'] ?>">Deschide</a>

              <?php if ($status === 'paused'): ?>
                <form method="post" action="/actions/tenant_resume.php" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                  <button class="btn">Reia</button>
                </form>
              <?php else: ?>
                <form method="post" action="/actions/tenant_pause.php" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                  <button class="btn">Pauză</button>
                </form>
              <?php endif; ?>

              <form method="post"
                    action="/actions/tenant_delete.php"
                    style="display:inline"
                    onsubmit="return confirm('Sigur vrei să ștergi clientul <?= App\Util::h($t['name']) ?> ? Această acțiune va opri și șterge containerul, volumul și rețeaua Docker.');">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button class="btn danger">Șterge</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Adaugă client nou</h3>
    <form method="post" action="/actions/tenant_create.php">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">

      <div class="form-group">
        <label>Nume client</label>
        <input name="name" required>
      </div>

      <div class="form-group">
        <label>Subnet /26 (opțional)</label>
        <input name="subnet" placeholder="ex. 10.20.0.0/26 (gol = auto)">
      </div>

      <div class="form-group">
        <label>
          <input type="checkbox" name="nat" checked>
          NAT implicit
        </label>
      </div>

      <button class="btn">Creează</button>
    </form>
  </div>
</main>
</body>
</html>
