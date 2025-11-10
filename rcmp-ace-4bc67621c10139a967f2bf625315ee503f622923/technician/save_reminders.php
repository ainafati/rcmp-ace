<?php
// =================================================================
// 🚨 BAHAGIAN 1: KONFIGURASI DAN LALUAN MUTLAK (WAJIB UNTUK AUTOMASI)
// =================================================================

// Tentukan Laluan Root Mutlak Projek
// Anda MESTI menggunakan forward slashes (/) untuk PHP
// Berdasarkan struktur folder anda: 'C:/xampp/htdocs/UniKL ACE/'
define('ROOT_DIR', 'C:/xampp/htdocs/UniKL ACE/'); 

// 1. Laluan ke config.php: Ia berada di dalam subfolder 'technician'
require ROOT_DIR . 'technician/config.php';

// 2. Laluan ke PHPMailer-master: Ia berada di folder root UniKL ACE/
require ROOT_DIR . 'PHPMailer-master/src/Exception.php';
require ROOT_DIR . 'PHPMailer-master/src/PHPMailer.php';
require ROOT_DIR . 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


// =================================================================
// 🔄 BAHAGIAN 2: FUNGSI SQL (Item Due & Item Overdue)
// =================================================================

// Fungsi 1: Mendapatkan item yang akan dipulangkan hari ini atau esok
function get_return_items_due($conn, $days_offset) {
    // Target date SQL: CURDATE() for Today, DATE_ADD(CURDATE(), INTERVAL 1 DAY) for Tomorrow, etc.
    $target_date_sql = $days_offset == 0 ? "CURDATE()" : "DATE_ADD(CURDATE(), INTERVAL $days_offset DAY)";
    
    // Status: Checked Out means the user still has the item
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

// Fungsi 2 (BARU): Mendapatkan item yang TERLEWAT (OVERDUE)
function get_overdue_items($conn) {
    // Cari semua item yang masih 'Checked Out' tetapi tarikh pulangan SUDAH BERLALU
    $sql = "SELECT
                ri.id, ri.reserve_date, ri.return_date, ri.quantity,
                u.name AS user_name, u.email AS user_email, u.phoneNum AS user_phone,
                i.item_name
            FROM reservation_items ri
            JOIN reservations r ON ri.reserve_id = r.reserve_id
            JOIN user u ON r.user_id = u.user_id
            JOIN item i ON ri.item_id = i.item_id
            WHERE ri.status = 'Checked Out' AND DATE(ri.return_date) < CURDATE()";
            
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}


// =================================================================
// 📧 BAHAGIAN 3: FUNGSI EMAIL (Termasuk Overdue)
// =================================================================
function send_email_notification($recipient_email, $recipient_name, $items, $is_today, $is_overdue = false) {
    $mail = new PHPMailer(true);
    try {
        // SMTP Configuration (Guna TLS Port 587 untuk kestabilan Task Scheduler)
        $mail->isSMTP();
        $mail->Host      = 'smtp.gmail.com';
        $mail->SMTPAuth  = true;
        
        // **GANTIKAN DENGAN BUTIRAN SMTP ANDA**
        $mail->Username  = 'ainafati12@gmail.com'; 
        $mail->Password  = 'qyzjufqzxndihtae'; 
        
        $mail->SMTPSecure = 'tls';      // ✅ TLS (Port 587)
        $mail->Port       = 587;        // ✅ Port 587
        
        // Sender/Recipient Setup
        $mail->setFrom('ainafati12@gmail.com', 'UniKL Inventory System');
        $mail->addAddress($recipient_email, $recipient_name);
        
        // Tentukan Subjek
        if ($is_overdue) {
            $date_str = 'OVERDUE';
            $subject = "URGENT: Inventory Item Return (OVERDUE)";
        } else {
            $date_str = $is_today ? 'TODAY' : 'TOMORROW';
            $subject = "Inventory Item Return (Due " . strtoupper($date_str) . ")";
        }

        // ... (Kod HTML body e-mel anda) ...
        $item_list_html = '<table style="width: 100%; border-collapse: collapse; margin-top: 15px; font-family: Arial, sans-serif;">';
        $item_list_html .= '<thead><tr style="background-color: #f2f2f2;"><th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Item Name</th><th style="border: 1px solid #ddd; padding: 10px; text-align: center;">Quantity</th><th style="border: 1px solid #ddd; padding: 10px; text-align: center;">Return Date</th></tr></thead>';
        $item_list_html .= '<tbody>';
        
        foreach ($items as $item) {
            $item_list_html .= '<tr>';
            $item_list_html .= '<td style="border: 1px solid #ddd; padding: 10px;">' . htmlspecialchars($item['item_name']) . '</td>';
            $item_list_html .= '<td style="border: 1px solid #ddd; padding: 10px; text-align: center;">' . htmlspecialchars($item['quantity']) . ' unit(s)</td>';
            $item_list_html .= '<td style="border: 1px solid #ddd; padding: 10px; text-align: center;">' . date('d M Y', strtotime($item['return_date'])) . '</td>';
            $item_list_html .= '</tr>';
        }
        $item_list_html .= '</tbody></table>';
        
        // Body (disimpan ringkas untuk fokus)
        $body = "
            <p style='font-family: Arial, sans-serif;'>Dear <strong>" . htmlspecialchars($recipient_name) . "</strong>,</p>
            <p style='font-family: Arial, sans-serif;'>This is an official and automated notice from the UniKL Inventory Management System regarding items currently in your possession. We wish to inform you that the item(s) listed below are <strong>due for return on $date_str</strong>.</p>
            <h3 style='font-family: Arial, sans-serif; color: #004d99;'>Item Return Details:</h3>
            " . $item_list_html . "
            <p style='font-family: Arial, sans-serif; margin-top: 20px;'>We kindly request your cooperation in ensuring these items are returned to the UniKL Technical Department <strong>promptly</strong>.</p>
            <p style='font-family: Arial, sans-serif;'>Sincerely,</p>
            <p style='font-family: Arial, sans-serif;'><strong>The UniKL Inventory Management Department</strong></p>
        ";


        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to send email to $recipient_email. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}


// =================================================================
// ⚙️ BAHAGIAN 4: LOGIK UTAMA (Memanggil Fungsi)
// =================================================================

// 1. Dapatkan item yang Due HARI INI (days_offset = 0)
$today_items = get_return_items_due($conn, 0);
if (!empty($today_items)) {
    $users_due_today = [];
    foreach ($today_items as $item) {
        $users_due_today[$item['user_email']]['name'] = $item['user_name'];
        $users_due_today[$item['user_email']]['items'][] = $item;
    }
    foreach ($users_due_today as $email => $user_data) {
        send_email_notification($email, $user_data['name'], $user_data['items'], true, false); // is_today=true, is_overdue=false
    }
}

// 2. Dapatkan item yang Due ESOK (days_offset = 1)
$tomorrow_items = get_return_items_due($conn, 1);
if (!empty($tomorrow_items)) {
    $users_due_tomorrow = [];
    foreach ($tomorrow_items as $item) {
        $users_due_tomorrow[$item['user_email']]['name'] = $item['user_name'];
        $users_due_tomorrow[$item['user_email']]['items'][] = $item;
    }
    foreach ($users_due_tomorrow as $email => $user_data) {
        send_email_notification($email, $user_data['name'], $user_data['items'], false, false); // is_today=false, is_overdue=false
    }
}

// 3. Dapatkan item yang TERLEWAT (OVERDUE)
$overdue_items = get_overdue_items($conn);
if (!empty($overdue_items)) {
    $users_overdue = [];
    foreach ($overdue_items as $item) {
        $users_overdue[$item['user_email']]['name'] = $item['user_name'];
        $users_overdue[$item['user_email']]['items'][] = $item;
    }
    foreach ($users_overdue as $email => $user_data) {
        send_email_notification($email, $user_data['name'], $user_data['items'], false, true); // is_today=false, is_overdue=true
    }
}


// Final confirmation message
echo "Script Reminder Complete. Found " . count($today_items) . " item(s) due today, " . count($tomorrow_items) . " item(s) due tomorrow, and " . count($overdue_items) . " item(s) overdue.\n";

$conn->close();
?>