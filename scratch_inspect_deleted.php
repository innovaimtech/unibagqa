<?php
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Db.php';

Env::load(__DIR__ . '/.env');

$trz = Db::trzPdo();
$erp = Db::erpPdo();

echo "=== AGENDA 22582 IN ERP ===\n";
$st = $erp->query("SELECT ag.*, ph.prd_number, ord.req_number 
                   FROM prod_agenda ag 
                   LEFT JOIN prod_header ph ON ag.ag_prdid = ph.id 
                   LEFT JOIN orders ord ON ag.ag_reqid = ord.id 
                   WHERE ag.id = 22582");
print_r($st->fetch(PDO::FETCH_ASSOC));

echo "=== ALL prod_worker_ot FOR AGENDA 22582 ===\n";
$st2 = $erp->query("SELECT * FROM prod_worker_ot WHERE wok_ag_id = 22582");
print_r($st2->fetchAll(PDO::FETCH_ASSOC));

echo "=== ROLLS 435 AND 439 IN TRZ ===\n";
$st3 = $trz->query("SELECT * FROM rolls WHERE id IN (434, 435, 436, 437, 438, 439)");
print_r($st3->fetchAll(PDO::FETCH_ASSOC));
