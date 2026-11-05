<?php
declare(strict_types=1);
// Tamaño del código de barras en todas las etiquetas térmicas, en ZPL, TSPL y EPL.
require_once __DIR__ . '/../../src/PrintService.php';

$failures = 0;
function checkBar(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures++;
    }
}

/** @return array{0:int,1:int,2:int,3:int,4:string}|null módulo, alto, x, y, valor */
function readBarcode(string $lang, string $cmd): ?array
{
    if ($lang === 'zpl' && preg_match('/\^BY(\d+),\d+,\d+\s*\^FO(\d+),(\d+)\^BCN,(\d+),N,N,N\^FD([^\^]*)\^FS/', $cmd, $m)) {
        return [(int)$m[1], (int)$m[4], (int)$m[2], (int)$m[3], $m[5]];
    }
    if ($lang === 'tspl' && preg_match('/^BARCODE (\d+),(\d+),"128",(\d+),0,0,(\d+),\d+,"([^"]*)"/m', $cmd, $m)) {
        return [(int)$m[4], (int)$m[3], (int)$m[1], (int)$m[2], $m[5]];
    }
    if ($lang === 'epl' && preg_match('/^B(\d+),(\d+),0,1,(\d+),\d+,(\d+),N,"([^"]*)"/m', $cmd, $m)) {
        return [(int)$m[3], (int)$m[4], (int)$m[1], (int)$m[2], $m[5]];
    }
    return null;
}

/** Alturas de las líneas de texto y trazos de la etiqueta. */
function contentRows(string $lang, string $cmd): array
{
    $pattern = ['zpl' => '/\^FO\d+,(\d+)\^(?:A0|FB|GB|FD)/', 'tspl' => '/^(?:TEXT|BAR) \d+,(\d+),/m', 'epl' => '/^(?:A|LO)\d+,(\d+),/m'][$lang];
    preg_match_all($pattern, $cmd, $m);
    return array_map('intval', $m[1]);
}

foreach (['zpl', 'tspl', 'epl'] as $lang) {
    $printer = new PrintService(['PRINT_LANGUAGE' => $lang]);
    foreach (['BOX-000123', 'TRZ-2026-11-05-000123-ABCD'] as $code) {
        $labels = [
            'bobina' => $printer->buildRollCommand(['id' => 1, 'roll_code' => $code, 'sku_code' => 'SKU']),
            'caja OT' => $printer->buildWorkOrderBoxCommand(['ot_code' => $code, 'sku_final' => 'SKU']),
            'caja' => $printer->buildBoxCommand(['box_code' => $code, 'final_sku' => 'SKU']),
            'pallet' => $printer->buildPalletCommand(['pallet_code' => $code, 'final_sku' => 'SKU']),
            'ingreso' => $printer->buildTraceabilityEntryCommand(['traceability_code' => $code, 'item_code' => 'SKU', 'item_description' => 'X', 'quantity' => 1]),
        ];
        foreach ($labels as $name => $cmd) {
            $tag = strtoupper($lang) . ' ' . $name . ' (' . strlen($code) . ' caracteres)';
            $bar = readBarcode($lang, $cmd);
            checkBar($tag . ': tiene código con el valor correcto', $bar !== null && $bar[4] === $code);
            if ($bar === null) {
                continue;
            }
            [$module, $height, $x, $y] = $bar;
            $width = (11 * strlen($code) + 35) * $module;
            checkBar($tag . ': módulo 0,25 mm y 10 mm de alto', $module === 2 && $height === 80);
            checkBar($tag . ': centrado y con margen para que lea la pistola', abs($x - (812 - $width) / 2) <= 1 && $x >= 2 * 10 && $x + $width <= 812 - 2 * 10);
            $inside = array_filter(contentRows($lang, $cmd), static fn(int $row): bool => $row >= $y && $row < $y + $height);
            $below = array_filter(contentRows($lang, $cmd), static fn(int $row): bool => $row >= $y + $height);
            checkBar($tag . ': no pisa ningún texto y el número queda debajo', $inside === [] && $below !== []);
        }
    }
}

echo $failures === 0 ? "\nCODIGO DE BARRAS OK\n" : "\nCODIGO DE BARRAS: {$failures} fallas\n";
exit($failures === 0 ? 0 : 1);
