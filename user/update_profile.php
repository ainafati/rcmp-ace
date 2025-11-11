<?php
session_start();
include 'config.php'; 


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];


$name = trim($_POST['name']); 
$email = trim($_POST['email']);
$phoneNum = trim($_POST['phoneNum']);
$new_password = $_POST['new_password']; 
$confirm_password = $_POST['confirm_password'];


if (empty($name) || empty($email) || empty($phoneNum)) {
    $_SESSION['error'] = "Name, email, and phone number cannot be empty.";
    header("Location: profile.php");
    exit();
}


$stmt_check = $conn->prepare("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
$stmt_check->bind_param("si", $email, $user_id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows > 0) {
    $_SESSION['error'] = "This email is already in use by another account.";
    $stmt_check->close();
    header("Location: profile.php");
    exit();
}
$stmt_check->close();


if (!empty($new_password)) {
    
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match.";
        header("Location: profile.php");
        exit();
    }
    
    
    $uppercase = preg_match('@[A-Z]@', $new_password);
    $lowercase = preg_match('@[a-z]@', $new_password);
    $number    = preg_match('@[0-9]@', $new_password);
    
    $specialChars = preg_match('@[\W_]@', $new_password); 

    if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($new_password) < 8) {
        
        $_SESSION['error'] = 'New password does not meet the requirements. Please ensure it has <strong>8+ characters</strong>, <strong>uppercase</strong>, <strong>lowercase</strong>, <strong>number</strong>, and a <strong>special character</strong>.';
        header("Location: profile.php");
        exit();
    }
    
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    
    $sql = "UPDATE user SET name = ?, email = ?, phoneNum = ?, password = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param("ssssi", $name, $email, $phoneNum, $hashed_password, $user_id);

} else {
    
    
    
    $sql = "UPDATE user SET name = ?, email = ?, phoneNum = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param("sssi", $name, $email, $phoneNum, $user_id);
}


if ($stmt->execute()) {
    $_SESSION['message'] = "Your profile has been updated successfully!";
} else {
    
    $_SESSION['error'] = "Failed to update profile. Please try again. Error: " . $stmt->error;
}

$stmt->close();
$conn->close();

header("Location: profile.php");
exit();
?>