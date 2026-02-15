<?php
require_once 'config.php';

$sql_file = 'database files/create_al_submissions_table.sql';

if (file_exists($sql_file)) {
    $sql = file_get_contents($sql_file);
    
    // Split by semicolon to handle multiple statements if any (though here it's just one)
    $queries = explode(';', $sql);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            if ($conn->query($query) === TRUE) {
                echo "Table created successfully.<br>";
            } else {
                echo "Error creating table: " . $conn->error . "<br>";
            }
        }
    }
} else {
    echo "SQL file not found.";
}

// Check if table exists
$check = $conn->query("SHOW TABLES LIKE 'al_exam_submissions'");
if ($check->num_rows > 0) {
    echo "<h3>al_exam_submissions table exists!</h3>";
} else {
    echo "<h3>Table does not exist.</h3>";
}
?>
