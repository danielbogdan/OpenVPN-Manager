<?php
require __DIR__ . '/../../config.php';

use App\ClientAuth;

ClientAuth::logout();
header('Location: /client/login.php');
exit;