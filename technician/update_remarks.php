<?php
session_start();
include 'config.php';


if (!isset($_SESSION['tech_id'])) {
    echo 'Not logged in';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $reserve_id = isset($_POST['reserve_id']) ? (int)$_POST['reserve_id'] : 0;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

    
    if ($reserve_id > 0 && !empty($remarks)) {
        
        $stmt = $conn->prepare("UPDATE reservations SET remarks = ? WHERE reserve_id = ?");
        $stmt->bind_param("si", $remarks, $reserve_id);

        if ($stmt->execute()) {
            echo 'success';  
        } else {
            echo 'Database error: ' . $stmt->error;  
        }

        $stmt->close();
    } else {
        echo 'Invalid data received';
    }
}
?>
