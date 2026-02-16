<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use Hub\Auth;
use Hub\Components\EnterpriseSidebar;
use Hub\Database;
use Hub\IconResolver;
use Hub\ManagementCenter;
use Hub\PackageAccessResolver;
use Hub\SiteSettings;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Management Console – Home dashboard
     *
     * Google Admin-style module card grid. Each installed package the user
     * has access to shows as a card. Sidebar provides page-level navigation.
     */
    public function index(Request $request)
    {
        $user     = $request->attributes->get('user');
        $userId   = (int) $user['id'];
        $userRole = $user['effective_role'] ?? $user['role'];

        $mc       = new ManagementCenter();
        $db       = Database::getInstance();
        $resolver = new PackageAccessResolver();

        // ── Site settings ────────────────────────────────────────────
        $mgmtDisplayName = SiteSettings::get('mgmt_display_name', 'Management');
        $mgmtIcon        = SiteSettings::get('mgmt_icon', 'bi-kanban');
        $siteName         = SiteSettings::get('site_name', 'The Hub');
        $orgName          = SiteSettings::get('organization_name', 'Your Organization');

        // ── Packages the user can access ─────────────────────────────
        $packages = $mc->getInstalledPackagesForUser($userId, $userRole);

        // Enrich each package with resolved role + accessible pages
        foreach ($packages as &$pkg) {
            $slug = $pkg['slug'] ?? '';
            $pkg['userPkgRole']     = $resolver->getUserPackageRole($userId, $slug);
            $pkg['accessiblePages'] = $resolver->getAccessiblePages($userId, $slug);
            $pkg['pages'] = array_filter($pkg['pages'] ?? [], function ($page) use ($resolver, $userId, $slug) {
                return $resolver->canAccessPage($userId, $slug, $page['id'] ?? '');
            });
        }
        unset($pkg);

        // ── Legacy sections (non-package) ────────────────────────────
        $sections     = ($userRole === 'super_admin')
            ? $mc->getSectionsWithCounts()
            : $mc->getSectionsWithCounts($userId);
        $packageSlugs   = array_column($packages, 'slug');
        $legacySections = array_filter($sections, fn($s) => !in_array($s['slug'], $packageSlugs));

        // ── Attention items (actionable alerts only) ──────────────
        $attentionItems = [];

        try {
            $pendingSubs = $db->fetchAll(
                "SELECT s.name, s.slug, COUNT(ss.id) as cnt
                 FROM section_submissions ss
                 JOIN sections s ON ss.section_id = s.id
                 WHERE ss.is_draft = 0 AND ss.is_active = 1 AND ss.reviewed_at IS NULL
                 GROUP BY s.name, s.slug ORDER BY cnt DESC"
            );
            foreach ($pendingSubs as $ps) {
                $attentionItems[] = [
                    'type'     => 'warning',
                    'icon'     => 'bi-inbox',
                    'title'    => $ps['cnt'] . ' unreviewed submission' . ($ps['cnt'] > 1 ? 's' : ''),
                    'subtitle' => $ps['name'],
                    'url'      => '/management/section.php?slug=' . urlencode($ps['slug']),
                    'action'   => 'Review',
                ];
            }
        } catch (\Exception $e) {
        }

        try {
            $alertCount = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM package_triggered_alerts WHERE resolved_at IS NULL"
            );
            if (($alertCount['cnt'] ?? 0) > 0) {
                $attentionItems[] = [
                    'type'     => 'danger',
                    'icon'     => 'bi-exclamation-triangle',
                    'title'    => $alertCount['cnt'] . ' unresolved alert' . ($alertCount['cnt'] > 1 ? 's' : ''),
                    'subtitle' => 'Package monitoring',
                    'url'      => '#',
                    'action'   => 'View',
                ];
            }
        } catch (\Exception $e) {
        }

        try {
            $urgentCount = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM section_submissions
                 WHERE priority = 'urgent' AND is_draft = 0 AND is_active = 1 AND reviewed_at IS NULL"
            );
            if (($urgentCount['cnt'] ?? 0) > 0) {
                $attentionItems[] = [
                    'type'     => 'danger',
                    'icon'     => 'bi-exclamation-octagon',
                    'title'    => $urgentCount['cnt'] . ' urgent item' . ($urgentCount['cnt'] > 1 ? 's' : '') . ' need attention',
                    'subtitle' => 'Priority submissions',
                    'url'      => '#',
                    'action'   => 'Review',
                ];
            }
        } catch (\Exception $e) {
        }

        // ── Categorize packages for card grid ────────────────────────
        // Map package IDs to display categories (from package repo structure)
        $categoryMap = [
            'district'     => ['icon' => 'fas fa-school',          'color' => 'blue',   'label' => 'District'],
            'operations'   => ['icon' => 'fas fa-tools',           'color' => 'orange', 'label' => 'Operations'],
            'finance'      => ['icon' => 'fas fa-dollar-sign',     'color' => 'green',  'label' => 'Finance'],
            'reporting'    => ['icon' => 'fas fa-chart-bar',       'color' => 'purple', 'label' => 'Reporting'],
            'analytics'    => ['icon' => 'fas fa-chart-line',      'color' => 'teal',   'label' => 'Analytics'],
            'forms'        => ['icon' => 'fas fa-file-alt',        'color' => 'indigo', 'label' => 'Forms'],
            'integrations' => ['icon' => 'fas fa-plug',            'color' => 'cyan',   'label' => 'Integrations'],
            'workflows'    => ['icon' => 'fas fa-project-diagram', 'color' => 'amber',  'label' => 'Workflows'],
            'student'      => ['icon' => 'fas fa-user-graduate',   'color' => 'pink',   'label' => 'Student'],
        ];

        // Build categorized card list for the view
        $packageCards = [];
        foreach ($packages as $pkg) {
            $pkgId    = $pkg['package_id'] ?? '';
            $category = explode('.', $pkgId)[0] ?? 'other';
            $catInfo  = $categoryMap[$category] ?? ['icon' => 'fas fa-box', 'color' => 'gray', 'label' => ucfirst($category)];

            $visiblePages = array_filter($pkg['pages'] ?? [], fn($p) => !preg_match('/\{[^}]+\}/', $p['route'] ?? ''));

            // Build quick action links from top pages (max 4)
            $quickActions = [];
            $baseUrl = '/management/package.php?id=' . urlencode($pkgId);
            foreach (array_slice(array_values($visiblePages), 0, 4) as $page) {
                $pageId = $page['id'] ?? $page['key'] ?? 'index';
                $pageTitle = $page['title'] ?? ucfirst($pageId);
                // Use smart icon guessing based on page ID/title instead of generic box
                $pageIcon = !empty($page['icon'])
                    ? IconResolver::resolve($page['icon'])
                    : EnterpriseSidebar::guessPageIcon($pageId, $pageTitle);

                $quickActions[] = [
                    'title' => $pageTitle,
                    'url'   => $baseUrl . '&page=' . urlencode($pageId),
                    'icon'  => $pageIcon,
                ];
            }

            // Generate a useful subtitle from the package description or category
            $subtitle = $pkg['description'] ?? ('Manage ' . strtolower($catInfo['label']) . ' ' . strtolower($pkg['name']));

            $packageCards[] = [
                'package_id'   => $pkgId,
                'name'         => $pkg['name'],
                'slug'         => $pkg['slug'],
                'icon'         => IconResolver::resolve($pkg['icon'] ?? null, $catInfo['icon']),
                'color'        => $catInfo['color'],
                'category'     => $catInfo['label'],
                'url'          => $baseUrl,
                'page_count'   => count($visiblePages),
                'pkg_role'     => $pkg['userPkgRole'] ?? null,
                'version'      => $pkg['version'] ?? '1.0.0',
                'quick_actions' => $quickActions,
                'subtitle'     => $subtitle,
            ];
        }

        // ── Build sidebar nav items (contextual — dashboard mode) ─────
        $navItems = \Hub\Components\EnterpriseSidebar::buildManagementNavItems(
            $legacySections,       // legacy sections
            null,                  // no active section slug
            $packages,             // all user packages (for category grouping)
            null,                  // no active package (dashboard mode)
            null,                  // no active page
            [],                    // no accessible pages filter
            $categoryMap           // pass category metadata for icons/labels
        );

        return view('management.dashboard', compact(
            'user',
            'userRole',
            'orgName',
            'siteName',
            'mgmtDisplayName',
            'mgmtIcon',
            'packages',
            'packageCards',
            'legacySections',
            'attentionItems',
            'navItems'
        ));
    }
}
