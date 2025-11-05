<?php

namespace Hub\Tests\Unit;

use Hub\NotificationService;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        // Define constants if not already defined
        if (!defined('SMTP_HOST')) {
            define('SMTP_HOST', 'smtp.test.local');
        }
        if (!defined('SMTP_USERNAME')) {
            define('SMTP_USERNAME', 'test@test.local');
        }
        if (!defined('SMTP_PASSWORD')) {
            define('SMTP_PASSWORD', 'testpass');
        }
        if (!defined('SMTP_ENCRYPTION')) {
            define('SMTP_ENCRYPTION', 'tls');
        }
        if (!defined('SMTP_PORT')) {
            define('SMTP_PORT', 587);
        }
        if (!defined('SMTP_FROM_EMAIL')) {
            define('SMTP_FROM_EMAIL', 'noreply@test.local');
        }
        if (!defined('SMTP_FROM_NAME')) {
            define('SMTP_FROM_NAME', 'Test Hub');
        }
        if (!defined('TWILIO_ENABLED')) {
            define('TWILIO_ENABLED', false);
        }
    }

    /**
     * Test send with email-only recipient
     */
    public function testSendWithEmailOnlyRecipient(): void
    {
        $recipients = [
            [
                'email' => 'test@example.com',
                'email_enabled' => true,
                'sms_enabled' => false,
                'preferred_contact_method' => 'email'
            ]
        ];

        $results = NotificationService::send(
            $recipients,
            'Test Subject',
            'Test message'
        );

        $this->assertIsArray($results);
        $this->assertArrayHasKey('email', $results);
        $this->assertArrayHasKey('sms', $results);
        $this->assertEquals(1, $results['email']['failed']); // Will fail with test SMTP
        $this->assertEquals(1, $results['sms']['skipped']);
    }

    /**
     * Test send with SMS-enabled recipient
     */
    public function testSendWithSMSEnabledRecipient(): void
    {
        $recipients = [
            [
                'email' => 'test@example.com',
                'email_enabled' => false,
                'sms_enabled' => true,
                'phone' => '+1234567890',
                'preferred_contact_method' => 'sms_only'
            ]
        ];

        $results = NotificationService::send(
            $recipients,
            'Test Subject',
            'Test SMS message'
        );

        $this->assertEquals(1, $results['email']['skipped']);
        $this->assertEquals(1, $results['sms']['sent']); // Simulated success
    }

    /**
     * Test send with both email and SMS
     */
    public function testSendWithBothEmailAndSMS(): void
    {
        $recipients = [
            [
                'email' => 'test@example.com',
                'email_enabled' => true,
                'sms_enabled' => true,
                'phone' => '+1234567890',
                'preferred_contact_method' => 'both'
            ]
        ];

        $results = NotificationService::send(
            $recipients,
            'Test Subject',
            'Test message'
        );

        $this->assertEquals(1, $results['email']['failed']); // Test SMTP fails
        $this->assertEquals(1, $results['sms']['sent']); // SMS simulated
    }

    /**
     * Test send with alt_email preference
     */
    public function testSendWithAltEmailPreference(): void
    {
        $recipients = [
            [
                'email' => 'primary@example.com',
                'alt_email' => 'alternate@example.com',
                'email_enabled' => true,
                'sms_enabled' => false,
                'preferred_contact_method' => 'alt_email'
            ]
        ];

        $results = NotificationService::send(
            $recipients,
            'Test Subject',
            'Test message'
        );

        // Should attempt to send to alt_email
        $this->assertArrayHasKey('email', $results);
    }

    /**
     * Test send with multiple recipients
     */
    public function testSendWithMultipleRecipients(): void
    {
        $recipients = [
            [
                'email' => 'user1@example.com',
                'email_enabled' => true,
                'sms_enabled' => false,
                'preferred_contact_method' => 'email'
            ],
            [
                'email' => 'user2@example.com',
                'email_enabled' => true,
                'sms_enabled' => false,
                'preferred_contact_method' => 'email'
            ],
            [
                'email' => 'user3@example.com',
                'email_enabled' => false,
                'sms_enabled' => true,
                'phone' => '+1234567890',
                'preferred_contact_method' => 'sms_only'
            ]
        ];

        $results = NotificationService::send(
            $recipients,
            'Test Subject',
            'Test message'
        );

        $this->assertEquals(2, $results['email']['failed']); // 2 email attempts
        $this->assertEquals(1, $results['email']['skipped']); // 1 SMS-only user
        $this->assertEquals(1, $results['sms']['sent']); // 1 SMS sent
        $this->assertEquals(2, $results['sms']['skipped']); // 2 email-only users
    }

    /**
     * Test send with HTML message
     */
    public function testSendWithHTMLMessage(): void
    {
        $recipients = [
            [
                'email' => 'test@example.com',
                'email_enabled' => true,
                'sms_enabled' => false,
                'preferred_contact_method' => 'email'
            ]
        ];

        $htmlMessage = '<html><body><h1>Test</h1></body></html>';

        $results = NotificationService::send(
            $recipients,
            'Test Subject',
            'Plain text',
            $htmlMessage
        );

        $this->assertIsArray($results);
    }

    /**
     * Test send with urgent priority
     */
    public function testSendWithUrgentPriority(): void
    {
        $recipients = [
            [
                'email' => 'urgent@example.com',
                'email_enabled' => true,
                'sms_enabled' => false,
                'preferred_contact_method' => 'email'
            ]
        ];

        $options = ['priority' => 'urgent'];

        $results = NotificationService::send(
            $recipients,
            'Urgent Test',
            'Urgent message',
            null,
            $options
        );

        $this->assertIsArray($results);
    }

    /**
     * Test send with attachments
     */
    public function testSendWithAttachments(): void
    {
        $recipients = [
            [
                'email' => 'test@example.com',
                'email_enabled' => true,
                'sms_enabled' => false,
                'preferred_contact_method' => 'email'
            ]
        ];

        $options = [
            'attachments' => [
                ['path' => '/tmp/test.txt', 'name' => 'test.txt']
            ]
        ];

        $results = NotificationService::send(
            $recipients,
            'Test with Attachment',
            'Message with attachment',
            null,
            $options
        );

        $this->assertIsArray($results);
    }

    /**
     * Test send with empty recipients
     */
    public function testSendWithEmptyRecipients(): void
    {
        $results = NotificationService::send(
            [],
            'Test Subject',
            'Test message'
        );

        $this->assertEquals(0, $results['email']['sent']);
        $this->assertEquals(0, $results['email']['failed']);
        $this->assertEquals(0, $results['sms']['sent']);
    }

    /**
     * Test send with SMS without phone number
     */
    public function testSendWithSMSWithoutPhoneNumber(): void
    {
        $recipients = [
            [
                'email' => 'test@example.com',
                'email_enabled' => false,
                'sms_enabled' => true,
                'phone' => '', // Empty phone
                'preferred_contact_method' => 'sms_only'
            ]
        ];

        $results = NotificationService::send(
            $recipients,
            'Test Subject',
            'Test message'
        );

        $this->assertEquals(1, $results['sms']['skipped']);
    }

    /**
     * Test send with default contact method
     */
    public function testSendWithDefaultContactMethod(): void
    {
        $recipients = [
            [
                'email' => 'test@example.com',
                'email_enabled' => true,
                'sms_enabled' => false
                // No preferred_contact_method set
            ]
        ];

        $results = NotificationService::send(
            $recipients,
            'Test Subject',
            'Test message'
        );

        // Should default to email
        $this->assertArrayHasKey('email', $results);
    }

    /**
     * Test send with email_only contact method
     */
    public function testSendWithEmailOnlyContactMethod(): void
    {
        $recipients = [
            [
                'email' => 'test@example.com',
                'email_enabled' => true,
                'sms_enabled' => true,
                'phone' => '+1234567890',
                'preferred_contact_method' => 'email_only'
            ]
        ];

        $results = NotificationService::send(
            $recipients,
            'Test Subject',
            'Test message'
        );

        $this->assertEquals(1, $results['sms']['skipped']); // SMS should be skipped
    }

    /**
     * Test buildSubject for submission event
     */
    public function testBuildSubjectForSubmission(): void
    {
        // Access private method via reflection
        $reflection = new \ReflectionClass(NotificationService::class);
        $method = $reflection->getMethod('buildSubject');
        $method->setAccessible(true);

        $subject = $method->invoke(null, 'test-section', 'submission', ['section_name' => 'Test Section']);

        $this->assertEquals('New Test Section Submission', $subject);
    }

    /**
     * Test buildSubject for status_change event
     */
    public function testBuildSubjectForStatusChange(): void
    {
        $reflection = new \ReflectionClass(NotificationService::class);
        $method = $reflection->getMethod('buildSubject');
        $method->setAccessible(true);

        $subject = $method->invoke(null, 'test-section', 'status_change', ['section_name' => 'Test Section']);

        $this->assertEquals('Test Section Status Update', $subject);
    }

    /**
     * Test buildSubject for assignment event
     */
    public function testBuildSubjectForAssignment(): void
    {
        $reflection = new \ReflectionClass(NotificationService::class);
        $method = $reflection->getMethod('buildSubject');
        $method->setAccessible(true);

        $subject = $method->invoke(null, 'test-section', 'assignment', ['section_name' => 'Test Section']);

        $this->assertEquals("You've Been Assigned a Test Section", $subject);
    }

    /**
     * Test buildSubject for comment event
     */
    public function testBuildSubjectForComment(): void
    {
        $reflection = new \ReflectionClass(NotificationService::class);
        $method = $reflection->getMethod('buildSubject');
        $method->setAccessible(true);

        $subject = $method->invoke(null, 'test-section', 'comment', ['section_name' => 'Test Section']);

        $this->assertEquals('New Comment on Test Section', $subject);
    }

    /**
     * Test buildSubject with default event type
     */
    public function testBuildSubjectWithDefaultEventType(): void
    {
        $reflection = new \ReflectionClass(NotificationService::class);
        $method = $reflection->getMethod('buildSubject');
        $method->setAccessible(true);

        $subject = $method->invoke(null, 'test-section', 'unknown', ['section_name' => 'Test Section']);

        $this->assertEquals('Test Section Notification', $subject);
    }

    /**
     * Test buildSubject derives section name from slug
     */
    public function testBuildSubjectDerivesNameFromSlug(): void
    {
        $reflection = new \ReflectionClass(NotificationService::class);
        $method = $reflection->getMethod('buildSubject');
        $method->setAccessible(true);

        $subject = $method->invoke(null, 'my-test-section', 'submission', []);

        $this->assertStringContainsString('My Test Section', $subject);
    }

    /**
     * Test buildMessage includes details
     */
    public function testBuildMessageIncludesDetails(): void
    {
        $_SERVER['HTTP_HOST'] = 'test.hub.local';

        $reflection = new \ReflectionClass(NotificationService::class);
        $method = $reflection->getMethod('buildMessage');
        $method->setAccessible(true);

        $data = [
            'section_name' => 'Test Section',
            'report_id' => '123',
            'details' => 'This is additional detail information'
        ];

        $message = $method->invoke(null, 'test-section', 'submission', $data);

        $this->assertStringContainsString('This is additional detail information', $message);
    }

    /**
     * Test buildMessage includes urgent priority
     */
    public function testBuildMessageIncludesUrgentPriority(): void
    {
        $_SERVER['HTTP_HOST'] = 'test.hub.local';

        $reflection = new \ReflectionClass(NotificationService::class);
        $method = $reflection->getMethod('buildMessage');
        $method->setAccessible(true);

        $data = [
            'section_name' => 'Test Section',
            'priority' => 'urgent'
        ];

        $message = $method->invoke(null, 'test-section', 'submission', $data);

        $this->assertStringContainsString('URGENT', $message);
    }

    /**
     * Test buildMessage includes URL
     */
    public function testBuildMessageIncludesURL(): void
    {
        $_SERVER['HTTP_HOST'] = 'test.hub.local';

        $reflection = new \ReflectionClass(NotificationService::class);
        $method = $reflection->getMethod('buildMessage');
        $method->setAccessible(true);

        $data = ['report_id' => '456'];

        $message = $method->invoke(null, 'test-section', 'submission', $data);

        $this->assertStringContainsString('https://test.hub.local', $message);
        $this->assertStringContainsString('id=456', $message);
    }

    /**
     * Test buildHtmlMessage returns HTML
     */
    public function testBuildHtmlMessageReturnsHTML(): void
    {
        $_SERVER['HTTP_HOST'] = 'test.hub.local';

        $reflection = new \ReflectionClass(NotificationService::class);
        $method = $reflection->getMethod('buildHtmlMessage');
        $method->setAccessible(true);

        $data = [
            'section_name' => 'Test Section',
            'report_id' => '789'
        ];

        $html = $method->invoke(null, 'test-section', 'submission', $data);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Test Section', $html);
        $this->assertStringContainsString('#789', $html);
    }

    /**
     * Test buildHtmlMessage includes urgent badge
     */
    public function testBuildHtmlMessageIncludesUrgentBadge(): void
    {
        $_SERVER['HTTP_HOST'] = 'test.hub.local';

        $reflection = new \ReflectionClass(NotificationService::class);
        $method = $reflection->getMethod('buildHtmlMessage');
        $method->setAccessible(true);

        $data = [
            'section_name' => 'Test Section',
            'priority' => 'urgent'
        ];

        $html = $method->invoke(null, 'test-section', 'submission', $data);

        $this->assertStringContainsString('URGENT', $html);
        $this->assertStringContainsString('#dc2626', $html); // Urgent color
    }

    /**
     * Test buildHtmlMessage escapes XSS
     */
    public function testBuildHtmlMessageEscapesXSS(): void
    {
        $_SERVER['HTTP_HOST'] = 'test.hub.local';

        $reflection = new \ReflectionClass(NotificationService::class);
        $method = $reflection->getMethod('buildHtmlMessage');
        $method->setAccessible(true);

        $data = [
            'section_name' => 'Test Section',
            'details' => '<script>alert("xss")</script>'
        ];

        $html = $method->invoke(null, 'test-section', 'submission', $data);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * Test results structure
     */
    public function testResultsStructure(): void
    {
        $recipients = [
            [
                'email' => 'test@example.com',
                'email_enabled' => true,
                'sms_enabled' => false,
                'preferred_contact_method' => 'email'
            ]
        ];

        $results = NotificationService::send($recipients, 'Test', 'Message');

        $this->assertArrayHasKey('email', $results);
        $this->assertArrayHasKey('sms', $results);
        $this->assertArrayHasKey('sent', $results['email']);
        $this->assertArrayHasKey('failed', $results['email']);
        $this->assertArrayHasKey('skipped', $results['email']);
        $this->assertArrayHasKey('sent', $results['sms']);
        $this->assertArrayHasKey('failed', $results['sms']);
        $this->assertArrayHasKey('skipped', $results['sms']);
    }
}
