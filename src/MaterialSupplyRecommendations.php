<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Motor de Recomendación Inteligente de Materiales y Telas para OTs
 * (src/MaterialSupplyRecommendations.php)
 * =============================================================================
 *
 * Responsabilidad:
 * Analiza las especificaciones técnicas de la Orden de Trabajo agendada (ficha técnica:
 * tipo de tela, ancho requerido, color, gramaje) y las compara contra el catálogo de
 * bobinas disponibles en bodega para sugerir al operador las bobinas más aptas.
 *
 * Principio de diseño:
 * Provee sugerencias técnicas no bloqueantes (el operador siempre tiene la libertad
 * de seleccionar cualquier bobina disponible según la realidad física de la planta).
 */
final class MaterialSupplyRecommendations
{
    /**
     * Limpia, pasa a minúsculas y elimina acentos/caracteres especiales de un texto.
     */
    private static function token(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($ascii === false ? $value : $ascii)) ?? '');
    }

    /**
     * Normaliza el tipo de polímero/material base (ej: TNT, PP, Non Woven -> PP; PLA -> PLA).
     */
    private static function material(string $value): string
    {
        $value = self::token($value);
        if (preg_match('/\bpla\b/', $value)) return 'PLA';
        if (preg_match('/\b(pp|tnt|polipropileno|non woven)\b/', $value)) return 'PP';
        return strtoupper($value);
    }

    /**
     * Normaliza nombres de colores unificando variantes comerciales (ej: crudo -> natural, cristal -> transparente).
     */
    private static function color(string $value): string
    {
        $value = self::token(preg_replace('/\(pantone[^)]*\)/i', '', $value) ?? $value);
        return ['crudo'=>'natural','beige'=>'natural','cristal'=>'transparente','naranjo'=>'naranja','rosado'=>'rosa'][$value] ?? $value;
    }

    /**
     * Extrae los requisitos técnicos de la OT agendada (material, ancho en cm, color y gramaje).
     *
     * @param array<string, mixed> $agenda Datos de la orden en prod_agenda.
     * @return array{material: string, width_cm: float, color: string, grammage: float}
     */
    public static function requirements(array $agenda): array
    {
        $width = (float)($agenda['req_operador_tela_width'] ?? 0);
        if ($width <= 0) $width = (float)($agenda['fab_med_width'] ?? 0);
        if ($width > 300) $width /= 10; // valor guardado en mm
        $type = trim((string)($agenda['fab_type'] ?? ''));
        $material = $type === '' ? '' : (stripos($type, 'PLA') !== false ? 'PLA' : 'PP');
        $grams = (float)($agenda['ot_grams'] ?? 0);
        if ($grams <= 0) $grams = (float)($agenda['fab_mat_gramms'] ?? 0);
        return ['material'=>$material, 'width_cm'=>$width,
            'color'=>trim((string)($agenda['fabric_color'] ?? $agenda['ag_color_tela'] ?? '')), 'grammage'=>$grams];
    }

    /**
     * Compara una bobina con la OT. Cada criterio puede ser:
     *   true  = coincide, false = NO coincide, null = sin dato (en la OT o en la bobina; no descarta).
     * Es compatible si ningún criterio falla y al menos dos coinciden. Tolerancias del sistema anterior:
     * gramaje ±5 g/m² y ancho igual o mayor (se acepta hasta 8 cm menos).
     */
    public static function roll(array $item, ?array $agenda): array
    {
        $title = (string)$item['description'];
        $parts = explode('/', $title);
        $specs = ['material'=>(stripos($title, 'PLA') !== false || stripos((string)($item['sku'] ?? ''), 'PLA') !== false) ? 'PLA' : 'PP',
            'color'=>trim($parts[1] ?? ''), 'width_cm'=>(float)($item['width_cm'] ?? 0), 'grammage'=>0.0];
        $specs['color'] = trim((string)preg_replace('/\(pantone[^)]*\)/i', '', $specs['color']));
        if (preg_match('/\/\s*(\d+(?:[.,]\d+)?)\s*x\s*(\d+(?:[.,]\d+)?)\s*x/i', $title, $m)) {
            if ($specs['width_cm'] <= 0) $specs['width_cm'] = (float)str_replace(',','.',$m[1]);
            $specs['grammage'] = (float)str_replace(',','.',$m[2]);
        } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:gsm|grs?|g\/m2)\b/i', $title, $m)) {
            $specs['grammage'] = (float)str_replace(',','.',$m[1]);
        } elseif (preg_match('/\/(\d{2,3})\/(\d{2,3})\s*GR/i', $title, $m)) {
            if ($specs['width_cm'] <= 0) $specs['width_cm'] = (float)$m[1];
            $specs['grammage'] = (float)$m[2];
        }
        $item['specs'] = $specs;
        $item['recommended'] = false;
        $item['match_score'] = 0;
        $item['checks'] = [];
        if ($agenda === null) return $item;
        $target = self::requirements($agenda);

        $checks = [];
        $checks['Material'] = $target['material'] === '' ? null : $specs['material'] === $target['material'];
        if ($target['width_cm'] <= 0 || $specs['width_cm'] <= 0) {
            $checks['Ancho'] = null;
        } else {
            $checks['Ancho'] = $specs['width_cm'] >= $target['width_cm'] - 8.0;
        }
        $tc = self::color($target['color']);
        $rc = self::color($specs['color']);
        if ($tc === '' || $rc === '') {
            $checks['Color'] = null;
        } else {
            $checks['Color'] = $rc === $tc || str_contains($rc, $tc) || str_contains($tc, $rc);
        }
        if ($target['grammage'] <= 0 || $specs['grammage'] <= 0) {
            $checks['Gramaje'] = null;
        } else {
            $checks['Gramaje'] = abs($specs['grammage'] - $target['grammage']) <= 5.0;
        }
        $item['checks'] = $checks;
        $ok = count(array_filter($checks, static fn($v) => $v === true));
        $bad = count(array_filter($checks, static fn($v) => $v === false));
        $item['match_score'] = $ok - 2 * $bad;
        $item['recommended'] = $bad === 0 && $ok >= 2;
        return $item;
    }

    public static function inkMatches(array $item, ?array $agenda): bool
    {
        if ($agenda === null) return false;
        $haystack = ' ' . self::token($item['sku'].' '.$item['description']) . ' ';
        for ($i=1; $i<=10; $i++) {
            $color = self::token((string)($agenda['fab_print_colordesc_'.$i] ?? ''));
            if ($color !== '' && str_contains($haystack, ' '.$color.' ')) return true;
        }
        return false;
    }
}
