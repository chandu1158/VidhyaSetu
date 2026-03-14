<?php
require_once '../config/db.php';

$uid = getAuthUID();
if (!$uid) {
    respond(['success' => false, 'message' => 'Unauthorized'], 401);
}

$body = getBody();
$conn = getDB();

// Build dynamic update query to handle both profile updates and skill test score updates
$fields = [];
$params = [];

// Map JS keys to MySQL columns
$fieldMap = [
    'name' => 'name',
    'phone' => 'mobile',
    'mobile' => 'mobile',
    'location' => 'location',
    'mode' => 'availability',
    'availability' => 'availability',
    'college' => 'college',
    'occupation' => 'occupation',
    'experience' => 'experience',
    'bio' => 'bio',
    'gender' => 'gender',
    'dob' => 'dob',
    'age' => 'age',
    'fee_per_hour' => 'fee_per_hour',
    'status' => 'status',
    'grade' => 'grade',
    'primary_subject' => 'primary_subject',
    'skill_level' => 'skill_level',
    'skill_score' => 'skill_score',
    'skill_test_passed' => 'skill_test_passed',
];

foreach ($fieldMap as $jsKey => $dbCol) {
    if (isset($body[$jsKey])) {
        $fields[] = "$dbCol = ?";
        $params[] = $body[$jsKey];
    }
}

// Special case for JSON fields
$jsonFields = ['subjects', 'availability_slots'];
foreach ($jsonFields as $jf) {
    if (isset($body[$jf])) {
        $fields[] = "$jf = ?";
        $params[] = json_encode($body[$jf]);
    }
}

if (empty($fields)) {
    respond(['success' => false, 'message' => 'No data to update']);
}

$sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE uid = ?";
$params[] = $uid;

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    respond(['success' => true, 'message' => 'Updated successfully']);
} catch (PDOException $e) {
    respond(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()], 500);
}
?>
