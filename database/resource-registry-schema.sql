-- ============================================================================
-- SHARED RESOURCE REGISTRY SCHEMA
-- Platform-owned metadata for cross-package resource sharing.
--
-- Design: Packages declare resources they provide/consume in package.json.
-- At install time, PackageManager resolves dependencies via this registry.
-- Consumers query platform-owned views (not raw tables) for isolation.
-- ============================================================================

-- 1. RESOURCE CONTRACTS (canonical definitions of shareable resource types)
-- One row per resource type. Defines the required schema + semantics.
-- Only platform admins or the owning package can create/update contracts.
CREATE TABLE IF NOT EXISTS hub_resource_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_key VARCHAR(100) NOT NULL UNIQUE COMMENT 'Canonical name: operations.vehicles, operations.locations, etc.',
    display_name VARCHAR(255) NOT NULL COMMENT 'Human-readable: District Vehicles',
    description TEXT COMMENT 'What this resource represents',
    contract_version VARCHAR(20) NOT NULL DEFAULT '1.0.0' COMMENT 'SemVer — breaking changes bump major',
    required_columns JSON NOT NULL COMMENT 'Columns every provider MUST expose: [{name, type, description}]',
    optional_columns JSON DEFAULT NULL COMMENT 'Columns a provider MAY expose: [{name, type, description}]',
    behavior_rules JSON DEFAULT NULL COMMENT 'Semantic rules: soft_delete, immutability, status_enum, etc.',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_resource_key (resource_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. RESOURCE PROVIDERS (which package owns which resource)
-- One active provider per resource_key at a time.
CREATE TABLE IF NOT EXISTS hub_resource_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_key VARCHAR(100) NOT NULL COMMENT 'FK to hub_resource_contracts.resource_key',
    provider_package_id VARCHAR(255) NOT NULL COMMENT 'Package ID that owns this resource',
    table_name VARCHAR(100) NOT NULL COMMENT 'Actual database table: vm_vehicles',
    view_name VARCHAR(100) NOT NULL COMMENT 'Platform view for consumers: hub_vehicles_v1',
    contract_version VARCHAR(20) NOT NULL COMMENT 'Version of the contract this provider implements',
    settings_gate VARCHAR(100) DEFAULT NULL COMMENT 'Settings key that must be TRUE to share',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'FALSE if provider package is uninstalled',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    registered_by INT UNSIGNED DEFAULT NULL COMMENT 'FK to users.id (installer)',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_resource_provider (resource_key),
    INDEX idx_provider_package (provider_package_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. RESOURCE CONSUMERS (which packages depend on which resources)
CREATE TABLE IF NOT EXISTS hub_resource_consumers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_key VARCHAR(100) NOT NULL COMMENT 'FK to hub_resource_contracts.resource_key',
    consumer_package_id VARCHAR(255) NOT NULL COMMENT 'Package ID that consumes this resource',
    capability ENUM('read', 'read-write') DEFAULT 'read' COMMENT 'Access level (currently always read)',
    min_contract_version VARCHAR(20) DEFAULT '1.0.0' COMMENT 'Minimum contract version required',
    is_required BOOLEAN DEFAULT TRUE COMMENT 'If TRUE, install fails without provider',
    usage_description VARCHAR(500) DEFAULT NULL COMMENT 'How this package uses the resource',
    linked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_consumer (resource_key, consumer_package_id),
    INDEX idx_consumer_package (consumer_package_id),
    INDEX idx_resource_key (resource_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED: operations.vehicles contract (the first canonical resource)
-- ============================================================================
INSERT IGNORE INTO hub_resource_contracts (
    resource_key, display_name, description, contract_version,
    required_columns, optional_columns, behavior_rules
) VALUES (
    'operations.vehicles',
    'District Vehicles',
    'Shared vehicle inventory for fleet management, trip planning, purchase orders, and any package that references district vehicles.',
    '1.0.0',
    JSON_ARRAY(
        JSON_OBJECT('name', 'id',              'type', 'CHAR(26)',      'description', 'ULID primary key'),
        JSON_OBJECT('name', 'unit_number',     'type', 'VARCHAR(50)',   'description', 'Unique district identifier (e.g., BUS-01)'),
        JSON_OBJECT('name', 'name',            'type', 'VARCHAR(255)',  'description', 'Display name (e.g., Activity Bus 1)'),
        JSON_OBJECT('name', 'year',            'type', 'INT',           'description', 'Model year'),
        JSON_OBJECT('name', 'make',            'type', 'VARCHAR(100)',  'description', 'Manufacturer'),
        JSON_OBJECT('name', 'model',           'type', 'VARCHAR(100)',  'description', 'Model name'),
        JSON_OBJECT('name', 'is_out_of_service', 'type', 'BOOLEAN',    'description', 'TRUE if vehicle is currently unavailable'),
        JSON_OBJECT('name', 'is_deleted',      'type', 'BOOLEAN',      'description', 'Soft delete flag — consumers must filter'),
        JSON_OBJECT('name', 'created_at',      'type', 'TIMESTAMP',    'description', 'Record creation time')
    ),
    JSON_ARRAY(
        JSON_OBJECT('name', 'color',            'type', 'VARCHAR(50)',   'description', 'Vehicle color'),
        JSON_OBJECT('name', 'current_odometer', 'type', 'INT',          'description', 'Latest odometer reading'),
        JSON_OBJECT('name', 'vin',              'type', 'VARCHAR(32)',   'description', 'Vehicle Identification Number'),
        JSON_OBJECT('name', 'license_plate',    'type', 'VARCHAR(20)',   'description', 'License plate number'),
        JSON_OBJECT('name', 'department_id',    'type', 'CHAR(26)',      'description', 'FK to departments (if package exposes it)'),
        JSON_OBJECT('name', 'campus_id',        'type', 'CHAR(26)',      'description', 'FK to campuses (if package exposes it)')
    ),
    JSON_OBJECT(
        'soft_delete',   'Providers MUST use is_deleted=0 in consumer view filter. Hard deletes are forbidden.',
        'immutable_ids', 'The id column must never be reassigned or recycled.',
        'status_field',  'is_out_of_service indicates availability. Consumers should filter or warn on TRUE.',
        'ownership',     'Only the provider package may INSERT, UPDATE, or DELETE rows. Consumers have read-only access via the platform view.',
        'migrations',    'Only the provider package may run DDL against the source table. Consumers must NOT alter the schema.',
        'extensions',    'Providers may add columns beyond the contract. These are NOT exposed to consumers unless added to the view.'
    )
);
