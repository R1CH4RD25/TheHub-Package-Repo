<?php

namespace Hub\Package;

/**
 * Icon Mapper
 *
 * Maps lucide-* icon class names to FontAwesome equivalents,
 * since Lucide CSS is not loaded but FontAwesome 6.5.1 is.
 *
 * Used by all package component renderers to ensure icons render correctly.
 */
class IconMapper
{
    /**
     * Lucide icon name → FontAwesome class mapping
     */
    private const LUCIDE_TO_FA = [
        'lucide-users'              => 'fas fa-users',
        'lucide-user'               => 'fas fa-user',
        'lucide-user-plus'          => 'fas fa-user-plus',
        'lucide-user-check'         => 'fas fa-user-check',
        'lucide-user-x'             => 'fas fa-user-xmark',
        'lucide-graduation-cap'     => 'fas fa-graduation-cap',
        'lucide-book-open'          => 'fas fa-book-open',
        'lucide-book'               => 'fas fa-book',
        'lucide-backpack'           => 'fas fa-school',
        'lucide-school'             => 'fas fa-school',
        'lucide-eye'                => 'fas fa-eye',
        'lucide-eye-off'            => 'fas fa-eye-slash',
        'lucide-pencil'             => 'fas fa-pencil',
        'lucide-pen'                => 'fas fa-pen',
        'lucide-trash'              => 'fas fa-trash',
        'lucide-trash-2'            => 'fas fa-trash-can',
        'lucide-printer'            => 'fas fa-print',
        'lucide-key-round'          => 'fas fa-key',
        'lucide-key'                => 'fas fa-key',
        'lucide-search'             => 'fas fa-search',
        'lucide-globe'              => 'fas fa-globe',
        'lucide-laptop'             => 'fas fa-laptop',
        'lucide-settings'           => 'fas fa-gear',
        'lucide-plus'               => 'fas fa-plus',
        'lucide-plus-circle'        => 'fas fa-circle-plus',
        'lucide-minus'              => 'fas fa-minus',
        'lucide-check'              => 'fas fa-check',
        'lucide-x'                  => 'fas fa-xmark',
        'lucide-alert-triangle'     => 'fas fa-triangle-exclamation',
        'lucide-info'               => 'fas fa-circle-info',
        'lucide-download'           => 'fas fa-download',
        'lucide-upload'             => 'fas fa-upload',
        'lucide-file'               => 'fas fa-file',
        'lucide-file-text'          => 'fas fa-file-lines',
        'lucide-file-spreadsheet'   => 'fas fa-file-excel',
        'lucide-mail'               => 'fas fa-envelope',
        'lucide-phone'              => 'fas fa-phone',
        'lucide-calendar'           => 'fas fa-calendar',
        'lucide-clock'              => 'fas fa-clock',
        'lucide-shield'             => 'fas fa-shield',
        'lucide-lock'               => 'fas fa-lock',
        'lucide-unlock'             => 'fas fa-unlock',
        'lucide-home'               => 'fas fa-house',
        'lucide-building'           => 'fas fa-building',
        'lucide-map-pin'            => 'fas fa-location-dot',
        'lucide-arrow-left'         => 'fas fa-arrow-left',
        'lucide-arrow-right'        => 'fas fa-arrow-right',
        'lucide-chevron-left'       => 'fas fa-chevron-left',
        'lucide-chevron-right'      => 'fas fa-chevron-right',
        'lucide-external-link'      => 'fas fa-arrow-up-right-from-square',
        'lucide-clipboard'          => 'fas fa-clipboard',
        'lucide-copy'               => 'fas fa-copy',
        'lucide-filter'             => 'fas fa-filter',
        'lucide-refresh-cw'         => 'fas fa-arrows-rotate',
        'lucide-save'               => 'fas fa-floppy-disk',
        'lucide-star'               => 'fas fa-star',
        'lucide-heart'              => 'fas fa-heart',
        'lucide-bell'               => 'fas fa-bell',
        'lucide-image'              => 'fas fa-image',
        'lucide-camera'             => 'fas fa-camera',
        'lucide-bar-chart'          => 'fas fa-chart-bar',
        'lucide-pie-chart'          => 'fas fa-chart-pie',
        'lucide-trending-up'        => 'fas fa-arrow-trend-up',
        'lucide-trending-down'      => 'fas fa-arrow-trend-down',
        'lucide-log-in'             => 'fas fa-right-to-bracket',
        'lucide-log-out'            => 'fas fa-right-from-bracket',
        'lucide-more-horizontal'    => 'fas fa-ellipsis',
        'lucide-more-vertical'      => 'fas fa-ellipsis-vertical',
    ];

    /**
     * Map an icon class to a renderable icon class.
     *
     * Converts lucide-* to FontAwesome. Passes through bi-*, fas/far/fab fa-* unchanged.
     *
     * @param string $iconClass The original icon class (e.g., 'lucide-eye', 'bi-search', 'fas fa-user')
     * @return string The mapped icon class
     */
    public static function map(string $iconClass): string
    {
        $iconClass = trim($iconClass);

        if ($iconClass === '') {
            return '';
        }

        // Already a FontAwesome or Bootstrap Icons class — pass through
        if (str_starts_with($iconClass, 'fas ') ||
            str_starts_with($iconClass, 'far ') ||
            str_starts_with($iconClass, 'fab ') ||
            str_starts_with($iconClass, 'fa ') ||
            str_starts_with($iconClass, 'bi-') ||
            str_starts_with($iconClass, 'bi ')) {
            return $iconClass;
        }

        // Map lucide-* to FontAwesome
        if (str_starts_with($iconClass, 'lucide-')) {
            return self::LUCIDE_TO_FA[$iconClass] ?? 'fas fa-cube';
        }

        return $iconClass;
    }
}
