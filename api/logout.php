<?php
// ============================================================
//  Auth – Logout
//  POST /api/auth/logout.php
//  Header: Authorization: Bearer <token>
// ============================================================
require_once '../config/db.php';

$uid = getAuthUID();
if (!$uid) respond(['success'=>false,'message'=>'Not authenticated'], 401);

$conn = getDB();
$stmt = $conn->prepare("DELETE FROM user_sessions WHERE uid = ?");
$stmt->execute([$uid]);

respond(['success'=>true,'message'=>'Logged out successfully']);
