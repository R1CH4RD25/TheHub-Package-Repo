<?php

namespace Hub\Package;

/**
 * Component Registry
 *
 * Maps component type strings from package JSON to renderer classes.
 * Core types: table, form, detail, filters, dashboard/cards
 *
 * @see PACKAGE_ARCHITECTURE_SPEC.md §1A (Presentation Layer)
 */
class ComponentRegistry
{
    /** @var array<string, string> type => renderer class FQCN */
    private array $renderers = [];

    /** @var array<string, callable> type => factory callable */
    private array $factories = [];

    private static ?self $instance = null;

    private function __construct()
    {
        $this->registerDefaults();
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register default component renderers
     */
    private function registerDefaults(): void
    {
        $this->register('table', Renderers\TableRenderer::class);
        $this->register('form', Renderers\FormRenderer::class);
        $this->register('detail', Renderers\DetailRenderer::class);
        $this->register('filters', Renderers\FilterRenderer::class);
        $this->register('dashboard', Renderers\DashboardRenderer::class);
        $this->register('cards', Renderers\DashboardRenderer::class); // alias
    }

    /**
     * Register a renderer class for a component type
     *
     * @param string $type Component type key
     * @param string $className FQCN of renderer (must implement ComponentRendererInterface)
     */
    public function register(string $type, string $className): void
    {
        $this->renderers[$type] = $className;
    }

    /**
     * Register a factory for lazy creation
     *
     * @param string $type Component type key
     * @param callable $factory Function returning a ComponentRendererInterface
     */
    public function registerFactory(string $type, callable $factory): void
    {
        $this->factories[$type] = $factory;
    }

    /**
     * Create a renderer instance for a component type
     *
     * @param string $type Component type from JSON
     * @return Contracts\ComponentRendererInterface
     * @throws \InvalidArgumentException if type not registered
     */
    public function create(string $type): Contracts\ComponentRendererInterface
    {
        // Factory takes precedence
        if (isset($this->factories[$type])) {
            $renderer = ($this->factories[$type])();
            if (!$renderer instanceof Contracts\ComponentRendererInterface) {
                throw new \InvalidArgumentException("Factory for '{$type}' did not return ComponentRendererInterface");
            }
            return $renderer;
        }

        if (!isset($this->renderers[$type])) {
            throw new \InvalidArgumentException("Unknown component type: '{$type}'. Registered: " . implode(', ', array_keys($this->renderers)));
        }

        $className = $this->renderers[$type];

        if (!class_exists($className)) {
            throw new \RuntimeException("Renderer class not found: {$className}");
        }

        $renderer = new $className();

        if (!$renderer instanceof Contracts\ComponentRendererInterface) {
            throw new \InvalidArgumentException("{$className} does not implement ComponentRendererInterface");
        }

        return $renderer;
    }

    /**
     * Check if a component type is registered
     */
    public function has(string $type): bool
    {
        return isset($this->renderers[$type]) || isset($this->factories[$type]);
    }

    /**
     * Get all registered component types
     *
     * @return string[]
     */
    public function getTypes(): array
    {
        return array_unique(array_merge(
            array_keys($this->renderers),
            array_keys($this->factories)
        ));
    }

    /**
     * Reset singleton (for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
