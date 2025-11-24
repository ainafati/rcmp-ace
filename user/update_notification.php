<?php

// Tetapan ralat dimatikan untuk pengeluaran (production)
error_reporting(E_ALL); 
ini_set('display_errors', '0'); 
ini_set('display_startup_errors', '0');

session_start();

// Tetapkan header respons JSON
header('Content-Type: application/json');

// Respons lalai
$response = ['success' => false, 'message' => 'Invalid Request or Not Logged In.'];


// Sertakan fail konfigurasi pangkalan data
include '../config.php'; 

// Semak sambungan pangkalan data
if (!$conn) {
    $response['message'] = "Database connection failed.";
    echo json_encode($response);
    exit();
}

// Semak status log masuk pengguna
if (!isset($_SESSION['person_id'])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pembetulan: Menggunakan isset() untuk action
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $user_id = (int) $_SESSION['person_id'];

    if ($action === 'mark_read') {
        
        // Baris 41: Pembetulan ralat - Menggunakan ternary operator/isset()
        $id = isset($_POST['id']) ? $_POST['id'] : 'all';
        
        // --- LOGIK: TANDAKAN SEMUA SEBAGAI TELAH DIBACA ---
        if ($id === 'all') {
            // SQL untuk menandakan SEMUA notifikasi yang belum dibaca sebagai telah dibaca untuk pengguna ini
            $sql = "UPDATE notifications SET is_read = 1 WHERE person_id = ? AND is_read = 0";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    // Semak jika sebarang baris terjejas (jika 0, mungkin tiada notif baru)
                    $affected_rows = $stmt->affected_rows;
                    $response = [
                        'success' => true, 
                        'message' => 'All unread notifications marked as read. ' . $affected_rows . ' notifications updated.'
                    ];
                } else {
                    $response['message'] = "Database execution error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $response['message'] = "Prepare statement failed: " . $conn->error;
            }
        } 
        
        // --- LOGIK: TANDAKAN SATU NOTIFIKASI SEBAGAI TELAH DIBACA (Opsional) ---
        else {
            // Kita anggap $id adalah ID notifikasi tertentu (integer)
            $notif_id = (int) $id;

            // Pastikan notifikasi ini milik pengguna yang log masuk (keselamatan tambahan)
            $sql_single = "UPDATE notifications SET is_read = 1 WHERE id = ? AND person_id = ?";
            
            $stmt_single = $conn->prepare($sql_single);
            
            if ($stmt_single) {
                $stmt_single->bind_param("ii", $notif_id, $user_id);
                
                if ($stmt_single->execute()) {
                    $response = ['success' => true, 'message' => 'Notification ID ' . $notif_id . ' marked as read.'];
                } else {
                    $response['message'] = "Database execution error: " . $stmt_single->error;
                }
                $stmt_single->close();
            } else {
                $response['message'] = "Prepare statement failed for single ID: " . $conn->error;
            }
        }
    } else {
        $response['message'] = 'Invalid action specified.';
    }
}


echo json_encode($response);
exit();