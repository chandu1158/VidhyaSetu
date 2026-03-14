<?php
// ============================================================
//  Auth – Login
//  POST /api/auth/login.php
//  Body: { email, password, role }
//  Returns: { success, token, uid, name, role, ... }
// ============================================================
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['success'=>false,'message'=>'Method not allowed'], 405);

$body     = getBody();
$email    = strtolower(trim($body['email'] ?? ''));
$password = $body['password'] ?? '';
$role     = $body['role'] ?? '';

if (!$email || !$password) {
    respond(['success'=>false,'message'=>'Email and password are required'], 400);
}

$conn = getDB();

// We check if either the exact email matches OR the uid matches, AND the requested role matches.
$stmt = $conn->prepare("SELECT * FROM users WHERE (email = ? OR uid = ?) AND role = ?");
$stmt->execute([$email, $email, $role]);
$user = $stmt->fetch();

if (!$user) {
    // Check if the user exists under ANY role to give a smart error message
    $chk = $conn->prepare("SELECT role FROM users WHERE email = ? OR uid = ?");
    $chk->execute([$email, $email]);
    $existing_roles = [];
    while ($row = $chk->fetch()) {
        $existing_roles[] = ucfirst($row['role']);
    }
    
    if (count($existing_roles) > 0) {
        $actual_roles_str = implode(' and ', $existing_roles);
        $req_role = ucfirst($role);
        respond(['success'=>false,'message'=>"No {$req_role} account found with that email. (However, you DO have an account as a {$actual_roles_str}. Please register your email as a {$req_role} first!)"], 403);
    }
    
    respond(['success'=>false,'message'=>'No account found with this Email or User ID'], 404);
}

if (!password_verify($password, $user['password_hash'])) {
    respond(['success'=>false,'message'=>'Incorrect password'], 401);
}

// Create session token (expires in 7 days)
$token     = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

$del  = $conn->prepare("DELETE FROM user_sessions WHERE uid = ?");
$del->execute([$user['uid']]);

$ins  = $conn->prepare("INSERT INTO user_sessions (uid, token, expires_at) VALUES (?,?,?)");
$ins->execute([$user['uid'], $token, $expiresAt]);

respond([
    'success'          => true,
    'token'            => $token,
    'uid'              => $user['uid'],
    'name'             => $user['name'],
    'email'            => $user['email'],
    'role'             => $user['role'],
    'skillLevel'       => $user['skill_level'] ?? 'Beginner',
    'skillTestPassed'  => (bool)($user['skill_test_passed'] ?? false),
    'profilePhoto'     => $user['profile_photo'] ?? '',
    'subjects'         => json_decode($user['subjects'] ?? '[]', true),
    'location'         => $user['location'] ?? '',
    'mobile'           => $user['mobile'] ?? '',
]);
