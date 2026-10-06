<?php
require_once __DIR__ . '/src/Env.php';
Env::load(__DIR__ . '/.env');
require_once __DIR__ . '/src/Db.php';
require_once __DIR__ . '/src/ProductionService.php';

$pdo = Db::erpPdo();
$svc = new ProductionService($pdo);

$t = microtime(true);
$act = $svc->listActiveOrdersInCourse(1);
echo "listActiveOrdersInCourse: " . count($act) . " rows in " . round(microtime(true) - $t, 3) . "s\n";

$t = microtime(true);
$sql = "SELECT DISTINCT t0.*,
               t1.req_number, t1.req_production_initdate, t1.req_status, t2.company_short,
               t3.shop_name, t4.cust_name, t1.req_hash, t2x.item_number_prod, t2x.item_title,
               t1x.item_amount, t3x.prd_number, t1x.fab_printtype, t1x.fab_type, t3x.id AS prdid,
               t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle, t1x.fab_print_width,
               t1x.fab_print_height, v1.add_name AS fabric_color, v2.add_name AS manilla_color,
               t0.ag_amount, teq.equipo_name, teq.equipo_type_id
        FROM prod_agenda t0
        INNER JOIN prod_header t3x ON t0.ag_prdid = t3x.id AND t3x.prd_status >= 2
        INNER JOIN orders t1 ON t0.ag_reqid = t1.id
        LEFT OUTER JOIN company_data t2 ON t1.req_company_id = t2.id
        LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id = t3.id
        LEFT OUTER JOIN customer t4 ON t1.req_cust_id = t4.id
        INNER JOIN orders_items t1x ON t1.id = t1x.req_id
        INNER JOIN item t2x ON t1x.item_id = t2x.id
        LEFT OUTER JOIN tran_comments_vals v1 ON t1x.fab_mat_fabric_color = v1.id
        LEFT OUTER JOIN tran_comments_vals v2 ON t1x.fab_mat_manilla_color = v2.id
        LEFT OUTER JOIN equipo teq ON t0.ag_equipo_id = teq.id
        WHERE t0.id = 487 LIMIT 1";
$t = microtime(true);
$st = $pdo->prepare("SELECT i.id, i.item_number_prod, i.item_title
 FROM item i
 INNER JOIN item_productcats ip ON i.id = ip.item_id
 INNER JOIN productcats p ON p.id = ip.cat_id AND p.cat_prefix = 'ANI'
 INNER JOIN item_equipos_rel ier ON ier.item_id = i.id
 WHERE i.item_status > 0 AND ier.equipo_id = 5
 ORDER BY i.item_number_prod ASC");
$st->execute();
$r = $st->fetchAll();
echo "Anilox query 1: " . count($r) . " rows in " . round(microtime(true) - $t, 3) . "s\n";

$t = microtime(true);
$stFallback = $pdo->query(
    "SELECT i.id, i.item_number_prod, i.item_title
     FROM item i
     INNER JOIN item_productcats ip ON i.id = ip.item_id
     INNER JOIN productcats p ON p.id = ip.cat_id AND p.cat_prefix = 'ANI'
     WHERE i.item_status > 0
     ORDER BY i.item_number_prod ASC"
);
$rFallback = $stFallback->fetchAll();
echo "Fallback anilox query: " . count($rFallback) . " rows in " . round(microtime(true) - $t, 3) . "s\n";

$t = microtime(true);
$stmtAgTot = $pdo->prepare(
    "SELECT SUM(t3.evt_amount) AS tot_all,
            SUM(t3.evt_amount_pulpo_frente) AS tot_frente,
            SUM(t3.evt_amount_pulpo_dorso) AS tot_dorso
     FROM prod_worker_ot t2
     INNER JOIN prod_worker_ot_events t3 ON t3.evt_prod_worker_otid = t2.id
     WHERE t2.wok_ag_id = 487
       AND t3.evt_type = 'prod'
       AND t3.evt_status > 0"
);
$stmtAgTot->execute();
$rTot = $stmtAgTot->fetch();
echo "Tot ag query in: " . round(microtime(true) - $t, 3) . "s\n";

