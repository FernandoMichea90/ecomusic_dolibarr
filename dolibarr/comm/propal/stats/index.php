<?php
/* Copyright (C) 2003      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2012      Marcos García        <marcosgdf@gmail.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 * Copyright (C) 2020      Maxime DEMAREST      <maxime@indelog.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	    \file       htdocs/comm/propal/stats/index.php
 *      \ingroup    propale
 *		\brief      Page des stats propositions commerciales
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propalestats.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formpropal.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';

$WIDTH = DolGraph::getDefaultGraphSizeForStats('width');
$HEIGHT = DolGraph::getDefaultGraphSizeForStats('height');

$mode = GETPOSTISSET("mode") ? GETPOST("mode", 'aZ09') : 'customer';

$object_status = GETPOST('object_status', 'intcomma');
$typent_id = GETPOST('typent_id', 'int');
$categ_id = GETPOST('categ_id', 'categ_id');

$userid = GETPOST('userid', 'int');
$socid = GETPOST('socid', 'int');
// Security check
if ($user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

$nowyear = strftime("%Y", dol_now());
$year = GETPOST('year') > 0 ? GETPOST('year', 'int') : $nowyear;
$startyear = $year - (empty($conf->global->MAIN_STATS_GRAPHS_SHOW_N_YEARS) ? 2 : max(1, min(10, $conf->global->MAIN_STATS_GRAPHS_SHOW_N_YEARS)));
$endyear = $year;

// Load translation files required by the page
$langs->loadLangs(array('orders', 'companies', 'other', 'suppliers', 'supplier_proposal'));

if ($mode == 'customer' && !$user->rights->propale->lire) {
	accessforbidden();
}
if ($mode == 'supplier' && !$user->rights->supplier_proposal->lire) {
	accessforbidden();
}


/*
 * View
 */

$form = new Form($db);
$formpropal = new FormPropal($db);
$formcompany = new FormCompany($db);
$formother = new FormOther($db);

$langs->loadLangs(array('propal', 'other', 'companies'));

if ($mode == 'customer') {
	$picto = 'propal';
	$title = $langs->trans("ProposalsStatistics");
	$dir = $conf->propal->dir_temp;
	$cat_type = Categorie::TYPE_CUSTOMER;
	$cat_label = $langs->trans("Category").' '.lcfirst($langs->trans("Customer"));
}
if ($mode == 'supplier') {
	$picto = 'supplier_proposal';
	$title = $langs->trans("ProposalsStatisticsSuppliers");
	$dir = $conf->supplier_proposal->dir_temp;
	$cat_type = Categorie::TYPE_SUPPLIER;
	$cat_label = $langs->trans("Category").' '.lcfirst($langs->trans("Supplier"));
}

llxHeader('', $title);

print load_fiche_titre($title, '', $picto);


dol_mkdir($dir);


$stats = new PropaleStats($db, $socid, ($userid > 0 ? $userid : 0), $mode, ($typent_id > 0 ? $typent_id : 0), ($categ_id > 0 ? $categ_id : 0));
if ($object_status != '' && $object_status >= 0) {
	$stats->where .= ' AND p.fk_statut IN ('.$db->sanitize($object_status).')';
}

// Build graphic number of object
$data = $stats->getNbByMonthWithPrevYear($endyear, $startyear);
// $data = array(array('Lib',val1,val2,val3),...)


if (empty($user->rights->societe->client->voir) || $user->socid) {
	$filenamenb = $dir.'/proposalsnbinyear-'.$user->id.'-'.$year.'.png';
	$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=propalstats&file=proposalsnbinyear-'.$user->id.'-'.$year.'.png';
} else {
	$filenamenb = $dir.'/proposalsnbinyear-'.$year.'.png';
	$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=propalstats&file=proposalsnbinyear-'.$year.'.png';
}

$px1 = new DolGraph();
$mesg = $px1->isGraphKo();
if (!$mesg) {
	$px1->SetData($data);
	$i = $startyear;
	$legend = array();
	while ($i <= $endyear) {
		$legend[] = $i;
		$i++;
	}
	$px1->SetLegend($legend);
	$px1->SetMaxValue($px1->GetCeilMaxValue());
	$px1->SetMinValue(min(0, $px1->GetFloorMinValue()));
	$px1->SetWidth($WIDTH);
	$px1->SetHeight($HEIGHT);
	$px1->SetYLabel($langs->trans("NbOfProposals"));
	$px1->SetShading(3);
	$px1->SetHorizTickIncrement(1);
	$px1->mode = 'depth';
	$px1->SetTitle($langs->trans("NumberOfProposalsByMonth"));

	$px1->draw($filenamenb, $fileurlnb);
}

// Build graphic amount of object
$data = $stats->getAmountByMonthWithPrevYear($endyear, $startyear, 0);
// $data = array(array('Lib',val1,val2,val3),...)

if (empty($user->rights->societe->client->voir) || $user->socid) {
	$filenameamount = $dir.'/proposalsamountinyear-'.$user->id.'-'.$year.'.png';
	$fileurlamount = DOL_URL_ROOT.'/viewimage.php?modulepart=propalstats&file=proposalsamountinyear-'.$user->id.'-'.$year.'.png';
} else {
	$filenameamount = $dir.'/proposalsamountinyear-'.$year.'.png';
	$fileurlamount = DOL_URL_ROOT.'/viewimage.php?modulepart=propalstats&file=proposalsamountinyear-'.$year.'.png';
}

$px2 = new DolGraph();
$mesg = $px2->isGraphKo();
if (!$mesg) {
	$px2->SetData($data);
	$i = $startyear;
	$legend = array();
	while ($i <= $endyear) {
		$legend[] = $i;
		$i++;
	}
	$px2->SetLegend($legend);
	$px2->SetMaxValue($px2->GetCeilMaxValue());
	$px2->SetMinValue(min(0, $px2->GetFloorMinValue()));
	$px2->SetWidth($WIDTH);
	$px2->SetHeight($HEIGHT);
	$px2->SetYLabel($langs->trans("AmountOfProposals"));
	$px2->SetShading(3);
	$px2->SetHorizTickIncrement(1);
	$px2->mode = 'depth';
	$px2->SetTitle($langs->trans("AmountOfProposalsByMonthHT"));

	$px2->draw($filenameamount, $fileurlamount);
}

$data = $stats->getAverageByMonthWithPrevYear($endyear, $startyear);

$fileurl_avg = '';
if (empty($user->rights->societe->client->voir) || $user->socid) {
	$filename_avg = $dir.'/ordersaverage-'.$user->id.'-'.$year.'.png';
	if ($mode == 'customer') {
		$fileurl_avg = DOL_URL_ROOT.'/viewimage.php?modulepart=orderstats&file=ordersaverage-'.$user->id.'-'.$year.'.png';
	}
	if ($mode == 'supplier') {
		$fileurl_avg = DOL_URL_ROOT.'/viewimage.php?modulepart=orderstatssupplier&file=ordersaverage-'.$user->id.'-'.$year.'.png';
	}
} else {
	$filename_avg = $dir.'/ordersaverage-'.$year.'.png';
	if ($mode == 'customer') {
		$fileurl_avg = DOL_URL_ROOT.'/viewimage.php?modulepart=orderstats&file=ordersaverage-'.$year.'.png';
	}
	if ($mode == 'supplier') {
		$fileurl_avg = DOL_URL_ROOT.'/viewimage.php?modulepart=orderstatssupplier&file=ordersaverage-'.$year.'.png';
	}
}

$px3 = new DolGraph();
$mesg = $px3->isGraphKo();
if (!$mesg) {
	$px3->SetData($data);
	$i = $startyear;
	$legend = array();
	while ($i <= $endyear) {
		$legend[] = $i;
		$i++;
	}
	$px3->SetLegend($legend);
	$px3->SetYLabel($langs->trans("AmountAverage"));
	$px3->SetMaxValue($px3->GetCeilMaxValue());
	$px3->SetMinValue($px3->GetFloorMinValue());
	$px3->SetWidth($WIDTH);
	$px3->SetHeight($HEIGHT);
	$px3->SetShading(3);
	$px3->SetHorizTickIncrement(1);
	$px3->mode = 'depth';
	$px3->SetTitle($langs->trans("AmountAverage"));

	$px3->draw($filename_avg, $fileurl_avg);
}


// Show array
$data = $stats->getAllByYear();
$arrayyears = array();
foreach ($data as $val) {
	if (!empty($val['year'])) {
		$arrayyears[$val['year']] = $val['year'];
	}
}
if (!count($arrayyears)) {
	$arrayyears[$nowyear] = $nowyear;
}


$h = 0;
$head = array();
$head[$h][0] = DOL_URL_ROOT.'/comm/propal/stats/index.php';
$head[$h][1] = $langs->trans("ByMonthYear");
$head[$h][2] = 'byyear';
$h++;

complete_head_from_modules($conf, $langs, null, $head, $h, 'propal_stats');

print dol_get_fiche_head($head, 'byyear', $langs->trans("Statistics"), -1);


print '<div class="fichecenter"><div class="fichethirdleft">';


// Show filter box
print '<form name="stats" method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="mode" value="'.$mode.'">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="liste_titre" colspan="2">'.$langs->trans("Filter").'</td></tr>';
// Company
print '<tr><td class="left">'.$langs->trans("ThirdParty").'</td><td class="left">';
$filter = 's.client IN (1,2,3)';
print img_picto('', 'company', 'class="pictofixedwidth"');
print $form->select_company($socid, 'socid', $filter, 1, 0, 0, array(), 0, 'widthcentpercentminusx maxwidth300', '');
print '</td></tr>';
// ThirdParty Type
print '<tr><td>'.$langs->trans("ThirdPartyType").'</td><td>';
$sortparam_typent = (empty($conf->global->SOCIETE_SORT_ON_TYPEENT) ? 'ASC' : $conf->global->SOCIETE_SORT_ON_TYPEENT); // NONE means we keep sort of original array, so we sort on position. ASC, means next function will sort on label.
print $form->selectarray("typent_id", $formcompany->typent_array(0), $typent_id, 1, 0, 0, '', 0, 0, 0, $sortparam_typent, '', 1);
if ($user->admin) {
	print ' '.info_admin($langs->trans("YouCanChangeValuesForThisListFromDictionarySetup"), 1);
}
print '</td></tr>';
// Category
print '<tr><td>'.$cat_label.'</td><td>';
print img_picto('', 'category', 'class="pictofixedwidth"');
print $formother->select_categories($cat_type, $categ_id, 'categ_id', 0, 1, 'widthcentpercentminusx maxwidth300');
print '</td></tr>';
// User
print '<tr><td>'.$langs->trans("CreatedBy").'</td><td>';
print img_picto('', 'user', 'class="pictofixedwidth"');
print $form->select_dolusers($userid, 'userid', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 'widthcentpercentminusx maxwidth300');
print '</td></tr>';
// Status
print '<tr><td>'.$langs->trans("Status").'</td><td>';
$formpropal->selectProposalStatus(($object_status != '' ? $object_status : -1), 0, 0, 1, $mode, 'object_status');
print '</td></tr>';
// Year
print '<tr><td>'.$langs->trans("Year").'</td><td>';
if (!in_array($year, $arrayyears)) {
	$arrayyears[$year] = $year;
}
if (!in_array($nowyear, $arrayyears)) {
	$arrayyears[$nowyear] = $nowyear;
}
arsort($arrayyears);
print $form->selectarray('year', $arrayyears, $year, 0, 0, 0, '', 0, 0, 0, '', 'width75');
print '</td></tr>';
print '<tr><td align="center" colspan="2"><input type="submit" name="submit" class="button small" value="'.$langs->trans("Refresh").'"></td></tr>';
print '</table>';
print '</form>';
print '<br><br>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre" height="24">';
print '<td class="center">'.$langs->trans("Year").'</td>';
print '<td class="right">'.$langs->trans("NbOfProposals").'</td>';
print '<td class="right">%</td>';
print '<td class="right">'.$langs->trans("AmountTotal").'</td>';
print '<td class="right">%</td>';
print '<td class="right">'.$langs->trans("AmountAverage").'</td>';
print '<td class="right">%</td>';
print '</tr>';

$oldyear = 0;
foreach ($data as $val) {
	$year = $val['year'];
	while (!empty($year) && $oldyear > $year + 1) {	// If we have empty year
		$oldyear--;

		print '<tr class="oddeven" height="24">';
		print '<td align="center"><a href="'.$_SERVER["PHP_SELF"].'?year='.$oldyear.'&amp;mode='.$mode.($socid > 0 ? '&socid='.$socid : '').($userid > 0 ? '&userid='.$userid : '').'">'.$oldyear.'</a></td>';
		print '<td class="right">0</td>';
		print '<td class="right"></td>';
		print '<td class="right">0</td>';
		print '<td class="right"></td>';
		print '<td class="right">0</td>';
		print '<td class="right"></td>';
		print '</tr>';
	}
	print '<tr class="oddeven" height="24">';
	print '<td align="center"><a href="'.$_SERVER["PHP_SELF"].'?year='.$year.($socid > 0 ? '&socid='.$socid : '').($userid > 0 ? '&userid='.$userid : '').'">'.$year.'</a></td>';
	print '<td class="right">'.$val['nb'].'</td>';
	print '<td class="right opacitylow" style="'.(($val['nb_diff'] >= 0) ? 'color: green;' : 'color: red;').'">'.round($val['nb_diff']).'%</td>';
	print '<td class="right">'.price(price2num($val['total'], 'MT'), 1).'</td>';
	print '<td class="right opacitylow" style="'.(($val['total_diff'] >= 0) ? 'color: green;' : 'color: red;').'">'.round($val['total_diff']).'%</td>';
	print '<td class="right">'.price(price2num($val['avg'], 'MT'), 1).'</td>';
	print '<td class="right opacitylow" style="'.(($val['avg_diff'] >= 0) ? 'color: green;' : 'color: red;').'">'.round($val['avg_diff']).'%</td>';
	print '</tr>';
	$oldyear = $year;
}

print '</table>';
print '</div>';

print '</div><div class="fichetwothirdright">';


// Show graphs
print '<table class="border centpercent"><tr class="pair nohover"><td align="center">';
if ($mesg) {
	print $mesg;
} else {
	print $px1->show();
	print "<br>\n";
	print $px2->show();
	print "<br>\n";
	print $px3->show();
}
print '</td></tr></table>';


print '</div></div>';
print '<div style="clear:both"></div>';


print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();
