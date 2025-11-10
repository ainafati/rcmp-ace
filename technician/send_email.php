<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Pastikan laluan fail PHPMailer anda betul
require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

function sendNotificationEmail($to_email, $user_name, $item_name, $asset_code, $reserve_date, $return_date, $smtp_user, $smtp_pass) {
    // PENGISYTIHARAN OBJEK PHPMailer
    $mail = new PHPMailer(true);

    try {
        // --- KONFIGURASI SMTP YANG HILANG (WAJIB ADA) ---
        $mail->isSMTP();                                       // Hantar menggunakan SMTP
        
        // GANTI NILAI INI DENGAN SERVER EMAIL ANDA YANG SEBENAR
        $mail->Host       = 'smtp.gmail.com';                // Contoh: smtp.gmail.com atau mail.unikl.edu.my
        $mail->SMTPAuth   = true;                              // Dayakan pengesahan SMTP
        $mail->Username   = $smtp_user;                        // Gunakan SMTP_USER dari config_email.php
        $mail->Password   = $smtp_pass;                        // Gunakan SMTP_PASS dari config_email.php (Pastikan ia App Password!)
        
        // GANTI NILAI INI DENGAN PORT ANDA YANG SEBENAR
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;       // Gunakan SMTPS (untuk Port 465)
        $mail->Port       = 465;                               // Atau 587 jika anda menggunakan TLS
        
        // Pastikan konfigurasi ini sama dengan apa yang anda tetapkan dalam config_email.php
        
        // --- BUTIRAN PENGHANTAR DAN PENERIMA ---
        $mail->setFrom($smtp_user, 'UniKL Inventory System');
        $mail->addAddress($to_email, $user_name);

        // --- KANDUNGAN E-MEL ---
        $mail->isHTML(true); 
        $mail->Subject = 'Confirmation of Assigned Assets ' . $item_name;
        
        $mail->Body = "
<html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px;'>
            <h2 style='color: #27ae60;'>Reservation Approved and Asset Assigned</h2>
            
            <p>Hello <strong>{$user_name}</strong>,</p>
            
            <p>Your reservation for the following asset(s) has been <strong>approved</strong> by the technician and is ready for collection.</p>
            
            <table border='0' cellpadding='5' cellspacing='0' style='width: 100%; margin: 15px 0; border-collapse: collapse;'>
                <tr>
                    <td style='width: 35%; padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Requested Item:</strong></td>
                    <td style='width: 65%; padding: 8px 0; border-bottom: 1px solid #eee;'>{$item_name}</td>
                </tr>
                <tr>
                    <td style='width: 35%; padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Reserved Date:</strong></td>
                    <td style='width: 65%; padding: 8px 0; border-bottom: 1px solid #eee;'>{$reserve_date}</td>
                </tr>
                <tr>
                    <td style='width: 35%; padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Return Date:</strong></td>
                    <td style='width: 65%; padding: 8px 0; border-bottom: 1px solid #eee;'><strong>{$return_date}</strong></td>
                </tr>
                <tr>
                    <td style='width: 35%; padding: 8px 0;'><strong>Assigned Asset Code(s):</strong></td>
                    <td style='width: 65%; padding: 8px 0;'><strong>{$asset_code}</strong></td>
                </tr>
            </table>

            <p style='color: #e67e22; font-weight: bold;'>Action Required:</p>
            <p>Please proceed to the inventory counter to collect the asset(s). Ensure you collect the specific asset(s) with the codes listed above.</p>

            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='font-size: 12px; color: #999;'>This is an automated email. Please do not reply to this message.</p>
            <p style='font-size: 12px; color: #999;'>UniKL Inventory System</p>
        </div>
    </body>
</html>
        ";
        
        $mail->AltBody = "Your reservation has been approved. Your assigned Asset Code(s) are: " . $asset_code . ". Please refer to the system for details.";

        $mail->send();
        return true; 
    } catch (Exception $e) {
        // Log ralat sebenar ke log server/PHP
        error_log("PHPMailer Error: {$mail->ErrorInfo}"); 
        return false; 
    }
}
?>