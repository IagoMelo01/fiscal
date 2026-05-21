<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file    class/fiscalvalidator.class.php
 * \ingroup fiscal
 * \brief   Fiscal validations before Focus NFe transmission.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/nfe.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/focuscompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

/**
 * Validates NF-e data before transmission. It does not call Focus.
 */
class FiscalValidator
{
	/** @var DoliDB */
	protected $db;

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * @param NFe $nfe NF-e
	 * @param FocusCompany|null $emitente Focus company
	 * @param Societe|null $destinatario Recipient thirdparty
	 * @return array{ok:bool,errors:array<int,string>,warnings:array<int,string>}
	 */
	public function validateForTransmission(NFe $nfe, FocusCompany $emitente = null, Societe $destinatario = null)
	{
		$errors = array();
		$warnings = array();

		if (empty(getDolGlobalString('FISCAL_FOCUS_TOKEN'))) {
			$errors[] = 'Token Focus nao configurado.';
		}
		if (getDolGlobalString('FISCAL_FOCUS_ENVIRONMENT', 'homologation') === 'production' && !getDolGlobalInt('FISCAL_FOCUS_PRODUCTION_CONFIRMED')) {
			$errors[] = 'Ambiente de producao Focus exige confirmacao administrativa.';
		}
		if ((int) $nfe->status !== NFe::STATUS_VALIDATED) {
			$errors[] = 'NF-e deve estar validada localmente antes da transmissao.';
		}
		if (empty($nfe->natureza_operacao)) {
			$errors[] = 'Natureza da operacao obrigatoria.';
		}

		if (!$emitente) {
			$emitente = $this->fetchEmitente($nfe);
		}
		$this->validateEmitente($emitente, $errors);

		if (!$destinatario) {
			$destinatario = $this->fetchDestinatario($nfe);
		}
		$this->validateDestinatario($destinatario, $errors, $warnings);

		$nfe->fetchLines();
		$this->validateItems($nfe, $errors);
		$this->validateTotals($nfe, $errors);

		return array('ok' => empty($errors), 'errors' => $errors, 'warnings' => $warnings);
	}

	/**
	 * @param FocusCompany|null $emitente Focus company
	 * @param array<int,string> $errors Error list
	 * @return void
	 */
	protected function validateEmitente($emitente, array &$errors)
	{
		if (!is_object($emitente) || empty($emitente->id)) {
			$errors[] = 'Nenhuma empresa Focus ativa foi selecionada.';
			return;
		}
		if (empty($emitente->active)) {
			$errors[] = 'Empresa Focus selecionada nao esta ativa.';
		}
		if (empty($emitente->habilita_nfe)) {
			$errors[] = 'Empresa Focus nao esta habilitada para NF-e.';
		}
		if (empty($emitente->fk_certificate_active)) {
			$errors[] = 'Empresa Focus ativa nao possui metadados de certificado enviado.';
		}
		if (!$this->isValidCnpj($emitente->cnpj)) {
			$errors[] = 'CNPJ do emitente invalido.';
		}
		if (empty($emitente->name)) {
			$errors[] = 'Razao social do emitente obrigatoria.';
		}
		if (empty($emitente->inscricao_estadual)) {
			$errors[] = 'Inscricao estadual do emitente obrigatoria.';
		}
		if (empty($emitente->regime_tributario) || !in_array((int) $emitente->regime_tributario, array(1, 2, 3), true)) {
			$errors[] = 'Regime tributario do emitente deve ser 1, 2 ou 3.';
		}
		if (empty($emitente->uf) || !preg_match('/^[A-Z]{2}$/', strtoupper((string) $emitente->uf))) {
			$errors[] = 'UF do emitente obrigatoria.';
		}
		if (empty($emitente->municipio)) {
			$errors[] = 'Municipio do emitente obrigatorio.';
		}
	}

	/**
	 * @param Societe|null $destinatario Thirdparty
	 * @param array<int,string> $errors Error list
	 * @param array<int,string> $warnings Warning list
	 * @return void
	 */
	protected function validateDestinatario($destinatario, array &$errors, array &$warnings)
	{
		if (!is_object($destinatario) || empty($destinatario->id)) {
			$errors[] = 'Destinatario obrigatorio.';
			return;
		}
		if (empty($destinatario->name)) {
			$errors[] = 'Nome do destinatario obrigatorio.';
		}
		$doc = $this->documentFromThirdparty($destinatario);
		if (dol_strlen($doc) == 14 && !$this->isValidCnpj($doc)) {
			$errors[] = 'CNPJ do destinatario invalido.';
		} elseif (dol_strlen($doc) == 11 && !$this->isValidCpf($doc)) {
			$errors[] = 'CPF do destinatario invalido.';
		} elseif (!in_array(dol_strlen($doc), array(11, 14), true)) {
			$errors[] = 'Destinatario deve ter CNPJ ou CPF valido.';
		}
		if (empty($destinatario->address)) {
			$errors[] = 'Endereco do destinatario obrigatorio.';
		}
		if (empty($destinatario->town)) {
			$errors[] = 'Municipio do destinatario obrigatorio.';
		}
		if (empty($destinatario->zip) || dol_strlen(preg_replace('/\D/', '', (string) $destinatario->zip)) != 8) {
			$errors[] = 'CEP do destinatario deve conter 8 digitos.';
		}
		if (empty($destinatario->state_code) || !preg_match('/^[A-Z]{2}$/', strtoupper((string) $destinatario->state_code))) {
			$errors[] = 'UF do destinatario obrigatoria.';
		}
		if (!preg_match('/,\s*([0-9A-Za-z\/\-]+)/', (string) $destinatario->address) && empty($destinatario->array_options['options_fiscal_numero'])) {
			$warnings[] = 'Endereco do destinatario nao possui numero claro; informe options_fiscal_numero ou use formato "logradouro, numero - bairro".';
		}
		if (!preg_match('/-\s*.+$/', (string) $destinatario->address) && empty($destinatario->array_options['options_fiscal_bairro'])) {
			$warnings[] = 'Bairro do destinatario nao identificado; informe options_fiscal_bairro ou use formato "logradouro, numero - bairro".';
		}
	}

	/**
	 * @param NFe $nfe NF-e
	 * @param array<int,string> $errors Error list
	 * @return void
	 */
	protected function validateItems(NFe $nfe, array &$errors)
	{
		if (empty($nfe->lines)) {
			$errors[] = 'NF-e deve ter ao menos um item.';
			return;
		}
		foreach ($nfe->lines as $line) {
			$prefix = 'Item '.((int) $line->numero_item).': ';
			if (empty($line->descricao)) {
				$errors[] = $prefix.'descricao obrigatoria.';
			}
			if (!preg_match('/^\d{8}$/', (string) $line->ncm)) {
				$errors[] = $prefix.'NCM deve conter 8 digitos.';
			}
			if (!preg_match('/^[1-7]\d{3}$/', (string) $line->cfop)) {
				$errors[] = $prefix.'CFOP deve conter 4 digitos e iniciar entre 1 e 7.';
			}
			if (empty($line->unidade_comercial)) {
				$errors[] = $prefix.'unidade comercial obrigatoria.';
			}
			if (price2num($line->quantidade_comercial) <= 0) {
				$errors[] = $prefix.'quantidade comercial deve ser maior que zero.';
			}
			if (price2num($line->valor_unitario_comercial) <= 0) {
				$errors[] = $prefix.'valor unitario deve ser maior que zero.';
			}
			if (price2num($line->valor_bruto) <= 0) {
				$errors[] = $prefix.'valor bruto deve ser maior que zero.';
			}
			if (!preg_match('/^[0-8]$/', (string) $line->icms_origem)) {
				$errors[] = $prefix.'origem ICMS deve ser um digito entre 0 e 8.';
			}
			if (!$this->isValidIcmsSituation((string) $line->icms_situacao_tributaria)) {
				$errors[] = $prefix.'CST/CSOSN ICMS invalido.';
			}
			if (!$this->isValidPisCofinsSituation((string) $line->pis_situacao_tributaria)) {
				$errors[] = $prefix.'CST PIS deve conter 2 digitos.';
			}
			if (!$this->isValidPisCofinsSituation((string) $line->cofins_situacao_tributaria)) {
				$errors[] = $prefix.'CST COFINS deve conter 2 digitos.';
			}
		}
	}

	/**
	 * @param NFe $nfe NF-e
	 * @param array<int,string> $errors Error list
	 * @return void
	 */
	protected function validateTotals(NFe $nfe, array &$errors)
	{
		$sum = 0;
		foreach ($nfe->lines as $line) {
			$sum += (float) price2num($line->valor_bruto);
		}
		if (abs($sum - (float) price2num($nfe->valor_produtos)) > 0.01) {
			$errors[] = 'Total de produtos nao confere com a soma dos itens.';
		}
		$expectedTotal = (float) price2num($nfe->valor_produtos) + (float) price2num($nfe->valor_frete) + (float) price2num($nfe->valor_seguro) - (float) price2num($nfe->valor_desconto);
		if (abs($expectedTotal - (float) price2num($nfe->valor_total)) > 0.01) {
			$errors[] = 'Valor total da NF-e nao confere com produtos + frete + seguro - desconto.';
		}
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
		return ($company->fetchActive(getDolGlobalString('FISCAL_FOCUS_ENVIRONMENT', 'homologation')) > 0) ? $company : null;
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
	 * @param Societe $soc Thirdparty
	 * @return string
	 */
	protected function documentFromThirdparty(Societe $soc)
	{
		foreach (array('idprof1', 'idprof2', 'siren') as $field) {
			if (!empty($soc->$field)) {
				$digits = preg_replace('/\D/', '', (string) $soc->$field);
				if (dol_strlen($digits) == 11 || dol_strlen($digits) == 14) {
					return $digits;
				}
			}
		}
		return '';
	}

	/**
	 * @param string $value CNPJ
	 * @return bool
	 */
	protected function isValidCnpj($value)
	{
		$cnpj = preg_replace('/\D/', '', (string) $value);
		if (dol_strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
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
	 * @param string $value CPF
	 * @return bool
	 */
	protected function isValidCpf($value)
	{
		$cpf = preg_replace('/\D/', '', (string) $value);
		if (dol_strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
			return false;
		}
		for ($t = 9; $t < 11; $t++) {
			$d = 0;
			for ($c = 0; $c < $t; $c++) {
				$d += (int) $cpf[$c] * (($t + 1) - $c);
			}
			$d = ((10 * $d) % 11) % 10;
			if ((int) $cpf[$t] !== $d) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param string $value CST or CSOSN
	 * @return bool
	 */
	protected function isValidIcmsSituation($value)
	{
		$value = preg_replace('/\D/', '', $value);
		$cst = array('00', '10', '20', '30', '40', '41', '50', '51', '60', '70', '90');
		$csosn = array('101', '102', '103', '201', '202', '203', '300', '400', '500', '900');
		return in_array($value, $cst, true) || in_array($value, $csosn, true);
	}

	/**
	 * @param string $value CST
	 * @return bool
	 */
	protected function isValidPisCofinsSituation($value)
	{
		return (bool) preg_match('/^\d{2}$/', preg_replace('/\D/', '', $value));
	}
}
