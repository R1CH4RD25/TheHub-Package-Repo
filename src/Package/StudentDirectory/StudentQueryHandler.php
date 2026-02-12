<?php

namespace Hub\Package\StudentDirectory;

use Hub\Package\StudentDirectory\StudentDatabase;

/**
 * Student Directory Query Handler
 *
 * Handles all read operations against the woodson_students database.
 * Provides student listing with search/filter/paginate, detail views,
 * statistics, and metadata queries.
 */
class StudentQueryHandler
{
    /**
     * List students with search, filtering, and pagination
     *
     * @param array $params Query parameters:
     *   - search: string (name, student_id, email search)
     *   - grade: string (PK, KG, 1-12)
     *   - graduation_year: int
     *   - page: int (default 1)
     *   - per_page: int (default 50, max 200)
     *   - sort: string (default 'grade')
     *   - direction: string (ASC|DESC, default ASC)
     * @return array ['data' => [...], 'pagination' => [...]]
     */
    public static function listStudents(array $params = []): array
    {
        $db = StudentDatabase::getConnection();

        $search = trim($params['search'] ?? '');
        $grade = trim($params['grade'] ?? '');
        $gradYear = (int) ($params['graduation_year'] ?? 0);
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($params['per_page'] ?? 50)));
        $sort = $params['sort'] ?? 'grade';
        $direction = strtoupper($params['direction'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        // Build WHERE clauses
        $where = [];
        $binds = [];

        if ($search !== '') {
            $where[] = "(
                last_name LIKE :search
                OR first_name LIKE :search2
                OR student_id LIKE :search3
                OR chromebook_login LIKE :search4
                OR CONCAT(first_name, ' ', last_name) LIKE :search5
            )";
            $searchVal = "%{$search}%";
            $binds['search'] = $searchVal;
            $binds['search2'] = $searchVal;
            $binds['search3'] = $searchVal;
            $binds['search4'] = $searchVal;
            $binds['search5'] = $searchVal;
        }

        if ($grade !== '') {
            $where[] = "grade = :grade";
            $binds['grade'] = $grade;
        }

        if ($gradYear > 0) {
            $where[] = "graduation_year = :grad_year";
            $binds['grad_year'] = $gradYear;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Sort with custom grade ordering
        $sortClause = self::buildSortClause($sort, $direction);

        // Count total
        $countSql = "SELECT COUNT(*) FROM students {$whereClause}";
        $total = (int) StudentDatabase::fetchColumn($countSql, $binds);

        // Fetch page
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT student_id, grade, first_name, last_name, nickname,
                       CONCAT(first_name, ' ', last_name) AS full_name,
                       chromebook_login, password, group_email, graduation_year,
                       ou_for_google, created_at, updated_at
                FROM students
                {$whereClause}
                {$sortClause}
                LIMIT {$perPage} OFFSET {$offset}";

        $rows = StudentDatabase::fetchAll($sql, $binds);

        return [
            'data' => $rows,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total,
            ],
        ];
    }

    /**
     * Get a single student by ID
     *
     * @param string $studentId The student_id
     * @param bool $includeSensitive Whether to include password field
     * @return array|null
     */
    public static function getStudent(string $studentId, bool $includeSensitive = false): ?array
    {
        $fields = $includeSensitive
            ? '*'
            : 'student_id, grade, first_name, last_name, middle_name, nickname, sex, dob,
               chromebook_login, group_email, password, graduation_year,
               ou_for_google,
               hispanic_latino, white, black_african_american,
               asian, american_indian_alaskan_native, hawaiian_pacific_isl,
               created_at, updated_at';

        $sql = "SELECT {$fields} FROM students WHERE student_id = :id";
        $row = StudentDatabase::fetchOne($sql, ['id' => $studentId]);

        return $row ?: null;
    }

    /**
     * Get summary statistics
     *
     * @return array ['total', 'elementary', 'junior_high', 'high_school', 'by_grade']
     */
    public static function getStats(): array
    {
        $total = (int) StudentDatabase::fetchColumn("SELECT COUNT(*) FROM students");

        $byGrade = StudentDatabase::fetchAll(
            "SELECT grade,
                    COUNT(*) AS count,
                    " . self::gradeOrderExpression() . " AS sort_order
             FROM students
             GROUP BY grade
             ORDER BY sort_order"
        );

        // Categorize into school levels: PK-5=Elementary, 6-8=Junior High, 9-12=High School
        $elementary = 0;
        $juniorHigh = 0;
        $highSchool = 0;

        foreach ($byGrade as $row) {
            $g = strtoupper($row['grade']);
            $count = (int) $row['count'];

            if (in_array($g, ['PK', 'KG', 'K', '1', '2', '3', '4', '5'])) {
                $elementary += $count;
            } elseif (in_array($g, ['6', '7', '8'])) {
                $juniorHigh += $count;
            } elseif (in_array($g, ['9', '10', '11', '12'])) {
                $highSchool += $count;
            }
        }

        return [
            'total' => $total,
            'elementary' => $elementary,
            'junior_high' => $juniorHigh,
            'high_school' => $highSchool,
            'by_grade' => $byGrade,
        ];
    }

    /**
     * Get distinct grade values
     *
     * @return array List of grades sorted PK, KG, 1-12
     */
    public static function getGrades(): array
    {
        return StudentDatabase::fetchAll(
            "SELECT DISTINCT grade,
                    " . self::gradeOrderExpression() . " AS sort_order
             FROM students
             ORDER BY sort_order"
        );
    }

    /**
     * Get distinct graduation years
     *
     * @return array List of graduation years sorted ascending
     */
    public static function getGraduationYears(): array
    {
        return StudentDatabase::fetchAll(
            "SELECT DISTINCT graduation_year
             FROM students
             WHERE graduation_year IS NOT NULL AND graduation_year > 0
             ORDER BY graduation_year ASC"
        );
    }

    /**
     * Get import history
     *
     * @param int $limit
     * @return array
     */
    public static function getImportHistory(int $limit = 20): array
    {
        return StudentDatabase::fetchAll(
            "SELECT * FROM import_history
             ORDER BY created_at DESC
             LIMIT :limit",
            ['limit' => $limit]
        );
    }

    /**
     * Check if a student_id exists
     */
    public static function studentExists(string $studentId): bool
    {
        $count = (int) StudentDatabase::fetchColumn(
            "SELECT COUNT(*) FROM students WHERE student_id = :id",
            ['id' => $studentId]
        );
        return $count > 0;
    }

    /**
     * Search students by name (for autocomplete)
     */
    public static function searchStudents(string $query, int $limit = 10): array
    {
        $sql = "SELECT student_id, first_name, last_name, grade, graduation_year
                FROM students
                WHERE first_name LIKE :q1
                   OR last_name LIKE :q2
                   OR CONCAT(first_name, ' ', last_name) LIKE :q3
                   OR student_id LIKE :q4
                ORDER BY last_name, first_name
                LIMIT :limit";

        return StudentDatabase::fetchAll($sql, [
            'q1' => "%{$query}%",
            'q2' => "%{$query}%",
            'q3' => "%{$query}%",
            'q4' => "%{$query}%",
            'limit' => $limit,
        ]);
    }

    /**
     * Build sort clause with custom grade ordering
     */
    private static function buildSortClause(string $sort, string $direction): string
    {
        $allowed = [
            'last_name',
            'first_name',
            'student_id',
            'grade',
            'graduation_year',
            'chromebook_login',
            'created_at',
            'updated_at',
        ];

        if ($sort === 'grade') {
            return "ORDER BY " . self::gradeOrderExpression() . " {$direction}, last_name ASC";
        }

        if (!in_array($sort, $allowed)) {
            $sort = 'last_name';
        }

        return "ORDER BY {$sort} {$direction}";
    }

    /**
     * MySQL CASE expression for sorting grades in natural order:
     * PK=0, KG=1, then numeric grades 1-12 = 2-13
     */
    private static function gradeOrderExpression(): string
    {
        return "CASE
            WHEN grade = 'PK' THEN 0
            WHEN grade IN ('KG', 'K') THEN 1
            ELSE CAST(grade AS UNSIGNED) + 1
        END";
    }
}
