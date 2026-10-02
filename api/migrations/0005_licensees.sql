-- The licensees of the official registries of Argentina (ENACOM) and Uruguay (URSEC), for the name on the
-- QSL cards (FR-QSL-3a). Only the call sign and the name: the registries also have other data that the system
-- does not need.
CREATE TABLE licensees (
    call_sign VARCHAR(20) NOT NULL,
    country CHAR(2) NOT NULL,
    name VARCHAR(150) NOT NULL,
    PRIMARY KEY (call_sign),
    KEY licensees_country (country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The last update of each registry.
CREATE TABLE licensee_updates (
    country CHAR(2) NOT NULL,
    licensee_count INT UNSIGNED NOT NULL,
    source_url VARCHAR(500) NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
