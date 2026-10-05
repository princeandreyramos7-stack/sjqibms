<?php
declare(strict_types=1);

// Minimal .xlsx (Office Open XML spreadsheet) writer for the Inventory export — no external library and no ZipArchive.
// Produces one worksheet with a bold, frozen header row, an AutoFilter and column widths. Text is written as inline
// strings (never as formulas), so cell values cannot run as spreadsheet formulas. The ZIP container is built here with
// deflate (zlib) when available, otherwise stored.

function inventory_xlsx_column(int $index): string
{
    $name = '';
    for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) $name = chr(65 + ($n - 1) % 26) . $name;
    return $name;
}

function inventory_xlsx_xml(string $value): string
{
    // Remove characters that are not allowed in XML 1.0, then escape.
    $value = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// $columns: list of ['label' => string, 'width' => float, 'type' => 'text'|'number'|'money'].
// $rows: list of lists (same order as $columns). Returns the .xlsx file contents.
function inventory_xlsx_build(string $sheet_name, array $columns, array $rows): string
{
    $last_column = inventory_xlsx_column(count($columns) - 1);
    $last_row = count($rows) + 1;
    $cols = '';
    foreach ($columns as $i => $column) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) $column['width'] . '" customWidth="1"/>';
    $sheet_rows = '<row r="1">';
    foreach ($columns as $i => $column) $sheet_rows .= '<c r="' . inventory_xlsx_column($i) . '1" t="inlineStr" s="1"><is><t>' . inventory_xlsx_xml($column['label']) . '</t></is></c>';
    $sheet_rows .= '</row>';
    foreach ($rows as $r => $row) {
        $number = $r + 2;
        $sheet_rows .= '<row r="' . $number . '">';
        foreach ($columns as $i => $column) {
            $value = $row[$i] ?? null;
            if ($value === null || $value === '') continue;
            $ref = inventory_xlsx_column($i) . $number;
            if (in_array($column['type'], ['number', 'money'], true) && is_numeric($value)) {
                $sheet_rows .= '<c r="' . $ref . '"' . ($column['type'] === 'money' ? ' s="2"' : '') . '><v>' . (0 + $value) . '</v></c>';
            } else {
                $sheet_rows .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . inventory_xlsx_xml((string) $value) . '</t></is></c>';
            }
        }
        $sheet_rows .= '</row>';
    }
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<dimension ref="A1:' . $last_column . $last_row . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<cols>' . $cols . '</cols><sheetData>' . $sheet_rows . '</sheetData>'
        . '<autoFilter ref="A1:' . $last_column . $last_row . '"/></worksheet>';
    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>',
        'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . inventory_xlsx_xml($sheet_name) . '</dc:title><dc:creator>SJQIBMS</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created></cp:coreProperties>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . inventory_xlsx_xml(mb_substr($sheet_name, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . inventory_xlsx_xml(mb_substr($sheet_name, 0, 31)) . '\'!$A$1:$' . $last_column . '$' . $last_row . '</definedName></definedNames></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF153F35"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
        'xl/worksheets/sheet1.xml' => $sheet,
    ];
    return inventory_zip($files);
}

// Builds a ZIP archive from [path => contents].
function inventory_zip(array $files): string
{
    $data = '';
    $directory = '';
    $time = getdate();
    $dos_time = ($time['hours'] << 11) | ($time['minutes'] << 5) | intdiv($time['seconds'], 2);
    $dos_date = (($time['year'] - 1980) << 9) | ($time['mon'] << 5) | $time['mday'];
    foreach ($files as $name => $contents) {
        $crc = (int) hexdec(hash('crc32b', $contents));
        $deflated = function_exists('gzdeflate') ? gzdeflate($contents, 6) : false;
        $method = $deflated !== false ? 8 : 0;
        $stored = $deflated !== false ? $deflated : $contents;
        $offset = strlen($data);
        $header = pack('vvvvvVVVvv', 20, 0x0800, $method, $dos_time, $dos_date, $crc, strlen($stored), strlen($contents), strlen($name), 0);
        $data .= "PK\x03\x04" . $header . $name . $stored;
        $directory .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0x0800, $method, $dos_time, $dos_date, $crc, strlen($stored), strlen($contents), strlen($name), 0, 0, 0, 0, 32, $offset) . $name;
    }
    return $data . $directory . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($files), count($files), strlen($directory), strlen($data), 0);
}
