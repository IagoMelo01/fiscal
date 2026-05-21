<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file    received_nfe_list.php
 * \ingroup fiscal
 * \brief   Received NF-e list.
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
dol_include_once('/fiscal/class/focusnfereceivedservice.class.php');

$langs->loadLangs(array('fiscal@fiscal', 'other'));

if (!isModEnabled('fiscal')) {
	accessforbidden();
}
if (!$user->hasRight('fiscal', 'received', 'read')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$filter = GETPOST('filter', 'alpha');
$search_chave = GETPOST('search_chave', 'alphanohtml');
$search_emitente = GETPOST('search_emitente', 'restricthtml');
$search_manifestacao = GETPOST('search_manifestacao', 'alphanohtml');

if ($action == 'sync_received') {
	$service = new FocusNFeReceivedService($db);
	$result = $service->synchronize($user);
	if (!empty($result['ok'])) {
		setEventMessages($result['message'].' '.$langs->trans('Records').': '.((int) $result['imported']).' / '.$langs->trans('Version').': '.((int) $result['cursor']), null, 'mesgs');
	} else {
		setEventMessages($result['message'], null, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].($filter ? '?filter='.urlencode($filter) : ''));
	exit;
}

$form = new Form($db);

$sql = 'SELECT rowid FROM '.$db->prefix().'fiscal_nfe_received';
$sql .= ' WHERE entity = '.((int) $conf->entity);
if ($filter == 'pending') {
	$sql .= " AND (manifestacao_destinatario IS NULL OR manifestacao_destinatario = '')";
}
if ($search_chave !== '') {
	$sql .= " AND chave_nfe LIKE '%".$db->escape($search_chave)."%'";
}
if ($search_emitente !== '') {
	$sql .= " AND nome_emitente LIKE '%".$db->escape($search_emitente)."%'";
}
if ($search_manifestacao !== '') {
	$sql .= " AND manifestacao_destinatario LIKE '%".$db->escape($search_manifestacao)."%'";
}
$sql .= ' ORDER BY versao DESC, data_emissao DESC, rowid DESC';

$received = array();
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$item = new NFeReceived($db);
		if ($item->fetch((int) $obj->rowid) > 0) {
			$received[] = $item;
		}
	}
	$db->free($resql);
} else {
	setEventMessages($db->lasterror(), null, 'errors');
}

llxHeader('', $langs->trans('NFeReceivedList'), '', '', 0, 0, '', '', '', 'mod-fiscal page-list');

print load_fiche_titre($langs->trans('NFeReceivedList'), '', 'fa-file-import');

print '<div class="tabsAction">';
print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=sync_received&token='.newToken().($filter ? '&filter='.urlencode($filter) : '').'">'.$langs->trans('FiscalNFeReceivedSync').'</a>';
print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?filter=pending">'.$langs->trans('FiscalNFeReceivedPending').'</a>';
print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('ShowAll').'</a>';
print '</div>';

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
if ($filter) {
	print '<input type="hidden" name="filter" value="'.dol_escape_htmltag($filter).'">';
}
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre_filter">';
print '<td><input type="text" class="flat maxwidth200" name="search_chave" value="'.dol_escape_htmltag($search_chave).'" placeholder="'.$langs->trans('NFeAccessKey').'"></td>';
print '<td><input type="text" class="flat maxwidth200" name="search_emitente" value="'.dol_escape_htmltag($search_emitente).'" placeholder="'.$langs->trans('Name').'"></td>';
print '<td></td><td></td><td></td>';
print '<td><input type="text" class="flat maxwidth100" name="search_manifestacao" value="'.dol_escape_htmltag($search_manifestacao).'" placeholder="'.$langs->trans('Manifestation').'"></td>';
print '<td class="right"><input type="submit" class="button smallpaddingimp" value="'.$langs->trans('Search').'"></td>';
print '</tr>';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('NFeAccessKey').'</td>';
print '<td>'.$langs->trans('Name').'</td>';
print '<td>'.$langs->trans('NFeIssueDate').'</td>';
print '<td class="right">'.$langs->trans('NFeTotal').'</td>';
print '<td class="right">'.$langs->trans('Version').'</td>';
print '<td>'.$langs->trans('Manifestation').'</td>';
print '<td class="right">'.$langs->trans('Status').'</td>';
print '</tr>';

if (empty($received)) {
	print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
} else {
	foreach ($received as $item) {
		print '<tr class="oddeven">';
		print '<td>'.$item->getNomUrl(1).'</td>';
		print '<td>'.dol_escape_htmltag($item->nome_emitente).'<br><span class="opacitymedium">'.dol_escape_htmltag($item->documento_emitente).'</span></td>';
		print '<td>'.(!empty($item->data_emissao) ? dol_print_date($item->data_emissao, 'day') : '').'</td>';
		print '<td class="right">'.price($item->valor_total).'</td>';
		print '<td class="right">'.((int) $item->versao).'</td>';
		print '<td>'.(empty($item->manifestacao_destinatario) ? '<span class="badge badge-status0">'.$langs->trans('FiscalNFeReceivedPending').'</span>' : '<span class="badge badge-status4">'.dol_escape_htmltag($item->manifestacao_destinatario).'</span>').'</td>';
		print '<td class="right">'.dol_escape_htmltag($item->situacao).'</td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
