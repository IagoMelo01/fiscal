<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file    class/nfereceived.class.php
 * \ingroup fiscal
 * \brief   Received NF-e synchronized from Focus NFe.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * NF-e issued against the company's CNPJ.
 */
class NFeReceived extends CommonObject
{
	public $module = 'fiscal';
	public $element = 'nfereceived';
	public $table_element = 'fiscal_nfe_received';
	public $picto = 'fa-file-import';
	public $ismultientitymanaged = 1;
	public $isextrafieldmanaged = 0;

	const STATUS_NEW = 0;
	const STATUS_MANIFESTED = 1;
	const STATUS_ARCHIVED = 9;

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 5, 'notnull' => 1, 'visible' => 0, 'default' => '1', 'index' => 1),
		'fk_focus_company' => array('type' => 'integer:FocusCompany:/fiscal/class/focuscompany.class.php', 'label' => 'FocusCompany', 'enabled' => '1', 'position' => 10, 'notnull' => 0, 'visible' => -1, 'index' => 1),
		'environment' => array('type' => 'varchar(20)', 'label' => 'FISCAL_FOCUS_ENVIRONMENT', 'enabled' => '1', 'position' => 20, 'notnull' => 1, 'visible' => -1, 'default' => 'homologation'),
		'chave_nfe' => array('type' => 'varchar(44)', 'label' => 'NFeAccessKey', 'enabled' => '1', 'position' => 30, 'notnull' => 1, 'visible' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'index' => 1),
		'cnpj_destinatario' => array('type' => 'varchar(14)', 'label' => 'CNPJ', 'enabled' => '1', 'position' => 40, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'documento_emitente' => array('type' => 'varchar(14)', 'label' => 'ThirdParty', 'enabled' => '1', 'position' => 50, 'notnull' => 0, 'visible' => 1, 'searchall' => 1, 'index' => 1),
		'nome_emitente' => array('type' => 'varchar(255)', 'label' => 'Name', 'enabled' => '1', 'position' => 51, 'notnull' => 0, 'visible' => 1, 'searchall' => 1),
		'ie_emitente' => array('type' => 'varchar(32)', 'label' => 'StateTaxID', 'enabled' => '1', 'position' => 52, 'notnull' => 0, 'visible' => -1),
		'uf_emitente' => array('type' => 'varchar(2)', 'label' => 'State', 'enabled' => '1', 'position' => 53, 'notnull' => 0, 'visible' => -1),
		'valor_total' => array('type' => 'price', 'label' => 'NFeTotal', 'enabled' => '1', 'position' => 60, 'notnull' => 0, 'visible' => 1, 'isameasure' => 1),
		'data_emissao' => array('type' => 'datetime', 'label' => 'NFeIssueDate', 'enabled' => '1', 'position' => 70, 'notnull' => 0, 'visible' => 1),
		'data_recebimento' => array('type' => 'datetime', 'label' => 'DateReception', 'enabled' => '1', 'position' => 71, 'notnull' => 0, 'visible' => -1),
		'situacao' => array('type' => 'varchar(64)', 'label' => 'Status', 'enabled' => '1', 'position' => 80, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'manifestacao_destinatario' => array('type' => 'varchar(64)', 'label' => 'Manifestation', 'enabled' => '1', 'position' => 90, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'nfe_completa' => array('type' => 'boolean', 'label' => 'Complete', 'enabled' => '1', 'position' => 100, 'notnull' => 1, 'visible' => -1, 'default' => '0'),
		'tipo_nfe' => array('type' => 'varchar(32)', 'label' => 'Type', 'enabled' => '1', 'position' => 110, 'notnull' => 0, 'visible' => -1),
		'versao' => array('type' => 'integer', 'label' => 'Version', 'enabled' => '1', 'position' => 120, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'digest_value' => array('type' => 'varchar(128)', 'label' => 'Hash', 'enabled' => '1', 'position' => 130, 'notnull' => 0, 'visible' => -1),
		'caminho_xml' => array('type' => 'varchar(255)', 'label' => 'NFeXmlPath', 'enabled' => '1', 'position' => 140, 'notnull' => 0, 'visible' => -1),
		'caminho_pdf' => array('type' => 'varchar(255)', 'label' => 'NFeDanfePath', 'enabled' => '1', 'position' => 141, 'notnull' => 0, 'visible' => -1),
		'raw_json' => array('type' => 'text', 'label' => 'JSON', 'enabled' => '1', 'position' => 150, 'notnull' => 0, 'visible' => 0),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => '1', 'position' => 160, 'notnull' => 1, 'visible' => 1, 'default' => '0', 'index' => 1),
		'last_sync' => array('type' => 'datetime', 'label' => 'LastSync', 'enabled' => '1', 'position' => 170, 'notnull' => 0, 'visible' => -1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => '1', 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => '1', 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => '1', 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $rowid;
	public $entity;
	public $fk_focus_company;
	public $environment;
	public $chave_nfe;
	public $cnpj_destinatario;
	public $documento_emitente;
	public $nome_emitente;
	public $ie_emitente;
	public $uf_emitente;
	public $valor_total;
	public $data_emissao;
	public $data_recebimento;
	public $situacao;
	public $manifestacao_destinatario;
	public $nfe_completa;
	public $tipo_nfe;
	public $versao;
	public $digest_value;
	public $caminho_xml;
	public $caminho_pdf;
	public $raw_json;
	public $status;
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
	 * Fetch by Focus access key and recipient document.
	 *
	 * @param string $accessKey NF-e access key
	 * @param string $recipientCnpj Recipient CNPJ
	 * @return int<-1,1>
	 */
	public function fetchByAccessKey($accessKey, $recipientCnpj)
	{
		$recipientCnpj = preg_replace('/\D/', '', $recipientCnpj);
		$morewhere = " AND t.chave_nfe = '".$this->db->escape($accessKey)."'";
		$morewhere .= " AND t.cnpj_destinatario = '".$this->db->escape($recipientCnpj)."'";
		return $this->fetchCommon(0, '', $morewhere);
	}

	/**
	 * @param int $withpicto Add picto
	 * @param string $option Link option
	 * @param int $notooltip Disable tooltip
	 * @param string $morecss More CSS
	 * @param int $save_lastsearch_value Save last search
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		$label = dol_escape_htmltag($this->chave_nfe);
		$url = dol_buildpath('/fiscal/received_nfe_card.php', 1).'?id='.(int) $this->id;
		$result = ($option == 'nolink') ? '<span>' : '<a href="'.$url.'"'.($morecss ? ' class="'.$morecss.'"' : '').'>';
		if ($withpicto) {
			$result .= img_object('', $this->picto, 'class="paddingright"');
		}
		$result .= $label;
		$result .= ($option == 'nolink') ? '</span>' : '</a>';
		return $result;
	}

	/**
	 * @return bool
	 */
	public function hasPendingManifestation()
	{
		return empty($this->manifestacao_destinatario);
	}

	/**
	 * Cron entrypoint for received NF-e incremental synchronization.
	 *
	 * @return int
	 */
	public function doScheduledJob()
	{
		global $user;

		if (!getDolGlobalInt('FISCAL_IMPORT_RECEIVED_NFE')) {
			return 0;
		}

		require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/focusnfereceivedservice.class.php';
		$service = new FocusNFeReceivedService($this->db);
		$result = $service->synchronize($user);
		if (empty($result['ok'])) {
			$this->error = $result['message'];
			dol_syslog(__METHOD__.' error: '.$this->error, LOG_WARNING);
			return -1;
		}
		return empty($result['imported']) ? 0 : (int) $result['imported'];
	}
}
