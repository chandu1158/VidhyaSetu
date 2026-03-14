<?php
require 'config/db.php';
$conn = getDB();
$stmt = $conn->query("SHOW COLUMNS FROM users");
while($row = $stmt->fetch()) echo $row['Field'] . "\n";
?>
