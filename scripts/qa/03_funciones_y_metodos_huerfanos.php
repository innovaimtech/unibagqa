<?php
declare(strict_types=1);
$root = str_replace('\\', '/', dirname(__DIR__, 2));
$files = [];
foreach (['/src','/scripts'] as $d) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . $d, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile() && $f->getExtension() === 'php') $files[] = $f->getPathname();
}
$files[] = $root . '/public/index.php'; $files[] = $root . '/public/prodwrk.php'; $files[] = $root . '/router.php';

$definedFns = []; $definedMethods = []; $classes = [];
$calls = []; $methodCalls = [];
$internal = array_flip(array_map('strtolower', get_defined_functions()['internal']));

foreach ($files as $file) {
    $tokens = token_get_all(file_get_contents($file));
    $n = count($tokens);
    $classDepth = 0; $braceStack = []; $pendingClass = false;
    $sig = function(int $i, int $dir) use ($tokens,$n) { // siguiente token significativo
        for ($j = $i + $dir; $j >= 0 && $j < $n; $j += $dir) {
            if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE,T_COMMENT,T_DOC_COMMENT], true)) continue;
            return [$j, $tokens[$j]];
        }
        return [-1, null];
    };
    $inClass = 0; $depth = 0; $classDepths = [];
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (is_array($t) && in_array($t[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
            [, $prev] = $sig($i, -1);
            if (!(is_array($prev) && $prev[0] === T_DOUBLE_COLON)) {
                [$j, $nm] = $sig($i, 1);
                if (is_array($nm) && $nm[0] === T_STRING) { $classes[strtolower($nm[1])] = true; $pendingClass = true; }
            }
        }
        if ($t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $depth++;
            if ($pendingClass && $t === '{') { $classDepths[] = $depth; $pendingClass = false; }
        } elseif ($t === '}') {
            if ($classDepths && end($classDepths) === $depth) array_pop($classDepths);
            $depth--;
        }
        if (is_array($t) && $t[0] === T_FUNCTION) {
            [$j, $nm] = $sig($i, 1);
            if ($nm === '&') [$j, $nm] = $sig($j, 1);
            if (is_array($nm) && $nm[0] === T_STRING) {
                if ($classDepths) $definedMethods[strtolower($nm[1])] = true;
                else $definedFns[strtolower($nm[1])] = true;
            }
        }
        if (is_array($t) && $t[0] === T_STRING) {
            [$j, $next] = $sig($i, 1);
            [, $prev] = $sig($i, -1);
            if ($next === '(') {
                $prevT = is_array($prev) ? $prev[0] : $prev;
                if ($prevT === T_FUNCTION || $prevT === T_NEW) continue;
                if ($prevT === T_OBJECT_OPERATOR || $prevT === T_NULLSAFE_OBJECT_OPERATOR) {
                    // receptor
                    $k = $i - 1; while ($k >= 0 && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) $k--; // ->
                    [, $recv] = $sig($k, -1);
                    $rv = is_array($recv) && $recv[0] === T_VARIABLE ? $recv[1] : '';
                    $methodCalls[] = [strtolower($t[1]), $rv, $file, $t[2]];
                } elseif ($prevT === T_DOUBLE_COLON) {
                    // estatico: ignorar
                } else {
                    $calls[] = [strtolower($t[1]), $file, $t[2]];
                }
            }
        }
    }
}

// ---- funciones libres no definidas ----
$undef = [];
foreach ($calls as [$name, $file, $line]) {
    if (isset($definedFns[$name]) || isset($internal[$name]) || isset($classes[$name])) continue;
    $undef[$name][] = str_replace($root . '/', '', str_replace('\\', '/', $file)) . ':' . $line;
}
ksort($undef);
echo "Funciones definidas en el proyecto: " . count($definedFns) . " | metodos: " . count($definedMethods) . " | clases: " . count($classes) . "\n";
echo "Llamadas a funciones libres sin definicion: " . count($undef) . "\n";
foreach ($undef as $name => $w) echo "  $name()  <- " . implode(', ', array_slice($w, 0, 3)) . "\n";

// ---- metodos de servicios no definidos ----
$svcRecv = ['$service','$prodservice','$receptionservice','$printer','$scale','$scaleservice','$prodsvc','$svc','$bonussvc','$bonusservice'];
$undefM = [];
foreach ($methodCalls as [$name, $rv, $file, $line]) {
    if (!in_array(strtolower($rv), $svcRecv, true)) continue;
    if (isset($definedMethods[$name])) continue;
    $undefM[$name][] = str_replace($root . '/', '', str_replace('\\', '/', $file)) . ':' . $line;
}
ksort($undefM);
echo "\nMetodos invocados sobre servicios sin definicion: " . count($undefM) . "\n";
foreach ($undefM as $name => $w) echo "  ->$name()  <- " . implode(', ', array_slice($w, 0, 3)) . "\n";
