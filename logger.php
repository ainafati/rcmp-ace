<?php

/**
 * Fungsi Logger yang diselaraskan dengan table activity_logs
 */
function log_activity($conn, $user_type, $person_id, $action, $details) {
    
    // 1. Tangkap IP Address yang lebih tepat
    $ip_address = 'UNKNOWN';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip_address = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Kadang-kadang X-Forwarded-For bagi list IP, kita ambil yang pertama
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip_address = trim($ips[0]);
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
    }

    // 2. SQL Query (Ikut nama column dalam gambar: person_id)
    $sql = "INSERT INTO activity_logs (user_type, person_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        /* PENTING: 
           - user_type = string (s)
           - person_id = integer (i) -> Ikut gambar table kau
           - action = string (s)
           - details = string (s)
           - ip_address = string (s)
        */
        $stmt->bind_param("sisss", $user_type, $person_id, $action, $details, $ip_address);
        
        if (!$stmt->execute()) {
            error_log("Gagal execute log: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare log statement: " . $conn->error);
    }
}
?>