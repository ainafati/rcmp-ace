<?php

// Define the root directory path
define('ROOT_DIR', '../'); // HANYA SATU DOT-DOT-SLASH

// Include configuration files
require ROOT_DIR . 'config.php'; // Panggil config.php dari ROOT_DIR
require ROOT_DIR . 'config_email.php';

// Load PHPMailer classes
require ROOT_DIR . 'PHPMailer-master/src/Exception.php';
require ROOT_DIR . 'PHPMailer-master/src/PHPMailer.php';
require ROOT_DIR . 'PHPMailer-master/src/SMTP.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


// Check database connection status
if ($conn->connect_error) {
    error_log("CRON JOB FAILED: Database connection failed: " . $conn->connect_error);
    echo "CRON JOB FAILED: Database connection error.\n";
    exit();
}

function get_return_items_due($conn, $days_offset) {
    // Determine the target date based on offset (CURDATE() or CURDATE() + 1 day)
    $target_date_sql = $days_offset == 0 ? "CURDATE()" : "DATE_ADD(CURDATE(), INTERVAL $days_offset DAY)";
    
    $sql = "SELECT
                ri.id, r.reserve_date, r.return_date, ri.quantity,
                u.name AS user_name, u.email AS user_email, u.phoneNum AS user_phone,
                i.item_name
            FROM reservation_items ri
            JOIN reservations r ON ri.reserve_id = r.reserve_id
            JOIN person u ON r.person_id = u.person_id
            JOIN item i ON ri.item_id = i.item_id
            WHERE ri.status = 'Checked Out' AND DATE(r.return_date) = $target_date_sql";
            
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_overdue_items($conn) {
    $sql = "SELECT
                ri.id, r.reserve_date, r.return_date, ri.quantity,
                u.name AS user_name, u.email AS user_email, u.phoneNum AS user_phone,
                i.item_name
            FROM reservation_items ri
            JOIN reservations r ON ri.reserve_id = r.reserve_id
            JOIN person u ON r.person_id = u.person_id
            JOIN item i ON ri.item_id = i.item_id
            WHERE ri.status = 'Checked Out' AND DATE(r.return_date) < CURDATE()";
            
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_collection_items_to_prep($conn) {
    $tomorrow_date_sql = "DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
    
    $sql = "SELECT
                r.reserve_id,
                r.reserve_date,
                u.name AS user_name,
                GROUP_CONCAT(CONCAT(i.item_name, ' (x', ri.quantity, ')') SEPARATOR '; ') AS requested_items
            FROM reservations r
            JOIN person u ON r.person_id = u.person_id
            JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
            JOIN item i ON ri.item_id = i.item_id
            WHERE 
                ri.status = 'APPROVED'
                AND DATE(r.reserve_date) = $tomorrow_date_sql
            GROUP BY
                r.reserve_id, r.reserve_date, u.name
            ORDER BY
                r.reserve_date ASC";
            
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}


function send_email_notification($recipient_email, $recipient_name, $items, $is_today, $is_overdue = false) {
    $mail = new PHPMailer(true);
    try {
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host      = SMTP_HOST;
        $mail->SMTPAuth  = true;
        $mail->Username  = SMTP_USER;
        $mail->Password  = SMTP_PASS;
        
        if (SMTP_SECURE == 'tls') {
             $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif (SMTP_SECURE == 'ssl') {
             $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        $mail->Port      = SMTP_PORT;
        
        
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME); 
        $mail->addAddress($recipient_email, $recipient_name);
        
        
        // Determine Subject and Main Message
        if ($is_overdue) {
            $date_str = 'OVERDUE';
            $subject = "URGENT: Inventory Item Return (OVERDUE)";
            $color = '#e74c3c';
        } else {
            $date_str = $is_today ? 'TODAY' : 'TOMORROW';
            $subject = "Inventory Item Return (Due " . strtoupper($date_str) . ")";
            $color = '#004d99';
        }

        
        // Build HTML Table
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
        
        
        $body = "
             <p style='font-family: Arial, sans-serif;'>Dear <strong>" . htmlspecialchars($recipient_name) . "</strong>,</p>
             <p style='font-family: Arial, sans-serif;'>This is an official and automated notice from the UniKL Inventory Management System regarding items currently in your possession. We wish to inform you that the item(s) listed below are <strong>due for return on $date_str</strong>.</p>
             <h3 style='font-family: Arial, sans-serif; color: {$color};'>Item Return Details:</h3>
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

function send_technician_prep_reminder($technician_email, $technician_name, $reservations_to_prep) {
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host      = SMTP_HOST;
        $mail->SMTPAuth  = true;
        $mail->Username  = SMTP_USER;
        $mail->Password  = SMTP_PASS;
        if (SMTP_SECURE == 'tls') {
             $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif (SMTP_SECURE == 'ssl') {
             $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        $mail->Port      = SMTP_PORT;

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($technician_email, $technician_name);

        $mail->isHTML(true);
        $mail->Subject = 'ACTION REQUIRED: Prepare Assets for Collection Tomorrow';
        $color = '#e67e22'; // Orange color

        $list_html = '<ul style="list-style-type: none; padding: 0;">';
        foreach ($reservations_to_prep as $res) {
            $reserve_date_fmt = date('d M Y', strtotime($res['reserve_date']));
            $list_html .= "
                <li style='margin-bottom: 15px; border-left: 4px solid #f39c12; padding-left: 10px; background-color: #fffaf0; padding: 10px; border-radius: 4px;'>
                    <strong>Reservation ID:</strong> {$res['reserve_id']}<br>
                    <strong>User:</strong> " . htmlspecialchars($res['user_name']) . "<br>
                    <strong>Items (Quantity):</strong> " . htmlspecialchars($res['requested_items']) . "<br>
                    <strong>Collection Date:</strong> {$reserve_date_fmt} (<span style='color: red; font-weight: bold;'>TOMORROW</span>)
                </li>
            ";
        }
        $list_html .= '</ul>';

        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 650px; margin: 0 auto; border: 1px solid #f39c12; padding: 20px; background-color: #fcf8e3;'>
                    <h2 style='color: {$color};'>Asset Preparation Reminder (Collection Tomorrow)</h2>
                    <p>Dear <strong>{$technician_name}</strong>,</p>
                    <p>Please note that the items below have been <strong>approved</strong> and are scheduled for collection by users <strong>tomorrow</strong>. Kindly prepare the relevant items/assets at the inventory counter, including asset code assignment if not yet done.</p>
                    
                    <h3 style='font-family: Arial, sans-serif; color: #e67e22;'>Reservations Requiring Preparation:</h3>
                    {$list_html}
                    
                    <p style='margin-top: 20px;'>Please log into the system to view further details and handle the check-out process.</p>
                    <hr style='border: 0; border-top: 1px solid #ddd; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #999;'>UniKL Inventory System (Automated Technician Reminder)</p>
                </div>
            </body>
            </html>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send technician prep email to $technician_email. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}


// 1. Get items due today
$today_items = get_return_items_due($conn, 0);
$users_due_today = [];
if (!empty($today_items)) {
    foreach ($today_items as $item) {
        // Group items by user email to send one email per user
        $users_due_today[$item['user_email']]['name'] = $item['user_name'];
        $users_due_today[$item['user_email']]['items'][] = $item;
    }
    foreach ($users_due_today as $email => $user_data) {
        send_email_notification($email, $user_data['name'], $user_data['items'], true, false);
    }
}


// 2. Get items due tomorrow
$tomorrow_items = get_return_items_due($conn, 1);
$users_due_tomorrow = [];
if (!empty($tomorrow_items)) {
    foreach ($tomorrow_items as $item) {
        // Group items by user email
        $users_due_tomorrow[$item['user_email']]['name'] = $item['user_name'];
        $users_due_tomorrow[$item['user_email']]['items'][] = $item;
    }
    foreach ($users_due_tomorrow as $email => $user_data) {
        send_email_notification($email, $user_data['name'], $user_data['items'], false, false);
    }
}


// 3. Get overdue items
$overdue_items = get_overdue_items($conn);
$users_overdue = [];
if (!empty($overdue_items)) {
    foreach ($overdue_items as $item) {
        // Group items by user email
        $users_overdue[$item['user_email']]['name'] = $item['user_name'];
        $users_overdue[$item['user_email']]['items'][] = $item;
    }
    foreach ($users_overdue as $email => $user_data) {
        send_email_notification($email, $user_data['name'], $user_data['items'], false, true);
    }
}


// 4. Send preparation reminder to technicians
$reservations_to_prep = get_collection_items_to_prep($conn);
$tech_prep_sent_count = 0;

if (!empty($reservations_to_prep)) {
    
    // Define Technician Email List
    $technician_emails = [
        ['email' => 'aina.fatihah@t.unikl.edu.my', 'name' => 'Main Technician'],
        // Add more technician emails if needed
    ];

    foreach ($technician_emails as $tech) {
        $is_sent = send_technician_prep_reminder(
            $tech['email'],
            $tech['name'],
            $reservations_to_prep
        );
        if ($is_sent) {
            $tech_prep_sent_count++;
        }
    }
}


// Output Cron Job Result
echo "Script Reminder Complete. Found " . count($today_items) . " item(s) due today, " . count($tomorrow_items) . " item(s) due tomorrow, " . count($overdue_items) . " item(s) overdue. Sent prep reminders to {$tech_prep_sent_count} technician(s) for " . count($reservations_to_prep) . " reservations.\n";


// Optional debugging output for overdue items
if (count($overdue_items) > 0) {
    echo "ATTEMPTING TO SEND EMAIL TO: " . $overdue_items[0]['user_email'] . " (Example of Overdue Email)\n";
}

$conn->close();
// NO CLOSING PHP TAG (Best practice for PHP scripts)