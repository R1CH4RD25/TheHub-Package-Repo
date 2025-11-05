<?php

namespace Hub\Tests\Unit;

use Hub\Theme;
use Hub\Database;
use PHPUnit\Framework\TestCase;

class ThemeTest extends TestCase
{
    private static Database $db;
    private Theme $theme;
    private array $testThemeIds = [];
    private int $testUserId;

    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance();
    }

    protected function setUp(): void
    {
        $this->theme = new Theme();
        self::$db->beginTransaction();

        // Create test user for foreign key constraint
        self::$db->prepare("
            INSERT INTO users (id, email, name, role, is_active)
            VALUES (700, 'theme-test@test.local', 'Theme Test User', 'admin', 1)
            ON DUPLICATE KEY UPDATE email = email
        ")->execute();

        $this->testUserId = 700;
    }

    protected function tearDown(): void
    {
        // Clean up test themes
        foreach ($this->testThemeIds as $id) {
            try {
                self::$db->prepare("DELETE FROM themes WHERE id = ?")->execute([$id]);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        self::$db->rollBack();
    }

    /**
     * Test getAll returns array of themes
     */
    public function testGetAllReturnsArray(): void
    {
        $themes = $this->theme->getAll();

        $this->assertIsArray($themes);
    }

    /**
     * Test getAll includes theme properties
     */
    public function testGetAllIncludesThemeProperties(): void
    {
        $themes = $this->theme->getAll();

        if (!empty($themes)) {
            $first = $themes[0];
            $this->assertArrayHasKey('id', $first);
            $this->assertArrayHasKey('name', $first);
            $this->assertArrayHasKey('slug', $first);
            $this->assertArrayHasKey('settings', $first);
        } else {
            $this->assertTrue(true, 'No themes to test');
        }
    }

    /**
     * Test create theme
     */
    public function testCreateTheme(): void
    {
        $settings = [
            'primary_color' => '#FF0000',
            'navbar_color' => '#000000'
        ];

        $result = $this->theme->create(
            'Test Theme ' . uniqid(),
            $settings,
            'Test description',
            $this->testUserId
        );

        $this->testThemeIds[] = $result['id'];

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('slug', $result);
        $this->assertArrayHasKey('settings', $result);
        $this->assertEquals($settings, $result['settings']);
    }    /**
     * Test get theme by ID
     */
    public function testGetThemeById(): void
    {
        $settings = ['primary_color' => '#00FF00'];
        $created = $this->theme->create('Get Test ' . uniqid(), $settings, null, $this->testUserId);
        $this->testThemeIds[] = $created['id'];

        $retrieved = $this->theme->get($created['id']);

        $this->assertNotNull($retrieved);
        $this->assertEquals($created['id'], $retrieved['id']);
        $this->assertEquals($created['name'], $retrieved['name']);
    }

    /**
     * Test get returns null for nonexistent theme
     */
    public function testGetReturnsNullForNonexistentTheme(): void
    {
        $result = $this->theme->get(999999);

        $this->assertNull($result);
    }

    /**
     * Test getBySlug
     */
    public function testGetBySlug(): void
    {
        $name = 'Slug Test ' . uniqid();
        $settings = ['primary_color' => '#0000FF'];
        $created = $this->theme->create($name, $settings, null, $this->testUserId);
        $this->testThemeIds[] = $created['id'];

        $retrieved = $this->theme->getBySlug($created['slug']);

        $this->assertNotNull($retrieved);
        $this->assertEquals($created['id'], $retrieved['id']);
    }

    /**
     * Test getBySlug returns null for nonexistent slug
     */
    public function testGetBySlugReturnsNullForNonexistentSlug(): void
    {
        $result = $this->theme->getBySlug('nonexistent-slug-' . uniqid());

        $this->assertNull($result);
    }

    /**
     * Test createSlug generates valid slug
     */
    public function testCreateSlugGeneratesValidSlug(): void
    {
        $name = 'Test Theme Name 123';
        $created = $this->theme->create($name, [], null, $this->testUserId);
        $this->testThemeIds[] = $created['id'];

        $this->assertEquals('test-theme-name-123', $created['slug']);
    }

    /**
     * Test createSlug handles special characters
     */
    public function testCreateSlugHandlesSpecialCharacters(): void
    {
        $name = 'Theme!@#$%^&*()_+{}|:"<>?';
        $created = $this->theme->create($name, [], null, $this->testUserId);
        $this->testThemeIds[] = $created['id'];

        // Should only contain lowercase letters, numbers, and hyphens
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $created['slug']);
    }

    /**
     * Test delete theme
     */
    public function testDeleteTheme(): void
    {
        $created = $this->theme->create('Delete Test ' . uniqid(), [], null, $this->testUserId);
        $id = $created['id'];

        $result = $this->theme->delete($id);

        $this->assertTrue($result);
        $this->assertNull($this->theme->get($id));
    }

    /**
     * Test delete nonexistent theme throws exception
     */
    public function testDeleteNonexistentThemeThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Theme not found');

        $this->theme->delete(999999);
    }

    /**
     * Test export theme
     */
    public function testExportTheme(): void
    {
        $settings = ['primary_color' => '#FF00FF'];
        $created = $this->theme->create('Export Test ' . uniqid(), $settings, 'Export desc', $this->testUserId);
        $this->testThemeIds[] = $created['id'];

        $exported = $this->theme->export($created['id']);

        $this->assertArrayHasKey('name', $exported);
        $this->assertArrayHasKey('description', $exported);
        $this->assertArrayHasKey('settings', $exported);
        $this->assertArrayHasKey('version', $exported);
        $this->assertArrayHasKey('exported_at', $exported);
        $this->assertEquals($settings, $exported['settings']);
    }

    /**
     * Test export nonexistent theme throws exception
     */
    public function testExportNonexistentThemeThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Theme not found');

        $this->theme->export(999999);
    }

    /**
     * Test import theme
     */
    public function testImportTheme(): void
    {
        $themeData = [
            'name' => 'Imported Theme ' . uniqid(),
            'description' => 'Imported description',
            'settings' => ['primary_color' => '#00FFFF']
        ];

        $result = $this->theme->import($themeData, $this->testUserId);
        $this->testThemeIds[] = $result['id'];

        $this->assertArrayHasKey('id', $result);
        $this->assertEquals($themeData['name'], $result['name']);
        $this->assertEquals($themeData['settings'], $result['settings']);
    }

    /**
     * Test import without name throws exception
     */
    public function testImportWithoutNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid theme data');

        $this->theme->import(['settings' => []], $this->testUserId);
    }

    /**
     * Test import without settings throws exception
     */
    public function testImportWithoutSettingsThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid theme data');

        $this->theme->import(['name' => 'Test'], $this->testUserId);
    }

    /**
     * Test import handles duplicate names
     */
    public function testImportHandlesDuplicateNames(): void
    {
        $name = 'Duplicate Import ' . uniqid();

        // Create first theme
        $first = $this->theme->create($name, [], null, $this->testUserId);
        $this->testThemeIds[] = $first['id'];

        // Import with same name
        $imported = $this->theme->import(['name' => $name, 'settings' => []], $this->testUserId);
        $this->testThemeIds[] = $imported['id'];

        // Should have different name (appended counter)
        $this->assertNotEquals($name, $imported['name']);
        $this->assertStringContainsString($name, $imported['name']);
    }

    /**
     * Test generatePreview returns HTML
     */
    public function testGeneratePreviewReturnsHTML(): void
    {
        $settings = [
            'primary_color' => '#FF0000',
            'navbar_color' => '#000000',
            'accent_color' => '#FFD700',
            'header_bg_color' => '#333333',
            'button_primary_bg' => '#FF0000'
        ];

        $html = $this->theme->generatePreview($settings);

        $this->assertIsString($html);
        $this->assertStringContainsString('theme-preview', $html);
        $this->assertStringContainsString('#FF0000', $html);
        $this->assertStringContainsString('#000000', $html);
    }

    /**
     * Test generatePreview uses defaults for missing settings
     */
    public function testGeneratePreviewUsesDefaults(): void
    {
        $html = $this->theme->generatePreview([]);

        $this->assertIsString($html);
        $this->assertStringContainsString('theme-preview', $html);
        // Should contain default colors
        $this->assertStringContainsString('#', $html);
    }

    /**
     * Test generatePreview escapes HTML
     */
    public function testGeneratePreviewEscapesHTML(): void
    {
        $settings = ['primary_color' => '<script>alert("xss")</script>'];

        $html = $this->theme->generatePreview($settings);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * Test saveFromCurrentSettings creates theme
     */
    public function testSaveFromCurrentSettingsCreatesTheme(): void
    {
        $name = 'Current Settings Theme ' . uniqid();

        $result = $this->theme->saveFromCurrentSettings($name, 'Description', $this->testUserId);
        $this->testThemeIds[] = $result['id'];

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('slug', $result);
        $this->assertArrayHasKey('settings', $result);
        $this->assertEquals($name, $result['name']);
    }

    /**
     * Test saveFromCurrentSettings handles duplicate names
     */
    public function testSaveFromCurrentSettingsHandlesDuplicates(): void
    {
        $name = 'Duplicate Save ' . uniqid();

        $first = $this->theme->saveFromCurrentSettings($name, null, $this->testUserId);
        $this->testThemeIds[] = $first['id'];

        $second = $this->theme->saveFromCurrentSettings($name, null, $this->testUserId);
        $this->testThemeIds[] = $second['id'];

        // Second should have (2) appended
        $this->assertStringContainsString($name, $second['name']);
        $this->assertNotEquals($first['name'], $second['name']);
    }

    /**
     * Test updateFromCurrentSettings updates theme
     */
    public function testUpdateFromCurrentSettingsUpdatesTheme(): void
    {
        $created = $this->theme->create('Update Test ' . uniqid(), [], null, $this->testUserId);
        $this->testThemeIds[] = $created['id'];

        $result = $this->theme->updateFromCurrentSettings(
            $created['id'],
            'Updated Name',
            'Updated description'
        );

        $this->assertTrue($result);

        $updated = $this->theme->get($created['id']);
        $this->assertEquals('Updated Name', $updated['name']);
        $this->assertEquals('Updated description', $updated['description']);
    }

    /**
     * Test updateFromCurrentSettings throws exception for nonexistent theme
     */
    public function testUpdateFromCurrentSettingsThrowsExceptionForNonexistent(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Theme not found');

        $this->theme->updateFromCurrentSettings(999999);
    }

    /**
     * Test getActive returns active theme
     */
    public function testGetActiveReturnsActiveTheme(): void
    {
        $active = $this->theme->getActive();

        // May be null or array depending on database state
        if ($active !== null) {
            $this->assertIsArray($active);
            $this->assertArrayHasKey('id', $active);
            $this->assertTrue((bool)$active['is_active']);
        } else {
            $this->assertNull($active);
        }
    }

    /**
     * Test settings are JSON decoded
     */
    public function testSettingsAreJSONDecoded(): void
    {
        $settings = ['primary_color' => '#123456', 'navbar_color' => '#654321'];
        $created = $this->theme->create('JSON Test ' . uniqid(), $settings, null, $this->testUserId);
        $this->testThemeIds[] = $created['id'];

        $retrieved = $this->theme->get($created['id']);

        $this->assertIsArray($retrieved['settings']);
        $this->assertEquals($settings, $retrieved['settings']);
    }

    /**
     * Test create without description
     */
    public function testCreateWithoutDescription(): void
    {
        $result = $this->theme->create('No Desc ' . uniqid(), [], null, $this->testUserId);
        $this->testThemeIds[] = $result['id'];

        $this->assertArrayHasKey('id', $result);
        $this->assertNull($result['description']);
    }

    /**
     * Test create without created_by
     */
    public function testCreateWithoutCreatedBy(): void
    {
        $result = $this->theme->create('No Creator ' . uniqid(), [], null, null);
        $this->testThemeIds[] = $result['id'];

        $this->assertArrayHasKey('id', $result);
    }

    /**
     * Test theme includes creator name from join
     */
    public function testThemeIncludesCreatorName(): void
    {
        $created = $this->theme->create('Creator Test ' . uniqid(), [], null, $this->testUserId);
        $this->testThemeIds[] = $created['id'];

        $retrieved = $this->theme->get($created['id']);

        // Should have creator_name from LEFT JOIN
        $this->assertArrayHasKey('creator_name', $retrieved);
    }
}
