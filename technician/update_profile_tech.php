<?php
session_start();
include '../config.php';


if (!isset($_SESSION['person_id'])) {
    $_SESSION['error'] = "Sila log masuk semula.";
    header("Location: ../login.php");
    exit();
}

$tech_id = (int) $_SESSION['person_id']; 


$phoneNum = isset($_POST['phoneNum']) ? trim($_POST['phoneNum']) : '';
$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
$confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';


if (empty($phoneNum)) {
    $_SESSION['error'] = "Phone number cannot be empty.";
    header("Location: profile_tech.php");
    exit();
}



$sql = "UPDATE person SET phoneNum = ? WHERE person_id = ?";
$types = "si"; 
$params = [$phoneNum, $tech_id];



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
    
    
    $sql = "UPDATE person SET phoneNum = ?, password = ? WHERE person_id = ?";
    $types = "ssi"; 
    $params = [$phoneNum, $hashed_password, $tech_id];
}



$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $_SESSION['error'] = "SQL Prepare Error: " . htmlspecialchars($conn->error);
    header("Location: profile_tech.php");
    exit();
}


$bind_params = [];
$bind_params[] = $types;
foreach ($params as $key => $value) {
    $bind_params[] = &$params[$key];
}

if (!call_user_func_array([$stmt, 'bind_param'], $bind_params)) {
    $_SESSION['error'] = "SQL Bind Param Error: " . htmlspecialchars($stmt->error);
    $stmt->close();
    $conn->close();
    header("Location: profile_tech.php");
    exit();
}

if ($stmt->execute()) {
    
    if ($stmt->affected_rows > 0) {
        $_SESSION['message'] = "Your profile has been updated successfully!";
    } else {
        
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