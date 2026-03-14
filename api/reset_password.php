<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['success'=>false,'message'=>'Method not allowed'], 405);

$body = getBody();
$email = strtolower(trim($body['email'] ?? ''));
$otp = trim($body['otp'] ?? '0');
$new_password = $body['password'] ?? '';

if (!$email || !$otp || !$new_password) {
    respond(['success'=>false,'message'=>'All fields are required'], 400);
}

$conn = getDB();

// Verify OTP again just to be safe
$stmt = $conn->prepare("SELECT * FROM otp_requests WHERE email = ? AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
$stmt->execute([$email, $otp]);
$valid = $stmt->fetch();

if (!$valid) {
    respond(['success'=>false,'message'=>'Invalid or expired OTP'], 400);
}

$hash = password_hash($new_password, PASSWORD_DEFAULT);
$update = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
$update->execute([$hash, $email]);

// Delete all OTPs for this email so they can't be reused
$del = $conn->prepare("DELETE FROM otp_requests WHERE email = ?");
$del->execute([$email]);

respond(['success'=>true, 'message'=>'Password reset successful. You can now login.']);
