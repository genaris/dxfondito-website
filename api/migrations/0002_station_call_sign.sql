-- The call sign of the station of the operator, from the STATION_CALLSIGN field of the log, such as LU2AOG/A.
-- An operator can use a different suffix in each activity. Null if the log has no STATION_CALLSIGN field.
ALTER TABLE contacts ADD COLUMN station_call_sign VARCHAR(20) NULL AFTER base_call_sign;
