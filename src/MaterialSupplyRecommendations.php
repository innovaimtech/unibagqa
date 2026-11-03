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

    /** Material de la OT normalizado igual que en la pantalla de la OT: PLA, PP (incluye TNT) o el texto original. */
    private static function targetMaterial(string $type): string
    {
        $t = mb_strtoupper(trim($type));
        if ($t === '') return '';
        if (str_contains($t, 'PLA')) return 'PLA';
        if (in_array($t, ['PP', 'TNT', 'TNT60'], true) || str_contains($t, 'POLIPROP') || str_contains($t, 'NON WOVEN')) return 'PP';
        return $t;
    }

    /** Palabras de color de la OT (sin Pantone), con sinónimos comerciales (crudo = natural = beige, etc.). */
    private static function colorKeywords(string $color): array
    {
        $clean = mb_strtolower(trim($color));
        $clean = preg_replace('/\(pantone[^)]*\)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/[^a-záéíóúñ\s]/u', ' ', $clean) ?? $clean;
        $words = array_values(array_filter(array_map('trim', explode(' ', $clean)), static fn($w) => mb_strlen($w) >= 3));
        $bases = ['blanco','negro','azul','rojo','verde','amarillo','naranjo','naranja','beige','crudo','natural','gris',
            'morado','fucsia','celeste','turquesa','transparente','cafe','café','cristal','dorado','plateado','rosa','rosado'];
        $keys = array_values(array_filter($words, static fn($w) => in_array($w, $bases, true)));
        $groups = [['crudo','natural','beige'], ['transparente','cristal'], ['naranja','naranjo']];
        foreach ($groups as $g) {
            if (array_intersect($g, $keys)) $keys = array_values(array_unique(array_merge($keys, $g)));
        }
        if (!$keys && $words) $keys = $words;
        return $keys;
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
        if ($width >= 300) $width /= 10; // valor guardado en mm
        $grams = (float)($agenda['ot_grams'] ?? 0);
        if ($grams <= 0) $grams = (float)($agenda['fab_mat_gramms'] ?? 0);
        return ['material'=>self::targetMaterial((string)($agenda['fab_type'] ?? '')), 'width_cm'=>$width,
            'color'=>trim((string)($agenda['fabric_color'] ?? $agenda['ag_color_tela'] ?? '')), 'grammage'=>$grams];
    }

    /**
     * Compara una bobina con la OT con las MISMAS reglas que la pantalla de la OT:
     * material (la bodega 100 es PLA y la 200 es PP), color por palabras clave y ancho (igual o mayor, o hasta 8 cm menos).
     * El gramaje no decide la compatibilidad; se muestra solo como dato.
     * Cada criterio: true = coincide, false = NO coincide, null = sin dato en la OT o en la bobina (no descarta).
     */
    public static function roll(array $item, ?array $agenda): array
    {
        $title = (string)$item['description'];
        $sku = (string)($item['sku'] ?? '');
        $stId = (int)($item['source_storehouse_id'] ?? 0);
        $whName = mb_strtoupper((string)($item['warehouse_name'] ?? ''));
        $parts = explode('/', $title);
        $titleMat = mb_strtoupper(trim($parts[0] ?? ''));

        $rollMat = 'OTRO';
        if ($titleMat === 'PLA' || $stId === 5 || str_contains($whName, 'PLA')) {
            $rollMat = 'PLA';
        } elseif (in_array($titleMat, ['PP', 'TNT'], true) || $stId === 11 || str_contains($whName, 'PP')) {
            $rollMat = 'PP';
        } elseif (str_starts_with(strtoupper($sku), 'TEL') || $titleMat !== '') {
            $rollMat = stripos($title, 'PLA') !== false ? 'PLA' : 'PP';
        }
        $color = trim((string)preg_replace('/\(pantone[^)]*\)/i', '', trim($parts[1] ?? '')));
        $specs = ['material'=>$rollMat, 'color'=>$color, 'width_cm'=>(float)($item['width_cm'] ?? 0), 'grammage'=>0.0];
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
        if ($target['material'] === '') {
            $checks['Material'] = null;
        } elseif (in_array($target['material'], ['PLA', 'PP'], true)) {
            $checks['Material'] = $rollMat === $target['material'];
        } else {
            $checks['Material'] = str_contains(mb_strtolower($title), mb_strtolower($target['material']));
        }
        if ($target['width_cm'] <= 0 || $specs['width_cm'] <= 0) {
            $checks['Ancho'] = null;
        } else {
            $checks['Ancho'] = $specs['width_cm'] >= $target['width_cm'] - 8.0;
        }
        $keys = self::colorKeywords($target['color']);
        if (!$keys) {
            $checks['Color'] = null;
        } else {
            $hay = mb_strtolower($color . ' ' . $title);
            $checks['Color'] = false;
            foreach ($keys as $kw) {
                if (str_contains($hay, $kw)) { $checks['Color'] = true; break; }
            }
        }
        $item['checks'] = $checks;
        $ok = count(array_filter($checks, static fn($v) => $v === true));
        $bad = count(array_filter($checks, static fn($v) => $v === false));
        $item['match_score'] = $ok - 2 * $bad;
        $item['recommended'] = $bad === 0 && $ok >= 1;
        return $item;
    }

    /**
     * Compara una bobina candidata con el artículo solicitado (cuando no hay OT planificada):
     * mismo material, color compartido y ancho igual o hasta 8 cm menor. true/false/null como en roll().
     *
     * @return array<string,bool|null>
     */
    public static function compareWithItem(array $candidate, array $requested): array
    {
        $c = self::roll($candidate, null)['specs'];
        $q = self::roll($requested, null)['specs'];
        $checks = [];
        $checks['Material'] = ($c['material'] === 'OTRO' || $q['material'] === 'OTRO') ? null : $c['material'] === $q['material'];
        if ($q['width_cm'] <= 0 || $c['width_cm'] <= 0) {
            $checks['Ancho'] = null;
        } else {
            $checks['Ancho'] = $c['width_cm'] >= $q['width_cm'] - 8.0;
        }
        $keys = self::colorKeywords((string)$q['color']);
        if (!$keys) {
            $checks['Color'] = null;
        } else {
            $hay = mb_strtolower($c['color'] . ' ' . (string)$candidate['description']);
            $checks['Color'] = false;
            foreach ($keys as $kw) {
                if (str_contains($hay, $kw)) { $checks['Color'] = true; break; }
            }
        }
        return $checks;
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
