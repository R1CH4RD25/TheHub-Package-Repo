<?php

namespace Hub\Package\Renderers;

use Hub\Package\Contracts\ComponentRendererInterface;

/**
 * Dashboard Renderer
 *
 * Renders KPI cards and dashboard tiles from JSON definitions.
 * Maps to 'dashboard' and 'cards' component types.
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
            'js'  => [],
        ];
    }

    public function render(array $config, array $data, array $context): string
    {
        $componentId = $config['id'] ?? 'pkg-dashboard-' . uniqid();
        $cards = $config['cards'] ?? $config['items'] ?? [];
        $columns = $config['columns'] ?? 4;
        $queryName = $config['query'] ?? '';

        $cardData = $data['data'] ?? $data;
        $baseUrl = rtrim($context['baseUrl'] ?? '', '/');

        $html = '<div class="pkg-dashboard pkg-dashboard-cols-' . (int)$columns . '" id="' . e($componentId) . '">';

        foreach ($cards as $card) {
            $html .= $this->renderCard($card, $cardData, $baseUrl);
        }

        $html .= '</div>';

        return $html;
    }

    private function renderCard(array $card, array $data, string $baseUrl): string
    {
        $title = $card['title'] ?? '';
        $icon = $card['icon'] ?? '';
        $value = $card['value'] ?? '';
        $subtitle = $card['subtitle'] ?? '';
        $color = $card['color'] ?? 'primary';
        $link = $card['link'] ?? '';
        $dataKey = $card['dataKey'] ?? '';

        // Resolve dynamic value from data
        if ($dataKey && isset($data[$dataKey])) {
            $value = $data[$dataKey];
        }

        $tag = $link ? 'a' : 'div';
        $href = $link ? ' href="' . e($baseUrl . $link) . '"' : '';

        $html = '<' . $tag . ' class="pkg-dashboard-card pkg-card-' . e($color) . '"' . $href . '>';

        if ($icon) {
            $html .= '<div class="pkg-card-icon"><i class="' . e($icon) . '"></i></div>';
        }

        $html .= '<div class="pkg-card-body">';
        if ($value !== '') {
            $html .= '<div class="pkg-card-value">' . e($value) . '</div>';
        }
        if ($title) {
            $html .= '<div class="pkg-card-title">' . e($title) . '</div>';
        }
        if ($subtitle) {
            $html .= '<div class="pkg-card-subtitle">' . e($subtitle) . '</div>';
        }
        $html .= '</div>';

        $html .= '</' . $tag . '>';

        return $html;
    }
}
