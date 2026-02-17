<?php

namespace Hub\Tests\Package;

use PHPUnit\Framework\TestCase;
use Hub\Package\VisualConfig;
use Hub\Package\VisualPresets;
use Hub\Package\ComponentCatalog;

/**
 * Visual Token System Tests
 *
 * Tests VisualConfig, VisualPresets, and ComponentCatalog.
 * Validates token registry, sanitization, CSS output, presets, and catalog completeness.
 */
class VisualTokenSystemTest extends TestCase
{
    // ─── VisualConfig ──────────────────────────────────────────

    public function testTokenRegistryReturnsNonEmptyArray(): void
    {
        $registry = VisualConfig::tokenRegistry();
        $this->assertIsArray($registry);
        $this->assertGreaterThan(50, count($registry));
    }

    public function testEveryTokenHasRequiredKeys(): void
    {
        foreach (VisualConfig::tokenRegistry() as $name => $def) {
            $this->assertArrayHasKey('default', $def, "Token '$name' missing 'default'");
            $this->assertArrayHasKey('type', $def, "Token '$name' missing 'type'");
            $this->assertArrayHasKey('scope', $def, "Token '$name' missing 'scope'");
            $this->assertArrayHasKey('description', $def, "Token '$name' missing 'description'");
        }
    }

    public function testTokenTypesAreValid(): void
    {
        $validTypes = ['length', 'color', 'shadow', 'string', 'number'];
        foreach (VisualConfig::tokenRegistry() as $name => $def) {
            $this->assertContains($def['type'], $validTypes, "Token '$name' has invalid type '{$def['type']}'");
        }
    }

    public function testTokenScopesAreValid(): void
    {
        $validScopes = array_keys(VisualConfig::scopes());
        foreach (VisualConfig::tokenRegistry() as $name => $def) {
            $this->assertContains($def['scope'], $validScopes, "Token '$name' has invalid scope '{$def['scope']}'");
        }
    }

    public function testSanitizeValueBlocksInjectionViaInlineStyle(): void
    {
        // sanitizeValue is private — test via toInlineStyle which calls it
        $tokens = [
            'table-row-height' => 'expression(alert(1))',  // injection attempt
            'table-font-size' => '0.8rem',                  // valid
        ];
        $result = VisualConfig::toInlineStyle($tokens);
        $this->assertStringNotContainsString('expression', $result);
        $this->assertStringContainsString('--v-table-font-size: 0.8rem', $result);
    }

    public function testSanitizeValueBlocksUrlInjectionViaInlineStyle(): void
    {
        $tokens = [
            'table-header-bg' => 'url(http://evil.com)',
            'table-row-bg' => '#ffffff',
        ];
        $result = VisualConfig::toInlineStyle($tokens);
        $this->assertStringNotContainsString('url(', $result);
        $this->assertStringContainsString('--v-table-row-bg: #ffffff', $result);
    }

    public function testSanitizeValueBlocksVarInjectionViaInlineStyle(): void
    {
        $tokens = ['table-border-color' => 'var(--nasty)'];
        $result = VisualConfig::toInlineStyle($tokens);
        $this->assertStringNotContainsString('var(', $result);
    }

    public function testToInlineStyleOutputsCorrectFormat(): void
    {
        $tokens = ['table-row-height' => '40px', 'table-font-size' => '0.8rem'];
        $result = VisualConfig::toInlineStyle($tokens);
        $this->assertStringContainsString('--v-table-row-height: 40px', $result);
        $this->assertStringContainsString('--v-table-font-size: 0.8rem', $result);
    }

    public function testToInlineStyleStripsInvalidTokens(): void
    {
        $tokens = [
            'table-row-height' => '40px',
            'bogus-not-real' => '999px',
        ];
        $result = VisualConfig::toInlineStyle($tokens);
        $this->assertStringContainsString('--v-table-row-height: 40px', $result);
        $this->assertStringNotContainsString('bogus', $result);
    }

    public function testToStyleBlockScopesToSelector(): void
    {
        $tokens = ['table-row-height' => '40px'];
        $result = VisualConfig::toStyleBlock($tokens, '', 'my-table');
        $this->assertStringContainsString('#my-table', $result);
        $this->assertStringContainsString('--v-table-row-height: 40px', $result);
    }

    public function testToStyleBlockReturnsEmptyForNoTokens(): void
    {
        $result = VisualConfig::toStyleBlock([], '', 'x');
        $this->assertEmpty($result);
    }

    public function testResolveConfigMergesPresetAndOverrides(): void
    {
        $result = VisualConfig::resolveConfig('compact', ['table-row-height' => '44px']);
        // Overrides should win
        $this->assertEquals('44px', $result['table-row-height']);
        // Other compact tokens should be present
        $this->assertArrayHasKey('table-cell-padding-x', $result);
    }

    public function testResolveConfigFallsBackOnInvalidPreset(): void
    {
        // Invalid preset should fall back to comfortable (empty overrides)
        $result = VisualConfig::resolveConfig('nonexistent-typo');
        $this->assertEmpty($result, 'Invalid preset should fall back to comfortable (no overrides)');
    }

    public function testResolveConfigComfortableReturnsEmpty(): void
    {
        $result = VisualConfig::resolveConfig('comfortable');
        $this->assertEmpty($result, 'Comfortable preset has no overrides — just uses defaults');
    }

    public function testDefaultsAsCSSOutputsAllTokens(): void
    {
        $css = VisualConfig::defaultsAsCSS();
        $this->assertStringContainsString(':root', $css);
        $this->assertStringContainsString('--v-table-row-height', $css);
        $this->assertStringContainsString('--v-card-gap', $css);
        $this->assertStringContainsString('--v-filter-input-width', $css);
    }

    public function testJsonSchemaHasPresetAndTokens(): void
    {
        $schema = VisualConfig::jsonSchema();
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('preset', $schema['properties']);
        $this->assertArrayHasKey('tokens', $schema['properties']);
        $presetEnum = $schema['properties']['preset']['enum'];
        $this->assertContains('compact', $presetEnum);
        $this->assertContains('comfortable', $presetEnum);
        $this->assertContains('data-grid', $presetEnum);
    }

    // ─── VisualPresets ─────────────────────────────────────────

    public function testPresetsListContainsFivePresets(): void
    {
        $names = VisualPresets::names();
        $this->assertCount(5, $names);
        $this->assertContains('comfortable', $names);
        $this->assertContains('compact', $names);
        $this->assertContains('spacious', $names);
        $this->assertContains('print', $names);
        $this->assertContains('data-grid', $names);
    }

    public function testComfortablePresetIsEmpty(): void
    {
        $preset = VisualPresets::get('comfortable');
        $this->assertEmpty($preset, 'Comfortable preset should have no overrides');
    }

    public function testCompactPresetHasSmallerValues(): void
    {
        $compact = VisualPresets::get('compact');
        $this->assertNotEmpty($compact);
        // Compact should reduce row height
        $this->assertArrayHasKey('table-row-height', $compact);
        $compactHeight = (int) $compact['table-row-height'];
        $this->assertLessThan(48, $compactHeight, 'Compact row height should be less than default 48px');
    }

    public function testSpaciousPresetHasLargerValues(): void
    {
        $spacious = VisualPresets::get('spacious');
        $this->assertNotEmpty($spacious);
        $this->assertArrayHasKey('table-row-height', $spacious);
        $spaciousHeight = (int) $spacious['table-row-height'];
        $this->assertGreaterThan(48, $spaciousHeight, 'Spacious row height should exceed default 48px');
    }

    public function testPrintPresetRemovesShadows(): void
    {
        $print = VisualPresets::get('print');
        $this->assertNotEmpty($print);
        $this->assertEquals('none', $print['table-shadow'] ?? 'none');
        $this->assertEquals('none', $print['card-shadow'] ?? 'none');
    }

    public function testGetReturnsEmptyForUnknownPreset(): void
    {
        $result = VisualPresets::get('does-not-exist');
        $this->assertEmpty($result);
    }

    public function testAllPresetsReturnValidTokenNames(): void
    {
        $registry = VisualConfig::tokenRegistry();
        foreach (VisualPresets::all() as $name => $def) {
            $tokens = $def['tokens'] ?? [];
            foreach ($tokens as $token => $value) {
                $this->assertArrayHasKey($token, $registry, "Preset '$name' has token '$token' not in registry");
            }
        }
    }

    public function testAllPresetsHaveMetadata(): void
    {
        $all = VisualPresets::all();
        foreach ($all as $name => $def) {
            $this->assertArrayHasKey('label', $def, "Preset '$name' missing 'label'");
            $this->assertArrayHasKey('description', $def, "Preset '$name' missing 'description'");
            $this->assertArrayHasKey('tokens', $def, "Preset '$name' missing 'tokens'");
        }
    }

    // ─── ComponentCatalog ──────────────────────────────────────

    public function testCatalogHasFiveTypes(): void
    {
        $types = ComponentCatalog::types();
        $this->assertCount(5, $types);
        $this->assertArrayHasKey('table', $types);
        $this->assertArrayHasKey('filters', $types);
        $this->assertArrayHasKey('dashboard', $types);
        $this->assertArrayHasKey('form', $types);
        $this->assertArrayHasKey('detail', $types);
    }

    public function testCatalogGetReturnsCompleteEntry(): void
    {
        $entry = ComponentCatalog::get('table');
        $this->assertNotNull($entry);
        $this->assertArrayHasKey('name', $entry);
        $this->assertArrayHasKey('purpose', $entry);
        $this->assertArrayHasKey('required', $entry);
        $this->assertArrayHasKey('example', $entry);
        $this->assertArrayHasKey('visualTokens', $entry);
    }

    public function testCatalogGetReturnsNullForUnknown(): void
    {
        $this->assertNull(ComponentCatalog::get('nonexistent'));
    }

    public function testCatalogRulesHaveRequiredAndOptional(): void
    {
        foreach (['table', 'filters', 'dashboard', 'form', 'detail'] as $type) {
            $rules = ComponentCatalog::rules($type);
            $this->assertNotNull($rules, "Rules for '$type' should not be null");
            $this->assertArrayHasKey('required', $rules, "Rules for '$type' missing 'required'");
            $this->assertArrayHasKey('optional', $rules, "Rules for '$type' missing 'optional'");
        }
    }

    public function testCatalogFieldTypesReturnsNonEmpty(): void
    {
        foreach (['table', 'filters', 'form', 'detail'] as $type) {
            $fieldTypes = ComponentCatalog::fieldTypes($type);
            $this->assertNotEmpty($fieldTypes, "Field types for '$type' should not be empty");
        }
    }

    public function testCatalogExampleJsonIsValidJson(): void
    {
        foreach (['table', 'filters', 'dashboard', 'form', 'detail'] as $type) {
            $json = ComponentCatalog::exampleJson($type);
            $decoded = json_decode($json, true);
            $this->assertNotNull($decoded, "Example JSON for '$type' is invalid JSON");
        }
    }

    public function testCatalogSearchFindsMatch(): void
    {
        $results = ComponentCatalog::search('chart');
        $this->assertArrayHasKey('dashboard', $results, 'Searching "chart" should find dashboard');
    }

    public function testCatalogSearchReturnsEmptyForNonsense(): void
    {
        $results = ComponentCatalog::search('zzz_no_match_999');
        $this->assertEmpty($results);
    }

    public function testCatalogToMarkdownProducesOutput(): void
    {
        $md = ComponentCatalog::toMarkdown();
        $this->assertNotEmpty($md);
        $this->assertStringContainsString('# Component Catalog', $md);
        $this->assertStringContainsString('table', $md);
        $this->assertStringContainsString('filters', $md);
        $this->assertStringContainsString('dashboard', $md);
        $this->assertStringContainsString('form', $md);
        $this->assertStringContainsString('detail', $md);
    }

    public function testCatalogVisualTokensArePresentForAllTypes(): void
    {
        foreach (['table', 'filters', 'dashboard', 'form', 'detail'] as $type) {
            $tokens = ComponentCatalog::visualTokens($type);
            $this->assertNotEmpty($tokens, "Visual tokens for '$type' should not be empty");
        }
    }

    // ─── Cross-system consistency ──────────────────────────────

    public function testCSSFallbacksMatchRegistryDefaults(): void
    {
        $cssPath = dirname(__DIR__, 2) . '/public/assets/css/package-components.css';
        if (!file_exists($cssPath)) {
            $this->markTestSkipped('CSS file not found');
        }

        $css = file_get_contents($cssPath);
        $registry = VisualConfig::tokenRegistry();

        // Extract var(--v-TOKEN, FALLBACK) patterns from CSS
        preg_match_all('/var\(--v-([a-z\-]+),\s*([^)]+)\)/', $css, $matches, PREG_SET_ORDER);

        $mismatches = [];
        foreach ($matches as $m) {
            $token = $m[1];
            $fallback = trim($m[2]);
            if (!isset($registry[$token])) {
                continue; // Token not in registry (e.g., hub-primary)
            }
            $expected = $registry[$token]['default'];
            if ($fallback !== $expected) {
                $mismatches[] = "$token: CSS='$fallback' vs Registry='$expected'";
            }
        }

        $this->assertEmpty($mismatches, "CSS fallbacks don't match registry defaults:\n" . implode("\n", $mismatches));
    }

    public function testPresetTokensExistInRegistry(): void
    {
        $registry = VisualConfig::tokenRegistry();
        foreach (VisualPresets::all() as $presetName => $def) {
            $tokens = $def['tokens'] ?? [];
            foreach (array_keys($tokens) as $token) {
                $this->assertArrayHasKey(
                    $token,
                    $registry,
                    "Preset '$presetName' references token '$token' not in VisualConfig registry"
                );
            }
        }
    }

    public function testJsonSchemaTokensExistInRegistry(): void
    {
        $schema = VisualConfig::jsonSchema();
        $schemaTokens = array_keys($schema['properties']['tokens']['properties'] ?? []);
        $registry = VisualConfig::tokenRegistry();

        foreach ($schemaTokens as $token) {
            $this->assertArrayHasKey(
                $token,
                $registry,
                "JSON schema references token '$token' not in VisualConfig registry"
            );
        }
    }
}
