-- Vehicle Request Form Schema
-- Package: operations.vehicle-request v1.0.0

CREATE TABLE IF NOT EXISTS vrf_requests (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_number          VARCHAR(50) NOT NULL,
    requester_id            INT UNSIGNED NOT NULL COMMENT 'Hub user ID',
    requester_email         VARCHAR(320) NOT NULL,
    requester_name          VARCHAR(255) NULL,
    activity_category       ENUM('athletics','academics','professional_development','administrative','other') NOT NULL DEFAULT 'other',
    group_name              VARCHAR(255) NULL COMMENT 'Team, class, or department name',
    activity_purpose        TEXT NOT NULL,
    destination             VARCHAR(255) NOT NULL,
    trip_start_date         DATE NOT NULL,
    estimated_departure     TIME NOT NULL,
    trip_end_date           DATE NOT NULL,
    estimated_return        TIME NOT NULL,
    number_of_students      INT NOT NULL DEFAULT 0,
    grade_levels            VARCHAR(255) NULL,
    number_of_staff         INT NOT NULL DEFAULT 0,
    estimated_miles         DECIMAL(10,2) NULL,
    driver_name             VARCHAR(255) NULL,
    transportation_comments TEXT NULL,
    general_comments        TEXT NULL,
    approval_status         ENUM('pending','approved','denied','cancelled') NOT NULL DEFAULT 'pending',
    approval_date           TIMESTAMP NULL,
    approved_by             INT UNSIGNED NULL,
    approved_by_name        VARCHAR(255) NULL,
    denial_reason           TEXT NULL,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_requester (requester_id),
    INDEX idx_status (approval_status),
    INDEX idx_trip_date (trip_start_date, trip_end_date),
    INDEX idx_request_number (request_number),
    INDEX idx_category (activity_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vrf_vehicles (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_name        VARCHAR(255) NOT NULL,
    vehicle_type        ENUM('bus','van','suburban','truck','car','other') NOT NULL DEFAULT 'bus',
    capacity            INT NOT NULL DEFAULT 0,
    license_plate       VARCHAR(50) NULL,
    is_available        TINYINT(1) NOT NULL DEFAULT 1,
    notes               TEXT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_type (vehicle_type),
    INDEX idx_available (is_available)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vrf_request_vehicles (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id      INT UNSIGNED NOT NULL,
    vehicle_id      INT UNSIGNED NOT NULL,
    assigned_by     INT UNSIGNED NULL,
    assigned_at     TIMESTAMP NULL,
    notes           TEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_request (request_id),
    INDEX idx_vehicle (vehicle_id),
    CONSTRAINT fk_rv_request FOREIGN KEY (request_id) REFERENCES vrf_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_rv_vehicle FOREIGN KEY (vehicle_id) REFERENCES vrf_vehicles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vrf_audit_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type     VARCHAR(50) NOT NULL COMMENT 'request, vehicle, or assignment',
    entity_id       INT UNSIGNED NOT NULL,
    action          VARCHAR(100) NOT NULL,
    actor_id        INT UNSIGNED NULL,
    old_values      JSON NULL,
    new_values      JSON NULL,
    reason          TEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_actor (actor_id),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
