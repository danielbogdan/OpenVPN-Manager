<?php
require __DIR__ . '/../config.php';
use App\Auth;
use App\DB;

Auth::start();

// dacă nu există niciun utilizator, mergem la wizardul de setup
try {
    $hasUser = (int)DB::pdo()->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0;
} catch (Throwable $e) {
    http_response_code(503);
    echo "Baza de date nu este gata. Reîncearcă peste câteva secunde.";
    exit;
}
if (!$hasUser) {
    header('Location: /setup.php');
    exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $pass  = $_POST['password'] ?? '';

    if (Auth::login($email, $pass)) {
        header('Location: /dashboard.php');
        exit;
    } else {
        $err = 'Email sau parolă greșită.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login - OpenVPN Manager</title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
    <style>
        .admin-login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0b1220 0%, #1a2332 50%, #0f172a 100%);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .admin-login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 80%, rgba(59, 130, 246, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(16, 185, 129, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .admin-login-card {
            background: rgba(31, 41, 55, 0.9);
            border: 1px solid #374151;
            border-radius: 20px;
            padding: 48px;
            max-width: 450px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 1;
        }
        
        .admin-login-header {
            margin-bottom: 32px;
        }
        
        .admin-login-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }
        
        .admin-logo-icon {
            font-size: 4rem;
            background: linear-gradient(135deg, #3b82f6, #10b981, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 0 20px rgba(59, 130, 246, 0.3));
        }
        
        .admin-login-title {
            color: #f9fafb;
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0;
            background: linear-gradient(135deg, #f9fafb, #d1d5db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .admin-login-subtitle {
            color: #9ca3af;
            font-size: 1.125rem;
            margin: 8px 0 0 0;
            font-weight: 500;
        }
        
        .admin-error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            color: #ef4444;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .admin-error-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .admin-login-form {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .admin-form-group {
            text-align: left;
        }
        
        .admin-form-group label {
            display: block;
            color: #f9fafb;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .admin-form-group input {
            width: 100%;
            padding: 16px 20px;
            background: rgba(17, 24, 39, 0.8);
            border: 2px solid #374151;
            border-radius: 12px;
            color: #f9fafb;
            font-size: 1rem;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        
        .admin-form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            background: rgba(17, 24, 39, 0.9);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .admin-form-group input::placeholder {
            color: #6b7280;
        }
        
        .admin-login-btn {
            width: 100%;
            padding: 18px 24px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1.125rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 8px;
        }
        
        .admin-login-btn:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }
        
        .admin-login-btn:active {
            transform: translateY(0);
        }
        
        .admin-btn-icon {
            font-size: 1.25rem;
        }
        
        .admin-login-footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #374151;
        }
        
        .admin-login-footer p {
            color: #9ca3af;
            margin: 0 0 16px 0;
            font-size: 0.875rem;
        }
        
        .admin-client-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #10b981;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .admin-client-link:hover {
            color: #059669;
            transform: translateY(-1px);
        }
        
        .admin-link-icon {
            font-size: 1rem;
        }
        
        .admin-security-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 8px;
            padding: 8px 12px;
            color: #10b981;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        @media (max-width: 640px) {
            .admin-login-card {
                padding: 32px 24px;
                margin: 16px;
            }
            
            .admin-login-title {
                font-size: 2rem;
            }
            
            .admin-logo-icon {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-login-card">
            <div class="admin-security-badge">
                🔒 Secure
            </div>
            
            <div class="admin-login-header">
                <div class="admin-login-logo">
                    <div class="admin-logo-icon">⚙️</div>
                    <h1 class="admin-login-title">Admin Panel</h1>
                    <p class="admin-login-subtitle">OpenVPN Management System</p>
                </div>
            </div>
            
            <?php if ($err): ?>
                <div class="admin-error-message">
                    <span class="admin-error-icon">❌</span>
                    <?= htmlspecialchars($err) ?>
                </div>
            <?php endif; ?>
            
            <form method="post" class="admin-login-form">
                <div class="admin-form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="admin@example.com">
                </div>
                
                <div class="admin-form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Enter your password">
                </div>
                
                <button type="submit" class="admin-login-btn">
                    <span class="admin-btn-icon">🚀</span>
                    Access Admin Panel
                </button>
            </form>
            
            <div class="admin-login-footer">
                <p>Authorized personnel only</p>
                <a href="/client/login.php" class="admin-client-link">
                    <span class="admin-link-icon">👤</span>
                    Client Portal
                </a>
            </div>
        </div>
    </div>
</body>
</html>
