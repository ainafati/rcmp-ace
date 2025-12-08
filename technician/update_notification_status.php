<?php
session_start();
// Pastikan config.php mengandungi sambungan $conn (MySQLi)
include '../config.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['person_id']) || $_SESSION['logged_in_role'] !== 'Technician') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized or session expired']);
    exit();
}

$tech_id = (int)$_SESSION['person_id'];
$tech_role_id = 2; // GANTIKAN dengan ID role Technician yang sebenar

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];

    if ($id === 'all') {
        $sql = "UPDATE notifications SET is_read = 1 
                WHERE person_id = ? AND recipient_role_id = ? AND is_read = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $tech_id, $tech_role_id);
    } else {
        $sql = "UPDATE notifications SET is_read = 1 
                WHERE id = ? AND person_id = ? AND recipient_role_id = ?";
        $stmt = $conn->prepare($sql);
        $notif_id = (int)$id;
        $stmt->bind_param("iii", $notif_id, $tech_id, $tech_role_id);
    }

    if ($stmt && $stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Status updated.']);
    } else {
        error_log("Update error: " . $conn->error);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
    }
    if ($stmt) $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}

$conn->close();
?>