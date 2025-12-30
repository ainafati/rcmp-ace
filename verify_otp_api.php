<?php

include 'config.php'; 


ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');


function sendJsonResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}


$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$otp_token = isset($_POST['token']) ? trim($_POST['token']) : '';
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

if (empty($email) || empty($otp_token) || empty($new_password)) {
    sendJsonResponse(false, "Incomplete data (Email, verification code, or new password missing).");
}

$password = $new_password;


$uppercase = preg_match('@[A-Z]@', $password);
$lowercase = preg_match('@[a-z]@', $password);
$number    = preg_match('@[0-9]@', $password);
$specialChars = preg_match('@[\W_]@', $password);

if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
    $error_message = 'The password does not meet the requirements. Please make sure it contains at least 8 characters, uppercase letters, lowercase letters, numbers, and special characters.';
    sendJsonResponse(false, $error_message);
}



if ($conn->connect_error) {
    error_log("DB Connection Error: " . $conn->connect_error, 0);
    sendJsonResponse(false, "Ralat sistem. Gagal menyambung ke pangkalan data.");
}

date_default_timezone_set('Asia/Kuala_Lumpur');
$current_time = date("Y-m-d H:i:s");



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
    
    
    $reset_data = $result->fetch_assoc();
    $reset_id = $reset_data['id'];
    
    
    $table_name = 'person';
    $id_column = 'person_id'; 

    
    $user_id = null;
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

    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    
    
    $update_stmt = $conn->prepare("UPDATE $table_name SET password = ? WHERE $id_column = ?");

    if ($update_stmt === false) {
        error_log("Prepare Error (Update): " . $conn->error, 0);
        $conn->close();
        sendJsonResponse(false, "Ralat sistem. Gagal menyediakan query (Update).");
    }

    $update_stmt->bind_param("si", $hashed_password, $user_id);

    
    if ($update_stmt->execute()) {
        
        
        $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE id = ?");
        $delete_stmt->bind_param("i", $reset_id);
        $delete_stmt->execute();

        $conn->close();
        sendJsonResponse(true, "Your password has been successfully set!");
    } else {
        
        $error_message = "Gagal menetapkan kata laluan. Ralat DB: " . $update_stmt->error;
        error_log("Update Execute Error: " . $error_message, 0);
        $conn->close();
        sendJsonResponse(false, $error_message); 
    }

} else {
    
    $conn->close();
    sendJsonResponse(false, "The verification code is invalid or has expired. Please resend the code if necessary.");
}
?>