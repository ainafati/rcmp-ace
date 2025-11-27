<?php


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;



require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';





function sendNotificationEmail($to_email, $user_name, $item_name, $asset_code, $reserve_date, $return_date, $smtp_user, $smtp_pass, $partial_reason = null) {
    
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = SMTP_DEBUG_LEVEL; 
        $mail->Debugoutput = 'error_log'; 
        
        $mail->isSMTP();
        $mail->Host      = SMTP_HOST;
        $mail->SMTPAuth    = SMTP_AUTH;
        $mail->Username    = $smtp_user; 
        $mail->Password    = $smtp_pass;
        $mail->SMTPSecure = SMTP_SECURE; 
        $mail->Port      = SMTP_PORT; 
        
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME); 
        $mail->addAddress($to_email, $user_name);

        $mail->addBCC('it.rcmp@unikl.edu.my', 'IT Monitoring');

        $mail->isHTML(true);
        $mail->Subject = 'Confirmation of Assigned Assets ' . $item_name;
        
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


function sendNewReservationNotification($technician_email, $reserve_id, $user_name, $item_name, $reserve_date, $link_to_approval) {
    
    $mail = new PHPMailer(true);

    try {
        
        $mail->isSMTP();
        $mail->Host      = SMTP_HOST;
        $mail->SMTPAuth    = SMTP_AUTH;
        $mail->Username    = SMTP_USER;
        $mail->Password    = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port      = SMTP_PORT;
        $mail->SMTPDebug  = SMTP_DEBUG_LEVEL;

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        
        $mail->addAddress($technician_email, 'Inventory Technician'); 

        $mail->isHTML(true);
        $mail->Subject = "NEW RESERVATION: Item - {$item_name} by {$user_name}";
        
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ffcc00; padding: 20px; background-color: #fffacd;'>
                    <h3 style='color: #ff9900;'>New Reservation Request Requires Action</h3>
                    <p>A user has submitted a new asset reservation request that requires your approval.</p>
                    
                    <table border='0' cellpadding='5' cellspacing='0' style='width: 100%; margin: 15px 0; border-collapse: collapse; border: 1px solid #ccc;'>
                        <tr><td style='width: 35%; padding: 8px;'><strong>Reservation ID:</strong></td><td style='width: 65%; padding: 8px;'><strong>{$reserve_id}</strong></td></tr>
                        <tr><td style='padding: 8px;'><strong>Requested By:</strong></td><td style='padding: 8px;'>{$user_name}</td></tr>
                        <tr><td style='padding: 8px;'><strong>Item Requested:</strong></td><td style='padding: 8px;'>{$item_name}</td></tr>
                        <tr><td style='padding: 8px;'><strong>Reservation Date:</strong></td><td style='padding: 8px;'>{$reserve_date}</td></tr>
                    </table>

                    <p style='margin-top: 20px;'>Please click the button below to review and approve/reject this request:</p>
                    
                    <a href='{$link_to_approval}' style='display: inline-block; padding: 10px 20px; color: white; background-color: #007bff; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                        Review Request Now
                    </a>
                    <hr style='border: 0; border-top: 1px solid #ddd; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #999;'>Please confirm the status of this request as soon as possible.</p>
                </div>
            </body>
            </html>
        ";

        $mail->AltBody = "A new reservation request has been submitted by {$user_name} for {$item_name}. Please log into the system for approval: {$link_to_approval}";     $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error (New Reservation): {$mail->ErrorInfo}");
        return false;
    }
}

function sendRejectionEmail($to_email, $user_name, $item_name, $rejection_reason, $smtp_user, $smtp_pass) {
    
    
    $mail = new PHPMailer(true); 

    try {
        $mail->SMTPDebug = SMTP_DEBUG_LEVEL;
        $mail->Debugoutput = 'error_log';
        
        $mail->isSMTP();
        $mail->Host      = SMTP_HOST;
        $mail->SMTPAuth    = SMTP_AUTH;
        $mail->Username    = $smtp_user; 
        $mail->Password    = $smtp_pass;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port      = SMTP_PORT; 
        
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $user_name);

        
        $mail->addBCC('it.rcmp@unikl.edu.my', 'IT Monitoring'); 

        $mail->isHTML(true);
        $mail->Subject = 'IMPORTANT: Your Reservation Request for ' . $item_name . ' has been Rejected';
        
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px;'>
                    <h2 style='color: #e74c3c;'>Reservation Request Rejected</h2>
                    <p>Hello <strong>{$user_name}</strong>,</p>
                    <p>We regret to inform you that your reservation request for the following item has been <strong>REJECTED</strong>:</p>
                    
                    <ul style='list-style-type: none; padding: 0;'>
                        <li style='margin-bottom: 10px; padding: 5px; background-color: #fceae9; border-left: 3px solid #e74c3c;'><strong>Item Requested:</strong> {$item_name}</li>
                    </ul>

                    <p style='font-weight: bold; color: #e74c3c;'>Reason for Rejection:</p>
                    <p style='border: 1px solid #e74c3c; padding: 10px; background-color: #fff; border-radius: 5px;'>
                        " . nl2br(htmlspecialchars($rejection_reason)) . "
                    </p>
                    
                    <p>If you require further clarification, please contact the inventory technician.</p>
                    
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #999;'>This is an automated email from the UniKL Inventory System.</p>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Your reservation for " . $item_name . " has been REJECTED. Reason: " . $rejection_reason;

        $mail->send();
        return true; 
    } catch (Exception $e) {
        error_log("PHPMailer Rejection Error: {$mail->ErrorInfo}"); 
        return false; 
    }
}
?>