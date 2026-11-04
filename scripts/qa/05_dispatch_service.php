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
INSERT INTO item VALUES (1,'Bolsa A','SKU-A'),(2,'Bolsa B','SKU-B');
INSERT INTO orders_delivery (id,dlv_num,dlv_docnum,dlv_status,dlv_mode,dlv_delivery_date,dlv_cust_id,dlv_direccion_destino) VALUES
 (10,100,'5001',2,0,1700000000,1,'Dir 1'),
 (11,101,'5002',5,0,1700000000,1,'Dir 1'),
 (12,102,'PENDIENTE_SII',2,0,1700000000,1,'Dir 1'),
 (13,103,'5004',2,3,1700000000,1,'Dir 1'),
 (14,104,'5005',2,0,1700000000,1,'Dir 1');
INSERT INTO orders_delivery_items (dlv_id,item_id,item_pos,item_amount_shipped) VALUES
 (10,1,1,300),(11,1,1,300),(12,1,1,300),(13,1,1,300),(14,1,1,999);");
$trz->exec("CREATE TABLE warehouses (id INTEGER PRIMARY KEY, code INTEGER, name TEXT);
CREATE TABLE pallets (id INTEGER PRIMARY KEY, pallet_code TEXT, final_sku TEXT, warehouse_id INTEGER, status TEXT DEFAULT 'CREATED');
CREATE TABLE boxes (id INTEGER PRIMARY KEY, box_code TEXT, final_sku TEXT, units_qty REAL, pallet_id INTEGER, warehouse_id INTEGER, status TEXT DEFAULT 'CREATED');
CREATE TABLE movements (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INTEGER, movement_type TEXT, from_warehouse_id INTEGER, to_warehouse_id INTEGER, payload TEXT);
CREATE TABLE events (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, payload TEXT);
CREATE TABLE traceability_warehouse_entries (id INTEGER PRIMARY KEY, traceability_code TEXT, entity_type TEXT, entity_id INTEGER, warehouse_id INTEGER, status TEXT DEFAULT 'IN_WAREHOUSE');
INSERT INTO warehouses VALUES (1,400,'Producto terminado'),(2,500,'Otra');
INSERT INTO pallets VALUES (1,'PAL-001','SKU-A',1,'STORED'),(2,'PAL-002','SKU-B',1,'STORED');
INSERT INTO boxes VALUES (1,'BOX-001','SKU-A',100,1,1,'STORED'),(2,'BOX-002','SKU-A',100,1,1,'STORED'),(3,'BOX-003','SKU-A',100,1,1,'STORED'),
 (4,'BOX-004','SKU-B',50,NULL,1,'STORED'),(5,'BOX-005','SKU-B',50,NULL,2,'STORED'),(6,'BOX-006','SKU-B',50,2,1,'STORED');
INSERT INTO traceability_warehouse_entries (traceability_code, entity_type, entity_id, warehouse_id) VALUES ('TRZ-BOX-4','BOX',4,1),('TRZ-PAL-1','PALLET',1,1);");

$svc = new DispatchService($erp, $trz);
$actor = ['name' => 'Tester', 'user_id' => 1];
$k1 = bin2hex(random_bytes(16));
$k2 = bin2hex(random_bytes(16));

// ---- creación
$check('picking sin tipo se rechaza', $throws(fn() => $svc->createPicking($k1, '', 1, '', $actor), 'venta directa'));
$check('picking sin bodega válida se rechaza', $throws(fn() => $svc->createPicking($k1, 'SALE', 99, '', $actor), 'bodega'));
$svc->createPicking($k1, 'SALE', 1, 'nota', $actor);
$svc->createPicking($k1, 'SALE', 1, 'nota', $actor);
$check('crear es idempotente', (int)$trz->query('SELECT COUNT(*) FROM dispatch_pickings')->fetchColumn() === 1);
$check('código DSP generado', str_starts_with((string)$trz->query('SELECT code FROM dispatch_pickings')->fetchColumn(), 'DSP-'));

// ---- escaneo
$check('no cierra vacío', $throws(fn() => $svc->close($k1, $actor), 'al menos'));
$check('código inexistente', $throws(fn() => $svc->scan($k1, 'XXX-999', $actor), 'no registrado'));
$check('caja de otra bodega se bloquea', $throws(fn() => $svc->scan($k1, 'BOX-005', $actor), 'bodega de origen'));
$svc->scan($k1, 'PAL-001', $actor);
$check('pallet suma sus cajas', (float)$trz->query("SELECT qty FROM dispatch_picking_items WHERE code='PAL-001'")->fetchColumn() === 300.0
    && (int)$trz->query("SELECT box_count FROM dispatch_picking_items WHERE code='PAL-001'")->fetchColumn() === 3);
$check('mismo código dos veces se bloquea', $throws(fn() => $svc->scan($k1, 'pal-001', $actor), 'ya está en el picking'));
$check('caja de pallet ya pickeado se bloquea', $throws(fn() => $svc->scan($k1, 'BOX-001', $actor), 'pallet'));
$svc->scan($k1, 'TRZ-BOX-4', $actor);
$check('código de ingreso trazable resuelve la caja', (int)$trz->query("SELECT COUNT(*) FROM dispatch_picking_items WHERE code='BOX-004'")->fetchColumn() === 1);

// ---- segundo picking no puede tomar lo mismo
$svc->createPicking($k2, 'MAQUILA', 1, '', $actor);
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
$check('el despacho no escribió en el ERP', (int)$erp->query('SELECT COUNT(*) FROM orders_delivery')->fetchColumn() === 5);

// ---- una guía no se reutiliza y lo despachado no se vuelve a pickear
$svc->close($k2, $actor);
$check('guía ya usada se rechaza', $svc->validation($k2, '5001')['compare']['errors'] !== []);
$k3 = bin2hex(random_bytes(16));
$svc->createPicking($k3, 'SALE', 1, '', $actor);
$check('pallet despachado no se vuelve a pickear', $throws(fn() => $svc->scan($k3, 'PAL-001', $actor), 'ya salió'));
$svc->cancel($k3, 'prueba de cancelación', $actor);
$check('cancelar deja el picking cancelado', (string)$trz->query('SELECT status FROM dispatch_pickings WHERE picking_key=' . $trz->quote($k3))->fetchColumn() === 'CANCELLED');
$check('listado filtra por estado', count($svc->list('DISPATCHED')) === 1 && count($svc->list('', 'MAQUILA')) === 1);

echo $fail === 0 ? "\nDESPACHO OK\n" : "\nDESPACHO: $fail fallas\n";
exit($fail === 0 ? 0 : 1);
