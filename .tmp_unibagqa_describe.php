<?php
$tables = [
  'supplier','supplier_order','supplier_order_items','supplier_order_items_specs',
  'item','item_shops_storehouses','company_shops_storehouses',
  'workers','user','prod_worker_ot','prod_worker_ot_events'
];
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=unibagqa;charset=utf8mb4', 'root', 'AY2309', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach ($tables as $table) {
    echo "===== DESCRIBE {$table} =====\n";
    foreach ($pdo->query("DESCRIBE `{$table}`") as $row) {
        echo $row['Field'], "\t", $row['Type'], "\t", $row['Null'], "\t", $row['Key'], "\n";
    }
    echo "\n";
}
$queries = [
  "SELECT id, supp_name, supp_status FROM supplier LIMIT 5",
  "SELECT id, sord_number, sord_supplier_id, sord_status, sord_crtdat FROM supplier_order ORDER BY id DESC LIMIT 5",
  "SELECT id, sord_id, item_id, item_amount, item_kgs FROM supplier_order_items ORDER BY id DESC LIMIT 5",
  "SELECT id, item_number, item_title, item_reg_gsm, item_reg_width, item_reg_length, item_reg_kg, item_status FROM item LIMIT 5",
  "SELECT item_id, shop_id, st_id, iss_inventory FROM item_shops_storehouses LIMIT 5",
  "SELECT id, st_name, st_shop_id, st_status FROM company_shops_storehouses LIMIT 10",
  "SELECT id, wrk_firstname, wrk_lastname, wrk_status, wrk_uid FROM workers LIMIT 5",
  "SELECT id, user_login, user_firstname, user_lastname, user_status, user_appmode_0, user_appmode_1, user_appmode_3 FROM user LIMIT 5",
  "SELECT id, wok_ag_id, wok_init_id, wok_status, wok_crtdat, wok_enddat FROM prod_worker_ot ORDER BY id DESC LIMIT 5",
  "SELECT id, evt_prod_worker_otid, evt_type, evt_amount, prod_bobina_kg, evt_crtdat FROM prod_worker_ot_events ORDER BY id DESC LIMIT 5"
];
foreach ($queries as $sql) {
    echo "===== QUERY =====\n{$sql}\n";
    foreach ($pdo->query($sql) as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), "\n";
    }
    echo "\n";
}
