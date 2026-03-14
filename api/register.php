<?php
// Disable error display in production to prevent JSON corruption
// They will still be available in server logs
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);

// ============================================================
//  Auth – Register
//  POST /api/auth/register.php
// ============================================================
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['success'=>false,'message'=>'Method not allowed'], 405);

$body = getBody();
$conn = getDB();

// Extract fields
$name       = trim($body['name'] ?? '');
$email      = strtolower(trim($body['email'] ?? ''));
$password   = $body['password'] ?? '';
$role       = $body['role'] ?? 'student';
$mobile     = $body['mobile'] ?? $body['phone'] ?? '';
$gender     = $body['gender'] ?? '';
$dob        = !empty($body['dob']) ? $body['dob'] : null; // Handle empty date
$age        = intval($body['age'] ?? 0);
$college    = $body['college'] ?? '';
$location   = $body['location'] ?? '';
$subjects   = json_encode($body['subjects'] ?? []);
$grade      = $body['grade'] ?? '';
$occupation = $body['occupation'] ?? '';
$experience = $body['experience'] ?? '';
$availability = $body['availability'] ?? $body['mode'] ?? 'Online';
$fee        = floatval($body['feePerHour'] ?? 0);
$bio        = $body['bio'] ?? '';
$photo      = $body['profilePhoto'] ?? '';

if (!$name || !$email || !$password || !in_array($role, ['student','tutor'])) {
    respond(['success'=>false,'message'=>'Missing required fields'], 400);
}
if (strlen($password) < 6) {
    respond(['success'=>false,'message'=>'Password must be at least 6 characters'], 400);
}

// Check if email already exists for THIS role
$chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = ?");
$chk->execute([$email, $role]);
if ($chk->fetch()) {
    respond(['success'=>false,'message'=>'Account already registered with this email for the ' . $role . ' role'], 409);
}

// Handle UserID (Provided by user or generate next)
$display_uid = trim($body['uid'] ?? '');
if (!$display_uid) {
    $display_uid = getNextUserID($role);
} else {
    // Check if custom UID is already taken
    $chkUID = $conn->prepare("SELECT id FROM users WHERE uid = ?");
    $chkUID->execute([$display_uid]);
    if ($chkUID->fetch()) {
        respond(['success'=>false,'message'=>'This User ID is already taken. Please choose another one.'], 409);
    }
}

$internal_uid = generateUID();
$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $stmt = $conn->prepare("
        INSERT INTO users (uid, internal_uid, role, name, email, password_hash, mobile, gender, dob, age, college, location,
            subjects, grade, occupation, experience, availability, fee_per_hour, bio, profile_photo)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    
    $stmt->execute([
        $display_uid, $internal_uid, $role, $name, $email, $hash, $mobile, $gender, $dob, $age, $college, $location,
        $subjects, $grade, $occupation, $experience, $availability, $fee, $bio, $photo
    ]);
    $inserted = true;
} catch (PDOException $e) {
    // Check if the error is due to the old 'email' unique index preventing multiple roles
    if (strpos($e->getMessage(), "Duplicate entry") !== false && strpos($e->getMessage(), "for key 'email'") !== false) {
        // Auto-fix the database schema right here
        $conn->query("ALTER TABLE users DROP INDEX email");
        $conn->query("ALTER TABLE users ADD UNIQUE INDEX unique_email_role (email, role)");
        
        // Retry the exact same insert
        $stmt->execute([
            $display_uid, $internal_uid, $role, $name, $email, $hash, $mobile, $gender, $dob, $age, $college, $location,
            $subjects, $grade, $occupation, $experience, $availability, $fee, $bio, $photo
        ]);
        $inserted = true;
    } else {
        respond(['success'=>false,'message'=>'Registration failed: ' . $e->getMessage()], 500);
    }
}

if (isset($inserted) && $inserted) {
    $uid = $display_uid;
    // Create welcome notification
    $notifMsg = "Welcome to VidhyaSetu, $name! 🎉";
    $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, icon, message) VALUES (?,?,?)");
    $icon = '👋';
    $notifStmt->execute([$uid, $icon, $notifMsg]);

    // --- AUTO-LOGIN AFTER REGISTRATION ---
    // Generate session token
    $token     = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

    $ins  = $conn->prepare("INSERT INTO user_sessions (uid, token, expires_at) VALUES (?,?,?)");
    $ins->execute([$uid, $token, $expiresAt]);

    // Clear any accidental output before sending final JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    respond([
        'success' => true,
        'message' => 'Registration successful',
        'token'   => $token,
        'uid'     => $uid,
        'role'    => $role,
        'name'    => $name,
        'email'   => $email,
        'subjects'=> json_decode($subjects, true),
        'skillLevel' => 'Beginner',
        'skillTestPassed' => false
    ]);
}
?>
