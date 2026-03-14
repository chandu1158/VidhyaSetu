<?php
require_once '../config/db.php';

$uid = getAuthUID();
if (!$uid) respond(['success' => false, 'message' => 'Unauthorized'], 401);

$body = getBody();
$booking_id = $body['bookingId'] ?? 0;
$rating = $body['rating'] ?? 0;
$review = $body['review'] ?? '';

if (!$booking_id || $rating < 1 || $rating > 5) {
    respond(['success' => false, 'message' => 'Invalid rating data'], 400);
}

$conn = getDB();

// 1. Verify the booking belongs to this student and is completed
$stmt = $conn->prepare("SELECT tutor_id FROM bookings WHERE id = ? AND student_id = ? AND status = 'completed'");
$stmt->execute([$booking_id, $uid]);
$booking = $stmt->fetch();

if (!$booking) {
    respond(['success' => false, 'message' => 'Booking not found or not eligible for rating'], 404);
}

$tutor_id = $booking['tutor_id'];

// 2. Update the booking with rating and review
$stmt2 = $conn->prepare("UPDATE bookings SET rating = ?, review = ? WHERE id = ?");
$stmt2->execute([$rating, $review, $booking_id]);

// 3. Calculate new average rating for the tutor
$stmt3 = $conn->prepare("SELECT AVG(rating) as avg_rating FROM bookings WHERE tutor_id = ? AND status = 'completed' AND rating > 0");
$stmt3->execute([$tutor_id]);
$res = $stmt3->fetch();
$new_avg = $res['avg_rating'] ?? 0;

// 4. Update the tutor's rating in the users table
$stmt4 = $conn->prepare("UPDATE users SET rating = ? WHERE uid = ?");
$stmt4->execute([$new_avg, $tutor_id]);
respond(['success' => true, 'message' => 'Rating submitted successfully', 'newRating' => $new_avg]);
?>
