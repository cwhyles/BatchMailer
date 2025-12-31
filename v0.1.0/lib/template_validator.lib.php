<?php
/**
 * Functions in this file
 * 
 * function extract_placeholders(string $text): array
 * function validate_batchmailer_template(array $template): array
 * function extract_placeholders_from_template(array $template): array
 * 
 */
 
/**
 * Validate batch mailer template structure and content
 *
 * @param array $template
 * @return array ['errors' => [], 'warnings' => []]
 */

function extract_placeholders(string $text): array
{
    preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $text, $matches);
    return array_unique(array_map('strtolower', $matches[1]));
}
 
function validate_batchmailer_template(array $template): array
{
    $result = [
        'errors'   => [],
        'warnings' => []
    ];

    // NEW MODEL SUPPORT
    if (!empty($template['fields']) && is_array($template['fields'])) {
        $template['required_fields'] = [];
    
        foreach ($template['fields'] as $field => $cfg) {
            if (!empty($cfg['use']) && !empty($cfg['required'])) {
                $template['required_fields'][] = strtolower($field);
            }
        }
    }
    /*
     * REQUIRED STRUCTURE
     */
    $requiredKeys = [
        'name',
        'subject'
    ];

    foreach ($requiredKeys as $key) {
        if (empty($template[$key])) {
            $result['errors'][] = 'BatchMailerErrorMissing'.ucfirst($key);
        }
    }

    if (empty($template['fields']) || !is_array($template['fields'])) {
        $result['errors'][] = 'BatchMailerErrorNoFieldsDefined';
    }
    
    if (
        isset($template['fields']['email']) &&
        empty($template['fields']['email']['use'])
    ) {
        $result['errors'][] = 'BatchMailerErrorEmailFieldDisabled';
    }

    /*
     * EMAIL FIELDS
     */
    if (!empty($template['from_email']) &&
        !filter_var($template['from_email'], FILTER_VALIDATE_EMAIL)
    ) {
        $result['errors'][] = 'BatchMailerErrorInvalidFromEmail';
    }

    if (!empty($template['reply_to']) &&
        !filter_var($template['reply_to'], FILTER_VALIDATE_EMAIL)
    ) {
        $result['errors'][] = 'BatchMailerErrorInvalidReplyTo';
    }

    /*
     * MESSAGE BODIES
     */
    $hasText = !empty(trim($template['body_text'] ?? ''));
    $hasHtml = !empty(trim($template['body_html'] ?? ''));

    if (!$hasText && !$hasHtml) {
        $result['errors'][] = 'BatchMailerErrorNoMessageBody';
    }

    if ($hasHtml && !$hasText) {
        $result['warnings'][] = 'BatchMailerWarningNoPlainText';
    }

    if ($hasText && !$hasHtml) {
        $result['warnings'][] = 'BatchMailerWarningNoHtml';
    }

    /*
     * PLACEHOLDER ANALYSIS
     */
//    $placeholdersUsed = extract_placeholders_from_template($template);
        $placeholdersUsed = array_merge(
        extract_placeholders($template['subject'] ?? ''),
        extract_placeholders($template['body_text'] ?? ''),
        extract_placeholders($template['body_html'] ?? '')
    );

    $placeholdersUsed = array_unique($placeholdersUsed);

    if (!empty($template['required_fields']) && is_array($template['required_fields'])) {

        // Required but not used
        foreach ($template['required_fields'] as $field) {

            $field = strtolower($field);

            // Email is allowed to be implicit (recipient address)
            if ($field === 'email') {
                continue;
            }

            if (!in_array($field, $placeholdersUsed, true)) {
                $result['warnings'][] =
                    'BatchMailerWarningRequiredFieldNotUsedContent:'.$field;
            }
        }

        // Used but not declared
        foreach ($placeholdersUsed as $field) {
            if (!in_array($field, $template['required_fields'], true)) {
                $result['warnings'][] =
                    'BatchMailerWarningPlaceholderNotDeclared:'.$field;
            }
        }
    }

    return $result;
}

function extract_placeholders_from_template(array $template): array
{
    $content = '';

    if (!empty($template['subject'])) {
        $content .= ' '.$template['subject'];
    }
    if (!empty($template['body_text'])) {
        $content .= ' '.$template['body_text'];
    }
    if (!empty($template['body_html'])) {
        $content .= ' '.$template['body_html'];
    }

    preg_match_all('/{{\s*([a-z0-9_]+)\s*}}/i', $content, $matches);

    if (empty($matches[1])) {
        return [];
    }

    // Normalize to lowercase + unique
    return array_values(array_unique(array_map('strtolower', $matches[1])));
}
