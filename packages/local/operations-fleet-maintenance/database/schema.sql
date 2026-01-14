-- Vehicle Maintenance & Fleet Tracking Package Database Schema
-- Version: 1.0.0
-- Category: operations-fleet-maintenance

-- ============================================================================
-- 1. VEHICLES (Fleet Inventory)
-- ============================================================================
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
    INDEX idx_deleted (is_deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. TRIP CATEGORIES (Constants for trip classification)
-- ============================================================================
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

-- ============================================================================
-- 3. FUEL LOGS (Fuel & Trip Tracking)
-- ============================================================================
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
    logged_by INT UNSIGNED NOT NULL COMMENT 'FK to users.id - who submitted this log',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vm_vehicles(id) ON DELETE RESTRICT,
    FOREIGN KEY (trip_category_id) REFERENCES vm_trip_categories(id) ON DELETE RESTRICT,
    INDEX idx_vehicle (vehicle_id),
    INDEX idx_trip_category (trip_category_id),
    INDEX idx_event_date (event_date),
    INDEX idx_logged_by (logged_by),
    INDEX idx_is_purchase (is_purchase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. MAINTENANCE ITEMS (Master list of maintenance types)
-- ============================================================================
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

-- ============================================================================
-- 5. MAINTENANCE EVENTS (Completed maintenance logs)
-- ============================================================================
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
    logged_by INT UNSIGNED NOT NULL COMMENT 'FK to users.id - who performed/logged this',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vm_vehicles(id) ON DELETE RESTRICT,
    FOREIGN KEY (maintenance_item_id) REFERENCES vm_maintenance_items(id) ON DELETE RESTRICT,
    INDEX idx_vehicle (vehicle_id),
    INDEX idx_maintenance_item (maintenance_item_id),
    INDEX idx_event_date (event_date),
    INDEX idx_logged_by (logged_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. MAINTENANCE TEMPLATES (Reusable maintenance plans)
-- ============================================================================
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

-- ============================================================================
-- 7. TEMPLATE ITEMS (Maintenance items within templates)
-- ============================================================================
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

-- ============================================================================
-- 8. VEHICLE SCHEDULES (Per-vehicle maintenance schedule)
-- ============================================================================
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

-- ============================================================================
-- 9. DEPARTMENTS (Optional organizational layer)
-- ============================================================================
CREATE TABLE IF NOT EXISTS vm_departments (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    name VARCHAR(255) NOT NULL UNIQUE COMMENT 'Transportation, Maintenance, Athletics, etc.',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. CAMPUSES (Optional organizational layer)
-- ============================================================================
CREATE TABLE IF NOT EXISTS vm_campuses (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID',
    name VARCHAR(255) NOT NULL UNIQUE COMMENT 'Elementary, Middle School, High School, etc.',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 11. SETTINGS (Package configuration)
-- ============================================================================
CREATE TABLE IF NOT EXISTS vm_settings (
    id CHAR(26) PRIMARY KEY COMMENT 'ULID - single row configuration',
    allow_driver_logging BOOLEAN DEFAULT TRUE COMMENT 'Allow Hub users to log fuel',
    enable_departments BOOLEAN DEFAULT TRUE,
    enable_campuses BOOLEAN DEFAULT TRUE,
    maintenance_lead_time_days INT DEFAULT 30 COMMENT 'Notify N days before maintenance due',
    maintenance_lead_distance_miles INT DEFAULT 500 COMMENT 'Notify N miles before maintenance due',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT UNSIGNED DEFAULT NULL COMMENT 'FK to users.id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- FOREIGN KEY CONSTRAINTS FOR OPTIONAL RELATIONSHIPS
-- ============================================================================
ALTER TABLE vm_vehicles
    ADD CONSTRAINT fk_vm_vehicles_department 
        FOREIGN KEY (department_id) REFERENCES vm_departments(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_vm_vehicles_campus 
        FOREIGN KEY (campus_id) REFERENCES vm_campuses(id) ON DELETE SET NULL;

-- ============================================================================
-- SEED DATA: Default Trip Categories (CONSTANTS)
-- ============================================================================
INSERT IGNORE INTO vm_trip_categories (id, code, name, description, sort_order) VALUES
('01JMVEHICLEMAINT0001', '11', 'Extracurricular', 'Athletics, UIL competitions, field trips', 1),
('01JMVEHICLEMAINT0002', '23', 'Student Transportation', 'Regular bus routes, special education transport', 2),
('01JMVEHICLEMAINT0003', '34', 'District Business', 'Administrative meetings, professional development', 3),
('01JMVEHICLEMAINT0004', '36', 'Training & PD', 'Staff training, professional development travel', 4),
('01JMVEHICLEMAINT0005', '41', 'Maintenance & Operations', 'Vehicle maintenance, facility operations', 5);

-- ============================================================================
-- SEED DATA: Default Maintenance Items
-- ============================================================================
INSERT IGNORE INTO vm_maintenance_items (id, name, description, default_mileage_interval, default_time_interval_days, sort_order) VALUES
('01JMVEHICLEMAINT1001', 'Oil Change', 'Engine oil and filter replacement', 5000, 90, 1),
('01JMVEHICLEMAINT1002', 'Tire Rotation', 'Rotate tires for even wear', 7500, 180, 2),
('01JMVEHICLEMAINT1003', 'Air Filter', 'Replace engine air filter', 15000, 365, 3),
('01JMVEHICLEMAINT1004', 'Annual Inspection', 'State-required annual safety inspection', NULL, 365, 4),
('01JMVEHICLEMAINT1005', 'Brake Inspection', 'Inspect brake pads, rotors, fluid', 25000, 180, 5),
('01JMVEHICLEMAINT1006', 'Transmission Service', 'Transmission fluid and filter service', 50000, 730, 6),
('01JMVEHICLEMAINT1007', 'Coolant Flush', 'Engine coolant system flush and refill', 50000, 730, 7),
('01JMVEHICLEMAINT1008', 'Battery Check', 'Battery load test and terminal cleaning', NULL, 180, 8);

-- ============================================================================
-- SEED DATA: Default Settings (Single Row)
-- ============================================================================
INSERT IGNORE INTO vm_settings (id, allow_driver_logging, enable_departments, enable_campuses, maintenance_lead_time_days, maintenance_lead_distance_miles) 
VALUES ('01JMVEHICLESETTINGS', TRUE, TRUE, TRUE, 30, 500);

-- ============================================================================
-- SEED DATA: Sample Maintenance Template
-- ============================================================================
INSERT IGNORE INTO vm_maintenance_templates (id, name, description, created_by) 
VALUES ('01JMVEHICLETEMPL001', 'Standard Bus Template', 'Recommended maintenance schedule for school buses', 1);

-- Link maintenance items to template
INSERT IGNORE INTO vm_template_items (id, template_id, maintenance_item_id, mileage_interval, time_interval_days, sort_order) VALUES
('01JMVEHICLETEMPLI01', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1001', 5000, 90, 1),
('01JMVEHICLETEMPLI02', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1002', 7500, 180, 2),
('01JMVEHICLETEMPLI03', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1003', 15000, 365, 3),
('01JMVEHICLETEMPLI04', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1004', NULL, 365, 4),
('01JMVEHICLETEMPLI05', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1005', 25000, 180, 5);
