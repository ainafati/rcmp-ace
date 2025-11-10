<?php
session_start();
include 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error_message'] = "Access denied.";
    header("Location: manage_accounts.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = $_POST['user_id'];
    $role = $_POST['role'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($newPassword !== $confirmPassword) {
        $_SESSION['error_message'] = "Passwords do not match.";
        header("Location: manage_accounts.php");
        exit();
    }
    
    // ✨ BLOK BARU: Pengesahan kata laluan yang lebih ketat ✨
    $uppercase = preg_match('@[A-Z]@', $newPassword);
    $lowercase = preg_match('@[a-z]@', $newPassword);
    $number    = preg_match('@[0-9]@', $newPassword);
    $specialChars = preg_match('@[\W_]@', $newPassword);

    if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($newPassword) < 8) {
        $_SESSION['error_message'] = 'Password must be at least 8 characters long and include an uppercase letter, a number, and a special character.';
        header("Location: manage_accounts.php");
        exit();
    }
    
    // Hash kata laluan baru
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Tentukan jadual dan lajur ID
    if ($role === 'User') {
        $tableName = 'user';
        $idColumn = 'user_id';
    } elseif ($role === 'Technician') {
        $tableName = 'technician';
        $idColumn = 'tech_id';
    } else {
        $_SESSION['error_message'] = "Invalid role specified.";
        header("Location: manage_accounts.php");
        exit();
    }

    $sql = "UPDATE $tableName SET password = ? WHERE $idColumn = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $hashedPassword, $userId);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Password has been successfully reset.";
    } else {
        $_SESSION['error_message'] = "Failed to reset password.";
    }
    
    $stmt->close();
    $conn->close();

    header("Location: manage_accounts.php");
    exit();
}
?>