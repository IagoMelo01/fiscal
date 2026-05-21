<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file    class/focusrequestlog.class.php
 * \ingroup fiscal
 * \brief   Sanitized Focus NFe request audit log.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Sanitized Focus request log.
 */
class FocusRequestLog extends CommonObject
{
	public $module = 'fiscal';
	public $element = 'focusrequestlog';
	public $table_element = 'fiscal_focus_request_log';
	public $picto = 'technic';
	public $ismultientitymanaged = 1;
	public $isextrafieldmanaged = 0;

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 5, 'notnull' => 1, 'visible' => 0, 'default' => '1', 'index' => 1),
		'environment' => array('type' => 'varchar(20)', 'label' => 'FISCAL_FOCUS_ENVIRONMENT', 'enabled' => '1', 'position' => 10, 'notnull' => 1, 'visible' => 1, 'default' => 'homologation'),
		'object_type' => array('type' => 'varchar(64)', 'label' => 'Type', 'enabled' => '1', 'position' => 20, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'object_id' => array('type' => 'integer', 'label' => 'ObjectID', 'enabled' => '1', 'position' => 21, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'method' => array('type' => 'varchar(8)', 'label' => 'Method', 'enabled' => '1', 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'endpoint' => array('type' => 'varchar(255)', 'label' => 'URL', 'enabled' => '1', 'position' => 31, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'http_code' => array('type' => 'integer', 'label' => 'HTTPCode', 'enabled' => '1', 'position' => 40, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'focus_status' => array('type' => 'varchar(64)', 'label' => 'NFeFocusStatus', 'enabled' => '1', 'position' => 50, 'notnull' => 0, 'visible' => -1),
		'sefaz_status' => array('type' => 'varchar(64)', 'label' => 'NFeSefazStatus', 'enabled' => '1', 'position' => 51, 'notnull' => 0, 'visible' => -1),
		'request_hash' => array('type' => 'varchar(64)', 'label' => 'Hash', 'enabled' => '1', 'position' => 60, 'notnull' => 0, 'visible' => -1),
		'request_summary' => array('type' => 'text', 'label' => 'Request', 'enabled' => '1', 'position' => 61, 'notnull' => 0, 'visible' => -1),
		'response_summary' => array('type' => 'text', 'label' => 'Response', 'enabled' => '1', 'position' => 62, 'notnull' => 0, 'visible' => -1),
		'error_message' => array('type' => 'text', 'label' => 'Error', 'enabled' => '1', 'position' => 70, 'notnull' => 0, 'visible' => 1),
		'duration_ms' => array('type' => 'integer', 'label' => 'Duration', 'enabled' => '1', 'position' => 80, 'notnull' => 0, 'visible' => -1),
		'date_request' => array('type' => 'datetime', 'label' => 'Date', 'enabled' => '1', 'position' => 90, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => '1', 'position' => 510, 'notnull' => 0, 'visible' => -2),
	);

	public $rowid;
	public $entity;
	public $environment;
	public $object_type;
	public $object_id;
	public $method;
	public $endpoint;
	public $http_code;
	public $focus_status;
	public $sefaz_status;
	public $request_hash;
	public $request_summary;
	public $response_summary;
	public $error_message;
	public $duration_ms;
	public $date_request;
	public $fk_user_creat;

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
}
