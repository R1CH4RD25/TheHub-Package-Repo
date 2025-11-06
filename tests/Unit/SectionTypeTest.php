<?php

namespace Hub\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Hub\SectionType;

class SectionTypeTest extends TestCase
{
    /**
     * Test getAll returns all section type definitions
     */
    public function testGetAllReturnsAllSectionTypes(): void
    {
        $types = SectionType::getAll();

        $this->assertIsArray($types);
        $this->assertNotEmpty($types);

        // Verify all expected types exist
        $this->assertArrayHasKey('recording', $types);
        $this->assertArrayHasKey('request_form', $types);
        $this->assertArrayHasKey('scheduled_report', $types);
        $this->assertArrayHasKey('hub', $types);
        $this->assertArrayHasKey('other', $types);
    }

    /**
     * Test each section type has required fields
     */
    public function testSectionTypesHaveRequiredFields(): void
    {
        $types = SectionType::getAll();

        foreach ($types as $key => $type) {
            $this->assertArrayHasKey('value', $type, "Type {$key} missing 'value'");
            $this->assertArrayHasKey('label', $type, "Type {$key} missing 'label'");
            $this->assertArrayHasKey('description', $type, "Type {$key} missing 'description'");
            $this->assertArrayHasKey('icon', $type, "Type {$key} missing 'icon'");
            $this->assertArrayHasKey('features', $type, "Type {$key} missing 'features'");
            $this->assertArrayHasKey('color', $type, "Type {$key} missing 'color'");

            // Verify value matches key
            $this->assertEquals($key, $type['value'], "Type {$key} value mismatch");

            // Verify features structure
            $this->assertArrayHasKey('has_export', $type['features']);
            $this->assertArrayHasKey('requires_approval', $type['features']);
            $this->assertArrayHasKey('send_notifications', $type['features']);
            $this->assertArrayHasKey('has_workflow', $type['features']);

            // Verify features are boolean
            $this->assertIsBool($type['features']['has_export']);
            $this->assertIsBool($type['features']['requires_approval']);
            $this->assertIsBool($type['features']['send_notifications']);
            $this->assertIsBool($type['features']['has_workflow']);

            // Verify color format
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/i', $type['color'], "Invalid color format for {$key}");
        }
    }

    /**
     * Test recording type has correct configuration
     */
    public function testRecordingTypeConfiguration(): void
    {
        $types = SectionType::getAll();
        $recording = $types['recording'];

        $this->assertEquals('recording', $recording['value']);
        $this->assertEquals('Recording/Data Entry', $recording['label']);
        $this->assertEquals('📊', $recording['icon']);
        $this->assertEquals('#2196F3', $recording['color']);
        $this->assertTrue($recording['features']['has_export']);
        $this->assertFalse($recording['features']['requires_approval']);
        $this->assertFalse($recording['features']['send_notifications']);
        $this->assertFalse($recording['features']['has_workflow']);
    }

    /**
     * Test request_form type has correct configuration
     */
    public function testRequestFormTypeConfiguration(): void
    {
        $types = SectionType::getAll();
        $requestForm = $types['request_form'];

        $this->assertEquals('request_form', $requestForm['value']);
        $this->assertEquals('Request Form', $requestForm['label']);
        $this->assertEquals('📝', $requestForm['icon']);
        $this->assertEquals('#FF9800', $requestForm['color']);
        $this->assertTrue($requestForm['features']['has_export']);
        $this->assertTrue($requestForm['features']['requires_approval']);
        $this->assertTrue($requestForm['features']['send_notifications']);
        $this->assertTrue($requestForm['features']['has_workflow']);
    }

    /**
     * Test scheduled_report type has correct configuration
     */
    public function testScheduledReportTypeConfiguration(): void
    {
        $types = SectionType::getAll();
        $scheduledReport = $types['scheduled_report'];

        $this->assertEquals('scheduled_report', $scheduledReport['value']);
        $this->assertEquals('Scheduled Report', $scheduledReport['label']);
        $this->assertEquals('📧', $scheduledReport['icon']);
        $this->assertEquals('#4CAF50', $scheduledReport['color']);
        $this->assertTrue($scheduledReport['features']['has_export']);
        $this->assertFalse($scheduledReport['features']['requires_approval']);
        $this->assertTrue($scheduledReport['features']['send_notifications']);
        $this->assertFalse($scheduledReport['features']['has_workflow']);
    }

    /**
     * Test hub type has correct configuration
     */
    public function testHubTypeConfiguration(): void
    {
        $types = SectionType::getAll();
        $hub = $types['hub'];

        $this->assertEquals('hub', $hub['value']);
        $this->assertEquals('Hub/Dashboard', $hub['label']);
        $this->assertEquals('🏠', $hub['icon']);
        $this->assertEquals('#9C27B0', $hub['color']);
        $this->assertFalse($hub['features']['has_export']);
        $this->assertFalse($hub['features']['requires_approval']);
        $this->assertFalse($hub['features']['send_notifications']);
        $this->assertFalse($hub['features']['has_workflow']);
    }

    /**
     * Test other type has correct configuration
     */
    public function testOtherTypeConfiguration(): void
    {
        $types = SectionType::getAll();
        $other = $types['other'];

        $this->assertEquals('other', $other['value']);
        $this->assertEquals('Other', $other['label']);
        $this->assertEquals('🔧', $other['icon']);
        $this->assertEquals('#607D8B', $other['color']);
        $this->assertFalse($other['features']['has_export']);
        $this->assertFalse($other['features']['requires_approval']);
        $this->assertFalse($other['features']['send_notifications']);
        $this->assertFalse($other['features']['has_workflow']);
    }

    /**
     * Test getOrdered returns types in correct order
     */
    public function testGetOrderedReturnsTypesInCorrectOrder(): void
    {
        $ordered = SectionType::getOrdered();

        $this->assertIsArray($ordered);
        $this->assertCount(5, $ordered);

        // Verify order
        $this->assertEquals('recording', $ordered[0]['value']);
        $this->assertEquals('request_form', $ordered[1]['value']);
        $this->assertEquals('scheduled_report', $ordered[2]['value']);
        $this->assertEquals('hub', $ordered[3]['value']);
        $this->assertEquals('other', $ordered[4]['value']);
    }

    /**
     * Test getValues returns array of type values
     */
    public function testGetValuesReturnsTypeValues(): void
    {
        $values = SectionType::getValues();

        $this->assertIsArray($values);
        $this->assertCount(5, $values);
        $this->assertContains('recording', $values);
        $this->assertContains('request_form', $values);
        $this->assertContains('scheduled_report', $values);
        $this->assertContains('hub', $values);
        $this->assertContains('other', $values);
    }

    /**
     * Test getLabel returns correct label for valid type
     */
    public function testGetLabelReturnsCorrectLabel(): void
    {
        $this->assertEquals('Recording/Data Entry', SectionType::getLabel('recording'));
        $this->assertEquals('Request Form', SectionType::getLabel('request_form'));
        $this->assertEquals('Scheduled Report', SectionType::getLabel('scheduled_report'));
        $this->assertEquals('Hub/Dashboard', SectionType::getLabel('hub'));
        $this->assertEquals('Other', SectionType::getLabel('other'));
    }

    /**
     * Test getLabel returns fallback for unknown type
     */
    public function testGetLabelReturnsFallbackForUnknownType(): void
    {
        $label = SectionType::getLabel('unknown_type');
        
        $this->assertEquals('Unknown type', $label);
    }

    /**
     * Test getLabel fallback handles underscores
     */
    public function testGetLabelFallbackHandlesUnderscores(): void
    {
        $label = SectionType::getLabel('custom_section_type');
        
        $this->assertEquals('Custom section type', $label);
    }

    /**
     * Test getIcon returns correct icon for valid type
     */
    public function testGetIconReturnsCorrectIcon(): void
    {
        $this->assertEquals('📊', SectionType::getIcon('recording'));
        $this->assertEquals('📝', SectionType::getIcon('request_form'));
        $this->assertEquals('📧', SectionType::getIcon('scheduled_report'));
        $this->assertEquals('🏠', SectionType::getIcon('hub'));
        $this->assertEquals('🔧', SectionType::getIcon('other'));
    }

    /**
     * Test getIcon returns fallback for unknown type
     */
    public function testGetIconReturnsFallbackForUnknownType(): void
    {
        $icon = SectionType::getIcon('unknown_type');
        
        $this->assertEquals('📁', $icon);
    }

    /**
     * Test getColor returns correct color for valid type
     */
    public function testGetColorReturnsCorrectColor(): void
    {
        $this->assertEquals('#2196F3', SectionType::getColor('recording'));
        $this->assertEquals('#FF9800', SectionType::getColor('request_form'));
        $this->assertEquals('#4CAF50', SectionType::getColor('scheduled_report'));
        $this->assertEquals('#9C27B0', SectionType::getColor('hub'));
        $this->assertEquals('#607D8B', SectionType::getColor('other'));
    }

    /**
     * Test getColor returns fallback for unknown type
     */
    public function testGetColorReturnsFallbackForUnknownType(): void
    {
        $color = SectionType::getColor('unknown_type');
        
        $this->assertEquals('#666666', $color);
    }

    /**
     * Test hasFeature returns true for enabled features
     */
    public function testHasFeatureReturnsTrueForEnabledFeatures(): void
    {
        // recording has export
        $this->assertTrue(SectionType::hasFeature('recording', 'has_export'));

        // request_form has all features
        $this->assertTrue(SectionType::hasFeature('request_form', 'has_export'));
        $this->assertTrue(SectionType::hasFeature('request_form', 'requires_approval'));
        $this->assertTrue(SectionType::hasFeature('request_form', 'send_notifications'));
        $this->assertTrue(SectionType::hasFeature('request_form', 'has_workflow'));

        // scheduled_report has export and notifications
        $this->assertTrue(SectionType::hasFeature('scheduled_report', 'has_export'));
        $this->assertTrue(SectionType::hasFeature('scheduled_report', 'send_notifications'));
    }

    /**
     * Test hasFeature returns false for disabled features
     */
    public function testHasFeatureReturnsFalseForDisabledFeatures(): void
    {
        // recording doesn't have approval
        $this->assertFalse(SectionType::hasFeature('recording', 'requires_approval'));
        $this->assertFalse(SectionType::hasFeature('recording', 'send_notifications'));
        $this->assertFalse(SectionType::hasFeature('recording', 'has_workflow'));

        // hub has no features
        $this->assertFalse(SectionType::hasFeature('hub', 'has_export'));
        $this->assertFalse(SectionType::hasFeature('hub', 'requires_approval'));
        $this->assertFalse(SectionType::hasFeature('hub', 'send_notifications'));
        $this->assertFalse(SectionType::hasFeature('hub', 'has_workflow'));
    }

    /**
     * Test hasFeature returns false for unknown feature
     */
    public function testHasFeatureReturnsFalseForUnknownFeature(): void
    {
        $result = SectionType::hasFeature('recording', 'unknown_feature');
        
        $this->assertFalse($result);
    }

    /**
     * Test hasFeature returns false for unknown type
     */
    public function testHasFeatureReturnsFalseForUnknownType(): void
    {
        $result = SectionType::hasFeature('unknown_type', 'has_export');
        
        $this->assertFalse($result);
    }

    /**
     * Test isValid returns true for valid types
     */
    public function testIsValidReturnsTrueForValidTypes(): void
    {
        $this->assertTrue(SectionType::isValid('recording'));
        $this->assertTrue(SectionType::isValid('request_form'));
        $this->assertTrue(SectionType::isValid('scheduled_report'));
        $this->assertTrue(SectionType::isValid('hub'));
        $this->assertTrue(SectionType::isValid('other'));
    }

    /**
     * Test isValid returns false for invalid types
     */
    public function testIsValidReturnsFalseForInvalidTypes(): void
    {
        $this->assertFalse(SectionType::isValid('unknown'));
        $this->assertFalse(SectionType::isValid('invalid_type'));
        $this->assertFalse(SectionType::isValid(''));
    }

    /**
     * Test getSqlEnum returns valid SQL ENUM format
     */
    public function testGetSqlEnumReturnsValidFormat(): void
    {
        $enum = SectionType::getSqlEnum();

        $this->assertIsString($enum);
        $this->assertStringStartsWith('ENUM(', $enum);
        $this->assertStringEndsWith(')', $enum);
        
        // Verify all types are included
        $this->assertStringContainsString("'recording'", $enum);
        $this->assertStringContainsString("'request_form'", $enum);
        $this->assertStringContainsString("'scheduled_report'", $enum);
        $this->assertStringContainsString("'hub'", $enum);
        $this->assertStringContainsString("'other'", $enum);
    }

    /**
     * Test getSqlEnum format matches MySQL syntax
     */
    public function testGetSqlEnumMatchesMySQLSyntax(): void
    {
        $enum = SectionType::getSqlEnum();

        // Should match: ENUM('value1', 'value2', ...)
        $this->assertMatchesRegularExpression(
            "/^ENUM\('[\w_]++'(?:, '[\w_]++')*\)$/",
            $enum,
            'SQL ENUM format should match MySQL syntax'
        );
    }

    /**
     * Test getForJavaScript returns valid JSON
     */
    public function testGetForJavaScriptReturnsValidJSON(): void
    {
        $json = SectionType::getForJavaScript();

        $this->assertIsString($json);
        
        // Should be valid JSON
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertNotNull($decoded);
    }

    /**
     * Test getForJavaScript includes all type data
     */
    public function testGetForJavaScriptIncludesAllTypeData(): void
    {
        $json = SectionType::getForJavaScript();
        $decoded = json_decode($json, true);

        $this->assertCount(5, $decoded);

        // Verify each type has required fields
        foreach ($decoded as $type) {
            $this->assertArrayHasKey('value', $type);
            $this->assertArrayHasKey('label', $type);
            $this->assertArrayHasKey('description', $type);
            $this->assertArrayHasKey('icon', $type);
            $this->assertArrayHasKey('features', $type);
            $this->assertArrayHasKey('color', $type);
        }
    }

    /**
     * Test all methods are static
     */
    public function testAllMethodsAreStatic(): void
    {
        $reflection = new \ReflectionClass(SectionType::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $this->assertTrue(
                $method->isStatic(),
                "Method {$method->getName()} should be static"
            );
        }
    }

    /**
     * Test section type values are unique
     */
    public function testSectionTypeValuesAreUnique(): void
    {
        $types = SectionType::getAll();
        $values = array_column($types, 'value');

        $this->assertCount(count($values), array_unique($values), 'All section type values should be unique');
    }

    /**
     * Test section type colors are unique
     */
    public function testSectionTypeColorsAreUnique(): void
    {
        $types = SectionType::getAll();
        $colors = array_column($types, 'color');

        $this->assertCount(count($colors), array_unique($colors), 'All section type colors should be unique');
    }

    /**
     * Test section type icons are unique
     */
    public function testSectionTypeIconsAreUnique(): void
    {
        $types = SectionType::getAll();
        $icons = array_column($types, 'icon');

        $this->assertCount(count($icons), array_unique($icons), 'All section type icons should be unique');
    }
}
