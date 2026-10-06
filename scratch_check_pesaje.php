<?php
$f = file(__DIR__ . '/public/libs.prodwrk/modules/ordendetrabajo/form.reboprod.php');
for ($i = 0; $i < min(100, count($f)); $i++) {
    echo ($i + 1) . ': ' . $f[$i];
}
