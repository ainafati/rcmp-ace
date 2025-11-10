<?php
session_start();
include 'config.php';

// Validate and sanitize input
if (isset($_POST['reserve_id']) && isset($_POST['status'])) {
    $reserve_id = (int)$_POST['reserve_id'];
    $status = (int)$_POST['status'];

    // Update the handled_status in the reservations table
    $sql = "UPDATE reservations SET handled_status = ? WHERE reserve_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $status, $reserve_id);
        if ($stmt->execute()) {
            echo 'success'; // Successful update
        } else {
            echo 'failure'; // Query execution failed
        }
        $stmt->close();
    } else {
        echo 'failure'; // Failed to prepare statement
    }
} else {
    echo 'failure'; // Missing parameters
}
?>
