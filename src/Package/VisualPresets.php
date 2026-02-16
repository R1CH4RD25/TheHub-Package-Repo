<?php

namespace Hub\Package;

/**
 * Visual Presets — Built-in Density & Style Collections
 *
 * Each preset is a named set of token overrides (only tokens that differ
 * from the defaults in VisualConfig::tokenRegistry).
 *
 * Resolution order:
 *   1. Registry defaults (VisualConfig)
 *   2. Preset overrides (this class)
 *   3. Package-level overrides (JSON "visual.tokens")
 *   4. Org-level overrides (future: admin settings)
 *
 * @see VisualConfig for the full token registry
 * @see docs/VISUAL_TOKEN_SYSTEM.md for architecture overview
 */
class VisualPresets
{
    /**
     * Get all available presets.
     *
     * @return array<string, array{label: string, description: string, tokens: array<string, string>}>
     */
    public static function all(): array
    {
        return [
            // ─── COMFORTABLE ───────────────────────────────
            // Default — no overrides, uses registry defaults exactly.
            'comfortable' => [
                'label'       => 'Comfortable',
                'description' => 'Balanced spacing and sizing. The default experience for most users.',
                'tokens'      => [],
            ],

            // ─── COMPACT ───────────────────────────────────
            // Dense data views — admin dashboards, logs, data-heavy tables.
            // Reduces spacing ~30-40% across all scopes.
            'compact' => [
                'label'       => 'Compact',
                'description' => 'Tight spacing for data-dense views. Best for admin panels and log tables.',
                'tokens'      => [
                    // Table
                    'table-row-height'       => '36px',
                    'table-cell-padding-x'   => '0.625rem',
                    'table-cell-padding-y'   => '0.3rem',
                    'table-header-padding-x' => '0.625rem',
                    'table-header-padding-y' => '0.5rem',
                    'table-font-size'        => '0.8rem',
                    'table-header-font-size' => '0.7rem',
                    'table-border-radius'    => '6px',

                    // Toolbar
                    'toolbar-padding-y'      => '0.5rem',
                    'toolbar-gap'            => '0.5rem',
                    'toolbar-info-font-size' => '0.8rem',

                    // Filters
                    'filter-input-width'     => '180px',
                    'filter-select-width'    => '150px',
                    'filter-input-height'    => '32px',
                    'filter-gap'             => '0.5rem',
                    'filter-padding'         => '0.625rem',
                    'filter-label-size'      => '0.65rem',

                    // Actions
                    'action-btn-size'        => '0.7rem',
                    'action-btn-padding-x'   => '0.4rem',
                    'action-btn-padding-y'   => '0.15rem',
                    'action-btn-radius'      => '4px',
                    'action-gap'             => '0.25rem',

                    // Pagination
                    'pagination-gap'         => '0.25rem',
                    'pagination-btn-size'    => '0.75rem',
                    'pagination-padding'     => '0.5rem 0',

                    // Badges
                    'badge-font-size'        => '0.65rem',
                    'badge-padding-x'        => '0.4rem',
                    'badge-padding-y'        => '0.125rem',

                    // Cards
                    'card-gap'               => '0.75rem',
                    'card-padding'           => '0.875rem',
                    'card-radius'            => '8px',
                    'card-icon-size'         => '1.75rem',
                    'card-value-size'        => '1.5rem',

                    // Forms
                    'form-input-height'      => '34px',
                    'form-section-gap'       => '1.25rem',
                    'form-field-gap'         => '0.625rem',
                    'form-grid-gap'          => '1rem',

                    // Page
                    'page-padding-x'         => '1.25rem',
                    'page-padding-y'         => '1rem',
                ],
            ],

            // ─── SPACIOUS ──────────────────────────────────
            // Comfortable with extra breathing room — public-facing,
            // accessibility-focused, or presentation views.
            'spacious' => [
                'label'       => 'Spacious',
                'description' => 'Generous spacing for readability. Good for presentations and accessibility.',
                'tokens'      => [
                    // Table
                    'table-row-height'       => '56px',
                    'table-cell-padding-x'   => '1.25rem',
                    'table-cell-padding-y'   => '0.875rem',
                    'table-header-padding-x' => '1.25rem',
                    'table-header-padding-y' => '1rem',
                    'table-font-size'        => '0.95rem',
                    'table-header-font-size' => '0.85rem',
                    'table-border-radius'    => '12px',
                    'table-shadow'           => '0 1px 3px rgba(0,0,0,0.08)',

                    // Toolbar
                    'toolbar-padding-y'      => '1rem',
                    'toolbar-gap'            => '1.5rem',
                    'toolbar-info-font-size' => '0.95rem',

                    // Filters
                    'filter-input-width'     => '280px',
                    'filter-select-width'    => '220px',
                    'filter-input-height'    => '44px',
                    'filter-gap'             => '1.5rem',
                    'filter-padding'         => '1.25rem',
                    'filter-border-radius'   => '12px',
                    'filter-label-size'      => '0.8rem',

                    // Actions
                    'action-btn-size'        => '0.85rem',
                    'action-btn-padding-x'   => '0.875rem',
                    'action-btn-padding-y'   => '0.375rem',
                    'action-btn-radius'      => '6px',
                    'action-gap'             => '0.5rem',

                    // Pagination
                    'pagination-gap'         => '0.5rem',
                    'pagination-btn-size'    => '0.85rem',
                    'pagination-padding'     => '1.25rem 0',

                    // Badges
                    'badge-font-size'        => '0.8rem',
                    'badge-padding-x'        => '0.75rem',
                    'badge-padding-y'        => '0.3rem',

                    // Cards
                    'card-gap'               => '2rem',
                    'card-padding'           => '1.75rem',
                    'card-radius'            => '16px',
                    'card-shadow'            => '0 2px 8px rgba(0,0,0,0.1)',
                    'card-icon-size'         => '3rem',
                    'card-value-size'        => '2.5rem',

                    // Forms
                    'form-input-height'      => '46px',
                    'form-input-radius'      => '8px',
                    'form-section-gap'       => '2.5rem',
                    'form-field-gap'         => '1.5rem',
                    'form-grid-gap'          => '2rem',

                    // Page
                    'page-padding-x'         => '3rem',
                    'page-padding-y'         => '2rem',
                ],
            ],

            // ─── PRINT ─────────────────────────────────────
            // Optimized for printed output — no shadows, tight spacing,
            // high contrast borders.
            'print' => [
                'label'       => 'Print',
                'description' => 'Optimized for printed output. No shadows, tight layout, clear borders.',
                'tokens'      => [
                    // Table
                    'table-row-height'       => '32px',
                    'table-cell-padding-x'   => '0.5rem',
                    'table-cell-padding-y'   => '0.25rem',
                    'table-header-padding-x' => '0.5rem',
                    'table-header-padding-y' => '0.375rem',
                    'table-font-size'        => '0.75rem',
                    'table-header-font-size' => '0.7rem',
                    'table-border-radius'    => '0',
                    'table-border-color'     => '#000000',
                    'table-header-bg'        => '#e5e7eb',
                    'table-header-color'     => '#000000',
                    'table-row-hover'        => 'transparent',
                    'table-row-stripe'       => '#f9fafb',
                    'table-text-color'       => '#000000',
                    'table-divider-color'    => '#d1d5db',
                    'table-shadow'           => 'none',

                    // Toolbar — hidden in print, but set minimal just in case
                    'toolbar-padding-y'      => '0.25rem',

                    // Filters — typically hidden in print via @media print
                    'filter-padding'         => '0.5rem',
                    'filter-gap'             => '0.5rem',
                    'filter-border-radius'   => '0',

                    // Actions — hidden in print
                    'action-btn-size'        => '0.65rem',
                    'action-btn-padding-x'   => '0.25rem',
                    'action-btn-padding-y'   => '0.1rem',

                    // Badges
                    'badge-font-size'        => '0.65rem',
                    'badge-padding-x'        => '0.375rem',
                    'badge-padding-y'        => '0.1rem',
                    'badge-radius'           => '3px',

                    // Cards
                    'card-gap'               => '0.5rem',
                    'card-padding'           => '0.5rem',
                    'card-radius'            => '0',
                    'card-shadow'            => 'none',
                    'card-border'            => '1px solid #000',

                    // Page
                    'page-padding-x'         => '0',
                    'page-padding-y'         => '0',
                    'page-bg'                => '#ffffff',
                ],
            ],

            // ─── DATA-GRID ─────────────────────────────────
            // Spreadsheet-like — maximum data density, striped rows,
            // minimal chrome. Think Excel/Google Sheets.
            'data-grid' => [
                'label'       => 'Data Grid',
                'description' => 'Spreadsheet-like density. Maximum rows visible, striped for tracking.',
                'tokens'      => [
                    // Table
                    'table-row-height'       => '32px',
                    'table-cell-padding-x'   => '0.5rem',
                    'table-cell-padding-y'   => '0.2rem',
                    'table-header-padding-x' => '0.5rem',
                    'table-header-padding-y' => '0.375rem',
                    'table-font-size'        => '0.78rem',
                    'table-header-font-size' => '0.7rem',
                    'table-border-radius'    => '4px',
                    'table-border-color'     => '#d1d5db',
                    'table-header-bg'        => '#e5e7eb',
                    'table-row-stripe'       => '#f9fafb',
                    'table-divider-color'    => '#e5e7eb',
                    'table-shadow'           => 'none',

                    // Toolbar
                    'toolbar-padding-y'      => '0.375rem',
                    'toolbar-gap'            => '0.5rem',
                    'toolbar-info-font-size' => '0.75rem',

                    // Filters
                    'filter-input-width'     => '160px',
                    'filter-select-width'    => '130px',
                    'filter-input-height'    => '28px',
                    'filter-gap'             => '0.375rem',
                    'filter-padding'         => '0.5rem',
                    'filter-border-radius'   => '4px',
                    'filter-label-size'      => '0.6rem',

                    // Actions
                    'action-btn-size'        => '0.65rem',
                    'action-btn-padding-x'   => '0.3rem',
                    'action-btn-padding-y'   => '0.1rem',
                    'action-btn-radius'      => '3px',
                    'action-gap'             => '0.2rem',

                    // Badges
                    'badge-font-size'        => '0.6rem',
                    'badge-padding-x'        => '0.3rem',
                    'badge-padding-y'        => '0.1rem',
                    'badge-radius'           => '3px',

                    // Cards — compact
                    'card-gap'               => '0.5rem',
                    'card-padding'           => '0.625rem',
                    'card-radius'            => '4px',
                    'card-shadow'            => 'none',
                    'card-icon-size'         => '1.25rem',
                    'card-value-size'        => '1.25rem',

                    // Page
                    'page-padding-x'         => '0.75rem',
                    'page-padding-y'         => '0.5rem',
                ],
            ],
        ];
    }

    /**
     * Get a preset's tokens by name.
     *
     * @param string $name Preset name
     * @return array<string, string> Token overrides (empty for 'comfortable')
     */
    public static function get(string $name): array
    {
        $presets = self::all();

        if (!isset($presets[$name])) {
            // Fall back to comfortable (no overrides)
            return [];
        }

        return $presets[$name]['tokens'];
    }

    /**
     * Get all preset names.
     *
     * @return string[]
     */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    /**
     * Get preset metadata (label + description) for UI display.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function metadata(): array
    {
        $result = [];
        foreach (self::all() as $name => $preset) {
            $result[$name] = [
                'label'       => $preset['label'],
                'description' => $preset['description'],
            ];
        }
        return $result;
    }

    /**
     * Validate that all preset tokens exist in the registry.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public static function validate(): array
    {
        $registry = VisualConfig::tokenRegistry();
        $errors = [];

        foreach (self::all() as $presetName => $preset) {
            foreach ($preset['tokens'] as $token => $value) {
                if (!isset($registry[$token])) {
                    $errors[] = "Preset '{$presetName}' references unknown token: '{$token}'";
                }
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }
}
