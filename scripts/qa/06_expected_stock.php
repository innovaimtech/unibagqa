<?php
declare(strict_types=1);
// Prueba funcional (sqlite en memoria) del servicio de Stock esperado: cálculo, estados, maestro e informe.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
require_once dirname(__DIR__, 2) . '/src/WarehouseVisibility.php';
require_once dirname(__DIR__, 2) . '/src/ExpectedStockService.php';

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $fail++;
    }
};
$throws = function (callable $fn, string $needle = '') {
    try {
        $fn();
    } catch (Throwable $e) {
        return $needle === '' || stripos($e->getMessage(), $needle) !== false;
    }
    return false;
};

// ---- cálculo puro
$cfg = ['warehouse_code' => 1000, 'units_per_box' => 100, 'boxes_per_pallet' => 10, 'target_units' => 5000, 'min_units' => 1000];
$c = ExpectedStockService::compute($cfg, 3250, true);
$check('3250 un = 3 pallets + 2 cajas + 50 un', $c['pallets'] === 3 && $c['boxes'] === 2 && $c['loose'] === 50 && $c['saldo'] === '3 pallets + 2 cajas + 50 un');
$check('diferencia contra objetivo', $c['diff'] === -1750 && $c['diff_pallets'] === -1.8 && $c['status'] === 'Bajo');
$check('stock 0 no divide por cero', ExpectedStockService::compute($cfg, 0, true)['pallets'] === 0 && ExpectedStockService::compute($cfg, 0, true)['status'] === 'Crítico');
$check('menos de una caja', ExpectedStockService::compute($cfg, 99, true)['boxes'] === 0 && ExpectedStockService::compute($cfg, 99, true)['loose'] === 99);
$check('exacto en pallets', ExpectedStockService::compute($cfg, 2000, true)['saldo'] === '2 pallets + 0 cajas + 0 un');
$check('bajo el mínimo es Crítico', ExpectedStockService::compute($cfg, 999, true)['status'] === 'Crítico');
$check('sobre el objetivo es OK', ExpectedStockService::compute($cfg, 5000, true)['status'] === 'OK');
$check('sin objetivo ni mínimo', ExpectedStockService::compute(['warehouse_code' => 400, 'units_per_box' => 50, 'boxes_per_pallet' => 4], 10, true)['status'] === 'Sin objetivo');
$check('sin embalaje: falta configurar y sin pallets', ExpectedStockService::compute(['warehouse_code' => 400], 10, true)['status'] === 'Falta configurar'
    && ExpectedStockService::compute(['warehouse_code' => 400], 10, true)['pallets'] === null);
$check('sin bodega: falta configurar', ExpectedStockService::compute(['units_per_box' => 1, 'boxes_per_pallet' => 1], 10, true)['status'] === 'Falta configurar');
$check('código inexistente en ERP', ExpectedStockService::compute($cfg, null, false)['status'] === 'No existe en ERP');

// ---- base
$erp = new PDO('sqlite::memory:');
$trz = new PDO('sqlite::memory:');
foreach ([$erp, $trz] as $p) {
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
$erp->exec("CREATE TABLE item (id INTEGER PRIMARY KEY, item_number_prod TEXT, item_title TEXT);
CREATE TABLE item_shops_storehouses (id INTEGER PRIMARY KEY, item_id INTEGER, st_id INTEGER, iss_order_id INTEGER DEFAULT 0, iss_inventory REAL, iss_inventory_reserved REAL);
CREATE TABLE company_shops_storehouses (id INTEGER PRIMARY KEY, st_name TEXT, st_status INTEGER);
INSERT INTO company_shops_storehouses VALUES (10,'1000 (PROD TERMINADOS)',1),(11,'400 (PROD TERMINADOS REVENTA)',1),(12,'3000 INSUMOS',1),(13,'1000 VIEJA',0);
INSERT INTO item VALUES (1,'BOL0250','BOLSA CRUZ VERDE ERP'),(2,'BOL0251','BOLSA EASY ERP'),(3,'ZZZ9999','PRODUCTO NUEVO ERP');
INSERT INTO item_shops_storehouses (item_id,st_id,iss_inventory,iss_inventory_reserved) VALUES
 (1,10,2500,100),(1,10,500,0),(1,11,50,0),(1,12,9999,0),(1,13,777,0),(2,11,300,0);");

$svc = new ExpectedStockService($erp, $trz);
$actor = ['name' => 'Tester'];

// ---- carga inicial
$m = $svc->master(false);
$check('42 códigos iniciales', count($m) === 42);
$check('sin códigos duplicados', count(array_unique(array_column($m, 'item_code'))) === 42);
$check('códigos y descripciones de la lista', (function () use ($m) {
    $by = array_column($m, 'description', 'item_code');
    return ($by['BOL0250'] ?? '') === 'CRUZ VERDE' && ($by['ONL0172'] ?? '') === 'PP BOU 30X40X11 NEGRO' && ($by['BOL0388'] ?? '') === 'SALCOBRAND ALGODÓN 38X42';
})());
$svc2 = new ExpectedStockService($erp, $trz);
$svc2->master(true);
$check('la carga inicial es idempotente', count($svc->master(false)) === 42);
$check('todo parte sin configurar', count(array_filter($m, fn($r) => $r['warehouse_code'] !== null || $r['units_per_box'] !== null)) === 0);

// ---- guardar maestro
$id250 = (int)array_column($m, 'id', 'item_code')['BOL0250'];
$id251 = (int)array_column($m, 'id', 'item_code')['BOL0251'];
$n = $svc->saveRows([
    $id250 => ['warehouse_code' => '1000', 'units_per_box' => '100', 'boxes_per_pallet' => '10', 'target_units' => '5000', 'min_units' => '1000'],
    $id251 => ['warehouse_code' => '1000', 'units_per_box' => '50', 'boxes_per_pallet' => '20'],
], $actor);
$check('guardar cuenta los campos cambiados', $n === 8);
$check('el historial registra quién y qué', count($svc->history()) === 8 && $svc->history()[0]['actor'] === 'Tester');
$check('guardar sin cambios no registra nada', $svc->saveRows([$id250 => ['warehouse_code' => '1000', 'units_per_box' => '100']], $actor) === 0 && count($svc->history()) === 8);
$check('bodega inválida se rechaza', $throws(fn() => $svc->saveRows([$id250 => ['warehouse_code' => '500']], $actor), 'bodega'));
$check('texto en número se rechaza', $throws(fn() => $svc->saveRows([$id250 => ['target_units' => 'abc']], $actor), 'número'));
$check('limpiar un campo lo deja vacío', $svc->saveRows([$id250 => ['min_units' => '']], $actor) === 1
    && $trz->query("SELECT min_units FROM expected_stock_master WHERE id=$id250")->fetchColumn() === null);
$svc->saveRows([$id250 => ['min_units' => '1000']], $actor);

// ---- informe con stock ERP
$rep = $svc->report();
$by = array_column($rep['rows'], null, 'code');
$check('informe trae los 42 activos', count($rep['rows']) === 42 && $rep['erp_ok']);
$check('stock = suma de las filas de la bodega 1000 (activa) y excluye 3000 y bodegas inactivas', $by['BOL0250']['stock'] === 3000);
$check('reservado y disponible', $by['BOL0250']['reserved'] === 100 && $by['BOL0250']['available'] === 2900);
$check('pallets y saldo con el stock real', $by['BOL0250']['pallets'] === 3 && $by['BOL0250']['boxes'] === 0 && $by['BOL0250']['loose'] === 0);
$check('otra bodega muestra lo de la 400', $by['BOL0250']['other_stock'] === 50);
$check('estado contra objetivo', $by['BOL0250']['status'] === 'Bajo' && $by['BOL0250']['diff'] === -2000);
$check('stock de la bodega asignada, no de otra', $by['BOL0251']['stock'] === 0 && $by['BOL0251']['other_stock'] === 300);
$check('código del maestro que no existe en ERP', $by['BOL0247']['status'] === 'No existe en ERP' || $by['BOL0247']['status'] === 'Falta configurar');
$check('nombre ERP disponible', $by['BOL0250']['erp_title'] === 'BOLSA CRUZ VERDE ERP');

// ---- agregar códigos
$r = $svc->addItem('zzz9999', '', $actor);
$check('agregar toma la descripción del ERP', $r['code'] === 'ZZZ9999' && $r['exists_in_erp'] === true
    && $trz->query("SELECT description FROM expected_stock_master WHERE item_code='ZZZ9999'")->fetchColumn() === 'PRODUCTO NUEVO ERP');
$check('código repetido se rechaza', $throws(fn() => $svc->addItem('ZZZ9999', 'x', $actor), 'ya está'));
$check('código fuera del ERP sin descripción se rechaza', $throws(fn() => $svc->addItem('NOP0001', '', $actor), 'descripción'));
$check('código fuera del ERP con descripción se avisa', $svc->addItem('NOP0002', 'Otro producto', $actor)['exists_in_erp'] === false);

// ---- pegar lista
$imp = $svc->importList("PRODUCTO A\tBOL5001\nBOL5002\tPRODUCTO B\n\nBOL5001\tDUPLICADO\nsin codigo aqui\n", $actor);
$check('pegar lista agrega ambos formatos', $imp['added'] === 2 && $trz->query("SELECT description FROM expected_stock_master WHERE item_code='BOL5002'")->fetchColumn() === 'PRODUCTO B');
$check('pegar lista informa lo no agregado', count($imp['skipped']) === 2);

// ---- desactivar
$svc->saveRows([$id251 => ['active' => '0']], $actor);
$check('inactivos salen del informe', !isset(array_column($svc->report()['rows'], null, 'code')['BOL0251']));

// ---- ERP caído
$bad = new PDO('sqlite::memory:');
$bad->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$bad->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$down = (new ExpectedStockService($bad, $trz))->report();
$check('sin ERP el informe no se cae y lo avisa', $down['erp_ok'] === false && $down['error'] !== '' && count($down['rows']) > 0 && $down['rows'][0]['status'] === 'Sin conexión ERP');

echo $fail === 0 ? "\nSTOCK ESPERADO OK\n" : "\nSTOCK ESPERADO: $fail fallas\n";
exit($fail === 0 ? 0 : 1);
