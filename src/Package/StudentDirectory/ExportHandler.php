<?php

namespace Hub\Package\StudentDirectory;

use Hub\Package\StudentDirectory\StudentDatabase;

/**
 * Student Directory Export Handler
 *
 * Generates CSV exports in two formats:
 * 1. Standard CSV: All student data
 * 2. Google Workspace CSV: Formatted for Google Admin bulk upload
 */
class ExportHandler
{
    /**
     * Export students as standard CSV
     *
     * @param array $filters Optional filters (grade, graduation_year)
     * @return array ['success', 'csv', 'filename', 'count']
     */
    public static function exportStandardCsv(array $filters = []): array
    {
        $students = self::getFilteredStudents($filters);

        if (empty($students)) {
            return ['success' => false, 'message' => 'No students found for export'];
        }

        $headers = [
            'Student ID', 'Grade', 'First Name', 'Last Name', 'Middle Name',
            'Nickname', 'Sex', 'Date of Birth', 'Chromebook Login', 'Password',
            'Group Email', 'Graduation Year', 'OU for Google',
            'Hispanic/Latino', 'White', 'Black/African American',
            'Asian', 'American Indian/Alaskan Native', 'Hawaiian/Pacific Isl',
            'Created', 'Updated',
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        foreach ($students as $s) {
            fputcsv($output, [
                $s['student_id'],
                $s['grade'],
                $s['first_name'],
                $s['last_name'],
                $s['middle_name'] ?? '',
                $s['nickname'] ?? '',
                $s['sex'] ?? '',
                $s['dob'] ?? '',
                $s['chromebook_login'] ?? '',
                $s['password'] ?? '',
                $s['group_email'] ?? '',
                $s['graduation_year'] ?? '',
                $s['ou_for_google'] ?? '',
                $s['hispanic_latino'] ?? 0,
                $s['white'] ?? 0,
                $s['black_african_american'] ?? 0,
                $s['asian'] ?? 0,
                $s['american_indian_alaskan_native'] ?? 0,
                $s['hawaiian_pacific_isl'] ?? 0,
                $s['created_at'] ?? '',
                $s['updated_at'] ?? '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $grade = $filters['grade'] ?? 'all';
        $filename = "students_{$grade}_" . date('Y-m-d') . '.csv';

        return [
            'success' => true,
            'csv' => $csv,
            'filename' => $filename,
            'count' => count($students),
            'content_type' => 'text/csv',
        ];
    }

    /**
     * Export students as Google Workspace CSV
     *
     * Format matches Google Admin Console's "Add multiple users" template:
     * First Name, Last Name, Email Address, Password, Org Unit Path, ...
     *
     * @param array $filters Optional filters
     * @return array
     */
    public static function exportGoogleCsv(array $filters = []): array
    {
        $students = self::getFilteredStudents($filters);

        if (empty($students)) {
            return ['success' => false, 'message' => 'No students found for export'];
        }

        $headers = [
            'First Name [Required]',
            'Last Name [Required]',
            'Email Address [Required]',
            'Password [Required]',
            'Password Hash Function [UPLOAD ONLY]',
            'Org Unit Path [Required]',
            'New Primary Email [UPLOAD ONLY]',
            'Recovery Email',
            'Home Secondary Email',
            'Work Secondary Email',
            'Recovery Phone [MUST BE IN THE E.164 FORMAT]',
            'Work Phone',
            'Home Phone',
            'Mobile Phone',
            'Work Address',
            'Home Address',
            'Employee ID',
            'Employee Type',
            'Employee Title',
            'Manager Email',
            'Department',
            'Cost Center',
            'Building ID',
            'Floor Name',
            'Floor Section',
            'Change Password at Next Sign-In',
            'New Status [UPLOAD ONLY]',
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);

        foreach ($students as $s) {
            $ouPath = '/Students/' . ($s['ou_for_google'] ?? 'Elementary');
            $email = $s['chromebook_login'] ?? '';

            fputcsv($output, [
                $s['first_name'],                   // First Name
                $s['last_name'],                     // Last Name
                $email,                              // Email Address
                $s['password'] ?? '',                // Password
                '',                                  // Password Hash Function
                $ouPath,                             // Org Unit Path
                '',                                  // New Primary Email
                '',                                  // Recovery Email
                '',                                  // Home Secondary Email
                '',                                  // Work Secondary Email
                '',                                  // Recovery Phone
                '',                                  // Work Phone
                '',                                  // Home Phone
                '',                                  // Mobile Phone
                '',                                  // Work Address
                '',                                  // Home Address
                $s['student_id'],                    // Employee ID
                'Student',                           // Employee Type
                'Grade ' . $s['grade'],              // Employee Title
                '',                                  // Manager Email
                $s['ou_for_google'] ?? '',           // Department
                '',                                  // Cost Center
                '',                                  // Building ID
                '',                                  // Floor Name
                '',                                  // Floor Section
                'TRUE',                              // Change Password at Next Sign-In
                '',                                  // New Status
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $grade = $filters['grade'] ?? 'all';
        $filename = "google_workspace_{$grade}_" . date('Y-m-d') . '.csv';

        return [
            'success' => true,
            'csv' => $csv,
            'filename' => $filename,
            'count' => count($students),
            'content_type' => 'text/csv',
        ];
    }

    /**
     * Get filtered students for export
     */
    private static function getFilteredStudents(array $filters): array
    {
        $where = [];
        $binds = [];

        if (!empty($filters['grade'])) {
            $where[] = "grade = :grade";
            $binds['grade'] = strtoupper(trim($filters['grade']));
        }

        if (!empty($filters['graduation_year'])) {
            $where[] = "graduation_year = :grad_year";
            $binds['grad_year'] = (int) $filters['graduation_year'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $gradeOrder = "CASE
            WHEN grade = 'PK' THEN 0
            WHEN grade IN ('KG', 'K') THEN 1
            ELSE CAST(grade AS UNSIGNED) + 1
        END";

        $sql = "SELECT * FROM students {$whereClause} ORDER BY {$gradeOrder}, last_name, first_name";

        return StudentDatabase::fetchAll($sql, $binds);
    }
}
