<?php

namespace Hub\Modules;

use Hub\Database;
use Hub\Auth;
use Hub\AuditLogger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

require_once __DIR__ . '/../bootstrap.php';

/**
 * EmailNotificationRenderer - Send email notifications
 * 
 * Implements EmailNotification module type from MODULE_CATALOG_V2.md
 * 
 * Use cases:
 * - Workflow state change notifications
 * - Form submission confirmations
 * - Employee evaluation delivery
 * - Approval request notifications
 * - System alerts and reminders
 * 
 * Features:
 * - HTML and plain text templates
 * - Variable substitution {firstName}, {recordId}, etc.
 * - Multiple recipients (to, cc, bcc)
 * - File attachments
 * - Reply-to configuration
 * - Template inheritance
 * - Error handling and logging
 * - Multi-tenant support
 * 
 * @author The Hub Team
 * @version 1.0.0
 */
class EmailNotificationRenderer implements ModuleInterface
{
    private array $config;
    private Database $db;
    private ?array $data = null;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db = Database::getInstance();
    }
    
    /**
     * Render email configuration/status
     * 
     * EmailNotification modules don't have a visual UI,
     * but we can show configuration for debugging.
     * 
     * @return string HTML output
     */
    public function render(): string
    {
        if (!$this->validate()) {
            return '<div class="alert alert-danger">Invalid email notification configuration</div>';
        }
        
        $html = '<div class="email-notification-info">';
        $html .= '<div class="alert alert-info">';
        $html .= '<h5><i class="bi bi-envelope me-2"></i>Email Notification Module</h5>';
        $html .= '<p class="mb-0">This module sends email notifications programmatically. ';
        $html .= 'It does not have a user interface and is triggered by other modules.</p>';
        $html .= '</div>';
        
        // Show configuration (for admins)
        if (Auth::hasRole('admin') || Auth::hasRole('super_admin')) {
            $html .= '<div class="card">';
            $html .= '<div class="card-header"><h6 class="mb-0">Configuration</h6></div>';
            $html .= '<div class="card-body">';
            $html .= '<dl class="row mb-0">';
            
            $html .= '<dt class="col-sm-3">Subject</dt>';
            $html .= '<dd class="col-sm-9">' . htmlspecialchars($this->config['subject'] ?? '') . '</dd>';
            
            $html .= '<dt class="col-sm-3">From Address</dt>';
            $html .= '<dd class="col-sm-9">' . htmlspecialchars($this->config['from'] ?? $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com') . '</dd>';
            
            $html .= '<dt class="col-sm-3">Template</dt>';
            $html .= '<dd class="col-sm-9">' . htmlspecialchars($this->config['template'] ?? 'inline') . '</dd>';
            
            $html .= '</dl>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Handle email sending
     * 
     * @param array $data Data for template substitution
     * @return array Result
     */
    public function handle(array $data): array
    {
        if (!$this->validate()) {
            return [
                'success' => false,
                'error' => 'Invalid email configuration'
            ];
        }
        
        $this->data = $data;
        
        try {
            // Determine recipients
            $recipients = $this->getRecipients($data);
            
            if (empty($recipients)) {
                return [
                    'success' => false,
                    'error' => 'No recipients specified'
                ];
            }
            
            // Send email to each recipient
            $results = [];
            foreach ($recipients as $recipient) {
                $result = $this->sendEmail($recipient, $data);
                $results[] = $result;
            }
            
            // Check if any failed
            $failedCount = count(array_filter($results, fn($r) => !$r['success']));
            
            if ($failedCount > 0) {
                return [
                    'success' => false,
                    'error' => "{$failedCount} email(s) failed to send",
                    'details' => $results
                ];
            }
            
            return [
                'success' => true,
                'message' => count($results) . ' email(s) sent successfully',
                'details' => $results
            ];
            
        } catch (\Exception $e) {
            error_log("Email notification error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to send email notification'
            ];
        }
    }
    
    /**
     * Get email recipients from configuration
     * 
     * @param array $data Context data
     * @return array Email addresses
     */
    private function getRecipients(array $data): array
    {
        $recipients = [];
        $to = $this->config['to'] ?? [];
        
        // Handle string (single recipient)
        if (is_string($to)) {
            $to = [$to];
        }
        
        foreach ($to as $recipient) {
            // Direct email address
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $recipient;
                continue;
            }
            
            // Role-based (e.g., "manager", "hr")
            if (is_string($recipient)) {
                $roleEmails = $this->getEmailsByRole($recipient);
                $recipients = array_merge($recipients, $roleEmails);
                continue;
            }
            
            // Complex recipient ({"field": "email_field"} or {"user": "user_id"})
            if (is_array($recipient)) {
                if (isset($recipient['field'])) {
                    // Get email from data field
                    $email = $data[$recipient['field']] ?? null;
                    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $recipients[] = $email;
                    }
                } elseif (isset($recipient['user'])) {
                    // Get user email by ID
                    $email = $this->getUserEmail($recipient['user']);
                    if ($email) {
                        $recipients[] = $email;
                    }
                } elseif (isset($recipient['owner'])) {
                    // Get record owner email
                    $ownerEmail = $this->getOwnerEmail($data);
                    if ($ownerEmail) {
                        $recipients[] = $ownerEmail;
                    }
                }
            }
        }
        
        return array_unique(array_filter($recipients));
    }
    
    /**
     * Get emails for users with specific role
     * 
     * @param string $role Role name
     * @return array Email addresses
     */
    private function getEmailsByRole(string $role): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT u.email
            FROM users u
            LEFT JOIN user_global_roles ugr ON u.id = ugr.user_id
            WHERE (u.role = ? OR ugr.role = ?)
            AND u.is_active = 1
            AND u.tenant_id = ?
        ");
        
        $stmt->execute([$role, $role, $_SESSION['tenant_id'] ?? 'default']);
        $results = $stmt->fetchAll();
        
        return array_column($results, 'email');
    }
    
    /**
     * Get user email by ID
     * 
     * @param string $userId User ID
     * @return string|null Email address
     */
    private function getUserEmail(string $userId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT email
            FROM users
            WHERE id = ?
            AND is_active = 1
            AND tenant_id = ?
        ");
        
        $stmt->execute([$userId, $_SESSION['tenant_id'] ?? 'default']);
        $result = $stmt->fetch();
        
        return $result['email'] ?? null;
    }
    
    /**
     * Get record owner email from data
     * 
     * @param array $data Context data
     * @return string|null Email address
     */
    private function getOwnerEmail(array $data): ?string
    {
        $ownerId = $data['created_by'] ?? $data['owner_id'] ?? null;
        
        if (!$ownerId) {
            return null;
        }
        
        return $this->getUserEmail($ownerId);
    }
    
    /**
     * Send email to single recipient
     * 
     * @param string $to Recipient email
     * @param array $data Template data
     * @return array Result
     */
    private function sendEmail(string $to, array $data): array
    {
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USERNAME'] ?? '';
            $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
            $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $_ENV['MAIL_PORT'] ?? 587;
            
            // Sender
            $fromAddress = $this->config['from'] ?? $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com';
            $fromName = $this->config['fromName'] ?? $_ENV['MAIL_FROM_NAME'] ?? 'The Hub';
            $mail->setFrom($fromAddress, $fromName);
            
            // Reply-to
            if (!empty($this->config['replyTo'])) {
                $replyTo = $this->substituteVariables($this->config['replyTo'], $data);
                $mail->addReplyTo($replyTo);
            }
            
            // Recipient
            $mail->addAddress($to);
            
            // CC
            if (!empty($this->config['cc'])) {
                $ccRecipients = is_array($this->config['cc']) ? $this->config['cc'] : [$this->config['cc']];
                foreach ($ccRecipients as $cc) {
                    if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                        $mail->addCC($cc);
                    }
                }
            }
            
            // BCC
            if (!empty($this->config['bcc'])) {
                $bccRecipients = is_array($this->config['bcc']) ? $this->config['bcc'] : [$this->config['bcc']];
                foreach ($bccRecipients as $bcc) {
                    if (filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
                        $mail->addBCC($bcc);
                    }
                }
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $this->substituteVariables($this->config['subject'] ?? 'Notification', $data);
            
            // Body
            $htmlBody = $this->renderTemplate($data);
            $mail->Body = $htmlBody;
            
            // Plain text alternative
            if (!empty($this->config['plainText'])) {
                $plainBody = $this->substituteVariables($this->config['plainText'], $data);
                $mail->AltBody = $plainBody;
            } else {
                $mail->AltBody = strip_tags($htmlBody);
            }
            
            // Attachments
            if (!empty($this->config['attachments'])) {
                foreach ($this->config['attachments'] as $attachment) {
                    $filePath = $this->resolveAttachmentPath($attachment, $data);
                    if ($filePath && file_exists($filePath)) {
                        $filename = $attachment['filename'] ?? basename($filePath);
                        $mail->addAttachment($filePath, $filename);
                    }
                }
            }
            
            // Send
            $mail->send();
            
            // Log success
            AuditLogger::log('email_sent', 'email_notification', $this->config['id'] ?? 'unknown', [
                'to' => $to,
                'subject' => $mail->Subject
            ]);
            
            return [
                'success' => true,
                'recipient' => $to
            ];
            
        } catch (PHPMailerException $e) {
            error_log("PHPMailer error: " . $e->getMessage());
            
            // Log failure
            AuditLogger::log('email_failed', 'email_notification', $this->config['id'] ?? 'unknown', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'recipient' => $to,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Render email template with data
     * 
     * @param array $data Template data
     * @return string HTML body
     */
    private function renderTemplate(array $data): string
    {
        $template = $this->config['template'] ?? null;
        
        // Inline HTML template
        if (!empty($this->config['html'])) {
            return $this->substituteVariables($this->config['html'], $data);
        }
        
        // External template file
        if ($template && file_exists($template)) {
            $templateContent = file_get_contents($template);
            return $this->substituteVariables($templateContent, $data);
        }
        
        // Default template
        return $this->renderDefaultTemplate($data);
    }
    
    /**
     * Render default email template
     * 
     * @param array $data Template data
     * @return string HTML
     */
    private function renderDefaultTemplate(array $data): string
    {
        $appName = $_ENV['APP_NAME'] ?? 'The Hub';
        $message = $this->config['message'] ?? '';
        $message = $this->substituteVariables($message, $data);
        
        $html = '<!DOCTYPE html>';
        $html .= '<html>';
        $html .= '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>';
        $html .= '<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">';
        
        // Header
        $html .= '<div style="background: #0d6efd; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0;">';
        $html .= '<h1 style="margin: 0;">' . htmlspecialchars($appName) . '</h1>';
        $html .= '</div>';
        
        // Content
        $html .= '<div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 5px 5px;">';
        $html .= '<div style="background: white; padding: 20px; border-radius: 5px;">';
        $html .= nl2br(htmlspecialchars($message));
        $html .= '</div>';
        $html .= '</div>';
        
        // Footer
        $html .= '<div style="text-align: center; margin-top: 20px; font-size: 12px; color: #666;">';
        $html .= '<p>This is an automated message from ' . htmlspecialchars($appName) . '.</p>';
        $html .= '</div>';
        
        $html .= '</body>';
        $html .= '</html>';
        
        return $html;
    }
    
    /**
     * Substitute variables in template
     * 
     * Replaces {variableName} with actual values from data
     * 
     * @param string $template Template string
     * @param array $data Data for substitution
     * @return string Processed template
     */
    private function substituteVariables(string $template, array $data): string
    {
        // Add common variables
        $data['appName'] = $_ENV['APP_NAME'] ?? 'The Hub';
        $data['appUrl'] = $_ENV['APP_URL'] ?? 'https://hub.example.com';
        $data['currentDate'] = date('F j, Y');
        $data['currentTime'] = date('g:i A');
        
        // Replace {variable} patterns
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $template = str_replace('{' . $key . '}', (string)$value, $template);
            }
        }
        
        return $template;
    }
    
    /**
     * Resolve attachment file path
     * 
     * @param array $attachment Attachment config
     * @param array $data Context data
     * @return string|null File path
     */
    private function resolveAttachmentPath(array $attachment, array $data): ?string
    {
        // Direct path
        if (!empty($attachment['path'])) {
            return $this->substituteVariables($attachment['path'], $data);
        }
        
        // Field reference
        if (!empty($attachment['field'])) {
            return $data[$attachment['field']] ?? null;
        }
        
        return null;
    }
    
    /**
     * Static helper: Send email from other modules
     * 
     * @param array $config Email configuration
     * @param array $data Template data
     * @return array Result
     */
    public static function send(array $config, array $data = []): array
    {
        $renderer = new self($config);
        return $renderer->handle($data);
    }
    
    /**
     * Get module configuration
     * 
     * @return array Configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
    
    /**
     * Validate module configuration
     * 
     * @return bool True if valid
     */
    public function validate(): bool
    {
        // Must have subject
        if (empty($this->config['subject'])) {
            return false;
        }
        
        // Must have recipients
        if (empty($this->config['to'])) {
            return false;
        }
        
        // Must have content (html, message, or template)
        if (empty($this->config['html']) && empty($this->config['message']) && empty($this->config['template'])) {
            return false;
        }
        
        return true;
    }
}
