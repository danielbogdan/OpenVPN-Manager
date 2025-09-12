<?php
require __DIR__ . '/../../config.php';

use App\ClientAuth;
use App\DB;
use App\OpenVPNManager;

ClientAuth::require();

$user = ClientAuth::getCurrentUser();
$tenantId = ClientAuth::getCurrentTenantId();
$pdo = DB::pdo();

// Refresh sessions to get latest connection data
try {
    OpenVPNManager::refreshSessions($tenantId);
} catch (\Throwable $e) {
    // Log error but don't break the page
    error_log("Failed to refresh sessions for tenant {$tenantId}: " . $e->getMessage());
}

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
$totalSessions = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE tenant_id = ?");
$totalSessions->execute([$tenantId]);
$totalSessions = $totalSessions->fetchColumn();

$countries = $pdo->prepare("SELECT COUNT(DISTINCT geo_country) FROM sessions WHERE tenant_id = ? AND geo_country IS NOT NULL");
$countries->execute([$tenantId]);
$countries = $countries->fetchColumn();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Client Dashboard - <?= htmlspecialchars($tenant['name']) ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/client.css?v=<?= time() ?>">
</head>
<body>
    <!-- Client Header -->
    <div class="client-header">
        <div class="client-header-content">
            <div class="client-title">
                <h1><?= htmlspecialchars($tenant['name']) ?></h1>
                <div class="client-meta">
                    <span class="client-welcome">Welcome, <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?>!</span>
                    <span class="client-tenant">Client Portal</span>
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
                <a href="/client/analytics.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">📊</span>
                    Analytics
                </a>
                <a href="/client/users.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">👥</span>
                    Users
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

    <!-- Main Dashboard Container -->
    <div class="client-container">
        <!-- Success/Error Messages -->
        <?php if ($success === 'user_created'): ?>
            <div class="alert alert-success">
                ✅ VPN user created successfully!
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                ❌ Error: <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <div class="stat-value"><?= $totalUsers ?></div>
                    <div class="stat-label">Total VPN Profile Users</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🌐</div>
                <div class="stat-content">
                    <div class="stat-value"><?= $activeUsers ?></div>
                    <div class="stat-label">Active Sessions</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🌍</div>
                <div class="stat-content">
                    <div class="stat-value"><?= $countries ?></div>
                    <div class="stat-label">Countries</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <div class="stat-value"><?= $totalSessions ?></div>
                    <div class="stat-label">Total Sessions</div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="dashboard-layout">
            <!-- Top Row: VPN Users and Quick Actions -->
            <div class="dashboard-row">
                <!-- VPN Users Overview -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <span class="card-icon">👥</span>
                            VPN Profile Users
                        </h3>
                        <div class="card-actions">
                            <a href="/client/users.php" class="btn btn-primary btn-sm">
                                <span class="btn-icon">➕</span>
                                Manage Users
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-content">
                        <?php if (empty($vpnUsers)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">👥</div>
                                <p class="empty-text">No VPN users configured yet</p>
                                <button onclick="showCreateUserForm()" class="btn btn-primary">
                                    <span class="btn-icon">➕</span>
                                    Create First User
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="users-list">
                                <?php foreach (array_slice($vpnUsers, 0, 5) as $vpnUser): ?>
                                    <div class="user-item">
                                        <div class="user-name"><?= htmlspecialchars($vpnUser['username']) ?></div>
                                        <div class="user-email"><?= htmlspecialchars($vpnUser['email'] ?? 'No email') ?></div>
                                        <span class="status-badge <?= $vpnUser['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                            <?= strtoupper($vpnUser['status']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($vpnUsers) > 5): ?>
                                    <div class="view-all">
                                        <a href="/client/users.php" class="btn btn-secondary btn-sm">
                                            View All Users (<?= count($vpnUsers) ?>)
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <span class="card-icon">⚡</span>
                            Quick Actions
                        </h3>
                    </div>
                    
                    <div class="card-content">
                        <div class="quick-actions">
                            <a href="/client/users.php" class="action-btn action-primary">
                                <span class="action-icon">👥</span>
                                <span class="action-text">Manage VPN Users</span>
                            </a>
                            <a href="/client/analytics.php" class="action-btn action-secondary">
                                <span class="action-icon">📊</span>
                                <span class="action-text">View Analytics</span>
                            </a>
                            <a href="/client/settings.php" class="action-btn action-secondary">
                                <span class="action-icon">⚙️</span>
                                <span class="action-text">Account Settings</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row: Active Connections -->
            <div class="dashboard-row">
                <!-- Active Connections -->
                <div class="dashboard-card full-width">
                    <div class="card-header">
                        <h3 class="card-title">
                            <span class="card-icon">🌐</span>
                            Active Connections
                        </h3>
                        <div class="card-actions">
                            <button onclick="location.reload()" class="btn btn-secondary btn-sm">
                                <span class="btn-icon">🔄</span>
                                Refresh
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-content">
                        <?php if (empty($activeSessions)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">🌐</div>
                                <p class="empty-text">No active connections</p>
                            </div>
                        <?php else: ?>
                            <div class="connections-list">
                                <?php foreach ($activeSessions as $session): ?>
                                    <div class="connection-item">
                                        <div class="connection-icon">🌐</div>
                                        <div class="connection-info">
                                            <div class="connection-name"><?= htmlspecialchars($session['common_name']) ?></div>
                                            <div class="connection-location">
                                                <?= htmlspecialchars($session['geo_country'] ?? 'Unknown') ?> • 
                                                <?= htmlspecialchars($session['geo_city'] ?? 'Unknown City') ?>
                                            </div>
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
        </div>
    </div>

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

    <script>
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
    
    <!-- Client Activity Tracker -->
    <script src="/assets/client-activity.js?v=<?= time() ?>"></script>
</body>
</html>
