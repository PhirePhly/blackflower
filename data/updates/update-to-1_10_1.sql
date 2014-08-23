SET @provides_database_version = '1.10.1.0';
SET @requires_code_version = '1.10.1';
SET @upgrade_from_database_version = '1.10.0.*';

CREATE TABLE unit_filter_sets (
  idx               INT NOT NULL AUTO_INCREMENT,
  filter_set_name   VARCHAR(80) NOT NULL,
  row_description   VARCHAR(80) NOT NULL,
  row_regexp        VARCHAR(255) NOT NULL,

  PRIMARY KEY (idx),
  INDEX (filter_set_name)
);

