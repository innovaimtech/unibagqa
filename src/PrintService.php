<?php

declare(strict_types=1);

final class PrintService
{
    public function __construct(private array $env)
    {
    }

    private function getPrintLanguage(): string
    {
        $language = strtolower(trim((string)($this->env['PRINT_LANGUAGE'] ?? 'zpl')));
        return in_array($language, ['zpl', 'tspl', 'epl'], true) ? $language : 'zpl';
    }

    public function getConfiguredPrintLanguage(): string
    {
        return $this->getPrintLanguage();
    }

    public function getConfiguredPrinterName(): string
    {
        return trim((string)($this->env['PRINT_PRINTER_NAME'] ?? ''));
    }

    public function isEnabled(): bool
    {
        $mode = strtolower((string)($this->env['PRINT_MODE'] ?? 'none'));
        return $mode !== '' && $mode !== 'none' && $mode !== 'off';
    }

    public function printRollLabel(array $roll): array
    {
        $mode = strtolower((string)($this->env['PRINT_MODE'] ?? 'none'));
        if ($mode !== 'http') {
            return ['ok' => false, 'error' => 'Impresión deshabilitada (PRINT_MODE).'];
        }

        $url = (string)($this->env['PRINT_HTTP_URL'] ?? 'http://localhost:8767/print');
        $printerName = (string)($this->env['PRINT_PRINTER_NAME'] ?? '');
        if ($printerName === '') {
            return ['ok' => false, 'error' => 'Falta PRINT_PRINTER_NAME.'];
        }

        $copies = (int)($this->env['PRINT_COPIES'] ?? 1);
        if ($copies <= 0) {
            $copies = 1;
        }

        $command = $this->buildRollCommand($roll);
        return $this->sendRaw($command, $copies, $printerName, $url, $this->getPrintLanguage());
    }

    public function printWorkOrderBoxLabel(array $workOrder, ?array $finish = null): array
    {
        $mode = strtolower((string)($this->env['PRINT_MODE'] ?? 'none'));
        if ($mode !== 'http') {
            return ['ok' => false, 'error' => 'Impresión deshabilitada (PRINT_MODE).'];
        }

        $url = (string)($this->env['PRINT_HTTP_URL'] ?? 'http://localhost:8767/print');
        $printerName = (string)($this->env['PRINT_PRINTER_NAME'] ?? '');
        if ($printerName === '') {
            return ['ok' => false, 'error' => 'Falta PRINT_PRINTER_NAME.'];
        }

        $copies = (int)($this->env['PRINT_COPIES'] ?? 1);
        if ($copies <= 0) {
            $copies = 1;
        }

        $command = $this->buildWorkOrderBoxCommand($workOrder, $finish);
        return $this->sendRaw($command, $copies, $printerName, $url, $this->getPrintLanguage());
    }

    public function printBoxLabel(array $box): array
    {
        $mode = strtolower((string)($this->env['PRINT_MODE'] ?? 'none'));
        if ($mode !== 'http') {
            return ['ok' => false, 'error' => 'Impresión deshabilitada (PRINT_MODE).'];
        }

        $url = (string)($this->env['PRINT_HTTP_URL'] ?? 'http://localhost:8767/print');
        $printerName = (string)($this->env['PRINT_PRINTER_NAME'] ?? '');
        if ($printerName === '') {
            return ['ok' => false, 'error' => 'Falta PRINT_PRINTER_NAME.'];
        }

        $copies = (int)($this->env['PRINT_COPIES'] ?? 1);
        if ($copies <= 0) {
            $copies = 1;
        }

        $command = $this->buildBoxCommand($box);
        return $this->sendRaw($command, $copies, $printerName, $url, $this->getPrintLanguage());
    }

    public function printPalletLabel(array $pallet, array $boxes = []): array
    {
        $mode = strtolower((string)($this->env['PRINT_MODE'] ?? 'none'));
        if ($mode !== 'http') {
            return ['ok' => false, 'error' => 'Impresión deshabilitada (PRINT_MODE).'];
        }

        $url = (string)($this->env['PRINT_HTTP_URL'] ?? 'http://localhost:8767/print');
        $printerName = (string)($this->env['PRINT_PRINTER_NAME'] ?? '');
        if ($printerName === '') {
            return ['ok' => false, 'error' => 'Falta PRINT_PRINTER_NAME.'];
        }

        $copies = (int)($this->env['PRINT_COPIES'] ?? 1);
        if ($copies <= 0) {
            $copies = 1;
        }

        $command = $this->buildPalletCommand($pallet, $boxes);
        return $this->sendRaw($command, $copies, $printerName, $url, $this->getPrintLanguage());
    }

    private function sendRaw(string $content, int $copies, string $printerName, string $url, string $language): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'cURL no está disponible en PHP.'];
        }

        $payload = json_encode([
            'printer' => $printerName,
            'copies' => $copies,
            'language' => $language,
            'content' => $content,
            'zpl' => $language === 'zpl' ? $content : null,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_FAILONERROR => false,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_POSTFIELDS => $payload,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            $curlMsg = $err !== '' ? " ($err)" : '';
            return ['ok' => false, 'error' => 'No se pudo conectar al puente de impresión local (puerto 8767). Asegúrate de que el script print_bridge.ps1 esté iniciado.' . $curlMsg];
        }

        $data = json_decode((string)$raw, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = is_array($data) && isset($data['error']) ? (string)$data['error'] : ('Error en puente de impresión (HTTP ' . $httpCode . ')');
            return ['ok' => false, 'error' => $msg, 'raw' => (string)$raw];
        }

        if (!is_array($data) || ($data['ok'] ?? false) !== true) {
            $msg = is_array($data) && isset($data['error']) ? (string)$data['error'] : 'Respuesta inválida del servicio de impresión.';
            return ['ok' => false, 'error' => $msg, 'raw' => (string)$raw];
        }

        return ['ok' => true, 'error' => null];
    }

    private function z(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        $value = str_replace('^', ' ', $value);
        return $value;
    }

    private function t(string $value): string
    {
        $value = $this->z($value);
        return str_replace('"', "'", $value);
    }

    private function printDpi(): int
    {
        $dpi = (int)($this->env['PRINT_DPI'] ?? 203);
        return $dpi > 0 ? $dpi : 203;
    }

    private function dotsPerMm(): float
    {
        return $this->printDpi() / 25.4;
    }

    private function mmToDots(float $mm): int
    {
        return (int)round(max(0.0, $mm) * $this->dotsPerMm());
    }

    private function getLabelWidthMm(): float
    {
        $value = (float)($this->env['PRINT_LABEL_WIDTH_MM'] ?? 100);
        return $value > 0 ? $value : 100.0;
    }

    private function getLabelHeightMm(): float
    {
        $value = (float)($this->env['PRINT_LABEL_HEIGHT_MM'] ?? 70);
        return $value > 0 ? $value : 70.0;
    }

    private function getLabelGapMm(): float
    {
        $value = (float)($this->env['PRINT_LABEL_GAP_MM'] ?? 3);
        return $value >= 0 ? $value : 3.0;
    }

    private function getPrintDirection(): int
    {
        $value = (int)($this->env['PRINT_DIRECTION'] ?? 0);
        return $value === 1 ? 1 : 0;
    }

    private function getReferenceXDots(): int
    {
        return (int)($this->env['PRINT_REF_X_DOTS'] ?? 0);
    }

    private function getReferenceYDots(): int
    {
        return (int)($this->env['PRINT_REF_Y_DOTS'] ?? 0);
    }

    private function formatLabelDate(?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '' || $value === '-') {
            return '-';
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return $this->z($value);
        }

        return date('d-m-Y', $ts);
    }

    private function resolveRollDate(array $roll): string
    {
        $rawDate = trim((string)($roll['arrival_date'] ?? ''));
        if ($rawDate === '' || $rawDate === '-') {
            $rawDate = trim((string)($roll['created_at'] ?? ''));
        }
        if ($rawDate === '' || $rawDate === '-') {
            $rawDate = date('Y-m-d');
        }
        return $this->formatLabelDate($rawDate);
    }

    public function getRollZpl(array $roll): string
    {
        return $this->buildZpl($roll);
    }

    public function getRollPrintCommand(array $roll): string
    {
        return $this->buildRollCommand($roll);
    }

    private function buildRollCommand(array $roll): string
    {
        $language = $this->getPrintLanguage();
        if ($language === 'tspl') {
            return $this->buildRollTspl($roll);
        }
        if ($language === 'epl') {
            return $this->buildRollEpl($roll);
        }
        return $this->buildZpl($roll);
    }

    private function buildWorkOrderBoxCommand(array $workOrder, ?array $finish = null): string
    {
        $lang = $this->getPrintLanguage();
        if ($lang === 'tspl') {
            return $this->buildWorkOrderBoxTspl($workOrder, $finish);
        }
        if ($lang === 'epl') {
            return $this->buildWorkOrderBoxEpl($workOrder, $finish);
        }
        return $this->buildWorkOrderBoxZpl($workOrder, $finish);
    }

    private function buildBoxCommand(array $box): string
    {
        $lang = $this->getPrintLanguage();
        if ($lang === 'tspl') {
            return $this->buildBoxTspl($box);
        }
        if ($lang === 'epl') {
            return $this->buildBoxEpl($box);
        }
        return $this->buildBoxZpl($box);
    }

    private function buildPalletCommand(array $pallet, array $boxes = []): string
    {
        $lang = $this->getPrintLanguage();
        if ($lang === 'tspl') {
            return $this->buildPalletTspl($pallet, $boxes);
        }
        if ($lang === 'epl') {
            return $this->buildPalletEpl($pallet, $boxes);
        }
        return $this->buildPalletZpl($pallet, $boxes);
    }

    private function buildZpl(array $roll): string
    {
        $id = (string)($roll['id'] ?? '');
        $rollCode = trim((string)($roll['roll_code'] ?? ''));
        $barcodeValue = $this->z($rollCode !== '' ? $rollCode : $id);

        $sku = $this->z((string)($roll['sku_code'] ?? '-'));
        $product = $this->z((string)($roll['sku_description'] ?? '-'));
        $arrivalDate = $this->resolveRollDate($roll);

        $warehouseCode = $this->z((string)($roll['warehouse_code'] ?? '-'));
        $warehouseName = $this->z((string)($roll['warehouse_name'] ?? ''));

        $weightKg = round((float)($roll['weight_kg'] ?? 0), 2);
        $weightStr = $weightKg > 0 ? number_format($weightKg, 2, '.', '') . ' KG' : '1 UNID';

        $metersVal = (float)($roll['meters'] ?? 0);
        $metersStr = $metersVal > 0 ? number_format($metersVal, 0, ',', '.') . ' m' : '-';

        $widthMm = (float)($roll['width_mm'] ?? 0);
        $gramsVal = (float)($roll['grams'] ?? $roll['microns'] ?? 0);
        $specsStr = '';
        if ($widthMm > 0) {
            $specsStr .= round($widthMm) . 'mm';
        }
        if ($gramsVal > 0) {
            $specsStr .= ($specsStr !== '' ? ' / ' : '') . round($gramsVal) . 'g';
        }
        if ($specsStr === '') {
            $specsStr = '-';
        }
        $specsStr = $this->z($specsStr);

        $color = $this->z(trim((string)($roll['color'] ?? '')) ?: '-');
        $poCode = $this->z(trim((string)($roll['po_code'] ?? '')) ?: '-');
        $containerCode = $this->z(trim((string)($roll['container_code'] ?? '')) ?: '-');
        $supplierName = $this->z(trim((string)($roll['supplier_name'] ?? '')) ?: '-');
        $processStage = $this->z(strtoupper(trim((string)($roll['process_stage'] ?? 'RAW'))));

        return "^XA
^CI28
^PW800
^LL560
^LH0,0

^CF0,28
^FO25,20^FDUNIBAG CHILE^FS
^CF0,18
^FO25,52^FDRECEPCION MATERIA PRIMA^FS

^CF0,20
^FO440,20^FDBODEGA: {$warehouseCode}^FS
^CF0,18
^FO440,48^FDFECHA: {$arrivalDate}^FS

^FO25,74^GB750,2,2^FS

^CF0,20
^FO25,86^FDSKU:^FS
^CF0,36
^FO90,80^FD{$sku}^FS

^CF0,22
^FO25,124^FB750,2,2,L,0^FD{$product}^FS

^FO25,182^GB750,1,1^FS

^CF0,16
^FO25,192^FDPESO REAL:^FS
^CF0,28
^FO25,212^FD{$weightStr}^FS

^CF0,16
^FO240,192^FDMETROS:^FS
^CF0,26
^FO240,214^FD{$metersStr}^FS

^CF0,16
^FO440,192^FDANCHO / GR:^FS
^CF0,24
^FO440,214^FD{$specsStr}^FS

^CF0,16
^FO640,192^FDCOLOR:^FS
^CF0,24
^FO640,214^FD{$color}^FS

^FO25,250^GB750,1,1^FS

^CF0,16
^FO25,258^FDORDEN COMPRA: {$poCode}^FS
^CF0,16
^FO320,258^FDCONTENEDOR: {$containerCode}^FS
^CF0,16
^FO25,280^FB750,1,0,L,0^FDPROVEEDOR: {$supplierName}^FS

^FO25,306^GB750,1,1^FS

^BY2,2,75
^FO100,318^BCN,75,N,N,N^FD{$barcodeValue}^FS

^CF0,26
^FO0,404^FB800,1,0,C,0^FD{$barcodeValue}^FS

^CF0,15
^FO0,442^FB800,1,0,C,0^FDETAPA: {$processStage}  |  CONTROL DE CALIDAD UNIBAG^FS

^XZ";
    }

    private function buildTsplHeader(): string
    {
        $widthMm = number_format($this->getLabelWidthMm(), 2, '.', '');
        $heightMm = number_format($this->getLabelHeightMm(), 2, '.', '');
        $gapMm = number_format($this->getLabelGapMm(), 2, '.', '');
        $direction = $this->getPrintDirection();
        $refX = $this->getReferenceXDots();
        $refY = $this->getReferenceYDots();

        return implode("\r\n", [
            'SIZE ' . $widthMm . ' mm,' . $heightMm . ' mm',
            'GAP ' . $gapMm . ' mm,0 mm',
            'DIRECTION ' . $direction,
            'REFERENCE ' . $refX . ',' . $refY,
            'CLS',
        ]);
    }

    private function buildRollTspl(array $roll): string
    {
        $id = (string)($roll['id'] ?? '');
        $rollCode = trim((string)($roll['roll_code'] ?? ''));
        $barcodeValue = $this->t($rollCode !== '' ? $rollCode : $id);
        $sku = $this->t((string)($roll['sku_code'] ?? '-'));
        $product = $this->t((string)($roll['sku_description'] ?? '-'));
        $arrivalDate = $this->t($this->resolveRollDate($roll));
        $warehouseCode = $this->t((string)($roll['warehouse_code'] ?? '-'));
        $weightKg = round((float)($roll['weight_kg'] ?? 0), 2);
        $weightStr = $weightKg > 0 ? number_format($weightKg, 2, '.', '') . ' KG' : '1 UNID';
        $metersVal = (float)($roll['meters'] ?? 0);
        $metersStr = $metersVal > 0 ? number_format($metersVal, 0, ',', '.') . ' m' : '-';
        $widthMm = (float)($roll['width_mm'] ?? 0);
        $gramsVal = (float)($roll['grams'] ?? $roll['microns'] ?? 0);
        $specsStr = '';
        if ($widthMm > 0) {
            $specsStr .= round($widthMm) . 'mm';
        }
        if ($gramsVal > 0) {
            $specsStr .= ($specsStr !== '' ? ' / ' : '') . round($gramsVal) . 'g';
        }
        if ($specsStr === '') {
            $specsStr = '-';
        }
        $color = $this->t(trim((string)($roll['color'] ?? '')) ?: '-');
        $poCode = $this->t(trim((string)($roll['po_code'] ?? '')) ?: '-');
        $containerCode = $this->t(trim((string)($roll['container_code'] ?? '')) ?: '-');
        $supplierName = $this->t(trim((string)($roll['supplier_name'] ?? '')) ?: '-');
        $processStage = $this->t(strtoupper(trim((string)($roll['process_stage'] ?? 'RAW'))));

        return $this->buildTsplHeader() . "\r\n"
            . 'BAR 25,72,750,2' . "\r\n"
            . 'BAR 25,180,750,1' . "\r\n"
            . 'BAR 25,248,750,1' . "\r\n"
            . 'BAR 25,304,750,1' . "\r\n"
            . 'TEXT 25,18,"3",0,1,1,"UNIBAG CHILE"' . "\r\n"
            . 'TEXT 25,46,"2",0,1,1,"RECEPCION MATERIA PRIMA"' . "\r\n"
            . 'TEXT 440,18,"2",0,1,1,"BODEGA: ' . $warehouseCode . '"' . "\r\n"
            . 'TEXT 440,44,"2",0,1,1,"FECHA: ' . $arrivalDate . '"' . "\r\n"
            . 'TEXT 25,82,"2",0,1,1,"SKU:"' . "\r\n"
            . 'TEXT 92,78,"4",0,1,1,"' . $sku . '"' . "\r\n"
            . 'TEXT 25,118,"2",0,1,1,"' . $product . '"' . "\r\n"
            . 'TEXT 25,190,"1",0,1,1,"PESO REAL:"' . "\r\n"
            . 'TEXT 25,210,"3",0,1,1,"' . $this->t($weightStr) . '"' . "\r\n"
            . 'TEXT 240,190,"1",0,1,1,"METROS:"' . "\r\n"
            . 'TEXT 240,210,"2",0,1,1,"' . $this->t($metersStr) . '"' . "\r\n"
            . 'TEXT 440,190,"1",0,1,1,"ANCHO / GR:"' . "\r\n"
            . 'TEXT 440,210,"2",0,1,1,"' . $this->t($specsStr) . '"' . "\r\n"
            . 'TEXT 640,190,"1",0,1,1,"COLOR:"' . "\r\n"
            . 'TEXT 640,210,"2",0,1,1,"' . $color . '"' . "\r\n"
            . 'TEXT 25,256,"1",0,1,1,"ORDEN COMPRA: ' . $poCode . '"' . "\r\n"
            . 'TEXT 320,256,"1",0,1,1,"CONTENEDOR: ' . $containerCode . '"' . "\r\n"
            . 'TEXT 25,278,"1",0,1,1,"PROVEEDOR: ' . $supplierName . '"' . "\r\n"
            . 'BARCODE 100,318,"128",72,0,0,2,2,"' . $barcodeValue . '"' . "\r\n"
            . 'TEXT 280,404,"3",0,1,1,"' . $barcodeValue . '"' . "\r\n"
            . 'TEXT 180,438,"1",0,1,1,"ETAPA: ' . $processStage . ' | CONTROL DE CALIDAD UNIBAG"' . "\r\n"
            . 'PRINT 1,1';
    }

    private function buildRollEpl(array $roll): string
    {
        $id = (string)($roll['id'] ?? '');
        $rollCode = trim((string)($roll['roll_code'] ?? ''));
        $barcodeValue = strtoupper($this->t($rollCode !== '' ? $rollCode : $id));
        $sku = strtoupper($this->t((string)($roll['sku_code'] ?? '-')));
        $product = strtoupper($this->t((string)($roll['sku_description'] ?? '-')));
        $arrivalDate = strtoupper($this->t($this->resolveRollDate($roll)));
        $warehouseCode = strtoupper($this->t((string)($roll['warehouse_code'] ?? '-')));
        $weightKg = round((float)($roll['weight_kg'] ?? 0), 2);
        $weightStr = strtoupper($this->t($weightKg > 0 ? number_format($weightKg, 2, '.', '') . ' KG' : '1 UNID'));
        $metersVal = (float)($roll['meters'] ?? 0);
        $metersStr = strtoupper($this->t($metersVal > 0 ? number_format($metersVal, 0, ',', '.') . ' M' : '-'));
        $widthMm = (float)($roll['width_mm'] ?? 0);
        $gramsVal = (float)($roll['grams'] ?? $roll['microns'] ?? 0);
        $specsStr = '';
        if ($widthMm > 0) {
            $specsStr .= round($widthMm) . 'MM';
        }
        if ($gramsVal > 0) {
            $specsStr .= ($specsStr !== '' ? ' / ' : '') . round($gramsVal) . 'G';
        }
        if ($specsStr === '') {
            $specsStr = '-';
        }
        $specsStr = strtoupper($this->t($specsStr));
        $color = strtoupper($this->t(trim((string)($roll['color'] ?? '')) ?: '-'));
        $poCode = strtoupper($this->t(trim((string)($roll['po_code'] ?? '')) ?: '-'));
        $containerCode = strtoupper($this->t(trim((string)($roll['container_code'] ?? '')) ?: '-'));
        $supplierName = strtoupper($this->t(trim((string)($roll['supplier_name'] ?? '')) ?: '-'));
        $processStage = strtoupper($this->t(trim((string)($roll['process_stage'] ?? 'RAW'))));

        return implode("\r\n", [
            'N',
            'q800',
            'Q560,24',
            'LO20,70,750,2',
            'LO20,166,750,1',
            'LO20,236,750,1',
            'LO20,286,750,1',
            'A20,18,0,3,1,1,N,"UNIBAG CHILE"',
            'A20,46,0,2,1,1,N,"RECEPCION MATERIA PRIMA"',
            'A440,18,0,2,1,1,N,"BOD: ' . $warehouseCode . '"',
            'A440,42,0,2,1,1,N,"FECHA: ' . $arrivalDate . '"',
            'A20,82,0,2,1,1,N,"SKU: ' . $sku . '"',
            'A20,110,0,2,1,1,N,"' . $product . '"',
            'A20,174,0,1,1,1,N,"PESO REAL:"',
            'A20,196,0,3,1,1,N,"' . $weightStr . '"',
            'A220,174,0,1,1,1,N,"METROS:"',
            'A220,196,0,2,1,1,N,"' . $metersStr . '"',
            'A420,174,0,1,1,1,N,"ANCHO / GR:"',
            'A420,196,0,2,1,1,N,"' . $specsStr . '"',
            'A620,174,0,1,1,1,N,"COLOR:"',
            'A620,196,0,2,1,1,N,"' . $color . '"',
            'A20,248,0,2,1,1,N,"OC: ' . $poCode . '"',
            'A260,248,0,2,1,1,N,"CONT: ' . $containerCode . '"',
            'A480,248,0,2,1,1,N,"PROV: ' . $supplierName . '"',
            'B100,304,0,1,2,6,72,N,"' . $barcodeValue . '"',
            'A220,388,0,3,1,1,N,"' . $barcodeValue . '"',
            'A140,422,0,1,1,1,N,"ETAPA: ' . $processStage . ' | CONTROL DE CALIDAD UNIBAG"',
            'P1',
        ]) . "\r\n";
    }

    private function buildWorkOrderBoxZpl(array $workOrder, ?array $finish = null): string
    {
        $otCode = $this->z((string)($workOrder['ot_code'] ?? ''));
        $product = $this->z((string)($workOrder['sku_final'] ?? ''));
        $boxQty = $this->z((string)($finish['box_qty'] ?? ''));
        $operator = $this->z((string)($finish['operator_name'] ?? ''));
        $finishedAt = $this->z((string)($finish['created_at'] ?? ''));
        $barcodeValue = $otCode !== '' ? $otCode : 'OT';

        return "^XA
^CI28
^PW600
^LL500
^CF0,32
^FO20,20^FDUNIBAG^FS
^CF0,22
^FO20,58^FDCAJA PRODUCTO TERMINADO^FS
^CF0,34
^FO20,92^FB560,2,0,L,0^FD{$product}^FS
^FO20,146^GB560,2,2^FS

^CF0,24
^FO20,166^FDOT: {$otCode}^FS
^FO20,194^FDFECHA CIERRE: {$finishedAt}^FS
^FO20,222^FDOPERADOR: {$operator}^FS
^FO20,250^FDCANTIDAD CAJAS: {$boxQty}^FS

^BY2,2,80
^FO80,300^BCN,90,Y,N,N^FD{$barcodeValue}^FS
^CF0,32
^FO0,426^FB600,1,0,C,0^FD{$barcodeValue}^FS
^XZ";
    }

    private function buildBoxZpl(array $box): string
    {
        $boxCode = $this->z((string)($box['box_code'] ?? ''));
        $otCode = $this->z((string)($box['ot_code'] ?? ''));
        $product = $this->z((string)($box['final_sku'] ?? ''));
        $sourceRoll = $this->z((string)($box['source_roll_code'] ?? ''));
        $units = $this->z((string)($box['units_qty'] ?? ''));
        $palletCode = $this->z((string)($box['pallet_code'] ?? ''));
        $operator = $this->z((string)($box['operator_name'] ?? ''));
        $destinationMode = $this->z((string)($box['destination_mode'] ?? ''));
        $customerOrderRef = $this->z((string)($box['customer_order_ref'] ?? ''));
        $warehouseCode = $this->z((string)($box['warehouse_code'] ?? ''));

        $destinationLabel = $destinationMode === 'CUSTOMER_ORDER'
            ? ('OC cliente: ' . ($customerOrderRef !== '' ? $customerOrderRef : '-'))
            : ('Stock bodega: ' . ($warehouseCode !== '' ? $warehouseCode : '-'));
        $destinationLabel = $this->z($destinationLabel);

        return "^XA
^CI28
^PW600
^LL610
^CF0,32
^FO20,20^FDUNIBAG^FS
^CF0,22
^FO20,58^FDCAJA TRAZABLE^FS
^CF0,34
^FO20,92^FB560,2,0,L,0^FD{$product}^FS
^FO20,146^GB560,2,2^FS

^CF0,24
^FO20,166^FDCAJA: {$boxCode}^FS
^FO20,194^FDOT: {$otCode}^FS
^FO20,222^FDBOBINA ORIGEN: {$sourceRoll}^FS
^FO20,250^FDUNIDADES: {$units}^FS
^FO20,278^FDPALLET: {$palletCode}^FS
^FO20,306^FDOPERADOR: {$operator}^FS
^FO20,334^FDDESTINO: {$destinationLabel}^FS

^BY2,2,82
^FO60,392^BCN,90,Y,N,N^FD{$boxCode}^FS
^CF0,32
^FO0,534^FB600,1,0,C,0^FD{$boxCode}^FS
^XZ";
    }

    private function buildPalletZpl(array $pallet, array $boxes = []): string
    {
        $palletCode = $this->z((string)($pallet['pallet_code'] ?? ''));
        $otCode = $this->z((string)($pallet['ot_code'] ?? ''));
        $product = $this->z((string)($pallet['final_sku'] ?? ''));
        $sourceRoll = $this->z((string)($pallet['source_roll_code'] ?? ''));
        $boxCount = $this->z((string)($pallet['box_count'] ?? ''));
        $operator = $this->z((string)($pallet['operator_name'] ?? ''));
        $destinationMode = $this->z((string)($pallet['destination_mode'] ?? ''));
        $customerOrderRef = $this->z((string)($pallet['customer_order_ref'] ?? ''));
        $warehouseCode = $this->z((string)($pallet['warehouse_code'] ?? ''));

        $boxCodes = [];
        foreach ($boxes as $box) {
            $code = trim((string)($box['box_code'] ?? ''));
            if ($code !== '') {
                $boxCodes[] = $code;
            }
            if (count($boxCodes) >= 4) {
                break;
            }
        }
        $boxSummary = $boxCodes === [] ? '-' : implode(', ', $boxCodes);
        if (count($boxes) > count($boxCodes)) {
            $boxSummary .= '...';
        }
        $boxSummary = $this->z($boxSummary);

        $destinationLabel = $destinationMode === 'CUSTOMER_ORDER'
            ? ('OC cliente: ' . ($customerOrderRef !== '' ? $customerOrderRef : '-'))
            : ('Stock bodega: ' . ($warehouseCode !== '' ? $warehouseCode : '-'));
        $destinationLabel = $this->z($destinationLabel);

        return "^XA
^CI28
^PW600
^LL630
^CF0,32
^FO20,20^FDUNIBAG^FS
^CF0,22
^FO20,58^FDPALLET TRAZABLE^FS
^CF0,34
^FO20,92^FB560,2,0,L,0^FD{$product}^FS
^FO20,146^GB560,2,2^FS

^CF0,24
^FO20,166^FDPALLET: {$palletCode}^FS
^FO20,194^FDOT: {$otCode}^FS
^FO20,222^FDBOBINA ORIGEN: {$sourceRoll}^FS
^FO20,250^FDCAJAS: {$boxCount}^FS
^FO20,278^FDOPERADOR: {$operator}^FS
^FO20,306^FDDESTINO: {$destinationLabel}^FS
^FO20,334^FB560,2,0,L,0^FDCODIGOS CAJA: {$boxSummary}^FS

^BY2,2,82
^FO60,402^BCN,90,Y,N,N^FD{$palletCode}^FS
^CF0,32
^FO0,554^FB600,1,0,C,0^FD{$palletCode}^FS
^XZ";
    }

    private function buildWorkOrderBoxTspl(array $workOrder, ?array $finish = null): string
    {
        $otCode = $this->t((string)($workOrder['ot_code'] ?? ''));
        $product = $this->t((string)($workOrder['sku_final'] ?? ''));
        $boxQty = $this->t((string)($finish['box_qty'] ?? ''));
        $operator = $this->t((string)($finish['operator_name'] ?? ''));
        $finishedAt = $this->t((string)($finish['created_at'] ?? ''));
        $barcodeValue = $otCode !== '' ? $otCode : 'OT';

        return $this->buildTsplHeader() . "\r\n"
            . 'BAR 20,146,560,2' . "\r\n"
            . 'TEXT 20,20,"3",0,1,1,"UNIBAG"' . "\r\n"
            . 'TEXT 20,52,"2",0,1,1,"CAJA PRODUCTO TERMINADO"' . "\r\n"
            . 'TEXT 20,90,"3",0,1,1,"' . $product . '"' . "\r\n"
            . 'TEXT 20,166,"2",0,1,1,"OT: ' . $otCode . '"' . "\r\n"
            . 'TEXT 20,194,"2",0,1,1,"FECHA CIERRE: ' . $finishedAt . '"' . "\r\n"
            . 'TEXT 20,222,"2",0,1,1,"OPERADOR: ' . $operator . '"' . "\r\n"
            . 'TEXT 20,250,"2",0,1,1,"CANTIDAD CAJAS: ' . $boxQty . '"' . "\r\n"
            . 'BARCODE 80,300,"128",90,1,0,2,2,"' . $barcodeValue . '"' . "\r\n"
            . 'TEXT 200,426,"3",0,1,1,"' . $barcodeValue . '"' . "\r\n"
            . 'PRINT 1,1';
    }

    private function buildBoxTspl(array $box): string
    {
        $boxCode = $this->t((string)($box['box_code'] ?? ''));
        $otCode = $this->t((string)($box['ot_code'] ?? ''));
        $product = $this->t((string)($box['final_sku'] ?? ''));
        $sourceRoll = $this->t((string)($box['source_roll_code'] ?? ''));
        $units = $this->t((string)($box['units_qty'] ?? ''));
        $palletCode = $this->t((string)($box['pallet_code'] ?? ''));
        $operator = $this->t((string)($box['operator_name'] ?? ''));
        $destinationMode = $this->t((string)($box['destination_mode'] ?? ''));
        $customerOrderRef = $this->t((string)($box['customer_order_ref'] ?? ''));
        $warehouseCode = $this->t((string)($box['warehouse_code'] ?? ''));
        $destinationLabel = $destinationMode === 'CUSTOMER_ORDER'
            ? ('OC cliente: ' . ($customerOrderRef !== '' ? $customerOrderRef : '-'))
            : ('Stock bodega: ' . ($warehouseCode !== '' ? $warehouseCode : '-'));
        $destinationLabel = $this->t($destinationLabel);

        return $this->buildTsplHeader() . "\r\n"
            . 'BAR 20,146,560,2' . "\r\n"
            . 'TEXT 20,20,"3",0,1,1,"UNIBAG"' . "\r\n"
            . 'TEXT 20,52,"2",0,1,1,"CAJA TRAZABLE"' . "\r\n"
            . 'TEXT 20,90,"3",0,1,1,"' . $product . '"' . "\r\n"
            . 'TEXT 20,166,"2",0,1,1,"CAJA: ' . $boxCode . '"' . "\r\n"
            . 'TEXT 20,194,"2",0,1,1,"OT: ' . $otCode . '"' . "\r\n"
            . 'TEXT 20,222,"2",0,1,1,"BOBINA ORIGEN: ' . $sourceRoll . '"' . "\r\n"
            . 'TEXT 20,250,"2",0,1,1,"UNIDADES: ' . $units . '"' . "\r\n"
            . 'TEXT 20,278,"2",0,1,1,"PALLET: ' . $palletCode . '"' . "\r\n"
            . 'TEXT 20,306,"2",0,1,1,"OPERADOR: ' . $operator . '"' . "\r\n"
            . 'TEXT 20,334,"2",0,1,1,"DESTINO: ' . $destinationLabel . '"' . "\r\n"
            . 'BARCODE 60,392,"128",92,1,0,2,2,"' . $boxCode . '"' . "\r\n"
            . 'TEXT 180,534,"3",0,1,1,"' . $boxCode . '"' . "\r\n"
            . 'PRINT 1,1';
    }

    private function buildPalletTspl(array $pallet, array $boxes = []): string
    {
        $palletCode = $this->t((string)($pallet['pallet_code'] ?? ''));
        $otCode = $this->t((string)($pallet['ot_code'] ?? ''));
        $product = $this->t((string)($pallet['final_sku'] ?? ''));
        $sourceRoll = $this->t((string)($pallet['source_roll_code'] ?? ''));
        $boxCount = $this->t((string)($pallet['box_count'] ?? ''));
        $operator = $this->t((string)($pallet['operator_name'] ?? ''));
        $destinationMode = $this->t((string)($pallet['destination_mode'] ?? ''));
        $customerOrderRef = $this->t((string)($pallet['customer_order_ref'] ?? ''));
        $warehouseCode = $this->t((string)($pallet['warehouse_code'] ?? ''));
        $boxCodes = [];
        foreach ($boxes as $box) {
            $code = trim((string)($box['box_code'] ?? ''));
            if ($code !== '') {
                $boxCodes[] = $code;
            }
            if (count($boxCodes) >= 4) {
                break;
            }
        }
        $boxSummary = $boxCodes === [] ? '-' : implode(', ', $boxCodes);
        if (count($boxes) > count($boxCodes)) {
            $boxSummary .= '...';
        }
        $boxSummary = $this->t($boxSummary);
        $destinationLabel = $destinationMode === 'CUSTOMER_ORDER'
            ? ('OC cliente: ' . ($customerOrderRef !== '' ? $customerOrderRef : '-'))
            : ('Stock bodega: ' . ($warehouseCode !== '' ? $warehouseCode : '-'));
        $destinationLabel = $this->t($destinationLabel);

        return $this->buildTsplHeader() . "\r\n"
            . 'BAR 20,146,560,2' . "\r\n"
            . 'TEXT 20,20,"3",0,1,1,"UNIBAG"' . "\r\n"
            . 'TEXT 20,52,"2",0,1,1,"PALLET TRAZABLE"' . "\r\n"
            . 'TEXT 20,90,"3",0,1,1,"' . $product . '"' . "\r\n"
            . 'TEXT 20,166,"2",0,1,1,"PALLET: ' . $palletCode . '"' . "\r\n"
            . 'TEXT 20,194,"2",0,1,1,"OT: ' . $otCode . '"' . "\r\n"
            . 'TEXT 20,222,"2",0,1,1,"BOBINA ORIGEN: ' . $sourceRoll . '"' . "\r\n"
            . 'TEXT 20,250,"2",0,1,1,"CAJAS: ' . $boxCount . '"' . "\r\n"
            . 'TEXT 20,278,"2",0,1,1,"OPERADOR: ' . $operator . '"' . "\r\n"
            . 'TEXT 20,306,"2",0,1,1,"DESTINO: ' . $destinationLabel . '"' . "\r\n"
            . 'TEXT 20,334,"2",0,1,1,"CODIGOS CAJA: ' . $boxSummary . '"' . "\r\n"
            . 'BARCODE 60,402,"128",92,1,0,2,2,"' . $palletCode . '"' . "\r\n"
            . 'TEXT 170,554,"3",0,1,1,"' . $palletCode . '"' . "\r\n"
            . 'PRINT 1,1';
    }

    private function buildWorkOrderBoxEpl(array $workOrder, ?array $finish = null): string
    {
        $otCode = strtoupper($this->t((string)($workOrder['ot_code'] ?? '')));
        $product = strtoupper($this->t((string)($workOrder['sku_final'] ?? '')));
        $boxQty = strtoupper($this->t((string)($finish['box_qty'] ?? '')));
        $operator = strtoupper($this->t((string)($finish['operator_name'] ?? '')));
        $finishedAt = strtoupper($this->t((string)($finish['created_at'] ?? '')));
        $barcodeValue = $otCode !== '' ? $otCode : 'OT';

        return implode("\r\n", [
            'N',
            'q800',
            'Q560,24',
            'LO20,68,750,2',
            'LO20,136,750,1',
            'LO20,268,750,1',
            'A20,18,0,3,1,1,N,"UNIBAG"',
            'A20,44,0,2,1,1,N,"CAJA PRODUCTO TERMINADO"',
            'A20,78,0,3,1,1,N,"' . $product . '"',
            'A20,146,0,2,1,1,N,"OT: ' . $otCode . '"',
            'A380,146,0,2,1,1,N,"CANTIDAD CAJAS: ' . $boxQty . '"',
            'A20,176,0,2,1,1,N,"FECHA CIERRE: ' . $finishedAt . '"',
            'A20,206,0,2,1,1,N,"OPERADOR: ' . $operator . '"',
            'B100,286,0,1,2,6,72,N,"' . $barcodeValue . '"',
            'A240,370,0,3,1,1,N,"' . $barcodeValue . '"',
            'P1',
        ]) . "\r\n";
    }

    private function buildBoxEpl(array $box): string
    {
        $boxCode = strtoupper($this->t((string)($box['box_code'] ?? '')));
        $otCode = strtoupper($this->t((string)($box['ot_code'] ?? '-')));
        $product = strtoupper($this->t((string)($box['final_sku'] ?? '')));
        $sourceRoll = strtoupper($this->t((string)($box['source_roll_code'] ?? '-')));
        $units = strtoupper($this->t((string)($box['units_qty'] ?? '0')));
        $palletCode = strtoupper($this->t((string)($box['pallet_code'] ?? '-')));
        $operator = strtoupper($this->t((string)($box['operator_name'] ?? '-')));
        $destinationMode = (string)($box['destination_mode'] ?? '');
        $customerOrderRef = (string)($box['customer_order_ref'] ?? '');
        $warehouseCode = (string)($box['warehouse_code'] ?? '');
        $destinationLabel = strtoupper($this->t(
            $destinationMode === 'CUSTOMER_ORDER'
                ? ('OC CLIENTE: ' . ($customerOrderRef !== '' ? $customerOrderRef : '-'))
                : ('BODEGA: ' . ($warehouseCode !== '' ? $warehouseCode : '-'))
        ));

        return implode("\r\n", [
            'N',
            'q800',
            'Q560,24',
            'LO20,68,750,2',
            'LO20,136,750,1',
            'LO20,286,750,1',
            'A20,18,0,3,1,1,N,"UNIBAG"',
            'A20,44,0,2,1,1,N,"CAJA TRAZABLE"',
            'A20,78,0,3,1,1,N,"' . $product . '"',
            'A20,146,0,2,1,1,N,"CAJA: ' . $boxCode . '"',
            'A380,146,0,2,1,1,N,"OT: ' . $otCode . '"',
            'A20,176,0,2,1,1,N,"BOBINA ORIGEN: ' . $sourceRoll . '"',
            'A380,176,0,2,1,1,N,"UNIDADES: ' . $units . '"',
            'A20,206,0,2,1,1,N,"PALLET: ' . $palletCode . '"',
            'A380,206,0,2,1,1,N,"OPERADOR: ' . $operator . '"',
            'A20,236,0,2,1,1,N,"DESTINO: ' . $destinationLabel . '"',
            'B100,306,0,1,2,6,72,N,"' . $boxCode . '"',
            'A240,390,0,3,1,1,N,"' . $boxCode . '"',
            'P1',
        ]) . "\r\n";
    }

    private function buildPalletEpl(array $pallet, array $boxes = []): string
    {
        $palletCode = strtoupper($this->t((string)($pallet['pallet_code'] ?? '')));
        $otCode = strtoupper($this->t((string)($pallet['ot_code'] ?? '-')));
        $product = strtoupper($this->t((string)($pallet['final_sku'] ?? '')));
        $sourceRoll = strtoupper($this->t((string)($pallet['source_roll_code'] ?? '-')));
        $boxCount = strtoupper($this->t((string)($pallet['box_count'] ?? '0')));
        $operator = strtoupper($this->t((string)($pallet['operator_name'] ?? '-')));
        $destinationMode = (string)($pallet['destination_mode'] ?? '');
        $customerOrderRef = (string)($pallet['customer_order_ref'] ?? '');
        $warehouseCode = (string)($pallet['warehouse_code'] ?? '');
        $destinationLabel = strtoupper($this->t(
            $destinationMode === 'CUSTOMER_ORDER'
                ? ('OC CLIENTE: ' . ($customerOrderRef !== '' ? $customerOrderRef : '-'))
                : ('BODEGA: ' . ($warehouseCode !== '' ? $warehouseCode : '-'))
        ));

        $boxCodes = [];
        foreach ($boxes as $box) {
            $code = trim((string)($box['box_code'] ?? ''));
            if ($code !== '') {
                $boxCodes[] = $code;
            }
            if (count($boxCodes) >= 4) {
                break;
            }
        }
        $boxSummary = $boxCodes === [] ? '-' : implode(', ', $boxCodes);
        if (count($boxes) > count($boxCodes)) {
            $boxSummary .= '...';
        }
        $boxSummary = strtoupper($this->t($boxSummary));

        return implode("\r\n", [
            'N',
            'q800',
            'Q560,24',
            'LO20,68,750,2',
            'LO20,136,750,1',
            'LO20,296,750,1',
            'A20,18,0,3,1,1,N,"UNIBAG"',
            'A20,44,0,2,1,1,N,"PALLET TRAZABLE"',
            'A20,78,0,3,1,1,N,"' . $product . '"',
            'A20,146,0,2,1,1,N,"PALLET: ' . $palletCode . '"',
            'A380,146,0,2,1,1,N,"OT: ' . $otCode . '"',
            'A20,176,0,2,1,1,N,"BOBINA ORIGEN: ' . $sourceRoll . '"',
            'A380,176,0,2,1,1,N,"CAJAS: ' . $boxCount . '"',
            'A20,206,0,2,1,1,N,"OPERADOR: ' . $operator . '"',
            'A20,236,0,2,1,1,N,"DESTINO: ' . $destinationLabel . '"',
            'A20,266,0,1,1,1,N,"CODIGOS CAJA: ' . $boxSummary . '"',
            'B100,314,0,1,2,6,72,N,"' . $palletCode . '"',
            'A240,398,0,3,1,1,N,"' . $palletCode . '"',
            'P1',
        ]) . "\r\n";
    }
}
