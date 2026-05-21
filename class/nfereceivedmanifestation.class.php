<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file    class/nfereceivedmanifestation.class.php
 * \ingroup fiscal
 * \brief   Manifestation history for received NF-es.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Received NF-e manifestation event.
 */
class NFeReceivedManifestation extends CommonObject
{
	public $module = 'fiscal';
	public $element = 'nfereceivedmanifestation';
	public $table_element = 'fiscal_nfe_received_manifestation';
	public $picto = 'fa-comment-dots';
	public $ismultientitymanaged = 1;
	public $isextrafieldmanaged = 0;

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 5, 'notnull' => 1, 'visible' => 0, 'default' => '1', 'index' => 1),
		'fk_nfe_received' => array('type' => 'integer:NFeReceived:/fiscal/class/nfereceived.class.php', 'label' => 'NFeReceived', 'enabled' => '1', 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'chave_nfe' => array('type' => 'varchar(44)', 'label' => 'NFeAccessKey', 'enabled' => '1', 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'tipo' => array('type' => 'varchar(32)', 'label' => 'Type', 'enabled' => '1', 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'justificativa' => array('type' => 'varchar(255)', 'label' => 'Reason', 'enabled' => '1', 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'status_focus' => array('type' => 'varchar(64)', 'label' => 'NFeFocusStatus', 'enabled' => '1', 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'status_sefaz' => array('type' => 'varchar(64)', 'label' => 'NFeSefazStatus', 'enabled' => '1', 'position' => 51, 'notnull' => 0, 'visible' => 1),
		'mensagem_sefaz' => array('type' => 'text', 'label' => 'NFeSefazMessage', 'enabled' => '1', 'position' => 52, 'notnull' => 0, 'visible' => 1),
		'protocolo' => array('type' => 'varchar(80)', 'label' => 'NFeProtocol', 'enabled' => '1', 'position' => 53, 'notnull' => 0, 'visible' => 1),
		'data_manifesto' => array('type' => 'datetime', 'label' => 'Date', 'enabled' => '1', 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'request_hash' => array('type' => 'varchar(64)', 'label' => 'Hash', 'enabled' => '1', 'position' => 70, 'notnull' => 0, 'visible' => 0),
		'raw_response' => array('type' => 'text', 'label' => 'Response', 'enabled' => '1', 'position' => 80, 'notnull' => 0, 'visible' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => '1', 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => '1', 'position' => 511, 'notnull' => 0, 'visible' => -2),
	);

	public $rowid;
	public $entity;
	public $fk_nfe_received;
	public $chave_nfe;
	public $tipo;
	public $justificativa;
	public $status_focus;
	public $status_sefaz;
	public $mensagem_sefaz;
	public $protocolo;
	public $data_manifesto;
	public $request_hash;
	public $raw_response;
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
}
