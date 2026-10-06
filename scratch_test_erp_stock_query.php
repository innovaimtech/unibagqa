<?php
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Db.php';
Env::load(__DIR__ . '/.env');

$erp = Db::erpPdo();

function getErpAvailableRolls($erp, ?string $targetMaterial, ?string $targetColor, float $targetWidthCm) {
    // Determine target warehouse(s)
    $tMatUpper = mb_strtoupper(trim((string)$targetMaterial));
    $normTargetMat = '';
    if ($tMatUpper === 'PLA' || $tMatUpper === 'P.L.A' || str_contains($tMatUpper, 'PLA')) {
        $normTargetMat = 'PLA';
    } elseif ($tMatUpper === 'PP' || $tMatUpper === 'TNT' || $tMatUpper === 'TNT60' || str_contains($tMatUpper, 'POLIPROP')) {
        $normTargetMat = 'PP';
    } elseif ($tMatUpper !== '') {
        $normTargetMat = $tMatUpper;
    }

    $stWhere = "css.id IN (5, 11) OR css.st_name LIKE '%100 (MP PLA)%' OR css.st_name LIKE '%200 (MP PP)%'";
    
    $sql = "SELECT iss.st_id, css.st_name AS warehouse_name,
                   i.id AS item_id, i.item_number, i.item_number_prod, i.item_title, i.item_desc,
                   i.item_reg_width AS width_cm,
                   iss.iss_inventory AS stock_qty, iss.iss_inventory_reserved AS stock_reserved
            FROM item_shops_storehouses iss
            JOIN company_shops_storehouses css ON iss.st_id = css.id
            JOIN item i ON iss.item_id = i.id
            WHERE ({$stWhere})
              AND iss.iss_inventory > 0
              AND i.item_status > 0
            ORDER BY (CASE WHEN css.id = " . ($normTargetMat === 'PLA' ? '5' : '11') . " THEN 1 ELSE 2 END),
                     iss.iss_inventory DESC";

    $stmt = $erp->query($sql);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Parse target color keywords
    $cleanCol = mb_strtolower(trim((string)$targetColor));
    $cleanCol = preg_replace('/\(pantone[^\)]*\)/i', '', $cleanCol);
    $cleanCol = preg_replace('/[^a-záéíóúñ\s]/u', ' ', $cleanCol);
    $rawWords = array_filter(array_map('trim', explode(' ', $cleanCol)), fn($w) => mb_strlen($w) >= 3);
    $colorBases = [
        'blanco', 'negro', 'azul', 'rojo', 'verde', 'amarillo', 'naranjo', 'naranja',
        'beige', 'crudo', 'natural', 'gris', 'morado', 'fucsia', 'celeste', 'turquesa',
        'transparente', 'cafe', 'café', 'cristal', 'burdeo'
    ];
    $colorKeywords = [];
    foreach ($rawWords as $w) {
        if (in_array($w, $colorBases, true)) $colorKeywords[] = $w;
    }
    if (in_array('crudo', $colorKeywords, true) || in_array('natural', $colorKeywords, true) || in_array('beige', $colorKeywords, true)) {
        $colorKeywords = array_unique(array_merge($colorKeywords, ['natural', 'crudo', 'beige']));
    }
    if (in_array('transparente', $colorKeywords, true) || in_array('cristal', $colorKeywords, true)) {
        $colorKeywords = array_unique(array_merge($colorKeywords, ['transparente', 'cristal']));
    }
    if (in_array('naranja', $colorKeywords, true) || in_array('naranjo', $colorKeywords, true)) {
        $colorKeywords = array_unique(array_merge($colorKeywords, ['naranja', 'naranjo']));
    }
    if (empty($colorKeywords) && !empty($rawWords)) {
        $colorKeywords = array_values($rawWords);
    }

    foreach ($items as &$it) {
        $titleUpper = mb_strtoupper((string)$it['item_title']);
        $titleLower = mb_strtolower((string)$it['item_title']);
        $stId = (int)$it['st_id'];
        $stName = mb_strtoupper((string)$it['warehouse_name']);

        // 1. Material
        $mat = 'OTRO';
        if ($stId === 5 || str_starts_with($titleUpper, 'PLA/') || str_contains($stName, 'PLA')) {
            $mat = 'PLA';
        } elseif ($stId === 11 || str_starts_with($titleUpper, 'PP/') || str_contains($stName, 'PP')) {
            $mat = 'PP';
        }
        $it['material'] = $mat;

        $matMatch = true;
        if ($normTargetMat === 'PLA') {
            $matMatch = ($mat === 'PLA');
        } elseif ($normTargetMat === 'PP') {
            $matMatch = ($mat === 'PP');
        } elseif ($normTargetMat !== '') {
            $matMatch = str_contains($titleLower, mb_strtolower($normTargetMat));
        }

        // 2. Color
        $colorMatch = true;
        if (!empty($colorKeywords)) {
            $colorMatch = false;
            foreach ($colorKeywords as $kw) {
                if (str_contains($titleLower, $kw)) {
                    $colorMatch = true;
                    break;
                }
            }
        }

        // 3. Width (cm)
        $itWidth = (float)$it['width_cm'];
        // Parse from title if item_reg_width is 0 (e.g. PLA/BLANCO/W80/100X80X1500 -> 100)
        if ($itWidth <= 0) {
            if (preg_match('/\/(\d{2,3})X\d+/i', $titleUpper, $m)) {
                $itWidth = (float)$m[1];
            }
        }
        $it['width_cm'] = $itWidth;

        $widthMatch = true;
        if ($targetWidthCm > 0) {
            // Check if roll width matches or accommodates target width
            $widthMatch = ($itWidth > 0 && abs($itWidth - $targetWidthCm) <= 8) || ($itWidth >= $targetWidthCm && $itWidth <= ($targetWidthCm + 15));
        }

        // Extracted color for display
        $parts = explode('/', $it['item_title']);
        $it['color_name'] = isset($parts[1]) ? trim($parts[1]) : '-';

        $isComp = ($matMatch && $colorMatch && $widthMatch);
        $it['is_compatible'] = $isComp;
        $it['is_material_match'] = $matMatch;
        $it['is_color_match'] = $colorMatch;
        $it['is_width_match'] = $widthMatch;

        $score = 0;
        if ($isComp) $score += 100;
        if ($matMatch) $score += 40;
        if ($colorMatch) $score += 30;
        if ($widthMatch) $score += 20;
        $it['compatibility_score'] = $score;
    }
    unset($it);

    usort($items, function ($a, $b) {
        $sd = ($b['compatibility_score'] ?? 0) - ($a['compatibility_score'] ?? 0);
        if ($sd !== 0) return $sd;
        return ($b['stock_qty'] ?? 0) <=> ($a['stock_qty'] ?? 0);
    });

    return $items;
}

// Test OT 23696: PLA, Blanco, 100 cm
echo "=== TEST OT 23696 (PLA Blanco, 100 cm) ===\n";
$res1 = getErpAvailableRolls($erp, 'PLA', 'Blanco', 100.0);
echo "Total in stock: " . count($res1) . "\n";
foreach ($res1 as $r) {
    if ($r['is_compatible']) {
        echo " -> COMPATIBLE: {$r['item_number_prod']} | {$r['item_title']} | Mat: {$r['material']} | Ancho: {$r['width_cm']}cm | Stock: {$r['stock_qty']} bobinas | Bodega: {$r['warehouse_name']}\n";
    }
}

// Test OT 23408: TNT, Beige, 100 cm
echo "\n=== TEST OT 23408 (TNT Beige, 100 cm) ===\n";
$res2 = getErpAvailableRolls($erp, 'TNT', 'Beige (Pantone 7501c)', 100.0);
echo "Total in stock: " . count($res2) . "\n";
foreach ($res2 as $r) {
    if ($r['is_compatible']) {
        echo " -> COMPATIBLE: {$r['item_number_prod']} | {$r['item_title']} | Mat: {$r['material']} | Ancho: {$r['width_cm']}cm | Stock: {$r['stock_qty']} bobinas | Bodega: {$r['warehouse_name']}\n";
    }
}
