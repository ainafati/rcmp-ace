<?php
session_start();
include 'config.php';

// Pastikan hanya Admin yang boleh mengakses fail ini
$allowed_role = 'Admin';
if (!isset($_SESSION['person_id']) || $_SESSION['logged_in_role'] !== $allowed_role) {
    $_SESSION['error_message'] = "Access denied.";
    header("Location: login.php");
    exit();
}

// Semak jika permintaan adalah POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ******************************************************
    // 1. PENGAMBILAN DAN SANITASI DATA
    // ******************************************************
    
    // Ambil data dari borang
    $person_id = filter_input(INPUT_POST, 'person_id', FILTER_VALIDATE_INT);
    $phoneNum = filter_input(INPUT_POST, 'phoneNum', FILTER_SANITIZE_SPECIAL_CHARS);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_SPECIAL_CHARS);
    $suspension_remarks = filter_input(INPUT_POST, 'suspension_remarks', FILTER_SANITIZE_SPECIAL_CHARS);

    // Semak data kritikal (person_id dan status)
    if ($person_id === false || $person_id === null || empty($status)) {
        $_SESSION['error_message'] = "Invalid input data received.";
        header("Location: manage_accounts.php");
        exit();
    }

    // Pembersihan remarks: Jika status bukan 'Suspended', set remarks kepada NULL
    if (strtolower($status) !== 'suspended') {
        $suspension_remarks = null;
    } else {
        // Jika status adalah 'Suspended' tetapi remarks kosong, kita sepatutnya telah dihalang oleh JS.
        // Walau bagaimanapun, kita tambah semakan keselamatan di sini.
        if (empty($suspension_remarks)) {
            $_SESSION['error_message'] = "Suspension remarks are required when status is 'Suspended'.";
            header("Location: manage_accounts.php");
            exit();
        }
    }
    
    // ******************************************************
    // 2. KEMAS KINI REKOD PENGGUNA
    // ******************************************************
    
    // Siapkan pernyataan SQL untuk mengemas kini jadual person
    $stmt = $conn->prepare("UPDATE person SET phoneNum = ?, status = ?, suspension_remarks = ? WHERE person_id = ?");
    
    if ($stmt === false) {
        $_SESSION['error_message'] = "Database error: Failed to prepare the update statement. " . $conn->error;
        header("Location: manage_accounts.php");
        exit();
    }

    // Bind parameter (s: string, s: string, s: string, i: integer)
    // Walaupun $suspension_remarks mungkin NULL, mysqli_stmt_bind_param memerlukan jenis data yang ditentukan.
    // Jika $suspension_remarks adalah NULL, kita perlu menggunakan bind_param yang sedikit berbeza atau memastikan ia dihantar sebagai string kosong/NULL yang dikendalikan oleh DB.
    
    // Cara standard untuk mengendalikan NULL/string:
    $stmt->bind_param("sssi", $phoneNum, $status, $suspension_remarks, $person_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Account for Person ID #{$person_id} updated successfully.";
    } else {
        $_SESSION['error_message'] = "Error updating account: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    // ******************************************************
    // 3. PENGALIHAN (REDIRECT)
    // ******************************************************
    header("Location: manage_accounts.php");
    exit();
} else {
    // Jika tiada data POST, alihkan balik
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: manage_accounts.php");
    exit();
}
?>