<?php


error_reporting(E_ALL); 
ini_set('display_errors', '0'); 
ini_set('display_startup_errors', '0');

session_start();


header('Content-Type: application/json');


$response = ['success' => false, 'message' => 'Invalid Request or Not Logged In.'];



include '../config.php'; 


if (!$conn) {
    $response['message'] = "Database connection failed.";
    echo json_encode($response);
    exit();
}


if (!isset($_SESSION['person_id'])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $user_id = (int) $_SESSION['person_id'];

    if ($action === 'mark_read') {
        
        
        $id = isset($_POST['id']) ? $_POST['id'] : 'all';
        
        
        if ($id === 'all') {
            
            $sql = "UPDATE notifications SET is_read = 1 WHERE person_id = ? AND is_read = 0";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    
                    $affected_rows = $stmt->affected_rows;
                    $response = [
                        'success' => true, 
                        'message' => 'All unread notifications marked as read. ' . $affected_rows . ' notifications updated.'
                    ];
                } else {
                    $response['message'] = "Database execution error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $response['message'] = "Prepare statement failed: " . $conn->error;
            }
        } 
        
        
        else {
            
            $notif_id = (int) $id;

            
            $sql_single = "UPDATE notifications SET is_read = 1 WHERE id = ? AND person_id = ?";
            
            $stmt_single = $conn->prepare($sql_single);
            
            if ($stmt_single) {
                $stmt_single->bind_param("ii", $notif_id, $user_id);
                
                if ($stmt_single->execute()) {
                    $response = ['success' => true, 'message' => 'Notification ID ' . $notif_id . ' marked as read.'];
                } else {
                    $response['message'] = "Database execution error: " . $stmt_single->error;
                }
                $stmt_single->close();
            } else {
                $response['message'] = "Prepare statement failed for single ID: " . $conn->error;
            }
        }
    } else {
        $response['message'] = 'Invalid action specified.';
    }
}

// Contoh logic dalam update_notification.php
if ($_POST['action'] == 'dismiss_void') {
    $id = $_POST['id'];
    // Update column is_acknowledged atau is_read dalam database
    $stmt = $conn->prepare("UPDATE reservation_items SET is_acknowledged = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
echo json_encode($response);
exit();