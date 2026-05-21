<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file    received_nfe_card.php
 * \ingroup fiscal
 * \brief   Received NF-e card and manifestation.
 */

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/fiscal/class/nfereceived.class.php');
dol_include_once('/fiscal/class/nfereceivedmanifestation.class.php');
dol_include_once('/fiscal/class/focusnfereceivedservice.class.php');

$langs->loadLangs(array('fiscal@fiscal', 'other'));

if (!isModEnabled('fiscal')) {
	accessforbidden();
}
if (!$user->hasRight('fiscal', 'received', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

$object = new NFeReceived($db);
if ($id > 0) {
	$result = $object->fetch($id);
	if ($result <= 0) {
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
}

if ($action == 'download_documents' && $object->id > 0) {
	$service = new FocusNFeReceivedService($db);
	$result = $service->downloadDocuments($object, $user);
	if (!empty($result['ok'])) {
		setEventMessages($result['message'], null, 'mesgs');
	} else {
		setEventMessages($result['message'], null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

if ($action == 'manifest' && $object->id > 0) {
	if (!$user->hasRight('fiscal', 'received', 'manifest')) {
		accessforbidden();
	}
	$service = new FocusNFeReceivedService($db);
	$result = $service->manifest($object, GETPOST('manifest_type', 'alpha'), GETPOST('justificativa', 'restricthtml'), $user);
	if (!empty($result['ok'])) {
		setEventMessages($result['message'], null, 'mesgs');
	} else {
		setEventMessages($result['message'], null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

$form = new Form($db);

llxHeader('', $langs->trans('NFeReceived'), '', '', 0, 0, '', '', '', 'mod-fiscal page-card');

$linkback = '<a href="'.dol_buildpath('/fiscal/received_nfe_list.php', 1).'">'.$langs->trans('BackToList').'</a>';
print load_fiche_titre($langs->trans('NFeReceived'), $linkback, 'fa-file-import');

print '<div class="tabsAction">';
print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=download_documents&token='.newToken().'">'.$langs->trans('FiscalNFeReceivedDownloadDocuments').'</a>';
print '</div>';

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="border centpercent tableforfield">';
print '<tr><td>'.$langs->trans('NFeAccessKey').'</td><td>'.dol_escape_htmltag($object->chave_nfe).'</td></tr>';
print '<tr><td>'.$langs->trans('CNPJ').'</td><td>'.dol_escape_htmltag($object->cnpj_destinatario).'</td></tr>';
print '<tr><td>'.$langs->trans('Name').'</td><td>'.dol_escape_htmltag($object->nome_emitente).'</td></tr>';
print '<tr><td>'.$langs->trans('ThirdParty').'</td><td>'.dol_escape_htmltag($object->documento_emitente).'</td></tr>';
print '<tr><td>'.$langs->trans('State').'</td><td>'.dol_escape_htmltag($object->uf_emitente).'</td></tr>';
print '<tr><td>'.$langs->trans('NFeIssueDate').'</td><td>'.(!empty($object->data_emissao) ? dol_print_date($object->data_emissao, 'dayhour') : '').'</td></tr>';
print '<tr><td>'.$langs->trans('NFeTotal').'</td><td>'.price($object->valor_total).'</td></tr>';
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<table class="border centpercent tableforfield">';
print '<tr><td>'.$langs->trans('Status').'</td><td>'.dol_escape_htmltag($object->situacao).'</td></tr>';
print '<tr><td>'.$langs->trans('Manifestation').'</td><td>'.(empty($object->manifestacao_destinatario) ? '<span class="badge badge-status0">'.$langs->trans('FiscalNFeReceivedPending').'</span>' : '<span class="badge badge-status4">'.dol_escape_htmltag($object->manifestacao_destinatario).'</span>').'</td></tr>';
print '<tr><td>'.$langs->trans('Complete').'</td><td>'.yn($object->nfe_completa).'</td></tr>';
print '<tr><td>'.$langs->trans('Version').'</td><td>'.((int) $object->versao).'</td></tr>';
print '<tr><td>'.$langs->trans('LastSync').'</td><td>'.(!empty($object->last_sync) ? dol_print_date($object->last_sync, 'dayhour') : '').'</td></tr>';
$xmlLink = $object->caminho_xml ? '<a href="'.DOL_URL_ROOT.'/document.php?modulepart=fiscal&file='.urlencode($object->caminho_xml).'">'.dol_escape_htmltag($object->caminho_xml).'</a>' : '<span class="opacitymedium">-</span>';
$pdfLink = $object->caminho_pdf ? '<a href="'.DOL_URL_ROOT.'/document.php?modulepart=fiscal&file='.urlencode($object->caminho_pdf).'">'.dol_escape_htmltag($object->caminho_pdf).'</a>' : '<span class="opacitymedium">-</span>';
print '<tr><td>'.$langs->trans('NFeXmlPath').'</td><td>'.$xmlLink.'</td></tr>';
print '<tr><td>'.$langs->trans('NFeDanfePath').'</td><td>'.$pdfLink.'</td></tr>';
print '</table>';
print '</div>';
print '</div>';
print '<div class="clearboth"></div>';

if ($user->hasRight('fiscal', 'received', 'manifest')) {
	print '<br>';
	print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="manifest">';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('FiscalNFeReceivedManifest').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('Type').'</td><td>'.$form->selectarray('manifest_type', array(
		'ciencia' => $langs->trans('FiscalManifestCiencia'),
		'confirmacao' => $langs->trans('FiscalManifestConfirmacao'),
		'desconhecimento' => $langs->trans('FiscalManifestDesconhecimento'),
		'nao_realizada' => $langs->trans('FiscalManifestNaoRealizada')
	), 'ciencia', 0, 0, 0, '', 0, 0, 0, '', 'minwidth300').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans('Reason').'</td><td><textarea class="flat centpercent" name="justificativa" rows="3" placeholder="'.$langs->trans('FiscalManifestJustificationHelp').'"></textarea></td></tr>';
	print '</table>';
	print '</div>';
	print '<div class="center"><input type="submit" class="button button-save" value="'.$langs->trans('FiscalNFeReceivedManifestSubmit').'"></div>';
	print '</form>';
}

print '<br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="7">'.$langs->trans('FiscalNFeReceivedManifestHistory').'</td></tr>';
print '<tr class="liste_titre"><td>'.$langs->trans('Date').'</td><td>'.$langs->trans('Type').'</td><td>'.$langs->trans('Reason').'</td><td>'.$langs->trans('NFeFocusStatus').'</td><td>'.$langs->trans('NFeSefazStatus').'</td><td>'.$langs->trans('NFeProtocol').'</td><td>'.$langs->trans('NFeSefazMessage').'</td></tr>';
$sql = 'SELECT rowid FROM '.$db->prefix().'fiscal_nfe_received_manifestation WHERE entity = '.((int) $conf->entity).' AND fk_nfe_received = '.((int) $object->id).' ORDER BY date_creation DESC, rowid DESC';
$resql = $db->query($sql);
$nb = 0;
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$nb++;
		$history = new NFeReceivedManifestation($db);
		$history->fetch((int) $obj->rowid);
		print '<tr class="oddeven">';
		print '<td>'.(!empty($history->data_manifesto) ? dol_print_date($history->data_manifesto, 'dayhour') : '').'</td>';
		print '<td>'.dol_escape_htmltag($history->tipo).'</td>';
		print '<td>'.dol_escape_htmltag($history->justificativa).'</td>';
		print '<td>'.dol_escape_htmltag($history->status_focus).'</td>';
		print '<td>'.dol_escape_htmltag($history->status_sefaz).'</td>';
		print '<td>'.dol_escape_htmltag($history->protocolo).'</td>';
		print '<td>'.dol_escape_htmltag(dol_trunc($history->mensagem_sefaz, 180)).'</td>';
		print '</tr>';
	}
	$db->free($resql);
}
if (!$nb) {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('FiscalNFeReceivedNoManifestHistory').'</span></td></tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
