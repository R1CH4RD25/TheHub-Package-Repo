<?php

namespace Hub\Package;

/**
 * Visual Configuration System
 *
 * Converts JSON visual config into CSS custom properties (design tokens).
 * Every configurable visual property uses the `--v-*` prefix so users
 * can adjust spacing, widths, colors, density, etc., without touching CSS.
 *
 * Philosophy:
 *   - Components should NEVER rely on "prayer" for layout.
 *   - Every visual dimension is a knob users can turn.
 *   - Presets provide sensible starting points; overrides handle the rest.
 *   - Contributors define component definitions; admins adjust visuals.
 *
 * Token Naming Convention:
 *   --v-{scope}-{property}
 *
 *   Scopes:
 *     table      Table-specific tokens
 *     filter     Filter/search bar tokens
 *     form       Form layout tokens
 *     card       Dashboard card tokens
 *     page       Page-level layout tokens
 *     text       Typography tokens
 *
 *   Examples:
 *     --v-table-row-height: 48px;
 *     --v-filter-input-width: 220px;
 *     --v-table-cell-padding-x: 1rem;
 *     --v-card-columns: 4;
 *
 * @see ComponentSchema for structural definitions
 * @see VisualPresets for built-in presets
 */
class VisualConfig
{
    /**
     * All supported visual tokens with their defaults, descriptions, and constraints.
     *
     * Format: 'token-name' => [
     *     'default'     => CSS value,
     *     'type'        => 'length' | 'color' | 'number' | 'string' | 'shadow',
     *     'description' => Human-readable explanation,
     *     'scope'       => Component scope,
     *     'min'         => Optional minimum (for numbers/lengths),
     *     'max'         => Optional maximum,
     *     'options'     => Optional enum for string type,
     * ]
     */
    public static function tokenRegistry(): array
    {
        return [
            // ─── TABLE TOKENS ──────────────────────────────
            'table-row-height'       => ['default' => '48px',     'type' => 'length',  'scope' => 'table', 'min' => '32px', 'max' => '80px',   'description' => 'Height of each table row'],
            'table-cell-padding-x'   => ['default' => '1rem',     'type' => 'length',  'scope' => 'table', 'min' => '0.25rem', 'max' => '3rem', 'description' => 'Horizontal padding inside cells'],
            'table-cell-padding-y'   => ['default' => '0.625rem', 'type' => 'length',  'scope' => 'table', 'min' => '0.125rem', 'max' => '2rem', 'description' => 'Vertical padding inside cells'],
            'table-header-padding-x' => ['default' => '1rem',     'type' => 'length',  'scope' => 'table', 'min' => '0.25rem', 'max' => '3rem', 'description' => 'Horizontal padding in header cells'],
            'table-header-padding-y' => ['default' => '0.75rem',  'type' => 'length',  'scope' => 'table', 'min' => '0.25rem', 'max' => '2rem', 'description' => 'Vertical padding in header cells'],
            'table-font-size'        => ['default' => '0.875rem', 'type' => 'length',  'scope' => 'table', 'min' => '0.7rem', 'max' => '1.25rem', 'description' => 'Base font size for table body'],
            'table-header-font-size' => ['default' => '0.8rem',   'type' => 'length',  'scope' => 'table', 'min' => '0.65rem', 'max' => '1rem', 'description' => 'Font size for table headers'],
            'table-border-radius'    => ['default' => '8px',      'type' => 'length',  'scope' => 'table', 'min' => '0', 'max' => '20px',     'description' => 'Corner rounding on table container'],
            'table-border-color'     => ['default' => '#e5e7eb',  'type' => 'color',   'scope' => 'table', 'description' => 'Border color for table container and dividers'],
            'table-header-bg'        => ['default' => '#f9fafb',  'type' => 'color',   'scope' => 'table', 'description' => 'Background color of table header row'],
            'table-header-color'     => ['default' => '#374151',  'type' => 'color',   'scope' => 'table', 'description' => 'Text color of table header row'],
            'table-row-bg'           => ['default' => '#ffffff',  'type' => 'color',   'scope' => 'table', 'description' => 'Default row background'],
            'table-row-hover'        => ['default' => '#f9fafb',  'type' => 'color',   'scope' => 'table', 'description' => 'Row background on hover'],
            'table-row-stripe'       => ['default' => 'transparent', 'type' => 'color', 'scope' => 'table', 'description' => 'Alternating row color (set to transparent to disable)'],
            'table-row-selected'     => ['default' => '#eff6ff',  'type' => 'color',   'scope' => 'table', 'description' => 'Background for selected rows'],
            'table-text-color'       => ['default' => '#1f2937',  'type' => 'color',   'scope' => 'table', 'description' => 'Default text color in cells'],
            'table-divider-color'    => ['default' => '#f3f4f6',  'type' => 'color',   'scope' => 'table', 'description' => 'Color of row divider lines'],
            'table-shadow'           => ['default' => 'none',     'type' => 'shadow',  'scope' => 'table', 'description' => 'Box shadow on the table container'],
            'table-max-width'        => ['default' => '100%',     'type' => 'length',  'scope' => 'table', 'description' => 'Maximum width of the table container'],
            'table-min-width'        => ['default' => '100%',     'type' => 'length',  'scope' => 'table', 'description' => 'Minimum width of the table (for scroll behavior)'],

            // ─── TOOLBAR TOKENS ────────────────────────────
            'toolbar-padding-x'      => ['default' => '0',       'type' => 'length',  'scope' => 'table', 'description' => 'Horizontal padding in the toolbar'],
            'toolbar-padding-y'      => ['default' => '0.75rem', 'type' => 'length',   'scope' => 'table', 'description' => 'Vertical padding in the toolbar'],
            'toolbar-gap'            => ['default' => '1rem',    'type' => 'length',   'scope' => 'table', 'description' => 'Gap between toolbar items'],
            'toolbar-info-font-size' => ['default' => '0.875rem', 'type' => 'length',   'scope' => 'table', 'description' => 'Font size of the record count text'],

            // ─── FILTER TOKENS ─────────────────────────────
            'filter-input-width'     => ['default' => '220px',   'type' => 'length',  'scope' => 'filter', 'min' => '100px', 'max' => '500px', 'description' => 'Width of search/text filter inputs'],
            'filter-select-width'    => ['default' => '180px',   'type' => 'length',  'scope' => 'filter', 'min' => '80px', 'max' => '400px',  'description' => 'Width of dropdown/select filters'],
            'filter-input-height'    => ['default' => '38px',    'type' => 'length',  'scope' => 'filter', 'min' => '28px', 'max' => '56px',   'description' => 'Height of filter inputs'],
            'filter-gap'             => ['default' => '1rem',    'type' => 'length',  'scope' => 'filter', 'min' => '0.25rem', 'max' => '3rem', 'description' => 'Gap between filter fields'],
            'filter-padding'         => ['default' => '1rem',    'type' => 'length',  'scope' => 'filter', 'description' => 'Padding inside the filter bar container'],
            'filter-bg'              => ['default' => '#ffffff', 'type' => 'color',   'scope' => 'filter', 'description' => 'Background of the filter bar'],
            'filter-border-radius'   => ['default' => '8px',     'type' => 'length',  'scope' => 'filter', 'description' => 'Corner rounding on filter bar'],
            'filter-border-color'    => ['default' => '#EDEBE9', 'type' => 'color',   'scope' => 'filter', 'description' => 'Border color of the filter bar'],
            'filter-label-size'      => ['default' => '0.75rem', 'type' => 'length',  'scope' => 'filter', 'description' => 'Font size for filter labels'],
            'filter-label-color'     => ['default' => '#605E5C', 'type' => 'color',   'scope' => 'filter', 'description' => 'Color of filter labels'],
            'filter-label-weight'    => ['default' => '600',     'type' => 'number',  'scope' => 'filter', 'description' => 'Font weight of filter labels'],

            // ─── ACTION TOKENS ─────────────────────────────
            'action-btn-size'        => ['default' => '0.78rem', 'type' => 'length',  'scope' => 'table', 'description' => 'Font size of row action buttons'],
            'action-btn-padding-x'   => ['default' => '0.625rem', 'type' => 'length',  'scope' => 'table', 'description' => 'Horizontal padding on action buttons'],
            'action-btn-padding-y'   => ['default' => '0.25rem', 'type' => 'length',  'scope' => 'table', 'description' => 'Vertical padding on action buttons'],
            'action-btn-radius'      => ['default' => '5px',     'type' => 'length',  'scope' => 'table', 'description' => 'Border radius on action buttons'],
            'action-gap'             => ['default' => '0.375rem', 'type' => 'length',   'scope' => 'table', 'description' => 'Gap between row action buttons'],

            // ─── PAGINATION TOKENS ─────────────────────────
            'pagination-gap'         => ['default' => '0.25rem', 'type' => 'length',   'scope' => 'table', 'description' => 'Gap between pagination buttons'],
            'pagination-btn-size'    => ['default' => '0.8rem',  'type' => 'length',   'scope' => 'table', 'description' => 'Font size of page buttons'],
            'pagination-padding'     => ['default' => '0.75rem 0',  'type' => 'string',  'scope' => 'table', 'description' => 'Padding around pagination bar'],

            // ─── BADGE TOKENS ──────────────────────────────
            'badge-font-size'        => ['default' => '0.75rem', 'type' => 'length',  'scope' => 'table', 'description' => 'Font size inside badge cells'],
            'badge-padding-x'        => ['default' => '0.5rem', 'type' => 'length',  'scope' => 'table', 'description' => 'Horizontal padding inside badges'],
            'badge-padding-y'        => ['default' => '0.2rem',  'type' => 'length',  'scope' => 'table', 'description' => 'Vertical padding inside badges'],
            'badge-radius'           => ['default' => '6px',  'type' => 'length',  'scope' => 'table', 'description' => 'Border radius on badges'],

            // ─── CARD/DASHBOARD TOKENS ─────────────────────
            'card-columns'           => ['default' => '4',       'type' => 'number',  'scope' => 'card', 'min' => 1, 'max' => 6,  'description' => 'Number of card columns on dashboard'],
            'card-gap'               => ['default' => '1rem',  'type' => 'length',  'scope' => 'card', 'description' => 'Gap between cards'],
            'card-padding'           => ['default' => '1.25rem', 'type' => 'length',  'scope' => 'card', 'description' => 'Internal padding of each card'],
            'card-radius'            => ['default' => '8px',    'type' => 'length',  'scope' => 'card', 'description' => 'Corner rounding on cards'],
            'card-shadow'            => ['default' => '0 1px 3px rgba(0,0,0,0.1)', 'type' => 'shadow', 'scope' => 'card', 'description' => 'Shadow on cards'],
            'card-border'            => ['default' => '1px solid #e5e7eb', 'type' => 'string', 'scope' => 'card', 'description' => 'Border style on cards'],
            'card-icon-size'         => ['default' => '1.75rem',  'type' => 'length',  'scope' => 'card', 'description' => 'Size of the icon in stat cards'],
            'card-value-size'        => ['default' => '1.5rem',    'type' => 'length',  'scope' => 'card', 'description' => 'Font size of the KPI value'],

            // ─── FORM TOKENS ───────────────────────────────
            'form-label-size'        => ['default' => '0.875rem', 'type' => 'length',  'scope' => 'form', 'description' => 'Font size for form labels'],
            'form-label-weight'      => ['default' => '500',     'type' => 'number',  'scope' => 'form', 'description' => 'Font weight for form labels'],
            'form-input-height'      => ['default' => '40px',    'type' => 'length',  'scope' => 'form', 'description' => 'Height of form inputs'],
            'form-input-radius'      => ['default' => '6px',     'type' => 'length',  'scope' => 'form', 'description' => 'Border radius of form inputs'],
            'form-section-gap'       => ['default' => '2rem',    'type' => 'length',  'scope' => 'form', 'description' => 'Gap between form sections'],
            'form-field-gap'         => ['default' => '1rem',    'type' => 'length',  'scope' => 'form', 'description' => 'Gap between form fields'],
            'form-grid-gap'          => ['default' => '1.5rem',  'type' => 'length',  'scope' => 'form', 'description' => 'Gap in grid layout forms'],

            // ─── PAGE TOKENS ───────────────────────────────
            'page-max-width'         => ['default' => '1400px',  'type' => 'length',  'scope' => 'page', 'description' => 'Maximum page content width'],
            'page-padding-x'         => ['default' => '2rem',    'type' => 'length',  'scope' => 'page', 'description' => 'Horizontal page padding'],
            'page-padding-y'         => ['default' => '1.5rem',  'type' => 'length',  'scope' => 'page', 'description' => 'Vertical padding at top/bottom of page'],
            'page-bg'                => ['default' => '#f8f9fa', 'type' => 'color',   'scope' => 'page', 'description' => 'Page background color'],
        ];
    }

    /**
     * Get available scopes for grouping tokens in UI
     */
    public static function scopes(): array
    {
        return [
            'table'  => ['label' => 'Table',         'icon' => 'bi-table',          'description' => 'Data table layout and appearance'],
            'filter' => ['label' => 'Filters',        'icon' => 'bi-funnel',         'description' => 'Search bars, dropdowns, and filter controls'],
            'card'   => ['label' => 'Cards/Dashboard', 'icon' => 'bi-grid-3x3-gap',  'description' => 'KPI cards and dashboard layouts'],
            'form'   => ['label' => 'Forms',           'icon' => 'bi-input-cursor',   'description' => 'Form inputs, labels, and layout'],
            'page'   => ['label' => 'Page Layout',     'icon' => 'bi-layout-text-window', 'description' => 'Page-level spacing and sizing'],
        ];
    }

    /**
     * Get tokens filtered by scope
     */
    public static function tokensByScope(string $scope): array
    {
        return array_filter(self::tokenRegistry(), fn($t) => $t['scope'] === $scope);
    }

    /**
     * Convert a visual config array into inline CSS custom properties.
     *
     * @param array $config Key-value pairs: token name => value
     *                      e.g., ['table-row-height' => '40px', 'filter-input-width' => '280px']
     * @return string CSS custom property declarations (for a style="" attribute)
     */
    public static function toInlineStyle(array $config): string
    {
        $registry = self::tokenRegistry();
        $props = [];

        foreach ($config as $token => $value) {
            if (!isset($registry[$token])) {
                continue; // Skip unrecognized tokens
            }
            $sanitized = self::sanitizeValue($value, $registry[$token]);
            if ($sanitized !== null) {
                $props[] = "--v-{$token}: {$sanitized}";
            }
        }

        return implode('; ', $props);
    }

    /**
     * Convert a visual config array into a <style> block.
     *
     * @param array  $config     Token overrides
     * @param string $selector   CSS selector to scope (default: :root)
     * @param string $componentId Optional component ID for scoping
     * @return string <style> tag
     */
    public static function toStyleBlock(array $config, string $selector = ':root', string $componentId = ''): string
    {
        if ($componentId) {
            $selector = "#" . htmlspecialchars($componentId, ENT_QUOTES);
        }

        $registry = self::tokenRegistry();
        $lines = [];

        foreach ($config as $token => $value) {
            if (!isset($registry[$token])) {
                continue;
            }
            $sanitized = self::sanitizeValue($value, $registry[$token]);
            if ($sanitized !== null) {
                $lines[] = "  --v-{$token}: {$sanitized};";
            }
        }

        if (empty($lines)) {
            return '';
        }

        return "<style>{$selector} {\n" . implode("\n", $lines) . "\n}</style>";
    }

    /**
     * Generate all default token declarations as a CSS block.
     *
     * @param string $selector CSS selector (default: :root)
     * @return string Full CSS with all defaults
     */
    public static function defaultsAsCSS(string $selector = ':root'): string
    {
        $registry = self::tokenRegistry();
        $lines = [];
        $currentScope = '';

        foreach ($registry as $token => $def) {
            if ($def['scope'] !== $currentScope) {
                $currentScope = $def['scope'];
                $scope = self::scopes()[$currentScope] ?? ['label' => $currentScope];
                $lines[] = "\n  /* ── {$scope['label']} ──────────── */";
            }
            $lines[] = "  --v-{$token}: {$def['default']};";
        }

        return "{$selector} {\n" . implode("\n", $lines) . "\n}";
    }

    /**
     * Merge a preset with user overrides into a final config.
     *
     * @param string $presetName Built-in preset name
     * @param array  $overrides  User overrides on top of preset
     * @return array Merged config (token => value)
     */
    public static function resolveConfig(string $presetName = 'comfortable', array $overrides = []): array
    {
        $preset = VisualPresets::get($presetName);
        return array_merge($preset, $overrides);
    }

    /**
     * Get the JSON schema for visual config (used in package validation).
     *
     * @return array JSON schema for the 'visual' section of a package definition
     */
    public static function jsonSchema(): array
    {
        $properties = [];
        foreach (self::tokenRegistry() as $token => $def) {
            $prop = [
                'type'        => self::jsonType($def['type']),
                'description' => $def['description'],
                'default'     => $def['default'],
            ];
            if (isset($def['min'])) {
                $prop['minimum'] = $def['min'];
            }
            if (isset($def['max'])) {
                $prop['maximum'] = $def['max'];
            }
            if (isset($def['options'])) {
                $prop['enum'] = $def['options'];
            }
            $properties[$token] = $prop;
        }

        return [
            'type'        => 'object',
            'description' => 'Visual configuration tokens (CSS custom properties).  ' .
                'Each token maps to a --v-{name} CSS variable.  ' .
                'Use presets as a starting point, then override individual tokens.',
            'properties'  => [
                'preset' => [
                    'type'        => 'string',
                    'description' => 'Built-in visual preset to use as base',
                    'enum'        => VisualPresets::names(),
                    'default'     => 'comfortable',
                ],
                'tokens' => [
                    'type'        => 'object',
                    'description' => 'Override individual visual tokens',
                    'properties'  => $properties,
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Sanitize a token value.
     * Prevents CSS injection while allowing valid CSS values.
     */
    private static function sanitizeValue(string $value, array $tokenDef): ?string
    {
        // Remove any attempts at CSS injection
        $value = trim($value);

        // Block dangerous patterns
        if (preg_match('/[;{}]|url\s*\(|expression\s*\(|javascript:|@import/i', $value)) {
            return null;
        }

        // Type-specific validation
        switch ($tokenDef['type']) {
            case 'color':
                // Accept hex, rgb(), rgba(), hsl(), hsla(), named colors
                if (!preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\)|hsla?\([^)]+\)|transparent|inherit|currentColor|[a-zA-Z]+)$/', $value)) {
                    return null;
                }
                break;

            case 'length':
                // Accept number + unit, calc(), var()
                if (!preg_match('/^(-?\d+\.?\d*\s*(px|rem|em|%|vw|vh|ch|ex|pt|cm|mm|in|pc)?|auto|100%|calc\([^)]+\)|var\([^)]+\)|0)$/', $value)) {
                    return null;
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    return null;
                }
                break;

            case 'shadow':
                // Accept shadow syntax or 'none'
                if ($value !== 'none' && !preg_match('/^[\d.\-\s]+(?:px|rem|em)?\s*(?:[\d.\-\s]+(?:px|rem|em)?\s*){1,3}(?:rgba?\([^)]+\)|#[0-9a-fA-F]{3,8}|[a-zA-Z]+)(?:\s*,\s*[\d.\-\s]+(?:px|rem|em)?\s*(?:[\d.\-\s]+(?:px|rem|em)?\s*){1,3}(?:rgba?\([^)]+\)|#[0-9a-fA-F]{3,8}|[a-zA-Z]+))*$/i', $value)) {
                    return null;
                }
                break;

            case 'string':
                // General string — just length check
                if (strlen($value) > 200) {
                    return null;
                }
                break;
        }

        return $value;
    }

    private static function jsonType(string $tokenType): string
    {
        return match ($tokenType) {
            'number' => 'number',
            default  => 'string',
        };
    }
}
