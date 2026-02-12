<?php

namespace Tests\Package\StudentDirectory;

use PHPUnit\Framework\TestCase;
use Hub\Package\StudentDirectory\StudentDirectoryHandler;

/**
 * Tests for StudentDirectoryHandler — the unified bridge
 * between the PageRouter and the individual Student Directory handlers
 */
class StudentDirectoryHandlerTest extends TestCase
{
    private StudentDirectoryHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new StudentDirectoryHandler();
    }

    public function testPackageId(): void
    {
        $this->assertSame('district.student-directory', $this->handler->getPackageId());
    }

    public function testSupportedQueries(): void
    {
        $queries = $this->handler->getSupportedQueries();
        $this->assertContains('listStudents', $queries);
        $this->assertContains('getStudent', $queries);
        $this->assertContains('getStats', $queries);
        $this->assertContains('getGrades', $queries);
        $this->assertContains('getGraduationYears', $queries);
        $this->assertContains('getImportHistory', $queries);
        $this->assertContains('searchStudents', $queries);
    }

    public function testSupportedMutations(): void
    {
        $mutations = $this->handler->getSupportedMutations();
        $this->assertContains('createStudent', $mutations);
        $this->assertContains('updateStudent', $mutations);
        $this->assertContains('resetPassword', $mutations);
        $this->assertContains('bulkDelete', $mutations);
        $this->assertContains('printCards', $mutations);
    }

    public function testUnknownQueryReturnsError(): void
    {
        $result = $this->handler->executeQuery('nonexistent', [], []);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Unknown query', $result['error']);
    }

    public function testUnknownMutationReturnsError(): void
    {
        $result = $this->handler->executeMutation('nonexistent', [], []);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Unknown mutation', $result['error']);
    }

    public function testGetStatsReturnsExpectedKeys(): void
    {
        $result = $this->handler->executeQuery('getStats', [], ['user' => ['id' => 1, 'role' => 'admin']]);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('elementary', $result);
        $this->assertArrayHasKey('junior_high', $result);
        $this->assertArrayHasKey('high_school', $result);
        $this->assertIsInt($result['total']);
    }

    public function testListStudentsReturnsNormalizedShape(): void
    {
        $result = $this->handler->executeQuery('listStudents', ['per_page' => 5, 'page' => 1], []);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('total', $result['meta']);
        $this->assertArrayHasKey('page', $result['meta']);
        $this->assertArrayHasKey('perPage', $result['meta']);
        $this->assertIsArray($result['data']);
        $this->assertLessThanOrEqual(5, count($result['data']));
    }

    public function testGetStudentReturnsData(): void
    {
        // First get a real student_id from listing
        $list = $this->handler->executeQuery('listStudents', ['per_page' => 1], []);
        $this->assertNotEmpty($list['data']);
        $studentId = $list['data'][0]['student_id'];

        $result = $this->handler->executeQuery('getStudent', ['id' => $studentId], []);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
        $this->assertSame($studentId, $result['data']['student_id']);
    }

    public function testGetGradesReturnsData(): void
    {
        $result = $this->handler->executeQuery('getGrades', [], []);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
    }

    public function testGetGraduationYearsReturnsData(): void
    {
        $result = $this->handler->executeQuery('getGraduationYears', [], []);
        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);
    }

    public function testGetImportHistoryReturnsNormalizedShape(): void
    {
        $result = $this->handler->executeQuery('getImportHistory', [], []);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertIsArray($result['data']);
    }

    public function testMutationCreateMissingFieldsReturnsError(): void
    {
        $result = $this->handler->executeMutation('createStudent', ['first_name' => 'Test'], []);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Missing required', $result['message']);
    }
}
