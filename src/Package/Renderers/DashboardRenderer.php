<?php

namespace Hub\Package\Renderers;

use Hub\Package\Contracts\ComponentRendererInterface;

/**
 * Dashboard Renderer
 *
 * Renders KPI cards, charts, action queues, and quick links
 * from JSON component definitions.
 *
 * Component types: 'dashboard', 'cards', 'chart', 'action-queue'
 *
 * Card config:
 *   { title, icon, value, dataKey, subtitle, color, link, trend, format }
 *
 * Chart config:
 *   { chartType: bar|line|pie|doughnut, labels, datasets, dataKey, height }
 *
 * Action queue config:
 *   { title, items: [{ label, count, dataKey, link, variant, icon }] }
 *
 * @see PACKAGE_ARCHITECTURE_SPEC.md §1A (Presentation Layer)
 */
class DashboardRenderer implements ComponentRendererInterface
{
    public function getType(): string
    {
        return 'dashboard';
    }

    public function getAssets(): array
    {
        return [
            'css' => ['/assets/css/package-components.css'],
            'js'  => ['/assets/js/package-dashboard.js'],
        ];
    }

    public function render(array $config, array $data, array $context): string
    {
        $componentId = $config['id'] ?? 'pkg-dashboard-' . uniqid();
        $layout = $config['layout'] ?? 'auto';
        $queryName = $config['query'] ?? $config['dataQuery'] ?? '';

        $cardData = $data['data'] ?? $data;
        $baseUrl = rtrim($context['baseUrl'] ?? '', '/');

        $html = '<div class="pkg-dashboard" id="' . e($componentId) . '">';

        // Render cards section
        $cards = $config['cards'] ?? $config['items'] ?? [];
        if (!empty($cards)) {
            $columns = $config['columns'] ?? min(count($cards), 4);
            $html .= '<div class="pkg-dashboard-cards pkg-dashboard-cols-' . (int)$columns . '">';
            foreach ($cards as $card) {
                $html .= $this->renderCard($card, $cardData, $baseUrl);
            }
            $html .= '</div>';
        }

        // Render charts section
        $charts = $config['charts'] ?? [];
        if (!empty($charts)) {
            $chartColumns = $config['chartColumns'] ?? 2;
            $html .= '<div class="pkg-dashboard-charts pkg-dashboard-cols-' . (int)$chartColumns . '">';
            foreach ($charts as $chart) {
                $html .= $this->renderChart($chart, $cardData, $componentId);
            }
            $html .= '</div>';
        }

        // Render action queues
        $queues = $config['queues'] ?? $config['actionQueues'] ?? [];
        if (!empty($queues)) {
            $html .= '<div class="pkg-dashboard-queues">';
            foreach ($queues as $queue) {
                $html .= $this->renderActionQueue($queue, $cardData, $baseUrl);
            }
            $html .= '</div>';
        }

        // Render quick links
        $quickLinks = $config['quickLinks'] ?? [];
        if (!empty($quickLinks)) {
            $html .= $this->renderQuickLinks($quickLinks, $baseUrl);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a KPI stat card
     */
    private function renderCard(array $card, array $data, string $baseUrl): string
    {
        $title = $card['title'] ?? '';
        $icon = $card['icon'] ?? '';
        $value = $card['value'] ?? '';
        $subtitle = $card['subtitle'] ?? '';
        $color = $card['color'] ?? 'primary';
        $link = $card['link'] ?? '';
        $dataKey = $card['dataKey'] ?? '';
        $format = $card['format'] ?? 'number';
        $trend = $card['trend'] ?? null;

        // Resolve dynamic value from data
        if ($dataKey && isset($data[$dataKey])) {
            $value = $data[$dataKey];
        }

        // Format value
        $displayValue = $this->formatCardValue($value, $format);

        $tag = $link ? 'a' : 'div';
        $href = $link ? ' href="' . e($baseUrl . $link) . '"' : '';

        $html = '<' . $tag . ' class="pkg-dashboard-card pkg-card-' . e($color) . '"' . $href . '>';

        if ($icon) {
            $html .= '<div class="pkg-card-icon"><i class="' . e($icon) . '"></i></div>';
        }

        $html .= '<div class="pkg-card-body">';

        if ($displayValue !== '') {
            $html .= '<div class="pkg-card-value">' . e($displayValue) . '</div>';
        }

        if ($title) {
            $html .= '<div class="pkg-card-title">' . e($title) . '</div>';
        }

        if ($subtitle) {
            $html .= '<div class="pkg-card-subtitle">' . e($subtitle) . '</div>';
        }

        // Trend indicator
        if ($trend && is_array($trend)) {
            $trendDir = $trend['direction'] ?? 'up';
            $trendValue = $trend['value'] ?? '';
            $isPositive = $trend['positive'] ?? ($trendDir === 'up');
            $trendClass = $isPositive ? 'pkg-trend-positive' : 'pkg-trend-negative';
            $trendIcon = $trendDir === 'up' ? 'bi-arrow-up-short' : 'bi-arrow-down-short';

            $html .= '<div class="pkg-card-trend ' . $trendClass . '">';
            $html .= '<i class="bi ' . $trendIcon . '"></i> ' . e($trendValue);
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</' . $tag . '>';

        return $html;
    }

    /**
     * Render a chart component (Chart.js)
     */
    private function renderChart(array $chart, array $data, string $componentId): string
    {
        $chartId = $chart['id'] ?? $componentId . '-chart-' . uniqid();
        $type = $chart['chartType'] ?? $chart['type'] ?? 'bar';
        $title = $chart['title'] ?? '';
        $height = $chart['height'] ?? 300;
        $dataKey = $chart['dataKey'] ?? '';

        // Resolve chart data
        $chartData = [];
        if ($dataKey && isset($data[$dataKey])) {
            $chartData = $data[$dataKey];
        } else {
            $chartData = [
                'labels' => $chart['labels'] ?? [],
                'datasets' => $chart['datasets'] ?? [],
            ];
        }

        $html = '<div class="pkg-chart-container">';

        if ($title) {
            $html .= '<h4 class="pkg-chart-title">' . e($title) . '</h4>';
        }

        $html .= '<div class="pkg-chart-wrapper" style="height:' . (int)$height . 'px;">';
        $html .= '<canvas id="' . e($chartId) . '" class="pkg-chart"></canvas>';
        $html .= '</div>';
        $html .= '</div>';

        // Chart.js initialization data
        $html .= '<script>';
        $html .= 'window.__pkgCharts = window.__pkgCharts || {};';
        $html .= 'window.__pkgCharts["' . e($chartId) . '"] = ' . json_encode([
            'type' => $type,
            'data' => $chartData,
            'options' => $chart['options'] ?? [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => ['position' => 'bottom'],
                ],
            ],
        ]) . ';';
        $html .= '</script>';

        return $html;
    }

    /**
     * Render an action queue (pending items needing attention)
     */
    private function renderActionQueue(array $queue, array $data, string $baseUrl): string
    {
        $title = $queue['title'] ?? 'Action Queue';
        $items = $queue['items'] ?? [];
        $emptyMessage = $queue['emptyMessage'] ?? 'No pending items';

        $html = '<div class="pkg-action-queue">';
        $html .= '<h4 class="pkg-queue-title">' . e($title) . '</h4>';

        if (empty($items)) {
            $html .= '<p class="pkg-queue-empty text-muted">' . e($emptyMessage) . '</p>';
        } else {
            $html .= '<ul class="pkg-queue-list">';
            foreach ($items as $item) {
                $label = $item['label'] ?? '';
                $count = $item['count'] ?? null;
                $dataKey = $item['dataKey'] ?? '';
                $link = $item['link'] ?? '';
                $variant = $item['variant'] ?? 'primary';
                $icon = $item['icon'] ?? 'bi-arrow-right';

                if ($dataKey && isset($data[$dataKey])) {
                    $count = $data[$dataKey];
                }

                $html .= '<li class="pkg-queue-item">';
                $html .= '<div class="pkg-queue-item-left">';
                if ($icon) {
                    $html .= '<i class="' . e($icon) . '"></i> ';
                }
                $html .= '<span>' . e($label) . '</span>';
                $html .= '</div>';

                $html .= '<div class="pkg-queue-item-right">';
                if ($count !== null) {
                    $html .= '<span class="badge badge-' . e($variant) . ' pkg-queue-badge">' . e((string)$count) . '</span>';
                }
                if ($link) {
                    $html .= ' <a href="' . e($baseUrl . $link) . '" class="btn btn-sm btn-outline-' . e($variant) . '">View</a>';
                }
                $html .= '</div>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render quick link buttons
     */
    private function renderQuickLinks(array $links, string $baseUrl): string
    {
        $html = '<div class="pkg-quick-links">';
        $html .= '<h4 class="pkg-quick-links-title">Quick Actions</h4>';
        $html .= '<div class="pkg-quick-links-grid">';

        foreach ($links as $link) {
            $label = $link['label'] ?? '';
            $icon = $link['icon'] ?? '';
            $url = $link['url'] ?? $link['link'] ?? '#';
            $variant = $link['variant'] ?? 'outline-primary';
            $description = $link['description'] ?? '';

            $html .= '<a href="' . e($baseUrl . $url) . '" class="pkg-quick-link btn btn-' . e($variant) . '">';
            if ($icon) {
                $html .= '<i class="' . e($icon) . '"></i> ';
            }
            $html .= '<span>' . e($label) . '</span>';
            if ($description) {
                $html .= '<small class="pkg-quick-link-desc">' . e($description) . '</small>';
            }
            $html .= '</a>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Format a card value based on format type
     */
    private function formatCardValue($value, string $format): string
    {
        if ($value === '' || $value === null) return '—';

        return match ($format) {
            'currency' => '$' . number_format((float)$value, 2),
            'percentage' => number_format((float)$value, 1) . '%',
            'number' => is_numeric($value) ? number_format((int)$value) : (string)$value,
            'compact' => $this->compactNumber($value),
            default => (string)$value,
        };
    }

    /**
     * Format large number in compact form (1.2K, 3.4M)
     */
    private function compactNumber($value): string
    {
        $num = (float)$value;
        if ($num >= 1000000) return number_format($num / 1000000, 1) . 'M';
        if ($num >= 1000) return number_format($num / 1000, 1) . 'K';
        return number_format($num);
    }
}
