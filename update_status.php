<?php
session_start();
include 'config.php';


if (isset($_POST['reserve_id']) && isset($_POST['status'])) {
    $reserve_id = (int)$_POST['reserve_id'];
    $status = (int)$_POST['status'];

    
    $sql = "UPDATE reservations SET handled_status = ? WHERE reserve_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $status, $reserve_id);
        if ($stmt->execute()) {
            echo 'success'; 
        } else {
            echo 'failure'; 
        }
        $stmt->close();
    } else {
        echo 'failure'; 
    }
} else {
    echo 'failure'; 
}
?>
