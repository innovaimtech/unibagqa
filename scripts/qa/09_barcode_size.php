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

// ------------------------------------------------- Etiquetas del navegador
require_once __DIR__ . '/../../src/Http/KernelHelpers.php';

/** Lee las barras de un SVG y devuelve el texto codificado, o null si el código no es válido. */
function decodeSvgText(string $svg): ?string
{
    preg_match_all('/<rect x="(\d+)" y="0" width="(\d+)"/', $svg, $m, PREG_SET_ORDER);
    $widths = [];
    $end = null;
    foreach ($m as $bar) {
        if ($end !== null) {
            $widths[] = (int)$bar[1] - $end;
        }
        $widths[] = (int)$bar[2];
        $end = (int)$bar[1] + (int)$bar[2];
    }
    if (count($widths) < 25 || implode('', array_slice($widths, -7)) !== '2331112') {
        return null;
    }
    $patterns = array_flip(code128Patterns());
    $values = [];
    foreach (array_chunk(array_slice($widths, 0, -7), 6) as $chunk) {
        $key = implode('', $chunk);
        if (!isset($patterns[$key])) {
            return null;
        }
        $values[] = $patterns[$key];
    }
    $start = array_shift($values);
    $check = array_pop($values);
    $sum = $start;
    $text = '';
    foreach ($values as $i => $value) {
        $sum += $value * ($i + 1);
        $text .= chr($value + 32);
    }
    return $start === 104 && $sum % 103 === $check ? $text : null;
}

$svgCode = 'TRZ-20261105-A1B2C3';
$svg = code128Svg($svgCode);
checkBar('navegador: el código se lee igual que el texto', decodeSvgText($svg) === $svgCode);
preg_match('/width="([\d.]+)mm" height="([\d.]+)mm" viewBox="0 0 (\d+) /', $svg, $size);
checkBar('navegador: módulo 0,25 mm y 10 mm de alto', isset($size[3]) && abs((float)$size[1] / (int)$size[3] - 0.25) < 0.0001 && (float)$size[2] === 10.0);
checkBar('navegador: con su margen cabe en la etiqueta', (float)$size[1] < 90);

// El dibujo de la pantalla de ingreso debe dar exactamente las mismas barras.
$module = (string)file_get_contents(__DIR__ . '/../../src/Http/InventoryModule.php');
$noCode39 = !str_contains($module, 'buildCode39Svg');
checkBar('ingreso: ya no dibuja Code 39', $noCode39);
checkBar('ingreso: no estira el código al ancho de la etiqueta', !preg_match('/barcode-svg-wrap svg \{[^}]*(?<!-)width:\s*100%/', $module));
$node = strtok(trim((string)shell_exec(stripos(PHP_OS, 'WIN') === 0 ? 'where node 2>NUL' : 'command -v node 2>/dev/null')), "\r\n");
if ($node !== false && $node !== '' && preg_match('/function buildCode128Svg\(value\) \{.*?\R        \}\R/s', $module, $js)) {
    $source = str_replace(
        ["' . BARCODE_HEIGHT_MM . '", "' . BARCODE_MODULE_MM . '"],
        [(string)BARCODE_HEIGHT_MM, (string)BARCODE_MODULE_MM],
        $js[0]
    );
    $script = tempnam(sys_get_temp_dir(), 'bc') . '.js';
    file_put_contents($script, 'var CODE128_PATTERNS=' . json_encode(code128Patterns()) . ';function escapeHtml(s){return s;}' . $source
        . 'process.stdout.write(buildCode128Svg(' . json_encode(strtolower($svgCode)) . '));');
    $jsSvg = (string)shell_exec(escapeshellarg($node) . ' ' . escapeshellarg($script));
    unlink($script);
    checkBar('ingreso: mismas barras que el resto del sistema', decodeSvgText($jsSvg) === $svgCode && str_contains($jsSvg, 'width="' . $size[1] . 'mm"'));
} else {
    checkBar('ingreso: se encontró el dibujo del código', $node === false || $node === '');
}

echo $failures === 0 ? "\nCODIGO DE BARRAS OK\n" : "\nCODIGO DE BARRAS: {$failures} fallas\n";
exit($failures === 0 ? 0 : 1);
