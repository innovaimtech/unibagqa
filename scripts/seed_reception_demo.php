<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';
require __DIR__ . '/../src/ReceptionService.php';

Env::load(__DIR__ . '/../.env');

$trz = Db::trzPdo();
$erp = Db::erpPdo();
$service = new ReceptionService($trz, $erp);

$suffix = date('ymdHis');
$operator = 'Seeder Recepcion';

upsertWarehouse($trz, 100, '100 (MP PLA)');
upsertWarehouse($trz, 200, '200 (MP PP)');
$warehouse100Id = getWarehouseIdByCode($trz, 100);
$warehouse200Id = getWarehouseIdByCode($trz, 200);
if ($warehouse100Id === null || $warehouse200Id === null) {
    throw new RuntimeException('No se pudieron preparar las bodegas demo de recepcion.');
}

$countryChileId = findOrCreateCountry($erp, 'Chile');
$countryPeruId = findOrCreateCountry($erp, 'Peru');

$nationalSupplierId = createSupplier($erp, [
    'name' => 'Proveedor Demo Nacional ' . $suffix,
    'short' => 'PDN' . substr($suffix, -4),
    'country_id' => $countryChileId,
]);
$importSupplierId = createSupplier($erp, [
    'name' => 'Proveedor Demo Importado ' . $suffix,
    'short' => 'PDI' . substr($suffix, -4),
    'country_id' => $countryPeruId,
]);

$itemNationalId = createItem($erp, 'TEL0006', 'PP/NEGRO/C90/100X80X1100');
$itemImportId = createItem($erp, 'TEL0044', 'PP/BLANCO/W80/100X80X1100');

$now = time();
$nationalPoPendingId = createPurchaseOrder($erp, [
    'number' => 'OC-DEM-NAC-PEND-' . $suffix,
    'supplier_id' => $nationalSupplierId,
    'created_at' => $now,
]);
$nationalPoPartialId = createPurchaseOrder($erp, [
    'number' => 'OC-DEM-NAC-PART-' . $suffix,
    'supplier_id' => $nationalSupplierId,
    'created_at' => $now - 3600,
]);
$importPoId = createPurchaseOrder($erp, [
    'number' => 'OC-DEM-IMP-' . $suffix,
    'supplier_id' => $importSupplierId,
    'created_at' => $now - 7200,
]);

$nationalPendingLineId = createPurchaseOrderLine($erp, [
    'purchase_order_id' => $nationalPoPendingId,
    'item_id' => $itemNationalId,
    'amount' => 3,
    'kgs' => 300.0,
    'description' => 'Bobina demo nacional pendiente',
]);
$nationalPartialLineId = createPurchaseOrderLine($erp, [
    'purchase_order_id' => $nationalPoPartialId,
    'item_id' => $itemNationalId,
    'amount' => 2,
    'kgs' => 180.0,
    'description' => 'Bobina demo nacional parcial',
]);
$importLineId = createPurchaseOrderLine($erp, [
    'purchase_order_id' => $importPoId,
    'item_id' => $itemImportId,
    'amount' => 4,
    'kgs' => 420.0,
    'description' => 'Bobina demo importacion parcial',
]);

$containerId = createImportContainer($erp, [
    'container_code' => 'CONT-DEM-' . $suffix,
    'description' => 'Contenedor demo importacion',
    'vessel' => 'Buque Demo',
    'forwarder' => 'Embarcador Demo',
    'bill_of_lading' => 'BL-DEM-' . $suffix,
    'po_codes' => 'OC-DEM-IMP-' . $suffix,
    'created_at' => $now - 5400,
    'eta_port' => $now + 86400 * 5,
    'eta_plant' => $now + 86400 * 8,
]);
$containerItemId = createImportContainerLine($erp, [
    'container_id' => $containerId,
    'purchase_order_line_id' => $importLineId,
    'amount' => 4,
    'kgs' => 420.0,
]);

$partialNationalReceipt = $service->createRollFromPurchaseOrderLine(
    $nationalPartialLineId,
    $warehouse100Id,
    92.500,
    $operator,
    1,
    'WEIGHT'
);
assertOk($partialNationalReceipt, 'No se pudo crear la recepcion parcial nacional.');

$partialImportReceipt = $service->createRollFromPurchaseOrderLine(
    $importLineId,
    $warehouse200Id,
    105.000,
    $operator,
    1,
    'WEIGHT',
    $containerId,
    $containerItemId
);
assertOk($partialImportReceipt, 'No se pudo crear la recepcion parcial de importacion.');

$result = [
    'national_supplier_id' => $nationalSupplierId,
    'import_supplier_id' => $importSupplierId,
    'national_po_pending' => 'OC-DEM-NAC-PEND-' . $suffix,
    'national_po_partial' => 'OC-DEM-NAC-PART-' . $suffix,
    'import_po' => 'OC-DEM-IMP-' . $suffix,
    'import_container' => 'CONT-DEM-' . $suffix,
    'national_partial_roll_id' => (int)($partialNationalReceipt['roll_id'] ?? 0),
    'import_partial_roll_id' => (int)($partialImportReceipt['roll_id'] ?? 0),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

function upsertWarehouse(PDO $pdo, int $code, string $name): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO warehouses (code, name) VALUES (:code, :name)
         ON DUPLICATE KEY UPDATE name = VALUES(name)'
    );
    $stmt->execute([':code' => $code, ':name' => $name]);
}

function getWarehouseIdByCode(PDO $pdo, int $code): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM warehouses WHERE code = :code LIMIT 1');
    $stmt->execute([':code' => $code]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int)$value;
}

function findOrCreateCountry(PDO $pdo, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM country WHERE country_name = :name LIMIT 1');
    $stmt->execute([':name' => $name]);
    $row = $stmt->fetchColumn();
    if ($row !== false) {
        return (int)$row;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO country (country_name, taxes_active, country_status, country_crtdat, country_crtusr, country_upddat, country_updusr, country_money_type)
         VALUES (:name, 1, 1, :crt_ts, 1, :upd_ts, 1, "CLP")'
    );
    $ts = time();
    $stmt->execute([
        ':name' => $name,
        ':crt_ts' => $ts,
        ':upd_ts' => $ts,
    ]);
    return (int)$pdo->lastInsertId();
}

function createSupplier(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO supplier (
            supp_status, supp_company, supp_short, supp_rut, supp_cellphone, supp_countryid,
            supp_crtusr, supp_crtdat, supp_updusr, supp_upddat, supp_dsc_finance_calc
         ) VALUES (
            1, :company, :short, :rut, "", :country_id,
            1, :crt_ts, 1, :upd_ts, "NET"
         )'
    );
    $ts = time();
    $stmt->execute([
        ':company' => (string)$data['name'],
        ':short' => (string)$data['short'],
        ':rut' => '76.' . random_int(100, 999) . '.' . random_int(100, 999) . '-K',
        ':country_id' => (int)$data['country_id'],
        ':crt_ts' => $ts,
        ':upd_ts' => $ts,
    ]);
    return (int)$pdo->lastInsertId();
}

function createItem(PDO $pdo, string $code, string $title): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO item (
            item_title, item_desc, item_unit, item_unit_amount, item_purchasable, item_sellable,
            item_status, item_crtusr, item_crtdat, item_updusr, item_upddat,
            item_img, item_img_gal1, item_img_gal2, item_img_gal3, item_img_gal4,
            item_number, item_number_prod, item_sellprice_calc_type, item_dct_allprice, item_dct_alltype,
            item_invoice_note, item_ext_prod_act, item_ext_prod_item_id, item_ext_prod_supp_id,
            item_invoicebuy_note, item_unitbuy, item_unitbuy_amount, item_ubicacion, item_nameshop,
            item_fabricate_prefix, item_fabricate_fabrictext, item_fabricate_fuelletext,
            item_reg_width, item_reg_gsm, item_reg_length, item_reg_kg
         ) VALUES (
            :title, :title, "KG", 1.00, 1, 0,
            1, 1, :crt_ts, 1, :upd_ts,
            "", "", "", "", "",
            :number, :number_prod, "", 0, 0,
            "", 0, 0, 0,
            "", 0, 1.00, 0, "",
            "", "", "",
            450.00, 35.00, 2200.00, 100.00
         )'
    );
    $ts = time();
    $number = preg_replace('/[^A-Z0-9]/', '', strtoupper($code)) ?: ('ITEM' . $ts);
    $stmt->execute([
        ':title' => $title,
        ':crt_ts' => $ts,
        ':upd_ts' => $ts,
        ':number' => $number,
        ':number_prod' => $number . 'P',
    ]);
    return (int)$pdo->lastInsertId();
}

function createPurchaseOrder(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO supplier_order (
            sord_title, sord_desc, sord_number, sord_company_id, sord_shop_id, sord_supplier_id,
            sord_date, sord_hash, sord_status, sord_sent, sord_taxes, sord_crtdat, sord_crtusr,
            sord_upddat, sord_updusr, sord_order_shipped, sord_type, sord_desc_intern,
            sord_supplier_dsc_finance_calc, sord_incoterms, sord_plazo_desc
         ) VALUES (
            :title, :desc, :number, 1, 1, :supplier_id,
            :date, :hash, 1, 0, 1, :crt_ts, 1,
            :upd_ts, 1, 0, 0, "",
            "NET", "", ""
         )'
    );
    $stmt->execute([
        ':title' => (string)$data['number'],
        ':desc' => (string)$data['number'],
        ':number' => (string)$data['number'],
        ':supplier_id' => (int)$data['supplier_id'],
        ':date' => (int)$data['created_at'],
        ':hash' => sha1((string)$data['number']),
        ':crt_ts' => (int)$data['created_at'],
        ':upd_ts' => (int)$data['created_at'],
    ]);
    return (int)$pdo->lastInsertId();
}

function createPurchaseOrderLine(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO supplier_order_items (
            sord_id, item_id, item_pos, item_amount, item_amount_shipped, item_type,
            item_costprice_brutto, item_costprice_taxes_perc, item_costprice_netto,
            item_costprice_netto_dsc, item_costprice_taxes, item_discount, item_discount_type,
            item_pcat_dsc_act, item_pcat_dsc_apply_level, item_pcat_dsc_apply_round,
            item_vol_act, item_vol_dsc, item_vol_dsctype, item_desc,
            item_subitem_id, item_subitem_amount, item_kgs, item_image, id
         ) VALUES (
            :sord_id, :item_id, :item_pos, :item_amount, 0, "PRODUCT",
            0, 19, 0, 0, 0, 0, 0,
            0, "", "",
            0, 0, 0, :item_desc,
            0, 0, :item_kgs, "", NULL
         )'
    );
    $stmt->execute([
        ':sord_id' => (int)$data['purchase_order_id'],
        ':item_id' => (int)$data['item_id'],
        ':item_pos' => nextItemPos($pdo, (int)$data['purchase_order_id']),
        ':item_amount' => number_format((float)$data['amount'], 2, '.', ''),
        ':item_desc' => (string)$data['description'],
        ':item_kgs' => number_format((float)$data['kgs'], 2, '.', ''),
    ]);
    return (int)$pdo->lastInsertId();
}

function nextItemPos(PDO $pdo, int $purchaseOrderId): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(item_pos), 0) + 1 FROM supplier_order_items WHERE sord_id = :id');
    $stmt->execute([':id' => $purchaseOrderId]);
    return (int)$stmt->fetchColumn();
}

function createImportContainer(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO supplier_contenedor (
            sord_title, sord_desc, sord_company_id, sord_shop_id, sord_status, sord_crtdat, sord_crtusr,
            sord_upddat, sord_updusr, sord_buque, sord_forward, sord_incoterm, sord_billoflanding,
            sord_eta_puerto, sord_eta_puertounibag, sord_contenedor, sord_diasviaje, sord_limit_paydate, sord_ocs
         ) VALUES (
            :title, :description, 1, 1, 1, :crt_ts, 1,
            :upd_ts, 1, :vessel, :forwarder, "CIF", :bl,
            :eta_port, :eta_plant, :container_code, 8, :limit_paydate, :po_codes
         )'
    );
    $stmt->execute([
        ':title' => (string)$data['container_code'],
        ':description' => (string)$data['description'],
        ':crt_ts' => (int)$data['created_at'],
        ':upd_ts' => (int)$data['created_at'],
        ':vessel' => (string)$data['vessel'],
        ':forwarder' => (string)$data['forwarder'],
        ':bl' => (string)$data['bill_of_lading'],
        ':eta_port' => (int)$data['eta_port'],
        ':eta_plant' => (int)$data['eta_plant'],
        ':limit_paydate' => (int)$data['eta_plant'],
        ':container_code' => (string)$data['container_code'],
        ':po_codes' => (string)$data['po_codes'],
    ]);
    return (int)$pdo->lastInsertId();
}

function createImportContainerLine(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO supplier_contenedor_items (sord_id, sord_pos_id, sord_amount, sord_kgs_amount)
         VALUES (:container_id, :purchase_order_line_id, :amount, :kgs)'
    );
    $stmt->execute([
        ':container_id' => (int)$data['container_id'],
        ':purchase_order_line_id' => (int)$data['purchase_order_line_id'],
        ':amount' => number_format((float)$data['amount'], 2, '.', ''),
        ':kgs' => number_format((float)$data['kgs'], 2, '.', ''),
    ]);
    return (int)$pdo->lastInsertId();
}

function assertOk(array $result, string $message): void
{
    if (($result['ok'] ?? false) === true) {
        return;
    }

    $errors = isset($result['errors']) && is_array($result['errors'])
        ? implode(' | ', array_map(static fn($value): string => (string)$value, array_values($result['errors'])))
        : 'sin detalle';

    throw new RuntimeException($message . ' ' . $errors);
}
