<?php
require_once '../config/db.php';

$uid = trim($_GET['uid'] ?? '');
if (!$uid) respond(['success' => false, 'available' => false]);

$conn = getDB();

$stmt = $conn->prepare("SELECT id FROM users WHERE uid = ?");
$stmt->execute([$uid]);
$available = ($stmt->fetch() === false);

respond(['success' => true, 'available' => $available]);

respond(['success' => true, 'available' => $available]);
?>
