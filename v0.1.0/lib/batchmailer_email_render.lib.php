<?php

/**
 * Functions in this file:
 * 
 * function batchmailer_generate_template_filename(array $template): string
 * function batchmailer_wrap_email_body(string $html, array $template): string
 * function batchmailer_render_footer(): string
 * function render_field_table(array $csvHeaders, array $template, $langs): void
 * function batchmailer_normalize_field(string $field): string
 * function batchmailer_get_org_context(): array
 * function batchmailer_render_logo_html(): string
 * function batchmailer_render_footer_html(array $org): string
 * function batchmailer_render_footer_text(array $org): string
 * 
 */

function batchmailer_generate_template_filename(array $template): string
{
    $base = batchmailer_normalize_field($template['name'] ?? 'template');
    if (!$base) {
        $base = 'template';
    }

    return $base . '.json';
}

function batchmailer_wrap_email_body(string $html, array $template): string
{
    global $conf;

    $out = '';

    // Logo (optional)
    if (!empty($template['include_logo']) && !empty($conf->mycompany->logo)) {
        $logoUrl = DOL_URL_ROOT . '/viewimage.php?modulepart=mycompany&file='
                 . urlencode($conf->mycompany->logo);

        $out .= '<div style="margin-bottom:15px;text-align:left">';
        $out .= '<img src="' . $logoUrl . '" alt="" style="max-height:80px">';
        $out .= '</div>';
    }

    $out .= $html;

    // Footer (optional)
    if (!empty($template['include_footer'])) {
        $out .= batchmailer_render_footer();
    }

    return $out;
}

function batchmailer_render_footer(): string
{
    global $conf;

    $lines = [];

    if (!empty($conf->mycompany->name)) {
        $lines[] = '<strong>' . dol_escape_htmltag($conf->mycompany->name) . '</strong>';
    }

    $address = trim(
        ($conf->mycompany->address ?? '') . '<br>' .
        ($conf->mycompany->zip ?? '') . ' ' .
        ($conf->mycompany->town ?? '')
    );
    if ($address !== '') {
        $lines[] = $address;
    }

    if (!empty($conf->mycompany->phone)) {
        $lines[] = $conf->mycompany->phone;
    }

    if (!empty($conf->mycompany->email)) {
        $lines[] = '<a href="mailto:' . $conf->mycompany->email . '">'
                 . $conf->mycompany->email . '</a>';
    }

    if (!empty($conf->mycompany->url)) {
        $lines[] = '<a href="' . $conf->mycompany->url . '">'
                 . $conf->mycompany->url . '</a>';
    }

    return
        '<hr style="margin-top:25px">' .
        '<div style="font-size:90%;color:#555">' .
        implode('<br>', $lines) .
        '</div>';
}

// Show table of fields read from CSV file with selection/required checkboxes
function render_field_table(array $csvHeaders, array $template, $langs): void
{
    $csvHeaders = $_SESSION['batchmailer_csv_analysis']['header'] ?? [];
    $hasCsvHeaders = is_array($csvHeaders) && count($csvHeaders) > 0;
    
    if ($hasCsvHeaders) {
        $csvHeaders = array_values(array_unique(array_filter(array_map(
            function ($h) { return batchmailer_normalize_field((string) $h); },
            $csvHeaders
        ))));
    }

    // Determine existing selections from template['fields'] (preferred) or template['required_fields'] (fallback)
    $fields = $template['fields'] ?? [];

    $use = [];
    $req = [];

    if (is_array($fields) && !empty($fields)) {
        foreach ($fields as $name => $opts) {
            $name = batchmailer_normalize_field((string) $name);
            if ($name === '') continue;
            if (!empty($opts['use'])) $use[$name] = true;
            if (!empty($opts['required'])) $req[$name] = true;
        }
    } else {
        // Fallback for older templates: treat required_fields as "use+required"
        $oldReq = $template['required_fields'] ?? [];
        if (is_array($oldReq)) {
            foreach ($oldReq as $name) {
                $name = batchmailer_normalize_field((string) $name);
                if ($name === '') continue;
                $use[$name] = true;
                $req[$name] = true;
            }
        }
    }

    // Ensure email is always present/locked
    $use['email'] = true;
    $req['email'] = true;

    // Build field list from CSV headers, but ensure email is included even if missing
    $fieldList = $csvHeaders;
    if (!in_array('email', $fieldList, true)) {
        array_unshift($fieldList, 'email');
        $fieldList = array_values(array_unique($fieldList));
    }

    print '<fieldset>';
    print '<legend>'.$langs->trans('TemplateFields').'</legend>';
    print '<p class="opacitymedium">'.$langs->trans('TemplateFieldsHelp').'</p>';

    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans('Field').'</td>';
    print '<td class="center">'.$langs->trans('UseInTemplate').'</td>';
    print '<td class="center">'.$langs->trans('FieldRequired').'</td>';
    print '</tr>';

    foreach ($fieldList as $f) {
        $f = batchmailer_normalize_field((string) $f);
        if ($f === '') continue;

        $isEmail = ($f === 'email');
        $isUseChecked = !empty($use[$f]);
        $isReqChecked = !empty($req[$f]);

        $useDisabled = $isEmail ? ' disabled' : '';
        $reqDisabled = (!$isUseChecked || $isEmail) ? ' disabled' : '';

        // Row
        print '<tr>';
        print '<td><code>'.dol_escape_htmltag($f).'</code></td>';

        // Use checkbox
        print '<td class="center">';
        print '<input type="checkbox" name="use_fields[]" value="'.dol_escape_htmltag($f).'"'
            . ($isUseChecked ? ' checked' : '')
            . $useDisabled
            . ' class="bm-use-field" data-field="'.dol_escape_htmltag($f).'">';
        print '</td>';

        // Required checkbox
        print '<td class="center">';
        print '<input type="checkbox" name="required_fields[]" value="'.dol_escape_htmltag($f).'"'
            . ($isReqChecked ? ' checked' : '')
            . $reqDisabled
            . ' class="bm-req-field" data-field="'.dol_escape_htmltag($f).'">';
        if ($isEmail) {
            print ' <span class="opacitymedium">'.$langs->trans('AlwaysRequired').'</span>';
        }
        print '</td>';

        print '</tr>';
    }

    print '</table>';

    // Helper panel (placeholder pills) – shown/filled by JS
    print '<div id="bm-placeholder-helper" style="margin-top:12px; display:none;">';
    print '<div class="opacitymedium" style="margin-bottom:6px">'
        . $langs->trans('PlaceholdersHelperText') . '</div>';
    print '<div id="bm-placeholder-pills"></div>';
    print '</div>';

    print '</fieldset>';
}

/*
 * Canonical normaliser (use this everywhere)
 *
 * Required behaviour
 * Normalisation must:
 * Lowercase
 * Trim
 * Replace spaces and punctuation with underscores
 * Collapse repeats
 */
function batchmailer_normalize_field(string $field): string
{
    $field = strtolower(trim($field));
    $field = preg_replace('/[^a-z0-9]+/', '_', $field);
    return trim($field, '_');
}

/*
 * Organisation header/footer branding functions
 */
function batchmailer_get_org_context(): array
{
    global $conf;

    return [
        'name'    => $conf->global->MAIN_INFO_SOCIETE_NOM ?? '',
        'address' => trim(
            ($conf->global->MAIN_INFO_SOCIETE_ADDRESS ?? '') . "\n" .
            ($conf->global->MAIN_INFO_SOCIETE_ZIP ?? '') . ' ' .
            ($conf->global->MAIN_INFO_SOCIETE_TOWN ?? '')
        ),
        'phone'   => $conf->global->MAIN_INFO_SOCIETE_TEL ?? '',
        'email'   => $conf->global->MAIN_INFO_SOCIETE_MAIL ?? '',
        'web'     => $conf->global->MAIN_INFO_SOCIETE_WEB ?? '',
        'logo'    => $conf->global->MAIN_INFO_SOCIETE_LOGO ?? ''
    ];
}

function batchmailer_render_logo_html(): string
{
    global $conf;

    if (empty($conf->global->MAIN_INFO_SOCIETE_LOGO)) {
        return '';
    }

    $logoUrl =
        DOL_URL_ROOT . '/viewimage.php?modulepart=mycompany&file='
        . urlencode($conf->global->MAIN_INFO_SOCIETE_LOGO);

    return '<div style="margin-bottom:20px">
        <img src="'.$logoUrl.'" alt="" style="max-width:200px;height:auto;">
    </div>';
}

function batchmailer_render_footer_html(array $org): string
{
    $lines = array_filter([
        '<strong>'.htmlspecialchars($org['name']).'</strong>',
        nl2br(htmlspecialchars($org['address'])),
        $org['phone'] ? 'Tel: '.htmlspecialchars($org['phone']) : '',
        $org['email'] ? 'Email: '.htmlspecialchars($org['email']) : '',
        $org['web']   ? htmlspecialchars($org['web']) : ''
    ]);

    return '<hr><div style="font-size:12px;color:#666;margin-top:15px">'
        . implode('<br>', $lines)
        . '</div>';
}

function batchmailer_render_footer_text(array $org): string
{
    $lines = array_filter([
        $org['name'],
        $org['address'],
        $org['phone'] ? 'Tel: '.$org['phone'] : '',
        $org['email'] ? 'Email: '.$org['email'] : '',
        $org['web']   ? $org['web'] : ''
    ]);

    return "\n\n---\n" . implode("\n", $lines);
}


