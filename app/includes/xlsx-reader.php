<?php

/**
 * Simple XLSX reader using PHP native ZipArchive + SimpleXML.
 * No external dependencies required.
 */
function read_xlsx(string $filePath): array
{
    $sheets = [];
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return [];
    }

    // Read shared strings
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $ss = new SimpleXMLElement($ssXml);
        foreach ($ss->si as $si) {
            $text = '';
            if (isset($si->t)) {
                $text = (string) $si->t;
            } elseif (isset($si->r)) {
                foreach ($si->r as $r) {
                    $text .= (string) $r->t;
                }
            }
            $sharedStrings[] = $text;
        }
    }

    // Read workbook to get sheet names
    $wbXml = $zip->getFromName('xl/workbook.xml');
    if (!$wbXml) { $zip->close(); return []; }
    $wb = new SimpleXMLElement($wbXml);
    $sheetNames = [];
    foreach ($wb->sheets->sheet as $sheet) {
        $sheetNames[] = (string) $sheet['name'];
    }

    // Read each sheet
    foreach ($sheetNames as $index => $sheetName) {
        $sheetFile = 'xl/worksheets/sheet' . ($index + 1) . '.xml';
        $sheetXml = $zip->getFromName($sheetFile);
        if (!$sheetXml) continue;

        $xml = new SimpleXMLElement($sheetXml);
        $rows = [];
        if (!isset($xml->sheetData->row)) continue;

        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            $maxCol = 0;
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $colIndex = xlsx_col_to_index($ref);
                $maxCol = max($maxCol, $colIndex);
                $value = xlsx_cell_value($cell, $sharedStrings);
                $rowData[$colIndex] = $value;
            }
            // Fill gaps
            $filled = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $filled[] = $rowData[$i] ?? '';
            }
            $rows[] = $filled;
        }

        $sheets[$sheetName] = $rows;
    }

    $zip->close();
    return $sheets;
}

function xlsx_col_to_index(string $cellRef): int
{
    preg_match('/^([A-Z]+)/', $cellRef, $m);
    $letters = $m[1] ?? 'A';
    $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

function xlsx_cell_value(SimpleXMLElement $cell, array $sharedStrings)
{
    $type = (string) ($cell['t'] ?? '');
    $value = (string) ($cell->v ?? '');

    if ($type === 's' && isset($sharedStrings[(int) $value])) {
        return $sharedStrings[(int) $value];
    }
    if ($type === 'str' || $type === 'inlineStr') {
        // Inline string - value is directly in <v> or <is><t>
        if ($value !== '') return $value;
        if (isset($cell->is->t)) return (string) $cell->is->t;
        return '';
    }
    if ($type === 'b') {
        return $value === '1' ? 'Sim' : 'Nao';
    }
    if ($value === '') {
        return '';
    }
    return $value;
}

function xlsx_date_value($value): ?string
{
    if (!$value || $value === '') return null;
    // Already a date string (YYYY-MM-DD)
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $value)) {
        return substr((string) $value, 0, 10);
    }
    // Excel serial date number
    if (is_numeric($value)) {
        $serial = (int) $value;
        if ($serial > 25000 && $serial < 60000) {
            $unix = ($serial - 25569) * 86400;
            return date('Y-m-d', $unix);
        }
    }
    // Try parsing
    $t = strtotime((string) $value);
    return $t ? date('Y-m-d', $t) : null;
}

function xlsx_number_value($value): float
{
    if (is_numeric($value)) return (float) $value;
    $clean = str_replace(['R$', ' ', '.'], '', (string) $value);
    $clean = str_replace(',', '.', $clean);
    return (float) $clean;
}

function xlsx_to_assoc(array $rows): array
{
    if (count($rows) < 2) return [];
    $headers = array_map('trim', $rows[0]);
    $data = [];
    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (implode('', $row) === '') continue;
        $assoc = [];
        foreach ($headers as $j => $header) {
            if ($header === '') continue;
            $assoc[$header] = $row[$j] ?? '';
        }
        $data[] = $assoc;
    }
    return $data;
}
