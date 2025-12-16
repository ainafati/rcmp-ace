<?php

define('ENVIRONMENT', 'DEVELOPMENT'); 



if (ENVIRONMENT === 'DEVELOPMENT') {
    
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    
    ini_set('display_errors', 0);
    error_reporting(0);
}


$host = "localhost";
$user = "root";
$pass = ""; 
$db = "inventory";

if (!defined('BASE_URL')) {
    define('BASE_URL', 'https://nexcheck.rcmp.edu.my/'); 
}


if (!defined('TECHNICIAN_GROUP_EMAIL')) {
    define('TECHNICIAN_GROUP_EMAIL', 'it.rcmp@unikl.edu.my');
}


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
