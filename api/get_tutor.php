<?php
//  GET /api/bookings/get_tutor.php?status=pending (optional)
require_once '../config/db.php';
$uid = getAuthUID();
if (!$uid) respond(['success'=>false,'message'=>'Unauthorized'], 401);

$status = $_GET['status'] ?? null;
$conn = getDB();

$sql = "SELECT b.*, u.name as student_name, u.email as student_email, u.mobile as student_mobile 
        FROM bookings b 
        JOIN users u ON b.student_id = u.uid 
        WHERE b.tutor_id = ?";

$params = [$uid];
if($status) {
    $sql .= " AND b.status = ?";
    $params[] = $status;
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();
respond(['success'=>true, 'bookings'=>$bookings]);
