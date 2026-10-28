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
        return ['material'=>self::material((string)($agenda['fab_type'] ?? '')),
            'width_cm'=>$width, 'color'=>(string)($agenda['fabric_color'] ?? ''),
            'grammage'=>(float)($agenda['ot_grams'] ?? $agenda['fab_mat_gramms'] ?? 0)];
    }

    public static function roll(array $item, ?array $agenda): array
    {
        $title = (string)$item['description'];
        $parts = explode('/', $title);
        $specs = ['material'=>self::material($parts[0] ?? ''),
            'color'=>trim($parts[1] ?? ''), 'width_cm'=>(float)($item['width_cm'] ?? 0), 'grammage'=>0.0];
        if (preg_match('/\/\s*(\d+(?:[.,]\d+)?)\s*x\s*(\d+(?:[.,]\d+)?)\s*x/i', $title, $m)) {
            if ($specs['width_cm'] <= 0) $specs['width_cm'] = (float)str_replace(',','.',$m[1]);
            $specs['grammage'] = (float)str_replace(',','.',$m[2]);
        } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:gsm|grs?|g\/m2)\b/i', $title, $m)) {
            $specs['grammage'] = (float)str_replace(',','.',$m[1]);
        }
        $item['specs'] = $specs;
        $item['recommended'] = false;
        $item['match_score'] = 0;
        $item['checks'] = [];
        if ($agenda === null) return $item;
        $target = self::requirements($agenda);
        // Unknown data must never count as a match, especially missing grammage.
        // Los datos desconocidos nunca cuentan como coincidencia, especialmente gramaje.
        $checks = [
            'Material'=>$target['material'] !== '' && $specs['material'] === $target['material'],
            'Ancho'=>$target['width_cm'] > 0 && $specs['width_cm'] >= $target['width_cm'] - 0.01,
            'Color'=>self::color($target['color']) !== '' && self::color($specs['color']) === self::color($target['color']),
            'Gramaje'=>$target['grammage'] > 0 && $specs['grammage'] > 0
                && abs($specs['grammage']-$target['grammage']) < 0.01,
        ];
        $item['checks'] = $checks;
        $item['match_score'] = count(array_filter($checks));
        $item['recommended'] = $item['match_score'] === 4;
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
