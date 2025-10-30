<?php
session_start();
include 'config.php';

// Pastikan pengguna sudah log masuk
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Pastikan borang dihantar
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Ambil data dari borang
$name = trim($_POST['name']); // Nama akan dihantar kerana kita dah betulkan HTML
$email = trim($_POST['email']);
$phoneNum = trim($_POST['phoneNum']);
$new_password = $_POST['new_password']; // Jangan trim password
$confirm_password = $_POST['confirm_password'];

// Pengesahan asas
if (empty($name) || empty($email) || empty($phoneNum)) {
    $_SESSION['error'] = "Name, email, and phone number cannot be empty.";
    header("Location: profile.php");
    exit();
}

// Semak jika e-mel baharu sudah digunakan oleh pengguna lain
$stmt_check = $conn->prepare("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
$stmt_check->bind_param("si", $email, $user_id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows > 0) {
    $_SESSION['error'] = "This email is already in use by another account.";
    header("Location: profile.php");
    exit();
}
$stmt_check->close();

// Logik untuk kemas kini kata laluan (jika diisi)
if (!empty($new_password)) {
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match.";
        header("Location: profile.php");
        exit();
    }
    
    if (strlen($new_password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        header("Location: profile.php");
        exit();
    }

    // KOD JIKA PASSWORD DIISI
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    // Kueri dengan klausa WHERE yang penting!
    $sql = "UPDATE user SET email = ?, phoneNum = ?, password = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    // Jenis 'sssi' -> string, string, string, integer
    $stmt->bind_param("sssi", $email, $phoneNum, $hashed_password, $user_id);

} else {
    // KOD JIKA PASSWORD KOSONG
    // Kueri dengan klausa WHERE yang penting!
    $sql = "UPDATE user SET email = ?, phoneNum = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    // Jenis 'ssi' -> string, string, integer
    $stmt->bind_param("ssi", $email, $phoneNum, $user_id);
}

// Laksanakan query
if ($stmt->execute()) {
    $_SESSION['message'] = "Your profile has been updated successfully!";
} else {
    $_SESSION['error'] = "Failed to update profile. Please try again.";
}

$stmt->close();
$conn->close();

header("Location: profile.php");
exit();
?>