-- ============================================================================
-- Vehicle Maintenance & Fleet Tracking — Seed Data
-- Migration: 002_seed_data.sql
-- Version: 2.1.0
--
-- Trip category codes (11,23,34,36,41) are treated as constants.
-- Do not repurpose or reorder without admin sign-off.
-- ============================================================================

-- Trip Categories (CONSTANTS)
INSERT IGNORE INTO vm_trip_categories (id, code, name, description, sort_order) VALUES
('01JMVEHICLEMAINT0001', '11', 'Extracurricular', 'Athletics, UIL competitions, field trips', 1),
('01JMVEHICLEMAINT0002', '23', 'Student Transportation', 'Regular bus routes, special education transport', 2),
('01JMVEHICLEMAINT0003', '34', 'District Business', 'Administrative meetings, professional development', 3),
('01JMVEHICLEMAINT0004', '36', 'Training & PD', 'Staff training, professional development travel', 4),
('01JMVEHICLEMAINT0005', '41', 'Maintenance & Operations', 'Vehicle maintenance, facility operations', 5);

-- Default Maintenance Items
INSERT IGNORE INTO vm_maintenance_items (id, name, description, default_mileage_interval, default_time_interval_days, sort_order) VALUES
('01JMVEHICLEMAINT1001', 'Oil Change', 'Engine oil and filter replacement', 5000, 90, 1),
('01JMVEHICLEMAINT1002', 'Tire Rotation', 'Rotate tires for even wear', 7500, 180, 2),
('01JMVEHICLEMAINT1003', 'Air Filter', 'Replace engine air filter', 15000, 365, 3),
('01JMVEHICLEMAINT1004', 'Annual Inspection', 'State-required annual safety inspection', NULL, 365, 4),
('01JMVEHICLEMAINT1005', 'Brake Inspection', 'Inspect brake pads, rotors, fluid', 25000, 180, 5),
('01JMVEHICLEMAINT1006', 'Transmission Service', 'Transmission fluid and filter service', 50000, 730, 6),
('01JMVEHICLEMAINT1007', 'Coolant Flush', 'Engine coolant system flush and refill', 50000, 730, 7),
('01JMVEHICLEMAINT1008', 'Battery Check', 'Battery load test and terminal cleaning', NULL, 180, 8);

-- Default Settings (single row — all optional fields OFF by default for simplicity)
-- Admin can turn on what they need: cost tracking, odometer, receipts, etc.
INSERT IGNORE INTO vm_settings (
    id,
    track_odometer, track_fuel_type, track_fuel_cost, track_price_per_gallon,
    track_vendor, track_receipts, track_purchase_flag,
    track_maintenance_cost, track_parts_labor_split, track_invoices, track_photos,
    track_vin, track_license_plate, track_vehicle_color, track_assigned_driver,
    enable_departments, enable_campuses,
    require_approval, allow_driver_logging,
    enable_maintenance_tracking, enable_scheduling,
    maintenance_lead_time_days, maintenance_lead_distance_miles,
    share_vehicles
) VALUES (
    '01JMVEHICLESETTINGS',
    TRUE, FALSE, FALSE, FALSE,
    FALSE, FALSE, FALSE,
    FALSE, FALSE, FALSE, FALSE,
    FALSE, FALSE, FALSE, FALSE,
    TRUE, TRUE,
    TRUE, TRUE,
    TRUE, TRUE,
    30, 500,
    TRUE
);

-- Sample Bus Maintenance Template
INSERT IGNORE INTO vm_maintenance_templates (id, name, description, created_by)
VALUES ('01JMVEHICLETEMPL001', 'Standard Bus Template', 'Recommended maintenance schedule for school buses', 1);

INSERT IGNORE INTO vm_template_items (id, template_id, maintenance_item_id, mileage_interval, time_interval_days, sort_order) VALUES
('01JMVEHICLETEMPLI01', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1001', 5000, 90, 1),
('01JMVEHICLETEMPLI02', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1002', 7500, 180, 2),
('01JMVEHICLETEMPLI03', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1003', 15000, 365, 3),
('01JMVEHICLETEMPLI04', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1004', NULL, 365, 4),
('01JMVEHICLETEMPLI05', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1005', 25000, 180, 5);
