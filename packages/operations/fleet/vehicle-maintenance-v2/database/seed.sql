-- Seed Data for Vehicle Maintenance v2
-- Trip Categories (Standard District Codes)

INSERT INTO vm_trip_categories (id, code, name, description, is_active, sort_order, created_at, updated_at) VALUES
('01JGKM1A2B3C4D5E6F7G8H9J0K', '11', 'Extracurricular', 'Athletics, UIL competitions, field trips, extracurricular events', TRUE, 1, NOW(), NOW()),
('01JGKM1A2B3C4D5E6F7G8H9J0L', '23', 'Student Transportation', 'Regular routes, special education transport, student pickups/dropoffs', TRUE, 2, NOW(), NOW()),
('01JGKM1A2B3C4D5E6F7G8H9J0M', '34', 'District Business', 'Administrative meetings, errands, district business', TRUE, 3, NOW(), NOW()),
('01JGKM1A2B3C4D5E6F7G8H9J0N', '36', 'Training & Professional Development', 'Staff training, workshops, professional development', TRUE, 4, NOW(), NOW()),
('01JGKM1A2B3C4D5E6F7G8H9J0P', '41', 'Maintenance & Operations', 'Facility maintenance, vehicle servicing, operational tasks', TRUE, 5, NOW(), NOW());

-- Default Maintenance Items

INSERT INTO vm_maintenance_items (id, name, description, default_mileage_interval, default_time_interval_days, is_active, sort_order, created_at, updated_at) VALUES
('01JGKM2A2B3C4D5E6F7G8H9J0K', 'Oil Change', 'Engine oil and filter replacement', 5000, 90, TRUE, 1, NOW(), NOW()),
('01JGKM2A2B3C4D5E6F7G8H9J0L', 'Tire Rotation', 'Rotate tires for even wear', 7500, 180, TRUE, 2, NOW(), NOW()),
('01JGKM2A2B3C4D5E6F7G8H9J0M', 'Air Filter', 'Engine air filter replacement', 15000, 365, TRUE, 3, NOW(), NOW()),
('01JGKM2A2B3C4D5E6F7G8H9J0N', 'Cabin Filter', 'Cabin air filter replacement', 15000, 365, TRUE, 4, NOW(), NOW()),
('01JGKM2A2B3C4D5E6F7G8H9J0P', 'Brake Inspection', 'Inspect brake pads, rotors, and fluid', 10000, 180, TRUE, 5, NOW(), NOW()),
('01JGKM2A2B3C4D5E6F7G8H9J0Q', 'Coolant Flush', 'Engine coolant system flush and refill', 30000, 730, TRUE, 6, NOW(), NOW()),
('01JGKM2A2B3C4D5E6F7G8H9J0R', 'Transmission Service', 'Transmission fluid and filter service', 30000, 730, TRUE, 7, NOW(), NOW()),
('01JGKM2A2B3C4D5E6F7G8H9J0S', 'Battery Check', 'Battery voltage and terminal inspection', NULL, 180, TRUE, 8, NOW(), NOW()),
('01JGKM2A2B3C4D5E6F7G8H9J0T', 'Annual Inspection', 'State-required annual vehicle inspection', NULL, 365, TRUE, 9, NOW(), NOW()),
('01JGKM2A2B3C4D5E6F7G8H9J0U', 'Alignment', 'Wheel alignment check and adjustment', 20000, NULL, TRUE, 10, NOW(), NOW()),
('01JGKM2A2B3C4D5E6F7G8H9J0V', 'Spark Plugs', 'Spark plug replacement', 30000, NULL, TRUE, 11, NOW(), NOW()),
('01JGKM2A2B3C4D5E6F7G8H9J0W', 'Wiper Blades', 'Windshield wiper blade replacement', NULL, 180, TRUE, 12, NOW(), NOW());

-- Default Settings

INSERT INTO vm_settings (id, allow_driver_logging, enable_departments, enable_campuses, maintenance_lead_time_days, maintenance_lead_distance_miles, updated_at, updated_by) VALUES
('01JGKM3A2B3C4D5E6F7G8H9J0K', TRUE, TRUE, TRUE, 30, 500, NOW(), NULL);
