<?php
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Db.php';

Env::load(__DIR__ . '/.env');

$erpPdo = Db::erpPdo();
$stmt3000 = $erpPdo->query("SELECT id, st_name FROM company_shops_storehouses WHERE st_name LIKE '3000%' AND st_status = 1");
print_r($stmt3000->fetchAll(PDO::FETCH_ASSOC));

$code = 'TEL0165';
$numId = is_numeric($code) ? (int)$code : 0;
$stmt = $erpPdo->prepare("
    SELECT DISTINCT t1.*, t3.cat_title,
           t4b.add_name AS colordesc, t4.val_id AS colorid,
           t6.add_name AS material,
           COALESCE(SUM(t7.iss_inventory), 0) AS iss_inventory,
           t7.st_id
    FROM item t1
    INNER JOIN item_productcats t2 ON t1.id = t2.item_id
    INNER JOIN productcats t3 ON t2.cat_id = t3.id
    LEFT JOIN tran_comments_item_vals t4 ON t4.item_id = t1.id AND t4.com_id = 17
    LEFT JOIN tran_comments_vals t4b ON t4.val_id = t4b.id
    LEFT JOIN tran_comments_item_vals t5 ON t5.item_id = t1.id AND t5.com_id = 18
    LEFT JOIN tran_comments_vals t6 ON t5.val_id = t6.id
    LEFT JOIN item_shops_storehouses t7 ON t1.id = t7.item_id
    WHERE (t1.item_number_prod = :code OR t1.item_number = :code OR t1.id = :num_id)
      AND t1.item_status > 0
    GROUP BY t1.id
    LIMIT 1
");
$stmt->execute([':code' => $code, ':num_id' => $numId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($item);
