<?php
declare(strict_types=1);
$root = str_replace('\\', '/', dirname(__DIR__, 2));
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) if ($f->isFile() && $f->getExtension() === 'php') $files[] = $f->getPathname();
$files[] = $root . '/public/index.php'; $files[] = $root . '/public/prodwrk.php';

// ---------- 1. Inventario de rutas ----------
$exact = []; $prefix = []; $regex = [];
foreach ($files as $file) {
    foreach (preg_split('/\R/', file_get_contents($file)) as $line) {
        if (strpos($line, '$path') === false && strpos($line, '$uri') === false) continue;
        if (preg_match_all("/'(\\/[^']*)'/", $line, $m)) {
            foreach ($m[1] as $lit) {
                if (preg_match('/starts_with\\(\\$path,\\s*\'' . preg_quote($lit, '/') . '\'/', $line)) $prefix[$lit] = true;
                else $exact[$lit] = true;
            }
        }
        if (preg_match_all("/preg_match\\('(#[^']+#[a-z]*)'/", $line, $m)) foreach ($m[1] as $r) $regex[$r] = true;
    }
}
// rutas servidas por archivo estatico
$static = function(string $p) use ($root): bool {
    $f = $root . '/public' . $p;
    return $p !== '/' && file_exists($f) && !is_dir($f);
};

$matches = function(string $p) use ($exact,$prefix,$regex,$static): bool {
    if (isset($exact[$p]) || $static($p)) return true;
    foreach ($prefix as $pre => $_) if (str_starts_with($p, $pre)) return true;
    foreach (array_keys($regex) as $r) {
        $re = str_replace('\\\\', '\\', $r);
        if (@preg_match($re, $p) === 1) return true;
        if (@preg_match($r, $p) === 1) return true;
    }
    return false;
};

// ---------- 2. Destinos internos ----------
$targets = [];
$pats = [
    '/(?:href|action)=\\\\?"(\\/[^"]*)"/',
    "/(?:href|action)=\\\\?'(\\/[^']*)'/",
    "/redirectResponse\\(\\s*'(\\/[^']*)'/",
    "/header\\(\\s*'Location:\\s*(\\/[^']*)'/",
    "/location\\.href\\s*=\\s*\\\\?'(\\/[^']*)'/",
];
foreach ($files as $file) {
    $c = file_get_contents($file);
    foreach ($pats as $p) {
        if (!preg_match_all($p, $c, $m)) continue;
        foreach ($m[1] as $raw) {
            $t = $raw;
            $t = preg_replace("/'\\s*\\.\\s*.*?\\s*\\.\\s*'/s", '123', $t);   // concatenaciones PHP
            $t = preg_replace('/<\\?=.*?\\?>/s', '123', $t);
            $t = preg_replace('/\\{\\$[^}]*\\}/', '123', $t);
            $t = preg_replace('/\\$[a-zA-Z_][a-zA-Z0-9_]*(\\[[^\\]]*\\])*/', '123', $t);
            $t = preg_split('/[?#]/', $t)[0];
            $t = rtrim($t, '.');
            if ($t === '' || $t === '/') { continue; }
            if (str_contains($t, "'") || str_contains($t, ' ')) continue; // no resoluble estaticamente
            $targets[$t][] = str_replace($root . '/', '', str_replace('\\', '/', $file));
        }
    }
}

$bad = [];
foreach ($targets as $t => $where) {
    if (!$matches($t)) $bad[$t] = array_values(array_unique($where));
}
// Falsos positivos revisados a mano (rutas dinamicas que este analisis estatico no puede resolver):
//  - /123 .............. URL armada con una expresion PHP (stock?bodega=... / purchase-orders?...)
//  - /trazabilidad* .... TraceabilityModule la resuelve con $cleanPath, no con $path
foreach (['/123', '/trazabilidad', '/trazabilidad/detalle'] as $ok) { unset($bad[$ok]); }
ksort($bad);
echo "Rutas registradas: exactas=" . count($exact) . " prefijos=" . count($prefix) . " regex=" . count($regex) . "\n";
echo "Destinos internos unicos analizados: " . count($targets) . "\n";
echo "Destinos SIN ruta correspondiente: " . count($bad) . "\n";
foreach ($bad as $t => $w) echo "  $t   <- " . implode(', ', array_slice($w, 0, 3)) . "\n";
exit($bad === [] ? 0 : 1);
