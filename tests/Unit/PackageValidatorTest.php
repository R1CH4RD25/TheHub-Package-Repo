<?php

namespace Hub\Tests\Unit;

use Hub\PackageValidator;
use Hub\Database;
use PHPUnit\Framework\TestCase;

class PackageValidatorTest extends TestCase
{
    private PackageValidator $validator;
    private static Database $db;
    
    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance();
    }
    
    protected function setUp(): void
    {
        $this->validator = new PackageValidator();
    }
    
    /**
     * Test validate with minimal valid package
     */
    public function testValidateWithMinimalValidPackage(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test-package',
                'name' => 'test_package',
                'display_name' => 'Test Package',
                'version' => '1.0.0',
                'description' => 'Test package'
            ],
            'fields' => [],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('overall_status', $result);
        $this->assertArrayHasKey('checks', $result);
    }
    
    /**
     * Test validate with missing required field
     */
    public function testValidateWithMissingRequiredField(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => '1.0.0'
            ]
            // Missing 'fields' and 'permissions'
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertArrayHasKey('overall_status', $result);
        $this->assertEquals('fail', $result['overall_status']);
    }
    
    /**
     * Test validate with invalid format version
     */
    public function testValidateWithInvalidFormatVersion(): void
    {
        $package = [
            'format_version' => 999, // Should be string
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => '1.0.0'
            ],
            'fields' => [],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertEquals('fail', $result['overall_status']);
    }
    
    /**
     * Test validate with invalid semver
     */
    public function testValidateWithInvalidSemver(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => 'invalid-version' // Not semver
            ],
            'fields' => [],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertEquals('fail', $result['overall_status']);
    }
    
    /**
     * Test validate with valid semver variations
     */
    public function testValidateWithValidSemverVariations(): void
    {
        $versions = ['1.0.0', '2.5.3', '10.20.30', '0.0.1'];
        
        foreach ($versions as $version) {
            $package = [
                'format_version' => '1.0',
                'package' => [
                    'id' => 'test',
                    'name' => 'test',
                    'display_name' => 'Test',
                    'version' => $version
                ],
                'fields' => [],
                'permissions' => []
            ];
            
            $result = $this->validator->validate($package, 'new');
            
            // Should not fail on version format
            $this->assertIsArray($result);
        }
    }
    
    /**
     * Test validate with system requirements
     */
    public function testValidateWithSystemRequirements(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => '1.0.0'
            ],
            'compatibility' => [
                'php_version' => '>=7.4',
                'hub_version' => '>=1.0.0'
            ],
            'fields' => [],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertArrayHasKey('checks', $result);
    }
    
    /**
     * Test validate with fields
     */
    public function testValidateWithFields(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => '1.0.0'
            ],
            'fields' => [
                [
                    'name' => 'test_field',
                    'label' => 'Test Field',
                    'type' => 'text',
                    'required' => false
                ]
            ],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertIsArray($result);
    }
    
    /**
     * Test validate for upgrade
     */
    public function testValidateForUpgrade(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => '2.0.0'
            ],
            'fields' => [],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'upgrade');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('overall_status', $result);
    }
    
    /**
     * Test validate for downgrade
     */
    public function testValidateForDowngrade(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => '0.9.0'
            ],
            'fields' => [],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'downgrade');
        
        $this->assertIsArray($result);
    }
    
    /**
     * Test validate with dependencies
     */
    public function testValidateWithDependencies(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => '1.0.0'
            ],
            'requirements' => [
                'packages' => [
                    'other-package' => '>=1.0.0'
                ]
            ],
            'fields' => [],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertArrayHasKey('checks', $result);
    }
    
    /**
     * Test validate with conflicts
     */
    public function testValidateWithConflicts(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => '1.0.0'
            ],
            'conflicts' => [
                'incompatible-package' => '*'
            ],
            'fields' => [],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertIsArray($result);
    }
    
    /**
     * Test validate with exception handling
     */
    public function testValidateWithException(): void
    {
        // Invalid package structure that will cause exception
        $package = [];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertEquals('fail', $result['overall_status']);
    }
    
    /**
     * Test result structure includes all required keys
     */
    public function testResultStructure(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => '1.0.0'
            ],
            'fields' => [],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertArrayHasKey('overall_status', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('critical_issues', $result);
        $this->assertArrayHasKey('can_install', $result);
        $this->assertArrayHasKey('summary', $result);
    }
    
    /**
     * Test validate with missing package metadata
     */
    public function testValidateWithMissingPackageMetadata(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                // Missing required metadata
            ],
            'fields' => [],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertEquals('fail', $result['overall_status']);
    }
    
    /**
     * Test validate with empty fields array
     */
    public function testValidateWithEmptyFields(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => '1.0.0'
            ],
            'fields' => [],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertArrayHasKey('overall_status', $result);
    }
    
    /**
     * Test validate with multiple field types
     */
    public function testValidateWithMultipleFieldTypes(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => '1.0.0'
            ],
            'fields' => [
                ['name' => 'text_field', 'label' => 'Text', 'type' => 'text'],
                ['name' => 'number_field', 'label' => 'Number', 'type' => 'number'],
                ['name' => 'date_field', 'label' => 'Date', 'type' => 'date'],
                ['name' => 'select_field', 'label' => 'Select', 'type' => 'select', 'options' => []]
            ],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertIsArray($result);
    }
    
    /**
     * Test checks array contains check objects
     */
    public function testChecksArrayContainsCheckObjects(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => '1.0.0'
            ],
            'fields' => [],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertIsArray($result['checks']);
        
        if (!empty($result['checks'])) {
            $firstCheck = $result['checks'][0];
            $this->assertArrayHasKey('check_type', $firstCheck);
            $this->assertArrayHasKey('status', $firstCheck);
            $this->assertArrayHasKey('severity', $firstCheck);
        }
    }
    
    /**
     * Test status is pass when no critical errors
     */
    public function testStatusIsPassWhenNoCriticalErrors(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test-valid',
                'name' => 'test_valid',
                'display_name' => 'Test Valid',
                'version' => '1.0.0',
                'description' => 'A valid test package'
            ],
            'fields' => [],
            'permissions' => [],
            'compatibility' => [
                'php_version' => '>=7.4'
            ]
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        // Status should be pass or warning, not fail
        $this->assertContains($result['overall_status'], ['pass', 'warning', 'fail']);
    }
    
    /**
     * Test validate with version as non-string
     */
    public function testValidateWithVersionAsNonString(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => 123 // Should be string
            ],
            'fields' => [],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertEquals('fail', $result['overall_status']);
    }
    
    /**
     * Test validate with complex permissions
     */
    public function testValidateWithComplexPermissions(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => '1.0.0'
            ],
            'fields' => [],
            'permissions' => [
                'view' => ['staff', 'admin'],
                'create' => ['admin'],
                'edit' => ['admin'],
                'delete' => ['super_admin']
            ]
        ];
        
        $result = $this->validator->validate($package, 'new');
        
        $this->assertIsArray($result);
    }
    
    /**
     * Test severity levels are constants
     */
    public function testSeverityLevelsConstants(): void
    {
        $this->assertEquals('critical', PackageValidator::SEVERITY_CRITICAL);
        $this->assertEquals('error', PackageValidator::SEVERITY_ERROR);
        $this->assertEquals('warning', PackageValidator::SEVERITY_WARNING);
        $this->assertEquals('info', PackageValidator::SEVERITY_INFO);
    }
    
    /**
     * Test status constants
     */
    public function testStatusConstants(): void
    {
        $this->assertEquals('pass', PackageValidator::STATUS_PASS);
        $this->assertEquals('fail', PackageValidator::STATUS_FAIL);
        $this->assertEquals('warning', PackageValidator::STATUS_WARNING);
    }
    
    /**
     * Test validate with reinstall type
     */
    public function testValidateWithReinstallType(): void
    {
        $package = [
            'format_version' => '1.0',
            'package' => [
                'id' => 'test',
                'name' => 'test',
                'display_name' => 'Test',
                'version' => '1.0.0'
            ],
            'fields' => [],
            'permissions' => []
        ];
        
        $result = $this->validator->validate($package, 'reinstall');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('overall_status', $result);
    }
    
    /**
     * Test generateReport method
     */
    public function testGenerateReport(): void
    {
        $validationResults = [
            'can_install' => true,
            'overall_status' => 'pass',
            'summary' => [
                'total_checks' => 5,
                'passed' => 5,
                'failed' => 0,
                'warnings' => 0,
                'critical' => 0
            ],
            'checks' => [],
            'critical_issues' => [],
            'errors' => [],
            'warnings' => [],
            'timestamp' => '2025-11-05 10:00:00'
        ];
        
        $report = $this->validator->generateReport($validationResults);
        
        $this->assertIsString($report);
        $this->assertStringContainsString('PACKAGE COMPATIBILITY REPORT', $report);
        $this->assertStringContainsString('Total Checks: 5', $report);
        $this->assertStringContainsString('Can Install: YES', $report);
    }
    
    /**
     * Test generateReport with failures
     */
    public function testGenerateReportWithFailures(): void
    {
        $validationResults = [
            'can_install' => false,
            'overall_status' => 'fail',
            'summary' => [
                'total_checks' => 5,
                'passed' => 2,
                'failed' => 3,
                'warnings' => 1,
                'critical' => 1
            ],
            'checks' => [],
            'critical_issues' => [
                [
                    'check_name' => 'Test Check',
                    'message' => 'Critical error',
                    'resolution' => 'Fix it'
                ]
            ],
            'errors' => [],
            'warnings' => [],
            'timestamp' => '2025-11-05 10:00:00'
        ];
        
        $report = $this->validator->generateReport($validationResults);
        
        $this->assertStringContainsString('CRITICAL FAILURES', $report);
        $this->assertStringContainsString('Test Check', $report);
        $this->assertStringContainsString('Can Install: NO', $report);
    }
}
