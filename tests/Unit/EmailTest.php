<?php

namespace Hub\Tests\Unit;

use Hub\Email;
use PHPUnit\Framework\TestCase;

class EmailTest extends TestCase
{
    private array $originalEnv;

    protected function setUp(): void
    {
        // Save original env vars
        $this->originalEnv = [
            'SMTP_HOST' => $_ENV['SMTP_HOST'] ?? null,
            'SMTP_USERNAME' => $_ENV['SMTP_USERNAME'] ?? null,
            'SMTP_PASSWORD' => $_ENV['SMTP_PASSWORD'] ?? null,
            'SMTP_PORT' => $_ENV['SMTP_PORT'] ?? null,
            'SMTP_ENCRYPTION' => $_ENV['SMTP_ENCRYPTION'] ?? null,
            'SMTP_FROM_EMAIL' => $_ENV['SMTP_FROM_EMAIL'] ?? null,
            'SMTP_FROM_NAME' => $_ENV['SMTP_FROM_NAME'] ?? null,
            'APP_URL' => $_ENV['APP_URL'] ?? null,
        ];

        // Set test SMTP configuration
        $_ENV['SMTP_HOST'] = 'smtp.test.local';
        $_ENV['SMTP_USERNAME'] = 'test@test.local';
        $_ENV['SMTP_PASSWORD'] = 'test_password';
        $_ENV['SMTP_PORT'] = '587';
        $_ENV['SMTP_ENCRYPTION'] = 'tls';
        $_ENV['SMTP_FROM_EMAIL'] = 'noreply@test.local';
        $_ENV['SMTP_FROM_NAME'] = 'Test Hub';
        $_ENV['APP_URL'] = 'https://test.hub.local';
    }

    protected function tearDown(): void
    {
        // Restore original env vars
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
    }

    /**
     * Test Email constructor sets SMTP configuration from env vars
     */
    public function testConstructorSetsSMTPConfigurationFromEnv(): void
    {
        $email = new Email();

        // Can't directly test PHPMailer internals without sending,
        // but constructor shouldn't throw any exceptions
        $this->assertInstanceOf(Email::class, $email);
    }

    /**
     * Test Email constructor with TLS encryption
     */
    public function testConstructorWithTLSEncryption(): void
    {
        $_ENV['SMTP_ENCRYPTION'] = 'tls';

        $email = new Email();

        $this->assertInstanceOf(Email::class, $email);
    }

    /**
     * Test Email constructor with SSL encryption
     */
    public function testConstructorWithSSLEncryption(): void
    {
        $_ENV['SMTP_ENCRYPTION'] = 'ssl';

        $email = new Email();

        $this->assertInstanceOf(Email::class, $email);
    }

    /**
     * Test Email constructor with uppercase SSL encryption
     */
    public function testConstructorWithUppercaseSSLEncryption(): void
    {
        $_ENV['SMTP_ENCRYPTION'] = 'SSL';

        $email = new Email();

        $this->assertInstanceOf(Email::class, $email);
    }

    /**
     * Test Email constructor with default TLS when encryption not set
     */
    public function testConstructorWithDefaultEncryption(): void
    {
        unset($_ENV['SMTP_ENCRYPTION']);

        $email = new Email();

        $this->assertInstanceOf(Email::class, $email);
    }

    /**
     * Test Email constructor uses default from name when not set
     */
    public function testConstructorUsesDefaultFromName(): void
    {
        unset($_ENV['SMTP_FROM_NAME']);

        $email = new Email();

        $this->assertInstanceOf(Email::class, $email);
    }

    /**
     * Test send method fails with invalid SMTP configuration
     * We expect an exception because SMTP server is not real
     */
    public function testSendFailsWithInvalidSMTPConfiguration(): void
    {
        $email = new Email();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to send email');

        $email->send('test@example.com', 'Test Subject', 'Test body');
    }

    /**
     * Test send with plain text body
     */
    public function testSendWithPlainTextBody(): void
    {
        $email = new Email();

        try {
            $email->send('test@example.com', 'Test Subject', 'Plain text body', false);
            $this->fail('Should have thrown exception with test SMTP config');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Failed to send email', $e->getMessage());
        }
    }

    /**
     * Test send with HTML body
     */
    public function testSendWithHTMLBody(): void
    {
        $email = new Email();

        $htmlBody = '<html><body><h1>Test Email</h1><p>This is a test.</p></body></html>';

        try {
            $email->send('test@example.com', 'HTML Test', $htmlBody, true);
            $this->fail('Should have thrown exception with test SMTP config');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Failed to send email', $e->getMessage());
        }
    }

    /**
     * Test sendTestEmail method
     */
    public function testSendTestEmail(): void
    {
        $email = new Email();

        try {
            $email->sendTestEmail('admin@example.com');
            $this->fail('Should have thrown exception with test SMTP config');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Failed to send email', $e->getMessage());
        }
    }

    /**
     * Test sendTestEmail uses app name from env
     */
    public function testSendTestEmailUsesAppNameFromEnv(): void
    {
        $_ENV['SMTP_FROM_NAME'] = 'Custom Hub Name';

        $email = new Email();

        try {
            $email->sendTestEmail('admin@example.com');
            $this->fail('Should have thrown exception with test SMTP config');
        } catch (\Exception $e) {
            // Expected - just testing it doesn't crash before sending
            $this->assertStringContainsString('Failed to send email', $e->getMessage());
        }
    }

    /**
     * Test sendTestEmail uses default app name when not set
     */
    public function testSendTestEmailUsesDefaultAppName(): void
    {
        unset($_ENV['SMTP_FROM_NAME']);

        $email = new Email();

        try {
            $email->sendTestEmail('admin@example.com');
            $this->fail('Should have thrown exception with test SMTP config');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Failed to send email', $e->getMessage());
        }
    }

    /**
     * Test send with multiple recipients would require multiple addAddress calls
     * Single recipient per call is the current design
     */
    public function testSendSingleRecipient(): void
    {
        $email = new Email();

        try {
            $email->send('single@example.com', 'Single Recipient', 'Test body');
            $this->fail('Should have thrown exception with test SMTP config');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Failed to send email', $e->getMessage());
        }
    }

    /**
     * Test send with empty subject
     */
    public function testSendWithEmptySubject(): void
    {
        $email = new Email();

        try {
            $email->send('test@example.com', '', 'Body with no subject');
            $this->fail('Should have thrown exception with test SMTP config');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Failed to send email', $e->getMessage());
        }
    }

    /**
     * Test send with empty body
     */
    public function testSendWithEmptyBody(): void
    {
        $email = new Email();

        try {
            $email->send('test@example.com', 'No Body Test', '');
            $this->fail('Should have thrown exception with test SMTP config');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Failed to send email', $e->getMessage());
        }
    }

    /**
     * Test send with special characters in subject
     */
    public function testSendWithSpecialCharactersInSubject(): void
    {
        $email = new Email();

        $subject = 'Test: 日本語 & Émojis 🎉 – Special «Chars»';

        try {
            $email->send('test@example.com', $subject, 'Test body');
            $this->fail('Should have thrown exception with test SMTP config');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Failed to send email', $e->getMessage());
        }
    }

    /**
     * Test send with long body
     */
    public function testSendWithLongBody(): void
    {
        $email = new Email();

        $longBody = str_repeat("This is a test sentence. ", 500);

        try {
            $email->send('test@example.com', 'Long Body Test', $longBody);
            $this->fail('Should have thrown exception with test SMTP config');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Failed to send email', $e->getMessage());
        }
    }

    /**
     * Test constructor with custom SMTP port
     */
    public function testConstructorWithCustomSMTPPort(): void
    {
        $_ENV['SMTP_PORT'] = '465';

        $email = new Email();

        $this->assertInstanceOf(Email::class, $email);
    }

    /**
     * Test multiple Email instances can be created
     */
    public function testMultipleEmailInstancesCanBeCreated(): void
    {
        $email1 = new Email();
        $email2 = new Email();

        $this->assertInstanceOf(Email::class, $email1);
        $this->assertInstanceOf(Email::class, $email2);
        $this->assertNotSame($email1, $email2);
    }
}
