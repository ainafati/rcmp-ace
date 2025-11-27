<?php
session_start();
include '../config.php';


function validatePassword($password) {
    
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters long.";
    }

    
    if (!preg_match("/[0-9]/", $password)) {
        return "Password must include at least one number (0-9).";
    }

    
    if (!preg_match("/[A-Z]/", $password)) {
        return "Password must include at least one uppercase letter (A-Z).";
    }

    
    if (!preg_match("/[a-z]/", $password)) {
        return "Password must include at least one lowercase letter (a-z).";
    }

    
    if (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $password)) {
        return "Password must include at least one special character (!@#\$%..).";
    }

    return true; 
}



if (!isset($_SESSION['person_id'])) {
    $_SESSION['error'] = "Sila log masuk semula.";
    header("Location: ../login.php");
    exit();
}

$tech_id = (int) $_SESSION['person_id'];

if (!isset($conn) || $conn->connect_error) {
    $_SESSION['error'] = "Database Connection Error.";
    header("Location: profile_tech.php");
    exit();
}


$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$phoneNum = isset($_POST['phoneNum']) ? trim($_POST['phoneNum']) : '';
$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
$confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';



if (empty($name) || empty($phoneNum)) {
    $_SESSION['error'] = "Full Name and Phone Number cannot be empty.";
    $_SESSION['keep_edit_mode'] = true; 
    header("Location: profile_tech.php");
    exit();
}



$sql_query_parts = ["name = ?", "phoneNum = ?"];
$types = "ss";
$params = [$name, $phoneNum];



if (!empty($new_password)) {
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match.";
        $_SESSION['keep_edit_mode'] = true; 
        header("Location: profile_tech.php");
        exit();
    }
    
    
    $validation_result = validatePassword($new_password);
    
    if ($validation_result !== true) {
        $_SESSION['error'] = $validation_result;
        $_SESSION['keep_edit_mode'] = true; 
        header("Location: profile_tech.php");
        exit();
    }
    
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $sql_query_parts[] = "password = ?";
    $types .= "s";
    $params[] = $hashed_password;
}





$sql = "UPDATE person SET " . implode(", ", $sql_query_parts) . " WHERE person_id = ?";
$types .= "i";
$params[] = $tech_id;

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $_SESSION['error'] = "SQL Prepare Error: " . htmlspecialchars($conn->error);
    $_SESSION['keep_edit_mode'] = true; 
    $conn->close();
    header("Location: profile_tech.php");
    exit();
}


if (count($params) > 0 && !$stmt->bind_param($types, ...$params)) { 
    $_SESSION['error'] = "SQL Bind Param Error: " . htmlspecialchars($stmt->error);
    $_SESSION['keep_edit_mode'] = true; 
    $stmt->close();
    $conn->close();
    header("Location: profile_tech.php");
    exit();
}



if ($stmt->execute()) {
    if ($stmt->affected_rows > 0 || count($sql_query_parts) == 0) { 
        $_SESSION['message'] = "Your profile has been updated successfully!";
    } else {
        $_SESSION['message'] = "Profile data is already up to date (or no changes were made).";
    }
} else {
    $_SESSION['error'] = "Failed to update profile. Execution Error: " . htmlspecialchars($stmt->error);
    $_SESSION['keep_edit_mode'] = true; 
}

$stmt->close();
$conn->close();

header("Location: profile_tech.php");
exit();
?>