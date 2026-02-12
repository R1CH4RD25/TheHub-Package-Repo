<?php

namespace Hub\Package\StudentDirectory;

/**
 * Password Generator
 *
 * Port of the Node.js passwordGenerator.js utility.
 * Generates student passwords based on grade level and personal data.
 *
 * Format rules (from Woodson ISD Google Sheets logic):
 * - PK & KG: "Woodson1" (simple default)
 * - Grades 1-3: {first_initial}{last_3_chars}{graduation_year} (e.g., rsul2037)
 * - Grades 4-12: [0-2 special chars] + [First 4 of last name, capitalized] + [MMYY from DOB]
 *   Example: #Bell0708 for Bellah born July 2008
 */
class PasswordGenerator
{
    private const SPECIAL_CHARS = ['!', '@', '#', '$', '%', '&', '*'];

    /**
     * Generate password based on grade
     */
    public static function generateByGrade(
        string $grade,
        string $firstName,
        string $lastName,
        ?int $graduationYear = null,
        ?string $dob = null
    ): string {
        if (!$grade || !$lastName) {
            return '';
        }

        $grade = strtoupper(trim($grade));

        // PK & KG: Simple default
        if (in_array($grade, ['PK', 'KG', 'K'])) {
            return 'Woodson1';
        }

        // Grades 1-3: Simplified format
        if (in_array($grade, ['1', '2', '3'])) {
            $first = $firstName ? strtolower(substr($firstName, 0, 1)) : '';
            $last = strtolower(substr($lastName, 0, 3));
            $year = (string) ($graduationYear ?? '');
            return $first . $last . $year;
        }

        // Grades 4-12: Complex format with special chars
        $lastNamePart = substr($lastName, 0, 4);
        $lastNamePart = ucfirst(strtolower($lastNamePart));

        $mmyy = self::extractMMYYfromDOB($dob);

        // Randomly decide: 0, 1, or 2 special chars; prefix or suffix
        $numSpecialChars = random_int(0, 2);
        $position = random_int(0, 1) === 0 ? 'prefix' : 'suffix';

        if ($numSpecialChars === 0) {
            return $lastNamePart . $mmyy;
        }

        $specialChars = self::getRandomSpecialChars($numSpecialChars);

        return $position === 'prefix'
            ? $specialChars . $lastNamePart . $mmyy
            : $lastNamePart . $mmyy . $specialChars;
    }

    /**
     * Extract MMYY from date of birth
     *
     * @param string|null $dob Date of birth string (YYYY-MM-DD or M/D/YYYY)
     * @return string 4-digit MMYY (e.g., "0708" for July 2008)
     */
    public static function extractMMYYfromDOB(?string $dob): string
    {
        if (!$dob) {
            return '0000';
        }

        $timestamp = strtotime($dob);
        if ($timestamp === false) {
            return '0000';
        }

        $month = date('m', $timestamp);
        $year = substr(date('Y', $timestamp), -2);

        return $month . $year;
    }

    /**
     * Generate Chromebook login email
     */
    public static function generateChromebookLogin(
        string $firstName,
        string $lastName,
        int $graduationYear,
        ?string $nickname = null
    ): string {
        if (!$firstName || !$lastName || !$graduationYear) {
            return '';
        }

        $yearShort = substr((string) $graduationYear, -2);
        $name = strtolower(preg_replace('/[^a-z]/i', '', $nickname ?: $firstName));
        $last = strtolower(preg_replace('/[^a-z]/i', '', $lastName));

        return "{$yearShort}.{$name}.{$last}@woodsonisd.net";
    }

    /**
     * Generate group email based on graduation year
     */
    public static function generateGroupEmail(int $graduationYear): string
    {
        return "seniors{$graduationYear}@woodsonisd.net";
    }

    /**
     * Determine organizational unit based on grade
     */
    public static function determineOU(string $grade): string
    {
        $grade = strtoupper(trim($grade));

        if (in_array($grade, ['9', '10', '11', '12'])) {
            return 'High School';
        }

        if (in_array($grade, ['6', '7', '8'])) {
            return 'Junior High';
        }

        return 'Elementary';
    }

    /**
     * Calculate graduation year from grade
     */
    public static function calculateGraduationYear(string $grade, ?int $currentYear = null): int
    {
        if (!$currentYear) {
            $currentYear = (int) date('Y');
            $currentMonth = (int) date('n');
            // School year starts in August
            if ($currentMonth < 8) {
                $currentYear--;
            }
        }

        $grade = strtoupper(trim($grade));

        if ($grade === 'PK') return $currentYear + 14;
        if (in_array($grade, ['KG', 'K'])) return $currentYear + 13;

        $gradeNum = (int) $grade;
        if ($gradeNum >= 1 && $gradeNum <= 12) {
            return $currentYear + (12 - $gradeNum) + 1;
        }

        return $currentYear;
    }

    /**
     * Get random special characters
     */
    private static function getRandomSpecialChars(int $count): string
    {
        $chars = '';
        for ($i = 0; $i < $count; $i++) {
            $chars .= self::SPECIAL_CHARS[array_rand(self::SPECIAL_CHARS)];
        }
        return $chars;
    }
}
