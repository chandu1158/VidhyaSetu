<?php
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(['success'=>false,'message'=>'Method not allowed'], 405);

$body = getBody();
$email = strtolower(trim($body['email'] ?? ''));

if (!$email) {
    respond(['success'=>false,'message'=>'Email is required'], 400);
}

$conn = getDB();

// Create table if not exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS otp_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        otp VARCHAR(10) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Ignore errors here silently to not break flow if DB user doesn't have privileges, or already exists.
}

$stmt = $conn->prepare("SELECT uid, name FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    respond(['success'=>false,'message'=>'No account found with this email'], 404);
}

// Generate 6 digit OTP
$otp = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
$expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

$ins = $conn->prepare("INSERT INTO otp_requests (email, otp, expires_at) VALUES (?, ?, ?)");
$ins->execute([$email, $otp, $expires_at]);

// Send email
$subject = "Your Password Reset OTP - VidhyaSetu";
$message = "Hello {$user['name']},\n\nYour OTP for password reset is: $otp\n\nThis OTP is valid for 15 minutes.\n\nThanks,\nVidhyaSetu Team";
$headers = "From: noreply@vidhyasetu.com\r\n";
$headers .= "Reply-To: support@vidhyasetu.com\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// We ignore mail errors locally but try to send it
@mail($email, $subject, $message, $headers);

// FOR DEVELOPMENT/TESTING ONLY: If local DB, you might want to see it in the response (useful when email server is not configured)
$dev_msg = (DB_HOST === 'localhost') ? " (DEV OTP: $otp)" : "";

respond(['success'=>true, 'message'=>'OTP sent successfully to your email' . $dev_msg]);
