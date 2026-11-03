<?php

declare(strict_types=1);

/**
 * Generador mínimo de documentos Word (.docx, OOXML) sin dependencias: solo ZipArchive.
 *
 * Soporta: portada/título, encabezados, párrafos, listas, tablas con encabezado y filas alternadas,
 * fichas de datos (etiqueta/valor), recuadros de aviso, imágenes, saltos de página,
 * encabezado y pie con numeración "Página X de Y". Tamaño A4 vertical.
 */
final class DocxBuilder
{
    private const TEAL = '00A9A6';
    private const TEAL_DARK = '0F766E';
    private const INK = '0F172A';
    private const MUTED = '64748B';
    private const LINE = 'CBD5E1';
    private const USABLE_TWIPS = 9638; // A4 (11906) - márgenes de 1134 a cada lado

    private string $body = '';
    /** @var array<int,array{name:string,data:string,rid:string,ext:string}> */
    private array $images = [];
    private int $imageSeq = 0;

    public function __construct(private string $title, private string $headerText, private string $footerText = '')
    {
    }

    // ------------------------------------------------------------------ utilidades

    private static function esc(string $text): string
    {
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** @param array{bold?:bool,italic?:bool,color?:string,size?:int,caps?:bool} $o */
    private function run(string $text, array $o = []): string
    {
        $pr = '';
        if (!empty($o['bold'])) {
            $pr .= '<w:b/><w:bCs/>';
        }
        if (!empty($o['italic'])) {
            $pr .= '<w:i/>';
        }
        if (!empty($o['caps'])) {
            $pr .= '<w:caps/>';
        }
        if (!empty($o['color'])) {
            $pr .= '<w:color w:val="' . $o['color'] . '"/>';
        }
        if (!empty($o['size'])) {
            $pr .= '<w:sz w:val="' . ((int)$o['size'] * 2) . '"/><w:szCs w:val="' . ((int)$o['size'] * 2) . '"/>';
        }
        $parts = preg_split('/\R/u', $text) ?: [''];
        $xml = '';
        foreach ($parts as $i => $part) {
            if ($i > 0) {
                $xml .= '<w:r>' . ($pr !== '' ? '<w:rPr>' . $pr . '</w:rPr>' : '') . '<w:br/></w:r>';
            }
            $xml .= '<w:r>' . ($pr !== '' ? '<w:rPr>' . $pr . '</w:rPr>' : '') . '<w:t xml:space="preserve">' . self::esc($part) . '</w:t></w:r>';
        }
        return $xml;
    }

    /** @param array{bold?:bool,italic?:bool,color?:string,size?:int,align?:string,before?:int,after?:int,style?:string,keepNext?:bool} $o */
    private function para(string $runs, array $o = []): string
    {
        $pPr = '';
        if (!empty($o['style'])) {
            $pPr .= '<w:pStyle w:val="' . $o['style'] . '"/>';
        }
        if (!empty($o['keepNext'])) {
            $pPr .= '<w:keepNext/>';
        }
        $pPr .= '<w:spacing w:before="' . (int)($o['before'] ?? 0) . '" w:after="' . (int)($o['after'] ?? 80) . '"/>';
        if (!empty($o['align'])) {
            $pPr .= '<w:jc w:val="' . $o['align'] . '"/>';
        }
        return '<w:p><w:pPr>' . $pPr . '</w:pPr>' . $runs . '</w:p>';
    }

    // ------------------------------------------------------------------ bloques

    public function cover(string $title, string $subtitle, array $facts = []): self
    {
        $this->body .= $this->para($this->run('UNIBAG', ['bold' => true, 'color' => self::TEAL, 'size' => 11]), ['after' => 40]);
        $this->body .= $this->para($this->run($title, ['bold' => true, 'color' => self::INK, 'size' => 24]), ['after' => 60, 'style' => 'Title']);
        if ($subtitle !== '') {
            $this->body .= $this->para($this->run($subtitle, ['color' => self::MUTED, 'size' => 12]), ['after' => 160]);
        }
        if ($facts !== []) {
            $this->kv($facts, 2);
        }
        return $this;
    }

    public function heading(string $text, int $level = 1): self
    {
        $level = max(1, min(3, $level));
        $this->body .= $this->para($this->run($text), ['style' => 'Heading' . $level, 'keepNext' => true, 'before' => $level === 1 ? 280 : 160, 'after' => 100]);
        return $this;
    }

    /** @param array{bold?:bool,italic?:bool,color?:string,size?:int,align?:string} $o */
    public function text(string $text, array $o = []): self
    {
        $this->body .= $this->para($this->run($text, $o), ['align' => $o['align'] ?? null]);
        return $this;
    }

    /** @param array<int,string> $items */
    public function bullets(array $items): self
    {
        foreach ($items as $item) {
            $this->body .= '<w:p><w:pPr><w:pStyle w:val="ListBullet"/><w:spacing w:after="40"/></w:pPr>' . $this->run($item) . '</w:p>';
        }
        return $this;
    }

    public function pageBreak(): self
    {
        $this->body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
        return $this;
    }

    public function spacer(int $twips = 120): self
    {
        $this->body .= '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="' . $twips . '" w:lineRule="exact"/></w:pPr></w:p>';
        return $this;
    }

    /** Recuadro de aviso (tabla de una celda con barra de color a la izquierda). */
    public function callout(string $title, string $text, string $color = self::TEAL, string $fill = 'F0FDFA'): self
    {
        $inner = $this->para($this->run($title, ['bold' => true, 'color' => $color, 'size' => 10, 'caps' => true]), ['after' => 40])
            . $this->para($this->run($text, ['color' => self::INK, 'size' => 11]), ['after' => 0]);
        $this->body .= '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>'
            . '<w:top w:val="single" w:sz="4" w:space="0" w:color="' . self::LINE . '"/>'
            . '<w:left w:val="single" w:sz="36" w:space="0" w:color="' . $color . '"/>'
            . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="' . self::LINE . '"/>'
            . '<w:right w:val="single" w:sz="4" w:space="0" w:color="' . self::LINE . '"/>'
            . '</w:tblBorders><w:tblCellMar><w:top w:w="100" w:type="dxa"/><w:left w:w="160" w:type="dxa"/><w:bottom w:w="100" w:type="dxa"/><w:right w:w="160" w:type="dxa"/></w:tblCellMar></w:tblPr>'
            . '<w:tblGrid><w:gridCol w:w="' . self::USABLE_TWIPS . '"/></w:tblGrid><w:tr><w:tc><w:tcPr><w:tcW w:w="5000" w:type="pct"/><w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/></w:tcPr>'
            . $inner . '</w:tc></w:tr></w:tbl>' . $this->para('', ['after' => 80]);
        return $this;
    }

    /**
     * Ficha de datos: pares etiqueta/valor en $columns columnas.
     *
     * @param array<int,array{0:string,1:string}>|array<string,string> $pairs
     */
    public function kv(array $pairs, int $columns = 2): self
    {
        $list = [];
        foreach ($pairs as $k => $v) {
            $list[] = is_array($v) ? [(string)$v[0], (string)$v[1]] : [(string)$k, (string)$v];
        }
        if ($list === []) {
            return $this;
        }
        $columns = max(1, min(3, $columns));
        $labelW = (int)round(self::USABLE_TWIPS * 0.18 / ($columns / 2));
        $valueW = (int)floor((self::USABLE_TWIPS - $labelW * $columns) / $columns);
        $grid = '';
        for ($c = 0; $c < $columns; $c++) {
            $grid .= '<w:gridCol w:w="' . $labelW . '"/><w:gridCol w:w="' . $valueW . '"/>';
        }
        $rows = '';
        foreach (array_chunk($list, $columns) as $chunk) {
            $cells = '';
            for ($c = 0; $c < $columns; $c++) {
                $pair = $chunk[$c] ?? ['', ''];
                $cells .= $this->cell($pair[0] === '' ? '' : $this->para($this->run($pair[0], ['bold' => true, 'size' => 9, 'color' => self::MUTED, 'caps' => true]), ['after' => 0]), $labelW, 'F1F5F9')
                    . $this->cell($pair[1] === '' && $pair[0] === '' ? '' : $this->para($this->run($pair[1] === '' ? '-' : $pair[1], ['size' => 10, 'color' => self::INK]), ['after' => 0]), $valueW);
            }
            $rows .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>' . $cells . '</w:tr>';
        }
        $this->body .= $this->tableOpen($grid) . $rows . '</w:tbl>' . $this->para('', ['after' => 80]);
        return $this;
    }

    /**
     * Tabla con encabezado.
     *
     * @param array<int,string> $headers
     * @param array<int,array<int,string>> $rows
     * @param array<int,int> $weights ancho relativo por columna
     * @param array<int,string> $align 'left'|'right'|'center' por columna
     */
    public function table(array $headers, array $rows, array $weights = [], array $align = []): self
    {
        $n = count($headers);
        if ($n === 0) {
            return $this;
        }
        $weights = $weights !== [] ? array_values($weights) : array_fill(0, $n, 1);
        $sum = max(1, array_sum($weights));
        $widths = [];
        foreach ($weights as $w) {
            $widths[] = (int)floor(self::USABLE_TWIPS * $w / $sum);
        }
        $grid = '';
        foreach ($widths as $w) {
            $grid .= '<w:gridCol w:w="' . $w . '"/>';
        }
        $head = '';
        foreach ($headers as $i => $h) {
            $head .= $this->cell($this->para($this->run($h, ['bold' => true, 'color' => 'FFFFFF', 'size' => 9]), ['after' => 0, 'align' => $align[$i] ?? 'left']), $widths[$i], self::TEAL_DARK);
        }
        $body = '<w:tr><w:trPr><w:cantSplit/><w:tblHeader/></w:trPr>' . $head . '</w:tr>';
        foreach ($rows as $r => $row) {
            $cells = '';
            for ($i = 0; $i < $n; $i++) {
                $cells .= $this->cell(
                    $this->para($this->run((string)($row[$i] ?? ''), ['size' => 9, 'color' => self::INK]), ['after' => 0, 'align' => $align[$i] ?? 'left']),
                    $widths[$i],
                    $r % 2 === 1 ? 'F8FAFC' : null
                );
            }
            $body .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>' . $cells . '</w:tr>';
        }
        if ($rows === []) {
            $body .= '<w:tr>' . $this->cell($this->para($this->run('Sin registros', ['italic' => true, 'color' => self::MUTED, 'size' => 9]), ['after' => 0]), self::USABLE_TWIPS, null, $n) . '</w:tr>';
        }
        $this->body .= $this->tableOpen($grid) . $body . '</w:tbl>' . $this->para('', ['after' => 80]);
        return $this;
    }

    private function tableOpen(string $grid): string
    {
        return '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>'
            . '<w:top w:val="single" w:sz="4" w:space="0" w:color="' . self::LINE . '"/><w:left w:val="single" w:sz="4" w:space="0" w:color="' . self::LINE . '"/>'
            . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="' . self::LINE . '"/><w:right w:val="single" w:sz="4" w:space="0" w:color="' . self::LINE . '"/>'
            . '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/><w:insideV w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>'
            . '</w:tblBorders><w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="60" w:type="dxa"/><w:left w:w="100" w:type="dxa"/><w:bottom w:w="60" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tblCellMar></w:tblPr>'
            . '<w:tblGrid>' . $grid . '</w:tblGrid>';
    }

    private function cell(string $content, int $width, ?string $fill = null, int $span = 1): string
    {
        $pr = '<w:tcW w:w="' . $width . '" w:type="dxa"/>';
        if ($span > 1) {
            $pr .= '<w:gridSpan w:val="' . $span . '"/>';
        }
        if ($fill !== null) {
            $pr .= '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>';
        }
        $pr .= '<w:vAlign w:val="center"/>';
        return '<w:tc><w:tcPr>' . $pr . '</w:tcPr>' . ($content !== '' ? $content : '<w:p/>') . '</w:tc>';
    }

    /** Inserta una imagen (jpg/png/gif) desde disco, con ancho máximo en centímetros. */
    public function image(string $path, float $maxWidthCm = 8.0, string $caption = ''): self
    {
        $info = @getimagesize($path);
        $data = @file_get_contents($path);
        if (!is_array($info) || $data === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            return $this;
        }
        $ext = match ($info[2]) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_GIF => 'gif',
            default => 'jpeg',
        };
        $this->imageSeq++;
        $rid = 'rIdImg' . $this->imageSeq;
        $this->images[] = ['name' => 'image' . $this->imageSeq . '.' . $ext, 'data' => $data, 'rid' => $rid, 'ext' => $ext];
        $wEmu = (int)round($maxWidthCm * 360000);
        $ratio = $info[0] > 0 ? $info[1] / $info[0] : 1;
        $hEmu = (int)round($wEmu * $ratio);
        $maxH = (int)round(11.0 * 360000);
        if ($hEmu > $maxH) {
            $wEmu = (int)round($wEmu * $maxH / $hEmu);
            $hEmu = $maxH;
        }
        $id = $this->imageSeq;
        $this->body .= '<w:p><w:pPr><w:spacing w:before="40" w:after="40"/><w:jc w:val="center"/></w:pPr><w:r><w:drawing>'
            . '<wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="' . $wEmu . '" cy="' . $hEmu . '"/><wp:docPr id="' . $id . '" name="Imagen ' . $id . '"/>'
            . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="' . $id . '" name="image' . $id . '"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . $rid . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $wEmu . '" cy="' . $hEmu . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
            . '</a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
        if ($caption !== '') {
            $this->body .= $this->para($this->run($caption, ['italic' => true, 'color' => self::MUTED, 'size' => 9]), ['align' => 'center']);
        }
        return $this;
    }

    // ------------------------------------------------------------------ empaquetado

    public function build(): string
    {
        $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';
        $sect = '<w:sectPr><w:headerReference w:type="default" r:id="rIdHdr"/><w:footerReference w:type="default" r:id="rIdFtr"/>'
            . '<w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1418" w:right="1134" w:bottom="1134" w:left="1134" w:header="567" w:footer="567" w:gutter="0"/></w:sectPr>';
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document ' . $ns . '><w:body>' . $this->body . $sect . '</w:body></w:document>';

        $header = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:hdr ' . $ns . '><w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="12" w:space="4" w:color="' . self::TEAL . '"/></w:pBdr>'
            . '<w:tabs><w:tab w:val="right" w:pos="' . self::USABLE_TWIPS . '"/></w:tabs><w:spacing w:after="0"/></w:pPr>'
            . $this->run('UNIBAG', ['bold' => true, 'color' => self::TEAL, 'size' => 11]) . $this->run('  ' . $this->headerText, ['color' => self::MUTED, 'size' => 9])
            . '<w:r><w:tab/></w:r>' . $this->run(date('d/m/Y H:i'), ['color' => self::MUTED, 'size' => 9]) . '</w:p></w:hdr>';
        $footer = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr ' . $ns . '><w:p><w:pPr><w:pBdr><w:top w:val="single" w:sz="4" w:space="4" w:color="' . self::LINE . '"/></w:pBdr>'
            . '<w:tabs><w:tab w:val="right" w:pos="' . self::USABLE_TWIPS . '"/></w:tabs><w:spacing w:after="0"/></w:pPr>'
            . $this->run($this->footerText, ['color' => self::MUTED, 'size' => 8]) . '<w:r><w:tab/></w:r>'
            . $this->run('Página ', ['color' => self::MUTED, 'size' => 8]) . $this->field('PAGE') . $this->run(' de ', ['color' => self::MUTED, 'size' => 8]) . $this->field('NUMPAGES')
            . '</w:p></w:ftr>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rIdNum" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>'
            . '<Relationship Id="rIdHdr" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>'
            . '<Relationship Id="rIdFtr" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>';
        foreach ($this->images as $img) {
            $rels .= '<Relationship Id="' . $img['rid'] . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/' . $img['name'] . '"/>';
        }
        $rels .= '</Relationships>';

        $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/><Default Extension="jpeg" ContentType="image/jpeg"/><Default Extension="gif" ContentType="image/gif"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>'
            . '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
            . '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>';

        $root = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>';
        $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . self::esc($this->title) . '</dc:title><dc:creator>Unibag</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created></cp:coreProperties>';

        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        if ($tmp === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal del documento Word.');
        }
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el documento Word.');
        }
        $zip->addFromString('[Content_Types].xml', $types);
        $zip->addFromString('_rels/.rels', $root);
        $zip->addFromString('docProps/core.xml', $core);
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/styles.xml', $this->styles());
        $zip->addFromString('word/numbering.xml', $this->numbering());
        $zip->addFromString('word/header1.xml', $header);
        $zip->addFromString('word/footer1.xml', $footer);
        $zip->addFromString('word/_rels/document.xml.rels', $rels);
        foreach ($this->images as $img) {
            $zip->addFromString('word/media/' . $img['name'], $img['data']);
        }
        $zip->close();
        $binary = (string)file_get_contents($tmp);
        @unlink($tmp);
        return $binary;
    }

    private function field(string $instr): string
    {
        $rp = '<w:rPr><w:color w:val="' . self::MUTED . '"/><w:sz w:val="16"/></w:rPr>';
        return '<w:r>' . $rp . '<w:fldChar w:fldCharType="begin"/></w:r><w:r>' . $rp . '<w:instrText xml:space="preserve"> ' . $instr . ' </w:instrText></w:r>'
            . '<w:r>' . $rp . '<w:fldChar w:fldCharType="separate"/></w:r><w:r>' . $rp . '<w:t>1</w:t></w:r><w:r>' . $rp . '<w:fldChar w:fldCharType="end"/></w:r>';
    }

    private function styles(): string
    {
        $h = static fn(int $n, int $size, string $color, string $extra): string =>
            '<w:style w:type="paragraph" w:styleId="Heading' . $n . '"><w:name w:val="heading ' . $n . '"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
            . '<w:pPr><w:keepNext/>' . $extra . '<w:outlineLvl w:val="' . ($n - 1) . '"/></w:pPr><w:rPr><w:b/><w:bCs/><w:color w:val="' . $color . '"/><w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/></w:rPr></w:style>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri" w:eastAsia="Calibri"/><w:sz w:val="21"/><w:szCs w:val="21"/><w:lang w:val="es-CL"/></w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:after="80" w:line="259" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            . '<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:qFormat/><w:rPr><w:b/><w:sz w:val="48"/></w:rPr></w:style>'
            . $h(1, 30, self::TEAL_DARK, '<w:pBdr><w:bottom w:val="single" w:sz="8" w:space="2" w:color="' . self::TEAL . '"/></w:pBdr>')
            . $h(2, 25, self::INK, '')
            . $h(3, 22, self::TEAL_DARK, '')
            . '<w:style w:type="paragraph" w:styleId="ListBullet"><w:name w:val="List Bullet"/><w:basedOn w:val="Normal"/><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr><w:ind w:left="360" w:hanging="260"/></w:pPr></w:style>'
            . '</w:styles>';
    }

    private function numbering(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:abstractNum w:abstractNumId="0"><w:multiLevelType w:val="hybridMultilevel"/><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/><w:lvlJc w:val="left"/>'
            . '<w:pPr><w:ind w:left="360" w:hanging="260"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:color w:val="' . self::TEAL . '"/></w:rPr></w:lvl></w:abstractNum>'
            . '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num></w:numbering>';
    }
}
