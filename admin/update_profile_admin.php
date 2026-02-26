<?php
session_start();
include '../config.php';

// Gunakan person_id (ikut file profile_admin.php kau)
if (!isset($_SESSION['person_id'])) {
    $_SESSION['error'] = "Sila log masuk semula.";
    header("Location: ../login.php"); // Pastikan path ke login betul
    exit();
}

$person_id = (int) $_SESSION['person_id'];

// Ambil data dari form
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$phoneNum = isset($_POST['phoneNum']) ? trim($_POST['phoneNum']) : '';
$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
$confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

// Email tak ada dlm input form kau tadi, tapi kalau nak update juga kena ada dlm form
// Kalau tak ada dlm form, kita jangan update email.

if (empty($name) || empty($phoneNum)) {
    $_SESSION['error'] = "Name and phone number cannot be empty.";
    header("Location: profile_admin.php");
    exit();
}

// Check kalau nak tukar password
if (!empty($new_password)) {
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match.";
        header("Location: profile_admin.php");
        exit();
    }
    
    if (strlen($new_password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        header("Location: profile_admin.php");
        exit();
    }

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // UPDATE table person (Ikut nama table asal kau)
    $stmt = $conn->prepare("UPDATE person SET name = ?, phoneNum = ?, password = ? WHERE person_id = ?");
    $stmt->bind_param("sssi", $name, $phoneNum, $hashed_password, $person_id);
} else {
    // Update tanpa tukar password
    $stmt = $conn->prepare("UPDATE person SET name = ?, phoneNum = ? WHERE person_id = ?");
    $stmt->bind_param("ssi", $name, $phoneNum, $person_id);
}

if ($stmt->execute()) {
    $_SESSION['message'] = "Your profile has been updated successfully!";
} else {
    $_SESSION['error'] = "Failed to update profile: " . $conn->error;
}

$stmt->close();
$conn->close();

header("Location: profile_admin.php");
exit();
?>