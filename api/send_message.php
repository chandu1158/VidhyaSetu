<?php
// ================================================================
//  VidhyaSetu — Send Message API
//  api/send_message.php
//  POST  { booking_id, receiver_id, text }
//  Auth  : Bearer token (or ?token= query param)
// ================================================================
require_once '../config/db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$uid = getAuthUID();
if (!$uid) {
    respond(['success' => false, 'message' => 'Unauthorized — please log in again'], 401);
}

$body       = getBody();
$booking_id = isset($body['booking_id']) ? (int) $body['booking_id'] : 0;
$receiver_id = trim($body['receiver_id'] ?? '');
$text        = trim($body['text'] ?? '');

// Validate required fields
if (!$booking_id) {
    respond(['success' => false, 'message' => 'Missing booking_id'], 400);
}
if (!$receiver_id) {
    respond(['success' => false, 'message' => 'Missing receiver_id'], 400);
}
if ($text === '') {
    respond(['success' => false, 'message' => 'Message cannot be empty'], 400);
}
if (mb_strlen($text) > 2000) {
    respond(['success' => false, 'message' => 'Message too long (max 2000 characters)'], 400);
}
// Prevent self-messaging
if ($uid === $receiver_id) {
    respond(['success' => false, 'message' => 'Cannot send a message to yourself'], 400);
}

$conn = getDB();

// Verify the sender is a participant in the booking AND booking is active (confirmed|completed)
$stmt = $conn->prepare(
    "SELECT id FROM bookings 
     WHERE id = ? 
       AND (student_id = ? OR tutor_id = ?) 
       AND status IN ('confirmed', 'completed')
     LIMIT 1"
);
$stmt->execute([$booking_id, $uid, $uid]);
if (!$stmt->fetch()) {
    respond(['success' => false, 'message' => 'Access denied — booking not found or not active'], 403);
}

// Also confirm receiver is the other participant in this specific booking
$stmtCheck = $conn->prepare(
    "SELECT id FROM bookings
     WHERE id = ?
       AND (student_id = ? OR tutor_id = ?)
     LIMIT 1"
);
$stmtCheck->execute([$booking_id, $receiver_id, $receiver_id]);
if (!$stmtCheck->fetch()) {
    respond(['success' => false, 'message' => 'Receiver is not part of this booking'], 403);
}

try {
    $stmtInsert = $conn->prepare(
        "INSERT INTO messages (booking_id, sender_id, receiver_id, text, is_read, created_at)
         VALUES (?, ?, ?, ?, 0, NOW())"
    );
    $stmtInsert->execute([$booking_id, $uid, $receiver_id, $text]);
    $newId = (int) $conn->lastInsertId();

    respond([
        'success'    => true,
        'message'    => 'Message sent',
        'message_id' => $newId
    ]);
} catch (PDOException $e) {
    // Handle missing is_read column gracefully (older schema)
    if (strpos($e->getMessage(), 'is_read') !== false) {
        try {
            $stmtInsert2 = $conn->prepare(
                "INSERT INTO messages (booking_id, sender_id, receiver_id, text, created_at)
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $stmtInsert2->execute([$booking_id, $uid, $receiver_id, $text]);
            $newId = (int) $conn->lastInsertId();
            respond([
                'success'    => true,
                'message'    => 'Message sent',
                'message_id' => $newId
            ]);
        } catch (PDOException $e2) {
            respond(['success' => false, 'message' => 'DB error: ' . $e2->getMessage()], 500);
        }
    }
    respond(['success' => false, 'message' => 'Failed to send message: ' . $e->getMessage()], 500);
}
?>
