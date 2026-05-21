<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file        class/nfe.class.php
 * \ingroup     fiscal
 * \brief       Issued NF-e domain object.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Issued NF-e header.
 */
class NFe extends CommonObject
{
	public $module = 'fiscal';
	public $element = 'nfe';
	public $TRIGGER_PREFIX = 'FISCAL_NFE';
	public $table_element = 'fiscal_nfe';
	public $table_element_line = 'fiscal_nfe_line';
	public $fk_element = 'fk_nfe';
	public $class_element_line = 'NFeLine';
	public $picto = 'fa-file-invoice';
	public $isextrafieldmanaged = 0;
	public $ismultientitymanaged = 1;
	protected $childtablesoncascade = array('fiscal_nfe_line');

	const STATUS_DRAFT = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_TRANSMITTED = 2;
	const STATUS_AUTHORIZED = 3;
	const STATUS_REJECTED = 4;
	const STATUS_CANCELED = 9;

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 5, 'notnull' => 1, 'visible' => 0, 'default' => '1', 'index' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'NFeRef', 'enabled' => '1', 'position' => 20, 'notnull' => 1, 'visible' => 1, 'default' => '(PROV)', 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1),
		'fk_soc' => array('type' => "integer:Societe:societe/class/societe.class.php:1:((status:=:1) AND (entity:IN:__SHARED_ENTITIES__))", 'label' => 'ThirdParty', 'picto' => 'company', 'enabled' => "isModEnabled('societe')", 'position' => 30, 'notnull' => 0, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150'),
		'fk_project' => array('type' => 'integer:Project:projet/class/project.class.php:1', 'label' => 'Project', 'picto' => 'project', 'enabled' => "isModEnabled('project')", 'position' => 31, 'notnull' => 0, 'visible' => -1, 'index' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150'),
		'fk_focus_company' => array('type' => 'integer:FocusCompany:/fiscal/class/focuscompany.class.php:1', 'label' => 'FocusCompany', 'enabled' => '1', 'position' => 40, 'notnull' => 0, 'visible' => -1, 'index' => 1),
		'focus_environment' => array('type' => 'varchar(20)', 'label' => 'FISCAL_FOCUS_ENVIRONMENT', 'enabled' => '1', 'position' => 41, 'notnull' => 1, 'visible' => -1, 'default' => 'homologation'),
		'focus_ref' => array('type' => 'varchar(128)', 'label' => 'NFeFocusRef', 'enabled' => '1', 'position' => 42, 'notnull' => 0, 'visible' => 1, 'index' => 1, 'searchall' => 1),
		'chave_nfe' => array('type' => 'varchar(44)', 'label' => 'NFeAccessKey', 'enabled' => '1', 'position' => 43, 'notnull' => 0, 'visible' => 1, 'index' => 1, 'searchall' => 1),
		'modelo' => array('type' => 'varchar(2)', 'label' => 'NFeModel', 'enabled' => '1', 'position' => 50, 'notnull' => 1, 'visible' => -1, 'default' => '55'),
		'serie' => array('type' => 'integer', 'label' => 'NFeSeries', 'enabled' => '1', 'position' => 51, 'notnull' => 0, 'visible' => 1, 'default' => '1'),
		'numero' => array('type' => 'integer', 'label' => 'NFeNumber', 'enabled' => '1', 'position' => 52, 'notnull' => 0, 'visible' => 1),
		'tipo_documento' => array('type' => 'integer', 'label' => 'NFeDocumentType', 'enabled' => '1', 'position' => 60, 'notnull' => 1, 'visible' => -1, 'default' => '1', 'arrayofkeyval' => array('0' => 'Entrada', '1' => 'Saida')),
		'finalidade_emissao' => array('type' => 'integer', 'label' => 'NFePurpose', 'enabled' => '1', 'position' => 61, 'notnull' => 1, 'visible' => -1, 'default' => '1', 'arrayofkeyval' => array('1' => 'Normal', '2' => 'Complementar', '3' => 'Ajuste', '4' => 'Devolucao')),
		'natureza_operacao' => array('type' => 'varchar(255)', 'label' => 'NFeNaturezaOperacao', 'enabled' => '1', 'position' => 70, 'notnull' => 1, 'visible' => 1, 'searchall' => 1, 'css' => 'minwidth300'),
		'data_emissao' => array('type' => 'datetime', 'label' => 'NFeIssueDate', 'enabled' => '1', 'position' => 80, 'notnull' => 0, 'visible' => 1),
		'data_entrada_saida' => array('type' => 'datetime', 'label' => 'DateDelivery', 'enabled' => '1', 'position' => 81, 'notnull' => 0, 'visible' => -1),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => '1', 'position' => 90, 'notnull' => 1, 'visible' => 5, 'noteditable' => 1, 'default' => '0', 'index' => 1, 'arrayofkeyval' => array('0' => 'FiscalStatusDraft', '1' => 'FiscalStatusValidated', '2' => 'FiscalStatusTransmitted', '3' => 'FiscalStatusAuthorized', '4' => 'FiscalStatusRejected', '9' => 'FiscalStatusCanceled')),
		'status_focus' => array('type' => 'varchar(64)', 'label' => 'NFeFocusStatus', 'enabled' => '1', 'position' => 100, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'status_sefaz' => array('type' => 'varchar(64)', 'label' => 'NFeSefazStatus', 'enabled' => '1', 'position' => 101, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'mensagem_sefaz' => array('type' => 'text', 'label' => 'NFeSefazMessage', 'enabled' => '1', 'position' => 102, 'notnull' => 0, 'visible' => -1),
		'protocolo' => array('type' => 'varchar(80)', 'label' => 'NFeProtocol', 'enabled' => '1', 'position' => 103, 'notnull' => 0, 'visible' => 1),
		'valor_produtos' => array('type' => 'price', 'label' => 'NFeTotalProducts', 'enabled' => '1', 'position' => 110, 'notnull' => 0, 'visible' => -1, 'default' => '0', 'isameasure' => 1),
		'valor_frete' => array('type' => 'price', 'label' => 'NFeTotalFreight', 'enabled' => '1', 'position' => 111, 'notnull' => 0, 'visible' => -1, 'default' => '0', 'isameasure' => 1),
		'valor_seguro' => array('type' => 'price', 'label' => 'Insurance', 'enabled' => '1', 'position' => 112, 'notnull' => 0, 'visible' => -1, 'default' => '0', 'isameasure' => 1),
		'valor_desconto' => array('type' => 'price', 'label' => 'NFeTotalDiscount', 'enabled' => '1', 'position' => 113, 'notnull' => 0, 'visible' => -1, 'default' => '0', 'isameasure' => 1),
		'valor_total' => array('type' => 'price', 'label' => 'NFeTotal', 'enabled' => '1', 'position' => 114, 'notnull' => 0, 'visible' => 1, 'default' => '0', 'isameasure' => 1),
		'request_hash' => array('type' => 'varchar(64)', 'label' => 'Hash', 'enabled' => '1', 'position' => 120, 'notnull' => 0, 'visible' => 0),
		'request_summary' => array('type' => 'text', 'label' => 'Request', 'enabled' => '1', 'position' => 121, 'notnull' => 0, 'visible' => 0),
		'response_summary' => array('type' => 'text', 'label' => 'Response', 'enabled' => '1', 'position' => 122, 'notnull' => 0, 'visible' => 0),
		'http_code' => array('type' => 'integer', 'label' => 'HTTPCode', 'enabled' => '1', 'position' => 123, 'notnull' => 0, 'visible' => -1),
		'caminho_xml' => array('type' => 'varchar(255)', 'label' => 'NFeXmlPath', 'enabled' => '1', 'position' => 130, 'notnull' => 0, 'visible' => -1),
		'caminho_danfe' => array('type' => 'varchar(255)', 'label' => 'NFeDanfePath', 'enabled' => '1', 'position' => 131, 'notnull' => 0, 'visible' => -1),
		'date_transmission' => array('type' => 'datetime', 'label' => 'NFeTransmissionDate', 'enabled' => '1', 'position' => 140, 'notnull' => 0, 'visible' => -1),
		'date_authorization' => array('type' => 'datetime', 'label' => 'NFeAuthorizationDate', 'enabled' => '1', 'position' => 141, 'notnull' => 0, 'visible' => -1),
		'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'enabled' => '1', 'position' => 150, 'notnull' => 0, 'visible' => 0, 'cssview' => 'wordbreak'),
		'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'enabled' => '1', 'position' => 151, 'notnull' => 0, 'visible' => 0, 'cssview' => 'wordbreak'),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => '1', 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => '1', 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'last_main_doc' => array('type' => 'varchar(255)', 'label' => 'LastMainDoc', 'enabled' => '1', 'position' => 600, 'notnull' => 0, 'visible' => 0),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => '1', 'position' => 1000, 'notnull' => 0, 'visible' => -2),
		'model_pdf' => array('type' => 'varchar(255)', 'label' => 'ModelPDF', 'enabled' => '1', 'position' => 1010, 'notnull' => 0, 'visible' => 0),
	);

	public $rowid;
	public $entity;
	public $ref;
	public $fk_soc;
	public $fk_project;
	public $fk_focus_company;
	public $focus_environment;
	public $focus_ref;
	public $chave_nfe;
	public $modelo;
	public $serie;
	public $numero;
	public $tipo_documento;
	public $finalidade_emissao;
	public $natureza_operacao;
	public $data_emissao;
	public $data_entrada_saida;
	public $status;
	public $status_focus;
	public $status_sefaz;
	public $mensagem_sefaz;
	public $protocolo;
	public $valor_produtos;
	public $valor_frete;
	public $valor_seguro;
	public $valor_desconto;
	public $valor_total;
	public $request_hash;
	public $request_summary;
	public $response_summary;
	public $http_code;
	public $caminho_xml;
	public $caminho_danfe;
	public $date_transmission;
	public $date_authorization;
	public $note_public;
	public $note_private;
	public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;
	public $last_main_doc;
	public $import_key;
	public $model_pdf;
	public $lines = array();

	public function __construct(DoliDB $db)
	{
		global $langs;

		$this->db = $db;

		if (getDolGlobalString('FISCAL_FOCUS_ENVIRONMENT')) {
			$this->fields['focus_environment']['default'] = getDolGlobalString('FISCAL_FOCUS_ENVIRONMENT', 'homologation');
		}
		$this->fields['serie']['default'] = getDolGlobalString('FISCAL_NFE_DEFAULT_SERIE', '1');

		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}

	public function create(User $user, $notrigger = 0)
	{
		global $conf;

		if (empty($this->entity) && is_object($conf)) {
			$this->entity = $conf->entity;
		}
		if (empty($this->focus_environment)) {
			$this->focus_environment = getDolGlobalString('FISCAL_FOCUS_ENVIRONMENT', 'homologation');
		}
		if (empty($this->modelo)) {
			$this->modelo = '55';
		}
		return $this->createCommon($user, $notrigger);
	}

	public function update(User $user, $notrigger = 0)
	{
		return $this->updateCommon($user, $notrigger);
	}

	public function delete(User $user, $notrigger = 0)
	{
		if ($this->isFiscalLocked()) {
			$this->error = 'FiscalNFeFiscalLocked';
			return -1;
		}
		return $this->deleteCommon($user, $notrigger, 1);
	}

	public function fetch($id, $ref = null, $noextrafields = 0, $nolines = 0)
	{
		$result = $this->fetchCommon($id, $ref, '', $noextrafields);
		if ($result > 0 && empty($nolines)) {
			$this->fetchLines($noextrafields);
		}
		return $result;
	}

	public function fetchLines($noextrafields = 0)
	{
		return $this->getLinesArray();
	}

	public function getLinesArray()
	{
		$this->lines = array();
		if (empty($this->id)) {
			return 0;
		}

		$line = new NFeLine($this->db);
		$sql = 'SELECT '.$line->getFieldList('l');
		$sql .= ' FROM '.$this->db->prefix().$line->table_element.' as l';
		$sql .= ' WHERE l.fk_nfe = '.((int) $this->id);
		$sql .= ' ORDER BY l.position ASC, l.numero_item ASC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$newline = new NFeLine($this->db);
			$newline->setVarsFromFetchObj($obj);
			$this->lines[] = $newline;
		}
		$this->db->free($resql);

		return 1;
	}

	public function addLine(User $user, array $data)
	{
		if ($this->isFiscalLocked() || $this->status != self::STATUS_DRAFT) {
			$this->error = 'FiscalNFeFiscalLocked';
			return -1;
		}

		$line = new NFeLine($this->db);
		$line->fk_nfe = $this->id;
		$line->numero_item = empty($data['numero_item']) ? (count($this->lines) + 1) : (int) $data['numero_item'];
		$line->position = $line->numero_item;
		$line->fk_product = empty($data['fk_product']) ? null : (int) $data['fk_product'];
		$line->codigo_produto = empty($data['codigo_produto']) ? '' : $data['codigo_produto'];
		$line->descricao = empty($data['descricao']) ? '' : $data['descricao'];
		$line->ncm = empty($data['ncm']) ? '' : preg_replace('/\D/', '', $data['ncm']);
		$line->cest = empty($data['cest']) ? '' : preg_replace('/\D/', '', $data['cest']);
		$line->cfop = empty($data['cfop']) ? '' : preg_replace('/\D/', '', $data['cfop']);
		$line->unidade_comercial = empty($data['unidade_comercial']) ? 'UN' : strtoupper($data['unidade_comercial']);
		$line->quantidade_comercial = price2num(empty($data['quantidade_comercial']) ? 0 : $data['quantidade_comercial']);
		$line->valor_unitario_comercial = price2num(empty($data['valor_unitario_comercial']) ? 0 : $data['valor_unitario_comercial']);
		$line->valor_bruto = price2num(empty($data['valor_bruto']) ? ($line->quantidade_comercial * $line->valor_unitario_comercial) : $data['valor_bruto']);
		$line->unidade_tributavel = $line->unidade_comercial;
		$line->quantidade_tributavel = $line->quantidade_comercial;
		$line->valor_unitario_tributavel = $line->valor_unitario_comercial;
		$line->icms_origem = empty($data['icms_origem']) ? '' : $data['icms_origem'];
		$line->icms_situacao_tributaria = empty($data['icms_situacao_tributaria']) ? '' : $data['icms_situacao_tributaria'];
		$line->pis_situacao_tributaria = empty($data['pis_situacao_tributaria']) ? '' : $data['pis_situacao_tributaria'];
		$line->cofins_situacao_tributaria = empty($data['cofins_situacao_tributaria']) ? '' : $data['cofins_situacao_tributaria'];

		$result = $line->create($user);
		if ($result > 0) {
			$this->recalculateTotals($user);
		} else {
			$this->setErrorsFromObject($line);
		}

		return $result;
	}

	public function deleteLine(User $user, $lineid)
	{
		if ($this->isFiscalLocked()) {
			$this->error = 'FiscalNFeFiscalLocked';
			return -1;
		}

		$line = new NFeLine($this->db);
		$result = $line->fetch($lineid);
		if ($result <= 0 || (int) $line->fk_nfe !== (int) $this->id) {
			$this->error = 'ErrorRecordNotFound';
			return -1;
		}
		$result = $line->delete($user);
		if ($result > 0) {
			$this->recalculateTotals($user);
		}
		return $result;
	}

	public function recalculateTotals(User $user)
	{
		$sql = 'SELECT SUM(valor_bruto) as total';
		$sql .= ' FROM '.$this->db->prefix().'fiscal_nfe_line';
		$sql .= ' WHERE fk_nfe = '.((int) $this->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		$this->valor_produtos = price2num(empty($obj->total) ? 0 : $obj->total);
		$this->valor_total = price2num($this->valor_produtos + $this->valor_frete + $this->valor_seguro - $this->valor_desconto);

		return $this->update($user, 1);
	}

	public function validate($user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_DRAFT) {
			return 0;
		}
		return $this->setStatusCommon($user, self::STATUS_VALIDATED, $notrigger, 'FISCAL_NFE_VALIDATE');
	}

	public function setDraft($user, $notrigger = 0)
	{
		if ($this->status == self::STATUS_DRAFT) {
			return 0;
		}
		if ($this->isFiscalLocked()) {
			$this->error = 'FiscalNFeFiscalLocked';
			return -1;
		}
		return $this->setStatusCommon($user, self::STATUS_DRAFT, $notrigger, 'FISCAL_NFE_UNVALIDATE');
	}

	/**
	 * Fiscal fields must stop changing after the document is sent to Focus.
	 *
	 * @return bool
	 */
	public function isFiscalLocked()
	{
		return (int) $this->status >= self::STATUS_TRANSMITTED;
	}

	public function cancel($user, $notrigger = 0)
	{
		if ($this->status == self::STATUS_CANCELED) {
			return 0;
		}
		return $this->setStatusCommon($user, self::STATUS_CANCELED, $notrigger, 'FISCAL_NFE_CANCEL');
	}

	public function reopen($user, $notrigger = 0)
	{
		return $this->setStatusCommon($user, self::STATUS_VALIDATED, $notrigger, 'FISCAL_NFE_REOPEN');
	}

	public function getTooltipContentArray($params)
	{
		global $langs;

		if (getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER')) {
			return array('optimize' => $langs->trans('ShowNFe'));
		}

		$datas = array();
		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans('NFe').'</u>';
		$datas['picto'] .= ' '.$this->getLibStatut(5);
		$datas['ref'] = '<br><b>'.$langs->trans('Ref').':</b> '.dol_escape_htmltag($this->ref);
		if (!empty($this->chave_nfe)) {
			$datas['chave'] = '<br><b>'.$langs->trans('NFeAccessKey').':</b> '.dol_escape_htmltag($this->chave_nfe);
		}

		return $datas;
	}

	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $conf, $langs;

		$result = '';
		$label = '';
		if (empty($notooltip)) {
			$params = array('id' => (string) $this->id, 'objecttype' => $this->element.'@'.$this->module);
			$label = implode('', $this->getTooltipContentArray($params));
		}

		$url = dol_buildpath('/fiscal/nfe_card.php', 1).'?id='.(int) $this->id;
		if ($save_lastsearch_value == 1 || ($save_lastsearch_value == -1 && isset($_SERVER['PHP_SELF']) && preg_match('/list\.php/', $_SERVER['PHP_SELF']))) {
			$url .= '&save_lastsearch_values=1';
		}

		$linkclose = empty($notooltip) ? ' title="'.dolPrintHTMLForAttribute($label).'" class="classfortooltip'.($morecss ? ' '.$morecss : '').'"' : ($morecss ? ' class="'.$morecss.'"' : '');
		$result .= ($option == 'nolink' ? '<span'.$linkclose.'>' : '<a href="'.$url.'"'.$linkclose.'>');
		if ($withpicto) {
			$result .= img_object(($notooltip ? '' : $label), $this->picto, (($withpicto != 2) ? 'class="paddingright"' : ''), 0, 0, $notooltip ? 0 : 1);
		}
		if ($withpicto != 2) {
			$result .= dol_escape_htmltag($this->ref);
		}
		$result .= ($option == 'nolink' ? '</span>' : '</a>');

		return $result;
	}

	public function getKanbanView($option = '', $arraydata = null)
	{
		$selected = (empty($arraydata['selected']) ? 0 : $arraydata['selected']);
		$return = '<div class="box-flex-item box-flex-grow-zero">';
		$return .= '<div class="info-box info-box-sm">';
		$return .= '<span class="info-box-icon bg-infobox-action">'.img_picto('', $this->picto).'</span>';
		$return .= '<div class="info-box-content">';
		$return .= '<span class="info-box-ref inline-block tdoverflowmax150 valignmiddle">'.$this->getNomUrl().'</span>';
		if ($selected >= 0) {
			$return .= '<input id="cb'.$this->id.'" class="flat checkforselect fright" type="checkbox" name="toselect[]" value="'.$this->id.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		$return .= '<br><span class="opacitymedium">'.$this->getLibStatut(5).'</span>';
		$return .= '</div></div></div>';
		return $return;
	}

	public function getLabelStatus($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	public function LibStatut($status, $mode = 0)
	{
		global $langs;

		if ($status === null || $status === '') {
			return '';
		}

		$labels = array(
			self::STATUS_DRAFT => 'FiscalStatusDraft',
			self::STATUS_VALIDATED => 'FiscalStatusValidated',
			self::STATUS_TRANSMITTED => 'FiscalStatusTransmitted',
			self::STATUS_AUTHORIZED => 'FiscalStatusAuthorized',
			self::STATUS_REJECTED => 'FiscalStatusRejected',
			self::STATUS_CANCELED => 'FiscalStatusCanceled',
		);
		$types = array(
			self::STATUS_DRAFT => 'status0',
			self::STATUS_VALIDATED => 'status1',
			self::STATUS_TRANSMITTED => 'status4',
			self::STATUS_AUTHORIZED => 'status6',
			self::STATUS_REJECTED => 'status8',
			self::STATUS_CANCELED => 'status9',
		);

		$key = isset($labels[$status]) ? $labels[$status] : 'StatusUnknown';
		$type = isset($types[$status]) ? $types[$status] : 'status0';

		return dolGetStatus($langs->transnoentitiesnoconv($key), $langs->transnoentitiesnoconv($key), '', $type, $mode);
	}

	public function info($id)
	{
		$sql = 'SELECT t.rowid, t.date_creation as datec, t.tms as datem, t.fk_user_creat, t.fk_user_modif';
		$sql .= ' FROM '.$this->db->prefix().$this->table_element.' as t';
		$sql .= ' WHERE t.rowid = '.((int) $id);
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			$this->id = $obj->rowid;
			$this->user_creation_id = $obj->fk_user_creat;
			$this->user_modification_id = $obj->fk_user_modif;
			$this->date_creation = $this->db->jdate($obj->datec);
			$this->date_modification = empty($obj->datem) ? '' : $this->db->jdate($obj->datem);
		}
		if ($resql) {
			$this->db->free($resql);
		}
	}

	public function initAsSpecimen()
	{
		$this->ref = 'NFE-SPECIMEN';
		$this->modelo = '55';
		$this->serie = 1;
		$this->natureza_operacao = 'Venda de producao';
		$this->status = self::STATUS_DRAFT;
		return 1;
	}

	public function printObjectLines($action, $seller, $buyer, $selected = 0, $dateSelector = 0, $defaulttpldir = '/core/tpl')
	{
		global $langs;

		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans('NFeLineNumber').'</td>';
		print '<td>'.$langs->trans('NFeProductCode').'</td>';
		print '<td>'.$langs->trans('NFeProductDescription').'</td>';
		print '<td>'.$langs->trans('NFeNcm').'</td>';
		print '<td>'.$langs->trans('NFeCfop').'</td>';
		print '<td class="right">'.$langs->trans('Qty').'</td>';
		print '<td class="right">'.$langs->trans('NFeCommercialUnitPrice').'</td>';
		print '<td class="right">'.$langs->trans('NFeGrossAmount').'</td>';
		print '<td></td>';
		print '</tr>';

		foreach ($this->lines as $line) {
			print '<tr class="oddeven" id="line_'.$line->id.'">';
			print '<td>'.((int) $line->numero_item).'</td>';
			print '<td>'.dol_escape_htmltag($line->codigo_produto).'</td>';
			print '<td>'.dol_escape_htmltag($line->descricao).'</td>';
			print '<td>'.dol_escape_htmltag($line->ncm).'</td>';
			print '<td>'.dol_escape_htmltag($line->cfop).'</td>';
			print '<td class="right">'.price($line->quantidade_comercial).'</td>';
			print '<td class="right">'.price($line->valor_unitario_comercial).'</td>';
			print '<td class="right">'.price($line->valor_bruto).'</td>';
			print '<td class="right">';
			if ($this->status == self::STATUS_DRAFT && !$this->isFiscalLocked()) {
				print '<a href="'.$_SERVER['PHP_SELF'].'?id='.(int) $this->id.'&action=deleteline&lineid='.(int) $line->id.'&token='.newToken().'">'.img_delete().'</a>';
			}
			print '</td>';
			print '</tr>';
		}
	}

	public function formAddObjectLine($dateSelector, $seller, $buyer, $defaulttpldir = '/core/tpl')
	{
		global $langs;

		$next = count($this->lines) + 1;
		print '<tr class="oddeven">';
		print '<td><input type="text" class="flat maxwidth50" name="numero_item" value="'.((int) $next).'"></td>';
		print '<td><input type="text" class="flat maxwidth100" name="codigo_produto"></td>';
		print '<td><input type="text" class="flat minwidth300" name="descricao"></td>';
		print '<td><input type="text" class="flat maxwidth75" name="ncm"></td>';
		print '<td><input type="text" class="flat maxwidth50" name="cfop"></td>';
		print '<td class="right"><input type="text" class="flat maxwidth75 right" name="quantidade_comercial" value="1"></td>';
		print '<td class="right"><input type="text" class="flat maxwidth75 right" name="valor_unitario_comercial" value="0"></td>';
		print '<td class="right"><input type="text" class="flat maxwidth75 right" name="valor_bruto" value="0"></td>';
		print '<td class="right"><input type="submit" class="button smallpaddingimp" value="'.$langs->trans('NFeAddLine').'"></td>';
		print '</tr>';
		print '<tr class="oddeven">';
		print '<td></td>';
		print '<td colspan="8">';
		print $langs->trans('NFeIcmsOrigin').' <input type="text" class="flat maxwidth50" name="icms_origem"> ';
		print $langs->trans('NFeIcmsTaxSituation').' <input type="text" class="flat maxwidth75" name="icms_situacao_tributaria"> ';
		print $langs->trans('NFePisTaxSituation').' <input type="text" class="flat maxwidth50" name="pis_situacao_tributaria"> ';
		print $langs->trans('NFeCofinsTaxSituation').' <input type="text" class="flat maxwidth50" name="cofins_situacao_tributaria"> ';
		print '<input type="hidden" name="unidade_comercial" value="UN">';
		print '</td>';
		print '</tr>';
	}

	public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams = null)
	{
		$this->error = 'FiscalDocumentGenerationNotAvailable';
		return 0;
	}

	public function doScheduledJob()
	{
		global $user;

		require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/focusnfeservice.class.php';

		$service = new FocusNFeService($this->db);
		$result = $service->synchronizePending(20, $user);
		if (!empty($result['errors'])) {
			$this->error = implode('; ', $result['errors']);
			dol_syslog(__METHOD__.' errors: '.$this->error, LOG_WARNING);
			return -1;
		}
		dol_syslog(__METHOD__.' processed '.$result['processed'].' pending NF-es', LOG_INFO);
		return (int) $result['processed'];
	}
}

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';

/**
 * Issued NF-e item line.
 */
class NFeLine extends CommonObjectLine
{
	public $module = 'fiscal';
	public $element = 'nfeline';
	public $table_element = 'fiscal_nfe_line';
	public $parent_element = 'nfe';
	public $fk_parent_attribute = 'fk_nfe';
	public $picto = 'line';
	public $isextrafieldmanaged = 0;
	public $ismultientitymanaged = 0;

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'fk_nfe' => array('type' => 'integer', 'label' => 'NFe', 'enabled' => '1', 'position' => 10, 'notnull' => 1, 'visible' => 0, 'index' => 1),
		'fk_product' => array('type' => 'integer:Product:product/class/product.class.php', 'label' => 'Product', 'enabled' => "isModEnabled('product') || isModEnabled('service')", 'position' => 20, 'notnull' => 0, 'visible' => -1, 'index' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => '1', 'position' => 30, 'notnull' => 1, 'visible' => 0, 'default' => '0'),
		'numero_item' => array('type' => 'integer', 'label' => 'NFeLineNumber', 'enabled' => '1', 'position' => 40, 'notnull' => 1, 'visible' => 1),
		'codigo_produto' => array('type' => 'varchar(80)', 'label' => 'NFeProductCode', 'enabled' => '1', 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'descricao' => array('type' => 'text', 'label' => 'NFeProductDescription', 'enabled' => '1', 'position' => 60, 'notnull' => 1, 'visible' => 1),
		'ncm' => array('type' => 'varchar(8)', 'label' => 'NFeNcm', 'enabled' => '1', 'position' => 70, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'cest' => array('type' => 'varchar(7)', 'label' => 'NFeCest', 'enabled' => '1', 'position' => 71, 'notnull' => 0, 'visible' => -1),
		'cfop' => array('type' => 'varchar(4)', 'label' => 'NFeCfop', 'enabled' => '1', 'position' => 80, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'unidade_comercial' => array('type' => 'varchar(6)', 'label' => 'NFeCommercialUnit', 'enabled' => '1', 'position' => 90, 'notnull' => 0, 'visible' => 1),
		'quantidade_comercial' => array('type' => 'double(24,8)', 'label' => 'NFeCommercialQty', 'enabled' => '1', 'position' => 100, 'notnull' => 0, 'visible' => 1),
		'valor_unitario_comercial' => array('type' => 'double(24,10)', 'label' => 'NFeCommercialUnitPrice', 'enabled' => '1', 'position' => 110, 'notnull' => 0, 'visible' => 1),
		'valor_bruto' => array('type' => 'price', 'label' => 'NFeGrossAmount', 'enabled' => '1', 'position' => 120, 'notnull' => 0, 'visible' => 1),
		'unidade_tributavel' => array('type' => 'varchar(6)', 'label' => 'Unit', 'enabled' => '1', 'position' => 130, 'notnull' => 0, 'visible' => -1),
		'quantidade_tributavel' => array('type' => 'double(24,8)', 'label' => 'Qty', 'enabled' => '1', 'position' => 131, 'notnull' => 0, 'visible' => -1),
		'valor_unitario_tributavel' => array('type' => 'double(24,10)', 'label' => 'PriceU', 'enabled' => '1', 'position' => 132, 'notnull' => 0, 'visible' => -1),
		'icms_origem' => array('type' => 'varchar(1)', 'label' => 'NFeIcmsOrigin', 'enabled' => '1', 'position' => 140, 'notnull' => 0, 'visible' => -1),
		'icms_situacao_tributaria' => array('type' => 'varchar(3)', 'label' => 'NFeIcmsTaxSituation', 'enabled' => '1', 'position' => 141, 'notnull' => 0, 'visible' => -1),
		'icms_aliquota' => array('type' => 'double(6,3)', 'label' => 'Rate', 'enabled' => '1', 'position' => 142, 'notnull' => 0, 'visible' => -1),
		'icms_valor' => array('type' => 'price', 'label' => 'Amount', 'enabled' => '1', 'position' => 143, 'notnull' => 0, 'visible' => -1),
		'pis_situacao_tributaria' => array('type' => 'varchar(2)', 'label' => 'NFePisTaxSituation', 'enabled' => '1', 'position' => 150, 'notnull' => 0, 'visible' => -1),
		'pis_aliquota' => array('type' => 'double(6,3)', 'label' => 'Rate', 'enabled' => '1', 'position' => 151, 'notnull' => 0, 'visible' => -1),
		'pis_valor' => array('type' => 'price', 'label' => 'Amount', 'enabled' => '1', 'position' => 152, 'notnull' => 0, 'visible' => -1),
		'cofins_situacao_tributaria' => array('type' => 'varchar(2)', 'label' => 'NFeCofinsTaxSituation', 'enabled' => '1', 'position' => 160, 'notnull' => 0, 'visible' => -1),
		'cofins_aliquota' => array('type' => 'double(6,3)', 'label' => 'Rate', 'enabled' => '1', 'position' => 161, 'notnull' => 0, 'visible' => -1),
		'cofins_valor' => array('type' => 'price', 'label' => 'Amount', 'enabled' => '1', 'position' => 162, 'notnull' => 0, 'visible' => -1),
		'informacoes_adicionais' => array('type' => 'text', 'label' => 'Note', 'enabled' => '1', 'position' => 170, 'notnull' => 0, 'visible' => -1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => '1', 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => '1', 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => '1', 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_nfe;
	public $position;
	public $numero_item;
	public $codigo_produto;
	public $descricao;
	public $ncm;
	public $cest;
	public $cfop;
	public $unidade_comercial;
	public $quantidade_comercial;
	public $valor_unitario_comercial;
	public $valor_bruto;
	public $unidade_tributavel;
	public $quantidade_tributavel;
	public $valor_unitario_tributavel;
	public $icms_origem;
	public $icms_situacao_tributaria;
	public $icms_aliquota;
	public $icms_valor;
	public $pis_situacao_tributaria;
	public $pis_aliquota;
	public $pis_valor;
	public $cofins_situacao_tributaria;
	public $cofins_aliquota;
	public $cofins_valor;
	public $informacoes_adicionais;
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
