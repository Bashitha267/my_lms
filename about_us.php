<?php
// Proxy file: serves dashboard/about_us.php from the root URL (/aboutus)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/dashboard/about_us.php';
