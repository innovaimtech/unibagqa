<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=unibagqa;charset=utf8mb4', 'root', 'AY2309', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$queries = [
  "SELECT id, supp_company, supp_short, supp_status FROM supplier LIMIT 5",
  "SELECT id, sord_number, sord_supplier_id, sord_status, sord_crtdat, sord_shop_id, sord_company_id FROM supplier_order ORDER BY id DESC LIMIT 5",
  "SELECT id, sord_id, item_id, item_amount, item_amount_shipped, item_kgs, item_desc FROM supplier_order_items ORDER BY id DESC LIMIT 5",
  "SELECT id, item_number, item_number_prod, item_title, item_status, item_reg_gsm, item_reg_width, item_reg_length, item_reg_kg, item_prodwrk_act, item_purchasable FROM item LIMIT 5",
  "SELECT item_id, shop_id, st_id, iss_inventory, iss_inventory_reserved FROM item_shops_storehouses ORDER BY iss_inventory DESC LIMIT 10",
  "SELECT id, st_name, st_shop_id, st_status, st_unibagreserva_act, st_unibagflexo_act, st_unibagseri_act, st_unibagsellador_act FROM company_shops_storehouses LIMIT 10",
  "SELECT id, user_login, user_firstname, user_lastname, user_status, user_appmode_0, user_appmode_1, user_appmode_3 FROM user LIMIT 10",
  "SELECT id, wrk_firstname, wrk_lastname, wrk_status, wrk_uid FROM workers LIMIT 10",
  "SELECT id, wok_ag_id, wok_init_id, wok_status, wok_crtdat, wok_enddat FROM prod_worker_ot ORDER BY id DESC LIMIT 10",
  "SELECT id, evt_prod_worker_otid, evt_type, evt_amount, prod_bobina_kg, evt_comments, evt_crtdat FROM prod_worker_ot_events ORDER BY id DESC LIMIT 10"
];
foreach ($queries as $sql) {
    echo "===== QUERY =====\n{$sql}\n";
    foreach ($pdo->query($sql) as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), "\n";
    }
    echo "\n";
}
