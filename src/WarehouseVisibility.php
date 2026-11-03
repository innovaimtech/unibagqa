<?php

declare(strict_types=1);

/**
 * Qué bodegas se muestran en pantalla.
 *
 * Trazabilidad (TRZ) puede tener bodegas propias que no existen en el ERP (creadas a mano, duplicadas
 * o heredadas). Solo se muestran las bodegas de TRZ vinculadas a una bodega ACTIVA del ERP
 * (`company_shops_storehouses.st_status = 1`). Los datos no se borran: solo quedan fuera de la vista.
 * Si el ERP no responde, se muestran las que tienen vínculo con el ERP (`erp_storehouse_id` no vacío).
 */
final class WarehouseVisibility
{
    /** @var array<string,array<int,bool>|null> */
    private static array $cache = [];

    /** Bodegas de destino permitidas al finalizar un embalaje (código al inicio del nombre ERP). */
    public const PACKAGING_DESTINATION_CODES = [400, 500, 700, 1000];

    /**
     * Ids de bodegas activas del ERP, o null si no se pudieron leer.
     *
     * @return array<int,bool>|null
     */
    public static function activeErpIds(?PDO $erp): ?array
    {
        if ($erp === null) {
            return null;
        }
        $key = spl_object_hash($erp);
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        try {
            $ids = [];
            foreach ($erp->query('SELECT id FROM company_shops_storehouses WHERE st_status = 1')->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $ids[(int)$id] = true;
            }
            return self::$cache[$key] = $ids;
        } catch (Throwable) {
            return self::$cache[$key] = null;
        }
    }

    /** ¿Esta bodega de TRZ existe en el ERP? (`erp_storehouse_id` apunta a una bodega activa del ERP). */
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
     * Deja solo las filas de bodegas TRZ que existen en el ERP. Cada fila debe traer `erp_storehouse_id`.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public static function filterTrz(array $rows, ?PDO $erp): array
    {
        return array_values(array_filter($rows, static fn(array $row): bool => self::isErpBacked($row, $erp)));
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
