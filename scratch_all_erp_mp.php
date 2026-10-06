<?php
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Db.php';
Env::load(__DIR__ . '/.env');

$erp = Db::erpPdo();
$sql = "
    SELECT iss.st_id, css.st_name, i.id as item_id, i.item_number_prod as sku, i.item_title, i.item_reg_width,
           iss.iss_inventory, iss.iss_inventory_reserved, (iss.iss_inventory - iss.iss_inventory_reserved) as available_qty
    FROM item_shops_storehouses iss
    JOIN company_shops_storehouses css ON iss.st_id = css.id
    JOIN item i ON iss.item_id = i.id
    WHERE iss.iss_inventory > 0
      AND (css.id IN (5, 11, 19, 22, 23, 30) OR css.st_name LIKE '%MP%')
    ORDER BY css.id, iss.iss_inventory DESC
";
$rows = $erp->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "Total ERP MP Items with Stock: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "St #{$r['st_id']} ({$r['st_name']}) | Item #{$r['item_id']} | SKU: {$r['sku']} | Ancho: {$r['item_reg_width']} | Stock: {$r['iss_inventory']} (disp: {$r['available_qty']}) | Title: {$r['item_title']}\n";
}
