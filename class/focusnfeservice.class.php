<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file    class/focusnfeservice.class.php
 * \ingroup fiscal
 * \brief   Focus NFe transmission and status synchronization service.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/focusnfeclient.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/focusnfepayloadbuilder.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/fiscalvalidator.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/focuscompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/nfe.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

/**
 * Application service for issued NF-e transmission.
 */
class FocusNFeService
{
	/** @var DoliDB */
	protected $db;

	/** @var FocusNFeClient */
	protected $client;

	/** @var FocusNFePayloadBuilder */
	protected $builder;

	/** @var FiscalValidator */
	protected $validator;

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
		$this->builder = new FocusNFePayloadBuilder($db);
		$this->validator = new FiscalValidator($db);
	}

	/**
	 * Build and validate payload without calling Focus.
	 *
	 * @param NFe $nfe NF-e
	 * @return array<string,mixed>
	 */
	public function previewPayload(NFe $nfe)
	{
		$emitente = $this->fetchEmitente($nfe);
		$destinatario = $this->fetchDestinatario($nfe);
		$validation = $this->validator->validateForTransmission($nfe, $emitente, $destinatario);
		$payload = $this->builder->build($nfe, $emitente, $destinatario);

		return array(
			'ok' => !empty($validation['ok']),
			'payload' => $payload,
			'errors' => $validation['errors'],
			'warnings' => $validation['warnings'],
		);
	}

	/**
	 * Transmit an NF-e to Focus.
	 *
	 * @param NFe $nfe NF-e
	 * @param User $user Current user
	 * @return array<string,mixed>
	 */
	public function transmit(NFe $nfe, User $user)
	{
		$this->errors = array();
		$preview = $this->previewPayload($nfe);
		if (empty($preview['ok'])) {
			$this->errors = $preview['errors'];
			return $this->result(false, 'Validacao fiscal pendente.', array('errors' => $preview['errors'], 'warnings' => $preview['warnings']));
		}

		$focusRef = $this->ensureFocusRef($nfe, $user);
		if ($focusRef === '') {
			return $this->result(false, implode(' ', $this->errors), array());
		}

		$response = $this->client->emitNFe($focusRef, $preview['payload'], (int) $nfe->id);
		$this->applyFocusResponse($nfe, $response, $user, true);

		if (empty($response['ok'])) {
			$message = empty($response['user_message']) ? $response['error'] : $response['user_message'];
			return $this->result(false, $message, array('focus' => $response));
		}

		return $this->result(true, 'NF-e enviada para a Focus.', array('focus' => $response));
	}

	/**
	 * Refresh Focus status for one NF-e.
	 *
	 * @param NFe $nfe NF-e
	 * @param User $user Current user
	 * @return array<string,mixed>
	 */
	public function refreshStatus(NFe $nfe, User $user)
	{
		$this->errors = array();
		if (empty($nfe->focus_ref)) {
			return $this->result(false, 'NF-e ainda nao possui referencia Focus.', array());
		}

		$response = $this->client->getNFe($nfe->focus_ref, true, (int) $nfe->id);
		$this->applyFocusResponse($nfe, $response, $user, false);

		if (empty($response['ok'])) {
			$message = empty($response['user_message']) ? $response['error'] : $response['user_message'];
			return $this->result(false, $message, array('focus' => $response));
		}

		return $this->result(true, 'Status Focus atualizado.', array('focus' => $response));
	}

	/**
	 * Cron helper to refresh pending transmitted NF-es.
	 *
	 * @param int $limit Max records
	 * @param User $user Current user
	 * @return array{processed:int,errors:array<int,string>}
	 */
	public function synchronizePending($limit, User $user)
	{
		global $conf;

		$processed = 0;
		$errors = array();
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'fiscal_nfe';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= ' AND status = '.((int) NFe::STATUS_TRANSMITTED);
		$sql .= " AND focus_ref IS NOT NULL AND focus_ref <> ''";
		$sql .= ' ORDER BY date_transmission ASC, rowid ASC';
		$sql .= $this->db->plimit(max(1, (int) $limit));
		$resql = $this->db->query($sql);
		if (!$resql) {
			return array('processed' => 0, 'errors' => array($this->db->lasterror()));
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$nfe = new NFe($this->db);
			if ($nfe->fetch((int) $obj->rowid) > 0) {
				$result = $this->refreshStatus($nfe, $user);
				$processed++;
				if (empty($result['ok'])) {
					$errors[] = $nfe->ref.': '.$result['message'];
				}
			}
		}
		$this->db->free($resql);

		return array('processed' => $processed, 'errors' => $errors);
	}

	/**
	 * @param NFe $nfe NF-e
	 * @param User $user Current user
	 * @return string
	 */
	protected function ensureFocusRef(NFe $nfe, User $user)
	{
		global $conf;

		if (!empty($nfe->focus_ref)) {
			return $nfe->focus_ref;
		}
		if (empty($nfe->id)) {
			$this->errors[] = 'NF-e precisa estar salva antes da transmissao.';
			return '';
		}

		$focusRef = 'NFE-'.$conf->entity.'-'.$nfe->id;
		$nfe->focus_ref = $focusRef;
		$nfe->focus_environment = getDolGlobalString('FISCAL_FOCUS_ENVIRONMENT', FocusNFeClient::ENV_HOMOLOGATION);

		$emitente = $this->fetchEmitente($nfe);
		if (is_object($emitente) && !empty($emitente->id)) {
			$nfe->fk_focus_company = $emitente->id;
		}

		if ($nfe->update($user, 1) <= 0) {
			$this->errors[] = $nfe->error;
			return '';
		}

		return $focusRef;
	}

	/**
	 * @param NFe $nfe NF-e
	 * @param array<string,mixed> $response Focus response
	 * @param User $user Current user
	 * @param bool $fromTransmission Whether this was POST /v2/nfe
	 * @return void
	 */
	protected function applyFocusResponse(NFe $nfe, array $response, User $user, $fromTransmission)
	{
		$data = empty($response['data']) || !is_array($response['data']) ? array() : $response['data'];
		$status = empty($data['status']) ? (empty($response['status_focus']) ? '' : $response['status_focus']) : (string) $data['status'];

		$nfe->http_code = (int) $response['http_code'];
		$nfe->request_hash = empty($response['request_hash']) ? $nfe->request_hash : $response['request_hash'];
		$nfe->request_summary = empty($response['request_summary']) ? $nfe->request_summary : $response['request_summary'];
		$nfe->response_summary = empty($response['response_summary']) ? $nfe->response_summary : $response['response_summary'];
		$nfe->status_focus = $status;
		$nfe->status_sefaz = empty($data['status_sefaz']) ? (empty($response['status_sefaz']) ? $nfe->status_sefaz : $response['status_sefaz']) : (string) $data['status_sefaz'];
		$nfe->mensagem_sefaz = $this->focusMessage($response, $data);

		if (!empty($data['chave_nfe'])) {
			$nfe->chave_nfe = preg_replace('/\D/', '', (string) $data['chave_nfe']);
		}
		if (!empty($data['numero'])) {
			$nfe->numero = (int) $data['numero'];
		}
		if (!empty($data['serie'])) {
			$nfe->serie = (int) $data['serie'];
		}
		if (!empty($data['protocolo'])) {
			$nfe->protocolo = (string) $data['protocolo'];
		} elseif (!empty($data['numero_protocolo'])) {
			$nfe->protocolo = (string) $data['numero_protocolo'];
		}

		if ($fromTransmission && empty($nfe->date_transmission) && !empty($response['ok'])) {
			$nfe->date_transmission = dol_now();
		}

		$this->applyStatus($nfe, $response, $status);
		$this->saveFocusJson($nfe, $data);
		$this->saveDocuments($nfe, $data, $user);

		$nfe->update($user, 1);
	}

	/**
	 * @param NFe $nfe NF-e
	 * @param array<string,mixed> $response Focus response
	 * @param string $status Focus status
	 * @return void
	 */
	protected function applyStatus(NFe $nfe, array $response, $status)
	{
		$status = strtolower((string) $status);
		if (!empty($response['ok'])) {
			if (in_array($status, array('autorizado', 'autorizada'), true) || (string) $nfe->status_sefaz === '100') {
				$nfe->status = NFe::STATUS_AUTHORIZED;
				if (empty($nfe->date_authorization)) {
					$nfe->date_authorization = dol_now();
				}
			} elseif (in_array($status, array('erro_autorizacao', 'rejeitado', 'rejeitada', 'denegado', 'denegada'), true)) {
				$nfe->status = NFe::STATUS_REJECTED;
			} elseif (in_array($status, array('cancelado', 'cancelada'), true)) {
				$nfe->status = NFe::STATUS_CANCELED;
			} elseif ($status !== '') {
				$nfe->status = NFe::STATUS_TRANSMITTED;
			}
		}
	}

	/**
	 * @param array<string,mixed> $response Focus response
	 * @param array<string,mixed> $data Focus data
	 * @return string
	 */
	protected function focusMessage(array $response, array $data)
	{
		foreach (array('mensagem_sefaz', 'mensagem', 'message', 'erro', 'error') as $key) {
			if (!empty($data[$key]) && is_scalar($data[$key])) {
				return (string) $data[$key];
			}
		}
		if (!empty($data['erros']) && is_array($data['erros'])) {
			$json = json_encode($data['erros']);
			return dol_trunc($json === false ? '' : $json, 1000);
		}
		if (!empty($response['error'])) {
			return (string) $response['error'];
		}
		return '';
	}

	/**
	 * @param NFe $nfe NF-e
	 * @param array<string,mixed> $data Focus data
	 * @return void
	 */
	protected function saveFocusJson(NFe $nfe, array $data)
	{
		if (empty($data)) {
			return;
		}
		$dir = $this->documentDir($nfe);
		if ($dir === '') {
			return;
		}
		$file = $dir.'/focus-'.dol_sanitizeFileName($nfe->focus_ref).'.json';
		@file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	}

	/**
	 * @param NFe $nfe NF-e
	 * @param array<string,mixed> $data Focus data
	 * @param User $user Current user
	 * @return void
	 */
	protected function saveDocuments(NFe $nfe, array $data, User $user)
	{
		$xmlPath = $this->firstDataPath($data, array('caminho_xml_nota_fiscal', 'caminho_xml'));
		$danfePath = $this->firstDataPath($data, array('caminho_danfe', 'caminho_pdf'));
		if ($xmlPath !== '') {
			$local = $this->downloadAndSave($nfe, $xmlPath, 'xml');
			$nfe->caminho_xml = $local === '' ? $xmlPath : $local;
		}
		if ($danfePath !== '') {
			$local = $this->downloadAndSave($nfe, $danfePath, 'danfe');
			$nfe->caminho_danfe = $local === '' ? $danfePath : $local;
			if ($local !== '') {
				$nfe->last_main_doc = $local;
			}
		}
	}

	/**
	 * @param array<string,mixed> $data Focus data
	 * @param string[] $keys Keys to inspect
	 * @return string
	 */
	protected function firstDataPath(array $data, array $keys)
	{
		foreach ($keys as $key) {
			if (!empty($data[$key]) && is_scalar($data[$key])) {
				return (string) $data[$key];
			}
		}
		return '';
	}

	/**
	 * @param NFe $nfe NF-e
	 * @param string $focusPath Relative path or URL returned by Focus
	 * @param string $fallbackPrefix Prefix when path has no filename
	 * @return string Relative local path
	 */
	protected function downloadAndSave(NFe $nfe, $focusPath, $fallbackPrefix)
	{
		$dir = $this->documentDir($nfe);
		if ($dir === '') {
			return '';
		}
		$response = $this->client->downloadDocument($focusPath, 'nfe', (int) $nfe->id);
		if (empty($response['ok']) || !is_string($response['data']) || $response['data'] === '') {
			return '';
		}

		$urlPath = parse_url($focusPath, PHP_URL_PATH);
		$filename = basename($urlPath ?: '');
		if ($filename === '' || $filename === '.' || $filename === '/') {
			$extension = $fallbackPrefix === 'xml' ? 'xml' : 'pdf';
			$filename = $fallbackPrefix.'-'.dol_sanitizeFileName($nfe->focus_ref).'.'.$extension;
		}
		$filename = dol_sanitizeFileName($filename);
		$fullpath = $dir.'/'.$filename;
		if (@file_put_contents($fullpath, $response['data']) === false) {
			return '';
		}

		return $nfe->element.'/'.dol_sanitizeFileName($nfe->ref).'/'.$filename;
	}

	/**
	 * @param NFe $nfe NF-e
	 * @return string Absolute document directory
	 */
	protected function documentDir(NFe $nfe)
	{
		global $conf;

		$root = empty($conf->fiscal->dir_output) ? DOL_DATA_ROOT.'/fiscal' : $conf->fiscal->dir_output;
		$ref = dol_sanitizeFileName(empty($nfe->ref) ? 'nfe-'.$nfe->id : $nfe->ref);
		$dir = $root.'/'.$nfe->element.'/'.$ref;
		if (dol_mkdir($dir) < 0) {
			return '';
		}
		return $dir;
	}

	/**
	 * @param NFe $nfe NF-e
	 * @return FocusCompany|null
	 */
	protected function fetchEmitente(NFe $nfe)
	{
		$company = new FocusCompany($this->db);
		if (!empty($nfe->fk_focus_company) && $company->fetch((int) $nfe->fk_focus_company) > 0) {
			return $company;
		}
		return ($company->fetchActive(getDolGlobalString('FISCAL_FOCUS_ENVIRONMENT', FocusNFeClient::ENV_HOMOLOGATION)) > 0) ? $company : null;
	}

	/**
	 * @param NFe $nfe NF-e
	 * @return Societe|null
	 */
	protected function fetchDestinatario(NFe $nfe)
	{
		if (empty($nfe->fk_soc)) {
			return null;
		}
		$soc = new Societe($this->db);
		return ($soc->fetch((int) $nfe->fk_soc) > 0) ? $soc : null;
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
