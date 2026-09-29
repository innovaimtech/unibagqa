<?php

declare(strict_types=1);

/**
 * Generador nativo OpenXML Spreadsheet (.xlsx) sin dependencias externas.
 *
 * Resuelve la advertencia de Microsoft Excel:
 * "El formato y la extensión de archivo no coinciden. Puede que el archivo esté dañado o no sea seguro..."
 * producida cuando se descargan archivos con extensión .xls que en realidad contienen HTML.
 *
 * Al generar un archivo ZIP OpenXML (.xlsx) real, Microsoft Excel lo abre de forma nativa
 * sin ninguna alerta de seguridad ni discrepancia de formato.
 */
class SimpleXlsx
{
    /**
     * Convierte una estructura HTML (títulos, metadatos y tablas) a un archivo XLSX nativo.
     */
    /**
     * Extrae una estructura de filas y celdas a partir de un HTML.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function parseHtmlToRowsData(string $html): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $rowsData = [];
        $xpath = new DOMXPath($dom);

        // Extraer títulos
        $titles = $xpath->query('//div[contains(@class, "title")] | //h1 | //h2');
        foreach ($titles as $t) {
            $text = trim($t->textContent);
            if ($text !== '') {
                $rowsData[] = [
                    ['val' => $text, 'is_header' => false, 'is_title' => true, 'colspan' => 1, 'is_num' => false]
                ];
            }
        }

        // Extraer subtítulos y metadatos
        $subs = $xpath->query('//div[contains(@class, "sub")] | //div[contains(@class, "meta")]');
        foreach ($subs as $s) {
            $text = trim(preg_replace('/\s+/', ' ', $s->textContent));
            if ($text !== '') {
                $rowsData[] = [
                    ['val' => $text, 'is_header' => false, 'is_meta' => true, 'colspan' => 1, 'is_num' => false]
                ];
            }
        }

        if (!empty($rowsData)) {
            $rowsData[] = []; // Separador
        }

        // Extraer tablas y secciones
        $tables = $xpath->query('//table');
        foreach ($tables as $table) {
            // Verificar si hay un encabezado de sección previo a esta tabla
            $prev = $table->previousSibling;
            while ($prev !== null && ($prev->nodeType === XML_TEXT_NODE || $prev->nodeType === XML_COMMENT_NODE)) {
                $prev = $prev->previousSibling;
            }
            if ($prev !== null && $prev instanceof DOMElement) {
                $prevClass = (string)$prev->getAttribute('class');
                if (str_contains($prevClass, 'section') || in_array(strtolower($prev->nodeName), ['h2', 'h3', 'h4'], true)) {
                    $secText = trim($prev->textContent);
                    if ($secText !== '') {
                        $rowsData[] = [
                            ['val' => $secText, 'is_header' => false, 'is_title' => true, 'colspan' => 1, 'is_num' => false]
                        ];
                    }
                }
            }

            $tableClass = (string)$table->getAttribute('class');
            $isSummaryTable = str_contains($tableClass, 'summary');

            $trs = $xpath->query('.//tr', $table);
            foreach ($trs as $tr) {
                $rowCells = [];
                $cells = $xpath->query('.//th | .//td', $tr);
                foreach ($cells as $cell) {
                    $val = trim($cell->textContent);
                    $isHeader = ($cell->nodeName === 'th');
                    $colspan = (int)$cell->getAttribute('colspan');
                    if ($colspan < 1) {
                        $colspan = 1;
                    }
                    $class = (string)$cell->getAttribute('class');

                    $isNum = false;
                    $numVal = 0.0;
                    $numFormat = 'int';

                    if (str_contains($class, 'num-int')) {
                        $isNum = true;
                        $clean = str_replace(['.', ' ', '$'], '', $val);
                        $clean = str_replace(',', '.', $clean);
                        $numVal = (float)$clean;
                        $numFormat = 'int';
                    } elseif (str_contains($class, 'num-dec') || str_contains($class, 'num-dec1') || str_contains($class, 'num-dec3')) {
                        $isNum = true;
                        $clean = str_replace([' ', '$'], '', $val);
                        if (preg_match('/\.\d{3}/', $clean) && str_contains($clean, ',')) {
                            $clean = str_replace('.', '', $clean);
                            $clean = str_replace(',', '.', $clean);
                        } else {
                            $clean = str_replace(',', '.', $clean);
                        }
                        $clean = preg_replace('/[^0-9.-]/', '', $clean);
                        $numVal = (float)$clean;
                        $numFormat = str_contains($class, 'num-dec3') ? 'dec3' : (str_contains($class, 'num-dec1') ? 'dec1' : 'dec2');
                    } elseif (!$isHeader && !str_contains($class, 'text-cell') && preg_match('/^-?[\d.,]+$/', $val)) {
                        // Si tiene ceros a la izquierda (ej: 00123), mantener como texto
                        if (strlen($val) > 1 && $val[0] === '0' && ctype_digit($val)) {
                            $isNum = false;
                        } else {
                            $clean = str_replace(['.'], '', $val);
                            $clean = str_replace(',', '.', $clean);
                            if (is_numeric($clean)) {
                                $isNum = true;
                                $numVal = (float)$clean;
                                $numFormat = str_contains($val, ',') || str_contains($val, '.') ? 'dec2' : 'int';
                            }
                        }
                    }

                    $rowCells[] = [
                        'val' => $val,
                        'is_header' => $isHeader,
                        'is_summary' => $isSummaryTable && $isHeader,
                        'colspan' => $colspan,
                        'is_num' => $isNum,
                        'num_val' => $numVal,
                        'num_format' => $numFormat,
                    ];
                }
                $rowsData[] = $rowCells;
            }
            $rowsData[] = []; // Separador entre tablas
        }

        return $rowsData;
    }

    /**
     * Convierte una estructura HTML (títulos, metadatos y tablas) a un archivo XLSX nativo.
     */
    public static function fromHtml(string $html, ?string $sheetTitle = 'Informe'): string
    {
        $rowsData = self::parseHtmlToRowsData($html);
        return self::buildMultiSheetZip([['title' => $sheetTitle ?: 'Informe', 'rows' => $rowsData]]);
    }

    /**
     * Convierte múltiples estructuras HTML a un archivo XLSX con múltiples hojas.
     *
     * @param array<int, array{title:string, html:string}>|array<string, string> $sheets
     */
    public static function fromHtmlSheets(array $sheets): string
    {
        $sheetsData = [];
        foreach ($sheets as $k => $v) {
            if (is_array($v) && isset($v['html'])) {
                $title = (string)($v['title'] ?? ('Hoja ' . (count($sheetsData) + 1)));
                $html = (string)$v['html'];
            } else {
                $title = is_string($k) ? $k : ('Hoja ' . (count($sheetsData) + 1));
                $html = (string)$v;
            }
            $sheetsData[] = [
                'title' => $title,
                'rows' => self::parseHtmlToRowsData($html),
            ];
        }
        return self::buildMultiSheetZip($sheetsData);
    }

    /**
     * Construye un XLSX directamente a partir de un arreglo de encabezados y filas.
     *
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows
     */
    public static function fromArray(array $headers, array $rows, ?string $title = null, array $meta = [], ?string $sheetTitle = 'Informe'): string
    {
        $rowsData = [];
        if ($title !== null && $title !== '') {
            $rowsData[] = [['val' => $title, 'is_header' => false, 'is_title' => true, 'colspan' => 1, 'is_num' => false]];
        }
        foreach ($meta as $m) {
            $rowsData[] = [['val' => (string)$m, 'is_header' => false, 'is_meta' => true, 'colspan' => 1, 'is_num' => false]];
        }
        if (!empty($rowsData)) {
            $rowsData[] = [];
        }

        // Encabezados
        $headerRow = [];
        foreach ($headers as $h) {
            $headerRow[] = ['val' => (string)$h, 'is_header' => true, 'colspan' => 1, 'is_num' => false];
        }
        $rowsData[] = $headerRow;

        // Filas de datos
        foreach ($rows as $row) {
            $rowCells = [];
            foreach ($row as $val) {
                $isNum = false;
                $numVal = 0.0;
                $numFormat = 'int';

                if (is_int($val)) {
                    $isNum = true;
                    $numVal = (float)$val;
                    $numFormat = 'int';
                } elseif (is_float($val)) {
                    $isNum = true;
                    $numVal = $val;
                    $numFormat = (floor($val) == $val) ? 'int' : 'dec2';
                } elseif (is_numeric($val) && !is_string($val)) {
                    $isNum = true;
                    $numVal = (float)$val;
                    $numFormat = 'dec2';
                }

                $rowCells[] = [
                    'val' => (string)$val,
                    'is_header' => false,
                    'colspan' => 1,
                    'is_num' => $isNum,
                    'num_val' => $numVal,
                    'num_format' => $numFormat,
                ];
            }
            $rowsData[] = $rowCells;
        }

        return self::buildZip($rowsData, $sheetTitle ?: 'Informe');
    }

    /**
     * Construye un archivo XLSX multi-hoja a partir de un arreglo de hojas con sus filas.
     *
     * @param list<array{title:string, rows:array}> $sheetsData
     */
    public static function buildMultiSheetZip(array $sheetsData): string
    {
        if (empty($sheetsData)) {
            $sheetsData = [['title' => 'Informe', 'rows' => []]];
        }

        $sheetXmls = [];
        $sheetDefs = [];
        $workbookRels = [];
        $sheetOverrides = '';

        $relId = 1;
        foreach ($sheetsData as $idx => $s) {
            $sheetNum = $idx + 1;
            $title = (string)($s['title'] ?? ('Hoja ' . $sheetNum));
            $safeTitle = htmlspecialchars(mb_substr($title, 0, 31), ENT_XML1, 'UTF-8');
            $rowsData = (array)($s['rows'] ?? []);

            $colWidths = [];
            foreach ($rowsData as $row) {
                $c = 1;
                foreach ($row as $cell) {
                    $colspan = (int)($cell['colspan'] ?? 1);
                    if ($colspan === 1) {
                        $len = mb_strlen((string)($cell['val'] ?? ''));
                        $colWidths[$c] = max($colWidths[$c] ?? 10, min(50, $len + 3));
                    }
                    $c += $colspan;
                }
            }

            $sheetXml = self::buildSheetXml($rowsData, $colWidths);
            $sheetXmls["xl/worksheets/sheet{$sheetNum}.xml"] = $sheetXml;

            $sheetDefs[] = '<sheet name="' . $safeTitle . '" sheetId="' . $sheetNum . '" r:id="rId' . $relId . '"/>';
            $workbookRels[] = '<Relationship Id="rId' . $relId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetNum . '.xml"/>';
            $sheetOverrides .= '<Override PartName="/xl/worksheets/sheet' . $sheetNum . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\n  ";
            $relId++;
        }

        $stylesRelId = 'rId' . $relId;
        $workbookRels[] = '<Relationship Id="' . $stylesRelId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    ' . implode("\n    ", $sheetDefs) . '
  </sheets>
</workbook>';

        $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  ' . implode("\n  ", $workbookRels) . '
</Relationships>';

        $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  ' . $sheetOverrides . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

        $stylesXml = self::buildStylesXml();
        $rootRelsXml = self::buildRootRels();

        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new ZipArchive();
        $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $contentTypesXml);
        $zip->addFromString('_rels/.rels', $rootRelsXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/styles.xml', $stylesXml);
        foreach ($sheetXmls as $path => $xml) {
            $zip->addFromString($path, $xml);
        }

        $zip->close();
        $binary = file_get_contents($tempFile);
        @unlink($tempFile);

        return $binary !== false ? $binary : '';
    }

    /**
     * Construye el archivo ZIP con la especificación completa OpenXML (.xlsx).
     */
    public static function buildZip(array $rowsData, string $sheetTitle = 'Informe'): string
    {
        return self::buildMultiSheetZip([['title' => $sheetTitle, 'rows' => $rowsData]]);
    }

    private static function colLetter(int $colIndex): string
    {
        $letter = '';
        while ($colIndex > 0) {
            $m = ($colIndex - 1) % 26;
            $letter = chr(65 + $m) . $letter;
            $colIndex = (int)(($colIndex - $m) / 26);
        }
        return $letter;
    }

    private static function buildSheetXml(array $rowsData, array $colWidths): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="20"/>';

        // Columnas con ancho optimizado
        if (!empty($colWidths)) {
            $xml .= '<cols>';
            foreach ($colWidths as $colNum => $w) {
                $xml .= '<col min="' . $colNum . '" max="' . $colNum . '" width="' . max(10, $w) . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';

        $merges = [];
        $rIdx = 1;
        foreach ($rowsData as $row) {
            if (empty($row)) {
                $rIdx++;
                continue;
            }
            $xml .= '<row r="' . $rIdx . '">';
            $cIdx = 1;
            foreach ($row as $cell) {
                $colLetter = self::colLetter($cIdx);
                $cellRef = $colLetter . $rIdx;
                $colspan = (int)($cell['colspan'] ?? 1);
                if ($colspan > 1) {
                    $endCol = self::colLetter($cIdx + $colspan - 1);
                    $merges[] = $cellRef . ':' . $endCol . $rIdx;
                }

                $styleId = 0;
                if (!empty($cell['is_title'])) {
                    $styleId = 5;
                } elseif (!empty($cell['is_summary'])) {
                    $styleId = 7;
                } elseif (!empty($cell['is_header'])) {
                    $styleId = 1;
                } elseif (!empty($cell['is_num'])) {
                    $fmt = $cell['num_format'] ?? 'int';
                    $styleId = match ($fmt) {
                        'dec1' => 3,
                        'dec2' => 4,
                        'dec3' => 6,
                        default => 2,
                    };
                }

                if (!empty($cell['is_num'])) {
                    $xml .= '<c r="' . $cellRef . '" s="' . $styleId . '">';
                    $xml .= '<v>' . (float)$cell['num_val'] . '</v>';
                    $xml .= '</c>';
                } else {
                    $val = htmlspecialchars((string)$cell['val'], ENT_XML1, 'UTF-8');
                    $xml .= '<c r="' . $cellRef . '" s="' . $styleId . '" t="inlineStr">';
                    $xml .= '<is><t>' . $val . '</t></is>';
                    $xml .= '</c>';
                }

                $cIdx += $colspan;
            }
            $xml .= '</row>';
            $rIdx++;
        }

        $xml .= '</sheetData>';
        if (!empty($merges)) {
            $xml .= '<mergeCells count="' . count($merges) . '">';
            foreach ($merges as $m) {
                $xml .= '<mergeCell ref="' . $m . '"/>';
            }
            $xml .= '</mergeCells>';
        }
        $xml .= '</worksheet>';
        return $xml;
    }

    private static function buildStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <numFmts count="3">
    <numFmt numFmtId="164" formatCode="#,##0"/>
    <numFmt numFmtId="165" formatCode="#,##0.00"/>
    <numFmt numFmtId="166" formatCode="#,##0.000"/>
  </numFmts>
  <fonts count="4">
    <!-- 0: Normal 11pt -->
    <font><sz val="11"/><name val="Calibri"/></font>
    <!-- 1: Bold White 11pt -->
    <font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>
    <!-- 2: Bold Big 14pt -->
    <font><b/><sz val="14"/><name val="Calibri"/></font>
    <!-- 3: Bold Dark 11pt -->
    <font><b/><color rgb="FF0F172A"/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="4">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <!-- 2: Dark Navy #0F172A -->
    <fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/></patternFill></fill>
    <!-- 3: Light Slate #F1F5F9 -->
    <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/></border>
    <border>
      <left style="thin"><color rgb="FFCBD5E1"/></left>
      <right style="thin"><color rgb="FFCBD5E1"/></right>
      <top style="thin"><color rgb="FFCBD5E1"/></top>
      <bottom style="thin"><color rgb="FFCBD5E1"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="8">
    <!-- 0: Normal text -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>
    <!-- 1: Header (Dark BG, White Text, Bold, Centered) -->
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 2: Integer number -->
    <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <!-- 3: Dec1 number -->
    <xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <!-- 4: Dec2 number -->
    <xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <!-- 5: Title big bold -->
    <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    <!-- 6: Dec3 number -->
    <xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <!-- 7: Summary header (Light slate BG, Bold, Left) -->
    <xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
  </cellXfs>
</styleSheet>';
    }

    private static function buildWorkbookXml(string $sheetTitle): string
    {
        $safeTitle = htmlspecialchars(mb_substr($sheetTitle, 0, 31), ENT_XML1, 'UTF-8');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="' . $safeTitle . '" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
    }

    private static function buildWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    }

    private static function buildContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
    }

    private static function buildRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    /**
     * Envía las cabeceras HTTP y el binario XLSX para descarga directa.
     */
    public static function stream(string $filename, string $binary): void
    {
        // Reemplazar extensión .xls por .xlsx para evitar discrepancia de formato en Excel
        if (str_ends_with(strtolower($filename), '.xls')) {
            $filename = substr($filename, 0, -4) . '.xlsx';
        } elseif (!str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($binary));
        header('Cache-Control: max-age=0, no-cache, must-revalidate');
        header('Pragma: public');
        echo $binary;
        exit;
    }

    /**
     * Convierte un HTML a XLSX y lo transmite al cliente.
     */
    public static function streamHtml(string $filename, string $html, ?string $sheetTitle = 'Informe'): void
    {
        $binary = self::fromHtml($html, $sheetTitle);
        self::stream($filename, $binary);
    }

    /**
     * Convierte múltiples estructuras HTML a un XLSX multi-hoja y lo transmite al cliente.
     *
     * @param array<int, array{title:string, html:string}>|array<string, string> $sheets
     */
    public static function streamHtmlSheets(string $filename, array $sheets): void
    {
        $binary = self::fromHtmlSheets($sheets);
        self::stream($filename, $binary);
    }
}

/**
 * Función global auxiliar para enviar reportes HTML como hojas de cálculo XLSX nativas.
 */
function unibagSendExcelHtml(string $filename, string $html, ?string $sheetTitle = 'Informe'): void
{
    SimpleXlsx::streamHtml($filename, $html, $sheetTitle);
}
