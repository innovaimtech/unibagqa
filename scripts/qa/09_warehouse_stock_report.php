<?php
declare(strict_types=1);
// Bodega → Informe de stock consolidado (ERP sqlite en memoria, solo lectura).
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
$root = dirname(__DIR__, 2);
require_once $root . '/src/bootstrap.php';

$fail = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . ($extra !== '' && !$ok ? " [$extra]" : '') . "\n";
    if (!$ok) {
        $fail++;
    }
};

$erp = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$erp->exec("CREATE TABLE company_shops_storehouses (id INTEGER PRIMARY KEY, st_shop_id INT, st_name TEXT, st_desc TEXT, st_capacidad INT, st_status INT);
CREATE TABLE item_units (id INTEGER PRIMARY KEY, unit_name TEXT);
CREATE TABLE item (id INTEGER PRIMARY KEY, item_number_prod TEXT, item_title TEXT, item_reg_kg REAL, item_unit INT);
CREATE TABLE item_shops_storehouses (id INTEGER PRIMARY KEY, item_id INT, shop_id INT, st_id INT, iss_order_id INT, iss_inventory REAL, iss_inventory_reserved REAL);
CREATE TABLE orders (id INTEGER PRIMARY KEY, req_number TEXT, req_cust_id INT);
CREATE TABLE orders_items (id INTEGER PRIMARY KEY, req_id INT, item_id INT);
CREATE TABLE customer (id INTEGER PRIMARY KEY, cust_name TEXT);
INSERT INTO company_shops_storehouses VALUES (5,30010,'100 (MP PLA)','',0,1),(11,30010,'200 (MP PP)','',0,1),(1,30010,'400 (PROD TERMINADOS REVENTA)','',0,1),
  (10,30010,'700 (BODEGA CANAL TRADICIONAL)','',0,1),(14,30010,'1000 (BODEGA RETAIL A y B)','',0,1),(30,30010,'5000 (PRODUCTOS INMOVILIZADOS)','',0,1),
  (16,30010,'3000 (MP PRODUCCIÓN)','',0,1),(40,30019,'1000 VENTAS','',0,1),(41,30010,'1000 (VIEJA)','',0,0);
INSERT INTO item_units VALUES (1,'BOB'),(2,'C/U');
INSERT INTO item VALUES (1,'TEL0088','PLA/BLANCO/W80/100X80X1500',30,1),(2,'TEL0006','PP/NEGRO/C90/100X80X1100',25,1),
  (3,'BOL0243','BOLSA BOUTIQUE / SALCOBRAND',0,2),(4,'BOL0352','BOLSA BOUTIQUE / TOTTUS',0,2),(5,'BOL0057','Bolsa Algodón 30x35 (Cod. 3035)',0,2),(6,'BOL0022','Promocional Extra Grande',0,2);
INSERT INTO customer VALUES (1,'Farmacias Salcobrand S.A.'),(2,'Hipermercados Tottus S.A.'),(3,'Walmart Chile S.A.');
INSERT INTO orders VALUES (900,'26-00255-1',3),(901,'26-00100-1',2),(902,'26-00101-1',1),(903,'26-00102-1',1);
INSERT INTO orders_items VALUES (1,901,4),(2,902,3),(3,903,3),(4,900,3);
-- bobinas: 400 en 100 (capacidad 1824 por defecto), 96 en 200; una fila en 0 que no cuenta
INSERT INTO item_shops_storehouses VALUES (1,1,30010,5,0,400,0),(2,2,30010,11,0,96,0),(3,2,30010,11,0,0,0),
-- 1000: BOL0243 libre (cliente principal = Salcobrand, 2 CC vs 1 de Walmart) 6.000 + asignado a CC 26-00255-1 de Walmart 1.000
  (4,3,30010,14,0,6000,0),(5,3,30010,14,900,1000,0),
-- 5000: BOL0352 de Tottus 3.000 (sin rotación)
  (6,4,30010,30,901,3000,0),
-- 400: algodón 1.500 y promocional 500; 700: promocional 500
  (7,5,30010,1,0,1500,0),(8,6,30010,1,0,500,0),(9,6,30010,10,0,500,0),
-- bodegas fuera del informe (3000, otra sucursal, inactiva)
  (10,3,30010,16,0,999,0),(11,3,30019,40,0,888,0),(12,3,30010,41,0,777,0);");

$rep = (new WarehouseStockReportService($erp))->report(1_800_000_000);
$by = array_column($rep['warehouses'], null, 'code');

$check('bodegas del informe en orden 100, 200, 400, 700, 1000, 5000', array_keys($by) === [100, 200, 400, 700, 1000, 5000] && $rep['missing'] === [], implode(',', array_keys($by)));
$check('total producto terminado = 400 + 700 + 1000 + 5000 (sin 3000, otra sucursal ni inactiva)', $rep['goods_total'] == 12500, (string)$rep['goods_total']);
$check('participación por bodega', $by[1000]['share'] == 56.0 && $by[5000]['share'] == 24.0 && $by[400]['share'] == 16.0 && $by[700]['share'] == 4.0, json_encode(array_column($rep['warehouses'], 'share', 'code')));
$check('ocupación bobinas con capacidad del Excel', $by[100]['capacity'] === 1824 && $by[100]['occupancy'] == 21.9 && $by[200]['capacity'] === 384 && $by[200]['occupancy'] == 25.0);
$check('kg de bobinas', $by[100]['kg'] == 12000 && $by[200]['kg'] == 2400);
$check('rotación: bodega 5000 = sin rotación', $rep['rotation'] == ['moved' => 9500.0, 'still' => 3000.0, 'still_pct' => 24.0], json_encode($rep['rotation']));
$check('cliente B1000: el de la CC si está asignado, si no el que más lo pidió', $rep['retail_by_customer'] == ['Farmacias Salcobrand' => 6000, 'Walmart' => 1000], json_encode($rep['retail_by_customer'], JSON_UNESCAPED_UNICODE));
$check('sin rotación por cliente', $rep['still_by_customer'] == ['Tottus' => 3000], json_encode($rep['still_by_customer'], JSON_UNESCAPED_UNICODE));
$check('algodón', $rep['cotton_total'] == 1500 && count($rep['cotton']) === 1 && $rep['cotton'][0]['sku'] === 'BOL0057');
$cc = array_values(array_filter($rep['rows'], static fn($r) => $r['cc'] === '26-00255-1'));
$check('detalle: una fila por artículo, bodega y CC; sin filas en 0', count($rep['rows']) === 8 && count($cc) === 1 && $cc[0]['qty'] == 1000 && $cc[0]['warehouse_name'] === '1000 (BODEGA RETAIL A y B)', (string)count($rep['rows']));
$erp->exec('UPDATE company_shops_storehouses SET st_capacidad = 2000 WHERE id = 5');
$rep2 = (new WarehouseStockReportService($erp))->report(1_800_000_000);
$check('capacidad cargada en el ERP manda sobre la del Excel', array_column($rep2['warehouses'], null, 'code')[100]['capacity'] === 2000);

// Pantalla y Excel.
$html = warehouseStockReportPage($rep);
$check('pantalla: indicadores, gráficos y detalle', str_contains($html, 'Informe de stock consolidado') && str_contains($html, 'id="wr-retail"') && str_contains($html, 'window.WR_DATA') && str_contains($html, '21,9 % ocupado'));
$xlsx = SimpleXlsx::fromHtmlSheets([['title' => 'x', 'html' => '<table><tr><td>1</td></tr></table>']]);
$check('Excel: generador disponible', str_starts_with($xlsx, 'PK'));
$check('rutas y menú registrados', str_contains((string)file_get_contents($root . '/public/index.php'), 'handleWarehouseStockReportRoutes(')
    && str_contains((string)file_get_contents($root . '/src/bootstrap.php'), 'WarehouseStockReportModule.php')
    && str_contains((string)file_get_contents($root . '/src/Views/layouts/erp_shell.php'), 'href="/stock/informe"'));

echo $fail === 0 ? "\nINFORME DE BODEGA OK\n" : "\nINFORME DE BODEGA: $fail fallas\n";
exit($fail > 0 ? 1 : 0);
