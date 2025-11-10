<?php

header('Content-Type: application/json');


$host = 'localhost';
$db   = 'inventory'; 
$user = 'root';     
$pass = ''; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     http_response_code(500);
     echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
     exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method or missing email.']);
    exit;
}

$email = trim($_POST['email']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}


$stmt = $pdo->prepare('SELECT user_id, name FROM inventory_user WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    
    error_log("Attempted OTP request for non-existent email: " . $email);
    echo json_encode(['success' => true, 'message' => 'If this email address is in our system, an OTP has been sent.']);
    exit;
}


$otp = rand(100000, 999999); 
$expiry_time = time() + (5 * 60); 


try {
    $stmt = $pdo->prepare(
        'UPDATE inventory_user SET otp_code_int = ?, otp_expiry_bignrt = ? WHERE email = ?'
    );
    
    $stmt->execute([$otp, $expiry_time, $email]);

} catch (\PDOException $e) {
    http_response_code(500);
    error_log("Database update failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred while processing your request.']);
    exit;
}

$mail_success = false;
$subject = "Password Reset OTP Code";
$body = "Hi " . htmlspecialchars($user['name']) . ",\n\nYour One-Time Password (OTP) is: " . $otp . "\n\nThis code expires in 5 minutes.";



$mail_function_used = mail($email, $subject, $body, "From: no-reply@yourdomain.com");

if ($mail_function_used) {
    $mail_success = true;
} else {
    
    error_log("Failed to send OTP email to: " . $email);
}




if ($mail_success) {
    echo json_encode(['success' => true, 'message' => 'An OTP has been sent to your email. It is valid for 5 minutes.']);
} else {
     
     echo json_encode(['success' => true, 'message' => 'An OTP has been generated, but we had trouble sending the email. Please check your spam folder.']);
}

?>
