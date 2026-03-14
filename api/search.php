<?php
// ============================================================
//  Tutors – Search / List Verified Tutors
//  GET /api/tutors/search.php
//  Query params: subject, level, mode, location, sort
//  Header: Authorization: Bearer <token>
// ============================================================
require_once '../config/db.php';

$uid = getAuthUID();
if (!$uid)
    respond(['success' => false, 'message' => 'Unauthorized'], 401);

$conn = getDB();

$subject = $_GET['subject'] ?? '';
$level = $_GET['level'] ?? '';
$mode = $_GET['mode'] ?? '';
$location = $_GET['location'] ?? '';
$userid = $_GET['userid'] ?? '';
$sort = $_GET['sort'] ?? 'rating';

$sql = "SELECT uid, name, occupation, experience, availability, location,
                  profile_photo, subjects, skill_level, fee_per_hour,
                  rating, total_sessions, bio, email, mobile
           FROM users
           WHERE role = 'tutor' AND skill_test_passed = 1";
$params = [];
$types = '';

if ($subject) {
    $sql .= " AND subjects LIKE ?";
    $like = '%' . $subject . '%';
    $params[] = $like;
    $types .= 's';
}
if ($level) {
    $sql .= " AND skill_level = ?";
    $params[] = $level;
    $types .= 's';
}
if ($mode && $mode !== 'Both') {
    $sql .= " AND (availability = ? OR availability = 'Both')";
    $params[] = $mode;
    $types .= 's';
}
if ($location) {
    $sql .= " AND location LIKE ?";
    $like = '%' . $location . '%';
    $params[] = $like;
    $types .= 's';
}
if ($userid) {
    $sql .= " AND uid LIKE ?";
    $like = '%' . $userid . '%';
    $params[] = $like;
    $types .= 's';
}

$orderMap = ['rating' => 'rating DESC', 'name' => 'name ASC', 'experience' => 'total_sessions DESC'];
$sql .= " ORDER BY " . ($orderMap[$sort] ?? 'rating DESC');

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Parse subjects JSON
foreach ($rows as &$r) {
    if (is_string($r['subjects'])) {
        $decoded = json_decode($r['subjects'], true);
        $r['subjects'] = is_array($decoded) ? $decoded : [];
    } else {
        $r['subjects'] = [];
    }
}

file_put_contents("debug_search.txt", "Search requested by UID $uid. Found " . count($rows) . " tutors.\n", FILE_APPEND);
respond(['success' => true, 'tutors' => $rows, 'count' => count($rows)]);
