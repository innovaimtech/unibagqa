<?php

declare(strict_types=1);

/**
 * Qué bodegas se muestran en pantalla: SOLO las del ERP (unibag_unibag).
 *
 * Trazabilidad (TRZ) tiene su propia tabla `warehouses`, con filas creadas a mano, duplicadas o heredadas
 * («21 · 100», «27 · 3000 (MP PRODUCCIÓN)», «VENTAS»…). Esa tabla solo sirve para traducir el id local al
 * id del ERP. Una bodega se muestra si existe en `company_shops_storehouses`, está activa (`st_status = 1`) y es de
 * la sucursal Unibag (`st_shop_id`, `.env` ERP_SHOP_ID, por defecto 30010); su nombre es el `st_name` del ERP.
 * Los datos de TRZ no se borran: solo quedan fuera de la vista.
 * Si el ERP no responde, se muestran las bodegas TRZ que tienen vínculo con el ERP, con su nombre de TRZ.
 */
final class WarehouseVisibility
{
    /** @var array<string,array<int,string>|null> */
    private static array $cache = [];

    /** Bodegas de destino permitidas al finalizar un embalaje (código al inicio del nombre ERP). */
    public const PACKAGING_DESTINATION_CODES = [400, 500, 700, 1000];

    public const DEFAULT_SHOP_ID = 30010;

    /** Sucursal Unibag en el ERP. */
    public static function shopId(): int
    {
        $raw = class_exists('Env') ? (string)(Env::get('ERP_SHOP_ID', '') ?? '') : '';
        return (int)$raw > 0 ? (int)$raw : self::DEFAULT_SHOP_ID;
    }

    /**
     * Bodegas activas de la sucursal Unibag en el ERP (id => st_name), o null si no se pudieron leer.
     * Si con la sucursal no aparece ninguna, se usan las activas de todas (para no dejar pantallas vacías).
     *
     * @return array<int,string>|null
     */
    public static function erpNames(?PDO $erp): ?array
    {
        if ($erp === null) {
            return null;
        }
        $key = spl_object_hash($erp);
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        try {
            $read = static function (?int $shop) use ($erp): array {
                $st = $erp->prepare('SELECT id, st_name FROM company_shops_storehouses WHERE st_status = 1'
                    . ($shop !== null ? ' AND st_shop_id = ?' : '') . ' ORDER BY st_name');
                $st->execute($shop !== null ? [$shop] : []);
                $out = [];
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $out[(int)$r['id']] = trim((string)$r['st_name']);
                }
                return $out;
            };
            $names = $read(self::shopId());
            if ($names === []) {
                $names = $read(null);
            }
            return self::$cache[$key] = $names;
        } catch (Throwable) {
            return self::$cache[$key] = null;
        }
    }

    /**
     * Ids de bodegas activas del ERP (sucursal Unibag), o null si no se pudieron leer.
     *
     * @return array<int,bool>|null
     */
    public static function activeErpIds(?PDO $erp): ?array
    {
        $names = self::erpNames($erp);
        return $names === null ? null : array_fill_keys(array_keys($names), true);
    }

    /** Condición SQL para filtrar `company_shops_storehouses` (alias opcional) a la sucursal Unibag. */
    public static function shopSql(?PDO $erp, string $alias = ''): string
    {
        $ids = self::activeErpIds($erp);
        $col = ($alias !== '' ? $alias . '.' : '') . 'id';
        if ($ids === null || $ids === []) {
            return '1=1';
        }
        return $col . ' IN (' . implode(',', array_map('intval', array_keys($ids))) . ')';
    }

    /** ¿Esta bodega de TRZ existe en el ERP? (`erp_storehouse_id` apunta a una bodega activa de Unibag en el ERP). */
    public static function isErpBacked(array $trzWarehouse, ?PDO $erp): bool
    {
        $erpId = (int)($trzWarehouse['erp_storehouse_id'] ?? 0);
        if ($erpId <= 0) {
            return false;
        }
        $active = self::activeErpIds($erp);
        return $active === null ? true : isset($active[$erpId]);
    }

    /**
     * Deja solo las filas de bodegas TRZ que existen en el ERP, una por bodega ERP (la de id local menor), con el
     * nombre del ERP en `name` (el de TRZ queda en `trz_name`).
     * Cada fila debe traer `erp_storehouse_id`.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public static function filterTrz(array $rows, ?PDO $erp): array
    {
        $names = self::erpNames($erp);
        $kept = [];
        foreach ($rows as $row) {
            if (!self::isErpBacked($row, $erp)) {
                continue;
            }
            $erpId = (int)$row['erp_storehouse_id'];
            if (isset($kept[$erpId]) && (int)($kept[$erpId]['id'] ?? 0) <= (int)($row['id'] ?? 0)) {
                continue;
            }
            $kept[$erpId] = $row;
        }
        $out = [];
        foreach ($rows as $row) {
            $erpId = (int)($row['erp_storehouse_id'] ?? 0);
            if (!isset($kept[$erpId]) || $kept[$erpId] !== $row) {
                continue;
            }
            $erpName = $names[$erpId] ?? '';
            // Solo el nombre: `code` lo usan pantallas para buscar la bodega y dos bodegas ERP pueden empezar igual.
            if ($erpName !== '') {
                $row['trz_name'] = $row['name'] ?? null;
                $row['name'] = $erpName;
            }
            $out[] = $row;
            unset($kept[$erpId]);
        }
        return $out;
    }

    /** Código de bodega (400, 500…) a partir del nombre ERP: "400 (PROD TERMINADOS REVENTA)" → 400. */
    public static function codeFromName(string $name): ?int
    {
        return preg_match('/^\s*(\d{3,4})\b/', $name, $m) === 1 ? (int)$m[1] : null;
    }

    /** ¿Es una bodega permitida como destino al finalizar el embalaje? */
    public static function isPackagingDestination(string $erpName): bool
    {
        return in_array(self::codeFromName($erpName), self::PACKAGING_DESTINATION_CODES, true);
    }
}
