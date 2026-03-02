-- Vehicle Request Seed Data

-- Default vehicles (customize after installation)
INSERT INTO vrf_vehicles (vehicle_name, vehicle_type, capacity, is_available) VALUES
    ('Bus #1', 'bus', 48, 1),
    ('Bus #2', 'bus', 48, 1),
    ('Bus #3', 'bus', 48, 1),
    ('Van #1', 'van', 12, 1),
    ('Van #2', 'van', 12, 1),
    ('Suburban #1', 'suburban', 8, 1)
ON DUPLICATE KEY UPDATE id=id;
