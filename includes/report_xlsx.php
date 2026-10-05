<?php
declare(strict_types=1);

require_once __DIR__ . '/inventory_xlsx.php';   // shared ZIP / XML helpers of the built-in .xlsx writer (read-only use)

// Multi-sheet .xlsx for reports: each sheet = ['name' => string, 'columns' => [['label', 'width', 'type']], 'rows' => [...]].
// Text is written as inline strings (never formulas); numbers and money as numbers. Header rows are bold and frozen.
function report_xlsx_build(array $sheets): string
{
    $workbook_sheets = '';
    $rels = '';
    $overrides = '';
    $files = [];
    foreach (array_values($sheets) as $index => $sheet) {
        $n = $index + 1;
        $columns = $sheet['columns'];
        $last_column = inventory_xlsx_column(count($columns) - 1);
        $cols = '';
        foreach ($columns as $i => $column) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) $column['width'] . '" customWidth="1"/>';
        $xml_rows = '<row r="1">';
        foreach ($columns as $i => $column) $xml_rows .= '<c r="' . inventory_xlsx_column($i) . '1" t="inlineStr" s="1"><is><t>' . inventory_xlsx_xml($column['label']) . '</t></is></c>';
        $xml_rows .= '</row>';
        foreach (array_values($sheet['rows']) as $r => $row) {
            $number = $r + 2;
            $xml_rows .= '<row r="' . $number . '">';
            foreach ($columns as $i => $column) {
                $value = $row[$i] ?? null;
                if ($value === null || $value === '') continue;
                $ref = inventory_xlsx_column($i) . $number;
                if (in_array($column['type'], ['number', 'money'], true) && is_numeric($value)) $xml_rows .= '<c r="' . $ref . '"' . ($column['type'] === 'money' ? ' s="2"' : '') . '><v>' . (0 + $value) . '</v></c>';
                else $xml_rows .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . inventory_xlsx_xml((string) $value) . '</t></is></c>';
            }
            $xml_rows .= '</row>';
        }
        $last_row = max(1, count($sheet['rows']) + 1);
        $files["xl/worksheets/sheet$n.xml"] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><dimension ref="A1:' . $last_column . $last_row . '"/><sheetViews><sheetView workbookViewId="0"' . ($index === 0 ? ' tabSelected="1"' : '') . '><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>' . $cols . '</cols><sheetData>' . $xml_rows . '</sheetData></worksheet>';
        $name = inventory_xlsx_xml(mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', (string) $sheet['name']), 0, 31));
        $workbook_sheets .= '<sheet name="' . $name . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        $overrides .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    $style_rel = count($sheets) + 1;
    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . $overrides . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>',
        'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>SJQIBMS Report</dc:title><dc:creator>SJQIBMS</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created></cp:coreProperties>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $workbook_sheets . '</sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '<Relationship Id="rId' . $style_rel . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF153F35"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
    ] + $files;
    return inventory_zip($files);
}
