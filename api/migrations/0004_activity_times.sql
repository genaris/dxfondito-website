-- The hours of an activity, in UTC (FR-ACT-3b). Each activity indicates its hours: in the parks they depend on
-- the Puesto de Salud. Null for the activities before this migration, until an administrator sets them.
ALTER TABLE activities ADD COLUMN start_time TIME NULL AFTER start_date, ADD COLUMN end_time TIME NULL AFTER end_date;
