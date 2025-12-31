<?php
/* 
 * BatchMailer – Controlled batch email module for Dolibarr
 *
 * Copyright (C) 2025–2026 Colin Whyles
 *
 * This file is part of the BatchMailer module for Dolibarr ERP/CRM.
 *
 * BatchMailer is designed to provide safe, auditable, batch-based
 * email sending using CSV recipient lists and reusable templates.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

require '../../main.inc.php';

//print '<pre> RAW $_POST: ';var_dump($_POST);print '</pre>';
// ---------------------------------------------------------------------
// Restore selected template from session if not provided by POST
// ---------------------------------------------------------------------
$selectedFile = GETPOST('template_file', 'alpha');

if ($selectedFile) {
    $_SESSION['batchmailer_template'] = $selectedFile;
} elseif (!empty($_SESSION['batchmailer_template'])) {
    $selectedFile = $_SESSION['batchmailer_template'];
}

// Load language file
$langs->loadLangs(['batchmailer@batchmailer']);

require_once DOL_DOCUMENT_ROOT.'/custom/batchmailer/lib/csv_preview.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/batchmailer/lib/batchmailer.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/batchmailer/lib/admin.lib.php';

// Security check
if (empty($user->rights->batchmailer->run)) {
    accessforbidden();
}

// Create batchmailer email log directory
dol_mkdir(DOL_DATA_ROOT . '/batchmailer/logs');

// ---------------------------------------------------------------------
// ACTION HANDLERS (controller logic)
// ---------------------------------------------------------------------
// Check for CSV file actions
$action = GETPOST('action', 'aZ09');

if ($action === 'clearworkflow') {
    batchmailer_reset_downstream_state();

    // Optional: also clear template / CSV if you want a hard reset
/*
    unset(
        $_SESSION['batchmailer_template'],
        $_SESSION['batchmailer_csv']
    );
*/
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=prepare');
    exit;
}

$file   = GETPOST('file', 'alpha');
// template action handler
$template = GETPOST('template', 'alpha');
// Check for template edit
// If template selection is posted, update session immediately (before approval handling)
if (
    GETPOSTISSET('template_file') &&
    (int) GETPOST('approve_send', 'int') === 0
) {
    $postedTemplate = GETPOST('template_file', 'alphanohtml');

    if (
        empty($_SESSION['batchmailer_template']) ||
        $_SESSION['batchmailer_template'] !== $postedTemplate
    ) {
        $_SESSION['batchmailer_template'] = $postedTemplate;
        unset($_SESSION['batchmailer_approved']);
    }
}

// Dry run 2 from Dry-run Batch button in dry_run_ui()
$doDryRun = (int) GETPOST('do_dryrun', 'int') === 1;
// ---------------------------------------------------------------------
// DRY-RUN ACTION HANDLER (controller logic)
// ---------------------------------------------------------------------
if ($doDryRun) {

    if (empty($_SESSION['batchmailer_approved'])) {

        setEventMessages(
            $langs->trans('ApprovalRequiredBeforeDryRun'),
            null,
            'errors'
        );

    } else {

        $template = load_template_file($_SESSION['batchmailer_template']);
        $csvPath  = $_SESSION['batchmailer_csv']['path'];

        if (!$template || !$csvPath) {

            setEventMessages(
                $langs->trans('UnableToRunDryRun'),
                null,
                'errors'
            );

        } else {

            $result = dry_run_batch($csvPath, $template);

            if (empty($result['error'])) {

                $_SESSION['batchmailer_dryrun_ok'] = [
                    'csv'      => $csvPath,
                    'template' => $_SESSION['batchmailer_template'],
                    'time'     => time(),
                    'stats'    => $result,
                ];

            } else {

                setEventMessages($result['error'], null, 'errors');
            }
        }
    }

    // PRG pattern — prevent resubmission
    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=send');
    exit;
}

$tab = GETPOST('tab', 'aZ09');
if (empty($tab)) $tab = 'prepare';

// CSV file management
$deleteFile = GETPOST('deletefile', 'restricthtml');

//$action = GETPOST('action', 'aZ09');

// --- ACTION HANDLERS FIRST ---

if ($action === 'cleartemplate') {
    unset($_SESSION['batchmailer_template']);
    unset($_SESSION['batchmailer_preview_done']);
    unset($_SESSION['batchmailer_stats']);
    unset($_SESSION['batchmailer_approved']);
    // Cancel 'Batch sending aborted' message panel
    unset($_SESSION['batchmailer_aborted']);

    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=' . urlencode($tab));
    exit;
}

if ($action === 'clearcsv') {
    unset($_SESSION['batchmailer_csv']);
    unset($_SESSION['batchmailer_preview_done']);
    unset($_SESSION['batchmailer_stats']);
    unset($_SESSION['batchmailer_approved']);
    // Cancel 'Batch sending aborted' message panel
    unset($_SESSION['batchmailer_aborted']);
    unset($_SESSION['batchmailer_csv_analysis']);

    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=' . urlencode($tab));
    exit;
}

// --- ONLY NOW read session state / render UI ---

$csvDir = DOL_DATA_ROOT . '/batchmailer/csv';

// Use a previously saved file
if ($action === 'usecsv' && $file) {
    $csvPath = $csvDir . '/' . $file;

    if (is_readable($csvPath)) {
        // Extract original name (strip timestamp_)
        $originalName = preg_replace('/^\d+_/', '', $file);

        $_SESSION['batchmailer_csv'] = [
            'path'     => realpath($csvPath),
            'name'     => $originalName,
            'uploaded' => filemtime($csvPath)
        ];

        // CSV changed → approval & preview no longer valid

        // Clear downstream state
        batchmailer_reset_workflow_state();
        batchmailer_reset_campaign_state();
        // Cancel 'Batch sending aborted' message panel
        unset($_SESSION['batchmailer_aborted']);
    }

    header('Location: ' . $_SERVER['PHP_SELF']. '?tab=prepare');
    exit;
}

// end CSV file action test

if ($action === 'usetemplate' && $template) {

    // Basic safety: ensure the file exists
    $templatePath = DOL_DATA_ROOT . '/batchmailer/templates/' . $template;

    if (is_readable($templatePath)) {
        $_SESSION['batchmailer_template'] = $template;

        // Template change invalidates downstream state
        batchmailer_reset_workflow_state();
        batchmailer_reset_campaign_state();
        // Cancel 'Batch sending aborted' message panel
        unset($_SESSION['batchmailer_aborted']);
    }

    // Stay on Templates tab
    header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=templates');
    exit;
}

// Delete template
// CSV file management
$deleteTplFile = GETPOST('deletetplfile', 'restricthtml');

if ($action === 'deletetpl' && $deleteTplFile) {
    $templateDir = realpath(DOL_DATA_ROOT.'/batchmailer/templates');
    $filePath = realpath($templateDir.'/'.$deleteTplFile);
    // Safety check: must be inside csv dir
    if ($filePath && strpos($filePath, $templateDir) === 0 && file_exists($filePath)) {

        // Do not delete active template
        if (!empty($_SESSION['batchmailer_template']['path']) &&
            realpath($_SESSION['batchmailer_template']['path']) === $filePath) {

            setEventMessages($langs->trans('CannotDeleteActiveTemplateFile'), null, 'warnings');

        } else {
            unlink($filePath);
            setEventMessages($langs->trans('TemplateFileDeleted'), null, 'mesgs');
        }
    }
}

// Copy template file action handler
$copyTplFile = GETPOST('template_file', 'restricthtml');
if ($action === 'copytemplate' && $copyTplFile) {
    $templateDir = DOL_DATA_ROOT . '/batchmailer/templates';

    $fileName = pathinfo($copyTplFile, PATHINFO_FILENAME); // $fileName is set to "name"
    $newTplFile = $templateDir . '/' . $fileName . '_' . time() . '.json'; // Create a unique file name
    if (!copy($templateDir.'/'.$copyTplFile, $newTplFile))
    {
        setEventMessages(
            $langs->trans('Copy template '.$copyTplFile.' failed'),
            null,
            'errors'
        );
    }
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=templates');
}
// end template action handler

// Send batch action handler
if (GETPOST('action') === 'send_batch') {
	// Enforce Dry-run before sending
	if (
		empty($_SESSION['batchmailer_dryrun_ok']) &&
		empty($_SESSION['batchmailer_admin_override'])
	) {
		setEventMessages(
			$langs->trans('DryRunRequiredBeforeSending'),
			null,
			'errors'
		);
		header('Location: '.$_SERVER['PHP_SELF'].'?tab=send');
		exit;
	}

    // Session aborted, block further sending
    if (!empty($_SESSION['batchmailer_aborted'])) {
        setEventMessages(
            $langs->trans('BatchAlreadyAborted'),
            null,
            'errors'
        );
        header('Location: '.$_SERVER['PHP_SELF'].'?tab=send');
        exit;
    }

    if (empty($_SESSION['batchmailer_total'])) {
        $_SESSION['batchmailer_total'] =
            (int) ($_SESSION['batchmailer_csv_analysis']['total_rows'] ?? 0);
    
        // Fallback if analysis is missing
        if ($_SESSION['batchmailer_total'] <= 0) {
                $csvStats = analyze_csv_for_preview($_SESSION['batchmailer_csv']['path'], 0);
                $_SESSION['batchmailer_total'] = $csvStats['total_rows'];
        }
    }
    
    $postBatchSize = (int) GETPOST('batch_size', 'int');
    
    if ($postBatchSize > 0) {
        $_SESSION['batchmailer_batch_size'] = $postBatchSize;
    }
    
    $batchSize = (int) ($_SESSION['batchmailer_batch_size'] ?? 50);
    
    // Initialise campaign counters once per campaign
    if (!isset($_SESSION['batchmailer_offset'])) {
        $_SESSION['batchmailer_offset'] = 0;
    }

    if (!isset($_SESSION['batchmailer_total'])) {
        $_SESSION['batchmailer_total'] = 
            (int) ($_SESSION['batchmailer_csv_analysis']['total_rows'] ?? 0);
    }
    
    if (!isset($_SESSION['batchmailer_campaign_totals'])) {
        $_SESSION['batchmailer_campaign_totals'] = [
            'sent'   => 0,
            'failed' => 0,
        ];
    }
    // Cancel 'Batch sending aborted' message panel
    unset($_SESSION['batchmailer_aborted']);

    //Mark "sending" state (critical)
    $_SESSION['batchmailer_sending'] = true;

	// Check if send is locked by Admin    
	if (!empty($_SESSION['batchmailer_send_locked'])) {
		setEventMessages(
			$langs->trans('SendingBlockedByAdmin'),
			null,
			'errors'
		);
		header('Location: '.$_SERVER['PHP_SELF'].'?tab=send');
		exit;
	}

    // Run the batch send (core operation)
    $template = load_template_file($_SESSION['batchmailer_template']);
    $result = send_batch_emails(
        $_SESSION['batchmailer_csv']['path'], /***/
        $template, //$_SESSION['batchmailer_template'],
        $batchSize,
        $_SESSION['batchmailer_offset']
    );

$_SESSION['batchmailer_campaign_totals']['sent']
    += (int) ($result['sent'] ?? 0);

$_SESSION['batchmailer_campaign_totals']['failed']
    += count($result['failed'] ?? []);
    
    // Update session state from result
    $_SESSION['batchmailer_offset'] += $result['attempted'];
    $_SESSION['batchmailer_send_result'] = $result;
    // Clear sending flag (always)
    unset($_SESSION['batchmailer_sending']);
    // end of send loop

    // Decide next UI state (implicitly)
    $completed = $_SESSION['batchmailer_offset'] >= $_SESSION['batchmailer_total'];
    
    // PRG redirect (mandatory)
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=send');
    exit;
}
// end Send Batch action handler

// Reset Button action handler
if (GETPOST('action') === 'reset_batch') {

    unset($_SESSION['batchmailer_aborted']);

    batchmailer_reset_campaign_state();

    header('Location: '.$_SERVER['PHP_SELF'].'?tab=prepare');
    exit;
}

// Abort action handler
if (GETPOST('action') === 'abort_batch') {

    $_SESSION['batchmailer_aborted'] = [
        'offset' => (int) $_SESSION['batchmailer_offset'],
        'total'  => (int) $_SESSION['batchmailer_total'],
        'time'   => time(),
    ];
    
    // Stop sending immediately
    unset($_SESSION['batchmailer_sending']);

    setEventMessages(
        $langs->trans('BatchAbortedMessage'),
        null,
        'warnings'
    );

    header('Location: '.$_SERVER['PHP_SELF'].'?tab=send');
    exit;
}

// Continuation after abort
if ($action === 'start_new_batch') {

    batchmailer_reset_campaign_state(); 
    // clears offset, totals, aborted, send_result, campaign_totals

    header('Location: '.$_SERVER['PHP_SELF'].'?tab=prepare');
    exit;
}

// Dismiss abort 'Batch sending aborted' panel
if ($action === 'dismiss_abort') {
    unset($_SESSION['batchmailer_aborted']);
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=send');
    exit;
}

// ---------------------------------------------------------------------
// ADMIN LOG ACTION HANDLERS (controller logic)
// ---------------------------------------------------------------------
if ($tab === 'admin') {

    if (empty($user->rights->batchmailer->admin)) {
        accessforbidden();
    }

    // View log (inline)
    if ($action === 'viewlog') {
        $logfile = GETPOST('logfile', 'alphanohtml');
        $path = batchmailer_resolve_log_path($logfile);

        if (!$path) {
            setEventMessages($langs->trans('ErrorFileNotFound'), null, 'errors');
            header('Location: '.$_SERVER['PHP_SELF'].'?tab=admin');
            exit;
        }

        // Store content in a session var to avoid long URLs? Not necessary.
        // We'll just render it in the UI section later by setting a flag.
        $_SESSION['batchmailer_admin_viewlog'] = $logfile;

        header('Location: '.$_SERVER['PHP_SELF'].'?tab=admin');
        exit;
    }

    // Download log (force download)
    if ($action === 'downloadlog') {
        $logfile = GETPOST('logfile', 'alphanohtml');
        $path = batchmailer_resolve_log_path($logfile);

        if (!$path) {
            setEventMessages($langs->trans('ErrorFileNotFound'), null, 'errors');
            header('Location: '.$_SERVER['PHP_SELF'].'?tab=admin');
            exit;
        }

        // Send file
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.basename($path).'"');
        header('Content-Length: '.filesize($path));

        // Important: no extra output
        readfile($path);
        exit;
    }

    // Delete log (safe delete pattern)
    if ($action === 'deletelog') {

        // Optional safeguard: don’t delete while actively sending
        if (!empty($_SESSION['batchmailer_sending'])) {
            setEventMessages($langs->trans('CannotDeleteWhileSending'), null, 'warnings');
            header('Location: '.$_SERVER['PHP_SELF'].'?tab=admin');
            exit;
        }

        $logfile = GETPOST('logfile', 'alphanohtml');
        $path = batchmailer_resolve_log_path($logfile);

        if (!$path) {
            setEventMessages($langs->trans('ErrorFileNotFound'), null, 'errors');
            header('Location: '.$_SERVER['PHP_SELF'].'?tab=admin');
            exit;
        }

        @unlink($path);

        // Clear viewer if it was open
        if (!empty($_SESSION['batchmailer_admin_viewlog']) && $_SESSION['batchmailer_admin_viewlog'] === $logfile) {
            unset($_SESSION['batchmailer_admin_viewlog']);
        }

        setEventMessages($langs->trans('LogFileDeleted'), null, 'mesgs');
        header('Location: '.$_SERVER['PHP_SELF'].'?tab=admin');
        exit;
    }

    // Close viewer explicitly (optional)
    if ($action === 'closelog') {
        unset($_SESSION['batchmailer_admin_viewlog']);
        header('Location: '.$_SERVER['PHP_SELF'].'?tab=admin');
        exit;
    }
}

// Resume campaign
if ($action === 'resumecampaign') {
    // No state change — just redirect
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=send');
    exit;
}

// Restart campaign
if ($action === 'restartcampaign') {

    batchmailer_reset_downstream_state();

    // Explicitly reset counters
    $_SESSION['batchmailer_offset'] = 0;
    unset($_SESSION['batchmailer_aborted']);

    setEventMessages(
        $langs->trans('CampaignRestarted'),
        null,
        'mesgs'
    );

    header('Location: '.$_SERVER['PHP_SELF'].'?tab=send');
    exit;
}

// Clear campaign
if ($action === 'clearcampaign') {

    batchmailer_reset_campaign_state();

    setEventMessages(
        $langs->trans('CampaignCleared'),
        null,
        'mesgs'
    );

    header('Location: '.$_SERVER['PHP_SELF'].'?tab=prepare');
    exit;
}

// Safety send lock action handlers
if ($action === 'locksend') {
    $_SESSION['batchmailer_send_locked'] = true;
    setEventMessages($langs->trans('SendingLocked'), null, 'warnings');
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=admin');
    exit;
}

if ($action === 'unlocksend') {
    unset($_SESSION['batchmailer_send_locked']);
    setEventMessages($langs->trans('SendingUnlocked'), null, 'mesgs');
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=admin');
    exit;
}

if ($action === 'enableoverride') {
    $_SESSION['batchmailer_admin_override'] = true;
    setEventMessages(
        $langs->trans('AdminOverrideEnabled'),
        null,
        'warnings'
    );
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=admin');
    exit;
}

if ($action === 'disableoverride') {
    unset($_SESSION['batchmailer_admin_override']);
    setEventMessages(
        $langs->trans('AdminOverrideDisabled'),
        null,
        'mesgs'
    );
    header('Location: '.$_SERVER['PHP_SELF'].'?tab=admin');
    exit;
}

// END of ADMIN ACTION HANDLERS

// --- END OF ACTION HANDLERS ---

// Check for approval of message
if (GETPOSTISSET('approve_send')) {

    $approveSend = (int) GETPOST('approve_send', 'int');

    if ($approveSend === 1) {
        $_SESSION['batchmailer_approved'] = [
            'csv'      => $_SESSION['batchmailer_csv']['path'] ?? '',
            'template' => $_SESSION['batchmailer_template'] ?? '',
            'time'     => time(),
        ];
    } else {
        unset($_SESSION['batchmailer_approved']);
    }
}


// Tabs
$head = array();

$head[0][0] = $_SERVER['PHP_SELF'].'?tab=prepare';
$head[0][1] = $langs->trans('PrepareRecipientListTab');
$head[0][2] = 'prepare';

$head[1][0] = $_SERVER['PHP_SELF'].'?tab=templates';
$head[1][1] = $langs->trans('TemplatesTab');
$head[1][2] = 'templates';

$head[2][0] = $_SERVER['PHP_SELF'].'?tab=send';
$head[2][1] = $langs->trans('SendBatchTab');
$head[2][2] = 'send';

$head[3][0] = $_SERVER['PHP_SELF'].'?tab=admin';
$head[3][1] = $langs->trans('AdministrationTab');
$head[3][2] = 'admin';

/*
 * FIRST
 */
if (!empty($_FILES['csvfile']['tmp_name'])) {

    $uploadDir = DOL_DATA_ROOT . '/batchmailer/csv';
    dol_mkdir($uploadDir);

    $originalName = basename($_FILES['csvfile']['name']);
    $safeName = dol_sanitizeFileName($originalName);
    $target = $uploadDir . '/' . time() . '_' . $safeName;

    if (move_uploaded_file($_FILES['csvfile']['tmp_name'], $target)) {
        $_SESSION['batchmailer_csv'] = [
            'path'     => $target,
            'name'     => $originalName,
            'uploaded' => time()
        ];

        // CSV changed → approval no longer valid
        batchmailer_reset_workflow_state();
        batchmailer_reset_campaign_state();
    }
}

/*
 * SECOND
 * Restore CSV state on every tab
 * Avoid re-reading preview rows unnecessarily
 * Guarantee $csvHeaders is always defined (array, never NULL)
*/
$csvHeaders = [];

if (
    !empty($_SESSION['batchmailer_csv']['path']) &&
    file_exists($_SESSION['batchmailer_csv']['path'])
) {
    $csvHeaders = read_csv_header($_SESSION['batchmailer_csv']['path']);
}

// CSV file management
if ($action === 'deletecsv' && $deleteFile) {

    $csvDir = realpath(DOL_DATA_ROOT.'/batchmailer/csv');
    $filePath = realpath($csvDir.'/'.$deleteFile);
    // Safety check: must be inside csv dir
    if ($filePath && strpos($filePath, $csvDir) === 0 && file_exists($filePath)) {

        // Do not delete active CSV
        if (!empty($_SESSION['batchmailer_csv']['path']) &&
            realpath($_SESSION['batchmailer_csv']['path']) === $filePath) {

            setEventMessages($langs->trans('CannotDeleteActiveCSVFile'), null, 'warnings');

        } else {
            unlink($filePath);
            setEventMessages($langs->trans('CSVFileDeleted'), null, 'mesgs');
        }
    }
}


// LAST POSSIBLE USE OF $action
//Check for template edit
if (!in_array($action, ['edit', 'create', 'delete', ''], true)) {
    $action = '';
}

//=========================================================
// ---- UI START ----
//=========================================================

$cssfiles = array(
    '/custom/batchmailer/css/batchmailer.css'
);

llxHeader('', 'Batch Mailer', '', '', 0, 0, '', $cssfiles);

dol_fiche_head($head, $tab, 'Batch Mailer');
// After print dol_fiche_head(...)

// Show Quick-Start
render_batchmailer_quickstart($tab);

// Show current state of workflow
print render_batchmailer_current_selections($tab);

$hasCsv        = !empty($_SESSION['batchmailer_csv']);
$hasTemplate   = !empty($_SESSION['batchmailer_template']);
if ($tab === 'prepare' && $hasCsv && $hasTemplate) {
    print '<div class="info" style="margin-top:10px">';
    print '<strong>'.$langs->trans('NextStep').'</strong><br>';
    print $langs->trans('ContinueEditingTemplateWithCsvFields');
    print '<br><br>';
    print '<a class="butAction" href="'
        . dol_buildpath('/custom/batchmailer/template_editor.php', 1)
        . '?template_file=' . urlencode($_SESSION['batchmailer_template'])
        . '&returntab=prepare">'
        . $langs->trans('ContinueEditingTemplate')
        . '</a>';
    print '</div>';
}

// Content placeholder
print '<div class="tabcontent" id="batchmailer-tabs">';

$approved = false;

if (!empty($_SESSION['batchmailer_approved'])) {
    $approved =
        ($_SESSION['batchmailer_approved']['csv'] === ($_SESSION['batchmailer_csv']['path'] ?? '')) &&
        ($_SESSION['batchmailer_approved']['template'] === ($_SESSION['batchmailer_template'] ?? ''));
}


switch ($tab) {

    //------------------------------------------------------------------------
    // Template tab
    //
    case 'templates':
    
        print '<h2>Templates</h2>';

        // Template management editor
        print '<div class="tabsAction">';
        print '<a class="butAction" href="'
            . dol_buildpath('/custom/batchmailer/template_editor.php?action=create&returntab=templates', 1)
            . '">';
        print $langs->trans('CreateNewTemplate');
        print '</a>';
        print '</div>';
    
        $templateDir = DOL_DATA_ROOT . '/batchmailer/templates';
        dol_mkdir($templateDir);
    
        $templates = [];
    
        foreach (glob($templateDir . '/*.json') as $file) {
            $json = file_get_contents($file);
            $data = json_decode($json, true);
    
            if (is_array($data)) {
                $templates[basename($file)] = $data;
            }
        }
    
        print '<h3>'.$langs->trans('AvailableTemplates').'</h3>';
    
        if (empty($templates)) {
            print '<div class="warning">'.$langs->trans('NoTemplatesFound').'</div>';
        } else {
            print '<table class="noborder centpercent">';
            print '<tr class="liste_titre">';
            print '<th>Select</th><th>Name</th><th>Description</th><th>Subject</th><th>Status</th><th>Action</th>';
            print '</tr>';
            
            // Display the available templates
            foreach ($templates as $file => $tpl) {
    
                $missing = validate_template_against_csv($tpl, $csvHeaders);
                $activeTemplate = $_SESSION['batchmailer_template'] ?? '';
                $isActive = ($file === $activeTemplate);
    
                print '<tr>';
                // Select column
                print '<td class="center">';
                
                // Select
                if ($isActive) {
                    print '&#9679;';
                } else {
                    print '<a class="butAction" href="?action=usetemplate&template='
                        . urlencode($file) . '">'
                        . $langs->trans('UseLabel')
                        . '</a>';
                }
                
                print '</td>';
                // Name
                print '<td>';
                    if ($isActive) {
                        print '<strong>' . dol_escape_htmltag($tpl['name']) . '</strong>';
                    } else {
                        print dol_escape_htmltag($tpl['name']);
                    }
                print '</td>';
                // Description
                print '<td>' . dol_escape_htmltag($tpl['description'] ?? '') . '</td>';
                // Subject
                print '<td>' . dol_escape_htmltag($tpl['subject'] ?? '') . '</td>';
                // Status
                print '<td>';
                if (empty($missing)) {
                    print '<span class="ok">Ready</span>';
                } else {
                    print '<span class="error">Missing: ' . implode(', ', $missing) . '</span>';
                }
                print '</td>';
                // Actions (Edit/Copy/Delete)
                print '<td class="nowrap">';
                // Edit: https://suffolkpoetrysociety.org/dbar/custom/batchmailer/template_editor.php?template_file=renewalreminder.json&returntab=templates
                print '<a class="butAction" href="'
                    . '/dbar/custom/batchmailer/template_editor.php?template_file=' 
                    . urlencode($file) 
                    . '&returntab=templates"'
                    . ' onclick="return confirm(\'' 
                    . dol_escape_js($langs->trans(
                        'ConfirmEditTemplate', dol_escape_htmltag($tpl['name'])))
                    . '\');">' 
                    . $langs->trans('EditLabel')
                    . '</a>';
                
                // Copy
                print '<a class="butAction" href="?action=copytemplate&template_file=' . urlencode($file) . '"'
                    . ' onclick="return confirm(\'' 
                    . dol_escape_js($langs->trans(
                        'ConfirmCopyTemplate', dol_escape_htmltag($tpl['name'])))
                    . '\');">' 
                    . $langs->trans('CopyLabel')
                    . '</a>';
                
                // Delete
                print '<a class="butActionDelete" href="?action=deletetpl&deletetplfile=' . urlencode($file) . '"'
                    . ' onclick="return confirm(\'' 
                    . dol_escape_js($langs->trans(
                        'ConfirmDeleteTemplate', dol_escape_htmltag($tpl['name'])))
                    . '\');">'
                    . $langs->trans('DeleteLabel')
                    . '</a>';

                print '</td>';
    
                print '</tr>';
            }
    
            print '</table>';
        }

    break;

    //------------------------------------------------------------------------
    // Send tab
    //
    case 'send':
		if (!empty($_SESSION['batchmailer_admin_override'])) {
			print '<div class="warning">';
			print $langs->trans('SendingWithAdminOverride');
			print '</div>';
		}

        // Base flags
        $hasCsv        = !empty($_SESSION['batchmailer_csv']);
        $hasTemplate   = !empty($_SESSION['batchmailer_template']);
        $hasHeaders    = !empty($csvHeaders);
        $isApproved    = !empty($_SESSION['batchmailer_approved']);
        $hasDryRun     = !empty($_SESSION['batchmailer_dryrun_ok']);
        $hasStartedSending = isset($_SESSION['batchmailer_offset']);
        $hasSendResult = !empty($_SESSION['batchmailer_send_result']);
        $isSending     = !empty($_SESSION['batchmailer_sending']);

        if ($hasStartedSending) {
            $offset    = (int) $_SESSION['batchmailer_offset'];
            $total     = (int) $_SESSION['batchmailer_total'];
            $completed = ($total > 0 && $offset >= $total);

            if ($completed) {
                $sendState = 'completed';
            } elseif ($isSending) {
                $sendState = 'sending_batch';
            } elseif ($hasSendResult) {
                // A batch has completed but more remain
                $sendState = 'ready_to_send';
            }
        } else {
            // Pre-send state resolution (order matters)
            if (!$hasCsv) {
                $sendState = 'no_csv';
            } elseif (!$hasTemplate) {
                $sendState = 'no_template';
            } elseif (!$hasHeaders) {
                $sendState = 'bad_csv';
            } elseif (!$isApproved) {
                $sendState = 'preview_and_approve';
            } elseif (!$hasDryRun) {
                $sendState = 'approved';
            } else {
                $sendState = 'ready_to_send';
            }
        }

        if (!empty($_SESSION['batchmailer_aborted'])) {
            $sendState = 'aborted';
        }
/*        
error_log(
    'SEND TAB STATE: offset=' . ($_SESSION['batchmailer_offset'] ?? 'unset')
  . ' total=' . ($_SESSION['batchmailer_total'] ?? 'unset')
  . ' hasSendResult=' . (empty($_SESSION['batchmailer_send_result']) ? 'no' : 'yes')
  . ' approved=' . (empty($_SESSION['batchmailer_approved']) ? 'no' : 'yes')
  . ' dryrun=' . (empty($_SESSION['batchmailer_dryrun_ok']) ? 'no' : 'yes')
  . ' sendState= '.$sendState
);
*/
       // Send batch
        print '<h2>'.$langs->trans('SendBatchTab').'</h2>';

            // Post batch abort warning – just in case
            if (!empty($_SESSION['batchmailer_aborted'])) {
                print '<div class="warning">'
                    . $langs->trans('BatchAbortedCannotSend')
                    . '</div>';
            }
            // Show progress
            $ctx = batchmailer_progress_context($langs);
            
            if ($ctx['offset'] > 0 && $ctx['offset'] < $ctx['total']) {
                print batch_status_panel(
                    $ctx,
                    $_SESSION['batchmailer_send_result'] ?? [],
                    $langs
                );

                print '
                <form method="post" style="margin-top:10px">
                    <input type="hidden" name="action" value="abort_batch">
                    <button type="submit"
                            class="butActionDelete"
                            onclick="return confirm(\''.$langs->trans('AbortBatchConfirm').'\');">
                        '.$langs->trans('AbortBatch').'
                    </button>
                </form>';
            }
        
        $progress = batchmailer_progress_context($langs);
        print $progress['progress_html'];

        // SEND TAB – STATUS / WARNINGS
        switch ($sendState) {
    
        case 'no_csv':
            print warning_upload_csv($langs);
            break;
    
        case 'no_template':
            print warning_select_template($langs);
            print template_selector($csvHeaders, $langs);
            break;
    
        case 'bad_csv':
            print warning_bad_csv($langs);
            break;
    
        case 'preview_and_approve':
            if (!$approved) {
                print warning_preview_required($langs);
            }
            print preview_ui($langs);
            print approval_ui($approved, $langs);
            break;
    
        case 'approved':
            print approval_confirmed($langs);
            print dry_run_ui($langs);
            // If this request is a dry-run, render results immediately
            if (
                empty($_SESSION['batchmailer_offset']) &&
                !empty($_SESSION['batchmailer_dryrun_ok'])
            ) {
                print dry_run_results_ui(
                    $_SESSION['batchmailer_dryrun_ok']['stats'],
                    $langs
                );
            }
            break;

        case 'ready_to_send':
            print '<div class="ok">'
                . $langs->trans('ReadyToSend')
                . '</div>';
        
            if ($progress['total'] <= 0) {
                print '<div class="warning">'
                    . $langs->trans('BatchMailerCampaignStateInvalid')
                    . '</div>';
                print start_new_batch_ui($langs);
            }
            
            if (
                empty($_SESSION['batchmailer_offset']) &&
                !empty($_SESSION['batchmailer_dryrun_ok'])) {
				print '<div class="warning">';
				print $langs->trans('DryRunMustBeCompleted');
				print '</div>';
            } else {
                print dry_run_results_ui(
                    $_SESSION['batchmailer_dryrun_ok']['stats'],
                    $langs
                );
            }

            print overall_progress_ui(
                $_SESSION['batchmailer_offset'] ?? null,
                $_SESSION['batchmailer_total'] ?? null,
                $_SESSION['batchmailer_batch_size'] ?? null,
                $langs
            );

			$progress = batchmailer_progress_context($langs);
			print $progress['progress_html'];
			
			if ($progress['hasCampaign']) {
				batchmailer_render_send_admin_hint($langs);
			}
            if (!$progress['hasCampaign']) {
                // Before first send
                print send_batch_ui(null, null, $langs);
            } elseif ($progress['offset'] < $progress['total']) {
                // Mid-campaign
                print send_batch_ui(
                    $progress['nextBatch'],
                    $progress['totalBatches'],
                    $langs
                );
            }
            break;
            
        case 'sending_batch':
            print '<div class="info">'
                . $langs->trans('SendingEmails')
                . '</div>';
            
            print sending_progress_ui($langs);
        
            break;

        case 'aborted':
        
            print render_batchmailer_aborted_panel(
                $_SESSION['batchmailer_aborted'],
                $langs
            );
        
            break;
            
        case 'completed':

            print '<div class="ok">'
                . $langs->trans('BatchSendCompleted')
                . '</div>';
        
            print final_summary_ui(
                $_SESSION['batchmailer_campaign_totals'],
                $langs
            );
        
            print start_new_batch_ui($langs);
        
            break;
        }
    break;

    //------------------------------------------------------------------------
    // Prepare data tab
    //
    case 'prepare':
        print '<h2>'.$langs->trans('PrepareRecipientListTab').'</h2>';

        if (!empty($_SESSION['batchmailer_csv'])) {
            print '<div class="info">';
            print $langs->trans('ActiveRecipientFile').': <strong>'
                . dol_escape_htmltag($_SESSION['batchmailer_csv']['name'])
                . '</strong><br>';
            print 'Uploaded: '
                . dol_print_date($_SESSION['batchmailer_csv']['uploaded'], 'dayhour');
            print '</div>';
        }
        
        // Link to Dolibarr adherent export.
        print '<div class="info" style="margin-bottom:10px">';
        print $langs->trans('BatchMailerNoCsvHelp');
        print '</div>';
        
        $url = dol_buildpath(
            '/exports/export.php?step=2&module_position=06&datatoexport=adherent_1',
            1
        );
        
        print '<a class="butAction" href="'.$url.'" target="_blank">'
            . $langs->trans('BatchMailerExportMembers')
            . '</a>';

        // Upload a recipient file
        print '<form method="post" enctype="multipart/form-data">';
        print '<input type="file" name="csvfile" accept=".csv">';
        print '<br><br>';
        print '<input type="submit" class="button" value="'
            . $langs->trans('UploadRecipientFile')
            . '">';
        print '</form>';
    
        if (!empty($_SESSION['batchmailer_csv'])) {
            print '<div class="info">';
            print $langs->trans('ActiveRecipientFile').': <strong>' 
                . dol_escape_htmltag($_SESSION['batchmailer_csv']['name']) 
                . '</strong>';
            print ' (uploaded ' . dol_print_date($_SESSION['batchmailer_csv']['uploaded'], 'dayhour') . ')';
            print '</div>';
        }

        $maxRows = max(1, (int) getDolGlobalInt('BATCHMAILER_CSV_PREVIEW_ROWS'));
        if (!empty($_SESSION['batchmailer_csv']['path'])) {
            $_SESSION['batchmailer_csv_analysis'] = analyze_csv_for_preview(
                $_SESSION['batchmailer_csv']['path'],
                $maxRows
            );
            $preview = $_SESSION['batchmailer_csv_analysis'];
        }

        if (!empty($preview['rows'])) {
            print '<table class="noborder centpercent">';
            print '<tr>';
        
            foreach ($preview['header'] as $col) {
                print '<th>'.dol_escape_htmltag($col).'</th>';
            }
            print '</tr>';
        
            foreach ($preview['rows'] as $row) {
                print '<tr>';
                foreach ($row as $cell) {
                    print '<td>'.dol_escape_htmltag($cell).'</td>';
                }
                print '</tr>';
            }
            print '</table>';
        }

        if (!empty($preview['errors'])) {
            print '<div class="error">';
            foreach ($preview['errors'] as $err) {
                print dol_escape_htmltag($err).'<br>';
            }
            print '</div>';
        } else {
//            print '<p>Valid rows: '.$preview['valid_rows'].'</p>';
//            print '<p>Total rows: '.$preview['total_rows'].'</p>';
        }
        
        // CSV file maintenance
        $csvDir = DOL_DATA_ROOT.'/batchmailer/csv';
        $files = glob($csvDir.'/*.csv');
        
        if (!empty($files)) {
            print '<h3>'.$langs->trans('StoredRecipientFilesHeading').'</h3>';

            print '<table class="noborder centpercent">';
            print '<tr class="liste_titre">';
            print '<th>' . $langs->trans('UseLabel') . '</th>';
            print '<th>' . $langs->trans('RecipientListLabel') . '</th>';
            print '<th>' . $langs->trans('UploadedLabel') . '</th>';
            print '<th>' . $langs->trans('ActionLabel') . '</th>';
            print '</tr>';
            
            foreach ($files as $path) {
                $file = basename($path);

                // Expect: TIMESTAMP_filename.csv
                if (!preg_match('/^(\d+?)_(.+)$/', $file, $m)) {
                    continue;
                }
            
                $timestamp = (int) $m[1];
                $cleanName = $m[2];
            
                $isActive = (
                    !empty($_SESSION['batchmailer_csv']['path']) &&
                    realpath($_SESSION['batchmailer_csv']['path']) === realpath($path)
                );
            
                print '<tr>';
            
                // Use radio
                print '<td class="center">';
                if ($isActive) {
                    print '&#9679;'; // filled circle
                } else {
                    print '<a class="butAction" href="?action=usecsv&file=' . urlencode($file) . '">'
                        . $langs->trans('UseLabel')
                        . '</a>';
                }
                print '</td>';
                
                // Filename
                print '<td>';
                if ($isActive) {
                    print '<strong>' . dol_escape_htmltag($cleanName) . '</strong>';
                } else {
                    print dol_escape_htmltag($cleanName);
                }
            
                // Upload date
                print '<td>' . dol_print_date($timestamp, 'dayhour') . '</td>';
            
                // Actions
                print '<td class="nowrap">';
                print '<a class="butActionDelete" href="?action=deletecsv&deletefile=' . urlencode($file) . '"'
                    . ' onclick="return confirm(\'' 
                    . dol_escape_js($langs->trans(
                        'ConfirmDeleteRecipientList',
                        $cleanName,
                        dol_print_date($timestamp, 'dayhour')
                    ))
                    . '\');">'
                    . $langs->trans('DeleteLabel')
                    . '</a>';

                print '</td>';
                print '</tr>';
            }
        
            print '</table>';
        }
        break;
        
    //------------------------------------------------------------------------
    // Admin tab
    //
    case 'admin':
        print '<h2>'.$langs->trans('BatchMailerAdministration').'</h2>';

		// Campaign state panel
		print load_fiche_titre(
			$langs->trans('BatchMailerAdmin'),
			'',
			'setup'
		);

		// Help panel for Lock/Override		
		batchmailer_render_admin_help_panel(
			$langs->trans('AdminHelpSafetyTitle'),
			'
			<p>Safety tools prevent accidental sending.</p>
			<ul>
				<li><strong>Send Lock</strong> disables all sending actions</li>
				<li><strong>Admin Override</strong> allows recovery actions if needed</li>
			</ul>
			<p>These tools are only available to administrators.</p>
			',
			'admin-help-safety'
		);

		// 🔒 SAFETY TOOLS (Set 3)
		print '<div class="batchmailer-admin-actions">';
			print '<div class="batchmailer-admin-action">';
				batchmailer_render_send_lock($langs);
			print '</div>';
			print '<div class="batchmailer-admin-action">';
				batchmailer_render_admin_override($langs);
			print '</div>';
		print '</div>';

		// Offer a help panel for the campaign state
		batchmailer_render_admin_help_panel($langs->trans('AdminHelpCampaignStateTitle'),
			$langs->transnoentities('CampaignStateHelp'), 'admin-help-campaign-state');
	
		batchmailer_render_campaign_state($langs);
			//Help panel for action control
			batchmailer_render_admin_help_panel(
				$langs->trans('AdminHelpRecoveryTitle'),
				'
				<p>These controls allow you to recover from an interrupted campaign.</p>
				<ul>
					<li><strong>Resume</strong> continues from the last unsent recipient</li>
					<li><strong>Restart</strong> begins the campaign again from the start</li>
					<li><strong>Clear</strong> removes the campaign state entirely</li>
				</ul>
				<p>Before resuming or restarting, review the campaign log.</p>
				',
				'admin-help-recovery'
			);
		batchmailer_render_campaign_actions($langs);

		// Help panel for the log viewer	
		batchmailer_render_admin_help_panel(
			$langs->trans('AdminHelpLogsTitle'),
			'
			<p>Each BatchMailer campaign creates a log file.</p>
			<ul>
				<li>Logs record every email attempt</li>
				<li>They are never edited after creation</li>
				<li>You can view, download, or delete logs individually</li>
			</ul>
			<p>Logs are useful for audits, troubleshooting, and record-keeping.</p>
			',
			'admin-help-logs'
		);
		// Campaign log viewer
		if (empty($user->rights->batchmailer->admin)) {
			print '<div class="error">'.$langs->trans('NotAuthorized').'</div>';
			break;
		}
	
		// If viewing a log, render viewer + back link
		if (!empty($_SESSION['batchmailer_admin_viewlog'])) {
	
			$logfile = $_SESSION['batchmailer_admin_viewlog'];
			$path = batchmailer_resolve_log_path($logfile);
	
			if (!$path) {
				unset($_SESSION['batchmailer_admin_viewlog']);
				setEventMessages($langs->trans('ErrorFileNotFound'), null, 'errors');
				print batchmailer_render_logs_admin_ui($langs);
				break;
			}
	
			$content = file_get_contents($path) ?: '';
			print batchmailer_render_log_as_html($content);

			// Also show actions below viewer (optional)
			print '<div style="margin-top:10px">';
			print '<a class="butAction" href="?tab=admin&action=downloadlog&logfile='.urlencode($logfile).'">'.$langs->trans('DownloadLabel').'</a> ';
			print '<a class="butActionDelete" href="?tab=admin&action=deletelog&logfile='.urlencode($logfile).'" onclick="return confirm(\''.dol_escape_js($langs->trans('ConfirmDeleteLog', $logfile)).'\');">'.$langs->trans('DeleteLabel').'</a> ';
			print '<a class="butAction" href="?tab=admin&action=closelog">'.$langs->trans('CloseLabel').'</a>';
			print '</div>';
	
		} else {
			// Normal logs list
			print batchmailer_render_logs_admin_ui($langs);
		}

        break;
        
    default:
        print '<p>Data preparation will go here.</p>';
        break;
}
print '</div>';

print '<div id="batchmailer-help-panel" class="batchmailer-help hidden">
  <div class="batchmailer-help-header">
    <span>BatchMailer Help</span>
    <button type="button" onclick="batchmailerCloseHelp()">✕</button>
  </div>

  <iframe
    id="batchmailer-help-frame"
    src=""
    loading="lazy"
    title="BatchMailer User Guide"
  ></iframe>
</div>';

// Scripts
print '<script>
function toggleQuickStart() {
    const el = document.getElementById(\'quickstart-content\');
    if (!el) return;
    el.style.display = (el.style.display === \'none\') ? \'block\' : \'none\';
}
</script>';

print '<script>
function toggleAdminHelp(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.style.display = (el.style.display === \'none\') ? \'block\' : \'none\';
}
</script>';

print '<script>
function batchmailerCopyLog(btn) {
    const details = btn.closest(\'details\');
    if (!details) return;

    const logView = details.querySelector(\'[data-logtext]\');
    if (!logView) return;

    // Extract visible text (not HTML)
    const text = logView.innerText;

    // Clipboard API if available
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            batchmailerFlashCopied(btn);
        });
    } else {
        // Fallback
        const ta = document.createElement(\'textarea\');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand(\'copy\');
        document.body.removeChild(ta);
        batchmailerFlashCopied(btn);
    }
}

function batchmailerFlashCopied(btn) {
    const original = btn.innerText;
    btn.innerText = \'✓\';
    btn.disabled = true;

    setTimeout(() => {
        btn.innerText = original;
        btn.disabled = false;
    }, 1200);
}
</script>';

dol_fiche_end();