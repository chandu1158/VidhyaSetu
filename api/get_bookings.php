<?php
require_once '../config/db.php';

$uid = getAuthUID();
if (!$uid)
    respond(['success' => false, 'message' => 'Unauthorized'], 401);

$conn = getDB();

// Determine role and basic info
$stmtRole = $conn->prepare("SELECT role, rating, total_sessions FROM users WHERE uid = ?");
$stmtRole->execute([$uid]);
$user = $stmtRole->fetch();
$role = $user['role'] ?? 'student';
$current_rating = (float) ($user['rating'] ?? 0);
$total_sessions_count = (int) ($user['total_sessions'] ?? 0);

$status = $_GET['status'] ?? '';
$isChat = isset($_GET['chat']) && $_GET['chat'] == '1';

$params = [$uid];

if ($role === 'tutor') {
    $sql = "SELECT b.*, u.name as student_name, u.email as student_email, u.mobile as student_mobile FROM bookings b JOIN users u ON b.student_id = u.uid WHERE b.tutor_id = ?";
    if ($status) {
        $sql .= " AND b.status = ?";
        $params[] = $status;
    } elseif ($isChat) {
        $sql .= " AND b.status IN ('confirmed', 'completed')";
    }
    if ($isChat)
        $sql .= " GROUP BY b.student_id, b.id, u.name, u.email, u.mobile"; // Grouping fix for Postgres
    $sql .= " ORDER BY b.created_at DESC";
} else {
    $sql = "SELECT b.*, u.name as tutor_name, u.email as tutor_email, u.mobile as tutor_mobile FROM bookings b JOIN users u ON b.tutor_id = u.uid WHERE b.student_id = ?";
    if ($status) {
        $sql .= " AND b.status = ?";
        $params[] = $status;
    } elseif ($isChat) {
        $sql .= " AND b.status IN ('confirmed', 'completed')";
    }
    if ($isChat)
        $sql .= " GROUP BY b.tutor_id, b.id, u.name, u.email, u.mobile"; // Grouping fix for Postgres
    $sql .= " ORDER BY b.created_at DESC";
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Calculate financial stats
$total_spent = 0;
$total_earnings = 0;

if ($role === 'tutor') {
    $statsStmt = $conn->prepare("SELECT SUM(amount - fee) as total FROM bookings WHERE tutor_id = ? AND status = 'completed'");
    $statsStmt->execute([$uid]);
    $total_earnings = (float) ($statsStmt->fetch()['total'] ?? 0);
} else {
    $statsStmt = $conn->prepare("SELECT SUM(amount) as total FROM bookings WHERE student_id = ? AND status != 'cancelled'");
    $statsStmt->execute([$uid]);
    $total_spent = (float) ($statsStmt->fetch()['total'] ?? 0);
}
respond([
    'success' => true,
    'bookings' => $bookings,
    'role' => $role,
    'total_spent' => $total_spent,
    'total_earnings' => $total_earnings,
    'current_rating' => $current_rating,
    'total_sessions_count' => $total_sessions_count
]);
?>
