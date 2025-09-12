<?php
namespace App;

class Auth {
  public static function start(): void {
    if (session_status() === PHP_SESSION_NONE) {
      session_start([
        'cookie_httponly'=>true,
        'cookie_samesite'=>'Lax'
      ]);
    }
  }
  public static function check(): bool {
    self::start();
    return !empty($_SESSION['uid']);
  }
  public static function require(): void {
    if (!self::check()) { header('Location: /login.php'); exit; }
  }
  public static function login(string $email, string $pass): bool {
    $stmt = DB::pdo()->prepare("SELECT id,password_hash FROM users WHERE email=?");
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if ($u && password_verify($pass, $u['password_hash'])) {
      self::start();
      $_SESSION['uid'] = (int)$u['id'];
      $_SESSION['csrf'] = bin2hex(random_bytes(16));
      return true;
    }
    return false;
  }
  public static function logout(): void {
    self::start();
    session_destroy();
  }
  public static function csrf(): string {
    self::start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
  }
  public static function verifyCsrf($token): bool {
    self::start();
    return !empty($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
  }
}
