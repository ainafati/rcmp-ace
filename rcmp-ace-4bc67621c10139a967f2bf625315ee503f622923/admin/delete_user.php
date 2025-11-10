<?php
session_start();
include 'config.php';

// Get the user ID from the URL
$id = $_GET['id'];
$role = $_GET['role'];

// Delete the user based on role
if ($role == 'User') {
    $sql = "DELETE FROM user WHERE user_id = ?";
} elseif ($role == 'Technician') {
    $sql = "DELETE FROM technician WHERE tech_id = ?";
} elseif ($role == 'Admin') {
    $sql = "DELETE FROM admin WHERE admin_id = ?";
}

// Prepare and execute delete query
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: manage_accounts.php");
exit;
?>
