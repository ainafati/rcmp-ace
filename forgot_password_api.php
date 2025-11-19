<?php
header('Content-Type: application/json');
include 'config.php'; 
include 'config_email.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';


$user_found = false;
$role = null;


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}

if (empty($_POST['email'])) {
    echo json_encode(["success" => false, "message" => "Email is required."]);
    exit;
}

$email = trim($_POST['email']);

// ====================================================================
// START LOGIK MENCARI PENGGUNA DAN ROLE DALAM JADUAL PERSON
// ====================================================================

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
    // Pengguna dijumpai. Kita ambil peranan pertama.
    // Jika pengguna mempunyai berbilang peranan, logik ini akan menggunakan yang pertama ditemui.
    $user_data = $result_person->fetch_assoc();
    
    $user_found = true;
    // Peranan (role) akan menjadi 'technician', 'admin', atau 'user' mengikut data dalam jadual 'roles'.
    // Kita gunakan peranan ini sebagai pengenalan untuk jadual password_resets.
    $role = strtolower($user_data['role_name']); 
}
$stmt_person->close();


if (!$user_found) {
    echo json_encode(["success" => false, "message" => "Email not found in our system."]);
    exit;
}

// ====================================================================
// END LOGIK MENCARI PENGGUNA DAN ROLE
// ====================================================================


$otp = rand(100000, 999999);
date_default_timezone_set('Asia/Kuala_Lumpur');
$expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));


// Mula transaksi untuk password_resets
$stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

$stmt = $conn->prepare("INSERT INTO password_resets (email, otp, role, expiry) VALUES (?, ?, ?, ?)");
// PENTING: Gunakan variable $role yang baru diambil dari jadual person dan roles
$stmt->bind_param("ssss", $email, $otp, $role, $expiry); 

if (!$stmt->execute()) {

    echo json_encode(["success" => false, "message" => "Database error while saving OTP: " . $conn->error]); 
    exit;
}


$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;

    
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];

    
    $mail->setFrom(SMTP_USER, 'Inventory System');
    $mail->addAddress($email); 
    $mail->isHTML(true);
    $mail->Subject = 'Your One-Time Password (OTP)';
    $mail->Body = "
        <div style='font-family: Arial, sans-serif; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>
            <h2 style='color:#4f46e5;'>Password Reset Request</h2>
            <p>Your OTP code is:</p>
            <h1 style='color:#4f46e5; letter-spacing: 3px;'>$otp</h1>
            <p>This code will expire in <b>10 minutes</b>.</p>
        </div>
    ";

    $mail->send();
    echo json_encode(["success" => true, "message" => "OTP sent successfully to your email.", "role" => $role]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Failed to send OTP. Mailer Error: {$mail->ErrorInfo}"]);
}
?>