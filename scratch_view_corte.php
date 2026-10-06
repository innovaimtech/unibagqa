<?php
$lines = file(__DIR__ . '/public/libs/modules/stats/prod/corteysellado.php');
for ($i = 1015; $i <= 1045; $i++) {
    if (isset($lines[$i])) {
        echo ($i + 1) . ': ' . $lines[$i];
    }
}
