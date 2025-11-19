<?php
// Mencegah PHP dari mencetak sebarang notis/warning yang akan merosakkan output JSON
error_reporting(E_ALL); 
ini_set('display_errors', '0'); 
ini_set('display_startup_errors', '0');

session_start();

// Tetapkan header respons sebagai JSON segera.
header('Content-Type: application/json');

// Jika skrip berhenti awal, pastikan respons JSON dikeluarkan.
$response = ['success' => false, 'message' => 'Invalid Request or Not Logged In.'];

// Pastikan laluan ini BETUL relatif kepada update_notification.php
// Jika config.php di folder utama, gunakan '../config.php'
include '../config.php'; 

// Semak sambungan DB
if (!$conn) {
    $response['message'] = "Database connection failed.";
    echo json_encode($response);
    exit();
}

// Semak log masuk
if (!isset($_SESSION['person_id'])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (int) $_SESSION['person_id'];

    if ($action === 'mark_read') {
        $id = $_POST['id'] ?? 'all';
        
        // Kuiri untuk menandakan semua notifikasi (is_read = 0) sebagai dibaca (is_read = 1)
        $sql = "UPDATE notifications SET is_read = 1 WHERE person_id = ?";
        
        if ($id === 'all') {
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    // Berjaya dikemaskini.
                    $response = ['success' => true, 'message' => 'All notifications marked as read.'];
                } else {
                    $response['message'] = "Database error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $response['message'] = "Prepare statement failed: " . $conn->error;
            }
        } 
        // Jika anda mahu menambah logik notifikasi individu, tambahkan di sini
        else {
             $response['message'] = 'Individual marking not implemented (but action failed successfully).';
        }
    } else {
        $response['message'] = 'Invalid action specified.';
    }
}

// Sentiasa keluarkan respons dalam format JSON di akhir fail.
echo json_encode($response);
exit();

// PENTING: JANGAN ada tag penutup ?> di hujung fail ini!