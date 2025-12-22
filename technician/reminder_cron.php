<?php
/**
 * UniKL Inventory Management System - Automated Reminder Script
 * This script handles:
 * 1. Collection (Pickup) reminders for users (Today)
 * 2. Return reminders for users (Today & Tomorrow)
 * 3. Overdue reminders for users
 * 4. Preparation reminders for technicians
 */

// 1. CONFIGURATION & PATHS
define('ROOT_DIR', '../'); 

require ROOT_DIR . 'config.php';
require ROOT_DIR . 'config_email.php';

// Load PHPMailer classes
require ROOT_DIR . 'PHPMailer-master/src/Exception.php';
require ROOT_DIR . 'PHPMailer-master/src/PHPMailer.php';
require ROOT_DIR . 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Check database connection
if ($conn->connect_error) {
    error_log("CRON JOB FAILED: Database connection failed: " . $conn->connect_error);
    exit("CRON JOB FAILED: Database connection error.\n");
}

// Cuma jalan kalau ada key atau jalan melalui server (CLI)
if (php_sapi_name() !== 'cli' && (!isset($_GET['key']) || $_GET['key'] !== 'mysecret123')) {
    die("Restricted Access!");
}
// ---------------------------------------------------------
// 2. HELPER FUNCTIONS (Design & Mailer)
// ---------------------------------------------------------

/**
 * Professional HTML Email Wrapper
 */
function email_template($title, $content, $footer_text = "UniKL Inventory Management System") {
    return "
    <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
        <div style='background-color: #004d99; padding: 20px; text-align: center; color: white;'>
            <h2 style='margin: 0; font-size: 20px;'>$title</h2>
        </div>
        <div style='padding: 30px; background-color: #ffffff;'>
            $content
        </div>
        <div style='padding: 20px; background-color: #f9f9f9; text-align: center; font-size: 12px; color: #888; border-top: 1px solid #eeeeee;'>
            <p style='margin: 0;'>&copy; " . date('Y') . " $footer_text. All rights reserved.</p>
            <p style='margin: 5px 0 0;'>This is an automated system notification. Please do not reply.</p>
        </div>
    </div>";
}

/**
 * Initialize PHPMailer with SMTP settings
 */
function init_mailer() {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->Port       = SMTP_PORT;
    $mail->SMTPSecure = (SMTP_SECURE == 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
    return $mail;
}

// ---------------------------------------------------------
// 3. DATA RETRIEVAL FUNCTIONS
// ---------------------------------------------------------

function get_return_items_due($conn, $days_offset) {
    $target_date_sql = ($days_offset == 0) ? "CURDATE()" : "DATE_ADD(CURDATE(), INTERVAL $days_offset DAY)";
    $sql = "SELECT ri.id, r.reserve_date, r.return_date, ri.quantity, u.name AS user_name, u.email AS user_email, i.item_name
            FROM reservation_items ri
            JOIN reservations r ON ri.reserve_id = r.reserve_id
            JOIN person u ON r.person_id = u.person_id
            JOIN item i ON ri.item_id = i.item_id
            WHERE ri.status = 'Checked Out' AND DATE(r.return_date) = $target_date_sql";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_overdue_items($conn) {
    $sql = "SELECT ri.id, r.reserve_date, r.return_date, ri.quantity, u.name AS user_name, u.email AS user_email, i.item_name
            FROM reservation_items ri
            JOIN reservations r ON ri.reserve_id = r.reserve_id
            JOIN person u ON r.person_id = u.person_id
            JOIN item i ON ri.item_id = i.item_id
            WHERE ri.status = 'Checked Out' AND DATE(r.return_date) < CURDATE()";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_pickup_reminders_today($conn) {
    $sql = "SELECT r.reserve_id, r.reserve_date, u.name AS user_name, u.email AS user_email,
                   GROUP_CONCAT(CONCAT(i.item_name, ' (x', ri.quantity, ')') SEPARATOR ', ') AS item_summary
            FROM reservations r
            JOIN person u ON r.person_id = u.person_id
            JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
            JOIN item i ON ri.item_id = i.item_id
            WHERE ri.status = 'APPROVED' AND DATE(r.reserve_date) = CURDATE()
            GROUP BY r.reserve_id";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_collection_items_to_prep($conn) {
    $sql = "SELECT r.reserve_id, r.reserve_date, u.name AS user_name,
                   GROUP_CONCAT(CONCAT(i.item_name, ' (x', ri.quantity, ')') SEPARATOR '; ') AS requested_items
            FROM reservations r
            JOIN person u ON r.person_id = u.person_id
            JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
            JOIN item i ON ri.item_id = i.item_id
            WHERE ri.status = 'APPROVED' AND DATE(r.reserve_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            GROUP BY r.reserve_id";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// ---------------------------------------------------------
// 4. NOTIFICATION LOGIC
// ---------------------------------------------------------

function send_return_email($recipient_email, $recipient_name, $items, $is_today, $is_overdue = false) {
    try {
        $mail = init_mailer();
        $mail->addAddress($recipient_email, $recipient_name);
        $mail->isHTML(true);

        if ($is_overdue) {
            $status_text = 'OVERDUE';
            $header_title = "ACTION REQUIRED: RETURN OVERDUE";
            $color = '#e74c3c';
        } else {
            $status_text = $is_today ? 'TODAY' : 'TOMORROW';
            $header_title = "Reminder: Item Return Due $status_text";
            $color = '#004d99';
        }

        $mail->Subject = $header_title;

        $table = "<table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
                    <thead><tr style='background-color: #f8f9fa; border-bottom: 2px solid $color;'>
                        <th style='padding: 12px; text-align: left;'>Item Name</th>
                        <th style='padding: 12px; text-align: center;'>Qty</th>
                        <th style='padding: 12px; text-align: center;'>Due Date</th>
                    </tr></thead><tbody>";
        foreach ($items as $item) {
            $table .= "<tr style='border-bottom: 1px solid #eee;'>
                        <td style='padding: 12px;'>{$item['item_name']}</td>
                        <td style='padding: 12px; text-align: center;'>{$item['quantity']}</td>
                        <td style='padding: 12px; text-align: center; color: $color; font-weight: bold;'>".date('d M Y', strtotime($item['return_date']))."</td>
                      </tr>";
        }
        $table .= "</tbody></table>";

        $content = "<p style='font-size: 16px;'>Hello <strong>$recipient_name</strong>,</p>
                    <p>This is to remind you that the following items are <strong>$status_text</strong> for return.</p>
                    $table
                    <p style='margin-top: 20px;'>Please return these items to the Technical Department counter promptly. Thank you.</p>";

        $mail->Body = email_template($header_title, $content);
        return $mail->send();
    } catch (Exception $e) { return false; }
}

function send_pickup_notification($recipient_email, $recipient_name, $reserve_id, $items_text) {
    try {
        $mail = init_mailer();
        $mail->addAddress($recipient_email, $recipient_name);
        $mail->isHTML(true);
        $header_title = "Ready for Collection: #$reserve_id";
        $mail->Subject = "REMINDER: Collect Your Items Today";

        $content = "<p style='font-size: 16px;'>Hi <strong>$recipient_name</strong>,</p>
                    <p>Your reserved items are prepared and ready for collection <strong>TODAY</strong>.</p>
                    <div style='background: #f4f7f6; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                        <strong>Reservation ID:</strong> #$reserve_id<br><strong>Items:</strong> $items_text
                    </div>
                    <p>Please head to the <strong>Technical Department</strong> counter to collect your assets.</p>";

        $mail->Body = email_template($header_title, $content);
        return $mail->send();
    } catch (Exception $e) { return false; }
}

function send_technician_prep_reminder($tech_email, $tech_name, $prep_list) {
    try {
        $mail = init_mailer();
        $mail->addAddress($tech_email, $tech_name);
        $mail->isHTML(true);
        $header_title = "Action Required: Tomorrow's Collection";
        $mail->Subject = "Prepare Assets for Tomorrow";

        $list = "<ul>";
        foreach ($prep_list as $res) { $list .= "<li><strong>ID {$res['reserve_id']}:</strong> {$res['requested_items']} (User: {$res['user_name']})</li>"; }
        $list .= "</ul>";

        $content = "<p>Dear <strong>$tech_name</strong>,</p><p>The following items are scheduled for pickup tomorrow. Please prepare them:</p>$list";
        $mail->Body = email_template($header_title, $content);
        return $mail->send();
    } catch (Exception $e) { return false; }
}

// ---------------------------------------------------------
// 5. EXECUTION LOGIC
// ---------------------------------------------------------

$counts = ['pickup' => 0, 'today' => 0, 'tomorrow' => 0, 'overdue' => 0];

// Process Pickups
foreach (get_pickup_reminders_today($conn) as $p) {
    if (send_pickup_notification($p['user_email'], $p['user_name'], $p['reserve_id'], $p['item_summary'])) $counts['pickup']++;
}

// Process Due Today
foreach (get_return_items_due($conn, 0) as $item) {
    if (send_return_email($item['user_email'], $item['user_name'], [$item], true)) $counts['today']++;
}

// Process Due Tomorrow
foreach (get_return_items_due($conn, 1) as $item) {
    if (send_return_email($item['user_email'], $item['user_name'], [$item], false)) $counts['tomorrow']++;
}

// Process Overdue
foreach (get_overdue_items($conn) as $item) {
    if (send_return_email($item['user_email'], $item['user_name'], [$item], false, true)) $counts['overdue']++;
}

// Process Tech Prep
$to_prep = get_collection_items_to_prep($conn);
if (!empty($to_prep)) {
    send_technician_prep_reminder('aina.fatihah@t.unikl.edu.my', 'Main Technician', $to_prep);
}

echo "Summary: Pickups: {$counts['pickup']}, Today: {$counts['today']}, Tomorrow: {$counts['tomorrow']}, Overdue: {$counts['overdue']}.\n";
$conn->close();