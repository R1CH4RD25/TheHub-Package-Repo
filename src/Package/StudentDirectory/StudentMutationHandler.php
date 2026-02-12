<?php

namespace Hub\Package\StudentDirectory;

use Hub\Package\StudentDirectory\StudentDatabase;
use Hub\Package\StudentDirectory\PasswordGenerator;
use Hub\Package\StudentDirectory\StudentQueryHandler;

/**
 * Student Directory Mutation Handler
 *
 * Handles all write operations: create, update, delete,
 * password reset, bulk operations, and print cards.
 */
class StudentMutationHandler
{
    /**
     * Create a new student
     *
     * Auto-generates: graduation_year, password, chromebook_login, group_email, ou_for_google
     *
     * @param array $data Student data
     * @return array ['success' => bool, 'student_id' => string, 'message' => string]
     */
    public static function createStudent(array $data): array
    {
        $required = ['student_id', 'first_name', 'last_name', 'grade'];
        foreach ($required as $field) {
            if (empty(trim($data[$field] ?? ''))) {
                return ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }

        $studentId = trim($data['student_id']);

        // Check for duplicates
        if (StudentQueryHandler::studentExists($studentId)) {
            return ['success' => false, 'message' => "Student ID {$studentId} already exists"];
        }

        $grade = strtoupper(trim($data['grade']));
        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);
        $nickname = trim($data['nickname'] ?? '');
        $dob = trim($data['dob'] ?? '') ?: null;

        // Auto-generate computed fields
        $graduationYear = !empty($data['graduation_year'])
            ? (int) $data['graduation_year']
            : PasswordGenerator::calculateGraduationYear($grade);

        $password = PasswordGenerator::generateByGrade(
            $grade,
            $firstName,
            $lastName,
            $graduationYear,
            $dob
        );

        $chromebookLogin = PasswordGenerator::generateChromebookLogin(
            $firstName,
            $lastName,
            $graduationYear,
            $nickname
        );

        $groupEmail = PasswordGenerator::generateGroupEmail($graduationYear);
        $ouForGoogle = PasswordGenerator::determineOU($grade);

        // Build insert
        $record = [
            'student_id' => $studentId,
            'grade' => $grade,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'nickname' => $nickname ?: null,
            'dob' => $dob,
            'chromebook_login' => $chromebookLogin,
            'group_email' => $groupEmail,
            'password' => $password,
            'graduation_year' => $graduationYear,
            'ou_for_google' => $ouForGoogle,
            'middle_name' => trim($data['middle_name'] ?? '') ?: null,
            'sex' => trim($data['sex'] ?? '') ?: null,
            'hispanic_latino' => (int) ($data['hispanic_latino'] ?? 0),
            'white' => (int) ($data['white'] ?? 0),
            'black_african_american' => (int) ($data['black_african_american'] ?? 0),
            'asian' => (int) ($data['asian'] ?? 0),
            'american_indian_alaskan_native' => (int) ($data['american_indian_alaskan_native'] ?? 0),
            'hawaiian_pacific_isl' => (int) ($data['hawaiian_pacific_isl'] ?? 0),
        ];

        $columns = implode(', ', array_keys($record));
        $placeholders = ':' . implode(', :', array_keys($record));

        $sql = "INSERT INTO students ({$columns}) VALUES ({$placeholders})";
        $affected = StudentDatabase::execute($sql, $record);

        if ($affected > 0) {
            return [
                'success' => true,
                'student_id' => $studentId,
                'message' => "Student {$firstName} {$lastName} created successfully",
                'generated' => [
                    'password' => $password,
                    'chromebook_login' => $chromebookLogin,
                    'group_email' => $groupEmail,
                    'ou_for_google' => $ouForGoogle,
                    'graduation_year' => $graduationYear,
                ],
            ];
        }

        return ['success' => false, 'message' => 'Failed to create student record'];
    }

    /**
     * Update an existing student
     *
     * If grade changes, regenerates: password, ou_for_google, graduation_year
     *
     * @param string $studentId The student_id to update
     * @param array $data Fields to update
     * @return array
     */
    public static function updateStudent(string $studentId, array $data): array
    {
        $existing = StudentQueryHandler::getStudent($studentId, true);
        if (!$existing) {
            return ['success' => false, 'message' => "Student {$studentId} not found"];
        }

        $updates = [];
        $binds = ['id' => $studentId];

        // Direct-update fields
        $directFields = [
            'first_name',
            'last_name',
            'middle_name',
            'nickname',
            'sex',
            'dob',
            'chromebook_login',
            'group_email',
            'hispanic_latino',
            'white',
            'black_african_american',
            'asian',
            'american_indian_alaskan_native',
            'hawaiian_pacific_isl',
        ];

        foreach ($directFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                if (in_array($field, ['hispanic_latino', 'white', 'black_african_american', 'asian', 'american_indian_alaskan_native', 'hawaiian_pacific_isl'])) {
                    $value = (int) $value;
                }
                $updates[] = "{$field} = :{$field}";
                $binds[$field] = $value ?: null;
            }
        }

        // Handle grade change → cascade updates
        $gradeChanged = isset($data['grade']) && strtoupper(trim($data['grade'])) !== $existing['grade'];

        if ($gradeChanged) {
            $newGrade = strtoupper(trim($data['grade']));
            $updates[] = "grade = :grade";
            $binds['grade'] = $newGrade;

            // Recalculate graduation year
            $newGradYear = PasswordGenerator::calculateGraduationYear($newGrade);
            $updates[] = "graduation_year = :graduation_year";
            $binds['graduation_year'] = $newGradYear;

            // Recalculate OU
            $newOU = PasswordGenerator::determineOU($newGrade);
            $updates[] = "ou_for_google = :ou_for_google";
            $binds['ou_for_google'] = $newOU;

            // Regenerate password for new grade
            $firstName = $data['first_name'] ?? $existing['first_name'];
            $lastName = $data['last_name'] ?? $existing['last_name'];
            $dob = $data['dob'] ?? $existing['dob'];
            $newPassword = PasswordGenerator::generateByGrade(
                $newGrade,
                $firstName,
                $lastName,
                $newGradYear,
                $dob
            );
            $updates[] = "password = :password";
            $binds['password'] = $newPassword;

            // Regenerate group email
            $newGroupEmail = PasswordGenerator::generateGroupEmail($newGradYear);
            $updates[] = "group_email = :group_email";
            $binds['group_email'] = $newGroupEmail;
        } elseif (array_key_exists('graduation_year', $data)) {
            $updates[] = "graduation_year = :graduation_year";
            $binds['graduation_year'] = (int) $data['graduation_year'];
        }

        if (empty($updates)) {
            return ['success' => true, 'message' => 'No changes to apply'];
        }

        $updates[] = "updated_at = NOW()";
        $setClauses = implode(', ', $updates);
        $sql = "UPDATE students SET {$setClauses} WHERE student_id = :id";

        $affected = StudentDatabase::execute($sql, $binds);

        return [
            'success' => true,
            'student_id' => $studentId,
            'message' => "Student updated successfully",
            'changed_fields' => count($updates) - 1, // exclude updated_at
        ];
    }

    /**
     * Reset a student's password
     *
     * @param string $studentId
     * @return array ['success', 'new_password', 'message']
     */
    public static function resetPassword(string $studentId): array
    {
        $student = StudentQueryHandler::getStudent($studentId, true);
        if (!$student) {
            return ['success' => false, 'message' => "Student {$studentId} not found"];
        }

        $newPassword = PasswordGenerator::generateByGrade(
            $student['grade'],
            $student['first_name'],
            $student['last_name'],
            (int) $student['graduation_year'],
            $student['dob'] ?? null
        );

        StudentDatabase::execute(
            "UPDATE students SET password = :pw, updated_at = NOW() WHERE student_id = :id",
            ['pw' => $newPassword, 'id' => $studentId]
        );

        return [
            'success' => true,
            'student_id' => $studentId,
            'new_password' => $newPassword,
            'message' => "Password reset for {$student['first_name']} {$student['last_name']}",
        ];
    }

    /**
     * Bulk delete students
     *
     * @param array $studentIds Array of student_id values
     * @return array ['success', 'deleted_count', 'message']
     */
    public static function bulkDelete(array $studentIds): array
    {
        if (empty($studentIds)) {
            return ['success' => false, 'message' => 'No student IDs provided'];
        }

        // Sanitize IDs
        $studentIds = array_map('trim', $studentIds);
        $studentIds = array_filter($studentIds);

        if (empty($studentIds)) {
            return ['success' => false, 'message' => 'No valid student IDs provided'];
        }

        // Build parameterized IN clause
        $placeholders = [];
        $binds = [];
        foreach ($studentIds as $i => $id) {
            $key = "id_{$i}";
            $placeholders[] = ":{$key}";
            $binds[$key] = $id;
        }

        $inClause = implode(', ', $placeholders);
        $sql = "DELETE FROM students WHERE student_id IN ({$inClause})";
        $deleted = StudentDatabase::execute($sql, $binds);

        return [
            'success' => true,
            'deleted_count' => $deleted,
            'message' => "Deleted {$deleted} student(s)",
        ];
    }

    /**
     * Generate printable student cards (HTML)
     *
     * @param array $studentIds Array of student_id values (empty = all)
     * @param string|null $grade Filter by grade
     * @return array ['success', 'html', 'count']
     */
    public static function printCards(array $studentIds = [], ?string $grade = null): array
    {
        if (!empty($studentIds)) {
            $placeholders = [];
            $binds = [];
            foreach ($studentIds as $i => $id) {
                $key = "id_{$i}";
                $placeholders[] = ":{$key}";
                $binds[$key] = $id;
            }
            $inClause = implode(', ', $placeholders);
            $sql = "SELECT student_id, first_name, last_name, nickname, grade,
                           chromebook_login, password, graduation_year
                    FROM students
                    WHERE student_id IN ({$inClause})
                    ORDER BY last_name, first_name";
            $students = StudentDatabase::fetchAll($sql, $binds);
        } elseif ($grade) {
            $sql = "SELECT student_id, first_name, last_name, nickname, grade,
                           chromebook_login, password, graduation_year
                    FROM students
                    WHERE grade = :grade
                    ORDER BY last_name, first_name";
            $students = StudentDatabase::fetchAll($sql, ['grade' => strtoupper(trim($grade))]);
        } else {
            return ['success' => false, 'message' => 'Provide student IDs or a grade filter'];
        }

        if (empty($students)) {
            return ['success' => false, 'message' => 'No students found'];
        }

        $html = self::renderCards($students);

        return [
            'success' => true,
            'html' => $html,
            'count' => count($students),
            'message' => 'Generated ' . count($students) . ' student card(s)',
        ];
    }

    /**
     * Render student cards as print-ready HTML
     */
    private static function renderCards(array $students): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<title>Student Login Cards</title>';
        $html .= '<style>
            @page { margin: 0.5in; }
            body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
            .cards-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
            .student-card {
                border: 2px solid #333; border-radius: 8px; padding: 12px;
                page-break-inside: avoid; text-align: center; font-size: 11px;
            }
            .student-card .name { font-size: 14px; font-weight: bold; margin-bottom: 6px; }
            .student-card .grade { color: #666; margin-bottom: 4px; }
            .student-card .login { font-family: monospace; font-size: 10px; word-break: break-all; }
            .student-card .password { font-size: 16px; font-weight: bold; color: #0066cc; margin: 6px 0; }
            @media print { .no-print { display: none; } }
        </style></head><body>';

        $html .= '<div class="no-print" style="padding:10px;text-align:center">';
        $html .= '<button onclick="window.print()" style="padding:10px 30px;font-size:16px;cursor:pointer">Print Cards</button>';
        $html .= '</div>';

        $html .= '<div class="cards-grid">';

        foreach ($students as $s) {
            $display = htmlspecialchars($s['nickname'] ?: $s['first_name']);
            $last = htmlspecialchars($s['last_name']);
            $grade = htmlspecialchars($s['grade']);
            $login = htmlspecialchars($s['chromebook_login'] ?? '');
            $pw = htmlspecialchars($s['password'] ?? '');

            $html .= "<div class=\"student-card\">";
            $html .= "<div class=\"name\">{$display} {$last}</div>";
            $html .= "<div class=\"grade\">Grade: {$grade}</div>";
            $html .= "<div class=\"login\">Login: {$login}</div>";
            $html .= "<div class=\"password\">{$pw}</div>";
            $html .= "</div>";
        }

        $html .= '</div></body></html>';

        return $html;
    }
}
