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

/****************************************************
 * Batch Mailer – Template Editor
 * CKEditor-enabled version (Dolibarr-compatible)
 *
 * Allows editing of:
 *   - templates/email_template.html (with CKEditor)
 *   - templates/email_template.txt (plain textarea)
 *
 * Strictly requires tokens:
 *   {{email}}
 *
 ****************************************************/

// Phase 1. Bootstrap phase (no logic)
require '../../main.inc.php';

require_once DOL_DOCUMENT_ROOT.'/custom/batchmailer/lib/template_draft.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/batchmailer/lib/batchmailer.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/batchmailer/lib/batchmailer_email_render.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/batchmailer/lib/template_validator.lib.php';

if (empty($user->rights->batchmailer->admin)) {
    accessforbidden();
}

$langs->loadLangs(['batchmailer@batchmailer']);

$templateDir = DOL_DATA_ROOT.'/batchmailer/templates';
dol_mkdir($templateDir);

// Phase 2. Determine intent (only done here)
$action = GETPOST('action', 'alpha');
$selectedFile = GETPOST('template_file', 'alphanohtml');
$returnTab = GETPOST('returntab', 'aZ09');
if (empty($returnTab)) {
    $returnTab = 'templates';
}

$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'save' && $method === 'POST') {
    $intent = 'save';
} elseif ($action === 'create') {
    $intent = 'create';
} elseif (!empty($selectedFile)) {
    $intent = 'edit';
} else {
    $intent = 'browse';
}

// $intent = browse | create | edit | save

// Phase 3 — Load or build template data (pure, predictable)

//Load all templates once
$templates = [];
foreach (glob($templateDir.'/*.json') as $file) {
    $data = json_decode(file_get_contents($file), true);
    if (is_array($data) && !empty($data['name'])) {
        $templates[basename($file)] = $data;
    }
}

// Resolve $template from intent
$template = null;
$isNewTemplate = false;

switch ($intent) {

    case 'create':
        $template = batchmailer_template_draft_base();
        $isNewTemplate = true;
        $selectedFile = '';
        break;

    case 'edit':
        if (isset($templates[$selectedFile])) {
            $template = $templates[$selectedFile];
        }
        break;

    case 'save':
        // build from POST later
        $template = [];
        break;

    case 'browse':
        // no editor yet
        break;
}

/*
 * At this point:
 * 
 * $template is either null or an array
 * It is never overwritten again
*/

// Phase 4 — Handle save (isolated, no UI)

// This is the only place that writes files.

if ($intent === 'save') {

    $updated = build_template_from_post(); // function below

    require_once DOL_DOCUMENT_ROOT.'/custom/batchmailer/lib/template_validator.lib.php';
    $validation = validate_batchmailer_template($updated);

    if (!empty($validation['errors'])) {
        foreach ($validation['errors'] as $err) {
            setEventMessages($langs->trans($err), null, 'errors');
        }
    } else {

        if (empty($selectedFile)) {
            // new template
            $filename = batchmailer_generate_template_filename($updated);
        } else {
            $filename = basename($selectedFile);
        }

        $path = $templateDir.'/'.$filename;

        file_put_contents(
            $path,
            json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        setEventMessages($langs->trans('TemplateSaved'), null, 'mesgs');

        header('Location: '.dol_buildpath(
            '/custom/batchmailer/template_editor.php', 1
        ).'?template_file='.urlencode($filename));
        exit;
    }

    // fall back to editor if validation failed
    $template = $updated;
    $isNewTemplate = empty($selectedFile);
}

// Helper function (this is crucial clarity)
function build_template_from_post(): array
{
    $tpl = [];

    $tpl['name']        = trim(GETPOST('name', 'restricthtml'));
    $tpl['description'] = trim(GETPOST('description', 'restricthtml'));
    $tpl['subject']     = trim(GETPOST('subject', 'restricthtml'));

    $tpl['from_name']  = trim(GETPOST('from_name', 'restricthtml'));
    $tpl['from_email'] = trim(GETPOST('from_email', 'email'));
    $tpl['reply_to']   = trim(GETPOST('reply_to', 'email'));

    $tpl['body_text'] = GETPOST('body_text', 'none');
    $tpl['body_html'] = GETPOST('body_html', 'none');
    
    $tpl['logo_url'] = GETPOST('logo_url', 'none');

    // NEW: fields table (use + required)
    $tpl['fields'] = [];

    $useFields = GETPOST('use_fields', 'array') ?: [];
    $reqFields = GETPOST('required_fields', 'array') ?: [];

    // Normalise posted arrays into sets of lowercase keys
    $useSet = [];
    foreach ($useFields as $f) {
        $k = strtolower(trim((string) $f));
        if ($k !== '') $useSet[$k] = true;
    }

    $reqSet = [];
    foreach ($reqFields as $f) {
        $k = strtolower(trim((string) $f));
        if ($k !== '') $reqSet[$k] = true;
    }

    // Always force email
    $tpl['fields']['email'] = ['use' => true, 'required' => true];

    // Build rule objects from selected "use" fields
    foreach ($useSet as $field => $_) {
        if ($field === 'email') continue;
        $tpl['fields'][$field] = [
            'use'      => true,
            'required' => !empty($reqSet[$field]),
        ];
    }

    // Back-compat: derive required_fields list (optional; helps older code paths)
    $tpl['required_fields'] = ['email'];
    foreach ($tpl['fields'] as $field => $opts) {
        if (!empty($opts['required']) && $field !== 'email') {
            $tpl['required_fields'][] = $field;
        }
    }

    // Include logo/footer options (if you add them as checkboxes)
    $tpl['include_logo']   = (int) GETPOST('include_logo', 'int') === 1;
    $tpl['include_footer'] = (int) GETPOST('include_footer', 'int') === 1;

    return $tpl;
}


/*
1️⃣render_template_selector()

This renders:

the template dropdown
the Create new template button

It assumes:

$templates is already loaded
$selectedFile may be empty
*/

function render_template_selector(array $templates, string $selectedFile = '')
{
    global $langs;
    
    print '<form method="post" action="'.dol_buildpath('/custom/batchmailer/template_editor.php', 1).'">';
    print '<label>'.$langs->trans('TemplateLabel').'</label> ';
    print '<select name="template_file">';
    print '<option value="">'.$langs->trans('SelectTemplatePlaceholder').'</option>';

    foreach ($templates as $file => $tpl) {
        $sel = ($file === $selectedFile) ? ' selected' : '';
        print '<option value="'.$file.'"'.$sel.'>'
            . dol_escape_htmltag($tpl['name'])
            . '</option>';
    }

    print '</select> ';
    print '<input type="submit" class="button" value="'.$langs->trans('LoadTemplateButton').'">';
    print '</form><br>';

    print '<a class="butAction" href="'
        . dol_buildpath('/custom/batchmailer/template_editor.php', 1)
        . '?action=create">';
    print $langs->trans('CreateNewTemplate');
    print '</a><br><br>';
}

/*
2️⃣ render_template_editor()

This renders everything:

metadata fields
required fields textarea
plain text body
HTML body (DolEditor)
save button

It assumes:

$template is an array
$isNewTemplate is accurate
$selectedFile may be empty
*/

function render_template_editor(array $template, string $selectedFile, bool $isNewTemplate)
{
    global $langs;

    print '<form method="post" action="'.dol_buildpath('/custom/batchmailer/template_editor.php', 1).'" onsubmit="return maybeGeneratePlainTextOnSave();">';

    print '<input type="hidden" name="action" value="save">';
    print '<input type="hidden" name="template_file" value="'.dol_escape_htmltag($selectedFile).'">';

    print '<table class="border centpercent">';

    print '<tr><td>Name</td><td>'
        . '<input class="minwidth300" name="name" value="'
        . dol_escape_htmltag($template['name'] ?? '')
        . '"></td></tr>';

    print '<tr><td>Description</td><td>'
        . '<input class="minwidth300" name="description" value="'
        . dol_escape_htmltag($template['description'] ?? '')
        . '"></td></tr>';

    print '<tr><td>Subject</td><td>'
        . '<input class="minwidth300" name="subject" value="'
        . dol_escape_htmltag($template['subject'] ?? '')
        . '"></td></tr>';

    print '<tr><td>From name</td><td>'
        . '<input name="from_name" value="'
        . dol_escape_htmltag($template['from_name'] ?? '')
        . '"></td></tr>';

    print '<tr><td>From email</td><td>'
        . '<input name="from_email" value="'
        . dol_escape_htmltag($template['from_email'] ?? '')
        . '"></td></tr>';

    print '<tr><td>Reply-to</td><td>'
        . '<input name="reply_to" value="'
        . dol_escape_htmltag($template['reply_to'] ?? '')
        . '"></td></tr>';

    print '</table><br>';

    // Required fields
    print '<fieldset>';
    print '<legend>'.$langs->trans('BatchMailerRequiredFields').'</legend>';

    print '<p class="opacitymedium">'
        . $langs->trans('BatchMailerRequiredFieldsHelp')
        . '</p>';

    $requiredText = '';
    if (!empty($template['required_fields']) && is_array($template['required_fields'])) {
        $requiredText = implode("\n", $template['required_fields']);
    }

    // Field selection table (from CSV headers)
    $csvHeaders = $_SESSION['batchmailer_csv_analysis']['header'] ?? [];

    $csvHeaders = array_values(array_unique(array_filter(array_map(
        function ($h) {
            return strtolower(trim((string) $h));
        },
        $csvHeaders
    ))));
    
    $hasCsvHeaders = !empty($csvHeaders);
    if (!$hasCsvHeaders) {
        print '<div class="info" style="margin-bottom:12px">';
        print $langs->trans('TemplateEditorNoCsvInfo');
        print '</div>';
    }
    
    if ($hasCsvHeaders) {
        render_field_table($csvHeaders, $template, $langs);
    } else {
        print '<fieldset>';
        print '<legend>'.$langs->trans('TemplateFields').'</legend>';
        print '<p class="opacitymedium">'
            . $langs->trans('TemplateFieldsAppearAfterCsv')
            . '</p>';
        print '</fieldset>';
    }
    
    print '<br>';

/*
    print '<textarea name="required_fields_text" rows="6" style="width:300px">';
    print dol_escape_htmltag($requiredText);
    print '</textarea>';
*/
    print '</fieldset><br>';

    print '<label>
      <input type="checkbox" name="include_logo" value="1"'
        . (!empty($template['include_logo']) ? 'checked' : '') . '>'
      . 'Include organisation logo'
    . '</label>';
    
    print '<label>
      <input type="checkbox" name="include_footer" value="1"'
        . (!empty($template['include_footer']) ? 'checked' : '') .'>'
      . 'Include organisation footer'
    . '</label>';
    
    // HTML body
    print '<h3>HTML body</h3>';

    // Logo preview block
    print '<p class="opacitymedium">';
    print $langs->trans('InsertAnOptionalHeaderLogo');
    print '<p>Logo web address: '
        . '<input name="logo_url" width="300" value="'
        . dol_escape_htmltag($template['logo_url'] ?? '')
        . '"></p>';
    if (!empty($template['logo_url'])) {
        print '<p>';
            print '<div class="template-logo-preview">
          <img src="'.$template['logo_url'].'" style="max-width:500px;height:auto">
        </div></p>';
    }
    print '<fieldset>';
    print '<legend>'.$langs->trans('BatchMailerHtmlBody').'</legend>';
    print '<p class="opacitymedium">'
        . 'Note: Do not use the link button for dynamic URLs. Instead, type, for example: '
        . '<span style="color:darkblue;">Click to renew here: {{url}}</span></p>';

    require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';

    // DolEditor block
    $editor = new DolEditor(
        'body_html',
        $template['body_html'] ?? '',
        '',
        300,
        'dolibarr_mailings',
        'In',
        false,
        true,
        true,
        ROWS_6,
        '90%'
    );
    $editor->Create();

    print '<p class="opacitymedium">'
        . $langs->trans('BatchMailerHtmlBody')
        . '</p>';

    print '</fieldset><br>'; 
    // end HTML

    // Plain text body
    print '<fieldset>';
    print '<legend>'.$langs->trans('BatchMailerTextBody').'</legend>';
    
    print '<p class="opacitymedium">'
        . $langs->trans('BatchMailerTextAutoGeneratedNotice')
        . '</p>';
    
    print '<details>';
    print '<summary>'.$langs->trans('ViewTextOnlyVersion').'</summary>';
    print '<pre class="batchmailer-text-preview">'
        . dol_escape_htmltag(
            batchmailer_generate_text_body($template['body_html'] ?? '')
        )
        . '</pre>';
    print '</details>';
    
    print '</fieldset>';
    // end plain-text
    
    print '<p class="opacitymedium">';
    print $langs->trans('TemplateMustHaveNameSubjectAndBody');
    print '</p>';
    print '<br><br><input type="submit" class="button button-save" value="'.$langs->trans('SaveTemplate').'">';

    // Return to start button
    print '<div class="center" style="margin-top:10px">';
    
    print '<a class="butAction" href="'.dol_buildpath(
        '/custom/batchmailer/template_editor.php', 1
    ).'">';

    print $langs->trans('BackToManageTemplates');
    print '</a>';
    print '</div>';

    print '</form>';
}

// Phase 5 — Render UI (the editor block stays)

/*========================================================================
 * UI Begins
 */
llxHeader('', 'Template Editor');

$hasCsv = !empty($_SESSION['batchmailer_csv']);

if ($hasCsv && empty($_SESSION['batchmailer_csv_analysis'])) {
    require_once DOL_DOCUMENT_ROOT
        . '/custom/batchmailer/lib/csv_preview.lib.php';

    $_SESSION['batchmailer_csv_analysis'] = analyze_csv_for_preview(
        $_SESSION['batchmailer_csv']['path'],
        0 // no preview rows needed here
    );
}

$backTab = $hasCsv
    ? ($returnTab ?: 'templates')
    : 'prepare';

$url = dol_buildpath('/custom/batchmailer/batchmailer.php', 1)
     . '?tab=' . urlencode($backTab);
print load_fiche_titre(
    $isNewTemplate
        ? $langs->trans('BatchMailerCreateTemplate')
        : $langs->trans('BatchMailerEditTemplate'),
    '<a class="butAction" href="' . $url . '">'
    .$langs->trans('BackToBatchMailer').
    '</a>',
    'email'
);

if ($intent === 'browse') {
    render_template_selector($templates, $selectedFile);
}

if (is_array($template)) {
    render_template_editor($template, $selectedFile, $isNewTemplate);
}

//JS behaviour: enable “required” only when “use” is checked + copy pills
print '<script>
(function() {
  function updateRequiredEnabled() {
    var useBoxes = document.querySelectorAll(".bm-use-field");
    useBoxes.forEach(function(useBox) {
      var field = useBox.getAttribute("data-field");
      var reqBox = document.querySelector(".bm-req-field[data-field=\\"" + field + "\\"]");
      if (!reqBox) return;

      // email stays locked
      if (field === "email") {
        reqBox.disabled = true;
        useBox.disabled = true;
        useBox.checked = true;
        reqBox.checked = true;
        return;
      }

      reqBox.disabled = !useBox.checked;
      if (!useBox.checked) reqBox.checked = false;
    });
  }

  function refreshPlaceholderPills() {
    var pillsWrap = document.getElementById("bm-placeholder-pills");
    var panel = document.getElementById("bm-placeholder-helper");
    if (!pillsWrap || !panel) return;

    var selected = [];
    document.querySelectorAll(".bm-use-field:checked").forEach(function(cb) {
      var field = cb.getAttribute("data-field");
      if (!field || field === "email") return;
      selected.push(field);
    });

    // Show panel only if at least one non-email field selected
    if (selected.length === 0) {
      panel.style.display = "none";
      pillsWrap.innerHTML = "";
      return;
    }

    panel.style.display = "block";
    pillsWrap.innerHTML = "";

    selected.sort().forEach(function(field) {
      var token = "{{" + field + "}}";
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "button small";
      btn.style.marginRight = "6px";
      btn.style.marginBottom = "6px";
      btn.textContent = token;
      btn.addEventListener("click", function() {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(token);
        } else {
          // fallback
          var tmp = document.createElement("textarea");
          tmp.value = token;
          document.body.appendChild(tmp);
          tmp.select();
          document.execCommand("copy");
          document.body.removeChild(tmp);
        }
      });
      pillsWrap.appendChild(btn);
    });
  }

  document.addEventListener("change", function(e) {
    if (e.target && e.target.classList && e.target.classList.contains("bm-use-field")) {
      updateRequiredEnabled();
      refreshPlaceholderPills();
    }
  });

  // initial
  updateRequiredEnabled();
  refreshPlaceholderPills();
})();
</script>';

// Script to convert HTML to plain-text and copy it.
print '<script>
function generatePlainText() {

  // Force CKEditor -> textarea sync
  if (window.CKEDITOR) {
    for (var k in CKEDITOR.instances) {
      if (CKEDITOR.instances.hasOwnProperty(k)) {
        CKEDITOR.instances[k].updateElement();
      }
    }
  }

  var htmlEl = document.querySelector(\'[name="body_html"]\');
  var textEl = document.querySelector(\'[name="body_text"]\');

  if (!htmlEl || !textEl) {
    alert("'.$langs->trans('CouldNotFindBodyHtmlBodyTextFields').'");
    return;
  }

  var tmp = document.createElement(\'div\');
  tmp.innerHTML = htmlEl.value || \'\';

  var text = tmp.textContent || tmp.innerText || \'\';

  // 🔧 Normalize line endings
  text = text.replace(/\\r\\n/g, \'\\n\').replace(/\\r/g, \'\\n\');

  // Clean excessive spacing
  text = text.replace(/\\n{3,}/g, \'\\n\\n\').trim();

  textEl.value = text;
}

function maybeGeneratePlainTextOnSave() {

    var checkbox = document.querySelector(\'[name="auto_generate_text"]\');
    if (!checkbox || !checkbox.checked) {
        return true;
    }

    var textField = document.querySelector(\'[name="body_text"]\');

    if (textField && textField.value.trim() !== \'\') {
        var ok = confirm('.$langs->trans('PlainTextContentWillBeRegeneratedFromHtml').');
        if (!ok) {
            return false; // abort save
        }
    }

    // IMPORTANT: always regenerate if we reach here
    generatePlainText();
    return true;
}

</script>';