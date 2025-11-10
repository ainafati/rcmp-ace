<?php
session_start();
include '../config.php';


if (!isset($_SESSION['tech_id'])) {
    $_SESSION['error'] = "Sila log masuk semula.";
    header("Location: ../login.php");
    exit();
}
$tech_id = (int) $_SESSION['tech_id'];


$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phoneNum = isset($_POST['phoneNum']) ? trim($_POST['phoneNum']) : '';
$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
$confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';


if (empty($name) || empty($email) || empty($phoneNum)) {
    $_SESSION['error'] = "Name, email, and phone number cannot be empty.";
    header("Location: profile_tech.php");
    exit();
}


$sql = "UPDATE technician SET name = ?, email = ?, phoneNum = ? WHERE tech_id = ?";
$types = "sssi"; 
$params = [$name, $email, $phoneNum, $tech_id];


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
    
    $sql = "UPDATE technician SET name = ?, email = ?, phoneNum = ?, password = ? WHERE tech_id = ?";
    $types = "ssssi";
    $params = [$name, $email, $phoneNum, $hashed_password, $tech_id];
}


$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $_SESSION['error'] = "SQL Error: " . $conn->error;
    header("Location: profile_tech.php");
    exit();
}


$bind_params = [];
$bind_params[] = $types;
foreach ($params as $key => $value) {
    $bind_params[] = &$params[$key];
}
call_user_func_array([$stmt, 'bind_param'], $bind_params);

if ($stmt->execute()) {
    $_SESSION['message'] = "Your profile has been updated successfully!";
} else {
    $_SESSION['error'] = "Failed to update profile. Please try again.";
}

$stmt->close();
$conn->close();

header("Location: profile_tech.php");
exit();
?>