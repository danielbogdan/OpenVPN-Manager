<?php
require __DIR__ . '/../../config.php';

use App\ClientAuth;
use App\DB;

// Redirect if already logged in
if (ClientAuth::isLoggedIn()) {
    header('Location: /client/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        if (ClientAuth::login($username, $password)) {
            header('Location: /client/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Please fill in all fields';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Client Login - OpenVPN Admin</title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/assets/client.css?v=<?= time() ?>">
</head>
<body class="client-login">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <span class="logo-icon">🔐</span>
                    <h1>Client Portal</h1>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <span class="error-icon">❌</span>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="post" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           placeholder="Enter your username">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="login-btn">
                    <span class="btn-icon">🚀</span>
                    Sign In
                </button>
            </form>
            
            <div class="login-footer">
                <p>Need help? Contact your administrator</p>
            </div>
        </div>
    </div>
</body>
</html>
