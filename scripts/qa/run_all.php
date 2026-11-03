<?php
declare(strict_types=1);

/**
 * Verificación integral (sin base de datos ni servidor):
 *   php scripts/qa/run_all.php
 *
 * 01 Flujo de áreas/permisos (nadie rebota a otra área ni en bucle)
 * 02 Todo enlace/formulario/redirección interna apunta a una ruta existente
 * 03 Sin llamadas a funciones o métodos de servicio inexistentes
 * 04 Todas las vistas y require/include resuelven a un archivo real
 */
$php = PHP_BINARY;
$exit = 0;
foreach (glob(__DIR__ . '/0*.php') as $script) {
    echo "\n=== " . basename($script) . " ===\n";
    passthru('"' . $php . '" "' . $script . '"', $rc);
    if ($rc !== 0) { $exit = 1; echo "(codigo de salida $rc)\n"; }
}
echo $exit === 0 ? "\nTODO OK\n" : "\nHAY FALLOS: revisar arriba\n";
exit($exit);