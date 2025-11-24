<?php
session_start();
include '../config.php';

// 1. KONSISTENKAN PENGGUNAAN SESSION ID
if (!isset($_SESSION['person_id'])) {
    $_SESSION['error'] = "Sila log masuk semula.";
    header("Location: ../login.php");
    exit();
}
// Tech ID kini adalah Person ID
$tech_id = (int) $_SESSION['person_id']; 

// Data yang hanya boleh diubah (PhoneNum)
$phoneNum = isset($_POST['phoneNum']) ? trim($_POST['phoneNum']) : '';
$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
$confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

// Semakan Asas
if (empty($phoneNum)) {
    $_SESSION['error'] = "Phone number cannot be empty.";
    header("Location: profile_tech.php");
    exit();
}

// 2. LOGIK KEMAS KINI ASAS (Hanya phoneNum)
// Menggunakan jadual 'person' dan kolum 'person_id'
$sql = "UPDATE person SET phoneNum = ? WHERE person_id = ?";
$types = "si"; // s (string) untuk phoneNum, i (integer) untuk person_id
$params = [$phoneNum, $tech_id];


// 3. LOGIK KEMAS KINI DENGAN PASSWORD
if (!empty($new_password)) {
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match.";
        header("Location: profile_tech.php");
        exit();
    }
    
    if (strlen($new_password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        header("Location: profile_tech.php");
        exit();
    }

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Guna SQL untuk kemaskini phoneNum DAN password
    $sql = "UPDATE person SET phoneNum = ?, password = ? WHERE person_id = ?";
    $types = "ssi"; // s (phoneNum), s (password), i (person_id)
    $params = [$phoneNum, $hashed_password, $tech_id];
}


// 4. PELAKSANAAN SQL
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $_SESSION['error'] = "SQL Prepare Error: " . htmlspecialchars($conn->error);
    header("Location: profile_tech.php");
    exit();
}

// Bind parameter secara dinamik
$bind_params = [];
$bind_params[] = $types;
foreach ($params as $key => $value) {
    $bind_params[] = &$params[$key];
}
// Pastikan call_user_func_array berjaya (penting untuk bind_param)
if (!call_user_func_array([$stmt, 'bind_param'], $bind_params)) {
    $_SESSION['error'] = "SQL Bind Param Error: " . htmlspecialchars($stmt->error);
    $stmt->close();
    $conn->close();
    header("Location: profile_tech.php");
    exit();
}

if ($stmt->execute()) {
    // Semak jika ada baris yang terjejas/diubah.
    if ($stmt->affected_rows > 0) {
        $_SESSION['message'] = "Your profile has been updated successfully!";
    } else {
        // Ini berlaku jika data yang dihantar sama dengan data semasa (tiada perubahan)
        $_SESSION['message'] = "Profile data is already up to date (or no changes were made)."; 
    }
} else {
    $_SESSION['error'] = "Failed to update profile. Execution Error: " . htmlspecialchars($stmt->error);
}

$stmt->close();
$conn->close();

header("Location: profile_tech.php");
exit();
?>