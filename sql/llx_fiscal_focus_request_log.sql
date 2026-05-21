-- Sanitized Focus NFe request/response audit log.

CREATE TABLE llx_fiscal_focus_request_log(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	environment varchar(20) DEFAULT 'homologation' NOT NULL,
	object_type varchar(64),
	object_id integer,
	method varchar(8) NOT NULL,
	endpoint varchar(255) NOT NULL,
	http_code integer,
	focus_status varchar(64),
	sefaz_status varchar(64),
	request_hash char(64),
	request_summary mediumtext,
	response_summary mediumtext,
	error_message text,
	duration_ms integer,
	date_request datetime NOT NULL,
	fk_user_creat integer
) ENGINE=innodb;
