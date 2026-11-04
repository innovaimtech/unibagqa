<?php

declare(strict_types=1);

/**
 * Servicio de Despacho (Bodega): picking de pallets y cajas, validación de la guía de despacho del ERP y
 * salida trazable de los códigos.
 *
 * Flujo: 1) crear picking (venta directa o maquila) y escanear códigos; 2) cerrarlo (queda esperando guía);
 * 3) ingresar el número de guía: se traen sus datos del ERP y se comparan; 4) al confirmar, los códigos salen
 * de la bodega (TRZ) y quedan asociados a la guía con movimientos, evento y copia de la guía.
 *
 * El ERP ya descuenta el stock al confirmar la guía; por eso este servicio NO escribe en el ERP (solo lo lee).
 */
final class DispatchService
{
    public const TYPE_SALE = 'SALE';
    public const TYPE_MAQUILA = 'MAQUILA';
    private const SCHEMA_VERSION = 'dispatch-2026-11-04-a';

    private bool $ready = false;

    public function __construct(private PDO $erp, private PDO $trz)
    {
    }

    public static function typeLabel(string $type): string
    {
        return $type === self::TYPE_MAQUILA ? 'Envío a procesar (maquila)' : 'Venta directa';
    }

    public static function statusLabel(string $status): string
    {
        return [
            'PICKING' => 'Pickeando',
            'AWAITING_GUIDE' => 'Esperando guía',
            'DISPATCHED' => 'Despachado',
            'CANCELLED' => 'Cancelado',
        ][$status] ?? $status;
    }

    private function sqlite(): bool
    {
        return $this->trz->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function lock(): string
    {
        return $this->sqlite() ? '' : ' FOR UPDATE';
    }

    private function one(string $sql, array $params = [], ?PDO $pdo = null): ?array
    {
        $st = ($pdo ?? $this->trz)->prepare($sql);
        $st->execute($params);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function all(string $sql, array $params = [], ?PDO $pdo = null): array
    {
        $st = ($pdo ?? $this->trz)->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function ensureSchema(): void
    {
        if ($this->ready) {
            return;
        }
        $useGate = !$this->sqlite() && class_exists('SchemaGate');
        if ($useGate && SchemaGate::done('dispatch', self::SCHEMA_VERSION)) {
            $this->ready = true;
            return;
        }
        $pk = $this->sqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $engine = $this->sqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $this->trz->exec("CREATE TABLE IF NOT EXISTS dispatch_pickings (
            id $pk,
            picking_key VARCHAR(32) NOT NULL UNIQUE,
            code VARCHAR(40) NOT NULL UNIQUE,
            dispatch_type VARCHAR(10) NOT NULL,
            warehouse_id INT NOT NULL,
            note TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'PICKING',
            created_by VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            closed_at TIMESTAMP NULL,
            guide_id BIGINT NULL,
            guide_number VARCHAR(40) NULL,
            guide_snapshot TEXT NULL,
            confirmed_by VARCHAR(120) NULL,
            confirmed_at TIMESTAMP NULL,
            cancel_reason TEXT NULL
        )$engine");
        $this->trz->exec("CREATE TABLE IF NOT EXISTS dispatch_picking_items (
            id $pk,
            picking_id BIGINT NOT NULL,
            entity_type VARCHAR(10) NOT NULL,
            entity_id BIGINT NOT NULL,
            code VARCHAR(60) NOT NULL,
            sku VARCHAR(80) NULL,
            qty DECIMAL(12,3) NOT NULL DEFAULT 0,
            box_count INT NOT NULL DEFAULT 0,
            from_warehouse_id INT NULL,
            active_code VARCHAR(40) NULL UNIQUE,
            status VARCHAR(12) NOT NULL DEFAULT 'PICKED',
            void_reason TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )$engine");
        $this->trz->exec("CREATE TABLE IF NOT EXISTS dispatch_audit (
            id $pk,
            picking_id BIGINT NOT NULL,
            action VARCHAR(30) NOT NULL,
            actor VARCHAR(120) NOT NULL,
            detail TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )$engine");
        if (!$this->sqlite()) {
            foreach ([
                'CREATE INDEX idx_dpi_picking ON dispatch_picking_items (picking_id)',
                'CREATE INDEX idx_dpi_entity ON dispatch_picking_items (entity_type, entity_id)',
                'CREATE INDEX idx_dpa_picking ON dispatch_audit (picking_id)',
                'CREATE INDEX idx_dp_status ON dispatch_pickings (status)',
            ] as $ddl) {
                try {
                    $this->trz->exec($ddl);
                } catch (Throwable) {
                    // El índice ya existe.
                }
            }
        }
        if ($useGate) {
            SchemaGate::mark('dispatch', self::SCHEMA_VERSION);
        }
        $this->ready = true;
    }

    private function actorName(array $actor): string
    {
        $name = trim((string)($actor['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('No se pudo identificar al usuario que realiza el despacho.');
        }
        return mb_substr($name, 0, 120);
    }

    private function audit(int $pickingId, string $action, string $actor, array $detail = []): void
    {
        $this->trz->prepare('INSERT INTO dispatch_audit (picking_id, action, actor, detail) VALUES (?,?,?,?)')
            ->execute([$pickingId, $action, $actor, json_encode($detail, JSON_UNESCAPED_UNICODE)]);
    }

    /** Bodegas internas disponibles como origen del picking. */
    public function warehouses(): array
    {
        return $this->all('SELECT id, code, name FROM warehouses ORDER BY code ASC');
    }

    // ------------------------------------------------------------------ picking

    public function createPicking(string $key, string $type, int $warehouseId, string $note, array $actor): string
    {
        $this->ensureSchema();
        $who = $this->actorName($actor);
        if (preg_match('/^[a-f0-9]{32}$/D', $key) !== 1) {
            throw new RuntimeException('Identificador de picking inválido. Recarga la página.');
        }
        if (!in_array($type, [self::TYPE_SALE, self::TYPE_MAQUILA], true)) {
            throw new RuntimeException('Indica si es venta directa o envío a procesar a otra empresa.');
        }
        if ($this->one('SELECT id FROM dispatch_pickings WHERE picking_key = ?', [$key])) {
            return $key;
        }
        if (!$this->one('SELECT id FROM warehouses WHERE id = ?', [$warehouseId])) {
            throw new RuntimeException('Selecciona la bodega de origen del picking.');
        }
        $this->trz->beginTransaction();
        try {
            $this->trz->prepare('INSERT INTO dispatch_pickings (picking_key, code, dispatch_type, warehouse_id, note, status, created_by)
                VALUES (?,?,?,?,?,?,?)')
                ->execute([$key, $key, $type, $warehouseId, trim($note) !== '' ? trim($note) : null, 'PICKING', $who]);
            $id = (int)$this->trz->lastInsertId();
            $this->trz->prepare('UPDATE dispatch_pickings SET code = ? WHERE id = ?')
                ->execute([sprintf('DSP-%s-%04d', date('Ym'), $id), $id]);
            $this->audit($id, 'CREATE', $who, ['type' => $type, 'warehouse_id' => $warehouseId]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
        return $key;
    }

    private function picking(string $key, bool $forUpdate = false): array
    {
        $row = $this->one('SELECT * FROM dispatch_pickings WHERE picking_key = ?' . ($forUpdate ? $this->lock() : ''), [$key]);
        if (!$row) {
            throw new RuntimeException('El picking no existe.');
        }
        return $row;
    }

    /** Resuelve un código leído a un pallet o una caja de TRZ (por código propio o por código de ingreso trazable). */
    private function resolveCode(string $raw): array
    {
        $code = strtoupper(preg_replace('/\s+/', '', trim($raw)) ?? '');
        if (strlen($code) < 3) {
            throw new RuntimeException('Escanea o escribe un código válido.');
        }
        $pallet = $this->one('SELECT * FROM pallets WHERE UPPER(pallet_code) = ?', [$code]);
        if ($pallet) {
            return ['PALLET', $pallet];
        }
        $box = $this->one('SELECT * FROM boxes WHERE UPPER(box_code) = ?', [$code]);
        if ($box) {
            return ['BOX', $box];
        }
        try {
            $entry = $this->one("SELECT entity_type, entity_id FROM traceability_warehouse_entries
                WHERE UPPER(REPLACE(traceability_code, ' ', '')) = ? AND entity_type IN ('PALLET','BOX') AND entity_id IS NOT NULL
                ORDER BY id DESC LIMIT 1", [$code]);
        } catch (Throwable) {
            $entry = null;
        }
        if ($entry) {
            $table = $entry['entity_type'] === 'PALLET' ? 'pallets' : 'boxes';
            $row = $this->one("SELECT * FROM $table WHERE id = ?", [(int)$entry['entity_id']]);
            if ($row) {
                return [$entry['entity_type'], $row];
            }
        }
        throw new RuntimeException('Código no registrado como pallet o caja: ' . $code . '. En despacho solo se pickean pallets y cajas.');
    }

    private function isActive(string $type, int $id): ?array
    {
        return $this->one("SELECT i.id, p.code AS picking_code FROM dispatch_picking_items i
            JOIN dispatch_pickings p ON p.id = i.picking_id
            WHERE i.entity_type = ? AND i.entity_id = ? AND i.status = 'PICKED'", [$type, $id]);
    }

    public function scan(string $key, string $rawCode, array $actor): string
    {
        $this->ensureSchema();
        $who = $this->actorName($actor);
        [$type, $row] = $this->resolveCode($rawCode);
        $this->trz->beginTransaction();
        try {
            $p = $this->picking($key, true);
            if ($p['status'] !== 'PICKING') {
                throw new RuntimeException('El picking ya está cerrado; no se pueden agregar códigos.');
            }
            $status = strtoupper((string)($row['status'] ?? ''));
            if (in_array($status, ['DISPATCHED', 'IN_MAQUILA'], true)) {
                throw new RuntimeException('El código ' . ($row[$type === 'PALLET' ? 'pallet_code' : 'box_code']) . ' ya salió de la bodega (' . $status . ').');
            }
            if ((int)($row['warehouse_id'] ?? 0) !== (int)$p['warehouse_id']) {
                throw new RuntimeException('El código no está en la bodega de origen de este picking.');
            }
            $id = (int)$row['id'];
            $label = (string)($row[$type === 'PALLET' ? 'pallet_code' : 'box_code']);
            if ($busy = $this->isActive($type, $id)) {
                throw new RuntimeException('El código ' . $label . ' ya está en el picking ' . $busy['picking_code'] . '.');
            }
            $qty = 0.0;
            $boxCount = 0;
            if ($type === 'PALLET') {
                $boxes = $this->all("SELECT id, units_qty FROM boxes WHERE pallet_id = ? AND status <> 'DISPATCHED'", [$id]);
                foreach ($boxes as $b) {
                    if ($this->isActive('BOX', (int)$b['id'])) {
                        throw new RuntimeException('Una caja de este pallet ya está en otro picking.');
                    }
                    $qty += (float)$b['units_qty'];
                }
                $boxCount = count($boxes);
            } else {
                if ((int)($row['pallet_id'] ?? 0) > 0 && $this->isActive('PALLET', (int)$row['pallet_id'])) {
                    throw new RuntimeException('La caja pertenece a un pallet que ya está en un picking.');
                }
                $qty = (float)$row['units_qty'];
                $boxCount = 1;
            }
            $this->trz->prepare('INSERT INTO dispatch_picking_items (picking_id, entity_type, entity_id, code, sku, qty, box_count, from_warehouse_id, active_code, status)
                VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([(int)$p['id'], $type, $id, $label, (string)($row['final_sku'] ?? ''), $qty, $boxCount,
                    (int)$p['warehouse_id'], $type . ':' . $id, 'PICKED']);
            $this->audit((int)$p['id'], 'SCAN', $who, ['code' => $label, 'type' => $type, 'qty' => $qty]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            if ($e instanceof PDOException && (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'Duplicate'))) {
                throw new RuntimeException('Ese código acaba de ser tomado por otro picking.');
            }
            throw $e;
        }
        return $label;
    }

    public function voidItem(int $itemId, string $reason, array $actor): void
    {
        $this->ensureSchema();
        $who = $this->actorName($actor);
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new RuntimeException('Indica el motivo para quitar el código (mínimo 5 caracteres).');
        }
        $this->trz->beginTransaction();
        try {
            $it = $this->one('SELECT i.*, p.status AS picking_status FROM dispatch_picking_items i JOIN dispatch_pickings p ON p.id = i.picking_id WHERE i.id = ?' . $this->lock(), [$itemId]);
            if (!$it || $it['status'] !== 'PICKED' || $it['picking_status'] !== 'PICKING') {
                throw new RuntimeException('Solo se puede quitar un código de un picking abierto.');
            }
            $this->trz->prepare("UPDATE dispatch_picking_items SET status = 'VOIDED', active_code = NULL, void_reason = ? WHERE id = ?")->execute([$reason, $itemId]);
            $this->audit((int)$it['picking_id'], 'VOID', $who, ['code' => $it['code'], 'reason' => $reason]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
    }

    public function close(string $key, array $actor): void
    {
        $this->ensureSchema();
        $who = $this->actorName($actor);
        $this->trz->beginTransaction();
        try {
            $p = $this->picking($key, true);
            if ($p['status'] !== 'PICKING') {
                throw new RuntimeException('El picking ya no está abierto.');
            }
            $n = (int)($this->one("SELECT COUNT(*) AS n FROM dispatch_picking_items WHERE picking_id = ? AND status = 'PICKED'", [(int)$p['id']])['n'] ?? 0);
            if ($n === 0) {
                throw new RuntimeException('Agrega al menos un pallet o caja antes de cerrar el picking.');
            }
            $this->trz->prepare("UPDATE dispatch_pickings SET status = 'AWAITING_GUIDE', closed_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([(int)$p['id']]);
            $this->audit((int)$p['id'], 'CLOSE', $who, ['items' => $n]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
    }

    public function cancel(string $key, string $reason, array $actor): void
    {
        $this->ensureSchema();
        $who = $this->actorName($actor);
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new RuntimeException('Indica el motivo de la cancelación (mínimo 5 caracteres).');
        }
        $this->trz->beginTransaction();
        try {
            $p = $this->picking($key, true);
            if (!in_array($p['status'], ['PICKING', 'AWAITING_GUIDE'], true)) {
                throw new RuntimeException('Un picking despachado o cancelado no se puede cancelar.');
            }
            $this->trz->prepare("UPDATE dispatch_picking_items SET status = 'VOIDED', active_code = NULL, void_reason = ? WHERE picking_id = ? AND status = 'PICKED'")
                ->execute(['Picking cancelado: ' . $reason, (int)$p['id']]);
            $this->trz->prepare("UPDATE dispatch_pickings SET status = 'CANCELLED', cancel_reason = ? WHERE id = ?")->execute([$reason, (int)$p['id']]);
            $this->audit((int)$p['id'], 'CANCEL', $who, ['reason' => $reason]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------ guía ERP

    /** Lee la guía del ERP por folio (dlv_docnum) o por número interno (dlv_num, con o sin prefijo GV). Solo lectura. */
    public function lookupGuide(string $number): ?array
    {
        $number = trim($number);
        if ($number === '') {
            return null;
        }
        $short = ltrim((string)preg_replace('/^GV/i', '', $number), '0');
        $st = $this->erp->prepare("SELECT od.*, c.cust_name, c.cust_rut, c.cust_street
            FROM orders_delivery od LEFT JOIN customer c ON od.dlv_cust_id = c.id
            WHERE CAST(od.dlv_docnum AS CHAR) = :n OR CAST(od.dlv_num AS CHAR) = :n2 OR CAST(od.dlv_num AS CHAR) = :n3
            ORDER BY od.id DESC LIMIT 1");
        $st->execute([':n' => $number, ':n2' => $number, ':n3' => $short !== '' ? $short : $number]);
        $g = $st->fetch(PDO::FETCH_ASSOC);
        if (!$g) {
            return null;
        }
        $it = $this->erp->prepare("SELECT odi.item_id, odi.item_amount_shipped, odi.item_desc,
                COALESCE(NULLIF(i.item_title, ''), odi.item_desc, '') AS item_title, i.item_number_prod
            FROM orders_delivery_items odi LEFT JOIN item i ON odi.item_id = i.id
            WHERE odi.dlv_id = :id ORDER BY odi.item_pos ASC");
        $it->execute([':id' => (int)$g['id']]);
        $items = [];
        foreach ($it->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $items[] = [
                'sku' => strtoupper(trim((string)($r['item_number_prod'] ?? ''))),
                'title' => (string)($r['item_title'] ?? ''),
                'qty' => (float)($r['item_amount_shipped'] ?? 0),
            ];
        }
        return [
            'id' => (int)$g['id'],
            'number' => (string)($g['dlv_num'] ?? ''),
            'docnum' => trim((string)($g['dlv_docnum'] ?? '')),
            'status' => (int)($g['dlv_status'] ?? 0),
            'mode' => isset($g['dlv_mode']) ? (int)$g['dlv_mode'] : null,
            'extern' => !empty($g['dlv_externprod_act']),
            'extern_cc' => (string)($g['dlv_externprod_ccnum'] ?? ''),
            'date' => (int)($g['dlv_delivery_date'] ?? 0) > 0 ? date('d/m/Y', (int)$g['dlv_delivery_date']) : '',
            'customer' => (string)($g['cust_name'] ?? ''),
            'rut' => (string)($g['cust_rut'] ?? ''),
            'address' => (string)($g['dlv_direccion_destino'] ?? ($g['cust_street'] ?? '')),
            'oc' => (string)($g['dlv_oc_number'] ?? ''),
            'bultos' => (string)($g['dlv_bultos'] ?? ''),
            'annotation' => (string)($g['dlv_annotation'] ?? ''),
            'items' => $items,
        ];
    }

    /** Cantidades pickeadas por SKU de un picking. */
    private function pickedBySku(int $pickingId): array
    {
        $out = [];
        foreach ($this->all("SELECT sku, SUM(qty) AS q FROM dispatch_picking_items WHERE picking_id = ? AND status IN ('PICKED','DISPATCHED') GROUP BY sku", [$pickingId]) as $r) {
            $out[strtoupper(trim((string)$r['sku']))] = (float)$r['q'];
        }
        return $out;
    }

    /**
     * Compara el picking con la guía. errors: impiden confirmar; warnings: requieren aceptación explícita.
     *
     * @return array{errors:array<int,string>,warnings:array<int,string>,rows:array<int,array<string,mixed>>}
     */
    public function compare(array $picking, ?array $guide): array
    {
        $errors = [];
        $warnings = [];
        $rows = [];
        if ($guide === null) {
            return ['errors' => ['La guía no existe en el ERP. Revisa el número.'], 'warnings' => [], 'rows' => []];
        }
        if ($guide['status'] === 5) {
            $errors[] = 'La guía está anulada en el ERP.';
        } elseif ($guide['status'] !== 2) {
            $errors[] = 'La guía aún no está finalizada en el ERP (estado ' . $guide['status'] . ').';
        }
        if ($guide['docnum'] === '' || stripos($guide['docnum'], 'PENDIENTE') !== false) {
            $errors[] = 'La guía todavía no tiene folio SII emitido.';
        }
        $used = $this->one("SELECT picking_key, code FROM dispatch_pickings WHERE guide_id = ? AND status = 'DISPATCHED' AND picking_key <> ?",
            [$guide['id'], (string)$picking['picking_key']]);
        if ($used) {
            $errors[] = 'La guía ya fue asociada al picking ' . $used['code'] . '.';
        }

        $picked = $this->pickedBySku((int)$picking['id']);
        $guideQty = [];
        foreach ($guide['items'] as $it) {
            $guideQty[$it['sku']] = ($guideQty[$it['sku']] ?? 0.0) + $it['qty'];
        }
        foreach (array_unique(array_merge(array_keys($picked), array_keys($guideQty))) as $sku) {
            $p = (float)($picked[$sku] ?? 0);
            $g = (float)($guideQty[$sku] ?? 0);
            $rows[] = ['sku' => $sku, 'picked' => $p, 'guide' => $g, 'ok' => abs($p - $g) < 0.001];
            if (abs($p - $g) >= 0.001) {
                $label = $sku !== '' ? $sku : '(sin código)';
                if ($g == 0.0) {
                    $warnings[] = "El artículo $label está pickeado pero no aparece en la guía.";
                } elseif ($p == 0.0) {
                    $warnings[] = "El artículo $label está en la guía pero no fue pickeado.";
                } else {
                    $warnings[] = "Artículo $label: pickeado " . rtrim(rtrim(number_format($p, 3, '.', ''), '0'), '.') . " y la guía indica " . rtrim(rtrim(number_format($g, 3, '.', ''), '0'), '.') . '.';
                }
            }
        }
        $guideSaysProcess = in_array($guide['mode'], [3, 4], true) || $guide['extern'];
        if ($picking['dispatch_type'] === self::TYPE_SALE && $guideSaysProcess) {
            $warnings[] = 'El picking es venta directa, pero la guía está marcada como envío a proveedor / producción externa.';
        }
        if ($picking['dispatch_type'] === self::TYPE_MAQUILA && !$guideSaysProcess) {
            $warnings[] = 'El picking es envío a procesar (maquila), pero la guía no está marcada como envío a proveedor / producción externa.';
        }
        return ['errors' => $errors, 'warnings' => $warnings, 'rows' => $rows];
    }

    /** Datos para la pantalla de validación (picking + guía + comparación). */
    public function validation(string $key, string $number): array
    {
        $this->ensureSchema();
        $p = $this->picking($key);
        $guide = $this->lookupGuide($number);
        return ['picking' => $p, 'guide' => $guide, 'compare' => $this->compare($p, $guide)];
    }

    // ------------------------------------------------------------------ confirmación

    public function confirmGuide(string $key, string $number, bool $acceptWarnings, array $actor): void
    {
        $this->ensureSchema();
        $who = $this->actorName($actor);
        $guide = $this->lookupGuide($number);
        $hasEntries = true;
        try {
            $this->trz->query('SELECT 1 FROM traceability_warehouse_entries LIMIT 0');
        } catch (Throwable) {
            $hasEntries = false;
        }
        $this->trz->beginTransaction();
        try {
            $p = $this->picking($key, true);
            if ($p['status'] === 'DISPATCHED' && $guide && (int)$p['guide_id'] === $guide['id']) {
                $this->trz->commit();
                return; // Reintento idempotente
            }
            if ($p['status'] !== 'AWAITING_GUIDE') {
                throw new RuntimeException('El picking debe estar cerrado y esperando guía para confirmarlo.');
            }
            $cmp = $this->compare($p, $guide);
            if ($cmp['errors'] !== []) {
                throw new RuntimeException(implode(' ', $cmp['errors']));
            }
            if ($cmp['warnings'] !== [] && !$acceptWarnings) {
                throw new RuntimeException('Hay diferencias entre el picking y la guía. Revísalas y confirma que aceptas continuar.');
            }
            $items = $this->all("SELECT * FROM dispatch_picking_items WHERE picking_id = ? AND status = 'PICKED' ORDER BY id" . $this->lock(), [(int)$p['id']]);
            if ($items === []) {
                throw new RuntimeException('El picking no tiene códigos.');
            }
            $ctx = [
                'operator_name' => $who,
                'movement_context' => 'DISPATCH_OUT',
                'dispatch_picking' => $p['code'],
                'dispatch_type' => $p['dispatch_type'],
                'guide_number' => $guide['number'],
                'guide_docnum' => $guide['docnum'],
                'customer' => $guide['customer'],
            ];
            foreach ($items as $it) {
                $from = (int)$it['from_warehouse_id'];
                if ($it['entity_type'] === 'PALLET') {
                    $boxes = $this->all("SELECT id, box_code FROM boxes WHERE pallet_id = ? AND status <> 'DISPATCHED'", [(int)$it['entity_id']]);
                    $this->trz->prepare("UPDATE pallets SET status = 'DISPATCHED', warehouse_id = NULL WHERE id = ?")->execute([(int)$it['entity_id']]);
                    $this->trz->prepare("UPDATE boxes SET status = 'DISPATCHED', warehouse_id = NULL WHERE pallet_id = ? AND status <> 'DISPATCHED'")->execute([(int)$it['entity_id']]);
                    $this->movement('PALLET', (int)$it['entity_id'], $from, $ctx + ['code' => $it['code']]);
                    foreach ($boxes as $b) {
                        $this->movement('BOX', (int)$b['id'], $from, $ctx + ['code' => $b['box_code'], 'pallet_code' => $it['code']]);
                    }
                    if ($hasEntries) {
                        $this->markEntries('PALLET', [(int)$it['entity_id']]);
                        $this->markEntries('BOX', array_map(static fn($b) => (int)$b['id'], $boxes));
                    }
                } else {
                    $this->trz->prepare("UPDATE boxes SET status = 'DISPATCHED', warehouse_id = NULL WHERE id = ?")->execute([(int)$it['entity_id']]);
                    $this->movement('BOX', (int)$it['entity_id'], $from, $ctx + ['code' => $it['code']]);
                    if ($hasEntries) {
                        $this->markEntries('BOX', [(int)$it['entity_id']]);
                    }
                }
                $this->trz->prepare("UPDATE dispatch_picking_items SET status = 'DISPATCHED' WHERE id = ?")->execute([(int)$it['id']]);
            }
            $this->trz->prepare("UPDATE dispatch_pickings SET status = 'DISPATCHED', guide_id = ?, guide_number = ?, guide_snapshot = ?,
                confirmed_by = ?, confirmed_at = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$guide['id'], $guide['docnum'] !== '' ? $guide['docnum'] : $guide['number'],
                    json_encode($guide, JSON_UNESCAPED_UNICODE), $who, (int)$p['id']]);
            $this->trz->prepare('INSERT INTO events (type, payload) VALUES (?, ?)')
                ->execute(['DISPATCH_CONFIRMED', json_encode($ctx + ['items' => count($items), 'warnings' => $cmp['warnings']], JSON_UNESCAPED_UNICODE)]);
            $this->audit((int)$p['id'], 'CONFIRM', $who, ['guide_id' => $guide['id'], 'guide' => $guide['docnum'], 'warnings' => $cmp['warnings']]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
    }

    private function movement(string $type, int $id, int $fromWarehouse, array $payload): void
    {
        $this->trz->prepare("INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
            VALUES (?, ?, 'TRANSFER', ?, NULL, ?)")
            ->execute([$type, $id, $fromWarehouse > 0 ? $fromWarehouse : null, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }

    private function markEntries(string $type, array $ids): void
    {
        foreach ($ids as $id) {
            $this->trz->prepare("UPDATE traceability_warehouse_entries SET status = 'DISPATCHED' WHERE entity_type = ? AND entity_id = ?")
                ->execute([$type, $id]);
        }
    }

    // ------------------------------------------------------------------ consultas

    public function list(string $status = '', string $type = '', string $q = '', int $limit = 100): array
    {
        $this->ensureSchema();
        $where = ['1=1'];
        $params = [];
        if (in_array($status, ['PICKING', 'AWAITING_GUIDE', 'DISPATCHED', 'CANCELLED'], true)) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }
        if (in_array($type, [self::TYPE_SALE, self::TYPE_MAQUILA], true)) {
            $where[] = 'p.dispatch_type = ?';
            $params[] = $type;
        }
        $q = trim($q);
        if ($q !== '') {
            $where[] = '(p.code LIKE ? OR p.guide_number LIKE ? OR EXISTS (SELECT 1 FROM dispatch_picking_items i WHERE i.picking_id = p.id AND i.code LIKE ?))';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        $limit = max(1, min(300, $limit));
        return $this->all("SELECT p.*, w.name AS warehouse_name,
                (SELECT COUNT(*) FROM dispatch_picking_items i WHERE i.picking_id = p.id AND i.status IN ('PICKED','DISPATCHED')) AS item_count,
                (SELECT COALESCE(SUM(i.qty),0) FROM dispatch_picking_items i WHERE i.picking_id = p.id AND i.status IN ('PICKED','DISPATCHED')) AS qty_total
            FROM dispatch_pickings p LEFT JOIN warehouses w ON w.id = p.warehouse_id
            WHERE " . implode(' AND ', $where) . " ORDER BY p.id DESC LIMIT $limit", $params);
    }

    public function detail(string $key): array
    {
        $this->ensureSchema();
        $p = $this->one('SELECT p.*, w.name AS warehouse_name FROM dispatch_pickings p LEFT JOIN warehouses w ON w.id = p.warehouse_id WHERE p.picking_key = ?', [$key]);
        if (!$p) {
            throw new RuntimeException('El picking no existe.');
        }
        $p['items'] = $this->all('SELECT * FROM dispatch_picking_items WHERE picking_id = ? ORDER BY id', [(int)$p['id']]);
        $p['audit'] = $this->all('SELECT * FROM dispatch_audit WHERE picking_id = ? ORDER BY id', [(int)$p['id']]);
        $p['guide'] = !empty($p['guide_snapshot']) ? json_decode((string)$p['guide_snapshot'], true) : null;
        return $p;
    }

    /** Despacho confirmado de un pallet o caja (para la trazabilidad 360°). */
    public function dispatchOf(string $entityType, int $entityId): ?array
    {
        try {
            $this->ensureSchema();
            return $this->one("SELECT p.code, p.dispatch_type, p.guide_number, p.guide_snapshot, p.confirmed_by, p.confirmed_at
                FROM dispatch_picking_items i JOIN dispatch_pickings p ON p.id = i.picking_id
                WHERE i.entity_type = ? AND i.entity_id = ? AND i.status = 'DISPATCHED' AND p.status = 'DISPATCHED'
                ORDER BY p.id DESC LIMIT 1", [$entityType, $entityId]);
        } catch (Throwable) {
            return null;
        }
    }
}
