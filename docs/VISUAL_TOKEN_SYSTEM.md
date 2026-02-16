# Visual Token & Flexible Component System

**Version**: 1.0.0-draft  
**Date**: February 16, 2026  
**Status**: Phase 1 Complete, Phase 2-5 In Progress  
**Author**: Engineering / AI Agent  
**Depends On**: `PACKAGE_ARCHITECTURE_SPEC.md`, `PACKAGE_CONTRIBUTING.md`

---

## Table of Contents

1. [Problem Statement](#1-problem-statement)
2. [Architecture Overview](#2-architecture-overview)
3. [Visual Token System](#3-visual-token-system)
4. [Preset System](#4-preset-system)
5. [Component Definition Model](#5-component-definition-model)
6. [CSS Architecture](#6-css-architecture)
7. [Renderer Integration](#7-renderer-integration)
8. [Package JSON Schema Changes](#8-package-json-schema-changes)
9. [File Map & Implementation Status](#9-file-map--implementation-status)
10. [How to Add a New Token](#10-how-to-add-a-new-token)
11. [How to Create a New Preset](#11-how-to-create-a-new-preset)
12. [How to Build a Component Definition](#12-how-to-build-a-component-definition)
13. [Security Model](#13-security-model)
14. [Testing Strategy](#14-testing-strategy)
15. [Migration Path](#15-migration-path)

---

## 1. Problem Statement

### The "Prayer" Problem

Previously, every table, filter bar, and dashboard had hardcoded CSS values:

```css
/* Before — hardcoded, no user control */
.pkg-table td {
    padding: 0.625rem 1rem;    /* What if I want tighter rows? */
    font-size: 0.875rem;        /* What if my data needs smaller text? */
}
.pkg-filter-input {
    width: 220px;              /* What if my labels are longer? */
}
```

Contributors creating packages had **no way** to adjust visual dimensions without:
- Overriding CSS with `!important` (fragile)
- Writing custom CSS per package (duplicative)
- "Praying" it looks right with their data

### The Solution: Visual Tokens

Every visual dimension becomes a **named CSS custom property** (a "token") that:
- Has a sensible **default** value
- Has a documented **min/max** range
- Can be overridden at any level (global, package, component)
- Is **sanitized** server-side to prevent CSS injection
- Is grouped into **presets** for one-click density changes

```css
/* After — token-driven, fully configurable */
.pkg-table td {
    padding: var(--v-table-cell-padding-y, 0.625rem)
             var(--v-table-cell-padding-x, 1rem);
    font-size: var(--v-table-font-size, 0.875rem);
}
```

---

## 2. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     PACKAGE JSON DEFINITION                      │
│                                                                   │
│  {                                                                │
│    "visual": {                                                    │
│      "preset": "comfortable",     ← Start from a preset          │
│      "tokens": {                                                  │
│        "table-row-height": "40px", ← Override specific tokens     │
│        "filter-input-width": "280px"                              │
│      }                                                            │
│    },                                                             │
│    "presentation": { ... },        ← Structural (columns, etc.)   │
│    "data": { ... }                 ← Queries/mutations             │
│  }                                                                │
└─────────────────┬───────────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                    PHP: VisualConfig                              │
│                                                                   │
│  VisualPresets::get('comfortable')  → base token values           │
│       +                                                           │
│  Package JSON "tokens"              → overrides                   │
│       =                                                           │
│  VisualConfig::resolveConfig()      → final merged config         │
│       │                                                           │
│       ├── toInlineStyle()  → style="--v-table-row-height: 40px"  │
│       ├── toStyleBlock()   → <style>#comp { --v-...: ... }</style>│
│       └── defaultsAsCSS() → :root { --v-...: ... }               │
└─────────────────┬───────────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                    CSS: package-components.css                    │
│                                                                   │
│  Every CSS rule reads from var(--v-token-name, fallback):        │
│                                                                   │
│  .pkg-table td {                                                  │
│    padding: var(--v-table-cell-padding-y) var(--v-table-cell-...) │
│    font-size: var(--v-table-font-size);                           │
│    color: var(--v-table-text-color);                              │
│  }                                                                │
│                                                                   │
│  .pkg-filter-input {                                              │
│    width: var(--v-filter-input-width);                            │
│    height: var(--v-filter-input-height);                          │
│  }                                                                │
└─────────────────────────────────────────────────────────────────┘
```

### Token Resolution Order (Cascade)

Specificity flows downward. More specific scopes override broader ones:

```
1. CSS defaults  (hardcoded in var() fallback)
2. :root tokens  (VisualConfig::defaultsAsCSS — global baseline)
3. Preset tokens (VisualPresets — chosen density: compact/comfortable/spacious)
4. Package-level (package JSON "visual.tokens" — package authors)
5. Org-level      (future: admin settings panel — org admins)
6. Inline/scoped  (per-component style="" — fine-tuned overrides)
```

---

## 3. Visual Token System

### File: `src/Package/VisualConfig.php` ✅ COMPLETE

The token registry is the single source of truth for every configurable visual property.

### Token Naming Convention

```
--v-{scope}-{property}
```

| Scope    | What It Controls                        |
|----------|----------------------------------------|
| `table`  | Table rows, cells, headers, borders    |
| `filter` | Search inputs, dropdowns, filter bars  |
| `card`   | Dashboard KPI cards                    |
| `form`   | Form inputs, labels, grid layout       |
| `page`   | Page width, padding, background        |

### Complete Token Reference

#### Table Tokens

| Token                      | Default        | Type   | Min      | Max      | Description                          |
|---------------------------|----------------|--------|----------|----------|--------------------------------------|
| `table-row-height`        | `48px`         | length | `32px`   | `80px`   | Height of each table row             |
| `table-cell-padding-x`   | `1rem`         | length | `0.25rem`| `3rem`   | Horizontal padding inside cells      |
| `table-cell-padding-y`   | `0.625rem`     | length | `0.125rem`| `2rem`  | Vertical padding inside cells        |
| `table-header-padding-x` | `1rem`         | length | `0.25rem`| `3rem`   | Horizontal padding in header cells   |
| `table-header-padding-y` | `0.75rem`      | length | `0.25rem`| `2rem`   | Vertical padding in header cells     |
| `table-font-size`        | `0.875rem`     | length | `0.7rem` | `1.25rem`| Base font size for table body        |
| `table-header-font-size` | `0.8rem`       | length | `0.65rem`| `1rem`   | Font size for table headers          |
| `table-border-radius`    | `8px`          | length | `0`      | `20px`   | Corner rounding on table container   |
| `table-border-color`     | `#e5e7eb`      | color  | —        | —        | Border color for table edges         |
| `table-header-bg`        | `#f9fafb`      | color  | —        | —        | Header row background                |
| `table-header-color`     | `#374151`      | color  | —        | —        | Header row text color                |
| `table-row-bg`           | `#ffffff`      | color  | —        | —        | Default row background               |
| `table-row-hover`        | `#f9fafb`      | color  | —        | —        | Row background on hover              |
| `table-row-stripe`       | `transparent`  | color  | —        | —        | Alternating row color                |
| `table-row-selected`     | `#eff6ff`      | color  | —        | —        | Selected row background              |
| `table-text-color`       | `#1f2937`      | color  | —        | —        | Text color in cells                  |
| `table-divider-color`    | `#f3f4f6`      | color  | —        | —        | Row divider line color               |
| `table-shadow`           | `none`         | shadow | —        | —        | Box shadow on container              |
| `table-max-width`        | `100%`         | length | —        | —        | Maximum table container width        |
| `table-min-width`        | `100%`         | length | —        | —        | Minimum table width (scroll)         |

#### Toolbar Tokens

| Token                       | Default      | Type   | Description                      |
|----------------------------|--------------|--------|----------------------------------|
| `toolbar-padding-x`       | `0`          | length | Horizontal toolbar padding       |
| `toolbar-padding-y`       | `0.75rem`    | length | Vertical toolbar padding         |
| `toolbar-gap`             | `1rem`       | length | Gap between toolbar items        |
| `toolbar-info-font-size`  | `0.875rem`   | length | Record count font size           |

#### Filter Tokens

| Token                     | Default      | Type   | Min      | Max      | Description                       |
|--------------------------|--------------|--------|----------|----------|-----------------------------------|
| `filter-input-width`     | `220px`      | length | `100px`  | `500px`  | Width of search/text inputs       |
| `filter-select-width`    | `180px`      | length | `80px`   | `400px`  | Width of dropdown selects         |
| `filter-input-height`    | `38px`       | length | `28px`   | `56px`   | Height of filter inputs           |
| `filter-gap`             | `1rem`       | length | `0.25rem`| `3rem`   | Gap between filter fields         |
| `filter-padding`         | `1rem`       | length | —        | —        | Filter bar container padding      |
| `filter-bg`              | `#ffffff`    | color  | —        | —        | Filter bar background             |
| `filter-border-radius`   | `8px`        | length | —        | —        | Filter bar corner rounding        |
| `filter-border-color`    | `#e5e7eb`    | color  | —        | —        | Filter bar border color           |
| `filter-label-size`      | `0.75rem`    | length | —        | —        | Filter label font size            |
| `filter-label-color`     | `#6b7280`    | color  | —        | —        | Filter label color                |
| `filter-label-weight`    | `600`        | number | —        | —        | Filter label font weight          |

#### Action Button Tokens

| Token                    | Default      | Type   | Description                       |
|-------------------------|--------------|--------|-----------------------------------|
| `action-btn-size`       | `0.78rem`    | length | Row action button font size       |
| `action-btn-padding-x`  | `0.625rem`   | length | Horizontal action button padding  |
| `action-btn-padding-y`  | `0.25rem`    | length | Vertical action button padding    |
| `action-btn-radius`     | `5px`        | length | Action button border radius       |
| `action-gap`            | `0.375rem`   | length | Gap between action buttons        |

#### Pagination Tokens

| Token                   | Default      | Type   | Description                       |
|------------------------|--------------|--------|-----------------------------------|
| `pagination-gap`       | `0.375rem`   | length | Gap between page buttons          |
| `pagination-btn-size`  | `0.8rem`     | length | Page button font size             |
| `pagination-padding`   | `1rem 0`     | string | Pagination bar padding            |

#### Badge Tokens

| Token                  | Default      | Type   | Description                       |
|-----------------------|--------------|--------|-----------------------------------|
| `badge-font-size`     | `0.75rem`    | length | Badge text font size              |
| `badge-padding-x`     | `0.625rem`   | length | Horizontal badge padding          |
| `badge-padding-y`     | `0.2rem`     | length | Vertical badge padding            |
| `badge-radius`        | `9999px`     | length | Badge border radius (pill shape)  |

#### Card/Dashboard Tokens

| Token                  | Default                          | Type   | Min | Max | Description                  |
|-----------------------|---------------------------------|--------|-----|-----|------------------------------|
| `card-columns`        | `4`                             | number | 1   | 6   | Dashboard card columns       |
| `card-gap`            | `1.5rem`                        | length | —   | —   | Gap between cards            |
| `card-padding`        | `1.25rem`                       | length | —   | —   | Card internal padding        |
| `card-radius`         | `12px`                          | length | —   | —   | Card corner rounding         |
| `card-shadow`         | `0 1px 3px rgba(0,0,0,0.1)`    | shadow | —   | —   | Card shadow                  |
| `card-border`         | `1px solid #e5e7eb`             | string | —   | —   | Card border style            |
| `card-icon-size`      | `2.5rem`                        | length | —   | —   | KPI card icon size           |
| `card-value-size`     | `2rem`                          | length | —   | —   | KPI card value font size     |

#### Form Tokens

| Token                  | Default      | Type   | Description                       |
|-----------------------|--------------|--------|-----------------------------------|
| `form-label-size`     | `0.875rem`   | length | Form label font size              |
| `form-label-weight`   | `500`        | number | Form label font weight            |
| `form-input-height`   | `40px`       | length | Form input height                 |
| `form-input-radius`   | `6px`        | length | Form input border radius          |
| `form-section-gap`    | `2rem`       | length | Gap between form sections         |
| `form-field-gap`      | `1rem`       | length | Gap between form fields           |
| `form-grid-gap`       | `1.5rem`     | length | Grid layout form gap              |

#### Page Layout Tokens

| Token                  | Default      | Type   | Description                       |
|-----------------------|--------------|--------|-----------------------------------|
| `page-max-width`      | `1400px`     | length | Maximum content width             |
| `page-padding-x`      | `2rem`       | length | Horizontal page padding           |
| `page-padding-y`      | `1.5rem`     | length | Vertical page padding             |
| `page-bg`             | `#f8f9fa`    | color  | Page background color             |

---

## 4. Preset System

### File: `src/Package/VisualPresets.php` ⏳ TODO

Presets are named collections of token overrides that provide one-click density/style switching.

### Built-in Presets

| Preset        | Description                              | Best For                      |
|--------------|------------------------------------------|-------------------------------|
| `compact`    | Tight spacing, smaller fonts, dense data | Data-heavy admin views, logs  |
| `comfortable`| Default — balanced spacing (no overrides)| General-purpose               |
| `spacious`   | Generous padding, larger fonts           | Public-facing, accessibility  |
| `print`      | Optimized for printed output             | Reports, exports              |

### Preset Structure

```php
// VisualPresets::get('compact') returns:
[
    'table-row-height'       => '36px',
    'table-cell-padding-x'   => '0.5rem',
    'table-cell-padding-y'   => '0.25rem',
    'table-font-size'        => '0.8rem',
    'table-header-font-size' => '0.7rem',
    'filter-input-height'    => '32px',
    'filter-gap'             => '0.5rem',
    'card-gap'               => '0.75rem',
    'card-padding'           => '0.75rem',
    // ... only the tokens that differ from defaults
]
```

### Resolution

```php
$config = VisualConfig::resolveConfig(
    presetName: 'compact',                       // Start from compact preset
    overrides: ['table-row-height' => '40px']    // But make rows a bit taller
);
// Result: all compact values, but table-row-height is 40px instead of 36px
```

---

## 5. Component Definition Model

### File: `src/Package/ComponentDefinition.php` ⏳ TODO

A **Component Definition** is a portable, self-contained blueprint that describes:
- **What** it displays (structural config — columns, fields, cards)
- **How** it looks (visual config — tokens)
- **Where** its data comes from (query binding)
- **Who** built it (contributor metadata)

### Why This Matters

Contributors build definitions. Admins install them. Organizations customize the visual tokens to match their branding/preferences. The structural rules stay intact (immutable), but the visual layer is flexible.

### Definition JSON Structure

```json
{
  "definition": {
    "id": "contrib.employee-roster",
    "name": "Employee Roster Table",
    "version": "1.0.0",
    "type": "table",
    "author": "Jane Doe",
    "description": "A clean employee directory with search, grade filter, and export",
    "tags": ["directory", "employee", "hr", "roster"]
  },
  "visual": {
    "preset": "comfortable",
    "tokens": {
      "table-row-height": "44px",
      "filter-input-width": "260px",
      "filter-select-width": "200px",
      "badge-radius": "6px"
    }
  },
  "structure": {
    "columns": [
      { "key": "name",       "label": "Name",       "sortable": true,  "type": "text" },
      { "key": "department", "label": "Department",  "sortable": true,  "type": "badge", "badgeMap": { "HR": "info", "IT": "primary" } },
      { "key": "email",      "label": "Email",       "sortable": false, "type": "text", "copyable": true },
      { "key": "hire_date",  "label": "Hired",        "sortable": true,  "type": "date" },
      { "key": "salary",     "label": "Salary",       "sortable": true,  "type": "currency", "responsive": "hide-mobile" }
    ],
    "filters": [
      { "key": "search",     "type": "search",  "placeholder": "Search by name or email..." },
      { "key": "department", "type": "select",   "label": "Department", "optionsQuery": "departments.list" },
      { "key": "status",     "type": "select",   "label": "Status",     "options": [{"value": "active", "label": "Active"}, {"value": "inactive", "label": "Inactive"}] }
    ],
    "actions": [
      { "id": "view",   "label": "View",   "icon": "fa-eye",   "type": "route", "to": "/employee/{id}" },
      { "id": "edit",   "label": "Edit",   "icon": "fa-edit",  "type": "route", "to": "/employee/{id}/edit" }
    ],
    "pagination": { "perPage": 50, "pageSizes": [25, 50, 100] }
  },
  "dataBinding": {
    "query": "employees.search",
    "countQuery": "employees.count"
  }
}
```

### PHP Class Methods

```php
class ComponentDefinition
{
    // Load from JSON file or array
    public static function fromJson(string $json): self;
    public static function fromArray(array $data): self;

    // Accessors
    public function getId(): string;
    public function getType(): string;        // table, form, chart, dashboard
    public function getVersion(): string;
    public function getVisualConfig(): array;  // preset + token overrides
    public function getStructure(): array;     // columns, filters, actions
    public function getDataBinding(): array;   // query references

    // Render helpers
    public function resolveVisualTokens(): array; // preset merged with overrides
    public function toRendererConfig(): array;     // config array for renderers

    // Validation
    public function validate(): ValidationResult;

    // Export
    public function toJson(): string;
    public function toArray(): array;
}
```

---

## 6. CSS Architecture

### File: `public/assets/css/package-components.css` ⏳ TODO (Refactor)

### Before (Hardcoded)

```css
.pkg-table td {
    padding: 0.625rem 1rem;
    border-bottom: 1px solid #f3f4f6;
    color: #1f2937;
}
```

### After (Token-Driven)

```css
.pkg-table td {
    padding: var(--v-table-cell-padding-y, 0.625rem)
             var(--v-table-cell-padding-x, 1rem);
    border-bottom: 1px solid var(--v-table-divider-color, #f3f4f6);
    color: var(--v-table-text-color, #1f2937);
}
```

### Key Rules

1. **Every `var()` must include a fallback** — the same value that was hardcoded before. This means the CSS works identically without any tokens set.
2. **Never use `!important` on tokenized properties** — the cascade handles it.
3. **Scoping** — tokens are scoped to the nearest ancestor that sets them. A `<style>` block on a `#component-id` only affects that component's children.
4. **Backwards compatible** — existing pages that don't set any `--v-*` tokens render exactly as before.

### Full CSS Properties to Tokenize

```css
/* ===== TABLE ===== */
.pkg-table-toolbar          → padding: var(--v-toolbar-padding-y) var(--v-toolbar-padding-x)
                              gap: var(--v-toolbar-gap)
.pkg-table-count .count-label → font-size: var(--v-toolbar-info-font-size)
.pkg-table-container        → border-radius: var(--v-table-border-radius)
                              border-color: var(--v-table-border-color)
                              box-shadow: var(--v-table-shadow)
                              max-width: var(--v-table-max-width)
.pkg-table                  → font-size: var(--v-table-font-size)
                              min-width: var(--v-table-min-width)
.pkg-table thead            → background: var(--v-table-header-bg)
.pkg-table th               → padding: var(--v-table-header-padding-y) var(--v-table-header-padding-x)
                              color: var(--v-table-header-color)
                              font-size: var(--v-table-header-font-size)
.pkg-table td               → padding: var(--v-table-cell-padding-y) var(--v-table-cell-padding-x)
                              border-bottom-color: var(--v-table-divider-color)
                              color: var(--v-table-text-color)
.pkg-table tbody tr          → background: var(--v-table-row-bg)
.pkg-table tbody tr:hover    → background: var(--v-table-row-hover)
.pkg-table tbody tr:nth-child(even) → background: var(--v-table-row-stripe)
tr[data-selected]            → background: var(--v-table-row-selected)

/* ===== FILTERS ===== */
.pkg-filters                 → padding: var(--v-filter-padding)
                               background: var(--v-filter-bg)
                               border-radius: var(--v-filter-border-radius)
                               border-color: var(--v-filter-border-color)
                               gap: var(--v-filter-gap)
.pkg-filter-input            → width: var(--v-filter-input-width)
                               height: var(--v-filter-input-height)
.pkg-filter-select           → width: var(--v-filter-select-width)
                               height: var(--v-filter-input-height)
.pkg-filter-label            → font-size: var(--v-filter-label-size)
                               color: var(--v-filter-label-color)
                               font-weight: var(--v-filter-label-weight)

/* ===== ACTIONS ===== */
.pkg-action-group            → gap: var(--v-action-gap)
.pkg-row-action              → font-size: var(--v-action-btn-size)
                               padding: var(--v-action-btn-padding-y) var(--v-action-btn-padding-x)
                               border-radius: var(--v-action-btn-radius)

/* ===== BADGES ===== */
.pkg-grade-badge             → font-size: var(--v-badge-font-size)
                               padding: var(--v-badge-padding-y) var(--v-badge-padding-x)
                               border-radius: var(--v-badge-radius)

/* ===== PAGINATION ===== */
.pkg-pagination              → padding: var(--v-pagination-padding)
                               gap: var(--v-pagination-gap)
.pkg-page-btn                → font-size: var(--v-pagination-btn-size)

/* ===== CARDS ===== */
.pkg-stat-cards              → grid-template-columns: repeat(var(--v-card-columns), 1fr)
                               gap: var(--v-card-gap)
.pkg-stat-card               → padding: var(--v-card-padding)
                               border-radius: var(--v-card-radius)
                               box-shadow: var(--v-card-shadow)
                               border: var(--v-card-border)
.pkg-stat-icon               → font-size: var(--v-card-icon-size)
.pkg-stat-value              → font-size: var(--v-card-value-size)

/* ===== FORMS ===== */
.pkg-form label              → font-size: var(--v-form-label-size)
                               font-weight: var(--v-form-label-weight)
.pkg-form input/select/textarea → height: var(--v-form-input-height)
                               border-radius: var(--v-form-input-radius)
.pkg-form-section + section → margin-top: var(--v-form-section-gap)
.pkg-form-grid              → gap: var(--v-form-grid-gap)
.pkg-form-field + field     → margin-top: var(--v-form-field-gap)

/* ===== PAGE ===== */
.pkg-page-content            → max-width: var(--v-page-max-width)
                               padding: var(--v-page-padding-y) var(--v-page-padding-x)
.pkg-page-wrap               → background: var(--v-page-bg)
```

---

## 7. Renderer Integration

### How Renderers Emit Tokens

Each renderer (TableRenderer, FilterRenderer, etc.) calls `VisualConfig` to emit a scoped `<style>` block:

```php
// In TableRenderer::render()
public function render(array $config, array $data, array $context): string
{
    $componentId = $config['id'] ?? 'pkg-table-' . uniqid();

    // Resolve visual tokens
    $visualConfig = $config['visual'] ?? [];
    $preset = $visualConfig['preset'] ?? 'comfortable';
    $overrides = $visualConfig['tokens'] ?? [];
    $resolvedTokens = VisualConfig::resolveConfig($preset, $overrides);

    $html = '';

    // Emit scoped style block (only if there are overrides)
    if (!empty($resolvedTokens)) {
        $html .= VisualConfig::toStyleBlock($resolvedTokens, '', $componentId);
    }

    // ... rest of table rendering (uses CSS that reads from var(--v-*) tokens)
}
```

### Scoping

The `<style>` block targets the component's ID:

```html
<style>#pkg-table-abc123 {
  --v-table-row-height: 40px;
  --v-filter-input-width: 280px;
}</style>
<div id="pkg-table-abc123">
  <!-- This table and its filters inherit the overridden tokens -->
</div>
```

Multiple tables on the same page can each have different visual configs — they're scoped by ID.

---

## 8. Package JSON Schema Changes

### File: `config/package-schema.json` ⏳ TODO

Add `visual` to the component definition:

```json
{
  "component": {
    "type": "object",
    "properties": {
      "type": { "enum": ["table", "form", "detail", "dashboard", "wizard", "report", "chart"] },
      "visual": {
        "$ref": "#/definitions/visualConfig"
      },
      "columns": { ... },
      "actions": { ... }
    }
  }
}
```

New definition to add:

```json
{
  "definitions": {
    "visualConfig": {
      "type": "object",
      "description": "Visual configuration tokens. Use a preset as base, then override individual tokens.",
      "properties": {
        "preset": {
          "type": "string",
          "enum": ["compact", "comfortable", "spacious", "print"],
          "default": "comfortable",
          "description": "Built-in visual density preset"
        },
        "tokens": {
          "type": "object",
          "description": "Override individual visual tokens (--v-* CSS custom properties)",
          "additionalProperties": { "type": "string" }
        }
      }
    }
  }
}
```

### File: `src/Package/Schema/ComponentSchema.php` ⏳ TODO

Add `visual` to table, form, detail, dashboard, and filters config schemas.

---

## 9. File Map & Implementation Status

| File | Type | Status | Description |
|------|------|--------|-------------|
| `src/Package/VisualConfig.php` | PHP | ✅ Complete | Token registry, sanitization, CSS output |
| `src/Package/VisualPresets.php` | PHP | ⏳ Todo | Built-in density presets |
| `src/Package/ComponentDefinition.php` | PHP | ⏳ Todo | Portable component blueprint class |
| `src/Package/Schema/ComponentSchema.php` | PHP | ⏳ Todo | Add `visual` section to all stack schemas |
| `config/package-schema.json` | JSON | ⏳ Todo | Add `visualConfig` definition |
| `public/assets/css/package-components.css` | CSS | ⏳ Todo | Refactor hardcoded values to `var(--v-*)` |
| `src/Package/Renderers/TableRenderer.php` | PHP | ⏳ Todo | Emit visual tokens as scoped style |
| `src/Package/Renderers/FilterRenderer.php` | PHP | ⏳ Todo | Same |
| `src/Package/Renderers/DashboardRenderer.php` | PHP | ⏳ Todo | Same |
| `src/Package/Renderers/FormRenderer.php` | PHP | ⏳ Todo | Same |
| `src/Package/ComponentRegistry.php` | PHP | ✅ Exists | No changes needed (already dynamic) |

### Dependency Chain

```
VisualConfig ← VisualPresets         (presets reference token registry)
             ← ComponentDefinition   (definitions contain visual config)
             ← Renderers             (renderers emit tokens)
             ← package-components.css (CSS reads tokens)
             ← ComponentSchema       (validates visual config in JSON)
             ← package-schema.json   (published JSON schema)
```

Build order: **VisualPresets → CSS refactor → Renderers → ComponentDefinition → Schema updates**

---

## 10. How to Add a New Token

### Step 1: Add to Registry

In `src/Package/VisualConfig.php`, add to `tokenRegistry()`:

```php
'table-header-border-width' => [
    'default'     => '2px',
    'type'        => 'length',
    'scope'       => 'table',
    'min'         => '0',
    'max'         => '5px',
    'description' => 'Bottom border width of header row',
],
```

### Step 2: Use in CSS

In `public/assets/css/package-components.css`:

```css
.pkg-table thead {
    border-bottom: var(--v-table-header-border-width, 2px) solid var(--v-table-border-color, #e5e7eb);
}
```

### Step 3: Add to Presets (if needed)

In `src/Package/VisualPresets.php`, override in relevant presets:

```php
'compact' => [
    'table-header-border-width' => '1px',
    // ...
],
```

### Step 4: Update Documentation

Add the token to the reference table in this document.

---

## 11. How to Create a New Preset

In `src/Package/VisualPresets.php`:

```php
public static function allPresets(): array
{
    return [
        'my-custom-preset' => [
            'label'       => 'My Custom',
            'description' => 'A preset for [use case]',
            'tokens'      => [
                'table-row-height'     => '44px',
                'table-font-size'      => '0.9rem',
                'filter-input-width'   => '300px',
                // Only include tokens that DIFFER from defaults
            ],
        ],
    ];
}
```

### Rules
- Only include token overrides — tokens not listed fall through to registry defaults.
- Preset names must be lowercase alphanumeric with hyphens.
- Keep the number of presets small. Most customization happens via per-package token overrides.

---

## 12. How to Build a Component Definition

### For Contributors

1. **Start from scratch or fork an existing definition**
2. **Choose a component type** (`table`, `form`, `chart`, `dashboard`)
3. **Define structure** (columns, fields, cards — these are immutable once published)
4. **Set visual defaults** (preset + token overrides — these are adjustable by orgs)
5. **Bind to data** (query names that the installing package implements)
6. **Validate** against the schema (`ComponentDefinition::validate()`)
7. **Publish** as part of a `.hubpkg` or as a standalone `.json` file

### For Admins Installing Definitions

1. **Browse** available component definitions
2. **Preview** with different presets (compact/comfortable/spacious)
3. **Customize** visual tokens in the admin settings panel
4. **Save** — overrides are stored per-org and applied at render time

### Example: A Contributor Creates a "Student Roster" Definition

```json
{
  "definition": {
    "id": "contrib.student-roster-v1",
    "name": "Student Roster",
    "type": "table",
    "version": "1.0.0",
    "author": "Jane@WoodsonISD",
    "tags": ["students", "directory", "roster"]
  },
  "visual": {
    "preset": "comfortable",
    "tokens": {
      "filter-input-width": "250px",
      "table-row-height": "44px"
    }
  },
  "structure": {
    "columns": [
      { "key": "student_name",  "label": "Student",    "sortable": true, "type": "text" },
      { "key": "student_id",    "label": "ID",          "sortable": true, "type": "text", "copyable": true },
      { "key": "grade",         "label": "Grade",       "sortable": true, "type": "badge" },
      { "key": "school",        "label": "School",      "sortable": true, "type": "text", "responsive": "hide-mobile" },
      { "key": "password",      "label": "Password",    "sortable": false, "type": "masked", "dataClass": "regulated" }
    ],
    "filters": [
      { "key": "search",    "type": "search",  "placeholder": "Search by name, ID, or email..." },
      { "key": "grade",     "type": "select",   "label": "Grade",           "optionsQuery": "grades.list" },
      { "key": "grad_year", "type": "select",   "label": "Graduation Year", "optionsQuery": "gradYears.list" }
    ],
    "actions": [
      { "id": "view", "label": "View", "icon": "fa-eye", "type": "route", "to": "/student/{student_id}" }
    ],
    "bulkActions": [
      { "label": "Export Selected", "icon": "fa-download", "mutation": "students.exportBulk" }
    ],
    "pagination": { "perPage": 50, "pageSizes": [25, 50, 100] }
  }
}
```

### An Admin Installs and Customizes

The admin overrides the contributor's visual tokens without changing structure:

```json
{
  "visual": {
    "preset": "compact",
    "tokens": {
      "filter-input-width": "300px",
      "table-header-bg": "#1e3a5f",
      "table-header-color": "#ffffff"
    }
  }
}
```

Result: The table uses compact density with the contributor's column/filter layout, but the admin's custom header colors and wider search box.

---

## 13. Security Model

### Token Sanitization

All token values are sanitized server-side in `VisualConfig::sanitizeValue()`:

| Type     | Allowed Values                                                  | Blocked                      |
|---------|----------------------------------------------------------------|------------------------------|
| `color`  | Hex, rgb(), rgba(), hsl(), hsla(), named colors, transparent    | url(), expression(), etc.    |
| `length` | Number+unit (px, rem, em, %, vw, vh), calc(), 0, auto          | Script injection, url()     |
| `number` | Numeric only                                                    | Non-numeric                 |
| `shadow` | Standard shadow syntax or 'none'                                | Complex injection            |
| `string` | Any string ≤200 chars                                           | Semicolons, braces, url()   |

**Global blocks**: Any value containing `;`, `{`, `}`, `url(`, `expression(`, `javascript:`, or `@import` is rejected.

### Contributor Boundaries

- Contributors define **structure** (columns, fields) — immutable
- Contributors suggest **visual defaults** — adjustable by admins
- **Token names are fixed** — contributors cannot invent new CSS properties
- All values pass through the sanitizer — even from trusted contributors

---

## 14. Testing Strategy

### Unit Tests for VisualConfig

```php
// tests/Unit/Package/VisualConfigTest.php
class VisualConfigTest extends TestCase
{
    public function test_token_registry_has_all_scopes(): void;
    public function test_to_inline_style_generates_valid_css(): void;
    public function test_sanitize_blocks_css_injection(): void;
    public function test_sanitize_allows_valid_lengths(): void;
    public function test_sanitize_allows_valid_colors(): void;
    public function test_to_style_block_scopes_by_id(): void;
    public function test_resolve_config_merges_preset_and_overrides(): void;
    public function test_unknown_tokens_are_ignored(): void;
    public function test_json_schema_matches_registry(): void;
}
```

### Unit Tests for VisualPresets

```php
class VisualPresetsTest extends TestCase
{
    public function test_all_preset_tokens_exist_in_registry(): void;
    public function test_preset_names_are_valid(): void;
    public function test_comfortable_returns_empty_overrides(): void;
    public function test_compact_reduces_spacing(): void;
}
```

### Integration Tests for Renderers

```php
class TableRendererVisualTest extends TestCase
{
    public function test_table_emits_scoped_style_block(): void;
    public function test_table_without_visual_config_renders_normally(): void;
    public function test_table_with_compact_preset_has_smaller_values(): void;
}
```

### CSS Tests (Manual/Visual)

- Render a table with each preset and verify visual differences
- Verify fallback behavior: remove all `--v-*` tokens, CSS still works identically to current
- Test multiple components on one page with different visual configs

---

## 15. Migration Path

### Phase 1: Foundation ✅ (Current)
- VisualConfig.php with full token registry
- Token sanitization and CSS output methods

### Phase 2: Presets + CSS Refactor
- Create VisualPresets.php with 3-4 built-in presets
- Refactor package-components.css to use `var(--v-*, fallback)` everywhere
- **Zero visual change** — all `var()` fallbacks match current hardcoded values

### Phase 3: Renderer Integration
- Update TableRenderer, FilterRenderer, DashboardRenderer, FormRenderer
- Each renderer reads `config['visual']` and emits a scoped `<style>` block
- Existing packages without `visual` config render exactly as before

### Phase 4: Component Definitions
- Build ComponentDefinition class
- Add `visual` to ComponentSchema validation
- Update package-schema.json

### Phase 5: Admin UI (Future)
- Admin panel for visual token customization with live preview
- "Theme" system that applies org-wide token overrides
- Preset switcher dropdown in management views

### Backwards Compatibility

**This is a non-breaking change.** Every step maintains backwards compatibility:
- CSS `var(--v-*, fallback)` renders identically when no tokens are set
- Renderers without `visual` config render exactly as before
- Package JSON without `visual` section validates and works normally
- No database migrations required (visual config lives in package JSON)

---

## Appendix A: Quick Reference Card

### "I want to make my search box wider"
```json
{ "visual": { "tokens": { "filter-input-width": "320px" } } }
```

### "I want tighter table rows for dense data"
```json
{ "visual": { "preset": "compact" } }
```

### "I want custom header colors"
```json
{
  "visual": {
    "tokens": {
      "table-header-bg": "#1e3a5f",
      "table-header-color": "#ffffff"
    }
  }
}
```

### "I want rounded badges instead of pills"
```json
{ "visual": { "tokens": { "badge-radius": "6px" } } }
```

### "I want more space between filter fields"
```json
{ "visual": { "tokens": { "filter-gap": "2rem" } } }
```

---

## Appendix B: Glossary

| Term                  | Meaning |
|-----------------------|---------|
| **Token**             | A named CSS custom property (`--v-table-row-height`) with a default, type, constraints, and description |
| **Preset**            | A named set of token overrides (e.g., "compact" = smaller spacing) |
| **Visual Config**     | The `visual` section of a package/component JSON definition |
| **Component Definition** | A portable JSON blueprint describing a table/form/chart's structure and visual config |
| **Scope**             | Token grouping (`table`, `filter`, `card`, `form`, `page`) |
| **Fallback**          | The value inside `var(--v-token, fallback)` — used when no token is set |
| **Sanitization**      | Server-side validation of token values to prevent CSS injection |
