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

/*
 * Function to retrieve the security key used by Stripe/PayPal URLs
 *-----------------------------------------------------------------*/
function batchmailer_get_payment_secure_key(): string
{
    if (!function_exists('getDolGlobalString')) {
        throw new Exception('Dolibarr environment not initialised');
    }

    $token = getDolGlobalString('PAYMENT_SECURITY_TOKEN');
    if (empty($token)) {
        throw new Exception('PAYMENT_SECURITY_TOKEN not set in Dolibarr configuration');
    }

    if (!getDolGlobalString('PAYMENT_SECURITY_TOKEN_UNIQUE')) {
        return urlencode($token);
    }

    return urlencode(dol_hash($token, 'sha1md5'));
}
/*
function render_batchmailer_quickstart($tab)
{
    print '<div style="border:2px solid red; padding:10px; margin:10px 0;">';
    print 'DEBUG: Quick Start rendered. Current tab = ' . htmlspecialchars($tab);
    print '</div>';
}

ℹ️ Quick Start — BatchMailer workflow   ▾

*/
function render_batchmailer_quickstart($currentTab)
{
    global $langs;

	$helpAnchorMap = [
		'prepare'   => '3-preparing-your-recipient-data',
		'templates' => '4-creating-and-managing-email-templates',
		'send'      => '5-review-approval--dry-run',
		'admin'     => '8-admin-tools--safety-controls',
	];
	
	$anchor = $helpAnchorMap[$currentTab] ?? null;

    $steps = [
        'prepare' => $langs->trans('QuickStartStepPrepare'),
        'templates' => $langs->trans('QuickStartStepTemplates'),
        'send' => $langs->trans('QuickStartStepSend'),
    ];

    $introKeyMap = [
        'prepare'   => 'QuickStartIntroPrepare',
        'templates' => 'QuickStartIntroTemplates',
        'send'      => 'QuickStartIntroSend',
    ];
    
    $introText = '';
    if (isset($introKeyMap[$currentTab])) {
        $introText = $langs->trans($introKeyMap[$currentTab]);
    }
    print '<div class="batchmailer-quickstart">';
    print '<button type="button" class="quickstart-toggle" onclick="toggleQuickStart()">ℹ️ '
        . $langs->trans('QuickStartTitle')
        . ' ▾</button>';

    if ($introText) {
        print '<p class="quickstart-intro">' . $introText . '</p>';
    }
    print '<div id="quickstart-content" class="quickstart-content" style="display:none;">';
    print '<ol>';

    foreach ($steps as $key => $label) {
        $class = ($key === $currentTab) ? ' class="current-step"' : '';
        print "<li{$class}>{$label}</li>";
    }

	/*    To use if Safari could handle a single-click
	print '<a href="#" id="batchmailer-help-link" onclick="batchmailerOpenHelp(\''.$anchor.'\'); return false;">';
	print $langs->trans('SeeFullUserGuide');
	print '</a>';
	*/

	print '<button ';
	print 'type="button" ';
	print 'class="batchmailer-help-button" ';
	print 'onclick="batchmailerOpenHelp(\''.$anchor.'\')"> ';
	print $langs->trans('SeeFullUserGuide');
	print '</button>';

	print '<script>
	function isSafari() {
		return /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
	}
	
	function batchmailerOpenHelp(anchor) {
	  const panel = document.getElementById(\'batchmailer-help-panel\');
	  const frame = document.getElementById(\'batchmailer-help-frame\');
	  const base = \'docs/user_guide.html\';
	
	if (isSafari()) {
		// Safari: always open at top
		frame.src = base;
	} else {
		// Other browsers: use anchors
		frame.src = anchor ? base + \'#\' + anchor : base;
	}

	  panel.classList.remove(\'hidden\');

	}

	function batchmailerCloseHelp() {
	  const panel = document.getElementById(\'batchmailer-help-panel\');
	  if (panel) panel.classList.add(\'hidden\');
	}

	</script>';

    print '</div></div>';
}


/**
 * Render the current BatchMailer selections (CSV + Template)
 *
 * Shown on all tabs to provide workflow continuity.
 *
 * @return string
 */
function render_batchmailer_current_selections($currentTab)
{
    global $langs;

    $hasCsv      = !empty($_SESSION['batchmailer_csv']);
    $hasTemplate = !empty($_SESSION['batchmailer_template']);

    $out  = '<div class="batchmailer-current-selections">';
    $out .= '<strong>' . $langs->trans('CurrentSelections') . '</strong>';
    $out .= '<ul>';


    /* Recipient list */
    if ($hasCsv) {
        $csv = $_SESSION['batchmailer_csv'];

        $out .= '<li>';
        $out .= '<strong>' . $langs->trans('RecipientList') . ':</strong> ';
        $out .= dol_escape_htmltag($csv['name']);

        $out .= ' <a href="?action=clearcsv" class="batchmailer-clear">';
        $out .= $langs->trans('Clear');
        $out .= '</a>';
        $out .= '</li>';
    } else {
        $out .= '<li>';
        $out .= '<strong>' . $langs->trans('RecipientList') . ':</strong> ';
        if ($currentTab === 'prepare') {
            $out .= '<em>'.$langs->trans('Please Select or Upload a recipient list').'</em>';
        } else {
            $out .= '<em><a href="?tab=prepare">'.$langs->trans('Please Select or Upload a recipient list').'</a></em>';
        }
        $out .= '</li>';
    }

    /* Template */
    if ($hasTemplate) {
        $out .= '<li>';
        $out .= '<strong>' . $langs->trans('TemplateLabel') . '</strong> ';
        $out .= dol_escape_htmltag($_SESSION['batchmailer_template']);

        $out .= ' <a href="'
            . dol_buildpath('/custom/batchmailer/template_editor.php', 1)
            . '?template_file=' . urlencode($_SESSION['batchmailer_template']) . '&returntab='.$currentTab.'">';
        $out .= $langs->trans('Edit');
        $out .= '</a>';

        $out .= ' · <a href="?action=cleartemplate" class="batchmailer-clear">';
        $out .= $langs->trans('Clear');
        $out .= '</a>';
        $out .= '</li>';
    } else {
        $out .= '<li>';
        $out .= '<strong>' . $langs->trans('TemplateLabel') . '</strong> ';
        if ($currentTab === 'template') {
            $out .= '<em>'.$langs->trans('Please Select or Create a Template').'</em>';
        } else {
            $out .= '<em><a href="?tab=templates">'.$langs->trans('Please Select or Create a Template').'</a></em>';
        }
        $out .= '</li>';
    }
    
    if ($hasCsv && $hasTemplate && $currentTab !== 'send') {
        $out .= '<li>';
        $out .= '<p><em><a href="?tab=send">'.$langs->trans('You may now prepare the email.').'</a></em></p>';
        $out .= '</li>';
        
    }

    $out .= '</ul>';
    $out .= '</div>';

    return $out;
}

function render_batchmailer_aborted_panel(array $ctx, $langs): string
{
    $sent  = (int) ($ctx['offset'] ?? 0);
    $total = (int) ($ctx['total'] ?? 0);

    $out  = '<div class="warning batchmailer-aborted">';
    $out .= '<h3>' . $langs->trans('BatchAbortedTitle') . '</h3>';

    $out .= '<p>'
          . $langs->trans('BatchAbortedStats', $sent, $total)
          . '</p>';

    $out .= '<p class="opacitymedium">'
          . $langs->trans('BatchAbortedConsequence')
          . '</p>';

    $out .= '<div class="batchmailer-actions">';
    $out .= '<a class="butAction" href="?action=view_log">'
          . $langs->trans('ViewBatchLog')
          . '</a> ';

    $out .= '<a class="butActionDelete" href="?action=start_new_batch">'
          . $langs->trans('StartNewBatch')
          . '</a>';
    $out .= '</div>';

    $out .= '<form method="post" style="margin-top:10px">'
          . '<input type="hidden" name="action" value="dismiss_abort">'
          . '<button class="butAction">'
          . $langs->trans('Dismiss')
          . '</button>'
          . '</form>';

    $out .= '</div>';

    return $out;
}

// ---- FUNCTIONS ----
function validate_template_against_csv(array $template, array $csvHeaders): array
{
    $missing = [];

    foreach ($template['required_fields'] ?? [] as $field) {
        if (!in_array(strtolower($field), $csvHeaders)) {
            $missing[] = $field;
        }
    }

    return $missing;
}

function load_templates(): array
{
    $templateDir = DOL_DATA_ROOT . '/batchmailer/templates';
    dol_mkdir($templateDir);

    $templates = [];

    foreach (glob($templateDir . '/*.json') as $file) {
        $json = file_get_contents($file);
        $data = json_decode($json, true);

        if (is_array($data) && !empty($data['name'])) {
            $templates[basename($file)] = $data;
        }
    }

    return $templates;
}

function filter_compatible_templates(array $templates, array $csvHeaders): array
{
    $usable = [];

    foreach ($templates as $file => $tpl) {
        $missing = validate_template_against_csv($tpl, $csvHeaders);
        if (empty($missing)) {
            $usable[$file] = $tpl;
        }
    }

    return $usable;
}

function load_template_file(string $filename): ?array
{
    $path = DOL_DATA_ROOT . '/batchmailer/templates/' . basename($filename);

    if (!file_exists($path)) {
        return null;
    }

    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function read_csv_row(string $csvFile, int $rowNumber): ?array
{
    if (!file_exists($csvFile)) {
        return null;
    }

    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        return null;
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return null;
    }

    $rowIndex = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $rowIndex++;
        if ($rowIndex === $rowNumber) {
            fclose($handle);
            return array_combine($header, $row);
        }
    }

    fclose($handle);
    return null;
}

function render_template(string $text, array $row): string
{
    foreach ($row as $key => $value) {
        $placeholder = '{{' . strtolower(trim($key)) . '}}';
        $text = str_replace($placeholder, $value ?? '', $text);
    }
    return $text;
}

// Dry run 3
function dry_run_batch(string $csvFile, array $template): array {
    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        return ['error' => 'Unable to open CSV file'];
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return ['error' => 'CSV header missing'];
    }

    $stats = [
        'total_rows' => 0,
        'sendable' => 0,
        'skipped' => [],
    ];

    while (($row = fgetcsv($handle)) !== false) {
        $stats['total_rows']++;

        $assoc = array_combine($header, $row);
         // normalise keys to lowercase
        $assoc = array_change_key_case($assoc, CASE_LOWER);
       $missing = [];

        foreach ($template['required_fields'] as $field) {
            if (empty($assoc[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            $stats['skipped'][] = [
                'email' => $assoc['email'] ?? '(none)',
                'reason' => 'Missing: '.implode(', ', $missing)
            ];
            continue;
        }

        if (!filter_var($assoc['email'], FILTER_VALIDATE_EMAIL)) {
            $stats['skipped'][] = [
                'email' => $assoc['email'] ?? '(none)',
                'reason' => 'Invalid email'
            ];
            continue;
        }

        $stats['sendable']++;
    }

    fclose($handle);
    return $stats;
}

// 'Save' helper functions
function warning_upload_csv($langs): string {
    // Stage 1: no recipient list
    $text = '<div class="warning">'
        . $langs->trans('PleaseUploadACSVFileFirst')
        . '</div>';
    return $text;
}
function warning_select_template($langs): string {
    // Stage 2: no template
    $text = '<div class="warning">'
        . $langs->trans('PleaseSelectATemplate')
        . '</div>';
    return $text;
}
function template_selector(array $csvHeaders, $langs): string {
    $text ="";
    $templates = load_templates();
    $usableTemplates = filter_compatible_templates($templates, $csvHeaders);

    if (empty($usableTemplates)) {
        $text = '<div class="warning">'.$langs->trans('NoTemplatesMatchTheUploadedCSV').'</div>';
        return $text;
    }

       // Select template
    $text  = '<form method="post">';
    $text .= '<table class="noborder">';
    $text .= '<tr><td>Template:</td><td>';

    $text .= '<select name="template_file" class="flat">';
    $text .= '<option value="">'.$langs->trans('SelectTemplatePlaceholder').'</option>';

    foreach ($usableTemplates as $file => $tpl) {
        $selected = (!empty($_SESSION['batchmailer_template']) && $_SESSION['batchmailer_template'] === $file)
            ? ' selected'
            : '';
        $text .= '<option value="'.$file.'"'.$selected.'>'
            . dol_escape_htmltag($tpl['name'])
            . '</option>';
    }

    $text .= '</select>';
    $text .= '</td></tr>';
    $text .= '</table>';

    $text .= '<br><input type="submit" class="button" value="'
        . $langs->trans('SelectTemplateButton')
        . '">';
    $text .= '</form>';
    // Summary
    if (!empty($_SESSION['batchmailer_template'])) {
        $tpl = $usableTemplates[$_SESSION['batchmailer_template']];
    
        $text .= '<h3>'.$langs->trans('ReadyToSend').'</h3>';
        $text .= '<div class="info">';
        $text .= '<strong>'.$langs->trans('RecipientListLabel').'</strong> '
            . dol_escape_htmltag($_SESSION['batchmailer_csv']['name']) . '<br>';
        $text .= '<strong>'.$langs->trans('TemplateLabel').':</strong> '
            . dol_escape_htmltag($tpl['name']) . '<br>';
        $text .= '<strong>'.$langs->trans('SubjectLabel').':</strong> '
            . dol_escape_htmltag($tpl['subject']) . '<br>';
        $text .= '</div>';
    
        $text .= '<p><em>'.$langs->trans('SendingWillBeEnabledInTheNextStep').'</em></p>';
    }
    
    return $text;
}
function warning_bad_csv($langs): string {
    // Stage 3: CSV unreadable or malformed
    $text = '<div class="warning">'
        . $langs->trans('RecipientFileHeadersNotAvailable')
        . '</div>';
    return $text;
}
function warning_preview_required($langs): string {
    // Stage 4: ready for preview / approval
    $text = '<div class="warning">'
        . $langs->trans('PreviewAndApprovalRequired')
        . '</div>';
    return $text;
}
function preview_ui($langs): string {
    $text = "";
    
    // Preview template
    $template = load_template_file($_SESSION['batchmailer_template']);

    if (!$template) {
        $text .= '<div class="error">'.$langs->trans('TemplateCouldNotBeLoaded').'</div>';
        return $text;
    }

    // Row selector
    $rowNum = max(1, (int) GETPOST('preview_row', 'int'));
    $csvPath = $_SESSION['batchmailer_csv']['path'];

    $rowData = read_csv_row($csvPath, $rowNum);
        
	$text  = '<h3>'.$langs->trans('EmailPreview').'</h3>';

	$text .= '<form method="post">';
	$text .= '<label>'.$langs->trans('PreviewCSVRow').': </label> ';
	$text .= '<input type="number" name="preview_row" value="'.$rowNum.'" min="1" style="width:80px">';
	$text .= ' <input type="submit" class="button small" value="'.$langs->trans('PreviewLabel').'">';
	$text .= '</form><br>';

	if (!$rowData) {
		$text .= '<div class="warning">'.$langs->trans('UnableToLoadThatRow').'</div>';
        return $text;
	}

	// Render content
	$subject = render_template($template['subject'], $rowData);
	$htmlBody = render_template($template['body_html'], $rowData);
	$textBody = batchmailer_generate_text_body($htmlBody);

	// Display preview
	$text .= '<div class="fichecenter">';
	$text .= '<table class="border centpercent">';

	$text .= '<tr><td width="15%"><strong>To</strong></td><td>'
		. dol_escape_htmltag($rowData['email'] ?? '')
		. '</td></tr>';

	$text .= '<tr><td><strong>Subject</strong></td><td>'
		. dol_escape_htmltag($subject)
		. '</td></tr>';

	$text .= '</table>';

	$text .= '<h4>HTML</h4>';
	$text .= '<div style="border:1px solid #ccc;padding:10px;background:#fff">'
		. $htmlBody
		. '</div>';
	
	// text-only details
    $text .= '<details style="margin-top:15px">';
    $text .= '<summary style="cursor:pointer;font-weight:bold">';
    $text .= $langs->trans('ViewTextOnlyVersion');
    $text .= '</summary>';
    
    $text .= '<pre style="white-space:pre-wrap;border:1px solid #ccc;padding:10px;background:#f9f9f9">';
    $text .= dol_escape_htmltag($textBody);
    $text .= '</pre>';
    
    $text .= '</details>';
	// end text-only
	
	$text .= '</div>';

	$text .= '<p><em>'.$langs->trans('SendingWillBeEnabledAfterPreviewApproval').'</em></p>';

    return $text;
}
function approval_ui(bool $approved, $langs): string {
    // Get approval to send
    $text  = '<form method="post" style="margin-top:15px">';
    $text .= '<input type="hidden" name="token" value="'.newToken().'">';
    
    // keep user on Send tab
    $text .= '<input type="hidden" name="tab" value="send">';
    
    // carry current selected template through the approval POST
    if (!empty($_SESSION['batchmailer_template'])) {
        $text .= '<input type="hidden" name="template_file" value="'
            . dol_escape_htmltag($_SESSION['batchmailer_template'])
            . '">';
    }
    
    $text .= '<label>';
    $text .= '<input type="hidden" name="approve_send" value="0">';
    $text .= '<input type="checkbox" name="approve_send" value="1" '
        . ($approved ? 'checked' : '')
        . '> ';
    $text .= '<strong>'.$langs->trans('IHaveReviewedThisEmailAndApproveSending').'</strong>';
    $text .= '</label><br><br>';
    $text .= '<input type="submit" class="button button-save" value="'
        . $langs->trans('ConfirmApproval')
        . '">';
    $text .= '</form>';

    if (!$approved) {
        $text .= '<div class="error">'
            . $langs->trans('YouMustPreviewAndApproveTheEmailBeforeSending')
            . '</div>';
        return $text;
    }
    
    return $text;
}
function approval_confirmed($langs): string {
    // Stage 5: approved
    $text = '<div class="ok">'
        . $langs->trans('EmailContentApprovedForSending')
        . '</div>';
    return $text;
}
function dry_run_ui($langs): string {
	// Dry run 1
	$text  = '<form method="post" style="margin-top:15px">';
    $text .= '<input type="hidden" name="tab" value="send">';
	$text .= '<input type="hidden" name="do_dryrun" value="1">';
	$text .= '<input type="submit" class="button" value="'.$langs->trans('DryRunBatch').'">';
	$text .= '</form>';
	
	return $text;
}
function dry_run_results_ui(array $result, $langs): string {
	// Dry run 4: Results
	$text  = '<h3>'.$langs->trans('DryRunResults').'</h3>';

	$text .= '<ul>';
	$text .= '<li>'.$langs->trans('TotalRows').': <strong>'.$result['total_rows'].'</strong></li>';
	$text .= '<li>'.$langs->trans('EmailsToSend').': <strong>'.$result['sendable'].'</strong></li>';
	$text .= '<li>'.$langs->trans('SkippedLabel').': <strong>'.count($result['skipped']).'</strong></li>';
	$text .= '</ul>';

	if (!empty($result['skipped'])) {
		$text .= '<h4>'.$langs->trans('SkippedEntries').'</h4>';
		$text .= '<table class="noborder">';
		$text .= '<tr class="liste_titre"><th>Email</th><th>Reason</th></tr>';

		foreach ($result['skipped'] as $skip) {
			$text .= '<tr>';
			$text .= '<td>'.dol_escape_htmltag($skip['email']).'</td>';
			$text .= '<td>'.dol_escape_htmltag($skip['reason']).'</td>';
			$text .= '</tr>';
		}
		$text .= '</table>';
	}

	$text .= '<div class="ok">'.$langs->trans('DryRunCompletedNoEmailsSent').'</div>';

    return $text;
}

function final_summary_ui(array $totals, $langs): string
{
    $sent   = (int) ($totals['sent'] ?? 0);
    $failed = (int) ($totals['failed'] ?? 0);

    $out  = '<div class="summary" style="margin-top:15px">';
    $out .= '<h3>' . $langs->trans('SendSummary') . '</h3>';
    $out .= '<ul>';
    $out .= '<li>' . $langs->trans('EmailsSent') . ': ' . $sent . '</li>';
    $out .= '<li>' . $langs->trans('EmailsFailed') . ': ' . $failed . '</li>';
    $out .= '</ul>';
    $out .= '</div>';

    return $out;
}

function start_new_batch_ui($langs): string
{
    return
        '<div class="ok">'
      . $langs->trans('BatchSendingCompleted')
      . '</div>'
      . '<form method="post" style="margin-top:15px">'
      . '<input type="hidden" name="tab" value="prepare">'
      . '<input type="hidden" name="action" value="reset_batch">'
      . '<input type="submit" class="button" value="'
      . $langs->trans('StartNewBatch')
      . '">'
      . '</form>';
}

function send_batch_ui(?int $currentBatch, ?int $totalBatches, $langs): string
{
    $label = ($currentBatch && $totalBatches)
        ? $langs->trans('SendBatchXofY', $currentBatch, $totalBatches)
        : $langs->trans('SendFirstBatch');
    $batchSize = (int) ($_SESSION['batchmailer_batch_size'] ?? 50);
    
    return
        '<form method="post" style="margin-top:15px">'
      . '<input type="hidden" name="tab" value="send">'
      . '<input type="hidden" name="action" value="send_batch">'
      . '<label style="margin-right:10px">'
      . $langs->trans('SendInBatchesOf')
      . ' <input type="number" name="batch_size" min="1" value="'.$batchSize.'" style="width:80px">'
      . '</label>'
      . '<input type="submit" class="button button-save" value="'.$label.'">'
      . '</form>';
}

function sending_progress_ui($langs) : void {
    $sentSoFar = $_SESSION['batchmailer_offset'];
    $totalRows = $_SESSION['batchmailer_total'];
    
    print '<div class="info">'
        . $langs->trans('EmailsSentSoFar', $sentSoFar, $totalRows)
        . '</div>';
}

function overall_progress_ui(
    ?int $offset,
    ?int $total,
    ?int $batchSize,
    $langs
): string {
    // No campaign started yet
    if (empty($offset) && empty($total)) {
        return '';
    }

    // Defensive normalisation
    $offset    = max(0, (int) $offset);
    $total     = max(0, (int) $total);
    $batchSize = max(0, (int) $batchSize);

    // Still not enough information to compute batches
    if ($total === 0 || $batchSize === 0) {
        return '';
    }

    $totalBatches  = (int) ceil($total / $batchSize);
    $currentBatch  = (int) floor($offset / $batchSize) + 1;
    $completedBatches = (int) floor($offset / $batchSize);

    // Clamp values defensively
    if ($currentBatch < 1) {
        $currentBatch = 1;
    }
    if ($currentBatch > $totalBatches) {
        $currentBatch = $totalBatches;
    }

    $out  = '<div class="info" style="margin-top:10px">';
    $out .= $langs->trans(
        'BatchProgressText',
        $completedBatches,
        $totalBatches,
        $offset,
        $total
    );
    $out .= '</div>';

    return $out;
}

function batchmailer_progress_context($langs): array
{
    $offset    = $_SESSION['batchmailer_offset'] ?? null;
    $total     = $_SESSION['batchmailer_total'] ?? null;
    $batchSize = $_SESSION['batchmailer_batch_size'] ?? null;

    $ctx = [
        'hasCampaign'   => false,
        'canBatchMath'  => false,
        'currentBatch'  => null,
        'completedBatches' => null,
        'nextBatch'     => null,
        'totalBatches'  => null,
        'offset'        => (int) ($offset ?? 0),
        'total'         => (int) ($total ?? 0),
        'batchSize'     => (int) ($batchSize ?? 0),
        'progress_html' => ''
    ];

    // Campaign not started yet
    if ($offset === null || $total === null) {
        return $ctx;
    }

    $ctx['hasCampaign'] = true;

    if ($ctx['batchSize'] <= 0 || $ctx['total'] <= 0) {
        return $ctx;
    }

    // Safe to calculate
    $ctx['canBatchMath'] = true;

    $ctx['totalBatches'] =
        (int) ceil($ctx['total'] / $ctx['batchSize']);

    $ctx['currentBatch'] =
        (int) floor($ctx['offset'] / $ctx['batchSize']) + 1;

    // Clamp defensively
    $ctx['currentBatch'] = max(
        1,
        min($ctx['currentBatch'], $ctx['totalBatches'])
    );

    $ctx['completedBatches'] = (int) floor($offset / $batchSize);
    $ctx['totalBatches']     = (int) ceil($total / $batchSize);
    
    $ctx['nextBatch'] = min($ctx['completedBatches'] + 1, $ctx['totalBatches']);
/*
    // Render progress text
    $ctx['progress_html'] =
        '<div class="info" style="margin-top:10px">'
      . $langs->trans(
            'BatchProgressText',
            $ctx['completedBatches'],
            $ctx['totalBatches'],
            $ctx['offset'],
            $ctx['total']
        )
      . '</div>';
*/
    return $ctx;
}

function batchmailer_find_company_logo(): string
{
    global $conf;

    $logo = $conf->global->MAIN_INFO_SOCIETE_LOGO ?? '';
    if (empty($logo)) {
        return '';
    }

    $baseDir = rtrim($conf->mycompany->dir_output, '/');
    if (empty($baseDir)) {
        return '';
    }

    // Dolibarr stores logos in mycompany/logos/
    $logoDir = $baseDir . '/logos';

    if (!is_dir($logoDir)) {
        return '';
    }

    $path = $logoDir . '/' . $logo;

    if (is_readable($path)) {
        return $path;
    }

    return '';
}

function send_batch_emails(
    string $csvFile,
    array $template,
    int $batchSize,
    int $offset
): array {
    global $conf;

    require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
/*
error_log("BatchMailer: offset=$offset batchSize=$batchSize");
*/
    /* ---------- Open CSV ---------- */
    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        return ['error' => 'Unable to open CSV file'];
    }

    /* ---------- Count total rows ---------- */
    $total = 0;
    while (fgetcsv($handle) !== false) {
        $total++;
    }
    rewind($handle);

    /* ---------- Read header ---------- */
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return ['error' => 'CSV header missing'];
    }
    $header = array_map('strtolower', $header);

    /* ---------- Logging ---------- */
    $logFile = batchmailer_get_log_path();
    $fh = fopen($logFile, 'a');

    fwrite($fh, "# BatchMailer send\n");
    fwrite($fh, "# CSV: {$csvFile}\n");
    fwrite($fh, "# Template: {$_SESSION['batchmailer_template']}\n");
    fwrite($fh, "# Started: " . dol_print_date(time(), 'dayhour') . "\n\n");

    /* ---------- Results ---------- */
    $results = [
        'attempted' => 0,
        'sent'      => 0,
        'failed'    => [],
        'total'     => $total,
        'logfile'   => $logFile,
    ];

    $rowIndex  = 0;
    $attempted = 0;

    /* ---------- Main send loop ---------- */
    while (($row = fgetcsv($handle)) !== false) {

        if ($rowIndex++ < $offset) {
            continue;
        }
        if ($attempted >= $batchSize) {
            break;
        }

        $attempted++;
        $results['attempted']++;

        try {
            $assoc = array_change_key_case(
                array_combine($header, $row),
                CASE_LOWER
            );
            if (!$assoc) {
                continue;
            }

            $email = trim($assoc['email'] ?? '');
            fwrite($fh, "ATTEMPT | {$email}\n");

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results['failed'][] = [
                    'email'  => $email ?: '(none)',
                    'reason' => 'Invalid email',
                ];
                continue;
            }

            /* ---------- Render subject & body ---------- */
            $subject = render_template($template['subject'], $assoc);

            $htmlParts = [];

            // Optional logo (public URL only)
            if (!empty($template['include_logo']) && !empty($template['logo_url'])) {
                $htmlParts[] =
                    '<div style="margin-bottom:20px">
                        <img src="'.dol_escape_htmltag($template['logo_url']).'"
                             style="max-width:500px;height:auto">
                     </div>';
            }

            // Main body
            $htmlParts[] = render_template($template['body_html'], $assoc);

            // Optional footer
            if (!empty($template['include_footer'])) {
                $org = batchmailer_get_org_context();
                $htmlParts[] = batchmailer_render_footer_html($org);
            }

            $htmlBody = implode("\n", $htmlParts);
            
            $css = '
                .batchmailer-logo {
                    margin-bottom: 20px;
                }
                .batchmailer-footer {
                    font-size: 12px;
                    color: #666;
                    margin-top: 15px;
                }
                ';

            // Plain text version
            $textBody = batchmailer_generate_text_body($htmlBody);
            if (!empty($template['include_footer'])) {
                $textBody .= batchmailer_render_footer_text($org);
            }

            /* ---------- Send ---------- */
            $mail = new CMailFile(
                $subject,
                $email,
                $template['from_email'] ?? '',
                $htmlBody,
                [], [], [], // no attachments
                '',
                '',
                0,
                1,
                getDolGlobalString('BATCHMAILER_ERRORS_TO') ?: null
            );

            $mail->msgishtml = 1;
            $mail->addr_from = $template['from_email'] ?? '';
            $mail->addr_from_name = $template['from_name'] ?? '';

            if ($mail->sendfile()) {
                $results['sent']++;
                fwrite($fh, "SUCCESS | {$email}\n");
            } else {
                $results['failed'][] = [
                    'email'  => $email,
                    'reason' => $mail->error,
                ];
                fwrite($fh, "FAILED  | {$email} | {$mail->error}\n");
            }

            $results['last_email'] = $email;

        } catch (Exception $e) {
            $results['failed'][] = [
                'email'  => $email ?? '(unknown)',
                'reason' => $e->getMessage(),
            ];
            fwrite($fh, "FAILED  | {$email} | {$e->getMessage()}\n");
        }

        $delay = max(0, (int) getDolGlobalInt('BATCHMAILER_SEND_DELAY_SECONDS'));
        if ($delay > 0) {
            sleep($delay);
        }
    }

    fclose($handle);

    fwrite($fh, "\n# Completed: " . dol_print_date(time(), 'dayhour') . "\n");
    fwrite(
        $fh,
        "# Attempted: {$results['attempted']}, Sent: {$results['sent']}, Failed: "
        . count($results['failed']) . "\n"
    );
    fclose($fh);

    return $results;
}

/*
 * Reset workflow state (preview → approve → dry-run)
 *
 * Use when:
 *      CSV changes
 *      template changes
 */
function batchmailer_reset_workflow_state(): void
{
    unset(
        $_SESSION['batchmailer_preview_done'],
        $_SESSION['batchmailer_approved'],
        $_SESSION['batchmailer_dryrun_ok']
    );
}

/*
 * Reset campaign execution state (batch sending)
 *
 * Use this when:
 *      CSV changes
 *      template changes
 *      “Start new batch” is clicked
 */
function batchmailer_reset_campaign_state(): void
{
    unset(
        $_SESSION['batchmailer_offset'],
        $_SESSION['batchmailer_total'],
        $_SESSION['batchmailer_batch_size'],
        $_SESSION['batchmailer_send_result'],
        $_SESSION['batchmailer_sending'],
        $_SESSION['batchmailer_campaign_totals'],
        $_SESSION['batchmailer_csv_analysis']
    );
}

/*
 * Full reset (rare, explicit)
 *
 * Use this only for:
 *      “Clear workflow”
 *      emergency / admin reset
 */
function batchmailer_reset_all_state(): void
{
    batchmailer_reset_workflow_state();
    batchmailer_reset_campaign_state();

    unset(
        $_SESSION['batchmailer_csv'],
        $_SESSION['batchmailer_template'],
        $_SESSION['batchmailer_csv_analysis']
    );
}

/*
 * Legacy reset function
 */
function batchmailer_reset_downstream_state(): void {
    unset($_SESSION['batchmailer_preview_done']);
    unset($_SESSION['batchmailer_approved']);
    unset($_SESSION['batchmailer_dryrun_ok']);
    unset($_SESSION['batchmailer_send_result']);
    unset($_SESSION['batchmailer_offset']);
    unset($_SESSION['batchmailer_total']);
    unset($_SESSION['batchmailer_batch_size']);
    unset($_SESSION['batchmailer_csv_analysis']);
}
// End 'Save' helper functions

// Email log functions
function batchmailer_get_log_path(): string
{
    if (empty($_SESSION['batchmailer_csv']['path'])) {
        throw new Exception('No active CSV for logging');
    }

    $csvBase = basename($_SESSION['batchmailer_csv']['path'], '.csv');
    return DOL_DATA_ROOT . '/batchmailer/logs/' . $csvBase . '.log';
}

// Display send mail results
/*
✔ Batch email send completed

Recipient list: renewalrequests.csv
Template: Renewal Reminder
Sent: 3
Failed: 0
Log file: View log
*/
function render_batchmailer_send_complete($langs): string
{
    if (empty($_SESSION['batchmailer_send_result'])) {
        return '';
    }

    $result = $_SESSION['batchmailer_send_result'];

    $sent   = (int) ($result['sent']   ?? 0);
    $failed = (array) ($result['failed'] ?? []);
    $total  = $sent + count($failed);

    $logfile = $result['logfile'] ?? null;

    $html  = '<div class="box box-success">';
    $html .= '<h3>'.$langs->trans('BatchSendCompleted').'</h3>';

    $html .= '<ul>';
    $html .= '<li>'.$langs->trans('TotalRecipients').': <strong>'.$total.'</strong></li>';
    $html .= '<li>'.$langs->trans('EmailsSent').': <strong>'.$sent.'</strong></li>';
    $html .= '<li>'.$langs->trans('EmailsFailed').': <strong>'.count($failed).'</strong></li>';
    $html .= '</ul>';

    // Failures table (only if needed)
    if (!empty($failed)) {
        $html .= '<h4>'.$langs->trans('FailedEmails').'</h4>';
        $html .= '<table class="noborder centpercent">';
        $html .= '<tr class="liste_titre">';
        $html .= '<th>'.$langs->trans('Email').'</th>';
        $html .= '<th>'.$langs->trans('Reason').'</th>';
        $html .= '</tr>';

        foreach ($failed as $row) {
            $html .= '<tr>';
            $html .= '<td>'.dol_escape_htmltag($row['email'] ?? '').'</td>';
            $html .= '<td>'.dol_escape_htmltag($row['reason'] ?? '').'</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
    }

    // Log link
    if (!empty($logfile) && file_exists($logfile)) {
        $html .= '<p>';
        $html .= '<a class="butAction" href="'
            . dol_buildpath('/custom/batchmailer/logs/'.basename($logfile), 1)
            . '" target="_blank">';
        $html .= $langs->trans('ViewBatchLog');
        $html .= '</a>';
        $html .= '</p>';
    }

    // Reset workflow button
    $html .= '<p style="margin-top: 15px">';
    $html .= '<a class="butAction" href="?action=clearworkflow&tab=prepare">';
    $html .= $langs->trans('StartNewBatch');
    $html .= '</a>';
    $html .= '</p>';

    $html .= '</div>';

    return $html;
}

function batchmailer_generate_text_body(string $html): string
{
    // Convert HTML → text safely
    $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($text);
    $text = preg_replace("/[ \t]+/", ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    return trim($text);
}

// Reset button actions
/*
function batchmailer_reset_all_state(): void {
    unset($_SESSION['batchmailer_csv']);
    unset($_SESSION['batchmailer_template']);
    batchmailer_reset_downstream_state();
}
*/

function batch_status_panel(array $ctx, array $lastResult, $langs): string
{
    $started = !empty($_SESSION['batchmailer_started_at'])
        ? dol_print_date($_SESSION['batchmailer_started_at'], 'dayhour')
        : '–';

    $sent  = $ctx['offset'];
    $total = $ctx['total'];

    $percent = $total > 0
        ? round(($sent / $total) * 100)
        : 0;

    $lastEmail = $lastResult['last_email'] ?? '–';

    $delay = (int) getDolGlobalInt('BATCHMAILER_SEND_DELAY_SECONDS');

    return '
<div class="status-box">
  <h3>'.$langs->trans('BatchStatus').'</h3>

  <p><strong>'.$langs->trans('Started').':</strong> '.$started.'</p>
  <p><strong>'.$langs->trans('LastRecipient').':</strong> '.$lastEmail.'</p>

  <p><strong>'.$langs->trans('Progress').':</strong>
     '.$sent.' / '.$total.'
  </p>

  <div class="progress-bar">
    <div class="progress-fill" style="width: '.$percent.'%"></div>
  </div>

  <p><strong>'.$langs->trans('DelayBetweenEmails').':</strong>
     '.$delay.' '.$langs->trans('Seconds').'
  </p>
</div>';
}

/**
 * ADMIN TAB FUNCTIONS
 *
 * Return absolute log directory (created if missing).
 */
function batchmailer_get_logs_dir(): string
{
    $dir = DOL_DATA_ROOT . '/batchmailer/logs';
    dol_mkdir($dir);
    return $dir;
}

/**
 * Safe resolve a logfile name to an absolute path inside log dir.
 * Returns '' if invalid/unsafe.
 */
function batchmailer_resolve_log_path(string $filename): string
{
    $logDir = realpath(batchmailer_get_logs_dir());
    if (!$logDir) return '';

    $filename = basename($filename); // basic hardening
    if ($filename === '' || strpos($filename, "\0") !== false) return '';

    $path = realpath($logDir . '/' . $filename);

    if (!$path) return '';
    if (strpos($path, $logDir) !== 0) return '';
    if (!is_file($path)) return '';

    return $path;
}

/**
 * Parse first few header lines from a log file to extract CSV and Template.
 * Returns ['csv' => '...', 'template' => '...'].
 */
function batchmailer_parse_log_header(string $logPath): array
{
    $out = [
        'csv'      => '',
        'template' => '',
    ];

    $fh = @fopen($logPath, 'r');
    if (!$fh) return $out;

    $maxLines = 30; // plenty for headers
    for ($i = 0; $i < $maxLines; $i++) {
        $line = fgets($fh);
        if ($line === false) break;

        // Stop once we hit first non-header chunk
        if (strpos($line, 'ATTEMPT') === 0 || strpos($line, 'SUCCESS') === 0 || strpos($line, 'FAILED') === 0) {
            break;
        }

        if (preg_match('/^\#\s*CSV:\s*(.+)\s*$/i', trim($line), $m)) {
            $out['csv'] = trim($m[1]);
        }
        if (preg_match('/^\#\s*Template:\s*(.+)\s*$/i', trim($line), $m)) {
            $out['template'] = trim($m[1]);
        }
    }

    fclose($fh);
    return $out;
}

/**
 * List log files with metadata sorted newest first.
 * Returns array of rows: ['file','path','mtime','size','csv','template'].
 */
function batchmailer_list_logs(): array
{
    $dir = batchmailer_get_logs_dir();
    $realDir = realpath($dir);
    if (!$realDir) return [];

    $files = glob($realDir . '/*.log');
    if (!$files) return [];

    $rows = [];
    foreach ($files as $path) {
        if (!is_file($path)) continue;

        $file = basename($path);
		$mtime = 0;
		if (preg_match('/^(\d{10})_/', basename($path), $m)) {
			$mtime = (int) $m[1];
		} else {
			$mtime = @filemtime($path) ?: 0;
		}
        $size = @filesize($path) ?: 0;

        $hdr = batchmailer_parse_log_header($path);

        $rows[] = [
            'file'     => $file,
            'path'     => $path,
            'mtime'    => $mtime,
            'size'     => $size,
            'csv'      => $hdr['csv'],
            'template' => $hdr['template'],
        ];
    }

    usort($rows, function ($a, $b) {
        return ($b['mtime'] <=> $a['mtime']);
    });

    return $rows;
}

/**
 * Render logs admin UI table.
 */
function batchmailer_render_logs_admin_ui($langs): string
{
    $logs = batchmailer_list_logs();

    $out  = '<h3>' . $langs->trans('LogsTitle') . '</h3>';

    if (empty($logs)) {
        $out .= '<div class="info">' . $langs->trans('NoLogsFound') . '</div>';
        return $out;
    }

    $out .= '<div class="div-table-responsive">';
    $out .= '<table class="noborder centpercent">';
    $out .= '<tr class="liste_titre">';
    $out .= '<td>' . $langs->trans('LogListDate') . '</td>';
    $out .= '<td>' . $langs->trans('LogListFilename') . '</td>';
    $out .= '<td>' . $langs->trans('LogListCsv') . '</td>';
    $out .= '<td>' . $langs->trans('LogListTemplate') . '</td>';
    $out .= '<td class="right">' . $langs->trans('LogListSize') . '</td>';
    $out .= '<td class="nowrap">' . $langs->trans('LogListActions') . '</td>';
    $out .= '</tr>';

    foreach ($logs as $row) {
        $date = $row['mtime'] ? dol_print_date($row['mtime'], 'dayhour') : $langs->trans('LogHeaderUnknown');
        $size = $row['size'] ? dol_print_size($row['size'], 1, 1) : '-';

        // Keep CSV/template short in the list (full visible in modal)
        $csvDisplay = $row['csv'] ? dol_escape_htmltag(basename($row['csv'])) : '<span class="opacitymedium">'.$langs->trans('LogHeaderUnknown').'</span>';
        $tplDisplay = $row['template'] ? dol_escape_htmltag($row['template']) : '<span class="opacitymedium">'.$langs->trans('LogHeaderUnknown').'</span>';

        $fileEsc = urlencode($row['file']);

        $out .= '<tr class="oddeven">';
        $out .= '<td class="nowrap">' . $date . '</td>';
        $out .= '<td class="nowrap">' . dol_escape_htmltag($row['file']) . '</td>';
        $out .= '<td>' . $csvDisplay . '</td>';
        $out .= '<td>' . $tplDisplay . '</td>';
        $out .= '<td class="right nowrap">' . $size . '</td>';

        $out .= '<td class="nowrap">';
        $out .= '<a class="butAction" href="?tab=admin&action=viewlog&logfile='.$fileEsc.'">'.$langs->trans('ViewLabel').'</a> ';
        $out .= '<a class="butAction" href="?tab=admin&action=downloadlog&logfile='.$fileEsc.'">'.$langs->trans('DownloadLabel').'</a> ';
        $out .= '<a class="butActionDelete" href="?tab=admin&action=deletelog&logfile='.$fileEsc.'"'
             .  ' onclick="return confirm(\'' . dol_escape_js($langs->trans('ConfirmDeleteLog', $row['file'])) . '\');">'
             .  $langs->trans('DeleteLabel')
             .  '</a>';
        $out .= '</td>';

        $out .= '</tr>';
    }

    $out .= '</table></div>';

    $out .= '<div class="opacitymedium" style="margin-top:8px">'
         .  $langs->trans('LogsTitle')
         .  ': ' . dol_escape_htmltag(batchmailer_get_logs_dir())
         .  '</div>';

    return $out;
}

/**
 * Render a simple log viewer panel.
 */
function batchmailer_render_log_viewer(string $filename, string $content, $langs): string
{
    $out  = '<h3>' . $langs->trans('LogsTitle') . '</h3>';
    $out .= '<div class="info">';
    $out .= '<strong>' . dol_escape_htmltag($filename) . '</strong>';
    $out .= ' &nbsp; <a class="butAction" href="?tab=admin&action=closelog">' . $langs->trans('CloseLabel') . '</a>';
    $out .= '</div>';

    $out .= '<div style="max-height:520px; overflow:auto; border:1px solid #ddd; padding:10px; background:#fff;">';
    $out .= '<pre style="margin:0; white-space:pre-wrap;">' . dol_escape_htmltag($content) . '</pre>';
    $out .= '</div>';

    return $out;
}

// Point user to admin panels from send screen (when appropriate)
function batchmailer_render_send_admin_hint($langs)
{
    print '<div class="info" style="margin-top:12px">';
    
    print '<strong>'
        . $langs->trans('NeedHelpOrControl')
        . '</strong><br>';

    print $langs->trans(
        'BatchMailerAdminHintText',
        '<a href="?tab=admin">'
        . $langs->trans('AdminTab')
        . '</a>'
    );

    print '</div>';
}

