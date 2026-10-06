<?php
$file = $argv[1] ?? 'src/Http/ProductionModule.php';
$query = $argv[2] ?? 'reboprod';
$lines = file($file);
foreach ($lines as $i => $l) {
    if (stripos($l, $query) !== false) {
        echo ($i + 1) . ': ' . trim($l) . "\n";
    }
}
