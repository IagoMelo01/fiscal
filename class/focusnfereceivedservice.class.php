<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file    class/focusnfereceivedservice.class.php
 * \ingroup fiscal
 * \brief   Received NF-e synchronization and manifestation service.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/focusnfeclient.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/focuscompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/focussynccursor.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/nfereceived.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/nfereceivedmanifestation.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

/**
 * Application service for NF-es issued against the company CNPJ.
 */
class FocusNFeReceivedService
{
	const CURSOR_SERVICE = 'nfe_received';

	/** @var DoliDB */
	protected $db;

	/** @var FocusNFeClient */
	protected $client;

	/** @var string[] */
	public $errors = array();

	/**
	 * @param DoliDB $db Database handler
	 * @param FocusNFeClient|null $client Focus client
	 */
	public function __construct($db, FocusNFeClient $client = null)
	{
		$this->db = $db;
		$this->client = $client ?: FocusNFeClient::fromDolibarrConfig($db);
	}

	/**
	 * Synchronize received NF-es using the incremental Focus version cursor.
	 *
	 * @param User $user Current user
	 * @param FocusCompany|null $company Optional company
	 * @return array<string,mixed>
	 */
	public function synchronize(User $user, FocusCompany $company = null)
	{
		$this->errors = array();
		if (!$company) {
			$company = $this->fetchActiveCompany();
		}
		if (!$company || empty($company->id)) {
			return $this->result(false, 'Nenhuma empresa Focus ativa encontrada.', array());
		}
		if (empty($company->habilita_manifestacao)) {
			return $this->result(false, 'Empresa Focus ativa nao esta habilitada para manifestacao.', array());
		}

		$cursor = $this->fetchOrCreateCursor($company, $user);
		$lastVersion = empty($cursor->last_version) ? 0 : (int) $cursor->last_version;
		$response = $this->client->listReceivedNFes($company->cnpj, $lastVersion);

		if (empty($response['ok'])) {
			$this->updateCursorError($cursor, empty($response['user_message']) ? $response['error'] : $response['user_message'], $user);
			return $this->result(false, empty($response['user_message']) ? $response['error'] : $response['user_message'], array('focus' => $response));
		}

		$rows = $this->extractRows($response['data']);
		$imported = 0;
		$maxVersion = $lastVersion;
		foreach ($rows as $row) {
			$id = $this->upsertReceived($row, $company, $user);
			if ($id > 0) {
				$imported++;
				$version = (int) $this->firstValue($row, array('versao', 'version'));
				if ($version > $maxVersion) {
					$maxVersion = $version;
				}
			}
		}

		if (!empty($response['headers']['x-max-version'])) {
			$maxVersion = max($maxVersion, (int) $response['headers']['x-max-version']);
		}
		$totalCount = empty($response['headers']['x-total-count']) ? count($rows) : (int) $response['headers']['x-total-count'];
		$this->updateCursorSuccess($cursor, $maxVersion, $totalCount, $user);

		return $this->result(true, 'Sincronizacao de NF-es recebidas concluida.', array(
			'imported' => $imported,
			'cursor' => $maxVersion,
			'total_count' => $totalCount,
			'focus' => $response,
		));
	}

	/**
	 * Download JSON/XML/PDF for a received NF-e.
	 *
	 * @param NFeReceived $received Received NF-e
	 * @param User $user Current user
	 * @return array<string,mixed>
	 */
	public function downloadDocuments(NFeReceived $received, User $user)
	{
		$saved = array();
		$json = $this->client->getReceivedNFeJson($received->chave_nfe, true, (int) $received->id);
		if (!empty($json['ok'])) {
			$path = $this->saveJson($received, $json['data']);
			if ($path !== '') {
				$saved[] = $path;
			}
			if (is_array($json['data'])) {
				$received->raw_json = json_encode($json['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				$received->nfe_completa = 1;
				$this->hydrateFromFocusRow($received, $json['data']);
			}
		}

		$xml = $this->client->downloadReceivedNFeXml($received->chave_nfe, (int) $received->id);
		if (!empty($xml['ok']) && is_string($xml['data']) && $xml['data'] !== '') {
			$path = $this->saveBinary($received, 'xml', $xml['data']);
			if ($path !== '') {
				$received->caminho_xml = $path;
				$saved[] = $path;
			}
		}

		$pdf = $this->client->downloadReceivedNFePdf($received->chave_nfe, (int) $received->id);
		if (!empty($pdf['ok']) && is_string($pdf['data']) && $pdf['data'] !== '') {
			$path = $this->saveBinary($received, 'pdf', $pdf['data']);
			if ($path !== '') {
				$received->caminho_pdf = $path;
				$saved[] = $path;
			}
		}

		$received->last_sync = dol_now();
		$received->update($user, 1);

		return $this->result(true, 'Documentos da NF-e recebida atualizados.', array('saved' => $saved, 'json' => $json, 'xml' => $xml, 'pdf' => $pdf));
	}

	/**
	 * Manifest a received NF-e.
	 *
	 * @param NFeReceived $received Received NF-e
	 * @param string $type Manifestation type
	 * @param string $justification Justification
	 * @param User $user Current user
	 * @return array<string,mixed>
	 */
	public function manifest(NFeReceived $received, $type, $justification, User $user)
	{
		$type = trim((string) $type);
		$justification = trim((string) $justification);
		$allowed = array('ciencia', 'confirmacao', 'desconhecimento', 'nao_realizada');
		if (!in_array($type, $allowed, true)) {
			return $this->result(false, 'Tipo de manifestacao invalido.', array());
		}
		if ($type === 'nao_realizada' && (dol_strlen($justification) < 15 || dol_strlen($justification) > 255)) {
			return $this->result(false, 'Operacao nao realizada exige justificativa entre 15 e 255 caracteres.', array());
		}

		$response = $this->client->manifestReceivedNFe($received->chave_nfe, $type, $justification, (int) $received->id);
		$this->saveManifestationHistory($received, $type, $justification, $response, $user);

		if (empty($response['ok'])) {
			return $this->result(false, empty($response['user_message']) ? $response['error'] : $response['user_message'], array('focus' => $response));
		}

		$data = is_array($response['data']) ? $response['data'] : array();
		$received->manifestacao_destinatario = $type;
		$received->situacao = empty($data['status']) ? $received->situacao : (string) $data['status'];
		$received->status = NFeReceived::STATUS_MANIFESTED;
		$received->last_sync = dol_now();
		if (!empty($data)) {
			$received->raw_json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}
		$received->update($user, 1);

		return $this->result(true, 'Manifestacao registrada.', array('focus' => $response));
	}

	/**
	 * @return FocusCompany|null
	 */
	protected function fetchActiveCompany()
	{
		$company = new FocusCompany($this->db);
		return ($company->fetchActive(getDolGlobalString('FISCAL_FOCUS_ENVIRONMENT', FocusNFeClient::ENV_HOMOLOGATION)) > 0) ? $company : null;
	}

	/**
	 * @param FocusCompany $company Focus company
	 * @param User $user Current user
	 * @return FocusSyncCursor
	 */
	protected function fetchOrCreateCursor(FocusCompany $company, User $user)
	{
		$cursor = new FocusSyncCursor($this->db);
		if ($cursor->fetchByKey($company->environment, $company->cnpj, self::CURSOR_SERVICE) > 0) {
			return $cursor;
		}
		$cursor->fk_focus_company = $company->id;
		$cursor->environment = $company->environment;
		$cursor->cnpj = preg_replace('/\D/', '', (string) $company->cnpj);
		$cursor->service = self::CURSOR_SERVICE;
		$cursor->last_version = 0;
		$cursor->last_total_count = 0;
		$cursor->create($user);
		return $cursor;
	}

	/**
	 * @param FocusSyncCursor $cursor Cursor
	 * @param int $maxVersion Version
	 * @param int $totalCount Count
	 * @param User $user Current user
	 * @return void
	 */
	protected function updateCursorSuccess(FocusSyncCursor $cursor, $maxVersion, $totalCount, User $user)
	{
		$cursor->last_version = (int) $maxVersion;
		$cursor->last_total_count = (int) $totalCount;
		$cursor->last_sync = dol_now();
		$cursor->last_error = '';
		$cursor->update($user, 1);
	}

	/**
	 * @param FocusSyncCursor $cursor Cursor
	 * @param string $error Error
	 * @param User $user Current user
	 * @return void
	 */
	protected function updateCursorError(FocusSyncCursor $cursor, $error, User $user)
	{
		$cursor->last_sync = dol_now();
		$cursor->last_error = dol_trunc($error, 1000);
		$cursor->update($user, 1);
	}

	/**
	 * @param mixed $data Response data
	 * @return array<int,array<string,mixed>>
	 */
	protected function extractRows($data)
	{
		if (!is_array($data)) {
			return array();
		}
		if (isset($data[0]) && is_array($data[0])) {
			return $data;
		}
		foreach (array('notas', 'nfes', 'data', 'items') as $key) {
			if (!empty($data[$key]) && is_array($data[$key])) {
				return $data[$key];
			}
		}
		return array();
	}

	/**
	 * @param array<string,mixed> $row Focus row
	 * @param FocusCompany $company Focus company
	 * @param User $user Current user
	 * @return int
	 */
	protected function upsertReceived(array $row, FocusCompany $company, User $user)
	{
		$key = preg_replace('/\D/', '', (string) $this->firstValue($row, array('chave_nfe', 'chave', 'chave_acesso')));
		if (dol_strlen($key) !== 44) {
			return 0;
		}
		$received = new NFeReceived($this->db);
		$fetched = $received->fetchByAccessKey($key, $company->cnpj);
		$received->fk_focus_company = $company->id;
		$received->environment = $company->environment;
		$received->chave_nfe = $key;
		$received->cnpj_destinatario = preg_replace('/\D/', '', (string) $company->cnpj);
		$this->hydrateFromFocusRow($received, $row);
		$received->raw_json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$received->status = empty($received->manifestacao_destinatario) ? NFeReceived::STATUS_NEW : NFeReceived::STATUS_MANIFESTED;
		$received->last_sync = dol_now();
		$result = ($fetched > 0) ? $received->update($user, 1) : $received->create($user, 1);
		return $result > 0 ? (int) $received->id : 0;
	}

	/**
	 * @param NFeReceived $received Local object
	 * @param array<string,mixed> $row Focus row
	 * @return void
	 */
	protected function hydrateFromFocusRow(NFeReceived $received, array $row)
	{
		$received->documento_emitente = preg_replace('/\D/', '', (string) $this->firstValue($row, array('cnpj_emitente', 'cpf_emitente', 'documento_emitente', 'cnpj_cpf_emitente')));
		$received->nome_emitente = (string) $this->firstValue($row, array('nome_emitente', 'razao_social_emitente', 'emitente'));
		$received->ie_emitente = (string) $this->firstValue($row, array('ie_emitente', 'inscricao_estadual_emitente'));
		$received->uf_emitente = strtoupper((string) $this->firstValue($row, array('uf_emitente', 'uf')));
		$received->valor_total = price2num($this->firstValue($row, array('valor_total', 'valor_nota', 'total')));
		$received->data_emissao = $this->dateValue($this->firstValue($row, array('data_emissao', 'data_emissao_nfe')));
		$received->data_recebimento = $this->dateValue($this->firstValue($row, array('data_recebimento', 'data_autorizacao', 'data_importacao')));
		$received->situacao = (string) $this->firstValue($row, array('situacao', 'status', 'status_nfe'));
		$received->manifestacao_destinatario = (string) $this->firstValue($row, array('manifestacao_destinatario', 'manifestacao', 'tipo_manifestacao'));
		$received->nfe_completa = $this->truthy($this->firstValue($row, array('nfe_completa', 'completa')));
		$received->tipo_nfe = (string) $this->firstValue($row, array('tipo_nfe', 'tipo'));
		$received->versao = (int) $this->firstValue($row, array('versao', 'version'));
		$received->digest_value = (string) $this->firstValue($row, array('digest_value', 'digest'));
	}

	/**
	 * @param NFeReceived $received Received NF-e
	 * @param string $type Manifestation type
	 * @param string $justification Justification
	 * @param array<string,mixed> $response Focus response
	 * @param User $user Current user
	 * @return void
	 */
	protected function saveManifestationHistory(NFeReceived $received, $type, $justification, array $response, User $user)
	{
		$data = is_array($response['data']) ? $response['data'] : array();
		$history = new NFeReceivedManifestation($this->db);
		$history->fk_nfe_received = $received->id;
		$history->chave_nfe = $received->chave_nfe;
		$history->tipo = $type;
		$history->justificativa = $justification;
		$history->status_focus = empty($data['status']) ? (empty($response['status_focus']) ? '' : $response['status_focus']) : (string) $data['status'];
		$history->status_sefaz = empty($data['status_sefaz']) ? (empty($response['status_sefaz']) ? '' : $response['status_sefaz']) : (string) $data['status_sefaz'];
		$history->mensagem_sefaz = (string) $this->firstValue($data, array('mensagem_sefaz', 'mensagem', 'message'));
		$history->protocolo = (string) $this->firstValue($data, array('protocolo', 'numero_protocolo'));
		$history->data_manifesto = $this->dateValue($this->firstValue($data, array('data_manifesto', 'data_evento'))) ?: dol_now();
		$history->request_hash = empty($response['request_hash']) ? '' : $response['request_hash'];
		$history->raw_response = json_encode($response['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$history->create($user, 1);
	}

	/**
	 * @param NFeReceived $received Received NF-e
	 * @param mixed $data JSON data
	 * @return string Relative path
	 */
	protected function saveJson(NFeReceived $received, $data)
	{
		$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return $this->saveBinary($received, 'json', $json === false ? '' : $json);
	}

	/**
	 * @param NFeReceived $received Received NF-e
	 * @param string $extension File extension
	 * @param string $content File content
	 * @return string Relative path
	 */
	protected function saveBinary(NFeReceived $received, $extension, $content)
	{
		if ($content === '') {
			return '';
		}
		$dir = $this->documentDir($received);
		if ($dir === '') {
			return '';
		}
		$filename = dol_sanitizeFileName($received->chave_nfe).'.'.$extension;
		if (@file_put_contents($dir.'/'.$filename, $content) === false) {
			return '';
		}
		return 'received/'.$received->chave_nfe.'/'.$filename;
	}

	/**
	 * @param NFeReceived $received Received NF-e
	 * @return string Absolute directory
	 */
	protected function documentDir(NFeReceived $received)
	{
		global $conf;

		$root = empty($conf->fiscal->dir_output) ? DOL_DATA_ROOT.'/fiscal' : $conf->fiscal->dir_output;
		$dir = $root.'/received/'.dol_sanitizeFileName($received->chave_nfe);
		return dol_mkdir($dir) < 0 ? '' : $dir;
	}

	/**
	 * @param array<string,mixed> $row Row
	 * @param string[] $keys Keys
	 * @return mixed
	 */
	protected function firstValue(array $row, array $keys)
	{
		foreach ($keys as $key) {
			if (isset($row[$key]) && $row[$key] !== '') {
				return $row[$key];
			}
		}
		return '';
	}

	/**
	 * @param mixed $value Date value
	 * @return int|string|null
	 */
	protected function dateValue($value)
	{
		if ($value === '' || $value === null) {
			return null;
		}
		$time = is_numeric($value) ? (int) $value : dol_stringtotime((string) $value);
		return $time > 0 ? $time : null;
	}

	/**
	 * @param mixed $value Value
	 * @return int
	 */
	protected function truthy($value)
	{
		return in_array($value, array(1, '1', true, 'true', 'sim', 'yes'), true) ? 1 : 0;
	}

	/**
	 * @param bool $ok Result status
	 * @param string $message Message
	 * @param array<string,mixed> $extra Extra data
	 * @return array<string,mixed>
	 */
	protected function result($ok, $message, array $extra)
	{
		return array_merge(array('ok' => (bool) $ok, 'message' => $message), $extra);
	}
}
