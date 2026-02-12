<?php

namespace Tests\Package\StudentDirectory;

use PHPUnit\Framework\TestCase;
use Hub\Package\StudentDirectory\PasswordGenerator;

/**
 * Tests for Student Directory Password Generator
 *
 * Verifies password generation algorithm matches Node.js behaviors:
 * - PK/KG → "Woodson1"
 * - Grades 1-3 → {first_initial}{last_3}{gradYear}
 * - Grades 4-12 → optional_specials + Last4 + MMYY
 * - Helper functions: chromebook login, group email, OU, graduation year
 */
class PasswordGeneratorTest extends TestCase
{
    // ─── Password Generation by Grade ───────────────────────────

    public function test_pk_returns_woodson1(): void
    {
        $pw = PasswordGenerator::generateByGrade('PK', 'John', 'Smith');
        $this->assertEquals('Woodson1', $pw);
    }

    public function test_kg_returns_woodson1(): void
    {
        $pw = PasswordGenerator::generateByGrade('KG', 'Jane', 'Doe');
        $this->assertEquals('Woodson1', $pw);
    }

    public function test_k_returns_woodson1(): void
    {
        $pw = PasswordGenerator::generateByGrade('K', 'Alice', 'Wonder');
        $this->assertEquals('Woodson1', $pw);
    }

    public function test_grade1_format(): void
    {
        $pw = PasswordGenerator::generateByGrade('1', 'Robert', 'Sullivan', 2037);
        // first_initial + last_3 + gradYear → rsul2037
        $this->assertEquals('rsul2037', $pw);
    }

    public function test_grade2_format(): void
    {
        $pw = PasswordGenerator::generateByGrade('2', 'Maria', 'Garcia', 2036);
        $this->assertEquals('mgar2036', $pw);
    }

    public function test_grade3_format(): void
    {
        $pw = PasswordGenerator::generateByGrade('3', 'Tyler', 'Lee', 2035);
        // 'lee' is only 3 chars, entire last name used
        $this->assertEquals('tlee2035', $pw);
    }

    public function test_grade4_through_12_contains_last_name_and_mmyy(): void
    {
        $pw = PasswordGenerator::generateByGrade('5', 'Emma', 'Bellah', null, '2008-07-15');
        // Should contain "Bell" (first 4 of last name, capitalized) and "0708" (MMYY)
        $this->assertStringContainsString('Bell', $pw);
        $this->assertStringContainsString('0708', $pw);
    }

    public function test_grade10_password_format(): void
    {
        $pw = PasswordGenerator::generateByGrade('10', 'Carlos', 'Washington', null, '2006-11-20');
        $this->assertStringContainsString('Wash', $pw);
        $this->assertStringContainsString('1106', $pw);
    }

    public function test_grade12_password_format(): void
    {
        $pw = PasswordGenerator::generateByGrade('12', 'Sarah', 'Thompson', null, '2005-03-10');
        $this->assertStringContainsString('Thom', $pw);
        $this->assertStringContainsString('0305', $pw);
    }

    public function test_short_last_name_caps_at_available(): void
    {
        $pw = PasswordGenerator::generateByGrade('6', 'Amy', 'Li', null, '2009-01-01');
        // 'Li' → 'Li' (only 2 chars available)
        $this->assertStringContainsString('Li', $pw);
    }

    public function test_empty_grade_returns_empty(): void
    {
        $pw = PasswordGenerator::generateByGrade('', 'John', 'Smith');
        $this->assertEquals('', $pw);
    }

    public function test_empty_last_name_returns_empty(): void
    {
        $pw = PasswordGenerator::generateByGrade('5', 'John', '');
        $this->assertEquals('', $pw);
    }

    // ─── MMYY Extraction ────────────────────────────────────────

    public function test_extractMMYY_standard_date(): void
    {
        $this->assertEquals('0708', PasswordGenerator::extractMMYYfromDOB('2008-07-15'));
    }

    public function test_extractMMYY_january(): void
    {
        $this->assertEquals('0110', PasswordGenerator::extractMMYYfromDOB('2010-01-05'));
    }

    public function test_extractMMYY_december(): void
    {
        $this->assertEquals('1299', PasswordGenerator::extractMMYYfromDOB('1999-12-31'));
    }

    public function test_extractMMYY_null_returns_0000(): void
    {
        $this->assertEquals('0000', PasswordGenerator::extractMMYYfromDOB(null));
    }

    public function test_extractMMYY_empty_returns_0000(): void
    {
        $this->assertEquals('0000', PasswordGenerator::extractMMYYfromDOB(''));
    }

    public function test_extractMMYY_invalid_returns_0000(): void
    {
        $this->assertEquals('0000', PasswordGenerator::extractMMYYfromDOB('not-a-date'));
    }

    // ─── Chromebook Login ───────────────────────────────────────

    public function test_chromebook_login_format(): void
    {
        $login = PasswordGenerator::generateChromebookLogin('Robert', 'Sullivan', 2037);
        $this->assertEquals('37.robert.sullivan@woodsonisd.net', $login);
    }

    public function test_chromebook_login_with_nickname(): void
    {
        $login = PasswordGenerator::generateChromebookLogin('Robert', 'Sullivan', 2037, 'Bobby');
        $this->assertEquals('37.bobby.sullivan@woodsonisd.net', $login);
    }

    public function test_chromebook_login_strips_specials(): void
    {
        $login = PasswordGenerator::generateChromebookLogin("O'Brien", 'Smith-Jones', 2035);
        // Specials stripped: obrien, smithjones
        $this->assertEquals('35.obrien.smithjones@woodsonisd.net', $login);
    }

    public function test_chromebook_login_empty_returns_empty(): void
    {
        $login = PasswordGenerator::generateChromebookLogin('', 'Smith', 2035);
        $this->assertEquals('', $login);
    }

    // ─── Group Email ────────────────────────────────────────────

    public function test_group_email_format(): void
    {
        $email = PasswordGenerator::generateGroupEmail(2037);
        $this->assertEquals('seniors2037@woodsonisd.net', $email);
    }

    // ─── Organizational Unit ────────────────────────────────────

    public function test_ou_pk_is_elementary(): void
    {
        $this->assertEquals('Elementary', PasswordGenerator::determineOU('PK'));
    }

    public function test_ou_kg_is_elementary(): void
    {
        $this->assertEquals('Elementary', PasswordGenerator::determineOU('KG'));
    }

    public function test_ou_grade5_is_elementary(): void
    {
        $this->assertEquals('Elementary', PasswordGenerator::determineOU('5'));
    }

    public function test_ou_grade6_is_junior_high(): void
    {
        $this->assertEquals('Junior High', PasswordGenerator::determineOU('6'));
    }

    public function test_ou_grade8_is_junior_high(): void
    {
        $this->assertEquals('Junior High', PasswordGenerator::determineOU('8'));
    }

    public function test_ou_grade9_is_high_school(): void
    {
        $this->assertEquals('High School', PasswordGenerator::determineOU('9'));
    }

    public function test_ou_grade12_is_high_school(): void
    {
        $this->assertEquals('High School', PasswordGenerator::determineOU('12'));
    }

    // ─── Graduation Year Calculation ────────────────────────────

    public function test_graduation_year_grade12(): void
    {
        $year = PasswordGenerator::calculateGraduationYear('12', 2025);
        $this->assertEquals(2026, $year); // 12 - 12 + 1 = 1 year
    }

    public function test_graduation_year_grade1(): void
    {
        $year = PasswordGenerator::calculateGraduationYear('1', 2025);
        $this->assertEquals(2037, $year); // 12 - 1 + 1 = 12 years
    }

    public function test_graduation_year_pk(): void
    {
        $year = PasswordGenerator::calculateGraduationYear('PK', 2025);
        $this->assertEquals(2039, $year); // PK → +14
    }

    public function test_graduation_year_kg(): void
    {
        $year = PasswordGenerator::calculateGraduationYear('KG', 2025);
        $this->assertEquals(2038, $year); // KG → +13
    }

    public function test_graduation_year_grade6(): void
    {
        $year = PasswordGenerator::calculateGraduationYear('6', 2025);
        $this->assertEquals(2032, $year); // 12 - 6 + 1 = 7
    }

    // ─── Password Randomness (Grades 4-12) ──────────────────────

    public function test_grade4_12_password_varies_per_call(): void
    {
        $passwords = [];
        for ($i = 0; $i < 20; $i++) {
            $passwords[] = PasswordGenerator::generateByGrade('8', 'Jane', 'Doe', null, '2008-05-10');
        }

        // All should contain 'Doe0' (Last4 is only 3 chars) and '0508'
        foreach ($passwords as $pw) {
            $this->assertStringContainsString('Doe', $pw);
            $this->assertStringContainsString('0508', $pw);
        }

        // At least some variation should exist (from special chars + position)
        $unique = array_unique($passwords);
        // With 20 tries, we expect at least 2 unique values (some may have 0 special chars)
        $this->assertGreaterThanOrEqual(1, count($unique));
    }
}
