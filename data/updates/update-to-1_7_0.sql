SET @provides_database_version = '1.7.0.0';
SET @requires_code_version = '1.7.0';
SET @upgrade_from_database_version = '1.5.3.*';

ALTER TABLE users ADD COLUMN  change_password  BOOL NOT NULL DEFAULT 0;

INSERT INTO deployment_history (schema_load_ts, database_version, requires_code_ver, mysql_user) VALUES (NOW(), @provides_database_version, @requires_code_version, CURRENT_USER());

ALTER TABLE unit_incident_paging MODIFY COLUMN to_pager_id INT;
ALTER TABLE unit_incident_paging ADD COLUMN to_person_id INT NOT NULL;

ALTER TABLE incident_units ADD COLUMN transport_time DATETIME after arrival_time;
ALTER TABLE incident_units ADD COLUMN transportdone_time DATETIME after transport_time;

INSERT INTO incident_types VALUES ('RANGERS');

