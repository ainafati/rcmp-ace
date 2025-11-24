<?php
session_start();
include '../config.php';


$allowed_role = 'Admin';
if (!isset($_SESSION['person_id']) || $_SESSION['logged_in_role'] !== $allowed_role) {
    $_SESSION['error_message'] = "Access denied.";
    header("Location: login.php");
    exit();
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    
    
    
    
    
    $person_id = filter_input(INPUT_POST, 'person_id', FILTER_VALIDATE_INT);
    $phoneNum = filter_input(INPUT_POST, 'phoneNum', FILTER_SANITIZE_SPECIAL_CHARS);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_SPECIAL_CHARS);
    $suspension_remarks = filter_input(INPUT_POST, 'suspension_remarks', FILTER_SANITIZE_SPECIAL_CHARS);

    
    if ($person_id === false || $person_id === null || empty($status)) {
        $_SESSION['error_message'] = "Invalid input data received.";
        header("Location: manage_accounts.php");
        exit();
    }

    
    if (strtolower($status) !== 'suspended') {
        $suspension_remarks = null;
    } else {
        
        
        if (empty($suspension_remarks)) {
            $_SESSION['error_message'] = "Suspension remarks are required when status is 'Suspended'.";
            header("Location: manage_accounts.php");
            exit();
        }
    }
    
    
    
    
    
    
    $stmt = $conn->prepare("UPDATE person SET phoneNum = ?, status = ?, suspension_remarks = ? WHERE person_id = ?");
    
    if ($stmt === false) {
        $_SESSION['error_message'] = "Database error: Failed to prepare the update statement. " . $conn->error;
        header("Location: manage_accounts.php");
        exit();
    }

    
    
    
    
    
    $stmt->bind_param("sssi", $phoneNum, $status, $suspension_remarks, $person_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Account for Person ID #{$person_id} updated successfully.";
    } else {
        $_SESSION['error_message'] = "Error updating account: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    
    
    
    header("Location: manage_accounts.php");
    exit();
} else {
    
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: manage_accounts.php");
    exit();
}
?>