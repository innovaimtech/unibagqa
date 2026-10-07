<?php
$dir = new RecursiveDirectoryIterator('C:/Users/Axiliarmu/Desktop/_BACKUP20260723');
$it = new RecursiveIteratorIterator($dir);
foreach ($it as $f) {
    if ($f->isFile() && $f->getExtension() === 'php') {
        $c = file_get_contents($f->getPathname());
        if (stripos($c, 'sth_supporder_contenedorid') !== false) {
            echo $f->getPathname() . PHP_EOL;
        }
    }
}
