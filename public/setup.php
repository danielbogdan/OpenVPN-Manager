<?php
require __DIR__.'/../config.php';
use App\DB;

$err = '';
$ok  = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');
  $pass2 = (string)($_POST['password2'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = 'Please enter a valid email address.';
  } elseif (strlen($pass) < 8) {
    $err = 'Password must be at least 8 characters long.';
  } elseif ($pass !== $pass2) {
    $err = 'Passwords do not match.';
  } else {
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $pdo  = DB::pdo();
    $pdo->beginTransaction();
    try {
      $exists = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
      if ($exists > 0) {
        $err = 'Administrator account already exists. You will be redirected to login page in 3 seconds.';
      } else {
        $stmt = $pdo->prepare("INSERT INTO users(email,password_hash) VALUES (?,?)");
        $stmt->execute([$email, $hash]);
        $pdo->commit();
        $ok = 'Administrator account created successfully! You will be redirected to login page in 5 seconds.';
      }
    } catch (Throwable $e) {
      $pdo->rollBack();
      $err = 'Database error: '.$e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OpenVPN Manager · Initial Setup</title>
  <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>">
  <link rel="stylesheet" href="/assets/client.css?v=<?= time() + 1 ?>">
  <style>
    body {
      background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .setup-container {
      background: rgba(30, 41, 59, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(71, 85, 105, 0.3);
      border-radius: 16px;
      padding: 32px 48px 48px 48px;
      width: 100%;
      max-width: 480px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
      margin: 20px;
      position: relative;
      z-index: 1;
    }
    
    .setup-header {
      text-align: center;
      margin-bottom: 32px;
      margin-top: 0;
    }
    
    .setup-header h1 {
      color: #f8fafc;
      font-size: 2rem;
      font-weight: 700;
      margin: 0 0 8px 0;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
    }
    
    .setup-header .icon {
      font-size: 2.5rem;
    }
    
    .setup-header p {
      color: #94a3b8;
      font-size: 1rem;
      margin: 0;
    }
    
    .form-group {
      margin-bottom: 24px;
    }
    
    .form-group label {
      display: block;
      color: #e2e8f0;
      font-weight: 500;
      margin-bottom: 8px;
      font-size: 0.9rem;
    }
    
    .form-group input {
      width: 100%;
      padding: 12px 16px;
      background: rgba(15, 23, 42, 0.8);
      border: 1px solid rgba(71, 85, 105, 0.5);
      border-radius: 8px;
      color: #f8fafc;
      font-size: 1rem;
      transition: all 0.3s ease;
      box-sizing: border-box;
    }
    
    .form-group input:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .form-group input::placeholder {
      color: #64748b;
    }
    
    .btn-setup {
      width: 100%;
      padding: 14px 24px;
      background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
      border: none;
      border-radius: 8px;
      color: white;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    
    .btn-setup:hover {
      background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
      transform: translateY(-1px);
      box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.4);
    }
    
    .btn-setup:active {
      transform: translateY(0);
    }
    
    .alert {
      padding: 16px;
      border-radius: 8px;
      margin: 0 0 24px 0;
      font-size: 0.9rem;
      display: flex;
      align-items: flex-start;
      gap: 8px;
      word-wrap: break-word;
      overflow-wrap: break-word;
      line-height: 1.4;
      width: 100%;
      box-sizing: border-box;
      position: relative;
      z-index: 2;
      min-height: auto;
    }
    
    .alert-error {
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.3);
      color: #fca5a5;
      flex-wrap: wrap;
      left: auto;
      right: auto;
      margin-left: 0;
      margin-right: 0;
      text-align: left;
    }
    
    .alert-success {
      background: rgba(34, 197, 94, 0.1);
      border: 1px solid rgba(34, 197, 94, 0.3);
      color: #86efac;
      flex-wrap: wrap;
      left: auto;
      right: auto;
      margin-left: 0;
      margin-right: 0;
      text-align: left;
    }
    
    .alert a {
      color: #3b82f6;
      text-decoration: none;
      font-weight: 500;
      white-space: nowrap;
    }
    
    .alert a:hover {
      text-decoration: underline;
    }
    
    .alert-success {
      flex-wrap: wrap;
    }
    
    .alert-success span:last-child,
    .alert-error span:last-child {
      flex: 1;
      min-width: 0;
      overflow-wrap: break-word;
      word-wrap: break-word;
      white-space: normal;
    }
    
    .setup-footer {
      text-align: center;
      margin-top: 32px;
      padding-top: 24px;
      border-top: 1px solid rgba(71, 85, 105, 0.3);
    }
    
    .setup-footer p {
      color: #64748b;
      font-size: 0.85rem;
      margin: 0;
    }
    
    @media (max-width: 640px) {
      .setup-container {
        margin: 16px;
        padding: 24px 24px 32px 24px;
      }
      
      .setup-header h1 {
        font-size: 1.75rem;
      }
      
      .alert {
        padding: 12px;
        font-size: 0.85rem;
        margin: 0 0 20px 0;
      }
      
      .alert-success,
      .alert-error {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }
    }
  </style>
</head>
<body>
  <div class="setup-container">
    <?php if($err): ?>
      <div class="alert alert-error">
        <span>⚠️</span>
        <span><?= htmlspecialchars($err) ?></span>
      </div>
    <?php endif; ?>
    
    <?php if($ok): ?>
      <div class="alert alert-success">
        <span>✅</span>
        <span><?= htmlspecialchars($ok) ?></span>
      </div>
    <?php endif; ?>
    
    <div class="setup-header">
      <h1>
        <span class="icon">🚀</span>
        OpenVPN Manager
      </h1>
      <p>Initial Setup - Create Administrator Account</p>
    </div>
    
    <form method="post">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input 
          type="email" 
          id="email" 
          name="email" 
          placeholder="admin@example.com"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          required
        >
      </div>
      
      <div class="form-group">
        <label for="password">Password</label>
        <input 
          type="password" 
          id="password" 
          name="password" 
          placeholder="Minimum 8 characters"
          required
          minlength="8"
        >
      </div>
      
      <div class="form-group">
        <label for="password2">Confirm Password</label>
        <input 
          type="password" 
          id="password2" 
          name="password2" 
          placeholder="Repeat your password"
          required
          minlength="8"
        >
      </div>
      
      <button type="submit" class="btn-setup">
        <span>🔐</span>
        <span>Create Administrator</span>
      </button>
    </form>
    
    <div class="setup-footer">
      <p>This will create the first administrator account for your OpenVPN Manager instance.</p>
    </div>
  </div>
  
  <script>
    // Password confirmation validation and auto-redirect
    document.addEventListener('DOMContentLoaded', function() {
      const password = document.getElementById('password');
      const password2 = document.getElementById('password2');
      const form = document.querySelector('form');
      
      function validatePasswords() {
        if (password.value && password2.value) {
          if (password.value !== password2.value) {
            password2.setCustomValidity('Passwords do not match');
            password2.style.borderColor = '#ef4444';
          } else {
            password2.setCustomValidity('');
            password2.style.borderColor = 'rgba(71, 85, 105, 0.5)';
          }
        }
      }
      
      password.addEventListener('input', validatePasswords);
      password2.addEventListener('input', validatePasswords);
      
      // Form submission validation
      form.addEventListener('submit', function(e) {
        if (password.value !== password2.value) {
          e.preventDefault();
          password2.focus();
          return false;
        }
      });
      
      // Auto-focus on email field
      document.getElementById('email').focus();
      
      // Auto-redirect functionality
      <?php if($err): ?>
        // Redirect to login after 3 seconds for error (admin exists)
        let countdown = 3;
        const errorAlert = document.querySelector('.alert-error span:last-child');
        const originalText = errorAlert.textContent;
        
        const countdownInterval = setInterval(function() {
          countdown--;
          if (countdown > 0) {
            errorAlert.textContent = originalText.replace('3 seconds', countdown + ' second' + (countdown === 1 ? '' : 's'));
          } else {
            clearInterval(countdownInterval);
            window.location.href = '/login.php';
          }
        }, 1000);
      <?php endif; ?>
      
      <?php if($ok): ?>
        // Redirect to login after 5 seconds for success (account created)
        let countdown = 5;
        const successAlert = document.querySelector('.alert-success span:last-child');
        const originalText = successAlert.textContent;
        
        const countdownInterval = setInterval(function() {
          countdown--;
          if (countdown > 0) {
            successAlert.textContent = originalText.replace('5 seconds', countdown + ' second' + (countdown === 1 ? '' : 's'));
          } else {
            clearInterval(countdownInterval);
            window.location.href = '/login.php';
          }
        }, 1000);
      <?php endif; ?>
    });
  </script>
</body>
</html>
