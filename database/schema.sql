-- The Hub - Core Database Schema
-- Run this file to create all necessary tables

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    google_id VARCHAR(255) UNIQUE NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(100) UNIQUE NULL,
    password VARCHAR(255) NULL,
    name VARCHAR(255) NOT NULL,
    picture VARCHAR(500) NULL,
    role ENUM('staff', 'manager', 'admin', 'super_admin') DEFAULT 'staff',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    invited_by INT NULL,
    invited_at DATETIME NULL,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    INDEX idx_email (email),
    INDEX idx_google_id (google_id),
    INDEX idx_username (username),
    INDEX idx_role (role),
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vehicles table
CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    vehicle_number VARCHAR(50) UNIQUE,
    make VARCHAR(100),
    model VARCHAR(100),
    year YEAR,
    vin VARCHAR(17),
    license_plate VARCHAR(20),
    fuel_type ENUM('gasoline', 'diesel', 'propane', 'electric', 'other'),
    initial_mileage INT,
    purchase_date DATE,
    tank_capacity DECIMAL(6,2) COMMENT 'Tank capacity in gallons',
    inspection_due_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    INDEX idx_vehicle_number (vehicle_number),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trip purposes table
CREATE TABLE IF NOT EXISTS trip_purposes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    description VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fuel records table
CREATE TABLE IF NOT EXISTS fuel_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    user_id INT NOT NULL,
    trip_purpose_id INT NOT NULL,
    mileage INT NOT NULL,
    fuel_amount DECIMAL(10, 2) NOT NULL,
    entry_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    INDEX idx_vehicle_id (vehicle_id),
    INDEX idx_user_id (user_id),
    INDEX idx_trip_purpose_id (trip_purpose_id),
    INDEX idx_entry_date (entry_date),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (trip_purpose_id) REFERENCES trip_purposes(id) ON DELETE RESTRICT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invitations table for user email invitations
CREATE TABLE IF NOT EXISTS invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    invited_by INT NOT NULL,
    role ENUM('staff', 'manager', 'admin', 'super_admin') DEFAULT 'staff',
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Global roles table for multiple platform-wide roles per user
CREATE TABLE IF NOT EXISTS user_global_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role ENUM('staff', 'maintenance', 'maintenance_director', 'manager', 'admin', 'super_admin') NOT NULL,
    granted_by INT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_role (user_id, role),
    INDEX idx_user_id (user_id),
    INDEX idx_role (role),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log table for tracking changes
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default trip purposes
INSERT INTO trip_purposes (code, description, sort_order) VALUES
('11', 'Field Trips', 1),
('23', 'Principal', 2),
('34', 'Regular Transportation', 3),
('36', 'Extra-Curricular', 4),
('41', 'Superintendent', 5)
ON DUPLICATE KEY UPDATE description=VALUES(description), sort_order=VALUES(sort_order);

-- Create view for reporting
CREATE OR REPLACE VIEW fuel_records_view AS
SELECT 
    fr.id,
    fr.entry_date,
    v.name AS vehicle_name,
    v.vehicle_number,
    u.name AS user_name,
    u.email AS user_email,
    tp.code AS purpose_code,
    tp.description AS purpose_description,
    fr.mileage,
    fr.fuel_amount,
    fr.notes,
    fr.created_at,
    fr.updated_at,
    u2.name AS updated_by_name
FROM fuel_records fr
JOIN vehicles v ON fr.vehicle_id = v.id
JOIN users u ON fr.user_id = u.id
JOIN trip_purposes tp ON fr.trip_purpose_id = tp.id
LEFT JOIN users u2 ON fr.updated_by = u2.id
ORDER BY fr.entry_date DESC, fr.created_at DESC;
