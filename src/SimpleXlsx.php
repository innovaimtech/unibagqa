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
            $columnKinds = [];

            $trs = $xpath->query('.//tr', $table);
            foreach ($trs as $tr) {
                $rowCells = [];
                $column = 0;
                $cells = $xpath->query('.//th | .//td', $tr);
                foreach ($cells as $cell) {
                    $val = trim($cell->textContent);
                    $isHeader = ($cell->nodeName === 'th');
                    $colspan = (int)$cell->getAttribute('colspan');
                    if ($colspan < 1) {
                        $colspan = 1;
                    }
                    $class = (string)$cell->getAttribute('class');

                    if ($isHeader && $colspan === 1) {
                        $columnKinds[$column] = self::columnKind($val);
                    }
                    $number = $isHeader ? null : self::cellNumber($val,$class,
                        $columnKinds[$column] ?? '',(string)$cell->getAttribute('style'),
                        (string)$cell->getAttribute('data-xlsx-type'),
                        $cell->hasAttribute('data-xlsx-value') ? $cell->getAttribute('data-xlsx-value') : null);

                    $rowCells[] = [
                        'val' => $val,
                        'is_header' => $isHeader,
                        'is_summary' => $isSummaryTable && $isHeader,
                        'colspan' => $colspan,
                        'is_num' => $number !== null,
                        'num_val' => $number['value'] ?? 0.0,
                        'num_format' => $number['format'] ?? 'int',
                    ];
                    $column += $colspan;
                }
                $rowsData[] = $rowCells;
            }
            $rowsData[] = []; // Separador entre tablas
        }

        return $rowsData;
    }

    private static function columnKind(string $header): string
    {
        $header = strtr(mb_strtolower($header),['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);
        if (preg_match('/\b(?:cantidad|cant|total|monto|precio|valor|bono|stock|peso|kg|horas|minutos|duracion|factor|mult|porcentaje|ocupacion|unidades|saldo|producci[oó]n)\b|%/u',$header)) {
            return 'number';
        }
        if (preg_match('/\b(?:sku|codigo|cod|rut|id|ot|oc|nv|cc|folio|barcode|ean|upc|serie|lote|documento|factura|guia|operador|cliente|articulo|producto|descripcion|observaciones|medidas|nombre|color|maquina|fecha|hora|turno|bodega|planta|unidad)\b|centro\s+(?:de\s+)?costos?|nota\s+venta/u',$header)) {
            return 'text';
        }
        return '';
    }

    /** Numbers are written without display symbols; currency/percent signs live in styles.
     * Los valores se guardan sin simbolos; moneda y porcentaje se aplican como formato. */
    private static function cellNumber(string $value,string $class,string $kind='',string $style='',string $type='',?string $raw=null): ?array
    {
        $classes = preg_split('/\s+/',trim($class)) ?: [];
        if ($type==='text' || in_array('text-cell',$classes,true) || preg_match('/mso-number-format\s*:[^;]*@/i',$style)) return null;
        $explicit = in_array($type,['number','currency','percentage'],true) || (bool)preg_match('/(?:^|\s)num-(?:int|dec[123]?|currency|percent)(?:\s|$)/',$class);
        if ($kind==='text' && !in_array($type,['number','currency','percentage'],true) && $raw===null) return null;
        $value = trim(str_replace(["\u{00A0}","\u{202F}","\u{2212}"],[' ',' ','-'],$value));
        if ($value==='' && $raw===null) return null;
        $currency = $type==='currency' || in_array('num-currency',$classes,true) || (bool)preg_match('/[$€£]|\b(?:CLP|USD|EUR|GBP)\b/u',$value);
        $currencyFormat = str_contains($value,'€') || preg_match('/\bEUR\b/',$value) ? 'eur'
            : (str_contains($value,'£') || preg_match('/\bGBP\b/',$value) ? 'gbp' : 'currency');
        $percent = $type==='percentage' || in_array('num-percent',$classes,true) || str_contains($value,'%');
        $decimalClass = (bool)preg_match('/(?:^|\s)num-dec[123]?(?:\s|$)/',$class);
        $clean = preg_replace('/US\$|[$€£%]|\b(?:CLP|USD|EUR|GBP)\b/u','',$value);
        $clean = trim((string)$clean);
        if ($currency && preg_match('/^[-–—]$/u',$clean)) return ['value'=>0.0,'format'=>$currencyFormat.'0'];
        if ($explicit || $kind==='number') {
            $clean = preg_replace('/\s*(?:kg|g|ton|unid\.?|unidades|uds|mts?|m|metros|mm|cm|hrs?|h|horas|min|minutos|m2|m²|m\/min)\s*$/iu','',$clean);
            if (preg_match('/^\s*x\s*/i',$clean)) $clean = preg_replace('/^\s*x\s*/i','',$clean);
        }
        $clean = trim((string)$clean);
        $negative = str_starts_with($clean,'(') && str_ends_with($clean,')');
        if ($negative) $clean = substr($clean,1,-1);
        $clean = str_replace(' ','',$clean);
        if ($raw!==null) {
            if (!preg_match('/^[+-]?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?$/',$raw)) return null;
            $canonical = $raw;
        } else {
            if (!preg_match('/^[+-]?\d[\d.,]*$/',$clean)) return null;
            // Ambiguous identifiers and long codes must not lose leading zeros or precision.
            // Los identificadores ambiguos conservan ceros y precision.
            if (!$explicit && $kind!=='number' && preg_match('/^0\d+$|^\d{16,}$/',$clean)) return null;
            $comma = str_contains($clean,',');$dot = str_contains($clean,'.');
            if ($comma && $dot) {
                $decimal = strrpos($clean,',')>strrpos($clean,'.') ? ',' : '.';
                $group = $decimal===',' ? '.' : ',';
                $parts = explode($decimal,$clean);
                if (count($parts)!==2 || !ctype_digit($parts[1])
                    || !self::validGroupedInteger($parts[0],$group)) return null;
                $canonical = str_replace($group,'',$parts[0]).'.'.$parts[1];
            } elseif ($comma) {
                if (substr_count($clean,',')>1 || (!$percent && ($currency || in_array('num-int',$classes,true))
                    && !$decimalClass && self::validGroupedInteger($clean,','))) {
                    if (!self::validGroupedInteger($clean,',')) return null;
                    $canonical = str_replace(',','',$clean);
                } else {
                    $canonical = str_replace(',','.',$clean);
                }
            } elseif ($dot && (!$decimalClass || $currency) && self::validGroupedInteger($clean,'.') && str_contains($clean,'.')) {
                $canonical = str_replace('.','',$clean);
            } else {
                $canonical = $clean;
            }
            if ($negative) $canonical = '-'.$canonical;
        }
        if (!is_numeric($canonical) || !is_finite((float)$canonical)) return null;
        $number = (float)$canonical;
        $decimals = str_contains($canonical,'.') ? strlen(explode('.',$canonical,2)[1]) : 0;
        if ($percent) return ['value'=>$raw!==null ? $number : $number/100,'format'=>$decimals>0?'percent2':'percent0'];
        if ($currency) return ['value'=>$number,'format'=>$currencyFormat.($decimals>0?'2':'0')];
        $format = in_array('num-int',$classes,true) ? 'int' : ($decimalClass
            ? (in_array('num-dec3',$classes,true) ? 'dec3' : (in_array('num-dec1',$classes,true) ? 'dec1' : 'dec2'))
            : ($decimals>0 ? ($decimals>2?'dec3':'dec2') : 'int'));
        return ['value'=>$number,'format'=>$format];
    }

    private static function validGroupedInteger(string $value,string $separator): bool
    {
        $separator = preg_quote($separator,'/');
        return (bool)preg_match('/^[+-]?(?:\d+|[1-9]\d{0,2}(?:'.$separator.'\d{3})+)$/',$value);
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
            foreach ($row as $index=>$val) {
                $isNum = false;
                $numVal = 0.0;
                $numFormat = 'int';
                $kind = self::columnKind((string)($headers[$index] ?? ''));

                if ($kind==='text') {
                    // IDs and SKUs remain text even when PDO supplies an integer.
                    // IDs y SKU siguen como texto incluso si PDO entrega un entero.
                } elseif (is_int($val)) {
                    $isNum = true;
                    $numVal = (float)$val;
                    $numFormat = 'int';
                } elseif (is_float($val) && is_finite($val)) {
                    $isNum = true;
                    $numVal = $val;
                    $numFormat = (floor($val) == $val) ? 'int' : 'dec2';
                } elseif (is_numeric($val) && !is_string($val) && is_finite((float)$val)) {
                    $isNum = true;
                    $numVal = (float)$val;
                    $numFormat = 'dec2';
                } elseif (is_string($val)) {
                    $raw = $kind==='number' && is_numeric($val) ? $val : null;
                    $number = self::cellNumber($val,'',$kind,'',$raw!==null?'number':'',$raw);
                    $isNum = $number!==null;
                    $numVal = $number['value'] ?? 0.0;
                    $numFormat = $number['format'] ?? 'int';
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
                        'currency0' => 8,
                        'currency2' => 9,
                        'percent0' => 10,
                        'percent2' => 11,
                        'eur0' => 12,
                        'eur2' => 13,
                        'gbp0' => 14,
                        'gbp2' => 15,
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
  <numFmts count="12">
    <numFmt numFmtId="164" formatCode="#,##0"/>
    <numFmt numFmtId="165" formatCode="#,##0.00"/>
    <numFmt numFmtId="166" formatCode="#,##0.000"/>
    <numFmt numFmtId="167" formatCode="&quot;$&quot; #,##0;[Red]&quot;$&quot; -#,##0;&quot;$&quot; -"/>
    <numFmt numFmtId="168" formatCode="&quot;$&quot; #,##0.00;[Red]&quot;$&quot; -#,##0.00;&quot;$&quot; -"/>
    <numFmt numFmtId="169" formatCode="0%"/>
    <numFmt numFmtId="170" formatCode="0.00%"/>
    <numFmt numFmtId="171" formatCode="#,##0.0"/>
    <numFmt numFmtId="180" formatCode="&quot;€&quot; #,##0"/>
    <numFmt numFmtId="181" formatCode="&quot;€&quot; #,##0.00"/>
    <numFmt numFmtId="182" formatCode="&quot;£&quot; #,##0"/>
    <numFmt numFmtId="183" formatCode="&quot;£&quot; #,##0.00"/>
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
  <cellXfs count="16">
    <!-- 0: Normal text -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>
    <!-- 1: Header (Dark BG, White Text, Bold, Centered) -->
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <!-- 2: Integer number -->
    <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <!-- 3: Dec1 number -->
    <xf numFmtId="171" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <!-- 4: Dec2 number -->
    <xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <!-- 5: Title big bold -->
    <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    <!-- 6: Dec3 number -->
    <xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <!-- 7: Summary header (Light slate BG, Bold, Left) -->
    <xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
    <xf numFmtId="167" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="168" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="169" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="170" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="180" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="181" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="182" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="183" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
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
