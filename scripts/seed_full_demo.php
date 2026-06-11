<?php

declare(strict_types=1);

$phpBinary = PHP_BINARY;
if (!is_file($phpBinary)) {
    throw new RuntimeException('No se pudo detectar el ejecutable de PHP actual.');
}

$baseDir = realpath(__DIR__ . '/..');
if ($baseDir === false) {
    throw new RuntimeException('No se pudo resolver la carpeta base del proyecto.');
}

$steps = [
    [
        'label' => 'Esquema minimo ERP produccion',
        'script' => __DIR__ . '/ensure_erp_production_schema.php',
    ],
    [
        'label' => 'Limpieza previa demo',
        'script' => __DIR__ . '/cleanup_demo_data.php',
    ],
    [
        'label' => 'Trazabilidad local demo',
        'script' => __DIR__ . '/seed_demo_flow.php',
    ],
    [
        'label' => 'Recepción ERP demo',
        'script' => __DIR__ . '/seed_reception_demo.php',
    ],
    [
        'label' => 'Producción ERP demo',
        'script' => __DIR__ . '/seed_erp_production_demo.php',
    ],
    [
        'label' => 'Sincronización ERP -> OT',
        'script' => __DIR__ . '/sync_erp_production_plan.php',
    ],
];

$summary = [];
foreach ($steps as $index => $step) {
    $scriptPath = $step['script'];
    if (!is_file($scriptPath)) {
        throw new RuntimeException('No existe el script requerido: ' . $scriptPath);
    }

    echo PHP_EOL;
    echo '============================================================' . PHP_EOL;
    echo 'Paso ' . ($index + 1) . '/' . count($steps) . ': ' . $step['label'] . PHP_EOL;
    echo basename($scriptPath) . PHP_EOL;
    echo '============================================================' . PHP_EOL;

    $result = runPhpScript($phpBinary, $scriptPath, $baseDir);
    echo $result['output'];

    if ($result['exit_code'] !== 0) {
        throw new RuntimeException(
            'Falló el paso "' . $step['label'] . '" con código ' . $result['exit_code'] . '.'
        );
    }

    $summary[] = [
        'step' => $step['label'],
        'script' => basename($scriptPath),
        'exit_code' => $result['exit_code'],
    ];
}

echo PHP_EOL;
echo json_encode([
    'ok' => true,
    'message' => 'Seed completo ejecutado correctamente.',
    'steps' => $summary,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

/**
 * @return array{exit_code:int,output:string}
 */
function runPhpScript(string $phpBinary, string $scriptPath, string $workingDirectory): array
{
    $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($scriptPath);
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $workingDirectory);
    if (!is_resource($process)) {
        throw new RuntimeException('No se pudo iniciar el proceso para ' . basename($scriptPath));
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    $output = (string)$stdout;
    if (trim((string)$stderr) !== '') {
        $output .= ($output !== '' && !str_ends_with($output, PHP_EOL) ? PHP_EOL : '') . $stderr;
    }
    if ($output !== '' && !str_ends_with($output, PHP_EOL)) {
        $output .= PHP_EOL;
    }

    return [
        'exit_code' => $exitCode,
        'output' => $output,
    ];
}
