<?php
require __DIR__ . '/../config.php';

use App\Auth;
use App\DB;
use App\Util;
use App\OpenVPNManager;

Auth::require();

$pdo     = DB::pdo();
$tenants = $pdo->query("SELECT * FROM tenants ORDER BY id DESC")->fetchAll();
$csrf    = Auth::csrf();

// Refresh sessions for all tenants to get latest connection data
foreach ($tenants as $tenant) {
    try {
        OpenVPNManager::refreshSessions($tenant['id']);
    } catch (\Throwable $e) {
        // Log error but don't break the page
        error_log("Failed to refresh sessions for tenant {$tenant['id']}: " . $e->getMessage());
    }
}

// Handle success messages
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>OpenVPN Admin · VPN Tenants</title>
  <link rel="stylesheet" href="/assets/style.css?v=<?= time() + 1 ?>">
  <link rel="stylesheet" href="/assets/client.css?v=<?= time() + 2 ?>">
  <link rel="stylesheet" href="/assets/dashboard.css?v=<?= time() + 5 ?>">
</head>
<body>
<!-- Admin Header -->
<div class="client-header">
    <div class="client-header-content">
        <div class="client-title">
            <h1>OpenVPN Admin</h1>
            <div class="client-meta">
                <span class="client-role">Administrator</span>
                <span class="client-tenant">Admin Panel</span>
                <div class="current-time">
                  <span id="live-clock"><?php
                    $currentTime = new DateTime('now', new DateTimeZone('Europe/Bucharest'));
                    echo $currentTime->format('M j, Y H:i:s');
                  ?></span>
                  <span class="timezone">(Local Time)</span>
                </div>
            </div>
        </div>
        <div class="client-actions">
            <a href="/dashboard.php" class="client-btn client-btn-secondary">
                <span class="btn-icon">🏠</span>
                Dashboard
            </a>
            <a href="/tenants.php" class="client-btn client-btn-secondary">
                <span class="btn-icon">🏢</span>
                Manage Tenants
            </a>
            <a href="/email_config.php" class="client-btn client-btn-secondary">
                <span class="btn-icon">📧</span>
                Email Config
            </a>
            <a href="/admin_settings.php" class="client-btn client-btn-secondary">
                <span class="btn-icon">⚙️</span>
                Settings
            </a>
            <a href="/logout.php" class="client-btn client-btn-secondary">
                <span class="btn-icon">🚪</span>
                Logout
            </a>
        </div>
    </div>
</div>

<div class="dashboard-container">
  <!-- Messages -->
  <?php if ($success): ?>
    <div class="alert alert-success">
      <span class="alert-icon">✅</span>
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>
  
  <?php if ($error): ?>
    <div class="alert alert-error">
      <span class="alert-icon">❌</span>
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <!-- VPN Tenants Section -->
  <div class="dashboard-tenants-section">
    <div class="card">
      <div class="card-header">
        <h2>🏢 VPN Tenants</h2>
        <div class="card-actions">
          <button class="btn btn-primary" onclick="showCreateTenantForm()" type="button">
            <span class="btn-icon">➕</span>
            New Tenant
          </button>
        </div>
      </div>
      
      <div class="card-content">
        <?php if (empty($tenants)): ?>
          <div class="empty-state">
            <div class="empty-icon">🏢</div>
            <h3>No Tenants Yet</h3>
            <p>Create your first VPN tenant to get started</p>
            <button class="btn btn-primary" onclick="showCreateTenantForm()" type="button">
              <span class="btn-icon">➕</span>
              Create First Tenant
            </button>
          </div>
        <?php else: ?>
          <div class="tenants-list">
            <?php foreach ($tenants as $t): ?>
              <?php
                $status = $t['status'] ?? 'running';
                $statusLabel = $status === 'paused' ? 'PAUSED' : 'RUNNING';
                $statusClass = $status === 'paused' ? 'status-offline' : 'status-online';
                $userCount = $pdo->prepare("SELECT COUNT(*) FROM vpn_users WHERE tenant_id = ?");
                $userCount->execute([$t['id']]);
                $userCount = $userCount->fetchColumn();
                $activeSessions = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE tenant_id = ? AND last_seen >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
                $activeSessions->execute([$t['id']]);
                $activeSessions = $activeSessions->fetchColumn();
              ?>
              <div class="tenant-card">
                <div class="tenant-header">
                  <div class="tenant-info">
                    <h3><?= Util::h($t['name']) ?></h3>
                    <div class="tenant-meta">
                      <span class="tenant-id">#<?= (int)$t['id'] ?></span>
                      <span class="tenant-status <?= $statusClass ?>"><?= $statusLabel ?></span>
                    </div>
                  </div>
                  <div class="tenant-stats">
                    <div class="stat">
                      <span class="stat-value"><?= $userCount ?></span>
                      <span class="stat-label">Users</span>
                    </div>
                    <div class="stat">
                      <span class="stat-value"><?= $activeSessions ?></span>
                      <span class="stat-label">Active</span>
                    </div>
                  </div>
                </div>
                
                <div class="tenant-details">
                  <div class="detail-row">
                    <div class="detail-item">
                      <span class="detail-label">Public IP:</span>
                      <span class="detail-value"><?= Util::h($t['public_ip']) ?></span>
                    </div>
                    <div class="detail-item">
                      <span class="detail-label">Port:</span>
                      <span class="detail-value"><?= (int)$t['listen_port'] ?></span>
                    </div>
                  </div>
                  <div class="detail-row">
                    <div class="detail-item">
                      <span class="detail-label">Subnet:</span>
                      <span class="detail-value"><?= Util::h($t['subnet_cidr']) ?></span>
                    </div>
                    <div class="detail-item">
                      <span class="detail-label">NAT:</span>
                      <span class="detail-value <?= $t['nat_enabled'] ? 'status-online' : 'status-offline' ?>">
                        <?= $t['nat_enabled'] ? 'ON' : 'OFF' ?>
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Tenant Users Section -->
                <div class="tenant-users-section">
                  <?php
                    // Get VPN users for this tenant
                    $vpnUsers = $pdo->prepare("SELECT * FROM vpn_users WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 5");
                    $vpnUsers->execute([$t['id']]);
                    $vpnUsers = $vpnUsers->fetchAll();
                    
                    // Get client users for this tenant
                    $clientUsers = $pdo->prepare("SELECT * FROM client_users WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 5");
                    $clientUsers->execute([$t['id']]);
                    $clientUsers = $clientUsers->fetchAll();
                  ?>
                  
                  <!-- Client Users -->
                  <?php if (!empty($clientUsers)): ?>
                    <div class="tenant-users-group">
                      <div class="tenant-users-header">
                        <h4>👨‍💼 Portal Client Users (<?= count($clientUsers) ?>)</h4>
                      </div>
                      <div class="tenant-users-list">
                        <?php foreach ($clientUsers as $clientUser): ?>
                          <?php
                            // Check if client user is currently active
                            $isClientActive = false;
                            if ($clientUser['last_activity']) {
                              $stmt = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) < 10");
                              $stmt->execute([$clientUser['last_activity']]);
                              $isClientActive = $stmt->fetchColumn();
                            }
                          ?>
                          <div class="tenant-user-item">
                            <div class="tenant-user-info">
                              <div class="tenant-user-name"><?= htmlspecialchars($clientUser['username']) ?></div>
                              <div class="tenant-user-email"><?= htmlspecialchars($clientUser['email'] ?? 'No email') ?></div>
                              <div class="tenant-user-login-info">
                                <?php if ($clientUser['last_login']): ?>
                                  <?php
                                    $utcTime = new DateTime($clientUser['last_login'], new DateTimeZone('UTC'));
                                    $utcTime->setTimezone(new DateTimeZone('Europe/Bucharest'));
                                    $localTime = $utcTime->format('M j, Y H:i');
                                  ?>
                                  <span class="login-time">Last login: <?= $localTime ?></span>
                                  <?php if ($clientUser['last_login_ip']): ?>
                                    <span class="login-ip">from <?= htmlspecialchars($clientUser['last_login_ip']) ?></span>
                                  <?php endif; ?>
                                <?php else: ?>
                                  <span class="login-time">Never logged in</span>
                                <?php endif; ?>
                              </div>
                            </div>
                            <div class="tenant-user-status">
                              <span class="status-badge <?= $isClientActive ? 'status-active' : 'status-inactive' ?>">
                                <?= $isClientActive ? '🟢 Online' : '⚪ Offline' ?>
                              </span>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endif; ?>

                  <!-- VPN Users -->
                  <?php if (!empty($vpnUsers)): ?>
                    <div class="tenant-users-group">
                      <div class="tenant-users-header">
                        <h4>👥 VPN Profile Users (<?= count($vpnUsers) ?>)</h4>
                      </div>
                      <div class="tenant-users-list">
                        <?php foreach ($vpnUsers as $vpnUser): ?>
                          <?php
                            // Check if user is active
                            $userStatus = $vpnUser['status'] === 'active';
                            $hasRecentSession = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE tenant_id = ? AND user_id = ? AND last_seen >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
                            $hasRecentSession->execute([$vpnUser['tenant_id'], $vpnUser['id']]);
                            $hasRecentSession = $hasRecentSession->fetchColumn() > 0;
                            $isActive = $userStatus || $hasRecentSession;
                          ?>
                          <div class="tenant-user-item">
                            <div class="tenant-user-info">
                              <div class="tenant-user-name"><?= htmlspecialchars($vpnUser['username']) ?></div>
                              <div class="tenant-user-email"><?= htmlspecialchars($vpnUser['email'] ?? 'No email') ?></div>
                            </div>
                            <div class="tenant-user-status">
                              <span class="status-badge <?= $isActive ? 'status-active' : 'status-inactive' ?>">
                                <?= $isActive ? '🟢 Active' : '⚪ Inactive' ?>
                              </span>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="tenant-actions">
                  <a class="btn btn-primary" href="/tenant.php?id=<?= (int)$t['id'] ?>">
                    <span class="btn-icon">👁️</span>
                    Manage
                  </a>
                  <a class="btn btn-secondary" href="/analytics.php?id=<?= (int)$t['id'] ?>">
                    <span class="btn-icon">📊</span>
                    Analytics
                  </a>

                  <?php if ($status === 'paused'): ?>
                    <form method="post" action="/actions/tenant_resume.php" style="display:inline">
                      <input type="hidden" name="csrf" value="<?= $csrf ?>">
                      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                      <button class="btn btn-success" type="submit">
                        <span class="btn-icon">▶️</span>
                        Resume
                      </button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="/actions/tenant_pause.php" style="display:inline">
                      <input type="hidden" name="csrf" value="<?= $csrf ?>">
                      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                      <button class="btn btn-warning" type="submit">
                        <span class="btn-icon">⏸️</span>
                        Pause
                      </button>
                    </form>
                  <?php endif; ?>

                  <form method="post" action="/actions/tenant_delete.php" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this tenant?')">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                    <button class="btn btn-danger" type="submit">
                      <span class="btn-icon">🗑️</span>
                      Delete
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Create Tenant Form (Hidden by default) -->
  <div id="createTenantModal" class="modal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Create New Tenant</h2>
        <button class="modal-close" onclick="hideCreateTenantForm()">&times;</button>
      </div>
      <form method="post" action="/actions/tenant_create.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <div class="form-group">
          <label for="name">Tenant Name</label>
          <input type="text" id="name" name="name" required placeholder="Enter tenant name">
        </div>
        <div class="form-group">
          <label>
            <input type="checkbox" name="nat" value="1" checked>
            Enable NAT
          </label>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" onclick="hideCreateTenantForm()">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Tenant</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function showCreateTenantForm() {
  document.getElementById('createTenantModal').style.display = 'flex';
}

function hideCreateTenantForm() {
  document.getElementById('createTenantModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('createTenantModal').addEventListener('click', function(e) {
  if (e.target === this) {
    hideCreateTenantForm();
  }
});
</script>

<!-- Live Clock JavaScript -->
<script>
function updateLiveClock() {
    const now = new Date();
    const options = {
        timeZone: 'Europe/Bucharest',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    };
    
    const formatter = new Intl.DateTimeFormat('en-US', options);
    const formattedTime = formatter.format(now);
    
    const clockElement = document.getElementById('live-clock');
    if (clockElement) {
        clockElement.textContent = formattedTime;
    }
}

// Update clock immediately and then every second
updateLiveClock();
setInterval(updateLiveClock, 1000);

// Auto-hide notifications after 4 seconds
setTimeout(function() {
    const notifications = document.querySelectorAll('.alert');
    notifications.forEach(function(notification) {
        notification.style.opacity = '0';
        notification.style.transform = 'translateY(-10px)';
        setTimeout(function() {
            notification.remove();
        }, 300);
    });
}, 4000);

// Clean URL if we have success/error messages
const urlParams = new URLSearchParams(window.location.search);
const success = urlParams.get('success');
const error = urlParams.get('error');

if (success || error) {
    const newUrl = window.location.pathname;
    window.history.replaceState({}, document.title, newUrl);
}
</script>

</body>
</html>
