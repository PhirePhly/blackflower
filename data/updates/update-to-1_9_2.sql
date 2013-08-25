SET @provides_database_version = '1.9.2.0';
SET @requires_code_version = '1.9.2';
SET @upgrade_from_database_version = '1.9.1.*';


INSERT INTO incident_types VALUES ('COURTESY TRANSPORT');

INSERT INTO unit_assignments VALUES ('MHDC', 'Mental Health Duty Chief', 'icongreen', NULL);
INSERT INTO unit_assignments VALUES ('L2000', 'Legal 2000 On-Call', 'icongreen', NULL);  
INSERT INTO unit_assignments VALUES ('CRC', 'Child Respite Center On-Call', 'icongreen', NULL);

