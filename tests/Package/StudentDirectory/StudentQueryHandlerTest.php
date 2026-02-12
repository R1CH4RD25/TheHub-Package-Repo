<?php

namespace Tests\Package\StudentDirectory;

use PHPUnit\Framework\TestCase;
use Hub\Package\StudentDirectory\StudentDatabase;
use Hub\Package\StudentDirectory\StudentQueryHandler;

/**
 * Tests for Student Directory Query Handler
 *
 * Tests against the live woodson_students database.
 * Requires STUDENT_DB_DATABASE=woodson_students in .env
 * and proper DB grants for WISDAdmin.
 */
class StudentQueryHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure bootstrap loaded env
        if (!function_exists('env')) {
            require_once __DIR__ . '/../../../src/bootstrap.php';
        }

        // Verify DB access
        try {
            $count = (int) StudentDatabase::fetchColumn("SELECT COUNT(*) FROM students");
            if ($count === 0) {
                $this->markTestSkipped('No students in woodson_students.students table');
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Cannot connect to woodson_students: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        StudentDatabase::reset();
        parent::tearDown();
    }

    // ─── listStudents ───────────────────────────────────────────

    public function test_listStudents_returns_data_and_pagination(): void
    {
        $result = StudentQueryHandler::listStudents();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertIsArray($result['data']);
        $this->assertGreaterThan(0, count($result['data']));

        $pagination = $result['pagination'];
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('page', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('total_pages', $pagination);
        $this->assertArrayHasKey('has_more', $pagination);
    }

    public function test_listStudents_with_search(): void
    {
        // Get a known student name first
        $all = StudentQueryHandler::listStudents(['per_page' => 1]);
        if (empty($all['data'])) {
            $this->markTestSkipped('No students');
        }

        $name = $all['data'][0]['last_name'];
        $result = StudentQueryHandler::listStudents(['search' => $name]);

        $this->assertNotEmpty($result['data']);
        foreach ($result['data'] as $row) {
            $this->assertStringContainsStringIgnoringCase($name, $row['last_name']);
        }
    }

    public function test_listStudents_with_grade_filter(): void
    {
        $grades = StudentQueryHandler::getGrades();
        if (empty($grades)) {
            $this->markTestSkipped('No grades');
        }

        $testGrade = $grades[0]['grade'];
        $result = StudentQueryHandler::listStudents(['grade' => $testGrade]);

        foreach ($result['data'] as $row) {
            $this->assertEquals($testGrade, $row['grade']);
        }
    }

    public function test_listStudents_pagination(): void
    {
        $result = StudentQueryHandler::listStudents(['per_page' => 5, 'page' => 1]);
        $this->assertLessThanOrEqual(5, count($result['data']));
        $this->assertEquals(1, $result['pagination']['page']);
        $this->assertEquals(5, $result['pagination']['per_page']);
    }

    public function test_listStudents_does_not_include_password(): void
    {
        $result = StudentQueryHandler::listStudents(['per_page' => 1]);
        if (!empty($result['data'])) {
            $this->assertArrayNotHasKey('password', $result['data'][0]);
        }
    }

    // ─── getStudent ─────────────────────────────────────────────

    public function test_getStudent_returns_student(): void
    {
        $list = StudentQueryHandler::listStudents(['per_page' => 1]);
        if (empty($list['data'])) {
            $this->markTestSkipped('No students');
        }

        $id = $list['data'][0]['student_id'];
        $student = StudentQueryHandler::getStudent($id);

        $this->assertNotNull($student);
        $this->assertEquals($id, $student['student_id']);
        $this->assertArrayHasKey('first_name', $student);
        $this->assertArrayHasKey('last_name', $student);
        $this->assertArrayHasKey('grade', $student);
    }

    public function test_getStudent_not_found_returns_null(): void
    {
        $student = StudentQueryHandler::getStudent('NONEXISTENT_ID_999999');
        $this->assertNull($student);
    }

    // ─── getStats ───────────────────────────────────────────────

    public function test_getStats_structure(): void
    {
        $stats = StudentQueryHandler::getStats();

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('elementary', $stats);
        $this->assertArrayHasKey('junior_high', $stats);
        $this->assertArrayHasKey('high_school', $stats);
        $this->assertArrayHasKey('by_grade', $stats);
        $this->assertGreaterThan(0, $stats['total']);
    }

    public function test_getStats_levels_sum_to_total(): void
    {
        $stats = StudentQueryHandler::getStats();
        $sum = $stats['elementary'] + $stats['junior_high'] + $stats['high_school'];
        $this->assertEquals($stats['total'], $sum);
    }

    // ─── getGrades ──────────────────────────────────────────────

    public function test_getGrades_sorted(): void
    {
        $grades = StudentQueryHandler::getGrades();
        $this->assertNotEmpty($grades);

        $sortOrders = array_column($grades, 'sort_order');
        $sorted = $sortOrders;
        sort($sorted);
        $this->assertEquals($sorted, $sortOrders);
    }

    // ─── getGraduationYears ─────────────────────────────────────

    public function test_getGraduationYears(): void
    {
        $years = StudentQueryHandler::getGraduationYears();
        $this->assertNotEmpty($years);

        foreach ($years as $y) {
            $this->assertArrayHasKey('graduation_year', $y);
            $this->assertGreaterThan(2000, (int) $y['graduation_year']);
        }
    }

    // ─── studentExists ──────────────────────────────────────────

    public function test_studentExists_true_for_real_student(): void
    {
        $list = StudentQueryHandler::listStudents(['per_page' => 1]);
        if (empty($list['data'])) {
            $this->markTestSkipped('No students');
        }

        $id = $list['data'][0]['student_id'];
        $this->assertTrue(StudentQueryHandler::studentExists($id));
    }

    public function test_studentExists_false_for_nonexistent(): void
    {
        $this->assertFalse(StudentQueryHandler::studentExists('FAKE_ID_000000'));
    }

    // ─── searchStudents ─────────────────────────────────────────

    public function test_searchStudents_returns_results(): void
    {
        $list = StudentQueryHandler::listStudents(['per_page' => 1]);
        if (empty($list['data'])) {
            $this->markTestSkipped('No students');
        }

        $name = $list['data'][0]['first_name'];
        $results = StudentQueryHandler::searchStudents($name);

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('student_id', $results[0]);
        $this->assertArrayHasKey('first_name', $results[0]);
    }

    public function test_searchStudents_respects_limit(): void
    {
        $results = StudentQueryHandler::searchStudents('a', 3);
        $this->assertLessThanOrEqual(3, count($results));
    }
}
