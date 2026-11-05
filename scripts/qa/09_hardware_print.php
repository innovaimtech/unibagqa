<?php
declare(strict_types=1);
// Impresión directa (puertos simulados) e intensidad/velocidad. Necesita Node; sin él, la prueba se omite.
$node = trim((string)shell_exec(stripos(PHP_OS, 'WIN') === 0 ? 'where node 2>NUL' : 'command -v node 2>/dev/null'));
$node = strtok($node, "\r\n");
if ($node === false || $node === '') {
    echo "Node no disponible: prueba omitida\n";
    exit(0);
}
passthru(escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/js/hardware_print_test.js'), $code);
exit((int)$code);
