DROP DATABASE IF EXISTS cad;
DROP DATABASE IF EXISTS cadarchives;
CREATE DATABASE cad;
CREATE DATABASE cadarchives;
USE cad;

/* enum (reference) tables */

CREATE TABLE unitcolors (
	role	set('Fire', 'Medical', 'Comm', 'MHB', 'Admin', 'Other'),
	color_name varchar(20),
	color_html varchar(20)
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

CREATE TABLE message_types (
  message_type varchar(20) not null primary key
  );

/* system control tables */

CREATE TABLE archive_master (
	tskey	varchar(30) not null primary key,
	ts	datetime not null,
	comment varchar(80)
	);

CREATE TABLE users (
  id            int not null auto_increment,
  username      varchar(20) not null,
  password      varchar(64) not null,
  name          varchar(40),
  access_level  int not null default 1,
  access_acl    varchar(20),
  timeout       int not null default 300,
  PRIMARY KEY (id)
);
/* data tables */

CREATE TABLE messages (
	oid	int not null auto_increment primary key,
	ts	datetime not null,
	unit	varchar(20),
	message	varchar(255) not null,
	deleted bool not null default 0,
	creator varchar(20),
  message_type varchar(20)
	);

CREATE TABLE units (
	unit	varchar(20) not null primary key,
	status	varchar(30),
	status_comment varchar(255),
	update_ts datetime,
	role	set('Fire', 'Medical', 'Comm', 'MHB', 'Admin', 'Law Enforcement', 'Other'),
	type	set('Unit', 'Individual', 'Generic'),
	personnel varchar(100)
	);

CREATE TABLE incidents (
	incident_id	int not null auto_increment primary key,
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
	updated datetime not null
	);

CREATE TABLE incident_notes (
	note_id		int not null auto_increment primary key,
	incident_id	int not null,
	ts		datetime not null,
	unit		varchar(20),
	message		varchar(255) not null,
	deleted		bool not null default 0,
	creator varchar(20)
	);

CREATE TABLE incident_units (
	uid int not null auto_increment primary key,
	incident_id	int not null,
	unit		varchar(20) not null,
	dispatch_time datetime,  /* TODO: not null */
	arrival_time datetime,
	cleared_time datetime,
	is_primary bool,  /* may be unused */
	is_generic bool
	);

/* Insert default long-lived values into reference tables *****************/

INSERT INTO incident_disposition_types VALUES ('Completed');
INSERT INTO incident_disposition_types VALUES ('Medical Transported');
INSERT INTO incident_disposition_types VALUES ('Other');
INSERT INTO incident_disposition_types VALUES ('Released AMA');
INSERT INTO incident_disposition_types VALUES ('Transferred to Agency');
INSERT INTO incident_disposition_types VALUES ('Treated And Released');
INSERT INTO incident_disposition_types VALUES ('Unable To Locate');
INSERT INTO incident_disposition_types VALUES ('Unfounded');

INSERT INTO incident_types VALUES ('FIRE');
INSERT INTO incident_types VALUES ('LAW ENFORCEMENT');
INSERT INTO incident_types VALUES ('ILLNESS');
INSERT INTO incident_types VALUES ('INJURY');
INSERT INTO incident_types VALUES ('MENTAL HEALTH');
INSERT INTO incident_types VALUES ('TRAFFIC CONTROL');
INSERT INTO incident_types VALUES ('TRAINING');
INSERT INTO incident_types VALUES ('OTHER');

INSERT INTO message_types VALUES ('Swim');
INSERT INTO message_types VALUES ('Run');
INSERT INTO message_types VALUES ('Bike');
INSERT INTO message_types VALUES ('DNF');
INSERT INTO message_types VALUES ('DQ');
INSERT INTO message_types VALUES ('Other');

INSERT INTO status_options VALUES ('Attached to Incident');
INSERT INTO status_options VALUES ('Available on Pager');
INSERT INTO status_options VALUES ('Busy');
INSERT INTO status_options VALUES ('In Service');
INSERT INTO status_options VALUES ('Off Comm');
INSERT INTO status_options VALUES ('Off Duty');
INSERT INTO status_options VALUES ('Off Playa');
INSERT INTO status_options VALUES ('Out of Service');

INSERT INTO unitcolors VALUES ('Admin', 'Orange', 'darkorange');
INSERT INTO unitcolors VALUES ('Comm', 'Purple', 'Purple');
INSERT INTO unitcolors VALUES ('Fire', 'Red', 'Red');
INSERT INTO unitcolors VALUES ('LE', 'Gold', 'Gold');
INSERT INTO unitcolors VALUES ('Medical', 'Blue', 'Blue');
INSERT INTO unitcolors VALUES ('MHB', 'Green', 'Green');
INSERT INTO unitcolors VALUES ('Other', 'Black', 'Black');

insert into users (username, password, name, access_level) values ('Administrator', PASSWORD('admin'), 'Administrator Role Account', 15);
