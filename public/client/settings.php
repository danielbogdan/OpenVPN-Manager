<?php
require_once __DIR__.'/../../config.php';
require_once __DIR__.'/../../lib/ClientAuth.php';

use App\ClientAuth;

ClientAuth::require();

$error = '';
$success = '';

// Get current user info
$pdo = \App\DB::pdo();
$stmt = $pdo->prepare("
    SELECT cu.*, t.name as tenant_name 
    FROM client_users cu 
    JOIN tenants t ON cu.tenant_id = t.id 
    WHERE cu.id = ?
");
$stmt->execute([$_SESSION['client_user_id']]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: /client/logout.php');
    exit;
}

// Create tenant array for consistency with other pages
$tenant = [
    'name' => $user['tenant_name']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if ($fullName && $email) {
            try {
                $stmt = $pdo->prepare("UPDATE client_users SET full_name = ?, email = ? WHERE id = ?");
                $stmt->execute([$fullName, $email, $user['id']]);
                $success = 'Profile updated successfully!';
                
                // Update session data
                $_SESSION['client_full_name'] = $fullName;
                
                // Refresh user data
                $stmt = $pdo->prepare("
                    SELECT cu.*, t.name as tenant_name 
                    FROM client_users cu 
                    JOIN tenants t ON cu.tenant_id = t.id 
                    WHERE cu.id = ?
                ");
                $stmt->execute([$_SESSION['client_user_id']]);
                $user = $stmt->fetch();
            } catch (\Exception $e) {
                $error = 'Failed to update profile: ' . $e->getMessage();
            }
        } else {
            $error = 'Please fill in all required fields';
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if ($currentPassword && $newPassword && $confirmPassword) {
            if (!password_verify($currentPassword, $user['password_hash'])) {
                $error = 'Current password is incorrect';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match';
            } elseif (strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters long';
            } else {
                try {
                    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE client_users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$newPasswordHash, $user['id']]);
                    $success = 'Password changed successfully!';
                } catch (\Exception $e) {
                    $error = 'Failed to change password: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'Please fill in all password fields';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Settings - Client Portal</title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/client.css?v=<?= time() ?>">
</head>
<body>
    <!-- Client Header -->
    <div class="client-header">
        <div class="client-header-content">
            <div class="client-title">
                <h1><?= htmlspecialchars($tenant['name']) ?> - Settings</h1>
                <div class="client-meta">
                    <span class="client-welcome">Welcome, <?= htmlspecialchars($user['full_name'] ?: $user['username']) ?>!</span>
                    <span class="client-tenant">Settings Portal</span>
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
                <a href="/client/users.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">👥</span>
                    Users
                </a>
                <a href="/client/logout.php" class="client-btn client-btn-secondary">
                    <span class="btn-icon">🚪</span>
                    Logout
                </a>
            </div>
        </div>
    </div>

    <div class="client-container">

        <!-- Main Content -->
        <main class="client-main">
            <!-- Integrated Settings Dashboard -->
            <div class="integrated-dashboard">
                
                <!-- User Profile Section -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>👤 User Profile</h2>
                        <div class="user-info">
                            <span class="user-name"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></span>
                            <span class="user-tenant"><?= htmlspecialchars($user['tenant_name']) ?></span>
                        </div>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <span class="alert-icon">❌</span>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <span class="alert-icon">✅</span>
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" class="settings-form">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="config-row">
                            <div class="config-field">
                                <label>Username</label>
                                <span class="config-value"><?= htmlspecialchars($user['username']) ?></span>
                                <small>Username cannot be changed</small>
                            </div>
                            
                            <div class="config-field">
                                <label>Tenant</label>
                                <span class="config-value"><?= htmlspecialchars($user['tenant_name']) ?></span>
                                <small>Your assigned tenant</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" required 
                                       value="<?= htmlspecialchars($user['full_name'] ?: '') ?>"
                                       placeholder="Enter your full name">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" required 
                                       value="<?= htmlspecialchars($user['email'] ?: '') ?>"
                                       placeholder="Enter your email address">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <span class="btn-icon">💾</span>
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password Section -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>🔒 Change Password</h2>
                    </div>
                    
                    <form method="post" class="settings-form">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-row single-field">
                            <div class="form-group">
                                <label for="current_password">Current Password *</label>
                                <input type="password" id="current_password" name="current_password" required 
                                       placeholder="Enter your current password">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password *</label>
                                <input type="password" id="new_password" name="new_password" required 
                                       placeholder="Enter your new password" minlength="6">
                                <small>Password must be at least 6 characters long</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" required 
                                       placeholder="Confirm your new password" minlength="6">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <span class="btn-icon">🔑</span>
                                Change Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Account Information Section -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>ℹ️ Account Information</h2>
                    </div>
                    
                    <div class="config-row">
                        <div class="config-field">
                            <label>Account Created</label>
                            <span class="config-value"><?= date('F j, Y', strtotime($user['created_at'])) ?></span>
                        </div>
                        
                        <div class="config-field">
                            <label>Last Login</label>
                            <span class="config-value"><?= $user['last_login'] ? date('F j, Y g:i A', strtotime($user['last_login'])) : 'Never' ?></span>
                        </div>
                        
                        <div class="config-field">
                            <label>Account Status</label>
                            <span class="status-badge <?= $user['is_active'] ? 'status-online' : 'status-offline' ?>">
                                <?= $user['is_active'] ? 'ACTIVE' : 'INACTIVE' ?>
                            </span>
                        </div>
                        
                        <div class="config-field">
                            <label>User ID</label>
                            <span class="config-value"><?= $user['id'] ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-hide notifications after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            
            alerts.forEach(function(alert) {
                // Show the alert with a fade-in effect
                alert.style.opacity = '0';
                alert.style.transform = 'translateX(-50%) translateY(-20px)';
                alert.style.transition = 'all 0.3s ease';
                
                // Trigger the animation
                setTimeout(function() {
                    alert.style.opacity = '1';
                    alert.style.transform = 'translateX(-50%) translateY(0)';
                }, 100);
                
                // Auto-hide after 5 seconds
                setTimeout(function() {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateX(-50%) translateY(-20px)';
                    
                    // Remove from DOM after animation
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
