<?php

// includes/mailer.php

// Use PHPMailer classes for sending emails.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load the Composer autoloader to access the PHPMailer library.
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Sends an email using PHPMailer via an SMTP server.
 *
 * @param string $to The recipient's email address.
 * @param string $subject The subject line of the email.
 * @param string $bodyText The plain-text body of the email.
 * @return bool True on success, false on failure.
 */
function send_mail($to, $subject, $bodyText) {
    $mail = new PHPMailer(true);

    try {
        // Configure SMTP settings (for Hostinger).
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        
        // IMPORTANT: Replace with your actual SMTP credentials.
        $mail->Username   = 'no-reply@yourdomain.com';
        $mail->Password   = 'YOUR_SMTP_PASSWORD';
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Set sender information.
        // IMPORTANT: Replace with your actual "From" address and name.
        $mail->setFrom('no-reply@yourdomain.com', 'Budget System');

        // Add the recipient.
        $mail->addAddress($to);

        // Set email content.
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $bodyText;

        // Send the email.
        $mail->send();
        return true;

    } catch (Exception $e) {
        // Handle exceptions (e.g., failed to connect, wrong credentials).
        // For production, it's a good practice to log the error.
        // error_log('Message could not be sent. Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}
