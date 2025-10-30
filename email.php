<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->SMTPDebug = 2;                        // Debug info (0 kalau tak nak)
    $mail->isSMTP();                             
    $mail->Host       = 'smtp.gmail.com';        
    $mail->SMTPAuth   = true;                    
    $mail->Username   = 'ainafati12@gmail.com';   // Gmail awak
    $mail->Password   = 'wiegizepudxjdezw';   // App password (bukan password biasa Gmail)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
    $mail->Port       = 587;                     

$mail->SMTPOptions = array(
    'ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    )
);
    // Recipients
    $mail->setFrom('ainafthhj@gmail.com', 'Aina');
    $mail->addAddress('ainafthhj@gmail.com');   // Send ke email sendiri dulu untuk test

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email dari PHPMailer';
    $mail->Body    = 'Hello Aina, ini <b>test email</b> dari PHPMailer!';
    $mail->AltBody = 'Hello Aina, ini test email dari PHPMailer (plain text).';

    $mail->send();
    echo '✅ Message has been sent';
} catch (Exception $e) {
    echo "❌ Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
?>