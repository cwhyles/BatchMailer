<?php
require '../../../main.inc.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

if (!$user->admin) accessforbidden();

$langs->loadLangs(['batchmailer@batchmailer', 'admin']);

$action = GETPOST('action', 'aZ09');

// Action handler
if ($action === 'save') {

    dolibarr_set_const(
        $db,
        'BATCHMAILER_SEND_DELAY_SECONDS',
        (int) GETPOST('BATCHMAILER_SEND_DELAY_SECONDS', 'int'),
        'chaine',
        0,
        '',
        $conf->entity
    );

    dolibarr_set_const(
        $db,
        'BATCHMAILER_ERRORS_TO',
        GETPOST('BATCHMAILER_ERRORS_TO', 'alphanohtml'),
        'chaine',
        0,
        '',
        $conf->entity
    );

    setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
}

llxHeader('', $langs->trans('BatchMailerSetup'));

print load_fiche_titre($langs->trans('BatchMailerSetup'));


// ------------------------------------------------------------------
// Handle save
// ------------------------------------------------------------------
if (GETPOST('action', 'aZ09') === 'save') {

    dolibarr_set_const(
        $db,
        'BATCHMAILER_SEND_DELAY_SECONDS',
        max(0, (int) GETPOST('BATCHMAILER_SEND_DELAY_SECONDS', 'int')),
        'chaine',
        0,
        '',
        $conf->entity
    );

    setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
}


// Default value if missing
if (!getDolGlobalInt('BATCHMAILER_CSV_PREVIEW_ROWS')) {
    dolibarr_set_const(
        $db,
        'BATCHMAILER_CSV_PREVIEW_ROWS',
        100,
        'chaine',
        0,
        '',
        $conf->entity
    );
}

$form = new Form($db);


// ------------------------------------------------------------------
// Form
// ------------------------------------------------------------------
print '<form method="post">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Parameter').'</td>';
print '<td>'.$langs->trans('Value').'</td>';
print '</tr>';

print '<tr>';
print '<td>';
print $langs->trans('DelayBetweenEmails');
print $form->textwithpicto('', $langs->trans('DelayBetweenEmailsHelp'));
print '</td>';

print '<td>';
print '<input type="number" min="0" name="BATCHMAILER_SEND_DELAY_SECONDS" value="'
    . getDolGlobalInt('BATCHMAILER_SEND_DELAY_SECONDS', 2)
    . '">';
print '</td>';
print '</tr>';

// Recipient of SMTP/transport errors
print '<tr>';
print '<td>'.$langs->trans('BatchMailerErrorsTo');
print $form->textwithpicto('', $langs->trans('BatchMailerErrorsToHelp')).'</td>';
print '<td>';
print '<input type="email" name="BATCHMAILER_ERRORS_TO" value="'
    . dol_escape_htmltag(getDolGlobalString('BATCHMAILER_ERRORS_TO'))
    . '" class="minwidth300">';
print '</td>';
print '</tr>';

print '</table>';

print '<br><input type="submit" class="button button-save" value="'
    . $langs->trans('Save')
    . '">';

print '</form>';

llxFooter();
