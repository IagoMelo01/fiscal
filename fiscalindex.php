<?php
/* Copyright (C) 2026 Farmevo */

/**
 * \file       fiscal/fiscalindex.php
 * \ingroup    fiscal
 * \brief      Fiscal module home page.
 */

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
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
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

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('fiscal@fiscal'));

if (!isModEnabled('fiscal')) {
	accessforbidden('Module not enabled');
}
if (!$user->hasRight('fiscal', 'nfe', 'read') && !$user->hasRight('fiscal', 'received', 'read')) {
	accessforbidden();
}

llxHeader('', $langs->trans('FiscalArea'), '', '', 0, 0, '', '', '', 'mod-fiscal page-index');

print load_fiche_titre($langs->trans('FiscalArea'), '', 'accounting_account');
print '<div class="fichecenter">';
print '<div class="opacitymedium">'.$langs->trans('FiscalDashboardIntro').'</div>';
print '<br>';

print '<div class="tabsAction">';
if ($user->hasRight('fiscal', 'nfe', 'read')) {
	print dolGetButtonAction('', $langs->trans('NFeList'), 'default', dol_buildpath('/fiscal/nfe_list.php', 1));
}
if ($user->admin) {
	print dolGetButtonAction('', $langs->trans('Settings'), 'default', dol_buildpath('/fiscal/admin/setup.php', 1));
}
print '</div>';
print '</div>';

llxFooter();
$db->close();
