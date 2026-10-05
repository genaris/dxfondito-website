-- The QSL mailer (system design, section 6.5).

-- The EMAIL field of each record of a log (FR-MAIL-2). Null if the record has no valid address.
ALTER TABLE contacts ADD COLUMN email VARCHAR(254) NULL AFTER name;

-- The address book: data that an administrator enters for a base call sign (FR-MAIL-3).
-- The name is for a later version: the QSL cards use only the name of the official lists (D-28).
CREATE TABLE address_book (
    call_sign VARCHAR(20) NOT NULL,
    name VARCHAR(150) NULL,
    email VARCHAR(254) NULL,
    no_mail TINYINT(1) NOT NULL DEFAULT 0,
    notes VARCHAR(500) NULL,
    updated_at DATETIME NOT NULL,
    updated_by INT UNSIGNED NOT NULL,
    PRIMARY KEY (call_sign),
    CONSTRAINT address_book_user FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The addresses that bounced. The mailer does not use them (FR-MAIL-5).
CREATE TABLE invalid_emails (
    email VARCHAR(254) NOT NULL,
    marked_at DATETIME NOT NULL,
    marked_by INT UNSIGNED NOT NULL,
    PRIMARY KEY (email),
    CONSTRAINT invalid_emails_user FOREIGN KEY (marked_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The subject and the body of the messages (FR-MAIL-10). The scope is "default", "activity:<id>" or "season:<year>".
CREATE TABLE mail_templates (
    kind ENUM('qsl', 'certificate') NOT NULL,
    scope VARCHAR(30) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    updated_at DATETIME NOT NULL,
    updated_by INT UNSIGNED NOT NULL,
    PRIMARY KEY (kind, scope),
    CONSTRAINT mail_templates_user FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The record of the messages: each sent or failed message, and each manual mark (FR-MAIL-7, FR-MAIL-14).
-- A QSL message is for a base call sign in an activity. The items are the contacts of its QSL cards.
-- A certificate message is for a base call sign, a season and a level, with the date of the certificate.
CREATE TABLE mail_deliveries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kind ENUM('qsl', 'certificate') NOT NULL,
    base_call_sign VARCHAR(20) NOT NULL,
    activity_id INT UNSIGNED NULL,
    season SMALLINT UNSIGNED NULL,
    points INT UNSIGNED NULL,
    certificate_date DATE NULL,
    method ENUM('email', 'manual') NOT NULL,
    status ENUM('sent', 'failed') NOT NULL,
    recipient VARCHAR(254) NULL,
    subject VARCHAR(255) NULL,
    items JSON NULL,
    error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY mail_deliveries_activity (activity_id, base_call_sign),
    KEY mail_deliveries_certificate (season, base_call_sign, points),
    KEY mail_deliveries_created (created_at),
    CONSTRAINT mail_deliveries_activity FOREIGN KEY (activity_id) REFERENCES activities (id) ON DELETE CASCADE,
    CONSTRAINT mail_deliveries_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
