<?php

namespace Tests\Package\StudentDirectory;

use PHPUnit\Framework\TestCase;
use Hub\Package\StudentDirectory\ExportHandler;
use Hub\Package\StudentDirectory\StudentDatabase;

/**
 * Tests for Student Directory Export Handler
 *
 * Verifies standard CSV and Google Workspace CSV export formats.
 */
class ExportHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('env')) {
            require_once __DIR__ . '/../../../src/bootstrap.php';
        }

        try {
            $count = (int) StudentDatabase::fetchColumn("SELECT COUNT(*) FROM students");
            if ($count === 0) {
                $this->markTestSkipped('No students in database');
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

    // ─── Standard CSV ───────────────────────────────────────────

    public function test_exportStandardCsv_returns_csv(): void
    {
        $result = ExportHandler::exportStandardCsv();

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['csv']);
        $this->assertGreaterThan(0, $result['count']);
        $this->assertStringContainsString('Student ID', $result['csv']);
        $this->assertStringContainsString('Grade', $result['csv']);
        $this->assertStringEndsWith('.csv', $result['filename']);
    }

    public function test_exportStandardCsv_with_grade_filter(): void
    {
        // Get available grades
        $grades = StudentDatabase::fetchAll(
            "SELECT DISTINCT grade FROM students LIMIT 1"
        );
        if (empty($grades)) {
            $this->markTestSkipped('No grades');
        }

        $grade = $grades[0]['grade'];
        $result = ExportHandler::exportStandardCsv(['grade' => $grade]);

        $this->assertTrue($result['success']);

        // Parse CSV and verify all rows match grade
        $lines = explode("\n", trim($result['csv']));
        $header = str_getcsv($lines[0]);
        $gradeIndex = array_search('Grade', $header);

        for ($i = 1; $i < count($lines); $i++) {
            $cols = str_getcsv($lines[$i]);
            if (!empty($cols[$gradeIndex])) {
                $this->assertEquals($grade, $cols[$gradeIndex]);
            }
        }
    }

    public function test_exportStandardCsv_includes_password(): void
    {
        $result = ExportHandler::exportStandardCsv();
        $this->assertStringContainsString('Password', $result['csv']);
        $this->assertStringContainsString('Hispanic/Latino', $result['csv']);
    }

    // ─── Google Workspace CSV ───────────────────────────────────

    public function test_exportGoogleCsv_format(): void
    {
        $result = ExportHandler::exportGoogleCsv();

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['csv']);

        // Verify Google Admin required headers
        $this->assertStringContainsString('First Name [Required]', $result['csv']);
        $this->assertStringContainsString('Last Name [Required]', $result['csv']);
        $this->assertStringContainsString('Email Address [Required]', $result['csv']);
        $this->assertStringContainsString('Password [Required]', $result['csv']);
        $this->assertStringContainsString('Org Unit Path [Required]', $result['csv']);
    }

    public function test_exportGoogleCsv_ou_path_format(): void
    {
        $result = ExportHandler::exportGoogleCsv();

        // OU paths should start with /Students/
        $this->assertStringContainsString('/Students/', $result['csv']);
    }

    public function test_exportGoogleCsv_filename(): void
    {
        $result = ExportHandler::exportGoogleCsv();
        $this->assertStringStartsWith('google_workspace_', $result['filename']);
        $this->assertStringEndsWith('.csv', $result['filename']);
    }

    // ─── Edge Cases ─────────────────────────────────────────────

    public function test_export_no_results_returns_failure(): void
    {
        $result = ExportHandler::exportStandardCsv(['grade' => 'NONEXISTENT']);
        $this->assertFalse($result['success']);
    }
}
