<?php
// ============================================================
//  Skill Test – Save Result
//  POST /api/skill_test/save_result.php
//  Header: Authorization: Bearer <token>
//  Body: { score, totalQuestions, level }
//  level: 'Beginner' | 'Intermediate' | 'Advanced'
// ============================================================
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['success'=>false,'message'=>'Method not allowed'], 405);

$uid = getAuthUID();
if (!$uid) respond(['success'=>false,'message'=>'Unauthorized'], 401);

$body   = getBody();
$passed = $body['passed'] ?? false;
$level  = $body['level']  ?? 'Basic';
$score  = intval($body['score'] ?? 0);
$fee    = intval($body['fee']   ?? 150);
$subject = $body['subject'] ?? '';

$allowed = ['Beginner', 'Intermediate', 'Advanced'];
if (!in_array($level, $allowed) && !in_array($level, ['Basic'])) {
    $level = 'Beginner';
}
if ($level === 'Basic') $level = 'Beginner'; // Normalize Basic to Beginner

// Calculate fee dynamically
if ($level === 'Advanced') {
    $fee = 250;
} else if ($level === 'Intermediate') {
    $fee = 200;
} else {
    $fee = 150;
}

$passedInt = $passed ? 1 : 0;

$conn = getDB();

// 0. Verify role
$chk = $conn->prepare("SELECT role FROM users WHERE uid = ?");
$chk->execute([$uid]);
$row = $chk->fetch();
$actualRole = $row ? $row['role'] : null;

if ($actualRole !== 'tutor') {
    respond(['success' => false, 'message' => 'Not a tutor (Role: ' . ($actualRole ?? 'Unknown') . ')'], 403);
}

// 1. Check if primary_subject column exists (MySQL specific, for Postgres we handle differently)
// Actually, since we are moving to a unified schema, we should ensure the column exists in our provided schema.
// For now, let's keep it safe but PDO.
try {
    $conn->query("SELECT primary_subject FROM users LIMIT 1");
} catch (Exception $e) {
    $conn->query("ALTER TABLE users ADD COLUMN primary_subject VARCHAR(100) DEFAULT NULL");
}

// 2. Update basic fields
$u_sql = "UPDATE users SET skill_score = ?, skill_level = ?, skill_test_passed = ?, fee_per_hour = ?, primary_subject = ? WHERE uid = ? AND role = 'tutor'";
$stmt = $conn->prepare($u_sql);
$feeAmount = floatval($fee);
$stmt->execute([$score, $level, $passedInt, $feeAmount, $subject, $uid]);

// 2. Insert history - wrapping in try catch to prevent failure if table schema is weird
try {
    $histStmt = $conn->prepare("INSERT INTO skill_tests (tutor_id, subject, score, total, percentage, level, fee, passed) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $totalQ = 15;
    $pct = floatval($score);
    $pts = round(($pct/100)*$totalQ);
    $histStmt->execute([$uid, $subject, $pts, $totalQ, $pct, $level, $feeAmount, $passedInt]);
} catch (Exception $e) {
    error_log("History insert failed but continuing: " . $e->getMessage());
}

// 3. Fetch data to return
$uStmt = $conn->prepare("SELECT * FROM users WHERE uid = ?");
$uStmt->execute([$uid]);
$userData = $uStmt->fetch();

if ($userData) {
    unset($userData['password_hash']);
    $userData['skillLevel']      = $userData['skill_level'] ?? 'Beginner';
    $userData['skillTestPassed'] = (bool)($userData['skill_test_passed'] ?? false);
    $userData['testScore']       = $userData['skill_score'] ?? 0;
    $userData['feePerHour']      = $userData['fee_per_hour'] ?? 0;
    $userData['primarySubject']  = $userData['primary_subject'] ?? '';
    
    foreach (['subjects', 'availability_slots'] as $jf) {
        if (isset($userData[$jf])) {
            $decoded = json_decode($userData[$jf], true);
            $userData[$jf] = is_array($decoded) ? $decoded : [];
        } else {
            $userData[$jf] = [];
        }
    }
}
respond([
    'success' => true,
    'passed'  => (bool)$passed,
    'userData' => $userData
]);
$conn->close();
