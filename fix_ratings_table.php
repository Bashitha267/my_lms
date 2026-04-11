<?php
require 'config.php';
$conn->query("ALTER TABLE instructor_ratings ADD COLUMN review TEXT AFTER rating");
echo "Added review column to instructor_ratings table.\n";
?>
