<?php

declare(strict_types=1);

/**
 * Búsqueda de bobinas por código de trazabilidad y peso registrado.
 *
 * El código que se escanea puede ser el `roll_code` de la bobina o el código de la etiqueta de
 * trazabilidad de bodega (`traceability_warehouse_entries.traceability_code`, entidad ROLL).
 * El peso registrado es `rolls.weight_kg`; si ese está vacío se usa el de la etiqueta de trazabilidad.
 * Así la producción trae SIEMPRE el peso registrado en lugar de pedirlo o suponer un valor.
 */
final class TraceableRollLookup
{
    /** Misma normalización que usa producción: sin espacios/saltos y comillas tipográficas como guion. */
    public static function normalize(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }
        $code = str_replace(["\r", "\n", "\t", ' '], '', $code);
        $code = str_replace(["'", '`', '´', '’', '‘', '‛'], '-', $code);
        return strtoupper($code);
    }

    /**
     * @return array<string,mixed>|null Fila de `rolls` con sku_code, sku_desc, warehouse_name y warehouse_code.
     */
    public static function find(PDO $trz, string $code): ?array
    {
        $raw = trim($code);
        $norm = self::normalize($code);
        if ($norm === '') {
            return null;
        }
        $select = "SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name, w.code AS warehouse_code
                   FROM rolls r
                   LEFT JOIN skus s ON r.sku_id = s.id
                   LEFT JOIN warehouses w ON r.warehouse_id = w.id";
        $st = $trz->prepare($select . "
            WHERE UPPER(REPLACE(r.roll_code, ' ', '')) = :nc OR r.roll_code = :c OR r.id = :cid
            ORDER BY r.id DESC LIMIT 1");
        $st->execute([':nc' => $norm, ':c' => $raw, ':cid' => ctype_digit($raw) ? (int)$raw : 0]);
        $roll = $st->fetch(PDO::FETCH_ASSOC);
        if ($roll) {
            return $roll;
        }
        // Etiqueta de trazabilidad de bodega que apunta a una bobina
        $st = $trz->prepare($select . "
            WHERE r.id = (SELECT e.entity_id FROM traceability_warehouse_entries e
                          WHERE UPPER(REPLACE(e.traceability_code, ' ', '')) = :nc AND e.entity_type = 'ROLL' AND e.entity_id IS NOT NULL
                          ORDER BY e.id DESC LIMIT 1)
            LIMIT 1");
        $st->execute([':nc' => $norm]);
        $roll = $st->fetch(PDO::FETCH_ASSOC);
        return $roll ?: null;
    }

    /**
     * Peso registrado en kg de la bobina (0 si no hay ninguno).
     *
     * @param array<string,mixed> $roll Fila de `rolls`.
     */
    public static function weightKg(PDO $trz, array $roll, string $scannedCode = ''): float
    {
        $own = (float)($roll['weight_kg'] ?? 0);
        if ($own > 0) {
            return $own;
        }
        try {
            $st = $trz->prepare("SELECT weight_kg FROM traceability_warehouse_entries
                WHERE weight_kg IS NOT NULL AND weight_kg > 0
                  AND ((entity_type = 'ROLL' AND entity_id = :rid)
                       OR UPPER(REPLACE(traceability_code, ' ', '')) IN (:c1, :c2))
                ORDER BY id DESC LIMIT 1");
            $st->execute([
                ':rid' => (int)($roll['id'] ?? 0),
                ':c1' => self::normalize((string)($roll['roll_code'] ?? '')),
                ':c2' => self::normalize($scannedCode),
            ]);
            return (float)($st->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            error_log('TraceableRollLookup::weightKg: ' . $e->getMessage());
            return 0.0;
        }
    }
}
