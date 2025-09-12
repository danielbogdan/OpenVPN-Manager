<?php
declare(strict_types=1);

// Composer autoload sau fallback PSR-4 pt. lib/
$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(function($class){
        if (str_starts_with($class, 'App\\')) {
            $path = __DIR__ . '/lib/' . str_replace(['App\\','\\'], ['', '/'], $class) . '.php';
            if (is_file($path)) require $path;
        }
    });
}

date_default_timezone_set(getenv('TZ') ?: 'Europe/Bucharest');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function env($key, $default=null){ $v=getenv($key); return $v!==false?$v:$default; }

define('APP_KEY', env('APP_KEY','please_change'));
define('OVPN_IMAGE', env('OVPN_IMAGE','kylemanna/openvpn:latest'));
define('DEFAULT_DNS1', env('DEFAULT_DNS1','1.1.1.1'));
define('DEFAULT_DNS2', env('DEFAULT_DNS2','9.9.9.9'));

define('DB_HOST', env('DB_HOST','127.0.0.1'));
define('DB_NAME', env('DB_NAME','openvpn_admin'));
define('DB_USER', env('DB_USER','root'));
define('DB_PASS', env('DB_PASS',''));
