<?php
declare(strict_types=1);
// Motor de kardex: cada movimiento deja documento, línea, kardex y stock; repetir no duplica; reversa y traspaso; retoma cortes.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
$root = dirname(__DIR__, 2);
require_once $root . '/src/bootstrap.php';
require_once __DIR__ . '/lib_erp_sqlite.php';

$fail = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . ($extra !== '' && !$ok ? " [$extra]" : '') . "\n";
    if (!$ok) {
        $fail++;
    }
};
$erp = qaErpSqlite();
$erp->exec("INSERT INTO item (id,item_title,item_number_prod) VALUES (10,'TINTA BLANCA','TIN0001'),(20,'BOLSA','BOL0001');
INSERT INTO orders (id,req_number,req_company_id,req_shop_id) VALUES (500,'26-00500-1',20010,30010);
INSERT INTO item_shops_storehouses VALUES (10,30010,16,0,0,50,0,0),(20,30010,8,0,0,300,0,500);");
$ledger = new StockLedgerService($erp);
$stock = fn(int $item, int $st, int $order = 0): float => (float)$erp->query("SELECT COALESCE(SUM(iss_inventory),0) FROM item_shops_storehouses WHERE item_id=$item AND st_id=$st AND iss_order_id=$order")->fetchColumn();
$kardex = fn(int $item, int $st, int $order = 0): float => (float)$erp->query("SELECT COALESCE(SUM(CASE WHEN tran_type IN ('stockchangeup','sthup') THEN tran_amount ELSE -tran_amount END),0) FROM tran_data WHERE item_id=$item AND tran_st_id=$st AND tran_order_id=$order")->fetchColumn();
$count = fn(string $t): int => (int)$erp->query("SELECT COUNT(*) FROM $t")->fetchColumn();
$err = function (callable $f): string { try { $f(); } catch (Throwable $e) { return $e->getMessage(); } return ''; };

$r = $ledger->post(['key' => 'qa.tinta.1', 'item_id' => 10, 'st_id' => 16, 'delta' => -12.5, 'text' => 'Tinta usada']);
$check('salida descuenta stock', $stock(10, 16) === 37.5);
$check('salida deja kardex', $kardex(10, 16) === -12.5 && $count('tran_data') === 1);
$check('documento numerado con 7 dígitos y estado 2', $r['number'] === 'IA0000101' && (int)$erp->query('SELECT stk_status FROM stockchanges')->fetchColumn() === 2, $r['number']);
$again = $ledger->post(['key' => 'qa.tinta.1', 'item_id' => 10, 'st_id' => 16, 'delta' => -12.5]);
$check('repetir la clave no duplica', $again['already'] === true && $stock(10, 16) === 37.5 && $count('stockchanges') === 1);
$check('la misma clave con otra cantidad se rechaza', str_contains($err(fn() => $ledger->post(['key' => 'qa.tinta.1', 'item_id' => 10, 'st_id' => 16, 'delta' => -3])), 'otro movimiento'));
$check('sin stock suficiente se rechaza y no escribe', str_contains($err(fn() => $ledger->post(['key' => 'qa.tinta.2', 'item_id' => 10, 'st_id' => 16, 'delta' => -100])), 'No hay stock suficiente') && $count('stockchanges') === 1);
$ledger->reverse('qa.tinta.1', 'prueba');
$check('reversa devuelve el stock con kardex', $stock(10, 16) === 50.0 && $kardex(10, 16) === 0.0);
$ledger->reverse('qa.tinta.1', 'prueba');
$check('reversa repetida no devuelve dos veces', $stock(10, 16) === 50.0 && $count('stockchanges') === 2);
$check('una clave revertida no queda abierta', $ledger->openKeys('qa.tinta.') === [] && $ledger->netByPrefix('qa.tinta.') === 0.0);

// Stock por CC
$ledger->post(['key' => 'qa.bolsa.1', 'item_id' => 20, 'st_id' => 8, 'delta' => -50, 'order_id' => 500]);
$check('la salida por CC usa la fila del CC', $stock(20, 8, 500) === 250.0 && $kardex(20, 8, 500) === -50.0);
$check('el documento lleva el CC', (string)$erp->query("SELECT sth_order_num FROM stockchanges WHERE stk_annotation LIKE '%qa.bolsa.1%'")->fetchColumn() === '26-00500-1');

// Traspaso
$t = $ledger->transfer(['key' => 'qa.tr.1', 'item_id' => 20, 'from_st' => 8, 'to_st' => 16, 'qty' => 200, 'order_id' => 500]);
$check('traspaso mueve el stock por CC', $stock(20, 8, 500) === 50.0 && $stock(20, 16, 500) === 200.0);
$check('traspaso deja sthdown y sthup', $kardex(20, 8, 500) === -250.0 && $kardex(20, 16, 500) === 200.0);
$ledger->transfer(['key' => 'qa.tr.1', 'item_id' => 20, 'from_st' => 8, 'to_st' => 16, 'qty' => 200, 'order_id' => 500]);
$check('traspaso repetido no duplica', $stock(20, 16, 500) === 200.0 && $count('storehousechanges') === 1);
$check('traspaso sin stock se rechaza', str_contains($err(fn() => $ledger->transfer(['key' => 'qa.tr.2', 'item_id' => 20, 'from_st' => 8, 'to_st' => 16, 'qty' => 999, 'order_id' => 500])), 'No hay stock'));

// Corte a mitad: documento en estado 1 con kardex pero sin mover stock → al retomar se mueve una sola vez.
$erp->exec("INSERT INTO stockchanges (id,stk_num,stk_annotation,stk_issueid,stk_companyid,stk_shopid,stk_negative,stk_status,stk_crtdat,stk_fixedsthid,sth_order_id) VALUES (900,'IA0009999','x [TRZ-LED:qa.corte.1]',19,20010,30010,1,1," . time() . ",16,0)");
$erp->exec("INSERT INTO stockchanges_items (stk_id,item_id,item_pos,item_amount,item_type,item_st_id) VALUES (900,10,0,5,'item',16)");
$erp->exec("INSERT INTO tran_data (tran_id,tran_pos,tran_type,tran_number,tran_currstock,tran_amount,tran_st_id,item_id,tran_order_id) VALUES (900,0,'stockchangedown','IA0009999',50,5,16,10,0)");
$ledger->post(['key' => 'qa.corte.1', 'item_id' => 10, 'st_id' => 16, 'delta' => -5]);
$check('retomar un corte sin stock movido lo mueve una vez', $stock(10, 16) === 45.0 && (int)$erp->query('SELECT stk_status FROM stockchanges WHERE id=900')->fetchColumn() === 2);
// Corte con stock ya movido → al retomar no vuelve a mover.
$erp->exec("INSERT INTO stockchanges (id,stk_num,stk_annotation,stk_issueid,stk_companyid,stk_shopid,stk_negative,stk_status,stk_crtdat,stk_fixedsthid,sth_order_id) VALUES (901,'IA0009998','x [TRZ-LED:qa.corte.2]',19,20010,30010,1,1," . time() . ",16,0)");
$erp->exec("INSERT INTO stockchanges_items (stk_id,item_id,item_pos,item_amount,item_type,item_st_id) VALUES (901,10,0,5,'item',16)");
$erp->exec("INSERT INTO tran_data (tran_id,tran_pos,tran_type,tran_number,tran_currstock,tran_amount,tran_st_id,item_id,tran_order_id) VALUES (901,0,'stockchangedown','IA0009998',45,5,16,10,0)");
$erp->exec("UPDATE item_shops_storehouses SET iss_inventory=40 WHERE item_id=10 AND st_id=16");
$ledger->post(['key' => 'qa.corte.2', 'item_id' => 10, 'st_id' => 16, 'delta' => -5]);
$check('retomar un corte con stock ya movido no lo mueve otra vez', $stock(10, 16) === 40.0);
// Sin fila de stock y entrada: crea la fila.
$ledger->post(['key' => 'qa.nueva.1', 'item_id' => 20, 'st_id' => 14, 'delta' => 30]);
$check('una entrada sin fila crea la fila general', $stock(20, 14) === 30.0 && $kardex(20, 14) === 30.0);
$check('claves inválidas se rechazan', str_contains($err(fn() => $ledger->post(['key' => "x' OR 1", 'item_id' => 10, 'st_id' => 16, 'delta' => 1])), 'no válida'));

echo $fail === 0 ? "TODO OK\n" : "FALLOS: $fail\n";
exit($fail === 0 ? 0 : 1);
