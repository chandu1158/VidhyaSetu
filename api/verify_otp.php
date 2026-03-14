<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['success'=>false,'message'=>'Method not allowed'], 405);

$body = getBody();
$email = strtolower(trim($body['email'] ?? ''));
$otp = trim($body['otp'] ?? '0');

if (!$email || !$otp) {
    respond(['success'=>false,'message'=>'Email and OTP are required'], 400);
}

$conn = getDB();

$stmt = $conn->prepare("SELECT * FROM otp_requests WHERE email = ? AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
$stmt->execute([$email, $otp]);
$valid = $stmt->fetch();

if (!$valid) {
    respond(['success'=>false,'message'=>'Invalid or expired OTP. Please try again or request a new one.'], 400);
}

respond(['success'=>true, 'message'=>'OTP verified successfully']);
