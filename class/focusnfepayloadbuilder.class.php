<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file    class/focusnfepayloadbuilder.class.php
 * \ingroup fiscal
 * \brief   Builds Focus NFe model 55 payloads from Dolibarr fiscal objects.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/nfe.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/fiscal/class/focuscompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

/**
 * Testable mapper from local NF-e data to Focus NFe JSON.
 */
class FocusNFePayloadBuilder
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
	 * Build a model 55 payload.
	 *
	 * @param NFe $nfe Issued NF-e
	 * @param FocusCompany|null $emitente Active Focus company
	 * @param Societe|null $destinatario Recipient thirdparty
	 * @return array<string,mixed>
	 */
	public function build(NFe $nfe, FocusCompany $emitente = null, Societe $destinatario = null)
	{
		if (!$emitente) {
			$emitente = $this->fetchEmitente($nfe);
		}
		if (!$destinatario) {
			$destinatario = $this->fetchDestinatario($nfe);
		}
		$nfe->fetchLines();

		$destDoc = $this->documentFromThirdparty($destinatario);
		$destAddress = $this->addressParts($destinatario);
		$destUf = strtoupper((string) $this->thirdpartyState($destinatario));
		$emitUf = is_object($emitente) ? strtoupper((string) $emitente->uf) : '';

		$payload = array(
			'natureza_operacao' => (string) $nfe->natureza_operacao,
			'data_emissao' => $this->toIso8601(empty($nfe->data_emissao) ? dol_now() : $nfe->data_emissao),
			'tipo_documento' => (int) $nfe->tipo_documento,
			'local_destino' => ($emitUf !== '' && $destUf !== '' && $emitUf !== $destUf) ? 2 : 1,
			'finalidade_emissao' => (int) $nfe->finalidade_emissao,
			'consumidor_final' => 0,
			'presenca_comprador' => 1,
			'cnpj_emitente' => is_object($emitente) ? preg_replace('/\D/', '', (string) $emitente->cnpj) : '',
			'nome_emitente' => is_object($emitente) ? (string) $emitente->name : '',
			'nome_fantasia_emitente' => is_object($emitente) ? (string) $emitente->nome_fantasia : '',
			'inscricao_estadual_emitente' => is_object($emitente) ? (string) $emitente->inscricao_estadual : '',
			'regime_tributario_emitente' => is_object($emitente) ? (int) $emitente->regime_tributario : '',
			'municipio_emitente' => is_object($emitente) ? (string) $emitente->municipio : '',
			'uf_emitente' => $emitUf,
			'nome_destinatario' => is_object($destinatario) ? (string) $destinatario->name : '',
			'indicador_inscricao_estadual_destinatario' => $this->recipientIeIndicator($destinatario),
			'inscricao_estadual_destinatario' => $this->thirdpartyStateTaxId($destinatario),
			'logradouro_destinatario' => $destAddress['logradouro'],
			'numero_destinatario' => $destAddress['numero'],
			'bairro_destinatario' => $destAddress['bairro'],
			'municipio_destinatario' => is_object($destinatario) ? (string) $destinatario->town : '',
			'uf_destinatario' => $destUf,
			'cep_destinatario' => is_object($destinatario) ? preg_replace('/\D/', '', (string) $destinatario->zip) : '',
			'pais_destinatario' => 'Brasil',
			'telefone_destinatario' => is_object($destinatario) ? preg_replace('/\D/', '', (string) $destinatario->phone) : '',
			'valor_frete' => (float) price2num($nfe->valor_frete),
			'valor_seguro' => (float) price2num($nfe->valor_seguro),
			'valor_desconto' => (float) price2num($nfe->valor_desconto),
			'valor_outras_despesas' => 0.0,
			'valor_produtos' => (float) price2num($nfe->valor_produtos),
			'valor_total' => (float) price2num($nfe->valor_total),
			'modalidade_frete' => 9,
			'items' => $this->buildItems($nfe),
		);

		if (!empty($nfe->data_entrada_saida)) {
			$payload['data_entrada_saida'] = $this->toIso8601($nfe->data_entrada_saida);
		}
		if (dol_strlen($destDoc) == 14) {
			$payload['cnpj_destinatario'] = $destDoc;
		} elseif (dol_strlen($destDoc) == 11) {
			$payload['cpf_destinatario'] = $destDoc;
		}
		if (!empty($nfe->serie)) {
			$payload['serie'] = (int) $nfe->serie;
		}
		if (!empty($nfe->numero)) {
			$payload['numero'] = (int) $nfe->numero;
		}

		return $this->removeEmptyValues($payload);
	}

	/**
	 * @param NFe $nfe NF-e
	 * @return array<int,array<string,mixed>>
	 */
	protected function buildItems(NFe $nfe)
	{
		$items = array();
		foreach ($nfe->lines as $line) {
			$item = array(
				'numero_item' => (int) $line->numero_item,
				'codigo_produto' => (string) $line->codigo_produto,
				'descricao' => (string) $line->descricao,
				'ncm' => preg_replace('/\D/', '', (string) $line->ncm),
				'cest' => preg_replace('/\D/', '', (string) $line->cest),
				'cfop' => preg_replace('/\D/', '', (string) $line->cfop),
				'unidade_comercial' => (string) $line->unidade_comercial,
				'quantidade_comercial' => (float) price2num($line->quantidade_comercial),
				'valor_unitario_comercial' => (float) price2num($line->valor_unitario_comercial),
				'valor_bruto' => (float) price2num($line->valor_bruto),
				'unidade_tributavel' => empty($line->unidade_tributavel) ? (string) $line->unidade_comercial : (string) $line->unidade_tributavel,
				'quantidade_tributavel' => (float) price2num(empty($line->quantidade_tributavel) ? $line->quantidade_comercial : $line->quantidade_tributavel),
				'valor_unitario_tributavel' => (float) price2num(empty($line->valor_unitario_tributavel) ? $line->valor_unitario_comercial : $line->valor_unitario_tributavel),
				'icms_origem' => (string) $line->icms_origem,
				'icms_situacao_tributaria' => (string) $line->icms_situacao_tributaria,
				'pis_situacao_tributaria' => (string) $line->pis_situacao_tributaria,
				'cofins_situacao_tributaria' => (string) $line->cofins_situacao_tributaria,
			);
			$items[] = $this->removeEmptyValues($item);
		}
		return $items;
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
		if ($company->fetchActive(getDolGlobalString('FISCAL_FOCUS_ENVIRONMENT', 'homologation')) > 0) {
			return $company;
		}
		return null;
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
	 * @param Societe|null $soc Thirdparty
	 * @return string
	 */
	protected function documentFromThirdparty($soc)
	{
		if (!is_object($soc)) {
			return '';
		}
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
	 * @param Societe|null $soc Thirdparty
	 * @return array{logradouro:string,numero:string,bairro:string}
	 */
	protected function addressParts($soc)
	{
		$address = is_object($soc) ? trim((string) $soc->address) : '';
		$parts = array('logradouro' => $address, 'numero' => '', 'bairro' => '');
		if (preg_match('/^(.+?),\s*([0-9A-Za-z\/\-]+)(?:\s*-\s*(.+))?$/', $address, $matches)) {
			$parts['logradouro'] = trim($matches[1]);
			$parts['numero'] = trim($matches[2]);
			$parts['bairro'] = empty($matches[3]) ? '' : trim($matches[3]);
		}
		if (is_object($soc) && !empty($soc->array_options['options_fiscal_bairro'])) {
			$parts['bairro'] = (string) $soc->array_options['options_fiscal_bairro'];
		}
		if (is_object($soc) && !empty($soc->array_options['options_fiscal_numero'])) {
			$parts['numero'] = (string) $soc->array_options['options_fiscal_numero'];
		}
		return $parts;
	}

	/**
	 * @param Societe|null $soc Thirdparty
	 * @return string
	 */
	protected function thirdpartyState($soc)
	{
		if (!is_object($soc)) {
			return '';
		}
		if (!empty($soc->state_code)) {
			return (string) $soc->state_code;
		}
		return '';
	}

	/**
	 * @param Societe|null $soc Thirdparty
	 * @return string
	 */
	protected function thirdpartyStateTaxId($soc)
	{
		if (!is_object($soc)) {
			return '';
		}
		foreach (array('idprof2', 'idprof3') as $field) {
			if (!empty($soc->$field)) {
				return preg_replace('/[^0-9A-Za-z]/', '', (string) $soc->$field);
			}
		}
		return '';
	}

	/**
	 * @param Societe|null $soc Thirdparty
	 * @return int
	 */
	protected function recipientIeIndicator($soc)
	{
		$ie = $this->thirdpartyStateTaxId($soc);
		if ($ie === '') {
			return 9;
		}
		if (preg_match('/ISENT/i', $ie)) {
			return 2;
		}
		return 1;
	}

	/**
	 * @param int|string $timestamp Dolibarr timestamp
	 * @return string
	 */
	protected function toIso8601($timestamp)
	{
		$timestamp = is_numeric($timestamp) ? (int) $timestamp : dol_stringtotime((string) $timestamp);
		if ($timestamp <= 0) {
			$timestamp = dol_now();
		}
		return date('c', $timestamp);
	}

	/**
	 * @param array<string,mixed> $payload Payload
	 * @return array<string,mixed>
	 */
	protected function removeEmptyValues(array $payload)
	{
		foreach ($payload as $key => $value) {
			if ($value === '' || $value === null || (is_array($value) && empty($value))) {
				unset($payload[$key]);
			}
		}
		return $payload;
	}
}
