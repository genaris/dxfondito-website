-- Initial schema. See docs/design/system-design.md, section 3.
-- Each statement ends with a semicolon at the end of a line.
-- All statements must operate on MySQL 5.7.

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    call_sign VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NULL,
    role ENUM('operator', 'administrator') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY users_call_sign (call_sign)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE series (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(10) NOT NULL,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY series_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The name is short because REFERENCES is a reserved word in MySQL.
CREATE TABLE refs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    series_id INT UNSIGNED NOT NULL,
    number INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY refs_series_number (series_id, number),
    CONSTRAINT refs_series FOREIGN KEY (series_id) REFERENCES series (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference_id INT UNSIGNED NOT NULL,
    season SMALLINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    description TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY activities_reference_start (reference_id, start_date),
    KEY activities_season (season, reference_id),
    CONSTRAINT activities_reference FOREIGN KEY (reference_id) REFERENCES refs (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    activity_id INT UNSIGNED NOT NULL,
    operator_id INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(100) NOT NULL,
    contact_count INT UNSIGNED NOT NULL,
    uploaded_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY logs_activity (activity_id),
    CONSTRAINT logs_activity FOREIGN KEY (activity_id) REFERENCES activities (id),
    CONSTRAINT logs_operator FOREIGN KEY (operator_id) REFERENCES users (id),
    CONSTRAINT logs_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contacts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    log_id INT UNSIGNED NOT NULL,
    activity_id INT UNSIGNED NOT NULL,
    call_sign VARCHAR(20) NOT NULL,
    base_call_sign VARCHAR(20) NOT NULL,
    name VARCHAR(100) NULL,
    qso_at DATETIME NOT NULL,
    frequency DECIMAL(10, 6) NULL,
    band VARCHAR(10) NULL,
    mode VARCHAR(20) NOT NULL,
    rst_sent VARCHAR(10) NULL,
    rst_rcvd VARCHAR(10) NULL,
    PRIMARY KEY (id),
    KEY contacts_activity_call (activity_id, base_call_sign, qso_at),
    KEY contacts_call (base_call_sign),
    CONSTRAINT contacts_log FOREIGN KEY (log_id) REFERENCES logs (id) ON DELETE CASCADE,
    CONSTRAINT contacts_activity FOREIGN KEY (activity_id) REFERENCES activities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE certificate_levels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    points INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY certificate_levels_points (points)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE qsl_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    activity_id INT UNSIGNED NOT NULL,
    operator_id INT UNSIGNED NOT NULL,
    stored_name VARCHAR(100) NOT NULL,
    fields JSON NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY qsl_templates_activity_operator (activity_id, operator_id),
    CONSTRAINT qsl_templates_activity FOREIGN KEY (activity_id) REFERENCES activities (id),
    CONSTRAINT qsl_templates_operator FOREIGN KEY (operator_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE certificate_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    season SMALLINT UNSIGNED NOT NULL,
    level_id INT UNSIGNED NOT NULL,
    stored_name VARCHAR(100) NOT NULL,
    fields JSON NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY certificate_templates_season_level (season, level_id),
    CONSTRAINT certificate_templates_level FOREIGN KEY (level_id) REFERENCES certificate_levels (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED NULL,
    detail JSON NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY audit_entries_created (created_at),
    CONSTRAINT audit_entries_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO series (code, name) VALUES ('DPS', 'Puestos de Salud'), ('EFE', 'Efemérides');

INSERT INTO certificate_levels (points) VALUES (5), (10), (15);
