<?php
// ================================================================
//  VidhyaSetu — Fetch Messages API
//  api/get_messages.php
//  GET  ?receiver_id=<uid>&booking_id=<id>&last_id=<int>
//       &mark_read=1   (optional — marks messages as read)
//  Auth : Bearer token (or ?token= query param)
// ================================================================
require_once '../config/db.php';

$uid = getAuthUID();
if (!$uid) {
    respond(['success' => false, 'message' => 'Unauthorized — please log in again'], 401);
}

$receiver_id = trim($_GET['receiver_id'] ?? '');
$booking_id  = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
$last_id     = isset($_GET['last_id'])    ? (int) $_GET['last_id']    : 0;
$mark_read   = isset($_GET['mark_read'])  && $_GET['mark_read'] == '1';

if (!$receiver_id) {
    respond(['success' => false, 'message' => 'Missing receiver_id'], 400);
}

$conn = getDB();

// ── Optionally mark incoming messages as read ────────────────────
if ($mark_read) {
    try {
        $stmtRead = $conn->prepare(
            "UPDATE messages
             SET is_read = 1
             WHERE receiver_id = ? AND sender_id = ? AND is_read = 0"
            . ($booking_id ? " AND booking_id = ?" : "")
        );
        $readParams = $booking_id
            ? [$uid, $receiver_id, $booking_id]
            : [$uid, $receiver_id];
        $stmtRead->execute($readParams);
    } catch (PDOException $e) {
        // Silently ignore — is_read column may not exist on older schema
    }
}

// ── Fetch new messages after last_id ────────────────────────────
try {
    if ($booking_id) {
        // Strict: only messages for this booking
        $sql = "SELECT m.id, m.booking_id, m.sender_id, m.receiver_id, m.text,
                       m.created_at,
                       u.name AS sender_name,
                       u.uid  AS sender_uid
                FROM messages m
                JOIN users u ON m.sender_id = u.uid
                WHERE m.booking_id = ?
                  AND ((m.sender_id = ? AND m.receiver_id = ?)
                    OR (m.sender_id = ? AND m.receiver_id = ?))
                  AND m.id > ?
                ORDER BY m.created_at ASC, m.id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$booking_id, $uid, $receiver_id, $receiver_id, $uid, $last_id]);
    } else {
        // Fallback: any messages between these two users
        $sql = "SELECT m.id, m.booking_id, m.sender_id, m.receiver_id, m.text,
                       m.created_at,
                       u.name AS sender_name,
                       u.uid  AS sender_uid
                FROM messages m
                JOIN users u ON m.sender_id = u.uid
                WHERE ((m.sender_id = ? AND m.receiver_id = ?)
                    OR (m.sender_id = ? AND m.receiver_id = ?))
                  AND m.id > ?
                ORDER BY m.created_at ASC, m.id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$uid, $receiver_id, $receiver_id, $uid, $last_id]);
    }
    $messages = $stmt->fetchAll();
} catch (PDOException $e) {
    respond(['success' => false, 'message' => 'DB error: ' . $e->getMessage()], 500);
}

// ── Unread count for the sidebar badge (messages FROM receiver TO me, unread) ──
$unread = 0;
try {
    $stmtUnread = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM messages WHERE receiver_id = ? AND is_read = 0"
    );
    $stmtUnread->execute([$uid]);
    $unread = (int) ($stmtUnread->fetch()['cnt'] ?? 0);
} catch (PDOException $e) {
    // Silently ignore if is_read column doesn't exist
}

respond([
    'success'      => true,
    'messages'     => $messages,
    'unread_total' => $unread,
    'count'        => count($messages)
]);
?>
