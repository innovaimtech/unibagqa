<?php
declare(strict_types=1);
// Caché de resultados (ResultCache): guarda, vence, no recalcula dentro del plazo y entrega lo vencido reciente.
require_once __DIR__ . '/../../src/ResultCache.php';

$failures = 0;
function checkRc(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures++;
    }
}

$key = 'qa-' . uniqid();
$calls = 0;
$compute = function () use (&$calls): array {
    $calls++;
    return ['n' => $calls, 'f' => 1.5, 's' => 'ñandú'];
};

$a = ResultCache::remember($key, 60, $compute);
$b = ResultCache::remember($key, 60, $compute);
checkRc('la segunda llamada usa lo guardado', $calls === 1 && $a === $b && $b['s'] === 'ñandú' && $b['f'] === 1.5);

ResultCache::get($key, 0, $found);
checkRc('plazo 0 = vencido', $found === false);

// Simular que lo guardado tiene 100 s.
$file = (new ReflectionMethod(ResultCache::class, 'file'));
$file->setAccessible(true);
$path = $file->invoke(null, $key);
touch($path, time() - 100);
$c = ResultCache::remember($key, 60, $compute);
checkRc('vencido: recalcula', $calls === 2 && $c['n'] === 2);

touch($path, time() - 100);
$d = ResultCache::rememberStale($key, 60, 600, $compute);
checkRc('vencido hace poco: entrega lo guardado sin esperar', $calls === 2 && $d['n'] === 2);

ResultCache::forget($key);
ResultCache::get($key, 60, $found);
checkRc('forget borra', $found === false);
$e = ResultCache::rememberStale($key, 60, 600, $compute);
checkRc('sin nada guardado: calcula en el momento', $calls === 3 && $e['n'] === 3);
ResultCache::forget($key);
@unlink($path . '.lock');

exit($failures > 0 ? 1 : 0);
