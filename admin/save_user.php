<?php
session_start();
include 'config.php';


if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $ic_num = trim($_POST['ic_num']); 
    $phoneNumber = trim($_POST['phoneNumber']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    $needle = '@unikl.edu.my';
    
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

    
    $status = 'active'; 
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    
    if ($role === 'Technician') {
        $table = 'technician';
        $id_column = 'tech_id';
    } elseif ($role === 'User') {
        $table = 'user';
        $id_column = 'user_id';
    } else {
        
        $_SESSION['error_message'] = "Invalid role specified.";
        header("Location: manage_accounts.php");
        exit();
    }

    
    $sql = "INSERT INTO $table (name, email, ic_num, phoneNum, password, status) VALUES (?, ?, ?, ?, ?, ?)"; 
    
    $stmt = $conn->prepare($sql);

    
    if ($stmt) {
        
        $stmt->bind_param("ssssss", $username, $email, $ic_num, $phoneNumber, $hashed_password, $status); 
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "$role account created successfully!";
        } else {
            
            if ($conn->errno == 1062) { 
                 
                 
                 
                 
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
        
        $_SESSION['error_message'] = "Error preparing the statement: " . $conn->error;
    }

    $conn->close();

} else {
    
    $_SESSION['error_message'] = "Invalid request method.";
}


header("Location: manage_accounts.php");
exit();
?>