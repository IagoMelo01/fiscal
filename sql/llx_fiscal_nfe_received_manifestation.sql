-- Manifestation history for received NF-es.

CREATE TABLE llx_fiscal_nfe_received_manifestation(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_nfe_received integer NOT NULL,
	chave_nfe varchar(44) NOT NULL,
	tipo varchar(32) NOT NULL,
	justificativa varchar(255),
	status_focus varchar(64),
	status_sefaz varchar(64),
	mensagem_sefaz text,
	protocolo varchar(80),
	data_manifesto datetime,
	request_hash varchar(64),
	raw_response mediumtext,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer,
	fk_user_modif integer
) ENGINE=innodb;
