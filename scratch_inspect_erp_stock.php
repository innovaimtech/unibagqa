<?php
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Db.php';
Env::load(__DIR__ . '/.env');

$erp = Db::erpPdo();

$rows = $erp->query("
    SELECT t0.id as ag_id, t0.ag_reqid, t1.id as order_id, t1.req_number,
           t1.req_operador_tela_width,
           rh.rebov2_input_rollos_telawidth, rh.rebov2_input_metros_telawidth,
           t1x.fab_med_height, t1x.fab_med_width, t1x.fab_med_fuelle
    FROM prod_agenda t0
    JOIN orders t1 ON t0.ag_reqid = t1.id
    JOIN orders_items t1x ON t1.id = t1x.req_id
    LEFT JOIN prod_ot_rebo_header rh ON rh.rebo_order_refid = t1.id
    WHERE t0.id IN (23696, 23695, 23668, 23667, 23663, 23409, 23408)
")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
