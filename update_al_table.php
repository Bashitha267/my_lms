<?php
require_once 'config.php';

$sql_file = __DIR__ . '/database files/alter_al_submissions_table.sql';

if (file_exists($sql_file)) {
    $sql = file_get_contents($sql_file);
    
    if ($conn->multi_query($sql)) {
        do {
            // consume all results
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        echo "Table altered successfully!";
    } else {
        echo "Error altering table: " . $conn->error;
    }
} else {
    echo "SQL file not found.";
}

$conn->close();
?>
