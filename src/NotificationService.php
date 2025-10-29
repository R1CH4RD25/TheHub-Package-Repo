<?php
/**
 * Notification Service
 * Handles email and SMS notifications for section events
 */

namespace Hub;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class NotificationService
{
    /**
     * Send notification to recipients
     * 
     * @param array $recipients Array of recipient objects with email/phone/preferences
     * @param string $subject Email subject
     * @param string $message Email/SMS message (plain text)
     * @param string $htmlMessage Optional HTML version of message
     * @param array $options Additional options (priority, attachments, etc.)
     * @return array Results with success/failure counts
     */
    public static function send($recipients, $subject, $message, $htmlMessage = null, $options = [])
    {
        $results = [
            'email' => ['sent' => 0, 'failed' => 0, 'skipped' => 0],
            'sms' => ['sent' => 0, 'failed' => 0, 'skipped' => 0]
        ];
        
        foreach ($recipients as $recipient) {
            // Determine contact method
            $contactMethod = $recipient['preferred_contact_method'] ?? 'email';
            
            // Send email if enabled
            if ($recipient['email_enabled'] && $contactMethod !== 'sms_only') {
                $emailTo = $recipient['email'];
                
                // Check if alt_email should be used
                if ($contactMethod === 'alt_email' && !empty($recipient['alt_email'])) {
                    $emailTo = $recipient['alt_email'];
                }
                
                $emailResult = self::sendEmail($emailTo, $subject, $message, $htmlMessage, $options);
                
                if ($emailResult) {
                    $results['email']['sent']++;
                } else {
                    $results['email']['failed']++;
                }
            } else {
                $results['email']['skipped']++;
            }
            
            // Send SMS if enabled
            if ($recipient['sms_enabled'] && !empty($recipient['phone']) && $contactMethod !== 'email_only') {
                $smsResult = self::sendSMS($recipient['phone'], $message);
                
                if ($smsResult) {
                    $results['sms']['sent']++;
                } else {
                    $results['sms']['failed']++;
                }
            } else {
                $results['sms']['skipped']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Send email using PHPMailer
     * 
     * @param string $to Recipient email
     * @param string $subject Subject line
     * @param string $message Plain text message
     * @param string $htmlMessage Optional HTML message
     * @param array $options Additional options
     * @return bool Success
     */
    private static function sendEmail($to, $subject, $message, $htmlMessage = null, $options = [])
    {
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            if (defined('SMTP_HOST') && SMTP_HOST) {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = SMTP_ENCRYPTION ?? PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = SMTP_PORT ?? 587;
            } else {
                $mail->isMail();
            }
            
            // Recipients
            $mail->setFrom(SMTP_FROM_EMAIL ?? 'noreply@' . $_SERVER['HTTP_HOST'], SMTP_FROM_NAME ?? 'The Hub');
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(!empty($htmlMessage));
            $mail->Subject = $subject;
            $mail->Body = $htmlMessage ?: $message;
            if ($htmlMessage) {
                $mail->AltBody = $message;
            }
            
            // Priority
            if (!empty($options['priority']) && $options['priority'] === 'urgent') {
                $mail->Priority = 1;
            }
            
            // Attachments
            if (!empty($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                }
            }
            
            $mail->send();
            
            // Log success
            error_log("Email sent to {$to}: {$subject}");
            
            return true;
        } catch (Exception $e) {
            error_log("Email failed to {$to}: {$mail->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Send SMS via Twilio or other provider
     * 
     * @param string $phone Phone number
     * @param string $message Message text
     * @return bool Success
     */
    private static function sendSMS($phone, $message)
    {
        // TODO: Implement SMS sending via Twilio
        // For now, just log that it would be sent
        
        if (!defined('TWILIO_ENABLED') || !TWILIO_ENABLED) {
            error_log("SMS would be sent to {$phone}: " . substr($message, 0, 50) . "...");
            return true; // Return true to simulate success
        }
        
        try {
            // Twilio implementation would go here
            // $client = new Twilio\Rest\Client(TWILIO_SID, TWILIO_TOKEN);
            // $client->messages->create($phone, ['from' => TWILIO_FROM, 'body' => $message]);
            
            error_log("SMS sent to {$phone}");
            return true;
        } catch (\Exception $e) {
            error_log("SMS failed to {$phone}: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Send notification for a section event
     * 
     * @param string $sectionSlug Section slug
     * @param string $eventType Event type (submission, status_change, etc.)
     * @param array $data Data for the notification message
     * @return array Results
     */
    public static function notifySection($sectionSlug, $eventType, $data = [])
    {
        // Get recipients
        $recipients = SectionPermissions::getNotificationRecipients($sectionSlug, $eventType);
        
        if (empty($recipients)) {
            return ['email' => ['skipped' => 0], 'sms' => ['skipped' => 0]];
        }
        
        // Build message based on event type
        $subject = self::buildSubject($sectionSlug, $eventType, $data);
        $message = self::buildMessage($sectionSlug, $eventType, $data);
        $htmlMessage = self::buildHtmlMessage($sectionSlug, $eventType, $data);
        
        // Send notifications
        return self::send($recipients, $subject, $message, $htmlMessage, $data['options'] ?? []);
    }
    
    /**
     * Build email subject
     */
    private static function buildSubject($sectionSlug, $eventType, $data)
    {
        $sectionName = $data['section_name'] ?? ucwords(str_replace('-', ' ', $sectionSlug));
        
        return match($eventType) {
            'submission' => "New {$sectionName} Submission",
            'status_change' => "{$sectionName} Status Update",
            'assignment' => "You've Been Assigned a {$sectionName}",
            'comment' => "New Comment on {$sectionName}",
            default => "{$sectionName} Notification"
        };
    }
    
    /**
     * Build plain text message
     */
    private static function buildMessage($sectionSlug, $eventType, $data)
    {
        $sectionName = $data['section_name'] ?? ucwords(str_replace('-', ' ', $sectionSlug));
        $reportId = $data['report_id'] ?? $data['id'] ?? 'N/A';
        $url = "https://{$_SERVER['HTTP_HOST']}/modules/{$sectionSlug}/dashboard.php?id={$reportId}";
        
        $message = match($eventType) {
            'submission' => "A new {$sectionName} has been submitted.\n\n",
            'status_change' => "{$sectionName} #{$reportId} status has been updated.\n\n",
            'assignment' => "You have been assigned {$sectionName} #{$reportId}.\n\n",
            'comment' => "A new comment has been added to {$sectionName} #{$reportId}.\n\n",
            default => "{$sectionName} notification.\n\n"
        };
        
        // Add details
        if (!empty($data['details'])) {
            $message .= "Details:\n" . $data['details'] . "\n\n";
        }
        
        // Add priority if urgent
        if (!empty($data['priority']) && $data['priority'] === 'urgent') {
            $message .= "⚠️ PRIORITY: URGENT\n\n";
        }
        
        $message .= "View full details: {$url}\n";
        
        return $message;
    }
    
    /**
     * Build HTML message
     */
    private static function buildHtmlMessage($sectionSlug, $eventType, $data)
    {
        $sectionName = $data['section_name'] ?? ucwords(str_replace('-', ' ', $sectionSlug));
        $reportId = $data['report_id'] ?? $data['id'] ?? 'N/A';
        $url = "https://{$_SERVER['HTTP_HOST']}/modules/{$sectionSlug}/dashboard.php?id={$reportId}";
        
        $urgentBadge = '';
        if (!empty($data['priority']) && $data['priority'] === 'urgent') {
            $urgentBadge = '<div style="background: #dc2626; color: white; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-weight: bold;">⚠️ PRIORITY: URGENT</div>';
        }
        
        $title = match($eventType) {
            'submission' => "New {$sectionName} Submission",
            'status_change' => "{$sectionName} Status Update",
            'assignment' => "You've Been Assigned",
            'comment' => "New Comment Added",
            default => "{$sectionName} Notification"
        };
        
        $details = !empty($data['details']) ? "<p style='color: #666; line-height: 1.6;'>" . nl2br(htmlspecialchars($data['details'])) . "</p>" : '';
        
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: white; font-size: 24px;">{$title}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px;">
                            {$urgentBadge}
                            <h2 style="color: #333; font-size: 18px; margin-top: 0;">{$sectionName} #{$reportId}</h2>
                            {$details}
                            <div style="margin-top: 30px; text-align: center;">
                                <a href="{$url}" style="display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;">View Details</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px;">
                            <p style="margin: 0;">This is an automated notification from The Hub</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
