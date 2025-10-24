<?php

namespace Hub;

class Invitation
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create($email, $role, $invitedBy)
    {
        // Verify domain if REQUIRE_DOMAIN_MATCH is enabled
        $requireDomain = filter_var($_ENV['REQUIRE_DOMAIN_MATCH'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        if ($requireDomain) {
            $allowedDomains = $_ENV['ALLOWED_DOMAINS'] ?? '';
            if (!empty($allowedDomains)) {
                $domains = array_map('trim', explode(',', $allowedDomains));
                $emailDomain = substr(strrchr($email, "@"), 1);
                
                if (!in_array($emailDomain, $domains)) {
                    $domainsStr = implode(', @', $domains);
                    throw new \Exception("Only @{$domainsStr} email addresses can be invited");
                }
            }
        }

        // Check if user already exists
        $existing = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            throw new \Exception('User already exists');
        }

        // Check if invitation already exists and hasn't expired
        $existingInvite = $this->db->fetchOne(
            "SELECT id FROM invitations WHERE email = ? AND expires_at > NOW() AND used_at IS NULL",
            [$email]
        );
        if ($existingInvite) {
            throw new \Exception('Active invitation already exists for this email');
        }

        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

        $this->db->execute(
            "INSERT INTO invitations (email, invited_by, role, token, expires_at) VALUES (?, ?, ?, ?, ?)",
            [$email, $invitedBy, $role, $token, $expiresAt]
        );

        $invitationId = $this->db->lastInsertId();

        // Send invitation email
        $this->sendInvitationEmail($email, $token);

        return $invitationId;
    }

    public function getByToken($token)
    {
        return $this->db->fetchOne(
            "SELECT * FROM invitations WHERE token = ? AND expires_at > NOW() AND used_at IS NULL",
            [$token]
        );
    }

    public function markUsed($invitationId)
    {
        $this->db->execute(
            "UPDATE invitations SET used_at = NOW() WHERE id = ?",
            [$invitationId]
        );
    }

    public function getAll()
    {
        return $this->db->fetchAll(
            "SELECT i.*, 
                    i.used_at as accepted_at,
                    u.name as invited_by_name, 
                    u.email as invited_by_email 
             FROM invitations i
             JOIN users u ON i.invited_by = u.id
             ORDER BY i.created_at DESC"
        );
    }

    private function sendInvitationEmail($email, $token)
    {
        $inviteUrl = $_ENV['APP_URL'] . '/accept-invite.php?token=' . $token;
        $orgName = SiteSettings::get('organization_name', 'Your Organization');
        $siteName = SiteSettings::get('site_name', 'The Hub');
        
        $subject = "Invitation to {$orgName}'s {$siteName}";
        
        // HTML version
        $htmlMessage = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: #000; color: #C99700; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                    <h1 style='margin: 0; font-size: 24px;'>{$orgName}</h1>
                    <p style='margin: 5px 0 0 0; font-size: 14px;'>{$siteName}</p>
                </div>
                <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px;'>
                    <h2 style='color: #000; margin-top: 0;'>You've Been Invited!</h2>
                    <p>You've been invited to join {$orgName}'s {$siteName}.</p>
                    <p>Click the button below to accept the invitation and sign in:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$inviteUrl}' style='background: #C99700; color: #000; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Accept Invitation</a>
                    </div>
                    <p style='font-size: 12px; color: #666;'>Or copy and paste this link into your browser:<br>
                    <a href='{$inviteUrl}' style='color: #C99700;'>{$inviteUrl}</a></p>
                    <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #666;'>
                        <strong>Note:</strong> This invitation expires in 7 days.<br>
                        If you did not expect this invitation, please ignore this email.
                    </p>
                </div>
            </div>
        </body>
        </html>";
        
        $mailer = new Email();
        $mailer->send($email, $subject, $htmlMessage, true);
    }

    public function sendApprovalEmail($email)
    {
        $loginUrl = $_ENV['APP_URL'];
        $orgName = SiteSettings::get('organization_name', 'Your Organization');
        $siteName = SiteSettings::get('site_name', 'The Hub');
        
        $subject = "Your {$orgName} {$siteName} Account Has Been Approved";
        
        // HTML version
        $htmlMessage = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: #000; color: #C99700; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                    <h1 style='margin: 0; font-size: 24px;'>{$orgName}</h1>
                    <p style='margin: 5px 0 0 0; font-size: 14px;'>{$siteName}</p>
                </div>
                <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px;'>
                    <h2 style='color: #28a745; margin-top: 0;'>✓ Account Approved!</h2>
                    <p>Your access to {$orgName}'s {$siteName} has been approved!</p>
                    <p>You can now log in and access the system.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$loginUrl}' style='background: #C99700; color: #000; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Log In Now</a>
                    </div>
                    <p style='font-size: 12px; color: #666;'>Sign in to get started.</p>
                </div>
            </div>
        </body>
        </html>";
        
        $mailer = new Email();
        $mailer->send($email, $subject, $htmlMessage, true);
    }
}
