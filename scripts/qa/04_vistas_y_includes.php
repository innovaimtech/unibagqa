<?php
declare(strict_types=1);
$root = str_replace('\\', '/', dirname(__DIR__, 2));
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) if ($f->isFile() && $f->getExtension() === 'php') $files[] = $f->getPathname();
$files[] = $root . '/public/index.php'; $files[] = $root . '/public/prodwrk.php'; $files[] = $root . '/router.php';
foreach (glob($root . '/scripts/*.php') as $s) $files[] = $s;

$bad = []; $viewsUsed = []; $total = 0;
foreach ($files as $file) {
    $c = file_get_contents($file);
    $dir = dirname($file);
    // renderView('a/b')
    if (preg_match_all("/renderView\\(\\s*'([^']+)'/", $c, $m)) {
        foreach ($m[1] as $v) { $viewsUsed[$v] = true; $total++;
            if (!file_exists($root . '/src/Views/' . $v . '.php')) $bad[] = "renderView('$v') <- " . basename($file);
        }
    }
    // require/include con __DIR__, dirname(__DIR__, n)
    if (preg_match_all("/(?:require|include)(?:_once)?\\s*\\(?\\s*(dirname\\(__DIR__(?:,\\s*(\\d+))?\\)|__DIR__)\\s*\\.\\s*'([^']+)'/", $c, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) { $total++;
            $base = $x[1] === '__DIR__' ? $dir : (function() use ($dir,$x){ $d=$dir; $lv = isset($x[2]) && $x[2] !== '' ? (int)$x[2] : 1; for($i=0;$i<$lv;$i++) $d = dirname($d); return $d; })();
            $target = $base . $x[3];
            if (!file_exists($target)) $bad[] = "require '" . $x[3] . "' <- " . str_replace($root.'/', '', str_replace('\\','/',$file));
        }
    }
}
// vistas huerfanas (existen pero nadie las usa)
$orphans = [];
$vit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src/Views', FilesystemIterator::SKIP_DOTS));
foreach ($vit as $f) {
    if (!$f->isFile()) continue;
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root . '/src/Views/')));
    $name = preg_replace('/\.php$/', '', $rel);
    if (str_starts_with($name, 'layouts/')) continue;
    if (!isset($viewsUsed[$name])) {
        // buscar uso indirecto por nombre de archivo
        $hit = false;
        foreach ($files as $fl) { if (str_contains(file_get_contents($fl), $name) || str_contains(file_get_contents($fl), basename($name))) { if ($fl !== $f->getPathname()) { $hit = true; break; } } }
        if (!$hit) $orphans[] = $name;
    }
}
echo "Referencias verificadas (renderView + require/include): $total\n";
echo "Referencias rotas: " . count($bad) . "\n"; foreach ($bad as $b) echo "  $b\n";
echo "Vistas sin ninguna referencia: " . count($orphans) . "\n"; foreach ($orphans as $o) echo "  $o\n";
