<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Embalaje, Cajas/Pallets, Rebobinado, Corte y Empalme
// (src/Services/Production/RewindAndPackagingService.php)
// =============================================================================

/** Tipo de máquina Selladoras, y máquina y tipo Embalaje del ERP: los embalajes de Corte y Sellado se registran en esta última. */
const UNIBAG_CYS_EQUIPO_TYPE_ID = 8;
const UNIBAG_PACKAGING_EQUIPO_ID = 25;
const UNIBAG_PACKAGING_EQUIPO_TYPE_ID = 15;
const UNIBAG_MIXED_ORIGIN_AT_DESTINATION = -1;

require_once dirname(__DIR__, 2) . '/StockLedgerService.php';
require_once dirname(__DIR__, 2) . '/BagBoxService.php';

trait RewindAndPackagingServiceTrait
{
    /** Aviso (si lo hubo) del último descuento de stock de embalaje; la pantalla lo muestra al operador. */
    private string $lastSupplyConsumptionWarning = '';

    public function getLastSupplyConsumptionWarning(): string
    {
        return $this->lastSupplyConsumptionWarning;
    }

    /** @var int[] Cajas con código creadas por el último cierre de embalaje (para imprimir sus etiquetas). */
    private array $lastCreatedBoxIds = [];

    /** @return int[] */
    public function getLastCreatedBoxIds(): array
    {
        return $this->lastCreatedBoxIds;
    }

    /** @var array<int,mixed>|null Datos del correo de facturación pendiente de enviar tras el commit. */
    private ?array $pendingBillingMail = null;

    /**
     * Envía el correo a facturación del último embalaje guardado. Llamar tras confirmar la transacción.
     * Un fallo de correo nunca interrumpe el guardado: queda como aviso para el operador.
     */
    public function sendPendingPackagingBillingMail(): void
    {
        $args = $this->pendingBillingMail;
        $this->pendingBillingMail = null;
        if ($args === null) {
            return;
        }
        try {
            require_once dirname(__DIR__, 2) . '/MailService.php';
            require_once dirname(__DIR__, 2) . '/PackagingBillingMail.php';
            [$eventId, $workerOtId, $embalajeId, $requestId, $detail, $mixedItems, $totalUnits] = $args;
            $mailRes = (new PackagingBillingMail($this->erpPdo, $this->trzPdo ?? null))->notify(
                $eventId, $workerOtId, $embalajeId, $requestId, $detail, $mixedItems, $totalUnits,
                (int)($_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? 0)
            );
            if (($mailRes['ok'] ?? true) !== true) {
                $warn = 'El embalaje se guardó, pero NO se pudo enviar el correo a facturación: ' . (string)($mailRes['error'] ?? 'error desconocido');
                $this->lastSupplyConsumptionWarning = trim($this->lastSupplyConsumptionWarning . ' ' . $warn);
            }
        } catch (Throwable $e) {
            error_log('Correo de facturación (embalaje): ' . $e->getMessage());
        }
    }

    /**
     * Cinta de embalaje según la caja: SIN logo (caja genérica) = INS0038; CON logo (impresa) = INS0073.
     * Sin logo cuando el pedido tiene "Logotipo empresa" = No (Genérico) o la medida dice SIN LOGO / SIN IMPRESION.
     */
    private function resolvePackagingTapeType(int $workerOtId, string $medidasCaja): string
    {
        $m = mb_strtoupper($medidasCaja);
        if (str_contains($m, 'SIN LOGO') || str_contains($m, 'SIN IMPRESION') || str_contains($m, 'SIN IMPRESIÓN')) {
            return 'INS0038';
        }
        try {
            $st = $this->erpPdo->prepare('SELECT o.req_caja_impresa FROM prod_worker_ot ot JOIN prod_agenda a ON a.id = ot.wok_ag_id
                JOIN orders o ON o.id = a.ag_reqid WHERE ot.id = ?');
            $st->execute([$workerOtId]);
            if (strtoupper(trim((string)$st->fetchColumn())) === 'NO') {
                return 'INS0038';
            }
        } catch (Throwable $e) {
            error_log('resolvePackagingTapeType: ' . $e->getMessage());
        }
        return 'INS0073';
    }

    /** @var string[]|null Columnas reales de prod_worker_embalajes (cache por instancia). */
    private ?array $packagingTableColumns = null;

    /**
     * Deja solo los campos que existen en prod_worker_embalajes del ERP. Algunos datos (p. ej. el tipo de cinta)
     * se usan para calcular el consumo de insumos pero la tabla del ERP puede no tener esa columna; sin este
     * filtro el INSERT/UPDATE fallaba con "Unknown column".
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function onlyExistingPackagingColumns(array $data): array
    {
        if ($this->packagingTableColumns === null) {
            try {
                $cols = $this->erpPdo->query('SHOW COLUMNS FROM prod_worker_embalajes')->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $this->packagingTableColumns = array_map('strval', $cols);
            } catch (Throwable $e) {
                error_log('prod_worker_embalajes columnas: ' . $e->getMessage());
                $this->packagingTableColumns = [];
            }
        }
        if ($this->packagingTableColumns === []) {
            return $data; // no se pudo consultar: comportamiento anterior
        }
        $known = array_flip($this->packagingTableColumns);
        $dropped = array_diff_key($data, $known);
        if ($dropped !== []) {
            error_log('Embalaje: columnas inexistentes en prod_worker_embalajes, no se guardan: ' . implode(', ', array_keys($dropped)));
        }
        return array_intersect_key($data, $known);
    }

    public function listPackagingClosureTypes(int $requestId = 0): array
    {
        $stmt = $this->erpPdo->prepare("SELECT codigo, descripcion, valor1 FROM parametros
            WHERE tabla = 'CIERRES P' ORDER BY codigo ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getMixedPackagingOrders(int $requestId): array
    {
        if ($requestId <= 0) return [];
        $stmt = $this->erpPdo->prepare('SELECT id, req_number, req_id_cc_m1, req_id_cc_m2, req_id_cc_m3,
            req_embalaje_medidas_caja, req_embalaje_bolsas_por_caja_amt,
            req_embalaje_cajas_completas_amt, req_embalaje_caja_final,
            req_operador_mermaperc FROM orders WHERE id = ?');
        $stmt->execute([$requestId]);
        $mainOrder = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$mainOrder) return [];

        $allIds = [(int)$mainOrder['id']];
        foreach (['req_id_cc_m1', 'req_id_cc_m2', 'req_id_cc_m3'] as $f) {
            $mid = (int)($mainOrder[$f] ?? 0);
            if ($mid > 0 && !in_array($mid, $allIds, true)) {
                $allIds[] = $mid;
            }
        }

        $inClause = implode(',', array_fill(0, count($allIds), '?'));
        $sql = "SELECT t1.id, t1.req_number, t1.req_id_cc_m1, t1.req_id_cc_m2, t1.req_id_cc_m3,
                       t1.req_embalaje_medidas_caja, t1.req_embalaje_bolsas_por_caja_amt,
                       t1.req_embalaje_cajas_completas_amt, t1.req_embalaje_caja_final,
                       t1.req_operador_mermaperc,
                       t1x.fab_design_imagehash, t1x.item_id, i.item_number_prod, i.item_title
                FROM orders t1
                INNER JOIN orders_items t1x ON t1.id = t1x.req_id
                LEFT JOIN item i ON i.id = t1x.item_id
                WHERE t1.id IN ($inClause)
                ORDER BY CASE WHEN t1.id = ? THEN 0 ELSE 1 END, t1.id ASC";

        $params = array_merge($allIds, [$requestId]);
        $q = $this->erpPdo->prepare($sql);
        $q->execute($params);
        $orders = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $warehousesSql = "SELECT st.id, st.st_name,
                                 COALESCE((SELECT SUM(x.iss_inventory) FROM item_shops_storehouses x
                                           WHERE x.item_id = i1.id AND x.st_id = st.id), 0) AS stock,
                                 ifnull(iss.iss_inventory, 0) AS stock_order,
                                 i1.id AS id_item, o.id AS id_orders
                          FROM orders o
                          INNER JOIN orders_items oi ON oi.req_id = o.id
                          INNER JOIN item i1 ON i1.id = oi.item_id
                          INNER JOIN customer c ON c.id = o.req_cust_id
                          INNER JOIN bodegacanal bc ON c.cust_canal = bc.bodegacanal_codigo_canal
                          INNER JOIN company_shops_storehouses st ON st.id = bc.bodegacanal_id_bodega
                          LEFT JOIN item_shops_storehouses iss ON iss.item_id = i1.id AND iss.iss_order_id = o.id AND iss.st_id = st.id
                          WHERE o.id IN ($inClause)";
        $wq = $this->erpPdo->prepare($warehousesSql);
        $wq->execute($allIds);
        $allWarehouses = $wq->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $warehouseMap = [];
        foreach ($allWarehouses as $wh) {
            $warehouseMap[(int)$wh['id_orders']][] = $wh;
        }

        foreach ($orders as &$ord) {
            $ord['bodegas'] = $warehouseMap[(int)$ord['id']] ?? [];
        }
        unset($ord);

        return $orders;
    }

    public function getMixedPackagingDetail(int $embalajeId): array
    {
        if ($embalajeId <= 0) return [];
        $stmt = $this->erpPdo->prepare('SELECT * FROM embalaje_mixto WHERE id_embalaje = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$embalajeId]);
        $mixto = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$mixto) return [];

        $detStmt = $this->erpPdo->prepare('SELECT dm.*, o.id AS order_id, oi.fab_design_imagehash, i.item_title
            FROM detalle_mixto dm
            LEFT JOIN orders o ON o.req_number = dm.req_number
            LEFT JOIN orders_items oi ON oi.req_id = o.id
            LEFT JOIN item i ON i.id = dm.id_item
            WHERE dm.id_mixto = ? ORDER BY dm.id ASC');
        $detStmt->execute([(int)$mixto['id']]);
        $details = $detStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // El origen se deduce del traspaso registrado; una línea guardada sin traspaso ya estaba en destino.
        foreach ($details as &$line) {
            $line['origin_wh'] = UNIBAG_MIXED_ORIGIN_AT_DESTINATION;
            foreach ($this->mixedTransferNet($embalajeId, trim((string)($line['req_number'] ?? ''))) as $pair => $qty) {
                if ($qty > 0) {
                    $line['origin_wh'] = (int)explode('>', (string)$pair)[0];
                    break;
                }
            }
        }
        unset($line);

        return [
            'header' => $mixto,
            'items' => $details,
        ];
    }

    /**
     * Traspaso de un CC del pallet mixto entre bodegas: actualiza el stock por pedido
     * (item_shops_storehouses) Y registra el kardex (storehousechanges + storehousechanges_items + tran_data
     * sthdown/sthup), igual que el traspaso entre bodegas del módulo de solicitudes.
     *
     * Es idempotente: cada documento lleva la marca [TRZ-EMB:embalaje:CC:origen>destino:cantidad]. Al volver a
     * guardar el embalaje solo se registra la DIFERENCIA (o la reversa si cambió el origen o el destino);
     * antes cada guardado movía el stock otra vez. Lanza RuntimeException si algo falla (no se traga el error).
     */
    private function transferMixedStock(int $embalajeId, int $itemId, int $fromWh, int $toWh, int $amount, string $reqNumber): void
    {
        $order = $this->erpPdo->prepare('SELECT id, req_company_id, req_shop_id FROM orders WHERE req_number = ? LIMIT 1');
        $order->execute([$reqNumber]);
        $o = $order->fetch(PDO::FETCH_ASSOC);
        if (!$o || (int)$o['req_company_id'] <= 0 || (int)$o['req_shop_id'] <= 0) {
            throw new RuntimeException('No se encontró el pedido ' . $reqNumber . ' para registrar el traspaso de bodega.');
        }
        $prefix = '[TRZ-EMB:' . $embalajeId . ':' . $reqNumber . ':';
        $net = $this->mixedTransferNet($embalajeId, $reqNumber);
        $want = $fromWh . '>' . $toWh;
        // Pares anteriores distintos al deseado: se revierten
        foreach ($net as $pair => $qty) {
            if ($pair === $want || $qty <= 0) continue;
            [$a, $b] = array_map('intval', explode('>', $pair));
            $this->postStorehouseTransfer($o, $itemId, $b, $a, $qty, $reqNumber, $prefix . $b . '>' . $a . ':' . $qty . '] reversa por cambio de bodega');
        }
        $delta = $amount - (int)($net[$want] ?? 0);
        if ($delta > 0) {
            $this->warnIfOriginWithoutStock($itemId, (int)$o['req_shop_id'], $fromWh, (int)$o['id'], $delta, $reqNumber);
            $this->postStorehouseTransfer($o, $itemId, $fromWh, $toWh, $delta, $reqNumber, $prefix . $fromWh . '>' . $toWh . ':' . $delta . ']');
        } elseif ($delta < 0) {
            $this->postStorehouseTransfer($o, $itemId, $toWh, $fromWh, -$delta, $reqNumber, $prefix . $toWh . '>' . $fromWh . ':' . (-$delta) . '] reversa por menor cantidad');
        }
    }

    /** Neto ya traspasado por (origen>destino) para este embalaje y CC, según las marcas de los documentos. */
    private function mixedTransferNet(int $embalajeId, string $reqNumber): array
    {
        $docs = $this->erpPdo->prepare('SELECT strc_desc FROM storehousechanges WHERE strc_desc LIKE ?');
        $docs->execute(['%[TRZ-EMB:' . $embalajeId . ':' . $reqNumber . ':%']);
        $net = [];
        foreach ($docs->fetchAll(PDO::FETCH_COLUMN) as $desc) {
            if (preg_match('/\[TRZ-EMB:' . preg_quote((string)$embalajeId, '/') . ':' . preg_quote($reqNumber, '/') . ':(\d+)>(\d+):(\d+)\]/', (string)$desc, $m) === 1) {
                $net[$m[1] . '>' . $m[2]] = ($net[$m[1] . '>' . $m[2]] ?? 0) + (int)$m[3];
            }
        }
        // Compensar movimientos opuestos para quedar con el neto por par
        foreach (array_keys($net) as $pair) {
            [$a, $b] = explode('>', $pair);
            $opp = $b . '>' . $a;
            if (isset($net[$pair], $net[$opp]) && $net[$pair] > 0 && $net[$opp] > 0) {
                $d = min($net[$pair], $net[$opp]);
                $net[$pair] -= $d;
                $net[$opp] -= $d;
            }
        }
        return $net;
    }

    /** El traspaso se registra igual porque las bolsas ya se movieron; el aviso permite a bodega corregir el stock. */
    private function warnIfOriginWithoutStock(int $itemId, int $shopId, int $fromWh, int $orderId, int $qty, string $reqNumber): void
    {
        $stmt = $this->erpPdo->prepare('SELECT iss_inventory FROM item_shops_storehouses WHERE item_id = ? AND shop_id = ? AND st_id = ? AND iss_order_id = ?');
        $stmt->execute([$itemId, $shopId, $fromWh, $orderId]);
        $available = (float)$stmt->fetchColumn();
        if ($available >= $qty) {
            return;
        }
        $name = $this->erpPdo->prepare('SELECT st_name FROM company_shops_storehouses WHERE id = ?');
        $name->execute([$fromWh]);
        $this->lastSupplyConsumptionWarning = trim($this->lastSupplyConsumptionWarning . ' CC ' . $reqNumber . ': la bodega '
            . (string)($name->fetchColumn() ?: $fromWh) . ' tenía ' . (int)round($available) . ' y salieron ' . $qty
            . '. Quedó con stock negativo: avisa a bodega.');
    }

    /** Si un CC se quitó del pallet al corregir el cierre, deshace su traspaso. */
    private function revertRemovedMixedTransfers(int $embalajeId, array $keepReqNumbers, int $destWh): void
    {
        $stmt = $this->erpPdo->prepare('SELECT h.strc_desc, si.item_id FROM storehousechanges h JOIN storehousechanges_items si ON si.strc_id = h.id WHERE h.strc_desc LIKE ?');
        $stmt->execute(['%[TRZ-EMB:' . $embalajeId . ':%']);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
            if (preg_match('/\[TRZ-EMB:' . $embalajeId . ':([^:\]]+):/', (string)$doc['strc_desc'], $m) === 1) {
                $items[$m[1]] = (int)$doc['item_id'];
            }
        }
        foreach ($items as $reqNumber => $itemId) {
            if (!in_array((string)$reqNumber, $keepReqNumbers, true)) {
                $this->transferMixedStock($embalajeId, $itemId, $destWh, $destWh, 0, (string)$reqNumber);
            }
        }
    }

    /** Un movimiento: stock por pedido + documento de traspaso + kardex (sthdown / sthup). */
    private function postStorehouseTransfer(array $order, int $itemId, int $fromWh, int $toWh, int $qty, string $reqNumber, string $marker): void
    {
        if ($qty <= 0 || $fromWh <= 0 || $toWh <= 0 || $fromWh === $toWh) {
            return;
        }
        $orderId = (int)$order['id'];
        $shopId = (int)$order['req_shop_id'];
        $companyId = (int)$order['req_company_id'];
        $item = $this->erpPdo->prepare('SELECT item_title FROM item WHERE id = ?');
        $item->execute([$itemId]);
        $title = $item->fetchColumn();
        if ($title === false) {
            throw new RuntimeException('Artículo ' . $itemId . ' no encontrado en el ERP.');
        }
        $company = $this->erpPdo->prepare('SELECT company_numformat_storehouse, company_numcounter_storehouse FROM company_data WHERE id = ?');
        $company->execute([$companyId]);
        $c = $company->fetch(PDO::FETCH_ASSOC);
        if (!$c) {
            throw new RuntimeException('Contador de traspasos no disponible para la empresa ' . $companyId . '.');
        }

        // Stock por pedido (filas con iss_order_id, como el sistema anterior)
        $before = [];
        foreach ([$fromWh, $toWh] as $st) {
            $q = $this->erpPdo->prepare('SELECT iss_inventory FROM item_shops_storehouses WHERE item_id = ? AND shop_id = ? AND st_id = ? AND iss_order_id = ?');
            $q->execute([$itemId, $shopId, $st, $orderId]);
            $cur = $q->fetchColumn();
            if ($cur === false) {
                $this->erpPdo->prepare('INSERT INTO item_shops_storehouses (item_id, shop_id, st_id, iss_order_amount, iss_inventory_min, iss_inventory, iss_inventory_reserved, iss_order_id)
                    VALUES (?, ?, ?, 0, 0, 0, 0, ?)')->execute([$itemId, $shopId, $st, $orderId]);
                $cur = 0;
            }
            $before[$st] = (float)$cur;
        }
        $this->erpPdo->prepare('UPDATE item_shops_storehouses SET iss_inventory = iss_inventory - ? WHERE item_id = ? AND shop_id = ? AND st_id = ? AND iss_order_id = ?')
            ->execute([$qty, $itemId, $shopId, $fromWh, $orderId]);
        $this->erpPdo->prepare('UPDATE item_shops_storehouses SET iss_inventory = iss_inventory + ? WHERE item_id = ? AND shop_id = ? AND st_id = ? AND iss_order_id = ?')
            ->execute([$qty, $itemId, $shopId, $toWh, $orderId]);

        // Documento de traspaso + líneas + kardex
        $counter = (int)$c['company_numcounter_storehouse'] + 1;
        $number = ($c['company_numformat_storehouse'] ?: 'TR') . sprintf('%07d', $counter);
        $this->erpPdo->prepare('UPDATE company_data SET company_numcounter_storehouse = ? WHERE id = ?')->execute([$counter, $companyId]);
        $now = time();
        $userId = (int)($_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? 0);
        $desc = 'Cierre mixto de embalaje. CC ' . $reqNumber . ' ' . $qty . ' un. ' . $marker;
        $this->erpPdo->prepare('INSERT INTO storehousechanges
            (strc_company_id,strc_shop_id,strc_company_dest_id,strc_shop_dest_id,strc_status,strc_date,strc_desc,strc_number,strc_crtusr,strc_crtdat,
             strc_order_id,strc_order_id_dest,strc_order_num,strc_order_num_dest,strc_shopsent,strc_shopreceived,strc_custid,strc_rel_reqnum)
            VALUES (?,?,?,?,2,?,?,?,?,?,?,?,?,?,0,0,0,?)')->execute([
                $companyId, $shopId, $companyId, $shopId, $now, $desc, $number, $userId, $now, $orderId, $orderId, $reqNumber, $reqNumber, $reqNumber]);
        $docId = (int)$this->erpPdo->lastInsertId();
        $this->erpPdo->prepare("INSERT INTO storehousechanges_items(strc_id,item_id,item_pos,item_amount,item_type,item_st_id,item_st_dest_id)
            VALUES (?,?,0,?,'item',?,?)")->execute([$docId, $itemId, $qty, $fromWh, $toWh]);
        foreach ([$fromWh => ['sthdown', 0], $toWh => ['sthup', 1]] as $st => [$type, $pos]) {
            $this->erpPdo->prepare("INSERT INTO tran_data(tran_id,tran_pos,tran_type,tran_number,tran_currstock,tran_amount,
                tran_company_id,tran_shop_id,tran_st_id,item_id,item_title,item_type,tran_day,tran_month,tran_year,tran_crtusr,tran_crtdat,tran_order_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,'item',?,?,?,?,?,?)")->execute([
                    $docId, $pos, $type, $number, $before[$st], $qty, $companyId, $shopId, $st, $itemId, (string)$title,
                    (int)date('j', $now), (int)date('n', $now), (int)date('Y', $now), $userId, $now, $orderId]);
        }
    }

    /** CyS records packing quantities, not supervisor-authorized packaging closure.
     *  CyS registra cantidades de embalaje, no el cierre autorizado por supervisor. */
    public static function normalizeCysProductionPacking(array $input): array
    {
        $detail = [
            'req_embalaje_medidas_caja' => trim((string)($input['req_embalaje_medidas_caja'] ?? '')),
            'req_embalaje_tipo_cinta' => trim((string)($input['req_embalaje_tipo_cinta'] ?? 'INS0073')),
        ];
        if (strlen($detail['req_embalaje_medidas_caja']) > 255) throw new RuntimeException('Medida de caja demasiado larga.');
        foreach (['req_embalaje_bolsas_por_caja_amt','req_embalaje_cajas_por_pallet_amt',
            'req_embalaje_pallets_completos_amt','req_embalaje_palletcajas_incompletos_amt',
            'req_embalaje_cajas_completas_amt','req_embalaje_caja_final',
            'req_embalaje_bolsas_sobrantes_amt','req_embalaje_showroom'] as $field) {
            $value = filter_var($input[$field] ?? 0,FILTER_VALIDATE_INT);
            if ($value === false || $value<0 || $value>100000000) {
                throw new RuntimeException('Las cantidades de embalaje deben ser enteros positivos o cero.');
            }
            $detail[$field] = $value;
        }
        if ($detail['req_embalaje_cajas_completas_amt']>0 && $detail['req_embalaje_bolsas_por_caja_amt']<=0) {
            throw new RuntimeException('Indica la cantidad de bolsas por caja.');
        }
        $total = $detail['req_embalaje_cajas_completas_amt']*$detail['req_embalaje_bolsas_por_caja_amt']
            +$detail['req_embalaje_caja_final']+$detail['req_embalaje_bolsas_sobrantes_amt']+$detail['req_embalaje_showroom'];
        if ($total>2147483647) throw new RuntimeException('Cantidad total de bolsas demasiado grande.');
        return $detail;
    }

    public function saveCysProductionPacking(int $eventId, int $workerOtId, array $input): void
    {
        $detail = self::normalizeCysProductionPacking($input);
        if (!$this->getCysSetupMeasuresStatus($workerOtId)['required']) throw new RuntimeException('El detalle corresponde solo a Corte y Sellado.');
        $stmt = $this->erpPdo->prepare("SELECT a.ag_reqid FROM prod_worker_ot_events e
            JOIN prod_worker_ot ot ON ot.id=e.evt_prod_worker_otid JOIN prod_agenda a ON a.id=ot.wok_ag_id
            WHERE e.id=? AND ot.id=? AND e.evt_type='prod'");
        $stmt->execute([$eventId,$workerOtId]);
        $requestId = (int)$stmt->fetchColumn();
        if ($requestId<=0) throw new RuntimeException('El detalle no pertenece a la produccion de esta OT.');
        $data = $detail + ['evt_prod_worker_otid'=>$workerOtId,'evt_reqid'=>$requestId,
            'evt_crtdat'=>time(),'req_v2refid'=>$eventId];
        $existing = $this->getPackagingEventDetail($eventId,$workerOtId);
        if (!$existing) $data += ['req_codigo_cierre'=>'','req_observacion'=>''];
        $data = $this->onlyExistingPackagingColumns($data);
        $params = [];
        foreach ($data as $field=>$value) $params[':'.$field]=$value;
        if ($existing) {
            $params[':id']=(int)$existing['id'];
            $sql='UPDATE prod_worker_embalajes SET '.implode(',',array_map(static fn($f)=>"$f=:$f",array_keys($data))).' WHERE id=:id';
        } else {
            $sql='INSERT INTO prod_worker_embalajes ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_keys($params)).')';
        }
        $this->erpPdo->prepare($sql)->execute($params);
        try {
            $this->consumePackagingSupplies($workerOtId, $eventId, $detail, '');
        } catch (Throwable $e) {
            error_log('Error en consumePackagingSupplies (CyS): ' . $e->getMessage());
        }
    }

    public static function normalizePackagingDetail(array $input): array
    {
        $detail = [];
        foreach (['req_embalaje_bolsas_por_caja_amt', 'req_embalaje_cajas_por_pallet_amt',
            'req_embalaje_pallets_completos_amt', 'req_embalaje_palletcajas_incompletos_amt',
            'req_embalaje_cajas_completas_amt', 'req_embalaje_caja_final',
            'req_embalaje_bolsas_sobrantes_amt', 'req_embalaje_showroom', 'req_id_bodega'] as $field) {
            $value = filter_var($input[$field] ?? 0, FILTER_VALIDATE_INT);
            if ($value === false || $value < 0 || $value > 100000000) {
                throw new RuntimeException('Las cantidades de embalaje deben ser numeros enteros positivos o cero.');
            }
            $detail[$field] = $value;
        }
        foreach (['req_embalaje_medidas_caja', 'req_codigo_cierre', 'req_observacion', 'req_embalaje_tipo_cinta'] as $field) {
            $detail[$field] = trim((string)($input[$field] ?? ''));
        }
        if (empty($detail['req_embalaje_tipo_cinta'])) {
            $detail['req_embalaje_tipo_cinta'] = 'INS0073';
        }
        if ($detail['req_embalaje_medidas_caja'] === '' || strlen($detail['req_embalaje_medidas_caja']) > 255 || $detail['req_codigo_cierre'] === '') {
            throw new RuntimeException('Indique medida de caja y clasificacion de cierre.');
        }
        if ($detail['req_id_bodega'] <= 0) {
            throw new RuntimeException('Indique la bodega de destino.');
        }
        if ($detail['req_embalaje_cajas_por_pallet_amt'] <= 0) {
            throw new RuntimeException('Indique las cajas por pallet.');
        }
        // Nota: "pallets completos" y "cajas en pallet incompleto" son valores DERIVADOS del total
        // de cajas y de las cajas por pallet. Se recalculan más abajo en el servidor; antes, si el
        // formulario los enviaba desfasados, el guardado fallaba con un error de validación.

        $isMixto = ($detail['req_codigo_cierre'] === '50');

        $mixedItems = [];
        $totalMixedBoxes = 0;
        $totalMixedUnits = 0;
        if (!empty($input['mixed_items']) && is_array($input['mixed_items'])) {
            $mixedItems = $input['mixed_items'];
            foreach ($mixedItems as $m) {
                $totalMixedBoxes += (int)($m['total_cajas'] ?? 0) + ((int)($m['bolsas_caja_final'] ?? 0) > 0 ? 1 : 0);
                $totalMixedUnits += (int)($m['total_bolsas'] ?? 0);
            }
        } else {
            // Se recorren los índices enviados: al quitar una fila quedan huecos y las siguientes también cuentan.
            $indexes = [];
            foreach (array_keys($input) as $key) {
                if (preg_match('/^(?:mixed_req_number|total_cajas)_(\d+)$/', (string)$key, $m) === 1) {
                    $indexes[(int)$m[1]] = true;
                }
            }
            ksort($indexes);
            foreach (array_keys($indexes) as $idx) {
                $reqNum = trim((string)($input['mixed_req_number_' . $idx] ?? ''));
                $totCajas = (int)($input['total_cajas_' . $idx] ?? 0);
                $bpc = (int)($input['bolsas_por_caja_' . $idx] ?? 0);
                $bcf = (int)($input['bolsas_caja_final_' . $idx] ?? 0);
                $bsobr = (int)($input['bolsas_sobrante_' . $idx] ?? 0);
                $itemId = (int)($input['mixed_item_id_' . $idx] ?? 0);
                $whId = (int)($input['req_id_bodega_' . $idx] ?? 0);
                $totBolsas = ($totCajas * $bpc) + $bcf + $bsobr;
                // La fila 0 es el CC del cierre. Sin esto, las bolsas de otro CC quedarían en su bodega sin aviso.
                if ($isMixto && $idx > 0 && $totBolsas > 0 && $whId === 0) {
                    throw new RuntimeException('Elige la bodega de origen de las bolsas del CC ' . $reqNum . '.');
                }

                if ($reqNum !== '' || $totCajas > 0 || $totBolsas > 0) {
                    $mixedItems[] = [
                        'req_number' => $reqNum,
                        'total_cajas' => $totCajas,
                        'bolsas_por_caja' => $bpc,
                        'bolsas_caja_final' => $bcf,
                        'bolsas_sobrante' => $bsobr,
                        'total_bolsas' => $totBolsas,
                        'id_item' => $itemId,
                        'req_id_bodega' => $whId,
                    ];
                    $totalMixedBoxes += $totCajas + ($bcf > 0 ? 1 : 0);
                    $totalMixedUnits += $totBolsas;
                }
            }
        }
        $detail['mixed_items'] = $mixedItems;

        if (!$isMixto) {
            if ($detail['req_embalaje_bolsas_por_caja_amt'] <= 0) {
                throw new RuntimeException('Indique unidades por caja.');
            }
            if ($detail['req_embalaje_caja_final'] >= $detail['req_embalaje_bolsas_por_caja_amt'] && $detail['req_embalaje_bolsas_por_caja_amt'] > 0) {
                throw new RuntimeException('La caja final debe tener menos unidades que una caja completa.');
            }
            // Reparto de pallets calculado a partir de las cajas (completas + la caja final, si existe).
            $totalBoxesStd = $detail['req_embalaje_cajas_completas_amt'] + ($detail['req_embalaje_caja_final'] > 0 ? 1 : 0);
            $detail['req_embalaje_pallets_completos_amt'] = intdiv($totalBoxesStd, $detail['req_embalaje_cajas_por_pallet_amt']);
            $detail['req_embalaje_palletcajas_incompletos_amt'] = $totalBoxesStd % $detail['req_embalaje_cajas_por_pallet_amt'];
            $detail['total_units'] = $detail['req_embalaje_cajas_completas_amt'] * $detail['req_embalaje_bolsas_por_caja_amt']
                + $detail['req_embalaje_caja_final'] + $detail['req_embalaje_bolsas_sobrantes_amt'] + $detail['req_embalaje_showroom'];
        } else {
            $mainUnits = $detail['req_embalaje_cajas_completas_amt'] * $detail['req_embalaje_bolsas_por_caja_amt']
                + $detail['req_embalaje_caja_final'] + $detail['req_embalaje_bolsas_sobrantes_amt'] + $detail['req_embalaje_showroom'];
            $detail['total_units'] = $mainUnits > 0 ? $mainUnits : ($totalMixedUnits > 0 ? $totalMixedUnits : 1);
            // Cierre mixto: el reparto de pallets se calcula con las cajas de TODOS los CC del pallet.
            if ($totalMixedBoxes > 0) {
                $detail['req_embalaje_pallets_completos_amt'] = intdiv($totalMixedBoxes, $detail['req_embalaje_cajas_por_pallet_amt']);
                $detail['req_embalaje_palletcajas_incompletos_amt'] = $totalMixedBoxes % $detail['req_embalaje_cajas_por_pallet_amt'];
            } else {
                $detail['req_embalaje_palletcajas_incompletos_amt'] = min(
                    $detail['req_embalaje_palletcajas_incompletos_amt'],
                    max(0, $detail['req_embalaje_cajas_por_pallet_amt'] - 1)
                );
            }
        }

        if ($detail['total_units'] <= 0) {
            throw new RuntimeException('Debe registrar al menos una unidad embalada.');
        }
        if ($detail['total_units'] > 2147483647) {
            throw new RuntimeException('La cantidad total de unidades es demasiado grande.');
        }
        return $detail;
    }

    /** Lo que puede rechazar un cierre de embalaje por datos del formulario. */
    public function assertPackagingCloseAllowed(array $detail, string $supervisor, string $password): void
    {
        if (!$this->validateSupervisor($supervisor, $password)) {
            throw new RuntimeException('Las credenciales del supervisor no son validas.');
        }
        $warehouse = $this->erpPdo->prepare('SELECT st_name FROM company_shops_storehouses WHERE id = :id AND st_status = 1');
        $warehouse->execute([':id' => (int)($detail['req_id_bodega'] ?? 0)]);
        $destName = $warehouse->fetchColumn();
        if ($destName === false || !WarehouseVisibility::isPackagingDestination((string)$destName)) {
            throw new RuntimeException('Bodega de destino no valida: solo se permiten las bodegas 400, 500, 700 y 1000.');
        }
    }

    public function savePackagingEventDetail(int $eventId, int $workerOtId, array $detail, string $supervisor, string $password): int
    {
        $detail = self::normalizePackagingDetail($detail);
        $this->assertPackagingCloseAllowed($detail, $supervisor, $password);
        // Un embalaje de selladora ya cerrado vive en la OT de Embalaje, enlazado con la OT de la selladora.
        $stmt = $this->erpPdo->prepare('SELECT a.ag_reqid, a.ag_equipotype_id, e.overrideembalaje_selladora_evtrefid AS linked_ot, o.req_number FROM prod_worker_ot_events e JOIN prod_worker_ot ot ON ot.id = e.evt_prod_worker_otid JOIN prod_agenda a ON a.id = ot.wok_ag_id LEFT JOIN orders o ON o.id = a.ag_reqid WHERE e.id = :event AND (ot.id = :ot OR (e.overrideembalaje_act = 1 AND e.overrideembalaje_selladora_evtrefid = :linked_ot))');
        $stmt->execute([':event' => $eventId, ':ot' => $workerOtId, ':linked_ot' => $workerOtId]);
        $eventOrder = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $requestId = (int)($eventOrder['ag_reqid'] ?? 0);
        if ($requestId <= 0) {
            throw new RuntimeException('No se encontro el pedido asociado al evento.');
        }
        // En selladora (stock automático) y en la máquina Embalaje (lo carga el sistema al terminar la OT) el CC del cierre
        // entra solo a la bodega de destino: si además se traspasa, queda doble en destino.
        $mainStockIsAutomatic = in_array((int)($eventOrder['ag_equipotype_id'] ?? 0), [UNIBAG_CYS_EQUIPO_TYPE_ID, UNIBAG_PACKAGING_EQUIPO_TYPE_ID], true)
            || (int)($eventOrder['linked_ot'] ?? 0) > 0;
        $mainReqNumber = trim((string)($eventOrder['req_number'] ?? ''));
        $user = $this->erpPdo->prepare('SELECT id FROM user WHERE user_login = :login AND user_status = 1 LIMIT 1');
        $user->execute([':login' => trim($supervisor)]);
        $supervisorId = (int)$user->fetchColumn();

        $mixedItems = $detail['mixed_items'] ?? [];
        $totalUnitsForMail = (int)($detail['total_units'] ?? 0);
        unset($detail['total_units'], $detail['mixed_items']);

        $data = $detail + ['evt_prod_worker_otid' => $workerOtId, 'evt_reqid' => $requestId,
            'evt_crtdat' => time(), 'req_id_supervisor' => $supervisorId, 'req_v2refid' => $eventId];
        $data = $this->onlyExistingPackagingColumns($data);
        $existing = $this->getPackagingEventDetail($eventId, $workerOtId);
        $params = [];
        foreach ($data as $field => $value) $params[':' . $field] = $value;
        if ($existing !== []) {
            $embalajeId = (int)$existing['id'];
            $params[':id'] = $embalajeId;
            $sql = 'UPDATE prod_worker_embalajes SET ' . implode(', ', array_map(static fn($f) => "$f = :$f", array_keys($data))) . ' WHERE id = :id';
            $this->erpPdo->prepare($sql)->execute($params);
        } else {
            $sql = 'INSERT INTO prod_worker_embalajes (' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', array_keys($params)) . ')';
            $this->erpPdo->prepare($sql)->execute($params);
            $embalajeId = (int)$this->erpPdo->lastInsertId();
        }

        if ($detail['req_codigo_cierre'] === '50') {
            $stmtMx = $this->erpPdo->prepare('SELECT id FROM embalaje_mixto WHERE id_embalaje = ? LIMIT 1');
            $stmtMx->execute([$embalajeId]);
            $existingMxId = (int)$stmtMx->fetchColumn();

            $sumCajas = 0;
            $sumBolsas = 0;
            foreach ($mixedItems as $m) {
                $sumCajas += (int)($m['total_cajas'] ?? 0);
                $sumBolsas += (int)($m['total_bolsas'] ?? 0);
            }

            if ($existingMxId > 0) {
                $idMixto = $existingMxId;
                $updMx = $this->erpPdo->prepare('UPDATE embalaje_mixto SET cantidad_cajas = ?, total_bolsa = ? WHERE id = ?');
                $updMx->execute([$sumCajas, $sumBolsas, $idMixto]);
                $this->erpPdo->prepare('DELETE FROM detalle_mixto WHERE id_mixto = ?')->execute([$idMixto]);
            } else {
                $insMx = $this->erpPdo->prepare('INSERT INTO embalaje_mixto (id_embalaje, cantidad_cajas, total_bolsa) VALUES (?, ?, ?)');
                $insMx->execute([$embalajeId, $sumCajas, $sumBolsas]);
                $idMixto = (int)$this->erpPdo->lastInsertId();
            }

            if (!empty($mixedItems)) {
                $insDet = $this->erpPdo->prepare('INSERT INTO detalle_mixto (id_mixto, bolsas_por_caja, bolsas_caja_final, bolsas_sobrante, req_number, id_item, total_cajas, total_bolsas) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                foreach ($mixedItems as $m) {
                    $insDet->execute([
                        $idMixto,
                        (int)($m['bolsas_por_caja'] ?? 0),
                        (int)($m['bolsas_caja_final'] ?? 0),
                        (int)($m['bolsas_sobrante'] ?? 0),
                        trim((string)($m['req_number'] ?? '')),
                        (int)($m['id_item'] ?? 0),
                        (int)($m['total_cajas'] ?? 0),
                        (int)($m['total_bolsas'] ?? 0)
                    ]);

                    $origWh = (int)($m['req_id_bodega'] ?? 0);
                    $destWh = (int)$detail['req_id_bodega'];
                    if ($origWh === UNIBAG_MIXED_ORIGIN_AT_DESTINATION
                        || ($mainStockIsAutomatic && trim((string)($m['req_number'] ?? '')) === $mainReqNumber)) {
                        $origWh = $destWh; // No hay nada que mover; si ya se había traspasado, se revierte.
                    }
                    $itemId = (int)($m['id_item'] ?? 0);
                    $totTraspaso = (int)($m['total_bolsas'] ?? 0);
                    if ($origWh > 0 && $destWh > 0 && $itemId > 0) {
                        try {
                            // Si el origen o la cantidad quedaron en 0/igual al destino, se revierte lo registrado antes.
                            $this->transferMixedStock($embalajeId, $itemId, $origWh, $destWh, $origWh !== $destWh ? $totTraspaso : 0, trim((string)($m['req_number'] ?? '')));
                        } catch (Throwable $e) {
                            error_log('Traspaso de bodega (cierre mixto): ' . $e->getMessage());
                            $this->lastSupplyConsumptionWarning = trim($this->lastSupplyConsumptionWarning
                                . ' NO se pudo registrar el traspaso de bodega del CC ' . trim((string)($m['req_number'] ?? '')) . ': ' . $e->getMessage());
                        }
                    }
                }
            }
        }

        if ($existing !== []) {
            try {
                $keep = $detail['req_codigo_cierre'] === '50'
                    ? array_map(static fn(array $m): string => trim((string)($m['req_number'] ?? '')), $mixedItems) : [];
                $this->revertRemovedMixedTransfers($embalajeId, $keep, (int)$detail['req_id_bodega']);
            } catch (Throwable $e) {
                error_log('Reversa de traspaso mixto, embalaje ' . $embalajeId . ': ' . $e->getMessage());
                $this->lastSupplyConsumptionWarning = trim($this->lastSupplyConsumptionWarning
                    . ' NO se pudo revertir el traspaso de un CC quitado del pallet: ' . $e->getMessage());
            }
        }

        // Consumo automático de insumos de embalaje en Bodega 3000 (pallets, cajas y cinta de papel)
        try {
            // Se envían también los CC del pallet mixto (antes se quitaban y el consumo de cajas
            // y pallets de un cierre mixto se calculaba solo con la fila principal).
            $supplyResult = $this->consumePackagingSupplies($workerOtId, $eventId, $detail + ['mixed_items' => $mixedItems], $supervisor);
            if (($supplyResult['ok'] ?? true) !== true) {
                $this->lastSupplyConsumptionWarning = trim($this->lastSupplyConsumptionWarning . ' '
                    . (string)($supplyResult['error'] ?? 'No se pudo descontar el stock de embalaje.'));
            }
        } catch (Throwable $e) {
            error_log('Error en consumePackagingSupplies: ' . $e->getMessage());
            $this->lastSupplyConsumptionWarning = trim($this->lastSupplyConsumptionWarning
                . ' No se pudo descontar el stock de embalaje: ' . $e->getMessage());
        }

        // El correo a facturación se envía DESPUÉS de confirmar la transacción (ver sendPendingPackagingBillingMail).
        $this->pendingBillingMail = [$eventId, $workerOtId, $embalajeId, $requestId, $detail, $mixedItems, $totalUnitsForMail];

        return $embalajeId;
    }

    /**
     * Termina un embalaje hecho desde una selladora: lo pasa a la máquina Embalaje y actualiza el stock.
     * El embalaje ya quedó guardado, así que un error aquí nunca interrumpe el cierre: queda como aviso.
     *
     * @param bool $closingNow true solo cuando el embalaje se cierra en esta acción. Sin un cierre nuevo, el stock
     *                         se toca únicamente si este embalaje ya lo había cargado, porque los anteriores los ajustó bodega.
     */
    public function finalizeCysPackagingEvent(int $eventId, int $workerOtId, bool $closingNow): void
    {
        try {
            $this->linkCysPackagingEvent($eventId);
        } catch (Throwable $e) {
            error_log('Embalaje CyS, evento ' . $eventId . ' de OT ' . $workerOtId . ': ' . $e->getMessage());
            $this->lastSupplyConsumptionWarning = trim($this->lastSupplyConsumptionWarning
                . ' El embalaje se guardó, pero NO quedó en la producción de Embalaje. Avisa a sistemas (evento ' . $eventId . ').');
        }
        try {
            $stockLoaded = $this->syncCysPackagingStock($eventId, $closingNow);
        } catch (Throwable $e) {
            $stockLoaded = false;
            error_log('Stock de embalaje CyS, evento ' . $eventId . ': ' . $e->getMessage());
            $this->lastSupplyConsumptionWarning = trim($this->lastSupplyConsumptionWarning
                . ' El embalaje se guardó, pero NO se pudo cargar el stock a bodega: avisa a bodega para ajustarlo.');
        }
        // Cada caja recibe su código con CC, SKU, bodega y unidades, solo si su stock quedó en la bodega por este sistema.
        $this->lastCreatedBoxIds = [];
        if ($stockLoaded && $this->trzPdo !== null) {
            try {
                $detail = $this->erpPdo->prepare('SELECT req_id_bodega FROM prod_worker_embalajes WHERE req_v2refid = ? ORDER BY id DESC LIMIT 1');
                $detail->execute([$eventId]);
                $warehouse = (int)$detail->fetchColumn();
                $operator = (string)($_SESSION['operator_name'] ?? $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Embalaje');
                $sync = (new BagBoxService($this->trzPdo, $this->erpPdo))->syncPackagingBoxes($eventId, $warehouse, $operator);
                $this->lastCreatedBoxIds = $sync['created'];
                if ($sync['warning'] !== '') {
                    $this->lastSupplyConsumptionWarning = trim($this->lastSupplyConsumptionWarning . ' ' . $sync['warning']);
                }
            } catch (Throwable $e) {
                error_log('Cajas con código del embalaje ' . $eventId . ': ' . $e->getMessage());
                $this->lastSupplyConsumptionWarning = trim($this->lastSupplyConsumptionWarning
                    . ' El embalaje se guardó, pero NO se crearon los códigos de sus cajas: ' . $e->getMessage());
            }
        }
    }

    /**
     * Datos del evento de embalaje y de la OT donde está hoy. Solo eventos terminados de tipo embalaje.
     */
    private function closedPackagingEventRow(int $eventId): ?array
    {
        $stmt = $this->erpPdo->prepare("SELECT e.id, e.evt_crtdat, e.evt_enddat, e.evt_amount, e.evt_prod_worker_otid,
                e.overrideembalaje_selladora_evtrefid AS linked_ot, ot.wok_ag_id, a.ag_equipotype_id, a.ag_prdid, a.ag_reqid,
                a.ag_plantaid, i.win_wrkid, i.win_plantaid
            FROM prod_worker_ot_events e
            JOIN prod_worker_ot ot ON ot.id = e.evt_prod_worker_otid
            JOIN prod_agenda a ON a.id = ot.wok_ag_id
            LEFT JOIN prod_worker_init i ON i.id = ot.wok_init_id
            WHERE e.id = ? AND e.evt_type = 'prod' AND e.overrideembalaje_act = 1 AND e.evt_status > 0 AND e.evt_enddat > 0");
        $stmt->execute([$eventId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Pasa un embalaje hecho desde una selladora a la máquina Embalaje, como hacía el sistema antiguo: registro de
     * máquina, agenda oculta y OT cerrada, con el enlace a la OT de la selladora. Los informes leen el tipo de máquina
     * de la agenda, así que esto saca las unidades de Corte y Sellado y las deja en Embalaje, en su fecha real.
     *
     * No toca stock. Las tablas del ERP son MyISAM y no permiten deshacer: el evento se mueve al final, y un fallo
     * previo solo deja registros sin eventos, que los informes no leen.
     *
     * @return array{event_id:int, moved:bool, source_ot:int, packaging_ot:int}|null null si el evento no corresponde.
     */
    public function linkCysPackagingEvent(int $eventId): ?array
    {
        $event = $this->closedPackagingEventRow($eventId);
        if ($event === null || (float)$event['evt_amount'] <= 0) {
            return null;
        }
        $amount = (float)$event['evt_amount'];
        $currentOt = (int)$event['evt_prod_worker_otid'];
        $moveDetail = $this->erpPdo->prepare('UPDATE prod_worker_embalajes SET evt_prod_worker_otid = ? WHERE req_v2refid = ?');

        if ((int)$event['linked_ot'] > 0) {
            // Ya movido: una corrección solo actualiza la cantidad y devuelve el detalle a la OT de Embalaje.
            $this->erpPdo->prepare('UPDATE prod_agenda SET ag_amount = ? WHERE id = ?')->execute([$amount, (int)$event['wok_ag_id']]);
            $moveDetail->execute([$currentOt, $eventId]);
            return ['event_id' => $eventId, 'moved' => false, 'source_ot' => (int)$event['linked_ot'], 'packaging_ot' => $currentOt];
        }
        if ((int)$event['ag_equipotype_id'] !== UNIBAG_CYS_EQUIPO_TYPE_ID) {
            return null;
        }

        $closedAt = (int)$event['evt_enddat'];
        $this->erpPdo->prepare('INSERT INTO prod_worker_init
                (win_crtdat, win_enddat, win_wrkid, win_status, win_plantaid, win_equipoid, win_ass_id, win_day, win_month, win_year)
                VALUES (?, ?, ?, 2, ?, ?, 0, ?, ?, ?)')
            ->execute([$closedAt, $closedAt, (int)$event['win_wrkid'], (int)$event['win_plantaid'], UNIBAG_PACKAGING_EQUIPO_ID,
                (int)date('j', $closedAt), (int)date('n', $closedAt), (int)date('Y', $closedAt)]);
        $initId = (int)$this->erpPdo->lastInsertId();
        $this->erpPdo->prepare('INSERT INTO prod_agenda
                (ag_date, ag_date_stamp, ag_equipo_id, ag_equipotype_id, ag_amount, ag_prdid, ag_reqid, ag_plantaid, ag_crtdat, ag_crtusr, ag_status, ag_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, 0)')
            ->execute([date('d.m.Y', $closedAt), $closedAt, UNIBAG_PACKAGING_EQUIPO_ID, UNIBAG_PACKAGING_EQUIPO_TYPE_ID, $amount,
                (int)$event['ag_prdid'], (int)$event['ag_reqid'], (int)$event['ag_plantaid'], $closedAt]);
        $agendaId = (int)$this->erpPdo->lastInsertId();
        $this->erpPdo->prepare('INSERT INTO prod_worker_ot (wok_ag_id, wok_init_id, wok_crtdat, wok_enddat, wok_status) VALUES (?, ?, ?, ?, 2)')
            ->execute([$agendaId, $initId, (int)$event['evt_crtdat'], $closedAt]);
        $packagingOt = (int)$this->erpPdo->lastInsertId();

        $moveDetail->execute([$packagingOt, $eventId]);
        $this->erpPdo->prepare('UPDATE prod_worker_ot_events SET evt_prod_worker_otid = ?, overrideembalaje_selladora_evtrefid = ?
                WHERE id = ? AND overrideembalaje_selladora_evtrefid = 0')
            ->execute([$packagingOt, $currentOt, $eventId]);
        return ['event_id' => $eventId, 'moved' => true, 'source_ot' => $currentOt, 'packaging_ot' => $packagingOt];
    }

    /**
     * Deja en bodega lo embalado desde una selladora. Cada documento lleva la marca [TRZ-EMB-STOCK:evento:bodega:cantidad],
     * y solo se registra la diferencia con lo ya cargado: repetir no duplica y una corrección ajusta lo justo.
     */
    /** @return bool true si el stock de este embalaje está cargado por este sistema (con su marca). */
    private function syncCysPackagingStock(int $eventId, bool $closingNow): bool
    {
        $event = $this->closedPackagingEventRow($eventId);
        if ($event === null) {
            return false;
        }
        // Máquina Embalaje propia: su stock lo carga hoy el sistema antiguo al terminar la OT; cargarlo aquí lo duplicaría.
        if ((int)$event['linked_ot'] <= 0 && (int)$event['ag_equipotype_id'] !== UNIBAG_CYS_EQUIPO_TYPE_ID) {
            return false;
        }
        $stmt = $this->erpPdo->prepare('SELECT * FROM prod_worker_embalajes WHERE req_v2refid = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$eventId]);
        $detail = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$detail) {
            return false;
        }

        $loaded = $this->loadedPackagingStock($eventId);
        // Sin cierre nuevo ni carga previa: es un embalaje que ya ajustó bodega.
        if (!$closingNow && $loaded === []) {
            return false;
        }

        // En un cierre mixto entra lo del CC principal; los demás CC llegan por su traspaso de bodega.
        $units = (int)$detail['req_embalaje_cajas_completas_amt'] * (int)$detail['req_embalaje_bolsas_por_caja_amt']
            + (int)$detail['req_embalaje_caja_final'] + (int)$detail['req_embalaje_bolsas_sobrantes_amt'] + (int)$detail['req_embalaje_showroom'];
        $warehouse = (int)$detail['req_id_bodega'];
        if ($warehouse <= 0) {
            throw new RuntimeException('El embalaje no tiene bodega de destino.');
        }
        $targets = array_fill_keys(array_keys($loaded), 0);
        $targets[$warehouse] = $units;
        foreach ($targets as $st => $target) {
            $delta = $target - (int)($loaded[$st] ?? 0);
            if ($delta !== 0) {
                $this->postPackagingStockChange($eventId, (int)$event['ag_reqid'], (int)$st, $delta);
            }
        }
        return true;
    }

    /** Unidades ya cargadas por este embalaje, por bodega, según las marcas de sus documentos. */
    private function loadedPackagingStock(int $eventId): array
    {
        $docs = $this->erpPdo->prepare('SELECT stk_annotation FROM stockchanges WHERE stk_annotation LIKE ?');
        $docs->execute(['%[TRZ-EMB-STOCK:' . $eventId . ':%']);
        $loaded = [];
        foreach ($docs->fetchAll(PDO::FETCH_COLUMN) as $annotation) {
            if (preg_match('/\[TRZ-EMB-STOCK:' . $eventId . ':(\d+):(-?\d+)\]/', (string)$annotation, $m) === 1) {
                $loaded[(int)$m[1]] = ($loaded[(int)$m[1]] ?? 0) + (int)$m[2];
            }
        }
        return $loaded;
    }

    /**
     * Devuelve el stock que movió este embalaje, antes de eliminarlo o anularlo. Revierte solo sus ingresos y traspasos
     * con marca, con documento y kardex; lo cargado a mano o por el sistema antiguo no se toca. Repetir no revierte dos veces.
     */
    public function reversePackagingEventStock(int $eventId): void
    {
        // Sus cajas con código se anulan primero; si alguna ya se usó o despachó, no se puede eliminar el embalaje.
        if ($this->trzPdo !== null) {
            (new BagBoxService($this->trzPdo, $this->erpPdo))->voidPackagingBoxes($eventId);
        }
        $stmt = $this->erpPdo->prepare('SELECT id, evt_reqid, req_id_bodega FROM prod_worker_embalajes WHERE req_v2refid = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$eventId]);
        $detail = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$detail) {
            return;
        }
        foreach ($this->loadedPackagingStock($eventId) as $warehouse => $units) {
            if ($units !== 0) {
                $this->postPackagingStockChange($eventId, (int)$detail['evt_reqid'], (int)$warehouse, -$units);
            }
        }
        $this->revertRemovedMixedTransfers((int)$detail['id'], [], (int)$detail['req_id_bodega']);
    }

    /** Documento de ajuste de stock, igual al del sistema antiguo: entra (o sale) del stock del pedido en la bodega. */
    private function postPackagingStockChange(int $eventId, int $orderId, int $warehouse, int $delta): void
    {
        $stmt = $this->erpPdo->prepare('SELECT o.req_number, o.req_company_id, o.req_shop_id, oi.item_id, i.item_title
            FROM orders o JOIN orders_items oi ON oi.req_id = o.id LEFT JOIN item i ON i.id = oi.item_id WHERE o.id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order || (int)$order['item_id'] <= 0) {
            throw new RuntimeException('No se encontró el artículo del pedido ' . $orderId . '.');
        }
        $companyId = (int)$order['req_company_id'];
        $shopId = (int)$order['req_shop_id'];
        $itemId = (int)$order['item_id'];
        $stmt = $this->erpPdo->prepare('SELECT company_numformat_stockchange, company_numcounter_stockchange FROM company_data WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            throw new RuntimeException('Contador de ajustes no disponible para la empresa ' . $companyId . '.');
        }
        $counter = (int)$company['company_numcounter_stockchange'] + 1;
        // 7 dígitos, como los documentos de embalaje del sistema antiguo (IA0033662).
        $number = (trim((string)$company['company_numformat_stockchange']) ?: 'IA') . sprintf('%07d', $counter);
        $this->erpPdo->prepare('UPDATE company_data SET company_numcounter_stockchange = ? WHERE id = ?')->execute([$counter, $companyId]);

        $now = time();
        $userId = (int)($_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? 0);
        $negative = $delta < 0;
        $qty = abs($delta);
        $issue = $negative ? 19 : 20;

        // La marca se escribe primero: sin transacciones, un reintento nunca volverá a sumar el stock.
        $this->erpPdo->prepare('INSERT INTO stockchanges
                (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate, stk_negative, stk_status, stk_crtdat, stk_crtusr,
                 stk_upddat, stk_updusr, stk_fixedsthid, sth_fromprodotid, sth_fromrebo_agid, sth_order_id, sth_order_num,
                 stk_cinumber, stk_vintnumber, sth_fromrebo_agid_grphash)
                VALUES (?, ?, ?, ?, ?, ?, ?, 2, ?, ?, ?, ?, ?, 0, 0, ?, ?, \'\', \'\', \'\')')
            ->execute([$number, 'Registrado por sistema de producción [TRZ-EMB-STOCK:' . $eventId . ':' . $warehouse . ':' . $delta . ']',
                $issue, $companyId, $shopId, $now, $negative ? 1 : 0, $now, $userId, $now, $userId, $warehouse, $orderId, (string)$order['req_number']]);
        $documentId = (int)$this->erpPdo->lastInsertId();
        $this->erpPdo->prepare("INSERT INTO stockchanges_items (stk_id, item_id, item_pos, item_amount, item_type, item_st_id) VALUES (?, ?, 0, ?, 'item', ?)")
            ->execute([$documentId, $itemId, $qty, $warehouse]);

        $stmt = $this->erpPdo->prepare('SELECT iss_inventory FROM item_shops_storehouses WHERE item_id = ? AND shop_id = ? AND st_id = ? AND iss_order_id = ?');
        $stmt->execute([$itemId, $shopId, $warehouse, $orderId]);
        $current = $stmt->fetchColumn();
        if ($current === false) {
            $this->erpPdo->prepare('INSERT INTO item_shops_storehouses (item_id, shop_id, st_id, iss_order_amount, iss_inventory_min, iss_inventory, iss_inventory_reserved, iss_order_id)
                VALUES (?, ?, ?, 0, 0, 0, 0, ?)')->execute([$itemId, $shopId, $warehouse, $orderId]);
            $current = 0;
        }
        $this->erpPdo->prepare('UPDATE item_shops_storehouses SET iss_inventory = iss_inventory + ? WHERE item_id = ? AND shop_id = ? AND st_id = ? AND iss_order_id = ?')
            ->execute([$delta, $itemId, $shopId, $warehouse, $orderId]);
        $this->erpPdo->prepare("INSERT INTO tran_data
                (tran_id, tran_pos, tran_type, tran_number, tran_currstock, tran_amount, tran_company_id, tran_shop_id, tran_st_id, tran_issueid,
                 item_id, item_title, item_type, tran_day, tran_month, tran_year, tran_crtusr, tran_crtdat, tran_order_id)
                VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'item', ?, ?, ?, ?, ?, ?)")
            ->execute([$documentId, $negative ? 'stockchangedown' : 'stockchangeup', $number, (float)$current, $qty, $companyId, $shopId, $warehouse, $issue,
                $itemId, (string)$order['item_title'], (int)date('j', $now), (int)date('n', $now), (int)date('Y', $now), $userId, $now, $orderId]);
    }

    /**
     * Calcula los pallets físicos consumidos según la regla:
     * Si las cajas sobrantes son <= 5, se colocan en el mismo pallet (sin consumir otro).
     * Si las cajas sobrantes son > 5, se consume un pallet adicional.
     */
    public static function calculatePalletsConsumed(int $totalBoxes, int $cajasPorPallet): array
    {
        $cajasPorPallet = max(1, $cajasPorPallet);
        $fullPallets = (int)floor($totalBoxes / $cajasPorPallet);
        $leftoverBoxes = $totalBoxes % $cajasPorPallet;

        if ($fullPallets === 0) {
            $pallets = ($totalBoxes > 0) ? 1 : 0;
            $absorbed = false;
        } else {
            if ($leftoverBoxes > 0 && $leftoverBoxes > 5) {
                $pallets = $fullPallets + 1;
                $absorbed = false;
            } else {
                $pallets = $fullPallets;
                $absorbed = ($leftoverBoxes > 0);
            }
        }

        return [
            'pallets' => $pallets,
            'full_pallets' => $fullPallets,
            'leftover_boxes' => $leftoverBoxes,
            'absorbed_in_same_pallet' => $absorbed,
        ];
    }

    /**
     * Extrae el ancho de la caja en cm a partir del string de medidas (ej: '50X40X40', '50X45X15').
     */
    public static function extractBoxWidthCm(string $medidasCaja): float
    {
        $clean = strtoupper(trim($medidasCaja));
        preg_match_all('/\d+/', $clean, $matches);
        $nums = array_map('intval', $matches[0] ?? []);
        if (empty($nums)) {
            return 40.0;
        }
        if (count($nums) >= 2) {
            $w = (float)$nums[1];
        } else {
            $w = (float)$nums[0];
        }
        if ($w >= 100) {
            $w = $w / 10.0;
        }
        return ($w > 0) ? $w : 40.0;
    }

    /**
     * Calcula la conversión de consumo de cinta de embalaje por pasada y total de lote.
     * 1 pasada = ancho de caja + 5cm por lado (+10cm total).
     * Conversión a rollo: largo_pasada_m / tape_roll_length_m (ej: 40m o 50m).
     */
    public static function calculateTapeConsumption(float $boxWidthCm, int $totalBoxes, float $tapeRollLengthM = 40.0): array
    {
        if ($boxWidthCm <= 0) {
            $boxWidthCm = 40.0;
        }
        if ($tapeRollLengthM <= 0) {
            $tapeRollLengthM = 40.0;
        }
        $passLengthCm = $boxWidthCm + 10.0;
        $passLengthM = round($passLengthCm / 100.0, 4);
        $consumptionPerPassRoll = round($passLengthM / $tapeRollLengthM, 5);
        $passesPerRoll = $passLengthM > 0 ? (int)floor($tapeRollLengthM / $passLengthM) : 0;
        $totalTapeMeters = round($totalBoxes * $passLengthM, 2);
        $totalRollsConsumed = round($totalTapeMeters / $tapeRollLengthM, 4);

        return [
            'box_width_cm' => $boxWidthCm,
            'pass_length_cm' => $passLengthCm,
            'pass_length_m' => $passLengthM,
            'tape_roll_length_m' => $tapeRollLengthM,
            'consumption_per_pass_roll' => $consumptionPerPassRoll,
            'passes_per_roll' => $passesPerRoll,
            'total_boxes' => $totalBoxes,
            'total_tape_meters' => $totalTapeMeters,
            'total_rolls_consumed' => $totalRollsConsumed,
        ];
    }

    /**
     * Busca en el catálogo de items de ERP la caja activa correspondiente
     * por medidas (Largo x Ancho x Alto) y tipo de impresión (con/sin logo).
     */
    public function matchPackagingBoxItem(string $medidasCaja): ?array
    {
        $medidasClean = strtoupper(trim($medidasCaja));
        preg_match_all('/\d+/', $medidasClean, $matches);
        $rawNumbers = array_map('intval', $matches[0] ?? []);

        $inputDims = [];
        foreach ($rawNumbers as $n) {
            if ($n >= 10 && $n <= 2000) {
                $inputDims[] = $n;
            }
        }
        $inputDims = array_slice($inputDims, 0, 3);
        $inputMm = array_map(fn($d) => ($d < 100 ? $d * 10 : $d), $inputDims);
        sort($inputMm);

        $isSinLogo = (str_contains($medidasClean, 'SIN LOGO') || str_contains($medidasClean, 'SIN IMPRESION') || str_contains($medidasClean, 'SIN IMPRESIÓN'));

        try {
            $stmt = $this->erpPdo->query("
                SELECT id, item_number_prod, item_title
                FROM item
                WHERE (item_title LIKE '%caja%' OR item_number_prod LIKE '%caja%')
                  AND item_title NOT LIKE 'NO USAR%'
                  AND item_title NOT LIKE '%REPUESTO%'
                  AND item_title NOT LIKE '%EPP%'
                  AND item_title NOT LIKE '%MASCARILLA%'
                  AND item_status = 1
            ");
            $boxes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $boxes = [];
        }

        $bestMatch = null;
        $bestScore = -1000;

        foreach ($boxes as $box) {
            $title = strtoupper((string)($box['item_title'] ?? ''));
            preg_match_all('/(\d+)\s*[X\*x]\s*(\d+)(?:\s*[X\*x]\s*(\d+))?/', $title, $dimMatches);
            $boxMm = [];
            if (!empty($dimMatches[0])) {
                $d1 = (int)$dimMatches[1][0];
                $d2 = (int)$dimMatches[2][0];
                $d3 = !empty($dimMatches[3][0]) ? (int)$dimMatches[3][0] : 0;
                $boxMm = array_values(array_filter([$d1, $d2, $d3]));
                $boxMm = array_map(fn($d) => ($d < 100 ? $d * 10 : $d), $boxMm);
                sort($boxMm);
            }

            if (empty($boxMm)) continue;

            $score = 0;
            if ($inputMm === $boxMm) {
                $score += 200;
            } else {
                $matchedCount = 0;
                $tempBoxMm = $boxMm;
                foreach ($inputMm as $imm) {
                    $idx = array_search($imm, $tempBoxMm, true);
                    if ($idx !== false) {
                        $matchedCount++;
                        unset($tempBoxMm[$idx]);
                    }
                }
                if ($matchedCount === 0) continue;
                $score += ($matchedCount * 30) - (count($tempBoxMm) * 20);
            }

            $titleSinLogo = (str_contains($title, 'SIN IMPRESION') || str_contains($title, 'SIN IMPRESIÓN'));
            if ($isSinLogo && $titleSinLogo) {
                $score += 50;
            } elseif (!$isSinLogo && !$titleSinLogo && (str_contains($title, 'IMPRESION') || str_contains($title, 'IMPRESA') || str_contains($title, 'UNIBAG'))) {
                $score += 50;
            } elseif ($isSinLogo && !$titleSinLogo) {
                $score -= 30;
            }

            if (str_contains($title, '14C')) $score += 5;
            if (str_contains($title, 'GENERICA')) $score += 5;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $box;
            }
        }

        if (!$bestMatch) {
            try {
                $stmtDef = $this->erpPdo->query("SELECT id, item_number_prod, item_title FROM item WHERE item_number_prod = 'INS0012' LIMIT 1");
                $bestMatch = $stmtDef->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable) {}
        }

        return $bestMatch;
    }

    /**
     * Ajusta el stock en item_shops_storehouses sumando o restando (deltaAmount).
     */
    public function adjustWarehouseStock(int $itemId, int $stId, float $deltaAmount, bool $throwOnError = false): void
    {
        if ($itemId <= 0 || $stId <= 0 || abs($deltaAmount) < 0.000001) {
            return;
        }
        try {
            $stCheck = $this->erpPdo->prepare("SELECT COUNT(*) FROM item_shops_storehouses WHERE item_id = :item_id AND st_id = :st_id");
            $stCheck->execute([':item_id' => $itemId, ':st_id' => $stId]);
            if ((int)$stCheck->fetchColumn() === 0) {
                $ins = $this->erpPdo->prepare("INSERT INTO item_shops_storehouses (item_id, shop_id, st_id, iss_order_amount, iss_inventory_min, iss_inventory, iss_inventory_reserved, iss_order_id) VALUES (:item_id, 30010, :st_id, 0, 0, :inv, 0, 0)");
                $ins->execute([
                    ':item_id' => $itemId,
                    ':st_id' => $stId,
                    ':inv' => max(0.0, $deltaAmount),
                ]);
            } else {
                $upd = $this->erpPdo->prepare("UPDATE item_shops_storehouses SET iss_inventory = GREATEST(0, iss_inventory + :delta) WHERE item_id = :item_id AND st_id = :st_id");
                $upd->execute([
                    ':delta' => $deltaAmount,
                    ':item_id' => $itemId,
                    ':st_id' => $stId,
                ]);
            }
        } catch (Throwable $e) {
            error_log("Error al ajustar stock en bodega {$stId} para item {$itemId}: " . $e->getMessage());
            // En modo estricto el error se propaga para que el consumo completo se revierta
            // (antes se ignoraba y quedaban descuentos a medias sin avisar).
            if ($throwOnError) {
                throw new RuntimeException("No se pudo ajustar el stock del ítem {$itemId} en la bodega {$stId}: " . $e->getMessage(), 0, $e);
            }
        }
    }

    /**
     * Descuenta un insumo de embalaje de la Bodega 3000 con documento y kardex. Devuelve la marca que se guarda en el
     * comentario del evento de materiales, para poder revertir exactamente ese documento.
     */
    private function postPackagingSupply(int $eventId, int $itemId, float $qty, string $label): string
    {
        $key = 'embsup.' . $eventId . '.' . $itemId . '.' . substr(bin2hex(random_bytes(4)), 0, 8);
        $posted = (new StockLedgerService($this->erpPdo))->post([
            'key' => $key, 'item_id' => $itemId, 'st_id' => 16, 'delta' => -$qty, 'allow_negative' => true,
            'text' => 'Consumo automático de embalaje: ' . $label . ' (evento ' . $eventId . ').',
        ]);
        if ($posted['stock_after'] < -0.0005) {
            $this->lastSupplyConsumptionWarning = trim($this->lastSupplyConsumptionWarning . ' ' . $label
                . ' quedó con stock negativo en Bodega 3000 (' . StockLedgerService::fmt($posted['stock_after']) . '): avisa a bodega.');
        }
        return '[TRZ-LED-KEY:' . $key . ']';
    }

    /**
     * Devuelve a la Bodega 3000 un insumo de embalaje ya descontado (al corregir o borrar el embalaje). Lo descontado con
     * kardex se revierte con su documento; lo de antes del kardex (descuento directo) se devuelve igual que se descontó.
     */
    public function revertPackagingSupplyEvent(array $supplyEvent, int $fallbackBoxItemId = 0): void
    {
        $comm = (string)($supplyEvent['evt_comments'] ?? '');
        if (preg_match('/\[TRZ-LED-KEY:([A-Za-z0-9_.:\-]+)\]/', $comm, $mKey) === 1) {
            (new StockLedgerService($this->erpPdo))->reverse($mKey[1], 'embalaje corregido o eliminado');
            return;
        }
        $prevAmt = (float)($supplyEvent['evt_amount'] ?? 0);
        if ($prevAmt <= 0) {
            return;
        }
        $itemId = preg_match('/\[ITEM_ID:(\d+)\]/', $comm, $mId) === 1 ? (int)$mId[1] : 0;
        if ($itemId <= 0 && str_contains($comm, 'pallet')) {
            $itemId = 118;
        } elseif ($itemId <= 0 && str_contains($comm, 'cajas')) {
            $itemId = $fallbackBoxItemId;
        }
        if ($itemId > 0) {
            $this->adjustWarehouseStock($itemId, 16, +$prevAmt, true);
        }
    }

    /**
     * Realiza el consumo automatizado de pallets, cajas y cinta de papel en Bodega 3000
     * al finalizar/cerrar el embalaje de producción o embalaje de corte y sellado.
     */
    public function consumePackagingSupplies(int $workerOtId, int $eventId, array $detail, string $supervisor = ''): array
    {
        // Atomicidad: el descuento de pallets, cajas y cinta es TODO o NADA. Si algo falla se
        // deshace lo ya descontado (SAVEPOINT dentro de la transacción del embalaje) y se
        // devuelve un error legible, en vez de dejar el stock descontado a medias.
        $savepoint = 'emb_supply_' . $eventId;
        $ownTransaction = !$this->erpPdo->inTransaction();
        try {
            if ($ownTransaction) {
                $this->erpPdo->beginTransaction();
            } else {
                $this->erpPdo->exec('SAVEPOINT ' . $savepoint);
            }
            $result = $this->consumePackagingSuppliesInner($workerOtId, $eventId, $detail, $supervisor);
            if ($ownTransaction) {
                $this->erpPdo->commit();
            } else {
                $this->erpPdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            return $result;
        } catch (Throwable $e) {
            try {
                if ($ownTransaction) {
                    if ($this->erpPdo->inTransaction()) {
                        $this->erpPdo->rollBack();
                    }
                } else {
                    $this->erpPdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                }
            } catch (Throwable) {
            }
            error_log('Consumo de insumos de embalaje revertido (evento ' . $eventId . '): ' . $e->getMessage());
            return [
                'ok' => false,
                'error' => 'No se pudo descontar el stock de pallets/cajas/cinta de Bodega 3000 (' . $e->getMessage() . '). El embalaje quedó guardado; avisa a bodega para ajustar el stock.',
            ];
        }
    }

    /** Lógica de cálculo y registro del consumo; la llama consumePackagingSupplies() dentro de una transacción. */
    private function consumePackagingSuppliesInner(int $workerOtId, int $eventId, array $detail, string $supervisor = ''): array
    {
        // 1. Total de cajas
        $isMixto = (($detail['req_codigo_cierre'] ?? '') === '50');
        $totalBoxes = 0;
        if ($isMixto && !empty($detail['mixed_items']) && is_array($detail['mixed_items'])) {
            foreach ($detail['mixed_items'] as $m) {
                $totalBoxes += (int)($m['total_cajas'] ?? 0) + ((int)($m['bolsas_caja_final'] ?? 0) > 0 ? 1 : 0);
            }
        }
        if ($totalBoxes <= 0) {
            $totalBoxes = (int)($detail['req_embalaje_cajas_completas_amt'] ?? 0) + ((int)($detail['req_embalaje_caja_final'] ?? 0) > 0 ? 1 : 0);
        }

        if ($totalBoxes <= 0) {
            return ['ok' => true, 'total_boxes' => 0, 'consumed' => []];
        }

        // 2. Cálculo de pallets según regla <= 5 cajas sobrantes
        $cajasPorPallet = max(1, (int)($detail['req_embalaje_cajas_por_pallet_amt'] ?? 18));
        $palletCalc = self::calculatePalletsConsumed($totalBoxes, $cajasPorPallet);
        $palletsConsumed = $palletCalc['pallets'];
        $leftoverBoxes = $palletCalc['leftover_boxes'];

        // 3. Selección de la caja en catálogo
        $medidasCaja = trim((string)($detail['req_embalaje_medidas_caja'] ?? ''));
        $boxItem = $this->matchPackagingBoxItem($medidasCaja);
        $boxItemId = (int)($boxItem['id'] ?? 93);
        $boxSku = (string)($boxItem['item_number_prod'] ?? 'INS0012');
        $boxTitle = (string)($boxItem['item_title'] ?? 'CAJA IMPRESION UNIBAG 500X400X400');

        // 4. Cinta de papel: ancho de la caja + 5 cm por lado (+10 cm)
        $boxWidthCm = self::extractBoxWidthCm($medidasCaja);
        $tapeType = $this->resolvePackagingTapeType($workerOtId, $medidasCaja);
        if ($tapeType === 'INS0038') {
            $tapeItemId = 153;
            $tapeSku = 'INS0038';
            $tapeTitle = 'CINTA EMBALAJE IMPRESA ECO PAPER 48mm X 50 MTS';
            $tapeRollLengthM = 50.0;
        } else {
            // Cinta de papel 40m estándar (INS0073)
            $tapeItemId = 575;
            $tapeSku = 'INS0073';
            $tapeTitle = 'CINTA DE EMBALAJE IMPRESA DE PAPEL CON PEGAMENTO DE CAUCHO NATURAL 48MMX40MT';
            $tapeRollLengthM = 40.0;
        }

        $tapeCalc = self::calculateTapeConsumption($boxWidthCm, $totalBoxes, $tapeRollLengthM);
        $passLengthM = $tapeCalc['pass_length_m'];
        $tapePerPassRoll = $tapeCalc['consumption_per_pass_roll'];
        $passesPerRoll = $tapeCalc['passes_per_roll'];
        $totalTapeMeters = $tapeCalc['total_tape_meters'];
        $tapeRollsConsumed = $tapeCalc['total_rolls_consumed'];

        // Pallet Americano estándar (INS0029)
        $palletItemId = 118;
        $palletSku = 'INS0029';
        $palletTitle = 'PALLETS TIPO AMERICANO 1 MT X 1,20 MT USADO';

        // Bodega 3000 (st_id = 16)
        $stId = 16;
        $refTag = "[EMB_SUPPLY_CONSUMPTION:evt_{$eventId}]";
        $now = time();

        // Si ya existían consumos de este evento, revertir stock para evitar duplicación
        try {
            $stPrev = $this->erpPdo->prepare("
                SELECT id, evt_amount, evt_comments
                FROM prod_worker_ot_events
                WHERE evt_prod_worker_otid = :ot AND evt_type = 'materiales' AND evt_comments LIKE :ref_tag
            ");
            $stPrev->execute([':ot' => $workerOtId, ':ref_tag' => '%' . $refTag . '%']);
            $prevEvents = $stPrev->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($prevEvents)) {
                foreach ($prevEvents as $pe) {
                    $this->revertPackagingSupplyEvent($pe, $boxItemId);
                }
                $this->erpPdo->prepare("
                    DELETE FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot AND evt_type = 'materiales' AND evt_comments LIKE :ref_tag
                ")->execute([':ot' => $workerOtId, ':ref_tag' => '%' . $refTag . '%']);
            }
        } catch (Throwable $e) {
            error_log('Error al revertir consumos previos de embalaje: ' . $e->getMessage());
            throw $e; // si no se pudo revertir el consumo previo, no se descuenta de nuevo (evita duplicar)
        }

        // Registrar Pallet
        if ($palletsConsumed > 0) {
            $palletLedgerTag = $this->postPackagingSupply($eventId, $palletItemId, (float)$palletsConsumed, $palletsConsumed . ' pallet(s) ' . $palletSku);
            $remDesc = ($leftoverBoxes <= 5 && $leftoverBoxes > 0)
                ? "Sobran {$leftoverBoxes} cajas (<=5) -> agrupadas en el mismo pallet"
                : ($leftoverBoxes > 5 ? "Sobran {$leftoverBoxes} cajas (>5) -> requiere 1 pallet adicional" : "Pallet(s) completos");
            $commPallet = "Consumo automático embalaje: {$palletsConsumed} pallet(s) [{$palletSku} - {$palletTitle}]. "
                . "Detalle: {$totalBoxes} cajas total, capacidad {$cajasPorPallet}/pallet ({$remDesc}). "
                . "[ITEM_ID:{$palletItemId}] {$refTag} {$palletLedgerTag}";

            $this->erpPdo->prepare("
                INSERT INTO prod_worker_ot_events (
                    evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments
                ) VALUES (
                    :ot, :amt, :now, :now, 2, 'materiales', :comm
                )
            ")->execute([
                ':ot' => $workerOtId,
                ':amt' => $palletsConsumed,
                ':now' => $now,
                ':comm' => $commPallet,
            ]);
        }

        // Registrar Cajas
        if ($totalBoxes > 0) {
            $boxLedgerTag = $this->postPackagingSupply($eventId, $boxItemId, (float)$totalBoxes, $totalBoxes . ' cajas ' . $boxSku);
            $commBox = "Consumo automático embalaje: {$totalBoxes} cajas [{$boxSku} - {$boxTitle}]. "
                . "Medida solicitada: '{$medidasCaja}'. "
                . "[ITEM_ID:{$boxItemId}] {$refTag} {$boxLedgerTag}";

            $this->erpPdo->prepare("
                INSERT INTO prod_worker_ot_events (
                    evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments
                ) VALUES (
                    :ot, :amt, :now, :now, 2, 'materiales', :comm
                )
            ")->execute([
                ':ot' => $workerOtId,
                ':amt' => $totalBoxes,
                ':now' => $now,
                ':comm' => $commBox,
            ]);
        }

        // Registrar Cinta de papel
        if ($totalTapeMeters > 0) {
            $tapeLedgerTag = $this->postPackagingSupply($eventId, $tapeItemId, (float)$tapeRollsConsumed, $tapeRollsConsumed . ' rollos de cinta ' . $tapeSku);
            $commTape = "Consumo automático embalaje: {$totalTapeMeters} m de cinta ({$tapeRollsConsumed} rollos de {$tapeRollLengthM}m) [{$tapeSku} - {$tapeTitle}]. "
                . "Conversión por pasada: ancho caja {$boxWidthCm}cm + 10cm (5cm por lado) = " . number_format($passLengthM, 2) . "m por pasada. "
                . "Consumo unitario: " . number_format($tapePerPassRoll, 5) . " rollos por pasada (rinde {$passesPerRoll} pasadas/cajas por rollo de {$tapeRollLengthM}m). "
                . "Total lote: {$totalBoxes} cajas (pasadas) x " . number_format($passLengthM, 2) . "m = {$totalTapeMeters}m ({$tapeRollsConsumed} rollos). "
                . "[ITEM_ID:{$tapeItemId}] {$refTag} {$tapeLedgerTag}";

            $this->erpPdo->prepare("
                INSERT INTO prod_worker_ot_events (
                    evt_prod_worker_otid, evt_amount, evt_amount_metros_lineales, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments
                ) VALUES (
                    :ot, :amt, :mlin, :now, :now, 2, 'materiales', :comm
                )
            ")->execute([
                ':ot' => $workerOtId,
                ':amt' => $tapeRollsConsumed,
                ':mlin' => (int)round($totalTapeMeters),
                ':now' => $now,
                ':comm' => $commTape,
            ]);
        }

        return [
            'ok' => true,
            'pallets' => $palletsConsumed,
            'boxes' => $totalBoxes,
            'box_item' => $boxItem,
            'tape_sku' => $tapeSku,
            'tape_item_id' => $tapeItemId,
            'tape_roll_length_m' => $tapeRollLengthM,
            'tape_pass_length_m' => $passLengthM,
            'tape_per_pass_roll' => $tapePerPassRoll,
            'tape_passes_per_roll' => $passesPerRoll,
            'tape_meters' => $totalTapeMeters,
            'tape_rolls' => $tapeRollsConsumed,
        ];
    }



    // =========================================================================
    // SECCIÓN: EVENTOS DE SIN PRODUCCIÓN / STANDBY DE MÁQUINA
    // =========================================================================


    /**
     * Guarda el registro de pesaje de bobinas entrantes y subproductos salientes para la Rebobinadora.
     * Replica con precisión la lógica legacy de editxid.php líneas 347-380 (reboexec = 'save').
     */
    public function saveReboInputWeights(int $reqId, int $workerOtId, array $postData): bool
    {
        if ($reqId <= 0 || $workerOtId <= 0) {
            return false;
        }
        try {
            $delStmt = $this->erpPdo->prepare(
                "DELETE FROM orders_classify_subproducts_inputweights
                 WHERE req_id = :req_id AND prod_worker_ot_id = :ot_id"
            );
            $delStmt->execute([':req_id' => $reqId, ':ot_id' => $workerOtId]);

            $insStmt = $this->erpPdo->prepare(
                "INSERT INTO orders_classify_subproducts_inputweights
                 (req_id, prod_worker_ot_id, subprod_rollo_idx, subprod_weight_enter,
                  subprod_salida_weight_0, subprod_salida_weight_1, subprod_salida_weight_2, subprod_salida_weight_3,
                  subprod_salida_weight_4, subprod_salida_weight_5, subprod_salida_weight_6, subprod_salida_weight_7,
                  subprod_salida_weight_8, subprod_salida_weight_9, subprod_salida_weight_10, subprod_salida_weight_11,
                  subprod_salida_weight_12, subprod_salida_weight_13, subprod_salida_weight_14, subprod_salida_weight_15,
                  subprod_salida_weight_16, subprod_salida_weight_17, subprod_salida_weight_18, subprod_salida_weight_19)
                 VALUES
                 (:req_id, :ot_id, :idx, :weight_enter,
                  :w0, :w1, :w2, :w3, :w4, :w5, :w6, :w7, :w8, :w9, :w10, :w11, :w12, :w13, :w14, :w15, :w16, :w17, :w18, :w19)"
            );

            foreach ($postData as $k => $val) {
                if (str_starts_with((string)$k, 'subprod_weight_enter_')) {
                    $idxStr = substr((string)$k, strlen('subprod_weight_enter_'));
                    $idx = (int)$idxStr;
                    $cleanedEnter = str_replace(',', '.', trim((string)$val));
                    $weightEnter = (float)$cleanedEnter;
                    if ($weightEnter > 0) {
                        $inps = [];
                        for ($z = 0; $z < 20; $z++) {
                            $rawZ = $postData["subprod_salida_weight_{$idx}_{$z}"] ?? 0;
                            $cleanedZ = str_replace(',', '.', trim((string)$rawZ));
                            $inps[$z] = (float)$cleanedZ;
                        }
                        $insStmt->execute([
                            ':req_id' => $reqId,
                            ':ot_id' => $workerOtId,
                            ':idx' => $idx,
                            ':weight_enter' => $weightEnter,
                            ':w0' => $inps[0], ':w1' => $inps[1], ':w2' => $inps[2], ':w3' => $inps[3],
                            ':w4' => $inps[4], ':w5' => $inps[5], ':w6' => $inps[6], ':w7' => $inps[7],
                            ':w8' => $inps[8], ':w9' => $inps[9], ':w10' => $inps[10], ':w11' => $inps[11],
                            ':w12' => $inps[12], ':w13' => $inps[13], ':w14' => $inps[14], ':w15' => $inps[15],
                            ':w16' => $inps[16], ':w17' => $inps[17], ':w18' => $inps[18], ':w19' => $inps[19],
                        ]);
                    }
                }
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Obtiene los materiales disponibles en bodega 3000 para consumir en la Rebobinadora.
     * Replica exactamente la consulta legacy de form.reboprod.php líneas 25-55.
     */
    public function getReboAvailableMaterials(array $agenda): array
    {
        $shopId = (int)($agenda['req_shop_id'] ?? 30010);
        $colorVals = array_filter([
            (int)($agenda['fab_mat_fabric_color'] ?? 0),
            (int)($agenda['fab_mat_manilla_color'] ?? 0),
        ]);
        $colorIn = !empty($colorVals) ? implode(',', $colorVals) : '';
        $fabType = trim((string)($agenda['fab_type'] ?? ''));

        $sql = "SELECT DISTINCT t1.*, t2.cat_id, t3.cat_title,
                       t4b.add_name AS colordesc,
                       t4.val_id AS colorid,
                       t6.add_name AS material,
                       t7.st_id,
                       SUM(t7.iss_inventory) AS iss_inventory
                FROM item t1
                INNER JOIN item_productcats t2            ON t1.id = t2.item_id
                INNER JOIN productcats t3                 ON t2.cat_id = t3.id
                INNER JOIN tran_comments_item_vals t4     ON t4.item_id = t1.id
                INNER JOIN tran_comments_vals t4b         ON t4.val_id = t4b.id
                INNER JOIN tran_comments_item_vals t5     ON t5.item_id = t1.id
                INNER JOIN tran_comments_vals t6          ON t5.val_id = t6.id
                INNER JOIN item_shops_storehouses t7      ON t1.id = t7.item_id
                INNER JOIN company_shops_storehouses t8   ON t7.st_id = t8.id
                WHERE
                    t1.item_status   > 0 AND
                    t1.item_released > 0 AND
                    t1.item_reg_kg   > 0 AND
                    t7.iss_inventory > 0 AND
                    t8.st_status     = 1 AND
                    t8.st_name LIKE '3000%' AND
                    t3.id IN (6, 27) AND
                    t4.com_id = 17 AND
                    t5.com_id = 18";

        if ($colorIn !== '') {
            $sql .= " AND t4.val_id IN ({$colorIn})";
        }
        if ($fabType !== '') {
            $sql .= " AND t6.add_name = :fab_type";
        }

        $sql .= " GROUP BY t1.id ORDER BY t3.cat_title, t1.item_title, t1.item_number_prod";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            if ($fabType !== '') {
                $stmt->execute([':fab_type' => $fabType]);
            } else {
                $stmt->execute();
            }
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($items)) {
                return $items;
            }

            // Fallback: Si no hay coincidencias estrictas, traer cualquier bobina de tela con stock positivo en Bodega 3000
            $sqlFb = "SELECT DISTINCT t1.*, t2.cat_id, t3.cat_title,
                             COALESCE((SELECT t4b.add_name FROM tran_comments_item_vals t4 INNER JOIN tran_comments_vals t4b ON t4.val_id = t4b.id WHERE t4.item_id = t1.id AND t4.com_id = 17 LIMIT 1), t1.item_title) AS colordesc,
                             COALESCE((SELECT t6.add_name FROM tran_comments_item_vals t5 INNER JOIN tran_comments_vals t6 ON t5.val_id = t6.id WHERE t5.item_id = t1.id AND t5.com_id = 18 LIMIT 1), 'TNT') AS material,
                             t7.st_id,
                             SUM(t7.iss_inventory) AS iss_inventory
                      FROM item t1
                      INNER JOIN item_productcats t2          ON t1.id = t2.item_id
                      INNER JOIN productcats t3               ON t2.cat_id = t3.id
                      INNER JOIN item_shops_storehouses t7    ON t1.id = t7.item_id
                      INNER JOIN company_shops_storehouses t8 ON t7.st_id = t8.id
                      WHERE
                          t1.item_status   > 0 AND
                          t1.item_released > 0 AND
                          t1.item_reg_kg   > 0 AND
                          t7.iss_inventory > 0 AND
                          t8.st_status     = 1 AND
                          t8.st_name LIKE '3000%' AND
                          t3.id IN (6, 27)
                      GROUP BY t1.id ORDER BY t3.cat_title, t1.item_title, t1.item_number_prod";
            $stmtFb = $this->erpPdo->query($sqlFb);
            return $stmtFb->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Determina si la orden de Rebobinado corresponde al Caso 2 (Solo Manillas) o al Caso 1 (Bolsas por cortar).
     */
    public function isReboSoloManillas(array $agenda, array $reboOrder = [], array $subproducts = []): bool
    {
        $opt = trim((string)($reboOrder['req_rebo_rolloscc_opt'] ?? ($agenda['req_rebo_rolloscc_opt'] ?? '')));
        if ($opt === '100cm_manillas') {
            return true;
        }

        if (!empty($subproducts)) {
            $hasNonManilla = false;
            $allEmpty = true;
            foreach ($subproducts as $sp) {
                $dest = strtolower(trim((string)($sp['subprod_destino'] ?? '')));
                $w = (float)($sp['subprod_ancho_cm'] ?? 0);
                if ($dest !== '') $allEmpty = false;
                if ($w > 15 || (!str_contains($dest, 'manilla') && $dest !== '')) {
                    $hasNonManilla = true;
                    break;
                }
            }
            if (!$hasNonManilla && !$allEmpty) {
                return true;
            }
        }

        $anchoManillas = (float)($agenda['req_infoaddprd_ancho_manillas'] ?? 0);
        if ($anchoManillas > 0 && empty($subproducts)) {
            return true;
        }

        return false;
    }

    /**
     * Escanea e ingresa una bobina para Rebobinado / Corte (Caso 1 o Caso 2).
     * Busca por código de producto (item_number_prod), código interno (item_number), ID en ERP o roll_code en TRZ.
     * Si la bobina no tiene stock en Bodega 3000, la transfiere/ingresa automáticamente desde Bodega 100/200.
     */
    public function scanAndPrepareReboRoll(string $code, array $agenda, int $userId): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'error' => 'Código de bobina vacío.'];
        }

        $shopId = (int)($agenda['req_shop_id'] ?? 20010);
        $stId3000 = 16; // 3000 INSUMOS EN PRODUCCION

        // 0. Una bobina con trazabilidad (roll_code o etiqueta de bodega) manda sobre el SKU del ERP:
        //    así se trae el peso registrado y no el peso estándar del ítem.
        $trzRoll = null;
        try {
            if ($this->trzPdo !== null) {
                $trzRoll = TraceableRollLookup::find($this->trzPdo, $code);
            }
        } catch (Throwable) {
            $trzRoll = null;
        }

        // 1. Buscar directamente en ERP item (solo si no es una bobina trazable)
        $item = false;
        if (!$trzRoll) {
            $stmt = $this->erpPdo->prepare("
                SELECT i.*,
                       COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id IN (16, 27)), 0) as stock_3000,
                       COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id IN (1, 10, 11)), 0) as stock_mp
                FROM item i
                WHERE i.item_number_prod = :c OR i.item_number = :c OR i.id = :c2
                LIMIT 1
            ");
            $stmt->execute([':c' => $code, ':c2' => is_numeric($code) ? (int)$code : 0]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // 2. Si no se encuentra, buscar en TRZ rolls por roll_code o id
        if (!$item) {
            try {
                if ($trzRoll) {
                    if (!empty($trzRoll['sku_code'])) {
                        $stmtBySku = $this->erpPdo->prepare("
                            SELECT i.*,
                                   COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id IN (16, 27)), 0) as stock_3000,
                                   COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id IN (1, 10, 11)), 0) as stock_mp
                            FROM item i
                            WHERE (i.item_number_prod = :sk OR i.item_number = :sk) AND i.item_status > 0
                            LIMIT 1
                        ");
                        $stmtBySku->execute([':sk' => $trzRoll['sku_code']]);
                        $item = $stmtBySku->fetch(PDO::FETCH_ASSOC);
                    }
                    if (!$item) {
                        $wCm = round(((float)($trzRoll['width_mm'] ?? 0)) / 10, 1);
                        $stmtF = $this->erpPdo->prepare("
                            SELECT i.*,
                                   COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id IN (16, 27)), 0) as stock_3000,
                                   COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id IN (1, 10, 11)), 0) as stock_mp
                            FROM item i
                            WHERE (i.item_reg_width = :w OR i.item_title LIKE :c) AND i.item_status > 0
                            LIMIT 1
                        ");
                        $stmtF->execute([':w' => $wCm, ':c' => '%' . ($trzRoll['color'] ?? '') . '%']);
                        $item = $stmtF->fetch(PDO::FETCH_ASSOC);
                    }
                    $regWeight = TraceableRollLookup::weightKg($this->trzPdo, $trzRoll, $code);
                    if ($item && $regWeight > 0) {
                        $item['item_reg_kg'] = $regWeight;
                    }
                }
            } catch (Throwable) {}
        }

        // 3. Si aún no se encuentra, probar búsqueda LIKE
        if (!$item) {
            $stmtLike = $this->erpPdo->prepare("
                SELECT i.*,
                       COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id IN (16, 27)), 0) as stock_3000,
                       COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id IN (1, 10, 11)), 0) as stock_mp
                FROM item i
                WHERE (i.item_number_prod LIKE :c OR i.item_title LIKE :c) AND i.item_status > 0
                LIMIT 1
            ");
            $stmtLike->execute([':c' => "%{$code}%"]);
            $item = $stmtLike->fetch(PDO::FETCH_ASSOC);
        }

        if (!$item) {
            return ['ok' => false, 'error' => "No se encontró ninguna bobina con el código '{$code}'."];
        }

        $itemId = (int)$item['id'];
        $stock3000 = (float)($item['stock_3000'] ?? 0);

        // Validación estricta de Bodega 3000:
        // 1. Si es una bobina de trazabilidad (TRZ), verificar que esté en Bodega 3000 (warehouse_id 18 o 58) y no consumida.
        if (!empty($trzRoll)) {
            $trzWhId = (int)($trzRoll['warehouse_id'] ?? 0);
            $trzStatus = strtoupper(trim((string)($trzRoll['status'] ?? '')));
            if ($trzStatus === 'CONSUMED') {
                return [
                    'ok' => false,
                    'error' => "La bobina de trazabilidad '{$code}' figura como CONSUMIDA en el sistema. No puede volver a procesarse.",
                ];
            }
            if (!in_array($trzWhId, [18, 58], true)) {
                $whName = 'Bodega ID #' . $trzWhId;
                try {
                    $stWh = $this->trzPdo->prepare("SELECT name FROM warehouses WHERE id = :w LIMIT 1");
                    $stWh->execute([':w' => $trzWhId]);
                    $whName = $stWh->fetchColumn() ?: $whName;
                } catch (Throwable) {}
                return [
                    'ok' => false,
                    'error' => "La bobina '{$code}' no está en Bodega 3000 (actualmente en '{$whName}'). Debe solicitarla o trasladarla a Bodega 3000 antes de cortarla.",
                ];
            }
        } else {
            // 2. Si es un SKU o código de ERP, verificar que tenga stock disponible en Bodega 3000
            if ($stock3000 <= 0.0001) {
                $itemLabel = trim(($item['item_number_prod'] ?? '') . ' - ' . ($item['item_title'] ?? ''));
                return [
                    'ok' => false,
                    'error' => "El ítem/SKU '{$code}' ({$itemLabel}) no tiene stock disponible en Bodega 3000 (Stock actual: 0). Verifique que tenga inventario en Bodega 3000 antes de cargarlo.",
                ];
            }
        }

        return [
            'ok' => true,
            'item' => $item,
            'item_id' => $itemId,
            'code' => $code,
            'item_number_prod' => $item['item_number_prod'] ?? '',
            'title' => $item['item_title'] ?? '',
            'weight_kg' => (float)($item['item_reg_kg'] ?? 0),
            'roll_id' => (int)($trzRoll['id'] ?? 0),
            'roll_code' => (string)($trzRoll['roll_code'] ?? $item['item_number_prod'] ?? $code),
        ];
    }

    /**
     * Obtiene las bobinas específicamente solicitadas para esta orden de trabajo (Trazabilidad).
     * Solo retorna las bobinas que ingresaron por solicitud a esta OT o están asignadas a la misma.
     */
    public function getRequestedRollsForRebo(int $workerOtId, int $agId): array
    {
        $requested = [];

        // 1. Recolectar OTs asociadas a esta agenda y orden comercial (para capturar bobinas de flexo/seri)
        $relatedOtIds = [];
        if ($workerOtId > 0) {
            $relatedOtIds[] = $workerOtId;
        }
        if ($agId > 0) {
            try {
                $stOts = $this->erpPdo->prepare("SELECT pwo.id FROM prod_worker_ot pwo WHERE pwo.wok_ag_id = :agid");
                $stOts->execute([':agid' => $agId]);
                foreach ($stOts->fetchAll(PDO::FETCH_COLUMN) as $oId) {
                    $relatedOtIds[] = (int)$oId;
                }

                // OTs de procesos previos de la misma orden (Flexo, Seri, Extrusión)
                $stPrevOts = $this->erpPdo->prepare("
                    SELECT pwo.id
                    FROM prod_worker_ot pwo
                    INNER JOIN prod_agenda pag ON pwo.wok_ag_id = pag.id
                    WHERE pag.ag_reqid = (SELECT ag_reqid FROM prod_agenda WHERE id = :agid2 LIMIT 1)
                ");
                $stPrevOts->execute([':agid2' => $agId]);
                foreach ($stPrevOts->fetchAll(PDO::FETCH_COLUMN) as $oId) {
                    $relatedOtIds[] = (int)$oId;
                }
            } catch (Throwable) {}
        }
        $relatedOtIds = array_values(array_unique(array_filter($relatedOtIds)));

        // Buscar en TRZ rolls asignadas o generadas por máquinas previas para esta orden
        if ($this->trzPdo !== null && !empty($relatedOtIds)) {
            try {
                $placeholders = implode(',', array_fill(0, count($relatedOtIds), '?'));
                $stmt = $this->trzPdo->prepare("
                    SELECT r.*, s.code AS sku_code, s.description AS sku_desc, s.name AS sku_name, w.name AS warehouse_name
                    FROM rolls r
                    LEFT JOIN skus s ON r.sku_id = s.id
                    LEFT JOIN warehouses w ON r.warehouse_id = w.id
                    WHERE (r.current_work_order_id IN ($placeholders) OR r.source_work_order_id IN ($placeholders))
                      AND r.status NOT IN ('CONSUMED', 'DISCARDED')
                    ORDER BY r.id DESC
                ");
                $stmt->execute(array_merge($relatedOtIds, $relatedOtIds));
                $trzRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($trzRows as $r) {
                    $rId = (int)$r['id'];

                    // Buscar el item correspondiente en ERP
                    $erpItem = null;
                    if (!empty($r['roll_code'])) {
                        $stItem = $this->erpPdo->prepare("
                            SELECT * FROM item WHERE item_number_prod = :c OR item_number = :c LIMIT 1
                        ");
                        $stItem->execute([':c' => $r['roll_code']]);
                        $erpItem = $stItem->fetch(PDO::FETCH_ASSOC);
                    }
                    if (!$erpItem && !empty($r['sku_code'])) {
                        $stItem = $this->erpPdo->prepare("
                            SELECT * FROM item WHERE item_number_prod = :c OR item_number = :c LIMIT 1
                        ");
                        $stItem->execute([':c' => $r['sku_code']]);
                        $erpItem = $stItem->fetch(PDO::FETCH_ASSOC);
                    }

                    $requested[] = [
                        'source' => 'trz',
                        'roll_id' => $rId,
                        'roll_code' => $r['roll_code'],
                        'sku_code' => $r['sku_code'] ?? ($erpItem['item_number_prod'] ?? ''),
                        'material' => $r['sku_name'] ?? ($erpItem['item_title'] ?? 'TNT'),
                        'colordesc' => $r['color'] ?? ($erpItem['colordesc'] ?? ''),
                        'width_cm' => round(((float)($r['width_mm'] ?? 0)) / 10, 1),
                        'weight_kg' => (float)($r['weight_kg'] ?? 0),
                        'meters' => (float)($r['meters'] ?? 0),
                        'erp_item_id' => (int)($erpItem['id'] ?? 0),
                        'status' => $r['status'],
                    ];
                }
            } catch (Throwable) {}
        }

        // 2. Buscar eventos de solicitud en ERP prod_worker_ot_events si no hubiese en TRZ
        if ($workerOtId > 0) {
            try {
                $stmtEvt = $this->erpPdo->prepare("
                    SELECT * FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id
                      AND evt_status = 1
                      AND evt_type = 'materiales'
                      AND evt_comments LIKE '%Solicitud Bobina%'
                    ORDER BY id DESC
                ");
                $stmtEvt->execute([':ot_id' => $workerOtId]);
                $evtRows = $stmtEvt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($evtRows as $ev) {
                    if (preg_match('/Solicitud Bobina\s+([A-Za-z0-9\-_]+)(?:\s+\[([A-Za-z0-9\-_]+)\])?/i', (string)$ev['evt_comments'], $mCode)) {
                        $rollCode = trim($mCode[1]);
                        $skuCode = trim($mCode[2] ?? '');

                        $already = false;
                        foreach ($requested as $rq) {
                            if ($rq['roll_code'] === $rollCode) { $already = true; break; }
                        }
                        if ($already) continue;

                        $stItem = $this->erpPdo->prepare("
                            SELECT * FROM item WHERE item_number_prod = :c OR item_number = :c LIMIT 1
                        ");
                        $stItem->execute([':c' => $skuCode !== '' ? $skuCode : $rollCode]);
                        $erpItem = $stItem->fetch(PDO::FETCH_ASSOC);

                        $requested[] = [
                            'source' => 'erp_event',
                            'roll_id' => 0,
                            'roll_code' => $rollCode,
                            'sku_code' => $skuCode,
                            'material' => $erpItem['item_title'] ?? 'TNT',
                            'colordesc' => $erpItem['item_desc'] ?? '',
                            'width_cm' => (float)($erpItem['item_reg_width'] ?? 0),
                            'weight_kg' => (float)$ev['evt_amount'],
                            'meters' => 0,
                            'erp_item_id' => (int)($erpItem['id'] ?? 0),
                            'status' => 'SOLICITADA',
                        ];
                    }
                }
            } catch (Throwable) {}
        }

        return $requested;
    }

    /**
     * Obtiene el listado de movimientos de producción finalizados en Rebobinado (form.reboprod.php líneas 700-880).
     */
    public function getReboFinishedMoves(int $agId): array
    {
        if ($agId <= 0) {
            return [];
        }
        try {
            $sql = "SELECT t1.*, t3.item_title, t3.item_number_prod, t2.item_amount,
                           t3.item_reg_kg, t2.item_reboprod_amt, t2.item_reboprod_unitkg
                    FROM stockchanges t1
                    INNER JOIN stockchanges_items t2 ON t1.id = t2.stk_id
                    INNER JOIN item t3 ON t2.item_id = t3.id
                    WHERE
                        t1.sth_fromrebo_agid = :agid AND
                        t1.stk_status        = 2 AND
                        t1.stk_negative      = 1 AND
                        t1.sth_fromrebo_agid_grphash != ''
                    ORDER BY t1.id ASC";
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([':agid' => $agId]);
            $moveouts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($moveouts as &$mo) {
                $sqlIn = "SELECT t1.*, t3.item_title, t3.item_number_prod, t2.item_amount,
                                 t3.item_reg_kg, t2.item_reboprod_amt, t2.item_reboprod_unitkg
                          FROM stockchanges t1
                          INNER JOIN stockchanges_items t2 ON t1.id = t2.stk_id
                          INNER JOIN item t3 ON t2.item_id = t3.id
                          WHERE
                              t1.sth_fromrebo_agid = :agid AND
                              t1.stk_status        = 2 AND
                              t1.stk_negative      = 0 AND
                              t1.sth_fromrebo_agid_grphash = :grphash
                          ORDER BY t1.id DESC";
                $stmtIn = $this->erpPdo->prepare($sqlIn);
                $stmtIn->execute([
                    ':agid' => $agId,
                    ':grphash' => $mo['sth_fromrebo_agid_grphash'],
                ]);
                $mo['moveins'] = $stmtIn->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
            unset($mo);
            return $moveouts;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Anula movimientos de rebobinado previamente registrados (form.reboprod.php líneas 4-22).
     */
    public function deleteReboStockChanges(array $delsthids, int $userId): bool
    {
        if (empty($delsthids)) {
            return false;
        }
        $now = time();
        foreach ($delsthids as $delsthid) {
            $id = (int)$delsthid;
            if ($id <= 0) continue;
            try {
                // Obtener datos del movimiento
                $stmt = $this->erpPdo->prepare("SELECT * FROM stockchanges WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $id]);
                $head = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$head || (int)($head['stk_status'] ?? 0) !== 2) {
                    continue;
                }
                $isNegative = (int)($head['stk_negative'] ?? 0) === 1;

                // Obtener ítems y revertir stock
                $stmtItems = $this->erpPdo->prepare("SELECT * FROM stockchanges_items WHERE stk_id = :stk_id");
                $stmtItems->execute([':stk_id' => $id]);
                $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];

                // Documento con kardex: se revierte con un documento contrario (stock + kardex). Sin kardex (anterior):
                // se revierte el stock como se movió.
                $hasKardex = $this->erpPdo->prepare('SELECT 1 FROM tran_data WHERE tran_id = ? AND tran_type IN (\'stockchangeup\',\'stockchangedown\') LIMIT 1');
                $hasKardex->execute([$id]);
                $ledgerReverse = $hasKardex->fetchColumn() !== false;
                foreach ($items as $it) {
                    $itemId = (int)($it['item_id'] ?? 0);
                    $stId = (int)($it['item_st_id'] ?? 0);
                    $amt = (float)($it['item_amount'] ?? 0);
                    if ($ledgerReverse && $itemId > 0 && $stId > 0 && $amt > 0) {
                        (new StockLedgerService($this->erpPdo))->post([
                            'key' => 'rebodel.' . $id . '.' . (int)($it['item_pos'] ?? 0), 'item_id' => $itemId, 'st_id' => $stId,
                            'shop_id' => (int)($head['stk_shopid'] ?? 0), 'delta' => $isNegative ? $amt : -$amt, 'allow_negative' => true,
                            'user_id' => $userId, 'text' => 'Anulación de ' . (string)($head['stk_num'] ?? $id) . ' (rebobinado/corte).',
                        ]);
                    } elseif ($itemId > 0 && $stId > 0 && $amt > 0) {
                        if ($isNegative) {
                            // Era salida (consumo) -> revertir sumando stock
                            $this->erpPdo->prepare("
                                UPDATE item_shops_storehouses
                                SET iss_inventory = iss_inventory + :amt
                                WHERE item_id = :itid AND st_id = :stid
                            ")->execute([':amt' => $amt, ':itid' => $itemId, ':stid' => $stId]);
                        } else {
                            // Era entrada (producido) -> revertir restando stock
                            $this->erpPdo->prepare("
                                UPDATE item_shops_storehouses
                                SET iss_inventory = GREATEST(0, iss_inventory - :amt)
                                WHERE item_id = :itid AND st_id = :stid
                            ")->execute([':amt' => $amt, ':itid' => $itemId, ':stid' => $stId]);
                        }
                    }
                }

                // Marcar como anulado
                $this->erpPdo->prepare("
                    UPDATE stockchanges
                    SET stk_status = 0, stk_upddat = :now, stk_updusr = :uid
                    WHERE id = :id
                ")->execute([':now' => $now, ':uid' => $userId, ':id' => $id]);

            } catch (Throwable) {}
        }
        return true;
    }

    /**
     * Finaliza la tarea de EMPALME de bobinas:
     * - Une 2 o más bobinas origen para convertirlas en una sola bobina de mayor metraje/peso.
     * - Consume todas las bobinas madre en ERP (Bodega 3000 salida stk_negative=1, issue 19)
     *   y genera los eventos 'materiales' en prod_worker_ot_events para el informe de consumo (consumo.php).
     * - En TRZ, actualiza las bobinas origen a 'CONSUMED'.
     * - Da de alta la bobina resultante empalmada en ERP (Bodega 3000 entrada stk_negative=0, issue 20)
     *   y en TRZ crea la nueva bobina en rolls (ROLL-EMP-XXXXX, process_stage='EMPALME', status='RECEIVED').
     */
    /**
     * Busca el id de una bodega en TRZ a partir de su código numérico (ej: 3000 -> 18).
     */
    public function findWarehouseIdByCode(int $warehouseCode): ?int
    {
        if ($this->trzPdo === null) {
            return null;
        }
        try {
            // Bodega 3000 de producción e insumos en TRZ corresponde a warehouse_id = 58 (erp_storehouse_id = 16 '3000 INSUMOS EN PRODUCCION')
            if ($warehouseCode === 3000 || $warehouseCode === 16) {
                $stmt = $this->trzPdo->query("SELECT id FROM warehouses WHERE erp_storehouse_id = 16 OR name LIKE '%3000 INSUMOS%' LIMIT 1");
                $foundId = $stmt->fetchColumn();
                if ($foundId) {
                    return (int)$foundId;
                }
            }

            $stmt = $this->trzPdo->prepare('SELECT id FROM warehouses WHERE code = :code LIMIT 1');
            $stmt->execute([':code' => $warehouseCode]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row !== false ? (int)$row['id'] : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function executeReboEmpalme(int $agId, array $postData, int $userId, int $companyId, int $workerOtId = 0, string $operatorName = ''): array
    {
        if ($agId <= 0) {
            return ['ok' => false, 'error' => 'Orden de trabajo inválida.'];
        }

        // 1. Obtener datos de la agenda
        $stmtAg = $this->erpPdo->prepare("
            SELECT pa.*, o.req_rebo_type, o.req_rebo_rolloscc, o.req_rebo_cortescc, o.req_shop_id,
                   oi.fab_type, o.req_number, o.id as order_id,
                   oi.fab_mat_fabric_color, oi.fab_mat_manilla_color
            FROM prod_agenda pa
            LEFT JOIN orders o ON pa.ag_reqid = o.id
            LEFT JOIN orders_items oi ON o.id = oi.req_id
            WHERE pa.id = :id LIMIT 1
        ");
        $stmtAg->execute([':id' => $agId]);
        $agenda = $stmtAg->fetch(PDO::FETCH_ASSOC) ?: [];
        $reqShopId = (int)($agenda['req_shop_id'] ?? 30010);
        $orderId = (int)($agenda['order_id'] ?? $agenda['ag_reqid'] ?? 0);
        $orderNum = (string)($agenda['req_number'] ?? '');
        $plantaId = (int)($agenda['ag_plantaid'] ?? 1);
        $equipoId = (int)($agenda['ag_equipo_id'] ?? $agenda['ag_equipoid'] ?? 0);

        // 2. Recolectar las bobinas seleccionadas para empalmar
        $inputRolls = [];
        for ($slot = 1; $slot <= 10; $slot++) {
            $itemId = (int)($postData["selitemid_{$slot}"] ?? $postData["selitemid{$slot}"] ?? 0);
            if ($itemId <= 0) continue;

            $stmtI = $this->erpPdo->prepare("
                SELECT i.*, COALESCE(iss.st_id, 16) as st_id, iss.iss_inventory
                FROM item i
                LEFT JOIN item_shops_storehouses iss ON i.id = iss.item_id
                WHERE i.id = :id LIMIT 1
            ");
            $stmtI->execute([':id' => $itemId]);
            $itemRow = $stmtI->fetch(PDO::FETCH_ASSOC);
            if (!$itemRow) continue;

            $ingreso = (float)str_replace(',', '.', (string)($postData["ingresokgamt_{$slot}"] ?? $itemRow['item_reg_kg']));
            $sobrante = (float)str_replace(',', '.', (string)($postData["sobranteamt_{$slot}"] ?? 0));
            $usedKg = max(0.0, $ingreso - $sobrante);
            $rollCode = trim((string)($postData["scanned_roll_code_{$slot}"] ?? ($itemRow['item_number_prod'] ?? '')));
            $rollId = (int)($postData["scanned_roll_id_{$slot}"] ?? 0);

            $inputRolls[] = [
                'slot' => $slot,
                'item_id' => $itemId,
                'item' => $itemRow,
                'ingreso_kg' => $ingreso,
                'sobrante_kg' => $sobrante,
                'used_kg' => $usedKg,
                'roll_code' => $rollCode,
                'roll_id' => $rollId,
                'length' => (float)($itemRow['item_reg_length'] ?? 0),
            ];
        }

        if (count($inputRolls) < 2) {
            return ['ok' => false, 'error' => 'Para la tarea Empalme debe ingresar o escanear al menos 2 bobinas para unirlas.'];
        }

        $now = time();
        $grpHash = md5((string)microtime(true));

        // Asegurar prod_worker_ot
        if ($workerOtId <= 0) {
            $stmtOt = $this->erpPdo->prepare("SELECT id FROM prod_worker_ot WHERE wok_ag_id = :agid ORDER BY id DESC LIMIT 1");
            $stmtOt->execute([':agid' => $agId]);
            $workerOtId = (int)($stmtOt->fetchColumn() ?: 0);
            if ($workerOtId <= 0) {
                $initId = 0;
                if ($equipoId > 0) {
                    $stInit = $this->erpPdo->prepare("SELECT id FROM prod_worker_init WHERE win_equipoid = :eqid ORDER BY id DESC LIMIT 1");
                    $stInit->execute([':eqid' => $equipoId]);
                    $initId = (int)($stInit->fetchColumn() ?: 0);
                }
                try {
                    $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot (wok_ag_id, wok_init_id, wok_crtdat, wok_status, wok_idusuario)
                        VALUES (:agid, :init_id, :now, 1, :uid)
                    ")->execute([':agid' => $agId, ':init_id' => $initId, ':now' => $now, ':uid' => $userId]);
                    $workerOtId = (int)$this->erpPdo->lastInsertId();
                } catch (Throwable) {}
            }
        }

        $totalUsedKg = 0.0;
        $totalMeters = 0.0;
        $mermaKg = (float)str_replace(',', '.', (string)($postData['itemmermakg'] ?? 0));
        $primaryItem = $inputRolls[0]['item'];
        $consumedDetails = [];

        // 3. Consumir cada una de las bobinas de entrada
        foreach ($inputRolls as $rData) {
            $uKg = $rData['used_kg'];
            $totalUsedKg += $uKg;
            $totalMeters += $rData['length'];
            $itRow = $rData['item'];
            $bKg = (float)($itRow['item_reg_kg'] ?? 1);
            if ($bKg <= 0) $bKg = 1.0;
            $uUnits = round($uKg / $bKg, 10);
            $stkFixedSthId = (int)($itRow['st_id'] ?? 16);

            $stkNumOut = $this->generateNextStockChangeNumber($companyId);
            $annotText = number_format($uKg, 2, ',', '.') . ' Kilogramos (Empalme de bobina)';

            // Salida ERP interna (issue 19, stk_negative=1)
            // Para empalme: sth_fromprodotid=0 y sth_fromrebo_agid=0 para NO registrar consumo en informe de materiales
            // ya que provienen de máquinas anteriores y ya fueron consumidas
            $stmtSC = $this->erpPdo->prepare("
                INSERT INTO stockchanges
                (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate, stk_negative,
                 stk_status, stk_crtdat, stk_crtusr, stk_fixedsthid, sth_fromrebo_agid, sth_fromrebo_agid_grphash,
                 sth_fromprodotid, sth_order_id, sth_order_num, sth_planta_id, sth_assign_wrkid, sth_assign_equipoid)
                VALUES
                (:num, :annot, 19, :compid, :shopid, :bdate, 1, 2, :crtdat, :crtusr, :sthid, 0, :grphash,
                 0, :order_id, :order_num, :planta_id, :worker_id, :equipo_id)
            ");
            $stmtSC->execute([
                ':num' => $stkNumOut,
                ':annot' => $annotText,
                ':compid' => $companyId > 0 ? $companyId : 20010,
                ':shopid' => $reqShopId,
                ':bdate' => $now,
                ':crtdat' => $now,
                ':crtusr' => $userId,
                ':sthid' => $stkFixedSthId,
                ':grphash' => $grpHash,
                ':order_id' => $orderId,
                ':order_num' => $orderNum,
                ':planta_id' => $plantaId,
                ':worker_id' => $userId,
                ':equipo_id' => $equipoId,
            ]);
            $stkIdOut = (int)$this->erpPdo->lastInsertId();

            // Consumo de 1 bobina exacta (item_amount = 1), con los kilos registrados en item_reboprod_unitkg
            $this->erpPdo->prepare("
                INSERT INTO stockchanges_items
                (stk_id, item_id, item_pos, item_amount, item_type, item_st_id,
                 item_costprice_brutto, item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes,
                 item_charges_act, item_sellprice_brutto, item_reboprod_amt, item_reboprod_unitkg)
                VALUES
                (:stk_id, :itid, 0, 1, 'item', :sthid, 0, 0, 0, 0, 0, 0, 1, :unitkg)
            ")->execute([
                ':stk_id' => $stkIdOut,
                ':itid' => $rData['item_id'],
                ':sthid' => $stkFixedSthId,
                ':unitkg' => $uKg,
            ]);

            try {
                $this->applyStockChangeLine($stkIdOut);
            } catch (Throwable $e) {
                error_log('Kardex del empalme (documento ' . $stkIdOut . '): ' . $e->getMessage());
            }

            if ($this->trzPdo !== null) {
                try {
                    $rCode = $rData['roll_code'];
                    $rId = $rData['roll_id'];
                    if ($rId > 0) {
                        $this->trzPdo->prepare("UPDATE rolls SET status = 'CONSUMED', weight_kg = GREATEST(0, weight_kg - :u) WHERE id = :id")->execute([':u' => $uKg, ':id' => $rId]);
                    } elseif ($rCode !== '') {
                        $this->trzPdo->prepare("UPDATE rolls SET status = 'CONSUMED', weight_kg = GREATEST(0, weight_kg - :u) WHERE roll_code = :c")->execute([':u' => $uKg, ':c' => $rCode]);
                    }
                } catch (Throwable) {}
            }

            $consumedDetails[] = "{$itRow['item_number_prod']} ({$uKg} Kg)";
        }

        // 4. Generar la bobina resultante del empalme
        $destNetKg = max(0.0, $totalUsedKg - $mermaKg);
        $primaryBaseKg = (float)($primaryItem['item_reg_kg'] ?? 1);
        if ($primaryBaseKg <= 0) $primaryBaseKg = 1.0;
        $destUnits = round($destNetKg / $primaryBaseKg, 10);
        if ($destUnits <= 0) $destUnits = 1.0;
        $stkFixedSthId = 16; // Siempre a Bodega 3000 Insumos en Produccion

        $stkNumIn = $this->generateNextStockChangeNumber($companyId);
        $stmtSCIn = $this->erpPdo->prepare("
            INSERT INTO stockchanges
            (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate, stk_negative,
             stk_status, stk_crtdat, stk_crtusr, stk_fixedsthid, sth_fromrebo_agid, sth_fromrebo_agid_grphash,
             sth_fromprodotid, sth_order_id, sth_order_num, sth_planta_id, sth_assign_wrkid, sth_assign_equipoid)
            VALUES
            (:num, 'Generado por producción - Bobina Empalmada', 20, :compid, :shopid, :bdate, 0, 2, :crtdat, :crtusr, 16, :agid, :grphash,
             :ot_id, :order_id, :order_num, :planta_id, :worker_id, :equipo_id)
        ");
        $stmtSCIn->execute([
            ':num' => $stkNumIn,
            ':compid' => $companyId > 0 ? $companyId : 20010,
            ':shopid' => $reqShopId,
            ':bdate' => $now,
            ':crtdat' => $now,
            ':crtusr' => $userId,
            ':agid' => $agId,
            ':grphash' => $grpHash,
            ':ot_id' => $workerOtId,
            ':order_id' => $orderId,
            ':order_num' => $orderNum,
            ':planta_id' => $plantaId,
            ':worker_id' => $userId,
            ':equipo_id' => $equipoId,
        ]);
        $stkIdIn = (int)$this->erpPdo->lastInsertId();

        $this->erpPdo->prepare("
            INSERT INTO stockchanges_items
            (stk_id, item_id, item_pos, item_amount, item_type, item_st_id,
             item_costprice_brutto, item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes,
             item_charges_act, item_sellprice_brutto, item_reboprod_amt, item_reboprod_unitkg)
            VALUES
            (:stk_id, :itid, 0, :amt, 'item', 16, 0, 0, 0, 0, 0, 0, 1, :unitkg)
        ")->execute([
            ':stk_id' => $stkIdIn,
            ':itid' => $primaryItem['id'],
            ':amt' => $destUnits,
            ':unitkg' => round($destNetKg, 2),
        ]);

        try {
            $this->applyStockChangeLine($stkIdIn);
        } catch (Throwable $e) {
            error_log('Kardex del empalme (documento ' . $stkIdIn . '): ' . $e->getMessage());
        }

        if ($workerOtId > 0) {
            try {
                $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events
                    (evt_prod_worker_otid, evt_type, evt_amount, prod_bobina_kg, evt_amount2, evt_crtdat, evt_enddat, evt_status, evt_comments)
                    VALUES
                    (:ot_id, 'prod', 1, :kg, :merma, :now, :now, 1, :comments)
                ")->execute([
                    ':ot_id' => $workerOtId,
                    ':kg' => $destNetKg,
                    ':merma' => $mermaKg,
                    ':now' => $now,
                    ':comments' => "Empalme finalizado: Unión de " . count($inputRolls) . " bobinas [" . implode(', ', $consumedDetails) . "] -> Salida: {$destNetKg} Kg (Merma: {$mermaKg} Kg)",
                ]);
            } catch (Throwable) {}
        }

        // 5. Alta en TRZ rolls en Bodega 3000
        $childRollId = 0;
        $childRollCode = '';
        if ($this->trzPdo !== null) {
            try {
                $skuCode = 'EMP-' . $primaryItem['id'];
                $skuId = 1;
                $stmtSku = $this->trzPdo->prepare("SELECT id FROM skus WHERE code = :c LIMIT 1");
                $stmtSku->execute([':c' => $skuCode]);
                $foundSku = $stmtSku->fetchColumn();
                if ($foundSku) {
                    $skuId = (int)$foundSku;
                } else {
                    $stmtInsSku = $this->trzPdo->prepare("INSERT INTO skus (code, description, is_active) VALUES (:c, :d, 1)");
                    $stmtInsSku->execute([':c' => $skuCode, ':d' => "Bobina Empalmada " . ($primaryItem['item_reg_width'] ?? '') . "cm"]);
                    $skuId = (int)$this->trzPdo->lastInsertId();
                }

                $stmtMax = $this->trzPdo->query("SELECT MAX(id) FROM rolls");
                $nextNum = ((int)$stmtMax->fetchColumn()) + 1;
                $childRollCode = sprintf('ROLL-EMP-%05d', $nextNum);
                $widthMm = (int)round(((float)($primaryItem['item_reg_width'] ?? 0)) * 10);
                $wh3000Trz = $this->findWarehouseIdByCode(3000) ?: 58;

                $insRoll = $this->trzPdo->prepare("
                    INSERT INTO rolls
                    (roll_code, sku_id, warehouse_id, weight_kg, received_qty, width_mm, meters, status, parent_roll_id, source_work_order_id, process_stage)
                    VALUES
                    (:code, :sku, :wh_id, :weight, 1.000, :width, :meters, 'RECEIVED', :parent, :wo_id, 'EMPALME')
                ");
                $insRoll->execute([
                    ':code' => $childRollCode,
                    ':sku' => $skuId,
                    ':wh_id' => $wh3000Trz,
                    ':weight' => number_format($destNetKg, 3, '.', ''),
                    ':width' => $widthMm > 0 ? $widthMm : null,
                    ':meters' => $totalMeters > 0 ? $totalMeters : null,
                    ':parent' => $inputRolls[0]['roll_id'] > 0 ? $inputRolls[0]['roll_id'] : null,
                    ':wo_id' => $workerOtId > 0 ? $workerOtId : null,
                ]);
                $childRollId = (int)$this->trzPdo->lastInsertId();

                try {
                    $stmtWhName = $this->trzPdo->prepare("SELECT name, erp_storehouse_id FROM warehouses WHERE id = :id LIMIT 1");
                    $stmtWhName->execute([':id' => $wh3000Trz]);
                    $whRow = $stmtWhName->fetch(PDO::FETCH_ASSOC) ?: [];
                    $whNameStr = (string)($whRow['name'] ?? '') ?: '3000 INSUMOS EN PRODUCCION';
                    // El código usa el id de bodega del ERP; la bobina (rolls) sigue con el id local.
                    $whEntryId = (int)($whRow['erp_storehouse_id'] ?? 0) ?: $wh3000Trz;

                    $stmtInsTwe = $this->trzPdo->prepare("
                        INSERT INTO traceability_warehouse_entries
                        (traceability_code, entity_type, entity_id, warehouse_id, warehouse_name,
                         item_id, item_code, item_description, quantity, unit_name,
                         weight_kg, width_mm, meters, order_ref, operator_name, comments, status, created_at)
                        VALUES
                        (:code, 'ROLL', :eid, :wh_id, :wh_name,
                         :item_id, :item_code, :item_desc, 1.000, 'UNID',
                         :weight, :width, :meters, :order_ref, :op_name, :comments, 'IN_WAREHOUSE', NOW())
                    ");
                    $stmtInsTwe->execute([
                        ':code' => $childRollCode,
                        ':eid' => $childRollId,
                        ':wh_id' => $whEntryId,
                        ':wh_name' => $whNameStr,
                        ':item_id' => (int)($primaryItem['id'] ?? 0) > 0 ? (int)$primaryItem['id'] : null,
                        ':item_code' => $skuCode,
                        ':item_desc' => 'Bobina Empalmada ' . ($primaryItem['item_title'] ?? ''),
                        ':weight' => $destNetKg,
                        ':width' => $widthMm > 0 ? $widthMm : null,
                        ':meters' => $totalMeters > 0 ? $totalMeters : null,
                        ':order_ref' => $workerOtId > 0 ? ('OT #' . $workerOtId) : null,
                        ':op_name' => $operatorName !== '' ? $operatorName : 'Operador Rebobinadora',
                        ':comments' => 'Empalme de bobinas: ' . implode(', ', array_column($inputRolls, 'roll_code')),
                    ]);
                } catch (Throwable) {}

                $stmtEvt = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
                $stmtEvt->execute([
                    ':type' => 'EMPALME_ROLL_CREATED',
                    ':payload' => json_encode([
                        'roll_id' => $childRollId,
                        'roll_code' => $childRollCode,
                        'input_rolls' => array_column($inputRolls, 'roll_code'),
                        'weight_kg' => $destNetKg,
                        'waste_kg' => $mermaKg,
                        'source_work_order_id' => $workerOtId,
                        'operator_id' => $userId,
                        'timestamp' => $now,
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            } catch (Throwable) {}
        }

        return [
            'ok' => true,
            'is_empalme' => true,
            'rebo_type' => 'Empalme',
            'consumed' => true,
            'created_roll' => [
                'id' => $childRollId,
                'roll_code' => $childRollCode,
                'weight_kg' => $destNetKg,
            ],
            'created_rolls' => [
                [
                    'id' => $childRollId,
                    'roll_code' => $childRollCode,
                    'weight_kg' => $destNetKg,
                ]
            ],
            'count' => count($inputRolls),
        ];
    }

    /**
     * Ejecuta la finalización de Rebobinado / Corte / Empalme (form.reboprod.php líneas 65-430 y 900-1000).
     *
     * REGLAS DE NEGOCIO:
     * - TAREA EMPALME: Une 2 o más bobinas en una sola de mayor tamaño. Consume las bobinas de origen en Bodega 3000
     *   (con registro en consumo de materiales) y da de alta la nueva bobina empalmada con trazabilidad.
     * - TAREA CORTE (SOLO MANILLAS o BOLSAS + MANILLAS): Consume la bobina madre en Bodega 3000 para los informes
     *   oficiales de consumo (igual que en flexografía). Da de alta en Bodega 3000 las bobinas de bolsa (ROLL-CUT)
     *   y las manillas (ROLL-HDL en pallet PAL-XXXXXX).
     * - SOLO REBOBINADO SIMPLE: Genera bobina rebobinada descontando merma.
     */
    public function executeReboFinalize(int $agId, array $postData, int $userId, int $companyId, int $workerOtId = 0, string $operatorName = ''): array
    {
        if ($agId <= 0) {
            return ['ok' => false, 'error' => 'Orden de trabajo inválida.'];
        }

        // Obtener datos de la OT y de la agenda
        $stmtAg = $this->erpPdo->prepare("
            SELECT pa.*, o.req_rebo_type, o.req_rebo_rolloscc, o.req_rebo_cortescc, o.req_rebo_rolloscc_opt, o.req_shop_id,
                   oi.fab_type, o.req_number, o.id as order_id,
                   oi.fab_mat_fabric_color, oi.fab_mat_manilla_color
            FROM prod_agenda pa
            LEFT JOIN orders o ON pa.ag_reqid = o.id
            LEFT JOIN orders_items oi ON o.id = oi.req_id
            WHERE pa.id = :id LIMIT 1
        ");
        $stmtAg->execute([':id' => $agId]);
        $agenda = $stmtAg->fetch(PDO::FETCH_ASSOC) ?: [];
        $reboType = trim((string)($postData['req_rebo_type'] ?? ($agenda['req_rebo_type'] ?? 'Corte')));
        $isEmpalme = ($reboType === 'Empalme') || !empty($postData['is_empalme']) || (!empty($postData['task']) && $postData['task'] === 'empalme');

        // Si la tarea es Empalme, delegar al método especializado de unión de bobinas
        if ($isEmpalme) {
            return $this->executeReboEmpalme($agId, $postData, $userId, $companyId, $workerOtId, $operatorName);
        }

        $selItemId = (int)($postData['selitemid1'] ?? $postData['selitemid'] ?? 0);
        if ($selItemId <= 0) {
            return ['ok' => false, 'error' => 'No se seleccionó una bobina válida.'];
        }

        $reqShopId = (int)($agenda['req_shop_id'] ?? 30010);
        $orderId = (int)($agenda['order_id'] ?? $agenda['ag_reqid'] ?? 0);
        $orderNum = (string)($agenda['req_number'] ?? '');
        $plantaId = (int)($agenda['ag_plantaid'] ?? 1);
        $equipoId = (int)($agenda['ag_equipo_id'] ?? $agenda['ag_equipoid'] ?? 0);

        // Obtener datos del ítem de origen (incluyendo color y material exactos)
        $stmtItem = $this->erpPdo->prepare("
            SELECT i.*, iss.st_id, iss.iss_inventory,
                   COALESCE((SELECT t4b.add_name FROM tran_comments_item_vals t4 INNER JOIN tran_comments_vals t4b ON t4.val_id = t4b.id WHERE t4.item_id = i.id AND t4.com_id = 17 LIMIT 1), i.item_title) as colordesc,
                   COALESCE((SELECT t4.val_id FROM tran_comments_item_vals t4 WHERE t4.item_id = i.id AND t4.com_id = 17 LIMIT 1), 0) as colorid,
                   COALESCE((SELECT t6.add_name FROM tran_comments_item_vals t5 INNER JOIN tran_comments_vals t6 ON t5.val_id = t6.id WHERE t5.item_id = i.id AND t5.com_id = 18 LIMIT 1), 'TNT') as material,
                   COALESCE((SELECT t5.val_id FROM tran_comments_item_vals t5 WHERE t5.item_id = i.id AND t5.com_id = 18 LIMIT 1), 0) as material_id
            FROM item i
            INNER JOIN item_shops_storehouses iss ON i.id = iss.item_id
            INNER JOIN company_shops_storehouses css ON iss.st_id = css.id
            WHERE i.id = :id AND css.st_name LIKE '3000%' AND iss.iss_inventory > 0
            LIMIT 1
        ");
        $stmtItem->execute([':id' => $selItemId]);
        $itemSel = $stmtItem->fetch(PDO::FETCH_ASSOC);
        if (!$itemSel) {
            // Intentar sin filtro de bodega si ya fue parcialmente consumida
            $stmtItem2 = $this->erpPdo->prepare("
                SELECT i.*, iss.st_id, iss.iss_inventory,
                       COALESCE((SELECT t4b.add_name FROM tran_comments_item_vals t4 INNER JOIN tran_comments_vals t4b ON t4.val_id = t4b.id WHERE t4.item_id = i.id AND t4.com_id = 17 LIMIT 1), i.item_title) as colordesc,
                       COALESCE((SELECT t4.val_id FROM tran_comments_item_vals t4 WHERE t4.item_id = i.id AND t4.com_id = 17 LIMIT 1), 0) as colorid,
                       COALESCE((SELECT t6.add_name FROM tran_comments_item_vals t5 INNER JOIN tran_comments_vals t6 ON t5.val_id = t6.id WHERE t5.item_id = i.id AND t5.com_id = 18 LIMIT 1), 'TNT') as material,
                       COALESCE((SELECT t5.val_id FROM tran_comments_item_vals t5 WHERE t5.item_id = i.id AND t5.com_id = 18 LIMIT 1), 0) as material_id
                FROM item i
                LEFT JOIN item_shops_storehouses iss ON i.id = iss.item_id
                WHERE i.id = :id LIMIT 1
            ");
            $stmtItem2->execute([':id' => $selItemId]);
            $itemSel = $stmtItem2->fetch(PDO::FETCH_ASSOC);
        }

        if (!$itemSel) {
            return ['ok' => false, 'error' => 'No se encontró la bobina en inventario.'];
        }

        $now = time();
        $useKgAmt = (float)str_replace(',', '.', (string)($postData['useamt_1'] ?? $postData['useamt'] ?? 0));
        if ($useKgAmt <= 0) {
            $ingresoKg = (float)str_replace(',', '.', (string)($postData['ingresokgamt_1'] ?? $itemSel['item_reg_kg']));
            $sobranteKg = (float)str_replace(',', '.', (string)($postData['sobranteamt_1'] ?? 0));
            $useKgAmt = max(0.0, $ingresoKg - $sobranteKg);
        }

        $baseKg = (float)($itemSel['item_reg_kg'] ?? 1);
        if ($baseKg <= 0) $baseKg = 1.0;
        $origenUnits = round($useKgAmt / $baseKg, 10);
        $stkFixedSthId = (int)($itemSel['st_id'] ?? 16);
        $grpHash = md5((string)microtime(true));

        // Asegurar la existencia de prod_worker_ot para vincular el informe oficial de consumo
        if ($workerOtId <= 0) {
            $stmtOt = $this->erpPdo->prepare("
                SELECT id FROM prod_worker_ot WHERE wok_ag_id = :agid ORDER BY id DESC LIMIT 1
            ");
            $stmtOt->execute([':agid' => $agId]);
            $workerOtId = (int)($stmtOt->fetchColumn() ?: 0);
            if ($workerOtId <= 0) {
                $initId = 0;
                if ($equipoId > 0) {
                    $stInit = $this->erpPdo->prepare("SELECT id FROM prod_worker_init WHERE win_equipoid = :eqid ORDER BY id DESC LIMIT 1");
                    $stInit->execute([':eqid' => $equipoId]);
                    $initId = (int)($stInit->fetchColumn() ?: 0);
                }
                try {
                    $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot (wok_ag_id, wok_init_id, wok_crtdat, wok_status, wok_idusuario)
                        VALUES (:agid, :init_id, :now, 1, :uid)
                    ")->execute([':agid' => $agId, ':init_id' => $initId, ':now' => $now, ':uid' => $userId]);
                    $workerOtId = (int)$this->erpPdo->lastInsertId();
                } catch (Throwable) {}
            }
        }

        // -------------------------------------------------------------
        // CASO 1: REBOBINADO SIMPLE -> SOLO REBOBINA LA BOBINA (NO AFECTA INFORME DE MATERIALES)
        // -------------------------------------------------------------
        $isRebobinado = $reboType === 'Rebobinado' || ($postData['task'] ?? '') === 'rebobinado';
        $createdChildRolls = [];
        $createdBagRolls = [];
        $createdHandleRolls = [];
        $createdHandlePallet = null;
        $totalScrappedManillasAmt = 0;
        $totalScrappedManillasKg = 0.0;
        $generalMermaKg = (float)str_replace(',', '.', (string)($postData['itemmermakg'] ?? 0));
        if ($reboType === 'Rebobinado' || (!empty($postData['task']) && $postData['task'] === 'rebobinado')) {
            $mermaKg = (float)str_replace(',', '.', (string)($postData['itemmermakg'] ?? 0));
            $destUseKg = max(0.0, $useKgAmt - $mermaKg);
            $destUnits = round($destUseKg / $baseKg, 10);
            if ($destUnits <= 0) $destUnits = 1.0;

            // 1. Dar de alta la bobina rebobinada resultante (stk_negative = 0, issue 20 = Generado por producción)
            // NOTA: sth_fromprodotid = 0 y sth_fromrebo_agid = 0 para no alterar ni duplicar en el informe de materiales
            $stkNumIn = $this->generateNextStockChangeNumber($companyId);
            $stmtSCIn = $this->erpPdo->prepare("
                INSERT INTO stockchanges
                (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate, stk_negative,
                 stk_status, stk_crtdat, stk_crtusr, stk_fixedsthid, sth_fromrebo_agid, sth_fromrebo_agid_grphash,
                 sth_fromprodotid, sth_order_id, sth_order_num, sth_planta_id, sth_assign_wrkid, sth_assign_equipoid)
                VALUES
                (:num, :annot, 20, :compid, :shopid, :bdate, 0, 2, :crtdat, :crtusr, :sthid, 0, :grphash,
                 0, :order_id, :order_num, :planta_id, :worker_id, :equipo_id)
            ");
            $annotRebo = "Rebobinado finalizado - Rollo {$itemSel['item_number_prod']}" . ($mermaKg > 0 ? " (Merma: {$mermaKg} kg)" : "");
            $stmtSCIn->execute([
                ':num' => $stkNumIn,
                ':annot' => $annotRebo,
                ':compid' => $companyId > 0 ? $companyId : 20010,
                ':shopid' => $reqShopId,
                ':bdate' => $now,
                ':crtdat' => $now,
                ':crtusr' => $userId,
                ':sthid' => 16, // Siempre a Bodega 3000
                ':grphash' => $grpHash,
                ':order_id' => $orderId,
                ':order_num' => $orderNum,
                ':planta_id' => $plantaId,
                ':worker_id' => $userId,
                ':equipo_id' => $equipoId,
            ]);
            $stkIdIn = (int)$this->erpPdo->lastInsertId();
            if ($stkIdIn > 0) {
                $this->erpPdo->prepare("
                    INSERT INTO stockchanges_items
                    (stk_id, item_id, item_pos, item_amount, item_type, item_st_id,
                     item_costprice_brutto, item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes,
                     item_charges_act, item_sellprice_brutto, item_reboprod_amt, item_reboprod_unitkg)
                    VALUES
                    (:stk_id, :itid, 0, 1, 'item', 16, 0, 0, 0, 0, 0, 0, 1, :unitkg)
                ")->execute([
                    ':stk_id' => $stkIdIn,
                    ':itid' => $selItemId,
                    ':unitkg' => round($destUseKg, 2),
                ]);
            }

            // 2. Registrar evento operativo 'prod' (NUNCA 'materiales') en prod_worker_ot_events
            if ($workerOtId > 0) {
                try {
                    $reboComment = trim((string)($postData['rebo_comments'] ?? ''));
                    if ($reboComment === '') {
                        $reboComment = "Rebobinado completado - Bobina {$itemSel['item_number_prod']} ({$destUseKg} Kg" . ($mermaKg > 0 ? ", Merma: {$mermaKg} Kg" : "") . ")";
                    } else {
                        $reboComment = "Rebobinado: Bobina {$itemSel['item_number_prod']} ({$destUseKg} Kg) - " . $reboComment;
                    }
                    $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot_events
                        (evt_prod_worker_otid, evt_type, evt_amount, prod_bobina_kg, evt_amount2, evt_crtdat, evt_enddat, evt_status, evt_comments)
                        VALUES
                        (:ot_id, 'prod', 1, :kg, :waste, :now, :now, 2, :comments)
                    ")->execute([
                        ':ot_id' => $workerOtId,
                        ':kg' => $destUseKg,
                        ':waste' => $mermaKg,
                        ':now' => $now,
                        ':comments' => $reboComment,
                    ]);
                } catch (Throwable) {}
            }
            return ['ok' => true, 'rebo_type' => 'Rebobinado', 'msg' => "Bobina {$itemSel['item_number_prod']} rebobinada con éxito."];
        }

        // -------------------------------------------------------------
        // CASO 2: CORTE DE BOBINA (MANILLAS / BOLSAS) -> CONSUME LA BOBINA
        // -------------------------------------------------------------
        if ($origenUnits > 0) {
            $skuCode = trim((string)($itemSel['item_number_prod'] ?? ''));
            // REGLA ESTRICTA DE CONSUMO:
            // Solo las bobinas TEL vírgenes (Bodega 3000) se consumen en el ERP oficial e informe de materiales.
            // Las bobinas provenientes de Flexo o Seri YA fueron consumidas en sus respectivas máquinas.
            $isVirginTel = (stripos($skuCode, 'TEL') === 0);
            $sourceTraceRollId = (int)($postData['scanned_roll_id'] ?? $postData['roll_id'] ?? 0);
            $sourceTraceRoll = null;
            if ($sourceTraceRollId > 0 && $this->trzPdo !== null) {
                $sourceStmt = $this->trzPdo->prepare('SELECT process_stage, microns, color FROM rolls WHERE id = :id');
                $sourceStmt->execute([':id' => $sourceTraceRollId]);
                $sourceTraceRoll = $sourceStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($sourceTraceRoll !== null && in_array((string)$sourceTraceRoll['process_stage'], ['PRINTED', 'CUT'], true)) {
                    $isVirginTel = false;
                }
            }

            if ($isVirginTel) {
                // 1. Crear número de transacción para la salida de la bobina virgen TEL
                $stkNumOut = $this->generateNextStockChangeNumber($companyId);
                $annotText = number_format($useKgAmt, 2, ',', '.') . ' Kilogramos';

                // Insertar cabecera de salida (stk_negative = 1, issue 19 = Uso material) vinculada a la OT
                $stmtSC = $this->erpPdo->prepare("
                    INSERT INTO stockchanges
                    (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate, stk_negative,
                     stk_status, stk_crtdat, stk_crtusr, stk_fixedsthid, sth_fromrebo_agid, sth_fromrebo_agid_grphash,
                     sth_fromprodotid, sth_order_id, sth_order_num, sth_planta_id, sth_assign_wrkid, sth_assign_equipoid)
                    VALUES
                    (:num, :annot, 19, :compid, :shopid, :bdate, 1, 2, :crtdat, :crtusr, :sthid, :agid, :grphash,
                     :ot_id, :order_id, :order_num, :planta_id, :worker_id, :equipo_id)
                ");
                $stmtSC->execute([
                    ':num' => $stkNumOut,
                    ':annot' => $annotText,
                    ':compid' => $companyId > 0 ? $companyId : 20010,
                    ':shopid' => $reqShopId,
                    ':bdate' => $now,
                    ':crtdat' => $now,
                    ':crtusr' => $userId,
                    ':sthid' => $stkFixedSthId,
                    ':agid' => $agId,
                    ':grphash' => $grpHash,
                    ':ot_id' => $workerOtId,
                    ':order_id' => $orderId,
                    ':order_num' => $orderNum,
                    ':planta_id' => $plantaId,
                    ':worker_id' => $userId,
                    ':equipo_id' => $equipoId,
                ]);
                $stkIdOut = (int)$this->erpPdo->lastInsertId();

                // Insertar ítem de salida: siempre 1 bobina consumida (item_amount = 1), con los kilos en item_reboprod_unitkg
                $this->erpPdo->prepare("
                    INSERT INTO stockchanges_items
                    (stk_id, item_id, item_pos, item_amount, item_type, item_st_id,
                     item_costprice_brutto, item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes,
                     item_charges_act, item_sellprice_brutto, item_reboprod_amt, item_reboprod_unitkg)
                    VALUES
                    (:stk_id, :itid, 0, 1, 'item', :sthid, 0, 0, 0, 0, 0, 0, 1, :unitkg)
                ")->execute([
                    ':stk_id' => $stkIdOut,
                    ':itid' => $selItemId,
                    ':sthid' => $stkFixedSthId,
                    ':unitkg' => $useKgAmt,
                ]);

                // Descontar la bobina madre virgen TEL en Bodega 3000 (1 bobina), con kardex
                try {
                    $this->applyStockChangeLine($stkIdOut);
                } catch (Throwable $e) {
                    error_log('Kardex del corte (documento ' . $stkIdOut . '): ' . $e->getMessage());
                }
            }

            // 2. Dar de alta los rollos de subproductos cortados
            $prodItems = [];
            $totalScrappedManillasAmt = 0;
            $totalScrappedManillasKg = 0.0;
            $scrappedManillasDetails = [];
            $totalCutMermaKg = 0.0;
            $cutMermaDetails = [];
            $generalMermaKg = (float)str_replace(',', '.', (string)($postData['itemmermakg'] ?? 0));
            $netProductsKg = max(0.0, $useKgAmt - $generalMermaKg);
            $rollWidth = (float)($itemSel['item_reg_width'] ?? 100);
            if ($rollWidth <= 0) $rollWidth = 100.0;
            $baseRegKg = (float)($itemSel['item_reg_kg'] ?? 1);
            if ($baseRegKg <= 0) $baseRegKg = 1.0;
            $sobranteKg = (float)str_replace(',', '.', (string)($postData['sobranteamt_1'] ?? 0));
            $calcLargo = (float)($itemSel['item_reg_length'] ?? 0)
                - (($generalMermaKg * (float)($itemSel['item_reg_length'] ?? 0)) / $baseRegKg)
                - (($sobranteKg * (float)($itemSel['item_reg_length'] ?? 0)) / $baseRegKg);

            // Recolectar todos los cortes definidos en el formulario
            $cutIndices = [];
            foreach ($postData as $reqKey => $val) {
                if (str_starts_with($reqKey, 'reboamt_')) {
                    $cutIndices[] = (int)substr($reqKey, strlen('reboamt_'));
                } elseif (str_starts_with($reqKey, 'stockplustotalkg_')) {
                    $cutIndices[] = (int)substr($reqKey, strlen('stockplustotalkg_'));
                }
            }
            $cutIndices = array_unique($cutIndices);
            sort($cutIndices);

            $sumCutWidth = 0.0;
            foreach ($cutIndices as $cIdx) {
                $cAmt = max(1, (int)($postData["reboamt_{$cIdx}"] ?? 1));
                $cDims = round((float)str_replace(',', '.', (string)($postData["rollo_dims_{$cIdx}"] ?? 0)), 2);
                $sumCutWidth += ($cAmt * $cDims);
            }

            foreach ($cutIndices as $idx) {
                $destItemId = (int)($postData["stockplusitemid_{$idx}"] ?? 0);
                $destDims = round((float)str_replace(',', '.', (string)($postData["rollo_dims_{$idx}"] ?? 0)), 2);
                $reboAmt = max(1, (int)($postData["reboamt_{$idx}"] ?? 1));
                $destLargo = (float)($postData["stockpluslargo_{$idx}"] ?? $calcLargo);
                $destinoLabel = trim((string)($postData["rebo_destino_{$idx}"] ?? ''));

                $isMermaDest = ($destinoLabel === 'merma' || str_contains(mb_strtolower($destinoLabel), 'merma') || str_contains(mb_strtolower($destinoLabel), 'refile'));
                if ($isMermaDest) {
                    $destType = 'merma';
                } else {
                    $destType = $this->isReboHandleDestination($destinoLabel, $destDims, $agenda) ? 'handle' : 'bag';
                }

                if ($destDims <= 0) {
                    continue;
                }

                $lineWidth = $reboAmt * $destDims;
                $productProportion = $rollWidth > 0 ? ($lineWidth / $rollWidth) : ($lineWidth / max(1, $sumCutWidth));
                $destUseKg = round($netProductsKg * $productProportion, 2);
                if ($destUseKg <= 0 && $netProductsKg > 0) {
                    $destUseKg = round($netProductsKg / max(1, count($cutIndices)), 2);
                }

                // Si la fila está marcada como 100% Merma / Refile, los kilos van directamente a merma
                if ($destType === 'merma') {
                    $totalCutMermaKg += $destUseKg;
                    $cutMermaDetails[] = "{$reboAmt}x Refile/Merma {$destDims}cm ({$destUseKg} Kg)";
                    $prodItems[] = [
                        'dest_itemid' => 0,
                        'dest_units' => 0,
                        'item_reboprod_amt' => 0,
                        'total_cut_amt' => $reboAmt,
                        'scrapped_amt' => $reboAmt,
                        'scrapped_kg' => $destUseKg,
                        'usable_kg' => 0.0,
                        'item_reboprod_unitkg' => 0.0,
                        'dest_dims' => $destDims,
                        'dest_largo' => $destLargo,
                        'destino_label' => 'Merma / Refile',
                        'dest_type' => 'merma',
                    ];
                    continue;
                }

                // Manillas a merma seleccionadas por el operador (por bobina sucia o defecto)
                $manillasMermaAmt = 0;
                if ($destType === 'handle') {
                    $manillasMermaAmt = max(0, min($reboAmt, (int)($postData["manillas_merma_amt_{$idx}"] ?? 0)));
                    if ($manillasMermaAmt === 0 && !empty($postData['total_manillas_merma_amt'])) {
                        $manillasMermaAmt = max(0, min($reboAmt, (int)$postData['total_manillas_merma_amt']));
                    }
                }

                $unitKg = $reboAmt > 0 ? round($destUseKg / $reboAmt, 2) : 0.0;
                $usableAmt = max(0, $reboAmt - $manillasMermaAmt);
                $scrappedKg = round($manillasMermaAmt * $unitKg, 2);
                $usableKg = round($usableAmt * $unitKg, 2);

                if ($manillasMermaAmt > 0) {
                    $totalScrappedManillasAmt += $manillasMermaAmt;
                    $totalScrappedManillasKg += $scrappedKg;
                    $scrappedManillasDetails[] = "{$manillasMermaAmt}x Manillas {$destDims}cm ({$scrappedKg} Kg)";
                }

                // Resolver color y gramaje exactos para la bobina hija / manilla
                $cutColorId = (int)($itemSel['colorid'] ?? 0);
                $cutColorDesc = trim((string)($itemSel['colordesc'] ?? ''));
                if ($cutColorId <= 0 && !empty($itemSel['id'])) {
                    $stc = $this->erpPdo->prepare("SELECT val_id FROM tran_comments_item_vals WHERE item_id = :id AND com_id = 17 LIMIT 1");
                    $stc->execute([':id' => (int)$itemSel['id']]);
                    $cutColorId = (int)($stc->fetchColumn() ?: 0);
                }
                if ($cutColorId <= 0 && !empty($sourceTraceRoll['color'])) {
                    $stcn = $this->erpPdo->prepare("SELECT id, add_name FROM tran_comments_vals WHERE add_com_id = 17 AND (add_name = :c OR add_name LIKE :cp) LIMIT 1");
                    $stcn->execute([':c' => trim((string)$sourceTraceRoll['color']), ':cp' => '%' . trim((string)$sourceTraceRoll['color']) . '%']);
                    $crow = $stcn->fetch(PDO::FETCH_ASSOC);
                    if ($crow) {
                        $cutColorId = (int)$crow['id'];
                        $cutColorDesc = (string)$crow['add_name'];
                    }
                }
                if ($cutColorId <= 0) {
                    if ($destType === 'handle' && !empty($agenda['fab_mat_manilla_color'])) {
                        $cutColorId = (int)$agenda['fab_mat_manilla_color'];
                    } elseif (!empty($agenda['fab_mat_fabric_color'])) {
                        $cutColorId = (int)$agenda['fab_mat_fabric_color'];
                    }
                }
                if ($cutColorId > 0 && $cutColorDesc === '') {
                    $stcname = $this->erpPdo->prepare("SELECT add_name FROM tran_comments_vals WHERE id = :id LIMIT 1");
                    $stcname->execute([':id' => $cutColorId]);
                    $cutColorDesc = (string)($stcname->fetchColumn() ?: '');
                }

                $cutGsm = (float)($sourceTraceRoll['microns'] ?? $itemSel['item_reg_gsm'] ?? 80);

                // Validar si el destItemId enviado desde el formulario coincide con el color requerido
                if ($destItemId > 0 && $cutColorId > 0) {
                    $stValid = $this->erpPdo->prepare("
                        SELECT t1.id FROM item t1
                        INNER JOIN tran_comments_item_vals t4 ON t4.item_id = t1.id AND t4.com_id = 17
                        WHERE t1.id = :id AND t4.val_id = :cid
                    ");
                    $stValid->execute([':id' => $destItemId, ':cid' => $cutColorId]);
                    if (!$stValid->fetchColumn()) {
                        // El ítem provisto no corresponde al color de la bobina; se debe buscar o crear el correcto
                        $destItemId = 0;
                    }
                }

                // Si no tiene destItemId válido, buscar si existe un ítem con ese ancho, gramaje y color exactos, o crearlo
                if ($destItemId <= 0) {
                    $destItemId = $this->findOrCreateCutItem(
                        $itemSel,
                        $destDims,
                        $destLargo,
                        $userId,
                        $destType,
                        $cutColorId,
                        $cutColorDesc,
                        $cutGsm
                    );
                }
                if ($destItemId <= 0) {
                    throw new RuntimeException("No se pudo resolver el código de producto para el corte de {$destDims}cm ({$cutColorDesc}).");
                }

                // Unidades producidas: exactamente la cantidad de rollos útiles obtenidos ($usableAmt).
                // Cada rollo cortado equivale a 1 unidad física en el inventario.
                $destUnits = (float)$usableAmt;

                $prodItems[] = [
                    'dest_itemid' => $destItemId,
                    'dest_units' => $destUnits,
                    'item_reboprod_amt' => $usableAmt,
                    'total_cut_amt' => $reboAmt,
                    'scrapped_amt' => $manillasMermaAmt,
                    'scrapped_kg' => $scrappedKg,
                    'usable_kg' => $usableKg,
                    'item_reboprod_unitkg' => $unitKg,
                    'dest_dims' => $destDims,
                    'dest_largo' => $destLargo,
                    'destino_label' => $destinoLabel,
                    'dest_type' => $destType,
                ];
            }

            // Insertar cabecera e ítems de entrada (stk_negative = 0, issue 20 = Generado por producción)
            // Solo dar de alta los ítems que tengan rollos útiles (usableAmt > 0)
            $stockableItems = array_filter($prodItems, fn($p) => ($p['item_reboprod_amt'] ?? 0) > 0 && ($p['dest_units'] ?? 0) > 0);
            if (!empty($stockableItems)) {
                $stkNumIn = $this->generateNextStockChangeNumber($companyId);
                $stmtSCIn = $this->erpPdo->prepare("
                    INSERT INTO stockchanges
                    (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate, stk_negative,
                     stk_status, stk_crtdat, stk_crtusr, stk_fixedsthid, sth_fromrebo_agid, sth_fromrebo_agid_grphash,
                     sth_fromprodotid, sth_order_id, sth_order_num, sth_planta_id, sth_assign_wrkid, sth_assign_equipoid)
                    VALUES
                    (:num, 'Generado por producción', 20, :compid, :shopid, :bdate, 0, 2, :crtdat, :crtusr, 16, :agid, :grphash,
                     :ot_id, :order_id, :order_num, :planta_id, :worker_id, :equipo_id)
                ");
                $stmtSCIn->execute([
                    ':num' => $stkNumIn,
                    ':compid' => $companyId > 0 ? $companyId : 20010,
                    ':shopid' => $reqShopId,
                    ':bdate' => $now,
                    ':crtdat' => $now,
                    ':crtusr' => $userId,
                    ':agid' => $agId,
                    ':grphash' => $grpHash,
                    ':ot_id' => $workerOtId,
                    ':order_id' => $orderId,
                    ':order_num' => $orderNum,
                    ':planta_id' => $plantaId,
                    ':worker_id' => $userId,
                    ':equipo_id' => $equipoId,
                ]);
                $stkIdIn = (int)$this->erpPdo->lastInsertId();

                $pos = 0;
                foreach ($stockableItems as $pRow) {
                    $this->erpPdo->prepare("
                        INSERT INTO stockchanges_items
                        (stk_id, item_id, item_pos, item_amount, item_type, item_st_id,
                         item_costprice_brutto, item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes,
                         item_charges_act, item_sellprice_brutto, item_reboprod_amt, item_reboprod_unitkg)
                        VALUES
                        (:stk_id, :itid, :pos, :amt, 'item', 16, 0, 0, 0, 0, 0, 0, :reboamt, :unitkg)
                    ")->execute([
                        ':stk_id' => $stkIdIn,
                        ':itid' => $pRow['dest_itemid'],
                        ':pos' => $pos++,
                        ':amt' => $pRow['dest_units'],
                        ':reboamt' => $pRow['item_reboprod_amt'],
                        ':unitkg' => $pRow['item_reboprod_unitkg'],
                    ]);

                    // Incrementar el subproducto en Bodega 3000 (st_id = 16), con kardex
                    try {
                        $this->applyStockChangeLine($stkIdIn, $pos - 1);
                    } catch (Throwable $e) {
                        error_log('Kardex del corte (documento ' . $stkIdIn . '): ' . $e->getMessage());
                    }
                }
            }

            // Registrar evento único y claro en la OT para seguimiento y trazabilidad (sin duplicidad de materiales)
            $totalProcessMermaKg = round($generalMermaKg + $totalScrappedManillasKg + $totalCutMermaKg, 2);
            if ($workerOtId > 0) {
                try {
                    $totalCuts = count($prodItems);

                    // Resumen detallado y profesional del proceso de corte/rebobinado
                    $cutsSummary = [];
                    $totalProducedRolls = 0;
                    foreach ($prodItems as $p) {
                        $amt = (int)($p['item_reboprod_amt'] ?? 1);
                        $totalProducedRolls += $amt;
                        $dim = (float)($p['dest_dims'] ?? 0);
                        $destLbl = !empty($p['destino_label']) ? trim((string)$p['destino_label']) : ($p['dest_type'] === 'handle' ? 'Manilla' : 'Bolsa');
                        $cutsSummary[] = "{$amt}x {$dim}cm ({$destLbl})";
                    }
                    $cutsText = !empty($cutsSummary) ? implode(', ', $cutsSummary) : "{$totalCuts} rollos";

                    $taskName = $isRebobinado ? 'Rebobinado' : 'Corte';
                    $originText = $isVirginTel 
                        ? "Bobina virgen TEL: {$itemSel['item_number_prod']} ({$useKgAmt} Kg consumidos)"
                        : "Bobina procesada previa: {$itemSel['item_number_prod']} ({$useKgAmt} Kg procesados)";

                    $prodComments = "{$taskName} finalizado: {$originText} -> Obtenidos {$totalProducedRolls} rollos útiles [{$cutsText}]. Merma total: {$totalProcessMermaKg} Kg";
                    if ($totalScrappedManillasAmt > 0) {
                        $prodComments .= " (incluye {$totalScrappedManillasAmt} manillas descartadas a merma: {$totalScrappedManillasKg} Kg)";
                    }
                    if ($totalCutMermaKg > 0) {
                        $prodComments .= " (incluye " . implode(', ', $cutMermaDetails) . ")";
                    }

                    // Un solo evento de producción ('prod') con estado finalizado y detalle completo
                    $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot_events
                        (evt_prod_worker_otid, evt_type, evt_amount, prod_bobina_kg, evt_amount2, evt_crtdat, evt_enddat, evt_status, evt_comments)
                        VALUES
                        (:ot_id, 'prod', :amt, :kg, :merma, :now, :now, 2, :comments)
                    ")->execute([
                        ':ot_id' => $workerOtId,
                        ':amt' => $totalProducedRolls > 0 ? $totalProducedRolls : 1,
                        ':kg' => $useKgAmt,
                        ':merma' => $totalProcessMermaKg,
                        ':now' => $now,
                        ':comments' => $prodComments,
                    ]);
                } catch (Throwable) {}
            }

            // Actualizar estado de la bobina en TRZ (Trazabilidad) y crear bobinas hijas
            $rollIdPost = (int)($postData['scanned_roll_id'] ?? $postData['roll_id'] ?? 0);
            $rollCodePost = trim((string)($postData['scanned_roll_code'] ?? $postData['roll_code'] ?? ($itemSel['item_number_prod'] ?? '')));
            $createdChildRolls = [];
            $createdBagRolls = [];
            $createdHandleRolls = [];
            $createdHandlePallet = null;
            if ($this->trzPdo !== null) {
                try {
                    if ($rollIdPost <= 0 && $rollCodePost !== '') {
                        $stmtFind = $this->trzPdo->prepare("SELECT id FROM rolls WHERE roll_code = :c LIMIT 1");
                        $stmtFind->execute([':c' => $rollCodePost]);
                        $rollIdPost = (int)($stmtFind->fetchColumn() ?: 0);
                    }

                    if ($rollIdPost > 0) {
                        $this->trzPdo->prepare("
                            UPDATE rolls
                            SET status = 'CONSUMED', weight_kg = GREATEST(0, weight_kg - :used)
                            WHERE id = :id
                        ")->execute([':used' => $useKgAmt, ':id' => $rollIdPost]);
                    } elseif ($rollCodePost !== '') {
                        $this->trzPdo->prepare("
                            UPDATE rolls
                            SET status = 'CONSUMED', weight_kg = GREATEST(0, weight_kg - :used)
                            WHERE roll_code = :c
                        ")->execute([':used' => $useKgAmt, ':c' => $rollCodePost]);
                    }

                    // Obtener target SKU del producto de la OT para asociarlo a las manillas
                    $targetProductSku = '';
                    $targetProductTitle = '';
                    if ($orderId > 0) {
                        try {
                            $stmtTgt = $this->erpPdo->prepare("
                                SELECT i.item_number_prod, i.item_title
                                FROM orders_items oi
                                JOIN item i ON oi.item_id = i.id
                                WHERE oi.req_id = :ord_id
                                LIMIT 1
                            ");
                            $stmtTgt->execute([':ord_id' => $orderId]);
                            $tgtRow = $stmtTgt->fetch(PDO::FETCH_ASSOC);
                            if ($tgtRow) {
                                $targetProductSku = trim((string)($tgtRow['item_number_prod'] ?? ''));
                                $targetProductTitle = trim((string)($tgtRow['item_title'] ?? ''));
                            }
                        } catch (Throwable) {}
                    }
                    if ($targetProductSku === '' && !empty($agenda['item_number_prod'])) {
                        $targetProductSku = trim((string)$agenda['item_number_prod']);
                    }

                    // Generar bobinas hijas cortadas (CUT) en TRZ
                    foreach ($prodItems as $pRow) {
                        $usableAmt = (int)($pRow['item_reboprod_amt'] ?? 0);
                        $scrappedAmt = (int)($pRow['scrapped_amt'] ?? 0);
                        $unitKg = (float)($pRow['item_reboprod_unitkg'] ?? 0);
                        $widthMm = (int)round(($pRow['dest_dims'] ?? 0) * 10);
                        $meters = (float)($pRow['dest_largo'] ?? 0);
                        $destItemId = (int)($pRow['dest_itemid'] ?? 0);
                        $destType = (string)($pRow['dest_type'] ?? 'bag');
                        $destinoLabel = trim((string)($pRow['destino_label'] ?? ''));

                        if ($destType === 'merma' || ($usableAmt <= 0 && $scrappedAmt <= 0)) {
                            continue;
                        }

                        $skuSuffix = ($destType === 'handle' && $targetProductSku !== '') ? "-{$targetProductSku}" : '';
                        $skuCode = ($destType === 'handle' ? 'CUT-MAN-' : 'CUT-BOL-') . $destItemId . $skuSuffix;
                        $cutBeforePrinting = $isVirginTel && $destType === 'bag';
                        $childStage = $cutBeforePrinting ? 'RAW' : 'CUT';
                        $skuDesc = $destinoLabel;
                        if ($cutBeforePrinting) {
                            $fabricStmt = $this->erpPdo->prepare('SELECT item_number_prod, item_title FROM item WHERE id = :id');
                            $fabricStmt->execute([':id' => $destItemId]);
                            $fabricItem = $fabricStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                            $skuCode = trim((string)($fabricItem['item_number_prod'] ?? ''));
                            if (!str_starts_with(strtoupper($skuCode), 'TEL')) {
                                throw new RuntimeException('El rollo sin imprimir debe conservar el SKU TEL de su ancho de corte.');
                            }
                            $skuDesc = "Tela virgen cortada {$pRow['dest_dims']}cm - Sin imprimir";
                        }
                        $skuCode = substr($skuCode, 0, 60);
                        $skuId = 1;
                        try {
                            $stmtSku = $this->trzPdo->prepare("SELECT id FROM skus WHERE code = :c LIMIT 1");
                            $stmtSku->execute([':c' => $skuCode]);
                            $foundSku = $stmtSku->fetchColumn();
                            if ($foundSku) {
                                $skuId = (int)$foundSku;
                            } else {
                                $skuDesc = $cutBeforePrinting ? $skuDesc : ($destType === 'handle'
                                    ? ("Manilla Cortada {$pRow['dest_dims']}cm" . ($targetProductSku !== '' ? " - Cargada a SKU: {$targetProductSku} (OT #{$orderNum})" : ''))
                                    : ("Cuerpo de Bolsa Cortada {$pRow['dest_dims']}cm" . ($targetProductSku !== '' ? " - Para OT #{$orderNum}" : '')));
                                $stmtInsSku = $this->trzPdo->prepare("INSERT INTO skus (code, description, is_active) VALUES (:c, :d, 1)");
                                $stmtInsSku->execute([
                                    ':c' => $skuCode,
                                    ':d' => $skuDesc,
                                ]);
                                $skuId = (int)$this->trzPdo->lastInsertId();
                            }
                        } catch (Throwable) {}

                        $wh3000Trz = $this->findWarehouseIdByCode(3000) ?: 58;

                        // 1. Rollos útiles recibidos en bodega 3000
                        for ($k = 1; $k <= $usableAmt; $k++) {
                            try {
                                $stmtMax = $this->trzPdo->query("SELECT MAX(id) FROM rolls");
                                $nextNum = ((int)$stmtMax->fetchColumn()) + 1;
                                $childRollCode = $destType === 'handle'
                                    ? sprintf('ROLL-MAN-%05d', $nextNum)
                                    : sprintf('ROLL-BOL-%05d', $nextNum);

                                $insRoll = $this->trzPdo->prepare("
                                    INSERT INTO rolls
                                    (roll_code, sku_id, warehouse_id, weight_kg, received_qty, width_mm, meters, status, parent_roll_id, source_work_order_id, process_stage, microns, color)
                                    VALUES
                                    (:code, :sku, :wh_id, :weight, 1.000, :width, :meters, 'RECEIVED', :parent, :wo_id, :stage, :grams, :color)
                                ");
                                $insRoll->execute([
                                    ':code' => $childRollCode,
                                    ':sku' => $skuId,
                                    ':wh_id' => $wh3000Trz,
                                    ':weight' => number_format($unitKg, 3, '.', ''),
                                    ':width' => $widthMm > 0 ? $widthMm : null,
                                    ':meters' => $meters > 0 ? $meters : null,
                                    ':parent' => $rollIdPost > 0 ? $rollIdPost : null,
                                    ':wo_id' => $workerOtId > 0 ? $workerOtId : null,
                                    ':stage' => $childStage,
                                    ':grams' => (float)($sourceTraceRoll['microns'] ?? $itemSel['item_reg_gsm'] ?? 0) ?: null,
                                    ':color' => $sourceTraceRoll['color'] ?? $itemSel['colordesc'] ?? null,
                                ]);
                                $childRollId = (int)$this->trzPdo->lastInsertId();

                                try {
                                    $stmtWhName = $this->trzPdo->prepare("SELECT name, erp_storehouse_id FROM warehouses WHERE id = :id LIMIT 1");
                                    $stmtWhName->execute([':id' => $wh3000Trz]);
                                    $whRow = $stmtWhName->fetch(PDO::FETCH_ASSOC) ?: [];
                                    $whNameStr = (string)($whRow['name'] ?? '') ?: '3000 INSUMOS EN PRODUCCION';
                                    // El código usa el id de bodega del ERP; la bobina (rolls) sigue con el id local.
                                    $whEntryId = (int)($whRow['erp_storehouse_id'] ?? 0) ?: $wh3000Trz;

                                    $stmtInsTwe = $this->trzPdo->prepare("
                                        INSERT INTO traceability_warehouse_entries
                                        (traceability_code, entity_type, entity_id, warehouse_id, warehouse_name,
                                         item_id, item_code, item_description, quantity, unit_name,
                                         weight_kg, width_mm, meters, order_ref, operator_name, comments, status, created_at)
                                        VALUES
                                        (:code, 'ROLL', :eid, :wh_id, :wh_name,
                                         :item_id, :item_code, :item_desc, 1.000, 'UNID',
                                         :weight, :width, :meters, :order_ref, :op_name, :comments, 'IN_WAREHOUSE', NOW())
                                    ");
                                    $stmtInsTwe->execute([
                                        ':code' => $childRollCode,
                                        ':eid' => $childRollId,
                                        ':wh_id' => $whEntryId,
                                        ':wh_name' => $whNameStr,
                                        ':item_id' => $destItemId > 0 ? $destItemId : null,
                                        ':item_code' => $skuCode,
                                        ':item_desc' => $skuDesc ?? $destinoLabel,
                                        ':weight' => $unitKg,
                                        ':width' => $widthMm > 0 ? $widthMm : null,
                                        ':meters' => $meters > 0 ? $meters : null,
                                        ':order_ref' => $orderNum !== '' ? ('OT #' . $orderNum) : null,
                                        ':op_name' => $operatorName !== '' ? $operatorName : 'Operador Rebobinadora',
                                        ':comments' => 'Corte/Rebobinado desde ' . ($rollCodePost ?: ('Bobina #' . $rollIdPost)),
                                    ]);
                                } catch (Throwable) {}

                                $stmtEvt = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
                                $stmtEvt->execute([
                                    ':type' => 'CHILD_ROLL_CREATED',
                                    ':payload' => json_encode([
                                        'child_roll_id' => $childRollId,
                                        'child_roll_code' => $childRollCode,
                                        'parent_roll_id' => $rollIdPost,
                                        'warehouse_id' => $wh3000Trz,
                                        'weight_kg' => $unitKg,
                                        'width_mm' => $widthMm,
                                        'meters' => $meters,
                                        'dest_type' => $destType,
                                        'cut_before_printing' => $cutBeforePrinting,
                                        'process_stage' => $childStage,
                                        'target_product_sku' => $targetProductSku,
                                        'target_product_title' => $targetProductTitle,
                                        'order_number' => $orderNum,
                                        'source_work_order_id' => $workerOtId,
                                        'operator_id' => $userId,
                                        'timestamp' => $now,
                                    ], JSON_UNESCAPED_UNICODE),
                                ]);

                                $childRollData = [
                                    'id' => $childRollId,
                                    'roll_code' => $childRollCode,
                                    'weight_kg' => $unitKg,
                                    'width_mm' => $widthMm,
                                    'meters' => $meters,
                                    'dest_type' => $destType,
                                    'destino_label' => $destinoLabel,
                                    'target_product_sku' => $targetProductSku,
                                    'order_number' => $orderNum,
                                ];
                                $createdChildRolls[] = $childRollData;
                                if ($destType === 'handle') {
                                    $createdHandleRolls[] = $childRollData;
                                } else {
                                    $createdBagRolls[] = $childRollData;
                                }
                            } catch (Throwable) {}
                        }

                        // 2. Trazabilidad de manillas descartadas a merma (por bobina sucia/defecto)
                        for ($m = 1; $m <= $scrappedAmt; $m++) {
                            try {
                                $stmtMaxM = $this->trzPdo->query("SELECT MAX(id) FROM rolls");
                                $nextNumM = ((int)$stmtMaxM->fetchColumn()) + 1;
                                $mermaRollCode = sprintf('ROLL-MAN-MERMA-%05d', $nextNumM);

                                $insRollM = $this->trzPdo->prepare("
                                    INSERT INTO rolls
                                    (roll_code, sku_id, warehouse_id, weight_kg, received_qty, width_mm, meters, status, parent_roll_id, source_work_order_id, process_stage)
                                    VALUES
                                    (:code, :sku, :wh_id, :weight, 0.000, :width, :meters, 'CONSUMED', :parent, :wo_id, 'CUT')
                                ");
                                $insRollM->execute([
                                    ':code' => $mermaRollCode,
                                    ':sku' => $skuId,
                                    ':wh_id' => $wh3000Trz,
                                    ':weight' => number_format($unitKg, 3, '.', ''),
                                    ':width' => $widthMm > 0 ? $widthMm : null,
                                    ':meters' => $meters > 0 ? $meters : null,
                                    ':parent' => $rollIdPost > 0 ? $rollIdPost : null,
                                    ':wo_id' => $workerOtId > 0 ? $workerOtId : null,
                                ]);
                                $mermaRollId = (int)$this->trzPdo->lastInsertId();

                                $stmtEvtScrap = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
                                $stmtEvtScrap->execute([
                                    ':type' => 'HANDLE_ROLL_SCRAPPED_MERMA',
                                    ':payload' => json_encode([
                                        'roll_id' => $mermaRollId,
                                        'roll_code' => $mermaRollCode,
                                        'parent_roll_id' => $rollIdPost,
                                        'dest_dims' => $pRow['dest_dims'],
                                        'weight_kg' => $unitKg,
                                        'reason' => 'BOBINA_SUCIA_MERMA_CORTE',
                                        'source_work_order_id' => $workerOtId,
                                        'operator_id' => $userId,
                                        'timestamp' => $now,
                                    ], JSON_UNESCAPED_UNICODE),
                                ]);
                            } catch (Throwable) {}
                        }
                    }

                    // Las manillas se etiquetan y gestionan bobina por bobina (sin consolidar en pallet)
                    // para un control directo y preciso de cada rollo en Bodega 3000.
                } catch (Throwable) {}
            }
        }

        return [
            'ok' => true,
            'rebo_type' => 'Corte',
            'consumed' => true,
            'created_rolls' => $createdChildRolls,
            'created_bag_rolls' => $createdBagRolls,
            'created_handle_rolls' => $createdHandleRolls,
            'handle_pallet' => $createdHandlePallet,
            'manillas_merma_amt' => $totalScrappedManillasAmt,
            'manillas_merma_kg' => $totalScrappedManillasKg,
            'total_merma_kg' => $totalProcessMermaKg ?? $generalMermaKg,
        ];
    }

    private function isReboHandleDestination(string $destinoLabel, float $destDims, array $agenda): bool
    {
        $dest = mb_strtolower(trim($destinoLabel));
        if ($dest !== '') {
            if ($dest === 'bolsa' || $dest === '3000' || str_contains($dest, 'bolsa')) {
                return false;
            }
            if ($dest === 'manilla' || $dest === 'rebo_handle_pallet' || str_contains($dest, 'manilla')) {
                return true;
            }
        }

        $opt = trim((string)($agenda['req_rebo_rolloscc_opt'] ?? ''));
        if ($opt === '100cm_manillas') {
            return true;
        }

        $anchoManillas = (float)($agenda['req_infoaddprd_ancho_manillas'] ?? 0);
        if ($anchoManillas > 0 && $destDims > 0 && $destDims <= ($anchoManillas + 1.0)) {
            return true;
        }

        return $destDims > 0 && $destDims <= 15.0;
    }

    /**
     * Descarta una bobina generada a merma (por sucia, falla de corte o defecto).
     * Marca el roll en TRZ como CONSUMED / merma, descuenta el inventario de Bodega 3000 si estaba ingresado,
     * y genera el evento de merma en la OT del ERP y la bitácora TRZ.
     */
    public function scrapRollToMerma(int $rollId, int $userId, int $workOrderId, string $reason = 'BOBINA_SUCIA'): array
    {
        if ($rollId <= 0) {
            return ['ok' => false, 'error' => 'ID de bobina inválido'];
        }

        $stmt = $this->trzPdo->prepare("SELECT r.*, s.code AS sku_code, s.description AS sku_desc FROM rolls r LEFT JOIN skus s ON s.id = r.sku_id WHERE r.id = :id LIMIT 1");
        $stmt->execute([':id' => $rollId]);
        $roll = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$roll) {
            return ['ok' => false, 'error' => 'Bobina no encontrada'];
        }

        if ($roll['status'] === 'CONSUMED') {
            return ['ok' => true, 'already_scrapped' => true, 'roll_code' => $roll['roll_code'], 'weight_kg' => (float)$roll['weight_kg']];
        }

        $weightKg = (float)($roll['weight_kg'] ?? 0);
        $rollCode = (string)($roll['roll_code'] ?? ('#' . $rollId));
        $now = date('Y-m-d H:i:s');

        // 1. Actualizar estado en TRZ
        $upd = $this->trzPdo->prepare("UPDATE rolls SET status = 'CONSUMED' WHERE id = :id");
        $upd->execute([':id' => $rollId]);

        // 2. Si la bobina seguía en stock de la Bodega 3000 (sin montar), sale con documento y kardex. Una bobina montada
        //    ya se descontó al montarla. Antes buscaba en una tabla inexistente (items) y restaba kilos en vez de unidades.
        if ($this->erpPdo && !empty($roll['sku_code']) && (string)$roll['status'] === 'RECEIVED' && empty($roll['current_work_order_id'])) {
            try {
                $stItem = $this->erpPdo->prepare("SELECT id FROM item WHERE item_number_prod = :code LIMIT 1");
                $stItem->execute([':code' => $roll['sku_code']]);
                $erpItemId = (int)$stItem->fetchColumn();
                $units = (float)($roll['received_qty'] ?? 0) > 0 ? (float)$roll['received_qty'] : 1.0;
                if ($erpItemId > 0) {
                    (new StockLedgerService($this->erpPdo))->post([
                        'key' => 'scrap.roll.' . $rollId, 'item_id' => $erpItemId, 'st_id' => 16, 'delta' => -$units, 'allow_negative' => true,
                        'user_id' => $userId, 'text' => 'Bobina ' . $rollCode . ' descartada como merma (' . $reason . ').',
                    ]);
                }
            } catch (Throwable $e) {
                error_log('Descarte de bobina ' . $rollCode . ' en el ERP: ' . $e->getMessage());
            }
        }

        // 3. Registrar evento en TRZ
        try {
            $stmtEvt = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
            $stmtEvt->execute([
                ':type' => 'ROLL_SCRAPPED_MERMA',
                ':payload' => json_encode([
                    'roll_id' => $rollId,
                    'roll_code' => $rollCode,
                    'weight_kg' => $weightKg,
                    'reason' => $reason,
                    'source_work_order_id' => $workOrderId,
                    'operator_id' => $userId,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable) {}

        // 4. Registrar evento en ERP (prod_worker_ot_events)
        if ($this->erpPdo && $workOrderId > 0) {
            try {
                $reasonText = ($reason === 'BOBINA_SUCIA' || str_contains($reason, 'sucia')) ? 'Bobina sucia / descarte' : 'Merma de corte / defecto';
                $comment = "Merma de corte: Se descartó bobina {$rollCode} (" . number_format($weightKg, 2, ',', '.') . " Kg) por {$reasonText}. No se emitió etiqueta.";
                // Columnas reales de la bitácora (antes usaba columnas inexistentes y el evento nunca se guardaba).
                $stEvt = $this->erpPdo->prepare("INSERT INTO prod_worker_ot_events (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                    VALUES (:ot_id, 0, :kg, :dt, :dt2, 2, 'materiales', :comment)");
                $stEvt->execute([
                    ':ot_id' => $workOrderId,
                    ':kg' => $weightKg,
                    ':dt' => time(),
                    ':dt2' => time(),
                    ':comment' => $comment,
                ]);
            } catch (Throwable) {}
        }

        return [
            'ok' => true,
            'roll_id' => $rollId,
            'roll_code' => $rollCode,
            'weight_kg' => $weightKg,
            'reason' => $reason,
        ];
    }

    /**
     * Registra el consumo de material (bobina/insumo) en el ERP oficial (stockchanges y stockchanges_items)
     * para que quede reflejado en el informe oficial de producción "Consumo de Materiales" (libs/modules/stats/prod/consumo.php).
     */
    public function recordStockChangeForRollConsumption(
        int $workerOtId,
        string $rollCode,
        string $skuCode,
        float $consumedKg,
        int $userId = 0,
        string $extraAnnotation = '',
        int $fallbackAgId = 0,
        float $consumedUnits = 0.0,
        bool $deductInventory = false
    ): ?int {
        if ($consumedKg <= 0) {
            return null;
        }

        try {
            $now = time();
            $agId = $fallbackAgId;
            $equipoId = 0;
            $orderId = 0;
            $orderNum = '';
            $companyId = 20010;
            $shopId = 30010;
            $stkFixedSthId = 16; // Bodega 3000 Insumos / Materias primas

            // 1. Obtener datos de OT y Agenda
            if ($workerOtId > 0) {
                $stOt = $this->erpPdo->prepare("
                    SELECT ot.id as ot_id, ot.wok_ag_id, ot.wok_init_id,
                           ag.ag_reqid, ag.ag_equipo_id,
                           pwi.win_equipoid,
                           ord.id as order_id, ord.req_number as order_num,
                           ord.req_company_id, ord.req_shop_id
                    FROM prod_worker_ot ot
                    LEFT JOIN prod_agenda ag ON ag.id = ot.wok_ag_id
                    LEFT JOIN prod_worker_init pwi ON pwi.id = ot.wok_init_id
                    LEFT JOIN orders ord ON ord.id = ag.ag_reqid
                    WHERE ot.id = :otid LIMIT 1
                ");
                $stOt->execute([':otid' => $workerOtId]);
                $otRow = $stOt->fetch(PDO::FETCH_ASSOC);
                if ($otRow) {
                    $agId = (int)($otRow['wok_ag_id'] ?? $agId);
                    $equipoId = (int)($otRow['win_equipoid'] ?: ($otRow['ag_equipo_id'] ?? 0));
                    $orderId = (int)($otRow['order_id'] ?? 0);
                    $orderNum = (string)($otRow['order_num'] ?? '');
                    if (!empty($otRow['req_company_id'])) $companyId = (int)$otRow['req_company_id'];
                    if (!empty($otRow['req_shop_id'])) $shopId = (int)$otRow['req_shop_id'];
                }
            }

            if ($agId > 0 && ($orderId <= 0 || $equipoId <= 0)) {
                $stAg = $this->erpPdo->prepare("
                    SELECT ag.ag_reqid, ag.ag_equipo_id, ord.id as order_id, ord.req_number as order_num,
                           ord.req_company_id, ord.req_shop_id
                    FROM prod_agenda ag
                    LEFT JOIN orders ord ON ord.id = ag.ag_reqid
                    WHERE ag.id = :agid LIMIT 1
                ");
                $stAg->execute([':agid' => $agId]);
                $agRow = $stAg->fetch(PDO::FETCH_ASSOC);
                if ($agRow) {
                    if ($equipoId <= 0 && !empty($agRow['ag_equipo_id'])) $equipoId = (int)$agRow['ag_equipo_id'];
                    if ($orderId <= 0 && !empty($agRow['order_id'])) $orderId = (int)$agRow['order_id'];
                    if ($orderNum === '' && !empty($agRow['order_num'])) $orderNum = (string)$agRow['order_num'];
                    if (!empty($agRow['req_company_id'])) $companyId = (int)$agRow['req_company_id'];
                    if (!empty($agRow['req_shop_id'])) $shopId = (int)$agRow['req_shop_id'];
                }
            }

            // 2. Resolver item en tabla item del ERP
            $cleanSku = trim($skuCode);
            if (preg_match('/(TEL\d+|BTE\d+|BOB\d+)/i', $cleanSku, $m)) {
                $cleanSku = strtoupper($m[1]);
            }
            $stItem = $this->erpPdo->prepare("
                SELECT id, item_number_prod, item_title, item_reg_kg, item_unit
                FROM item
                WHERE item_number_prod = :sku
                LIMIT 1
            ");
            $stItem->execute([':sku' => $cleanSku]);
            $itemRow = $stItem->fetch(PDO::FETCH_ASSOC);
            if (!$itemRow && $cleanSku !== '') {
                $stItemLike = $this->erpPdo->prepare("
                    SELECT id, item_number_prod, item_title, item_reg_kg, item_unit
                    FROM item
                    WHERE item_number_prod LIKE :sku OR item_title LIKE :sku2
                    ORDER BY id DESC LIMIT 1
                ");
                $stItemLike->execute([':sku' => "%{$cleanSku}%", ':sku2' => "%{$cleanSku}%"]);
                $itemRow = $stItemLike->fetch(PDO::FETCH_ASSOC);
            }
            if (!$itemRow && $orderId > 0) {
                $stItemOrd = $this->erpPdo->prepare("
                    SELECT i.id, i.item_number_prod, i.item_title, i.item_reg_kg, i.item_unit
                    FROM orders_items oi
                    JOIN item i ON i.id = oi.item_id
                    WHERE oi.req_id = :oid
                    LIMIT 1
                ");
                $stItemOrd->execute([':oid' => $orderId]);
                $itemRow = $stItemOrd->fetch(PDO::FETCH_ASSOC);
            }

            if (!$itemRow) {
                return null;
            }

            $itemId = (int)$itemRow['id'];
            $baseKg = (float)($itemRow['item_reg_kg'] ?? 0);
            $itemSku = strtoupper(trim((string)($itemRow['item_number_prod'] ?? $cleanSku)));

            // Si viene una cantidad explícita de bobinas consumidas (puede ser decimal, ej: 1.5, 2.0, 0.5)
            if ($consumedUnits > 0.0) {
                $amountUnits = round($consumedUnits, 4);
            } elseif (preg_match('/^(TEL|BTE|BOB|ROLL)/i', $itemSku) || (int)($itemRow['item_unit'] ?? 0) === 1) {
                $amountUnits = 1.0;
            } elseif ($baseKg > 1.0) {
                $amountUnits = round($consumedKg / $baseKg, 4);
            } else {
                $amountUnits = 1.0;
            }

            // 3. Generar número de movimiento
            $stkNum = $this->generateNextStockChangeNumber($companyId);
            $annotText = number_format($consumedKg, 2, ',', '.') . " Kilogramos";
            if ($amountUnits > 0) {
                $unitLabel = ($amountUnits == 1.0) ? '1 bobina' : (number_format($amountUnits, 2, ',', '.') . ' bobinas');
                $annotText .= " ({$unitLabel})";
            }
            if ($rollCode !== '') {
                $annotText .= " [{$rollCode}]";
            }
            if ($extraAnnotation !== '') {
                $annotText .= " - {$extraAnnotation}";
            }

            // 4. Insertar cabecera stockchanges (stk_negative = 1, stk_issueid = 19 Uso material)
            $stmtSC = $this->erpPdo->prepare("
                INSERT INTO stockchanges
                (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate, stk_negative,
                 stk_status, stk_crtdat, stk_crtusr, stk_fixedsthid, sth_fromprodotid, sth_fromrebo_agid,
                 sth_order_id, sth_order_num, sth_assign_equipoid, sth_assign_wrkid)
                VALUES
                (:num, :annot, 19, :compid, :shopid, :bdate, 1,
                 2, :crtdat, :crtusr, :sthid, :ot_id, :ag_id,
                 :order_id, :order_num, :equipoid, :wrkid)
            ");
            $stmtSC->execute([
                ':num' => $stkNum,
                ':annot' => $annotText,
                ':compid' => $companyId,
                ':shopid' => $shopId,
                ':bdate' => $now,
                ':crtdat' => $now,
                ':crtusr' => $userId,
                ':sthid' => $stkFixedSthId,
                ':ot_id' => $workerOtId,
                ':ag_id' => $agId,
                ':order_id' => $orderId,
                ':order_num' => $orderNum,
                ':equipoid' => $equipoId,
                ':wrkid' => $userId,
            ]);
            $stkId = (int)$this->erpPdo->lastInsertId();

            if ($stkId > 0) {
                // 5. Insertar ítem en stockchanges_items
                $this->erpPdo->prepare("
                    INSERT INTO stockchanges_items
                    (stk_id, item_id, item_pos, item_amount, item_type, item_st_id,
                     item_costprice_brutto, item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes,
                     item_charges_act, item_sellprice_brutto, item_reboprod_amt, item_reboprod_unitkg)
                    VALUES
                    (:stk_id, :itid, 0, :amt, 'item', :sthid,
                     0, 0, 0, 0,
                     0, 0, :reboprod_amt, :unitkg)
                ")->execute([
                    ':stk_id' => $stkId,
                    ':itid' => $itemId,
                    ':amt' => $amountUnits,
                    ':sthid' => $stkFixedSthId,
                    ':reboprod_amt' => $amountUnits,
                    ':unitkg' => round($consumedKg, 2),
                ]);

                // 6. Descontar inventario en item_shops_storehouses sólo si se solicita explícitamente
                if ($deductInventory) {
                    try {
                        $this->applyStockChangeLine($stkId);
                    } catch (Throwable $e) {
                        error_log('Kardex del consumo de bobina (documento ' . $stkId . '): ' . $e->getMessage());
                    }
                }
            }

            return $stkId;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Aplica al stock y al kardex una línea de un documento de ajuste ya creado (rebobinado, empalme, corte, consumo).
     * Reemplaza los UPDATE directos: mueve la fila de stock general de esa bodega y deja su registro en tran_data con el
     * saldo previo. Si la línea ya tiene su kardex, no hace nada (repetir no mueve stock dos veces).
     */
    private function applyStockChangeLine(int $docId, int $pos = 0): void
    {
        $doc = $this->erpPdo->prepare('SELECT stk_num, stk_negative, stk_companyid, stk_shopid, stk_issueid, stk_crtdat, stk_crtusr, sth_order_id
            FROM stockchanges WHERE id = ?');
        $doc->execute([$docId]);
        $d = $doc->fetch(PDO::FETCH_ASSOC);
        $line = $this->erpPdo->prepare('SELECT item_id, item_amount, item_st_id FROM stockchanges_items WHERE stk_id = ? AND item_pos = ?');
        $line->execute([$docId, $pos]);
        $l = $line->fetch(PDO::FETCH_ASSOC);
        if (!$d || !$l || (float)$l['item_amount'] <= 0) {
            return;
        }
        $negative = (int)$d['stk_negative'] === 1;
        $type = $negative ? 'stockchangedown' : 'stockchangeup';
        $has = $this->erpPdo->prepare('SELECT 1 FROM tran_data WHERE tran_id = ? AND tran_pos = ? AND tran_type = ?');
        $has->execute([$docId, $pos, $type]);
        if ($has->fetchColumn() !== false) {
            return;
        }
        $itemId = (int)$l['item_id'];
        $stId = (int)$l['item_st_id'];
        $qty = (float)$l['item_amount'];
        $shopId = (int)$d['stk_shopid'];
        $row = $this->erpPdo->prepare('SELECT shop_id, iss_inventory FROM item_shops_storehouses WHERE item_id = ? AND st_id = ? AND iss_order_id = 0
            ORDER BY CASE WHEN shop_id = ? THEN 0 ELSE 1 END LIMIT 1');
        $row->execute([$itemId, $stId, $shopId]);
        $r = $row->fetch(PDO::FETCH_ASSOC);
        $before = $r ? (float)$r['iss_inventory'] : 0.0;
        $shopId = $r ? (int)$r['shop_id'] : ($shopId > 0 ? $shopId : StockLedgerService::DEFAULT_SHOP_ID);
        $title = $this->erpPdo->prepare('SELECT item_title FROM item WHERE id = ?');
        $title->execute([$itemId]);
        $created = (int)$d['stk_crtdat'] ?: time();
        $this->erpPdo->prepare("INSERT INTO tran_data
                (tran_id, tran_pos, tran_type, tran_number, tran_currstock, tran_amount, tran_company_id, tran_shop_id, tran_st_id, tran_issueid,
                 item_id, item_title, item_type, tran_day, tran_month, tran_year, tran_crtusr, tran_crtdat, tran_order_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'item', ?, ?, ?, ?, ?, ?)")
            ->execute([$docId, $pos, $type, (string)$d['stk_num'], $before, $qty, (int)$d['stk_companyid'], $shopId, $stId, (int)$d['stk_issueid'],
                $itemId, (string)($title->fetchColumn() ?: ''), (int)date('j', $created), (int)date('n', $created), (int)date('Y', $created),
                (int)$d['stk_crtusr'], $created, (int)$d['sth_order_id']]);
        if ($r) {
            $this->erpPdo->prepare('UPDATE item_shops_storehouses SET iss_inventory = iss_inventory + ? WHERE item_id = ? AND shop_id = ? AND st_id = ? AND iss_order_id = 0')
                ->execute([$negative ? -$qty : $qty, $itemId, $shopId, $stId]);
        } else {
            $this->erpPdo->prepare('INSERT INTO item_shops_storehouses (item_id, shop_id, st_id, iss_order_amount, iss_inventory_min, iss_inventory, iss_inventory_reserved, iss_order_id)
                VALUES (?, ?, ?, 0, 0, ?, 0, 0)')->execute([$itemId, $shopId, $stId, $negative ? -$qty : $qty]);
        }
    }

    /**
     * Genera el siguiente número de transacción de stock (company_numcounter_stockchange).
     */
    private function generateNextStockChangeNumber(int $companyId): string
    {
        $compId = $companyId > 0 ? $companyId : 20010;
        try {
            $this->erpPdo->beginTransaction();
            $stmt = $this->erpPdo->prepare("
                SELECT company_numformat_stockchange, company_numcounter_stockchange
                FROM company_data WHERE id = :id FOR UPDATE
            ");
            $stmt->execute([':id' => $compId]);
            $cd = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $prefix = !empty($cd['company_numformat_stockchange']) ? trim((string)$cd['company_numformat_stockchange']) : 'IA';
            $nextCount = ((int)($cd['company_numcounter_stockchange'] ?? 33000)) + 1;

            $this->erpPdo->prepare("
                UPDATE company_data
                SET company_numcounter_stockchange = :c
                WHERE id = :id
            ")->execute([':c' => $nextCount, ':id' => $compId]);

            $this->erpPdo->commit();
            return $prefix . sprintf('%08d', $nextCount);
        } catch (Throwable) {
            if ($this->erpPdo->inTransaction()) {
                $this->erpPdo->rollBack();
            }
            return 'IA' . sprintf('%08d', time() % 100000000);
        }
    }

    /**
     * Busca un ítem existente o crea uno nuevo para un subproducto cortado (form.reboprod.php líneas 150-330).
     * REGLA ESTRICTA: Debe coincidir exactamente en MEDIDAS (ancho), GRAMAJE (gsm) y COLOR (val_id en com_id=17).
     * NUNCA debe devolver un ítem de un color diferente. Si no existe, se genera un nuevo código TEL con el color y medidas correctos.
     */
    public function findOrCreateCutItem(
        array $baseItem,
        float $destDims,
        float $destLargo,
        int $userId,
        string $destType = 'bag',
        int $forcedColorId = 0,
        string $forcedColorDesc = '',
        ?float $forcedGsm = null
    ): int {
        $gsm = $forcedGsm !== null && $forcedGsm > 0 ? $forcedGsm : (float)($baseItem['item_reg_gsm'] ?? 80);
        $width = round($destDims, 2);

        // 1. Resolver el color exacto (ID y Nombre)
        $colorId = $forcedColorId;
        $colorDesc = trim($forcedColorDesc);

        if ($colorId <= 0 && !empty($baseItem['colorid'])) {
            $colorId = (int)$baseItem['colorid'];
        }
        if ($colorId <= 0 && !empty($baseItem['id'])) {
            try {
                $stC = $this->erpPdo->prepare("
                    SELECT val_id FROM tran_comments_item_vals WHERE item_id = :iid AND com_id = 17 LIMIT 1
                ");
                $stC->execute([':iid' => (int)$baseItem['id']]);
                $colorId = (int)($stC->fetchColumn() ?: 0);
            } catch (Throwable) {}
        }
        if ($colorId <= 0 && $colorDesc === '' && !empty($baseItem['colordesc'])) {
            $colorDesc = trim((string)$baseItem['colordesc']);
        }
        if ($colorId <= 0 && $colorDesc !== '') {
            try {
                $stCd = $this->erpPdo->prepare("
                    SELECT id FROM tran_comments_vals WHERE add_com_id = 17 AND (add_name = :c OR add_name LIKE :cp) LIMIT 1
                ");
                $stCd->execute([':c' => $colorDesc, ':cp' => '%' . $colorDesc . '%']);
                $colorId = (int)($stCd->fetchColumn() ?: 0);
            } catch (Throwable) {}
        }
        if ($colorId > 0 && $colorDesc === '') {
            try {
                $stCn = $this->erpPdo->prepare("SELECT add_name FROM tran_comments_vals WHERE id = :id LIMIT 1");
                $stCn->execute([':id' => $colorId]);
                $colorDesc = (string)($stCn->fetchColumn() ?: '');
            } catch (Throwable) {}
        }

        // 2. Resolver material (ID y Nombre)
        $materialId = (int)($baseItem['material_id'] ?? 0);
        $materialName = trim((string)($baseItem['material'] ?? ''));
        if ($materialId <= 0 && !empty($baseItem['id'])) {
            try {
                $stM = $this->erpPdo->prepare("
                    SELECT val_id FROM tran_comments_item_vals WHERE item_id = :iid AND com_id = 18 LIMIT 1
                ");
                $stM->execute([':iid' => (int)$baseItem['id']]);
                $materialId = (int)($stM->fetchColumn() ?: 0);
            } catch (Throwable) {}
        }
        if ($materialName === '' && $materialId > 0) {
            try {
                $stMn = $this->erpPdo->prepare("SELECT add_name FROM tran_comments_vals WHERE id = :id LIMIT 1");
                $stMn->execute([':id' => $materialId]);
                $materialName = (string)($stMn->fetchColumn() ?: 'TNT');
            } catch (Throwable) {}
        }
        if ($materialName === '') {
            $materialName = 'TNT';
        }

        // 3. Buscar si ya existe un ítem en catálogo con este ancho, gramaje y COLOR exacto
        if ($width > 0 && $gsm > 0 && $colorId > 0) {
            try {
                $sql = "SELECT DISTINCT t1.id
                        FROM item t1
                        INNER JOIN item_productcats t2 ON t1.id = t2.item_id
                        INNER JOIN tran_comments_item_vals t4 ON t4.item_id = t1.id AND t4.com_id = 17
                        WHERE
                            t1.item_status > 0 AND
                            t2.cat_id IN (6, 27) AND
                            ABS(t1.item_reg_gsm - :gsm) < 0.5 AND
                            ABS(t1.item_reg_width - :w) < 0.2 AND
                            t4.val_id = :color_id";
                if (str_starts_with(strtoupper((string)($baseItem['item_number_prod'] ?? '')), 'TEL')) {
                    $sql .= " AND t1.item_number_prod LIKE 'TEL%'";
                }
                $sql .= " ORDER BY (CASE WHEN t1.item_number_prod LIKE 'TEL%' THEN 0 ELSE 1 END), t1.id DESC LIMIT 1";
                $stmt = $this->erpPdo->prepare($sql);
                $stmt->execute([':gsm' => $gsm, ':w' => $width, ':color_id' => $colorId]);
                $existingId = (int)$stmt->fetchColumn();
                if ($existingId > 0) {
                    return $existingId;
                }
            } catch (Throwable) {}
        }

        // 4. Si no existe, crear un nuevo ítem con el código, medidas, gramaje y color exactos
        try {
            $now = time();
            $stmtCols = $this->erpPdo->query("SHOW COLUMNS FROM item");
            $allCols = $stmtCols->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $colsToCopy = array_diff($allCols, ['id']);
            $colList = implode(', ', $colsToCopy);

            $this->erpPdo->prepare("
                INSERT INTO item ({$colList})
                SELECT {$colList} FROM item WHERE id = :bid
            ")->execute([':bid' => $baseItem['id']]);
            $newItemId = (int)$this->erpPdo->lastInsertId();

            if ($newItemId > 0) {
                // Generar nuevo código item_number_prod (TELxxxx o BTExxxx)
                $isBte = str_contains((string)($baseItem['item_number_prod'] ?? ''), 'BTE');
                $catId = $isBte ? 27 : 6;
                $prefix = $isBte ? 'BTE' : 'TEL';

                $stmtCount = $this->erpPdo->prepare("
                    SELECT item_number_prod FROM item WHERE item_number_prod LIKE :p ORDER BY id DESC LIMIT 100
                ");
                $stmtCount->execute([':p' => "{$prefix}%"]);
                $existingProds = $stmtCount->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $maxNum = 1;
                foreach ($existingProds as $ep) {
                    $num = (int)str_replace($prefix, '', (string)$ep);
                    if ($num >= $maxNum) $maxNum = $num + 1;
                }
                $newProdNumber = $prefix . sprintf('%04d', $maxNum);

                // Calcular peso estimado según ancho, gsm y largo
                $effLargo = $destLargo > 0 ? $destLargo : (float)($baseItem['item_reg_length'] ?? 1100);
                if ($effLargo <= 0) $effLargo = 1100.0;
                $unitKg = round(($destDims * $gsm * $effLargo) / 100000, 2);
                if ($unitKg <= 0) $unitKg = round($destDims * 0.8, 2);

                // Formatear título descriptivo del producto con su color, medidas y gramos reales
                $cleanColorTitle = strtoupper($colorDesc ?: 'COLOR');
                $newTitle = "{$materialName}/{$cleanColorTitle}/{$width}x{$gsm}x" . (int)$effLargo;

                $this->erpPdo->prepare("
                    UPDATE item
                    SET item_number_prod = :pnum,
                        item_title = :title,
                        item_reg_width = :w,
                        item_reg_length = :l,
                        item_reg_gsm = :gsm,
                        item_reg_kg = :kg,
                        item_crtdat = :now,
                        item_crtusr = :uid,
                        item_status = 1,
                        item_released = 1
                    WHERE id = :id
                ")->execute([
                    ':pnum' => $newProdNumber,
                    ':title' => $newTitle,
                    ':w' => $destDims,
                    ':l' => $effLargo,
                    ':gsm' => $gsm,
                    ':kg' => $unitKg,
                    ':now' => $now,
                    ':uid' => $userId,
                    ':id' => $newItemId,
                ]);

                // Asignar categoría
                $this->erpPdo->prepare("
                    INSERT INTO item_productcats (item_id, cat_id) VALUES (:iid, :cid)
                ")->execute([':iid' => $newItemId, ':cid' => $catId]);

                // Asignar color exacto en tran_comments_item_vals (com_id = 17)
                if ($colorId > 0) {
                    $this->erpPdo->prepare("
                        INSERT INTO tran_comments_item_vals (item_id, com_id, val_id)
                        VALUES (:nid, 17, :cid)
                    ")->execute([':nid' => $newItemId, ':cid' => $colorId]);
                }

                // Asignar material exacto en tran_comments_item_vals (com_id = 18)
                if ($materialId > 0) {
                    $this->erpPdo->prepare("
                        INSERT INTO tran_comments_item_vals (item_id, com_id, val_id)
                        VALUES (:nid, 18, :mid)
                    ")->execute([':nid' => $newItemId, ':mid' => $materialId]);
                }

                return $newItemId;
            }
        } catch (Throwable) {}

        return (int)$baseItem['id'];
    }


}

#[AllowDynamicProperties]
class RewindAndPackagingService
{
    use RewindAndPackagingServiceTrait;

    private ?ProductionService $bridge = null;

    public function __construct(private PDO $erpPdo, private ?PDO $trzPdo = null, ?ProductionService $bridge = null)
    {
        $this->bridge = $bridge;
    }

    public function __call(string $name, array $arguments): mixed
    {
        $this->bridge ??= new ProductionService($this->erpPdo, $this->trzPdo);
        return $this->bridge->$name(...$arguments);
    }
}
