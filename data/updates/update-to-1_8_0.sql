SET @provides_database_version = '1.8.0.0';
SET @requires_code_version = '1.8.0';
SET @upgrade_from_database_version = '1.7.0.*';

CREATE TABLE incident_locks (
  lock_id            INTEGER NOT NULL AUTO_INCREMENT, 
  incident_id        INTEGER NOT NULL,
  user_id            INTEGER NOT NULL,
  timestamp          DATETIME NOT NULL,
  ipaddr             VARCHAR(80) NOT NULL,
  takeover_by_userid INTEGER,
  takeover_timestamp DATETIME,
  takeover_ipaddr    VARCHAR(80),
  session_id         VARCHAR(128),

  PRIMARY KEY        (lock_id),
  INDEX              (incident_id)
);


CREATE TABLE unit_roles (
        role       VARCHAR(20) not null primary key,
        color_name VARCHAR(20),
        color_html VARCHAR(20)
        );


INSERT INTO unit_roles (role, color_name, color_html) VALUES 
('Medical', 'Blue', 'Blue'),
('Fire', 'Red', 'Red'),
('MHB', 'Green', 'Green'),
('Comm', 'Purple', 'Purple'),
('Admin', 'Orange', 'darkorange'),
('Law Enforcement', 'Brown', 'brown'),
('Other', 'Black', 'Black');

DROP TABLE unitcolors;

ALTER TABLE units CHANGE role role VARCHAR(20);
