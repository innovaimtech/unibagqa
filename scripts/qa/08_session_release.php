<?php
declare(strict_types=1);
// Liberación del bloqueo de sesión en páginas de solo lectura: dos procesos reales usando la misma sesión.
$root = dirname(__DIR__, 2);
$php = PHP_BINARY;
$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'unibag_qa_sess_' . getmypid();
@mkdir($dir);

if (($argv[1] ?? '') === 'holder') {
    // Simula la página pesada: abre la sesión, aplica la regla y sigue "calculando" 1,5 s.
    [, , $path, $method, $withQueue, $sid, $sessDir] = $argv;
    ob_start();
    ini_set('session.save_path', $sessDir);
    session_id($sid);
    session_start();
    require $root . '/src/bootstrap.php';
    if ($withQueue === '1') {
        $_SESSION['unibag_client_print_queue'] = [['job' => 1]];
    }
    unibagReleaseSessionLockForReadOnlyPage($path, $method);
    $_SESSION['escrito_tarde'] = 'si';
    $_SESSION['borrar'] = null;
    unset($_SESSION['borrar']);
    usleep(1500000);
    exit(0);
}
if (($argv[1] ?? '') === 'reader') {
    [, , $sid, $sessDir] = $argv;
    ob_start();
    ini_set('session.save_path', $sessDir);
    session_id($sid);
    $t = microtime(true);
    session_start();
    $ms = (microtime(true) - $t) * 1000;
    $out = json_encode(['ms' => $ms, 'keys' => array_keys($_SESSION), 'tarde' => $_SESSION['escrito_tarde'] ?? null]);
    session_write_close();
    ob_end_clean();
    echo $out;
    exit(0);
}

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $fail++;
    }
};
$self = __FILE__;
$scenario = function (string $path, string $method, string $queue) use ($php, $self, $dir): array {
    $sid = 'qa' . bin2hex(random_bytes(8));
    file_put_contents($dir . '/sess_' . $sid, 'previo|s:1:"x";borrar|s:1:"y";');
    $null = stripos(PHP_OS, 'WIN') === 0 ? 'NUL' : '/dev/null';
    $h = proc_open([$php, $self, 'holder', $path, $method, $queue, $sid, $dir], [1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes);
    usleep(800000); // tiempo para que el proceso cargue el sistema y tome la sesión
    $first = json_decode((string)shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($self) . ' reader ' . $sid . ' ' . escapeshellarg($dir)), true);
    proc_close($h);
    $last = json_decode((string)shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($self) . ' reader ' . $sid . ' ' . escapeshellarg($dir)), true);
    return ['first' => $first, 'last' => $last];
};

$r = $scenario('/stock', 'GET', '0');
$check('GET /stock no bloquea a la otra solicitud de la misma sesión', ($r['first']['ms'] ?? 9999) < 300);
$check('lo escrito en $_SESSION después de liberar se guarda', ($r['last']['tarde'] ?? null) === 'si');
$check('los datos previos de la sesión se conservan', in_array('previo', $r['last']['keys'] ?? [], true));
$check('lo borrado después de liberar queda borrado', !in_array('borrar', $r['last']['keys'] ?? [], true));

$r = $scenario('/production/machines', 'GET', '0');
$check('Producción mantiene el bloqueo (imprime por GET)', ($r['first']['ms'] ?? 0) > 300);

$r = $scenario('/reception', 'GET', '0');
$check('Recepción mantiene el bloqueo (imprime por GET)', ($r['first']['ms'] ?? 0) > 300);

$r = $scenario('/stock', 'POST', '0');
$check('POST mantiene el bloqueo', ($r['first']['ms'] ?? 0) > 300);

$r = $scenario('/stock', 'GET', '1');
$check('con etiquetas en cola no se libera (hay que entregarlas)', ($r['first']['ms'] ?? 0) > 300);

array_map('unlink', glob($dir . '/*') ?: []);
@rmdir($dir);
echo $fail === 0 ? "\nLIBERACION DE SESION OK\n" : "\nLIBERACION DE SESION: $fail fallas\n";
exit($fail === 0 ? 0 : 1);
