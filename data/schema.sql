SET @provides_database_version = '1.8.0.0';
SET @requires_code_version = '1.8.0';

-- DROP DATABASE IF EXISTS cad;
-- DROP DATABASE IF EXISTS cadarchives;
-- CREATE DATABASE cad;
-- CREATE DATABASE cadarchives;
-- USE cad;

/* enum (reference) tables */

CREATE TABLE unit_roles (
        role       VARCHAR(20) not null primary key,
        color_name VARCHAR(20),
        color_html VARCHAR(20)
        );

CREATE TABLE status_options (
 	status  varchar(30) not null primary key
 );

CREATE TABLE incident_disposition_types (
 	disposition varchar(80) not null primary key
	);

CREATE TABLE incident_types (
	call_type	varchar(40) not null primary key
	);

        
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

CREATE TABLE message_types (
  message_type varchar(20) not null primary key
  );

/* system control tables */

CREATE TABLE archive_master (
  tskey             VARCHAR(30) NOT NULL,
  ts                DATETIME NOT NULL,
  comment           VARCHAR(80),
  database_version  VARCHAR(20),
  requires_code_ver VARCHAR(20),

  PRIMARY KEY (tskey)
);

CREATE TABLE users (
  id            INTEGER NOT NULL AUTO_INCREMENT,
  username      VARCHAR(20) NOT NULL,
  password      VARCHAR(64) NOT NULL,
  name          VARCHAR(40),
  access_level  INTEGER NOT NULL DEFAULT 1,
  access_acl    VARCHAR(20),
  timeout       INTEGER NOT NULL DEFAULT 300,
  preferences   TEXT,
  change_password  BOOL NOT NULL DEFAULT 0,

  PRIMARY KEY (id),
  INDEX (username)
);
/* data tables */

CREATE TABLE bulletins (
  bulletin_id   INTEGER NOT NULL AUTO_INCREMENT,
  bulletin_subject VARCHAR(160),
  bulletin_text TEXT,
  updated       DATETIME,
  updated_by    INTEGER,
  access_level  INTEGER,
  closed        BOOL NOT NULL DEFAULT 0,

  PRIMARY KEY (bulletin_id),
  INDEX (updated),
  INDEX (access_level),
  INDEX (closed)
);

CREATE TABLE bulletin_views (
  id            INTEGER NOT NULL AUTO_INCREMENT,
  bulletin_id   INTEGER,
  user_id       INTEGER,
  last_read     DATETIME,

  PRIMARY KEY (id),
  INDEX (user_id, bulletin_id),
  INDEX (last_read)
);

CREATE TABLE bulletin_history (
  id            INTEGER NOT NULL AUTO_INCREMENT,
  bulletin_id   INTEGER,
  action        ENUM('Created', 'Edited', 'Closed', 'Reopened'),
  updated       DATETIME,
  updated_by    INTEGER,

  PRIMARY KEY (id),
  INDEX (bulletin_id, updated)
);

CREATE TABLE messages (
	oid	int not null auto_increment primary key,
	ts	datetime not null,
	unit	varchar(20),
	message	varchar(255) not null,
	deleted bool not null default 0,
	creator varchar(20),
  message_type varchar(20),

  INDEX (deleted),
  INDEX (unit)
	);

CREATE TABLE units (
	unit	        VARCHAR(20) NOT NULL PRIMARY KEY,
	status	        VARCHAR(30),
	status_comment  VARCHAR(255),
	update_ts       DATETIME,
	-- role	        SET('Fire', 'Medical', 'Comm', 'MHB', 'Admin', 'Law Enforcement', 'Other'),
	role            VARCHAR(20),
	type	        SET('Unit', 'Individual', 'Generic'),
	personnel       VARCHAR(100),
	assignment      VARCHAR(20),
        personnel_ts	DATETIME,
	location	VARCHAR(255),
	location_ts	DATETIME,
	notes		VARCHAR(255),
	notes_ts	DATETIME,

  INDEX (status, type)
	);


CREATE TABLE unit_incident_paging (
  row_id        INT NOT NULL AUTO_INCREMENT,
  unit          VARCHAR(20) NOT NULL,
  to_pager_id   INT NOT NULL,  -- deprecated as of 1.7 integration with paging 3.0
  to_person_id  INT NOT NULL,

  PRIMARY KEY (row_id),
  INDEX (unit)
  );


CREATE TABLE unit_assignments (
  assignment      VARCHAR(20),
  description     VARCHAR(40),
  display_class   VARCHAR(80),
  display_style   TEXT,

  PRIMARY KEY (assignment)
  );


CREATE TABLE incidents (
	incident_id	int not null auto_increment primary key,
        call_number     varchar(40),
	call_type	varchar(40),
	call_details	varchar(80),
	ts_opened	datetime not null,
	ts_dispatch	datetime,
	ts_arrival	datetime,
	ts_complete	datetime,
	location	varchar(80),
	location_num	varchar(15),
	reporting_pty	varchar(80),
	contact_at	varchar(80),
	disposition	varchar(80),
	visible		bool not null default 0,
	primary_unit	varchar(20),
	completed	bool not null default 0,
	updated datetime not null,

  INDEX (visible, completed),
  INDEX (ts_opened)
	);

CREATE TABLE incident_notes (
	note_id		int not null auto_increment primary key,
	incident_id	int not null,
	ts		datetime not null,
	unit		varchar(20),
	message		varchar(255) not null,
	deleted		bool not null default 0,
	creator varchar(20),

  INDEX (incident_id, deleted)
	);

CREATE TABLE incident_units (
	uid int not null auto_increment primary key,
	incident_id	int not null,
	unit		varchar(20) not null,
	dispatch_time datetime,  /* TODO: not null */
	arrival_time datetime,
        transport_time datetime,
        transportdone_time datetime,
	cleared_time datetime,
	is_primary bool,  /* deprecated 1.8.0: unused */
  is_generic bool, /* deprecated 1.8.0: unused */

  INDEX (incident_id, cleared_time),
  INDEX (dispatch_time)
	);


CREATE TABLE deployment_history (
  idx               INT NOT NULL AUTO_INCREMENT,
  schema_load_ts    DATETIME NOT NULL,
  update_ts         TIMESTAMP,
  database_version  VARCHAR(20) NOT NULL,
  requires_code_ver VARCHAR(20) NOT NULL,
  mysql_user        VARCHAR(255),
  host              VARCHAR(255),  -- supplied from OS
  uid               INT,           -- supplied from OS
  user              VARCHAR(8),    -- MySQL CURRENT_USER() function
  cwd               VARCHAR(255),  -- supplied from OS

  PRIMARY KEY (idx)
);


/* Insert default long-lived values into reference tables *****************/

INSERT INTO incident_disposition_types VALUES ('Completed');
INSERT INTO incident_disposition_types VALUES ('Medical Transported');
INSERT INTO incident_disposition_types VALUES ('Other');
INSERT INTO incident_disposition_types VALUES ('Released AMA');
INSERT INTO incident_disposition_types VALUES ('Transferred to Agency');
INSERT INTO incident_disposition_types VALUES ('Transferred to Rangers');
INSERT INTO incident_disposition_types VALUES ('Treated And Released');
INSERT INTO incident_disposition_types VALUES ('Unable To Locate');
INSERT INTO incident_disposition_types VALUES ('Unfounded');

INSERT INTO incident_types VALUES ('FIRE');
INSERT INTO incident_types VALUES ('LAW ENFORCEMENT');
INSERT INTO incident_types VALUES ('ILLNESS');
INSERT INTO incident_types VALUES ('INJURY');
INSERT INTO incident_types VALUES ('MENTAL HEALTH');
INSERT INTO incident_types VALUES ('PUBLIC ASSIST');
INSERT INTO incident_types VALUES ('TRAFFIC CONTROL');
INSERT INTO incident_types VALUES ('TRAINING');
INSERT INTO incident_types VALUES ('RANGERS');
INSERT INTO incident_types VALUES ('OTHER');

INSERT INTO message_types VALUES ('Swim');
INSERT INTO message_types VALUES ('Run');
INSERT INTO message_types VALUES ('Bike');
INSERT INTO message_types VALUES ('DNF');
INSERT INTO message_types VALUES ('DQ');
INSERT INTO message_types VALUES ('Other');

INSERT INTO status_options VALUES ('Attached To Incident');
INSERT INTO status_options VALUES ('Available On Pager');
INSERT INTO status_options VALUES ('Busy');
INSERT INTO status_options VALUES ('In Service');
INSERT INTO status_options VALUES ('Off Comm');
INSERT INTO status_options VALUES ('Off Duty');
INSERT INTO status_options VALUES ('Out Of Service');
INSERT INTO status_options VALUES ('Off Duty; On Pager');

INSERT INTO unit_roles (role, color_name, color_html) VALUES 
('Medical', 'Blue', 'Blue'),
('Fire', 'Red', 'Red'),
('MHB', 'Green', 'Green'),
('Comm', 'Purple', 'Purple'),
('Admin', 'Orange', 'darkorange'),
('Law Enforcement', 'Brown', 'brown'),
('Other', 'Black', 'Black');

INSERT INTO unit_assignments (assignment, description, display_class, display_style) VALUES
('BC', 'Battalion Chief', 'iconyellow', NULL),
('IC', 'Incident Commander', 'iconwhite', NULL),
('FDC', 'Fire Duty Chief', 'iconred', NULL),
('MDC', 'Medical Duty Chief', 'iconblue', NULL),
('ADC', 'Assistant Medical Duty Chief', 'iconblue', NULL),
('SDC', 'Support Duty Chief', 'icongray', NULL),
('CDC', 'Comm Duty Chief', 'iconpurple', NULL),
('OC', 'On-Call', 'icongray', NULL),
('S', 'Supervisor', 'icongray', NULL),
('FS', 'Field Supervisor', 'icongray', NULL);

INSERT INTO deployment_history (schema_load_ts, database_version, requires_code_ver, mysql_user) VALUES (NOW(), @provides_database_version, @requires_code_version, CURRENT_USER());
