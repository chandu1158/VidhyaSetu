<?php
//  GET /api/bookings/get_student.php
require_once '../config/db.php';
$uid = getAuthUID();
if (!$uid) respond(['success'=>false,'message'=>'Unauthorized'], 401);

$conn = getDB();
$stmt = $conn->prepare("SELECT b.*, u.name as tutor_name, u.email as tutor_email, u.mobile as tutor_mobile 
                        FROM bookings b 
                        JOIN users u ON b.tutor_id = u.uid 
                        WHERE b.student_id = ?");
$stmt->execute([$uid]);
$bookings = $stmt->fetchAll();
respond(['success'=>true, 'bookings'=>$bookings]);
