SET @provides_database_version = '1.5.3.0';
SET @requires_code_version = '1.5.3';
SET @upgrade_from_database_version = '1.5.2.*';

CREATE TABLE deployment_history (
  idx               INT NOT NULL AUTO_INCREMENT,
  schema_load_ts    DATETIME NOT NULL,
  update_ts         TIMESTAMP,  
  database_version  VARCHAR(20) NOT NULL,
  requires_code_ver VARCHAR(20) NOT NULL,
  mysql_user        VARCHAR(255), 
  host              VARCHAR(255), 
  uid               INT, 
  user              VARCHAR(255), 
  cwd               VARCHAR(255), 

  PRIMARY KEY (idx)
);

ALTER TABLE archive_master ADD COLUMN database_version VARCHAR(20);  
ALTER TABLE archive_master ADD COLUMN requires_code_ver VARCHAR(20);

INSERT INTO deployment_history (schema_load_ts, database_version, requires_code_ver, mysql_user) VALUES (NOW(), @provides_database_version, @requires_code_version, CURRENT_USER());
