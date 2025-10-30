<?php
session_start();
include 'config.php';

// Ensure technician is logged in
if (!isset($_SESSION['tech_id'])) {
    echo 'Not logged in';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize input
    $reserve_id = isset($_POST['reserve_id']) ? (int)$_POST['reserve_id'] : 0;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

    // Check if valid data is provided
    if ($reserve_id > 0 && !empty($remarks)) {
        // Update remarks in the database
        $stmt = $conn->prepare("UPDATE reservations SET remarks = ? WHERE reserve_id = ?");
        $stmt->bind_param("si", $remarks, $reserve_id);

        if ($stmt->execute()) {
            echo 'success';  // Respond back to client
        } else {
            echo 'Database error: ' . $stmt->error;  // Show DB error if any
        }

        $stmt->close();
    } else {
        echo 'Invalid data received';
    }
}
?>
