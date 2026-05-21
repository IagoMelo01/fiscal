<?php
/* Copyright (C) 2026 Farmevo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    fiscal/admin/setup.php
 * \ingroup fiscal
 * \brief   Fiscal module setup page.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once '../lib/fiscal.lib.php';
require_once '../class/focusnfeclient.class.php';
require_once '../class/focuscompanyservice.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'fiscal@fiscal'));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$error = 0;

$environment = getDolGlobalString('FISCAL_FOCUS_ENVIRONMENT', 'homologation');
$productionConfirmed = getDolGlobalInt('FISCAL_FOCUS_PRODUCTION_CONFIRMED');
$focusClient = FocusNFeClient::fromDolibarrConfig($db);
$focusCompanyService = new FocusCompanyService($db, $focusClient);
$focusLookupResult = null;

if ($action == 'focus_lookup_cnpj') {
	$focusLookupResult = $focusCompanyService->lookupCompany(GETPOST('focus_cnpj', 'alphanohtml'));
	if (!empty($focusLookupResult['ok'])) {
		setEventMessages($focusLookupResult['message'], null, 'mesgs');
	} else {
		setEventMessages($focusLookupResult['message'], null, 'errors');
	}
}

if (in_array($action, array('focus_company_dry_run', 'focus_company_submit'), true)) {
	$companyInput = array(
		'focus_id' => GETPOST('focus_id', 'alphanohtml'),
		'cnpj' => GETPOST('focus_cnpj', 'alphanohtml'),
		'name' => GETPOST('focus_name', 'restricthtml'),
		'nome_fantasia' => GETPOST('focus_nome_fantasia', 'restricthtml'),
		'inscricao_estadual' => GETPOST('focus_inscricao_estadual', 'alphanohtml'),
		'regime_tributario' => GETPOST('focus_regime_tributario', 'alphanohtml'),
		'uf' => GETPOST('focus_uf', 'alpha'),
		'municipio' => GETPOST('focus_municipio', 'restricthtml'),
		'data_inicio_recebimento_nfe' => GETPOST('focus_data_inicio_recebimento_nfe', 'alpha'),
	);
	$result = $focusCompanyService->submitCompany(
		$companyInput,
		empty($_FILES['focus_certificate']) ? null : $_FILES['focus_certificate'],
		GETPOST('focus_certificate_password', 'none'),
		$user,
		($action == 'focus_company_submit'),
		(GETPOSTINT('focus_set_active') > 0)
	);

	if (!empty($result['ok'])) {
		setEventMessages($result['message'], null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	} else {
		setEventMessages($result['message'], null, 'errors');
	}
}

if ($action == 'focus_set_active') {
	if ($focusCompanyService->setActiveCompany(GETPOSTINT('company_id'), $user)) {
		setEventMessages($langs->trans('FiscalFocusActiveCompanySaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
	setEventMessages(implode('<br>', $focusCompanyService->errors), null, 'errors');
}

if ($action == 'save') {
	$db->begin();

	$newEnvironment = GETPOST('FISCAL_FOCUS_ENVIRONMENT', 'alpha');
	$newProductionConfirmed = GETPOSTINT('FISCAL_FOCUS_PRODUCTION_CONFIRMED');

	if ($newEnvironment == 'production' && !$newProductionConfirmed) {
		$error++;
		setEventMessages($langs->trans('FiscalProductionRequiresConfirmation'), null, 'errors');
	}

	if (!$error) {
		$result = dolibarr_set_const($db, 'FISCAL_FOCUS_ENVIRONMENT', ($newEnvironment == 'production' ? 'production' : 'homologation'), 'chaine', 0, '', $conf->entity);
		if (!($result > 0)) {
			$error++;
		}

		$postedToken = trim(GETPOST('FISCAL_FOCUS_TOKEN', 'restricthtml'));
		if ($postedToken !== '') {
			$result = dolibarr_set_const($db, 'FISCAL_FOCUS_TOKEN', $postedToken, 'chaine', 0, '', $conf->entity);
			if (!($result > 0)) {
				$error++;
			}
		}

		$result = dolibarr_set_const($db, 'FISCAL_FOCUS_PRODUCTION_CONFIRMED', $newProductionConfirmed ? '1' : '0', 'yesno', 0, '', $conf->entity);
		if (!($result > 0)) {
			$error++;
		}

		$result = dolibarr_set_const($db, 'FISCAL_NFE_DEFAULT_SERIE', GETPOST('FISCAL_NFE_DEFAULT_SERIE', 'alpha') ?: '1', 'chaine', 0, '', $conf->entity);
		if (!($result > 0)) {
			$error++;
		}

		$result = dolibarr_set_const($db, 'FISCAL_FOCUS_POLLING_INTERVAL_MINUTES', max(5, GETPOSTINT('FISCAL_FOCUS_POLLING_INTERVAL_MINUTES')), 'chaine', 0, '', $conf->entity);
		if (!($result > 0)) {
			$error++;
		}

		$result = dolibarr_set_const($db, 'FISCAL_IMPORT_RECEIVED_NFE', GETPOSTINT('FISCAL_IMPORT_RECEIVED_NFE') ? '1' : '0', 'yesno', 0, '', $conf->entity);
		if (!($result > 0)) {
			$error++;
		}
	}

	if (!$error) {
		$db->commit();
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		$db->rollback();
		if (empty($langs->errors)) {
			setEventMessages($langs->trans('SetupNotSaved'), null, 'errors');
		}
	}

	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$form = new Form($db);
$title = 'FiscalSetup';
$help_url = '';

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-fiscal page-admin');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.img_picto($langs->trans('BackToModuleList'), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans('BackToModuleList').'</span></a>';
print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

$head = fiscalAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($title), -1, 'accounting_account');

print '<span class="opacitymedium">'.$langs->trans('FiscalSetupIntro').'</span><br><br>';

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('FiscalFocusSettings').'</td></tr>';

print '<tr class="oddeven">';
print '<td class="fieldrequired">'.$form->textwithpicto($langs->trans('FISCAL_FOCUS_ENVIRONMENT'), $langs->trans('FISCAL_FOCUS_ENVIRONMENTTooltip'), 1).'</td>';
print '<td>'.$form->selectarray('FISCAL_FOCUS_ENVIRONMENT', array(
	'homologation' => $langs->trans('FiscalFocusEnvironmentHomologation'),
	'production' => $langs->trans('FiscalFocusEnvironmentProduction')
), $environment, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td class="fieldrequired">'.$form->textwithpicto($langs->trans('FISCAL_FOCUS_TOKEN'), $langs->trans('FISCAL_FOCUS_TOKENTooltip'), 1).'</td>';
print '<td><input type="password" class="flat minwidth300" name="FISCAL_FOCUS_TOKEN" value="" autocomplete="new-password" placeholder="'.dol_escape_htmltag(getDolGlobalString('FISCAL_FOCUS_TOKEN') ? $langs->trans('FiscalTokenConfigured') : $langs->trans('FiscalTokenNotConfigured')).'">';
print ' <span class="opacitymedium">'.(getDolGlobalString('FISCAL_FOCUS_TOKEN') ? $langs->trans('FiscalTokenConfigured') : $langs->trans('FiscalTokenNotConfigured')).'</span></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$form->textwithpicto($langs->trans('FISCAL_FOCUS_PRODUCTION_CONFIRMED'), $langs->trans('FISCAL_FOCUS_PRODUCTION_CONFIRMEDTooltip'), 1).'</td>';
print '<td>'.$form->selectyesno('FISCAL_FOCUS_PRODUCTION_CONFIRMED', $productionConfirmed, 1).'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('FISCAL_NFE_DEFAULT_SERIE').'</td>';
print '<td><input type="text" class="flat maxwidth75" name="FISCAL_NFE_DEFAULT_SERIE" value="'.dol_escape_htmltag(getDolGlobalString('FISCAL_NFE_DEFAULT_SERIE', '1')).'"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$form->textwithpicto($langs->trans('FISCAL_FOCUS_POLLING_INTERVAL_MINUTES'), $langs->trans('FISCAL_FOCUS_POLLING_INTERVAL_MINUTESTooltip'), 1).'</td>';
print '<td><input type="number" min="5" step="1" class="flat maxwidth75" name="FISCAL_FOCUS_POLLING_INTERVAL_MINUTES" value="'.((int) getDolGlobalInt('FISCAL_FOCUS_POLLING_INTERVAL_MINUTES', 15)).'"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('FISCAL_IMPORT_RECEIVED_NFE').'</td>';
print '<td>'.$form->selectyesno('FISCAL_IMPORT_RECEIVED_NFE', getDolGlobalInt('FISCAL_IMPORT_RECEIVED_NFE'), 1).'</td>';
print '</tr>';

print '</table>';
print '</div>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '</div>';
print '</form>';

print '<br>';
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="'.newToken().'">';

$postedCnpj = GETPOST('focus_cnpj', 'alphanohtml');
$postedFocusId = GETPOST('focus_id', 'alphanohtml');
$postedName = GETPOST('focus_name', 'restricthtml');
$postedAlias = GETPOST('focus_nome_fantasia', 'restricthtml');
$postedIe = GETPOST('focus_inscricao_estadual', 'alphanohtml');
$postedTaxRegime = GETPOST('focus_regime_tributario', 'alphanohtml');
$postedUf = GETPOST('focus_uf', 'alpha');
$postedTown = GETPOST('focus_municipio', 'restricthtml');
$postedReceivedStart = GETPOST('focus_data_inicio_recebimento_nfe', 'alpha');

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('FiscalFocusCompanyAssistant').'</td></tr>';
print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('FiscalFocusCompanyIntro').'</span></td></tr>';

print '<tr class="oddeven">';
print '<td class="fieldrequired">'.$langs->trans('FiscalFocusCompanyCnpj').'</td>';
print '<td><input type="text" class="flat minwidth200" name="focus_cnpj" value="'.dol_escape_htmltag($postedCnpj).'" maxlength="18">';
print ' <button class="button smallpaddingimp" type="submit" name="action" value="focus_lookup_cnpj">'.$langs->trans('FiscalFocusLookupCnpj').'</button></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('FiscalFocusCompanyFocusId').'</td>';
print '<td><input type="text" class="flat minwidth200" name="focus_id" value="'.dol_escape_htmltag($postedFocusId).'"> <span class="opacitymedium">'.$langs->trans('FiscalFocusCompanyExisting').'</span></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td class="fieldrequired">'.$langs->trans('FiscalFocusCompanyName').'</td>';
print '<td><input type="text" class="flat minwidth400" name="focus_name" value="'.dol_escape_htmltag($postedName).'"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('FiscalFocusCompanyAlias').'</td>';
print '<td><input type="text" class="flat minwidth400" name="focus_nome_fantasia" value="'.dol_escape_htmltag($postedAlias).'"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('FiscalFocusCompanyIE').'</td>';
print '<td><input type="text" class="flat minwidth200" name="focus_inscricao_estadual" value="'.dol_escape_htmltag($postedIe).'"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('FiscalFocusCompanyTaxRegime').'</td>';
print '<td><input type="text" class="flat minwidth200" name="focus_regime_tributario" value="'.dol_escape_htmltag($postedTaxRegime).'"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('FiscalFocusCompanyUF').'</td>';
print '<td><input type="text" class="flat maxwidth50" name="focus_uf" value="'.dol_escape_htmltag($postedUf).'" maxlength="2"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('FiscalFocusCompanyTown').'</td>';
print '<td><input type="text" class="flat minwidth300" name="focus_municipio" value="'.dol_escape_htmltag($postedTown).'"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('FiscalFocusCompanyReceivedStart').'</td>';
print '<td><input type="date" class="flat" name="focus_data_inicio_recebimento_nfe" value="'.dol_escape_htmltag($postedReceivedStart).'"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('FiscalFocusEnabledServices').'</td>';
print '<td><span class="badge badge-status4">'.$langs->trans('FiscalFocusEnableNFe').'</span> <span class="badge badge-status4">'.$langs->trans('FiscalFocusEnableManifestation').'</span></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td class="fieldrequired">'.$form->textwithpicto($langs->trans('FiscalFocusCertificateFile'), $langs->trans('FiscalFocusCertificateMetadataOnly'), 1).'</td>';
print '<td><input type="file" class="flat" name="focus_certificate" accept=".pfx,.p12"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td class="fieldrequired">'.$form->textwithpicto($langs->trans('FiscalFocusCertificatePassword'), $langs->trans('FiscalFocusCertificatePasswordTooltip'), 1).'</td>';
print '<td><input type="password" class="flat minwidth200" name="focus_certificate_password" value="" autocomplete="new-password"></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('FiscalFocusSetAsActive').'</td>';
print '<td>'.$form->selectyesno('focus_set_active', GETPOSTINT('focus_set_active'), 1).'</td>';
print '</tr>';

if (is_array($focusLookupResult)) {
	print '<tr class="oddeven"><td>'.$langs->trans('FiscalFocusLookupResult').'</td><td>';
	if (!empty($focusLookupResult['cnpj'])) {
		print '<div>GET /v2/cnpjs: HTTP '.((int) $focusLookupResult['cnpj']['http_code']).(!empty($focusLookupResult['cnpj']['error']) ? ' - '.dol_escape_htmltag($focusLookupResult['cnpj']['error']) : '').'</div>';
	}
	if (!empty($focusLookupResult['companies'])) {
		print '<div>GET /v2/empresas: HTTP '.((int) $focusLookupResult['companies']['http_code']).(!empty($focusLookupResult['companies']['error']) ? ' - '.dol_escape_htmltag($focusLookupResult['companies']['error']) : '').'</div>';
	}
	print '</td></tr>';
}

print '</table>';
print '</div>';

print '<div class="center">';
print '<button class="button" type="submit" name="action" value="focus_company_dry_run">'.$langs->trans('FiscalFocusDryRunCompany').'</button> ';
print '<button class="button button-save" type="submit" name="action" value="focus_company_submit">'.$langs->trans('FiscalFocusSubmitCompany').'</button>';
print '</div>';
print '</form>';

$localCompanies = $focusCompanyService->fetchLocalCompanies();
print '<br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="9">'.$langs->trans('FiscalFocusCompanyLocalList').'</td></tr>';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Status').'</td>';
print '<td>'.$langs->trans('FiscalFocusCompanyCnpj').'</td>';
print '<td>'.$langs->trans('FiscalFocusCompanyName').'</td>';
print '<td>'.$langs->trans('FiscalFocusCompanyFocusId').'</td>';
print '<td>'.$langs->trans('Environment').'</td>';
print '<td>'.$langs->trans('FiscalFocusEnableNFe').'</td>';
print '<td>'.$langs->trans('FiscalFocusEnableManifestation').'</td>';
print '<td>'.$langs->trans('FiscalFocusCertificateFile').'</td>';
print '<td class="right">'.$langs->trans('Action').'</td>';
print '</tr>';

if (empty($localCompanies)) {
	print '<tr class="oddeven"><td colspan="9"><span class="opacitymedium">'.$langs->trans('FiscalFocusNoCompany').'</span></td></tr>';
} else {
	foreach ($localCompanies as $localCompany) {
		print '<tr class="oddeven">';
		print '<td>'.($localCompany->active ? '<span class="badge badge-status4">'.$langs->trans('Active').'</span>' : '<span class="opacitymedium">'.dol_escape_htmltag($localCompany->status).'</span>').'</td>';
		print '<td>'.dol_escape_htmltag($localCompany->cnpj).'</td>';
		print '<td>'.dol_escape_htmltag($localCompany->name).'</td>';
		print '<td>'.dol_escape_htmltag($localCompany->focus_id).'</td>';
		print '<td>'.dol_escape_htmltag($localCompany->environment).'</td>';
		print '<td>'.yn($localCompany->habilita_nfe).'</td>';
		print '<td>'.yn($localCompany->habilita_manifestacao).'</td>';
		print '<td>'.($localCompany->fk_certificate_active > 0 ? dol_escape_htmltag((string) $localCompany->fk_certificate_active) : '<span class="opacitymedium">-</span>').'</td>';
		print '<td class="right">';
		if (empty($localCompany->active)) {
			print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="inline-block">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="focus_set_active">';
			print '<input type="hidden" name="company_id" value="'.((int) $localCompany->id).'">';
			print '<input type="submit" class="button smallpaddingimp" value="'.$langs->trans('FiscalFocusSetActive').'">';
			print '</form>';
		}
		print '</td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
