<?php
require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Db.php';
Env::load(__DIR__ . '/../.env');

$erp = Db::erpPdo();
$orders = [11634, 11625, 11585, 11523, 11428];
foreach ($orders as $oid) {
    echo "=== ORDER {$oid} ===\n";
    $o = $erp->query("SELECT req_number, req_rebo_type, req_rebo_rolloscc_opt, req_rebo_rolloscc, req_rebo_cortescc FROM orders WHERE id = {$oid}")->fetch(PDO::FETCH_ASSOC);
    print_r($o);
    $rv = $erp->query("SELECT * FROM orders_classify_rebo_values WHERE req_id = {$oid}")->fetchAll(PDO::FETCH_ASSOC);
    echo "rebo_values: " . json_encode($rv) . "\n";
    $sp = $erp->query("SELECT * FROM orders_classify_subproducts WHERE req_id = {$oid}")->fetchAll(PDO::FETCH_ASSOC);
    echo "subproducts: " . json_encode($sp) . "\n\n";
}
