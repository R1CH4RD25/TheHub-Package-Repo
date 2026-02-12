<?php

namespace Hub\Tests\Package;

use PHPUnit\Framework\TestCase;
use Hub\Package\PackageAuditLogger;
use Hub\Database;

/**
 * PackageAuditLogger Test Suite
 *
 * Tests the high-volume audit logging system:
 * - Core log method
 * - Convenience methods (logQuery, logMutation, etc.)
 * - Query methods (getRecentEvents, getEventCounts, getUserActivity)
 * - Alert integration
 *
 * Uses real database with transactions (rolled back after each test).
 */
class PackageAuditLoggerTest extends TestCase
{
    private static Database $db;
    private static int $testUserId = 70002;

    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance();
        // Create test user
        self::$db->execute("INSERT INTO users (id, email, name, role) VALUES (70002, 'audit-test@example.com', 'Audit Test User', 'admin') ON DUPLICATE KEY UPDATE id=id");
    }

    protected function setUp(): void
    {
        self::$db->beginTransaction();
    }

    protected function tearDown(): void
    {
        self::$db->rollBack();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            self::$db->execute("DELETE FROM package_audit_events WHERE user_id = 70002");
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }

    // =========================================================================
    // CORE LOG TESTS
    // =========================================================================

    public function testLogCreatesEvent(): void
    {
        PackageAuditLogger::log('query', 'test-pkg', 'listStudents', [
            'userId' => self::$testUserId,
            'outcome' => 'success',
            'checkAlerts' => false,
        ]);

        $events = PackageAuditLogger::getRecentEvents('test-pkg', 10, 'query');
        $this->assertNotEmpty($events);

        $found = false;
        foreach ($events as $event) {
            if ($event['action_name'] === 'listStudents' && (int)$event['user_id'] === self::$testUserId) {
                $found = true;
                $this->assertEquals('query', $event['event_type']);
                $this->assertEquals('success', $event['outcome']);
                break;
            }
        }
        $this->assertTrue($found, 'Should find the logged event');
    }

    public function testLogWithDetails(): void
    {
        PackageAuditLogger::log('mutation', 'test-pkg', 'updateStudent', [
            'userId' => self::$testUserId,
            'outcome' => 'success',
            'targetRecordId' => 42,
            'details' => ['field' => 'name', 'oldValue' => 'John', 'newValue' => 'Jane'],
            'checkAlerts' => false,
        ]);

        $events = PackageAuditLogger::getRecentEvents('test-pkg', 10, 'mutation');
        $lastEvent = null;
        foreach ($events as $event) {
            if ($event['action_name'] === 'updateStudent' && (int)$event['user_id'] === self::$testUserId) {
                $lastEvent = $event;
                break;
            }
        }

        $this->assertNotNull($lastEvent);
        $this->assertEquals(42, (int)$lastEvent['target_record_id']);

        $details = json_decode($lastEvent['details'], true);
        $this->assertEquals('name', $details['field']);
    }

    // =========================================================================
    // CONVENIENCE METHOD TESTS
    // =========================================================================

    public function testLogQuery(): void
    {
        PackageAuditLogger::logQuery('test-pkg', 'getStudents', ['campus' => 1], 45.5, 150);

        $events = PackageAuditLogger::getRecentEvents('test-pkg', 10, 'query');
        $found = false;
        foreach ($events as $event) {
            if ($event['action_name'] === 'getStudents') {
                $found = true;
                $this->assertEquals('success', $event['outcome']);
                $details = json_decode($event['details'], true);
                $this->assertEquals(150, $details['rowCount']);
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testLogMutation(): void
    {
        PackageAuditLogger::logMutation(
            'test-pkg',
            'createStudent',
            ['name' => 'Test Student'],
            null,
            ['id' => 1, 'name' => 'Test Student'],
            12.3,
            1
        );

        $events = PackageAuditLogger::getRecentEvents('test-pkg', 10, 'mutation');
        $found = false;
        foreach ($events as $event) {
            if ($event['action_name'] === 'createStudent') {
                $found = true;
                $this->assertEquals(1, (int)$event['target_record_id']);
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testLogAccessDenied(): void
    {
        PackageAuditLogger::logAccessDenied(
            'test-pkg',
            'deleteStudents',
            'Insufficient role: standard < admin',
            self::$testUserId
        );

        $events = PackageAuditLogger::getRecentEvents('test-pkg', 10, 'access_denied');
        $found = false;
        foreach ($events as $event) {
            if ($event['action_name'] === 'deleteStudents' && (int)$event['user_id'] === self::$testUserId) {
                $found = true;
                $this->assertEquals('denied', $event['outcome']);
                $this->assertEquals('warning', $event['severity']);
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testLogRateLimited(): void
    {
        PackageAuditLogger::logRateLimited('test-pkg', 'listStudents', 120, 121, self::$testUserId);

        $events = PackageAuditLogger::getRecentEvents('test-pkg', 10, 'rate_limited');
        $found = false;
        foreach ($events as $event) {
            if ($event['action_name'] === 'listStudents' && $event['outcome'] === 'rate_limited') {
                $found = true;
                $details = json_decode($event['details'], true);
                $this->assertEquals(120, $details['limit']);
                $this->assertEquals(121, $details['used']);
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testLogReveal(): void
    {
        PackageAuditLogger::logReveal('test-pkg', 'revealPassword', 'Student forgot password', 42, self::$testUserId);

        $events = PackageAuditLogger::getRecentEvents('test-pkg', 10, 'reveal');
        $found = false;
        foreach ($events as $event) {
            if ($event['action_name'] === 'revealPassword' && (int)$event['user_id'] === self::$testUserId) {
                $found = true;
                $this->assertEquals('critical', $event['severity']);
                $this->assertEquals(42, (int)$event['target_record_id']);
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testLogPolicyViolation(): void
    {
        PackageAuditLogger::logPolicyViolation(
            'test-pkg',
            'viewSSN',
            'Token ownership mismatch',
            self::$testUserId
        );

        $events = PackageAuditLogger::getRecentEvents('test-pkg', 10, 'policy_violation');
        $found = false;
        foreach ($events as $event) {
            if ($event['action_name'] === 'viewSSN' && (int)$event['user_id'] === self::$testUserId) {
                $found = true;
                $this->assertEquals('critical', $event['severity']);
                $this->assertEquals('denied', $event['outcome']);
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testLogTokenEvent(): void
    {
        $fakeToken = str_repeat('ab', 64); // 128 chars
        PackageAuditLogger::logTokenEvent('token_issued', 'test-pkg', 'revealPassword', $fakeToken, self::$testUserId, 42);

        $events = PackageAuditLogger::getRecentEvents('test-pkg', 10, 'token_issued');
        $found = false;
        foreach ($events as $event) {
            if ($event['action_name'] === 'revealPassword' && $event['event_type'] === 'token_issued') {
                $found = true;
                $details = json_decode($event['details'], true);
                // Token should be truncated in log
                $this->assertStringContainsString('...', $details['tokenId']);
                break;
            }
        }
        $this->assertTrue($found);
    }

    // =========================================================================
    // QUERY METHOD TESTS
    // =========================================================================

    public function testGetRecentEventsWithFilter(): void
    {
        // Log some events of different types
        PackageAuditLogger::log('query', 'filter-test-pkg', 'action1', [
            'userId' => self::$testUserId,
            'checkAlerts' => false
        ]);
        PackageAuditLogger::log('mutation', 'filter-test-pkg', 'action2', [
            'userId' => self::$testUserId,
            'checkAlerts' => false
        ]);

        // Filter by type
        $queries = PackageAuditLogger::getRecentEvents('filter-test-pkg', 50, 'query');
        $mutations = PackageAuditLogger::getRecentEvents('filter-test-pkg', 50, 'mutation');

        $this->assertNotEmpty($queries);
        $this->assertNotEmpty($mutations);

        // Verify filtering works
        foreach ($queries as $event) {
            $this->assertEquals('query', $event['event_type']);
        }
        foreach ($mutations as $event) {
            $this->assertEquals('mutation', $event['event_type']);
        }
    }

    public function testGetEventCounts(): void
    {
        // Log events
        PackageAuditLogger::log('query', 'count-test-pkg', 'action1', [
            'userId' => self::$testUserId,
            'checkAlerts' => false
        ]);
        PackageAuditLogger::log('query', 'count-test-pkg', 'action2', [
            'userId' => self::$testUserId,
            'checkAlerts' => false
        ]);
        PackageAuditLogger::log('mutation', 'count-test-pkg', 'action3', [
            'userId' => self::$testUserId,
            'checkAlerts' => false
        ]);

        $counts = PackageAuditLogger::getEventCounts('count-test-pkg');
        $this->assertArrayHasKey('query', $counts);
        $this->assertGreaterThanOrEqual(2, $counts['query']);
        $this->assertArrayHasKey('mutation', $counts);
        $this->assertGreaterThanOrEqual(1, $counts['mutation']);
    }

    public function testGetUserActivity(): void
    {
        PackageAuditLogger::log('query', 'activity-test-pkg', 'action1', [
            'userId' => self::$testUserId,
            'outcome' => 'success',
            'checkAlerts' => false
        ]);

        $activity = PackageAuditLogger::getUserActivity(self::$testUserId, 'activity-test-pkg');
        $this->assertNotEmpty($activity);
        $this->assertArrayHasKey('event_type', $activity[0]);
        $this->assertArrayHasKey('count', $activity[0]);
    }

    public function testGetUnresolvedAlerts(): void
    {
        $alerts = PackageAuditLogger::getUnresolvedAlerts();
        $this->assertIsArray($alerts);
        // No active alerts expected in test env, just verify structure works
    }

    // =========================================================================
    // SEVERITY INFERENCE TESTS
    // =========================================================================

    public function testSeverityInferredCorrectly(): void
    {
        // Access denied should be 'warning'
        PackageAuditLogger::logAccessDenied('sev-test-pkg', 'testAction', 'No access', self::$testUserId);
        $events = PackageAuditLogger::getRecentEvents('sev-test-pkg', 10, 'access_denied');
        $found = false;
        foreach ($events as $event) {
            if ($event['action_name'] === 'testAction') {
                $this->assertEquals('warning', $event['severity']);
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        // Reveal should be 'critical'
        PackageAuditLogger::logReveal('sev-test-pkg', 'revealTest', 'Testing severity', null, self::$testUserId);
        $revealEvents = PackageAuditLogger::getRecentEvents('sev-test-pkg', 10, 'reveal');
        $found = false;
        foreach ($revealEvents as $event) {
            if ($event['action_name'] === 'revealTest') {
                $this->assertEquals('critical', $event['severity']);
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }
}
