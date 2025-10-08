<?php
$isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path'     => $cookieParams['path'],
    'domain'   => $cookieParams['domain'],
    'secure'   => $isSecure, // false on XAMPP, true on HTTPS
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();
?>