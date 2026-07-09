<?php
// Redirect to dashboard as login functionality is now there
header("Location: index.php" . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit();
?>
