<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Pastikan fail konfigurasi e-mel anda di-'require' di sini atau sebelum fungsi ini dipanggil.

require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

function sendNotificationEmail($to_email, $user_name, $item_name, $asset_code, $reserve_date, $return_date, $smtp_user, $smtp_pass, $partial_reason = null) {
    
    $mail = new PHPMailer(true);

    try {
        
        // 1. PENGGUNAAN KONFIGURASI GLOBAL
        // Ambil nilai debug (0 untuk Live, 4 untuk Local) dari fail config
        $mail->SMTPDebug = SMTP_DEBUG_LEVEL; 
        $mail->Debugoutput = 'error_log'; 
        

        $mail->isSMTP();
        
        // Tetapan SMTP dari fail konfigurasi
        $mail->Host      = SMTP_HOST;      
        
        // 2. PENGGUNAAN SMTP_AUTH DARI KONFIGURASI
        // Ambil nilai (true/false) dari fail config
        $mail->SMTPAuth  = SMTP_AUTH; 
        
        // Pengguna dan Kata Laluan kekal menggunakan pembolehubah yang dihantar ke fungsi
        $mail->Username  = $smtp_user;    
        $mail->Password  = $smtp_pass;
        
        // Sekuriti dan Port dari fail konfigurasi
        // Perhatian: Jika LOCAL_MAIL_TEST=true, SMTP_SECURE akan menjadi 'false'
        $mail->SMTPSecure = SMTP_SECURE; 
        $mail->Port      = SMTP_PORT;      
        
        
        // Tetapan From
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME); 
        $mail->addAddress($to_email, $user_name);

        $mail->isHTML(true);
        $mail->Subject = 'Confirmation of Assigned Assets ' . $item_name;
        
        // ... (Kandungan Body E-mel dikekalkan sama) ...
        
        $partial_notice = '';
        if ($partial_reason) {
            $partial_notice = "<p style='color: #e74c3c; font-weight: bold; border: 1px dashed #e74c3c; padding: 10px;'>
                                 <strong>Attention:</strong> The approved quantity is reduced. {$partial_reason}
                                </p>";
        }

        $mail->Body = "
<html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px;'>
            <h2 style='color: #27ae60;'>Reservation Approved and Asset Assigned</h2>
            
            <p>Hello <strong>{$user_name}</strong>,</p>
            
            {$partial_notice} <p>Your reservation for the following asset(s) has been <strong>approved</strong> by the technician and is ready for collection.</p>
            
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
        
        $mail->AltBody = "Your reservation has been approved. Your assigned Asset Code(s) are: " . $asset_code . ". Please refer to the system for details." . ($partial_reason ? " Note: Quantity was partially rejected. Reason: {$partial_reason}" : "");

        $mail->send();
        return true; 
    } catch (Exception $e) {
        
        error_log("PHPMailer Error: {$mail->ErrorInfo}"); 
        return false; 
    }
}
?>