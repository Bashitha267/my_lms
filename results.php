<?php
// Proxy file: serves dashboard/ALDetails.php from the root URL (/results)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent browsers/proxies from caching the auth-check result
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Serve the full A/L results page for all users
require_once __DIR__ . '/dashboard/ALDetails.php';
