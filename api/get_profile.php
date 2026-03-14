<?php
require_once '../config/db.php';

$auth_uid = getAuthUID();
if (!$auth_uid) {
    respond(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Allow fetching other users by optional UID parameter
$uid = $_GET['uid'] ?? $auth_uid;

$conn = getDB();
$stmt = $conn->prepare("SELECT * FROM users WHERE uid = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

if (!$user) {
    respond(['success' => false, 'message' => 'User not found'], 404);
}

// Remove sensitive data
unset($user['password_hash']);
unset($user['internal_uid']);

// Mapping MySQL names to JS names for compatibility
$user['phone']        = $user['mobile'];
$user['mode']         = $user['availability'];
$user['testScore']    = $user['skill_score'] ?? 0;
$user['skillLevel']   = $user['skill_level'] ?? 'Beginner';
$user['skillTestPassed'] = (bool)($user['skill_test_passed'] ?? false);

// Decode JSON fields
$jsonFields = ['subjects', 'availability_slots'];
foreach ($jsonFields as $jf) {
    if (isset($user[$jf])) {
        $decoded = json_decode($user[$jf], true);
        $user[$jf] = is_array($decoded) ? $decoded : [];
    } else {
        $user[$jf] = [];
    }
}

respond(['success' => true, 'user' => $user]);
?>
