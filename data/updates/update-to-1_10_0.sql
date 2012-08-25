
INSERT INTO incident_disposition_types VALUES ('Duplicate');

ALTER TABLE incidents ADD COLUMN duplicate_of_incident_id int null;
