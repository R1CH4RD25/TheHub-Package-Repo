-- ============================================================================
-- Vehicle Maintenance & Fleet Tracking — Initial Schema
-- Migration: 001_create_fleet_tables.sql
-- Version: 2.1.0
--
-- All tables live in woodson_hub with vm_ prefix for namespace isolation.
-- Tables use CHAR(26) ULID primary keys for portability.
-- Foreign keys to users.id are INT UNSIGNED (native hub FK).
-- ============================================================================

-- 9. DEPARTMENTS (create first — referenced by vehicles)
CREATE TABLE IF NOT EXISTS vm_departments (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    name VARCHAR(255) NOT NULL UNIQUE COMMENT 'Transportation, Maintenance, Athletics, etc.',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. CAMPUSES (create first — referenced by vehicles)
CREATE TABLE IF NOT EXISTS vm_campuses (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    name VARCHAR(255) NOT NULL UNIQUE COMMENT 'Elementary, Middle School, High School, etc.',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1. VEHICLES (Fleet Inventory)
CREATE TABLE IF NOT EXISTS vm_vehicles (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    unit_number VARCHAR(50) NOT NULL UNIQUE COMMENT 'District identifier like BUS-01',
    name VARCHAR(255) NOT NULL COMMENT 'Display name like Elementary Bus #1',
    vin VARCHAR(32) DEFAULT NULL COMMENT 'Vehicle Identification Number',
    license_plate VARCHAR(20) DEFAULT NULL,
    year INT DEFAULT NULL COMMENT 'Model year (1900-2100)',
    make VARCHAR(100) DEFAULT NULL COMMENT 'Ford, Chevy, International, etc.',
    model VARCHAR(100) DEFAULT NULL COMMENT 'Transit, Express, CE, etc.',
    current_odometer INT DEFAULT NULL COMMENT 'Latest odometer reading (updated from logs)',
    department_id CHAR(26) DEFAULT NULL COMMENT 'FK to vm_departments',
    campus_id CHAR(26) DEFAULT NULL COMMENT 'FK to vm_campuses',
    is_out_of_service BOOLEAN DEFAULT FALSE,
    out_of_service_reason TEXT DEFAULT NULL,
    out_of_service_date DATE DEFAULT NULL,
    is_deleted BOOLEAN DEFAULT FALSE COMMENT 'Soft delete',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NOT NULL COMMENT 'FK to users.id',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED DEFAULT NULL COMMENT 'FK to users.id',
    INDEX idx_unit_number (unit_number),
    INDEX idx_department (department_id),
    INDEX idx_campus (campus_id),
    INDEX idx_out_of_service (is_out_of_service),
    INDEX idx_deleted (is_deleted),
    CONSTRAINT fk_vm_vehicles_department FOREIGN KEY (department_id) REFERENCES vm_departments(id) ON DELETE SET NULL,
    CONSTRAINT fk_vm_vehicles_campus FOREIGN KEY (campus_id) REFERENCES vm_campuses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TRIP CATEGORIES (Constants for trip classification — codes 11,23,34,36,41)
CREATE TABLE IF NOT EXISTS vm_trip_categories (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    code VARCHAR(10) NOT NULL UNIQUE COMMENT 'Trip code: 11, 23, 34, 36, 41',
    name VARCHAR(100) NOT NULL COMMENT 'Extracurricular, Student Transport, etc.',
    description TEXT DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. FUEL LOGS (Fuel & Trip Tracking with Layer 2 workflow)
CREATE TABLE IF NOT EXISTS vm_fuel_logs (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    vehicle_id CHAR(26) NOT NULL COMMENT 'FK to vm_vehicles.id',
    trip_category_id CHAR(26) NOT NULL COMMENT 'FK to vm_trip_categories.id',
    event_date DATE NOT NULL,
    odometer INT NOT NULL COMMENT 'Current odometer reading',
    gallons DECIMAL(10,2) DEFAULT NULL COMMENT 'Fuel added in gallons',
    fuel_type ENUM('unleaded', 'diesel', 'propane', 'other') DEFAULT 'unleaded',
    is_purchase BOOLEAN DEFAULT FALSE COMMENT 'TRUE if purchased fuel, FALSE if district tank',
    vendor VARCHAR(255) DEFAULT NULL COMMENT 'Vendor name if purchased',
    location VARCHAR(255) DEFAULT NULL COMMENT 'Purchase location if applicable',
    total_cost DECIMAL(10,2) DEFAULT NULL COMMENT 'Total fuel cost',
    price_per_gallon DECIMAL(10,2) DEFAULT NULL COMMENT 'Price per gallon',
    receipt_path VARCHAR(500) DEFAULT NULL COMMENT 'Path to uploaded receipt',
    notes TEXT DEFAULT NULL,
    logged_by INT UNSIGNED NOT NULL COMMENT 'FK to users.id — who submitted this log',
    workflow_status ENUM('draft','submitted','in_review','needs_correction','resubmitted','approved','rejected') DEFAULT 'draft' COMMENT 'Layer 2 workflow state',
    reviewed_by INT UNSIGNED DEFAULT NULL COMMENT 'FK to users.id — manager who reviewed',
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    review_notes TEXT DEFAULT NULL COMMENT 'Manager notes on approval/rejection',
    correction_reason TEXT DEFAULT NULL COMMENT 'Reason for returning (required by editBoundaries)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vm_vehicles(id) ON DELETE RESTRICT,
    FOREIGN KEY (trip_category_id) REFERENCES vm_trip_categories(id) ON DELETE RESTRICT,
    INDEX idx_vehicle (vehicle_id),
    INDEX idx_trip_category (trip_category_id),
    INDEX idx_event_date (event_date),
    INDEX idx_logged_by (logged_by),
    INDEX idx_is_purchase (is_purchase),
    INDEX idx_workflow_status (workflow_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. MAINTENANCE ITEMS (Master list of maintenance types)
CREATE TABLE IF NOT EXISTS vm_maintenance_items (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    name VARCHAR(255) NOT NULL UNIQUE COMMENT 'Oil Change, Tire Rotation, etc.',
    description TEXT DEFAULT NULL,
    default_mileage_interval INT DEFAULT NULL COMMENT 'Default miles between services',
    default_time_interval_days INT DEFAULT NULL COMMENT 'Default days between services',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. MAINTENANCE EVENTS (Completed maintenance logs with Layer 2 workflow)
CREATE TABLE IF NOT EXISTS vm_maintenance_events (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    vehicle_id CHAR(26) NOT NULL COMMENT 'FK to vm_vehicles.id',
    maintenance_item_id CHAR(26) NOT NULL COMMENT 'FK to vm_maintenance_items.id',
    event_date DATE NOT NULL,
    odometer INT NOT NULL COMMENT 'Odometer at time of service',
    vendor VARCHAR(255) DEFAULT NULL COMMENT 'Main Street Auto, In-House, etc.',
    parts_cost DECIMAL(10,2) DEFAULT 0.00,
    labor_cost DECIMAL(10,2) DEFAULT 0.00,
    total_cost DECIMAL(10,2) NOT NULL COMMENT 'parts_cost + labor_cost',
    invoice_path VARCHAR(500) DEFAULT NULL COMMENT 'Path to uploaded invoice (PDF/image)',
    photo_paths JSON DEFAULT NULL COMMENT 'Array of uploaded photo paths',
    notes TEXT DEFAULT NULL,
    logged_by INT UNSIGNED NOT NULL COMMENT 'FK to users.id — who performed/logged this',
    workflow_status ENUM('draft','submitted','in_review','needs_correction','resubmitted','approved','rejected') DEFAULT 'draft' COMMENT 'Layer 2 workflow state',
    reviewed_by INT UNSIGNED DEFAULT NULL COMMENT 'FK to users.id — manager who reviewed',
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    review_notes TEXT DEFAULT NULL COMMENT 'Manager notes on approval/rejection',
    correction_reason TEXT DEFAULT NULL COMMENT 'Reason for returning (required by editBoundaries)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vm_vehicles(id) ON DELETE RESTRICT,
    FOREIGN KEY (maintenance_item_id) REFERENCES vm_maintenance_items(id) ON DELETE RESTRICT,
    INDEX idx_vehicle (vehicle_id),
    INDEX idx_maintenance_item (maintenance_item_id),
    INDEX idx_event_date (event_date),
    INDEX idx_logged_by (logged_by),
    INDEX idx_workflow_status (workflow_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. MAINTENANCE TEMPLATES (Reusable maintenance plans)
CREATE TABLE IF NOT EXISTS vm_maintenance_templates (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    name VARCHAR(255) NOT NULL UNIQUE COMMENT 'Bus Template, Truck Template, etc.',
    description TEXT DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NOT NULL COMMENT 'FK to users.id',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TEMPLATE ITEMS (Maintenance items within templates)
CREATE TABLE IF NOT EXISTS vm_template_items (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    template_id CHAR(26) NOT NULL COMMENT 'FK to vm_maintenance_templates.id',
    maintenance_item_id CHAR(26) NOT NULL COMMENT 'FK to vm_maintenance_items.id',
    mileage_interval INT DEFAULT NULL COMMENT 'Miles between services (e.g., 5000)',
    time_interval_days INT DEFAULT NULL COMMENT 'Days between services (e.g., 90)',
    is_required BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES vm_maintenance_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (maintenance_item_id) REFERENCES vm_maintenance_items(id) ON DELETE RESTRICT,
    INDEX idx_template (template_id),
    INDEX idx_maintenance_item (maintenance_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. VEHICLE SCHEDULES (Per-vehicle maintenance schedule)
CREATE TABLE IF NOT EXISTS vm_vehicle_schedules (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    vehicle_id CHAR(26) NOT NULL COMMENT 'FK to vm_vehicles.id',
    maintenance_item_id CHAR(26) NOT NULL COMMENT 'FK to vm_maintenance_items.id',
    mileage_interval INT DEFAULT NULL COMMENT 'Miles between services (from template or custom)',
    time_interval_days INT DEFAULT NULL COMMENT 'Days between services (from template or custom)',
    last_service_date DATE DEFAULT NULL COMMENT 'From latest vm_maintenance_events',
    last_service_odometer INT DEFAULT NULL COMMENT 'From latest vm_maintenance_events',
    next_due_date DATE DEFAULT NULL COMMENT 'Auto-calculated based on intervals',
    next_due_odometer INT DEFAULT NULL COMMENT 'Auto-calculated based on intervals',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vm_vehicles(id) ON DELETE RESTRICT,
    FOREIGN KEY (maintenance_item_id) REFERENCES vm_maintenance_items(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_vehicle_item (vehicle_id, maintenance_item_id),
    INDEX idx_vehicle (vehicle_id),
    INDEX idx_maintenance_item (maintenance_item_id),
    INDEX idx_next_due_date (next_due_date),
    INDEX idx_next_due_odometer (next_due_odometer),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. SETTINGS (Package configuration — single row)
CREATE TABLE IF NOT EXISTS vm_settings (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID — single row configuration',
    allow_driver_logging BOOLEAN DEFAULT TRUE COMMENT 'Allow Hub users to log fuel',
    enable_departments BOOLEAN DEFAULT TRUE,
    enable_campuses BOOLEAN DEFAULT TRUE,
    maintenance_lead_time_days INT DEFAULT 30 COMMENT 'Notify N days before maintenance due',
    maintenance_lead_distance_miles INT DEFAULT 500 COMMENT 'Notify N miles before maintenance due',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED DEFAULT NULL COMMENT 'FK to users.id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. AUDIT LOG (Package-level audit trail for Layer 2 workflow)
CREATE TABLE IF NOT EXISTS vm_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL COMMENT 'FUEL_LOG_SUBMITTED, VEHICLE_CREATED, etc.',
    entity_type VARCHAR(100) NOT NULL COMMENT 'fuel_log, maintenance_event, vehicle',
    entity_id CHAR(26) NOT NULL COMMENT 'FK to the entity record',
    user_id INT UNSIGNED NOT NULL COMMENT 'FK to users.id',
    before_data JSON DEFAULT NULL COMMENT 'State before change',
    after_data JSON DEFAULT NULL COMMENT 'State after change',
    notes TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
