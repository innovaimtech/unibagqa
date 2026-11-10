<?php
declare(strict_types=1);
// Bodegas visibles: solo las del ERP (activas, sucursal Unibag), una por bodega ERP y con el nombre del ERP.
require_once __DIR__ . '/../../src/WarehouseVisibility.php';

$failures = 0;
function checkWh(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures++;
    }
}

$erp = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$erp->exec('CREATE TABLE company_shops_storehouses (id INTEGER PRIMARY KEY, st_shop_id INTEGER, st_status INTEGER, st_name TEXT)');
$erp->exec("INSERT INTO company_shops_storehouses VALUES
    (1, 30010, 1, '400 (PROD TERMINADOS REVENTA)'),
    (2, 99, 1, 'VENTAS'),
    (5, 30010, 1, '100 (MP PLA)'),
    (16, 30010, 1, '3000 INSUMOS EN PRODUCCION'),
    (21, 30010, 0, '100'),
    (27, 99, 1, '3000 (MP PRODUCCIÓN)')");

$names = WarehouseVisibility::erpNames($erp);
checkWh('solo activas de la sucursal 30010', array_keys($names) === [5, 16, 1] || count($names) === 3 && isset($names[1], $names[5], $names[16]));
checkWh('sin VENTAS (otra sucursal) ni la 21 (inactiva) ni la 27 (otra sucursal)', !isset($names[2]) && !isset($names[21]) && !isset($names[27]));

$trz = [
    ['id' => 1, 'code' => '100', 'name' => '100 viejo', 'erp_storehouse_id' => 5],
    ['id' => 12, 'code' => '400', 'name' => '400 (PROD TERMINADOS REVENTA)', 'erp_storehouse_id' => 1],
    ['id' => 13, 'code' => '2', 'name' => 'VENTAS', 'erp_storehouse_id' => 2],
    ['id' => 18, 'code' => '3000', 'name' => '3000 INSUMOS EN PRODUCCION', 'erp_storehouse_id' => 16],
    ['id' => 23, 'code' => '21', 'name' => '100', 'erp_storehouse_id' => 21],
    ['id' => 28, 'code' => '27', 'name' => '3000 (MP PRODUCCIÓN)', 'erp_storehouse_id' => 27],
    ['id' => 40, 'code' => '3001', 'name' => '3000 repetida', 'erp_storehouse_id' => 16],
    ['id' => 41, 'code' => '999', 'name' => 'sin ERP', 'erp_storehouse_id' => null],
];
$out = WarehouseVisibility::filterTrz($trz, $erp);
$ids = array_map(static fn($r) => (int)$r['id'], $out);
checkWh('quedan solo 100, 400 y 3000 (' . implode(',', $ids) . ')', $ids === [1, 12, 18]);
checkWh('3000 repetida: queda una sola, la de id local menor', !in_array(40, $ids, true));
$byId = array_column($out, null, 'id');
checkWh('el nombre es el del ERP', ($byId[1]['name'] ?? '') === '100 (MP PLA)' && ($byId[1]['trz_name'] ?? '') === '100 viejo');
checkWh('el code no cambia (pantallas lo usan para buscar)', ($byId[1]['code'] ?? '') === '100');
checkWh('shopSql filtra por los ids de Unibag', WarehouseVisibility::shopSql($erp, 's') === 's.id IN (5,16,1)' || str_starts_with(WarehouseVisibility::shopSql($erp, 's'), 's.id IN ('));

// Si la sucursal no tiene bodegas (otro ERP), no se deja la pantalla vacía: se usan todas las activas.
$erp2 = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$erp2->exec('CREATE TABLE company_shops_storehouses (id INTEGER PRIMARY KEY, st_shop_id INTEGER, st_status INTEGER, st_name TEXT)');
$erp2->exec("INSERT INTO company_shops_storehouses VALUES (7, 1, 1, '900 (TINTAS)'), (8, 1, 0, '500')");
$n2 = WarehouseVisibility::erpNames($erp2);
checkWh('sin bodegas de la sucursal: usa las activas de todas', array_keys($n2) === [7]);

// ERP caído: se muestran las bodegas con vínculo al ERP, con su nombre de TRZ.
$down = WarehouseVisibility::filterTrz($trz, null);
checkWh('ERP caído: muestra las que tienen vínculo (sin repetidas)', count($down) === 6 && ($down[0]['name'] ?? '') === '100 viejo');

exit($failures > 0 ? 1 : 0);
