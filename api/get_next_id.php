<?php
require_once '../config/db.php';
$role = $_GET['role'] ?? 'student';
respond(['success'=>true, 'next_id' => getNextUserID($role)]);
