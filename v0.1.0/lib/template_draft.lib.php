<?php
/*
 * Support functions for creating templates
 *-----------------------------------------------------*/


// Prevent direct access
if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

/**
 * Return a clean draft template structure
 *
 * @return array
 */
function batchmailer_template_draft_base(): array
{
    return [
        'name'            => '',
        'description'     => '',
        'subject'         => '',
        'from_name'       => '',
        'from_email'      => '',
        'reply_to'        => '',
        'body_text'       => '',
        'body_html'       => '',
        'required_fields' => [],
        'include_logo'    => true,
        'include_footer'  => true,
        '_is_draft'       => true,
        'logo_url'        => ''
    ];
}

// Add CSV content to base template
function batchmailer_create_draft_with_csv(array $csvHeaders): array
{
    $draft = batchmailer_template_draft_base();

    $draft['csv_fields'] = array_map('strtolower', $csvHeaders);

    return $draft;
}
