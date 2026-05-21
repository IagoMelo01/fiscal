<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file    class/focuscompany.class.php
 * \ingroup fiscal
 * \brief   Focus NFe company registration domain object.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Focus company registered for an entity/environment.
 */
class FocusCompany extends CommonObject
{
	public $module = 'fiscal';
	public $element = 'focuscompany';
	public $table_element = 'fiscal_focus_company';
	public $picto = 'company';
	public $ismultientitymanaged = 1;
	public $isextrafieldmanaged = 0;

	const STATUS_DRAFT = 'draft';
	const STATUS_DRY_RUN_OK = 'dry_run_ok';
	const STATUS_ACTIVE = 'active';
	const STATUS_ERROR = 'error';

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 5, 'notnull' => 1, 'visible' => 0, 'default' => '1', 'index' => 1),
		'environment' => array('type' => 'varchar(20)', 'label' => 'FISCAL_FOCUS_ENVIRONMENT', 'enabled' => '1', 'position' => 10, 'notnull' => 1, 'visible' => 1, 'default' => 'homologation', 'index' => 1),
		'focus_id' => array('type' => 'varchar(64)', 'label' => 'FocusCompany', 'enabled' => '1', 'position' => 20, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'cnpj' => array('type' => 'varchar(14)', 'label' => 'CNPJ', 'enabled' => '1', 'position' => 30, 'notnull' => 0, 'visible' => 1, 'searchall' => 1, 'index' => 1),
		'cpf' => array('type' => 'varchar(11)', 'label' => 'CPF', 'enabled' => '1', 'position' => 31, 'notnull' => 0, 'visible' => -1, 'index' => 1),
		'name' => array('type' => 'varchar(255)', 'label' => 'Name', 'enabled' => '1', 'position' => 40, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'nome_fantasia' => array('type' => 'varchar(255)', 'label' => 'AliasName', 'enabled' => '1', 'position' => 41, 'notnull' => 0, 'visible' => -1),
		'inscricao_estadual' => array('type' => 'varchar(32)', 'label' => 'StateTaxID', 'enabled' => '1', 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'inscricao_municipal' => array('type' => 'varchar(32)', 'label' => 'ProfId5BR', 'enabled' => '1', 'position' => 51, 'notnull' => 0, 'visible' => -1),
		'regime_tributario' => array('type' => 'integer', 'label' => 'TaxRegime', 'enabled' => '1', 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'uf' => array('type' => 'varchar(2)', 'label' => 'State', 'enabled' => '1', 'position' => 70, 'notnull' => 0, 'visible' => 1),
		'municipio' => array('type' => 'varchar(100)', 'label' => 'Town', 'enabled' => '1', 'position' => 71, 'notnull' => 0, 'visible' => -1),
		'habilita_nfe' => array('type' => 'boolean', 'label' => 'NFe', 'enabled' => '1', 'position' => 80, 'notnull' => 1, 'visible' => 1, 'default' => '1'),
		'habilita_manifestacao' => array('type' => 'boolean', 'label' => 'NFeReceived', 'enabled' => '1', 'position' => 81, 'notnull' => 1, 'visible' => 1, 'default' => '0'),
		'data_inicio_recebimento_nfe' => array('type' => 'date', 'label' => 'DateStart', 'enabled' => '1', 'position' => 82, 'notnull' => 0, 'visible' => -1),
		'fk_certificate_active' => array('type' => 'integer', 'label' => 'FocusCertificateSubmission', 'enabled' => '1', 'position' => 90, 'notnull' => 0, 'visible' => -1, 'index' => 1),
		'active' => array('type' => 'boolean', 'label' => 'Status', 'enabled' => '1', 'position' => 100, 'notnull' => 1, 'visible' => 1, 'default' => '0', 'index' => 1),
		'status' => array('type' => 'varchar(32)', 'label' => 'Status', 'enabled' => '1', 'position' => 101, 'notnull' => 1, 'visible' => 1, 'default' => 'draft', 'index' => 1),
		'focus_status' => array('type' => 'varchar(64)', 'label' => 'NFeFocusStatus', 'enabled' => '1', 'position' => 110, 'notnull' => 0, 'visible' => -1),
		'focus_message' => array('type' => 'text', 'label' => 'Message', 'enabled' => '1', 'position' => 111, 'notnull' => 0, 'visible' => -1),
		'last_sync' => array('type' => 'datetime', 'label' => 'LastSync', 'enabled' => '1', 'position' => 120, 'notnull' => 0, 'visible' => -1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => '1', 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => '1', 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => '1', 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $rowid;
	public $entity;
	public $environment;
	public $focus_id;
	public $cnpj;
	public $cpf;
	public $name;
	public $nome_fantasia;
	public $inscricao_estadual;
	public $inscricao_municipal;
	public $regime_tributario;
	public $uf;
	public $municipio;
	public $habilita_nfe;
	public $habilita_manifestacao;
	public $data_inicio_recebimento_nfe;
	public $fk_certificate_active;
	public $active;
	public $status;
	public $focus_status;
	public $focus_message;
	public $last_sync;
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

	/**
	 * Fetch the active Focus company for the current Dolibarr entity.
	 *
	 * @param string $environment Focus environment
	 * @return int<-1,1>
	 */
	public function fetchActive($environment)
	{
		$morewhere = " AND t.environment = '".$this->db->escape($environment)."' AND t.active = 1";
		return $this->fetchCommon(0, '', $morewhere);
	}

	/**
	 * Fetch a company by document and environment.
	 *
	 * @param string $document CNPJ or CPF with digits only
	 * @param string $environment Focus environment
	 * @return int<-1,1>
	 */
	public function fetchByDocument($document, $environment)
	{
		$document = preg_replace('/\D/', '', $document);
		$field = (dol_strlen($document) == 11) ? 'cpf' : 'cnpj';
		$morewhere = " AND t.environment = '".$this->db->escape($environment)."' AND t.".$field." = '".$this->db->escape($document)."'";
		return $this->fetchCommon(0, '', $morewhere);
	}
}
