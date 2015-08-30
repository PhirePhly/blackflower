SET @provides_database_version = '1.12.0.0';
SET @requires_code_version = '1.12.0';
SET @upgrade_from_database_version = '1.10.*';


INSERT INTO status_options VALUES ('Staged At Location');    -- Magic string

ALTER TABLE channels ADD COLUMN staging_id INTEGER after incident_id;
ALTER TABLE channels ADD INDEX(staging_id);

CREATE TABLE staging_locations (
   staging_id   int not null auto_increment,
   location     varchar(80),
   created_by   varchar(80),
   time_created   datetime not null,
   time_released  datetime,
   staging_notes   TEXT,

  PRIMARY KEY (staging_id)
);


CREATE TABLE unit_staging_assignments (
   staging_assignment_id        int not null auto_increment ,
   staged_at_location_id        int not null,
   unit_name                    varchar(20),
   time_staged                  datetime not null,
   time_reassigned              datetime,
   
  PRIMARY KEY (staging_assignment_id),
  INDEX(staged_at_location_id),
  INDEX(unit_name)

);

