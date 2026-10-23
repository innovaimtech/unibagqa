<?php

declare(strict_types=1);

/**
 * Central warehouse requests, dispatches and receipt acknowledgements.
 * Solicitudes centrales, despachos y confirmaciones de recepcion.
 * Only TRZ owns the journal; ERP uses its native warehouse-transfer documents.
 * Solo TRZ guarda el diario; ERP utiliza sus documentos nativos de traspaso.
 */
final class MaterialSupplyService
{
    private PDO $erp;
    private PDO $trz;
    private bool $ready = false;

    public function __construct(PDO $erp, PDO $trz)
    {
        $this->erp = $erp;
        $this->trz = $trz;
    }

    private function sqlite(PDO $pdo): bool
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function lock(): string
    {
        return $this->sqlite($this->trz) ? '' : ' FOR UPDATE';
    }

    private function one(PDO $pdo, string $sql, array $params = []): ?array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function target(): string
    {
        return $this->sqlite($this->erp) ? 'sqlite-test' : hash('sha256', json_encode(
            $this->erp->query('SELECT DATABASE() AS db, @@hostname AS host, @@port AS port')->fetch(PDO::FETCH_ASSOC),
            JSON_THROW_ON_ERROR
        ));
    }

    public function ensureSchema(): void
    {
        if ($this->ready) return;
        $engine = $this->sqlite($this->trz) ? '' : ' ENGINE=InnoDB';
        $this->trz->exec('CREATE TABLE IF NOT EXISTS production_supply_requests (
            request_key VARCHAR(32) PRIMARY KEY, erp_target VARCHAR(64) NOT NULL,
            worker_ot_id BIGINT NOT NULL, agenda_id BIGINT NOT NULL, context TEXT NOT NULL,
            item_id BIGINT NOT NULL, sku VARCHAR(100) NOT NULL, description TEXT NOT NULL,
            source_storehouse_id BIGINT NOT NULL, requested_qty DECIMAL(12,3) NOT NULL,
            unit VARCHAR(40) NOT NULL, requested_by TEXT NOT NULL, notes TEXT NOT NULL,
            created_at BIGINT NOT NULL' . ($engine === '' ? '' : ', INDEX idx_supply_ot (erp_target,worker_ot_id)')
            . ')' . $engine);
        $this->trz->exec('CREATE TABLE IF NOT EXISTS production_supply_dispatches (
            dispatch_key VARCHAR(32) PRIMARY KEY, request_key VARCHAR(32) NOT NULL,
            quantity DECIMAL(12,3) NOT NULL, state VARCHAR(24) NOT NULL,
            payload TEXT NOT NULL, erp_transfer_id BIGINT NULL, error_message TEXT NULL,
            delivered_by TEXT NOT NULL, received_by TEXT NULL,
            created_at BIGINT NOT NULL, delivered_at BIGINT NULL, received_at BIGINT NULL'
            . ($engine === '' ? '' : ', INDEX idx_supply_dispatch_request (request_key,state)') . ')' . $engine);
        if (!$this->sqlite($this->trz)) {
            $tables = $this->trz->query("SHOW TABLE STATUS WHERE Name IN
                ('production_supply_requests','production_supply_dispatches','rolls',
                 'traceability_warehouse_entries','movements','events')")->fetchAll(PDO::FETCH_ASSOC);
            if (count($tables) !== 6) throw new RuntimeException('No se pudieron verificar las tablas TRZ.');
            foreach ($tables as $table) {
                if (strcasecmp($table['Engine'], 'InnoDB') !== 0) {
                    throw new RuntimeException('Las tablas TRZ de solicitudes y stock deben usar InnoDB.');
                }
            }
        }
        $this->ready = true;
    }

    public static function quantity(float $qty, bool $whole = false): float
    {
        if (!is_finite($qty) || $qty <= 0 || $qty > 999999999
            || abs($qty - round($qty, 3)) > 0.000001 || ($whole && floor($qty) !== $qty)) {
            throw new RuntimeException($whole ? 'Indica una cantidad entera de bobinas mayor a cero.' : 'Cantidad invalida (maximo tres decimales).');
        }
        return round($qty, 3);
    }

    private function key(string $key): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $key)) throw new RuntimeException('Confirmacion invalida.');
    }

    private function actor(array $actor): string
    {
        if ((int)($actor['user_id'] ?? 0) <= 0 || trim((string)($actor['name'] ?? '')) === '') {
            throw new RuntimeException('Se requiere un usuario identificado.');
        }
        return json_encode($actor, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    public function operatorContext(int $otId, int $workerId, int $plantId): array
    {
        $ctx = $this->one($this->erp, 'SELECT ot.id AS worker_ot_id, ot.wok_status, ot.wok_ag_id AS agenda_id,
                wi.win_wrkid, wi.win_plantaid, wi.win_equipoid, eq.equipo_planta_id AS machine_plant_id,
                ag.ag_reqid AS order_id, o.req_number, o.req_company_id, o.req_shop_id
            FROM prod_worker_ot ot JOIN prod_worker_init wi ON wi.id=ot.wok_init_id
            JOIN prod_agenda ag ON ag.id=ot.wok_ag_id JOIN orders o ON o.id=ag.ag_reqid
            LEFT JOIN equipo eq ON eq.id=wi.win_equipoid WHERE ot.id=?', [$otId]);
        // Legacy sessions may omit the plant; use the machine's registered plant.
        // Los turnos antiguos pueden omitir la planta; usar la registrada en la maquina.
        $effectivePlantId = (int)($ctx['win_plantaid'] ?? 0);
        if ($effectivePlantId <= 0) {
            $effectivePlantId = (int)($ctx['machine_plant_id'] ?? 0);
        }
        if (!$ctx || $workerId <= 0 || (int)$ctx['win_wrkid'] !== $workerId || (int)$ctx['wok_status'] !== 1) {
            throw new RuntimeException('La OT debe estar abierta y pertenecer al operador conectado.');
        }
        $ctx['win_plantaid'] = $effectivePlantId;
        return $ctx;
    }

    /**
     * Requests belong to the active machine, not to a plant or an open work order.
     * Las solicitudes pertenecen a la maquina activa, no a una planta u OT abierta.
     */
    public function machineContext(int $workerId): array
    {
        if ($workerId <= 0) throw new RuntimeException('Se requiere un operador identificado.');
        $machine = $this->one($this->erp, 'SELECT wi.id AS machine_session_id,
                wi.win_wrkid, wi.win_plantaid, wi.win_equipoid, eq.equipo_name AS machine_name
            FROM prod_worker_init wi JOIN equipo eq ON eq.id=wi.win_equipoid
            WHERE wi.win_wrkid=? AND wi.win_status=1 ORDER BY wi.id DESC LIMIT 1', [$workerId]);
        if (!$machine) throw new RuntimeException('Selecciona la maquina donde estas trabajando para solicitar materiales.');
        // Warehouse ownership determines the ERP shop; plant is informational only.
        // La sucursal ERP proviene de la bodega; la planta es solo informativa.
        $shop = $this->one($this->erp, 'SELECT sh.id AS req_shop_id, sh.shop_company_id AS req_company_id
            FROM company_shops_storehouses wh JOIN company_shops sh ON sh.id=wh.st_shop_id WHERE wh.id=16');
        if (!$shop) throw new RuntimeException('Bodega 3000 no tiene una sucursal ERP valida.');
        return array_merge($machine, $shop, ['worker_ot_id'=>0, 'agenda_id'=>0, 'order_id'=>0, 'req_number'=>'']);
    }

    public function catalog(int $shopId): array
    {
        $stmt = $this->erp->prepare("SELECT i.id AS item_id, i.item_number_prod AS sku,
                i.item_title AS description, i.item_reg_width AS width_cm, i.item_desc,
                css.id AS source_storehouse_id, css.st_name AS warehouse_name,
                COALESCE(u.unit_name,'Unid.') AS unit,
                iss.iss_inventory-COALESCE(iss.iss_inventory_reserved,0) AS available_qty
            FROM item_shops_storehouses iss JOIN item i ON i.id=iss.item_id
            JOIN company_shops_storehouses css ON css.id=iss.st_id AND css.st_shop_id=iss.shop_id
            LEFT JOIN item_units u ON u.id=i.item_unit
            WHERE iss.shop_id=? AND (
                (iss.st_id IN (5,11) AND i.item_number_prod NOT LIKE 'TIN%')
                OR (iss.st_id IN (7,24,28) AND i.item_number_prod LIKE 'TIN%'))
              AND (i.item_status>0 OR i.item_status IS NULL)
              AND iss.iss_inventory-COALESCE(iss.iss_inventory_reserved,0)>0
            ORDER BY i.item_number_prod, css.id LIMIT 1000");
        $stmt->execute([$shopId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function item(int $itemId, int $source, int $shopId): array
    {
        if (!in_array($source, [5, 11, 7, 24, 28], true)) throw new RuntimeException('Bodega de solicitud no permitida.');
        $item = $this->one($this->erp,"SELECT i.id AS item_id,i.item_number_prod AS sku,i.item_title AS description,
                COALESCE(u.unit_name,'Unid.') AS unit,
                iss.iss_inventory-COALESCE(iss.iss_inventory_reserved,0) AS available_qty
            FROM item i JOIN item_shops_storehouses iss ON iss.item_id=i.id
            JOIN company_shops_storehouses css ON css.id=iss.st_id AND css.st_shop_id=iss.shop_id
            LEFT JOIN item_units u ON u.id=i.item_unit
            WHERE i.id=? AND iss.st_id=? AND iss.shop_id=? AND (i.item_status>0 OR i.item_status IS NULL)
              AND ((iss.st_id IN (5,11) AND i.item_number_prod NOT LIKE 'TIN%')
                OR (iss.st_id IN (7,24,28) AND i.item_number_prod LIKE 'TIN%'))
              AND iss.iss_inventory-COALESCE(iss.iss_inventory_reserved,0)>0",[$itemId,$source,$shopId]);
        if ($item) return $item;
        throw new RuntimeException('El material no tiene stock disponible en la bodega central seleccionada.');
    }

    public function planningContext(array $ctx, int $agendaId): array
    {
        if ($agendaId <= 0) return $ctx;
        $ag = $this->one($this->erp, 'SELECT * FROM prod_agenda WHERE id=?', [$agendaId]);
        $stamp = (int)($ag['ag_date_stamp'] ?? 0);
        $isToday = ($ag['ag_date'] ?? '') === date('d.m.Y')
            || ($stamp >= strtotime('today') && $stamp < strtotime('tomorrow'));
        if (!$ag || (int)$ag['ag_equipo_id'] !== (int)$ctx['win_equipoid']
            || (int)$ag['ag_status'] !== 1 || !$isToday) {
            throw new RuntimeException('Selecciona una OT planificada para hoy en esta maquina.');
        }
        require_once __DIR__ . '/ProductionService.php';
        $details = (new ProductionService($this->erp))->getWorkOrderAgendaDetails($agendaId);
        if (!$details || (int)($details['req_shop_id'] ?? 0) !== (int)$ctx['req_shop_id']) {
            throw new RuntimeException('No se pudo obtener la ficha de la OT en esta sucursal.');
        }
        $ctx['agenda_id'] = $agendaId;
        $ctx['order_id'] = (int)$ag['ag_reqid'];
        $ctx['req_number'] = (string)$details['req_number'];
        $ctx['planned_ot_number'] = (string)($details['prd_number'] ?? $agendaId);
        $ctx['planning_details'] = $details;
        return $ctx;
    }

    public function request(string $key, int $otId, array $actor, int $itemId, int $source, float $qty, string $notes, int $agendaId = 0): void
    {
        $this->key($key);
        $who = $this->actor($actor);
        $ctx = $otId > 0
            ? $this->operatorContext($otId, (int)($actor['worker_id'] ?? 0), (int)($actor['plant_id'] ?? 0))
            : $this->machineContext((int)($actor['worker_id'] ?? 0));
        if ($otId === 0) $ctx = $this->planningContext($ctx, $agendaId);
        $qty = self::quantity($qty);
        $this->ensureSchema();
        $this->trz->beginTransaction();
        try {
            $existing = $this->one($this->trz, 'SELECT * FROM production_supply_requests WHERE request_key=?' . $this->lock(), [$key]);
            if ($existing) {
                if ((int)$existing['worker_ot_id'] !== $otId || (int)$existing['item_id'] !== $itemId
                    || (int)$existing['source_storehouse_id'] !== $source || (float)$existing['requested_qty'] !== $qty
                    || $existing['erp_target'] !== $this->target()) throw new RuntimeException('Confirmacion ya utilizada.');
                $original = json_decode($existing['requested_by'], true, 512, JSON_THROW_ON_ERROR);
                $originalContext = json_decode($existing['context'], true, 512, JSON_THROW_ON_ERROR);
                if ((int)$original['user_id'] !== (int)$actor['user_id']
                    || (int)$originalContext['win_equipoid'] !== (int)$ctx['win_equipoid']
                    || (int)$originalContext['agenda_id'] !== (int)$ctx['agenda_id']) {
                    throw new RuntimeException('Confirmacion ya utilizada por otra solicitud.');
                }
            } else {
                $item = $this->item($itemId, $source, (int)$ctx['req_shop_id']);
                self::quantity($qty,str_starts_with(strtoupper($item['sku']), 'TEL'));
                if ($qty > (float)$item['available_qty']) throw new RuntimeException('La cantidad supera el stock disponible.');
                $this->trz->prepare('INSERT INTO production_supply_requests
                    (request_key,erp_target,worker_ot_id,agenda_id,context,item_id,sku,description,
                     source_storehouse_id,requested_qty,unit,requested_by,notes,created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
                        $key,$this->target(),$otId,$ctx['agenda_id'],json_encode($ctx,JSON_THROW_ON_ERROR),
                        $itemId,$item['sku'],$item['description'],$source,$qty,$item['unit'],$who,trim($notes),time(),
                    ]);
                $this->event('SUPPLY_REQUESTED', ['request_key'=>$key,'context'=>$ctx,'quantity'=>$qty,'actor'=>$actor]);
            }
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        }
    }

    private function event(string $type, array $payload): void
    {
        $this->trz->prepare('INSERT INTO events(type,payload) VALUES (?,?)')
            ->execute([$type,json_encode($payload,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    public function requests(?int $otId = null, ?int $machineId = null): array
    {
        $this->ensureSchema();
        $stmt = $this->trz->prepare("SELECT r.*,
                COALESCE((SELECT SUM(d.quantity) FROM production_supply_dispatches d
                    WHERE d.request_key=r.request_key AND d.state!='CANCELLED'),0) AS allocated_qty,
                COALESCE((SELECT SUM(d.quantity) FROM production_supply_dispatches d
                    WHERE d.request_key=r.request_key AND d.state IN ('DISPATCHED','RECEIVED')),0) AS delivered_qty,
                COALESCE((SELECT SUM(d.quantity) FROM production_supply_dispatches d
                    WHERE d.request_key=r.request_key AND d.state='RECEIVED'),0) AS received_qty
            FROM production_supply_requests r WHERE erp_target=?" . ($otId === null ? '' : ' AND worker_ot_id=?') .
            ($machineId === null ? '' : " AND CAST(JSON_EXTRACT(r.context,'$.win_equipoid') AS "
                . ($this->sqlite($this->trz) ? 'INTEGER' : 'SIGNED') . ")=?") .
            " ORDER BY CASE WHEN EXISTS (SELECT 1 FROM production_supply_dispatches p
                WHERE p.request_key=r.request_key AND p.state IN ('PENDING','DISPATCHED')) THEN 0 ELSE 1 END,
                created_at DESC,request_key DESC LIMIT 200");
        $params = [$this->target()];
        if ($otId !== null) $params[] = $otId;
        if ($machineId !== null) $params[] = $machineId;
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deliveries(string $requestKey): array
    {
        $this->ensureSchema();
        $stmt = $this->trz->prepare('SELECT d.* FROM production_supply_dispatches d
            JOIN production_supply_requests r ON r.request_key=d.request_key
            WHERE d.request_key=? AND r.erp_target=? ORDER BY d.created_at,d.dispatch_key');
        $stmt->execute([$requestKey,$this->target()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assertNoOutstandingReceipts(int $otId): void
    {
        $exists = $this->sqlite($this->trz)
            ? $this->trz->query("SELECT name FROM sqlite_master WHERE type='table' AND name='production_supply_dispatches'")->fetchColumn()
            : $this->trz->query("SHOW TABLES LIKE 'production_supply_dispatches'")->fetchColumn();
        if (!$exists) return;
        $row = $this->one($this->trz,"SELECT d.dispatch_key FROM production_supply_dispatches d
            JOIN production_supply_requests r ON r.request_key=d.request_key
            WHERE r.worker_ot_id=? AND r.erp_target=? AND d.state IN ('PENDING','DISPATCHED') LIMIT 1",[$otId,$this->target()]);
        if ($row) throw new RuntimeException('Resuelve los despachos pendientes y confirma la recepcion de materiales antes de terminar la OT.');
    }

    public function chainForRoll(int $rollId): array
    {
        $exists = $this->sqlite($this->trz)
            ? $this->trz->query("SELECT name FROM sqlite_master WHERE type='table' AND name='production_supply_dispatches'")->fetchColumn()
            : $this->trz->query("SHOW TABLES LIKE 'production_supply_dispatches'")->fetchColumn();
        if (!$exists) return [];
        $stmt = $this->trz->prepare('SELECT d.*,r.worker_ot_id,r.sku,r.requested_by,r.source_storehouse_id,r.created_at AS requested_at
            FROM production_supply_dispatches d JOIN production_supply_requests r ON r.request_key=d.request_key
            WHERE r.erp_target=? AND ' . ($this->sqlite($this->trz)
                ? "EXISTS (SELECT 1 FROM json_each(d.payload,'$.rolls') j WHERE json_extract(j.value,'$.id')=CAST(? AS INTEGER))"
                : "JSON_CONTAINS(d.payload,?,'$.rolls')") . ' ORDER BY d.created_at DESC LIMIT 100');
        $stmt->execute([$this->target(),$this->sqlite($this->trz)?$rollId:json_encode(['id'=>$rollId],JSON_THROW_ON_ERROR)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function requestRow(string $key): array
    {
        $row = $this->one($this->trz, 'SELECT * FROM production_supply_requests WHERE request_key=? AND erp_target=?'
            . $this->lock(), [$key,$this->target()]);
        if (!$row) throw new RuntimeException('Solicitud no disponible en este ERP.');
        return $row;
    }

    private function roll(string $code): array
    {
        $row = $this->one($this->trz, 'SELECT r.*,s.code AS sku,w.erp_storehouse_id FROM rolls r
            JOIN skus s ON s.id=r.sku_id JOIN warehouses w ON w.id=r.warehouse_id
            WHERE r.roll_code=?' . $this->lock(), [$code]);
        if (!$row) throw new RuntimeException('Codigo no registrado: ' . $code . '. Ingresa su trazabilidad antes de despachar.');
        return $row;
    }

    public function dispatch(string $key, string $requestKey, float $qty, string $codes, array $actor): void
    {
        $this->key($key);
        $who = $this->actor($actor);
        $this->ensureSchema();
        $this->trz->beginTransaction();
        try {
            $r = $this->requestRow($requestKey);
            $existing = $this->one($this->trz, 'SELECT * FROM production_supply_dispatches WHERE dispatch_key=?', [$key]);
            if ($existing) {
                if ($existing['request_key'] !== $requestKey || abs((float)$existing['quantity']-$qty)>0.000001) {
                    throw new RuntimeException('Confirmacion ya utilizada.');
                }
                $this->trz->commit();
                $this->resume($key,$actor);
                return;
            }
            $ctx = json_decode($r['context'],true,512,JSON_THROW_ON_ERROR);
            if ((int)$r['source_storehouse_id'] === 16) {
                throw new RuntimeException('Crea una nueva solicitud de tintas desde Bodega 900, 910 o 920.');
            }
            if (str_starts_with(strtoupper($r['sku']),'TIN') && trim($codes) !== '') {
                throw new RuntimeException('La entrega de tintas usa la cantidad del articulo, no codigos de bobina.');
            }
            $qty = self::quantity($qty,str_starts_with(strtoupper($r['sku']),'TEL'));
            $pending = $this->one($this->trz,"SELECT dispatch_key FROM production_supply_dispatches
                WHERE request_key=? AND state='PENDING'",[$requestKey]);
            if ($pending) throw new RuntimeException('Primero resuelve el despacho pendiente.');
            $sum = $this->one($this->trz,"SELECT COALESCE(SUM(quantity),0) AS qty FROM production_supply_dispatches
                WHERE request_key=? AND state!='CANCELLED'",[$requestKey]);
            if ((float)$sum['qty']+$qty > (float)$r['requested_qty']+0.000001) throw new RuntimeException('La entrega supera lo solicitado.');
            $destination = $this->one($this->trz,'SELECT id FROM warehouses WHERE code=3000 AND erp_storehouse_id=16 ORDER BY id LIMIT 1');
            if (!$destination) throw new RuntimeException('No existe Bodega 3000 vinculada al ERP 16.');
            $codeList = array_values(array_filter(preg_split('/[\s,;]+/',trim($codes)) ?: []));
            if (count(array_unique($codeList)) !== count($codeList)) throw new RuntimeException('Hay codigos repetidos.');
            if (str_starts_with(strtoupper($r['sku']),'TEL') && count($codeList) !== (int)$qty) {
                throw new RuntimeException('Escanea un codigo individual por cada bobina entregada.');
            }
            $rolls = [];
            $total = 0.0;
            foreach ($codeList as $code) {
                $roll = $this->roll($code);
                if ($roll['sku'] !== $r['sku'] || (int)$roll['erp_storehouse_id'] !== (int)$r['source_storehouse_id']
                    || $roll['status'] !== 'RECEIVED' || !empty($roll['current_work_order_id'])) {
                    throw new RuntimeException('El codigo no corresponde al material disponible en la bodega origen: ' . $code);
                }
                $rolls[] = ['id'=>(int)$roll['id'],'code'=>$code,'warehouse_id'=>(int)$roll['warehouse_id'],
                    'quantity'=>(float)$roll['received_qty'],'weight_kg'=>(float)$roll['weight_kg']];
                $total += (float)$roll['received_qty'];
            }
            if ($rolls && abs($total-$qty)>0.000001) throw new RuntimeException('La cantidad de los codigos no coincide con la entrega.');
            $payload = ['rolls'=>$rolls,'destination_id'=>(int)$destination['id'],'context'=>$ctx,
                'prepared_by'=>$actor,'requested_by'=>json_decode($r['requested_by'],true),'sku'=>$r['sku']];
            $this->trz->prepare('INSERT INTO production_supply_dispatches
                (dispatch_key,request_key,quantity,state,payload,delivered_by,created_at) VALUES (?,?,?,\'PENDING\',?,?,?)')
                ->execute([$key,$requestKey,$qty,json_encode($payload,JSON_THROW_ON_ERROR),$who,time()]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        }
        $this->resume($key,$actor);
    }

    public function resume(string $key, array $actor): void
    {
        $this->actor($actor);
        $this->ensureSchema();
        $this->trz->beginTransaction();
        try {
            $d = $this->one($this->trz,'SELECT * FROM production_supply_dispatches WHERE dispatch_key=?' . $this->lock(),[$key]);
            if (!$d) throw new RuntimeException('Despacho no encontrado.');
            $r = $this->requestRow($d['request_key']);
            if ($d['state'] !== 'PENDING') { $this->trz->commit(); return; }
            $payload = json_decode($d['payload'],true,512,JSON_THROW_ON_ERROR);
            foreach ($payload['rolls'] as $expected) {
                $roll = $this->roll($expected['code']);
                if ((int)$roll['warehouse_id'] !== $expected['warehouse_id'] || $roll['status'] !== 'RECEIVED'
                    || !empty($roll['current_work_order_id']) || abs((float)$roll['received_qty']-(float)$expected['quantity'])>0.000001
                    || abs((float)$roll['weight_kg']-(float)$expected['weight_kg'])>0.000001) {
                    throw new RuntimeException('El codigo cambio durante el despacho; requiere conciliacion.');
                }
            }
            if ((int)$r['source_storehouse_id'] === 16) {
                throw new RuntimeException('Crea una nueva solicitud de tintas desde Bodega 900, 910 o 920.');
            }
            $transferId = $this->bookTransfer($r,$d,$payload);
            foreach ($payload['rolls'] as $roll) {
                $this->trz->prepare('UPDATE rolls SET warehouse_id=? WHERE id=?')
                    ->execute([$payload['destination_id'],$roll['id']]);
                $this->trz->prepare("UPDATE traceability_warehouse_entries SET warehouse_id=?
                    WHERE entity_type='ROLL' AND entity_id=? AND traceability_code=? AND warehouse_id=?")
                    ->execute([$payload['destination_id'],$roll['id'],$roll['code'],$roll['warehouse_id']]);
                $movement = ['request_key'=>$r['request_key'],'dispatch_key'=>$key,'erp_transfer_id'=>$transferId,
                    'requested_by'=>$payload['requested_by'],'delivered_by'=>$payload['prepared_by'],
                    'roll_code'=>$roll['code'],'quantity'=>$roll['quantity'],'sku'=>$r['sku']];
                $this->trz->prepare("INSERT INTO movements(entity_type,entity_id,movement_type,from_warehouse_id,to_warehouse_id,payload)
                    VALUES ('ROLL',?,'TRANSFER',?,?,?)")->execute([$roll['id'],$roll['warehouse_id'],
                        $payload['destination_id'],json_encode($movement,JSON_THROW_ON_ERROR)]);
            }
            $this->event('SUPPLY_DISPATCHED',['request_key'=>$r['request_key'],'dispatch_key'=>$key,
                'erp_transfer_id'=>$transferId,'quantity'=>(float)$d['quantity'],'detail'=>$payload]);
            $this->trz->prepare("UPDATE production_supply_dispatches SET state='DISPATCHED',
                erp_transfer_id=?,delivered_at=?,payload=?,error_message=NULL WHERE dispatch_key=?")
                ->execute([$transferId ?: null,time(),json_encode($payload,JSON_THROW_ON_ERROR),$key]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            $this->trz->prepare("UPDATE production_supply_dispatches SET error_message=? WHERE dispatch_key=? AND state='PENDING'")
                ->execute([mb_substr($e->getMessage(),0,1000),$key]);
            throw new RuntimeException('Despacho pendiente. Usa Reintentar; no vuelvas a despachar el mismo material. ' . $e->getMessage(),0,$e);
        }
    }

    public function receive(string $key, int $otId, array $actor): void
    {
        $who = $this->actor($actor);
        $machine = $this->machineContext((int)($actor['worker_id']??0));
        $this->ensureSchema();
        $this->trz->beginTransaction();
        try {
            $d = $this->one($this->trz,'SELECT * FROM production_supply_dispatches WHERE dispatch_key=?' . $this->lock(),[$key]);
            if (!$d) throw new RuntimeException('Despacho no encontrado.');
            $r = $this->requestRow($d['request_key']);
            $ctx = json_decode($r['context'], true, 512, JSON_THROW_ON_ERROR);
            if ((int)$ctx['win_equipoid'] !== (int)$machine['win_equipoid']) {
                throw new RuntimeException('La entrega corresponde a otra maquina.');
            }
            if ($otId > 0 && (int)$r['worker_ot_id'] !== $otId) throw new RuntimeException('La entrega no pertenece a esta OT.');
            if ($d['state'] === 'RECEIVED') { $this->trz->commit(); return; }
            if ($d['state'] !== 'DISPATCHED') throw new RuntimeException('Primero se debe completar el despacho.');
            $this->trz->prepare("UPDATE production_supply_dispatches SET state='RECEIVED',received_by=?,received_at=?
                WHERE dispatch_key=?")->execute([$who,time(),$key]);
            $this->event('SUPPLY_RECEIVED',['request_key'=>$r['request_key'],'dispatch_key'=>$key,'actor'=>$actor,
                'quantity'=>(float)$d['quantity'],'erp_transfer_id'=>(int)$d['erp_transfer_id']]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        }
    }

    public function cancelPending(string $key, string $reason, array $actor): void
    {
        $this->actor($actor);
        if (trim($reason)==='') throw new RuntimeException('Indica el motivo de cancelacion.');
        $this->ensureSchema();
        $locked=false;
        $this->trz->beginTransaction();
        try {
            $d=$this->one($this->trz,'SELECT * FROM production_supply_dispatches WHERE dispatch_key=?' . $this->lock(),[$key]);
            if (!$d) throw new RuntimeException('Despacho no encontrado.');
            $this->requestRow($d['request_key']);
            if ($d['state']!=='PENDING') throw new RuntimeException('Solo se cancelan despachos pendientes sin movimiento ERP.');
            if (!$this->sqlite($this->erp)) {
                $this->erp->exec('LOCK TABLES storehousechanges READ');
                $locked=true;
            }
            if ($this->one($this->erp,'SELECT id FROM storehousechanges WHERE strc_desc LIKE ?',['%[TRZ-SUPPLY:'.$key.']%'])) {
                throw new RuntimeException('Existe un traspaso ERP. Reintenta o concilia; no se puede cancelar.');
            }
            $payload=json_decode($d['payload'],true,512,JSON_THROW_ON_ERROR);
            $payload['cancelled_by']=$actor;
            $payload['cancellation_reason']=trim($reason);
            $this->trz->prepare("UPDATE production_supply_dispatches SET state='CANCELLED',payload=?,error_message=NULL WHERE dispatch_key=?")
                ->execute([json_encode($payload,JSON_THROW_ON_ERROR),$key]);
            $this->event('SUPPLY_DISPATCH_CANCELLED',['dispatch_key'=>$key,'request_key'=>$d['request_key'],'actor'=>$actor,'reason'=>trim($reason)]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        } finally {
            if ($locked) $this->erp->exec('UNLOCK TABLES');
        }
    }

    private function fingerprint(int $itemId, int $shopId, int $storehouseId): string
    {
        $row = $this->one($this->erp,'SELECT tran_id,tran_pos,tran_type,tran_amount,tran_crtdat FROM tran_data
            WHERE item_id=? AND tran_shop_id=? AND tran_st_id=?
            ORDER BY tran_crtdat DESC,tran_id DESC,tran_pos DESC,tran_type DESC LIMIT 1',[$itemId,$shopId,$storehouseId]);
        return hash('sha256',json_encode($row ?: [],JSON_THROW_ON_ERROR));
    }

    /**
     * Persist checkpoints before MyISAM writes; never guess after another movement.
     * Persistir saldos antes de escribir MyISAM; no inferirlos tras otro movimiento.
     */
    private function bookTransfer(array $r, array $d, array $payload): int
    {
        $locked = !$this->sqlite($this->erp);
        if ($locked) $this->erp->exec('LOCK TABLES company_data WRITE, storehousechanges WRITE,
            storehousechanges_items WRITE, item_shops_storehouses WRITE, tran_data WRITE,
            item READ, company_shops READ, company_shops_storehouses READ, prod_worker_ot READ');
        try {
            $ctx = $payload['context'];
            $from = $this->one($this->erp,'SELECT * FROM company_shops_storehouses WHERE id=?',[$r['source_storehouse_id']]);
            $to = $this->one($this->erp,'SELECT * FROM company_shops_storehouses WHERE id=16');
            if (!$from || !$to || (int)$from['st_shop_id'] !== (int)$ctx['req_shop_id']
                || (int)$to['st_shop_id'] !== (int)$ctx['req_shop_id']) throw new RuntimeException('Bodegas y sucursal no coinciden.');
            $shop = $this->one($this->erp,'SELECT shop_company_id FROM company_shops WHERE id=?',[$ctx['req_shop_id']]);
            if (!$shop || (int)$shop['shop_company_id'] !== (int)$ctx['req_company_id']) throw new RuntimeException('Empresa y sucursal no coinciden.');
            $item = $this->one($this->erp,'SELECT item_title FROM item WHERE id=?',[$r['item_id']]);
            if (!$item) throw new RuntimeException('Articulo ERP no encontrado.');
            $marker = '[TRZ-SUPPLY:' . $d['dispatch_key'] . ']';
            $doc = $this->one($this->erp,'SELECT * FROM storehousechanges WHERE strc_desc LIKE ?', ['%'.$marker.'%']);
            $qty = (float)$d['quantity'];
            if (!$doc) {
                $anchors = [];
                foreach ([(int)$r['source_storehouse_id'],16] as $st) {
                    $stock = $this->one($this->erp,'SELECT iss_inventory,iss_inventory_reserved FROM item_shops_storehouses
                        WHERE item_id=? AND shop_id=? AND st_id=?',[$r['item_id'],$ctx['req_shop_id'],$st]);
                    if (!$stock && $st !== 16) throw new RuntimeException('Stock origen no existe.');
                    if ($st !== 16 && (float)$stock['iss_inventory']-(float)$stock['iss_inventory_reserved']+0.000001<$qty) {
                        throw new RuntimeException('Stock disponible insuficiente.');
                    }
                    $anchors[$st] = ['before'=>(float)($stock['iss_inventory']??0),
                        'after'=>round((float)($stock['iss_inventory']??0)+($st===16?$qty:-$qty),10),
                        'fingerprint'=>$this->fingerprint((int)$r['item_id'],(int)$ctx['req_shop_id'],$st)];
                }
                $company = $this->one($this->erp,'SELECT company_numformat_storehouse,company_numcounter_storehouse FROM company_data WHERE id=?',[$ctx['req_company_id']]);
                if (!$company) throw new RuntimeException('Contador de traspasos no disponible.');
                $counter = (int)$company['company_numcounter_storehouse']+1;
                $number = ($company['company_numformat_storehouse'] ?: 'TR') . sprintf('%08d',$counter);
                $this->erp->prepare('UPDATE company_data SET company_numcounter_storehouse=? WHERE id=?')->execute([$counter,$ctx['req_company_id']]);
                $description = 'Solicitud ' . $r['request_key'] . '. ' . $r['sku'] . ' ' . $qty .
                    '. Maquina ' . ($ctx['machine_name'] ?? ('#' . $ctx['win_equipoid'])) .
                    '. Solicitado por ' . $payload['requested_by']['name'] . '; entregado por ' . $payload['prepared_by']['name'] .
                    '. ' . $marker . ' [TRZ-SUPPLY-STOCK:' . base64_encode(json_encode($anchors,JSON_THROW_ON_ERROR)) . ']';
                $this->erp->prepare('INSERT INTO storehousechanges
                    (strc_company_id,strc_shop_id,strc_company_dest_id,strc_shop_dest_id,strc_status,
                     strc_date,strc_desc,strc_number,strc_crtusr,strc_crtdat,strc_order_id,strc_order_id_dest,
                     strc_order_num,strc_order_num_dest,strc_shopsent,strc_shopreceived,strc_custid,strc_rel_reqnum)
                    VALUES (?,?,?,?,1,?,?,?,?,?,?,?,?,?,0,0,0,?)')->execute([
                        $ctx['req_company_id'],$ctx['req_shop_id'],$ctx['req_company_id'],$ctx['req_shop_id'],
                        time(),$description,$number,$payload['prepared_by']['user_id'],time(),
                        $ctx['order_id'],$ctx['order_id'],$ctx['req_number'],$ctx['req_number'],$ctx['req_number'],
                    ]);
                $doc = $this->one($this->erp,'SELECT * FROM storehousechanges WHERE id=?',[(int)$this->erp->lastInsertId()]);
            }
            if (!in_array((int)$doc['strc_status'],[1,2],true)
                || (int)$doc['strc_company_id'] !== (int)$ctx['req_company_id']
                || (int)$doc['strc_company_dest_id'] !== (int)$ctx['req_company_id']
                || (int)$doc['strc_shop_id'] !== (int)$ctx['req_shop_id']
                || (int)$doc['strc_shop_dest_id'] !== (int)$ctx['req_shop_id']
                || !preg_match('/\[TRZ-SUPPLY-STOCK:([A-Za-z0-9+\/=]+)\]/',$doc['strc_desc'],$match)) {
                throw new RuntimeException('Documento ERP alterado; requiere conciliacion.');
            }
            $anchors = json_decode(base64_decode($match[1],true),true,512,JSON_THROW_ON_ERROR);
            $id = (int)$doc['id'];
            $line = $this->one($this->erp,'SELECT * FROM storehousechanges_items WHERE strc_id=? AND item_pos=0',[$id]);
            if (!$line && (int)$doc['strc_status']===1) {
                $this->erp->prepare("INSERT INTO storehousechanges_items(strc_id,item_id,item_pos,item_amount,item_type,item_st_id,item_st_dest_id)
                    VALUES (?,?,0,?,'item',?,16)")->execute([$id,$r['item_id'],$qty,$r['source_storehouse_id']]);
            } elseif (!$line || (int)$line['item_id'] !== (int)$r['item_id']
                || (int)$line['item_st_id'] !== (int)$r['source_storehouse_id'] || (int)$line['item_st_dest_id'] !== 16
                || abs((float)$line['item_amount']-$qty)>0.000001) throw new RuntimeException('Detalle ERP alterado.');
            // Native sthdown/sthup entries feed both stock and warehouse-transfer reports.
            // Los asientos nativos alimentan movimientos de stock e informe de traspasos.
            foreach ([(int)$r['source_storehouse_id']=>'sthdown',16=>'sthup'] as $st=>$type) {
                $pos = $st===16 ? 1 : 0;
                $anchor = $anchors[$st];
                if (abs($anchor['after']-($anchor['before']+($st===16?$qty:-$qty)))>0.000001) throw new RuntimeException('Saldo de recuperacion alterado.');
                $ledger = $this->one($this->erp,'SELECT * FROM tran_data WHERE tran_id=? AND tran_type=? AND tran_pos=?',[$id,$type,$pos]);
                if ($ledger) {
                    if ((int)$ledger['item_id'] !== (int)$r['item_id'] || (int)$ledger['tran_st_id'] !== $st
                        || (int)$ledger['tran_shop_id'] !== (int)$ctx['req_shop_id']
                        || abs((float)$ledger['tran_amount']-$qty)>0.000001) throw new RuntimeException('Libro ERP alterado.');
                    continue;
                }
                if ((int)$doc['strc_status']!==1 || $anchor['fingerprint'] !== $this->fingerprint((int)$r['item_id'],(int)$ctx['req_shop_id'],$st)) {
                    throw new RuntimeException('Otros movimientos impiden recuperar automaticamente. Requiere conciliacion.');
                }
                $stock = $this->one($this->erp,'SELECT iss_inventory,iss_inventory_reserved FROM item_shops_storehouses WHERE item_id=? AND shop_id=? AND st_id=?',
                    [$r['item_id'],$ctx['req_shop_id'],$st]);
                $current = (float)($stock['iss_inventory']??0);
                if (abs($current-$anchor['before'])<0.000001) {
                    if ($st !== 16 && $current-(float)($stock['iss_inventory_reserved']??0)+0.000001<$qty) {
                        throw new RuntimeException('El stock disponible cambio por reservas; requiere conciliacion.');
                    }
                    if ($stock) $this->erp->prepare('UPDATE item_shops_storehouses SET iss_inventory=? WHERE item_id=? AND shop_id=? AND st_id=?')
                        ->execute([$anchor['after'],$r['item_id'],$ctx['req_shop_id'],$st]);
                    else $this->erp->prepare('INSERT INTO item_shops_storehouses(item_id,shop_id,st_id,iss_inventory) VALUES (?,?,?,?)')
                        ->execute([$r['item_id'],$ctx['req_shop_id'],$st,$anchor['after']]);
                } elseif (abs($current-$anchor['after'])>=0.000001) throw new RuntimeException('Saldo ambiguo; requiere conciliacion.');
                $this->erp->prepare("INSERT INTO tran_data(tran_id,tran_pos,tran_type,tran_number,tran_currstock,tran_amount,
                    tran_company_id,tran_shop_id,tran_st_id,item_id,item_title,item_type,tran_day,tran_month,tran_year,tran_crtusr,tran_crtdat,tran_order_id)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,'item',?,?,?,?,?,?)")->execute([
                        $id,$pos,$type,$doc['strc_number'],$anchor['before'],$qty,$ctx['req_company_id'],$ctx['req_shop_id'],$st,$r['item_id'],$item['item_title'],
                        (int)date('j',(int)$doc['strc_date']),(int)date('n',(int)$doc['strc_date']),(int)date('Y',(int)$doc['strc_date']),
                        $payload['prepared_by']['user_id'],$doc['strc_crtdat'],$ctx['order_id'],
                    ]);
            }
            $this->erp->prepare('UPDATE storehousechanges SET strc_status=2,strc_updusr=?,strc_upddat=? WHERE id=?')
                ->execute([$payload['prepared_by']['user_id'],time(),$id]);
            return $id;
        } finally {
            if ($locked) $this->erp->exec('UNLOCK TABLES');
        }
    }
}
