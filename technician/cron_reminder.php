<?php
// cron_reminder.php

$base_dir = __DIR__;

// GUNA LALUAN MUTLAK untuk semua include/require
// Asumsi: cron_reminder.php berada dalam folder technician/
include $base_dir . '/config.php'; 
include $base_dir . '/config_email.php'; 
require $base_dir . '/send_email.php';

// Ini penting untuk skrip yang dijalankan secara automatik
ini_set('display_errors', 0);
error_reporting(E_NONE);
// Fungsi untuk mendapatkan maklumat tempahan yang perlu dihantar peringatan
function getReservationsForReminder($conn) {
    // Mencari tempahan yang statusnya 'Checked Out' dan tarikh pulangan
    // adalah HARI INI atau ESOK
    $stmt = $conn->prepare("
        SELECT 
            ri.id AS reservation_item_id,
            u.email AS user_email,
            u.name AS user_name,
            i.item_name,
            ri.return_date,
            GROUP_CONCAT(a.asset_code SEPARATOR ', ') AS asset_codes
        FROM reservation_items ri
        JOIN reservations r ON ri.reserve_id = r.reserve_id
        JOIN user u ON r.user_id = u.user_id 
        JOIN item i ON ri.item_id = i.item_id
        JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
        JOIN assets a ON ra.asset_id = a.asset_id
        WHERE ri.status = 'Checked Out' 
          AND (ri.return_date = CURDATE() OR ri.return_date = CURDATE() + INTERVAL 1 DAY)
        GROUP BY ri.id
    ");

    if (!$stmt || !$stmt->execute()) {
        error_log("Reminder SQL Error: " . $conn->error);
        return [];
    }

    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result;
}


// --- LOGIK UTAMA SKRIP ---

// 1. Dapatkan senarai tempahan yang perlu dihantar peringatan
$reminders = getReservationsForReminder($conn);

if (empty($reminders)) {
    // Tiada peringatan untuk dihantar
    exit('No reminders to send.');
}

$sent_count = 0;

foreach ($reminders as $item) {
    $current_date = new DateTime();
    $return_date_dt = new DateTime($item['return_date']);
    $diff = $current_date->diff($return_date_dt)->days;
    
    // Tentukan jenis peringatan
    if ($diff == 0 && $current_date->format('Y-m-d') == $return_date_dt->format('Y-m-d')) {
        $subject = '🔴 [PERINGATAN AKHIR] Hari Terakhir Pemulangan Aset: ' . $item['item_name'];
        $body_message = "Sila pulangkan aset-aset ini <strong>pada hari ini juga ({$item['return_date']})</strong> untuk mengelakkan penalti.";
    } elseif ($diff == 1) {
        $subject = '🟡 [PERINGATAN AWAL] Aset Dijangka Pulang Esok: ' . $item['item_name'];
        $body_message = "Aset-aset ini dijangka akan dipulangkan <strong>esok ({$item['return_date']})</strong>. Sila buat persiapan untuk proses pemulangan.";
    } else {
        continue; // Langkau jika logik tarikh tidak sepadan
    }

    // PANGGIL FUNGSI E-MEL (Anda perlu tambah subjek dan mesej baharu ke fungsi ini)
    // Sila lihat Langkah 3 untuk kemas kini fungsi e-mel.
    $email_sent = sendReturnReminderEmail(
        $item['user_email'],
        $item['user_name'],
        $item['item_name'],
        $item['asset_codes'],
        $item['return_date'],
        $body_message, // Mesej peringatan baharu
        $subject,      // Subjek baharu
        SMTP_USER,
        SMTP_PASS
    );

    if ($email_sent) {
        $sent_count++;
    }
}

echo "Reminder script finished. Sent {$sent_count} emails.";

$conn->close();
?>