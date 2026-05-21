-- Incremental synchronization cursors for Focus services.

CREATE TABLE llx_fiscal_sync_cursor(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_focus_company integer,
	environment varchar(20) DEFAULT 'homologation' NOT NULL,
	cnpj varchar(14) NOT NULL,
	service varchar(64) NOT NULL,
	last_version bigint DEFAULT 0 NOT NULL,
	last_total_count integer DEFAULT 0,
	last_sync datetime,
	last_error text,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer
) ENGINE=innodb;
