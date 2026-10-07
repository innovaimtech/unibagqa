<?php
$f1 = 'C:/Users/Axiliarmu/Desktop/_BACKUP20260723/libs/modules/supplier_order_shiprcv/data.receive.php';
$f2 = 'C:/Users/Axiliarmu/Desktop/_BACKUP20260723/libs/modules/supplier_order_shiprcv/data.sth.form.php';

echo "=== data.receive.php ===\n";
echo file_get_contents($f1) . "\n\n";

echo "=== data.sth.form.php (first 100 lines) ===\n";
$lines = file($f2);
echo implode('', array_slice($lines, 0, 100)) . "\n\n";
