<?php
session_start();
include 'config.php';

// 1. Pastikan admin yang log masuk
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error_message'] = "You must be logged in as an admin to perform this action.";
    header("Location: manage_accounts.php");
    exit();
}
$admin_id = $_SESSION['admin_id'];

// 2. Semak jika kaedah adalah POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 3. Dapatkan data dengan selamat dari borang
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $role = isset($_POST['role']) ? $_POST['role'] : '';
    $phoneNum = isset($_POST['phoneNum']) ? trim($_POST['phoneNum']) : '';
    $status_input = isset($_POST['status']) ? trim($_POST['status']) : ''; // 'Active' or 'Suspended'
    $remarks = isset($_POST['suspension_remarks']) ? trim($_POST['suspension_remarks']) : '';

    // 4. Tentukan nama jadual dan kolum ID
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

    // 5. Logik untuk tukar status (kini simpan perkataan penuh)
    $db_status = '';
    if (strtolower($status_input) === 'active') {
        $db_status = 'Active'; // <-- DIKEMAS KINI
        $remarks = NULL;       // Kosongkan remarks jika diaktifkan
    } else {
        $db_status = 'Suspended'; // <-- DIKEMAS KINI
    }

    // 6. Sediakan query kemas kini
    $sql = "UPDATE $table_name SET phoneNum = ?, status = ?, suspension_remarks = ? WHERE $id_column = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("sssi", $phoneNum, $db_status, $remarks, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Account for $role (ID: $id) updated successfully.";
            
            // Log aktiviti
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
    // Bukan POST, halakan kembali
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: manage_accounts.php");
    exit();
}
?>