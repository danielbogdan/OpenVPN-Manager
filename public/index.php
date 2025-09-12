<?php
require __DIR__.'/../config.php';
use App\Auth;
use App\DB;
use App\Util;

Auth::start();

try {
  $hasUser = (int)DB::pdo()->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0;
} catch (Throwable $e) {
  // dacă DB încă pornește, arată un mesaj prietenos
  http_response_code(503);
  echo "DB încă nu este gata. Încearcă iar peste câteva secunde.";
  exit;
}

if (!$hasUser) {
  header('Location: /setup.php'); 
  exit;
}

// Check if user is already logged in
if (Auth::check()) { 
  header('Location: /dashboard.php'); 
  exit; 
}

// Detect public IP and redirect accordingly
$publicIP = Util::detectPublicIP();
$adminIP = '91.213.11.31'; // Your admin server IP

if ($publicIP === $adminIP) {
  // Admin access - redirect to admin login
  header('Location: /login.php');
  exit;
} else {
  // Client access - redirect to client login
  header('Location: /client/login.php');
  exit;
}
