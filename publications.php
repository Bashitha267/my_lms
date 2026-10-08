<?php
// Proxy file: serves dashboard/publications.php from the root URL (/publications)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Publications is publicly accessible — no auth required.
// If not logged in, still serve the page (it handles guest users internally).
// However, if session is completely missing and you want a soft prompt, uncomment below.

// Serve the full page for everyone (publications is a public-facing page)
require_once __DIR__ . '/dashboard/publications.php';
