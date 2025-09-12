<?php
require __DIR__ . '/../config.php';

use App\Auth;
use App\DB;

Auth::require();

$pdo = DB::pdo();
$csrf = Auth::csrf();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::verifyCsrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_admin':
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if (!$email || !$password || !$confirmPassword) {
                    $error = 'All fields are required';
                } elseif ($password !== $confirmPassword) {
                    $error = 'Passwords do not match';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Invalid email address';
                } else {
                    // Check if user already exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = 'User with this email already exists';
                    } else {
                        // Create new admin user
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, NOW())");
                        $stmt->execute([$email, $hashedPassword]);
                        $message = "Admin user created successfully!";
                    }
                }
                break;
                
            case 'delete_admin':
                $userId = (int)($_POST['user_id'] ?? 0);
                $currentUserId = $_SESSION['uid'] ?? 0;
                
                if ($userId === $currentUserId) {
                    $error = 'You cannot delete your own account';
                } elseif ($userId > 0) {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $message = "Admin user deleted successfully!";
                } else {
                    $error = 'Invalid user ID';
                }
                break;
        }
    } catch (\Throwable $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get all admin users
$users = $pdo->query("SELECT id, email, created_at FROM users ORDER BY created_at DESC")->fetchAll();
$currentUserId = $_SESSION['uid'] ?? 0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Settings - OpenVPN Manager</title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/client.css?v=<?= time() ?>">
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

    <main>
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">✅</span>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span class="alert-icon">❌</span>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Admin Users Management -->
            <div class="card">
                <div class="card-header">
                    <h2>👥 Admin Users</h2>
                    <div class="card-actions">
                        <button class="btn btn-primary" onclick="showAddAdminForm()">
                            <span class="btn-icon">➕</span>
                            Add Admin User
                        </button>
                    </div>
                </div>
                
                <div class="card-content">
                    <?php if (empty($users)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">👥</div>
                            <h3>No Admin Users</h3>
                            <p>Add your first admin user to get started</p>
                        </div>
                    <?php else: ?>
                        <div class="users-list">
                            <?php foreach ($users as $user): ?>
                                <div class="user-item">
                                    <div class="user-info">
                                        <div class="user-name"><?= htmlspecialchars($user['email']) ?></div>
                                        <div class="user-email">Created: <?= date('M j, Y', strtotime($user['created_at'])) ?></div>
                                    </div>
                                    <div class="user-actions">
                                        <?php if ($user['id'] === $currentUserId): ?>
                                            <span class="status-badge status-active">Current User</span>
                                        <?php else: ?>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this admin user?')">
                                                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                                <input type="hidden" name="action" value="delete_admin">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <span class="btn-icon">🗑️</span>
                                                    Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Admin User Modal -->
    <div id="addAdminModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Admin User</h2>
                <button class="modal-close" onclick="hideAddAdminForm()">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="add_admin">
                
                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required 
                           placeholder="admin@example.com">
                </div>
                
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter password">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Confirm password">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="hideAddAdminForm()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Admin User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddAdminForm() {
            document.getElementById('addAdminModal').style.display = 'flex';
        }

        function hideAddAdminForm() {
            document.getElementById('addAdminModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addAdminModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }

        // Auto-hide messages
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            });
        }, 5000);
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
    </script>
</body>
</html>
