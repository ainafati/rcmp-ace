<?php

include 'config.php'; 

// Tetapan ralat dan header
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Fungsi untuk menghantar respons JSON
function sendJsonResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// Mendapatkan data POST dari borang
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$otp_token = isset($_POST['token']) ? trim($_POST['token']) : '';
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

if (empty($email) || empty($otp_token) || empty($new_password)) {
    sendJsonResponse(false, "Data tidak lengkap (E-mel, kod pengesahan, atau kata laluan baharu tiada).");
}

$password = $new_password;

// 2. Pengesahan Kekuatan Kata Laluan
$uppercase = preg_match('@[A-Z]@', $password);
$lowercase = preg_match('@[a-z]@', $password);
$number    = preg_match('@[0-9]@', $password);
$specialChars = preg_match('@[\W_]@', $password);

if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
    $error_message = 'Kata laluan tidak memenuhi keperluan. Sila pastikan ia mengandungi sekurang-kurangnya 8 aksara, huruf besar, huruf kecil, nombor, dan aksara khas.';
    sendJsonResponse(false, $error_message);
}


// Semakan Sambungan Pangkalan Data
if ($conn->connect_error) {
    error_log("DB Connection Error: " . $conn->connect_error, 0);
    sendJsonResponse(false, "Ralat sistem. Gagal menyambung ke pangkalan data.");
}

date_default_timezone_set('Asia/Kuala_Lumpur');
$current_time = date("Y-m-d H:i:s");


// 3. SEMAKAN OTP DAN TARIKH LUPUT
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
    
    // Kod Pengesahan Sah dan Belum Luput
    $reset_data = $result->fetch_assoc();
    $reset_id = $reset_data['id'];
    
    // ** PEMBETULAN NAMA LAJUR BERDASARKAN SKEMA DB ANDA **
    $table_name = 'person';
    $id_column = 'person_id'; // Nama Lajur ID yang BETUL dari DB anda

    // Dapatkan ID pengguna sebenar berdasarkan e-mel
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

    // 4. HASHING KATA LALUAN BARU (KRITIKAL)
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // KEMAS KINI KATA LALUAN
    // Jenis data: 's' untuk String (password hash), 'i' untuk Integer (person_id)
    $update_stmt = $conn->prepare("UPDATE $table_name SET password = ? WHERE $id_column = ?");

    if ($update_stmt === false) {
        error_log("Prepare Error (Update): " . $conn->error, 0);
        $conn->close();
        sendJsonResponse(false, "Ralat sistem. Gagal menyediakan query (Update).");
    }

    $update_stmt->bind_param("si", $hashed_password, $user_id);

    // 5. PENYEMAKAN EKSEKUSI (CRITICAL: Mencegah Ralat Senyap)
    if ($update_stmt->execute()) {
        
        // Padamkan token reset dari jadual password_resets
        $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE id = ?");
        $delete_stmt->bind_param("i", $reset_id);
        $delete_stmt->execute();

        $conn->close();
        sendJsonResponse(true, "Kata laluan anda telah berjaya ditetapkan!");
    } else {
        // Log ralat sebenar jika execute() gagal
        $error_message = "Gagal menetapkan kata laluan. Ralat DB: " . $update_stmt->error;
        error_log("Update Execute Error: " . $error_message, 0);
        $conn->close();
        sendJsonResponse(false, $error_message); 
    }

} else {
    // Kod pengesahan tidak sah
    $conn->close();
    sendJsonResponse(false, "Kod pengesahan tidak sah atau telah luput. Sila hantar semula kod jika perlu.");
}
?>