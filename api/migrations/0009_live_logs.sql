-- The live logs: the contacts that the operators enter on the live page during an activity (FR-LIVE-1 to FR-LIVE-15).

-- A live log has no file. Its ADIF file comes from its contacts. `live_key` is "<activity>:<operator>" for a live log
-- and null for an uploaded log. Thus each operator has one live log for each activity: MySQL 5.7 has no partial indexes.
-- `skipped_count` is the number of records of an uploaded file that were already in the live log of the operator.
ALTER TABLE logs
    ADD COLUMN source ENUM('adif', 'live') NOT NULL DEFAULT 'adif' AFTER uploaded_by,
    ADD COLUMN live_key VARCHAR(30) NULL AFTER source,
    MODIFY stored_name VARCHAR(100) NULL,
    ADD COLUMN skipped_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER contact_count,
    ADD UNIQUE KEY logs_live_key (live_key);

-- The data of a live contact. `client_id` is the identifier that the browser makes for the contact.
-- A second sending of the same contact changes the same line. `version` goes up with each correction.
ALTER TABLE contacts
    ADD COLUMN client_id CHAR(36) NULL AFTER log_id,
    ADD COLUMN notes VARCHAR(500) NULL AFTER rst_rcvd,
    ADD COLUMN entered_by INT UNSIGNED NULL AFTER notes,
    ADD COLUMN updated_at DATETIME NULL AFTER entered_by,
    ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1 AFTER updated_at,
    ADD UNIQUE KEY contacts_client (client_id),
    ADD CONSTRAINT contacts_entered_by FOREIGN KEY (entered_by) REFERENCES users (id);

-- The live contacts that a user deleted. The other browsers of the activity get the deletion with their next request.
CREATE TABLE live_deletions (
    client_id CHAR(36) NOT NULL,
    activity_id INT UNSIGNED NOT NULL,
    deleted_at DATETIME NOT NULL,
    PRIMARY KEY (client_id),
    KEY live_deletions_activity (activity_id, deleted_at),
    CONSTRAINT live_deletions_activity FOREIGN KEY (activity_id) REFERENCES activities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
