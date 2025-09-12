<?php
require_once __DIR__.'/../../config.php';
require_once __DIR__.'/../../lib/ClientAuth.php';

use App\ClientAuth;
use App\DB;

ClientAuth::require();

$user = ClientAuth::getCurrentUser();
$tenantId = ClientAuth::getCurrentTenantId();
$pdo = DB::pdo();

// Get tenant info
$stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch();

// Handle success/error messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Get VPN users for this tenant
$stmt = $pdo->prepare("SELECT * FROM vpn_users WHERE tenant_id = ? ORDER BY id DESC");
$stmt->execute([$tenantId]);
$vpnUsers = $stmt->fetchAll();

// Get active sessions
$stmt = $pdo->prepare("SELECT * FROM sessions WHERE tenant_id = ? AND last_seen >= DATE_SUB(NOW(), INTERVAL 1 HOUR) ORDER BY last_seen DESC");
$stmt->execute([$tenantId]);
$activeSessions = $stmt->fetchAll();

// Get statistics
$totalUsers = count($vpnUsers);
$activeUsers = count($activeSessions);

// Get recent activity
$stmt = $pdo->prepare("
    SELECT s.*, vu.username 
    FROM sessions s 
    JOIN vpn_users vu ON s.user_id = vu.id 
    WHERE s.tenant_id = ? 
    ORDER BY s.last_seen DESC 
    LIMIT 10
");
$stmt->execute([$tenantId]);
$recentActivity = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VPN Users - Client Portal</title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/client.css?v=<?= time() ?>">
</head>
<body>
    <!-- Client Header -->
    <div class="client-header">
        <div class="client-header-content">
            <div class="client-title">
                <h1><?= htmlspecialchars($tenant['name']) ?> - VPN Users</h1>
                <div class="client-meta">
                    <span class="client-welcome">Welcome, <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?>!</span>
                    <span class="client-tenant">Users Portal</span>
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
                <a href="/client/dashboard.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">🏠</span>
                    Dashboard
                </a>
                <a href="/client/analytics.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">📊</span>
                    Analytics
                </a>
                <a href="/client/settings.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">⚙️</span>
                    Settings
                </a>
                <a href="/client/logout.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">🚪</span>
                    Logout
                </a>
            </div>
        </div>
    </div>

    <div class="client-container">

        <!-- Success/Error Messages -->
        <?php if ($success === 'user_revoked'): ?>
            <div class="alert alert-success" style="margin: 20px; padding: 12px 16px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; color: #10b981;">
                ✅ User certificate revoked successfully!
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin: 20px; padding: 12px 16px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; color: #ef4444;">
                ❌ Error: <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Main Content -->
        <main class="client-main">
            <!-- Integrated Users Dashboard -->
            <div class="integrated-dashboard">
                
                <!-- Overview Section -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>👥 VPN Profile Users Overview</h2>
                        <div class="user-info">
                            <span class="user-name"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></span>
                            <span class="user-tenant"><?= htmlspecialchars($tenant['name']) ?></span>
                        </div>
                    </div>
                    
                    <div class="config-row">
                        <div class="config-field">
                            <label>Total Users</label>
                            <span class="config-value"><?= $totalUsers ?></span>
                        </div>
                        
                        <div class="config-field">
                            <label>Active Users</label>
                            <span class="config-value"><?= $activeUsers ?></span>
                        </div>
                        
                        <div class="config-field">
                            <label>Tenant</label>
                            <span class="config-value"><?= htmlspecialchars($tenant['name']) ?></span>
                        </div>
                        
                        <div class="config-field">
                            <label>Server IP</label>
                            <span class="config-value"><?= htmlspecialchars($tenant['public_ip']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- VPN Users List -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>🔐 VPN Profile Users</h2>
                        <div class="section-actions">
                            <button onclick="showCreateUserForm()" class="btn btn-primary">
                                <span class="btn-icon">➕</span>
                                Create New User
                            </button>
                        </div>
                    </div>
                    
                    <div class="users-container">
                        <?php if (empty($vpnUsers)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">👤</div>
                                <h3>No VPN Users</h3>
                                <p>No VPN users have been created for this tenant yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="users-list">
                                <?php foreach ($vpnUsers as $vpnUser): ?>
                                    <?php
                                    // Check if user is currently active
                                    $isActive = false;
                                    $lastSeen = null;
                                    foreach ($activeSessions as $session) {
                                        if ($session['user_id'] == $vpnUser['id']) {
                                            $isActive = true;
                                            $lastSeen = $session['last_seen'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <div class="user-card-compact">
                                        <div class="user-main-info">
                                            <div class="user-avatar-small">
                                                <span class="avatar-icon">👤</span>
                                            </div>
                                            <div class="user-details-compact">
                                                <div class="user-name-row">
                                                    <h4 class="user-name"><?= htmlspecialchars($vpnUser['username']) ?></h4>
                                                    <span class="status-badge-small <?= $isActive ? 'status-online' : 'status-offline' ?>">
                                                        <?= $isActive ? 'ONLINE' : 'OFFLINE' ?>
                                                    </span>
                                                </div>
                                                <p class="user-email"><?= htmlspecialchars($vpnUser['email'] ?? 'No email') ?></p>
                                                <div class="user-meta-compact">
                                                    <span class="meta-item">Created: <?= date('M j, Y', strtotime($vpnUser['created_at'])) ?></span>
                                                    <span class="meta-item">ID: #<?= $vpnUser['id'] ?></span>
                                                    <?php if ($lastSeen): ?>
                                                        <span class="meta-item">Last seen: <?= date('M j, g:i A', strtotime($lastSeen)) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="user-actions-compact">
                                            <button class="btn btn-secondary btn-sm" onclick="downloadConfig(<?= $vpnUser['id'] ?>)">
                                                <span class="btn-icon">📥</span>
                                                Download
                                            </button>
                                            <button class="btn btn-primary btn-sm" onclick="sendEmail(<?= $vpnUser['id'] ?>)">
                                                <span class="btn-icon">📧</span>
                                                Send Email
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="revokeUser(<?= $vpnUser['id'] ?>, '<?= htmlspecialchars($vpnUser['username']) ?>')">
                                                <span class="btn-icon">🚫</span>
                                                Revoke
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>📊 Recent Activity</h2>
                    </div>
                    
                    <div class="activity-container">
                        <?php if (empty($recentActivity)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">📊</div>
                                <h3>No Recent Activity</h3>
                                <p>No recent VPN connections have been recorded.</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <span class="icon">🔗</span>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-header">
                                                <span class="username"><?= htmlspecialchars($activity['username']) ?></span>
                                                <span class="activity-time"><?= date('M j, g:i A', strtotime($activity['last_seen'])) ?></span>
                                            </div>
                                            <div class="activity-details">
                                                <span class="ip-address"><?= htmlspecialchars($activity['real_address']) ?></span>
                                                <?php if ($activity['geo_country']): ?>
                                                    <span class="country"><?= htmlspecialchars($activity['geo_country']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="activity-status">
                                            <span class="status-badge status-online">ACTIVE</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function downloadConfig(userId) {
            // Create a form to download the config
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/actions/client_user_download_ovpn.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id';
            input.value = userId;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        function sendEmail(userId) {
            // Create a form to send email
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/actions/client_user_send_cert.php';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id';
            input.value = userId;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        function revokeUser(userId, username) {
            if (confirm(`Are you sure you want to revoke the certificate for user "${username}"? This action cannot be undone.`)) {
                // Create a form to revoke user
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/actions/client_user_revoke_cert.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'id';
                input.value = userId;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }
        }

        // Auto-hide success/error messages
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });

        // Create User Modal Functions
        function showCreateUserForm() {
            document.getElementById('createUserModal').style.display = 'flex';
        }

        function hideCreateUserForm() {
            document.getElementById('createUserModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('createUserModal');
            if (event.target === modal) {
                hideCreateUserForm();
            }
        }
    </script>

    <!-- Create User Modal -->
    <div id="createUserModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New VPN User</h2>
                <button class="modal-close" onclick="hideCreateUserForm()">&times;</button>
            </div>
            <form method="post" action="/actions/client_user_create_cert.php">
                <input type="hidden" name="tenant_id" value="<?= $tenantId ?>">
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

        updateLiveClock();
        setInterval(updateLiveClock, 1000);
    </script>
    
    <!-- Client Activity Tracker -->
    <script src="/assets/client-activity.js?v=<?= time() ?>"></script>
</body>
</html>
