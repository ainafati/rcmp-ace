<?php
session_start();
include '../config.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['person_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi tamat.']);
    exit();
}

$current_tech_id = (int)$_SESSION['person_id'];

try {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE person_id = ? AND is_read = 0");
    
    if ($stmt) {
        $stmt->bind_param("i", $current_tech_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success', 'message' => 'All notifications have been marked as read.']);
    } else {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>