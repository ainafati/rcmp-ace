<?php
session_start();
include 'config.php';

// Pastikan hanya admin yang boleh akses
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Pastikan borang telah dihantar menggunakan kaedah POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Ambil data dari borang (TERMASUK 'ic_num')
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $ic_num = trim($_POST['ic_num']); // <-- 1. TAMBAH INI
    $phoneNumber = trim($_POST['phoneNumber']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    $needle = '@unikl.edu.my';
    // Semak jika bahagian akhir $email sama dengan $needle
    $email_ends_with_unikl = (substr($email, -strlen($needle)) === $needle); 
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$email_ends_with_unikl) {
        $_SESSION['error_message'] = "Invalid UniKL email format.";
        header("Location: manage_accounts.php");
        exit();
    }
    
    if (!preg_match('/^\d{12}$/', $ic_num)) {
         $_SESSION['error_message'] = "Invalid IC Number format. Must be 12 digits.";
         header("Location: manage_accounts.php");
         exit();
    }

    // 2. Tetapkan status default dan hash kata laluan
    $status = 'active'; // Status untuk pengguna baru adalah 'Active' secara default
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 3. Tentukan table mana untuk simpan data berdasarkan 'role'
    if ($role === 'Technician') {
        $table = 'technician';
        $id_column = 'tech_id';
    } elseif ($role === 'User') {
        $table = 'user';
        $id_column = 'user_id';
    } else {
        // Jika role tidak sah
        $_SESSION['error_message'] = "Invalid role specified.";
        header("Location: manage_accounts.php");
        exit();
    }

    // 4. Sediakan dan laksanakan query INSERT (TERMASUK 'ic_num')
    $sql = "INSERT INTO $table (name, email, ic_num, phoneNum, password, status) VALUES (?, ?, ?, ?, ?, ?)"; 
    
    $stmt = $conn->prepare($sql);

    // Semak jika prepare() berjaya
    if ($stmt) {
        // 'ssssss' bermaksud 6 pembolehubah adalah jenis string
        $stmt->bind_param("ssssss", $username, $email, $ic_num, $phoneNumber, $hashed_password, $status); 
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "$role account created successfully!";
        } else {
            // Tangani ralat jika email atau ic_num sudah wujud
            if ($conn->errno == 1062) { // Kod ralat untuk 'Duplicate entry'
                 
                 // =======================================================
                 // ✨ DIBETULKAN UNTUK PHP 7 (Ganti str_contains) ✨
                 // =======================================================
                 if (strpos($stmt->error, 'email') !== false) {
                    $_SESSION['error_message'] = "Error: An account with this email already exists.";
                 } elseif (strpos($stmt->error, 'ic_num') !== false) {
                    $_SESSION['error_message'] = "Error: An account with this IC Number already exists.";
                 } else {
                    $_SESSION['error_message'] = "Error: Duplicate entry. Check email or IC Number.";
                 }
            } else {
                $_SESSION['error_message'] = "Error creating account: " . $stmt->error;
            }
        }
        $stmt->close();
    } else {
        // Ralat jika prepare() gagal
        $_SESSION['error_message'] = "Error preparing the statement: " . $conn->error;
    }

    $conn->close();

} else {
    // Jika bukan kaedah POST, hantar mesej ralat
    $_SESSION['error_message'] = "Invalid request method.";
}

// 5. Kembali ke halaman manage_accounts.php
header("Location: manage_accounts.php");
exit();
?>