<?php
// ============================================================
//  Bookings – Update Status (Tutor: accept/decline/complete)
//  POST /api/bookings/update_status.php
//  Header: Authorization: Bearer <token>
//  Body: { bookingId, status }   status: confirmed|cancelled|completed
//  Optionally for student rating: { rating, review }
// ============================================================
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['success'=>false,'message'=>'Method not allowed'], 405);

$uid  = getAuthUID();
if (!$uid) respond(['success'=>false,'message'=>'Unauthorized'], 401);

$body      = getBody();
$bookingId = intval($body['bookingId'] ?? 0);
$newStatus = $body['status'] ?? '';
$rating    = intval($body['rating'] ?? 0);
$review    = $body['review'] ?? '';

$allowed = ['confirmed','cancelled','completed'];
if (!$bookingId || !in_array($newStatus, $allowed)) {
    respond(['success'=>false,'message'=>'Invalid booking ID or status'], 400);
}

$conn = getDB();

// Get booking
$s = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
$s->execute([$bookingId]);
$booking = $s->fetch();
if (!$booking) respond(['success'=>false,'message'=>'Booking not found'], 404);

// Only tutor or student can update (tutor for confirm/cancel/complete, student for rating)
if ($booking['tutor_id'] !== $uid && $booking['student_id'] !== $uid) {
    respond(['success'=>false,'message'=>'Forbidden'], 403);
}

// Check if payment details are missing and populate them if completing
$tutorId = $booking['tutor_id'];
$amount = floatval($booking['amount'] ?? 0);
$fee = floatval($booking['fee'] ?? 0);

if ($newStatus === 'completed' && $amount == 0) {
    $tn = $conn->prepare("SELECT fee_per_hour FROM users WHERE uid = ?");
    $tn->execute([$tutorId]);
    $tutorData = $tn->fetch();
    $amount = floatval($tutorData['fee_per_hour'] ?? 0);
    $fee = round($amount * 0.10, 2);
}

// Update booking status
$upd = $conn->prepare("UPDATE bookings SET status = ?, rating = ?, review = ?, amount = ?, fee = ? WHERE id = ?");
$upd->execute([$newStatus, $rating, $review, $amount, $fee, $bookingId]);

// If completed → update tutor stats and rating
if ($newStatus === 'completed') {
    $tutorId = $booking['tutor_id'];
    // Recalculate average rating
    $rSnap = $conn->prepare("SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM bookings WHERE tutor_id = ? AND status='completed' AND rating > 0");
    $rSnap->execute([$tutorId]);
    $rData = $rSnap->fetch();
    $avgRating = round($rData['avg_r'] ?? 0, 2);
    $totalSess = intval($rData['cnt'] ?? 0);

    $upd2 = $conn->prepare("UPDATE users SET rating = ?, total_sessions = ? WHERE uid = ?");
    $upd2->execute([$avgRating, $totalSess, $tutorId]);

    // Notify student
    $icon  = '✅';
    $msg   = "Your session for {$booking['subject']} has been marked complete!";
    $nStmt = $conn->prepare("INSERT INTO notifications (user_id, icon, message) VALUES (?,?,?)");
    $nStmt->execute([$booking['student_id'], $icon, $msg]);
}

// Notify relevant party
if ($newStatus === 'confirmed') {
    $icon = '📅'; $msg = "Your booking for {$booking['subject']} on {$booking['date']} has been accepted!";
    $nStmt = $conn->prepare("INSERT INTO notifications (user_id, icon, message) VALUES (?,?,?)");
    $nStmt->execute([$booking['student_id'], $icon, $msg]);
} elseif ($newStatus === 'cancelled') {
    $icon = '❌'; $msg = "Your booking for {$booking['subject']} was declined.";
    $nStmt = $conn->prepare("INSERT INTO notifications (user_id, icon, message) VALUES (?,?,?)");
    $nStmt->execute([$booking['student_id'], $icon, $msg]);
}

respond(['success'=>true,'message'=>"Booking $newStatus successfully"]);
