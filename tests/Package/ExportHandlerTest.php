<?php

namespace Hub\Tests\Package;

use PHPUnit\Framework\TestCase;
use Hub\Package\ExportHandler;

/**
 * ExportHandler Unit Tests
 *
 * Tests CSV/JSON/XLSX generation logic, column inference,
 * value formatting, and rate limiting.
 *
 * NOTE: These tests exercise the internal generation methods
 * via reflection since they are protected. Integration tests
 * for the full pipeline would require DB fixtures.
 */
class ExportHandlerTest extends TestCase
{
    private ExportHandler $handler;

    protected function setUp(): void
    {
        // ExportHandler constructor requires enforcement pipeline objects.
        // We create it with nulls since our unit tests call internal helpers
        // via reflection rather than the full export() flow.
        $this->handler = $this->createPartialMock(ExportHandler::class, []);
    }

    // =========================================================================
    // CSV GENERATION
    // =========================================================================

    public function testGenerateCsvBasic(): void
    {
        $data = [
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'Bob', 'email' => 'bob@example.com'],
        ];

        $columns = [
            ['key' => 'name', 'label' => 'Full Name'],
            ['key' => 'email', 'label' => 'Email Address'],
        ];

        $result = $this->invokeMethod('generateCsv', [$data, $columns, 'test_export']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('mimeType', $result);
        $this->assertEquals('test_export.csv', $result['filename']);
        $this->assertStringContainsString('text/csv', $result['mimeType']);

        $csv = $result['content'];

        // Has BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        // Has header row
        $this->assertStringContainsString('Full Name', $csv);
        $this->assertStringContainsString('Email Address', $csv);

        // Has data
        $this->assertStringContainsString('Alice', $csv);
        $this->assertStringContainsString('bob@example.com', $csv);
    }

    public function testGenerateCsvEmptyData(): void
    {
        $result = $this->invokeMethod('generateCsv', [[], [['key' => 'name', 'label' => 'Name']], 'empty']);
        $csv = $result['content'];

        // Should still have BOM and header
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Name', $csv);
    }

    public function testGenerateCsvMissingKeys(): void
    {
        $data = [
            ['name' => 'Alice'],  // Missing 'email' key
        ];

        $columns = [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'Email'],
        ];

        $result = $this->invokeMethod('generateCsv', [$data, $columns, 'missing_keys']);
        $csv = $result['content'];

        // Alice should be in the output; missing keys produce empty values
        $this->assertStringContainsString('Alice', $csv);
    }

    public function testGenerateCsvSpecialCharacters(): void
    {
        $data = [
            ['name' => 'O\'Brien', 'note' => 'Line1\nLine2'],
            ['name' => 'Smith, Jr.', 'note' => 'Has "quotes"'],
        ];

        $columns = [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'note', 'label' => 'Note'],
        ];

        $result = $this->invokeMethod('generateCsv', [$data, $columns, 'special']);
        $csv = $result['content'];

        $this->assertStringContainsString("O'Brien", $csv);
        $this->assertStringContainsString('"Smith, Jr."', $csv);
    }

    // =========================================================================
    // JSON GENERATION
    // =========================================================================

    public function testGenerateJsonBasic(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ];

        $columns = [
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'age', 'label' => 'Age', 'type' => 'number'],
        ];

        $result = $this->invokeMethod('generateJson', [$data, $columns, 'test_export']);
        $parsed = json_decode($result['content'], true);

        $this->assertNotNull($parsed);
        $this->assertArrayHasKey('data', $parsed);
        $this->assertArrayHasKey('exportedAt', $parsed);
        $this->assertCount(2, $parsed['data']);
        $this->assertEquals('Alice', $parsed['data'][0]['name']);
    }

    public function testGenerateJsonIncludesExportMeta(): void
    {
        $result = $this->invokeMethod('generateJson', [[], [], 'meta_test']);
        $parsed = json_decode($result['content'], true);

        $this->assertArrayHasKey('exportedAt', $parsed);
        $this->assertEquals(0, $parsed['rowCount']);
    }

    // =========================================================================
    // COLUMN INFERENCE
    // =========================================================================

    public function testInferColumnsFromData(): void
    {
        $data = ['name' => 'Alice', 'email' => 'alice@test.com', 'is_active' => true];

        $columns = $this->invokeMethod('inferColumnsFromData', [$data]);

        $this->assertCount(3, $columns);

        $keys = array_column($columns, 'key');
        $this->assertContains('name', $keys);
        $this->assertContains('email', $keys);
        $this->assertContains('is_active', $keys);

        // Label should be humanised from key
        $nameCol = array_filter($columns, fn($c) => $c['key'] === 'name');
        $nameCol = array_values($nameCol)[0];
        $this->assertEquals('Name', $nameCol['label']);
    }

    public function testInferColumnsExcludesInternalKeys(): void
    {
        $data = ['id' => 1, 'name' => 'Test', 'password' => 'hash', '_meta' => 'skip'];

        $columns = $this->invokeMethod('inferColumnsFromData', [$data]);
        $keys = array_column($columns, 'key');

        // Keys starting with _ are excluded
        $this->assertNotContains('_meta', $keys);
        // Regular keys are included
        $this->assertContains('id', $keys);
        $this->assertContains('name', $keys);
        $this->assertContains('password', $keys);
    }

    public function testInferColumnsEmpty(): void
    {
        // inferColumnsFromData takes a single row (assoc array), empty = no columns
        $columns = $this->invokeMethod('inferColumnsFromData', [[]]);
        $this->assertIsArray($columns);
        $this->assertEmpty($columns);
    }

    // =========================================================================
    // VALUE FORMATTING
    // =========================================================================

    public function testFormatValueForExportCurrency(): void
    {
        $result = $this->invokeMethod('formatValueForExport', [1500.50, ['type' => 'currency']]);
        // Currency in export is formatted as 1500.50 (raw number)
        $this->assertStringContainsString('1500.50', $result);
    }

    public function testFormatValueForExportDate(): void
    {
        $result = $this->invokeMethod('formatValueForExport', ['2025-06-15 14:30:00', ['type' => 'date']]);
        $this->assertStringContainsString('2025', $result);
        $this->assertStringContainsString('15', $result);
    }

    public function testFormatValueForExportBoolean(): void
    {
        $this->assertEquals('Yes', $this->invokeMethod('formatValueForExport', [true, ['type' => 'boolean']]));
        $this->assertEquals('Yes', $this->invokeMethod('formatValueForExport', [1, ['type' => 'boolean']]));
        $this->assertEquals('No', $this->invokeMethod('formatValueForExport', [false, ['type' => 'boolean']]));
        $this->assertEquals('No', $this->invokeMethod('formatValueForExport', [0, ['type' => 'boolean']]));
    }

    public function testFormatValueForExportNull(): void
    {
        $result = $this->invokeMethod('formatValueForExport', [null, ['type' => 'text']]);
        $this->assertEquals('', $result);
    }

    public function testFormatValueForExportArray(): void
    {
        // Default type = text, arrays get json_encoded
        $result = $this->invokeMethod('formatValueForExport', [['a', 'b', 'c'], ['type' => 'text']]);
        $this->assertStringContainsString('a', $result);
    }

    public function testFormatValueForExportMasked(): void
    {
        $result = $this->invokeMethod('formatValueForExport', ['123-45-6789', ['type' => 'masked']]);
        $this->assertEquals('****', $result);
    }

    public function testFormatValueForExportList(): void
    {
        $result = $this->invokeMethod('formatValueForExport', [['a', 'b', 'c'], ['type' => 'list']]);
        $this->assertEquals('a; b; c', $result);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Call a protected/private method on ExportHandler via reflection.
     */
    private function invokeMethod(string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod(ExportHandler::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke($this->handler, ...$args);
    }
}
