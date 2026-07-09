<?php
// Redirect to root index.php which now serves the main dashboard landing page
header("Location: ../index.php" . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit;
?>