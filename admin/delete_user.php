<?php
session_start();
include 'config.php';

if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error_message'] = "You must be logged in as an admin to perform this action.";
    header("Location: manage_accounts.php");
    exit();
}
$admin_id = $_SESSION['admin_id']; // Dapatkan ID admin untuk log


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $role = isset($_POST['role']) ? $_POST['role'] : '';

    $table_name = '';
    $id_column = '';
    if (strtolower($role) === 'user') {
        $table_name = 'user';
        $id_column = 'user_id';
    } elseif (strtolower($role) === 'technician') {
        $table_name = 'technician';
        $id_column = 'tech_id';
    } else {
        $_SESSION['error_message'] = "Invalid account role specified for deletion.";
        header("Location: manage_accounts.php");
        exit();
    }

    if ($id > 0) {
        $sql = "DELETE FROM $table_name WHERE $id_column = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $_SESSION['success_message'] = "Account (ID: $id, Role: $role) has been deleted successfully.";
                    
                    if (function_exists('log_activity')) {
                        log_activity($conn, 'admin', $admin_id, 'ACCOUNT_DELETE', "Admin deleted $role account (ID: $id).");
                    }
                } else {
                    $_SESSION['error_message'] = "Account not found or already deleted.";
                }
            } else {
                $_SESSION['error_message'] = "Failed to delete account. Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Failed to prepare statement. Error: " . $conn->error;
        }
    } else {
        $_SESSION['error_message'] = "Invalid ID specified for deletion.";
    }

} else {
    $_SESSION['error_message'] = "Invalid request method.";
}

$conn->close();
header("Location: manage_accounts.php");
exit();
?>