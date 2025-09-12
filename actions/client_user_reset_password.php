<?php
require __DIR__.'/../config.php';
use App\Auth;
use App\DB;

Auth::require();

$tenantId = (int)($_POST['tenant_id'] ?? 0);
$userId = (int)($_POST['user_id'] ?? 0);
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_new_password'] ?? '';

if (!$tenantId || !$userId || !$newPassword || !$confirmPassword) {
    header("Location: /tenant.php?id=$tenantId&error=" . urlencode("All fields are required"));
    exit;
}

if ($newPassword !== $confirmPassword) {
    header("Location: /tenant.php?id=$tenantId&error=" . urlencode("Passwords do not match"));
    exit;
}

if (strlen($newPassword) < 6) {
    header("Location: /tenant.php?id=$tenantId&error=" . urlencode("Password must be at least 6 characters long"));
    exit;
}

try {
    $pdo = DB::pdo();
    
    // Verify the user belongs to this tenant
    $stmt = $pdo->prepare("SELECT id, username FROM client_users WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$userId, $tenantId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: /tenant.php?id=$tenantId&error=" . urlencode("Client user not found or does not belong to this tenant"));
        exit;
    }
    
    // Update the password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE client_users SET password_hash = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$hashedPassword, $userId, $tenantId]);
    
    header("Location: /tenant.php?id=$tenantId&success=" . urlencode("Password reset successfully for user: " . $user['username']));
    
} catch (\Throwable $e) {
    error_log("Client user password reset error: " . $e->getMessage());
    header("Location: /tenant.php?id=$tenantId&error=" . urlencode("Error resetting password: " . $e->getMessage()));
}
