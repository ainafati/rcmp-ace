<?php
// KOD UNTUK verify_otp_api.php - Versi Akhir dengan Pengesahan Kata Laluan

ini_set('display_errors', 0);
error_reporting(E_ALL);

// Mulakan dan hentikan output buffering
ob_start();
include 'config.php'; 
ob_end_clean();

header('Content-Type: application/json');

function sendJsonResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// 1. Ambil input dari permintaan POST
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$otp_token = isset($_POST['token']) ? trim($_POST['token']) : ''; 
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : ''; 

if (empty($email) || empty($otp_token) || empty($new_password)) {
    sendJsonResponse(false, "Data tidak lengkap (E-mel, kod pengesahan, atau kata laluan baharu tiada).");
}

// -----------------------------------------------------
// 1.5. PENAMBAHAN: Pengesahan Kekuatan Kata Laluan
// -----------------------------------------------------
$password = $new_password;

$uppercase = preg_match('@[A-Z]@', $password);
$lowercase = preg_match('@[a-z]@', $password);
$number    = preg_match('@[0-9]@', $password);
// [\W_] bermaksud sebarang aksara bukan perkataan, termasuk _ 
$specialChars = preg_match('@[\W_]@', $password); 

if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
    $error_message = 'Kata laluan tidak memenuhi keperluan. Sila pastikan ia mengandungi sekurang-kurangnya 8 aksara, huruf besar, huruf kecil, nombor, dan aksara khas.';
    sendJsonResponse(false, $error_message);
}
// -----------------------------------------------------

// 2. Sambungan ke MySQL (menggunakan $conn dari config.php)
if ($conn->connect_error) {
    error_log("DB Connection Error: " . $conn->connect_error, 0); 
    sendJsonResponse(false, "Ralat sistem. Gagal menyambung ke pangkalan data.");
}

// ZON WAKTU
date_default_timezone_set('Asia/Kuala_Lumpur'); 
$current_time = date("Y-m-d H:i:s"); 

// 3. Mencari OTP yang SAH
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

    // Ambil nilai role
    $user_role = $reset_data['role'];

    // Tentukan nama jadual dan nama lajur ID berdasarkan peranan pengguna
    switch ($user_role) {
        case 'technician':
            $table_name = 'technician';
            $id_column = 'tech_id';
            break;
        case 'user':
            $table_name = 'user';
            $id_column = 'user_id';
            break;
        default:
            $conn->close();
            sendJsonResponse(false, "Ralat peranan. Peranan pengguna tidak sah.");
            break;
    }

    $user_id = null;
    $stmt_id = $conn->prepare("SELECT $id_column FROM $table_name WHERE email = ?"); // Query dinamik
    
    if ($stmt_id === false) { 
        error_log("Prepare Error (User ID): " . $conn->error, 0); 
        $conn->close(); 
        sendJsonResponse(false, "Ralat sistem. Gagal menyediakan query (User ID)."); 
    }
    
    $stmt_id->bind_param("s", $email); // Fix: Using $stmt_id for binding param
    $stmt_id->execute();
    $result_user = $stmt_id->get_result();
    
    if ($result_user->num_rows === 1) {
        $user_id = $result_user->fetch_assoc()[$id_column]; // Fetching the correct user ID
    } else {
        $conn->close();
        sendJsonResponse(false, "Ralat sistem. Pengguna tidak ditemui dalam jadual utama.");
    }
    
    // 4b: Hash kata laluan baharu
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // 4c: Kemas kini kata laluan menggunakan user_id
    $update_stmt = $conn->prepare("UPDATE $table_name SET password = ? WHERE $id_column = ?"); // Fix: Dynamically using table_name

    if ($update_stmt === false) { 
        error_log("Prepare Error (Update): " . $conn->error, 0); 
        $conn->close(); 
        sendJsonResponse(false, "Ralat sistem. Gagal menyediakan query (Update)."); 
    }

    $update_stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($update_stmt->execute()) {
        
        // 4d: Padamkan rekod OTP
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
    $conn->close();
    sendJsonResponse(false, "Kod pengesahan tidak sah atau telah luput. Sila hantar semula kod jika perlu.");
}
?>
