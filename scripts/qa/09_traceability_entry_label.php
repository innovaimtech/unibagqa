<?php
declare(strict_types=1);
// Etiqueta de Ingreso con Trazabilidad: muestra la cantidad del bulto y usa toda la etiqueta de 100x70 mm (sin impresora).
$root = dirname(__DIR__, 2);
require_once $root . '/src/bootstrap.php';

$fail = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . ($extra !== '' && !$ok ? " [$extra]" : '') . "\n";
    if (!$ok) {
        $fail++;
    }
};

$entry = ['traceability_code' => 'TRZ5C4687', 'item_code' => 'BOL0352', 'item_description' => 'BOLSA KRAFT 30X40 CON MANILLA PLANA IMPRESA 2 COLORES LOGO TOTTUS',
    'quantity' => 150, 'quantity_initial' => 200, 'unit_name' => 'Unid.', 'package_number' => 3, 'total_packages' => 10,
    'warehouse_name' => '400 (PROD TERMINADOS REVENTA)', 'operator_name' => 'Gabriel Muñoz', 'created_at' => '2026-11-12 10:15:22',
    'order_ref' => '26-00808-1', 'entity_type' => 'PALLET', 'box_count' => 50, 'lot_number' => 'L-77'];

$check('unidad legible', PrintService::entryUnitLabel('Unid.') === 'UNIDADES' && PrintService::entryUnitLabel('C/U') === 'UNIDADES'
    && PrintService::entryUnitLabel('BOB') === 'BOBINAS' && PrintService::entryUnitLabel('KG') === 'KG');
$check('cantidad con miles chilenos', PrintService::entryQuantityText(125000) === '125.000' && PrintService::entryQuantityText(2.5) === '2,5'
    && PrintService::entryQuantityText(200) === '200');

// TSPL: medidas exactas de las fuentes internas para comprobar que nada se sale de la etiqueta.
$tsplFont = [1 => [8, 12], 2 => [12, 20], 3 => [16, 24], 4 => [24, 32], 5 => [32, 48]];
foreach (['tspl', 'zpl', 'epl'] as $lang) {
    $cmd = (new PrintService(['PRINT_LANGUAGE' => $lang]))->buildTraceabilityEntryCommand($entry);
    $L = strtoupper($lang);
    $check("$L: cantidad original del bulto (200, no el saldo 150) en el recuadro", preg_match('/[",D]200("|\^FS)/', $cmd) === 1 && !preg_match('/[",D]150("|\^FS)/', $cmd)
        && str_contains($cmd, 'CANTIDAD') && str_contains($cmd, 'UNIDADES'));
    $check("$L: bulto, referencia, SKU y operador", str_contains($cmd, 'BULTO 3 DE 10') && str_contains($cmd, 'CC 26-00808-1') && str_contains($cmd, 'BOL0352')
        && str_contains($cmd, '50 CAJAS') && str_contains($cmd, '12/11/2026 10:15 - OP: GABRIEL MUNOZ'));
    if ($lang === 'tspl') {
        preg_match_all('/^TEXT (\d+),(\d+),"(\d)",0,(\d),\d,"([^"]*)"/m', $cmd, $m, PREG_SET_ORDER);
        $out = [];
        $maxY = 0;
        foreach ($m as [, $x, $y, $font, $mult, $value]) {
            [$cw, $ch] = $tsplFont[(int)$font];
            $right = (int)$x + strlen($value) * $cw * (int)$mult;
            $bottom = (int)$y + $ch * (int)$mult;
            $maxY = max($maxY, $bottom);
            if ($right > 812 - 8 || $bottom > 560 - 8) {
                $out[] = $value;
            }
        }
        $check('TSPL: ningún texto se sale de la etiqueta', $out === [], implode(' | ', $out));
        $check('TSPL: usa todo el alto (hasta el pie)', $maxY >= 530, (string)$maxY);
        $check('TSPL: recuadro de cantidad a la derecha', str_contains($cmd, 'BOX 476,224,776,502,5'));
        $check('TSPL: SKU grande (al menos 64 puntos de alto)', preg_match('/TEXT 36,226,"(\d)",0,(\d),\d,"BOL0352"/', $cmd, $sk) === 1 && $tsplFont[(int)$sk[1]][1] * (int)$sk[2] >= 64);
        $long = (new PrintService(['PRINT_LANGUAGE' => 'tspl']))->buildTraceabilityEntryCommand(['quantity' => 125000, 'quantity_initial' => null] + $entry);
        $check('TSPL: cantidad grande cabe en el recuadro', preg_match('/TEXT (\d+),\d+,"(\d)",0,(\d),\d,"125\.000"/', $long, $q) === 1 && (int)$q[1] >= 476
            && (int)$q[1] + 7 * $tsplFont[(int)$q[2]][0] * (int)$q[3] <= 776);
    }
}

// Etiqueta del navegador (vista previa e impresión con el navegador).
$src = (string)file_get_contents($root . '/src/Http/InventoryModule.php');
$check('navegador: la etiqueta muestra CANTIDAD con la cantidad original y la unidad', str_contains($src, 'function trzLabelQty(data)') && str_contains($src, 'data.quantity_initial')
    && str_contains($src, '<small>CANTIDAD</small>') && str_contains($src, 'trzLabelUnit(data.unit_name)'));
$check('navegador: etiqueta de 100x70 mm con el mismo estilo en pantalla e impresión', str_contains($src, '.trz-lbl{width:100mm;height:70mm') && substr_count($src, 'TRZ_LABEL_CSS') >= 3);

// Bobina como la de la foto: la descripción se corta en «/» (no en medio de un número) y se agregan las medidas.
$roll = ['traceability_code' => 'RB-20261028-4ID4GQ', 'item_code' => 'TEL0180', 'item_description' => 'PLA/BLANCO/W60/108X90X1400', 'quantity' => 1,
    'unit_name' => 'BOB', 'warehouse_name' => '100 (MP PLA)', 'operator_name' => 'Gabriel Rodolfo Munoz Messina', 'created_at' => '2026-10-28 14:17:00',
    'width_mm' => 1080, 'microns' => 90, 'meters' => 1400];
foreach (['epl', 'tspl', 'zpl'] as $lang) {
    $cmd = (new PrintService(['PRINT_LANGUAGE' => $lang]))->buildTraceabilityEntryCommand($roll);
    $check(strtoupper($lang) . ': bobina con descripción cortada en «/», medidas y 1 BOBINAS', (str_contains($cmd, '"108X90X1400"') || str_contains($cmd, '^FD108X90X1400^FS'))
        && str_contains($cmd, 'ANCHO 1080 MM - 90 G - 1.400 M') && str_contains($cmd, 'BOBINAS') && !str_contains($cmd, '108X90X"'));
}
$check('EPL: no usa la fuente 5 (el «1» se veía raro)', !preg_match('/^A\d+,\d+,0,5,/m', (new PrintService(['PRINT_LANGUAGE' => 'epl']))->buildTraceabilityEntryCommand($roll)));

// Cada computador recibe la etiqueta en el lenguaje de SU impresora (cookie del panel de Hardware).
$_COOKIE['unibag_print_lang'] = 'tspl';
$ps = new PrintService(['PRINT_LANGUAGE' => 'epl']);
$job = $ps->buildClientJob($ps->buildTraceabilityEntryCommand($entry), 1, 'x');
$check('PC con Barpos (TSPL): la etiqueta se arma en TSPL aunque el servidor diga EPL', $job['language'] === 'tspl' && str_contains($job['content'], 'SIZE 100.00 mm'));
$_COOKIE['unibag_print_lang'] = 'zpl';
$check('PC con Zebra: ZPL', str_starts_with((new PrintService(['PRINT_LANGUAGE' => 'epl']))->buildTraceabilityEntryCommand($entry), '^XA'));
$_COOKIE['unibag_print_lang'] = 'xyz';
$check('cookie inválida: se usa el lenguaje del servidor', (new PrintService(['PRINT_LANGUAGE' => 'epl']))->getConfiguredPrintLanguage() === 'epl');
unset($_COOKIE['unibag_print_lang']);
$check('sin cookie: lenguaje del servidor', (new PrintService(['PRINT_LANGUAGE' => 'epl']))->getConfiguredPrintLanguage() === 'epl');

$hw = (string)file_get_contents($root . '/public/assets/js/unibag-hardware.js');
$check('navegador: guarda el tipo de impresora de esta PC en la cookie', str_contains($hw, 'function syncClientPrinterLanguageCookie()') && str_contains($hw, 'unibag_print_lang='));
$check('COM: el lote se envía en tramos (no se corta en lotes grandes)', str_contains($hw, 'bytes.subarray(off, Math.min(bytes.length, off + CHUNK))'));
$check('COM/USB de esta PC no disponible: se imprime por el servicio Windows de la misma PC', str_contains($hw, 'var ownLinkError') && str_contains($hw, 'await probeLocalPrintBridge(true);'));

echo $fail === 0 ? "\nETIQUETA DE INGRESO OK\n" : "\nETIQUETA DE INGRESO: $fail fallas\n";
exit($fail > 0 ? 1 : 0);
