<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\Database;

class SectionConfigTest extends TestCase
{
    private $db;
    private $testSectionId;

    protected function setUp(): void
    {
        $this->db = Database::getInstance();

        // Ensure test category exists
        $stmt = $this->db->prepare("SELECT id FROM section_categories WHERE id = 1");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $this->db->prepare("
                INSERT INTO section_categories (id, name, display_name, icon, description)
                VALUES (1, 'Test Category', 'Test Category', 'fa-test', 'Test')
            ")->execute();
        }

        // Create a test section
        $stmt = $this->db->prepare("
            INSERT INTO sections (name, display_name, slug, icon, base_url, category_id, is_active)
            VALUES (:name, :display_name, :slug, :icon, :base_url, :category_id, 1)
        ");
        $stmt->execute([
            'name' => 'Test Section',
            'display_name' => 'Test Section',
            'slug' => 'test-section-' . time(),
            'icon' => 'fa-test',
            'base_url' => '/test',
            'category_id' => 1
        ]);

        $this->testSectionId = $this->db->lastInsertId();
    }    protected function tearDown(): void
    {
        // Clean up test data
        if ($this->testSectionId) {
            $this->db->prepare("DELETE FROM section_submission_permissions WHERE section_id = ?")->execute([$this->testSectionId]);
            $this->db->prepare("DELETE FROM section_review_permissions WHERE section_id = ?")->execute([$this->testSectionId]);
            $this->db->prepare("DELETE FROM section_notification_rules WHERE section_id = ?")->execute([$this->testSectionId]);
            $this->db->prepare("DELETE FROM section_guidelines WHERE section_id = ?")->execute([$this->testSectionId]);
            $this->db->prepare("DELETE FROM sections WHERE id = ?")->execute([$this->testSectionId]);
        }
    }

    public function testSubmissionPermissionsCanBeSaved()
    {
        // Insert submission permissions
        $stmt = $this->db->prepare("
            INSERT INTO section_submission_permissions
            (section_id, role_name, can_submit, allow_anonymous)
            VALUES (:section_id, :role_name, :can_submit, :allow_anonymous)
        ");

        $stmt->execute([
            'section_id' => $this->testSectionId,
            'role_name' => 'student',
            'can_submit' => 1,
            'allow_anonymous' => 0
        ]);

        // Verify it was saved
        $stmt = $this->db->prepare("
            SELECT * FROM section_submission_permissions
            WHERE section_id = ? AND role_name = 'student'
        ");
        $stmt->execute([$this->testSectionId]);
        $perm = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($perm, 'Permission should be saved');
        $this->assertEquals(1, $perm['can_submit']);
        $this->assertEquals(0, $perm['allow_anonymous']);
    }

    public function testReviewPermissionsCanBeSaved()
    {
        // Insert review permissions
        $stmt = $this->db->prepare("
            INSERT INTO section_review_permissions
            (section_id, role_name, can_view, can_edit, can_delete)
            VALUES (:section_id, :role_name, :can_view, :can_edit, :can_delete)
        ");

        $stmt->execute([
            'section_id' => $this->testSectionId,
            'role_name' => 'admin',
            'can_view' => 1,
            'can_edit' => 1,
            'can_delete' => 1
        ]);

        // Verify it was saved
        $stmt = $this->db->prepare("
            SELECT * FROM section_review_permissions
            WHERE section_id = ? AND role_name = 'admin'
        ");
        $stmt->execute([$this->testSectionId]);
        $perm = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($perm, 'Review permission should be saved');
        $this->assertEquals(1, $perm['can_view']);
        $this->assertEquals(1, $perm['can_edit']);
    }

    public function testNotificationRulesCanBeSaved()
    {
        // Insert notification rule
        $stmt = $this->db->prepare("
            INSERT INTO section_notification_rules
            (section_id, role_name, notify_on_submission, notify_on_status_change)
            VALUES (:section_id, :role_name, :notify_on_submission, :notify_on_status_change)
        ");

        $stmt->execute([
            'section_id' => $this->testSectionId,
            'role_name' => 'admin',
            'notify_on_submission' => 1,
            'notify_on_status_change' => 1
        ]);

        // Verify it was saved
        $stmt = $this->db->prepare("
            SELECT * FROM section_notification_rules
            WHERE section_id = ? AND role_name = 'admin'
        ");
        $stmt->execute([$this->testSectionId]);
        $rule = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($rule, 'Notification rule should be saved');
        $this->assertEquals('admin', $rule['role_name']);
        $this->assertEquals(1, $rule['notify_on_submission']);
    }

    public function testGuidelinesCanBeSaved()
    {
        // Insert guideline
        $stmt = $this->db->prepare("
            INSERT INTO section_guidelines
            (section_id, guideline_type, title, content, display_order)
            VALUES (:section_id, :type, :title, :content, :order)
        ");

        $stmt->execute([
            'section_id' => $this->testSectionId,
            'type' => 'general',
            'title' => 'Test Guideline',
            'content' => 'This is a test guideline',
            'order' => 1
        ]);

        // Verify it was saved
        $stmt = $this->db->prepare("
            SELECT * FROM section_guidelines
            WHERE section_id = ? AND title = 'Test Guideline'
        ");
        $stmt->execute([$this->testSectionId]);
        $guideline = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($guideline, 'Guideline should be saved');
        $this->assertEquals('Test Guideline', $guideline['title']);
    }    public function testConfigurationValidation()
    {
        // Test that a section with no permissions is considered "needs configuration"
        $stmt = $this->db->prepare("
            SELECT
                (SELECT COUNT(*) FROM section_submission_permissions WHERE section_id = ?) as sub_count,
                (SELECT COUNT(*) FROM section_review_permissions WHERE section_id = ?) as rev_count
        ");
        $stmt->execute([$this->testSectionId, $this->testSectionId]);
        $counts = $stmt->fetch(\PDO::FETCH_ASSOC);

        $needsConfig = ($counts['sub_count'] == 0 && $counts['rev_count'] == 0);
        $this->assertTrue($needsConfig, 'Empty section should need configuration');
    }

    public function testCategoryCanBeUpdated()
    {
        // Ensure category 2 exists
        $stmt = $this->db->prepare("SELECT id FROM section_categories WHERE id = 2");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $this->db->prepare("
                INSERT INTO section_categories (id, name, display_name, icon, description)
                VALUES (2, 'Test Category 2', 'Test Category 2', 'fa-test2', 'Test 2')
            ")->execute();
        }

        // Update category
        $stmt = $this->db->prepare("UPDATE sections SET category_id = :category_id WHERE id = :id");
        $stmt->execute(['category_id' => 2, 'id' => $this->testSectionId]);

        // Verify it was updated
        $stmt = $this->db->prepare("SELECT category_id FROM sections WHERE id = ?");
        $stmt->execute([$this->testSectionId]);
        $section = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals(2, $section['category_id']);
    }
}
