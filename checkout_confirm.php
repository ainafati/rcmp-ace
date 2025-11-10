<?php
session_start();
include 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reserve_id = (int)$_POST['reserve_id'];

    // 1. Update status
    $stmt = $conn->prepare("UPDATE reservations SET status = 'checked_out' WHERE reserve_id = ?");
    $stmt->bind_param("i", $reserve_id);
    $stmt->execute();
    $stmt->close();

    // 2. Fetch reservation info (user email, items, dates)
    $sql = "
        SELECT u.email, u.name AS username, r.reserve_date, r.return_date, i.item_name, ri.quantity
        FROM reservations r
        JOIN user u ON r.user_id = u.user_id
        JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
        JOIN item i ON ri.item_id = i.item_id
        WHERE r.reserve_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $reserve_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $items_list = "";
    $user_email = "";
    $username   = "";
    $reserve_date = "";
    $return_date = "";

    while ($row = $res->fetch_assoc()) {
        $user_email = $row['email'];
        $username   = $row['username'];
        $reserve_date = $row['reserve_date'];
        $return_date = $row['return_date'];
        $items_list .= "- {$row['item_name']} (Qty: {$row['quantity']})<br>";
    }
    $stmt->close();

    // 3. Send Email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ainafthhj@gmail.com';  
        $mail->Password   = 'udzl nvxz sqfd ddwl';  // app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;


        $mail->setFrom('ainafthhj@gmail.com', 'UniKL Inventory');
        $mail->addAddress('aina.fatihah@t.unikl.edu.my', $username);

        $mail->isHTML(true);
        $mail->Subject = "Reservation Checked Out - #$reserve_id";
        $mail->Body    = "
            Hi <b>$username</b>,<br><br>
            Your reservation has been confirmed.<br><br>
            <b>Items:</b><br>
            $items_list
            <br>
            <b>Pickup Date:</b> $reserve_date<br>
            <b>Return Date:</b> $return_date<br><br>
            Please take care of the items and return on time.<br><br>
            Regards,<br>
            UniKL Technician
        ";

        $mail->send();
        echo 'success';
    } catch (Exception $e) {
        echo "Mailer Error: {$mail->ErrorInfo}";
    }
}