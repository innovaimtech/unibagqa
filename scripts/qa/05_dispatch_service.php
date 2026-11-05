<?php
declare(strict_types=1);
// Prueba funcional (sqlite en memoria) del servicio de Despacho: picking, validación de guía y confirmación.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
require_once dirname(__DIR__, 2) . '/src/DispatchService.php';

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

$erp = new PDO('sqlite::memory:');
$trz = new PDO('sqlite::memory:');
foreach ([$erp, $trz] as $p) {
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
$erp->exec("CREATE TABLE customer (id INTEGER PRIMARY KEY, cust_name TEXT, cust_rut TEXT, cust_street TEXT);
CREATE TABLE item (id INTEGER PRIMARY KEY, item_title TEXT, item_number_prod TEXT);
CREATE TABLE orders_delivery (id INTEGER PRIMARY KEY, dlv_num INTEGER, dlv_docnum TEXT, dlv_status INTEGER, dlv_mode INTEGER,
  dlv_delivery_date INTEGER, dlv_cust_id INTEGER, dlv_direccion_destino TEXT, dlv_oc_number TEXT, dlv_bultos INTEGER,
  dlv_annotation TEXT, dlv_externprod_act INTEGER DEFAULT 0, dlv_externprod_ccnum TEXT);
CREATE TABLE orders_delivery_items (id INTEGER PRIMARY KEY, dlv_id INTEGER, item_id INTEGER, item_pos INTEGER, item_amount_shipped REAL, item_desc TEXT);
INSERT INTO customer VALUES (1,'Cliente SA','11.111.111-1','Calle 1');
INSERT INTO item VALUES (1,'Bolsa A','SKU-A'),(2,'Bolsa B','SKU-B'),(3,'Producto','SKU-P');
INSERT INTO orders_delivery (id,dlv_num,dlv_docnum,dlv_status,dlv_mode,dlv_delivery_date,dlv_cust_id,dlv_direccion_destino) VALUES
 (10,100,'5001',2,0,1700000000,1,'Dir 1'),
 (11,101,'5002',5,0,1700000000,1,'Dir 1'),
 (12,102,'PENDIENTE_SII',2,0,1700000000,1,'Dir 1'),
 (13,103,'5004',2,3,1700000000,1,'Dir 1'),
 (14,104,'5005',2,0,1700000000,1,'Dir 1'),
 (15,105,'5006',2,0,1700000000,1,'Dir 1'),
 (16,106,'5007',2,0,1700000000,1,'Dir 1'),
 (17,107,'5008',2,0,1700000000,1,'Dir 1');
INSERT INTO orders_delivery_items (dlv_id,item_id,item_pos,item_amount_shipped) VALUES
 (10,1,1,300),(11,1,1,300),(12,1,1,300),(13,1,1,300),(14,1,1,999),(15,2,1,70),(16,2,1,30),(17,3,1,40);");
$trz->exec("CREATE TABLE warehouses (id INTEGER PRIMARY KEY, code INTEGER, name TEXT, erp_storehouse_id INTEGER);
CREATE TABLE pallets (id INTEGER PRIMARY KEY, pallet_code TEXT, final_sku TEXT, warehouse_id INTEGER, status TEXT DEFAULT 'CREATED');
CREATE TABLE boxes (id INTEGER PRIMARY KEY, box_code TEXT, final_sku TEXT, units_qty REAL, pallet_id INTEGER, warehouse_id INTEGER, status TEXT DEFAULT 'CREATED');
CREATE TABLE movements (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INTEGER, movement_type TEXT, from_warehouse_id INTEGER, to_warehouse_id INTEGER, payload TEXT);
CREATE TABLE events (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, payload TEXT);
CREATE TABLE traceability_warehouse_entries (id INTEGER PRIMARY KEY, traceability_code TEXT, entity_type TEXT, entity_id INTEGER, warehouse_id INTEGER, warehouse_name TEXT, item_code TEXT, quantity REAL DEFAULT 1, status TEXT DEFAULT 'IN_WAREHOUSE');
INSERT INTO warehouses VALUES (1,400,'Producto terminado',16),(2,500,'Otra',17);
INSERT INTO pallets VALUES (1,'PAL-001','SKU-A',1,'STORED'),(2,'PAL-002','SKU-B',1,'STORED');
INSERT INTO boxes VALUES (1,'BOX-001','SKU-A',100,1,1,'STORED'),(2,'BOX-002','SKU-A',100,1,1,'STORED'),(3,'BOX-003','SKU-A',100,1,1,'STORED'),
 (4,'BOX-004','SKU-B',50,NULL,1,'STORED'),(5,'BOX-005','SKU-B',50,NULL,2,'STORED'),(6,'BOX-006','SKU-B',50,2,1,'STORED');
INSERT INTO traceability_warehouse_entries (traceability_code, entity_type, entity_id, warehouse_id) VALUES ('TRZ-BOX-4','BOX',4,1),('TRZ-PAL-1','PALLET',1,1);
INSERT INTO traceability_warehouse_entries (traceability_code, entity_type, entity_id, warehouse_id, warehouse_name, item_code, quantity) VALUES
 ('TRZ5C4687','PRODUCT',NULL,17,'500 Otra','SKU-P',100),('TRZ000000','PRODUCT',NULL,17,'500 Otra','SKU-P',5);
UPDATE traceability_warehouse_entries SET status='CONSUMED' WHERE traceability_code='TRZ000000';");

$svc = new DispatchService($erp, $trz);
$actor = ['name' => 'Tester', 'user_id' => 1];
$k1 = bin2hex(random_bytes(16));
$k2 = bin2hex(random_bytes(16));

// ---- creación
$check('picking sin tipo se rechaza', $throws(fn() => $svc->createPicking($k1, '', '', $actor), 'venta directa'));
$svc->createPicking($k1, 'SALE', 'nota', $actor);
$svc->createPicking($k1, 'SALE', 'nota', $actor);
$check('crear es idempotente', (int)$trz->query('SELECT COUNT(*) FROM dispatch_pickings')->fetchColumn() === 1);
$check('código DSP generado', str_starts_with((string)$trz->query('SELECT code FROM dispatch_pickings')->fetchColumn(), 'DSP-'));

// ---- escaneo
$check('no cierra vacío', $throws(fn() => $svc->close($k1, $actor), 'al menos'));
$check('código inexistente', $throws(fn() => $svc->scan($k1, 'XXX-999', $actor), 'no registrado'));
$svc->scan($k1, 'PAL-001', $actor);
$check('pallet suma sus cajas', (float)$trz->query("SELECT qty FROM dispatch_picking_items WHERE code='PAL-001'")->fetchColumn() === 300.0
    && (int)$trz->query("SELECT box_count FROM dispatch_picking_items WHERE code='PAL-001'")->fetchColumn() === 3);
$check('mismo código dos veces se bloquea', $throws(fn() => $svc->scan($k1, 'pal-001', $actor), 'ya está en el picking'));
$check('caja de pallet ya pickeado se bloquea', $throws(fn() => $svc->scan($k1, 'BOX-001', $actor), 'pallet'));
$svc->scan($k1, 'TRZ-BOX-4', $actor);
$check('código de ingreso trazable resuelve la caja', (int)$trz->query("SELECT COUNT(*) FROM dispatch_picking_items WHERE code='BOX-004'")->fetchColumn() === 1);

// ---- segundo picking no puede tomar lo mismo
$svc->createPicking($k2, 'MAQUILA', '', $actor);
$check('código en otro picking se bloquea', $throws(fn() => $svc->scan($k2, 'BOX-004', $actor), 'ya está en el picking'));

// ---- quitar / liberar
$check('quitar exige motivo', $throws(fn() => $svc->voidItem(2, 'x', $actor), 'motivo'));
$svc->voidItem(2, 'error de lectura', $actor);
$svc->scan($k2, 'BOX-004', $actor);
$check('quitar libera el código para otro picking', (int)$trz->query("SELECT COUNT(*) FROM dispatch_picking_items WHERE code='BOX-004' AND status='PICKED'")->fetchColumn() === 1);
$check('código tomado por k2 no se puede volver a tomar en k1', $throws(fn() => $svc->scan($k1, 'BOX-004', $actor), 'ya está en el picking'));

// ---- cierre y validación de guía
$check('no se confirma un picking abierto', $throws(fn() => $svc->confirmGuide($k1, '5001', false, $actor), 'cerrado'));
$svc->close($k1, $actor);
$check('no se escanea en picking cerrado', $throws(fn() => $svc->scan($k1, 'BOX-006', $actor), 'cerrado'));
$val = fn(string $n) => $svc->validation($k1, $n)['compare'];
$check('guía inexistente da error', $val('9999')['errors'] !== []);
$check('guía anulada da error', $val('5002')['errors'] !== []);
$check('guía sin folio SII da error', $val('PENDIENTE_SII')['errors'] !== []);
$ok = $val('5001');
$check('guía correcta no tiene errores ni diferencias', $ok['errors'] === [] && $ok['warnings'] === []);
$check('se busca también por número interno GV', $svc->lookupGuide('GV000100')['docnum'] === '5001');
$check('datos de la guía traen cliente e ítems', ($svc->lookupGuide('5001')['customer'] ?? '') === 'Cliente SA' && count($svc->lookupGuide('5001')['items']) === 1);
$diff = $val('5005');
$check('cantidad distinta genera diferencia', $diff['errors'] === [] && $diff['warnings'] !== []);
$check('guía a proveedor con venta directa avisa', count($val('5004')['warnings']) >= 1);
$check('diferencias sin aceptar no confirman', $throws(fn() => $svc->confirmGuide($k1, '5005', false, $actor), 'diferencias'));
$check('guía anulada no confirma', $throws(fn() => $svc->confirmGuide($k1, '5002', true, $actor)));
$check('nada salió tras los rechazos', (string)$trz->query('SELECT status FROM pallets WHERE id=1')->fetchColumn() === 'STORED');

// ---- confirmación
$svc->confirmGuide($k1, '5001', false, $actor);
$check('pallet queda DISPATCHED y sin bodega', $trz->query('SELECT status, warehouse_id FROM pallets WHERE id=1')->fetch() === ['status' => 'DISPATCHED', 'warehouse_id' => null]);
$check('sus cajas salen también', (int)$trz->query("SELECT COUNT(*) FROM boxes WHERE pallet_id=1 AND status='DISPATCHED' AND warehouse_id IS NULL")->fetchColumn() === 3);
$check('caja ajena no se toca', (string)$trz->query('SELECT status FROM boxes WHERE id=6')->fetchColumn() === 'STORED');
$check('movimientos DISPATCH_OUT del pallet y sus cajas', (int)$trz->query("SELECT COUNT(*) FROM movements WHERE payload LIKE '%DISPATCH_OUT%' AND payload LIKE '%5001%'")->fetchColumn() === 4);
$check('etiqueta de ingreso marcada DISPATCHED', (string)$trz->query("SELECT status FROM traceability_warehouse_entries WHERE entity_type='PALLET'")->fetchColumn() === 'DISPATCHED');
$row = $trz->query('SELECT * FROM dispatch_pickings WHERE picking_key=' . $trz->quote($k1))->fetch();
$check('picking asociado a la guía con copia', $row['status'] === 'DISPATCHED' && (int)$row['guide_id'] === 10 && str_contains((string)$row['guide_snapshot'], 'Cliente SA'));
$check('evento DISPATCH_CONFIRMED', (int)$trz->query("SELECT COUNT(*) FROM events WHERE type='DISPATCH_CONFIRMED'")->fetchColumn() === 1);
$mov = (int)$trz->query('SELECT COUNT(*) FROM movements')->fetchColumn();
$svc->confirmGuide($k1, '5001', false, $actor);
$check('reintento es idempotente', (int)$trz->query('SELECT COUNT(*) FROM movements')->fetchColumn() === $mov);
$d = $svc->dispatchOf('PALLET', 1);
$check('trazabilidad del pallet muestra el despacho', $d !== null && $d['guide_number'] === '5001');
$check('el despacho no escribió en el ERP', (int)$erp->query('SELECT COUNT(*) FROM orders_delivery')->fetchColumn() === 8);

// ---- una guía no se reutiliza y lo despachado no se vuelve a pickear
$svc->close($k2, $actor);
$check('guía ya usada se rechaza', $svc->validation($k2, '5001')['compare']['errors'] !== []);
$k3 = bin2hex(random_bytes(16));
$svc->createPicking($k3, 'SALE', '', $actor);
$check('pallet despachado no se vuelve a pickear', $throws(fn() => $svc->scan($k3, 'PAL-001', $actor), 'ya salió'));
$svc->cancel($k3, 'prueba de cancelación', $actor);
$check('cancelar deja el picking cancelado', (string)$trz->query('SELECT status FROM dispatch_pickings WHERE picking_key=' . $trz->quote($k3))->fetchColumn() === 'CANCELLED');
$check('listado filtra por estado', count($svc->list('DISPATCHED')) === 1 && count($svc->list('', 'MAQUILA')) === 1);

// ---- varias bodegas y despacho parcial
$itemId = fn(string $key, string $code): int => (int)$trz->query("SELECT i.id FROM dispatch_picking_items i JOIN dispatch_pickings p ON p.id = i.picking_id
    WHERE p.picking_key = " . $trz->quote($key) . " AND i.code = " . $trz->quote($code) . " AND i.status = 'PICKED'")->fetchColumn();
$k4 = bin2hex(random_bytes(16));
$svc->createPicking($k4, 'SALE', '', $actor);
$svc->scan($k4, 'BOX-005', $actor);
$svc->scan($k4, 'BOX-006', $actor);
$rows = $trz->query("SELECT i.code, i.from_warehouse_id, i.from_warehouse_name FROM dispatch_picking_items i JOIN dispatch_pickings p ON p.id = i.picking_id WHERE p.picking_key = " . $trz->quote($k4) . " ORDER BY i.id")->fetchAll();
$check('cada código toma la bodega donde está', (int)$rows[0]['from_warehouse_id'] === 2 && $rows[0]['from_warehouse_name'] === 'Otra' && (int)$rows[1]['from_warehouse_id'] === 1 && $rows[1]['from_warehouse_name'] === 'Producto terminado');
$detail = $svc->detail($k4);
$check('el picking muestra las dos bodegas', str_contains((string)$detail['warehouse_name'], 'Otra') && str_contains((string)$detail['warehouse_name'], 'Producto terminado'));
$i6 = $itemId($k4, 'BOX-006');
$check('cantidad cero se rechaza', $throws(fn() => $svc->setQuantity($i6, '0', $actor), 'mayor que cero'));
$check('cantidad no numérica se rechaza', $throws(fn() => $svc->setQuantity($i6, 'abc', $actor), 'mayor que cero'));
$check('no se despacha más de lo que tiene la caja', $throws(fn() => $svc->setQuantity($i6, '51', $actor), 'solo tiene 50'));
$svc->setQuantity($i6, '20', $actor);
$check('el picking no toca la caja antes de confirmar', (float)$trz->query('SELECT units_qty FROM boxes WHERE id=6')->fetchColumn() === 50.0);
$check('la guía compara con la cantidad parcial', $svc->validation($k4, '5006')['compare']['errors'] === [] && $svc->validation($k4, '5006')['compare']['warnings'] === []);
$svc->close($k4, $actor);
$check('tras cerrar no cambia la cantidad', $throws(fn() => $svc->setQuantity($i6, '10', $actor), 'abierto'));
$svc->confirmGuide($k4, '5006', false, $actor);
$b6 = $trz->query('SELECT * FROM boxes WHERE id=6')->fetch();
$check('la caja queda con su saldo en la misma bodega', (float)$b6['units_qty'] === 30.0 && (int)$b6['warehouse_id'] === 1 && $b6['status'] === 'STORED');
$check('se conservan las unidades originales', (float)$b6['units_initial'] === 50.0);
$check('el código de la caja no cambia', $b6['box_code'] === 'BOX-006');
$check('la caja completa de la otra bodega sale', (string)$trz->query('SELECT status FROM boxes WHERE id=5')->fetchColumn() === 'DISPATCHED');
$check('el movimiento indica lo despachado y el saldo', str_contains((string)$trz->query("SELECT payload FROM movements WHERE entity_type='BOX' AND entity_id=6 ORDER BY id DESC LIMIT 1")->fetchColumn(), '"remaining":30'));
$k5 = bin2hex(random_bytes(16));
$svc->createPicking($k5, 'SALE', '', $actor);
$svc->scan($k5, 'BOX-006', $actor);
$check('el saldo se puede pickear después y trae solo lo que queda', (float)$trz->query("SELECT qty FROM dispatch_picking_items WHERE id=" . $itemId($k5, 'BOX-006'))->fetchColumn() === 30.0);
$svc->close($k5, $actor);
$svc->confirmGuide($k5, '5007', false, $actor);
$b6 = $trz->query('SELECT * FROM boxes WHERE id=6')->fetch();
$check('con el último saldo la caja sale y no pierde sus unidades originales', $b6['status'] === 'DISPATCHED' && (float)$b6['units_initial'] === 50.0 && $b6['warehouse_id'] === null);
$k6 = bin2hex(random_bytes(16));
$svc->createPicking($k6, 'SALE', '', $actor);
$check('caja sin saldo no se vuelve a pickear', $throws(fn() => $svc->scan($k6, 'BOX-006', $actor), 'ya salió'));

// ---- producto con código de trazabilidad
$check('producto no disponible se rechaza', $throws(fn() => $svc->scan($k6, 'TRZ000000', $actor), 'no está disponible'));
$svc->scan($k6, 'trz5c4687', $actor);
$p1 = $itemId($k6, 'TRZ5C4687');
$rowP = $trz->query("SELECT * FROM dispatch_picking_items WHERE id=$p1")->fetch();
$check('el producto toma la bodega de su ingreso', $rowP['entity_type'] === 'PRODUCT' && (int)$rowP['from_warehouse_id'] === 2 && $rowP['from_warehouse_name'] === '500 Otra' && $rowP['sku'] === 'SKU-P');
$svc->setQuantity($p1, '40', $actor);
$svc->close($k6, $actor);
$svc->confirmGuide($k6, '5008', false, $actor);
$entry = $trz->query("SELECT * FROM traceability_warehouse_entries WHERE traceability_code='TRZ5C4687'")->fetch();
$check('el producto queda con saldo y en bodega', (float)$entry['quantity'] === 60.0 && (float)$entry['quantity_initial'] === 100.0 && $entry['status'] === 'IN_WAREHOUSE');
$check('el movimiento del producto lleva su código', str_contains((string)$trz->query("SELECT payload FROM movements WHERE entity_type='PRODUCT' ORDER BY id DESC LIMIT 1")->fetchColumn(), 'TRZ5C4687'));

// ---- pallet completo y cancelar
$k7 = bin2hex(random_bytes(16));
$svc->createPicking($k7, 'SALE', '', $actor);
$svc->scan($k7, 'PAL-002', $actor);
$check('el pallet no admite cantidad parcial', $throws(fn() => $svc->setQuantity($itemId($k7, 'PAL-002'), '1', $actor), 'pallet'));
$svc->voidItem($itemId($k7, 'PAL-002'), 'prueba quitar', $actor);
$svc->scan($k7, 'TRZ5C4687', $actor);
$svc->setQuantity($itemId($k7, 'TRZ5C4687'), '10', $actor);
$svc->cancel($k7, 'prueba de cancelación', $actor);
$check('cancelar no descuenta nada', (float)$trz->query("SELECT quantity FROM traceability_warehouse_entries WHERE traceability_code='TRZ5C4687'")->fetchColumn() === 60.0);

// ---- la etiqueta conserva las unidades originales
require_once dirname(__DIR__, 2) . '/src/PrintService.php';
foreach (['epl', 'zpl', 'tspl'] as $lang) {
    $printer = new PrintService(['PRINT_LANGUAGE' => $lang]);
    $cmd = $printer->buildBoxCommand(['box_code' => 'BOX-006', 'final_sku' => 'X', 'units_qty' => 30, 'units_initial' => 50]);
    $check(strtoupper($lang) . ': etiqueta de caja con unidades originales', str_contains($cmd, 'UNIDADES: 50') && !str_contains($cmd, 'UNIDADES: 30'));
    $cmd = $printer->buildTraceabilityEntryCommand(['traceability_code' => 'TRZ5C4687', 'item_code' => 'X', 'quantity' => 60, 'quantity_initial' => 100]);
    $check(strtoupper($lang) . ': etiqueta de ingreso con cantidad original', str_contains($cmd, 'CANT: 100') && !str_contains($cmd, 'CANT: 60'));
}
$check('la cantidad parcial no escribió en el ERP', (int)$erp->query('SELECT COUNT(*) FROM orders_delivery')->fetchColumn() === 8);

echo $fail === 0 ? "\nDESPACHO OK\n" : "\nDESPACHO: $fail fallas\n";
exit($fail === 0 ? 0 : 1);
