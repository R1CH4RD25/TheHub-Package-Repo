-- ============================================================================
-- Vehicle Maintenance & Fleet Tracking — Seed Data
-- Migration: 002_seed_data.sql
-- Version: 2.1.0
--
-- Canonical seed for operations.vehicle-maintenance package.
-- Trip category codes (11,23,34,36,41) are district constants.
-- Maintenance items are the real-world types a school district fleet needs.
-- ============================================================================

-- ═══════════════════════════════════════════════════════════════════════════
-- TRIP CATEGORIES — District Standard Codes
-- These codes match state reporting requirements. Do not repurpose.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO vm_trip_categories (id, code, name, description, sort_order) VALUES
('01JMVEHICLEMAINT0001', '11', 'Extracurricular Activities', 'Athletics, UIL competitions, field trips, extracurricular events', 1),
('01JMVEHICLEMAINT0002', '23', 'Student Transportation', 'Regular bus routes, special education transport, student pickups/dropoffs', 2),
('01JMVEHICLEMAINT0003', '34', 'Business / Administrative', 'Administrative meetings, errands, district business travel', 3),
('01JMVEHICLEMAINT0004', '36', 'Professional Development', 'Staff training, workshops, conferences, professional development travel', 4),
('01JMVEHICLEMAINT0005', '41', 'Maintenance & Operations', 'Facility maintenance runs, vehicle servicing, operational tasks', 5);


-- ═══════════════════════════════════════════════════════════════════════════
-- MAINTENANCE ITEMS — What a maintenance person actually does
--
-- These are the choices shown when a tech taps "Log Maintenance"
-- The list should match real shop work, not generic auto-parts categories.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO vm_maintenance_items (id, name, description, default_mileage_interval, default_time_interval_days, sort_order) VALUES

-- ── Engine / Fluids ──
('01JMVEHICLEMAINT1001', 'Oil Change',              'Engine oil and filter replacement',                    5000,   90,   1),
('01JMVEHICLEMAINT1002', 'Coolant Flush',            'Cooling system flush and refill',                     50000,  730,  2),
('01JMVEHICLEMAINT1003', 'Transmission Service',     'Transmission fluid and filter change',                50000,  730,  3),
('01JMVEHICLEMAINT1004', 'Power Steering Fluid',     'Power steering fluid check and top-off/replace',      NULL,   365,  4),
('01JMVEHICLEMAINT1005', 'Differential Service',     'Differential fluid change (front/rear)',              60000,  NULL, 5),

-- ── Tires / Wheels ──
('01JMVEHICLEMAINT1010', 'Tire Rotation',            'Rotate tires for even wear',                          7500,   180,  10),
('01JMVEHICLEMAINT1011', 'Tire Replacement',         'Replace one or more tires',                           NULL,   NULL, 11),
('01JMVEHICLEMAINT1012', 'Flat Repair',              'Patch or plug a flat tire',                           NULL,   NULL, 12),
('01JMVEHICLEMAINT1013', 'Wheel Alignment',          'Wheel alignment check and adjustment',                20000,  NULL, 13),
('01JMVEHICLEMAINT1014', 'Wheel Bearing Service',    'Wheel bearing repack or replacement',                 60000,  NULL, 14),

-- ── Brakes ──
('01JMVEHICLEMAINT1020', 'Brake Inspection',         'Inspect brake pads, rotors, drums, and fluid level',  25000,  180,  20),
('01JMVEHICLEMAINT1021', 'Brake Pad / Shoe Replacement', 'Replace worn brake pads or shoes',                NULL,   NULL, 21),
('01JMVEHICLEMAINT1022', 'Brake Rotor / Drum Service',   'Resurface or replace rotors/drums',               NULL,   NULL, 22),
('01JMVEHICLEMAINT1023', 'Brake Fluid Flush',        'Brake hydraulic fluid flush and refill',              NULL,   730,  23),

-- ── AC / Heating ──
('01JMVEHICLEMAINT1030', 'A/C Service',              'Refrigerant recharge, compressor, lines',             NULL,   365,  30),
('01JMVEHICLEMAINT1031', 'Heater Core / Hoses',      'Heater core or heater hose repair/replacement',       NULL,   NULL, 31),
('01JMVEHICLEMAINT1032', 'Cabin Air Filter',         'Replace cabin air filter',                            15000,  365,  32),

-- ── Electrical ──
('01JMVEHICLEMAINT1040', 'Battery Replacement',      'Replace vehicle battery',                             NULL,   NULL, 40),
('01JMVEHICLEMAINT1041', 'Battery Test / Service',   'Load test, clean terminals, check cables',            NULL,   180,  41),
('01JMVEHICLEMAINT1042', 'Alternator / Starter',     'Alternator or starter motor repair/replacement',      NULL,   NULL, 42),
('01JMVEHICLEMAINT1043', 'Lighting',                 'Headlights, tail lights, turn signals, markers',      NULL,   NULL, 43),
('01JMVEHICLEMAINT1044', 'Wiring Repair',            'Electrical wiring diagnosis and repair',              NULL,   NULL, 44),

-- ── Filters / Air ──
('01JMVEHICLEMAINT1050', 'Engine Air Filter',        'Replace engine air filter',                           15000,  365,  50),
('01JMVEHICLEMAINT1051', 'Fuel Filter',              'Replace fuel filter',                                 30000,  365,  51),
('01JMVEHICLEMAINT1052', 'DEF / Emissions',          'DEF fluid top-off, DPF regen, emissions system',      NULL,   NULL, 52),

-- ── Belts / Hoses ──
('01JMVEHICLEMAINT1060', 'Serpentine Belt',          'Replace serpentine/drive belt',                        60000,  NULL, 60),
('01JMVEHICLEMAINT1061', 'Radiator Hoses',           'Replace upper/lower radiator hoses',                  NULL,   NULL, 61),
('01JMVEHICLEMAINT1062', 'Timing Belt / Chain',      'Replace timing belt or chain',                        100000, NULL, 62),

-- ── Suspension / Steering ──
('01JMVEHICLEMAINT1070', 'Suspension Inspection',    'Inspect shocks, struts, bushings, ball joints',       30000,  365,  70),
('01JMVEHICLEMAINT1071', 'Shock / Strut Replacement', 'Replace shocks or struts',                           NULL,   NULL, 71),
('01JMVEHICLEMAINT1072', 'Steering Repair',          'Tie rods, steering box/rack, idler arm',              NULL,   NULL, 72),

-- ── Body / Glass / Interior ──
('01JMVEHICLEMAINT1080', 'Windshield Repair / Replace', 'Windshield chip repair or full replacement',       NULL,   NULL, 80),
('01JMVEHICLEMAINT1081', 'Body Repair',              'Dents, scratches, panel damage, paint',               NULL,   NULL, 81),
('01JMVEHICLEMAINT1082', 'Mirror Replacement',       'Side mirror or rear-view mirror replacement',         NULL,   NULL, 82),
('01JMVEHICLEMAINT1083', 'Seat / Interior Repair',   'Seat cover, upholstery, interior trim repair',        NULL,   NULL, 83),

-- ── Exhaust ──
('01JMVEHICLEMAINT1090', 'Exhaust System',           'Muffler, catalytic converter, exhaust pipe repair',   NULL,   NULL, 90),

-- ── Compliance / Inspections ──
('01JMVEHICLEMAINT1100', 'State Inspection',         'Annual TX state safety inspection',                   NULL,   365,  100),
('01JMVEHICLEMAINT1101', 'Registration Renewal',     'License plate and registration renewal',              NULL,   365,  101),
('01JMVEHICLEMAINT1102', 'DOT / Bus Inspection',     'DOT-required school bus comprehensive inspection',    NULL,   365,  102),
('01JMVEHICLEMAINT1103', 'Fire Extinguisher Check',  'Inspect/replace bus fire extinguisher',               NULL,   365,  103),
('01JMVEHICLEMAINT1104', 'First Aid Kit Check',      'Restock bus first aid kit',                           NULL,   365,  104),

-- ── Bus-Specific ──
('01JMVEHICLEMAINT1110', 'Stop Arm / Lights Test',   'Test stop arm extension, red flashers, crossing arm', NULL,   180,  110),
('01JMVEHICLEMAINT1111', 'Emergency Exit Test',      'Test all emergency exits and buzzers',                NULL,   180,  111),
('01JMVEHICLEMAINT1112', 'Two-Way Radio Service',    'Radio repair, antenna, channel programming',          NULL,   NULL, 112),
('01JMVEHICLEMAINT1113', 'Camera System',            'Interior/exterior camera repair, DVR service',        NULL,   NULL, 113),
('01JMVEHICLEMAINT1114', 'Child Safety Seat Check',  'Inspect child restraint systems and mounting',        NULL,   365,  114),

-- ── Fleet Operations ──
('01JMVEHICLEMAINT1120', 'Wiper Blades',             'Windshield wiper blade replacement',                  NULL,   180,  120),
('01JMVEHICLEMAINT1121', 'Tow / Recovery',           'Vehicle tow or roadside recovery',                    NULL,   NULL, 121),
('01JMVEHICLEMAINT1122', 'Detailing / Wash',         'Interior/exterior cleaning and detailing',            NULL,   NULL, 122),
('01JMVEHICLEMAINT1123', 'Fuel System',              'Fuel pump, injectors, fuel line repair',              NULL,   NULL, 123),
('01JMVEHICLEMAINT1124', 'Pre-Trip Inspection',      'Pre-trip safety walkthrough (driver daily)',          NULL,   1,    124),

-- ── Catch-All ──
('01JMVEHICLEMAINT1199', 'Other',                    'Describe work performed in notes',                    NULL,   NULL, 999);


-- ═══════════════════════════════════════════════════════════════════════════
-- DEFAULT SETTINGS — Start minimal, admin toggles on what they need
-- ═══════════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO vm_settings (
    id,
    -- Fuel logging
    track_odometer, track_fuel_type, track_fuel_cost, track_price_per_gallon,
    track_vendor, track_receipts, track_purchase_flag,
    -- Maintenance
    track_maintenance_cost, track_parts_labor_split, track_invoices, track_photos,
    -- Vehicle details
    track_vin, track_license_plate, track_vehicle_color, track_assigned_driver,
    -- Organization
    enable_departments, enable_campuses,
    -- Workflow
    require_approval, allow_driver_logging,
    -- Scheduling
    enable_maintenance_tracking, enable_scheduling,
    maintenance_lead_time_days, maintenance_lead_distance_miles,
    -- Sharing
    share_vehicles
) VALUES (
    '01JMVEHICLESETTINGS',
    -- Fuel: odometer ON, rest off (start simple)
    TRUE, FALSE, FALSE, FALSE,
    FALSE, FALSE, FALSE,
    -- Maintenance: cost off, start simple
    FALSE, FALSE, FALSE, FALSE,
    -- Vehicle: all off, start simple
    FALSE, FALSE, FALSE, FALSE,
    -- Org: departments + campuses on
    TRUE, TRUE,
    -- Workflow: approval on, driver logging on
    TRUE, TRUE,
    -- Scheduling: on
    TRUE, TRUE,
    30, 500,
    -- Sharing: on
    TRUE
);


-- ═══════════════════════════════════════════════════════════════════════════
-- SAMPLE MAINTENANCE TEMPLATE — Standard School Bus Schedule
-- ═══════════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO vm_maintenance_templates (id, name, description, created_by)
VALUES ('01JMVEHICLETEMPL001', 'Standard Bus Schedule', 'Recommended maintenance schedule for school buses — oil, tires, brakes, annual inspection', 1);

INSERT IGNORE INTO vm_template_items (id, template_id, maintenance_item_id, mileage_interval, time_interval_days, sort_order) VALUES
('01JMVEHICLETEMPLI01', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1001', 5000,  90,  1),   -- Oil Change
('01JMVEHICLETEMPLI02', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1010', 7500,  180, 2),   -- Tire Rotation
('01JMVEHICLETEMPLI03', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1050', 15000, 365, 3),   -- Engine Air Filter
('01JMVEHICLETEMPLI04', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1100', NULL,  365, 4),   -- State Inspection
('01JMVEHICLETEMPLI05', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1020', 25000, 180, 5),   -- Brake Inspection
('01JMVEHICLETEMPLI06', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1002', 50000, 730, 6),   -- Coolant Flush
('01JMVEHICLETEMPLI07', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1110', NULL,  180, 7),   -- Stop Arm / Lights Test
('01JMVEHICLETEMPLI08', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1111', NULL,  180, 8),   -- Emergency Exit Test
('01JMVEHICLETEMPLI09', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1102', NULL,  365, 9),   -- DOT / Bus Inspection
('01JMVEHICLETEMPLI10', '01JMVEHICLETEMPL001', '01JMVEHICLEMAINT1103', NULL,  365, 10);  -- Fire Extinguisher Check


-- ═══════════════════════════════════════════════════════════════════════════
-- SAMPLE TEMPLATE #2 — Light Duty Truck/SUV Schedule
-- ═══════════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO vm_maintenance_templates (id, name, description, created_by)
VALUES ('01JMVEHICLETEMPL002', 'Light Duty Truck / SUV', 'Maintenance schedule for district pickups, SUVs, and passenger vehicles', 1);

INSERT IGNORE INTO vm_template_items (id, template_id, maintenance_item_id, mileage_interval, time_interval_days, sort_order) VALUES
('01JMVEHICLETEMPLI11', '01JMVEHICLETEMPL002', '01JMVEHICLEMAINT1001', 5000,  90,  1),   -- Oil Change
('01JMVEHICLETEMPLI12', '01JMVEHICLETEMPL002', '01JMVEHICLEMAINT1010', 7500,  180, 2),   -- Tire Rotation
('01JMVEHICLETEMPLI13', '01JMVEHICLETEMPL002', '01JMVEHICLEMAINT1050', 15000, 365, 3),   -- Engine Air Filter
('01JMVEHICLETEMPLI14', '01JMVEHICLETEMPL002', '01JMVEHICLEMAINT1032', 15000, 365, 4),   -- Cabin Air Filter
('01JMVEHICLETEMPLI15', '01JMVEHICLETEMPL002', '01JMVEHICLEMAINT1100', NULL,  365, 5),   -- State Inspection
('01JMVEHICLETEMPLI16', '01JMVEHICLETEMPL002', '01JMVEHICLEMAINT1020', 30000, 365, 6),   -- Brake Inspection
('01JMVEHICLETEMPLI17', '01JMVEHICLETEMPL002', '01JMVEHICLEMAINT1030', NULL,  365, 7);   -- A/C Service


-- ═══════════════════════════════════════════════════════════════════════════
-- SEED DEPARTMENTS (Typical small-district departments)
-- ═══════════════════════════════════════════════════════════════════════════

INSERT IGNORE INTO vm_departments (id, name) VALUES
('01JMVEHICLEDEPT0001', 'Transportation'),
('01JMVEHICLEDEPT0002', 'Maintenance'),
('01JMVEHICLEDEPT0003', 'Athletics'),
('01JMVEHICLEDEPT0004', 'Administration'),
('01JMVEHICLEDEPT0005', 'Cafeteria');

INSERT IGNORE INTO vm_campuses (id, name) VALUES
('01JMVEHICLECAMP0001', 'High School'),
('01JMVEHICLECAMP0002', 'Middle School'),
('01JMVEHICLECAMP0003', 'Elementary'),
('01JMVEHICLECAMP0004', 'Central Office / Admin'),
('01JMVEHICLECAMP0005', 'Bus Barn');
