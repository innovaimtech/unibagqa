<?php
declare(strict_types=1);
// Código de trazabilidad corto: formato, sin repetidos y compatibilidad con las etiquetas ya impresas.
require_once __DIR__ . '/../../src/Services/Reception/RollStockService.php';

$failures = 0;
function checkCode(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures++;
    }
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE traceability_warehouse_entries (traceability_code TEXT UNIQUE)');
$service = new RollStockService($pdo, $pdo);

foreach (['PRODUCT' => 'TRZ', 'PALLET' => 'PL', 'BOX' => 'BX', 'ROLL' => 'RB'] as $type => $prefix) {
    $code = $service->generateTraceabilityCode($type);
    checkCode($type . ': prefijo + 6 caracteres, sin guiones ni fecha (' . $code . ')', preg_match('/^' . $prefix . '[0-9A-F]{6}$/', $code) === 1);
}

// Las tres primeras consultas responden que el código ya existe.
$tried = [];
$colliding = new class('sqlite::memory:') extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare('SELECT 1 WHERE qa_taken(?) = 1');
    }
};
$colliding->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$colliding->sqliteCreateFunction('qa_taken', static function (string $code) use (&$tried): ?int {
    $tried[] = $code;
    return count($tried) <= 3 ? 1 : null;
}, 1);
$final = (new RollStockService($colliding, $colliding))->generateTraceabilityCode('PRODUCT');
checkCode('código ya usado: prueba otro y entrega uno libre', count($tried) === 4 && $final === end($tried) && count(array_unique($tried)) === 4);

// Las etiquetas ya impresas se buscan por el código exacto: nada debe exigir el formato anterior.
$root = __DIR__ . '/../../src';
$offenders = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() === 'php'
        && preg_match("/(LIKE\s+['\"]TRZ-|\^TRZ-|str_starts_with\([^)]*['\"]TRZ-|strpos\([^)]*['\"]TRZ-)/", (string)file_get_contents($file->getPathname()))) {
        $offenders[] = basename($file->getPathname());
    }
}
checkCode('ningún archivo depende de la forma TRZ-', $offenders === []);

$module = (string)file_get_contents($root . '/Http/InventoryModule.php');
checkCode('el botón Nuevo usa el generador del servidor', str_contains($module, '/stock/traceability-code-suggest?type=') && !str_contains($module, 'Math.random().toString(36)'));

echo $failures === 0 ? "\nCODIGO DE TRAZABILIDAD OK\n" : "\nCODIGO DE TRAZABILIDAD: {$failures} fallas\n";
exit($failures === 0 ? 0 : 1);
