<?php

declare(strict_types=1);

/**
 * Tipo de máquina en un solo lugar. Antes cada pantalla decidía por su cuenta si una máquina era Pulpo
 * (por el nombre, por el id 36 o por el tipo 22) y los resultados podían no coincidir.
 */
final class MachineKind
{
    public const PULPO_EQUIPO_ID = 36;
    public const PULPO_EQUIPO_TYPE_ID = 22;
    public const SERI_EQUIPO_TYPE_ID = 11;

    /** Pulpo Serigráfico: el equipo 36, el tipo 22 o una serigrafía cuyo nombre dice «pulpo». */
    public static function isPulpo(int $equipoId, string $equipoName = '', int $equipoTypeId = 0, bool $isSeriPrinter = false): bool
    {
        if ($equipoId === self::PULPO_EQUIPO_ID || $equipoTypeId === self::PULPO_EQUIPO_TYPE_ID) {
            return true;
        }
        return ($isSeriPrinter || $equipoTypeId === self::SERI_EQUIPO_TYPE_ID) && stripos($equipoName, 'pulpo') !== false;
    }

    /** Serigrafía (plana o pulpo). */
    public static function isSeri(int $equipoId, string $equipoName = '', int $equipoTypeId = 0, bool $isSeriPrinter = false, string $typeTitle = ''): bool
    {
        return $isSeriPrinter || in_array($equipoTypeId, [self::SERI_EQUIPO_TYPE_ID, self::PULPO_EQUIPO_TYPE_ID], true)
            || stripos($typeTitle, 'seri') !== false || stripos($equipoName, 'seri') !== false
            || self::isPulpo($equipoId, $equipoName, $equipoTypeId, $isSeriPrinter);
    }
}
