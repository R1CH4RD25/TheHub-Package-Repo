<?php

namespace Hub\Package;

/**
 * Component Definition — Portable Component Blueprint
 *
 * A ComponentDefinition is a self-contained description of a UI component
 * that contributors create and admins install/customize. It packages:
 *
 *   - Metadata (id, name, version, author, tags)
 *   - Visual config (preset + token overrides)
 *   - Structure (columns, filters, actions, fields — depends on type)
 *   - Data binding (query/mutation references)
 *
 * Component definitions are:
 *   - Portable: JSON-serializable, can be shared as files
 *   - Validated: Schema-checked before rendering
 *   - Customizable: Admins can override visual tokens without changing structure
 *   - Immutable structure: Contributors set columns/fields; admins adjust visuals
 *
 * @see VisualConfig for token system
 * @see VisualPresets for density presets
 * @see docs/VISUAL_TOKEN_SYSTEM.md for full architecture
 */
class ComponentDefinition
{
    private array $data;

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Create from a JSON string.
     *
     * @throws \InvalidArgumentException if JSON is invalid
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        return new self($data);
    }

    /**
     * Create from an array (e.g., already-decoded JSON or built programmatically).
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Create a new definition builder for a given type.
     *
     * @param string $type Component type: table, form, chart, dashboard, detail
     * @return ComponentDefinitionBuilder
     */
    public static function build(string $type): ComponentDefinitionBuilder
    {
        return new ComponentDefinitionBuilder($type);
    }

    // ─── Metadata Accessors ────────────────────────────

    public function getId(): string
    {
        return $this->data['definition']['id'] ?? '';
    }

    public function getName(): string
    {
        return $this->data['definition']['name'] ?? '';
    }

    public function getType(): string
    {
        return $this->data['definition']['type'] ?? 'table';
    }

    public function getVersion(): string
    {
        return $this->data['definition']['version'] ?? '1.0.0';
    }

    public function getAuthor(): string
    {
        return $this->data['definition']['author'] ?? '';
    }

    public function getDescription(): string
    {
        return $this->data['definition']['description'] ?? '';
    }

    public function getTags(): array
    {
        return $this->data['definition']['tags'] ?? [];
    }

    public function getMetadata(): array
    {
        return $this->data['definition'] ?? [];
    }

    // ─── Visual Config ─────────────────────────────────

    /**
     * Get the visual config section (preset + token overrides).
     */
    public function getVisualConfig(): array
    {
        return $this->data['visual'] ?? [];
    }

    /**
     * Get the preset name (defaults to 'comfortable').
     */
    public function getPreset(): string
    {
        return $this->data['visual']['preset'] ?? 'comfortable';
    }

    /**
     * Get raw token overrides from the definition.
     */
    public function getTokenOverrides(): array
    {
        return $this->data['visual']['tokens'] ?? [];
    }

    /**
     * Resolve visual tokens: merge preset defaults + definition overrides.
     *
     * @param array $orgOverrides Optional org-level overrides on top
     * @return array Fully resolved token => value map
     */
    public function resolveVisualTokens(array $orgOverrides = []): array
    {
        $preset = $this->getPreset();
        $defTokens = $this->getTokenOverrides();

        // Resolution: preset → definition overrides → org overrides
        return VisualConfig::resolveConfig($preset, array_merge($defTokens, $orgOverrides));
    }

    /**
     * Generate a scoped <style> block for this definition's visual tokens.
     *
     * @param string $componentId DOM element ID to scope tokens to
     * @param array  $orgOverrides Optional org-level overrides
     * @return string HTML <style> tag (empty string if no overrides)
     */
    public function toStyleBlock(string $componentId, array $orgOverrides = []): string
    {
        $tokens = $this->resolveVisualTokens($orgOverrides);
        if (empty($tokens)) {
            return '';
        }
        return VisualConfig::toStyleBlock($tokens, '', $componentId);
    }

    // ─── Structure ─────────────────────────────────────

    /**
     * Get the structural config (columns, filters, actions, fields, pagination, etc.)
     */
    public function getStructure(): array
    {
        return $this->data['structure'] ?? [];
    }

    /**
     * Get columns (for table type).
     */
    public function getColumns(): array
    {
        return $this->data['structure']['columns'] ?? [];
    }

    /**
     * Get filters (for table/dashboard type).
     */
    public function getFilters(): array
    {
        return $this->data['structure']['filters'] ?? [];
    }

    /**
     * Get row actions (for table type).
     */
    public function getActions(): array
    {
        return $this->data['structure']['actions'] ?? [];
    }

    /**
     * Get bulk actions (for table type).
     */
    public function getBulkActions(): array
    {
        return $this->data['structure']['bulkActions'] ?? [];
    }

    /**
     * Get pagination config.
     */
    public function getPagination(): array
    {
        return $this->data['structure']['pagination'] ?? ['perPage' => 50];
    }

    /**
     * Get fields (for form type).
     */
    public function getFields(): array
    {
        return $this->data['structure']['fields'] ?? [];
    }

    /**
     * Get sections (for form type with multi-section layout).
     */
    public function getSections(): array
    {
        return $this->data['structure']['sections'] ?? [];
    }

    /**
     * Get cards (for dashboard type).
     */
    public function getCards(): array
    {
        return $this->data['structure']['cards'] ?? [];
    }

    /**
     * Get charts (for dashboard type).
     */
    public function getCharts(): array
    {
        return $this->data['structure']['charts'] ?? [];
    }

    // ─── Data Binding ──────────────────────────────────

    /**
     * Get data binding config (query/mutation references).
     */
    public function getDataBinding(): array
    {
        return $this->data['dataBinding'] ?? [];
    }

    /**
     * Get the primary query name for data fetching.
     */
    public function getQuery(): string
    {
        return $this->data['dataBinding']['query'] ?? '';
    }

    /**
     * Get the mutation name for form submission.
     */
    public function getMutation(): string
    {
        return $this->data['dataBinding']['mutation'] ?? '';
    }

    // ─── Renderer Integration ──────────────────────────

    /**
     * Convert this definition into a config array suitable for renderers.
     *
     * Maps the portable definition format into the renderer's expected config
     * structure (which currently lives in package JSON components).
     *
     * @param string|null $componentId Optional override for the component DOM ID
     * @return array Config array for ComponentRendererInterface::render()
     */
    public function toRendererConfig(?string $componentId = null): array
    {
        $structure = $this->getStructure();
        $visual = $this->getVisualConfig();
        $binding = $this->getDataBinding();

        $config = array_merge($structure, [
            'id'        => $componentId ?? 'pkg-' . $this->getType() . '-' . substr(md5($this->getId()), 0, 8),
            'type'      => $this->getType(),
            'visual'    => $visual,
            'dataQuery' => $binding['query'] ?? '',
            'mutation'  => $binding['mutation'] ?? '',
        ]);

        return $config;
    }

    // ─── Validation ────────────────────────────────────

    /**
     * Validate this definition against the component schema.
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validate(): array
    {
        $errors = [];

        // Metadata validation
        if (empty($this->getId())) {
            $errors[] = 'definition.id is required';
        }
        if (empty($this->getType())) {
            $errors[] = 'definition.type is required';
        }
        if (!in_array($this->getType(), ['table', 'form', 'detail', 'dashboard', 'chart', 'filters', 'cards'])) {
            $errors[] = "definition.type '{$this->getType()}' is not a valid component type";
        }
        if (empty($this->getName())) {
            $errors[] = 'definition.name is required';
        }

        // Visual config validation
        $preset = $this->getPreset();
        if (!in_array($preset, VisualPresets::names())) {
            $errors[] = "visual.preset '{$preset}' is not a valid preset. Valid: " . implode(', ', VisualPresets::names());
        }

        $registry = VisualConfig::tokenRegistry();
        foreach ($this->getTokenOverrides() as $token => $value) {
            if (!isset($registry[$token])) {
                $errors[] = "visual.tokens.{$token} is not a recognized token";
            }
        }

        // Structure validation (type-specific)
        switch ($this->getType()) {
            case 'table':
                if (empty($this->getColumns())) {
                    $errors[] = 'structure.columns is required for table components';
                }
                foreach ($this->getColumns() as $i => $col) {
                    if (empty($col['key'])) {
                        $errors[] = "structure.columns[{$i}].key is required";
                    }
                    if (empty($col['label'])) {
                        $errors[] = "structure.columns[{$i}].label is required";
                    }
                }
                break;

            case 'form':
                if (empty($this->getFields()) && empty($this->getSections())) {
                    $errors[] = 'structure.fields or structure.sections is required for form components';
                }
                break;

            case 'dashboard':
            case 'cards':
                if (empty($this->getCards()) && empty($this->getCharts())) {
                    $errors[] = 'structure.cards or structure.charts is required for dashboard components';
                }
                break;
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    // ─── Serialization ─────────────────────────────────

    /**
     * Export as a JSON string.
     */
    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->data, $flags);
    }

    /**
     * Export as an array.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Clone this definition with visual overrides applied.
     *
     * @param array $tokenOverrides Additional token overrides
     * @param string|null $newPreset Optional preset change
     * @return self New instance with merged visual config
     */
    public function withVisualOverrides(array $tokenOverrides, ?string $newPreset = null): self
    {
        $data = $this->data;

        if ($newPreset !== null) {
            $data['visual']['preset'] = $newPreset;
        }

        $existing = $data['visual']['tokens'] ?? [];
        $data['visual']['tokens'] = array_merge($existing, $tokenOverrides);

        return new self($data);
    }
}

/**
 * Fluent Builder for ComponentDefinition
 *
 * Usage:
 *   $def = ComponentDefinition::build('table')
 *       ->id('contrib.employee-roster')
 *       ->name('Employee Roster')
 *       ->preset('comfortable')
 *       ->token('filter-input-width', '280px')
 *       ->column('name', 'Name', ['sortable' => true])
 *       ->column('dept', 'Department', ['type' => 'badge'])
 *       ->filter('search', 'search', ['placeholder' => 'Search...'])
 *       ->action('view', 'View', ['icon' => 'fa-eye', 'type' => 'route', 'to' => '/employee/{id}'])
 *       ->query('employees.search')
 *       ->toDefinition();
 */
class ComponentDefinitionBuilder
{
    private array $data;

    public function __construct(string $type)
    {
        $this->data = [
            'definition' => [
                'id'      => '',
                'name'    => '',
                'type'    => $type,
                'version' => '1.0.0',
                'author'  => '',
                'tags'    => [],
            ],
            'visual' => [
                'preset' => 'comfortable',
                'tokens' => [],
            ],
            'structure' => [],
            'dataBinding' => [],
        ];
    }

    // Metadata
    public function id(string $id): self { $this->data['definition']['id'] = $id; return $this; }
    public function name(string $name): self { $this->data['definition']['name'] = $name; return $this; }
    public function version(string $v): self { $this->data['definition']['version'] = $v; return $this; }
    public function author(string $a): self { $this->data['definition']['author'] = $a; return $this; }
    public function description(string $d): self { $this->data['definition']['description'] = $d; return $this; }
    public function tags(array $t): self { $this->data['definition']['tags'] = $t; return $this; }

    // Visual config
    public function preset(string $p): self { $this->data['visual']['preset'] = $p; return $this; }
    public function token(string $name, string $value): self { $this->data['visual']['tokens'][$name] = $value; return $this; }
    public function tokens(array $t): self { $this->data['visual']['tokens'] = array_merge($this->data['visual']['tokens'], $t); return $this; }

    // Table structure
    public function column(string $key, string $label, array $options = []): self
    {
        $col = array_merge(['key' => $key, 'label' => $label], $options);
        $this->data['structure']['columns'][] = $col;
        return $this;
    }

    public function filter(string $key, string $type, array $options = []): self
    {
        $filter = array_merge(['key' => $key, 'type' => $type], $options);
        $this->data['structure']['filters'][] = $filter;
        return $this;
    }

    public function action(string $id, string $label, array $options = []): self
    {
        $action = array_merge(['id' => $id, 'label' => $label], $options);
        $this->data['structure']['actions'][] = $action;
        return $this;
    }

    public function bulkAction(string $label, string $mutation, array $options = []): self
    {
        $action = array_merge(['label' => $label, 'mutation' => $mutation], $options);
        $this->data['structure']['bulkActions'][] = $action;
        return $this;
    }

    public function pagination(int $perPage = 50, array $sizes = [25, 50, 100]): self
    {
        $this->data['structure']['pagination'] = ['perPage' => $perPage, 'pageSizes' => $sizes];
        return $this;
    }

    // Form structure
    public function field(string $key, string $type, string $label, array $options = []): self
    {
        $field = array_merge(['key' => $key, 'type' => $type, 'label' => $label], $options);
        $this->data['structure']['fields'][] = $field;
        return $this;
    }

    public function section(string $title, array $fields, array $options = []): self
    {
        $section = array_merge(['title' => $title, 'fields' => $fields], $options);
        $this->data['structure']['sections'][] = $section;
        return $this;
    }

    // Dashboard structure
    public function card(string $title, string $dataKey, array $options = []): self
    {
        $card = array_merge(['title' => $title, 'dataKey' => $dataKey], $options);
        $this->data['structure']['cards'][] = $card;
        return $this;
    }

    public function chart(string $chartType, array $options = []): self
    {
        $chart = array_merge(['chartType' => $chartType], $options);
        $this->data['structure']['charts'][] = $chart;
        return $this;
    }

    // Data binding
    public function query(string $q): self { $this->data['dataBinding']['query'] = $q; return $this; }
    public function mutation(string $m): self { $this->data['dataBinding']['mutation'] = $m; return $this; }
    public function countQuery(string $q): self { $this->data['dataBinding']['countQuery'] = $q; return $this; }

    /**
     * Build the final ComponentDefinition.
     */
    public function toDefinition(): ComponentDefinition
    {
        return ComponentDefinition::fromArray($this->data);
    }
}
