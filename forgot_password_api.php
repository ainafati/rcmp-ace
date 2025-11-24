<?php
header('Content-Type: application/json');
// Ensure config files are included first
include 'config.php'; 
include 'config_email.php'; // Includes SMTP_HOST, SMTP_USER, etc.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Assuming PHPMailer path is correct
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';


$user_found = false;
$role = null;

// 1. INPUT VALIDATION
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}

if (empty($_POST['email'])) {
    echo json_encode(["success" => false, "message" => "Email is required."]);
    exit;
}

$email = trim($_POST['email']);

// 2. CHECK USER AND ROLE
$sql = "SELECT p.person_id, r.role_name 
        FROM person p
        JOIN person_roles pr ON p.person_id = pr.person_id
        JOIN roles r ON pr.role_id = r.role_id
        WHERE p.email = ?";

$stmt_person = $conn->prepare($sql);

if ($stmt_person === false) {
    echo json_encode(["success" => false, "message" => "Database error during query preparation: " . $conn->error]);
    exit;
}

$stmt_person->bind_param("s", $email);
$stmt_person->execute();
$result_person = $stmt_person->get_result();

if ($result_person->num_rows > 0) {
    // Fetch the first role found
    $user_data = $result_person->fetch_assoc();
    $user_found = true;
    // Store role in lowercase
    $role = strtolower($user_data['role_name']); 
}
$stmt_person->close();


if (!$user_found) {
    echo json_encode(["success" => false, "message" => "Email not found in our system."]);
    exit;
}

// 3. GENERATE AND SAVE OTP
$otp = rand(100000, 999999);
date_default_timezone_set('Asia/Kuala_Lumpur');
$expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

// Delete any previous OTP for this email
$stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

// Insert new OTP
$stmt = $conn->prepare("INSERT INTO password_resets (email, otp, role, expiry) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $email, $otp, $role, $expiry); 

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Database error while saving OTP: " . $conn->error]); 
    exit;
}


// 4. SEND EMAIL VIA OFFICE 365/EXCHANGE (PHPMailer)
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    
    // --- USING CONFIG_EMAIL DEFINITIONS ---
    $mail->Host = SMTP_HOST; // 'smtp.office365.com'
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE; // 'tls'
    $mail->Port = SMTP_PORT; // 587
    // ------------------------------------

    // Recommended for Office 365/Exchange on port 587 (TLS)
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];

    // Set From Address and Name from config
    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME); 
    $mail->addAddress($email); 
    $mail->isHTML(true);
    $mail->Subject = 'Your One-Time Password (OTP)';
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>
            <h2 style='color:#003366;'>UniKL Inventory Password Reset</h2>
            <p>You requested a password reset. Your OTP code is:</p>
            <h1 style='color:#003366; letter-spacing: 3px;'>$otp</h1>
            <p>This code will expire in <b>10 minutes</b>. If you did not request this, please ignore this email.</p>
        </div>
    ";

    $mail->send();
    echo json_encode(["success" => true, "message" => "OTP sent successfully to your email.", "role" => $role]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Failed to send OTP. Mailer Error: {$mail->ErrorInfo}"]);
}
?>