<?php

declare(strict_types=1);

/**
 * Código de trazabilidad (fila ROLL en traceability_warehouse_entries) de una bobina de la tabla rolls.
 *
 * Toda bobina que se crea (recepción, impresa en Flexo/Serigrafía, sobrante, cortada o empalmada en Rebobinadora)
 * debe tener su fila: sin ella el código «no existe» para la bodega, Traspasos y Ajuste de códigos.
 * La fila usa el id de bodega del ERP (warehouses.erp_storehouse_id); la bobina en rolls sigue con el id local.
 */
final class RollTraceabilityEntry
{
    /**
     * Crea o actualiza la fila de la bobina. Devuelve el id de la fila (0 si la bobina no existe o no tiene bodega).
     *
     * @param array{order_ref?:?string,comments?:?string,item_id?:?int,item_description?:?string} $extra datos que solo se
     *        escriben si vienen (al crear la fila, o para completar una existente)
     */
    public static function sync(PDO $trz, int $rollId, string $operatorName = '', array $extra = []): int
    {
        if ($rollId <= 0) {
            return 0;
        }
        $sqlite = $trz->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $stmt = $trz->prepare(
            'SELECT r.id, r.roll_code, r.weight_kg, r.received_qty, r.reception_mode, r.microns, r.width_mm, r.meters, r.status,
                    w.id AS local_warehouse_id, w.code AS warehouse_code, w.name AS warehouse_name, w.erp_storehouse_id,
                    s.code AS sku_code, s.description AS sku_description
             FROM rolls r
             INNER JOIN warehouses w ON w.id = r.warehouse_id
             LEFT JOIN skus s ON s.id = r.sku_id
             WHERE r.id = ?
             LIMIT 1'
        );
        $stmt->execute([$rollId]);
        $roll = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$roll) {
            return 0;
        }
        $warehouseEntryId = (int)($roll['erp_storehouse_id'] ?? 0) ?: (int)($roll['local_warehouse_id'] ?? 0);
        if ($warehouseEntryId <= 0) {
            return 0;
        }

        $operatorName = trim($operatorName);
        if ($operatorName === '') {
            $operatorName = self::lastOperator($trz, $rollId, $sqlite) ?: 'Sistema';
        }
        $quantity = (float)($roll['received_qty'] ?? 1);
        if ($quantity <= 0) {
            $quantity = 1.0;
        }
        $code = (string)$roll['roll_code'];
        $params = [
            'traceability_code' => $code,
            'entity_id' => $rollId,
            'warehouse_id' => $warehouseEntryId,
            'warehouse_name' => trim((string)($roll['warehouse_name'] ?? '')) !== '' ? (string)$roll['warehouse_name'] : ('Bodega ' . (string)($roll['warehouse_code'] ?? '')),
            'item_code' => (string)($roll['sku_code'] ?? ''),
            'item_description' => trim((string)($extra['item_description'] ?? '')) !== '' ? (string)$extra['item_description'] : (string)($roll['sku_description'] ?? ''),
            'quantity' => number_format($quantity, 3, '.', ''),
            'unit_name' => ($roll['reception_mode'] ?? '') === 'QUANTITY' ? 'UN' : 'BOB',
            'weight_kg' => number_format((float)($roll['weight_kg'] ?? 0), 3, '.', ''),
            'width_mm' => isset($roll['width_mm']) && $roll['width_mm'] !== '' ? (int)$roll['width_mm'] : null,
            'microns' => isset($roll['microns']) && $roll['microns'] !== '' ? (int)$roll['microns'] : null,
            'meters' => isset($roll['meters']) && $roll['meters'] !== '' ? (float)$roll['meters'] : null,
            'operator_name' => mb_substr($operatorName, 0, 120),
            'status' => strtoupper(trim((string)($roll['status'] ?? ''))) === 'RECEIVED' ? 'IN_WAREHOUSE' : 'IN_PROCESS',
        ];
        $optional = [];
        foreach (['order_ref', 'comments'] as $k) {
            $v = trim((string)($extra[$k] ?? ''));
            if ($v !== '') {
                $optional[$k] = $v;
            }
        }
        if ((int)($extra['item_id'] ?? 0) > 0) {
            $optional['item_id'] = (int)$extra['item_id'];
        }

        // Se busca por la bobina (entity_id) o por el código. El código se compara en PHP: las tablas tienen
        // intercalaciones distintas y un JOIN o una comparación cruzada falla en MySQL.
        $existing = $trz->prepare("SELECT id FROM traceability_warehouse_entries WHERE (entity_type = 'ROLL' AND entity_id = ?) OR traceability_code = ? ORDER BY id DESC LIMIT 1");
        $existing->execute([$rollId, $code]);
        $existingId = (int)($existing->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $set = [];
            $values = [];
            foreach ($params as $k => $v) {
                $set[] = $k . ' = ?';
                $values[] = $v;
            }
            $set[] = "entity_type = 'ROLL'";
            foreach ($optional as $k => $v) {
                $set[] = $k . ' = ?';
                $values[] = $v;
            }
            $values[] = $existingId;
            $trz->prepare('UPDATE traceability_warehouse_entries SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($values);
            return $existingId;
        }

        $cols = array_merge(array_keys($params), array_keys($optional), ['entity_type', 'package_number', 'total_packages']);
        $values = array_merge(array_values($params), array_values($optional), ['ROLL', 1, 1]);
        $marks = array_fill(0, count($cols), '?');
        $cols[] = 'created_at';
        $marks[] = $sqlite ? "datetime('now', 'localtime')" : 'NOW()';
        $trz->prepare('INSERT INTO traceability_warehouse_entries (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $marks) . ')')
            ->execute($values);
        return (int)$trz->lastInsertId();
    }

    /** Igual que sync(), pero si falla lo anota en el log del servidor en vez de cortar el proceso que creó la bobina. */
    public static function syncSafe(PDO $trz, int $rollId, string $operatorName = '', array $extra = []): int
    {
        try {
            return self::sync($trz, $rollId, $operatorName, $extra);
        } catch (Throwable $e) {
            error_log('[RollTraceabilityEntry] No se pudo crear el código de la bobina #' . $rollId . ': ' . $e->getMessage());
            return 0;
        }
    }

    private static function lastOperator(PDO $trz, int $rollId, bool $sqlite): string
    {
        try {
            $expr = $sqlite ? "json_extract(payload, '$.operator_name')" : "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.operator_name'))";
            $stmt = $trz->prepare("SELECT {$expr} FROM movements WHERE entity_type = 'ROLL' AND entity_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$rollId]);
            return trim((string)($stmt->fetchColumn() ?: ''));
        } catch (Throwable) {
            return '';
        }
    }
}
