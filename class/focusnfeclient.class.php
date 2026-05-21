<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file    class/focusnfeclient.class.php
 * \ingroup fiscal
 * \brief   HTTP client for Focus NFe API v2.
 */

/**
 * Isolated Focus NFe HTTP client. This class has no UI dependency and never
 * writes authentication tokens, certificate files, certificate passwords,
 * certificate base64 content or private keys to logs.
 */
class FocusNFeClient
{
	const ENV_HOMOLOGATION = 'homologation';
	const ENV_PRODUCTION = 'production';

	const BASE_HOMOLOGATION = 'https://homologacao.focusnfe.com.br';
	const BASE_PRODUCTION = 'https://api.focusnfe.com.br';

	/** @var DoliDB|null */
	protected $db;

	/** @var string */
	protected $token;

	/** @var string */
	protected $environment;

	/** @var string */
	protected $baseUrl;

	/** @var int */
	protected $timeout;

	/** @var array<string,mixed> */
	public $lastResponse = array();

	/**
	 * @param string $token Focus API token
	 * @param string $environment homologation or production
	 * @param DoliDB|null $db Optional Dolibarr database handler for audit logs
	 * @param int $timeout Request timeout in seconds
	 */
	public function __construct($token, $environment = self::ENV_HOMOLOGATION, $db = null, $timeout = 45)
	{
		$this->token = (string) $token;
		$this->environment = ($environment === self::ENV_PRODUCTION) ? self::ENV_PRODUCTION : self::ENV_HOMOLOGATION;
		$this->baseUrl = ($this->environment === self::ENV_PRODUCTION) ? self::BASE_PRODUCTION : self::BASE_HOMOLOGATION;
		$this->db = $db;
		$this->timeout = (int) $timeout;
	}

	/**
	 * Build a client from Dolibarr global constants.
	 *
	 * @param DoliDB|null $db Optional Dolibarr database handler
	 * @return self
	 */
	public static function fromDolibarrConfig($db = null)
	{
		return new self(
			getDolGlobalString('FISCAL_FOCUS_TOKEN'),
			getDolGlobalString('FISCAL_FOCUS_ENVIRONMENT', self::ENV_HOMOLOGATION),
			$db
		);
	}

	/**
	 * @param array<string,string|int> $filters cnpj/cpf/offset
	 * @return array<string,mixed>
	 */
	public function listCompanies(array $filters = array())
	{
		return $this->request('GET', '/v2/empresas', $filters, null, array('object_type' => 'focus_company'));
	}

	/**
	 * @param string|int $id Focus company id
	 * @return array<string,mixed>
	 */
	public function getCompany($id)
	{
		return $this->request('GET', '/v2/empresas/'.rawurlencode((string) $id), array(), null, array('object_type' => 'focus_company'));
	}

	/**
	 * @param string $cnpj CNPJ to consult in Focus NFe
	 * @return array<string,mixed>
	 */
	public function consultCnpj($cnpj)
	{
		return $this->request('GET', '/v2/cnpjs/'.rawurlencode(preg_replace('/\D/', '', $cnpj)), array(), null, array('object_type' => 'focus_cnpj'));
	}

	/**
	 * @param array<string,mixed> $payload Focus company payload
	 * @param bool $dryRun Use dry_run=1 before persisting
	 * @return array<string,mixed>
	 */
	public function createCompany(array $payload, $dryRun = true)
	{
		$query = $dryRun ? array('dry_run' => 1) : array();
		return $this->request('POST', '/v2/empresas', $query, $payload, array('object_type' => 'focus_company'));
	}

	/**
	 * @param string|int $id Focus company id
	 * @param array<string,mixed> $payload Focus company payload
	 * @param bool $dryRun Use dry_run=1 before persisting
	 * @return array<string,mixed>
	 */
	public function updateCompany($id, array $payload, $dryRun = true)
	{
		$query = $dryRun ? array('dry_run' => 1) : array();
		return $this->request('PUT', '/v2/empresas/'.rawurlencode((string) $id), $query, $payload, array('object_type' => 'focus_company'));
	}

	/**
	 * @param string $ref Unique Focus reference
	 * @param array<string,mixed> $payload NF-e payload
	 * @return array<string,mixed>
	 */
	public function emitNFe($ref, array $payload, $objectId = 0)
	{
		return $this->request('POST', '/v2/nfe', array('ref' => $ref), $payload, array('object_type' => 'nfe', 'object_id' => (int) $objectId));
	}

	/**
	 * @param string $ref Unique Focus reference
	 * @param bool $complete Return full Focus payload/protocol when available
	 * @return array<string,mixed>
	 */
	public function getNFe($ref, $complete = false, $objectId = 0)
	{
		$query = $complete ? array('completa' => 1) : array();
		return $this->request('GET', '/v2/nfe/'.rawurlencode($ref), $query, null, array('object_type' => 'nfe', 'object_id' => (int) $objectId));
	}

	/**
	 * Download XML/DANFE using the path returned by Focus.
	 *
	 * @param string $pathOrUrl Relative Focus path or absolute URL
	 * @param string $objectType Logged object type
	 * @param int $objectId Logged object id
	 * @return array<string,mixed>
	 */
	public function downloadDocument($pathOrUrl, $objectType = 'nfe', $objectId = 0)
	{
		if (!preg_match('/^https?:\/\//i', $pathOrUrl) && substr($pathOrUrl, 0, 1) !== '/') {
			$pathOrUrl = '/'.$pathOrUrl;
		}
		$result = $this->request('GET', $pathOrUrl, array(), null, array('object_type' => $objectType, 'object_id' => (int) $objectId), '*/*', $this->shouldSendAuth($pathOrUrl), false);
		if (in_array((int) $result['http_code'], array(301, 302, 303, 307, 308), true) && !empty($result['headers']['location'])) {
			$location = $result['headers']['location'];
			return $this->request('GET', $location, array(), null, array('object_type' => $objectType, 'object_id' => (int) $objectId), '*/*', $this->shouldSendAuth($location), false);
		}
		return $result;
	}

	/**
	 * @param string $cnpj Recipient CNPJ
	 * @param int $version Incremental version cursor
	 * @param array<string,string|int> $filters Extra filters such as pendente
	 * @return array<string,mixed>
	 */
	public function listReceivedNFes($cnpj, $version = 0, array $filters = array())
	{
		$query = array_merge($filters, array('cnpj' => preg_replace('/\D/', '', $cnpj)));
		if ((int) $version > 0) {
			$query['versao'] = (int) $version;
		}
		return $this->request('GET', '/v2/nfes_recebidas', $query, null, array('object_type' => 'nfe_received'));
	}

	/**
	 * @param string $accessKey NF-e access key
	 * @param bool $complete Return complete JSON when available
	 * @param int $objectId Local received NF-e id
	 * @return array<string,mixed>
	 */
	public function getReceivedNFeJson($accessKey, $complete = true, $objectId = 0)
	{
		$query = $complete ? array('completa' => 1) : array();
		return $this->request('GET', '/v2/nfes_recebidas/'.rawurlencode(preg_replace('/\D/', '', $accessKey)).'.json', $query, null, array('object_type' => 'nfe_received', 'object_id' => (int) $objectId));
	}

	/**
	 * @param string $accessKey NF-e access key
	 * @param int $objectId Local received NF-e id
	 * @return array<string,mixed>
	 */
	public function downloadReceivedNFeXml($accessKey, $objectId = 0)
	{
		return $this->downloadDocument('/v2/nfes_recebidas/'.rawurlencode(preg_replace('/\D/', '', $accessKey)).'.xml', 'nfe_received', (int) $objectId);
	}

	/**
	 * @param string $accessKey NF-e access key
	 * @param int $objectId Local received NF-e id
	 * @return array<string,mixed>
	 */
	public function downloadReceivedNFePdf($accessKey, $objectId = 0)
	{
		return $this->downloadDocument('/v2/nfes_recebidas/'.rawurlencode(preg_replace('/\D/', '', $accessKey)).'.pdf', 'nfe_received', (int) $objectId);
	}

	/**
	 * @param string $accessKey NF-e access key
	 * @param string $type ciencia, confirmacao, desconhecimento or nao_realizada
	 * @param string $justification Justification when required
	 * @param int $objectId Local received NF-e id
	 * @return array<string,mixed>
	 */
	public function manifestReceivedNFe($accessKey, $type, $justification = '', $objectId = 0)
	{
		$payload = array('tipo' => $type);
		if ($justification !== '') {
			$payload['justificativa'] = $justification;
		}
		return $this->request('POST', '/v2/nfes_recebidas/'.rawurlencode(preg_replace('/\D/', '', $accessKey)).'/manifesto', array(), $payload, array('object_type' => 'nfe_received', 'object_id' => (int) $objectId));
	}

	/**
	 * @param string $method HTTP method
	 * @param string $path API path beginning with /v2
	 * @param array<string,mixed> $query Query string parameters
	 * @param array<string,mixed>|null $payload JSON payload
	 * @param array<string,mixed> $logContext Log context object_type/object_id
	 * @return array<string,mixed>
	 */
	protected function request($method, $path, array $query = array(), $payload = null, array $logContext = array(), $accept = 'application/json', $useAuth = true, $logResponseBody = true)
	{
		$method = strtoupper($method);
		$url = preg_match('/^https?:\/\//i', $path) ? $path : $this->baseUrl.$path;
		$logPath = preg_match('/^https?:\/\//i', $path) ? (string) parse_url($path, PHP_URL_PATH) : $path;
		if ($logPath === '') {
			$logPath = '/download';
		}
		if (!empty($query)) {
			$url .= '?'.http_build_query($query, '', '&');
		}

		$headers = array('Accept: '.$accept);
		$body = null;
		if ($payload !== null) {
			$body = json_encode($payload);
			$headers[] = 'Content-Type: application/json';
		}

		$responseHeaders = array();
		$start = microtime(true);
		$raw = false;
		$httpCode = 0;
		$curlError = '';

		if ($useAuth && empty($this->token)) {
			$result = $this->normalizeResponse($method, $logPath, 0, '', array(), null, 'Focus token is not configured.', 0, $logResponseBody);
			$this->writeLog($method, $logPath, $payload, $result, $logContext);
			return $result;
		}

		if (!function_exists('curl_init')) {
			$result = $this->normalizeResponse($method, $logPath, 0, '', array(), null, 'PHP cURL extension is not installed.', 0, $logResponseBody);
			$this->writeLog($method, $logPath, $payload, $result, $logContext);
			return $result;
		}

		$ch = curl_init();
		if ($ch === false) {
			$result = $this->normalizeResponse($method, $logPath, 0, '', array(), null, 'Unable to initialize cURL.', 0, $logResponseBody);
			$this->writeLog($method, $logPath, $payload, $result, $logContext);
			return $result;
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		if ($useAuth) {
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($ch, CURLOPT_USERPWD, $this->token.':');
		}
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$responseHeaders) {
			$len = strlen($header);
			$header = trim($header);
			if ($header !== '' && strpos($header, ':') !== false) {
				list($name, $value) = explode(':', $header, 2);
				$responseHeaders[strtolower(trim($name))] = trim($value);
			}
			return $len;
		});

		if ($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}

		$raw = curl_exec($ch);
		if ($raw === false) {
			$curlError = curl_error($ch);
		}
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$durationMs = (int) round((microtime(true) - $start) * 1000);
		$result = $this->normalizeResponse($method, $logPath, $httpCode, ($raw === false ? '' : (string) $raw), $responseHeaders, $payload, $curlError, $durationMs, $logResponseBody);
		$this->writeLog($method, $logPath, $payload, $result, $logContext);
		$this->lastResponse = $result;

		return $result;
	}

	/**
	 * @param string $method HTTP method
	 * @param string $path API path
	 * @param int $httpCode HTTP code
	 * @param string $raw Raw response body
	 * @param array<string,string> $headers Response headers
	 * @param array<string,mixed>|null $payload Request payload
	 * @param string $transportError cURL or local error
	 * @param int $durationMs Duration in milliseconds
	 * @return array<string,mixed>
	 */
	protected function normalizeResponse($method, $path, $httpCode, $raw, array $headers, $payload, $transportError, $durationMs, $logResponseBody = true)
	{
		$data = null;
		if ($raw !== '') {
			$decoded = json_decode($raw, true);
			$data = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $raw;
		}

		$ok = ($httpCode >= 200 && $httpCode < 300 && empty($transportError));
		$errorMessage = $ok ? '' : $this->extractErrorMessage($httpCode, $data, $transportError);
		$requestSummary = $this->jsonSummary($this->sanitizeForLog($payload));
		$responseSummary = $logResponseBody ? $this->jsonSummary($this->sanitizeForLog($data)) : '[downloaded content omitted]';

		$result = array(
			'ok' => $ok,
			'method' => $method,
			'endpoint' => $path,
			'environment' => $this->environment,
			'http_code' => $httpCode,
			'status_focus' => $this->extractStatus($data, array('status', 'status_focus', 'codigo')),
			'status_sefaz' => $this->extractStatus($data, array('status_sefaz', 'codigo_sefaz')),
			'data' => $data,
			'headers' => $headers,
			'error' => $errorMessage,
			'user_message' => $ok ? '' : $this->userMessageForHttpCode($httpCode),
			'request_hash' => $requestSummary === '' ? '' : hash('sha256', $requestSummary),
			'request_summary' => $requestSummary,
			'response_summary' => $responseSummary,
			'duration_ms' => $durationMs,
		);

		return $result;
	}

	/**
	 * @param int $httpCode HTTP code
	 * @param mixed $data Decoded response body
	 * @param string $transportError cURL/local error
	 * @return string
	 */
	protected function extractErrorMessage($httpCode, $data, $transportError)
	{
		if ($transportError !== '') {
			return $transportError;
		}
		if (is_array($data)) {
			foreach (array('mensagem', 'message', 'erro', 'error', 'codigo', 'status') as $key) {
				if (!empty($data[$key]) && is_scalar($data[$key])) {
					return (string) $data[$key];
				}
			}
			if (!empty($data['erros']) && is_array($data['erros'])) {
				return $this->jsonSummary($this->sanitizeForLog($data['erros']));
			}
		}
		if (is_string($data) && $data !== '') {
			return dol_trunc($data, 500);
		}
		return 'HTTP '.$httpCode;
	}

	/**
	 * @param string $pathOrUrl Relative path or absolute URL
	 * @return bool
	 */
	protected function shouldSendAuth($pathOrUrl)
	{
		if (!preg_match('/^https?:\/\//i', $pathOrUrl)) {
			return true;
		}
		$base = parse_url($this->baseUrl);
		$url = parse_url($pathOrUrl);
		return !empty($base['host']) && !empty($url['host']) && strtolower($base['host']) === strtolower($url['host']);
	}

	/**
	 * @param int $httpCode HTTP code
	 * @return string
	 */
	protected function userMessageForHttpCode($httpCode)
	{
		$messages = array(
			0 => 'Falha local ao chamar a Focus NFe. Confira token, rede e extensao cURL.',
			400 => 'A Focus NFe recusou a requisicao como invalida. Revise os campos enviados.',
			401 => 'Token Focus nao autorizado. Confira o token do ambiente selecionado.',
			403 => 'Acao nao permitida pela Focus NFe. Confira habilitacoes da conta e da empresa.',
			404 => 'Recurso nao encontrado na Focus NFe. Confira referencia, chave ou ID informado.',
			415 => 'Formato de requisicao nao aceito pela Focus NFe. O cliente envia JSON.',
			422 => 'A Focus NFe entendeu a requisicao, mas os dados fiscais ou certificado nao foram aceitos.',
			429 => 'Limite de chamadas da Focus NFe atingido. Aguarde antes de tentar novamente.',
			500 => 'Erro interno na Focus NFe. Registre o contexto e tente novamente mais tarde.',
		);
		if (isset($messages[$httpCode])) {
			return $messages[$httpCode];
		}
		if ($httpCode >= 500) {
			return $messages[500];
		}
		return 'A Focus NFe retornou HTTP '.$httpCode.'. Confira o log tecnico.';
	}

	/**
	 * @param mixed $data Response payload
	 * @param string[] $keys Candidate keys
	 * @return string
	 */
	protected function extractStatus($data, array $keys)
	{
		if (!is_array($data)) {
			return '';
		}
		foreach ($keys as $key) {
			if (!empty($data[$key]) && is_scalar($data[$key])) {
				return (string) $data[$key];
			}
		}
		return '';
	}

	/**
	 * @param mixed $value Data to sanitize
	 * @return mixed
	 */
	public function sanitizeForLog($value)
	{
		if (is_array($value)) {
			$clean = array();
			foreach ($value as $key => $item) {
				if ($this->isSensitiveKey((string) $key)) {
					$clean[$key] = '[redacted]';
				} else {
					$clean[$key] = $this->sanitizeForLog($item);
				}
			}
			return $clean;
		}
		if (is_string($value) && strlen($value) > 2000) {
			return substr($value, 0, 2000).'...[truncated]';
		}
		return $value;
	}

	/**
	 * @param string $key Payload key
	 * @return bool
	 */
	protected function isSensitiveKey($key)
	{
		return (bool) preg_match('/token|senha|password|certificado|certificate|base64|private|chave_privada|key/i', $key);
	}

	/**
	 * @param mixed $value Value to summarize
	 * @return string
	 */
	protected function jsonSummary($value)
	{
		if ($value === null || $value === '') {
			return '';
		}
		$json = json_encode($value);
		if ($json === false) {
			$json = (string) $value;
		}
		return dol_trunc($json, 8000);
	}

	/**
	 * @param string $method HTTP method
	 * @param string $path Endpoint path
	 * @param array<string,mixed>|null $payload Request payload
	 * @param array<string,mixed> $result Normalized response
	 * @param array<string,mixed> $context Log context
	 * @return void
	 */
	protected function writeLog($method, $path, $payload, array $result, array $context)
	{
		global $conf, $user;

		if (!is_object($this->db)) {
			dol_syslog('Focus NFe '.$method.' '.$path.' HTTP '.$result['http_code'].' '.$result['error'], empty($result['ok']) ? LOG_WARNING : LOG_DEBUG);
			return;
		}

		$entity = is_object($conf) ? (int) $conf->entity : 1;
		$userId = is_object($user) ? (int) $user->id : 0;
		$objectType = empty($context['object_type']) ? '' : (string) $context['object_type'];
		$objectId = empty($context['object_id']) ? 0 : (int) $context['object_id'];

		$sql = 'INSERT INTO '.$this->db->prefix().'fiscal_focus_request_log (';
		$sql .= 'entity, environment, object_type, object_id, method, endpoint, http_code, focus_status, sefaz_status, request_hash, request_summary, response_summary, error_message, duration_ms, date_request, fk_user_creat';
		$sql .= ') VALUES (';
		$sql .= ((int) $entity).', ';
		$sql .= "'".$this->db->escape($this->environment)."', ";
		$sql .= ($objectType === '' ? 'NULL' : "'".$this->db->escape($objectType)."'").', ';
		$sql .= ($objectId > 0 ? (int) $objectId : 'NULL').', ';
		$sql .= "'".$this->db->escape($method)."', ";
		$sql .= "'".$this->db->escape($path)."', ";
		$sql .= ((int) $result['http_code']).', ';
		$sql .= ($result['status_focus'] === '' ? 'NULL' : "'".$this->db->escape($result['status_focus'])."'").', ';
		$sql .= ($result['status_sefaz'] === '' ? 'NULL' : "'".$this->db->escape($result['status_sefaz'])."'").', ';
		$sql .= ($result['request_hash'] === '' ? 'NULL' : "'".$this->db->escape($result['request_hash'])."'").', ';
		$sql .= ($result['request_summary'] === '' ? 'NULL' : "'".$this->db->escape($result['request_summary'])."'").', ';
		$sql .= ($result['response_summary'] === '' ? 'NULL' : "'".$this->db->escape($result['response_summary'])."'").', ';
		$sql .= ($result['error'] === '' ? 'NULL' : "'".$this->db->escape($result['error'])."'").', ';
		$sql .= ((int) $result['duration_ms']).', ';
		$sql .= "'".$this->db->idate(dol_now())."', ";
		$sql .= ($userId > 0 ? (int) $userId : 'NULL');
		$sql .= ')';

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog('Focus NFe log insert failed: '.$this->db->lasterror(), LOG_WARNING);
		}
	}
}
