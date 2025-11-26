<?php


$host = "localhost";
$user = "nexcheck_dbuser";
$pass = "nexcheck_dbuser"; 
$db = "inventory";


$conn = new mysqli($host, $user, $pass, $db);


if ($conn->connect_error) {
    
    
    
    
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    
    
    $error_response = [
        'success' => false, 
        'message' => 'Database connection failed: ' . $conn->connect_error
    ];
    echo json_encode($error_response);
    
    
    exit(); 
}

