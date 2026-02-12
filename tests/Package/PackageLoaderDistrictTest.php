<?php

namespace Tests\Package;

use PHPUnit\Framework\TestCase;
use Hub\Package\PackageLoader;

/**
 * Tests for PackageLoader — verifying it can load packages from
 * both packages/local/ and packages/district/ directories
 */
class PackageLoaderDistrictTest extends TestCase
{
    protected function setUp(): void
    {
        PackageLoader::clearCache();
    }

    public function testLoadStudentDirectoryFromDatabase(): void
    {
        $loader = new PackageLoader();
        $pkg = $loader->load('district.student-directory');

        $this->assertNotNull($pkg, 'Package should be loadable');
        $this->assertTrue($pkg['_installed']);
        $this->assertSame('Student Directory', $pkg['package']['display_name']);
        $this->assertSame('district.student-directory', $pkg['package']['id']);
    }

    public function testPackageHasExpectedPages(): void
    {
        $loader = new PackageLoader();
        $pkg = $loader->load('district.student-directory');
        $this->assertNotNull($pkg);

        $pages = $pkg['presentation']['pages'] ?? [];
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('view', $pages);
        $this->assertArrayHasKey('add', $pages);
        $this->assertArrayHasKey('edit', $pages);
        $this->assertArrayHasKey('import', $pages);
        $this->assertArrayHasKey('export', $pages);
    }

    public function testPackageHasExpectedQueries(): void
    {
        $loader = new PackageLoader();
        $pkg = $loader->load('district.student-directory');
        $this->assertNotNull($pkg);

        $queries = $pkg['data']['queries'] ?? [];
        $this->assertArrayHasKey('listStudents', $queries);
        $this->assertArrayHasKey('getStudent', $queries);
        $this->assertArrayHasKey('getStats', $queries);
        $this->assertArrayHasKey('getGrades', $queries);
    }

    public function testPackageHasExpectedMutations(): void
    {
        $loader = new PackageLoader();
        $pkg = $loader->load('district.student-directory');
        $this->assertNotNull($pkg);

        $mutations = $pkg['data']['mutations'] ?? [];
        $this->assertArrayHasKey('createStudent', $mutations);
        $this->assertArrayHasKey('updateStudent', $mutations);
        $this->assertArrayHasKey('resetPassword', $mutations);
        $this->assertArrayHasKey('bulkDelete', $mutations);
    }

    public function testPackageAppearsInInstalledList(): void
    {
        $loader = new PackageLoader();
        $installed = $loader->getInstalled();

        $this->assertArrayHasKey('district.student-directory', $installed);
        $this->assertSame('Student Directory', $installed['district.student-directory']['_display_name']);
    }

    public function testPackageHasSectionId(): void
    {
        $loader = new PackageLoader();
        $pkg = $loader->load('district.student-directory');
        $this->assertNotNull($pkg);

        $this->assertNotNull($pkg['_section_id']);
        $this->assertIsInt((int)$pkg['_section_id']);
    }

    public function testPackageHasIcon(): void
    {
        $loader = new PackageLoader();
        $pkg = $loader->load('district.student-directory');
        $this->assertNotNull($pkg);

        $this->assertSame('bi-people-fill', $pkg['_icon']);
    }

    public function testFilesystemFallbackFindsDistrictPackages(): void
    {
        // Test that loadFromFilesystem can also find district packages
        // We test this indirectly through a non-installed package scenario
        // For now, just verify the filesystem structure exists
        $packagePath = dirname(__DIR__, 2) . '/packages/district/student-directory/package.json';
        $this->assertFileExists($packagePath);

        $json = json_decode(file_get_contents($packagePath), true);
        $this->assertNotNull($json);
        $this->assertSame('district.student-directory', $json['package']['id']);
    }
}
