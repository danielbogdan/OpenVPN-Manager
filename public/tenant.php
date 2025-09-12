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

// Get tenant statistics
$activeSessions = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE tenant_id = ? AND last_seen >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$activeSessions->execute([$id]);
$activeSessions = $activeSessions->fetchColumn();

$totalSessions = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE tenant_id = ?");
$totalSessions->execute([$id]);
$totalSessions = $totalSessions->fetchColumn();

$countries = $pdo->prepare("SELECT COUNT(DISTINCT geo_country) FROM sessions WHERE tenant_id = ? AND geo_country IS NOT NULL");
$countries->execute([$id]);
$countries = $countries->fetchColumn();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>OpenVPN Admin · <?= htmlspecialchars($tenant['name']) ?></title>
  <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
  <link rel="stylesheet" href="/assets/client.css?v=<?= time() + 2 ?>">
  <link rel="stylesheet" href="/assets/dashboard.css?v=<?= time() + 5 ?>">
  <link rel="stylesheet" href="/assets/tenant.css?v=<?= time() + 6 ?>">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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

<main>
  <!-- Flash Messages (excluding NAT, email, and client user messages) -->
  <?php if ($success && !str_contains($success, 'NAT') && !str_contains($success, 'email') && !str_contains($success, 'client_user')): ?>
    <div class="flash flash-success">
      <span class="flash-icon">✅</span>
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>
  
  <?php if ($error && !str_contains($error, 'NAT') && !str_contains($error, 'email') && !str_contains($error, 'PHPMailer') && !str_contains($error, 'client_user')): ?>
    <div class="flash flash-error">
      <span class="flash-icon">❌</span>
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <!-- Tenant Header -->
  <div class="tenant-header">
    <div class="tenant-header-content">
      <div class="tenant-title">
        <h1><?= htmlspecialchars($tenant['name']) ?></h1>
        <div class="tenant-meta">
          <span class="tenant-id">#<?= $tenant['id'] ?></span>
          <span class="tenant-status <?= ($tenant['status'] ?? 'running') === 'paused' ? 'status-offline' : 'status-online' ?>">
            <?= ($tenant['status'] ?? 'running') === 'paused' ? 'PAUSED' : 'RUNNING' ?>
          </span>
        </div>
      </div>
      <div class="tenant-actions">
        <a href="/analytics.php?id=<?= $tenant['id'] ?>" class="btn btn-primary">
          <span class="btn-icon">📊</span>
          Analytics
        </a>
      </div>
    </div>
  </div>

  <!-- Overview Cards -->
  <div class="overview-cards">
    <div class="overview-card">
      <div class="overview-icon">👥</div>
      <div class="overview-content">
        <h3><?= count($users) ?></h3>
        <p>Total Users</p>
      </div>
    </div>
    <div class="overview-card">
      <div class="overview-icon">🌐</div>
      <div class="overview-content">
        <h3><?= $activeSessions ?></h3>
        <p>Active Sessions</p>
      </div>
    </div>
    <div class="overview-card">
      <div class="overview-icon">🌍</div>
      <div class="overview-content">
        <h3><?= $countries ?></h3>
        <p>Countries</p>
      </div>
    </div>
    <div class="overview-card">
      <div class="overview-icon">📊</div>
      <div class="overview-content">
        <h3><?= $totalSessions ?></h3>
        <p>Total Sessions</p>
      </div>
    </div>
  </div>

  <!-- Integrated Management Dashboard -->
  <div class="integrated-dashboard">
    <!-- Server Configuration Row -->
    <div class="dashboard-section">
      <div class="section-header">
        <h2>⚙️ Server Configuration</h2>
        <form method="post" action="/actions/tenant_toggle_nat.php" style="display: inline;">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
          <button type="submit" class="btn btn-secondary">
            <span class="btn-icon">🔄</span>
            Toggle NAT
          </button>
        </form>
        <div id="natNotification" class="inline-notification" style="display: none;">
          <span class="notification-icon">✅</span>
          <span class="notification-text"></span>
        </div>
      </div>
      <div class="config-row">
        <div class="config-field">
          <label>Public IP</label>
          <span class="config-value"><?= htmlspecialchars($tenant['public_ip']) ?></span>
        </div>
        <div class="config-field">
          <label>Port</label>
          <span class="config-value"><?= $tenant['listen_port'] ?></span>
        </div>
        <div class="config-field">
          <label>Main Subnet</label>
          <span class="config-value"><?= htmlspecialchars($tenant['subnet_cidr']) ?></span>
        </div>
        <div class="config-field">
          <label>NAT Status</label>
          <span class="status-badge <?= $tenant['nat_enabled'] ? 'status-online' : 'status-offline' ?>">
            <?= $tenant['nat_enabled'] ? 'ON' : 'OFF' ?>
          </span>
        </div>
      </div>
    </div>

    <!-- VPN Users Section -->
    <div class="dashboard-section">
      <div class="section-header">
        <h2>👥 VPN Profile Users</h2>
        <button class="btn btn-primary" onclick="showCreateUserForm()">
          <span class="btn-icon">➕</span>
          New User
        </button>
      </div>
      
      <?php if (empty($users)): ?>
        <div class="empty-state">
          <div class="empty-icon">👥</div>
          <h3>No Users Yet</h3>
          <p>Create your first VPN user to get started</p>
          <button class="btn btn-primary" onclick="showCreateUserForm()">
            <span class="btn-icon">➕</span>
            Create First User
          </button>
        </div>
      <?php else: ?>
        <div class="users-grid">
          <?php foreach ($users as $user): ?>
            <div class="user-item">
              <div class="user-info">
                <h3><?= htmlspecialchars($user['username']) ?></h3>
                <div class="user-meta">
                  <span class="user-email"><?= htmlspecialchars($user['email'] ?? 'No email') ?></span>
                  <span class="user-status status-<?= $user['status'] ?>"><?= strtoupper($user['status']) ?></span>
                </div>
              </div>
              <div class="user-actions">
                <form method="post" action="/actions/user_download_ovpn.php" style="display: inline;">
                  <input type="hidden" name="csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                  <input type="hidden" name="username" value="<?= $user['username'] ?>">
                  <button type="submit" class="btn btn-secondary btn-sm">
                    <span class="btn-icon">⬇️</span>
                    Download
                  </button>
                </form>
                
                <?php if ($user['email'] && $user['status'] === 'active'): ?>
                  <form method="post" action="/actions/user_send_cert.php" style="display: inline;" onsubmit="return confirm('Send certificate via email?')">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                    <input type="hidden" name="username" value="<?= $user['username'] ?>">
                    <button type="submit" class="btn btn-success btn-sm">
                      <span class="btn-icon">📧</span>
                      Send Email
                    </button>
                  </form>
                <?php endif; ?>
                
                <form method="post" action="/actions/user_revoke_cert.php" style="display: inline;" onsubmit="return confirm('Revoke this certificate?')">
                  <input type="hidden" name="csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                  <input type="hidden" name="username" value="<?= $user['username'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">
                    <span class="btn-icon">🚫</span>
                    Revoke
                  </button>
                </form>
                
                <div id="emailNotification_<?= $user['username'] ?>" class="inline-notification" style="display: none;">
                  <span class="notification-icon">✅</span>
                  <span class="notification-text"></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Network & Connections Section -->
    <div class="dashboard-section">
      <div class="section-header">
        <h2>🌐 Network & Connections</h2>
        <button class="btn btn-secondary" onclick="refreshConnections()">
          <span class="btn-icon">🔄</span>
          Refresh Connections
        </button>
      </div>
      
      <div class="network-grid">
        <!-- Additional Subnets -->
        <div class="network-item">
          <h3>Additional Subnets</h3>
          <?php if (empty($nets)): ?>
            <div class="empty-mini">
              <span class="empty-icon">🌐</span>
              <p>No additional subnets</p>
            </div>
          <?php else: ?>
            <div class="subnets-list">
              <?php foreach ($nets as $net): ?>
                <div class="subnet-item">
                  <span class="subnet-cidr"><?= htmlspecialchars($net['subnet_cidr']) ?></span>
                  <form method="post" action="/actions/tenant_del_net.php" onsubmit="return confirm('Delete this subnet?')" style="display: inline;">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="id" value="<?= $net['id'] ?>">
                    <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-xs">
                      <span class="btn-icon">🗑️</span>
                    </button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <form method="post" action="/actions/tenant_add_net.php" class="add-subnet-form">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
            <div class="form-group">
              <input type="text" name="subnet" placeholder="ex. 10.21.0.0/26" required>
              <button type="submit" class="btn btn-primary btn-sm">
                <span class="btn-icon">➕</span>
                Add
              </button>
            </div>
          </form>
        </div>

        <!-- Active Connections -->
        <div class="network-item">
          <h3>Active Connections</h3>
          <?php if (empty($sessions)): ?>
            <div class="empty-mini">
              <span class="empty-icon">🌐</span>
              <p>No active connections</p>
            </div>
          <?php else: ?>
            <div class="connections-list">
              <?php foreach ($sessions as $session): ?>
                <div class="connection-item">
                  <div class="connection-info">
                    <span class="connection-name"><?= htmlspecialchars($session['common_name']) ?></span>
                    <span class="connection-ip"><?= htmlspecialchars($session['vpn_ip']) ?></span>
                  </div>
                  <div class="connection-location">
                    <?= htmlspecialchars($session['geo_country'] ?? 'Unknown') ?> • 
                    <?= htmlspecialchars($session['geo_city'] ?? 'Unknown City') ?>
                  </div>
                  <div class="connection-time">
                    <?= date('H:i', strtotime($session['last_seen'])) ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Client Users Section -->
    <div class="dashboard-section">
      <div class="section-header">
        <h2>👨‍💼 Client Users</h2>
        <button class="btn btn-primary" onclick="showCreateClientUserForm()">
          <span class="btn-icon">➕</span>
          New Client User
        </button>
      </div>
      
      <?php
      $clientUsers = $pdo->prepare("SELECT * FROM client_users WHERE tenant_id = ? ORDER BY created_at DESC");
      $clientUsers->execute([$tenant['id']]);
      $clientUsers = $clientUsers->fetchAll();
      ?>
      
      <?php if (empty($clientUsers)): ?>
        <div class="empty-state">
          <div class="empty-icon">👨‍💼</div>
          <h3>No Client Users</h3>
          <p>Create client users to allow self-management access</p>
          <button class="btn btn-primary" onclick="showCreateClientUserForm()">
            <span class="btn-icon">➕</span>
            Create First Client User
          </button>
        </div>
      <?php else: ?>
        <div class="client-users-grid">
          <?php foreach ($clientUsers as $clientUser): ?>
            <div class="client-user-item">
              <div class="client-user-info">
                <h3><?= htmlspecialchars($clientUser['username']) ?></h3>
                <div class="client-user-meta">
                  <span class="client-user-email"><?= htmlspecialchars($clientUser['email'] ?? 'No email') ?></span>
                  <span class="client-user-status status-<?= $clientUser['is_active'] ? 'active' : 'revoked' ?>">
                    <?= $clientUser['is_active'] ? 'ACTIVE' : 'INACTIVE' ?>
                  </span>
                </div>
                <div class="client-user-login-info">
                  <?php if ($clientUser['last_login']): ?>
                    <span class="login-time">Last login: <?= date('M j, Y H:i', strtotime($clientUser['last_login'])) ?></span>
                    <?php if ($clientUser['last_login_ip']): ?>
                      <span class="login-ip">from <?= htmlspecialchars($clientUser['last_login_ip']) ?></span>
                    <?php else: ?>
                      <span class="login-ip">IP: Not recorded</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="login-time">Never logged in</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="client-user-actions">
                <a href="/client/login.php?tenant=<?= $tenant['id'] ?>" class="btn btn-secondary btn-sm" target="_blank">
                  <span class="btn-icon">🔗</span>
                  Portal
                </a>
                
                <button type="button" class="btn btn-info btn-sm" onclick="showResetPasswordForm(<?= $clientUser['id'] ?>, '<?= htmlspecialchars($clientUser['username']) ?>')">
                  <span class="btn-icon">🔑</span>
                  Reset Password
                </button>
                
                <form method="post" action="/actions/client_user_toggle.php" style="display: inline;">
                  <input type="hidden" name="csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                  <input type="hidden" name="user_id" value="<?= $clientUser['id'] ?>">
                  <button type="submit" class="btn <?= $clientUser['is_active'] ? 'btn-warning' : 'btn-success' ?> btn-sm">
                    <span class="btn-icon"><?= $clientUser['is_active'] ? '⏸️' : '▶️' ?></span>
                    <?= $clientUser['is_active'] ? 'Deactivate' : 'Activate' ?>
                  </button>
                </form>
                
                <form method="post" action="/actions/client_user_delete.php" style="display: inline;" onsubmit="return confirm('Delete this client user?')">
                  <input type="hidden" name="csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                  <input type="hidden" name="user_id" value="<?= $clientUser['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">
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

  <!-- Create User Modal -->
  <div id="createUserModal" class="modal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Create New User</h2>
        <button class="modal-close" onclick="hideCreateUserForm()">&times;</button>
      </div>
      <form method="post" action="/actions/user_create_cert.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
        <div class="form-group">
          <label for="username">Username <span class="required">*</span></label>
          <input type="text" id="username" name="username" required placeholder="ex. dan.popescu">
        </div>
        <div class="form-group">
          <label for="email">Email <span class="required">*</span></label>
          <input type="email" id="email" name="email" required placeholder="user@example.com">
        </div>
        <div class="form-group">
          <label>
            <input type="checkbox" name="nopass" checked>
            No password required
          </label>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" onclick="hideCreateUserForm()">Cancel</button>
          <button type="submit" class="btn btn-primary">Create User</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Create Client User Modal -->
  <div id="createClientUserModal" class="modal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Create Client User</h2>
        <button class="modal-close" onclick="hideCreateClientUserForm()">&times;</button>
      </div>
      <form method="post" action="/actions/client_user_create.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
        <div class="form-group">
          <label for="client_username">Username <span class="required">*</span></label>
          <input type="text" id="client_username" name="username" required placeholder="ex. admin">
        </div>
        <div class="form-group">
          <label for="client_password">Password <span class="required">*</span></label>
          <input type="password" id="client_password" name="password" required placeholder="Enter password">
        </div>
        <div class="form-group">
          <label for="client_email">Email</label>
          <input type="email" id="client_email" name="email" placeholder="admin@example.com">
        </div>
        <div class="form-group">
          <label for="client_full_name">Full Name</label>
          <input type="text" id="client_full_name" name="full_name" placeholder="John Doe">
        </div>
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" onclick="hideCreateClientUserForm()">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Client User</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Reset Password Modal -->
  <div id="resetPasswordModal" class="modal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Reset Client Password</h2>
        <button class="modal-close" onclick="hideResetPasswordForm()">&times;</button>
      </div>
      <form method="post" action="/actions/client_user_reset_password.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
        <input type="hidden" name="user_id" id="resetUserId" value="">
        
        <div class="form-group">
          <label>Username</label>
          <input type="text" id="resetUsername" readonly style="background: #f3f4f6; color: #6b7280;">
        </div>
        
        <div class="form-group">
          <label for="new_password">New Password <span class="required">*</span></label>
          <input type="password" id="new_password" name="new_password" required 
                 placeholder="Enter new password">
        </div>
        
        <div class="form-group">
          <label for="confirm_new_password">Confirm New Password <span class="required">*</span></label>
          <input type="password" id="confirm_new_password" name="confirm_new_password" required 
                 placeholder="Confirm new password">
        </div>
        
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" onclick="hideResetPasswordForm()">Cancel</button>
          <button type="submit" class="btn btn-primary">Reset Password</button>
        </div>
      </form>
    </div>
  </div>
</main>

<script>
function showCreateUserForm() {
    document.getElementById('createUserModal').style.display = 'flex';
}

function hideCreateUserForm() {
    document.getElementById('createUserModal').style.display = 'none';
}

function showCreateClientUserForm() {
    document.getElementById('createClientUserModal').style.display = 'flex';
}

function hideCreateClientUserForm() {
    document.getElementById('createClientUserModal').style.display = 'none';
}

function showResetPasswordForm(userId, username) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUsername').value = username;
    document.getElementById('resetPasswordModal').style.display = 'flex';
}

function hideResetPasswordForm() {
    document.getElementById('resetPasswordModal').style.display = 'none';
    // Clear form
    document.getElementById('resetUserId').value = '';
    document.getElementById('resetUsername').value = '';
    document.getElementById('new_password').value = '';
    document.getElementById('confirm_new_password').value = '';
}

function refreshConnections() {
    location.reload();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const userModal = document.getElementById('createUserModal');
    const clientModal = document.getElementById('createClientUserModal');
    const resetModal = document.getElementById('resetPasswordModal');
    
    if (event.target === userModal) {
        userModal.style.display = 'none';
    }
    if (event.target === clientModal) {
        clientModal.style.display = 'none';
    }
    if (event.target === resetModal) {
        resetModal.style.display = 'none';
    }
}

// Handle inline notifications
function showInlineNotification(notificationId, message, isSuccess = true) {
    const notification = document.getElementById(notificationId);
    if (!notification) return;
    
    const icon = notification.querySelector('.notification-icon');
    const text = notification.querySelector('.notification-text');
    
    // Set message and icon
    text.textContent = message;
    icon.textContent = isSuccess ? '✅' : '❌';
    
    // Show notification
    notification.style.display = 'flex';
    notification.className = `inline-notification ${isSuccess ? 'success' : 'error'}`;
    
    // Hide after 3 seconds
    setTimeout(() => {
        notification.style.display = 'none';
    }, 3000);
}

// Handle NAT toggle notification
function showNatNotification(message, isSuccess = true) {
    showInlineNotification('natNotification', message, isSuccess);
}

// Handle email notification
function showEmailNotification(username, message, isSuccess = true) {
    showInlineNotification(`emailNotification_${username}`, message, isSuccess);
}

// Check for success/error messages on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const error = urlParams.get('error');
    const username = urlParams.get('username');
    
    if (success) {
        if (success.includes('NAT')) {
            showNatNotification(success, true);
        } else if (success.includes('email')) {
            if (username) {
                showEmailNotification(username, 'Email sent successfully', true);
            } else {
                showInlineNotification('natNotification', 'Email sent successfully', true);
            }
        } else if (success.includes('client_user')) {
            showInlineNotification('natNotification', 'Client user ' + success.replace('client_user_', ''), true);
        }
    } else if (error) {
        if (error.includes('NAT')) {
            showNatNotification(error, false);
        } else if (error.includes('email') || error.includes('PHPMailer')) {
            if (username) {
                showEmailNotification(username, 'Email failed: ' + error, false);
            } else {
                showInlineNotification('natNotification', 'Email failed: ' + error, false);
            }
        } else if (error.includes('client_user')) {
            showInlineNotification('natNotification', 'Client user error: ' + error, false);
        }
    }
    
    // Clean URL if we processed any messages
    if (success || error) {
        const newUrl = window.location.pathname + '?id=' + urlParams.get('id');
        window.history.replaceState({}, document.title, newUrl);
    }
    
    // Auto-hide notifications after 4 seconds
    setTimeout(function() {
        const notifications = document.querySelectorAll('.flash');
        notifications.forEach(function(notification) {
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-10px)';
            setTimeout(function() {
                notification.remove();
            }, 300);
        });
    }, 4000);
});

// Live Clock Functionality
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

updateLiveClock();
setInterval(updateLiveClock, 1000);
</script>
</body>
</html>
