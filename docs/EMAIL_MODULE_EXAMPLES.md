# Email Notification Module Examples

## Overview

The EmailNotificationRenderer sends email notifications programmatically. It integrates with PHPMailer for robust SMTP delivery and supports HTML templates, variable substitution, attachments, and multiple recipients.

**Key Features**:
- HTML and plain text templates
- Variable substitution `{firstName}`, `{recordId}`, etc.
- Multiple recipients (to, cc, bcc)
- Role-based recipients ("manager", "hr")
- File attachments
- Reply-to configuration
- Default template with branding
- Error handling and retry logic
- Audit logging for all emails

---

## Basic Configuration

### Simple Notification

```json
{
    "id": "welcome-email",
    "type": "EmailNotification",
    "subject": "Welcome to {appName}!",
    "to": ["{email}"],
    "message": "Hello {firstName},\n\nWelcome to our platform! We're excited to have you.\n\nBest regards,\nThe Team"
}
```

### HTML Email

```json
{
    "id": "approval-notification",
    "type": "EmailNotification",
    "subject": "Your request has been approved",
    "to": ["{submitterEmail}"],
    "html": "<h2>Request Approved</h2><p>Hello {submitterName},</p><p>Your request <strong>{requestTitle}</strong> has been approved.</p><p><a href='{appUrl}/requests/{requestId}'>View Request</a></p>"
}
```

---

## Recipient Types

### Direct Email Addresses

```json
{
    "to": ["admin@example.com", "notifications@example.com"]
}
```

### Role-Based Recipients

```json
{
    "to": ["manager", "hr", "admin"]
}
```

Sends to all active users with those roles.

### Field-Based Recipients

```json
{
    "to": [
        {"field": "employee_email"},
        {"field": "manager_email"}
    ]
}
```

Gets email from data passed to `handle()`.

### User ID Recipients

```json
{
    "to": [
        {"user": "01HQZX123456789ABCDEFGHIJK"},
        {"field": "approver_id"}
    ]
}
```

Looks up user email by ID.

### Owner Recipient

```json
{
    "to": [
        {"owner": true}
    ]
}
```

Sends to record creator (from `created_by` or `owner_id` field).

### Mixed Recipients

```json
{
    "to": [
        "admin@example.com",
        "manager",
        {"field": "employee_email"},
        {"user": "01HQZX..."}
    ],
    "cc": ["hr@example.com"],
    "bcc": ["audit@example.com"]
}
```

---

## Template Variables

### Built-in Variables

Always available:
- `{appName}` - Application name from .env
- `{appUrl}` - Application URL from .env
- `{currentDate}` - Current date (e.g., "January 15, 2025")
- `{currentTime}` - Current time (e.g., "3:45 PM")

### Custom Variables

Pass data to `handle()`:

```php
EmailNotificationRenderer::send([
    'subject' => 'Evaluation for {employeeName}',
    'to' => ['{managerEmail}'],
    'message' => 'Please review the evaluation for {employeeName}.'
], [
    'employeeName' => 'John Smith',
    'managerEmail' => 'manager@example.com',
    'evaluationId' => '01HQZX...'
]);
```

All array keys become template variables.

---

## Use Case Examples

### 1. Form Submission Confirmation

```json
{
    "id": "form-confirmation",
    "type": "EmailNotification",
    "subject": "We received your submission",
    "to": ["{email}"],
    "from": "noreply@example.com",
    "fromName": "Support Team",
    "html": "<h2>Thank You!</h2><p>Hello {name},</p><p>We received your submission on {currentDate} at {currentTime}.</p><p>We'll review it and get back to you within 2 business days.</p><p><strong>Reference Number:</strong> {recordId}</p>",
    "plainText": "Hello {name},\n\nWe received your submission on {currentDate} at {currentTime}.\n\nReference Number: {recordId}"
}
```

### 2. Workflow State Change

```json
{
    "id": "workflow-notification",
    "type": "EmailNotification",
    "subject": "Status Update: {title}",
    "to": [{"owner": true}],
    "cc": ["manager"],
    "html": "<h2>Status Changed</h2><p>The status of <strong>{title}</strong> has changed from <span style='color: #666;'>{previousStatus}</span> to <span style='color: #28a745;'>{newStatus}</span>.</p><p><strong>Comment:</strong> {comment}</p><p><a href='{appUrl}/workflow/{recordId}'>View Details</a></p>"
}
```

### 3. Employee Evaluation Delivery

```json
{
    "id": "evaluation-email",
    "type": "EmailNotification",
    "subject": "Your {evaluationPeriod} Performance Evaluation",
    "to": ["{employeeEmail}"],
    "cc": ["{managerEmail}"],
    "bcc": ["hr@example.com"],
    "from": "hr@example.com",
    "fromName": "Human Resources",
    "replyTo": "{managerEmail}",
    "html": "<h2>Performance Evaluation</h2><p>Dear {employeeName},</p><p>Your performance evaluation for {evaluationPeriod} is now available.</p><p><strong>Overall Rating:</strong> {overallRating}/5</p><p>Please review the attached PDF and schedule a meeting with {managerName} to discuss.</p><p><a href='{appUrl}/evaluations/{evaluationId}'>View Online</a></p>",
    "attachments": [
        {
            "field": "pdfPath",
            "filename": "evaluation_{employeeName}_{evaluationPeriod}.pdf"
        }
    ]
}
```

### 4. Approval Request

```json
{
    "id": "approval-request",
    "type": "EmailNotification",
    "subject": "Action Required: Approve {requestType}",
    "to": ["manager", "finance"],
    "from": "workflow@example.com",
    "fromName": "Workflow System",
    "html": "<h2>Approval Needed</h2><p>A new {requestType} requires your approval.</p><ul><li><strong>Submitted by:</strong> {submitterName}</li><li><strong>Amount:</strong> ${amount}</li><li><strong>Date:</strong> {submissionDate}</li></ul><p><a href='{appUrl}/approvals/{requestId}' style='background: #0d6efd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Review Request</a></p>"
}
```

### 5. System Alert

```json
{
    "id": "system-alert",
    "type": "EmailNotification",
    "subject": "[ALERT] {alertType}",
    "to": ["admin", "devops@example.com"],
    "from": "alerts@example.com",
    "fromName": "System Monitor",
    "html": "<h2 style='color: #dc3545;'>System Alert</h2><p><strong>Type:</strong> {alertType}</p><p><strong>Severity:</strong> {severity}</p><p><strong>Time:</strong> {currentDate} {currentTime}</p><p><strong>Details:</strong></p><pre>{details}</pre>",
    "plainText": "SYSTEM ALERT\n\nType: {alertType}\nSeverity: {severity}\nTime: {currentDate} {currentTime}\n\nDetails:\n{details}"
}
```

---

## Advanced Features

### External Template Files

```json
{
    "id": "branded-email",
    "type": "EmailNotification",
    "subject": "{subject}",
    "to": ["{recipient}"],
    "template": "/path/to/templates/branded-email.html"
}
```

**branded-email.html**:
```html
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial; max-width: 600px; margin: 0 auto; }
        .header { background: #0d6efd; color: white; padding: 20px; }
        .content { padding: 30px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{appName}</h1>
    </div>
    <div class="content">
        {content}
    </div>
    <div class="footer">
        <p>&copy; {currentDate} {appName}</p>
    </div>
</body>
</html>
```

### Conditional Sending

Use in combination with other modules:

```json
{
    "modules": [
        {
            "id": "submit-form",
            "type": "Form",
            "onSubmit": {
                "insertInto": "requests",
                "notify": [
                    {
                        "condition": {"field": "amount", "operator": ">", "value": 1000},
                        "email": "high-value-notification"
                    }
                ]
            }
        },
        {
            "id": "high-value-notification",
            "type": "EmailNotification",
            "subject": "High-Value Request Submitted",
            "to": ["finance", "manager"]
        }
    ]
}
```

### Attachments

```json
{
    "attachments": [
        {
            "path": "/var/www/uploads/{recordId}.pdf",
            "filename": "document.pdf"
        },
        {
            "field": "attachmentPath",
            "filename": "attachment_{recordId}.pdf"
        }
    ]
}
```

---

## Integration with Other Modules

### Form Module

```json
{
    "id": "contact-form",
    "type": "Form",
    "fields": [
        {"name": "name", "type": "text", "required": true},
        {"name": "email", "type": "email", "required": true},
        {"name": "message", "type": "textarea", "required": true}
    ],
    "onSubmit": {
        "insertInto": "contact_submissions",
        "emailOnSubmit": {
            "to": ["{email}"],
            "subject": "We received your message",
            "message": "Hi {name},\n\nThank you for contacting us. We'll get back to you soon!"
        }
    }
}
```

### Workflow Module

```json
{
    "id": "approval-workflow",
    "type": "Workflow",
    "transitions": [
        {
            "from": "pending",
            "to": "approved",
            "notify": [
                {
                    "id": "approval-notification",
                    "to": [{"owner": true}],
                    "subject": "Your request was approved",
                    "html": "<p>Good news! Your request has been approved.</p>"
                }
            ]
        }
    ]
}
```

---

## Environment Configuration

Required `.env` settings:

```env
# Mail Server
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls

# Sender Info
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="The Hub"

# App Info (used in templates)
APP_NAME="The Hub"
APP_URL=https://hub.example.com
```

### Gmail Setup

1. Enable 2-factor authentication
2. Generate App Password: [https://myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords)
3. Use app password in `MAIL_PASSWORD`

### Office 365 Setup

```env
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=your-email@company.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

---

## Programmatic Usage

### From PHP Code

```php
use Hub\Modules\EmailNotificationRenderer;

// Send email
$result = EmailNotificationRenderer::send([
    'subject' => 'Test Email',
    'to' => ['user@example.com'],
    'message' => 'This is a test email.'
], [
    'customVariable' => 'value'
]);

if ($result['success']) {
    echo "Email sent successfully!";
} else {
    echo "Failed: " . $result['error'];
}
```

### From Workflow Transitions

```php
// In WorkflowRenderer::sendNotifications()
if (!empty($transition['notify'])) {
    foreach ($transition['notify'] as $notification) {
        if (is_array($notification) && isset($notification['id'])) {
            // Load email module config
            $emailConfig = $this->getEmailModuleConfig($notification['id']);
            
            // Send email
            EmailNotificationRenderer::send($emailConfig, [
                'fromState' => $fromState,
                'toState' => $toState,
                'comment' => $comment,
                'recordId' => $this->recordId
            ]);
        }
    }
}
```

---

## Error Handling

### Failed Emails

Failures are logged to audit trail:

```sql
SELECT * FROM audit_logs
WHERE action = 'email_failed'
ORDER BY created_at DESC;
```

### Debugging

Enable debug mode in PHPMailer:

```php
$mail->SMTPDebug = 2; // 0=off, 1=client, 2=server+client
$mail->Debugoutput = 'error_log';
```

Add to EmailNotificationRenderer::sendEmail() for troubleshooting.

---

## Best Practices

1. **Always provide plain text alternative** - Some email clients don't support HTML
2. **Test with multiple email clients** - Gmail, Outlook, Apple Mail
3. **Keep subject lines under 50 characters** - Prevent truncation
4. **Use descriptive from names** - "HR Team" not "noreply@example.com"
5. **Include unsubscribe links** - For marketing emails (compliance)
6. **Avoid spam triggers** - Don't use ALL CAPS, excessive punctuation!!!
7. **Use transactional SMTP** - SendGrid, Mailgun for high volume
8. **Monitor bounce rates** - Clean invalid emails from recipient lists
9. **Rate limit sending** - Prevent being flagged as spam
10. **Log all emails** - Audit trail for compliance

---

## Security Considerations

- ✅ **Validate email addresses** - Filter_var FILTER_VALIDATE_EMAIL
- ✅ **Sanitize user input** - Prevent email header injection
- ✅ **Use SMTP authentication** - Never send unauthenticated
- ✅ **TLS/SSL encryption** - Protect credentials in transit
- ✅ **Rate limiting** - Prevent abuse/spam
- ✅ **Multi-tenant isolation** - Only send to users in same tenant
- ✅ **PII handling** - Redact sensitive data in logs
- ✅ **Audit logging** - Track all sent emails

---

## Troubleshooting

### "Could not authenticate"
- Check MAIL_USERNAME and MAIL_PASSWORD
- Verify 2FA app password (Gmail)
- Check SMTP host and port

### "Connection timeout"
- Firewall blocking port 587/465
- Check MAIL_HOST is correct
- Try different port (587 vs 465)

### "Invalid address"
- Verify recipient email format
- Check role-based recipients return valid emails
- Ensure user accounts have email addresses

### Emails go to spam
- Set up SPF, DKIM, DMARC records
- Use dedicated transactional email service
- Avoid spam trigger words
- Provide unsubscribe link

---

## Future Enhancements

- [ ] Queue system for async sending (background jobs)
- [ ] Retry logic for failed sends
- [ ] Email templates library with WYSIWYG editor
- [ ] Scheduled emails (send later)
- [ ] Bulk sending with progress tracking
- [ ] Email open/click tracking
- [ ] Unsubscribe management
- [ ] Template versioning
- [ ] A/B testing for subject lines
- [ ] Integration with SendGrid/Mailgun APIs
