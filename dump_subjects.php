<?php
require_once 'config.php';
$columns = $conn->query("DESCRIBE subjects");
$out = "";
if ($columns) {
    while ($row = $columns->fetch_assoc()) {
        $out .= $row['Field'] . "\n";
    }
} else {
    $out = "Failed to describe table: " . $conn->error;
}
file_put_contents('subjects_cols.txt', $out);
?>
