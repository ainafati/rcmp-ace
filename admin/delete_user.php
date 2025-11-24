<?php

session_start();
include '../config.php'; // Kembali ke direktori utama untuk config.php

// 1. KONSISTENSI SESI & KAWALAN AKSES
$allowed_role = 'Admin';
if (!isset($_SESSION['person_id']) || $_SESSION['logged_in_role'] !== $allowed_role) {
    $_SESSION['error_message'] = "You must be logged in as an admin to perform this action.";
    header("Location: manage_accounts.php");
    exit();
}
$admin_id = $_SESSION['person_id']; // Menggunakan person_id sebagai ID admin untuk log
$admin_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ID yang dihantar dari borang manage_accounts.php adalah person_id
    $person_to_delete_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $role_list = isset($_POST['role']) ? $_POST['role'] : 'Unknown Role'; // Role digunakan hanya untuk mesej log/success

    if ($person_to_delete_id <= 0) {
        $_SESSION['error_message'] = "Invalid ID specified for deletion.";
        header("Location: manage_accounts.php");
        exit();
    }
    
    // Elakkan Admin daripada memadam akaunnya sendiri
    if ($person_to_delete_id === $admin_id) {
        $_SESSION['error_message'] = "You cannot delete your own admin account.";
        header("Location: manage_accounts.php");
        exit();
    }

    $conn->begin_transaction();
    $delete_successful = false;
    
    try {
        // A. DELETE dari person_roles (Wajib untuk Foreign Key Constraint)
        $sql_roles = "DELETE FROM person_roles WHERE person_id = ?";
        $stmt_roles = $conn->prepare($sql_roles);
        
        if ($stmt_roles === false) {
             throw new Exception("Failed to prepare role deletion statement: " . $conn->error);
        }
        
        $stmt_roles->bind_param("i", $person_to_delete_id);
        $stmt_roles->execute();
        $stmt_roles->close();

        // B. DELETE dari person (Rekod utama)
        $sql_person = "DELETE FROM person WHERE person_id = ?";
        $stmt_person = $conn->prepare($sql_person);
        
        if ($stmt_person === false) {
            throw new Exception("Failed to prepare person deletion statement: " . $conn->error);
        }
        
        $stmt_person->bind_param("i", $person_to_delete_id);
        
        if (!$stmt_person->execute()) {
             throw new Exception("Failed to delete account from person table: " . $stmt_person->error);
        }
        
        if ($stmt_person->affected_rows === 0) {
            throw new Exception("Account not found in person table or already deleted.");
        }

        $stmt_person->close();
        
        // C. COMMIT Transaction
        $conn->commit();
        $delete_successful = true;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Failed to delete account (ID: {$person_to_delete_id}, Role: {$role_list}). Error: " . $e->getMessage();
    }

    if ($delete_successful) {
        $_SESSION['success_message'] = "Account (ID: {$person_to_delete_id}, Role: {$role_list}) has been deleted successfully.";
        
        // Log Aktiviti (jika fungsi log_activity wujud)
        if (function_exists('log_activity')) {
            // Pastikan log_activity mengambil admin_id, bukannya admin_name
            log_activity($conn, 'admin', $admin_id, 'ACCOUNT_DELETE', "Admin {$admin_name} deleted {$role_list} account (Person ID: {$person_to_delete_id}).");
        }
    }

} else {
    $_SESSION['error_message'] = "Invalid request method.";
}

$conn->close();
header("Location: manage_accounts.php");
exit();
?>