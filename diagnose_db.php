<?php
require_once 'config.php';

echo "<h2>Database Diagnostics for " . htmlspecialchars(DB_NAME) . "</h2>";

// List all triggers
$triggers = $conn->query("SHOW TRIGGERS");
if ($triggers) {
    echo "<h3>Triggers in Database</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background: #eee;'><th>Trigger Name</th><th>Event</th><th>Table</th><th>Statement</th><th>Timing</th></tr>";
    while ($row = $triggers->fetch_assoc()) {
        $has_bad_ref = (stripos($row['Statement'], 'teachers_register') !== false || stripos($row['Statement'], 'teachers_regitser') !== false);
        $style = $has_bad_ref ? "style='background: #fee;'" : "";
        echo "<tr {$style}>";
        echo "<td>" . htmlspecialchars($row['Trigger']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Event']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Table']) . "</td>";
        echo "<td><pre>" . htmlspecialchars($row['Statement']) . "</pre></td>";
        echo "<td>" . htmlspecialchars($row['Timing']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Error fetching triggers: " . htmlspecialchars($conn->error) . "</p>";
}

// List all tables
$tables = $conn->query("SHOW TABLES");
if ($tables) {
    echo "<h3>Tables in Database</h3>";
    echo "<ul>";
    while ($row = $tables->fetch_row()) {
        echo "<li>" . htmlspecialchars($row[0]) . "</li>";
    }
    echo "</ul>";
}
?>
