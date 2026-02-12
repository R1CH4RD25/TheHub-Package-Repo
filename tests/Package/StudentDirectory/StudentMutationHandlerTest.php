<?php

namespace Tests\Package\StudentDirectory;

use PHPUnit\Framework\TestCase;
use Hub\Package\StudentDirectory\StudentDatabase;
use Hub\Package\StudentDirectory\StudentMutationHandler;
use Hub\Package\StudentDirectory\StudentQueryHandler;

/**
 * Tests for Student Mutation Handler
 *
 * Tests create, update, reset password, and bulk operations.
 * Creates test records with a unique prefix and cleans up after.
 */
class StudentMutationHandlerTest extends TestCase
{
    private const TEST_PREFIX = 'TEST_';
    private array $createdIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('env')) {
            require_once __DIR__ . '/../../../src/bootstrap.php';
        }

        try {
            StudentDatabase::fetchColumn("SELECT 1");
        } catch (\Exception $e) {
            $this->markTestSkipped('Cannot connect to woodson_students: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        // Clean up any test records
        foreach ($this->createdIds as $id) {
            try {
                StudentDatabase::execute(
                    "DELETE FROM students WHERE student_id = :id",
                    ['id' => $id]
                );
            } catch (\Exception $e) {
                // Silent cleanup
            }
        }

        StudentDatabase::reset();
        parent::tearDown();
    }

    private function makeTestId(): string
    {
        $id = self::TEST_PREFIX . uniqid();
        $this->createdIds[] = $id;
        return $id;
    }

    // ─── createStudent ──────────────────────────────────────────

    public function test_createStudent_success(): void
    {
        $id = $this->makeTestId();

        $result = StudentMutationHandler::createStudent([
            'student_id' => $id,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'grade' => '5',
            'dob' => '2013-06-15',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals($id, $result['student_id']);
        $this->assertArrayHasKey('generated', $result);
        $this->assertNotEmpty($result['generated']['password']);
        $this->assertStringContainsString('@woodsonisd.net', $result['generated']['chromebook_login']);
        $this->assertEquals('Elementary', $result['generated']['ou_for_google']);
    }

    public function test_createStudent_auto_generates_fields(): void
    {
        $id = $this->makeTestId();

        $result = StudentMutationHandler::createStudent([
            'student_id' => $id,
            'first_name' => 'Emma',
            'last_name' => 'Bellah',
            'grade' => '8',
            'dob' => '2010-07-20',
        ]);

        $this->assertTrue($result['success']);
        $gen = $result['generated'];

        $this->assertStringContainsString('Bell', $gen['password']);
        $this->assertEquals('Junior High', $gen['ou_for_google']);
        $this->assertStringContainsString('seniors', $gen['group_email']);
    }

    public function test_createStudent_duplicate_fails(): void
    {
        $id = $this->makeTestId();

        StudentMutationHandler::createStudent([
            'student_id' => $id,
            'first_name' => 'Test',
            'last_name' => 'Dup',
            'grade' => '3',
        ]);

        $result = StudentMutationHandler::createStudent([
            'student_id' => $id,
            'first_name' => 'Test',
            'last_name' => 'Dup2',
            'grade' => '3',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already exists', $result['message']);
    }

    public function test_createStudent_missing_required_fails(): void
    {
        $result = StudentMutationHandler::createStudent([
            'student_id' => $this->makeTestId(),
            'first_name' => 'Test',
            // missing last_name and grade
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing required', $result['message']);
    }

    // ─── updateStudent ──────────────────────────────────────────

    public function test_updateStudent_basic_fields(): void
    {
        $id = $this->makeTestId();
        StudentMutationHandler::createStudent([
            'student_id' => $id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'grade' => '5',
        ]);

        $result = StudentMutationHandler::updateStudent($id, [
            'first_name' => 'Johnny',
            'nickname' => 'JD',
        ]);

        $this->assertTrue($result['success']);

        $updated = StudentQueryHandler::getStudent($id);
        $this->assertEquals('Johnny', $updated['first_name']);
        $this->assertEquals('JD', $updated['nickname']);
    }

    public function test_updateStudent_grade_change_cascades(): void
    {
        $id = $this->makeTestId();
        StudentMutationHandler::createStudent([
            'student_id' => $id,
            'first_name' => 'Amy',
            'last_name' => 'Test',
            'grade' => '5',
        ]);

        $before = StudentQueryHandler::getStudent($id);
        $this->assertEquals('Elementary', $before['ou_for_google']);

        $result = StudentMutationHandler::updateStudent($id, ['grade' => '9']);
        $this->assertTrue($result['success']);

        $after = StudentQueryHandler::getStudent($id);
        $this->assertEquals('9', $after['grade']);
        $this->assertEquals('High School', $after['ou_for_google']);
    }

    public function test_updateStudent_not_found(): void
    {
        $result = StudentMutationHandler::updateStudent('NONEXISTENT_ID', ['first_name' => 'Test']);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    // ─── resetPassword ──────────────────────────────────────────

    public function test_resetPassword_success(): void
    {
        $id = $this->makeTestId();
        StudentMutationHandler::createStudent([
            'student_id' => $id,
            'first_name' => 'Reset',
            'last_name' => 'Test',
            'grade' => 'PK',
        ]);

        $result = StudentMutationHandler::resetPassword($id);

        $this->assertTrue($result['success']);
        $this->assertEquals('Woodson1', $result['new_password']);
    }

    public function test_resetPassword_not_found(): void
    {
        $result = StudentMutationHandler::resetPassword('FAKE_999');
        $this->assertFalse($result['success']);
    }

    // ─── bulkDelete ─────────────────────────────────────────────

    public function test_bulkDelete_success(): void
    {
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $id = $this->makeTestId();
            $ids[] = $id;
            StudentMutationHandler::createStudent([
                'student_id' => $id,
                'first_name' => "Bulk{$i}",
                'last_name' => 'Test',
                'grade' => '4',
            ]);
        }

        $result = StudentMutationHandler::bulkDelete($ids);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['deleted_count']);

        // Verify deleted
        foreach ($ids as $id) {
            $this->assertFalse(StudentQueryHandler::studentExists($id));
        }
    }

    public function test_bulkDelete_empty_fails(): void
    {
        $result = StudentMutationHandler::bulkDelete([]);
        $this->assertFalse($result['success']);
    }

    // ─── printCards ─────────────────────────────────────────────

    public function test_printCards_by_ids(): void
    {
        $id = $this->makeTestId();
        StudentMutationHandler::createStudent([
            'student_id' => $id,
            'first_name' => 'Print',
            'last_name' => 'Test',
            'grade' => '3',
        ]);

        $result = StudentMutationHandler::printCards([$id]);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['count']);
        $this->assertStringContainsString('Print', $result['html']);
        $this->assertStringContainsString('student-card', $result['html']);
    }

    public function test_printCards_empty_params_fails(): void
    {
        $result = StudentMutationHandler::printCards();
        $this->assertFalse($result['success']);
    }
}
