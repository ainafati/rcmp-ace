<?php
session_start();
include 'config.php';

if (!isset($_SESSION['tech_id'])) {
    echo 'Not logged in';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reserve_id = (int)$_POST['reserve_id'];
    $remarks = $_POST['remarks'];

    // Sanitize remarks to prevent SQL injection
    $remarks = mysqli_real_escape_string($conn, $remarks);

    // Update the remarks in the reservations table
    $sql = "UPDATE reservations SET remarks = ? WHERE reserve_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $remarks, $reserve_id);

    if ($stmt->execute()) {
        echo 'Remarks updated successfully';
    } else {
        echo 'Failed to update remarks';
    }
    $stmt->close();
}
?>
