<?php

namespace Hub\Package\StudentDirectory;

use Hub\Package\StudentDirectory\StudentDatabase;
use Hub\Package\StudentDirectory\PasswordGenerator;

/**
 * Student Directory CSV Import Handler
 *
 * Port of the Node.js import.js route.
 * Supports CSV upload with preview, field mapping, and three import modes:
 * - append: Only insert new records (skip existing student_ids)
 * - rewrite: Update existing records and insert new ones
 * - ignore: Skip all existing records completely (same as append but logs differently)
 */
class ImportHandler
{
    /** Fields that can be imported */
    private const IMPORTABLE_FIELDS = [
        'student_id',
        'first_name',
        'last_name',
        'middle_name',
        'nickname',
        'sex',
        'grade',
        'dob',
        'graduation_year',
        'hispanic_latino',
        'white',
        'black_african_american',
        'asian',
        'american_indian_alaskan_native',
        'hawaiian_pacific_isl',
    ];

    /** Required fields for import */
    private const REQUIRED_FIELDS = ['student_id', 'first_name', 'last_name', 'grade'];

    /**
     * Parse CSV file for preview
     *
     * @param string $filePath Path to uploaded CSV file
     * @param int $previewRows Number of rows to return for preview
     * @return array ['headers' => [...], 'preview' => [...], 'total_rows' => int, 'importable_fields' => [...]]
     */
    public static function parseForPreview(string $filePath, int $previewRows = 5): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return ['success' => false, 'message' => 'File not found or not readable'];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['success' => false, 'message' => 'Could not open file'];
        }

        // Read BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Read headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return ['success' => false, 'message' => 'Could not read CSV headers'];
        }

        // Clean headers
        $headers = array_map(function ($h) {
            return trim(strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', trim($h))));
        }, $headers);

        // Read preview rows
        $preview = [];
        $totalRows = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $totalRows++;
            if (count($preview) < $previewRows) {
                $mapped = [];
                foreach ($headers as $i => $header) {
                    $mapped[$header] = $row[$i] ?? '';
                }
                $preview[] = $mapped;
            }
        }

        fclose($handle);

        // Auto-detect field mapping
        $suggestedMapping = self::suggestFieldMapping($headers);

        return [
            'success' => true,
            'headers' => $headers,
            'preview' => $preview,
            'total_rows' => $totalRows,
            'importable_fields' => self::IMPORTABLE_FIELDS,
            'required_fields' => self::REQUIRED_FIELDS,
            'suggested_mapping' => $suggestedMapping,
        ];
    }

    /**
     * Process CSV import
     *
     * @param string $filePath Path to uploaded CSV file
     * @param array $fieldMapping Maps CSV column => student DB field
     * @param string $mode 'append' | 'rewrite' | 'ignore'
     * @param int|null $userId User performing the import (for audit)
     * @return array Import results
     */
    public static function processImport(
        string $filePath,
        array $fieldMapping,
        string $mode = 'append',
        ?int $userId = null
    ): array {
        if (!in_array($mode, ['append', 'rewrite', 'ignore'])) {
            return ['success' => false, 'message' => "Invalid import mode: {$mode}"];
        }

        // Validate required fields are mapped
        $mappedFields = array_values($fieldMapping);
        foreach (self::REQUIRED_FIELDS as $required) {
            if (!in_array($required, $mappedFields)) {
                return ['success' => false, 'message' => "Required field '{$required}' is not mapped"];
            }
        }

        // Parse entire CSV
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['success' => false, 'message' => 'Could not open file'];
        }

        // Skip BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Read headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return ['success' => false, 'message' => 'Could not read CSV headers'];
        }

        $headers = array_map(function ($h) {
            return trim(strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', trim($h))));
        }, $headers);

        $stats = [
            'total' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_details' => [],
        ];

        StudentDatabase::beginTransaction();

        try {
            $rowNum = 1; // 1-indexed (header = 0)
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                $stats['total']++;

                // Map CSV row to student fields
                $mapped = self::mapRow($headers, $row, $fieldMapping);

                // Validate required fields
                $missing = [];
                foreach (self::REQUIRED_FIELDS as $req) {
                    if (empty(trim($mapped[$req] ?? ''))) {
                        $missing[] = $req;
                    }
                }

                if (!empty($missing)) {
                    $stats['errors']++;
                    $stats['error_details'][] = "Row {$rowNum}: Missing " . implode(', ', $missing);
                    continue;
                }

                $studentId = trim($mapped['student_id']);
                $exists = (int) StudentDatabase::fetchColumn(
                    "SELECT COUNT(*) FROM students WHERE student_id = :id",
                    ['id' => $studentId]
                ) > 0;

                if ($exists) {
                    if ($mode === 'rewrite') {
                        self::updateFromImport($studentId, $mapped);
                        $stats['updated']++;
                    } else {
                        $stats['skipped']++;
                    }
                } else {
                    self::insertFromImport($mapped);
                    $stats['inserted']++;
                }
            }

            // Log import history
            self::logImport($stats, $mode, $userId);

            StudentDatabase::commit();
        } catch (\Exception $e) {
            StudentDatabase::rollBack();
            fclose($handle);
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'stats' => $stats,
            ];
        }

        fclose($handle);

        return [
            'success' => true,
            'message' => sprintf(
                'Import complete: %d processed, %d inserted, %d updated, %d skipped, %d errors',
                $stats['total'],
                $stats['inserted'],
                $stats['updated'],
                $stats['skipped'],
                $stats['errors']
            ),
            'stats' => $stats,
        ];
    }

    /**
     * Map a CSV row to student fields using the field mapping
     */
    private static function mapRow(array $headers, array $row, array $fieldMapping): array
    {
        $mapped = [];
        foreach ($fieldMapping as $csvCol => $dbField) {
            if (!$dbField || $dbField === 'skip') {
                continue;
            }
            $colIndex = array_search($csvCol, $headers);
            if ($colIndex !== false && isset($row[$colIndex])) {
                $mapped[$dbField] = trim($row[$colIndex]);
            }
        }
        return $mapped;
    }

    /**
     * Insert a new student from import data
     */
    private static function insertFromImport(array $mapped): void
    {
        $grade = strtoupper(trim($mapped['grade']));
        $firstName = trim($mapped['first_name']);
        $lastName = trim($mapped['last_name']);
        $nickname = trim($mapped['nickname'] ?? '');
        $dob = trim($mapped['dob'] ?? '') ?: null;

        $graduationYear = !empty($mapped['graduation_year'])
            ? (int) $mapped['graduation_year']
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

        $record = [
            'student_id' => trim($mapped['student_id']),
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
            'middle_name' => trim($mapped['middle_name'] ?? '') ?: null,
            'sex' => trim($mapped['sex'] ?? '') ?: null,
            'hispanic_latino' => (int) ($mapped['hispanic_latino'] ?? 0),
            'white' => (int) ($mapped['white'] ?? 0),
            'black_african_american' => (int) ($mapped['black_african_american'] ?? 0),
            'asian' => (int) ($mapped['asian'] ?? 0),
            'american_indian_alaskan_native' => (int) ($mapped['american_indian_alaskan_native'] ?? 0),
            'hawaiian_pacific_isl' => (int) ($mapped['hawaiian_pacific_isl'] ?? 0),
        ];

        $columns = implode(', ', array_keys($record));
        $placeholders = ':' . implode(', :', array_keys($record));

        StudentDatabase::execute(
            "INSERT INTO students ({$columns}) VALUES ({$placeholders})",
            $record
        );
    }

    /**
     * Update an existing student from import data
     */
    private static function updateFromImport(string $studentId, array $mapped): void
    {
        $updates = [];
        $binds = ['id' => $studentId];

        // Update provided fields
        $updatableFields = [
            'first_name', 'last_name', 'middle_name', 'nickname', 'sex', 'dob',
            'hispanic_latino', 'white', 'black_african_american',
            'asian', 'american_indian_alaskan_native', 'hawaiian_pacific_isl',
        ];

        foreach ($updatableFields as $field) {
            if (isset($mapped[$field]) && $mapped[$field] !== '') {
                $value = trim($mapped[$field]);
                if (in_array($field, ['hispanic_latino', 'white', 'black_african_american', 'asian', 'american_indian_alaskan_native', 'hawaiian_pacific_isl'])) {
                    $value = (int) $value;
                }
                $updates[] = "{$field} = :{$field}";
                $binds[$field] = $value;
            }
        }

        // Handle grade change with cascading updates
        if (isset($mapped['grade']) && $mapped['grade'] !== '') {
            $newGrade = strtoupper(trim($mapped['grade']));
            $updates[] = "grade = :grade";
            $binds['grade'] = $newGrade;

            $gradYear = !empty($mapped['graduation_year'])
                ? (int) $mapped['graduation_year']
                : PasswordGenerator::calculateGraduationYear($newGrade);

            $updates[] = "graduation_year = :graduation_year";
            $binds['graduation_year'] = $gradYear;

            $updates[] = "ou_for_google = :ou_for_google";
            $binds['ou_for_google'] = PasswordGenerator::determineOU($newGrade);

            // Regenerate password
            $firstName = $mapped['first_name'] ?? '';
            $lastName = $mapped['last_name'] ?? '';
            $dob = $mapped['dob'] ?? null;

            if ($firstName && $lastName) {
                $password = PasswordGenerator::generateByGrade(
                    $newGrade,
                    $firstName,
                    $lastName,
                    $gradYear,
                    $dob
                );
                $updates[] = "password = :password";
                $binds['password'] = $password;
            }

            // Regenerate group email
            $updates[] = "group_email = :group_email";
            $binds['group_email'] = PasswordGenerator::generateGroupEmail($gradYear);
        }

        if (!empty($updates)) {
            $updates[] = "updated_at = NOW()";
            $sql = "UPDATE students SET " . implode(', ', $updates) . " WHERE student_id = :id";
            StudentDatabase::execute($sql, $binds);
        }
    }

    /**
     * Log import to import_history table
     */
    private static function logImport(array $stats, string $mode, ?int $userId): void
    {
        // Check if import_history table exists
        try {
            StudentDatabase::execute(
                "INSERT INTO import_history (mode, total_rows, inserted, updated, skipped, errors, user_id, created_at)
                 VALUES (:mode, :total, :inserted, :updated, :skipped, :errors, :user_id, NOW())",
                [
                    'mode' => $mode,
                    'total' => $stats['total'],
                    'inserted' => $stats['inserted'],
                    'updated' => $stats['updated'],
                    'skipped' => $stats['skipped'],
                    'errors' => $stats['errors'],
                    'user_id' => $userId,
                ]
            );
        } catch (\Exception $e) {
            // import_history table might not have all columns; silently continue
        }
    }

    /**
     * Suggest field mapping based on CSV header names
     */
    private static function suggestFieldMapping(array $headers): array
    {
        $mapping = [];
        $aliases = [
            'student_id' => ['student_id', 'studentid', 'id', 'student_number', 'sid'],
            'first_name' => ['first_name', 'firstname', 'first', 'fname', 'given_name'],
            'last_name' => ['last_name', 'lastname', 'last', 'lname', 'surname', 'family_name'],
            'nickname' => ['nickname', 'nick', 'preferred_name', 'preferred', 'goes_by'],
            'middle_name' => ['middle_name', 'middlename', 'middle', 'mname'],
            'sex' => ['sex', 'gender'],
            'grade' => ['grade', 'grade_level', 'gradelevel', 'gr'],
            'dob' => ['dob', 'date_of_birth', 'dateofbirth', 'birthday', 'birth_date', 'birthdate'],
            'graduation_year' => ['graduation_year', 'graduationyear', 'grad_year', 'gradyear', 'class_of'],
            'hispanic_latino' => ['hispanic_latino', 'hispanic', 'latino', 'ethnicity'],
            'american_indian_alaskan_native' => ['american_indian_alaskan_native', 'american_indian', 'native_american'],
            'asian' => ['asian'],
            'black_african_american' => ['black_african_american', 'black', 'african_american'],
            'hawaiian_pacific_isl' => ['hawaiian_pacific_isl', 'pacific_islander', 'hawaiian'],
            'white' => ['white', 'caucasian'],
        ];

        foreach ($headers as $header) {
            $cleaned = strtolower(preg_replace('/[^a-z0-9]/', '_', $header));
            $mapped = false;

            foreach ($aliases as $field => $aliasList) {
                if (in_array($cleaned, $aliasList)) {
                    $mapping[$header] = $field;
                    $mapped = true;
                    break;
                }
            }

            if (!$mapped) {
                $mapping[$header] = 'skip';
            }
        }

        return $mapping;
    }
}
