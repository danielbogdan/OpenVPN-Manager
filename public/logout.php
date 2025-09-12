<?php
require __DIR__ . '/../config.php';
use App\Auth;

Auth::start();
Auth::logout();

// după logout îl trimitem spre login
header('Location: /login.php');
exit;
