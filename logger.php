<?php
// File: /includes/logger.php

/**
 * Merekodkan aktiviti ke dalam database.
 *
 * @param mysqli $conn Objek sambungan database.
 * @param string $user_type Jenis pengguna ('admin', 'user', 'tech', 'system').
 * @param int|null $user_id ID pengguna (atau null jika 'system').
 * @param string $action Kod ringkas tindakan (cth: 'LOGIN', 'CREATE_RESERVATION').
 * @param string $details Penerangan penuh tentang tindakan.
 */
function log_activity($conn, $user_type, $user_id, $action, $details) {
    
    // Dapatkan IP address pengguna
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
        // 'sisss' -> string, integer, string, string, string
        $stmt->bind_param("sisss", $user_type, $user_id, $action, $details, $ip_address);
        $stmt->execute();
        $stmt->close();
    } else {
        // Jika gagal, sekurang-kurangnya log ke error log server
        error_log("Failed to prepare log statement: " . $conn->error);
    }
}
?>