<?php

namespace Hub\Package\Contracts;

/**
 * Component Renderer Interface
 *
 * Every UI component renderer must implement this interface.
 * Renderers convert JSON component definitions into HTML.
 *
 * @see PACKAGE_ARCHITECTURE_SPEC.md §1A (Presentation Layer)
 */
interface ComponentRendererInterface
{
    /**
     * Render a component to HTML
     *
     * @param array $config Component configuration from package JSON
     * @param array $data Data from query execution (already scoped & masked)
     * @param array $context Rendering context:
     *   - 'packageId' => string
     *   - 'pageId' => string
     *   - 'user' => array (current user)
     *   - 'routeParams' => array (URL parameters)
     *   - 'csrfToken' => string
     *   - 'baseUrl' => string (package base URL)
     *
     * @return string Rendered HTML
     */
    public function render(array $config, array $data, array $context): string;

    /**
     * Get the component type this renderer handles
     *
     * @return string e.g., 'table', 'form', 'detail'
     */
    public function getType(): string;

    /**
     * Get required CSS/JS assets for this component type
     *
     * @return array ['css' => [...urls], 'js' => [...urls]]
     */
    public function getAssets(): array;
}
