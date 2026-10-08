<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    die('Invalid publication ID.');
}

// Fetch publication
$stmt = $conn->prepare("SELECT id, title, pdf_path, is_free, price FROM publications WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$pub = $result->fetch_assoc();
$stmt->close();

if (!$pub) {
    http_response_code(404);
    die('Publication not found.');
}

if (empty($pub['pdf_path'])) {
    http_response_code(404);
    die('This publication does not have a downloadable PDF file attached.');
}

// Ensure the path is inside uploads/publications/
$relative_path = ltrim($pub['pdf_path'], '/\\');
$full_path = realpath(__DIR__ . '/' . $relative_path);
$base_upload_dir = realpath(__DIR__ . '/uploads/publications');

// Security check: ensure path is within uploads/publications directory
if (!$full_path || !$base_upload_dir || stripos($full_path, $base_upload_dir) !== 0 || !file_exists($full_path)) {
    http_response_code(404);
    die('The requested PDF file could not be found on the server.');
}

// Sanitize filename for download
$safe_title = preg_replace('/[^A-Za-z0-9_\-\. ]/', '', $pub['title']);
$safe_title = trim($safe_title);
if (empty($safe_title)) {
    $safe_title = 'publication_' . $pub['id'];
}
$download_filename = $safe_title . '.pdf';

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Serve PDF
header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $download_filename) . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($full_path));

readfile($full_path);
exit;
