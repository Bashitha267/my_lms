<?php
require_once 'config.php';
if ($conn->ping()) {
    echo "Database Connected Successfully!\n";
} else {
    echo "Database Connection Failed: " . $conn->error . "\n";
}
?>
