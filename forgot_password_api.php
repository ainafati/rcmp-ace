<?php
header('Content-Type: application/json');
include 'config.php'; // sambung ke database
include 'config_email.php'; // file yang ada SMTP_USER & SMTP_PASS

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// PENTING: Initialization Pembolehubah
$user_found = false;
$role = null;

// ✅ Semak kalau POST ada data email
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
echo json_encode(["success" => false, "message" => "Invalid request method."]);
 exit;
}

if (empty($_POST['email'])) {
 echo json_encode(["success" => false, "message" => "Email is required."]);
exit;
}

$email = trim($_POST['email']);

// 1. Cuba cari dalam jadual 'technician'
$stmt_tech = $conn->prepare("SELECT email FROM technician WHERE email = ?");
$stmt_tech->bind_param("s", $email);
$stmt_tech->execute();
if ($stmt_tech->get_result()->num_rows > 0) {
	$user_found = true;
	$role = 'technician';
}
$stmt_tech->close();

// 2. Cuba cari dalam jadual 'user' (hanya jika belum ditemui)
if (!$user_found) {
	$stmt_user = $conn->prepare("SELECT email FROM user WHERE email = ?");
	$stmt_user->bind_param("s", $email);
	$stmt_user->execute();
 if ($stmt_user->get_result()->num_rows > 0) {
	    $user_found = true;
	    $role = 'user';
   }
    $stmt_user->close();
}

// Semak Keputusan Akhir
if (!$user_found) {
	echo json_encode(["success" => false, "message" => "Email not found in our system."]);
	exit;
}

// ************** BAHAGIAN INI TELAH DIBUANG: if ($result->num_rows === 0) { ... } **************

// ✅ Generate OTP & expiry
$otp = rand(100000, 999999);
date_default_timezone_set('Asia/Kuala_Lumpur');
$expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

// ✅ Padam OTP lama, simpan yang baru
$stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

$stmt = $conn->prepare("INSERT INTO password_resets (email, otp, role, expiry) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $email, $otp, $role, $expiry);

if (!$stmt->execute()) {
// Tambah maklumat ralat database untuk debugging
 echo json_encode(["success" => false, "message" => "Database error while saving OTP: " . $conn->error]); 
 exit;
}

// ✅ Hantar email guna PHPMailer
$mail = new PHPMailer(true);

try {
 $mail->isSMTP();
 $mail->Host = 'smtp.gmail.com';
 $mail->SMTPAuth = true;
 $mail->Username = SMTP_USER;
 $mail->Password = SMTP_PASS;
 $mail->SMTPSecure = 'ssl';
 $mail->Port = 465;

 // Optional: bypass SSL verify kalau local test
 $mail->SMTPOptions = [
 'ssl' => [
'verify_peer' => false,
 'verify_peer_name' => false,
 'allow_self_signed' => true,
 ],
 ];

 // Email details
 $mail->setFrom(SMTP_USER, 'Inventory System');
 $mail->addAddress($email); // ← ni penting, hantar ke email yang user isi
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