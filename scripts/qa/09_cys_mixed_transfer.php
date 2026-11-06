<?php
declare(strict_types=1);
// Cierre mixto: el CC secundario debe indicar de qué bodega sale, con traspaso y kardex; las filas no se pierden y el origen sin stock avisa.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
$root = dirname(__DIR__, 2);
require_once $root . '/src/ProductionService.php';

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $fail++;
    }
};

$base = ['req_embalaje_medidas_caja' => '50X40', 'req_codigo_cierre' => '50', 'req_id_bodega' => 14, 'req_embalaje_cajas_por_pallet_amt' => 30,
    'req_embalaje_bolsas_por_caja_amt' => 100, 'req_embalaje_cajas_completas_amt' => 10,
    'mixed_req_number_0' => 'M', 'mixed_item_id_0' => 1, 'total_cajas_0' => 10, 'bolsas_por_caja_0' => 100];
$secondary = static fn(int $i, $origin): array => ["mixed_req_number_$i" => "S$i", "mixed_item_id_$i" => 2, "total_cajas_$i" => 5, "bolsas_por_caja_$i" => 100, "req_id_bodega_$i" => $origin];

$error = '';
try {
    ProductionService::normalizePackagingDetail($base + $secondary(1, ''));
} catch (RuntimeException $e) {
    $error = $e->getMessage();
}
$check('un CC secundario con bolsas y sin bodega de origen no se puede cerrar', str_contains($error, 'S1'));

$ok = ProductionService::normalizePackagingDetail($base + $secondary(1, '-1'));
$check('se acepta indicar que ya está en la bodega de destino', (int)$ok['mixed_items'][1]['req_id_bodega'] === UNIBAG_MIXED_ORIGIN_AT_DESTINATION);

$empty = ProductionService::normalizePackagingDetail($base + ['mixed_req_number_1' => 'S1', 'total_cajas_1' => 0, 'bolsas_por_caja_1' => 0, 'req_id_bodega_1' => '']);
$check('un CC sin bolsas no exige bodega', count($empty['mixed_items']) === 2);

$standard = ProductionService::normalizePackagingDetail(['req_codigo_cierre' => '10'] + $base + $secondary(1, ''));
$check('un cierre estándar no exige bodega de origen', (string)$standard['req_codigo_cierre'] === '10');

$gap = ProductionService::normalizePackagingDetail($base + $secondary(2, '8') + $secondary(3, '8'));
$check('quitar una fila no pierde las filas siguientes', array_column($gap['mixed_items'], 'req_number') === ['M', 'S2', 'S3']);

$erp = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$erp->exec("CREATE TABLE orders (id INTEGER PRIMARY KEY, req_number TEXT, req_company_id INT, req_shop_id INT);
CREATE TABLE item (id INTEGER PRIMARY KEY, item_title TEXT);
CREATE TABLE company_data (id INTEGER PRIMARY KEY, company_numformat_storehouse TEXT, company_numcounter_storehouse INT);
CREATE TABLE company_shops_storehouses (id INTEGER PRIMARY KEY, st_name TEXT);
CREATE TABLE item_shops_storehouses (item_id INT, shop_id INT, st_id INT, iss_order_amount REAL, iss_inventory_min REAL, iss_inventory REAL, iss_inventory_reserved REAL, iss_order_id INT);
CREATE TABLE storehousechanges (id INTEGER PRIMARY KEY AUTOINCREMENT, strc_company_id INT, strc_shop_id INT, strc_company_dest_id INT, strc_shop_dest_id INT, strc_status INT, strc_date INT, strc_desc TEXT, strc_number TEXT, strc_crtusr INT, strc_crtdat INT, strc_order_id INT, strc_order_id_dest INT, strc_order_num TEXT, strc_order_num_dest TEXT, strc_shopsent INT, strc_shopreceived INT, strc_custid INT, strc_rel_reqnum TEXT);
CREATE TABLE storehousechanges_items (strc_id INT, item_id INT, item_pos INT, item_amount REAL, item_type TEXT, item_st_id INT, item_st_dest_id INT);
CREATE TABLE tran_data (tran_id INT, tran_pos INT, tran_type TEXT, tran_number TEXT, tran_currstock REAL, tran_amount REAL, tran_company_id INT, tran_shop_id INT, tran_st_id INT, item_id INT, item_title TEXT, item_type TEXT, tran_day INT, tran_month INT, tran_year INT, tran_crtusr INT, tran_crtdat INT, tran_order_id INT);
CREATE TABLE embalaje_mixto (id INTEGER PRIMARY KEY, id_embalaje INT);
CREATE TABLE detalle_mixto (id INTEGER PRIMARY KEY, id_mixto INT, req_number TEXT, id_item INT);
CREATE TABLE orders_items (id INTEGER PRIMARY KEY, req_id INT, item_id INT, fab_design_imagehash TEXT);
INSERT INTO orders VALUES (1,'S1',20010,30010),(2,'S2',20010,30010);
INSERT INTO item VALUES (2,'BOLSA');
INSERT INTO company_data VALUES (20010,'IT',100);
INSERT INTO company_shops_storehouses VALUES (8,'500 BODEGA');
INSERT INTO item_shops_storehouses VALUES (2,30010,8,0,0,5000,0,1);
INSERT INTO embalaje_mixto VALUES (1,50);
INSERT INTO detalle_mixto VALUES (1,1,'S1',2),(2,1,'S2',2);");
$service = new ProductionService($erp, null);
$transfer = new ReflectionMethod($service, 'transferMixedStock');
$transfer->setAccessible(true);
$stock = fn(int $order, int $st): float => (float)$erp->query("SELECT COALESCE(SUM(iss_inventory),0) x FROM item_shops_storehouses WHERE iss_order_id=$order AND st_id=$st")->fetch()['x'];
$kardex = fn(string $type): float => (float)$erp->query("SELECT COALESCE(SUM(tran_amount),0) x FROM tran_data WHERE tran_type='$type'")->fetch()['x'];

$transfer->invoke($service, 50, 2, 8, 14, 3000, 'S1');
$check('el traspaso saca de origen y entra a destino', $stock(1, 8) === 2000.0 && $stock(1, 14) === 3000.0);
$check('el traspaso deja kardex de salida y entrada', $kardex('sthdown') === 3000.0 && $kardex('sthup') === 3000.0);
$check('el número usa el formato del ERP', (string)$erp->query('SELECT strc_number x FROM storehousechanges')->fetch()['x'] === 'IT0000101');
$check('con stock suficiente no hay aviso', $service->getLastSupplyConsumptionWarning() === '');

$detail = $service->getMixedPackagingDetail(50);
$origins = array_column($detail['items'], 'origin_wh', 'req_number');
$check('al corregir se recupera el origen registrado', $origins['S1'] === 8 && $origins['S2'] === UNIBAG_MIXED_ORIGIN_AT_DESTINATION);

$transfer->invoke($service, 50, 2, 8, 14, 3000, 'S1');
$check('repetir no duplica', $stock(1, 8) === 2000.0 && (int)$erp->query('SELECT COUNT(*) x FROM storehousechanges')->fetch()['x'] === 1);

$transfer->invoke($service, 50, 2, 14, 14, 0, 'S1');
$check('indicar que ya está en destino revierte el traspaso anterior', $stock(1, 8) === 5000.0 && $stock(1, 14) === 0.0);

$transfer->invoke($service, 51, 2, 8, 14, 700, 'S2');
$warning = $service->getLastSupplyConsumptionWarning();
$check('un origen sin stock avisa con el CC y la bodega', str_contains($warning, 'S2') && str_contains($warning, '500 BODEGA') && $stock(2, 8) === -700.0);

$transfer->invoke($service, 50, 2, 8, 14, 1000, 'S1');
$revert = new ReflectionMethod($service, 'revertRemovedMixedTransfers');
$revert->setAccessible(true);
$revert->invoke($service, 50, ['M'], 14);
$check('quitar un CC del pallet deshace su traspaso', $stock(1, 8) === 5000.0 && $stock(1, 14) === 0.0);

$view = (string)file_get_contents($root . '/src/Views/production/sealing/packaging_fields.php');
$check('el formulario ya no ofrece «Misma bodega» a los CC secundarios', str_contains($view, 'Elegir bodega') && substr_count($view, 'Misma bodega') === 1);
$check('en selladora el CC principal no tiene selector de origen', str_contains($view, 'Entra solo a la bodega de destino'));

echo $fail === 0 ? "CIERRE MIXTO OK\n" : "CIERRE MIXTO CON FALLOS: $fail\n";
exit($fail === 0 ? 0 : 1);
