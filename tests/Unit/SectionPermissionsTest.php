<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\SectionPermissions;
use Tests\Helpers\TestDatabase;

/**
 * Test suite for SectionPermissions class
 * Tests granular permission checking for sections
 */
class SectionPermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        TestDatabase::beginTransaction();

        // Clean up any test users from previous tests to ensure isolation
        $db = \Hub\Database::getInstance();
        $db->execute("DELETE FROM users WHERE email LIKE '%@example.com' OR email LIKE '%@test%'");
        $db->execute("UPDATE users SET is_active = 0 WHERE email NOT LIKE '%@example.com' AND email NOT LIKE '%@test%'");
    }    protected function tearDown(): void
    {
        TestDatabase::rollback();
    }

    /**
     * Helper: Create a test user
     */
    private function createTestUser($email = 'test@example.com', $role = 'staff'): int
    {
        $db = \Hub\Database::getInstance();
        $db->execute(
            "INSERT INTO users (email, name, role, is_active) VALUES (?, ?, ?, ?)",
            [$email, 'Test User', $role, 1]
        );
        return (int)$db->lastInsertId();
    }

    /**
     * Helper: Create a test section
     */
    private function createTestSection($slug = 'test-section', $categoryId = null): int
    {
        $db = \Hub\Database::getInstance();
        $db->execute(
            "INSERT INTO sections (name, slug, display_name, description, base_url, is_active, category_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            ['Test Section ' . uniqid(), $slug, 'Test Section', 'A test section', '/test', 1, $categoryId]
        );
        return (int)$db->lastInsertId();
    }

    /**
     * Helper: Create a section category
     */
    private function createTestCategory(
        $name = 'Test Category',
        $requiresForms = false,
        $requiresSubmissions = false,
        $requiresNotifications = false,
        $requiresGuidelines = false
    ): int {
        $db = \Hub\Database::getInstance();
        $db->execute(
            "INSERT INTO section_categories (name, display_name, description, requires_forms, requires_submissions, requires_notifications, requires_guidelines)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$name, $name, 'Category description', (int)$requiresForms, (int)$requiresSubmissions, (int)$requiresNotifications, (int)$requiresGuidelines]
        );
        return (int)$db->lastInsertId();
    }

    /**
     * Helper: Create submission permission
     */
    private function createSubmissionPermission($sectionId, $roleName, $canSubmit = true, $allowAnonymous = false): void
    {
        $db = \Hub\Database::getInstance();
        $db->execute(
            "INSERT INTO section_submission_permissions (section_id, role_name, can_submit, allow_anonymous)
             VALUES (?, ?, ?, ?)",
            [$sectionId, $roleName, (int)$canSubmit, (int)$allowAnonymous]
        );
    }

    /**
     * Helper: Create review permission
     */
    private function createReviewPermission(
        $sectionId,
        $roleName,
        $canView = true,
        $canEdit = false,
        $canDelete = false,
        $canAddNotes = false,
        $canChangeStatus = false,
        $canAssign = false,
        $canExport = false
    ): void {
        $db = \Hub\Database::getInstance();
        $db->execute(
            "INSERT INTO section_review_permissions
             (section_id, role_name, can_view, can_edit, can_delete, can_add_notes, can_change_status, can_assign, can_export)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$sectionId, $roleName, (int)$canView, (int)$canEdit, (int)$canDelete, (int)$canAddNotes, (int)$canChangeStatus, (int)$canAssign, (int)$canExport]
        );
    }

    // ========== Can Submit Tests ==========

    public function testCanSubmitReturnsTrueWhenUserHasPermission()
    {
        $userId = $this->createTestUser('user@example.com', 'staff');
        $sectionId = $this->createTestSection('submit-section');
        $this->createSubmissionPermission($sectionId, 'staff', true);

        $result = SectionPermissions::canSubmit($userId, 'submit-section');

        $this->assertTrue($result);
    }

    public function testCanSubmitReturnsFalseWhenUserLacksPermission()
    {
        $userId = $this->createTestUser('user@example.com', 'student');
        $sectionId = $this->createTestSection('restricted-section');
        $this->createSubmissionPermission($sectionId, 'staff', true); // Only staff can submit

        $result = SectionPermissions::canSubmit($userId, 'restricted-section');

        $this->assertFalse($result);
    }

    public function testCanSubmitHandlesAnonymousSubmissions()
    {
        $userId = $this->createTestUser('user@example.com', 'staff');
        $sectionId = $this->createTestSection('anon-section');
        $this->createSubmissionPermission($sectionId, 'staff', true, true); // Allow anonymous

        $result = SectionPermissions::canSubmit($userId, 'anon-section', true);

        $this->assertTrue($result);
    }

    public function testCanSubmitBlocksAnonymousWhenNotAllowed()
    {
        $userId = $this->createTestUser('user@example.com', 'staff');
        $sectionId = $this->createTestSection('no-anon-section');
        $this->createSubmissionPermission($sectionId, 'staff', true, false); // Disallow anonymous

        $result = SectionPermissions::canSubmit($userId, 'no-anon-section', true);

        $this->assertFalse($result);
    }

    public function testCanSubmitReturnsFalseWhenSectionNotFound()
    {
        $userId = $this->createTestUser();

        $result = SectionPermissions::canSubmit($userId, 'nonexistent-section');

        $this->assertFalse($result);
    }

    // ========== Can Review Tests ==========

    public function testCanReviewReturnsTrueWhenUserHasPermission()
    {
        $userId = $this->createTestUser('reviewer@example.com', 'admin');
        $sectionId = $this->createTestSection('review-section');
        $this->createReviewPermission($sectionId, 'admin', true);

        $result = SectionPermissions::canReview($userId, 'review-section');

        $this->assertTrue($result);
    }

    public function testCanReviewReturnsFalseWhenUserLacksPermission()
    {
        $userId = $this->createTestUser('user@example.com', 'student');
        $sectionId = $this->createTestSection('review-section');
        $this->createReviewPermission($sectionId, 'admin', true); // Only admin can review

        $result = SectionPermissions::canReview($userId, 'review-section');

        $this->assertFalse($result);
    }

    public function testCanReviewReturnsFalseWhenSectionNotFound()
    {
        $userId = $this->createTestUser();

        $result = SectionPermissions::canReview($userId, 'nonexistent-section');

        $this->assertFalse($result);
    }

    // ========== Get Review Permissions Tests ==========

    /**
     * @group incomplete
     */
    public function testGetReviewPermissionsReturnsAllPermissions()
    {
        $userId = $this->createTestUser('admin@example.com', 'admin');
        $sectionId = $this->createTestSection('full-perm-section');
        $this->createReviewPermission($sectionId, 'admin', true, true, true, true, true, true, true);

        $permissions = SectionPermissions::getReviewPermissions($userId, 'full-perm-section');

        $this->assertTrue((bool)$permissions['can_view']);
        $this->assertTrue((bool)$permissions['can_edit']);
        $this->assertTrue((bool)$permissions['can_delete']);
        $this->assertTrue((bool)$permissions['can_add_notes']);
        $this->assertTrue((bool)$permissions['can_change_status']);
        $this->assertTrue((bool)$permissions['can_assign']);
        $this->assertTrue((bool)$permissions['can_export']);
    }

    /**
     * @group incomplete
     */
    public function testGetReviewPermissionsReturnsMaxAcrossMultipleRoles()
    {
        $userId = $this->createTestUser('multi@example.com', 'staff');
        $db = \Hub\Database::getInstance();
        // Add global role
        $db->execute("INSERT INTO user_global_roles (user_id, role) VALUES (?, ?)", [$userId, 'admin']);

        $sectionId = $this->createTestSection('multi-role-section');
        $this->createReviewPermission($sectionId, 'staff', true, false, false, false, false, false, false);
        $this->createReviewPermission($sectionId, 'admin', true, true, true, false, false, false, false);

        $permissions = SectionPermissions::getReviewPermissions($userId, 'multi-role-section');

        $this->assertTrue((bool)$permissions['can_view']);
        $this->assertTrue((bool)$permissions['can_edit']); // From manager role
        $this->assertTrue((bool)$permissions['can_delete']); // From manager role
    }

    public function testGetReviewPermissionsReturnsDefaultsWhenNoPermissions()
    {
        $userId = $this->createTestUser();
        $this->createTestSection('no-perm-section');

        $permissions = SectionPermissions::getReviewPermissions($userId, 'no-perm-section');

        $this->assertFalse((bool)$permissions['can_view']);
        $this->assertFalse((bool)$permissions['can_edit']);
        $this->assertFalse((bool)$permissions['can_delete']);
        $this->assertFalse((bool)$permissions['can_add_notes']);
        $this->assertFalse((bool)$permissions['can_change_status']);
        $this->assertFalse((bool)$permissions['can_assign']);
        $this->assertFalse((bool)$permissions['can_export']);
    }

    // ========== Get Section Category Tests ==========

    public function testGetSectionCategoryReturnsCategory()
    {
        $categoryId = $this->createTestCategory('Form Category', true);
        $this->createTestSection('form-section', $categoryId);

        $category = SectionPermissions::getSectionCategory('form-section');

        $this->assertNotNull($category);
        $this->assertEquals('Form Category', $category['name']);
    }

    public function testGetSectionCategoryReturnsNullWhenNotFound()
    {
        $category = SectionPermissions::getSectionCategory('nonexistent-section');

        $this->assertNull($category);
    }

    // ========== Get Section Config Tests ==========

    /**
     * @group incomplete
     * @group needs-section-configuration-table
     */
    public function testGetSectionConfigReturnsConfiguration()
    {
        $sectionId = $this->createTestSection('config-section');
        $db = \Hub\Database::getInstance();
        $db->execute(
            "INSERT INTO section_configuration (section_id, max_attachments, allowed_file_types, form_config)
             VALUES (?, ?, ?, ?)",
            [$sectionId, 10, json_encode(['pdf', 'doc']), json_encode(['field1' => 'value1'])]
        );

        $config = SectionPermissions::getSectionConfig('config-section');

        $this->assertNotNull($config);
        $this->assertEquals(10, $config['max_attachments']);
        $this->assertIsArray($config['allowed_file_types']);
        $this->assertEquals(['pdf', 'doc'], $config['allowed_file_types']);
        $this->assertIsArray($config['form_config']);
        $this->assertEquals('value1', $config['form_config']['field1']);
    }

    public function testGetSectionConfigReturnsNullWhenNotFound()
    {
        $this->createTestSection('no-config-section');

        $config = SectionPermissions::getSectionConfig('no-config-section');

        $this->assertNull($config);
    }

    // ========== Get Guidelines Tests ==========

    public function testGetGuidelinesReturnsActiveGuidelines()
    {
        $sectionId = $this->createTestSection('guide-section');
        $db = \Hub\Database::getInstance();
        $db->execute(
            "INSERT INTO section_guidelines (section_id, guideline_type, title, content, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$sectionId, 'submission', 'Guideline 1', 'Content 1', 1, 1]
        );
        $db->execute(
            "INSERT INTO section_guidelines (section_id, guideline_type, title, content, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$sectionId, 'submission', 'Guideline 2', 'Content 2', 2, 1]
        );

        $guidelines = SectionPermissions::getGuidelines('guide-section');

        $this->assertCount(2, $guidelines);
        $this->assertEquals('Guideline 1', $guidelines[0]['title']);
        $this->assertEquals('Guideline 2', $guidelines[1]['title']);
    }

    public function testGetGuidelinesFiltersInactive()
    {
        $sectionId = $this->createTestSection('filter-section');
        $db = \Hub\Database::getInstance();
        $db->execute(
            "INSERT INTO section_guidelines (section_id, guideline_type, title, content, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$sectionId, 'submission', 'Active', 'Content', 1, 1]
        );
        $db->execute(
            "INSERT INTO section_guidelines (section_id, guideline_type, title, content, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$sectionId, 'submission', 'Inactive', 'Content', 2, 0]
        );

        $guidelines = SectionPermissions::getGuidelines('filter-section');

        $this->assertCount(1, $guidelines);
        $this->assertEquals('Active', $guidelines[0]['title']);
    }

    public function testGetGuidelinesFiltersByType()
    {
        $sectionId = $this->createTestSection('type-section');
        $db = \Hub\Database::getInstance();
        $db->execute(
            "INSERT INTO section_guidelines (section_id, guideline_type, title, content, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$sectionId, 'submission', 'Submit Guide', 'Content', 1, 1]
        );
        $db->execute(
            "INSERT INTO section_guidelines (section_id, guideline_type, title, content, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$sectionId, 'review', 'Review Guide', 'Content', 2, 1]
        );

        $guidelines = SectionPermissions::getGuidelines('type-section', 'submission');

        $this->assertCount(1, $guidelines);
        $this->assertEquals('Submit Guide', $guidelines[0]['title']);
    }

    public function testGetGuidelinesOrdersByDisplayOrder()
    {
        $sectionId = $this->createTestSection('order-section');
        $db = \Hub\Database::getInstance();
        $db->execute(
            "INSERT INTO section_guidelines (section_id, guideline_type, title, content, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$sectionId, 'general', 'Third', 'Content', 3, 1]
        );
        $db->execute(
            "INSERT INTO section_guidelines (section_id, guideline_type, title, content, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$sectionId, 'general', 'First', 'Content', 1, 1]
        );
        $db->execute(
            "INSERT INTO section_guidelines (section_id, guideline_type, title, content, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$sectionId, 'general', 'Second', 'Content', 2, 1]
        );

        $guidelines = SectionPermissions::getGuidelines('order-section');

        $this->assertEquals('First', $guidelines[0]['title']);
        $this->assertEquals('Second', $guidelines[1]['title']);
        $this->assertEquals('Third', $guidelines[2]['title']);
    }

    // ========== Get Notification Recipients Tests ==========

    public function testGetNotificationRecipientsReturnsUsers()
    {
        $this->createTestUser('admin1@example.com', 'admin');
        $this->createTestUser('admin2@example.com', 'admin');
        $sectionId = $this->createTestSection('notify-section');
        $db = \Hub\Database::getInstance();
        $db->execute(
            "INSERT INTO section_notification_rules
             (section_id, role_name, notify_on_submission, email_enabled, is_active)
             VALUES (?, ?, ?, ?, ?)",
            [$sectionId, 'admin', 1, 1, 1]
        );

        $recipients = SectionPermissions::getNotificationRecipients('notify-section', 'submission');

        $this->assertCount(2, $recipients);
        $this->assertEquals('admin', $recipients[0]['role_name']);
    }    public function testGetNotificationRecipientsFiltersInactiveRules()
    {
        $this->createTestUser('admin@example.com', 'admin');
        $sectionId = $this->createTestSection('inactive-notify-section');
        $db = \Hub\Database::getInstance();
        $db->execute(
            "INSERT INTO section_notification_rules
             (section_id, role_name, notify_on_submission, email_enabled, is_active)
             VALUES (?, ?, ?, ?, ?)",
            [$sectionId, 'admin', 1, 1, 0] // Inactive
        );

        $recipients = SectionPermissions::getNotificationRecipients('inactive-notify-section');

        $this->assertCount(0, $recipients);
    }

    public function testGetNotificationRecipientsFiltersByEventType()
    {
        $this->createTestUser('admin@example.com', 'admin');
        $sectionId = $this->createTestSection('event-section');
        $db = \Hub\Database::getInstance();
        $db->execute(
            "INSERT INTO section_notification_rules
             (section_id, role_name, notify_on_submission, notify_on_status_change, email_enabled, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$sectionId, 'admin', 1, 0, 1, 1]
        );

        $submissionRecipients = SectionPermissions::getNotificationRecipients('event-section', 'submission');
        $statusRecipients = SectionPermissions::getNotificationRecipients('event-section', 'status_change');

        $this->assertCount(1, $submissionRecipients);
        $this->assertCount(0, $statusRecipients);
    }

    // ========== Requires Feature Tests ==========

    public function testRequiresFeatureReturnsTrueWhenRequired()
    {
        $categoryId = $this->createTestCategory('Form Cat', true, false, false, false);
        $this->createTestSection('feature-section', $categoryId);

        $result = SectionPermissions::requiresFeature('feature-section', 'forms');

        $this->assertTrue($result);
    }

    public function testRequiresFeatureReturnsFalseWhenNotRequired()
    {
        $categoryId = $this->createTestCategory('Simple Cat', false, false, false, false);
        $this->createTestSection('no-feature-section', $categoryId);

        $result = SectionPermissions::requiresFeature('no-feature-section', 'forms');

        $this->assertFalse($result);
    }

    public function testRequiresFeatureReturnsFalseWhenCategoryNotFound()
    {
        $this->createTestSection('no-cat-section', null);

        $result = SectionPermissions::requiresFeature('no-cat-section', 'forms');

        $this->assertFalse($result);
    }

    // ========== Validate Section Config Tests ==========

    public function testValidateSectionConfigReturnsErrorsForIncompleteConfig()
    {
        $categoryId = $this->createTestCategory('Required Cat', true, true, false, false);
        $sectionId = $this->createTestSection('incomplete-section', $categoryId);

        $validation = SectionPermissions::validateSectionConfig($sectionId);

        $this->assertArrayHasKey('errors', $validation);
        $this->assertArrayHasKey('warnings', $validation);
        $this->assertNotEmpty($validation['errors']);
    }

    public function testValidateSectionConfigPassesForCompleteConfig()
    {
        $categoryId = $this->createTestCategory('Complete Cat', true, true, false, false);
        $sectionId = $this->createTestSection('complete-section', $categoryId);
        $this->createSubmissionPermission($sectionId, 'staff', true);
        $this->createReviewPermission($sectionId, 'admin', true);

        $validation = SectionPermissions::validateSectionConfig($sectionId);

        $this->assertEmpty($validation['errors']);
    }

    public function testValidateSectionConfigReturnsWarningsForOptionalMissingFeatures()
    {
        $categoryId = $this->createTestCategory('Warning Cat', false, false, true, true);
        $sectionId = $this->createTestSection('warning-section', $categoryId);

        $validation = SectionPermissions::validateSectionConfig($sectionId);

        $this->assertNotEmpty($validation['warnings']);
    }

    public function testValidateSectionConfigReturnsErrorForNonexistentSection()
    {
        $validation = SectionPermissions::validateSectionConfig(99999);

        $this->assertContains('Section not found', $validation['errors']);
    }
}
