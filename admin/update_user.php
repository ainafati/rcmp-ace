<?php
session_start();
include 'config.php';


if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error_message'] = "You must be logged in as an admin to perform this action.";
    header("Location: manage_accounts.php");
    exit();
}
$admin_id = $_SESSION['admin_id'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $role = isset($_POST['role']) ? $_POST['role'] : '';
    $phoneNum = isset($_POST['phoneNum']) ? trim($_POST['phoneNum']) : '';
    $status_input = isset($_POST['status']) ? trim($_POST['status']) : ''; 
    $remarks = isset($_POST['suspension_remarks']) ? trim($_POST['suspension_remarks']) : '';

    
    $table_name = '';
    $id_column = '';
    if (strtolower($role) === 'user') {
        $table_name = 'user';
        $id_column = 'user_id';
    } elseif (strtolower($role) === 'technician') {
        $table_name = 'technician';
        $id_column = 'tech_id';
    } else {
        $_SESSION['error_message'] = "Invalid account role specified.";
        header("Location: manage_accounts.php");
        exit();
    }

    
    $db_status = '';
    if (strtolower($status_input) === 'active') {
        $db_status = 'Active'; 
        $remarks = NULL;       
    } else {
        $db_status = 'Suspended'; 
    }

    
    $sql = "UPDATE $table_name SET phoneNum = ?, status = ?, suspension_remarks = ? WHERE $id_column = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("sssi", $phoneNum, $db_status, $remarks, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Account for $role (ID: $id) updated successfully.";
            
            
            if (function_exists('log_activity')) {
                log_activity($conn, 'admin', $admin_id, 'ACCOUNT_UPDATE', "Admin updated $role account (ID: $id). Set status to $db_status.");
            }
        } else {
            $_SESSION['error_message'] = "Failed to update account. Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Failed to prepare statement. Error: " . $conn->error;
    }
    
    $conn->close();
    header("Location: manage_accounts.php");
    exit();

} else {
    
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: manage_accounts.php");
    exit();
}
?>