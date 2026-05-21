<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file    class/focussynccursor.class.php
 * \ingroup fiscal
 * \brief   Incremental cursor for Focus NFe synchronization.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Synchronization cursor per entity, environment, CNPJ and service.
 */
class FocusSyncCursor extends CommonObject
{
	public $module = 'fiscal';
	public $element = 'focussynccursor';
	public $table_element = 'fiscal_sync_cursor';
	public $picto = 'technic';
	public $ismultientitymanaged = 1;
	public $isextrafieldmanaged = 0;

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 5, 'notnull' => 1, 'visible' => 0, 'default' => '1', 'index' => 1),
		'fk_focus_company' => array('type' => 'integer:FocusCompany:/fiscal/class/focuscompany.class.php', 'label' => 'FocusCompany', 'enabled' => '1', 'position' => 10, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'environment' => array('type' => 'varchar(20)', 'label' => 'FISCAL_FOCUS_ENVIRONMENT', 'enabled' => '1', 'position' => 20, 'notnull' => 1, 'visible' => 1, 'default' => 'homologation'),
		'cnpj' => array('type' => 'varchar(14)', 'label' => 'CNPJ', 'enabled' => '1', 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'service' => array('type' => 'varchar(64)', 'label' => 'Service', 'enabled' => '1', 'position' => 40, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'last_version' => array('type' => 'integer', 'label' => 'Version', 'enabled' => '1', 'position' => 50, 'notnull' => 1, 'visible' => 1, 'default' => '0'),
		'last_total_count' => array('type' => 'integer', 'label' => 'Total', 'enabled' => '1', 'position' => 60, 'notnull' => 0, 'visible' => -1, 'default' => '0'),
		'last_sync' => array('type' => 'datetime', 'label' => 'LastSync', 'enabled' => '1', 'position' => 70, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'last_error' => array('type' => 'text', 'label' => 'Error', 'enabled' => '1', 'position' => 80, 'notnull' => 0, 'visible' => -1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => '1', 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => '1', 'position' => 511, 'notnull' => 0, 'visible' => -2),
	);

	public $rowid;
	public $entity;
	public $fk_focus_company;
	public $environment;
	public $cnpj;
	public $service;
	public $last_version;
	public $last_total_count;
	public $last_sync;
	public $last_error;
	public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;

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

	public function fetch($id, $ref = null, $noextrafields = 0, $nolines = 0)
	{
		return $this->fetchCommon($id, $ref, '', $noextrafields);
	}

	/**
	 * Fetch cursor by natural key.
	 *
	 * @param string $environment Focus environment
	 * @param string $cnpj CNPJ
	 * @param string $service Service key
	 * @return int<-1,1>
	 */
	public function fetchByKey($environment, $cnpj, $service)
	{
		$cnpj = preg_replace('/\D/', '', $cnpj);
		$morewhere = " AND t.environment = '".$this->db->escape($environment)."'";
		$morewhere .= " AND t.cnpj = '".$this->db->escape($cnpj)."'";
		$morewhere .= " AND t.service = '".$this->db->escape($service)."'";
		return $this->fetchCommon(0, '', $morewhere);
	}
}
