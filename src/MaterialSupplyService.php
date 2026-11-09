<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Servicio de Abastecimiento de Materiales entre Bodega Central y Producción
 * (src/MaterialSupplyService.php)
 * =============================================================================
 *
 * Responsabilidad:
 * Coordina el ciclo de vida completo de suministro de materias primas e insumos
 * para las órdenes de trabajo en planta:
 * 1. Solicitud (Request): El operador o supervisor solicita bobinas para una máquina/OT.
 * 2. Despacho (Dispatch): Bodega Central (Bodega 3000 / 100) prepara y despacha las bobinas físicas.
 * 3. Recepción en Máquina (Receive): El operador confirma en la consola la recepción física
 *    de las bobinas, habilitándolas inmediatamente para su consumo en la OT.
 * 4. Control de Integridad: Impide el cierre de una OT si existen despachos pendientes
 *    de confirmación (assertNoOutstandingReceipts).
 * 5. Trazabilidad de Cadena (chainForRoll): Reconstruye el historial completo desde la
 *    solicitud de bodega hasta el consumo final en el producto terminado.
 */
final class MaterialSupplyService
{
    private PDO $erp;
    private PDO $trz;
    private bool $ready = false;

    /**
     * @param PDO $erp Conexión a la base de datos ERP (unibag_unibag).
     * @param PDO $trz Conexión a la base de datos TRZ (unibag_trazabilidad).
     */
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

    /** Cambiar este texto cada vez que se agregue una tabla o columna al esquema de solicitudes. */
    private const SCHEMA_VERSION = 'supply-2026-11-08-boxes';

    public function ensureSchema(): void
    {
        if ($this->ready) return;
        // Rendimiento: tras una verificación exitosa no se repite en cada página (solo en MySQL real).
        $useGate = !$this->sqlite($this->trz) && class_exists('SchemaGate');
        if ($useGate && SchemaGate::done('supply', self::SCHEMA_VERSION)) {
            $this->ready = true;
            return;
        }
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
        // ---- Traspasos entre bodegas: estados de la solicitud, escaneos y auditoría ----
        // Estados de la solicitud (columna status):
        //   PENDING    = recién creada por el operador, espera a la bodega de origen
        //   ACCEPTED   = aceptada: el encargado está preparando (escaneando bobinas)
        //   REJECTED   = rechazada por bodega (con motivo)
        //   CANCELLED  = cancelada antes de despachar (con motivo)
        //   DISPATCHED = despachada: el stock ya pasó a la Bodega 3000
        //   RECEIVED   = recepción confirmada
        $newRequestColumns = [
            'status' => "VARCHAR(24) NOT NULL DEFAULT 'PENDING'",
            'accepted_by' => 'TEXT NULL', 'accepted_at' => 'BIGINT NULL',
            'rejected_by' => 'TEXT NULL', 'rejected_at' => 'BIGINT NULL', 'reject_reason' => 'TEXT NULL',
            'cancelled_by' => 'TEXT NULL', 'cancelled_at' => 'BIGINT NULL', 'cancel_reason' => 'TEXT NULL',
        ];
        $statusColumnIsNew = !$this->columnExists('production_supply_requests', 'status');
        foreach ($newRequestColumns as $column => $definition) {
            if (!$this->columnExists('production_supply_requests', $column)) {
                $this->trz->exec('ALTER TABLE production_supply_requests ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
        if ($statusColumnIsNew) {
            // Solicitudes anteriores a este módulo: se les asigna el estado según sus despachos.
            $this->trz->exec("UPDATE production_supply_requests SET status='ACCEPTED' WHERE EXISTS (
                SELECT 1 FROM production_supply_dispatches d WHERE d.request_key=production_supply_requests.request_key AND d.state!='CANCELLED')");
            $this->trz->exec("UPDATE production_supply_requests SET status='DISPATCHED' WHERE EXISTS (
                SELECT 1 FROM production_supply_dispatches d WHERE d.request_key=production_supply_requests.request_key AND d.state='DISPATCHED')");
            $this->trz->exec("UPDATE production_supply_requests SET status='RECEIVED' WHERE
                (SELECT COALESCE(SUM(d.quantity),0) FROM production_supply_dispatches d WHERE d.request_key=production_supply_requests.request_key AND d.state='RECEIVED') >= requested_qty");
        }
        // Un escaneo por bobina: active_code lleva el código mientras el escaneo está vigente (NULL si se anula
        // o ya se recibió). El índice UNIQUE impide que dos personas preparen la misma bobina.
        $this->trz->exec('CREATE TABLE IF NOT EXISTS production_supply_scans (
            scan_key VARCHAR(32) PRIMARY KEY, request_key VARCHAR(32) NOT NULL, dispatch_key VARCHAR(32) NULL,
            roll_id BIGINT NOT NULL, code VARCHAR(60) NOT NULL, quantity DECIMAL(12,3) NOT NULL,
            state VARCHAR(16) NOT NULL, active_code VARCHAR(60) NULL,
            scanned_by TEXT NOT NULL, scanned_at BIGINT NOT NULL, received_by TEXT NULL, received_at BIGINT NULL,
            voided_by TEXT NULL, voided_at BIGINT NULL, void_reason TEXT NULL,
            UNIQUE (active_code)' . ($engine === '' ? '' : ', INDEX idx_supply_scan_request (request_key,state), INDEX idx_supply_scan_dispatch (dispatch_key)')
            . ')' . $engine);
        // Bobina de reemplazo / más unidades: motivo del bodeguero (despacho) y comentario del operador (recepción).
        $newColumns = [
            'production_supply_dispatches' => ['change_reason' => 'TEXT NULL', 'change_flags' => 'VARCHAR(64) NULL', 'receive_comment' => 'TEXT NULL'],
            'production_supply_scans' => ['sku' => 'VARCHAR(100) NULL', 'meters' => 'DECIMAL(12,2) NULL', 'substitute' => 'TINYINT NOT NULL DEFAULT 0',
                // BOX = caja de bolsas con código (roll_id guarda el id de la caja); vacío = bobina.
                'entity_type' => 'VARCHAR(10) NULL'],
            // BOXES = se piden bolsas que llegan en cajas con código (se escanean y se traspasa el saldo de cada caja con su CC).
            'production_supply_requests' => ['material_kind' => 'VARCHAR(16) NULL'],
        ];
        foreach ($newColumns as $table => $columns) {
            foreach ($columns as $column => $definition) {
                if (!$this->columnExists($table, $column)) {
                    $this->trz->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
                }
            }
        }
        // Kardex / auditoría: solo se inserta, nunca se actualiza ni se borra.
        $this->trz->exec('CREATE TABLE IF NOT EXISTS production_supply_audit (
            audit_key VARCHAR(32) PRIMARY KEY, created_at BIGINT NOT NULL, actor TEXT NOT NULL,
            action VARCHAR(30) NOT NULL, request_key VARCHAR(32) NOT NULL, dispatch_key VARCHAR(32) NULL,
            from_warehouse VARCHAR(40) NULL, to_warehouse VARCHAR(40) NULL, sku VARCHAR(100) NULL,
            code VARCHAR(60) NULL, quantity DECIMAL(12,3) NULL,
            state_from VARCHAR(24) NULL, state_to VARCHAR(24) NULL, detail TEXT NULL'
            . ($engine === '' ? '' : ', INDEX idx_supply_audit_request (request_key), INDEX idx_supply_audit_date (created_at)')
            . ')' . $engine);
        if (!$this->sqlite($this->trz)) {
            $tables = $this->trz->query("SHOW TABLE STATUS WHERE Name IN
                ('production_supply_requests','production_supply_dispatches','rolls',
                 'traceability_warehouse_entries','movements','events','production_supply_scans','production_supply_audit')")->fetchAll(PDO::FETCH_ASSOC);
            if (count($tables) !== 8) throw new RuntimeException('No se pudieron verificar las tablas TRZ.');
            foreach ($tables as $table) {
                if (strcasecmp($table['Engine'], 'InnoDB') !== 0) {
                    throw new RuntimeException('Las tablas TRZ de solicitudes y stock deben usar InnoDB.');
                }
            }
        }
        $this->ready = true;
        if ($useGate) {
            SchemaGate::mark('supply', self::SCHEMA_VERSION);
        }
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

    /**
     * Ids ERP de las bodegas "400" (productos terminados de reventa) de la sucursal.
     * Se identifican por el código al inicio del nombre (ej. "400 (PROD TERMINADOS REVENTA)"), no por un id fijo.
     *
     * @return int[]
     */
    public function productStorehouseIds(int $shopId): array
    {
        $stmt = $this->erp->prepare('SELECT id, st_name FROM company_shops_storehouses WHERE st_shop_id=?');
        $stmt->execute([$shopId]);
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (preg_match('/^\s*400\b/', (string)$row['st_name']) === 1) {
                $ids[] = (int)$row['id'];
            }
        }
        return $ids;
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
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$row) {
            $row['kind'] = str_starts_with(strtoupper((string)$row['sku']), 'TIN') ? 'inks' : 'rolls';
        }
        unset($row);

        // Productos de la bodega 400 (bolsas de algodón y otros): consulta aparte para no desplazar bobinas ni tintas.
        $productIds = $this->productStorehouseIds($shopId);
        if ($productIds !== []) {
            $in = implode(',', array_map('intval', $productIds));
            $stmt = $this->erp->prepare("SELECT i.id AS item_id, i.item_number_prod AS sku,
                    i.item_title AS description, i.item_reg_width AS width_cm, i.item_desc,
                    css.id AS source_storehouse_id, css.st_name AS warehouse_name,
                    COALESCE(u.unit_name,'Unid.') AS unit,
                    iss.iss_inventory-COALESCE(iss.iss_inventory_reserved,0) AS available_qty
                FROM item_shops_storehouses iss JOIN item i ON i.id=iss.item_id
                JOIN company_shops_storehouses css ON css.id=iss.st_id AND css.st_shop_id=iss.shop_id
                LEFT JOIN item_units u ON u.id=i.item_unit
                WHERE iss.shop_id=? AND iss.st_id IN ($in)
                  AND (i.item_status>0 OR i.item_status IS NULL)
                  AND iss.iss_inventory-COALESCE(iss.iss_inventory_reserved,0)>0
                ORDER BY i.item_title, i.item_number_prod LIMIT 20000");
            $stmt->execute([$shopId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $row['kind'] = 'products';
                $items[] = $row;
            }
        }
        foreach ($this->boxCatalog($shopId) as $row) {
            $items[] = $row;
        }
        return $items;
    }

    private function bagBoxes(): BagBoxService
    {
        require_once __DIR__ . '/BagBoxService.php';
        return new BagBoxService($this->trz, $this->erp);
    }

    /** Bolsas en cajas con código (en blanco de Corte y Sellado o compradas), por artículo y bodega, fuera de la 3000. */
    public function boxCatalog(int $shopId): array
    {
        $items = [];
        try {
            $groups = $this->bagBoxes()->availableGroups();
        } catch (Throwable) {
            return [];
        }
        foreach ($groups as $g) {
            $row = $this->one($this->erp, "SELECT i.id AS item_id, i.item_number_prod AS sku, i.item_title AS description, css.st_name AS warehouse_name
                FROM item i JOIN company_shops_storehouses css ON css.id=? WHERE i.id=? AND css.st_shop_id=?", [$g['st_id'], $g['item_id'], $shopId]);
            if (!$row) continue;
            $items[] = $row + ['source_storehouse_id' => $g['st_id'], 'unit' => 'bolsas', 'available_qty' => $g['units'],
                'box_count' => $g['boxes'], 'width_cm' => 0, 'item_desc' => '', 'kind' => 'boxes'];
        }
        return $items;
    }

    private function isBoxRequest(array $r): bool
    {
        return strtoupper((string)($r['material_kind'] ?? '')) === 'BOXES';
    }

    private function item(int $itemId, int $source, int $shopId): array
    {
        $productIds = $this->productStorehouseIds($shopId);
        if (in_array($source, $productIds, true)) {
            $product = $this->one($this->erp, "SELECT i.id AS item_id,i.item_number_prod AS sku,i.item_title AS description,
                    COALESCE(u.unit_name,'Unid.') AS unit,
                    iss.iss_inventory-COALESCE(iss.iss_inventory_reserved,0) AS available_qty
                FROM item i JOIN item_shops_storehouses iss ON iss.item_id=i.id
                LEFT JOIN item_units u ON u.id=i.item_unit
                WHERE i.id=? AND iss.st_id=? AND iss.shop_id=? AND (i.item_status>0 OR i.item_status IS NULL)
                  AND iss.iss_inventory-COALESCE(iss.iss_inventory_reserved,0)>0", [$itemId, $source, $shopId]);
            if ($product) return $product;
            throw new RuntimeException('El producto no tiene stock disponible en la bodega 400.');
        }
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

    public function request(string $key, int $otId, array $actor, int $itemId, int $source, float $qty, string $notes, int $agendaId = 0, string $kind = ''): void
    {
        $boxes = $kind === 'boxes';
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
                if ($boxes) {
                    $item = null;
                    foreach ($this->boxCatalog((int)$ctx['req_shop_id']) as $row) {
                        if ((int)$row['item_id'] === $itemId && (int)$row['source_storehouse_id'] === $source) $item = $row;
                    }
                    if ($item === null) throw new RuntimeException('No hay cajas de ese producto disponibles en esa bodega.');
                    self::quantity($qty, true);
                } else {
                    $item = $this->item($itemId, $source, (int)$ctx['req_shop_id']);
                    self::quantity($qty,str_starts_with(strtoupper($item['sku']), 'TEL'));
                }
                if ($qty > (float)$item['available_qty']) throw new RuntimeException('La cantidad supera el stock disponible.');
                $this->trz->prepare('INSERT INTO production_supply_requests
                    (request_key,erp_target,worker_ot_id,agenda_id,context,item_id,sku,description,
                     source_storehouse_id,requested_qty,unit,requested_by,notes,created_at,material_kind)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
                        $key,$this->target(),$otId,$ctx['agenda_id'],json_encode($ctx,JSON_THROW_ON_ERROR),
                        $itemId,$item['sku'],$item['description'],$source,$qty,$item['unit'],$who,trim($notes),time(),$boxes ? 'BOXES' : null,
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

    public function dispatch(string $key, string $requestKey, float $qty, string $codes, array $actor, string $changeReason = ''): void
    {
        $this->key($key);
        $who = $this->actor($actor);
        $this->ensureSchema();
        // Los códigos pegados en el formulario se registran como escaneos antes de despachar.
        $this->prescanCodes($key, $requestKey, $codes, $actor);
        $changeReason = trim($changeReason);
        $this->trz->beginTransaction();
        try {
            $r = $this->requestRow($requestKey);
            $existing = $this->one($this->trz, 'SELECT * FROM production_supply_dispatches WHERE dispatch_key=?', [$key]);
            if ($existing) {
                if ($existing['request_key'] !== $requestKey) {
                    throw new RuntimeException('Confirmacion ya utilizada.');
                }
                $this->trz->commit();
                $this->resumeRequestDispatches($requestKey, $key, $actor);
                return;
            }
            $ctx = json_decode($r['context'],true,512,JSON_THROW_ON_ERROR);
            if ((int)$r['source_storehouse_id'] === 16) {
                throw new RuntimeException('Crea una nueva solicitud de tintas desde Bodega 900, 910 o 920.');
            }
            // Solo se despacha una solicitud ACEPTADA por bodega (EN PREPARACION).
            if ($r['status'] !== 'ACCEPTED') {
                throw new RuntimeException('La solicitud debe estar aceptada por bodega antes de despachar (estado actual: ' . $this->statusLabel((string)$r['status']) . ').');
            }
            $isBoxes = $this->isBoxRequest($r);
            $isRoll = !$isBoxes && str_starts_with(strtoupper($r['sku']),'TEL');
            if (str_starts_with(strtoupper($r['sku']),'TIN') && trim($codes) !== '') {
                throw new RuntimeException('La entrega de tintas usa la cantidad del articulo, no codigos de bobina.');
            }
            $requested = (float)$r['requested_qty'];
            $pending = $this->one($this->trz,"SELECT dispatch_key FROM production_supply_dispatches
                WHERE request_key=? AND state='PENDING'",[$requestKey]);
            if ($pending) throw new RuntimeException('Primero resuelve el despacho pendiente.');
            $destination = $this->one($this->trz,'SELECT id FROM warehouses WHERE erp_storehouse_id=16 ORDER BY id LIMIT 1');
            if (!$destination) throw new RuntimeException('No existe la bodega 3000 Insumos en Producción (ERP 16) en trazabilidad.');

            // Grupos a despachar, uno por SKU (cada uno es un traspaso ERP de un solo artículo).
            $groups = [];
            $total = 0.0;
            $meterDiff = false;
            if ($isBoxes) {
                // Cada caja escaneada se traspasa completa (su saldo), con su CC.
                $scanStmt = $this->trz->prepare("SELECT code FROM production_supply_scans WHERE request_key=? AND state='SCANNED' ORDER BY scanned_at,scan_key");
                $scanStmt->execute([$requestKey]);
                $codeList = array_column($scanStmt->fetchAll(PDO::FETCH_ASSOC), 'code');
                if (!$codeList) throw new RuntimeException('Escanea al menos una caja antes de despachar.');
                $list = [];
                foreach ($codeList as $code) {
                    $box = $this->bagBoxes()->find((string)$code);
                    if (!$box || (string)$box['status'] !== 'IN_WAREHOUSE' || (int)$box['erp_st_id'] !== (int)$r['source_storehouse_id']) {
                        throw new RuntimeException('La caja ' . $code . ' ya no está disponible en la bodega de origen.');
                    }
                    $list[] = ['id' => (int)$box['id'], 'code' => (string)$box['box_code'], 'qty' => (float)$box['units_qty'],
                        'order_id' => (int)($box['erp_order_id'] ?? 0), 'from_st' => (int)$box['erp_st_id']];
                    $total += (float)$box['units_qty'];
                }
                if ($total + 0.000001 < (float)$r['requested_qty']) {
                    throw new RuntimeException('Faltan cajas por escanear: se pidieron ' . (float)$r['requested_qty'] . ' bolsas y las cajas escaneadas suman ' . $total . '.');
                }
                $groups[(string)$r['sku']] = ['rolls' => [], 'boxes' => $list, 'qty' => $total];
            } elseif ($isRoll) {
                // Las bobinas a despachar son las ESCANEADAS (registradas) para esta solicitud.
                $scanStmt = $this->trz->prepare("SELECT code FROM production_supply_scans WHERE request_key=? AND state='SCANNED' ORDER BY scanned_at,scan_key");
                $scanStmt->execute([$requestKey]);
                $codeList = array_column($scanStmt->fetchAll(PDO::FETCH_ASSOC), 'code');
                if (!$codeList) throw new RuntimeException('Escanea al menos una bobina antes de despachar.');
                $refMeters = $this->referenceMeters($r);
                foreach ($codeList as $code) {
                    $roll = $this->roll($code);
                    if ((int)$roll['erp_storehouse_id'] !== (int)$r['source_storehouse_id']
                        || $roll['status'] !== 'RECEIVED' || !empty($roll['current_work_order_id'])) {
                        throw new RuntimeException('El codigo no corresponde al material disponible en la bodega origen: ' . $code);
                    }
                    $sku = (string)$roll['sku'];
                    if ($sku !== (string)$r['sku']) $this->assertCompatibleSubstitute($r, $sku, $code);
                    $meters = isset($roll['meters']) && (float)$roll['meters'] > 0 ? (float)$roll['meters'] : null;
                    if ($meters !== null && $refMeters > 0 && abs($meters - $refMeters) / $refMeters > 0.01) $meterDiff = true;
                    $groups[$sku]['rolls'][] = ['id'=>(int)$roll['id'],'code'=>$code,'warehouse_id'=>(int)$roll['warehouse_id'],
                        'quantity'=>(float)$roll['received_qty'],'weight_kg'=>(float)$roll['weight_kg'],'sku'=>$sku,'meters'=>$meters];
                    $groups[$sku]['qty'] = ($groups[$sku]['qty'] ?? 0.0) + (float)$roll['received_qty'];
                    $total += (float)$roll['received_qty'];
                }
                if ($total + 0.000001 < $requested) {
                    throw new RuntimeException('Faltan bobinas por escanear: se solicitaron ' . (float)$requested . ' y se escanearon ' . (float)$total . '.');
                }
            } else {
                $qty = self::quantity($qty, false);
                if ($qty + 0.000001 < $requested) {
                    throw new RuntimeException('El despacho no puede ser menor a lo solicitado (' . (float)$requested . ' ' . $r['unit'] . ').');
                }
                $groups[(string)$r['sku']] = ['rolls' => [], 'qty' => $qty];
                $total = $qty;
            }

            // Diferencias respecto de lo pedido: exigen motivo del bodeguero.
            $flags = [];
            foreach (array_keys($groups) as $sku) {
                if ((string)$sku !== (string)$r['sku']) { $flags[] = 'SKU'; break; }
            }
            if (abs($total - $requested) > 0.000001) $flags[] = 'UNITS';
            if ($meterDiff) $flags[] = 'METERS';
            if ($isBoxes) $flags = array_values(array_diff($flags, ['UNITS'])); // las cajas se traspasan completas
            if ($flags && mb_strlen($changeReason) < 10) {
                throw new RuntimeException('Estas enviando algo distinto a lo solicitado (' . implode(', ', array_map(
                    static fn($f) => ['SKU' => 'otro código', 'UNITS' => 'otra cantidad', 'METERS' => 'otros metros'][$f], $flags))
                    . '). Escribe el motivo del cambio (mínimo 10 caracteres).');
            }
            $flagText = $flags ? implode(',', $flags) : null;
            $reasonText = $flags ? $changeReason : null;

            // La clave del formulario es del grupo del SKU pedido (o del primero); las demás se derivan de ella.
            $order = array_keys($groups);
            usort($order, static fn($a, $b) => ((string)$a === (string)$r['sku'] ? 0 : 1) <=> ((string)$b === (string)$r['sku'] ? 0 : 1));
            foreach ($order as $i => $sku) {
                $sku = (string)$sku;
                $group = $groups[$sku];
                $itemId = (int)$r['item_id'];
                if ($sku !== (string)$r['sku']) {
                    $item = $this->erpItemBySku($sku, (int)$r['source_storehouse_id']);
                    if (!$item) throw new RuntimeException('El código ' . $sku . ' no existe o está inactivo en el ERP.');
                    $itemId = (int)$item['item_id'];
                }
                $dKey = $i === 0 ? $key : substr(hash('sha256', $key . '|' . $sku), 0, 32);
                $payload = ['rolls'=>$group['rolls'],'boxes'=>$group['boxes'] ?? [],'destination_id'=>(int)$destination['id'],'context'=>$ctx,
                    'prepared_by'=>$actor,'requested_by'=>json_decode($r['requested_by'],true),'sku'=>$sku,
                    'item_id'=>$itemId,'requested_sku'=>$r['sku']];
                $this->trz->prepare('INSERT INTO production_supply_dispatches
                    (dispatch_key,request_key,quantity,state,payload,delivered_by,created_at,change_flags,change_reason)
                    VALUES (?,?,?,\'PENDING\',?,?,?,?,?)')
                    ->execute([$dKey,$requestKey,(float)$group['qty'],json_encode($payload,JSON_THROW_ON_ERROR),$who,time(),$flagText,$reasonText]);
                // Las bobinas escaneadas quedan ligadas a su despacho.
                foreach ($group['rolls'] as $gr) {
                    $this->trz->prepare("UPDATE production_supply_scans SET dispatch_key=? WHERE request_key=? AND code=? AND state='SCANNED'")
                        ->execute([$dKey,$requestKey,$gr['code']]);
                }
            }
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        }
        $this->resumeRequestDispatches($requestKey, $key, $actor);
    }

    /** Ejecuta (o reintenta) los despachos pendientes de una solicitud, uno por SKU. */
    private function resumeRequestDispatches(string $requestKey, string $firstKey, array $actor): void
    {
        $keys = [$firstKey];
        $stmt = $this->trz->prepare("SELECT dispatch_key FROM production_supply_dispatches WHERE request_key=? AND state='PENDING' ORDER BY created_at,dispatch_key");
        $stmt->execute([$requestKey]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $k) {
            if (!in_array($k, $keys, true)) $keys[] = (string)$k;
        }
        foreach ($keys as $k) {
            $this->resume($k, $actor);
        }
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
            // Cada despacho es de un solo artículo: el del grupo (puede ser un reemplazo del pedido).
            $rr = $r;
            if (!empty($payload['item_id'])) $rr['item_id'] = (int)$payload['item_id'];
            if (!empty($payload['sku'])) $rr['sku'] = (string)$payload['sku'];
            $changeNote = !empty($d['change_flags']) ? ' | Cambio (' . $d['change_flags'] . '): ' . (string)$d['change_reason'] : '';
            $transferId = !empty($payload['boxes']) ? $this->transferBoxes($rr, $d, $payload, $actor) : $this->bookTransfer($rr,$d,$payload);
            foreach ($payload['rolls'] as $roll) {
                $this->trz->prepare('UPDATE rolls SET warehouse_id=? WHERE id=?')
                    ->execute([$payload['destination_id'],$roll['id']]);
                $this->trz->prepare("UPDATE traceability_warehouse_entries SET warehouse_id=?
                    WHERE entity_type='ROLL' AND entity_id=? AND traceability_code=? AND warehouse_id=?")
                    ->execute([$payload['destination_id'],$roll['id'],$roll['code'],$roll['warehouse_id']]);
                $movement = ['request_key'=>$r['request_key'],'dispatch_key'=>$key,'erp_transfer_id'=>$transferId,
                    'requested_by'=>$payload['requested_by'],'delivered_by'=>$payload['prepared_by'],
                    'roll_code'=>$roll['code'],'quantity'=>$roll['quantity'],'sku'=>$rr['sku']];
                $this->trz->prepare("INSERT INTO movements(entity_type,entity_id,movement_type,from_warehouse_id,to_warehouse_id,payload)
                    VALUES ('ROLL',?,'TRANSFER',?,?,?)")->execute([$roll['id'],$roll['warehouse_id'],
                        $payload['destination_id'],json_encode($movement,JSON_THROW_ON_ERROR)]);
                $this->audit($rr,'DISPATCH',$actor,'ACCEPTED','DISPATCHED',$key,$roll['code'],(float)$roll['quantity'],'Traspaso ERP ' . $transferId . $changeNote);
            }
            foreach ($payload['boxes'] ?? [] as $box) {
                $this->audit($rr,'DISPATCH',$actor,'ACCEPTED','DISPATCHED',$key,$box['code'],(float)$box['qty'],'Traspaso ERP con CC' . $changeNote);
            }
            if (!$payload['rolls'] && empty($payload['boxes'])) {
                $this->audit($rr,'DISPATCH',$actor,'ACCEPTED','DISPATCHED',$key,null,(float)$d['quantity'],'Traspaso ERP ' . $transferId . $changeNote);
            }
            $this->trz->prepare("UPDATE production_supply_scans SET state='DISPATCHED' WHERE dispatch_key=? AND state='SCANNED'")->execute([$key]);
            $this->trz->prepare("UPDATE production_supply_requests SET status='DISPATCHED' WHERE request_key=? AND status='ACCEPTED'")->execute([$r['request_key']]);
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

    /**
     * Quién puede recepcionar: hoy solo el operador identificado (con turno en la máquina de la solicitud).
     * Cuando exista el módulo de supervisor, se agrega aquí su caso sin tocar el resto.
     */
    public function canReceiveSupply(array $actor): bool
    {
        return (int)($actor['worker_id'] ?? 0) > 0;
    }

    public function receive(string $key, int $otId, array $actor, string $comment = ''): void
    {
        $who = $this->actor($actor);
        if (!$this->canReceiveSupply($actor)) {
            throw new RuntimeException('Solo el operador de la máquina puede recepcionar los materiales.');
        }
        $comment = trim($comment);
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
            if (!empty($d['change_flags']) && mb_strlen($comment) < 10) {
                throw new RuntimeException('Recibes algo distinto a lo solicitado. Escribe un comentario aceptando el cambio y que sirve para producir (mínimo 10 caracteres).');
            }
            $this->finishReceipt($d,$r,$actor,$who,'OPERADOR',$comment);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // TRASPASOS ENTRE BODEGAS: aceptar / rechazar / escanear / recibir
    // =========================================================================

    private function columnExists(string $table, string $column): bool
    {
        if ($this->sqlite($this->trz)) {
            foreach ($this->trz->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $c) {
                if (strcasecmp((string)$c['name'], $column) === 0) return true;
            }
            return false;
        }
        $stmt = $this->trz->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?');
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    }

    /** Etiqueta legible de una bodega ERP para el kardex. */
    private function warehouseLabel(int $erpStorehouseId): string
    {
        $known = [5 => 'Bodega 100', 11 => 'Bodega 200', 7 => 'Bodega 900', 24 => 'Bodega 910',
            28 => 'Bodega 920', 16 => '3000 Insumos en Producción'];
        if (isset($known[$erpStorehouseId])) return $known[$erpStorehouseId];
        $row = $this->one($this->erp, 'SELECT st_name FROM company_shops_storehouses WHERE id=?', [$erpStorehouseId]);
        if ($row && preg_match('/^\s*(\d{3,4})\b/', (string)$row['st_name'], $m) === 1) return 'Bodega ' . $m[1];
        return 'ERP ' . $erpStorehouseId;
    }

    /**
     * Registra un asiento en el kardex (production_supply_audit). Se llama SIEMPRE dentro de la
     * misma transacción del cambio que documenta: si el cambio se revierte, el asiento también.
     *
     * @param array<string,mixed> $r Fila de la solicitud.
     * @param array<string,mixed> $actor Usuario que ejecuta la acción.
     */
    private function audit(array $r, string $action, array $actor, ?string $from, ?string $to,
        ?string $dispatchKey = null, ?string $code = null, ?float $qty = null, string $detail = ''): void
    {
        $this->trz->prepare('INSERT INTO production_supply_audit
            (audit_key,created_at,actor,action,request_key,dispatch_key,from_warehouse,to_warehouse,sku,code,quantity,state_from,state_to,detail)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
            bin2hex(random_bytes(16)), time(), json_encode($actor, JSON_UNESCAPED_UNICODE), $action, $r['request_key'], $dispatchKey,
            $this->warehouseLabel((int)$r['source_storehouse_id']), $this->warehouseLabel(16), $r['sku'], $code, $qty,
            $from, $to, $detail !== '' ? $detail : null,
        ]);
    }

    private function statusLabel(string $status): string
    {
        return ['PENDING' => 'Pendiente', 'ACCEPTED' => 'En preparación', 'REJECTED' => 'Rechazada',
            'CANCELLED' => 'Cancelada', 'DISPATCHED' => 'Despachada', 'RECEIVED' => 'Recibida'][$status] ?? $status;
    }

    private function assertWarehouseRequest(array $r): void
    {
        if ((int)$r['source_storehouse_id'] === 16) {
            throw new RuntimeException('Registro anterior: crea una nueva solicitud desde las bodegas 100, 200, 900, 910 o 920.');
        }
    }

    /** El encargado de la bodega de origen ACEPTA la solicitud: pasa a EN PREPARACIÓN. */
    public function accept(string $requestKey, array $actor): void
    {
        $who = $this->actor($actor);
        $this->ensureSchema();
        $this->trz->beginTransaction();
        try {
            $r = $this->requestRow($requestKey);
            $this->assertWarehouseRequest($r);
            if ($r['status'] !== 'PENDING') {
                throw new RuntimeException('La solicitud ya no está pendiente (estado actual: ' . $this->statusLabel((string)$r['status']) . ').');
            }
            $upd = $this->trz->prepare("UPDATE production_supply_requests SET status='ACCEPTED',accepted_by=?,accepted_at=?
                WHERE request_key=? AND status='PENDING'");
            $upd->execute([$who, time(), $requestKey]);
            if ($upd->rowCount() !== 1) throw new RuntimeException('Otra persona ya tomó esta solicitud.');
            $this->audit($r, 'ACCEPT', $actor, 'PENDING', 'ACCEPTED');
            $this->event('SUPPLY_ACCEPTED', ['request_key' => $requestKey, 'actor' => $actor]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        }
    }

    /** RECHAZA una solicitud pendiente. El motivo es obligatorio. */
    public function reject(string $requestKey, string $reason, array $actor): void
    {
        $who = $this->actor($actor);
        $reason = trim($reason);
        if ($reason === '') throw new RuntimeException('Indica el motivo del rechazo.');
        $this->ensureSchema();
        $this->trz->beginTransaction();
        try {
            $r = $this->requestRow($requestKey);
            $this->assertWarehouseRequest($r);
            if ($r['status'] !== 'PENDING') {
                throw new RuntimeException('Solo se pueden rechazar solicitudes pendientes (estado actual: ' . $this->statusLabel((string)$r['status']) . ').');
            }
            $upd = $this->trz->prepare("UPDATE production_supply_requests SET status='REJECTED',rejected_by=?,rejected_at=?,reject_reason=?
                WHERE request_key=? AND status='PENDING'");
            $upd->execute([$who, time(), $reason, $requestKey]);
            if ($upd->rowCount() !== 1) throw new RuntimeException('La solicitud cambió de estado; actualiza la pantalla.');
            $this->audit($r, 'REJECT', $actor, 'PENDING', 'REJECTED', null, null, null, $reason);
            $this->event('SUPPLY_REJECTED', ['request_key' => $requestKey, 'actor' => $actor, 'reason' => $reason]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        }
    }

    /**
     * CANCELA una solicitud pendiente o en preparación (antes de despachar). Libera las bobinas
     * ya escaneadas. No se puede cancelar si ya hay un despacho; en ese caso se cancela el despacho.
     */
    public function cancelRequest(string $requestKey, string $reason, array $actor): void
    {
        $who = $this->actor($actor);
        $reason = trim($reason);
        if ($reason === '') throw new RuntimeException('Indica el motivo de la cancelación.');
        $this->ensureSchema();
        $this->trz->beginTransaction();
        try {
            $r = $this->requestRow($requestKey);
            $this->assertWarehouseRequest($r);
            if (!in_array($r['status'], ['PENDING', 'ACCEPTED'], true)) {
                throw new RuntimeException('No se puede cancelar una solicitud en estado ' . $this->statusLabel((string)$r['status']) . '.');
            }
            $open = $this->one($this->trz, "SELECT dispatch_key FROM production_supply_dispatches WHERE request_key=? AND state!='CANCELLED'", [$requestKey]);
            if ($open) throw new RuntimeException('La solicitud ya tiene un despacho. Resuelve o cancela primero el despacho.');
            $scans = $this->trz->prepare("SELECT scan_key,code,quantity FROM production_supply_scans WHERE request_key=? AND state='SCANNED'");
            $scans->execute([$requestKey]);
            foreach ($scans->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $this->trz->prepare("UPDATE production_supply_scans SET state='VOIDED',active_code=NULL,voided_by=?,voided_at=?,void_reason=? WHERE scan_key=?")
                    ->execute([$who, time(), 'Solicitud cancelada: ' . $reason, $s['scan_key']]);
                $this->audit($r, 'SCAN_VOID', $actor, 'SCANNED', 'VOIDED', null, $s['code'], (float)$s['quantity'], 'Solicitud cancelada');
            }
            $this->trz->prepare("UPDATE production_supply_requests SET status='CANCELLED',cancelled_by=?,cancelled_at=?,cancel_reason=? WHERE request_key=?")
                ->execute([$who, time(), $reason, $requestKey]);
            $this->audit($r, 'CANCEL', $actor, (string)$r['status'], 'CANCELLED', null, null, null, $reason);
            $this->event('SUPPLY_REQUEST_CANCELLED', ['request_key' => $requestKey, 'actor' => $actor, 'reason' => $reason]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        }
    }

    /**
     * Registra el ESCANEO de una bobina para una solicitud en preparación. Si cualquier validación
     * falla se lanza un mensaje claro y NO se registra nada.
     */
    public function scan(string $requestKey, string $code, array $actor): void
    {
        $who = $this->actor($actor);
        $code = trim($code);
        if ($code === '') throw new RuntimeException('Escanea o escribe un código de trazabilidad.');
        if (!preg_match('/^[A-Za-z0-9._\-\/]{3,60}$/D', $code)) {
            throw new RuntimeException('El código contiene caracteres no válidos: ' . mb_substr($code, 0, 40));
        }
        $this->ensureSchema();
        $this->trz->beginTransaction();
        try {
            $r = $this->requestRow($requestKey);
            $this->assertWarehouseRequest($r);
            if ($r['status'] !== 'ACCEPTED') {
                throw new RuntimeException('La solicitud debe estar aceptada para escanear (estado actual: ' . $this->statusLabel((string)$r['status']) . ').');
            }
            if ($this->isBoxRequest($r)) {
                $this->scanBox($r, $code, $actor, $who);
                $this->trz->commit();
                return;
            }
            if (!str_starts_with(strtoupper((string)$r['sku']), 'TEL')) {
                throw new RuntimeException('Este material (' . $r['sku'] . ') se entrega por cantidad; no requiere escanear códigos.');
            }
            $roll = $this->roll($code);
            $already = $this->one($this->trz, 'SELECT request_key,state FROM production_supply_scans WHERE active_code=?', [$code]);
            if ($already) {
                throw new RuntimeException('El código ' . $code . ' ya fue escaneado' . ($already['request_key'] === $requestKey
                    ? ' en esta solicitud.' : ' en la solicitud ' . substr((string)$already['request_key'], 0, 8) . '.'));
            }
            if ($roll['status'] !== 'RECEIVED') {
                throw new RuntimeException('El código ' . $code . ' no está disponible (estado ' . $roll['status'] . ': bloqueado, en proceso o consumido).');
            }
            if (!empty($roll['current_work_order_id'])) {
                throw new RuntimeException('El código ' . $code . ' está asignado a una OT; no se puede traspasar.');
            }
            if ((int)$roll['erp_storehouse_id'] !== (int)$r['source_storehouse_id']) {
                throw new RuntimeException('El código ' . $code . ' no está en la bodega de origen (' . $this->warehouseLabel((int)$r['source_storehouse_id']) . ').');
            }
            // Bobina de OTRO código: solo si es compatible con lo que necesita la OT (material, color, ancho).
            $substitute = (string)$roll['sku'] !== (string)$r['sku'] ? 1 : 0;
            if ($substitute === 1) {
                $this->assertCompatibleSubstitute($r, (string)$roll['sku'], $code);
            }
            // Ya no hay tope: el bodeguero puede enviar más unidades que las solicitadas (con motivo al despachar).
            $this->trz->prepare("INSERT INTO production_supply_scans
                (scan_key,request_key,roll_id,code,quantity,state,active_code,scanned_by,scanned_at,sku,meters,substitute)
                VALUES (?,?,?,?,?,'SCANNED',?,?,?,?,?,?)")
                ->execute([bin2hex(random_bytes(16)), $requestKey, (int)$roll['id'], $code, (float)$roll['received_qty'], $code, $who, time(),
                    (string)$roll['sku'], isset($roll['meters']) && (float)$roll['meters'] > 0 ? (float)$roll['meters'] : null, $substitute]);
            $this->audit($r, 'SCAN', $actor, null, 'SCANNED', null, $code, (float)$roll['received_qty']);
            $this->trz->commit();
        } catch (PDOException $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            // Violación del índice UNIQUE(active_code): otra persona escaneó la misma bobina al mismo tiempo.
            if ((string)$e->getCode() === '23000') throw new RuntimeException('El código ' . $code . ' ya fue escaneado por otra persona.', 0, $e);
            throw $e;
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        }
    }

    /** Escaneo de una caja de bolsas: debe estar disponible en la bodega de origen y ser del producto pedido. */
    private function scanBox(array $r, string $code, array $actor, string $who): void
    {
        $box = $this->bagBoxes()->find($code, true);
        if (!$box) throw new RuntimeException('El código ' . $code . ' no es una caja registrada. Etiquétala con el ingreso trazable antes de despachar.');
        if (!BagBoxService::isErpLinked($box)) {
            throw new RuntimeException('La caja ' . $code . ' no tiene artículo o bodega del ERP: no se puede traspasar con kardex. Etiquétala de nuevo con el ingreso trazable.');
        }
        $already = $this->one($this->trz, 'SELECT request_key FROM production_supply_scans WHERE active_code=?', [(string)$box['box_code']]);
        if ($already) {
            throw new RuntimeException('La caja ' . $box['box_code'] . ' ya fue escaneada' . ($already['request_key'] === $r['request_key'] ? ' en esta solicitud.' : ' en otra solicitud.'));
        }
        if ((string)$box['status'] !== 'IN_WAREHOUSE' || (float)$box['units_qty'] <= 0) {
            throw new RuntimeException('La caja ' . $box['box_code'] . ' no está disponible (estado ' . $box['status'] . ').');
        }
        if ((int)$box['erp_st_id'] !== (int)$r['source_storehouse_id']) {
            throw new RuntimeException('La caja ' . $box['box_code'] . ' no está en la bodega de origen (' . $this->warehouseLabel((int)$r['source_storehouse_id']) . ').');
        }
        if ((int)$box['erp_item_id'] !== (int)$r['item_id']) {
            throw new RuntimeException('La caja ' . $box['box_code'] . ' es de ' . $box['final_sku'] . ', no de ' . $r['sku'] . '.');
        }
        $this->trz->prepare("INSERT INTO production_supply_scans
            (scan_key,request_key,roll_id,code,quantity,state,active_code,scanned_by,scanned_at,sku,meters,substitute,entity_type)
            VALUES (?,?,?,?,?,'SCANNED',?,?,?,?,NULL,0,'BOX')")
            ->execute([bin2hex(random_bytes(16)), $r['request_key'], (int)$box['id'], (string)$box['box_code'], (float)$box['units_qty'],
                (string)$box['box_code'], $who, time(), (string)$box['final_sku']]);
        $this->audit($r, 'SCAN', $actor, null, 'SCANNED', null, (string)$box['box_code'], (float)$box['units_qty']);
    }

    /** Artículo ERP activo por código (SKU) para evaluar o despachar una bobina de reemplazo. */
    private function erpItemBySku(string $sku, int $source): ?array
    {
        return $this->one($this->erp, "SELECT i.id AS item_id, i.item_number_prod AS sku, i.item_title AS description,
                i.item_reg_width AS width_cm, css.st_name AS warehouse_name, css.id AS source_storehouse_id
            FROM item i JOIN company_shops_storehouses css ON css.id=?
            WHERE i.item_number_prod=? AND (i.item_status>0 OR i.item_status IS NULL) LIMIT 1", [$source, $sku]);
    }

    /**
     * Una bobina de otro código solo se puede enviar si cumple lo que necesita la OT (material, color y ancho,
     * con las mismas reglas que la pantalla de la OT). Sin OT planificada se compara con el artículo solicitado.
     * Un criterio sin dato no bloquea; uno que no coincide sí.
     */
    private function assertCompatibleSubstitute(array $r, string $rollSku, string $code): void
    {
        require_once __DIR__ . '/MaterialSupplyRecommendations.php';
        $source = (int)$r['source_storehouse_id'];
        $candidate = $this->erpItemBySku($rollSku, $source);
        if (!$candidate) {
            throw new RuntimeException('El código ' . $code . ' es de ' . $rollSku . ', que no existe o está inactivo en el ERP.');
        }
        $ctx = json_decode((string)$r['context'], true) ?: [];
        $agenda = is_array($ctx['planning_details'] ?? null) && $ctx['planning_details'] ? $ctx['planning_details'] : null;
        if ($agenda !== null) {
            $checks = MaterialSupplyRecommendations::roll($candidate, $agenda)['checks'];
        } else {
            $requested = $this->erpItemBySku((string)$r['sku'], $source)
                ?? ['sku' => $r['sku'], 'description' => $r['description'], 'width_cm' => 0, 'source_storehouse_id' => $source, 'warehouse_name' => ''];
            $checks = MaterialSupplyRecommendations::compareWithItem($candidate, $requested);
        }
        $failed = array_keys(array_filter($checks, static fn($v) => $v === false));
        if ($failed) {
            throw new RuntimeException('El código ' . $code . ' (' . $rollSku . ') no es compatible con lo que necesita la OT: ' . implode(', ', $failed) . '.');
        }
    }

    /** Metros habituales del material solicitado: el del título del artículo o, si no, el más frecuente en stock. */
    private function referenceMeters(array $r): float
    {
        if (preg_match('/(\d{2,5})\s*(?:mts?|metros)\b/i', (string)$r['description'], $m) === 1) {
            return (float)$m[1];
        }
        try {
            $row = $this->one($this->trz, 'SELECT ro.meters AS meters, COUNT(*) AS c FROM rolls ro JOIN skus s ON s.id=ro.sku_id
                WHERE s.code=? AND ro.meters IS NOT NULL AND ro.meters>0 GROUP BY ro.meters ORDER BY c DESC LIMIT 1', [(string)$r['sku']]);
            return $row ? (float)$row['meters'] : 0.0;
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    /** Anula un escaneo todavía no despachado (se pidió corregir). Queda registrado con su motivo. */
    public function voidScan(string $scanKey, string $reason, array $actor): void
    {
        $who = $this->actor($actor);
        $reason = trim($reason);
        if ($reason === '') throw new RuntimeException('Indica el motivo de la anulación.');
        $this->ensureSchema();
        $this->trz->beginTransaction();
        try {
            $s = $this->one($this->trz, 'SELECT * FROM production_supply_scans WHERE scan_key=?' . $this->lock(), [$scanKey]);
            if (!$s) throw new RuntimeException('Escaneo no encontrado.');
            $r = $this->requestRow($s['request_key']);
            if ($s['state'] !== 'SCANNED') throw new RuntimeException('Solo se pueden anular escaneos aún no despachados.');
            $pending = $this->one($this->trz, "SELECT dispatch_key FROM production_supply_dispatches WHERE dispatch_key=? AND state='PENDING'", [(string)$s['dispatch_key']]);
            if ($pending) throw new RuntimeException('Hay un despacho pendiente con esta bobina; resuélvelo primero.');
            $this->trz->prepare("UPDATE production_supply_scans SET state='VOIDED',active_code=NULL,voided_by=?,voided_at=?,void_reason=? WHERE scan_key=?")
                ->execute([$who, time(), $reason, $scanKey]);
            $this->audit($r, 'SCAN_VOID', $actor, 'SCANNED', 'VOIDED', null, $s['code'], (float)$s['quantity'], $reason);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        }
    }

    /** Escaneos de una solicitud (para la pantalla de preparación). */
    public function scans(string $requestKey): array
    {
        $this->ensureSchema();
        $stmt = $this->trz->prepare("SELECT * FROM production_supply_scans WHERE request_key=? AND state!='VOIDED' ORDER BY scanned_at,scan_key");
        $stmt->execute([$requestKey]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cierra la recepción de un despacho (dentro de la transacción del llamador): actualiza el despacho,
     * los escaneos, el estado de la solicitud y el kardex. $via indica quién confirmó.
     */
    private function finishReceipt(array $d, array $r, array $actor, string $who, string $via, string $comment = ''): void
    {
        $now = time();
        $this->trz->prepare("UPDATE production_supply_dispatches SET state='RECEIVED',received_by=?,received_at=?,receive_comment=? WHERE dispatch_key=?")
            ->execute([$who, $now, $comment !== '' ? $comment : null, $d['dispatch_key']]);
        if ($comment !== '') $via .= ' | Comentario del operador: ' . $comment;
        $r['sku'] = (string)(json_decode((string)$d['payload'], true)['sku'] ?? $r['sku']);
        $this->trz->prepare("UPDATE production_supply_scans SET state='RECEIVED',active_code=NULL,received_by=?,received_at=?
            WHERE dispatch_key=? AND state='DISPATCHED'")->execute([$who, $now, $d['dispatch_key']]);
        $scans = $this->trz->prepare("SELECT code,quantity FROM production_supply_scans WHERE dispatch_key=? AND state='RECEIVED'");
        $scans->execute([$d['dispatch_key']]);
        $rows = $scans->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $s) {
            $this->audit($r, 'RECEIVE', $actor, 'DISPATCHED', 'RECEIVED', $d['dispatch_key'], $s['code'], (float)$s['quantity'], $via);
        }
        if (!$rows) $this->audit($r, 'RECEIVE', $actor, 'DISPATCHED', 'RECEIVED', $d['dispatch_key'], null, (float)$d['quantity'], $via);
        $sum = $this->one($this->trz, "SELECT COALESCE(SUM(quantity),0) AS qty FROM production_supply_dispatches WHERE request_key=? AND state='RECEIVED'", [$r['request_key']]);
        if ((float)$sum['qty'] + 0.000001 >= (float)$r['requested_qty']) {
            $this->trz->prepare("UPDATE production_supply_requests SET status='RECEIVED' WHERE request_key=? AND status='DISPATCHED'")
                ->execute([$r['request_key']]);
        }
        $this->event('SUPPLY_RECEIVED', ['request_key' => $r['request_key'], 'dispatch_key' => $d['dispatch_key'], 'actor' => $actor,
            'quantity' => (float)$d['quantity'], 'erp_transfer_id' => (int)$d['erp_transfer_id'], 'via' => $via]);
    }

    /**
     * Escanea los códigos pegados en el formulario de despacho (uno por línea) antes de despachar.
     * Los que ya estaban escaneados en esta misma solicitud se omiten; cualquier otro error se informa.
     */
    private function prescanCodes(string $dispatchKey, string $requestKey, string $codes, array $actor): void
    {
        if (trim($codes) === '') return;
        if ($this->one($this->trz, 'SELECT dispatch_key FROM production_supply_dispatches WHERE dispatch_key=?', [$dispatchKey])) return;
        foreach (array_values(array_unique(array_filter(preg_split('/[\s,;]+/', trim($codes)) ?: []))) as $code) {
            if ($this->one($this->trz, "SELECT scan_key FROM production_supply_scans WHERE request_key=? AND code=? AND state='SCANNED'", [$requestKey, $code])) continue;
            $this->scan($requestKey, $code, $actor);
        }
    }

    // =========================================================================
    // REPORTES
    // =========================================================================

    /**
     * Reportes mínimos del periodo [$from,$to] (timestamps):
     *  - 'response': por bodega de origen, solicitudes, pendientes y tiempo medio de respuesta (aceptar/rechazar).
     *  - 'movements': unidades despachadas por producto y bodega.
     *  - 'differences': solicitado vs despachado vs recibido por solicitud.
     *
     * @return array{response: array, movements: array, differences: array, changes: array}
     */
    public function report(int $from, int $to): array
    {
        $this->ensureSchema();
        $target = $this->target();
        $q = function (string $sql, array $params): array {
            $stmt = $this->trz->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        };
        $response = $q("SELECT source_storehouse_id,
                COUNT(*) AS total,
                SUM(CASE WHEN status='PENDING' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status='REJECTED' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN status='CANCELLED' THEN 1 ELSE 0 END) AS cancelled,
                AVG(CASE WHEN accepted_at IS NOT NULL THEN accepted_at-created_at
                         WHEN rejected_at IS NOT NULL THEN rejected_at-created_at END) AS avg_response_seconds
            FROM production_supply_requests WHERE erp_target=? AND created_at BETWEEN ? AND ?
            GROUP BY source_storehouse_id ORDER BY source_storehouse_id", [$target, $from, $to]);
        $movements = $q("SELECT r.sku, r.description, r.source_storehouse_id, r.unit,
                COUNT(DISTINCT d.dispatch_key) AS dispatches, SUM(d.quantity) AS quantity
            FROM production_supply_dispatches d JOIN production_supply_requests r ON r.request_key=d.request_key
            WHERE r.erp_target=? AND d.state IN ('DISPATCHED','RECEIVED') AND d.delivered_at BETWEEN ? AND ?
            GROUP BY r.sku, r.description, r.source_storehouse_id, r.unit ORDER BY r.sku, r.source_storehouse_id", [$target, $from, $to]);
        $differences = $q("SELECT r.request_key, r.sku, r.source_storehouse_id, r.status, r.requested_qty,
                COALESCE((SELECT SUM(d.quantity) FROM production_supply_dispatches d WHERE d.request_key=r.request_key AND d.state IN ('DISPATCHED','RECEIVED')),0) AS dispatched_qty,
                COALESCE((SELECT SUM(d.quantity) FROM production_supply_dispatches d WHERE d.request_key=r.request_key AND d.state='RECEIVED'),0) AS received_qty
            FROM production_supply_requests r WHERE r.erp_target=? AND r.created_at BETWEEN ? AND ?
              AND r.status IN ('ACCEPTED','DISPATCHED','RECEIVED')
            ORDER BY r.created_at DESC LIMIT 500", [$target, $from, $to]);
        $changes = $q("SELECT r.request_key, r.sku AS requested_sku, d.dispatch_key, d.quantity, d.change_flags, d.change_reason, d.receive_comment, d.state
            FROM production_supply_dispatches d JOIN production_supply_requests r ON r.request_key=d.request_key
            WHERE r.erp_target=? AND d.change_flags IS NOT NULL AND d.change_flags!='' AND d.created_at BETWEEN ? AND ?
            ORDER BY d.created_at DESC LIMIT 500", [$target, $from, $to]);
        return ['response' => $response, 'movements' => $movements, 'differences' => $differences, 'changes' => $changes];
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
            if ($this->one($this->erp,'SELECT id FROM storehousechanges WHERE strc_desc LIKE ? OR strc_desc LIKE ?',['%[TRZ-SUPPLY:'.$key.']%','%[TRZ-LED:supply.'.$key.'.%'])) {
                throw new RuntimeException('Existe un traspaso ERP. Reintenta o concilia; no se puede cancelar.');
            }
            $payload=json_decode($d['payload'],true,512,JSON_THROW_ON_ERROR);
            $payload['cancelled_by']=$actor;
            $payload['cancellation_reason']=trim($reason);
            $this->trz->prepare("UPDATE production_supply_dispatches SET state='CANCELLED',payload=?,error_message=NULL WHERE dispatch_key=?")
                ->execute([json_encode($payload,JSON_THROW_ON_ERROR),$key]);
            // Las bobinas escaneadas se desligan del despacho cancelado (siguen escaneadas, listas para reintentar).
            $this->trz->prepare("UPDATE production_supply_scans SET dispatch_key=NULL WHERE dispatch_key=? AND state='SCANNED'")->execute([$key]);
            $this->audit($this->requestRow($d['request_key']),'DISPATCH_CANCEL',$actor,'PENDING','CANCELLED',$key,null,(float)$d['quantity'],trim($reason));
            $this->event('SUPPLY_DISPATCH_CANCELLED',['dispatch_key'=>$key,'request_key'=>$d['request_key'],'actor'=>$actor,'reason'=>trim($reason)]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        } finally {
            if ($locked) $this->erp->exec('UNLOCK TABLES');
        }
    }

    /**
     * Traspasa cada caja a la Bodega 3000: su saldo y su CC, con documento y kardex (StockLedgerService, idempotente por caja).
     * Al reintentar, una caja ya traspasada por este despacho no se mueve otra vez.
     */
    private function transferBoxes(array $r, array $d, array $payload, array $actor): int
    {
        require_once __DIR__ . '/StockLedgerService.php';
        $ledger = new StockLedgerService($this->erp);
        $bags = $this->bagBoxes();
        $firstId = 0;
        foreach ($payload['boxes'] as $expected) {
            $box = $bags->byId((int)$expected['id'], true);
            $key = 'supply.' . $d['dispatch_key'] . '.' . (int)$expected['id'];
            $done = $box && (int)$box['erp_st_id'] === 16;
            if (!$box || (!$done && ((string)$box['status'] !== 'IN_WAREHOUSE' || (int)$box['erp_st_id'] !== (int)$expected['from_st']
                || abs((float)$box['units_qty'] - (float)$expected['qty']) > 0.0005))) {
                throw new RuntimeException('La caja ' . $expected['code'] . ' cambió durante el despacho; requiere revisión.');
            }
            $res = $ledger->transfer(['key' => $key, 'item_id' => (int)$r['item_id'], 'from_st' => (int)$expected['from_st'], 'to_st' => 16,
                'qty' => (float)$expected['qty'], 'order_id' => (int)$expected['order_id'], 'user_id' => (int)($actor['user_id'] ?? 0),
                'allow_negative' => true,
                'text' => 'Caja ' . $expected['code'] . ' a 3000 Insumos en Producción. Solicitud ' . substr((string)$r['request_key'], 0, 8)
                    . ', máquina ' . ($payload['context']['machine_name'] ?? '') . '.']);
            $firstId = $firstId ?: (int)$res['id'];
            if (!$done) {
                $fromWh = $box['warehouse_id'] !== null ? (int)$box['warehouse_id'] : null;
                $toWh = $bags->localWarehouseId(16);
                $bags->update((int)$box['id'], ['erp_st_id' => 16, 'warehouse_id' => $toWh]);
                $bags->movement((int)$box['id'], 'TRANSFER', $fromWh, $toWh, ['dispatch_key' => $d['dispatch_key'], 'erp_transfer' => $res['number'], 'units' => (float)$expected['qty']]);
            }
        }
        return $firstId;
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
