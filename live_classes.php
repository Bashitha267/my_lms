<?php
// Proxy file: serves dashboard/live_classes.php from the root URL (/live_classes)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Prevent browsers/proxies from caching the auth-check result
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Serve the full page for all users (guests see public live classes)
require_once __DIR__ . '/dashboard/live_classes.php';
