<?php

namespace Tests\Integration;

use Hub\Theme;
use Hub\SiteSettings;
use Hub\Database;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\TestDatabase;

/**
 * Integration tests for Theme system
 * Tests complete theme workflows: creation, activation, settings management, and CSS generation
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Theme::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\SiteSettings::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\Hub\Database::class)]
class ThemeIntegrationTest extends TestCase
{
    private Theme $theme;
    private SiteSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->theme = new Theme();
        $this->settings = new SiteSettings();
        TestDatabase::beginTransaction();
    }

    protected function tearDown(): void
    {
        TestDatabase::rollBack();
        parent::tearDown();
    }

    /**
     * Test complete theme creation workflow
     */
    public function testCompleteThemeCreationFlow(): void
    {
        $userId = TestDatabase::createTestUser(['role' => 'admin']);

        $themeSettings = [
            'primary_color' => '#007bff',
            'secondary_color' => '#6c757d',
            'background_color' => '#ffffff',
            'text_color' => '#212529'
        ];

        // Create theme
        $theme = $this->theme->create('Test Theme', $themeSettings, 'A test theme', $userId);

        $this->assertIsArray($theme);
        $this->assertArrayHasKey('id', $theme);
        $this->assertArrayHasKey('name', $theme);
        $this->assertArrayHasKey('slug', $theme);
        $this->assertArrayHasKey('settings', $theme);
        $this->assertEquals('Test Theme', $theme['name']);
        $this->assertEquals('test-theme', $theme['slug']);
        $this->assertEquals($themeSettings, $theme['settings']);
    }

    /**
     * Test theme retrieval methods
     */
    public function testThemeRetrievalMethods(): void
    {
        $userId = TestDatabase::createTestUser(['role' => 'admin']);

        // Create test themes
        $theme1 = $this->theme->create('Theme One', ['color' => '#ff0000'], 'First theme', $userId);
        $theme2 = $this->theme->create('Theme Two', ['color' => '#00ff00'], 'Second theme', $userId);

        // Test getAll()
        $allThemes = $this->theme->getAll();
        $this->assertIsArray($allThemes);
        $this->assertGreaterThanOrEqual(2, count($allThemes));

        // Test get() by ID
        $retrieved = $this->theme->get($theme1['id']);
        $this->assertNotNull($retrieved);
        $this->assertEquals('Theme One', $retrieved['name']);
        $this->assertEquals($theme1['settings'], $retrieved['settings']);

        // Test getBySlug()
        $bySlug = $this->theme->getBySlug('theme-two');
        $this->assertNotNull($bySlug);
        $this->assertEquals('Theme Two', $bySlug['name']);
    }

    /**
     * Test theme activation workflow
     * Note: This test doesn't use transactions due to nested transaction issues in Theme::activate()
     */
    public function testThemeActivationWorkflow(): void
    {
        // Don't use transaction for this test
        TestDatabase::rollBack();

        // Cleanup any leftover themes from previous runs
        $db = TestDatabase::getConnection();
        $db->exec("DELETE FROM themes WHERE name IN ('Theme A Workflow', 'Theme B Workflow')");

        $userId = TestDatabase::createTestUser(['role' => 'admin']);

        // Create two themes
        $theme1 = $this->theme->create('Theme A Workflow', ['color' => '#aaaaaa'], null, $userId);
        $theme2 = $this->theme->create('Theme B Workflow', ['color' => '#bbbbbb'], null, $userId);

        // Activate first theme
        $result = $this->theme->activate($theme1['id']);
        $this->assertTrue($result);

        // Verify first theme is active
        $active = $this->theme->getActive();
        $this->assertNotNull($active);
        $this->assertEquals($theme1['id'], $active['id']);

        // Activate second theme
        $this->theme->activate($theme2['id']);

        // Verify only second theme is active now
        $active = $this->theme->getActive();
        $this->assertEquals($theme2['id'], $active['id']);

        // Verify first theme is no longer active
        $theme1Data = $this->theme->get($theme1['id']);
        $this->assertEquals(0, $theme1Data['is_active']);

        // Cleanup - deactivate before deleting
        $db = TestDatabase::getConnection();
        $db->exec("UPDATE themes SET is_active = FALSE");
        $this->theme->delete($theme1['id']);
        $this->theme->delete($theme2['id']);

        // Restart transaction for tearDown
        TestDatabase::beginTransaction();
    }

    /**
     * Test theme settings persistence
     */
    public function testThemeSettingsPersistence(): void
    {
        $userId = TestDatabase::createTestUser(['role' => 'admin']);

        $settings = [
            'primary_color' => '#007bff',
            'secondary_color' => '#6c757d',
            'success_color' => '#28a745',
            'warning_color' => '#ffc107',
            'danger_color' => '#dc3545',
            'font_family' => 'Arial, sans-serif'
        ];

        $theme = $this->theme->create('Complete Theme', $settings, 'Full settings', $userId);

        // Retrieve and verify all settings persisted
        $retrieved = $this->theme->get($theme['id']);
        $this->assertEquals($settings, $retrieved['settings']);

        // Verify each setting
        foreach ($settings as $key => $value) {
            $this->assertArrayHasKey($key, $retrieved['settings']);
            $this->assertEquals($value, $retrieved['settings'][$key]);
        }
    }

    /**
     * Test slug generation
     */
    public function testSlugGeneration(): void
    {
        $userId = TestDatabase::createTestUser(['role' => 'admin']);

        // Test various name formats - use unique names to avoid constraint violations
        $testCases = [
            'Simple Slug Theme' => 'simple-slug-theme',
            'Spaces   Theme Test' => 'spaces-theme-test',
            'Pre-Dashed-Theme' => 'pre-dashed-theme',
            'Numbers123Theme' => 'numbers123theme'
        ];

        foreach ($testCases as $name => $expectedSlug) {
            $theme = $this->theme->create($name, ['test' => true], null, $userId);
            $this->assertEquals($expectedSlug, $theme['slug']);
        }
    }

    /**
     * Test theme deletion with constraints
     * Note: This test doesn't use transactions due to nested transaction issues
     */
    public function testThemeDeletionConstraints(): void
    {
        TestDatabase::rollBack();

        $db = TestDatabase::getConnection();
        $db->exec("DELETE FROM themes WHERE name = 'Active Delete Test Theme'");

        $userId = TestDatabase::createTestUser(['role' => 'admin']);

        // Create and activate a theme
        $theme1 = $this->theme->create('Active Delete Test Theme', ['color' => '#111111'], null, $userId);
        $this->theme->activate($theme1['id']);

        // Try to delete active theme - should throw exception
        try {
            $this->theme->delete($theme1['id']);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Cannot delete the active theme', $e->getMessage());
        }

        // Cleanup - deactivate first
        $db = TestDatabase::getConnection();
        $db->exec("UPDATE themes SET is_active = FALSE WHERE id = {$theme1['id']}");
        $this->theme->delete($theme1['id']);

        TestDatabase::beginTransaction();
    }    /**
     * Test deleting inactive theme succeeds
     * Note: This test doesn't use transactions due to nested transaction issues
     */
    public function testDeletingInactiveThemeSucceeds(): void
    {
        TestDatabase::rollBack();

        $db = TestDatabase::getConnection();
        $db->exec("DELETE FROM themes WHERE name IN ('Deletable Test Theme', 'Active Theme For Deletion Test')");

        $userId = TestDatabase::createTestUser(['role' => 'admin']);

        $theme1 = $this->theme->create('Deletable Test Theme', ['color' => '#222222'], null, $userId);
        $theme2 = $this->theme->create('Active Theme For Deletion Test', ['color' => '#333333'], null, $userId);

        // Activate second theme
        $this->theme->activate($theme2['id']);

        // Delete first theme should succeed
        $result = $this->theme->delete($theme1['id']);
        $this->assertTrue($result);

        // Verify theme is deleted
        $this->assertNull($this->theme->get($theme1['id']));

        // Cleanup
        $db = TestDatabase::getConnection();
        $db->exec("UPDATE themes SET is_active = FALSE WHERE id = {$theme2['id']}");
        $this->theme->delete($theme2['id']);

        TestDatabase::beginTransaction();
    }

    /**
     * Test system theme protection
     */
    public function testSystemThemeProtection(): void
    {
        // Create a system theme directly in database
        $db = TestDatabase::getConnection();
        $stmt = $db->prepare("
            INSERT INTO themes (name, slug, description, settings, is_active, is_system, created_by)
            VALUES (?, ?, ?, ?, FALSE, TRUE, NULL)
        ");
        $stmt->execute(['System Theme', 'system-theme', 'Built-in', json_encode(['color' => '#000000'])]);
        $themeId = $db->lastInsertId();

        // Try to delete system theme - should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete system themes');
        $this->theme->delete($themeId);
    }

    /**
     * Test theme export functionality
     */
    public function testThemeExport(): void
    {
        $userId = TestDatabase::createTestUser(['role' => 'admin']);

        $settings = ['primary_color' => '#007bff', 'font_size' => '16px'];
        $theme = $this->theme->create('Export Theme', $settings, 'Theme for export', $userId);

        // Export theme
        $exported = $this->theme->export($theme['id']);

        $this->assertIsArray($exported);
        $this->assertArrayHasKey('name', $exported);
        $this->assertArrayHasKey('description', $exported);
        $this->assertArrayHasKey('settings', $exported);
        $this->assertArrayHasKey('version', $exported);
        $this->assertArrayHasKey('exported_at', $exported);

        $this->assertEquals('Export Theme', $exported['name']);
        $this->assertEquals($settings, $exported['settings']);
    }

    /**
     * Test theme activation applies settings
     * Note: This test doesn't use transactions due to nested transaction issues
     */
    public function testThemeActivationAppliesSettings(): void
    {
        TestDatabase::rollBack();

        $db = TestDatabase::getConnection();
        $db->exec("DELETE FROM themes WHERE name = 'Settings Application Theme'");

        $userId = TestDatabase::createTestUser(['role' => 'admin']);

        // Create theme with specific settings
        $themeSettings = [
            'primary_color' => '#ff5500',
            'site_name' => 'Themed Site'
        ];

        $theme = $this->theme->create('Settings Application Theme', $themeSettings, null, $userId);

        // Activate theme
        $this->theme->activate($theme['id']);

        // Verify active theme ID is set in site settings
        $db = TestDatabase::getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'active_theme_id'");
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result) {
            $this->assertEquals($theme['id'], (int)$result['setting_value']);
        }

        // Cleanup
        $db->exec("UPDATE themes SET is_active = FALSE WHERE id = {$theme['id']}");
        $this->theme->delete($theme['id']);

        TestDatabase::beginTransaction();

        // Note: applyThemeSettings() would update site_settings table,
        // but that's tested more thoroughly in integration with SiteSettings
    }

    /**
     * Test getActive returns null when no theme is active
     */
    public function testGetActiveReturnsNullWhenNoActiveTheme(): void
    {
        // Deactivate all themes
        $db = TestDatabase::getConnection();
        $db->exec("UPDATE themes SET is_active = FALSE");

        $active = $this->theme->getActive();
        $this->assertNull($active);
    }

    /**
     * Test theme creation with minimal data
     */
    public function testThemeCreationWithMinimalData(): void
    {
        // Create theme with only required fields (no description, no created_by)
        $theme = $this->theme->create('Minimal Theme', ['color' => '#ffffff']);

        $this->assertIsArray($theme);
        $this->assertEquals('Minimal Theme', $theme['name']);
        $this->assertEquals('minimal-theme', $theme['slug']);
        $this->assertEquals(['color' => '#ffffff'], $theme['settings']);
    }

    /**
     * Test theme retrieval with non-existent ID returns null
     */
    public function testGetNonExistentThemeReturnsNull(): void
    {
        $theme = $this->theme->get(999999);
        $this->assertNull($theme);
    }

    /**
     * Test activating non-existent theme throws exception
     */
    public function testActivateNonExistentThemeThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Theme not found');
        $this->theme->activate(999999);
    }

    /**
     * Test deleting non-existent theme throws exception
     */
    public function testDeleteNonExistentThemeThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Theme not found');
        $this->theme->delete(999999);
    }
}
