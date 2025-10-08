<?php
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

$csp = "default-src 'self'; "
     . "script-src 'self' https://cdn.jsdelivr.net https://maps.googleapis.com; "
     . "style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
     . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; "
     . "img-src 'self' data: blob: https://*.googleapis.com https://*.gstatic.com; "
     . "connect-src 'self' https://maps.googleapis.com; "
     . "object-src 'none'; "
     . "frame-ancestors 'none'; "
     . "base-uri 'self'; "
     . "form-action 'self';";
header("Content-Security-Policy: " . str_replace("\n", "", $csp));

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
  header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}
?>