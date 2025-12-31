<?php

require_once DOL_DOCUMENT_ROOT.'/custom/batchmailer/lib/batchmailer_email_render.lib.php';

/*
 * Functions in this file:
 *
 * function read_csv_header(string $csvFile): array
 * function get_recipients_from_csv(string $csvFile): array
 * function analyze_csv_for_preview(string $csvFile, int $maxRows): array
 *
 */

if (!function_exists('read_csv_header')) {
    function read_csv_header(string $csvFile): array
    {
        if (!file_exists($csvFile) || !is_readable($csvFile)) {
            return [];
        }
    
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            return [];
        }
    
        $header = fgetcsv($handle);
        fclose($handle);
    
        if (!is_array($header)) {
            return [];
        }
    
        $clean = [];
        foreach ($header as $col) {
            if (!is_string($col)) continue;
            $col = trim($col);
            $col = preg_replace('/^\xEF\xBB\xBF/', '', $col); // strip BOM
            if ($col !== '') {
                $clean[] = strtolower($col);
            }
        }
    
        return array_unique($clean);
    }
}

/*
 * CSV LOADING (for sending & status)
 * -------------------------------------------------- */

function get_recipients_from_csv(string $csvFile): array
{
    $rows = [];

    if (!file_exists($csvFile)) {
        return $rows;
    }

    if (($handle = fopen($csvFile, "r")) === false) {
        return $rows;
    }

    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        return $rows;
    }

    $map = [];
    foreach ($header as $index => $name) {
        $key = strtolower(trim($name));
        $map[$key] = $index;
    }

    $required = ['forename','surname','email','url'];
    foreach ($required as $key) {
        if (!isset($map[$key])) {
            fclose($handle);
            return [];
        }
    }

    while (($data = fgetcsv($handle)) !== false) {
        $email = $data[$map['email']] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

        $rows[] = [
            'forename' => $data[$map['forename']] ?? '',
            'surname'  => $data[$map['surname']]  ?? '',
            'email'    => $email,
            'url'      => $data[$map['url']]      ?? '',
        ];
    }

    fclose($handle);
    return $rows;
}

/*
 * CSV ANALYSIS FOR PREVIEW
 * -------------------------------------------------- */

function analyze_csv_for_preview(string $csvFile, int $maxRows): array
{
    $result = [
        'exists'          => false,
        'header'          => [],
        'missing'         => [],
        'total_rows'      => 0,
        'rows'            => [],   // first N rows
        'invalid_emails'  => [],
        'duplicate_emails'=> [],
        'empty_urls'      => 0,
        'error'           => '',
        'email'           => ''
    ];

    if (!file_exists($csvFile)) {
        $result['error'] = 'CSV file not found.';
        return $result;
    }
    $result['exists'] = true;

    if (($handle = fopen($csvFile, 'r')) === false) {
        $result['error'] = 'Unable to open CSV file.';
        return $result;
    }

    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        $result['error'] = 'CSV appears to be empty.';
        return $result;
    }

    $result['header'] = $header;

    $map = [];
    foreach ($header as $index => $name) {
//        $key = strtolower(trim($name));
        $key = batchmailer_normalize_field($name);
        $map[$key] = $index;
    }

    $required = ['email'];
    
    if (!isset($map['email'])) {
        $result['missing'][] = 'email';
    }
    foreach ($required as $key) {
        if (!isset($map[$key])) {
            $result['missing'][] = $key;
        }
    }

    $emailCounts = [];
    $rowIndex = 0;

    while (($data = fgetcsv($handle)) !== false) {
        $rowIndex++;
        $result['total_rows']++;

        $row = [];
        foreach ($map as $key => $index) {
            $row[$key] = $data[$index] ?? '';
        }
        $email = $row['email'];
        if ($email !== '') {
            $emailCounts[$email] = ($emailCounts[$email] ?? 0) + 1;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result['invalid_emails'][$email] = true;
            }
        }

        if (count($result['rows']) < $maxRows) {
            $result['rows'][] = $row;
        }
    }

    fclose($handle);

    foreach ($emailCounts as $email => $count) {
        if ($count > 1) {
            $result['duplicate_emails'][$email] = $count;
        }
    }

    return $result;
}

