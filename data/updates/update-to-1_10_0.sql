SET @provides_database_version = '1.10.0.0';
SET @requires_code_version = '1.10.0';
SET @upgrade_from_database_version = '1.9.*';


LOCK TABLE incidents WRITE;
ALTER TABLE incidents ADD COLUMN incident_status ENUM('New', 'Open', 'Dispositioned', 'Closed');

UPDATE incidents SET incident_status='New' WHERE visible=0 AND completed=0;
UPDATE incidents SET incident_status='Closed' WHERE completed=1;
UPDATE incidents SET incident_status='Open' WHERE incident_status IS NULL;

ALTER TABLE incidents DROP INDEX (visible,completed);
ALTER TABLE incidents DROP COLUMN 'visible';
ALTER TABLE incidents DROP COLUMN 'completed';
ALTER TABLE incidents ADD INDEX (incident_status);

UNLOCK TABLES;

