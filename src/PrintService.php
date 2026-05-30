<?php

declare(strict_types=1);

final class PrintService
{
    public function __construct(private array $env)
    {
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

        $zpl = $this->buildZpl($roll);
        return $this->sendZpl($zpl, $copies, $printerName, $url);
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

        $zpl = $this->buildWorkOrderBoxZpl($workOrder, $finish);
        return $this->sendZpl($zpl, $copies, $printerName, $url);
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

        $zpl = $this->buildBoxZpl($box);
        return $this->sendZpl($zpl, $copies, $printerName, $url);
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

        $zpl = $this->buildPalletZpl($pallet, $boxes);
        return $this->sendZpl($zpl, $copies, $printerName, $url);
    }

    private function sendZpl(string $zpl, int $copies, string $printerName, string $url): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'cURL no está disponible en PHP.'];
        }

        $payload = json_encode([
            'printer' => $printerName,
            'copies' => $copies,
            'zpl' => $zpl,
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
            return ['ok' => false, 'error' => $err !== '' ? $err : 'No se pudo imprimir.'];
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return ['ok' => false, 'error' => 'Respuesta HTTP inválida: ' . $httpCode, 'raw' => (string)$raw];
        }

        $data = json_decode((string)$raw, true);
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

    private function formatLabelDate(?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '-';
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return $this->z($value);
        }

        return date('d-m-Y', $ts);
    }

    private function buildZpl(array $roll): string
    {
        $id = (string)($roll['id'] ?? '');
        $barcodeValue = $this->z($id);
        $sku = $this->z((string)($roll['sku_code'] ?? ''));
        $product = $this->z((string)($roll['sku_description'] ?? ''));
        $arrivalDate = $this->formatLabelDate((string)($roll['arrival_date'] ?? $roll['created_at'] ?? ''));
        $containerCode = $this->z((string)($roll['container_code'] ?? ''));
        $grams = $this->z((string)($roll['grams'] ?? $roll['microns'] ?? ''));
        $meters = $this->z((string)($roll['meters'] ?? ''));

        return "^XA
^CI28
^PW600
^LL640
^CF0,40
^FO20,20^FDUNIBAG^FS
^CF0,24
^FO20,64^FDETIQUETA RECEPCION^FS
^FO20,96^GB560,2,2^FS

^CF0,24
^FO20,112^FDSKU:^FS
^CF0,34
^FO110,108^FD{$sku}^FS

^CF0,24
^FO20,150^FDDESCRIPCION:^FS
^CF0,28
^FO20,180^FB560,2,4,L,0^FD{$product}^FS

^CF0,24
^FO20,250^FDFECHA ARRIBO:^FS
^FO320,250^FDSERIAL CONTENEDOR:^FS
^CF0,28
^FO20,280^FD{$arrivalDate}^FS
^FO320,280^FD{$containerCode}^FS

^CF0,24
^FO20,326^FDUNIDAD MEDIDA:^FS
^FO320,326^FDCODIGO BULTO:^FS
^CF0,28
^FO20,356^FDGRAMOS / METRO^FS
^FO320,356^FD{$barcodeValue}^FS

^CF0,24
^FO20,402^FDGRAMOS:^FS
^FO320,402^FDMETRO:^FS
^CF0,28
^FO20,432^FD{$grams}^FS
^FO320,432^FD{$meters}^FS

^BY2,2,80
^FO60,476^BCN,74,Y,N,N^FD{$barcodeValue}^FS
^CF0,34
^FO0,586^FB600,1,0,C,0^FD{$barcodeValue}^FS
^XZ";
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
}
