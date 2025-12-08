<?php
session_start();
include '../config.php'; // Pastikan laluan ke config.php betul

header('Content-Type: application/json');

if (!isset($_SESSION['person_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi tamat.']);
    exit();
}

$current_tech_id = (int)$_SESSION['person_id'];

try {
    // Tandakan SEMUA notifikasi untuk pengguna ini sebagai sudah dibaca
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE person_id = ? AND is_read = 0");
    
    if ($stmt) {
        $stmt->bind_param("i", $current_tech_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'Semua notifikasi telah ditanda sebagai dibaca.']);
    } else {
        throw new Exception("Gagal menyediakan query: " . $conn->error);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>