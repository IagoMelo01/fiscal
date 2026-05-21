<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file    class/focuscertificatesubmission.class.php
 * \ingroup fiscal
 * \brief   Metadata for certificate submissions sent to Focus NFe.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Certificate submission metadata. No certificate file, password, base64
 * content or private key is stored by this object.
 */
class FocusCertificateSubmission extends CommonObject
{
	public $module = 'fiscal';
	public $element = 'focuscertificatesubmission';
	public $table_element = 'fiscal_focus_certificate_submission';
	public $picto = 'lock';
	public $ismultientitymanaged = 1;
	public $isextrafieldmanaged = 0;

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 5, 'notnull' => 1, 'visible' => 0, 'default' => '1', 'index' => 1),
		'fk_focus_company' => array('type' => 'integer:FocusCompany:/fiscal/class/focuscompany.class.php', 'label' => 'FocusCompany', 'enabled' => '1', 'position' => 10, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'environment' => array('type' => 'varchar(20)', 'label' => 'FISCAL_FOCUS_ENVIRONMENT', 'enabled' => '1', 'position' => 20, 'notnull' => 1, 'visible' => 1, 'default' => 'homologation'),
		'certificate_hash' => array('type' => 'varchar(64)', 'label' => 'Hash', 'enabled' => '1', 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'subject_name' => array('type' => 'varchar(255)', 'label' => 'Name', 'enabled' => '1', 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'issuer_name' => array('type' => 'varchar(255)', 'label' => 'Issuer', 'enabled' => '1', 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'serial_number' => array('type' => 'varchar(128)', 'label' => 'SerialNumber', 'enabled' => '1', 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'cnpj_certificado' => array('type' => 'varchar(14)', 'label' => 'CNPJ', 'enabled' => '1', 'position' => 70, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'valid_from' => array('type' => 'datetime', 'label' => 'DateStart', 'enabled' => '1', 'position' => 80, 'notnull' => 0, 'visible' => 1),
		'valid_to' => array('type' => 'datetime', 'label' => 'DateEnd', 'enabled' => '1', 'position' => 90, 'notnull' => 0, 'visible' => 1),
		'status' => array('type' => 'varchar(32)', 'label' => 'Status', 'enabled' => '1', 'position' => 100, 'notnull' => 1, 'visible' => 1, 'default' => 'submitted', 'index' => 1),
		'focus_message' => array('type' => 'text', 'label' => 'Message', 'enabled' => '1', 'position' => 110, 'notnull' => 0, 'visible' => -1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => '1', 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => '1', 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => '1', 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $rowid;
	public $entity;
	public $fk_focus_company;
	public $environment;
	public $certificate_hash;
	public $subject_name;
	public $issuer_name;
	public $serial_number;
	public $cnpj_certificado;
	public $valid_from;
	public $valid_to;
	public $status;
	public $focus_message;
	public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;
	public $import_key;

	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	public function create(User $user, $notrigger = 0)
	{
		global $conf;
		if (empty($this->entity) && is_object($conf)) {
			$this->entity = $conf->entity;
		}
		return $this->createCommon($user, $notrigger);
	}

	public function update(User $user, $notrigger = 0)
	{
		return $this->updateCommon($user, $notrigger);
	}

	public function delete(User $user, $notrigger = 0)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	public function fetch($id, $ref = null, $noextrafields = 0, $nolines = 0)
	{
		return $this->fetchCommon($id, $ref, '', $noextrafields);
	}
}
