<?php
// File: /includes/logger.php
function log_activity($conn, $user_type, $user_id, $action, $details) {
    
    $ip_address = 'UNKNOWN';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip_address = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
    }

    $sql = "INSERT INTO activity_logs (user_type, user_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
// BARU: 'sssss' -> string, string (s), string, string, string
$stmt->bind_param("sssss", $user_type, $user_id, $action, $details, $ip_address);        $stmt->execute();
        $stmt->close();
    } else {
        error_log("Failed to prepare log statement: " . $conn->error);
    }
}
?>