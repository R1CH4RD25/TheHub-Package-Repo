<?php

namespace Hub;

/**
 * Centralized icon resolver — converts package icon strings to renderable CSS classes.
 *
 * Packages may declare icons as:
 *   - FontAwesome:    "fas fa-users", "far fa-envelope", "fa-truck"
 *   - Bootstrap Icons: "bi-folder", "bi-gear"
 *   - Lucide (legacy): "lucide-graduation-cap" → mapped to FA equivalent
 *   - Emoji/text:      "📦" → returned as-is (caller wraps in <span>)
 *
 * Usage:
 *   $class = IconResolver::resolve('lucide-graduation-cap'); // "fas fa-graduation-cap"
 *   $html  = IconResolver::toHtml('lucide-users');           // '<i class="fas fa-users"></i>'
 */
class IconResolver
{
    /**
     * Lucide → FontAwesome mapping for common icons used in packages.
     */
    private static array $lucideToFa = [
        'lucide-graduation-cap'   => 'fas fa-graduation-cap',
        'lucide-users'            => 'fas fa-users',
        'lucide-user'             => 'fas fa-user',
        'lucide-user-graduate'    => 'fas fa-user-graduate',
        'lucide-settings'         => 'fas fa-cog',
        'lucide-file-text'        => 'fas fa-file-alt',
        'lucide-bar-chart'        => 'fas fa-chart-bar',
        'lucide-calendar'         => 'fas fa-calendar',
        'lucide-bell'             => 'fas fa-bell',
        'lucide-shield'           => 'fas fa-shield-alt',
        'lucide-book'             => 'fas fa-book',
        'lucide-book-open'        => 'fas fa-book-open',
        'lucide-home'             => 'fas fa-home',
        'lucide-mail'             => 'fas fa-envelope',
        'lucide-search'           => 'fas fa-search',
        'lucide-folder'           => 'fas fa-folder',
        'lucide-clipboard'        => 'fas fa-clipboard',
        'lucide-truck'            => 'fas fa-truck',
        'lucide-map-pin'          => 'fas fa-map-marker-alt',
        'lucide-database'         => 'fas fa-database',
        'lucide-activity'         => 'fas fa-chart-line',
        'lucide-alert-triangle'   => 'fas fa-exclamation-triangle',
        'lucide-check-circle'     => 'fas fa-check-circle',
        'lucide-lock'             => 'fas fa-lock',
        'lucide-key'              => 'fas fa-key',
        'lucide-key-round'        => 'fas fa-key',
        'lucide-backpack'         => 'fas fa-school',
        'lucide-eye'              => 'fas fa-eye',
        'lucide-printer'          => 'fas fa-print',
        'lucide-trash-2'          => 'fas fa-trash-alt',
        'lucide-pencil'           => 'fas fa-pencil-alt',
        'lucide-laptop'           => 'fas fa-laptop',
        'lucide-globe'            => 'fas fa-globe',
        'lucide-upload'           => 'fas fa-upload',
        'lucide-file-spreadsheet' => 'fas fa-file-excel',
        'lucide-cloud-download'   => 'fas fa-cloud-download-alt',
        'lucide-plus'             => 'fas fa-plus',
        'lucide-edit'             => 'fas fa-edit',
        'lucide-download'         => 'fas fa-download',
        'lucide-x'                => 'fas fa-times',
        'lucide-check'            => 'fas fa-check',
        'lucide-info'             => 'fas fa-info-circle',
        'lucide-arrow-left'       => 'fas fa-arrow-left',
        'lucide-arrow-right'      => 'fas fa-arrow-right',
    ];

    /**
     * Resolve an icon string to a CSS class string.
     *
     * @param string|null $icon  Raw icon value from package/section
     * @param string      $fallback  Fallback class if icon is null/empty
     * @return string  CSS class string (e.g. "fas fa-users")
     */
    public static function resolve(?string $icon, string $fallback = 'fas fa-box'): string
    {
        if (empty($icon)) {
            return $fallback;
        }

        // Bootstrap Icons: bi-*
        if (str_starts_with($icon, 'bi-')) {
            return 'bi ' . $icon;
        }

        // FontAwesome: already qualified (fas fa-*, far fa-*, fab fa-*)
        if (preg_match('/^(fas?|far|fab|fal)\s/', $icon)) {
            return $icon;
        }

        // FontAwesome: short form (fa-icon)
        if (str_starts_with($icon, 'fa-')) {
            return 'fas ' . $icon;
        }

        // Lucide: map to FA
        if (str_starts_with($icon, 'lucide-')) {
            return self::$lucideToFa[$icon]
                ?? 'fas fa-' . str_replace('lucide-', '', $icon);
        }

        // Already a plain class or emoji — return as-is
        return $icon;
    }

    /**
     * Render an icon as an HTML <i> element.
     *
     * @param string|null $icon       Raw icon value
     * @param string      $fallback   Fallback class
     * @param string      $extraClass Extra CSS classes to add
     * @return string  HTML string
     */
    public static function toHtml(?string $icon, string $fallback = 'fas fa-box', string $extraClass = ''): string
    {
        $resolved = self::resolve($icon, $fallback);

        // If it looks like a CSS class, wrap in <i>
        if (preg_match('/^[a-z]/', $resolved)) {
            $classes = $extraClass ? $resolved . ' ' . $extraClass : $resolved;
            return '<i class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '"></i>';
        }

        // Emoji / plain text
        return '<span>' . htmlspecialchars($resolved, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
