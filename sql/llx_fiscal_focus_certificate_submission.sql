-- Metadata of certificates submitted to Focus NFe. The PFX/P12 file,
-- password, base64 payload and private key are never stored here.

CREATE TABLE llx_fiscal_focus_certificate_submission(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_focus_company integer,
	environment varchar(20) DEFAULT 'homologation' NOT NULL,
	certificate_hash char(64) NOT NULL,
	subject_name varchar(255),
	issuer_name varchar(255),
	serial_number varchar(128),
	cnpj_certificado varchar(14),
	valid_from datetime,
	valid_to datetime,
	status varchar(32) DEFAULT 'submitted' NOT NULL,
	focus_message text,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NOT NULL,
	fk_user_modif integer,
	import_key varchar(14)
) ENGINE=innodb;
