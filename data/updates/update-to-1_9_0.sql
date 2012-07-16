SET @provides_database_version = '1.9.0.0';
SET @requires_code_version = '1.9.0';
SET @upgrade_from_database_version = '1.8.0.*';


CREATE TABLE channels (
        channel_id      INTEGER NOT NULL AUTO_INCREMENT,
        channel_name    VARCHAR(40) NOT NULL,
        repeater        BOOL NOT NULL DEFAULT 0,
        available       BOOL NOT NULL DEFAULT 1,
        precedence      INTEGER NOT NULL DEFAULT 50,
        incident_id     INTEGER,
        notes           VARCHAR(160),

        PRIMARY KEY     (channel_id),
        INDEX           (precedence,channel_name),
        INDEX           (incident_id)
        );

INSERT INTO channels (channel_name, repeater, available, precedence) VALUES 
('Tac 11', 0, 1, 10),
('Tac 12', 1, 1, 10),
('Tac 13', 0, 1, 10),
('Fire Ground 1', 0, 1, 20),
('Fire Ground 2', 0, 1, 20),
('911', 1, 0, 97),
('Operations', 1, 0, 98),
('Admin', 1, 0, 99);


ALTER TABLE users ADD COLUMN locked_out            BOOL NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN failed_login_count    INT NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN last_login_time       DATETIME;


