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

// Display the campaign state
function batchmailer_render_campaign_state($langs)
{
    $hasCsv      = !empty($_SESSION['batchmailer_csv']);
    $hasTemplate = !empty($_SESSION['batchmailer_template']);

    if (!$hasCsv && !$hasTemplate) {
        print '<div class="opacitymedium">';
        print $langs->trans('NoActiveCampaign');
        print '</div>';
        return;
    }

    $csvName = $hasCsv
        ? basename($_SESSION['batchmailer_csv']['path'] ?? '')
        : '—';

    $template = $_SESSION['batchmailer_template'] ?? '—';

    $offset = (int) ($_SESSION['batchmailer_offset'] ?? 0);
    $total  = (int) ($_SESSION['batchmailer_total'] ?? 0);

    $failed = !empty($_SESSION['batchmailer_failed'])
        ? count($_SESSION['batchmailer_failed'])
        : 0;

    // Status resolution
    $status = $langs->trans('CampaignReady');

    if (!empty($_SESSION['batchmailer_aborted'])) {
        $status = $langs->trans('CampaignAborted');
    } elseif ($total > 0 && $offset >= $total) {
        $status = $langs->trans('CampaignCompleted');
    } elseif ($offset > 0) {
        $status = $langs->trans('CampaignInProgress');
    }

    // Last action time
    $lastAction = '—';
    if (!empty($_SESSION['batchmailer_aborted']['time'])) {
        $lastAction = dol_print_date(
            $_SESSION['batchmailer_aborted']['time'],
            'dayhour'
        );
    }

    $logFile = basename(batchmailer_get_log_path() ?? '');

    print '<fieldset class="batchmailer-admin-panel">';
    print '<legend>'.$langs->trans('CampaignState').'</legend>';

    print '<table class="noborder centpercent">';
    print '<tr><td>'.$langs->trans('RecipientList').'</td><td>'.$csvName.'</td></tr>';
    print '<tr><td>'.$langs->trans('TemplateLabel').'</td><td>'.$template.'</td></tr>';
    print '<tr><td>'.$langs->trans('TotalRecipients').'</td><td>'.$total.'</td></tr>';
    print '<tr><td>'.$langs->trans('SentSoFar').'</td><td>'.$offset.'</td></tr>';
    print '<tr><td>'.$langs->trans('FailedCount').'</td><td>'.$failed.'</td></tr>';
    print '<tr><td>'.$langs->trans('CampaignStatus').'</td><td>'.$status.'</td></tr>';
    print '<tr><td>'.$langs->trans('LastAction').'</td><td>'.$lastAction.'</td></tr>';
    print '<tr><td>'.$langs->trans('LogFile').'</td><td>'.$logFile.'</td></tr>';
    print '</table>';

    print '</fieldset>';
}

// Campaign action buttons
function batchmailer_render_campaign_actions($langs)
{
    $offset = (int) ($_SESSION['batchmailer_offset'] ?? 0);
    $total  = (int) ($_SESSION['batchmailer_total'] ?? 0);
    $aborted = !empty($_SESSION['batchmailer_aborted']);

    print '<div class="batchmailer-admin-actions">';

    // ▶️ Resume
    if ($offset > 0 && $offset < $total && !$aborted) {
        print '<a class="butAction" href="?tab=send&action=resumecampaign">';
        print $langs->trans('ResumeCampaign');
        print '</a> ';
    }

    // 🔁 Restart
    if ($total > 0) {
        print '<a class="butActionDelete" href="?action=restartcampaign"'
            . ' onclick="return confirm(\'' 
            . dol_escape_js($langs->trans('ConfirmRestartCampaign')) 
            . '\');">';
        print $langs->trans('RestartCampaign');
        print '</a> ';
    }

    // 🧹 Clear
    if ($total > 0 || $offset > 0) {
        print '<a class="butActionDelete" href="?action=clearcampaign"'
            . ' onclick="return confirm(\'' 
            . dol_escape_js($langs->trans('ConfirmClearCampaign')) 
            . '\');">';
        print $langs->trans('ClearCampaign');
        print '</a>';
    }

    print '</div>';
}

// Safety: Send lock UI
function batchmailer_render_send_lock($langs)
{
    $locked = !empty($_SESSION['batchmailer_send_locked']);

    print '<fieldset>';
    print '<legend>'.$langs->trans('SafetyControls').'</legend>';

    if ($locked) {
        print '<div class="warning">'.$langs->trans('SendingIsLocked').'</div>';
        print '<a class="butAction" href="?action=unlocksend">'
            .$langs->trans('UnlockSending')
            .'</a>';
    } else {
        print '<div class="ok">'.$langs->trans('SendingIsUnlocked').'</div>';
        print '<a class="butActionDelete" href="?action=locksend"'
            .' onclick="return confirm(\'' 
            .dol_escape_js($langs->trans('ConfirmLockSending'))
            .'\');">'
            .$langs->trans('LockSending')
            .'</a>';
    }

    print '</fieldset>';
}

// Admin emergency override 
function batchmailer_render_admin_override($langs)
{
    $active = !empty($_SESSION['batchmailer_admin_override']);

    print '<fieldset>';
    print '<legend>'.$langs->trans('AdminOverrides').'</legend>';

    if ($active) {
        print '<div class="warning">'
            .$langs->trans('AdminOverrideActive')
            .'</div>';
        print '<a class="butActionDelete" href="?action=disableoverride">'
            .$langs->trans('DisableOverride')
            .'</a>';
    } else {
        print '<a class="butActionDelete" href="?action=enableoverride"'
            .' onclick="return confirm(\'' 
            .dol_escape_js($langs->trans('ConfirmAdminOverride'))
            .'\');">'
            .$langs->trans('EnableOverride')
            .'</a>';
    }

    print '</fieldset>';
}

// Admin help panel
function batchmailer_render_admin_help_panel(
    string $title,
    string $content,
    string $id
) {
    global $langs;

    print '<div class="batchmailer-admin-help">';
    print '<button type="button"
        class="admin-help-toggle"
        onclick="toggleAdminHelp(\''.$id.'\')">';
    print dol_escape_htmltag($title);
    print '</button>';

    print '<div id="'.$id.'" class="admin-help-content" style="display:none">';
    print $content;
    print '</div>';
    print '</div>';
}

// Convert log files to sections and HTML
function batchmailer_render_log_as_html(string $raw): string
{
	global $langs;

    // Split log into campaigns
    $blocks = preg_split('/(?=^# BatchMailer send)/m', $raw);

    $out = '<div class="batchmailer-log-container">';

    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') continue;

        $lines = preg_split('/\R/', $block);

        // Extract summary info for the header
        $title = 'BatchMailer send';
        foreach ($lines as $line) {
            if (strpos($line, '# Started:') === 0) {
                $title .= ' — ' . trim(substr($line, 10));
                break;
            }
        }

//        $out .= '<details class="batchmailer-log-block">';
//        $out .= '<summary>' . dol_escape_htmltag($title) . '</summary>';
		$out .= '<details class="batchmailer-log-block">';
		$out .= '<summary>';
		$out .= '<span>' . dol_escape_htmltag($title) . '</span>';
		$out .= ' <button type="button" class="log-copy-btn" onclick="batchmailerCopyLog(this)">'
			 . $langs->trans('CopyLabel')
			 . '</button>';
		$out .= '</summary>';
		$out .= '<div class="batchmailer-log-view" data-logtext>';

        foreach ($lines as $line) {
            $escaped = dol_escape_htmltag($line);

            if (strpos($line, '# ') === 0) {
                $out .= '<div class="log-header">'.$escaped.'</div>';
            } elseif (strpos($line, 'SUCCESS |') === 0) {
                $out .= '<div class="log-success">'.$escaped.'</div>';
            } elseif (strpos($line, 'FAILED  |') === 0) {
                $out .= '<div class="log-failed">'.$escaped.'</div>';
            } elseif (strpos($line, 'ATTEMPT |') === 0) {
                $out .= '<div class="log-attempt">'.$escaped.'</div>';
            } elseif (trim($line) === '') {
                $out .= '<div class="log-blank">&nbsp;</div>';
            } else {
                $out .= '<div class="log-line">'.$escaped.'</div>';
            }
        }

        $out .= '</div></details>';
    }

    $out .= '</div>';
    return $out;
}

//Admin FAQ
function batchmailer_render_admin_faq()
{
    global $langs;

    $out  = '<div class="batchmailer-admin-faq">';

    // Logs
    $out .= '<div class="admin-faq-block">';
    $out .= '<h3>'.$langs->trans('AdminFAQLogsTitle').'</h3>';
    $out .= '<p>'.$langs->trans('AdminFAQLogsIntro').'</p>';
    $out .= '<ul>';
    $out .= '<li>'.$langs->trans('AdminFAQLogsPoint1').'</li>';
    $out .= '<li>'.$langs->trans('AdminFAQLogsPoint2').'</li>';
    $out .= '<li>'.$langs->trans('AdminFAQLogsPoint3').'</li>';
    $out .= '</ul>';
    $out .= '</div>';

    // Aborted campaigns
    $out .= '<div class="admin-faq-block">';
    $out .= '<h3>'.$langs->trans('AdminFAQAbortTitle').'</h3>';
    $out .= '<p>'.$langs->trans('AdminFAQAbortIntro').'</p>';
    $out .= '<ul>';
    $out .= '<li>'.$langs->trans('AdminFAQAbortPoint1').'</li>';
    $out .= '<li>'.$langs->trans('AdminFAQAbortPoint2').'</li>';
    $out .= '</ul>';
    $out .= '</div>';

    $out .= '</div>';

    return $out;
}
