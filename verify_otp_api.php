<?php
// Fail: reset_password_submit.php (atau seumpamanya)

// Sediakan konfigurasi ralat (Hanya untuk debugging)
ini_set('display_errors', 0); // Pastikan ini 0 dalam production
error_reporting(E_ALL);

include 'config.php'; // Fail sambungan DB anda

// Header dan fungsi untuk menghantar respons JSON
header('Content-Type: application/json');

function sendJsonResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// 1. Terima dan sahkan input
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$otp_token = isset($_POST['token']) ? trim($_POST['token']) : ''; 
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : ''; 

if (empty($email) || empty($otp_token) || empty($new_password)) {
    sendJsonResponse(false, "Data tidak lengkap (E-mel, kod pengesahan, atau kata laluan baharu tiada).");
}

$password = $new_password;

// 2. Sahkan kekuatan kata laluan (Password Strength)
$uppercase = preg_match('@[A-Z]@', $password);
$lowercase = preg_match('@[a-z]@', $password);
$number    = preg_match('@[0-9]@', $password);
$specialChars = preg_match('@[\W_]@', $password); 

if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
    $error_message = 'Kata laluan tidak memenuhi keperluan. Sila pastikan ia mengandungi sekurang-kurangnya 8 aksara, huruf besar, huruf kecil, nombor, dan aksara khas.';
    sendJsonResponse(false, $error_message);
}

// 3. Sambungan DB (Semakan pantas jika sambungan gagal)
if ($conn->connect_error) {
    error_log("DB Connection Error: " . $conn->connect_error, 0); 
    sendJsonResponse(false, "Ralat sistem. Gagal menyambung ke pangkalan data.");
}

// Tetapkan Zon Masa
date_default_timezone_set('Asia/Kuala_Lumpur'); 
$current_time = date("Y-m-d H:i:s"); 

// 4. Semak OTP dan tarikh luput dalam jadual password_resets
$stmt = $conn->prepare("SELECT id, email, otp, expiry, role FROM password_resets WHERE email = ? AND otp = ? AND expiry > ?");

if ($stmt === false) { 
    error_log("Prepare Error (OTP): " . $conn->error, 0); 
    $conn->close(); 
    sendJsonResponse(false, "Ralat sistem. Gagal menyediakan query (OTP)."); 
}

$stmt->bind_param("sss", $email, $otp_token, $current_time);
$stmt->execute();
$result = $stmt->get_result(); 

if ($result->num_rows === 1) {
    // OTP Sah dan Belum Luput
    $reset_data = $result->fetch_assoc();
    $reset_id = $reset_data['id']; 
    $user_role = $reset_data['role'];

    
    // 5. MENCARI PERSON_ID (DIJAMIN GUNA JADUAL PERSON DAN PERSON_ID)
    
    // Berdasarkan skema DB anda, semua pengguna ada dalam jadual 'person'
    $table_name = 'person';
    $id_column = 'person_id'; 
    
    $user_id = null;
    
    // Query untuk dapatkan person_id
    $stmt_id = $conn->prepare("SELECT $id_column FROM $table_name WHERE email = ?"); 
    
    if ($stmt_id === false) { 
        error_log("Prepare Error (User ID): " . $conn->error, 0); 
        $conn->close(); 
        sendJsonResponse(false, "Ralat sistem. Gagal menyediakan query (User ID)."); 
    }
    
    $stmt_id->bind_param("s", $email); 
    $stmt_id->execute();
    $result_user = $stmt_id->get_result();
    
    if ($result_user->num_rows === 1) {
        $user_id = $result_user->fetch_assoc()[$id_column]; 
    } else {
        $conn->close();
        sendJsonResponse(false, "Ralat sistem. Pengguna tidak ditemui dalam jadual utama (Person).");
    }
    
    // 6. KEMAS KINI KATA LALUAN
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $update_stmt = $conn->prepare("UPDATE $table_name SET password = ? WHERE $id_column = ?"); 

    if ($update_stmt === false) { 
        error_log("Prepare Error (Update): " . $conn->error, 0); 
        $conn->close(); 
        sendJsonResponse(false, "Ralat sistem. Gagal menyediakan query (Update)."); 
    }

    $update_stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($update_stmt->execute()) {
        
        // 7. PADAM OTP selepas kejayaan
        $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE id = ?");
        $delete_stmt->bind_param("i", $reset_id);
        $delete_stmt->execute(); 

        $conn->close();
        sendJsonResponse(true, "Kata laluan anda telah berjaya ditetapkan!");
    } else {
        error_log("Update Execute Error: " . $update_stmt->error, 0);
        $conn->close();
        sendJsonResponse(false, "Gagal menetapkan kata laluan. Ralat kemas kini DB.");
    }

} else {
    // OTP tidak sah, telah luput, atau tidak sepadan
    $conn->close();
    sendJsonResponse(false, "Kod pengesahan tidak sah atau telah luput. Sila hantar semula kod jika perlu.");
}
?>