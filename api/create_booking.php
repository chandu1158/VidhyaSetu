<?php
// ============================================================
//  Bookings – Create Booking Request (Student)
//  POST /api/bookings/create_booking.php
//  Header: Authorization: Bearer <token>
//  Body: { tutorId, subject, date, time, mode, message }
// ============================================================
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['success'=>false,'message'=>'Method not allowed'], 405);

$studentId = getAuthUID();
if (!$studentId) respond(['success'=>false,'message'=>'Unauthorized'], 401);

$body     = getBody();
$tutorId  = $body['tutorId']  ?? $body['tutor_id'] ?? '';
$subject  = $body['subject']  ?? '';
$date     = $body['date']     ?? '';
$time     = $body['time']     ?? '';
$mode     = $body['mode']     ?? 'Online';
$message  = $body['message']  ?? '';

if (!$tutorId || !$subject || !$date || !$time) {
    respond(['success'=>false,'message'=>'Missing required booking details'], 400);
}

$conn = getDB();

// Get student and tutor names for easy history display
$sn = $conn->prepare("SELECT name FROM users WHERE uid = ?");
$sn->execute([$studentId]);
$studentName = $sn->fetch()['name'] ?? 'Student';

$tn = $conn->prepare("SELECT name, fee_per_hour FROM users WHERE uid = ?");
$tn->execute([$tutorId]);
$tutorData = $tn->fetch();
$tutorName = $tutorData['name'] ?? 'Tutor';
$amount = floatval($tutorData['fee_per_hour'] ?? 0);
$fee = round($amount * 0.10, 2); // 10% platform fee

// Insert booking
try {
    $stmt = $conn->prepare("
        INSERT INTO bookings (student_id, tutor_id, student_name, tutor_name, subject, date, time, mode, status, review, amount, fee)
        VALUES (?,?,?,?,?,?,?,?,'pending','',?,?)
    ");
    $stmt->execute([$studentId, $tutorId, $studentName, $tutorName, $subject, $date, $time, $mode, $amount, $fee]);
    $bookingId = $conn->lastInsertId();

    // Notify tutor
    $icon = '📅';
    $msg  = "$studentName requested a session for $subject on $date at $time.";
    $nStmt = $conn->prepare("INSERT INTO notifications (user_id, icon, message) VALUES (?,?,?)");
    $nStmt->execute([$tutorId, $icon, $msg]);

    respond(['success'=>true, 'bookingId'=>$bookingId, 'message'=>'Booking request sent!']);
} catch (PDOException $e) {
    respond(['success'=>false, 'message'=>'Failed to create booking: '.$e->getMessage()], 500);
}
?>
