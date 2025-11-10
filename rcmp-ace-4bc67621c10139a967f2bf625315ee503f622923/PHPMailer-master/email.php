<?php
// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Create an instance; passing `true` enables exceptions
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
    $mail->isSMTP();                                            // Send using SMTP
    $mail->Host       = 'smtp.gmail.com';                        // Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                    // Enable SMTP authentication
    $mail->Username   = 'ainafthhj@gmail.com';                   // SMTP username
    $mail->Password   = 'udzl nvxz sqfd ddwl';                   // SMTP password (Use your actual password or app password)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;          // Enable implicit TLS encryption
    $mail->Port       = 587;                                     // TCP port to connect to; use 587 for TLS

    // Recipients
    $mail->setFrom('aina.fati@t.unikl.edu.my', 'Mailer');
    $mail->addAddress('ainafthhj@gmail.com', 'Joe User');        // Add a recipient
    $mail->addAddress('ellen@example.com');                      // Add another recipient
    $mail->addReplyTo('info@example.com', 'Information');
    $mail->addCC('cc@example.com');
    $mail->addBCC('bcc@example.com');

    // Attachments (if any)
    $mail->addAttachment('/var/tmp/file.tar.gz');                // Add attachments
    $mail->addAttachment('/tmp/image.jpg', 'new.jpg');           // Optional name for attachment

    // Content
    $mail->isHTML(true);                                         // Set email format to HTML
    $mail->Subject = 'Here is the subject';
    $mail->Body    = 'This is the HTML message body <b>in bold!</b>';
    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

    $mail->send();  // Attempt to send the email
    echo 'Message has been sent';
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
?>
