<?php

namespace Hub\Tests\Unit;

use Hub\PackageManager;
use Hub\Database;
use PHPUnit\Framework\TestCase;

class PackageManagerTest extends TestCase
{
    private PackageManager $manager;
    private static Database $db;

    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance();
    }

    protected function setUp(): void
    {
        self::$db->beginTransaction();
        $this->manager = new PackageManager();

        // Create test user for foreign keys
        self::$db->execute("INSERT INTO users (id, email, name, role) VALUES (800, 'pkg@test.com', 'Pkg Test', 'admin') ON DUPLICATE KEY UPDATE id=id");
    }

    protected function tearDown(): void
    {
        self::$db->rollback();
    }

    /**
     * Test constructor initializes dependencies
     */
    public function testConstructor(): void
    {
        $manager = new PackageManager();
        $this->assertInstanceOf(PackageManager::class, $manager);
    }

    /**
     * Test status constants
     */
    public function testStatusConstants(): void
    {
        $this->assertEquals('pending', PackageManager::STATUS_PENDING);
        $this->assertEquals('installing', PackageManager::STATUS_INSTALLING);
        $this->assertEquals('installed', PackageManager::STATUS_INSTALLED);
        $this->assertEquals('failed', PackageManager::STATUS_FAILED);
        $this->assertEquals('upgrading', PackageManager::STATUS_UPGRADING);
        $this->assertEquals('uninstalling', PackageManager::STATUS_UNINSTALLING);
    }

    /**
     * Test uploadPackage with invalid file error
     */
    public function testUploadPackageWithFileError(): void
    {
        $file = [
            'name' => 'test.hubpkg',
            'tmp_name' => '/tmp/test',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 1024
        ];

        $result = $this->manager->uploadPackage($file, 800);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test uploadPackage with file too large
     */
    public function testUploadPackageWithFileTooLarge(): void
    {
        $file = [
            'name' => 'test.hubpkg',
            'tmp_name' => '/tmp/test',
            'error' => UPLOAD_ERR_OK,
            'size' => 51 * 1024 * 1024 // 51MB
        ];

        $result = $this->manager->uploadPackage($file, 800);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('too large', $result['error']);
    }

    /**
     * Test uploadPackage with invalid extension
     */
    public function testUploadPackageWithInvalidExtension(): void
    {
        $file = [
            'name' => 'test.zip',
            'tmp_name' => '/tmp/test',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024
        ];

        $result = $this->manager->uploadPackage($file, 800);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid package', $result['error']);
    }

    /**
     * Test installPackage with non-existent package
     */
    public function testInstallPackageWithNonExistentPackage(): void
    {
        // Commit existing transaction to avoid conflict
        self::$db->commit();

        $result = $this->manager->installPackage(99999, 800);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not found', $result['error']);

        // Restart transaction for tearDown
        self::$db->beginTransaction();
    }    /**
     * Test getPackages returns array
     */
    public function testGetPackages(): void
    {
        $result = $this->manager->getPackages();

        $this->assertIsArray($result);
    }

    /**
     * Test getPackages with filters
     */
    public function testGetPackagesWithFilters(): void
    {
        $result = $this->manager->getPackages([
            'status' => 'installed',
            'search' => 'test'
        ]);

        $this->assertIsArray($result);
    }

    /**
     * Test getInstalledPackages returns array
     */
    public function testGetInstalledPackages(): void
    {
        $result = $this->manager->getInstalledPackages();

        $this->assertIsArray($result);
    }

    /**
     * Test getInstalledPackages structure
     */
    public function testGetInstalledPackagesStructure(): void
    {
        // Create a test package installation
        $sectionId = self::$db->insert('sections', [
            'name' => 'test_pkg',
            'slug' => 'test-pkg',
            'display_name' => 'Test Package',
            'base_url' => '/test/',
            'is_active' => 1,
            'is_dynamic' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        self::$db->insert('section_installations', [
            'section_id' => $sectionId,
            'package_id' => 'test-pkg-id',
            'package_record_id' => 1,
            'installed_version' => '1.0.0',
            'installed_by' => 800,
            'installed_at' => date('Y-m-d H:i:s'),
            'status' => 'installed'
        ]);

        $result = $this->manager->getInstalledPackages();

        $this->assertIsArray($result);
        if (!empty($result)) {
            $first = $result[0];
            $this->assertArrayHasKey('package_id', $first);
            $this->assertArrayHasKey('installed_version', $first);
            $this->assertArrayHasKey('display_name', $first);
        }
    }    /**
     * Test checkForUpdates returns array
     */
    public function testCheckForUpdates(): void
    {
        $result = $this->manager->checkForUpdates();

        $this->assertIsArray($result);
    }

    /**
     * Test enableSection with non-existent package
     */
    public function testEnableSectionWithNonExistentPackage(): void
    {
        $result = $this->manager->enableSection('non-existent-pkg', 800);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test disableSection with non-existent package
     */
    public function testDisableSectionWithNonExistentPackage(): void
    {
        $result = $this->manager->disableSection('non-existent-pkg', 800);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test enableSection with valid package
     */
    public function testEnableSectionWithValidPackage(): void
    {
        // Create test section
        $sectionId = self::$db->insert('sections', [
            'name' => 'test_enable',
            'slug' => 'test-enable',
            'display_name' => 'Test Enable',
            'base_url' => '/test/',
            'is_active' => 0,
            'is_dynamic' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        self::$db->insert('section_installations', [
            'section_id' => $sectionId,
            'package_id' => 'test-enable-pkg',
            'package_record_id' => 1,
            'installed_version' => '1.0.0',
            'installed_by' => 800,
            'installed_at' => date('Y-m-d H:i:s'),
            'status' => 'installed'
        ]);

        $result = $this->manager->enableSection('test-enable-pkg', 800);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message', $result);

        // Verify section is now active
        $section = self::$db->fetchOne("SELECT is_active FROM sections WHERE id = ?", [$sectionId]);
        $this->assertEquals(1, $section['is_active']);
    }

    /**
     * Test disableSection with valid package
     */
    public function testDisableSectionWithValidPackage(): void
    {
        // Create test section
        $sectionId = self::$db->insert('sections', [
            'name' => 'test_disable',
            'slug' => 'test-disable',
            'display_name' => 'Test Disable',
            'base_url' => '/test/',
            'is_active' => 1,
            'is_dynamic' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        self::$db->insert('section_installations', [
            'section_id' => $sectionId,
            'package_id' => 'test-disable-pkg',
            'package_record_id' => 1,
            'installed_version' => '1.0.0',
            'installed_by' => 800,
            'installed_at' => date('Y-m-d H:i:s'),
            'status' => 'installed'
        ]);

        $result = $this->manager->disableSection('test-disable-pkg', 800);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message', $result);

        // Verify section is now inactive
        $section = self::$db->fetchOne("SELECT is_active FROM sections WHERE id = ?", [$sectionId]);
        $this->assertEquals(0, $section['is_active']);
    }

    /**
     * Test enableSection already enabled
     */
    public function testEnableSectionAlreadyEnabled(): void
    {
        // Create already-enabled section
        $sectionId = self::$db->insert('sections', [
            'name' => 'already_enabled',
            'slug' => 'already-enabled',
            'display_name' => 'Already Enabled',
            'base_url' => '/test/',
            'is_active' => 1,
            'is_dynamic' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        self::$db->insert('section_installations', [
            'section_id' => $sectionId,
            'package_id' => 'already-enabled-pkg',
            'package_record_id' => 1,
            'installed_version' => '1.0.0',
            'installed_by' => 800,
            'installed_at' => date('Y-m-d H:i:s'),
            'status' => 'installed'
        ]);

        $result = $this->manager->enableSection('already-enabled-pkg', 800);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('was_already_enabled', $result);
        $this->assertTrue($result['was_already_enabled']);
    }    /**
     * Test disableSection already disabled
     */
    public function testDisableSectionAlreadyDisabled(): void
    {
        // Create already-disabled section
        $sectionId = self::$db->insert('sections', [
            'name' => 'already_disabled',
            'slug' => 'already-disabled',
            'display_name' => 'Already Disabled',
            'base_url' => '/test/',
            'is_active' => 0,
            'is_dynamic' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        self::$db->insert('section_installations', [
            'section_id' => $sectionId,
            'package_id' => 'already-disabled-pkg',
            'package_record_id' => 1,
            'installed_version' => '1.0.0',
            'installed_by' => 800,
            'installed_at' => date('Y-m-d H:i:s'),
            'status' => 'installed'
        ]);

        $result = $this->manager->disableSection('already-disabled-pkg', 800);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('was_already_disabled', $result);
        $this->assertTrue($result['was_already_disabled']);
    }

    /**
     * Test uninstallPackage with non-existent package
     */
    public function testUninstallPackageWithNonExistentPackage(): void
    {
        // Commit existing transaction to avoid conflict
        self::$db->commit();

        $result = $this->manager->uninstallPackage('non-existent', 800);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);

        // Restart transaction for tearDown
        self::$db->beginTransaction();
    }

    /**
     * Test upgradePackage with non-existent package
     */
    public function testUpgradePackageWithNonExistentPackage(): void
    {
        // Commit existing transaction to avoid conflict
        self::$db->commit();

        $result = $this->manager->upgradePackage(99999, 800);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);

        // Restart transaction for tearDown
        self::$db->beginTransaction();
    }

    /**
     * Test directory constants are defined
     */
    public function testDirectoryConstants(): void
    {
        $this->assertIsString(PackageManager::UPLOAD_DIR);
        $this->assertIsString(PackageManager::PACKAGE_DIR);
        $this->assertIsString(PackageManager::TEMP_DIR);

        $this->assertStringContainsString('uploads', PackageManager::UPLOAD_DIR);
        $this->assertStringContainsString('packages', PackageManager::PACKAGE_DIR);
    }
}
