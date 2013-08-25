SET @provides_database_version = '1.9.1.0';
SET @requires_code_version = '1.9.1';
SET @upgrade_from_database_version = '1.9.0.*';

INSERT INTO incident_disposition_types VALUES ('Duplicate');

ALTER TABLE incidents ADD COLUMN duplicate_of_incident_id int null;
