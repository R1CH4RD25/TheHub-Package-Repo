<?php

namespace Hub;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Email
{
    private $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        
        // Server settings
        $this->mailer->isSMTP();
        $this->mailer->Host       = $_ENV['SMTP_HOST'];
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = $_ENV['SMTP_USERNAME'];
        $this->mailer->Password   = $_ENV['SMTP_PASSWORD'];
        
        // Set encryption based on .env setting
        $encryption = strtolower($_ENV['SMTP_ENCRYPTION'] ?? 'tls');
        if ($encryption === 'ssl') {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $this->mailer->Port       = $_ENV['SMTP_PORT'];
        
        // Sender
        $fromName = $_ENV['SMTP_FROM_NAME'] ?? 'The Hub';
        $this->mailer->setFrom($_ENV['SMTP_FROM_EMAIL'], $fromName);
    }

    public function send($to, $subject, $body, $isHtml = false)
    {
        try {
            // Recipient
            $this->mailer->addAddress($to);

            // Content
            $this->mailer->isHTML($isHtml);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $body;
            
            if ($isHtml) {
                // Plain text alternative
                $this->mailer->AltBody = strip_tags($body);
            }

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email send failed: {$this->mailer->ErrorInfo}");
            throw new \Exception("Failed to send email: {$this->mailer->ErrorInfo}");
        }
    }

    public function sendTestEmail($to)
    {
        $appName = $_ENV['SMTP_FROM_NAME'] ?? 'The Hub';
        $subject = 'Test Email from ' . $appName;
        
        $body = "This is a test email from " . $appName . ".\n\n";
        $body .= "If you received this email, the email configuration is working correctly!\n\n";
        $body .= "Sent at: " . date('Y-m-d H:i:s') . "\n";
        $body .= "System URL: " . $_ENV['APP_URL'];
        
        return $this->send($to, $subject, $body);
    }
}
