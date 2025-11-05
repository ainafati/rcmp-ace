<?php
include __DIR__ . '/../config.php';
require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Tetapan Hari: 0 = Hari Ini, 1 = Esok, -1 = Semalam
function get_return_items_due($conn, $days_offset) {
    // Tarikh sasaran: CURDATE() untuk Hari Ini, DATE_ADD(CURDATE(), INTERVAL 1 DAY) untuk Esok, dll.
    $target_date_sql = $days_offset == 0 ? "CURDATE()" : "DATE_ADD(CURDATE(), INTERVAL $days_offset DAY)";
    
    // Status: Checked Out bermakna masih di tangan pengguna
    $sql = "SELECT
                ri.id, ri.reserve_date, ri.return_date, ri.quantity,
                u.name AS user_name, u.email AS user_email, u.phoneNum AS user_phone,
                i.item_name
            FROM reservation_items ri
            JOIN reservations r ON ri.reserve_id = r.reserve_id
            JOIN user u ON r.user_id = u.user_id
            JOIN item i ON ri.item_id = i.item_id
            WHERE ri.status = 'Checked Out' AND DATE(ri.return_date) = $target_date_sql";
            
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function send_email_notification($recipient_email, $recipient_name, $items, $is_today) {
    // ******* GANTIKAN DENGAN BUTIRAN SMTP ANDA *******
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Ganti dengan SMTP Host anda
        $mail->SMTPAuth = true;
        // MENGGUNAKAN PARAMETER YANG DITERIMA
        $mail->Username   = $smtp_user;    
        $mail->Password   = $smtp_pass;    
        $mail->SMTPSecure = 'ssl';         
        $mail->Port       = 465;          
        
        $mail->setFrom($smtp_user, 'UniKL Inventory System');
        $mail->addAddress($recipient_email, $recipient_name);
        
        $date_str = $is_today ? 'HARI INI' : 'Esok';
        $subject = "[Peringatan] Pemulangan Item Inventori ($date_str)";

        $item_list_html = '<ul>';
        foreach ($items as $item) {
            $item_list_html .= "<li>**" . htmlspecialchars($item['item_name']) . "** (Kuantiti: {$item['quantity']}) - Tarikh Pulang: " . date('d M Y', strtotime($item['return_date'])) . "</li>";
        }
        $item_list_html .= '</ul>';
        
        $body = "Salam Sejahtera **" . htmlspecialchars($recipient_name) . "**,
                <p>Ini adalah peringatan automatik. Anda dikehendaki memulangkan item berikut **$date_str**:</p>
                $item_list_html
                <p>Sila hubungi Jabatan Teknikal jika anda mempunyai sebarang pertanyaan.</p>";

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = nl2br($body); // Guna nl2br untuk format perenggan
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Gagal menghantar e-mel kepada $recipient_email. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// ----------------------------------------------------
// 1. Dapatkan item yang perlu dipulangkan HARI INI (days_offset = 0)
// ----------------------------------------------------
$today_items = get_return_items_due($conn, 0);

if (!empty($today_items)) {
    // Kumpulkan item mengikut pengguna
    $users_due_today = [];
    foreach ($today_items as $item) {
        $users_due_today[$item['user_email']]['name'] = $item['user_name'];
        $users_due_today[$item['user_email']]['items'][] = $item;
    }

    foreach ($users_due_today as $email => $user_data) {
        send_email_notification($email, $user_data['name'], $user_data['items'], true);
    }
}
$tomorrow_items = get_return_items_due($conn, 1);

if (!empty($tomorrow_items)) {
    // Kumpulkan item mengikut pengguna
    $users_due_tomorrow = [];
    foreach ($tomorrow_items as $item) {
        $users_due_tomorrow[$item['user_email']]['name'] = $item['user_name'];
        $users_due_tomorrow[$item['user_email']]['items'][] = $item;
    }

    foreach ($users_due_tomorrow as $email => $user_data) {
        send_email_notification($email, $user_data['name'], $user_data['items'], false);
    }
}

// Tulis log untuk pengesahan cron job
echo "Skrip Reminder Selesai. Ditemui " . count($today_items) . " item due hari ini dan " . count($tomorrow_items) . " item due esok.\n";

$conn->close();
?>