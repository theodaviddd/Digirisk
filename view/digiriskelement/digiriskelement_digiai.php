<?php
/* Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
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
 *   	\file       view/digiriskelement/digiriskelement_digiai.php
 *		\ingroup    digiriskdolibarr
 *		\brief      Page to create/edit/view digiai
 */

// Load DigiriskDolibarr environment
if (file_exists('../digiriskdolibarr.main.inc.php')) {
    require_once __DIR__ . '/../digiriskdolibarr.main.inc.php';
} elseif (file_exists('../../digiriskdolibarr.main.inc.php')) {
    require_once __DIR__ . '/../../digiriskdolibarr.main.inc.php';
} else {
    die('Include of digiriskdolibarr main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

require_once __DIR__ . '/../../class/digiriskelement.class.php';
require_once __DIR__ . '/../../class/digiriskstandard.class.php';
require_once __DIR__ . '/../../lib/digiriskdolibarr_digiriskelement.lib.php';
require_once __DIR__ . '/../../lib/digiriskdolibarr_function.lib.php';

global $conf, $db, $hookmanager, $langs, $user;

saturne_load_langs(['other']);

$id          = GETPOST('id', 'int');
$action      = GETPOST('action', 'aZ09');
$subaction   = GETPOST('subaction', 'aZ09');
$massaction  = GETPOST('massaction', 'alpha');
$confirm     = GETPOST('confirm', 'alpha');
$cancel      = GETPOST('cancel', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'digiaicard';
$backtopage  = GETPOST('backtopage', 'alpha');
$toselect    = GETPOST('toselect', 'array');
$limit       = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield   = GETPOST('sortfield', 'alpha');
$sortorder   = GETPOST('sortorder', 'alpha');
$fromid      = GETPOST('fromid', 'int');
$page        = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
$page        = is_numeric($page) ? $page : 0;
$page        = $page == -1 ? 0 : $page;

$object           = new DigiriskElement($db);
$digiriskstandard = new DigiriskStandard($db);
$riskAssessment   = new RiskAssessment($db);
$extrafields      = new ExtraFields($db);
$usertmp          = new User($db);
$project          = new Project($db);

$hookmanager->initHooks(array('digiaicard', 'digiriskelementview', 'globalcard'));

// Load Digirisk_element object
include DOL_DOCUMENT_ROOT . '/core/actions_fetchobject.inc.php';

//Permission for digiriskelement_digiai
$permissiontoread   = $user->rights->digiriskdolibarr->digiai->read;
$permissiontoadd    = $user->rights->digiriskdolibarr->digiai->write;
$permissiontodelete = $user->rights->digiriskdolibarr->digiai->delete;

// Security check
saturne_check_access($permissiontoread, $object);

/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) { $action = 'list'; $massaction = ''; }

$parameters = array();
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $digiai, $action); // Note that $action and $digiai may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook)) {
    // Selection of new fields
    include DOL_DOCUMENT_ROOT . '/core/actions_changeselectedfields.inc.php';

    // Purge search criteria
    if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
        foreach ($digiai->fields as $key => $val) {
            $search[$key] = '';
        }
        $toselect             = '';
        $search_array_options = array();
    }
    if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
        || GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
        $massaction = '';
    }

    $error = 0;

    $backtopage = dol_buildpath('/digiriskdolibarr/view/digiriskelement/digiriskelement_digiai.php', 1) . (empty($fromid) ? '?id=' . ($id > 0 ? $id : '__ID__') : '?fromid=' . ($fromid > 0 ? $fromid : '__ID__'));


}

/*
 * View
 */

$form = new Form($db);

$title    = $langs->trans("DigiAI");
$helpUrl  = 'FR:Module_Digirisk#.C3.89valuateurs';

digirisk_header($title, $helpUrl);

print '<div id="cardContent" value="">';

if ($object->id > 0) {
    $res = $object->fetch_optionals();

    saturne_get_fiche_head($object, 'elementDigiAI', $title);

    // Object card
    // ------------------------------------------------------------
    list($morehtmlref, $moreParams) = $object->getBannerTabContent();

    saturne_banner_tab($object,'ref','none', 0, 'ref', 'ref', $morehtmlref, true, $moreParams);

    if ($fromid) {
        print '<div class="underbanner clearboth"></div>';
    }

    print '<div class="fichecenter digiailist wpeo-wrap">';

    print '<form method="POST" enctype="multipart/form-data" action="' . $_SERVER["PHP_SELF"] . '?id='. $id .'" name="upload_image_form" id="upload_image_form">' . "\n";
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="upload_image">';
    print '<input type="hidden" name="id" value="' . $id . '">';

    print '<h2>';
    print $langs->trans('WelcomeToDigiAI');
    print '</h2>';

    print '<div class="digiAI-upload">';
    print '<label for="image_file">' . $langs->trans('UploadAnImage') . ':</label>';
    print '<input type="file" name="image_file" id="image_file" accept="image/*">';
    print '<button type="submit" class="digiAI-upload-button">' . $langs->trans('Upload') . '</button>';
    print '</div>';
    print '</form>';

    print '<div class="wpeo-modal" id="digiai_modal">';
    print '<div class="modal-container">';
    print '<div class="modal-header">';
    print '<h2>Analyse de risques par l\'IA</h2>';
    print '</div>';
    print '<div class="modal-content">';
    print '  <table id="risque_table" style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; display:none">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Cotation</th>
                        <th>Description du Risque</th>
                        <th>Actions de Prévention</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>';
    print '</div>';
    print '</div>';
    print '</div>';

    print '<input hidden id="dol_url_root" value="'. DOL_URL_ROOT .'">';
    print dol_get_fiche_end();
}

print '</div>' . "\n";
print '<!-- End div class="cardcontent" -->';

// End of page
llxFooter();
$db->close();
