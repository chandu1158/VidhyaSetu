<?php
require_once '../config/db.php';

$conn = getDB();

$stats = [
    'students' => 0,
    'tutors' => 0,
    'sessions' => 0,
    'subjects' => 9 // Default fallback
];

// 1. Registered Students
$res = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'student'");
if ($res) {
    $row = $res->fetch();
    $stats['students'] = (int)$row['c'];
}

// 2. Verified Tutors
$res = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'tutor' AND skill_test_passed = 1");
if ($res) {
    $row = $res->fetch();
    $stats['tutors'] = (int)$row['c'];
}

// 3. Sessions Completed
$res = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status = 'completed'");
if ($res) {
    $row = $res->fetch();
    $stats['sessions'] = (int)$row['c'];
}

// 4. Subjects Covered
$res = $conn->query("SELECT subjects FROM users WHERE role = 'tutor' AND subjects IS NOT NULL AND subjects != ''");
if ($res) {
    $unique_subjects = [];
    while($row = $res->fetch()) {
        if (!empty($row['subjects'])) {
            $subj_arr = json_decode($row['subjects'], true);
            if (is_array($subj_arr)) {
                foreach($subj_arr as $s) {
                    $unique_subjects[$s] = true;
                }
            }
        }
    }
    $counted = count($unique_subjects);
    if ($counted > 0) {
        $stats['subjects'] = $counted;
    }
}

// Return the stats
respond(['success' => true, 'stats' => $stats]);
?>
