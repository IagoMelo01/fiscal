<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file    class/focuscompanyservice.class.php
 * \ingroup fiscal
 * \brief   Focus company orchestration without UI dependencies.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/focusnfeclient.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/focuscompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/focuscertificatesubmission.class.php';

/**
 * Orchestrates Focus company lookup, dry-run, submission and local metadata.
 */
class FocusCompanyService
{
	/** @var DoliDB */
	protected $db;

	/** @var FocusNFeClient */
	protected $client;

	/** @var string[] */
	public $errors = array();

	/**
	 * @param DoliDB $db Database handler
	 * @param FocusNFeClient $client Focus client
	 */
	public function __construct($db, FocusNFeClient $client)
	{
		$this->db = $db;
		$this->client = $client;
	}

	/**
	 * Consult CNPJ and existing Focus company records.
	 *
	 * @param string $cnpj CNPJ
	 * @return array<string,mixed>
	 */
	public function lookupCompany($cnpj)
	{
		$cnpj = $this->digits($cnpj);
		if (!$this->isValidCnpj($cnpj)) {
			return $this->result(false, 'CNPJ invalido.', array());
		}

		$cnpjResult = $this->client->consultCnpj($cnpj);
		$companiesResult = $this->client->listCompanies(array('cnpj' => $cnpj));
		$ok = !empty($cnpjResult['ok']) || !empty($companiesResult['ok']);
		$message = $ok ? 'Consulta Focus executada.' : $this->firstError(array($cnpjResult, $companiesResult));

		return $this->result($ok, $message, array(
			'cnpj' => $cnpjResult,
			'companies' => $companiesResult,
		));
	}

	/**
	 * Submit company to Focus. Final submission always runs dry_run first.
	 *
	 * @param array<string,mixed> $input Form/domain input
	 * @param array<string,mixed>|null $uploadedFile PHP uploaded file array
	 * @param string $certificatePassword Certificate password used only in memory
	 * @param User $user Current user
	 * @param bool $finalSubmit Whether to send the effective POST/PUT after dry-run
	 * @param bool $setActive Whether to mark the local company active after success
	 * @return array<string,mixed>
	 */
	public function submitCompany(array $input, $uploadedFile, $certificatePassword, User $user, $finalSubmit = false, $setActive = false)
	{
		global $conf;

		$this->errors = array();
		$environment = getDolGlobalString('FISCAL_FOCUS_ENVIRONMENT', FocusNFeClient::ENV_HOMOLOGATION);
		$cnpj = $this->digits(empty($input['cnpj']) ? '' : $input['cnpj']);
		$focusId = trim((string) (empty($input['focus_id']) ? '' : $input['focus_id']));

		if (empty(getDolGlobalString('FISCAL_FOCUS_TOKEN'))) {
			$this->errors[] = 'Token Focus nao configurado.';
		}
		if (!$this->isValidCnpj($cnpj)) {
			$this->errors[] = 'CNPJ invalido.';
		}
		if (empty($input['name'])) {
			$this->errors[] = 'Razao social obrigatoria.';
		}
		if (!$uploadedFile || empty($uploadedFile['tmp_name']) || !empty($uploadedFile['error'])) {
			$this->errors[] = 'Certificado A1 (.pfx/.p12) obrigatorio para validar e enviar empresa.';
		}
		if ($certificatePassword === '') {
			$this->errors[] = 'Senha do certificado obrigatoria para validacao transitoria.';
		}

		if (!empty($this->errors)) {
			return $this->result(false, implode(' ', $this->errors), array());
		}

		$certificate = $this->readCertificateMetadata($uploadedFile, $certificatePassword, $cnpj);
		if (!$certificate['ok']) {
			return $this->result(false, $certificate['message'], array());
		}

		$payload = $this->buildPayload($input, $certificate['content'], $certificatePassword);
		$existing = null;
		if ($focusId === '') {
			$list = $this->client->listCompanies(array('cnpj' => $cnpj));
			$focusId = $this->extractCompanyId($list);
			$existing = $list;
		}

		$dryRun = ($focusId !== '') ? $this->client->updateCompany($focusId, $payload, true) : $this->client->createCompany($payload, true);
		$localCompanyId = 0;
		$certificateId = 0;
		$message = empty($dryRun['ok']) ? $this->firstError(array($dryRun)) : 'Dry-run Focus executado com sucesso.';

		if (!empty($dryRun['ok'])) {
			$localCompanyId = $this->upsertLocalCompany($input, $focusId, $environment, 'dry_run_ok', $dryRun, $user);
			$certificateId = $this->saveCertificateMetadata($localCompanyId, $environment, $certificate['metadata'], 'dry_run_ok', $dryRun, $user);
		}

		$final = null;
		if (!empty($dryRun['ok']) && $finalSubmit) {
			$final = ($focusId !== '') ? $this->client->updateCompany($focusId, $payload, false) : $this->client->createCompany($payload, false);
			if (!empty($final['ok'])) {
				$newFocusId = $this->extractCompanyId($final);
				if ($newFocusId !== '') {
					$focusId = $newFocusId;
				}
				$localCompanyId = $this->upsertLocalCompany($input, $focusId, $environment, 'submitted', $final, $user);
				$certificateId = $this->saveCertificateMetadata($localCompanyId, $environment, $certificate['metadata'], 'submitted', $final, $user);
				if ($certificateId > 0 && $localCompanyId > 0) {
					$this->setCompanyCertificate($localCompanyId, $certificateId, $user);
				}
				if ($setActive && $localCompanyId > 0) {
					$this->setActiveCompany($localCompanyId, $user);
				}
				$message = 'Empresa enviada para a Focus com sucesso.';
			} else {
				$message = $this->firstError(array($final));
			}
		}

		unset($payload['arquivo_certificado_base64'], $payload['certificado'], $payload['senha_certificado'], $certificatePassword, $certificate['content']);

		return $this->result(!empty($dryRun['ok']) && (!$finalSubmit || !empty($final['ok'])), $message, array(
			'dry_run' => $dryRun,
			'final' => $final,
			'existing' => $existing,
			'company_id' => $localCompanyId,
			'certificate_id' => $certificateId,
		));
	}

	/**
	 * Mark one local Focus company active for the current entity/environment.
	 *
	 * @param int $id Local company id
	 * @param User $user Current user
	 * @return bool
	 */
	public function setActiveCompany($id, User $user)
	{
		global $conf;

		$company = new FocusCompany($this->db);
		if ($company->fetch($id) <= 0) {
			$this->errors[] = 'Empresa local nao encontrada.';
			return false;
		}

		$this->db->begin();
		$sql = 'UPDATE '.$this->db->prefix().'fiscal_focus_company';
		$sql .= ' SET active = 0, fk_user_modif = '.((int) $user->id);
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= " AND environment = '".$this->db->escape($company->environment)."'";
		if (!$this->db->query($sql)) {
			$this->errors[] = $this->db->lasterror();
			$this->db->rollback();
			return false;
		}

		$company->active = 1;
		$company->status = 'active';
		$result = $company->update($user);
		if ($result <= 0) {
			$this->errors[] = $company->error;
			$this->db->rollback();
			return false;
		}

		$this->db->commit();
		return true;
	}

	/**
	 * Fetch local Focus companies.
	 *
	 * @return FocusCompany[]
	 */
	public function fetchLocalCompanies()
	{
		global $conf;

		$companies = array();
		$sql = 'SELECT rowid FROM '.$this->db->prefix().'fiscal_focus_company';
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$sql .= ' ORDER BY active DESC, tms DESC, rowid DESC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->errors[] = $this->db->lasterror();
			return $companies;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$company = new FocusCompany($this->db);
			if ($company->fetch((int) $obj->rowid) > 0) {
				$companies[] = $company;
			}
		}
		$this->db->free($resql);

		return $companies;
	}

	/**
	 * @param array<string,mixed> $input Company input
	 * @param string $certificateContent Raw certificate content
	 * @param string $certificatePassword Certificate password
	 * @return array<string,mixed>
	 */
	protected function buildPayload(array $input, $certificateContent, $certificatePassword)
	{
		$payload = array(
			'cnpj' => $this->digits(empty($input['cnpj']) ? '' : $input['cnpj']),
			'razao_social' => trim((string) $input['name']),
			'nome_fantasia' => trim((string) (empty($input['nome_fantasia']) ? $input['name'] : $input['nome_fantasia'])),
			'inscricao_estadual' => trim((string) (empty($input['inscricao_estadual']) ? '' : $input['inscricao_estadual'])),
			'regime_tributario' => trim((string) (empty($input['regime_tributario']) ? '' : $input['regime_tributario'])),
			'uf' => strtoupper(trim((string) (empty($input['uf']) ? '' : $input['uf']))),
			'municipio' => trim((string) (empty($input['municipio']) ? '' : $input['municipio'])),
			'habilita_nfe' => true,
			'habilita_manifestacao' => true,
			'arquivo_certificado_base64' => base64_encode($certificateContent),
			'senha_certificado' => $certificatePassword,
		);

		$startDate = trim((string) (empty($input['data_inicio_recebimento_nfe']) ? '' : $input['data_inicio_recebimento_nfe']));
		if ($startDate !== '') {
			$payload['data_inicio_recebimento_nfe'] = $startDate;
		}

		foreach ($payload as $key => $value) {
			if ($value === '') {
				unset($payload[$key]);
			}
		}

		return $payload;
	}

	/**
	 * @param array<string,mixed> $uploadedFile PHP uploaded file
	 * @param string $password Certificate password
	 * @param string $expectedCnpj Expected CNPJ
	 * @return array<string,mixed>
	 */
	protected function readCertificateMetadata($uploadedFile, $password, $expectedCnpj)
	{
		if (!function_exists('openssl_pkcs12_read') || !function_exists('openssl_x509_parse')) {
			return $this->result(false, 'Extensao OpenSSL do PHP nao esta disponivel para validar certificado A1.', array());
		}

		$name = empty($uploadedFile['name']) ? '' : (string) $uploadedFile['name'];
		$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		if (!in_array($extension, array('pfx', 'p12'), true)) {
			return $this->result(false, 'Envie um certificado A1 nos formatos .pfx ou .p12.', array());
		}

		$tmpName = (string) $uploadedFile['tmp_name'];
		$content = file_get_contents($tmpName);
		if ($content === false || $content === '') {
			return $this->result(false, 'Nao foi possivel ler o certificado enviado.', array());
		}

		$certs = array();
		if (!openssl_pkcs12_read($content, $certs, $password) || empty($certs['cert'])) {
			unset($content, $certs);
			return $this->result(false, 'Certificado ou senha nao aceitos pelo OpenSSL.', array());
		}

		$parsed = openssl_x509_parse($certs['cert']);
		if (!is_array($parsed)) {
			unset($content, $certs);
			return $this->result(false, 'Nao foi possivel extrair metadados do certificado.', array());
		}

		$metadata = array(
			'hash' => hash('sha256', $content),
			'subject_name' => $this->arrayName(empty($parsed['subject']) ? array() : $parsed['subject']),
			'issuer_name' => $this->arrayName(empty($parsed['issuer']) ? array() : $parsed['issuer']),
			'serial_number' => empty($parsed['serialNumberHex']) ? (string) (empty($parsed['serialNumber']) ? '' : $parsed['serialNumber']) : (string) $parsed['serialNumberHex'],
			'cnpj_certificado' => $this->extractCnpj($parsed, $certs['cert']),
			'valid_from' => empty($parsed['validFrom_time_t']) ? null : (int) $parsed['validFrom_time_t'],
			'valid_to' => empty($parsed['validTo_time_t']) ? null : (int) $parsed['validTo_time_t'],
		);

		if (!empty($metadata['valid_to']) && $metadata['valid_to'] < dol_now()) {
			unset($content, $certs);
			return $this->result(false, 'Certificado expirado.', array('metadata' => $metadata));
		}

		if (!empty($metadata['cnpj_certificado']) && $metadata['cnpj_certificado'] !== $expectedCnpj) {
			unset($content, $certs);
			return $this->result(false, 'CNPJ do certificado nao confere com o CNPJ informado.', array('metadata' => $metadata));
		}

		unset($certs);
		return array(
			'ok' => true,
			'message' => '',
			'content' => $content,
			'metadata' => $metadata,
		);
	}

	/**
	 * @param array<string,mixed> $input Form/domain input
	 * @param string $focusId Focus company id
	 * @param string $environment Focus environment
	 * @param string $status Local status
	 * @param array<string,mixed> $focusResult Focus response
	 * @param User $user Current user
	 * @return int
	 */
	protected function upsertLocalCompany(array $input, $focusId, $environment, $status, array $focusResult, User $user)
	{
		$document = $this->digits(empty($input['cnpj']) ? '' : $input['cnpj']);
		$company = new FocusCompany($this->db);
		$fetched = $company->fetchByDocument($document, $environment);

		$company->environment = $environment;
		$company->focus_id = $focusId;
		$company->cnpj = $document;
		$company->name = trim((string) $input['name']);
		$company->nome_fantasia = trim((string) (empty($input['nome_fantasia']) ? '' : $input['nome_fantasia']));
		$company->inscricao_estadual = trim((string) (empty($input['inscricao_estadual']) ? '' : $input['inscricao_estadual']));
		$company->regime_tributario = trim((string) (empty($input['regime_tributario']) ? '' : $input['regime_tributario']));
		$company->uf = strtoupper(trim((string) (empty($input['uf']) ? '' : $input['uf'])));
		$company->municipio = trim((string) (empty($input['municipio']) ? '' : $input['municipio']));
		$company->habilita_nfe = 1;
		$company->habilita_manifestacao = 1;
		$company->data_inicio_recebimento_nfe = $this->dateToDb(empty($input['data_inicio_recebimento_nfe']) ? '' : $input['data_inicio_recebimento_nfe']);
		$company->status = $status;
		$company->focus_status = empty($focusResult['status_focus']) ? '' : $focusResult['status_focus'];
		$company->focus_message = empty($focusResult['error']) ? $this->summaryFromData(empty($focusResult['data']) ? null : $focusResult['data']) : $focusResult['error'];
		$company->last_sync = dol_now();

		$result = ($fetched > 0) ? $company->update($user) : $company->create($user);
		if ($result <= 0) {
			$this->errors[] = $company->error;
			return 0;
		}

		return (int) $company->id;
	}

	/**
	 * @param int $companyId Local company id
	 * @param string $environment Focus environment
	 * @param array<string,mixed> $metadata Certificate metadata
	 * @param string $status Local status
	 * @param array<string,mixed> $focusResult Focus response
	 * @param User $user Current user
	 * @return int
	 */
	protected function saveCertificateMetadata($companyId, $environment, array $metadata, $status, array $focusResult, User $user)
	{
		if ($companyId <= 0) {
			return 0;
		}

		$certificate = new FocusCertificateSubmission($this->db);
		$certificate->fk_focus_company = $companyId;
		$certificate->environment = $environment;
		$certificate->certificate_hash = empty($metadata['hash']) ? '' : $metadata['hash'];
		$certificate->subject_name = empty($metadata['subject_name']) ? '' : $metadata['subject_name'];
		$certificate->issuer_name = empty($metadata['issuer_name']) ? '' : $metadata['issuer_name'];
		$certificate->serial_number = empty($metadata['serial_number']) ? '' : $metadata['serial_number'];
		$certificate->cnpj_certificado = empty($metadata['cnpj_certificado']) ? '' : $metadata['cnpj_certificado'];
		$certificate->valid_from = empty($metadata['valid_from']) ? null : (int) $metadata['valid_from'];
		$certificate->valid_to = empty($metadata['valid_to']) ? null : (int) $metadata['valid_to'];
		$certificate->status = $status;
		$certificate->focus_message = empty($focusResult['error']) ? $this->summaryFromData(empty($focusResult['data']) ? null : $focusResult['data']) : $focusResult['error'];

		$result = $certificate->create($user);
		if ($result <= 0) {
			$this->errors[] = $certificate->error;
			return 0;
		}

		return (int) $certificate->id;
	}

	/**
	 * @param int $companyId Company id
	 * @param int $certificateId Certificate metadata id
	 * @param User $user Current user
	 * @return void
	 */
	protected function setCompanyCertificate($companyId, $certificateId, User $user)
	{
		$company = new FocusCompany($this->db);
		if ($company->fetch($companyId) <= 0) {
			return;
		}
		$company->fk_certificate_active = $certificateId;
		$company->update($user);
	}

	/**
	 * @param array<string,mixed> $result Focus response or list response
	 * @return string
	 */
	protected function extractCompanyId(array $result)
	{
		if (empty($result['data'])) {
			return '';
		}
		$data = $result['data'];
		if (is_array($data)) {
			foreach (array('id', 'codigo', 'focus_id', 'empresa_id') as $key) {
				if (!empty($data[$key]) && is_scalar($data[$key])) {
					return (string) $data[$key];
				}
			}
			foreach ($data as $row) {
				if (is_array($row)) {
					foreach (array('id', 'codigo', 'focus_id', 'empresa_id') as $key) {
						if (!empty($row[$key]) && is_scalar($row[$key])) {
							return (string) $row[$key];
						}
					}
				}
			}
		}
		return '';
	}

	/**
	 * @param array<string,mixed> $parsed Parsed x509 data
	 * @param string $certPem Public certificate PEM
	 * @return string
	 */
	protected function extractCnpj(array $parsed, $certPem)
	{
		$text = json_encode($parsed).' '.$certPem;
		if (preg_match_all('/\D(\d{14})\D/', ' '.$text.' ', $matches)) {
			foreach ($matches[1] as $candidate) {
				if ($this->isValidCnpj($candidate)) {
					return $candidate;
				}
			}
		}
		return '';
	}

	/**
	 * @param array<string,mixed> $parts Certificate DN parts
	 * @return string
	 */
	protected function arrayName(array $parts)
	{
		$values = array();
		foreach ($parts as $key => $value) {
			if (is_array($value)) {
				$value = implode(', ', $value);
			}
			$values[] = $key.'='.$value;
		}
		return dol_trunc(implode(', ', $values), 255);
	}

	/**
	 * @param mixed $value Data to summarize
	 * @return string
	 */
	protected function summaryFromData($value)
	{
		if ($value === null || $value === '') {
			return '';
		}
		$json = json_encode($value);
		return dol_trunc($json === false ? (string) $value : $json, 1000);
	}

	/**
	 * @param string $date YYYY-MM-DD
	 * @return int|string|null
	 */
	protected function dateToDb($date)
	{
		$date = trim($date);
		if ($date === '') {
			return null;
		}
		$time = dol_stringtotime($date);
		return $time > 0 ? $time : null;
	}

	/**
	 * @param string $value Raw value
	 * @return string
	 */
	protected function digits($value)
	{
		return preg_replace('/\D/', '', (string) $value);
	}

	/**
	 * @param string $cnpj CNPJ digits
	 * @return bool
	 */
	protected function isValidCnpj($cnpj)
	{
		$cnpj = $this->digits($cnpj);
		if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
			return false;
		}

		for ($t = 12; $t < 14; $t++) {
			$d = 0;
			$p = $t - 7;
			for ($c = 0; $c < $t; $c++) {
				$d += (int) $cnpj[$c] * $p;
				$p = ($p == 2) ? 9 : $p - 1;
			}
			$d = ((10 * $d) % 11) % 10;
			if ((int) $cnpj[$t] !== $d) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array<int,array<string,mixed>> $responses Focus responses
	 * @return string
	 */
	protected function firstError(array $responses)
	{
		foreach ($responses as $response) {
			if (!empty($response['user_message'])) {
				return $response['user_message'];
			}
			if (!empty($response['error'])) {
				return $response['error'];
			}
		}
		return 'Operacao Focus nao concluida.';
	}

	/**
	 * @param bool $ok Result status
	 * @param string $message User message
	 * @param array<string,mixed> $extra Extra data
	 * @return array<string,mixed>
	 */
	protected function result($ok, $message, array $extra)
	{
		return array_merge(array('ok' => (bool) $ok, 'message' => $message), $extra);
	}
}
