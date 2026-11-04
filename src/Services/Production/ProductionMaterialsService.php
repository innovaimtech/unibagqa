<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Materiales en Planta · Bobinas Bodega 3000, Montaje, Anilox y Tintas
// (src/Services/Production/ProductionMaterialsService.php)
// =============================================================================

trait ProductionMaterialsServiceTrait
{
    // =========================================================================
    // SECCIÓN 4.1: TRAZABILIDAD Y CONSUMO DE BOBINAS / MATERIALES (TRZ + ERP)
    // =========================================================================

    /**
     * Lista las bobinas disponibles en stock (recepcionadas en bodegas) para ser consumidas en la OT.
     *
     * @param string|null $filterSku Filtro opcional por SKU o descripción
     * @param int $limit Límite de resultados
     * @return array<int, array<string, mixed>>
     */
    public function listAvailableRollsForProduction(
        ?string $filterSku = null,
        int $limit = 200,
        ?string $targetColor = null,
        int|float|null $targetWidth = null,
        ?string $targetMaterial = null,
        int|float|null $targetGrammage = null
    ): array {
        try {
            $rolls = [];

            // 1. Normalizar material objetivo de la OT (PLA vs PP / TNT vs Otro)
            $tMatUpper = mb_strtoupper(trim((string)$targetMaterial));
            $normTargetMat = '';
            if ($tMatUpper === 'PLA' || $tMatUpper === 'P.L.A' || str_contains($tMatUpper, 'PLA')) {
                $normTargetMat = 'PLA';
            } elseif ($tMatUpper === 'PP' || $tMatUpper === 'TNT' || $tMatUpper === 'TNT60' || str_contains($tMatUpper, 'POLIPROP') || str_contains($tMatUpper, 'NON WOVEN')) {
                $normTargetMat = 'PP';
            } elseif ($tMatUpper !== '') {
                $normTargetMat = $tMatUpper;
            }

            // 2. Extraer palabras clave de color objetivo de la OT (limpiando códigos Pantone y puntuación)
            $cleanCol = mb_strtolower(trim((string)$targetColor));
            $cleanCol = preg_replace('/\(pantone[^\)]*\)/i', '', $cleanCol);
            $cleanCol = preg_replace('/[^a-záéíóúñ\s]/u', ' ', $cleanCol);
            $rawWords = array_filter(array_map('trim', explode(' ', $cleanCol)), fn($w) => mb_strlen($w) >= 3);

            $colorBases = [
                'blanco', 'negro', 'azul', 'rojo', 'verde', 'amarillo', 'naranjo', 'naranja',
                'beige', 'crudo', 'natural', 'gris', 'morado', 'fucsia', 'celeste', 'turquesa',
                'transparente', 'cafe', 'café', 'cristal', 'dorado', 'plateado', 'rosa', 'rosado'
            ];
            $colorKeywords = [];
            foreach ($rawWords as $w) {
                if (in_array($w, $colorBases, true)) {
                    $colorKeywords[] = $w;
                }
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

            $targetWidthMm = (int)$targetWidth;
            if ($targetWidthMm > 0 && $targetWidthMm < 100) {
                $targetWidthMm *= 10; // Convertir cm a mm
            }

            // 3. CONSULTAR BOBINAS REALES DESDE LA BASE DE DATOS unibag_unibag (ERP)
            // Solo bobinas de Bodega 100 (MP PLA) y Bodega 200 (MP PP). La bodega 3000 es la que ya está en producción.
            $stWhere = "(css.id IN (5, 11) OR css.st_name LIKE '%100 (MP PLA)%' OR css.st_name LIKE '%200 (MP PP)%') AND css.id NOT IN (16, 26, 27) AND css.st_name NOT LIKE '%3000%'";
            $where = [
                "({$stWhere})",
                "iss.iss_inventory > 0",
                "(i.item_status > 0 OR i.item_status IS NULL)",
                "(i.item_number_prod LIKE 'TEL%' OR i.item_title LIKE 'PLA/%' OR i.item_title LIKE 'PP/%' OR i.item_title LIKE 'TNT/%')"
            ];
            $params = [];

            if ($filterSku !== null && trim($filterSku) !== '') {
                $where[] = "(i.item_number_prod LIKE :q OR i.item_title LIKE :q OR i.item_desc LIKE :q)";
                $params[':q'] = '%' . trim($filterSku) . '%';
            }

            $orderSql = "(CASE WHEN css.id = 5 THEN 1 WHEN css.id = 11 THEN 2 ELSE 3 END), iss.iss_inventory DESC";
            if ($normTargetMat === 'PLA') {
                $orderSql = "(CASE WHEN css.id = 5 OR i.item_title LIKE 'PLA/%' THEN 1 ELSE 2 END), iss.iss_inventory DESC";
            } elseif ($normTargetMat === 'PP') {
                $orderSql = "(CASE WHEN css.id = 11 OR i.item_title LIKE 'PP/%' OR i.item_title LIKE 'TNT/%' THEN 1 ELSE 2 END), iss.iss_inventory DESC";
            }

            $whereSql = implode(' AND ', $where);
            $sql = "SELECT iss.st_id, css.st_name AS warehouse_name,
                           i.id AS item_id, i.item_number, i.item_number_prod AS sku_code,
                           i.item_title AS sku_desc, i.item_desc,
                           i.item_reg_width AS width_cm,
                           iss.iss_inventory AS stock_qty,
                           iss.iss_inventory_reserved AS stock_reserved,
                           (iss.iss_inventory - iss.iss_inventory_reserved) AS available_qty
                    FROM item_shops_storehouses iss
                    JOIN company_shops_storehouses css ON iss.st_id = css.id
                    JOIN item i ON iss.item_id = i.id
                    WHERE {$whereSql}
                    ORDER BY {$orderSql}
                    LIMIT :lim";

            $stmt = $this->erpPdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $erpRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($erpRows as $er) {
                $stId = (int)$er['st_id'];
                $itemId = (int)$er['item_id'];
                $sku = trim((string)$er['sku_code']);
                $title = trim((string)$er['sku_desc']);
                $whName = (string)$er['warehouse_name'];
                $whCode = ($stId === 5 ? '100' : ($stId === 11 ? '200' : (string)$stId));

                // Parsear título para obtener material, color, ancho, gramaje, metros
                $parts = explode('/', $title);
                $titleMat = mb_strtoupper(trim($parts[0] ?? ''));
                $titleColor = trim($parts[1] ?? '');
                $colorClean = preg_replace('/\(pantone[^\)]*\)/i', '', $titleColor);
                $colorClean = trim($colorClean) ?: '-';

                $widthCm = (float)$er['width_cm'];
                $grammage = 80.0;
                $meters = 1100.0;
                if (preg_match('/\/(\d+(?:\.\d+)?)[xX](\d+(?:\.\d+)?)[xX](\d+)/i', $title, $mD)) {
                    if ($widthCm <= 0) $widthCm = (float)$mD[1];
                    $grammage = (float)$mD[2];
                    $meters = (float)$mD[3];
                }

                $widthMm = (int)round($widthCm * 10);
                $weightKg = round(($widthCm / 100) * $meters * ($grammage / 1000), 1);
                if ($weightKg <= 0) {
                    $weightKg = ($titleMat === 'PLA' ? 120.0 : 88.0);
                }

                // Detectar material de la bobina
                $rollMat = 'OTRO';
                if ($titleMat === 'PLA' || $stId === 5 || str_contains($whName, 'PLA')) {
                    $rollMat = 'PLA';
                } elseif ($titleMat === 'PP' || $titleMat === 'TNT' || $stId === 11 || str_contains($whName, 'PP')) {
                    $rollMat = 'PP';
                } elseif (str_starts_with($sku, 'TEL')) {
                    $rollMat = str_contains($title, 'PLA') ? 'PLA' : 'PP';
                }

                // 1. Filtro por Materialidad
                $matMatch = true;
                if ($normTargetMat === 'PLA') {
                    $matMatch = ($rollMat === 'PLA');
                } elseif ($normTargetMat === 'PP') {
                    $matMatch = ($rollMat === 'PP');
                } elseif ($normTargetMat !== '') {
                    $matMatch = str_contains(mb_strtolower($title), mb_strtolower($normTargetMat));
                }

                // 2. Filtro por Color
                $colorMatch = true;
                $rColorLower = mb_strtolower($colorClean);
                $rTitleLower = mb_strtolower($title);
                if (!empty($colorKeywords)) {
                    $colorMatch = false;
                    foreach ($colorKeywords as $kw) {
                        if (str_contains($rColorLower, $kw) || str_contains($rTitleLower, $kw)) {
                            $colorMatch = true;
                            break;
                        }
                    }
                }

                // 3. Filtro por Ancho de Bobina
                $widthMatch = true;
                if ($targetWidthMm > 0) {
                    $widthMatch = ($widthMm > 0 && abs($widthMm - $targetWidthMm) <= 80) || ($widthMm >= $targetWidthMm);
                }

                $isCompatible = ($matMatch && $colorMatch && $widthMatch);
                $score = 0;
                if ($isCompatible) $score += 100;
                if ($matMatch) $score += 40;
                if ($colorMatch) $score += 30;
                if ($widthMatch) $score += 20;

                $availQty = max(0.0, (float)$er['available_qty']);

                $rolls[] = [
                    'id' => "ERP_{$stId}_{$itemId}",
                    'raw_id' => "ERP_{$stId}_{$itemId}",
                    'roll_code' => $sku,
                    'roll_number' => $sku,
                    'item_id' => $itemId,
                    'st_id' => $stId,
                    'sku_code' => $sku,
                    'sku_desc' => $title,
                    'warehouse_code' => $whCode,
                    'warehouse_name' => $whName,
                    'stock_qty' => $availQty,
                    'current_weight_kg' => $weightKg,
                    'weight_kg' => $weightKg,
                    'microns' => (int)$grammage,
                    'width_mm' => $widthMm,
                    'width_cm' => $widthCm,
                    'color' => $colorClean,
                    'meters' => $meters,
                    'status' => 'DISPONIBLE',
                    'roll_material' => $rollMat,
                    'is_from_erp' => true,
                    'is_compatible' => $isCompatible,
                    'is_material_match' => $matMatch,
                    'is_color_match' => $colorMatch,
                    'is_width_match' => $widthMatch,
                    'compatibility_score' => $score,
                    'compatibility_label' => $isCompatible ? '⭐ COMPATIBLE' : '',
                ];
            }

            // 4. Incorporar bobinas físicas ya recibidas en TRZ (no demo, no asignadas)
            if ($this->trzPdo !== null) {
                try {
                    $sqlTrz = "SELECT r.id, r.roll_code, r.roll_code AS roll_number,
                                      r.weight_kg, r.weight_kg AS current_weight_kg,
                                      r.microns, r.width_mm, r.color, r.meters, r.status,
                                      r.purchase_order_id, r.purchase_order_line_id, r.created_at,
                                      s.code AS sku_code, s.description AS sku_desc,
                                      w.code AS warehouse_code, w.name AS warehouse_name
                               FROM rolls r
                               INNER JOIN skus s ON r.sku_id = s.id
                               INNER JOIN warehouses w ON r.warehouse_id = w.id
                               WHERE r.status IN ('RECEIVED', 'IN_PROCESS')
                                 AND r.current_work_order_id IS NULL
                                 AND r.roll_code NOT LIKE 'DEMO-%'
                                  AND (w.code IN ('100', '200') OR w.erp_storehouse_id IN (5, 11) OR w.name LIKE '%100%' OR w.name LIKE '%200%')
                                  AND w.code NOT IN ('3000') AND w.name NOT LIKE '%3000%'
                               ORDER BY r.id DESC LIMIT 50";
                    $stmtTrz = $this->trzPdo->query($sqlTrz);
                    $trzRolls = $stmtTrz ? ($stmtTrz->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

                    foreach ($trzRolls as $tr) {
                        $rColor = mb_strtolower(trim((string)($tr['color'] ?? '')));
                        $rDesc = mb_strtolower(trim((string)($tr['sku_desc'] ?? '')));
                        $rSku = mb_strtolower(trim((string)($tr['sku_code'] ?? '')));
                        $whCode = trim((string)($tr['warehouse_code'] ?? ''));
                        $whName = mb_strtoupper(trim((string)($tr['warehouse_name'] ?? '')));
                        $rWidth = (int)($tr['width_mm'] ?? 0);

                        $rollMat = 'OTRO';
                        if (str_starts_with($rSku, 'pp-') || str_starts_with($rDesc, 'pp ') || str_contains($rDesc, 'pp/') || str_starts_with($rSku, 'tel')) {
                            $rollMat = 'PP';
                        } elseif (str_starts_with($rSku, 'pla-') || str_starts_with($rDesc, 'pla ') || str_contains($rDesc, 'pla/')) {
                            $rollMat = 'PLA';
                        } elseif ($whCode === '100' || str_contains($whName, 'PLA')) {
                            $rollMat = 'PLA';
                        } elseif ($whCode === '200' || str_contains($whName, 'PP')) {
                            $rollMat = 'PP';
                        }
                        $tr['roll_material'] = $rollMat;

                        $matMatch = true;
                        if ($normTargetMat === 'PLA') {
                            $matMatch = ($rollMat === 'PLA');
                        } elseif ($normTargetMat === 'PP') {
                            $matMatch = ($rollMat === 'PP');
                        }

                        $colorMatch = true;
                        if (!empty($colorKeywords)) {
                            $colorMatch = false;
                            foreach ($colorKeywords as $kw) {
                                if (str_contains($rColor, $kw) || str_contains($rDesc, $kw) || str_contains($rSku, $kw)) {
                                    $colorMatch = true;
                                    break;
                                }
                            }
                        }

                        $widthMatch = true;
                        if ($targetWidthMm > 0) {
                            $widthMatch = ($rWidth > 0 && abs($rWidth - $targetWidthMm) <= 80) || ($rWidth >= $targetWidthMm);
                        }

                        $isCompatible = ($matMatch && $colorMatch && $widthMatch);
                        $score = 0;
                        if ($isCompatible) $score += 100;
                        if ($matMatch) $score += 40;
                        if ($colorMatch) $score += 30;
                        if ($widthMatch) $score += 20;

                        $tr['id'] = (int)$tr['id'];
                        $tr['raw_id'] = (string)$tr['id'];
                        $tr['stock_qty'] = 1.0;
                        $tr['is_from_erp'] = false;
                        $tr['is_compatible'] = $isCompatible;
                        $tr['is_material_match'] = $matMatch;
                        $tr['is_color_match'] = $colorMatch;
                        $tr['is_width_match'] = $widthMatch;
                        $tr['compatibility_score'] = $score;
                        $tr['compatibility_label'] = $isCompatible ? '⭐ COMPATIBLE' : '';

                        $rolls[] = $tr;
                    }
                } catch (Throwable) {}
            }

            // 5. Ordenar: primero las compatibles por score descendente y luego por stock disponible
            usort($rolls, static function ($a, $b) {
                $scoreDiff = ($b['compatibility_score'] ?? 0) - ($a['compatibility_score'] ?? 0);
                if ($scoreDiff !== 0) return $scoreDiff;
                return ($b['stock_qty'] ?? 0) <=> ($a['stock_qty'] ?? 0);
            });

            return $rolls;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Calcula la cuota máxima de bobinas/material permitida para la OT activa
     * y determina si se requiere autorización de supervisor para solicitar más.
     */
    public function getWorkOrderMaterialQuota(int $agId, int $workerOtId = 0): array
    {
        $default = [
            'planned_units' => 0,
            'planned_kg' => 0.0,
            'max_allowed_rolls' => 2,
            'assigned_rolls_count' => 0,
            'assigned_kg' => 0.0,
            'requires_supervisor' => false,
            'assigned_rolls' => [],
        ];

        try {
            $stmt = $this->erpPdo->prepare("
                SELECT t0.ag_amount, t1x.fab_mat_gramms, t1x.fab_med_width, t1x.fab_med_height,
                       t1.req_operador_mermaperc, t1.req_operador_tela_width,
                       t1x.fab_type, v1.add_name AS fabric_color
                FROM prod_agenda t0
                INNER JOIN orders t1 ON t0.ag_reqid = t1.id
                INNER JOIN orders_items t1x ON t1.id = t1x.req_id
                LEFT OUTER JOIN tran_comments_vals v1 ON t1x.fab_mat_fabric_color = v1.id
                WHERE t0.id = :agid
                LIMIT 1
            ");
            $stmt->execute([':agid' => $agId]);
            $ot = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ot) {
                return $default;
            }

            $amount = (float)($ot['ag_amount'] ?? 0);
            $gramms = (float)($ot['fab_mat_gramms'] ?? 70);
            $widthCm = (float)($ot['req_operador_tela_width'] ?: ($ot['fab_med_width'] ?? 35));
            $heightCm = (float)($ot['fab_med_height'] ?? 40);
            $mermaPerc = (float)($ot['req_operador_mermaperc'] ?? 5.0);

            $areaBolsaM2 = (2 * ($widthCm / 100) * ($heightCm / 100));
            $pesoUnitarioKg = ($areaBolsaM2 * $gramms) / 1000;
            if ($pesoUnitarioKg <= 0.005) {
                $pesoUnitarioKg = 0.035;
            }

            $plannedKg = round($amount * $pesoUnitarioKg * (1 + ($mermaPerc / 100)), 1);
            $maxAllowedRolls = max(2, (int)ceil($plannedKg / 60));

            $assignedRolls = [];
            $assignedKg = 0.0;
            if ($this->trzPdo !== null && $workerOtId > 0) {
                $traceabilityWorkOrderId = $this->resolveTraceabilityWorkOrderId($workerOtId, $agId, false);
                $stmtRolls = $this->trzPdo->prepare("
                    SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                    FROM rolls r
                    INNER JOIN skus s ON r.sku_id = s.id
                    LEFT JOIN warehouses w ON r.warehouse_id = w.id
                    WHERE (
                            r.current_work_order_id = :ot_id
                            OR (:trz_ot_id > 0 AND r.current_work_order_id = :trz_ot_id)
                            OR (r.source_work_order_id = :ot_id2 AND r.status = 'CONSUMED')
                    )
                    ORDER BY r.id ASC
                ");
                $stmtRolls->execute([
                    ':ot_id' => $workerOtId,
                    ':trz_ot_id' => $traceabilityWorkOrderId,
                    ':ot_id2' => $workerOtId,
                ]);
                $assignedRolls = $stmtRolls->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($assignedRolls as $rollIndex => &$ar) {
                    $ar['roll_number'] = $ar['roll_code'];
                    $ar['current_weight_kg'] = $ar['weight_kg'];
                    $ar['entry_weight_kg'] = (float)($ar['weight_kg'] ?? 0);

                    $arId = (int)$ar['id'];
                    $state=(new MachineRollContinuityService($this->erpPdo,$this->trzPdo))->state($arId);
                    // Una OT TRZ puede compartirse entre aperturas o maquinas del mismo CC.
                    // A TRZ job may be shared by different openings or machines on one cost center.
                    if ($ar['status']==='IN_PROCESS' && $state['worker_ot_id']>0 && $state['worker_ot_id']!==$workerOtId) {
                        unset($assignedRolls[$rollIndex]);
                        continue;
                    }
                    $assignedKg += (float)($ar['weight_kg'] ?? 0);
                    $lastMount=$state['event'];
                    $ar['is_mounted'] = $state['mounted'] && $state['worker_ot_id']===$workerOtId
                        && (string)($ar['status']??'')==='IN_PROCESS';
                    $ar['mounted_at'] = $ar['is_mounted']
                        ? ($state['payload']['physical_mount_created_at']??$lastMount['created_at']) : null;
                    if ($lastMount && !empty($lastMount['payload'])) {
                        $pData = json_decode((string)$lastMount['payload'], true);
                        if (!empty($pData['entry_weight_kg'])) {
                            $ar['entry_weight_kg'] = (float)$pData['entry_weight_kg'];
                        }
                    }

                    $stmtOut = $this->trzPdo->prepare("
                        SELECT type, payload, created_at FROM events
                        WHERE (
                            (type IN ('ROLL_OUTPUT_COMPLETED', 'ROLL_CONSUMED') AND (
                                JSON_UNQUOTE(JSON_EXTRACT(payload, '$.roll_id')) = :rid
                                OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.remaining_mounted_roll_id')) = :rid2
                            ))
                            OR (type = 'ROLL_REMAINED_MOUNTED_AFTER_OUTPUT' AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.remaining_mounted_roll_id')) = :rid3)
                        )
                        AND (
                            JSON_UNQUOTE(JSON_EXTRACT(payload, '$.worker_ot_id')) = :otid
                            OR (:trz_ot_id > 0 AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.worker_ot_id')) = :trz_otid)
                        )
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmtOut->execute([
                        ':rid' => (string)$arId,
                        ':rid2' => (string)$arId,
                        ':rid3' => (string)$arId,
                        ':otid' => (string)$workerOtId,
                        ':trz_ot_id' => $traceabilityWorkOrderId,
                        ':trz_otid' => (string)$traceabilityWorkOrderId,
                    ]);
                    $lastOut = $stmtOut->fetch(PDO::FETCH_ASSOC);
                    if ($lastOut && !empty($lastOut['payload'])) {
                        $outData = json_decode((string)$lastOut['payload'], true);
                        $ar['output_bobina_kg'] = (float)($outData['output_bobina_kg'] ?? $outData['output_binas_kg'] ?? 0);
                        $ar['output_binas_kg'] = $ar['output_bobina_kg'];
                        $ar['output_roll_id'] = (int)($outData['output_roll_id'] ?? 0);
                        $ar['output_roll_code'] = (string)($outData['output_roll_code'] ?? '');
                        $ar['output_meters'] = (float)($outData['output_meters'] ?? 0);
                        $ar['scrap_kg'] = (float)($outData['scrap_kg'] ?? 0);
                        $ar['leftover_kg'] = (float)($outData['leftover_kg'] ?? 0);
                        $ar['leftover_roll_code'] = (string)($outData['leftover_roll_code'] ?? '');
                        $ar['leftover_roll_id'] = (int)($outData['leftover_roll_id'] ?? 0);
                        $ar['remaining_mounted_roll_id'] = (int)($outData['remaining_mounted_roll_id'] ?? 0);
                        $ar['remaining_mounted_roll_code'] = (string)($outData['remaining_mounted_roll_code'] ?? '');
                        $ar['remaining_mounted_kg'] = (float)($outData['remaining_mounted_kg'] ?? 0);
                        $ar['remaining_mounted_units'] = (float)($outData['remaining_mounted_units'] ?? 0);
                        $ar['remaining_mounted_formula'] = (string)($outData['remaining_mounted_formula'] ?? '');
                        $ar['total_consumed_units'] = (float)($outData['total_consumed_units'] ?? 0);
                        $ar['total_net_consumed_kg'] = (float)($outData['total_net_consumed_kg'] ?? 0);
                        if ($ar['leftover_roll_id'] <= 0 && $ar['leftover_roll_code'] !== '') {
                            $stFindSob = $this->trzPdo->prepare("SELECT id FROM rolls WHERE roll_code = :c LIMIT 1");
                            $stFindSob->execute([':c' => $ar['leftover_roll_code']]);
                            $ar['leftover_roll_id'] = (int)$stFindSob->fetchColumn();
                        }
                        $ar['mermas_detail'] = (array)($outData['mermas_detail'] ?? []);
                        $ar['output_at'] = $lastOut['created_at'];
                    }

                    if ((string)($ar['status'] ?? '') === 'CONSUMED') {
                        $ar['is_mounted'] = false;
                    }

                    if ($ar['is_mounted'] && (string)($ar['status'] ?? '') === 'IN_PROCESS' && (float)($ar['remaining_mounted_kg'] ?? 0) > 0) {
                        $ar['entry_weight_kg'] = (float)$ar['remaining_mounted_kg'];
                        $ar['current_weight_kg'] = (float)$ar['remaining_mounted_kg'];
                        if (!empty($ar['remaining_mounted_units']) && (float)$ar['remaining_mounted_units'] > 0) {
                            $ar['received_qty'] = (float)$ar['remaining_mounted_units'];
                            $ar['available_units'] = (float)$ar['remaining_mounted_units'];
                        }
                    }
                }
                unset($ar);
                $assignedRolls=array_values($assignedRolls);
            }

            $assignedCount = count($assignedRolls);
            $requiresSupervisor = ($assignedCount >= $maxAllowedRolls) || ($plannedKg > 0 && $assignedKg >= ($plannedKg * 1.15));

            return [
                'planned_units' => (int)$amount,
                'planned_kg' => $plannedKg,
                'max_allowed_rolls' => $maxAllowedRolls,
                'assigned_rolls_count' => $assignedCount,
                'assigned_kg' => round($assignedKg, 1),
                'requires_supervisor' => $requiresSupervisor,
                'assigned_rolls' => $assignedRolls,
                'target_color' => (string)($ot['fabric_color'] ?? ''),
                'target_material' => (string)($ot['fab_type'] ?? ''),
                'target_width' => $widthCm,
                'target_grammage' => $gramms,
            ];
        } catch (Throwable $e) {
            error_log('Work order material quota: '.$e->getMessage());
            return $default;
        }
    }

    /**
     * Solicita una o varias bobinas físicas desde bodega para una Orden de Trabajo.
     * Conecta directamente con la base de datos real unibag_unibag (ERP) e instancía
     * los bultos trazables en TRZ para su posterior montaje y consumo.
     */
    public function requestRollsForWorkOrder(
        int $workerOtId,
        array $rollIds,
        string $comments = '',
        string $operatorName = '',
        string $supervisorUser = '',
        string $supervisorPass = '',
        bool $requiresSupervisor = false,
        array $rollQuantities = []
    ): array {
        if ($workerOtId <= 0 || empty($rollIds)) {
            return ['ok' => false, 'error' => 'Debe seleccionar al menos una bobina para solicitar.'];
        }

        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible.'];
        }

        // Si se supera la cuota, verificar supervisor una sola vez para el lote
        if ($requiresSupervisor) {
            if (!$this->validateSupervisor($supervisorUser, $supervisorPass)) {
                return ['ok' => false, 'error' => 'Se ha alcanzado el límite de bobinas planificadas para esta OT. Requiere autorización válida de Supervisor (usuario y contraseña incorrectos).'];
            }
        }

        try {
            $assignedCount = 0;
            $lastRollCode = '';
            $lastWeightKg = 0.0;
            $now = time();
            $productionStorehouseId = 16;
            $productionStorehouseName = '3000 INSUMOS EN PRODUCCION';

            try {
                $stmtProdWh = $this->erpPdo->query("
                    SELECT id, st_name
                    FROM company_shops_storehouses
                    WHERE id IN (16, 27) OR st_name LIKE '%3000%'
                    ORDER BY CASE WHEN id = 16 THEN 0 ELSE 1 END, id ASC
                    LIMIT 1
                ");
                $prodWh = $stmtProdWh ? $stmtProdWh->fetch(PDO::FETCH_ASSOC) : false;
                if ($prodWh) {
                    $productionStorehouseId = (int)($prodWh['id'] ?? 16);
                    $productionStorehouseName = trim((string)($prodWh['st_name'] ?? '')) ?: $productionStorehouseName;
                }
            } catch (Throwable) {}

            $stmtWh3000 = $this->trzPdo->prepare("
                INSERT INTO warehouses (code, name, erp_storehouse_id)
                VALUES (3000, :name, :erpid)
                ON DUPLICATE KEY UPDATE name = VALUES(name), erp_storehouse_id = VALUES(erp_storehouse_id)
            ");
            $stmtWh3000->execute([
                ':name' => $productionStorehouseName,
                ':erpid' => $productionStorehouseId,
            ]);
            $productionWarehouseId = (int)$this->trzPdo->lastInsertId();
            if ($productionWarehouseId <= 0) {
                $stW3000 = $this->trzPdo->prepare("SELECT id FROM warehouses WHERE code = 3000 LIMIT 1");
                $stW3000->execute();
                $productionWarehouseId = (int)$stW3000->fetchColumn();
            }

            foreach ($rollIds as $rawRollId) {
                $rawRollId = trim((string)$rawRollId);
                if ($rawRollId === '') continue;

                // CASO 1: Bobina seleccionada directamente desde el stock del ERP (unibag_unibag)
                if (preg_match('/^ERP_(\d+)_(\d+)$/', $rawRollId, $mErp)) {
                    $stId = (int)$mErp[1];
                    $itemId = (int)$mErp[2];

                    $stmtErpItem = $this->erpPdo->prepare("
                        SELECT i.*, iss.iss_inventory, css.st_name
                        FROM item i
                        JOIN item_shops_storehouses iss ON i.id = iss.item_id AND iss.st_id = :stid
                        JOIN company_shops_storehouses css ON iss.st_id = css.id
                        WHERE i.id = :itid
                        LIMIT 1
                    ");
                    $stmtErpItem->execute([':stid' => $stId, ':itid' => $itemId]);
                    $erpItem = $stmtErpItem->fetch(PDO::FETCH_ASSOC);
                    if (!$erpItem) continue;

                    $sku = trim((string)$erpItem['item_number_prod']) ?: ('ITEM' . $itemId);
                    $desc = trim((string)$erpItem['item_title']);
                    $whName = (string)$erpItem['st_name'];
                    $whCode = (int)($stId === 5 ? 100 : ($stId === 11 ? 200 : $stId));

                    $parts = explode('/', $desc);
                    $color = trim($parts[1] ?? '-');
                    $color = preg_replace('/\(pantone[^\)]*\)/i', '', $color);
                    $color = trim($color) ?: '-';

                    $widthCm = (float)$erpItem['item_reg_width'];
                    $gramms = 80.0;
                    $meters = 1100.0;
                    if (preg_match('/\/(\d+(?:\.\d+)?)[xX](\d+(?:\.\d+)?)[xX](\d+)/i', $desc, $mD)) {
                        if ($widthCm <= 0) $widthCm = (float)$mD[1];
                        $gramms = (float)$mD[2];
                        $meters = (float)$mD[3];
                    }
                    $widthMm = (int)round($widthCm * 10);
                    $weightKg = round(($widthCm / 100) * $meters * ($gramms / 1000), 1);
                    if ($weightKg <= 0) $weightKg = 88.0;

                    // Asegurar SKU en TRZ
                    $stmtSku = $this->trzPdo->prepare("
                        INSERT INTO skus (code, description, is_active)
                        VALUES (:code, :desc, 1)
                        ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = 1
                    ");
                    $stmtSku->execute([':code' => $sku, ':desc' => $desc]);
                    $skuId = (int)$this->trzPdo->lastInsertId();
                    if ($skuId <= 0) {
                        $stSk = $this->trzPdo->prepare("SELECT id FROM skus WHERE code = :code LIMIT 1");
                        $stSk->execute([':code' => $sku]);
                        $skuId = (int)$stSk->fetchColumn();
                    }

                    $reqQty = isset($rollQuantities[$rawRollId]) ? max(1, (int)$rollQuantities[$rawRollId]) : 1;
                    for ($q = 1; $q <= $reqQty; $q++) {
                        $rollCode = "BOB-{$sku}-" . date('ymd') . "-" . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
                        $insRoll = $this->trzPdo->prepare("
                            INSERT INTO rolls
                                (roll_code, sku_id, warehouse_id, weight_kg, received_qty, reception_mode, microns, width_mm, color, meters, status, current_work_order_id, process_stage, created_at)
                            VALUES
                                (:code, :sku_id, :wh_id, :weight_kg, 1, 'ERP_STOCK', :microns, :width_mm, :color, :meters, 'RECEIVED', NULL, 'PRODUCTION', NOW())
                        ");
                        $insRoll->execute([
                            ':code' => $rollCode,
                            ':sku_id' => $skuId,
                            ':wh_id' => $productionWarehouseId,
                            ':weight_kg' => $weightKg,
                            ':microns' => (int)$gramms,
                            ':width_mm' => $widthMm,
                            ':color' => $color,
                            ':meters' => $meters,
                        ]);
                        $rollId = (int)$this->trzPdo->lastInsertId();
                        $lastRollCode = $rollCode;
                        $lastWeightKg = $weightKg;

                        // Evento en TRZ
                        $stmtEvent = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
                        $stmtEvent->execute([
                            ':type' => 'ROLL_TRANSFERRED_TO_BODEGA_3000',
                            ':payload' => json_encode([
                                'roll_id' => $rollId,
                                'roll_code' => $rollCode,
                                'sku_code' => $sku,
                                'weight_kg' => $weightKg,
                                'requested_worker_ot_id' => $workerOtId,
                                'requested_by' => $operatorName,
                                'supervisor_auth' => $requiresSupervisor ? $supervisorUser : null,
                                'comments' => $comments,
                                'timestamp' => $now,
                                'from_warehouse_code' => $whCode,
                                'from_warehouse_name' => $whName,
                                'to_warehouse_code' => 3000,
                                'to_warehouse_name' => $productionStorehouseName,
                                'action' => 'REQUESTED_TO_BODEGA_3000',
                            ], JSON_UNESCAPED_UNICODE),
                        ]);

                        // Evento en prod_worker_ot_events (ERP remoto)
                        $erpComment = "Solicitud Bobina {$rollCode} [{$sku}] - {$desc} traspasada a Bodega 3000 desde {$whName} por {$operatorName}";
                        if ($requiresSupervisor && $supervisorUser !== '') {
                            $erpComment .= " [Aut. Supervisor: {$supervisorUser}]";
                        }
                        if (trim($comments) !== '') {
                            $erpComment .= " (" . trim($comments) . ")";
                        }

                        $stmtErp = $this->erpPdo->prepare("
                            INSERT INTO prod_worker_ot_events
                             (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                            VALUES
                             (:ot_id, 0, :pkg, :crtdat, :enddat, 2, 'materiales', :comments)
                        ");
                        $stmtErp->execute([
                            ':ot_id' => $workerOtId,
                            ':pkg' => $weightKg,
                            ':crtdat' => $now,
                            ':enddat' => $now,
                            ':comments' => $erpComment,
                        ]);

                        // Reflejar el traspaso físico al stock de Bodega 3000 en ERP
                        try {
                            if ($stId !== $productionStorehouseId) {
                                $this->erpPdo->prepare("
                                    UPDATE item_shops_storehouses
                                    SET iss_inventory = GREATEST(0, iss_inventory - 1)
                                    WHERE item_id = :itid AND st_id = :stid
                                ")->execute([':itid' => $itemId, ':stid' => $stId]);
                            }

                            $upd3000 = $this->erpPdo->prepare("
                                UPDATE item_shops_storehouses
                                SET iss_inventory = iss_inventory + 1
                                WHERE item_id = :itid AND st_id = :stid
                            ");
                            $upd3000->execute([':itid' => $itemId, ':stid' => $productionStorehouseId]);
                            if ($upd3000->rowCount() === 0) {
                                $shopId = 1;
                                try {
                                    $stShop = $this->erpPdo->query("SELECT id FROM company_shops ORDER BY id ASC LIMIT 1");
                                    $shopId = (int)($stShop ? $stShop->fetchColumn() : 1);
                                    if ($shopId <= 0) {
                                        $shopId = 1;
                                    }
                                } catch (Throwable) {}

                                $this->erpPdo->prepare("
                                    INSERT INTO item_shops_storehouses (item_id, shop_id, st_id, iss_inventory)
                                    VALUES (:itid, :shopid, :stid, 1)
                                ")->execute([
                                    ':itid' => $itemId,
                                    ':shopid' => $shopId,
                                    ':stid' => $productionStorehouseId,
                                ]);
                            }
                        } catch (Throwable) {}

                        $assignedCount++;
                    }
                    continue;
                }

                // CASO 2: Bobina con ID numérico ya existente en TRZ
                $rollId = (int)$rawRollId;
                if ($rollId <= 0) continue;

                $stmt = $this->trzPdo->prepare(
                    "SELECT r.*, s.code AS sku_code, s.description AS sku_desc
                     FROM rolls r
                     INNER JOIN skus s ON r.sku_id = s.id
                     WHERE r.id = :id
                     LIMIT 1"
                );
                $stmt->execute([':id' => $rollId]);
                $roll = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$roll) continue;

                $rollCode = (string)$roll['roll_code'];
                $skuCode = (string)$roll['sku_code'];
                $weightKg = (float)$roll['weight_kg'];
                $lastRollCode = $rollCode;
                $lastWeightKg = $weightKg;

                $updateRoll = $this->trzPdo->prepare(
                    "UPDATE rolls
                     SET status = 'RECEIVED',
                         current_work_order_id = NULL,
                         warehouse_id = :wh_id,
                         process_stage = 'PRODUCTION'
                     WHERE id = :id"
                );
                $updateRoll->execute([
                    ':wh_id' => $productionWarehouseId,
                    ':id' => $rollId,
                ]);

                $stmtEvent = $this->trzPdo->prepare(
                    "INSERT INTO events (type, payload) VALUES (:type, :payload)"
                );
                $stmtEvent->execute([
                    ':type' => 'ROLL_TRANSFERRED_TO_BODEGA_3000',
                    ':payload' => json_encode([
                        'roll_id' => $rollId,
                        'roll_code' => $rollCode,
                        'sku_code' => $skuCode,
                        'weight_kg' => $weightKg,
                        'requested_worker_ot_id' => $workerOtId,
                        'requested_by' => $operatorName,
                        'supervisor_auth' => $requiresSupervisor ? $supervisorUser : null,
                        'comments' => $comments,
                        'timestamp' => $now,
                        'to_warehouse_code' => 3000,
                        'to_warehouse_name' => $productionStorehouseName,
                        'action' => 'REQUESTED_TO_BODEGA_3000',
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                $erpComment = "Solicitud Bobina {$rollCode} [{$skuCode}] traspasada a Bodega 3000 por {$operatorName}";
                if ($requiresSupervisor && $supervisorUser !== '') {
                    $erpComment .= " [Aut. Supervisor: {$supervisorUser}]";
                }
                if (trim($comments) !== '') {
                    $erpComment .= " (" . trim($comments) . ")";
                }

                // Guardia anti-duplicidad: si ya se cargó esta misma bobina en los últimos 15 segundos para esta OT, no duplicar evento
                $stmtCheckDupMat = $this->erpPdo->prepare("
                    SELECT id FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id
                      AND evt_type = 'materiales'
                      AND evt_comments LIKE :roll_matcher
                      AND evt_crtdat >= :since_ts
                    ORDER BY id DESC LIMIT 1
                ");
                $stmtCheckDupMat->execute([
                    ':ot_id' => $workerOtId,
                    ':roll_matcher' => "%{$rollCode}%",
                    ':since_ts' => $now - 15,
                ]);
                if ($stmtCheckDupMat->fetchColumn()) {
                    $assignedCount++;
                    continue;
                }

                $stmtErp = $this->erpPdo->prepare(
                    "INSERT INTO prod_worker_ot_events
                     (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                     VALUES
                     (:ot_id, 0, :pkg, :crtdat, :enddat, 2, 'materiales', :comments)"
                );
                $stmtErp->execute([
                    ':ot_id' => $workerOtId,
                    ':pkg' => $weightKg,
                    ':crtdat' => $now,
                    ':enddat' => $now,
                    ':comments' => $erpComment,
                ]);

                $assignedCount++;
            }

            return [
                'ok' => true,
                'count' => $assignedCount,
                'roll_code' => $lastRollCode,
                'weight_kg' => $lastWeightKg,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al solicitar bobinas: ' . $e->getMessage()];
        }
    }

    /**
     * Compatibilidad: asigna una bobina individual a la OT
     */
    public function assignRollToWorkOrder(
        int $workerOtId,
        int $rollId,
        float $consumedWeight = 0.0,
        string $comments = '',
        string $operatorName = '',
        string $supervisorUser = '',
        string $supervisorPass = '',
        bool $requiresSupervisor = false
    ): array {
        return $this->requestRollsForWorkOrder(
            $workerOtId,
            [$rollId],
            $comments,
            $operatorName,
            $supervisorUser,
            $supervisorPass,
            $requiresSupervisor
        );
    }

    /**
     * Registra el montaje físico de una bobina en la máquina (desbobinador) solicitando el peso de ingreso.
     */
    public function mountRollOnMachine(int $workerOtId, int $rollId, string $operatorName, float $entryWeightKg = 0.0, string $comments = ''): array
    {
        if ($workerOtId <= 0 || $rollId <= 0) {
            return ['ok' => false, 'error' => 'Parámetros inválidos para montar bobina.'];
        }
        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible.'];
        }

        $rollLocks=[];
        try {
            $rollLocks=$this->lockRollState([$rollId],$workerOtId);
            $now = time();
            $stmt = $this->trzPdo->prepare("SELECT roll_code, weight_kg, status, current_work_order_id FROM rolls WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $rollId]);
            $roll = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$roll || in_array((string)$roll['status'],['CONSUMED','RETURNED','SCRAPPED'],true)) {
                return ['ok'=>false,'error'=>'La bobina no esta disponible para montaje.'];
            }
            $traceabilityWorkOrderId=$this->resolveTraceabilityWorkOrderId($workerOtId,null,false);
            $owner=(int)($roll['current_work_order_id']??0);
            if ($owner>0 && $owner!==$workerOtId && $owner!==$traceabilityWorkOrderId) {
                return ['ok'=>false,'error'=>'La bobina pertenece a otra OT activa.'];
            }
            $rollCode = $roll['roll_code'] ?? "ID #{$rollId}";
            $currentKg = (float)($roll['weight_kg'] ?? 0);
            $actualEntryKg = $entryWeightKg > 0 ? $entryWeightKg : $currentKg;

            // Guardia anti-duplicidad: si ya se montó esta misma bobina en los últimos 15 segundos para esta OT, evitar duplicidad
            if ($this->erpPdo !== null) {
                $stDupMount = $this->erpPdo->prepare("
                    SELECT id FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id
                      AND evt_type = 'materiales'
                      AND evt_comments LIKE :matcher
                      AND evt_crtdat >= :since_ts
                    ORDER BY id DESC LIMIT 1
                ");
                $stDupMount->execute([
                    ':ot_id' => $workerOtId,
                    ':matcher' => "%Montaje de Bobina " . ($rollCode ?: "ID #{$rollId}") . "%",
                    ':since_ts' => $now - 15,
                ]);
                if ($stDupMount->fetchColumn()) {
                    return ['ok' => true, 'roll_code' => $rollCode, 'entry_weight_kg' => $actualEntryKg, 'duplicate_prevented' => true];
                }
            }

            // Actualizar peso de la bobina en TRZ al peso de ingreso medido
            if ($entryWeightKg > 0) {
                $this->trzPdo->prepare("UPDATE rolls SET weight_kg = :w, status = 'IN_PROCESS' WHERE id = :id")
                    ->execute([':w' => $entryWeightKg, ':id' => $rollId]);
            } else {
                $this->trzPdo->prepare("UPDATE rolls SET status = 'IN_PROCESS' WHERE id = :id")
                    ->execute([':id' => $rollId]);
            }

            $stmtEvent = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
            $stmtEvent->execute([
                ':type' => 'ROLL_MOUNTED_ON_MACHINE',
                ':payload' => json_encode([
                    'roll_id' => $rollId,
                    'roll_code' => $rollCode,
                    'worker_ot_id' => $workerOtId,
                    'entry_weight_kg' => $actualEntryKg,
                    'operator' => $operatorName,
                    'comments' => $comments,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $comm = "Montaje de Bobina {$rollCode} en máquina (Peso ingreso: {$actualEntryKg} kg) por {$operatorName}";
            if ($comments !== '') {
                $comm .= " [{$comments}]";
            }
            $stmtErp = $this->erpPdo->prepare(
                "INSERT INTO prod_worker_ot_events
                 (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                 VALUES
                 (:ot_id, 0, :pkg, :crtdat, :enddat, 2, 'materiales', :comments)"
            );
            $stmtErp->execute([
                ':ot_id' => $workerOtId,
                ':pkg' => $actualEntryKg,
                ':crtdat' => $now,
                ':enddat' => $now,
                ':comments' => $comm,
            ]);

            return ['ok' => true, 'roll_code' => $rollCode, 'entry_weight_kg' => $actualEntryKg];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al montar bobina: ' . $e->getMessage()];
        } finally {
            $this->unlockRollState($rollLocks);
        }
    }

    /**
     * Registra la salida de una bobina montada en máquina:
     * - Peso de salida en binas / bobinas producidas ($outputBinasKg)
     * - Mermas de salida generadas por la bobina ($scrapKg)
     * - Sobrante en la bobina ($leftoverKg): si es > 0, genera un nuevo código de bobina
     *   y la registra en Bodega 3000 con trazabilidad vinculada a la bobina madre.
     * - Cierra la bobina original como CONSUMED y registra los eventos en TRZ y ERP.
     */
    public function recordRollOutputAndLeftover(
        int $workerOtId,
        int $rollId,
        float $outputBobinaKg,
        float $scrapKg,
        float $leftoverKg,
        string $comments = '',
        string $operatorName = '',
        array $mermasDetail = [],
        bool $createOutputRoll = false,
        string $outputSkuCode = '',
        float $outputMeters = 0.0,
        array $mergedSourceRollIds = [],
        bool $keepRemainingMounted = false,
        int $remainingMountedRollId = 0,
        float $remainingMountedKg = 0.0
    ): array {
        if ($rollId <= 0 || $workerOtId <= 0) {
            return ['ok' => false, 'error' => 'Parámetros inválidos para registrar salida de bobina.'];
        }
        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible.'];
        }
        if ($createOutputRoll && $outputBobinaKg <= 0) {
            return ['ok' => false, 'error' => 'Debes registrar el peso de la nueva bobina saliente para generar su etiqueta.'];
        }
        if (!empty($mergedSourceRollIds) && !$createOutputRoll) {
            return ['ok' => false, 'error' => 'Para unir varias bobinas en una sola debe generar una bobina saliente con etiqueta.'];
        }

        // Si se informaron mermas por tipo pero scrapKg vino en 0, calcular total
        if ($scrapKg <= 0 && !empty($mermasDetail)) {
            $sumKgs = 0.0;
            foreach ($mermasDetail as $md) {
                $sumKgs += (float)($md['kgs'] ?? 0);
            }
            if ($sumKgs > 0) {
                $scrapKg = round($sumKgs, 2);
            }
        }

        $rollLocks=[];
        try {
            $rollLocks=$this->lockRollState(array_merge([$rollId,$remainingMountedRollId],$mergedSourceRollIds),$workerOtId);
            $now = time();
            $stmt = $this->trzPdo->prepare("
                SELECT r.*, s.code AS sku_code, s.description AS sku_desc
                FROM rolls r
                LEFT JOIN skus s ON r.sku_id = s.id
                WHERE r.id = :id LIMIT 1
            ");
            $stmt->execute([':id' => $rollId]);
            $roll = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$roll) {
                return ['ok' => false, 'error' => 'Bobina no encontrada en trazabilidad.'];
            }

            $traceabilityWorkOrderId = $this->resolveTraceabilityWorkOrderId($workerOtId, null, false);
            $rollCode = (string)$roll['roll_code'];
            $skuId = (int)($roll['sku_id'] ?? 0);
            $skuCode = trim((string)($roll['sku_code'] ?? 'TEL'));
            $entryWeight = (float)($roll['weight_kg'] ?? 0);
            $currentWorkOrderId = (int)($roll['current_work_order_id'] ?? 0);
            if (
                $currentWorkOrderId > 0
                && $currentWorkOrderId !== $workerOtId
                && $currentWorkOrderId !== $traceabilityWorkOrderId
            ) {
                return ['ok' => false, 'error' => 'La bobina principal pertenece a otra OT activa.'];
            }

            $normalizedMergedSourceRollIds = [];
            foreach ($mergedSourceRollIds as $mergedSourceRollId) {
                $mergedSourceRollId = (int)$mergedSourceRollId;
                if ($mergedSourceRollId > 0 && $mergedSourceRollId !== $rollId) {
                    $normalizedMergedSourceRollIds[] = $mergedSourceRollId;
                }
            }
            $normalizedMergedSourceRollIds = array_values(array_unique($normalizedMergedSourceRollIds));

            $resolveMountedEntryWeightAndUnits = function (int $sourceRollId, float $fallbackWeight, float $fallbackUnits) use ($workerOtId, $traceabilityWorkOrderId): array {
                $resolvedWeight = $fallbackWeight;
                $resolvedUnits = $fallbackUnits > 0 ? $fallbackUnits : 1.0;

                $stEv = $this->trzPdo->prepare("
                    SELECT type, payload FROM events
                    WHERE (
                        (type = 'ROLL_MOUNTED_ON_MACHINE' AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.roll_id')) = :rid)
                        OR
                        (type = 'ROLL_OUTPUT_COMPLETED' AND (
                            JSON_UNQUOTE(JSON_EXTRACT(payload, '$.roll_id')) = :rid
                            OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.remaining_mounted_roll_id')) = :rid
                        ))
                        OR
                        (type = 'ROLL_REMAINED_MOUNTED_AFTER_OUTPUT' AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.remaining_mounted_roll_id')) = :rid)
                    )
                    AND (
                        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.worker_ot_id')) = :otid
                        OR (:trz_ot_id > 0 AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.worker_ot_id')) = :trz_otid)
                    )
                    ORDER BY id DESC LIMIT 1
                ");
                $stEv->execute([
                    ':rid' => (string)$sourceRollId,
                    ':otid' => (string)$workerOtId,
                    ':trz_ot_id' => $traceabilityWorkOrderId,
                    ':trz_otid' => (string)$traceabilityWorkOrderId,
                ]);
                $lastEv = $stEv->fetch(PDO::FETCH_ASSOC);
                if ($lastEv && !empty($lastEv['payload'])) {
                    $pData = json_decode((string)$lastEv['payload'], true);
                    $evType = (string)$lastEv['type'];

                    if ($evType === 'ROLL_REMAINED_MOUNTED_AFTER_OUTPUT'
                        || ($evType === 'ROLL_OUTPUT_COMPLETED' && !empty($pData['keep_remaining_mounted']) && (int)($pData['remaining_mounted_roll_id'] ?? 0) === $sourceRollId)
                    ) {
                        if (isset($pData['remaining_mounted_kg']) && (float)$pData['remaining_mounted_kg'] > 0) {
                            $resolvedWeight = (float)$pData['remaining_mounted_kg'];
                        }
                        if (!empty($pData['remaining_mounted_units']) && (float)$pData['remaining_mounted_units'] > 0) {
                            $resolvedUnits = (float)$pData['remaining_mounted_units'];
                        } else {
                            $foundDetail = false;
                            foreach ((array)($pData['source_rolls_detail'] ?? []) as $srd) {
                                if ((int)($srd['id'] ?? 0) === $sourceRollId) {
                                    if (isset($srd['remaining_units']) && (float)$srd['remaining_units'] > 0) {
                                        $resolvedUnits = (float)$srd['remaining_units'];
                                    } else {
                                        $srdConsumed = (float)($srd['consumed_units'] ?? 0);
                                        $srdAvail = (float)($srd['available_units'] ?? 1.0);
                                        if ($srdAvail <= 0) $srdAvail = 1.0;
                                        $resolvedUnits = max(0.0001, round($srdAvail - $srdConsumed, 4));
                                    }
                                    $foundDetail = true;
                                    break;
                                }
                            }
                            if (!$foundDetail) {
                                $origEntry = (float)($pData['entry_weight_kg'] ?? $pData['total_source_weight_kg'] ?? 0);
                                if ($origEntry > 0) {
                                    $resolvedUnits = round($resolvedWeight / $origEntry, 4);
                                }
                            }
                        }
                    } elseif ($evType === 'ROLL_MOUNTED_ON_MACHINE' && !empty($pData['entry_weight_kg'])) {
                        $resolvedWeight = (float)$pData['entry_weight_kg'];
                        $resolvedUnits = $fallbackUnits > 0 ? $fallbackUnits : 1.0;
                    }
                }

                if ($resolvedUnits <= 0) {
                    $resolvedUnits = 1.0;
                }

                return [$resolvedWeight, $resolvedUnits];
            };

            // Buscar peso de ingreso y unidades disponibles en el último evento de montaje o salida previa
            [$entryWeight, $primaryRollUnits] = $resolveMountedEntryWeightAndUnits(
                $rollId,
                $entryWeight,
                (float)($roll['received_qty'] ?? 1.0)
            );

            $mergedSourceRolls = [];
            $mergedSourceCodes = [];
            $mergedSourceWeightKg = 0.0;
            if (!empty($normalizedMergedSourceRollIds)) {
                $placeholders = [];
                $mergeParams = [];
                foreach ($normalizedMergedSourceRollIds as $idx => $mergedSourceRollId) {
                    $placeholder = ':merge_id_' . $idx;
                    $placeholders[] = $placeholder;
                    $mergeParams[$placeholder] = $mergedSourceRollId;
                }

                $stmtMerge = $this->trzPdo->prepare("
                    SELECT r.*, s.code AS sku_code, s.description AS sku_desc
                    FROM rolls r
                    LEFT JOIN skus s ON r.sku_id = s.id
                    WHERE r.id IN (" . implode(', ', $placeholders) . ")
                ");
                $stmtMerge->execute($mergeParams);
                $mergeRows = $stmtMerge->fetchAll(PDO::FETCH_ASSOC);
                if (count($mergeRows) !== count($normalizedMergedSourceRollIds)) {
                    return ['ok' => false, 'error' => 'Una o más bobinas adicionales ya no existen en trazabilidad.'];
                }

                $mergeRowsById = [];
                foreach ($mergeRows as $mergeRow) {
                    $mergeRowsById[(int)$mergeRow['id']] = $mergeRow;
                }

                foreach ($normalizedMergedSourceRollIds as $mergedSourceRollId) {
                    $mergeRoll = $mergeRowsById[$mergedSourceRollId] ?? null;
                    if ($mergeRoll === null) {
                        return ['ok' => false, 'error' => 'No se pudo resolver una de las bobinas adicionales seleccionadas.'];
                    }

                    $mergeCurrentOtId = (int)($mergeRoll['current_work_order_id'] ?? 0);
                    if (
                        $mergeCurrentOtId > 0
                        && $mergeCurrentOtId !== $workerOtId
                        && $mergeCurrentOtId !== $traceabilityWorkOrderId
                    ) {
                        return ['ok' => false, 'error' => 'Una de las bobinas adicionales pertenece a otra OT activa.'];
                    }

                    $mergeStatus = strtoupper((string)($mergeRoll['status'] ?? ''));
                    if (in_array($mergeStatus, ['CONSUMED', 'BLOCKED'], true)) {
                        return ['ok' => false, 'error' => 'Una de las bobinas adicionales ya no está disponible para esta salida.'];
                    }

                    $mergeRollCode = trim((string)($mergeRoll['roll_code'] ?? ''));
                    [$mergeEntryWeight, $mergeRollUnits] = $resolveMountedEntryWeightAndUnits(
                        $mergedSourceRollId,
                        (float)($mergeRoll['weight_kg'] ?? 0),
                        (float)($mergeRoll['received_qty'] ?? 1.0)
                    );

                    $mergedSourceRolls[] = [
                        'id' => $mergedSourceRollId,
                        'roll_code' => $mergeRollCode,
                        'sku_code' => trim((string)($mergeRoll['sku_code'] ?? '')),
                        'entry_weight_kg' => $mergeEntryWeight,
                        'available_units' => $mergeRollUnits,
                        'status' => $mergeStatus,
                    ];
                    $mergedSourceCodes[] = $mergeRollCode !== '' ? $mergeRollCode : ('#' . $mergedSourceRollId);
                    $mergedSourceWeightKg += $mergeEntryWeight;
                }
            }

            $leftoverRollCode = '';
            $newRollId = 0;
            $outputRollId = 0;
            $outputRollCode = '';
            $outputMeters = max(0.0, $outputMeters);
            $totalSourceWeightKg = round($entryWeight + $mergedSourceWeightKg, 2);
            $remainingMountedRollCode = '';
            $remainingMountedFormula = '';
            $remainingMountedTargetRoll = null;

            if ($keepRemainingMounted) {
                $allowedRemainingRollIds = [$rollId];
                foreach ($mergedSourceRolls as $mergedSourceRoll) {
                    $allowedRemainingRollIds[] = (int)$mergedSourceRoll['id'];
                }
                $allowedRemainingRollIds = array_values(array_unique($allowedRemainingRollIds));

                if ($remainingMountedRollId <= 0) {
                    $remainingMountedRollId = $rollId;
                }
                if (!in_array($remainingMountedRollId, $allowedRemainingRollIds, true)) {
                    return ['ok' => false, 'error' => 'La bobina que debe seguir montada no coincide con las bobinas usadas en esta salida.'];
                }

                if ($remainingMountedKg <= 0) {
                    $remainingMountedKg = max(0.0, round($totalSourceWeightKg - $outputBobinaKg - $scrapKg, 2));
                } else {
                    $remainingMountedKg = max(0.0, round($remainingMountedKg, 2));
                }
                if ($remainingMountedKg <= 0) {
                    return ['ok' => false, 'error' => 'El remanente calculado para dejar montado quedó en 0 kg. Revise la salida o las mermas.'];
                }

                if ($remainingMountedRollId === $rollId) {
                    $remainingMountedTargetRoll = [
                        'id' => $rollId,
                        'roll_code' => $rollCode,
                        'entry_weight_kg' => $entryWeight,
                        'meters' => (float)($roll['meters'] ?? 0),
                    ];
                } else {
                    foreach ($mergedSourceRolls as $mergedSourceRoll) {
                        if ((int)$mergedSourceRoll['id'] === $remainingMountedRollId) {
                            $remainingMountedTargetRoll = [
                                'id' => (int)$mergedSourceRoll['id'],
                                'roll_code' => (string)$mergedSourceRoll['roll_code'],
                                'entry_weight_kg' => (float)$mergedSourceRoll['entry_weight_kg'],
                                'meters' => (float)($mergeRowsById[$remainingMountedRollId]['meters'] ?? 0),
                            ];
                            break;
                        }
                    }
                }
                if ($remainingMountedTargetRoll === null) {
                    return ['ok' => false, 'error' => 'No se pudo resolver la bobina que seguirá montada.'];
                }

                $remainingMountedRollCode = (string)($remainingMountedTargetRoll['roll_code'] ?? '');
                $remainingMountedFormula = number_format($totalSourceWeightKg, 2, '.', '')
                    . ' - ' . number_format($outputBobinaKg, 2, '.', '')
                    . ' - ' . number_format($scrapKg, 2, '.', '')
                    . ' = ' . number_format($remainingMountedKg, 2, '.', '') . ' kg';
                $leftoverKg = 0.0;
            }

            // Consolidar todas las bobinas de entrada (principal + adicionales unidas)
            $sourceRollsList = [];
            $sourceRollsList[] = [
                'id' => $rollId,
                'roll_code' => $rollCode,
                'sku_code' => $skuCode,
                'sku_id' => $skuId,
                'entry_weight_kg' => $entryWeight,
                'available_units' => $primaryRollUnits,
                'is_primary' => true,
            ];
            foreach ($mergedSourceRolls as $mRoll) {
                $mId = (int)$mRoll['id'];
                $sourceRollsList[] = [
                    'id' => $mId,
                    'roll_code' => (string)$mRoll['roll_code'],
                    'sku_code' => (string)$mRoll['sku_code'],
                    'sku_id' => (int)($mergeRowsById[$mId]['sku_id'] ?? $skuId),
                    'entry_weight_kg' => (float)$mRoll['entry_weight_kg'],
                    'available_units' => (float)($mRoll['available_units'] ?? 1.0),
                    'is_primary' => false,
                ];
            }

            // Cálculo proporcional exacto de consumo y remanente por bobina
            $totalConsumedUnits = 0.0;
            $leftoverUnits = 0.0;
            $leftoverSourceSku = $skuCode;
            $leftoverSkuId = $skuId;

            if ($keepRemainingMounted) {
                if ($remainingMountedRollId === $rollId) {
                    $netConsumedKg = max(0.0, round($entryWeight - $remainingMountedKg, 2));
                } else {
                    $netConsumedKg = round($entryWeight, 2);
                }
                $totalNetConsumedKg = max(0.0, round($totalSourceWeightKg - $remainingMountedKg, 2));

                foreach ($sourceRollsList as &$sRoll) {
                    $sWeight = (float)$sRoll['entry_weight_kg'];
                    $sAvailUnits = (float)($sRoll['available_units'] ?? 1.0);
                    if ($sAvailUnits <= 0) $sAvailUnits = 1.0;

                    if ((int)$sRoll['id'] === $remainingMountedRollId) {
                        $sRem = min($sWeight, max(0.0, $remainingMountedKg));
                        $sConsumed = max(0.0, round($sWeight - $sRem, 2));
                    } else {
                        $sRem = 0.0;
                        $sConsumed = $sWeight;
                    }
                    $sFrac = $sWeight > 0.0 ? round(($sConsumed / $sWeight) * $sAvailUnits, 4) : $sAvailUnits;
                    $sRemUnits = $sWeight > 0.0 ? round(($sRem / $sWeight) * $sAvailUnits, 4) : 0.0;

                    $sRoll['consumed_kg'] = $sConsumed;
                    $sRoll['consumed_units'] = $sFrac;
                    $sRoll['remaining_kg'] = $sRem;
                    $sRoll['remaining_units'] = $sRemUnits;
                    $totalConsumedUnits += $sFrac;
                }
                unset($sRoll);
            } else {
                $totalNetConsumedKg = max(0.0, round($totalSourceWeightKg - $leftoverKg, 2));
                $remToConsume = $totalNetConsumedKg;
                foreach ($sourceRollsList as &$sRoll) {
                    $sWeight = (float)$sRoll['entry_weight_kg'];
                    $sAvailUnits = (float)($sRoll['available_units'] ?? 1.0);
                    if ($sAvailUnits <= 0) $sAvailUnits = 1.0;

                    $sConsumed = min($sWeight, $remToConsume);
                    $sConsumed = max(0.0, round($sConsumed, 2));
                    $remToConsume = max(0.0, round($remToConsume - $sConsumed, 2));
                    $sRem = max(0.0, round($sWeight - $sConsumed, 2));
                    $sFrac = $sWeight > 0.0 ? round(($sConsumed / $sWeight) * $sAvailUnits, 4) : $sAvailUnits;
                    $sRemUnits = $sWeight > 0.0 ? round(($sRem / $sWeight) * $sAvailUnits, 4) : 0.0;

                    $sRoll['consumed_kg'] = $sConsumed;
                    $sRoll['consumed_units'] = $sFrac;
                    $sRoll['remaining_kg'] = $sRem;
                    $sRoll['remaining_units'] = $sRemUnits;
                    $totalConsumedUnits += $sFrac;
                    if ($sRem > 0.0) {
                        $leftoverUnits += round(($sRem / max(0.01, $sWeight)) * $sAvailUnits, 4);
                        $leftoverSourceSku = (string)$sRoll['sku_code'];
                        $leftoverSkuId = (int)$sRoll['sku_id'];
                    }
                }
                unset($sRoll);
                $netConsumedKg = (float)($sourceRollsList[0]['consumed_kg'] ?? 0);
            }

            $totalConsumedUnits = max(0.0, round($totalConsumedUnits, 4));
            if ($totalConsumedUnits <= 0.0 && $totalNetConsumedKg > 0.0) {
                $totalConsumedUnits = (float)($sourceRollsList[0]['available_units'] ?? 1.0);
            }
            $leftoverUnits = max(0.0, round($leftoverUnits, 4));
            if ($leftoverKg > 0.0 && $leftoverUnits <= 0.0) {
                $leftoverUnits = round(($leftoverKg / max(0.01, $entryWeight)) * (float)($sourceRollsList[0]['available_units'] ?? 1.0), 4);
            }

            if ($createOutputRoll && $outputBobinaKg > 0) {
                $resolvedOutputSku = trim($outputSkuCode) !== '' ? trim($outputSkuCode) : $skuCode;
                $outputRes = $this->createOutputRollFromWorkOrder(
                    $workerOtId,
                    $rollId,
                    $resolvedOutputSku,
                    $outputBobinaKg,
                    $outputMeters,
                    500,
                    $operatorName,
                    array_merge([$rollId], $normalizedMergedSourceRollIds)
                );
                if (($outputRes['ok'] ?? false) !== true) {
                    return ['ok' => false, 'error' => (string)($outputRes['error'] ?? 'No se pudo generar la bobina saliente.')];
                }
                $outputRollId = (int)($outputRes['child_roll_id'] ?? 0);
                $outputRollCode = (string)($outputRes['child_roll_code'] ?? '');
            }

            // 1. SI HAY SOBRANTE EN LA BOBINA -> GENERAR NUEVO CÓDIGO CON TRAZABILIDAD Y REINTEGRAR PROPORCIÓN A BODEGA 3000
            if (!$keepRemainingMounted && $leftoverKg > 0) {
                $dateSuffix = date('ymd');
                $randSuffix = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
                $targetLeftoverSku = $leftoverSourceSku !== '' ? $leftoverSourceSku : $skuCode;
                if (str_starts_with(strtoupper($targetLeftoverSku), 'TEL')) {
                    $leftoverRollCode = "{$targetLeftoverSku}-SOB-{$dateSuffix}-{$randSuffix}";
                } elseif (str_starts_with(strtoupper($rollCode), 'BOB-')) {
                    $leftoverRollCode = "{$rollCode}-SOB-{$randSuffix}";
                } else {
                    $leftoverRollCode = "BOB-{$targetLeftoverSku}-SOB-{$dateSuffix}-{$randSuffix}";
                }

                // Resolver Bodega 3000
                $whId = $this->resolveBodega3000InsumosWarehouseId();
                if ($whId <= 0) {
                    $whId = (int)($roll['warehouse_id'] ?? 1);
                }

                // Insertar nueva bobina sobrante en TRZ disponible en Bodega 3000 con la cantidad proporcional
                $insSob = $this->trzPdo->prepare("
                    INSERT INTO rolls
                        (roll_code, sku_id, warehouse_id, weight_kg, received_qty, reception_mode,
                         status, current_work_order_id, parent_roll_id, source_work_order_id,
                         process_stage, width_mm, microns, color, meters, created_at)
                    VALUES
                        (:code, :sku_id, :wh_id, :weight_kg, :qty, 'SOBRANTE',
                         'RECEIVED', NULL, :parent_id, :src_ot,
                         'PRODUCTION', :width, :microns, :color, :meters, NOW())
                ");
                $insSob->execute([
                    ':code' => $leftoverRollCode,
                    ':sku_id' => $leftoverSkuId > 0 ? $leftoverSkuId : $skuId,
                    ':wh_id' => $whId,
                    ':weight_kg' => $leftoverKg,
                    ':qty' => $leftoverUnits > 0 ? $leftoverUnits : 1.0,
                    ':parent_id' => $rollId,
                    ':src_ot' => $workerOtId,
                    ':width' => (int)($roll['width_mm'] ?? 0),
                    ':microns' => (int)($roll['microns'] ?? 80),
                    ':color' => (string)($roll['color'] ?? ''),
                    ':meters' => round($leftoverKg / max(0.01, ($entryWeight > 0 ? ($entryWeight / max(1, (float)$roll['meters'])) : 0.08)), 1),
                ]);
                $newRollId = (int)$this->trzPdo->lastInsertId();

                // Reintegrar la fracción sobrante al inventario de Bodega 3000 en ERP
                // ya que cuando la bobina fue tomada para la OT se descontó 1 bobina completa de Bodega 3000
                if ($leftoverUnits > 0.0) {
                    try {
                        $this->adjustBodega3000InventoryBySku($targetLeftoverSku, $leftoverUnits);
                    } catch (Throwable) {}
                }

                // Registrar evento de sobrante en TRZ
                $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)")->execute([
                    ':type' => 'ROLL_LEFTOVER_CREATED',
                    ':payload' => json_encode([
                        'parent_roll_id' => $rollId,
                        'parent_roll_code' => $rollCode,
                        'new_roll_id' => $newRollId,
                        'new_roll_code' => $leftoverRollCode,
                        'leftover_kg' => $leftoverKg,
                        'leftover_units' => $leftoverUnits,
                        'warehouse' => '3000 Insumos de Produccion',
                        'warehouse_id' => $whId,
                        'worker_ot_id' => $workerOtId,
                        'operator' => $operatorName,
                        'timestamp' => $now,
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }

            // 2. CERRAR / ACTUALIZAR BOBINAS SEGÚN EL RESULTADO DEL DESMONTAJE
            $remainingMountedUnits = 0.0;
            if ($keepRemainingMounted) {
                foreach ($sourceRollsList as $srItem) {
                    if ((int)$srItem['id'] === $remainingMountedRollId) {
                        $remainingMountedUnits = (float)($srItem['remaining_units'] ?? 0.0);
                        break;
                    }
                }
                if ($remainingMountedUnits <= 0.0 && $totalSourceWeightKg > 0.0) {
                    $remainingMountedUnits = round($remainingMountedKg / $totalSourceWeightKg, 4);
                }
            }

            if ($keepRemainingMounted && $remainingMountedRollId === $rollId) {
                $remainingMeters = null;
                $rollMeters = (float)($roll['meters'] ?? 0);
                if ($entryWeight > 0 && $rollMeters > 0) {
                    $remainingMeters = round(($remainingMountedKg / $entryWeight) * $rollMeters, 1);
                }
                $this->trzPdo->prepare("
                    UPDATE rolls
                    SET weight_kg = :weight_kg,
                        received_qty = :received_qty,
                        meters = :meters,
                        status = 'IN_PROCESS',
                        source_work_order_id = :src_ot,
                        current_work_order_id = :curr_ot
                    WHERE id = :id
                ")->execute([
                    ':id' => $rollId,
                    ':weight_kg' => $remainingMountedKg,
                    ':received_qty' => $remainingMountedUnits,
                    ':meters' => $remainingMeters,
                    ':src_ot' => $workerOtId,
                    ':curr_ot' => $traceabilityWorkOrderId > 0 ? $traceabilityWorkOrderId : $currentWorkOrderId,
                ]);
            } else {
                $this->trzPdo->prepare("
                    UPDATE rolls
                    SET status = 'CONSUMED',
                        source_work_order_id = :src_ot,
                        current_work_order_id = NULL
                    WHERE id = :id
                ")->execute([':id' => $rollId, ':src_ot' => $workerOtId]);
            }

            foreach ($mergedSourceRolls as $mergedSourceRoll) {
                if ($keepRemainingMounted && (int)$mergedSourceRoll['id'] === $remainingMountedRollId) {
                    $mergeMeters = (float)($mergeRowsById[(int)$mergedSourceRoll['id']]['meters'] ?? 0);
                    $mergeEntryWeight = (float)($mergedSourceRoll['entry_weight_kg'] ?? 0);
                    $remainingMeters = null;
                    if ($mergeEntryWeight > 0 && $mergeMeters > 0) {
                        $remainingMeters = round(($remainingMountedKg / $mergeEntryWeight) * $mergeMeters, 1);
                    }
                    $this->trzPdo->prepare("
                        UPDATE rolls
                        SET weight_kg = :weight_kg,
                            received_qty = :received_qty,
                            meters = :meters,
                            status = 'IN_PROCESS',
                            source_work_order_id = :src_ot,
                            current_work_order_id = :curr_ot
                        WHERE id = :id
                    ")->execute([
                        ':id' => (int)$mergedSourceRoll['id'],
                        ':weight_kg' => $remainingMountedKg,
                        ':received_qty' => $remainingMountedUnits,
                        ':meters' => $remainingMeters,
                        ':src_ot' => $workerOtId,
                        ':curr_ot' => $traceabilityWorkOrderId > 0 ? $traceabilityWorkOrderId : $workerOtId,
                    ]);
                } else {
                    $this->trzPdo->prepare("
                        UPDATE rolls
                        SET status = 'CONSUMED',
                            source_work_order_id = :src_ot,
                            current_work_order_id = NULL
                        WHERE id = :id
                    ")->execute([
                        ':id' => (int)$mergedSourceRoll['id'],
                        ':src_ot' => $workerOtId,
                    ]);
                }

                $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)")->execute([
                    ':type' => 'ROLL_MERGED_IN_OUTPUT',
                    ':payload' => json_encode([
                        'roll_id' => (int)$mergedSourceRoll['id'],
                        'roll_code' => (string)$mergedSourceRoll['roll_code'],
                        'entry_weight_kg' => (float)$mergedSourceRoll['entry_weight_kg'],
                        'merged_into_roll_id' => $outputRollId,
                        'merged_into_roll_code' => $outputRollCode,
                        'primary_roll_id' => $rollId,
                        'primary_roll_code' => $rollCode,
                        'worker_ot_id' => $workerOtId,
                        'operator' => $operatorName,
                        'comments' => $comments,
                        'timestamp' => $now,
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }

            if ($keepRemainingMounted) {
                $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)")->execute([
                    ':type' => 'ROLL_REMAINED_MOUNTED_AFTER_OUTPUT',
                    ':payload' => json_encode([
                        'remaining_mounted_roll_id' => $remainingMountedRollId,
                        'remaining_mounted_roll_code' => $remainingMountedRollCode,
                        'remaining_mounted_kg' => $remainingMountedKg,
                        'remaining_mounted_units' => $remainingMountedUnits,
                        'remaining_mounted_formula' => $remainingMountedFormula,
                        'total_source_weight_kg' => $totalSourceWeightKg,
                        'output_bobina_kg' => $outputBobinaKg,
                        'scrap_kg' => $scrapKg,
                        'worker_ot_id' => $workerOtId,
                        'operator' => $operatorName,
                        'comments' => $comments,
                        'timestamp' => $now,
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }

            // Registrar evento de salida de bobina en TRZ
            $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)")->execute([
                ':type' => 'ROLL_OUTPUT_COMPLETED',
                ':payload' => json_encode([
                    'roll_id' => $rollId,
                    'roll_code' => $rollCode,
                    'entry_weight_kg' => $entryWeight,
                    'output_bobina_kg' => $outputBobinaKg,
                    'output_binas_kg' => $outputBobinaKg,
                    'output_roll_id' => $outputRollId,
                    'output_roll_code' => $outputRollCode,
                    'output_meters' => $outputMeters,
                    'scrap_kg' => $scrapKg,
                    'leftover_kg' => $leftoverKg,
                    'leftover_roll_id' => $newRollId,
                    'leftover_roll_code' => $leftoverRollCode,
                    'leftover_units' => $leftoverUnits,
                    'keep_remaining_mounted' => $keepRemainingMounted,
                    'remaining_mounted_roll_id' => $remainingMountedRollId,
                    'remaining_mounted_roll_code' => $remainingMountedRollCode,
                    'remaining_mounted_kg' => $remainingMountedKg,
                    'remaining_mounted_units' => $remainingMountedUnits,
                    'remaining_mounted_formula' => $remainingMountedFormula,
                    'merged_source_roll_ids' => array_column($mergedSourceRolls, 'id'),
                    'merged_source_roll_codes' => $mergedSourceCodes,
                    'merged_source_rolls' => $mergedSourceRolls,
                    'merged_source_weight_kg' => round($mergedSourceWeightKg, 2),
                    'total_source_weight_kg' => $totalSourceWeightKg,
                    'mermas_detail' => $mermasDetail,
                    'net_consumed_kg' => $netConsumedKg,
                    'total_net_consumed_kg' => $totalNetConsumedKg,
                    'total_consumed_units' => $totalConsumedUnits,
                    'source_rolls_detail' => $sourceRollsList,
                    'worker_ot_id' => $workerOtId,
                    'operator' => $operatorName,
                    'comments' => $comments,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            // 3. REGISTRAR EVENTOS EN ERP (prod_worker_ot_events)
            $unitText = number_format($totalConsumedUnits, 2, ',', '.') . ' ' . (abs($totalConsumedUnits - 1.0) < 0.0001 ? 'bobina' : 'bobinas');
            $descMat = "Salida Bobina {$rollCode}: Ingreso {$totalSourceWeightKg} kg -> Consumo: {$unitText} (" . number_format($totalNetConsumedKg, 2, ',', '.') . " kg), Salida: {$outputBobinaKg} kg, Mermas: {$scrapKg} kg";
            if (!empty($mergedSourceCodes)) {
                $descMat .= ', Unida con: ' . implode(', ', $mergedSourceCodes);
            }
            if ($keepRemainingMounted && $remainingMountedKg > 0) {
                $descMat .= ", Queda montada: {$remainingMountedRollCode} ({$remainingMountedKg} kg). Cálculo: {$remainingMountedFormula}";
            } elseif ($leftoverKg > 0) {
                $descMat .= ", Sobrante: {$leftoverKg} kg (" . number_format($leftoverUnits, 2, ',', '.') . " bobinas, Código: {$leftoverRollCode})";
            } else {
                $descMat .= " (Consumida completa)";
            }
            if ($comments !== '') {
                $descMat .= " [{$comments}]";
            }

            $stmtErpMat = $this->erpPdo->prepare("
                INSERT INTO prod_worker_ot_events
                (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                VALUES
                (:ot_id, 0, :pkg, :crtdat, :enddat, 2, 'materiales', :comments)
            ");
            $stmtErpMat->execute([
                ':ot_id' => $workerOtId,
                ':pkg' => $totalNetConsumedKg,
                ':crtdat' => $now,
                ':enddat' => $now,
                ':comments' => $descMat,
            ]);

            // 3b. Registrar consumo en ERP oficial (stockchanges / stockchanges_items) para el informe oficial de consumo por máquina
            // NOTA: Se pasa deductInventory = false porque el stock en Bodega 3000 (item_shops_storehouses)
            // fue descontado previamente al momento de tomar y montar cada bobina en la máquina.
            if ($totalNetConsumedKg > 0) {
                try {
                    $consumptionsBySku = [];
                    foreach ($sourceRollsList as $sr) {
                        $sSku = trim((string)$sr['sku_code']);
                        if ($sSku === '') $sSku = $skuCode;
                        if (!isset($consumptionsBySku[$sSku])) {
                            $consumptionsBySku[$sSku] = [
                                'sku_code' => $sSku,
                                'consumed_kg' => 0.0,
                                'consumed_units' => 0.0,
                                'roll_codes' => [],
                            ];
                        }
                        $consumptionsBySku[$sSku]['consumed_kg'] += (float)($sr['consumed_kg'] ?? 0);
                        $consumptionsBySku[$sSku]['consumed_units'] += (float)($sr['consumed_units'] ?? 0);
                        $consumptionsBySku[$sSku]['roll_codes'][] = (string)$sr['roll_code'];
                    }

                    if (count($consumptionsBySku) <= 1) {
                        $this->recordStockChangeForRollConsumption(
                            $workerOtId,
                            $rollCode,
                            $skuCode,
                            $totalNetConsumedKg,
                            0,
                            !empty($mergedSourceCodes) ? ('Unida con: ' . implode(', ', $mergedSourceCodes)) : '',
                            0,
                            $totalConsumedUnits,
                            false
                        );
                    } else {
                        foreach ($consumptionsBySku as $cSku => $cData) {
                            $cKg = round($cData['consumed_kg'], 2);
                            $cUnits = round($cData['consumed_units'], 4);
                            if ($cKg > 0 || $cUnits > 0) {
                                $cRollCode = $cData['roll_codes'][0] ?? $rollCode;
                                $cOtherCodes = array_slice($cData['roll_codes'], 1);
                                $cAnnot = !empty($cOtherCodes) ? ('Unida con: ' . implode(', ', $cOtherCodes)) : '';
                                $this->recordStockChangeForRollConsumption(
                                    $workerOtId,
                                    $cRollCode,
                                    $cSku,
                                    $cKg,
                                    0,
                                    $cAnnot,
                                    0,
                                    $cUnits,
                                    false
                                );
                            }
                        }
                    }
                } catch (Throwable) {}
            }

            // Si se informaron mermas de salida, registrarlas en el evento de merma de la OT
            $mermaEvtId = 0;
            if ($scrapKg > 0) {
                $stmtErpMerma = $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events
                    (evt_prod_worker_otid, evt_amount, evt_amount2, evt_type, evt_comments, evt_crtdat, evt_enddat, evt_status)
                    VALUES
                    (:ot_id, :amt, 0, 'merma', :comments, :crtdat, :enddat, 2)
                ");
                $stmtErpMerma->execute([
                    ':ot_id' => $workerOtId,
                    ':amt' => $scrapKg,
                    ':comments' => "Merma salida bobina {$rollCode}: {$scrapKg} kg" . ($comments !== '' ? " [{$comments}]" : ""),
                    ':crtdat' => $now,
                    ':enddat' => $now,
                ]);
                $mermaEvtId = (int)$this->erpPdo->lastInsertId();

                // Registrar desglose en prod_worker_ot_defectunits si se proporcionó
                if ($mermaEvtId > 0) {
                    $hasBreakdown = false;
                    if (!empty($mermasDetail)) {
                        foreach ($mermasDetail as $mtId => $mInfo) {
                            $mKgs = (float)($mInfo['kgs'] ?? 0);
                            $mComm = trim((string)($mInfo['comments'] ?? ''));
                            if ($mKgs > 0 || $mComm !== '') {
                                $hasBreakdown = true;
                                $stmtDefect = $this->erpPdo->prepare("
                                    INSERT INTO prod_worker_ot_defectunits
                                    (evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, evt_merma_typeid, evt_refid, evt_kgstounits, evt_mtstounits)
                                    VALUES
                                    (0, :crtdat, 1, 'merma', :comm, :type_id, :ref_id, :kgs, 0)
                                ");
                                $stmtDefect->execute([
                                    ':crtdat' => $now,
                                    ':comm' => $mComm,
                                    ':type_id' => (int)$mtId,
                                    ':ref_id' => $mermaEvtId,
                                    ':kgs' => $mKgs,
                                ]);
                            }
                        }
                    }
                    if (!$hasBreakdown && $scrapKg > 0) {
                        $stmtDefect = $this->erpPdo->prepare("
                            INSERT INTO prod_worker_ot_defectunits
                            (evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, evt_merma_typeid, evt_refid, evt_kgstounits, evt_mtstounits)
                            VALUES
                            (0, :crtdat, 1, 'merma', :comm, 6, :ref_id, :kgs, 0)
                        ");
                        $stmtDefect->execute([
                            ':crtdat' => $now,
                            ':comm' => "Merma salida bobina {$rollCode}",
                            ':ref_id' => $mermaEvtId,
                            ':kgs' => $scrapKg,
                        ]);
                    }
                }
            }

            return [
                'ok' => true,
                'roll_code' => $rollCode,
                'leftover_roll_id' => $newRollId,
                'leftover_roll_code' => $leftoverRollCode,
                'leftover_kg' => $leftoverKg,
                'output_roll_id' => $outputRollId,
                'output_roll_code' => $outputRollCode,
                'output_meters' => $outputMeters,
                'output_bobina_kg' => $outputBobinaKg,
                'output_binas_kg' => $outputBobinaKg,
                'remaining_mounted_roll_id' => $remainingMountedRollId,
                'remaining_mounted_roll_code' => $remainingMountedRollCode,
                'remaining_mounted_kg' => $remainingMountedKg,
                'scrap_kg' => $scrapKg,
                'mermas_detail' => $mermasDetail,
                'net_consumed_kg' => $netConsumedKg,
                'total_net_consumed_kg' => $totalNetConsumedKg,
                'merged_source_rolls' => $mergedSourceRolls,
                'message' => "Salida de bobina {$rollCode} registrada exitosamente."
                    . (!empty($mergedSourceCodes) ? ' Se consolidaron también: ' . implode(', ', $mergedSourceCodes) . '.' : '')
                    . ($leftoverRollCode !== '' ? " Se generó sobrante {$leftoverRollCode} ({$leftoverKg} kg) con trazabilidad en 3000 Insumos de Produccion." : "")
                    . ($remainingMountedRollCode !== '' ? " Quedó montada {$remainingMountedRollCode} ({$remainingMountedKg} kg) para el siguiente tiraje." : '')
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al registrar salida de bobina: ' . $e->getMessage()];
        } finally {
            $this->unlockRollState($rollLocks);
        }
    }

    /**
     * Registra el desmontaje de una bobina de la máquina.
     */
    public function unmountRollFromMachine(int $workerOtId, int $rollId, string $operatorName): array
    {
        if ($workerOtId <= 0 || $rollId <= 0 || $this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Parámetros inválidos.'];
        }
        $rollLocks=[];
        try {
            $rollLocks=$this->lockRollState([$rollId],$workerOtId);
            $now = time();
            $traceabilityWorkOrderId = $this->resolveTraceabilityWorkOrderId($workerOtId, null, false);
            $stmtRoll = $this->trzPdo->prepare("
                SELECT roll_code, weight_kg, current_work_order_id
                FROM rolls
                WHERE id = :id
                LIMIT 1
            ");
            $stmtRoll->execute([':id' => $rollId]);
            $roll = $stmtRoll->fetch(PDO::FETCH_ASSOC);
            if (!$roll) {
                return ['ok' => false, 'error' => 'Bobina no encontrada.'];
            }

            $rollCode = trim((string)($roll['roll_code'] ?? ''));
            $rollWeightKg = (float)($roll['weight_kg'] ?? 0);
            $currentWorkOrderId = (int)($roll['current_work_order_id'] ?? 0);
            if ($currentWorkOrderId > 0 && $currentWorkOrderId !== $workerOtId && $currentWorkOrderId !== $traceabilityWorkOrderId) {
                return ['ok' => false, 'error' => 'La bobina pertenece a otra OT activa.'];
            }

            $stmtEvent = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
            $stmtEvent->execute([
                ':type' => 'ROLL_UNMOUNTED_FROM_MACHINE',
                ':payload' => json_encode([
                    'roll_id' => $rollId,
                    'roll_code' => $rollCode,
                    'weight_kg' => $rollWeightKg,
                    'worker_ot_id' => $workerOtId,
                    'operator' => $operatorName,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            if ($this->erpPdo !== null) {
                $entryWeightKg = $rollWeightKg;
                $stmtMount = $this->trzPdo->prepare("
                    SELECT payload
                    FROM events
                    WHERE type = 'ROLL_MOUNTED_ON_MACHINE'
                      AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.roll_id')) = :rid
                      AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.worker_ot_id')) = :otid
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $stmtMount->execute([
                    ':rid' => (string)$rollId,
                    ':otid' => (string)$workerOtId,
                ]);
                $mountEvent = $stmtMount->fetch(PDO::FETCH_ASSOC);
                if ($mountEvent && !empty($mountEvent['payload'])) {
                    $mountPayload = json_decode((string)$mountEvent['payload'], true);
                    if (!empty($mountPayload['entry_weight_kg'])) {
                        $entryWeightKg = (float)$mountPayload['entry_weight_kg'];
                    }
                }

                $evtComment = 'Bobina desmontada: '
                    . ($rollCode !== '' ? $rollCode : ('#' . $rollId))
                    . ($entryWeightKg > 0 ? ' (' . number_format($entryWeightKg, 2, ',', '.') . ' kg)' : '');

                $stmtErp = $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events
                    (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                    VALUES
                    (:ot_id, 0, :pkg, :crtdat, :enddat, 2, 'materiales', :comments)
                ");
                $stmtErp->execute([
                    ':ot_id' => $workerOtId,
                    ':pkg' => $entryWeightKg,
                    ':crtdat' => $now,
                    ':enddat' => $now,
                    ':comments' => $evtComment,
                ]);
            }
            return ['ok' => true, 'roll_code' => $rollCode];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al desmontar bobina: ' . $e->getMessage()];
        } finally {
            $this->unlockRollState($rollLocks);
        }
    }

    /**
     * Desvincula una bobina no utilizada de la OT y la devuelve a bodega.
     */
    public function unassignRollFromWorkOrder(int $workerOtId, int $rollId, string $operatorName): array
    {
        if ($workerOtId <= 0 || $rollId <= 0 || $this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Parámetros inválidos.'];
        }
        $rollLocks=[];
        try {
            $rollLocks=$this->lockRollState([$rollId],$workerOtId);
            $now = time();
            $traceabilityWorkOrderId = $this->resolveTraceabilityWorkOrderId($workerOtId, null, false);
            $warehouse3000Id = $this->resolveBodega3000WarehouseId();
            $stmtRoll = $this->trzPdo->prepare("
                SELECT r.roll_code, r.reception_mode, r.weight_kg, s.code AS sku_code
                FROM rolls r
                LEFT JOIN skus s ON r.sku_id = s.id
                WHERE r.id = :id
                  AND (
                        r.current_work_order_id = :ot_id
                        OR (:trz_ot_id > 0 AND r.current_work_order_id = :trz_ot_id)
                  )
                LIMIT 1
            ");
            $stmtRoll->execute([
                ':id' => $rollId,
                ':ot_id' => $workerOtId,
                ':trz_ot_id' => $traceabilityWorkOrderId,
            ]);
            $roll = $stmtRoll->fetch(PDO::FETCH_ASSOC);
            if (!$roll) {
                return ['ok' => false, 'error' => 'La bobina no está tomada por esta OT.'];
            }

            $rollCode = trim((string)($roll['roll_code'] ?? ''));
            $rollWeightKg = (float)($roll['weight_kg'] ?? 0);
            $skuCode = trim((string)($roll['sku_code'] ?? ''));
            $wasMounted = false;
            $entryWeightKg = $rollWeightKg;

            $stmtLastMovement = $this->trzPdo->prepare("
                SELECT type, payload
                FROM events
                WHERE type IN ('ROLL_MOUNTED_ON_MACHINE', 'ROLL_UNMOUNTED_FROM_MACHINE')
                  AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.roll_id')) = :rid
                  AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.worker_ot_id')) = :otid
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmtLastMovement->execute([
                ':rid' => (string)$rollId,
                ':otid' => (string)$workerOtId,
            ]);
            $lastMovement = $stmtLastMovement->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($lastMovement) {
                $wasMounted = (string)($lastMovement['type'] ?? '') === 'ROLL_MOUNTED_ON_MACHINE';
                $movementPayload = json_decode((string)($lastMovement['payload'] ?? ''), true);
                if (!empty($movementPayload['entry_weight_kg'])) {
                    $entryWeightKg = (float)$movementPayload['entry_weight_kg'];
                }
            }

            $stmtEvent = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
            if ($wasMounted) {
                $stmtEvent->execute([
                    ':type' => 'ROLL_UNMOUNTED_FROM_MACHINE',
                    ':payload' => json_encode([
                        'roll_id' => $rollId,
                        'roll_code' => $rollCode,
                        'weight_kg' => $rollWeightKg,
                        'worker_ot_id' => $workerOtId,
                        'operator' => $operatorName,
                        'timestamp' => $now,
                        'reason' => 'RETURNED_TO_WAREHOUSE',
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }

            $stmt = $this->trzPdo->prepare("
                UPDATE rolls
                SET status = 'RECEIVED',
                    current_work_order_id = NULL,
                    warehouse_id = CASE WHEN :warehouse_3000_id > 0 THEN :warehouse_3000_id ELSE warehouse_id END
                WHERE id = :id
                  AND (
                        current_work_order_id = :ot_id
                        OR (:trz_ot_id > 0 AND current_work_order_id = :trz_ot_id)
                  )
            ");
            $stmt->execute([
                ':warehouse_3000_id' => $warehouse3000Id,
                ':id' => $rollId,
                ':ot_id' => $workerOtId,
                ':trz_ot_id' => $traceabilityWorkOrderId,
            ]);
            if (
                $stmt->rowCount() > 0
                && !empty($roll['sku_code'])
                && strtoupper((string)($roll['reception_mode'] ?? '')) !== 'MANUAL_NO_TRACEABILITY'
            ) {
                $restoreQty = (float)($roll['received_qty'] ?? 1);
                if ($restoreQty <= 0) {
                    $restoreQty = 1.0;
                }
                $this->adjustBodega3000InventoryBySku((string)$roll['sku_code'], $restoreQty);
            }

            $stmtEvent->execute([
                ':type' => 'ROLL_RETURNED_TO_WAREHOUSE',
                ':payload' => json_encode([
                    'roll_id' => $rollId,
                    'roll_code' => $rollCode,
                    'sku_code' => $skuCode,
                    'weight_kg' => $rollWeightKg,
                    'entry_weight_kg' => $entryWeightKg,
                    'was_mounted' => $wasMounted,
                    'warehouse_id' => $warehouse3000Id,
                    'worker_ot_id' => $workerOtId,
                    'operator' => $operatorName,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            if ($this->erpPdo !== null) {
                if ($wasMounted) {
                    $erpUnmountComment = 'Bobina desmontada: '
                        . ($rollCode !== '' ? $rollCode : ('#' . $rollId))
                        . ($entryWeightKg > 0 ? ' (' . number_format($entryWeightKg, 2, ',', '.') . ' kg)' : '');

                    $stmtErpUnmount = $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot_events
                        (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                        VALUES
                        (:ot_id, 0, :pkg, :crtdat, :enddat, 2, 'materiales', :comments)
                    ");
                    $stmtErpUnmount->execute([
                        ':ot_id' => $workerOtId,
                        ':pkg' => $entryWeightKg,
                        ':crtdat' => $now,
                        ':enddat' => $now,
                        ':comments' => $erpUnmountComment,
                    ]);
                }

                $erpReturnComment = 'Bobina devuelta a Bodega 3000: '
                    . ($rollCode !== '' ? $rollCode : ('#' . $rollId))
                    . ($rollWeightKg > 0 ? ' (' . number_format($rollWeightKg, 2, ',', '.') . ' kg)' : '');

                $stmtErpReturn = $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events
                    (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                    VALUES
                    (:ot_id, 0, :pkg, :crtdat, :enddat, 2, 'materiales', :comments)
                ");
                $stmtErpReturn->execute([
                    ':ot_id' => $workerOtId,
                    ':pkg' => $rollWeightKg,
                    ':crtdat' => $now,
                    ':enddat' => $now,
                    ':comments' => $erpReturnComment,
                ]);
            }

            return ['ok' => true, 'roll_code' => $rollCode];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al devolver bobina: ' . $e->getMessage()];
        } finally {
            $this->unlockRollState($rollLocks);
        }
    }

    /**
     * Obtiene el listado de bobinas cargadas o en proceso en máquinas.
     * Permite filtrar opcionalmente por una OT específica ($workerOtId).
     *
     * @return list<array{
     *     roll_id: int,
     *     roll_code: string,
     *     sku_code: string,
     *     sku_description: string,
     *     weight_kg: float,
     *     status: string,
     *     worker_ot_id: int,
     *     ot_number: string,
     *     cc_number: string,
     *     item_title: string,
     *     machine_id: int,
     *     machine_name: string,
     *     process_name: string,
     *     operator_name: string,
     *     is_mounted: bool,
     *     created_at: string
     * }>
     */
    public function getLoadedRollsAcrossMachines(?int $workerOtId = null): array
    {
        if ($this->trzPdo === null) {
            return [];
        }

        try {
            $sql = "
                SELECT 
                    r.id AS roll_id,
                    r.roll_code,
                    r.sku_id,
                    s.code AS sku_code,
                    s.description AS sku_description,
                    r.weight_kg,
                    r.status,
                    r.current_work_order_id,
                    r.source_work_order_id,
                    r.warehouse_id,
                    w.name AS warehouse_name,
                    r.reception_mode,
                    r.created_at
                FROM rolls r
                LEFT JOIN skus s ON r.sku_id = s.id
                LEFT JOIN warehouses w ON r.warehouse_id = w.id
                WHERE r.status = 'IN_PROCESS'
                   OR r.current_work_order_id IS NOT NULL
                ORDER BY r.id DESC
            ";
            $stmt = $this->trzPdo->query($sql);
            $rolls = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

            $result = [];
            foreach ($rolls as $roll) {
                $rollId = (int)$roll['roll_id'];
                $sourceOtId = (int)($roll['source_work_order_id'] ?? 0);
                $currOtId = (int)($roll['current_work_order_id'] ?? 0);

                // Obtener último evento en trazabilidad para resolver OT, máquina y operador si no están explícitos
                $stmtEvt = $this->trzPdo->prepare("
                    SELECT type, payload, created_at FROM events
                    WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.roll_id')) = :rid
                    ORDER BY id DESC LIMIT 1
                ");
                $stmtEvt->execute([':rid' => (string)$rollId]);
                $lastEvt = $stmtEvt->fetch(PDO::FETCH_ASSOC) ?: null;
                $payload = (!empty($lastEvt['payload']) && is_string($lastEvt['payload']))
                    ? (json_decode($lastEvt['payload'], true) ?: [])
                    : [];

                $physicalState=(new MachineRollContinuityService($this->erpPdo,$this->trzPdo))->state($rollId);
                // El CC de origen no cambia; el CC operativo viene de la ultima carga fisica.
                // Provenance stays unchanged; the current job follows the latest physical load.
                $resolvedWorkerOtId = $physicalState['worker_ot_id']>0
                    ? $physicalState['worker_ot_id'] : (int)($payload['worker_ot_id']??$sourceOtId);
                if ($resolvedWorkerOtId <= 0 && !empty($payload['worker_ot_id'])) {
                    $resolvedWorkerOtId = (int)$payload['worker_ot_id'];
                }
                if ($resolvedWorkerOtId <= 0 && $currOtId > 0 && $this->erpPdo !== null) {
                    $chkPwo = $this->erpPdo->prepare("SELECT id FROM prod_worker_ot WHERE id = :id LIMIT 1");
                    $chkPwo->execute([':id' => $currOtId]);
                    if ($chkPwo->fetchColumn()) {
                        $resolvedWorkerOtId = $currOtId;
                    } else {
                        $chkWo = $this->trzPdo->prepare("SELECT ot_code FROM work_orders WHERE id = :id LIMIT 1");
                        $chkWo->execute([':id' => $currOtId]);
                        $otCode = $chkWo->fetchColumn();
                        if ($otCode) {
                            $stmtAg = $this->erpPdo->prepare("
                                SELECT pwo.id 
                                FROM prod_worker_ot pwo
                                JOIN prod_agenda ag ON pwo.wok_ag_id = ag.id
                                JOIN prod_header ph ON ag.ag_prdid = ph.id
                                WHERE ph.prd_number = :ot_code
                                ORDER BY pwo.id DESC LIMIT 1
                            ");
                            $stmtAg->execute([':ot_code' => $otCode]);
                            $foundId = (int)$stmtAg->fetchColumn();
                            if ($foundId > 0) {
                                $resolvedWorkerOtId = $foundId;
                            }
                        }
                    }
                }

                // Si se solicitó filtrar por un workerOtId específico y no coincide, saltar
                if ($workerOtId !== null && $workerOtId > 0) {
                    $matchTrzWo = ($currOtId > 0 && $this->resolveTraceabilityWorkOrderId($workerOtId, null, false) === $currOtId);
                    if ($resolvedWorkerOtId !== $workerOtId && !$matchTrzWo) {
                        continue;
                    }
                }

                // Verificar si la bobina se encuentra montada
                $isMounted = $physicalState['mounted'] && (string)$roll['status']==='IN_PROCESS';

                $otInfo = [
                    'ot_number' => '',
                    'cc_number' => '',
                    'item_title' => '',
                    'machine_id' => 0,
                    'machine_name' => '',
                    'process_name' => '',
                    'operator_name' => (string)($payload['operator'] ?? ''),
                ];

                if ($resolvedWorkerOtId > 0 && $this->erpPdo !== null) {
                    $stmtOt = $this->erpPdo->prepare("
                        SELECT pwo.id AS worker_ot_id, ph.prd_number AS ot_number, ord.req_number AS cc_number, i.item_title,
                               pwi.win_equipoid, eq.equipo_name, eq.equipo_type_id,
                               CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, '')) AS op_name
                        FROM prod_worker_ot pwo
                        LEFT JOIN prod_agenda ag ON pwo.wok_ag_id = ag.id
                        LEFT JOIN prod_header ph ON ag.ag_prdid = ph.id
                        LEFT JOIN orders ord ON ag.ag_reqid = ord.id
                        LEFT JOIN orders_items oi ON ord.id = oi.req_id
                        LEFT JOIN item i ON oi.item_id = i.id
                        LEFT JOIN prod_worker_init pwi ON pwo.wok_init_id = pwi.id
                        LEFT JOIN equipo eq ON pwi.win_equipoid = eq.id
                        LEFT JOIN workers w ON pwi.win_wrkid = w.id
                        WHERE pwo.id = :otid
                        LIMIT 1
                    ");
                    $stmtOt->execute([':otid' => $resolvedWorkerOtId]);
                    $otRow = $stmtOt->fetch(PDO::FETCH_ASSOC);
                    if ($otRow) {
                        $otInfo['ot_number'] = (string)($otRow['ot_number'] ?? '');
                        $otInfo['cc_number'] = (string)($otRow['cc_number'] ?? '');
                        $otInfo['item_title'] = (string)($otRow['item_title'] ?? '');
                        $otInfo['machine_id'] = (int)($otRow['win_equipoid'] ?? 0);
                        $otInfo['machine_name'] = (string)($otRow['equipo_name'] ?? '');
                        $typeId = (int)($otRow['equipo_type_id'] ?? 0);
                        $otInfo['process_name'] = match($typeId) {
                            7 => 'Flexografía',
                            8 => 'Corte y Sellado',
                            11 => 'Serigrafía',
                            22 => 'Pulpo Serigráfico',
                            15 => 'Embalaje',
                            12 => 'Rebobinado',
                            default => 'Producción'
                        };
                        if (empty($otInfo['operator_name']) && !empty(trim((string)$otRow['op_name']))) {
                            $otInfo['operator_name'] = trim((string)$otRow['op_name']);
                        }
                    }
                }

                if (empty($otInfo['machine_name']) && !empty($payload['machine_id']) && $this->erpPdo !== null) {
                    $stMch = $this->erpPdo->prepare("SELECT equipo_name FROM equipo WHERE id = :mid LIMIT 1");
                    $stMch->execute([':mid' => $payload['machine_id']]);
                    $otInfo['machine_name'] = (string)$stMch->fetchColumn();
                }

                $result[] = [
                    'roll_id' => $rollId,
                    'roll_code' => (string)($roll['roll_code'] ?? ''),
                    'sku_code' => (string)($roll['sku_code'] ?? ''),
                    'sku_description' => (string)($roll['sku_description'] ?? ''),
                    'weight_kg' => (float)($roll['weight_kg'] ?? 0),
                    'status' => (string)($roll['status'] ?? ''),
                    'worker_ot_id' => $resolvedWorkerOtId,
                    'ot_number' => $otInfo['ot_number'],
                    'cc_number' => $otInfo['cc_number'],
                    'item_title' => $otInfo['item_title'],
                    'machine_id' => $otInfo['machine_id'],
                    'machine_name' => $otInfo['machine_name'],
                    'process_name' => $otInfo['process_name'],
                    'operator_name' => $otInfo['operator_name'],
                    'is_mounted' => $isMounted,
                    'created_at' => (string)($roll['created_at'] ?? ''),
                ];
            }

            return $result;
        } catch (Throwable $e) {
            error_log('Error in getLoadedRollsAcrossMachines: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Devuelve una bobina cargada en máquina directamente a Bodega 3000.
     * Desmonta la bobina si estaba montada, actualiza trazabilidad (estado RECEIVED, sin OT, warehouse_id Bodega 3000),
     * reintegra el inventario en el ERP en Bodega 3000 (item_shops_storehouses), e inserta los eventos
     * de auditoría tanto en TRZ (events) como en ERP (prod_worker_ot_events).
     *
     * @param int $rollId ID de la bobina en trazabilidad (rolls.id)
     * @param string $operatorName Nombre del operador o supervisor que realiza la devolución
     * @param string $reason Motivo de la devolución
     * @return array{ok: bool, roll_code?: string, sku_code?: string, message?: string, error?: string}
     */
    public function returnRollToBodega3000(int $rollId, string $operatorName = '', string $reason = ''): array
    {
        if ($rollId <= 0 || $this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Identificador de bobina no válido.'];
        }

        $rollLocks=[];
        try {
            $rollLocks=$this->lockRollState([$rollId]);
            $now = time();
            $warehouse3000Id = $this->resolveBodega3000WarehouseId();

            // 1. Obtener datos de la bobina
            $stmtRoll = $this->trzPdo->prepare("
                SELECT r.*, s.code AS sku_code, s.description AS sku_description
                FROM rolls r
                LEFT JOIN skus s ON r.sku_id = s.id
                WHERE r.id = :id
                LIMIT 1
            ");
            $stmtRoll->execute([':id' => $rollId]);
            $roll = $stmtRoll->fetch(PDO::FETCH_ASSOC);
            if (!$roll) {
                return ['ok' => false, 'error' => "Bobina #{$rollId} no encontrada en trazabilidad."];
            }

            $rollCode = trim((string)($roll['roll_code'] ?? ''));
            $skuCode = trim((string)($roll['sku_code'] ?? ''));
            $rollWeightKg = (float)($roll['weight_kg'] ?? 0);
            $currentStatus = strtoupper(trim((string)($roll['status'] ?? '')));
            $currentOtId = (int)($roll['current_work_order_id'] ?? 0);
            $sourceOtId = (int)($roll['source_work_order_id'] ?? 0);

            // Si ya está devuelta en Bodega 3000
            if ($currentStatus === 'RECEIVED' && $currentOtId === 0 && (int)($roll['warehouse_id'] ?? 0) === $warehouse3000Id) {
                return [
                    'ok' => true,
                    'already_returned' => true,
                    'roll_code' => $rollCode,
                    'sku_code' => $skuCode,
                    'message' => "La bobina {$rollCode} ya se encuentra devuelta en Bodega 3000."
                ];
            }

            // 2. Resolver worker_ot_id y eventos de montaje
            $physicalState=(new MachineRollContinuityService($this->erpPdo,$this->trzPdo))->state($rollId);
            $workerOtId = $physicalState['worker_ot_id']?:$sourceOtId;
            $wasMounted = false;
            $entryWeightKg = $rollWeightKg;

            $stmtLastMovement = $this->trzPdo->prepare("
                SELECT type, payload
                FROM events
                WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.roll_id')) = :rid
                ORDER BY id DESC
                LIMIT 5
            ");
            $stmtLastMovement->execute([':rid' => (string)$rollId]);
            $events = $stmtLastMovement->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($events as $evt) {
                $pData = !empty($evt['payload']) ? json_decode((string)$evt['payload'], true) : [];
                if ($workerOtId <= 0 && !empty($pData['worker_ot_id'])) {
                    $workerOtId = (int)$pData['worker_ot_id'];
                }
                if ($operatorName === '' && !empty($pData['operator'])) {
                    $operatorName = (string)$pData['operator'];
                }
            }

            // Verificar si el último estado de montaje fue ROLL_MOUNTED_ON_MACHINE
            $stmtLastMount = $this->trzPdo->prepare("
                SELECT type, payload
                FROM events
                WHERE type IN ('ROLL_MOUNTED_ON_MACHINE', 'ROLL_UNMOUNTED_FROM_MACHINE')
                  AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.roll_id')) = :rid
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmtLastMount->execute([':rid' => (string)$rollId]);
            $lastMount = $stmtLastMount->fetch(PDO::FETCH_ASSOC);
            if ($lastMount) {
                $wasMounted = ((string)($lastMount['type'] ?? '') === 'ROLL_MOUNTED_ON_MACHINE');
                $mPayload = json_decode((string)($lastMount['payload'] ?? ''), true);
                if (!empty($mPayload['entry_weight_kg'])) {
                    $entryWeightKg = (float)$mPayload['entry_weight_kg'];
                }
            }

            if ($operatorName === '') {
                $operatorName = 'Supervisor / Producción';
            }
            if ($reason === '') {
                $reason = 'Devolución directa a Bodega 3000 desde Informe de Producción por Máquina';
            }

            $stmtEvent = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");

            // 3. Desmontar de la máquina si estaba montada
            if ($wasMounted) {
                $stmtEvent->execute([
                    ':type' => 'ROLL_UNMOUNTED_FROM_MACHINE',
                    ':payload' => json_encode([
                        'roll_id' => $rollId,
                        'roll_code' => $rollCode,
                        'weight_kg' => $rollWeightKg,
                        'worker_ot_id' => $workerOtId,
                        'operator' => $operatorName,
                        'timestamp' => $now,
                        'reason' => 'RETURNED_TO_WAREHOUSE_FROM_REPORT',
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }

            // 4. Actualizar tabla rolls
            $stmtUpd = $this->trzPdo->prepare("
                UPDATE rolls
                SET status = 'RECEIVED',
                    current_work_order_id = NULL,
                    warehouse_id = CASE WHEN :wh_id > 0 THEN :wh_id ELSE warehouse_id END
                WHERE id = :id
            ");
            $stmtUpd->execute([
                ':wh_id' => $warehouse3000Id,
                ':id' => $rollId,
            ]);

            // 5. Devolver inventario a Bodega 3000 en ERP
            if (
                $skuCode !== ''
                && strtoupper((string)($roll['reception_mode'] ?? '')) !== 'MANUAL_NO_TRACEABILITY'
            ) {
                $restoreQty = (float)($roll['received_qty'] ?? 1);
                if ($restoreQty <= 0) {
                    $restoreQty = 1.0;
                }
                $this->adjustBodega3000InventoryBySku($skuCode, $restoreQty);
            }

            // 6. Registrar evento ROLL_RETURNED_TO_WAREHOUSE en TRZ
            $stmtEvent->execute([
                ':type' => 'ROLL_RETURNED_TO_WAREHOUSE',
                ':payload' => json_encode([
                    'roll_id' => $rollId,
                    'roll_code' => $rollCode,
                    'sku_code' => $skuCode,
                    'weight_kg' => $rollWeightKg,
                    'entry_weight_kg' => $entryWeightKg,
                    'was_mounted' => $wasMounted,
                    'warehouse_id' => $warehouse3000Id,
                    'worker_ot_id' => $workerOtId,
                    'operator' => $operatorName,
                    'reason' => $reason,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            // 7. Registrar en prod_worker_ot_events en ERP para auditoría de la OT si corresponde
            if ($this->erpPdo !== null && $workerOtId > 0) {
                if ($wasMounted) {
                    $erpUnmountComment = 'Desmontaje de Bobina '
                        . ($rollCode !== '' ? $rollCode : ('#' . $rollId))
                        . ' devuelta a Bodega 3000 (desde Informe Producción)';
                    if ($entryWeightKg > 0) {
                        $erpUnmountComment .= ' (Peso ingreso: ' . number_format($entryWeightKg, 2, '.', '') . ' kg)';
                    }
                    if (trim($operatorName) !== '') {
                        $erpUnmountComment .= ' por ' . trim($operatorName);
                    }

                    $stmtErpUnmount = $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot_events
                        (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                        VALUES
                        (:ot_id, 0, :pkg, :crtdat, :enddat, 2, 'materiales', :comments)
                    ");
                    $stmtErpUnmount->execute([
                        ':ot_id' => $workerOtId,
                        ':pkg' => $entryWeightKg,
                        ':crtdat' => $now,
                        ':enddat' => $now,
                        ':comments' => $erpUnmountComment,
                    ]);
                }

                $erpReturnComment = 'Bobina '
                    . ($rollCode !== '' ? $rollCode : ('#' . $rollId))
                    . ' devuelta a Bodega 3000 desde Informe de Producción';
                if ($skuCode !== '') {
                    $erpReturnComment .= ' [' . $skuCode . ']';
                }
                if ($rollWeightKg > 0) {
                    $erpReturnComment .= ' (' . number_format($rollWeightKg, 2, '.', '') . ' kg)';
                }
                if (trim($operatorName) !== '') {
                    $erpReturnComment .= ' por ' . trim($operatorName);
                }

                $stmtErpReturn = $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events
                    (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                    VALUES
                    (:ot_id, 0, :pkg, :crtdat, :enddat, 2, 'materiales', :comments)
                ");
                $stmtErpReturn->execute([
                    ':ot_id' => $workerOtId,
                    ':pkg' => $rollWeightKg,
                    ':crtdat' => $now,
                    ':enddat' => $now,
                    ':comments' => $erpReturnComment,
                ]);
            }

            return [
                'ok' => true,
                'roll_code' => $rollCode,
                'sku_code' => $skuCode,
                'message' => "Bobina {$rollCode} devuelta con éxito a Bodega 3000."
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al devolver bobina a Bodega 3000: ' . $e->getMessage()];
        } finally {
            $this->unlockRollState($rollLocks);
        }
    }

    /**
     * Registra el consumo real de una bobina y el eventual retazo que se devuelve a bodega,
     * garantizando la trazabilidad total del insumo.
     */
    public function recordRollConsumptionAndScrap(
        int $workerOtId,
        int $rollId,
        float $consumedKg,
        float $scrapKg,
        string $comments = '',
        string $operatorName = ''
    ): array {
        if ($rollId <= 0 || $workerOtId <= 0) {
            return ['ok' => false, 'error' => 'Parámetros inválidos.'];
        }

        $rollLocks=[];
        try {
            $rollLocks=$this->lockRollState([$rollId],$workerOtId);
            $now = time();
            $stmt = $this->trzPdo->prepare("
                SELECT r.*, s.code AS sku_code
                FROM rolls r
                LEFT JOIN skus s ON r.sku_id = s.id
                WHERE r.id = :id LIMIT 1
            ");
            $stmt->execute([':id' => $rollId]);
            $roll = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$roll) {
                return ['ok' => false, 'error' => 'Bobina no encontrada.'];
            }
            $traceabilityWorkOrderId=$this->resolveTraceabilityWorkOrderId($workerOtId,null,false);
            $owner=(int)($roll['current_work_order_id']??0);
            if ($owner>0 && $owner!==$workerOtId && $owner!==$traceabilityWorkOrderId) {
                return ['ok'=>false,'error'=>'La bobina pertenece a otra OT activa.'];
            }
            if (in_array((string)$roll['status'],['CONSUMED','RETURNED','SCRAPPED'],true)) {
                return ['ok'=>false,'error'=>'La bobina ya no esta disponible para consumo.'];
            }

            $originalWeight = (float)($roll['weight_kg'] ?? 0);
            $rollCode = (string)$roll['roll_code'];
            $skuCode = trim((string)($roll['sku_code'] ?? ''));

            $newStatus = ($scrapKg > 0) ? 'RETURNED' : 'CONSUMED';
            $upd = $this->trzPdo->prepare("
                UPDATE rolls
                SET status = :st,
                    weight_kg = :scrap_kg
                WHERE id = :id
            ");
            $upd->execute([
                ':st' => $newStatus,
                ':scrap_kg' => $scrapKg,
                ':id' => $rollId,
            ]);

            // Registrar evento en TRZ
            $evStmt = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
            $evStmt->execute([
                ':type' => 'ROLL_CONSUMED',
                ':payload' => json_encode([
                    'roll_id' => $rollId,
                    'roll_code' => $rollCode,
                    'initial_kg' => $originalWeight,
                    'consumed_kg' => $consumedKg,
                    'scrap_kg' => $scrapKg,
                    'worker_ot_id' => $workerOtId,
                    'operator' => $operatorName,
                    'comments' => $comments,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            // Registrar evento en ERP
            $desc = "Consumo Bobina {$rollCode}: {$consumedKg} kg consumidos";
            if ($scrapKg > 0) {
                $desc .= " (Retazo devuelto a bodega: {$scrapKg} kg)";
            }
            if ($comments !== '') {
                $desc .= " - {$comments}";
            }
            $stmtErp = $this->erpPdo->prepare("
                INSERT INTO prod_worker_ot_events
                (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                VALUES
                (:ot_id, 0, :pkg, :crtdat, :enddat, 2, 'materiales', :comments)
            ");
            $stmtErp->execute([
                ':ot_id' => $workerOtId,
                ':pkg' => $consumedKg,
                ':crtdat' => $now,
                ':enddat' => $now,
                ':comments' => $desc,
            ]);

            // Consumo en ERP oficial (stockchanges / stockchanges_items) para el informe oficial de consumo
            if ($consumedKg > 0) {
                try {
                    $cUnits = ($originalWeight > 0.0) ? round($consumedKg / $originalWeight, 4) : 1.0;
                    $this->recordStockChangeForRollConsumption(
                        $workerOtId,
                        $rollCode,
                        $skuCode,
                        $consumedKg,
                        0,
                        $comments,
                        0,
                        $cUnits,
                        false
                    );
                } catch (Throwable) {}
            }

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } finally {
            $this->unlockRollState($rollLocks);
        }
    }

    /**
     * Genera una bobina hija (semielaborada) en TRZ a partir del proceso productivo en la OT,
     * vinculándola a la bobina madre y registrando el evento en el ERP remoto.
     */
    public function createOutputRollFromWorkOrder(
        int $workerOtId,
        ?int $parentRollId,
        string $skuCode,
        float $weightKg,
        float $meters,
        int $warehouseCode = 500,
        string $operatorName = '',
        array $sourceRollIds = []
    ): array {
        if ($workerOtId <= 0 || $weightKg <= 0) {
            return ['ok' => false, 'error' => 'Peso y OT inválidos para generar bobina semielaborada.'];
        }

        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible.'];
        }

        try {
            // Guardia anti-duplicidad: si ya se generó una bobina de salida para esta OT con este peso en los últimos 15 segundos
            $stDupRoll = $this->trzPdo->prepare("
                SELECT id, roll_code FROM rolls
                WHERE source_work_order_id = :ot_id
                  AND weight_kg = :w
                  AND (:pid IS NULL OR parent_roll_id = :pid)
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 15 SECOND)
                ORDER BY id DESC LIMIT 1
            ");
            $stDupRoll->execute([
                ':ot_id' => $workerOtId,
                ':w' => number_format($weightKg, 3, '.', ''),
                ':pid' => ($parentRollId !== null && $parentRollId > 0) ? $parentRollId : null,
            ]);
            $existingRoll = $stDupRoll->fetch(PDO::FETCH_ASSOC);
            if ($existingRoll) {
                return [
                    'ok' => true,
                    'child_roll_id' => (int)$existingRoll['id'],
                    'child_roll_code' => (string)$existingRoll['roll_code'],
                    'duplicate_prevented' => true,
                ];
            }

            $normalizedSourceRollIds = [];
            foreach ($sourceRollIds as $sourceRollId) {
                $sourceRollId = (int)$sourceRollId;
                if ($sourceRollId > 0) {
                    $normalizedSourceRollIds[] = $sourceRollId;
                }
            }
            if ($parentRollId !== null && $parentRollId > 0) {
                array_unshift($normalizedSourceRollIds, $parentRollId);
            }
            $normalizedSourceRollIds = array_values(array_unique($normalizedSourceRollIds));

            $skuCode = trim($skuCode);
            if ($skuCode === '' && $parentRollId !== null && $parentRollId > 0) {
                $stmtParentSku = $this->trzPdo->prepare("
                    SELECT s.code
                    FROM rolls r
                    LEFT JOIN skus s ON r.sku_id = s.id
                    WHERE r.id = :id
                    LIMIT 1
                ");
                $stmtParentSku->execute([':id' => $parentRollId]);
                $skuCode = trim((string)$stmtParentSku->fetchColumn());
            }
            if ($skuCode === '') {
                $skuCode = 'OT-' . $workerOtId;
            }
            if (strlen($skuCode) > 60) {
                if ($parentRollId !== null && $parentRollId > 0) {
                    $stmtParentSku = $this->trzPdo->prepare("
                        SELECT s.code
                        FROM rolls r
                        LEFT JOIN skus s ON r.sku_id = s.id
                        WHERE r.id = :id
                        LIMIT 1
                    ");
                    $stmtParentSku->execute([':id' => $parentRollId]);
                    $parentSkuCode = trim((string)$stmtParentSku->fetchColumn());
                    if ($parentSkuCode !== '') {
                        $skuCode = $parentSkuCode;
                    }
                }
                if (strlen($skuCode) > 60) {
                    $skuCode = substr($skuCode, 0, 60);
                }
            }

            // Resolver o crear el SKU local
            $skuId = 1;
            $stmtSku = $this->trzPdo->prepare("SELECT id FROM skus WHERE code = :code LIMIT 1");
            $stmtSku->execute([':code' => $skuCode]);
            $foundSku = $stmtSku->fetch();
            if ($foundSku) {
                $skuId = (int)$foundSku['id'];
            } else {
                $insSku = $this->trzPdo->prepare("INSERT INTO skus (code, description, is_active) VALUES (:c, :d, 1)");
                $insSku->execute([':c' => $skuCode, ':d' => $skuCode]);
                $skuId = (int)$this->trzPdo->lastInsertId();
            }

            // Resolver ID de bodega
            $warehouseId = 1;
            $stmtWh = $this->trzPdo->prepare("SELECT id FROM warehouses WHERE code = :c LIMIT 1");
            $stmtWh->execute([':c' => $warehouseCode]);
            $foundWh = $stmtWh->fetch();
            if ($foundWh) {
                $warehouseId = (int)$foundWh['id'];
            }

            // Obtener especificaciones de la bobina madre si existen
            $parentWidth = null;
            $parentMicrons = null;
            $parentColor = null;
            $sourceRollCodes = [];
            if ($parentRollId !== null && $parentRollId > 0) {
                $stParent = $this->trzPdo->prepare("SELECT width_mm, microns, color FROM rolls WHERE id = :id LIMIT 1");
                $stParent->execute([':id' => $parentRollId]);
                $pRow = $stParent->fetch(PDO::FETCH_ASSOC);
                if ($pRow) {
                    $parentWidth = $pRow['width_mm'] ?? null;
                    $parentMicrons = $pRow['microns'] ?? null;
                    $parentColor = $pRow['color'] ?? null;
                }
            }
            if (!empty($normalizedSourceRollIds)) {
                $placeholders = [];
                $sourceParams = [];
                foreach ($normalizedSourceRollIds as $idx => $sourceRollId) {
                    $placeholder = ':src_roll_' . $idx;
                    $placeholders[] = $placeholder;
                    $sourceParams[$placeholder] = $sourceRollId;
                }
                $stmtSources = $this->trzPdo->prepare("
                    SELECT id, roll_code
                    FROM rolls
                    WHERE id IN (" . implode(', ', $placeholders) . ")
                    ORDER BY id ASC
                ");
                $stmtSources->execute($sourceParams);
                $sourceRows = $stmtSources->fetchAll(PDO::FETCH_ASSOC);
                foreach ($sourceRows as $sourceRow) {
                    $sourceCode = trim((string)($sourceRow['roll_code'] ?? ''));
                    if ($sourceCode !== '') {
                        $sourceRollCodes[] = $sourceCode;
                    }
                }
            }

            // Calcular metros aproximados por peso si no vinieron informados
            if ($meters <= 0 && $weightKg > 0) {
                $wCm = ($parentWidth !== null && (float)$parentWidth > 0) ? ((float)$parentWidth / 10.0) : 80.0;
                $g = ($parentMicrons !== null && (float)$parentMicrons > 0) ? (float)$parentMicrons : 80.0;
                $meters = round(($weightKg * 100000.0) / ($wCm * $g));
                if ($meters <= 0) $meters = 1.0;
            }

            // Generar código único de bobina hija
            $stmtMax = $this->trzPdo->query("SELECT MAX(id) FROM rolls");
            $nextNum = ((int)$stmtMax->fetchColumn()) + 1;
            $childRollCode = sprintf('ROLL-SE-%05d', $nextNum);

            // Insertar bobina en TRZ
            $now = time();
            $insRoll = $this->trzPdo->prepare(
                "INSERT INTO rolls
                 (roll_code, sku_id, warehouse_id, weight_kg, received_qty, meters, status, parent_roll_id, source_work_order_id, process_stage, width_mm, microns, color, created_at)
                 VALUES
                 (:code, :sku, :wh, :weight, 1.000, :meters, 'RECEIVED', :parent, :wo_id, 'PRINTED', :w_mm, :mic, :col, NOW())"
            );
            $insRoll->execute([
                ':code' => $childRollCode,
                ':sku' => $skuId,
                ':wh' => $warehouseId,
                ':weight' => number_format($weightKg, 3, '.', ''),
                ':meters' => $meters > 0 ? $meters : null,
                ':parent' => $parentRollId > 0 ? $parentRollId : null,
                ':wo_id' => $workerOtId,
                ':w_mm' => $parentWidth,
                ':mic' => $parentMicrons,
                ':col' => $parentColor,
            ]);

            $childId = (int)$this->trzPdo->lastInsertId();

            // Evento en TRZ
            $stmtEvent = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
            $stmtEvent->execute([
                ':type' => 'CHILD_ROLL_CREATED',
                ':payload' => json_encode([
                    'child_roll_id' => $childId,
                    'child_roll_code' => $childRollCode,
                    'parent_roll_id' => $parentRollId,
                    'source_roll_ids' => $normalizedSourceRollIds,
                    'source_roll_codes' => $sourceRollCodes,
                    'source_roll_count' => count($normalizedSourceRollIds),
                    'weight_kg' => $weightKg,
                    'meters' => $meters,
                    'worker_ot_id' => $workerOtId,
                    'operator' => $operatorName,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            return [
                'ok' => true,
                'child_roll_id' => $childId,
                'child_roll_code' => $childRollCode,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al crear bobina hija: ' . $e->getMessage()];
        }
    }

    /**
     * Genera cajas de producto terminado vinculadas a la OT activa en TRZ y ERP,
     * asociando la bobina de origen (source_roll_id) y opcionalmente un Pallet contenedor.
     */
    public function createOutputBoxesFromWorkOrder(
        int $workerOtId,
        string $skuFinal,
        int $boxQty,
        int $unitsPerBox,
        string $operatorName = '',
        ?int $sourceRollId = null,
        ?string $customerOrderRef = null,
        int $boxesPerPallet = 0,
        string $destinationMode = 'STOCK',
        int $warehouseId = 1
    ): array {
        if ($workerOtId <= 0 || $boxQty <= 0 || $unitsPerBox <= 0) {
            return ['ok' => false, 'error' => 'Datos de cajas inválidos.'];
        }

        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible.'];
        }

        try {
            $createdBoxes = [];
            $createdBoxIds = [];
            $now = time();
            $totalUnits = $boxQty * $unitsPerBox;

            // Auto-detectar bobina de origen montada en la OT si no fue especificada
            if ($sourceRollId === null || $sourceRollId <= 0) {
                $stmtAct = $this->trzPdo->prepare("
                    SELECT id FROM rolls
                    WHERE current_work_order_id = :otid
                    ORDER BY id DESC LIMIT 1
                ");
                $stmtAct->execute([':otid' => $workerOtId]);
                $rId = (int)$stmtAct->fetchColumn();
                if ($rId > 0) {
                    $sourceRollId = $rId;
                } else {
                    $stmtAct2 = $this->trzPdo->prepare("
                        SELECT id FROM rolls
                        WHERE source_work_order_id = :otid
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmtAct2->execute([':otid' => $workerOtId]);
                    $rId2 = (int)$stmtAct2->fetchColumn();
                    if ($rId2 > 0) {
                        $sourceRollId = $rId2;
                    }
                }
            }

            // Crear Pallet si fue solicitado
            $palletId = null;
            $palletCode = null;
            if ($boxesPerPallet > 0) {
                $stmtMaxPal = $this->trzPdo->query("SELECT MAX(id) FROM pallets");
                $nextPalNum = ((int)$stmtMaxPal->fetchColumn()) + 1;
                $palletCode = sprintf('PAL-%06d', $nextPalNum);

                $insPal = $this->trzPdo->prepare("
                    INSERT INTO pallets
                    (pallet_code, work_order_id, source_roll_id, final_sku, destination_mode, customer_order_ref, warehouse_id, box_count, operator_name, status)
                    VALUES
                    (:code, :wo_id, :roll_id, :sku, :dest, :cust_ref, :wh, :box_count, :op, 'OPEN')
                ");
                $insPal->execute([
                    ':code' => $palletCode,
                    ':wo_id' => $workerOtId,
                    ':roll_id' => $sourceRollId > 0 ? $sourceRollId : null,
                    ':sku' => trim($skuFinal) !== '' ? trim($skuFinal) : 'PRODUCTO-TERMINADO',
                    ':dest' => $destinationMode,
                    ':cust_ref' => $customerOrderRef,
                    ':wh' => $warehouseId,
                    ':box_count' => $boxQty,
                    ':op' => $operatorName,
                ]);
                $palletId = (int)$this->trzPdo->lastInsertId();
            }

            $insBox = $this->trzPdo->prepare(
                "INSERT INTO boxes
                 (box_code, work_order_id, source_roll_id, pallet_id, final_sku, units_qty, destination_mode, customer_order_ref, warehouse_id, operator_name, status)
                 VALUES
                 (:code, :wo_id, :roll_id, :pallet_id, :sku, :units, :dest, :cust_ref, :wh, :op, 'CREATED')"
            );

            for ($i = 1; $i <= $boxQty; $i++) {
                $stmtMax = $this->trzPdo->query("SELECT MAX(id) FROM boxes");
                $nextNum = ((int)$stmtMax->fetchColumn()) + 1;
                $boxCode = sprintf('BOX-%06d', $nextNum);

                $insBox->execute([
                    ':code' => $boxCode,
                    ':wo_id' => $workerOtId,
                    ':roll_id' => $sourceRollId > 0 ? $sourceRollId : null,
                    ':pallet_id' => $palletId,
                    ':sku' => trim($skuFinal) !== '' ? trim($skuFinal) : 'PRODUCTO-TERMINADO',
                    ':units' => $unitsPerBox,
                    ':dest' => $destinationMode,
                    ':cust_ref' => $customerOrderRef,
                    ':wh' => $warehouseId,
                    ':op' => $operatorName,
                ]);

                $boxId = (int)$this->trzPdo->lastInsertId();
                $createdBoxes[] = $boxCode;
                $createdBoxIds[] = $boxId;
            }

            // Registrar evento de creación de cajas en TRZ
            $stmtEvt = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
            $stmtEvt->execute([
                ':type' => 'BOXES_CREATED',
                ':payload' => json_encode([
                    'worker_ot_id' => $workerOtId,
                    'source_roll_id' => $sourceRollId,
                    'pallet_id' => $palletId,
                    'pallet_code' => $palletCode,
                    'boxes' => $createdBoxes,
                    'box_count' => $boxQty,
                    'units_per_box' => $unitsPerBox,
                    'total_units' => $totalUnits,
                    'operator' => $operatorName,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            // Registrar evento de producción en ERP remoto
            $stmtErp = $this->erpPdo->prepare(
                "INSERT INTO prod_worker_ot_events
                 (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments)
                 VALUES
                 (:ot_id, :amt, :crtdat, 1, 'prod', :comments)"
            );
            $stmtErp->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $totalUnits,
                ':crtdat' => $now,
                ':comments' => "{$boxQty} Cajas generadas (" . implode(', ', array_slice($createdBoxes, 0, 3)) . ($boxQty > 3 ? '...' : '') . ") - {$totalUnits} unidades totales" . ($palletCode ? " [Pallet: {$palletCode}]" : ''),
            ]);

            return [
                'ok' => true,
                'box_count' => $boxQty,
                'total_units' => $totalUnits,
                'boxes' => $createdBoxes,
                'box_ids' => $createdBoxIds,
                'pallet_id' => $palletId,
                'pallet_code' => $palletCode,
                'source_roll_id' => $sourceRollId,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al registrar cajas: ' . $e->getMessage()];
        }
    }

    /**
     * Construye el árbol completo de trazabilidad "Inicio a Fin" a partir de cualquier código:
     * Código de Bobina (ROLL-*), Código de Caja (BOX-*), Código de Pallet (PAL-*), Número de OT o Número de OC.
     */
    /**
     * Movimientos de una entidad por (tipo, id) o por un código dentro del JSON del payload.
     * Rendimiento: JSON_EXTRACT(...) = :code obliga a leer y parsear TODAS las filas de movements en cada
     * consulta. Se crean (una vez) columnas virtuales indexadas con esos códigos y se consulta por índice;
     * si el motor no las admite, se usa la consulta original.
     */
    private function trzMovementsFor(string $jsonKey, string $code, string $entityType, int $entityId): array
    {
        $cols = ['traceability_code' => 'mv_trz_code', 'box_code' => 'mv_box_code', 'pallet_code' => 'mv_pallet_code'];
        $col = $cols[$jsonKey] ?? null;
        if ($col !== null && $this->ensureMovementCodeColumns()) {
            $stmt = $this->trzPdo->prepare("SELECT * FROM movements WHERE (entity_type = :etype AND entity_id = :eid) OR {$col} = :code ORDER BY id DESC");
            $stmt->execute([':etype' => $entityType, ':eid' => $entityId, ':code' => $code]);
            return $stmt->fetchAll() ?: [];
        }
        $stmt = $this->trzPdo->prepare("SELECT * FROM movements WHERE (entity_type = :etype AND entity_id = :eid) OR JSON_EXTRACT(payload, '$.{$jsonKey}') = :code ORDER BY id DESC");
        $stmt->execute([':etype' => $entityType, ':eid' => $entityId, ':code' => $code]);
        return $stmt->fetchAll() ?: [];
    }

    /** Despacho confirmado (picking, guía, cliente) de un pallet o caja, o null si no ha salido. */
    private function dispatchOf(string $type, int $id): ?array
    {
        if ($this->trzPdo === null) {
            return null;
        }
        require_once dirname(__DIR__, 2) . '/DispatchService.php';
        $row = (new DispatchService($this->erpPdo, $this->trzPdo))->dispatchOf($type, $id);
        if ($row === null) {
            return null;
        }
        $g = !empty($row['guide_snapshot']) ? json_decode((string)$row['guide_snapshot'], true) : [];
        return [
            'picking' => (string)$row['code'],
            'type' => (string)$row['dispatch_type'],
            'guide' => (string)$row['guide_number'],
            'customer' => (string)($g['customer'] ?? ''),
            'confirmed_by' => (string)$row['confirmed_by'],
            'confirmed_at' => (string)$row['confirmed_at'],
        ];
    }

    private function ensureMovementCodeColumns(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        $version = 'movcols-v1';
        if (SchemaGate::done('movements-codes', $version)) {
            return $ok = true;
        }
        try {
            $have = [];
            foreach ($this->trzPdo->query('SHOW COLUMNS FROM movements')->fetchAll() as $c) {
                $have[(string)$c['Field']] = true;
            }
            foreach (['mv_trz_code' => 'traceability_code', 'mv_box_code' => 'box_code', 'mv_pallet_code' => 'pallet_code'] as $col => $key) {
                if (!isset($have[$col])) {
                    $this->trzPdo->exec("ALTER TABLE movements ADD COLUMN {$col} VARCHAR(80) GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(payload, '$.{$key}'))) VIRTUAL, ADD INDEX idx_{$col} ({$col})");
                }
            }
            SchemaGate::mark('movements-codes', $version);
            return $ok = true;
        } catch (Throwable) {
            return $ok = false;
        }
    }

    public function getTraceabilityTree(string $queryCode): ?array
    {
        $queryCode = trim($queryCode);
        if ($queryCode === '' || $this->trzPdo === null) {
            return null;
        }

        $result = [
            'query' => $queryCode,
            'warehouse_entry' => null,
            'purchase_order' => null,
            'supplier' => null,
            'roll' => null,
            'parent_roll' => null,
            'virgin_roll' => null,
            'child_rolls' => [],
            'pallet' => null,
            'pallets' => [],
            'box' => null,
            'boxes' => [],
            'work_order' => null,
            'events' => [],
            'movements' => [],
        ];

        // 0. ¿Es un código de ingreso de trazabilidad en bodega?
        $lookupRollId = 0;
        $lookupBoxId = 0;
        $lookupPalletId = 0;

        $stmtWh = $this->trzPdo->prepare("SELECT * FROM traceability_warehouse_entries WHERE traceability_code = :q OR id = :id LIMIT 1");
        $stmtWh->execute([':q' => $queryCode, ':id' => is_numeric($queryCode) ? (int)$queryCode : 0]);
        $whEntry = $stmtWh->fetch();
        if ($whEntry) {
            $result['warehouse_entry'] = $whEntry;
            try {
                $result['movements'] = $this->trzMovementsFor('traceability_code', (string)$whEntry['traceability_code'], (string)$whEntry['entity_type'], (int)($whEntry['entity_id'] ?? 0));
            } catch (Throwable) {}

            if ($whEntry['entity_type'] === 'ROLL' && !empty($whEntry['entity_id'])) {
                $lookupRollId = (int)$whEntry['entity_id'];
            } elseif ($whEntry['entity_type'] === 'BOX' && !empty($whEntry['entity_id'])) {
                $lookupBoxId = (int)$whEntry['entity_id'];
            } elseif ($whEntry['entity_type'] === 'PALLET' && !empty($whEntry['entity_id'])) {
                $lookupPalletId = (int)$whEntry['entity_id'];
            }
        }

        // 1. ¿Es una bobina?
        $stmtRoll = $this->trzPdo->prepare(
            "SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
             FROM rolls r
             INNER JOIN skus s ON r.sku_id = s.id
             INNER JOIN warehouses w ON r.warehouse_id = w.id
             WHERE r.roll_code = :q OR r.id = :id OR (r.id = :lookup_rid AND :lookup_rid > 0)
             LIMIT 1"
        );
        $stmtRoll->execute([':q' => $queryCode, ':id' => is_numeric($queryCode) ? (int)$queryCode : 0, ':lookup_rid' => $lookupRollId]);
        $roll = $stmtRoll->fetch();

        if ($roll) {
            $result['roll'] = $roll;
            if ($result['warehouse_entry'] === null) {
                $stmtWhRoll = $this->trzPdo->prepare("SELECT * FROM traceability_warehouse_entries WHERE (entity_type = 'ROLL' AND entity_id = :eid) OR traceability_code = :code LIMIT 1");
                $stmtWhRoll->execute([':eid' => (int)$roll['id'], ':code' => (string)$roll['roll_code']]);
                $result['warehouse_entry'] = $stmtWhRoll->fetch() ?: null;
            }
            if (empty($result['movements'])) {
                try {
                    $result['movements'] = $this->trzMovementsFor('traceability_code', (string)$roll['roll_code'], 'ROLL', (int)$roll['id']);
                } catch (Throwable) {}
            }

            // Buscar bobina madre si esta bobina es hija
            $parentRollId = (int)($roll['parent_roll_id'] ?? 0);
            if ($parentRollId > 0) {
                $stmtParent = $this->trzPdo->prepare(
                    "SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                     FROM rolls r
                     INNER JOIN skus s ON r.sku_id = s.id
                     LEFT JOIN warehouses w ON r.warehouse_id = w.id
                     WHERE r.id = :pid LIMIT 1"
                );
                $stmtParent->execute([':pid' => $parentRollId]);
                $result['parent_roll'] = $stmtParent->fetch() ?: null;
            }

            // Buscar datos de la OC en ERP si tiene purchase_order_id (en esta bobina o en su madre)
            $poId = (int)($roll['purchase_order_id'] ?? ($result['parent_roll']['purchase_order_id'] ?? 0));
            if ($poId > 0) {
                $stmtPo = $this->erpPdo->prepare(
                    "SELECT po.*, s.supp_company AS supplier_name, s.supp_rut AS supplier_rut, c.country_name
                     FROM supplier_order po
                     LEFT OUTER JOIN supplier s ON po.sord_supplier_id = s.id
                     LEFT OUTER JOIN country c ON s.supp_countryid = c.id
                     WHERE po.id = :id
                     LIMIT 1"
                );
                $stmtPo->execute([':id' => $poId]);
                $poData = $stmtPo->fetch();
                if ($poData) {
                    $result['purchase_order'] = [
                        'id' => (int)$poData['id'],
                        'po_code' => (string)$poData['sord_number'],
                        'date' => (int)$poData['sord_crtdat'] > 0 ? date('d.m.Y', (int)$poData['sord_crtdat']) : '-',
                    ];
                    $result['supplier'] = [
                        'name' => (string)($poData['supplier_name'] ?? 'Proveedor'),
                        'rut' => (string)($poData['supplier_rut'] ?? '-'),
                        'country' => (string)($poData['country_name'] ?? 'Chile'),
                    ];
                }
            }

            // Buscar bobinas hijas
            $stmtChildren = $this->trzPdo->prepare(
                "SELECT r.*, s.code AS sku_code, s.description AS sku_desc
                 FROM rolls r
                 INNER JOIN skus s ON r.sku_id = s.id
                 WHERE r.parent_roll_id = :pid
                 ORDER BY r.id ASC"
            );
            $stmtChildren->execute([':pid' => (int)$roll['id']]);
            $result['child_rolls'] = $stmtChildren->fetchAll();

            // Buscar OT vinculada
            $woId = (int)($roll['current_work_order_id'] ?? $roll['source_work_order_id'] ?? 0);
            if ($woId > 0) {
                $stmtOt = $this->erpPdo->prepare(
                    "SELECT t1.*, t4.prd_number, t5.req_number, t7.equipo_name, t8.type_ant_title,
                            t9.wrk_firstname, t9.wrk_lastname, t3.ag_amount AS planned_qty,
                            (SELECT COALESCE(SUM(e.evt_amount), 0)
                               FROM prod_worker_ot_events e
                              WHERE e.evt_prod_worker_otid = t1.id
                                AND e.evt_type = 'prod'
                                AND e.evt_status > 0
                                AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))) AS total_produced_qty,
                            (SELECT COALESCE(SUM(e.prod_bobina_kg), 0)
                               FROM prod_worker_ot_events e
                              WHERE e.evt_prod_worker_otid = t1.id
                                AND e.evt_type = 'prod'
                                AND e.evt_status > 0) AS total_output_kg,
                            (SELECT COALESCE(SUM(e.evt_amount_metros_lineales), 0)
                               FROM prod_worker_ot_events e
                              WHERE e.evt_prod_worker_otid = t1.id
                                AND e.evt_type = 'prod'
                                AND e.evt_status > 0) AS total_output_meters,
                            (SELECT COALESCE(SUM(d.evt_amount), 0)
                               FROM prod_worker_ot_defectunits d
                               INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                              WHERE e.evt_prod_worker_otid = t1.id
                                AND d.evt_type = 'merma'
                                AND d.evt_status > 0) AS total_merma_units,
                            (SELECT COALESCE(SUM(d.evt_kgstounits), 0)
                               FROM prod_worker_ot_defectunits d
                               INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                              WHERE e.evt_prod_worker_otid = t1.id
                                AND d.evt_type = 'merma'
                                AND d.evt_status > 0) AS total_merma_kg
                     FROM prod_worker_ot t1
                     INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
                     LEFT OUTER JOIN equipo t7 ON t2.win_equipoid = t7.id
                     LEFT OUTER JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
                     LEFT OUTER JOIN workers t9 ON t2.win_wrkid = t9.id
                     INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
                     INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
                     INNER JOIN orders t5 ON t3.ag_reqid = t5.id
                     WHERE t1.id = :id
                     LIMIT 1"
                );
                $stmtOt->execute([':id' => $woId]);
                $otData = $stmtOt->fetch();
                if ($otData) {
                    $result['work_order'] = $otData;
                    $stmtEvts = $this->erpPdo->prepare(
                        "SELECT * FROM prod_worker_ot_events WHERE evt_prod_worker_otid = :otid AND evt_status > 0 ORDER BY id ASC"
                    );
                    $stmtEvts->execute([':otid' => $woId]);
                    $result['events'] = $stmtEvts->fetchAll();
                }

                // Cajas vinculadas a la OT
                $stmtBoxes = $this->trzPdo->prepare(
                    "SELECT * FROM boxes WHERE work_order_id = :woid ORDER BY id ASC"
                );
                $stmtBoxes->execute([':woid' => $woId]);
                $result['boxes'] = $stmtBoxes->fetchAll();
            }

            // Cajas vinculadas directamente a esta bobina por source_roll_id
            $stmtDirectBoxes = $this->trzPdo->prepare(
                "SELECT * FROM boxes WHERE source_roll_id = :rid ORDER BY id ASC"
            );
            $stmtDirectBoxes->execute([':rid' => (int)$roll['id']]);
            $directBoxes = $stmtDirectBoxes->fetchAll();
            if (!empty($directBoxes)) {
                $existingBoxCodes = array_column($result['boxes'], 'box_code');
                foreach ($directBoxes as $db) {
                    if (!in_array($db['box_code'], $existingBoxCodes, true)) {
                        $result['boxes'][] = $db;
                    }
                }
            }

            // Pallets vinculados directamente a esta bobina
            $stmtDirectPallets = $this->trzPdo->prepare(
                "SELECT * FROM pallets WHERE source_roll_id = :rid ORDER BY id ASC"
            );
            $stmtDirectPallets->execute([':rid' => (int)$roll['id']]);
            $result['pallets'] = $stmtDirectPallets->fetchAll();

            require_once dirname(__DIR__, 2) . '/MaterialSupplyService.php';
            $result['supply_chain'] = (new MaterialSupplyService($this->erpPdo, $this->trzPdo))
                ->chainForRoll((int)$roll['id']);

            return $result;
        }

        // 2. ¿Es una caja (BOX-...)?
        $stmtBox = $this->trzPdo->prepare("SELECT * FROM boxes WHERE box_code = :q OR id = :id OR (id = :lookup_bid AND :lookup_bid > 0) LIMIT 1");
        $stmtBox->execute([':q' => $queryCode, ':id' => is_numeric($queryCode) ? (int)$queryCode : 0, ':lookup_bid' => $lookupBoxId]);
        $box = $stmtBox->fetch();
        if ($box) {
            $result['box'] = $box;
            $result['boxes'] = [$box];

            if (!empty($box['pallet_id'])) {
                $stmtPal = $this->trzPdo->prepare("SELECT * FROM pallets WHERE id = :pid LIMIT 1");
                $stmtPal->execute([':pid' => (int)$box['pallet_id']]);
                $result['pallet'] = $stmtPal->fetch() ?: null;
            }

            if (!empty($box['source_roll_id'])) {
                $stmtR = $this->trzPdo->prepare("
                    SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                    FROM rolls r
                    INNER JOIN skus s ON r.sku_id = s.id
                    LEFT JOIN warehouses w ON r.warehouse_id = w.id
                    WHERE r.id = :rid LIMIT 1
                ");
                $stmtR->execute([':rid' => (int)$box['source_roll_id']]);
                $result['roll'] = $stmtR->fetch() ?: null;

                if (!empty($result['roll']['parent_roll_id'])) {
                    $stmtParent = $this->trzPdo->prepare("
                        SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                        FROM rolls r
                        INNER JOIN skus s ON r.sku_id = s.id
                        LEFT JOIN warehouses w ON r.warehouse_id = w.id
                        WHERE r.id = :pid LIMIT 1
                    ");
                    $stmtParent->execute([':pid' => (int)$result['roll']['parent_roll_id']]);
                    $result['parent_roll'] = $stmtParent->fetch() ?: null;

                    if (!empty($result['parent_roll']['parent_roll_id'])) {
                        $stmtV = $this->trzPdo->prepare("
                            SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                            FROM rolls r
                            INNER JOIN skus s ON r.sku_id = s.id
                            LEFT JOIN warehouses w ON r.warehouse_id = w.id
                            WHERE r.id = :vid LIMIT 1
                        ");
                        $stmtV->execute([':vid' => (int)$result['parent_roll']['parent_roll_id']]);
                        $result['virgin_roll'] = $stmtV->fetch() ?: null;
                    }
                }

                // Resolver datos de compra desde el bulto raíz
                $rootRoll = $result['virgin_roll'] ?? $result['parent_roll'] ?? $result['roll'];
                $poId = (int)($rootRoll['purchase_order_id'] ?? 0);
                if ($poId > 0 && empty($result['purchase_order'])) {
                    $stmtPo = $this->erpPdo->prepare("
                        SELECT po.*, s.supp_company AS supplier_name, s.supp_rut AS supplier_rut, c.country_name
                        FROM supplier_order po
                        LEFT OUTER JOIN supplier s ON po.sord_supplier_id = s.id
                        LEFT OUTER JOIN country c ON s.supp_countryid = c.id
                        WHERE po.id = :id
                        LIMIT 1
                    ");
                    $stmtPo->execute([':id' => $poId]);
                    $poData = $stmtPo->fetch();
                    if ($poData) {
                        $result['purchase_order'] = [
                            'id' => (int)$poData['id'],
                            'po_code' => (string)$poData['sord_number'],
                            'date' => (int)$poData['sord_crtdat'] > 0 ? date('d.m.Y', (int)$poData['sord_crtdat']) : '-',
                        ];
                        $result['supplier'] = [
                            'name' => (string)($poData['supplier_name'] ?? 'Proveedor'),
                            'rut' => (string)($poData['supplier_rut'] ?? '-'),
                            'country' => (string)($poData['country_name'] ?? 'Chile'),
                        ];
                    }
                }
            }

            $woId = (int)($box['work_order_id'] ?? 0);
            if ($woId > 0) {
                $stmtOt = $this->erpPdo->prepare("
                    SELECT t1.*, t4.prd_number, t5.req_number, t7.equipo_name, t8.type_ant_title,
                           t9.wrk_firstname, t9.wrk_lastname, t3.ag_amount AS planned_qty,
                           (SELECT COALESCE(SUM(e.evt_amount), 0)
                              FROM prod_worker_ot_events e
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND e.evt_type = 'prod'
                               AND e.evt_status > 0
                               AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))) AS total_produced_qty,
                           (SELECT COALESCE(SUM(e.prod_bobina_kg), 0)
                              FROM prod_worker_ot_events e
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND e.evt_type = 'prod'
                               AND e.evt_status > 0) AS total_output_kg,
                           (SELECT COALESCE(SUM(e.evt_amount_metros_lineales), 0)
                              FROM prod_worker_ot_events e
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND e.evt_type = 'prod'
                               AND e.evt_status > 0) AS total_output_meters,
                           (SELECT COALESCE(SUM(d.evt_amount), 0)
                              FROM prod_worker_ot_defectunits d
                              INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND d.evt_type = 'merma'
                               AND d.evt_status > 0) AS total_merma_units,
                           (SELECT COALESCE(SUM(d.evt_kgstounits), 0)
                              FROM prod_worker_ot_defectunits d
                              INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND d.evt_type = 'merma'
                               AND d.evt_status > 0) AS total_merma_kg
                    FROM prod_worker_ot t1
                    INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
                    LEFT OUTER JOIN equipo t7 ON t2.win_equipoid = t7.id
                    LEFT OUTER JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
                    LEFT OUTER JOIN workers t9 ON t2.win_wrkid = t9.id
                    INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
                    INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
                    INNER JOIN orders t5 ON t3.ag_reqid = t5.id
                    WHERE t1.id = :id LIMIT 1
                ");
                $stmtOt->execute([':id' => $woId]);
                $result['work_order'] = $stmtOt->fetch() ?: null;

                $stmtEvts = $this->erpPdo->prepare("
                    SELECT * FROM prod_worker_ot_events WHERE evt_prod_worker_otid = :otid AND evt_status > 0 ORDER BY id ASC
                ");
                $stmtEvts->execute([':otid' => $woId]);
                $result['events'] = $stmtEvts->fetchAll() ?: [];
            }

            try {
                $result['movements'] = $this->trzMovementsFor('box_code', (string)$box['box_code'], 'BOX', (int)$box['id']);
            } catch (Throwable) {}
            $result['dispatch'] = $this->dispatchOf('BOX', (int)$box['id']);

            return $result;
        }

        // 2b. ¿Es un pallet (PAL-...)?
        $stmtPallet = $this->trzPdo->prepare("SELECT * FROM pallets WHERE pallet_code = :q OR id = :id OR (id = :lookup_pid AND :lookup_pid > 0) LIMIT 1");
        $stmtPallet->execute([':q' => $queryCode, ':id' => is_numeric($queryCode) ? (int)$queryCode : 0, ':lookup_pid' => $lookupPalletId]);
        $pallet = $stmtPallet->fetch();
        if ($pallet) {
            $result['pallet'] = $pallet;

            $stmtBoxes = $this->trzPdo->prepare("SELECT * FROM boxes WHERE pallet_id = :pid ORDER BY id ASC");
            $stmtBoxes->execute([':pid' => (int)$pallet['id']]);
            $result['boxes'] = $stmtBoxes->fetchAll() ?: [];

            if (!empty($pallet['source_roll_id'])) {
                $stmtR = $this->trzPdo->prepare("
                    SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                    FROM rolls r
                    INNER JOIN skus s ON r.sku_id = s.id
                    LEFT JOIN warehouses w ON r.warehouse_id = w.id
                    WHERE r.id = :rid LIMIT 1
                ");
                $stmtR->execute([':rid' => (int)$pallet['source_roll_id']]);
                $result['roll'] = $stmtR->fetch() ?: null;

                if (!empty($result['roll']['parent_roll_id'])) {
                    $stmtParent = $this->trzPdo->prepare("
                        SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                        FROM rolls r
                        INNER JOIN skus s ON r.sku_id = s.id
                        LEFT JOIN warehouses w ON r.warehouse_id = w.id
                        WHERE r.id = :pid LIMIT 1
                    ");
                    $stmtParent->execute([':pid' => (int)$result['roll']['parent_roll_id']]);
                    $result['parent_roll'] = $stmtParent->fetch() ?: null;

                    if (!empty($result['parent_roll']['parent_roll_id'])) {
                        $stmtV = $this->trzPdo->prepare("
                            SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                            FROM rolls r
                            INNER JOIN skus s ON r.sku_id = s.id
                            LEFT JOIN warehouses w ON r.warehouse_id = w.id
                            WHERE r.id = :vid LIMIT 1
                        ");
                        $stmtV->execute([':vid' => (int)$result['parent_roll']['parent_roll_id']]);
                        $result['virgin_roll'] = $stmtV->fetch() ?: null;
                    }
                }
            }

            $woId = (int)($pallet['work_order_id'] ?? 0);
            if ($woId > 0) {
                $stmtOt = $this->erpPdo->prepare("
                    SELECT t1.*, t4.prd_number, t5.req_number, t7.equipo_name, t8.type_ant_title,
                           t9.wrk_firstname, t9.wrk_lastname, t3.ag_amount AS planned_qty,
                           (SELECT COALESCE(SUM(e.evt_amount), 0)
                              FROM prod_worker_ot_events e
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND e.evt_type = 'prod'
                               AND e.evt_status > 0
                               AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%')) ) AS total_produced_qty,
                           (SELECT COALESCE(SUM(e.prod_bobina_kg), 0)
                              FROM prod_worker_ot_events e
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND e.evt_type = 'prod'
                               AND e.evt_status > 0) AS total_output_kg,
                           (SELECT COALESCE(SUM(e.evt_amount_metros_lineales), 0)
                              FROM prod_worker_ot_events e
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND e.evt_type = 'prod'
                               AND e.evt_status > 0) AS total_output_meters,
                           (SELECT COALESCE(SUM(d.evt_amount), 0)
                              FROM prod_worker_ot_defectunits d
                              INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND d.evt_type = 'merma'
                               AND d.evt_status > 0) AS total_merma_units,
                           (SELECT COALESCE(SUM(d.evt_kgstounits), 0)
                              FROM prod_worker_ot_defectunits d
                              INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND d.evt_type = 'merma'
                               AND d.evt_status > 0) AS total_merma_kg
                    FROM prod_worker_ot t1
                    INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
                    LEFT OUTER JOIN equipo t7 ON t2.win_equipoid = t7.id
                    LEFT OUTER JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
                    LEFT OUTER JOIN workers t9 ON t2.win_wrkid = t9.id
                    INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
                    INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
                    INNER JOIN orders t5 ON t3.ag_reqid = t5.id
                    WHERE t1.id = :id LIMIT 1
                ");
                $stmtOt->execute([':id' => $woId]);
                $result['work_order'] = $stmtOt->fetch() ?: null;

                $stmtEvts = $this->erpPdo->prepare("
                    SELECT * FROM prod_worker_ot_events WHERE evt_prod_worker_otid = :otid AND evt_status > 0 ORDER BY id ASC
                ");
                $stmtEvts->execute([':otid' => $woId]);
                $result['events'] = $stmtEvts->fetchAll() ?: [];
            }

            try {
                $result['movements'] = $this->trzMovementsFor('pallet_code', (string)$pallet['pallet_code'], 'PALLET', (int)$pallet['id']);
            } catch (Throwable) {}
            $result['dispatch'] = $this->dispatchOf('PALLET', (int)$pallet['id']);

            return $result;
        }

        // 3. ¿Es una OT de producción (por prd_number o id)?
        $cleanOt = $queryCode;
        if (preg_match('#(?:OT|PRD)[\s\-]*(\d+)#i', $queryCode, $mOt)) {
            $cleanOt = $mOt[1];
        }
        $numOt = is_numeric($cleanOt) ? (int)$cleanOt : 0;

        $stmtOtSearch = $this->erpPdo->prepare(
            "SELECT t1.id AS worker_ot_id, t4.prd_number
             FROM prod_worker_ot t1
             INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
             INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
             WHERE t4.prd_number = :p_prd OR t4.id = :p_hdr OR t3.id = :p_agd OR t1.id = :p_wot
             ORDER BY t1.id DESC LIMIT 1"
        );
        $stmtOtSearch->execute([
            ':p_prd' => $cleanOt,
            ':p_hdr' => $numOt,
            ':p_agd' => $numOt,
            ':p_wot' => $numOt,
        ]);
        $otRow = $stmtOtSearch->fetch();
        if ($otRow) {
            $woId = (int)$otRow['worker_ot_id'];
            // Buscar rollos vinculados a esta OT
            $stmtRollsInOt = $this->trzPdo->prepare(
                "SELECT roll_code FROM rolls WHERE current_work_order_id = :woid1 OR source_work_order_id = :woid2 LIMIT 1"
            );
            $stmtRollsInOt->execute([':woid1' => $woId, ':woid2' => $woId]);
            $firstRoll = $stmtRollsInOt->fetchColumn();
            if ($firstRoll) {
                return $this->getTraceabilityTree((string)$firstRoll);
            }

            // Si la OT aún no tiene bobinas vinculadas, armar los datos directos de la OT
            $stmtDirectOt = $this->erpPdo->prepare(
                "SELECT t1.*, t4.prd_number, t5.req_number, t7.equipo_name, t8.type_ant_title,
                        t9.wrk_firstname, t9.wrk_lastname
                 FROM prod_worker_ot t1
                 INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
                 LEFT OUTER JOIN equipo t7 ON t2.win_equipoid = t7.id
                 LEFT OUTER JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
                 LEFT OUTER JOIN workers t9 ON t2.win_wrkid = t9.id
                 INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
                 INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
                 INNER JOIN orders t5 ON t3.ag_reqid = t5.id
                 WHERE t1.id = :wo_id
                 LIMIT 1"
            );
            $stmtDirectOt->execute([':wo_id' => $woId]);
            $dOt = $stmtDirectOt->fetch();
            if ($dOt) {
                $result['work_order'] = $dOt;
                $stmtEvts = $this->erpPdo->prepare(
                    "SELECT * FROM prod_worker_ot_events WHERE evt_prod_worker_otid = :otid AND evt_status > 0 ORDER BY id ASC"
                );
                $stmtEvts->execute([':otid' => $woId]);
                $result['events'] = $stmtEvts->fetchAll();

                $stmtBoxes = $this->trzPdo->prepare("SELECT * FROM boxes WHERE work_order_id = :woid ORDER BY id ASC");
                $stmtBoxes->execute([':woid' => $woId]);
                $result['boxes'] = $stmtBoxes->fetchAll();
                return $result;
            }
        }

        // 4. ¿Es una Orden de Compra (OC)?
        if (preg_match('#^OC[\d]+#i', $queryCode) === 1 || is_numeric($queryCode)) {
            $stmtPo = $this->erpPdo->prepare(
                "SELECT po.*, s.supp_company AS supplier_name, s.supp_rut AS supplier_rut, c.country_name
                 FROM supplier_order po
                 LEFT OUTER JOIN supplier s ON po.sord_supplier_id = s.id
                 LEFT OUTER JOIN country c ON s.supp_countryid = c.id
                 WHERE po.sord_number = :p_sord OR po.id = :p_sord_id
                 LIMIT 1"
            );
            $stmtPo->execute([
                ':p_sord' => $queryCode,
                ':p_sord_id' => is_numeric($queryCode) ? (int)$queryCode : 0,
            ]);
            $poData = $stmtPo->fetch();
            if ($poData) {
                $result['purchase_order'] = [
                    'id' => (int)$poData['id'],
                    'po_code' => (string)$poData['sord_number'],
                    'date' => (int)$poData['sord_crtdat'] > 0 ? date('d.m.Y', (int)$poData['sord_crtdat']) : '-',
                ];
                $result['supplier'] = [
                    'name' => (string)($poData['supplier_name'] ?? 'Proveedor'),
                    'rut' => (string)($poData['supplier_rut'] ?? '-'),
                    'country' => (string)($poData['country_name'] ?? 'Chile'),
                ];

                $stmtPoRolls = $this->trzPdo->prepare(
                    "SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                     FROM rolls r
                     INNER JOIN skus s ON r.sku_id = s.id
                     INNER JOIN warehouses w ON r.warehouse_id = w.id
                     WHERE r.purchase_order_id = :po_id
                     ORDER BY r.id ASC LIMIT 1"
                );
                $stmtPoRolls->execute([':po_id' => (int)$poData['id']]);
                $rFound = $stmtPoRolls->fetch();
                if ($rFound) {
                    $result['roll'] = $rFound;
                }
                return $result;
            }
        }

        return null;
    }



    /**
     * Obtiene y auto-inicializa el contexto de Anilox para una OT flexográfica.
     * Replica exactamente editxid.php líneas 90-340.
     */
    public function getAniloxContext(int $agId, int $reqId, int $equipoId, int $userId = 0): array
    {
        $itemAnilox = [];
        $detalleAnilox = [];
        $aniloxDescMap = [];
        $aniloxInkConfigByUnit = [];

        try {
            // 1. Catálogo de anilox para la máquina
            $stmtAni = $this->erpPdo->prepare(
                "SELECT i.id, i.item_number_prod, i.item_title
                 FROM item i
                 INNER JOIN item_productcats ip ON i.id = ip.item_id
                 INNER JOIN productcats p ON p.id = ip.cat_id AND p.cat_prefix = 'ANI'
                 INNER JOIN item_equipos_rel ier ON ier.item_id = i.id
                 WHERE i.item_status > 0 AND ier.equipo_id = :eq_id
                 ORDER BY i.item_number_prod ASC"
            );
            $stmtAni->execute([':eq_id' => $equipoId]);
            $itemAnilox = $stmtAni->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Fallback: Si no hay anilox asociados por máquina, cargar todos los anilox activos
            if (empty($itemAnilox)) {
                $stmtFallback = $this->erpPdo->query(
                    "SELECT i.id, i.item_number_prod, i.item_title
                     FROM item i
                     INNER JOIN item_productcats ip ON i.id = ip.item_id
                     INNER JOIN productcats p ON p.id = ip.cat_id AND p.cat_prefix = 'ANI'
                     WHERE i.item_status > 0
                     ORDER BY i.item_number_prod ASC"
                );
                $itemAnilox = $stmtFallback->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            // 2. Verificar si existe cabecera prod_anilox_ot
            $stmtAniOt = $this->erpPdo->prepare(
                "SELECT * FROM prod_anilox_ot
                 WHERE prod_anilox_agid = :ag_id AND prod_anilox_reqid = :req_id
                 LIMIT 1"
            );
            $stmtAniOt->execute([':ag_id' => $agId, ':req_id' => $reqId]);
            $aniOt = $stmtAniOt->fetch(PDO::FETCH_ASSOC);

            // Si no existe, auto-crear según editxid.php 272-330
            if (!is_array($aniOt) || empty($aniOt['id'])) {
                $stmtColors = $this->erpPdo->prepare(
                    "SELECT fab_print_colordesc_1 AS color1,
                            fab_print_colordesc_2 AS color2,
                            fab_print_colordesc_3 AS color3,
                            fab_print_colordesc_4 AS color4,
                            fab_print_colordesc_5 AS color5,
                            fab_print_colordesc_6 AS color6
                     FROM orders_items
                     WHERE req_id = :req_id
                     LIMIT 1"
                );
                $stmtColors->execute([':req_id' => $reqId]);
                $colorsRow = $stmtColors->fetch(PDO::FETCH_ASSOC) ?: [];

                $now = time();
                $stmtInsPao = $this->erpPdo->prepare(
                    "INSERT INTO prod_anilox_ot
                     (prod_anilox_agid, prod_anilox_reqid, prod_anilox_unidad,
                      prod_anilox_fecha_creacion, prod_anilox_user_creacion,
                      prod_anilox_fecha_actualiza, prod_anilox_user_actualiza)
                     VALUES
                     (:ag_id, :req_id, 0, :cdate, :cuser, :udate, :uuser)"
                );
                $stmtInsPao->execute([
                    ':ag_id' => $agId,
                    ':req_id' => $reqId,
                    ':cdate' => $now,
                    ':cuser' => $userId,
                    ':udate' => $now,
                    ':uuser' => $userId,
                ]);
                $stkId = (int)$this->erpPdo->lastInsertId();

                if ($stkId > 0) {
                    $stmtInsWorker = $this->erpPdo->prepare(
                        "INSERT INTO prod_anilox_ot_worker
                         (paow_unidad, paow_posicion, paow_color, paow_anilox, paow_ot_worker_id)
                         VALUES
                         (:u, :p, :color, 0, :wid)"
                    );
                    for ($x = 1; $x <= 6; $x++) {
                        $col = (string)($colorsRow['color' . $x] ?? '');
                        $stmtInsWorker->execute([
                            ':u' => $x,
                            ':p' => $x,
                            ':color' => $col,
                            ':wid' => $stkId,
                        ]);
                    }
                }

                // Reconsultar la cabecera
                $stmtAniOt->execute([':ag_id' => $agId, ':req_id' => $reqId]);
                $aniOt = $stmtAniOt->fetch(PDO::FETCH_ASSOC);
            }

            // 3. Consultar las filas de prod_anilox_ot_worker
            if (is_array($aniOt) && !empty($aniOt['id'])) {
                $stkId = (int)$aniOt['id'];
                $stmtWorkerAni = $this->erpPdo->prepare(
                    "SELECT * FROM prod_anilox_ot_worker
                     WHERE paow_ot_worker_id = :stk_id
                     ORDER BY paow_unidad ASC, paow_posicion ASC"
                );
                $stmtWorkerAni->execute([':stk_id' => $stkId]);
                $detalleAnilox = $stmtWorkerAni->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $aniMap = [];
                foreach ($itemAnilox as $ia) {
                    $aniMap[(int)$ia['id']] = $ia['item_number_prod'] . '-' . $ia['item_title'];
                }

                foreach ($detalleAnilox as $da) {
                    $pos = (int)($da['paow_posicion'] ?? $da['paow_unidad'] ?? 0);
                    $aid = (int)($da['paow_anilox'] ?? 0);
                    if ($aid > 0 && isset($aniMap[$aid])) {
                        $aniloxDescMap[$pos] = $aniMap[$aid];
                    }
                }
            }

            $aniloxInkConfigByUnit = $this->getFlexoAniloxInkConfigByUnit($agId);
        } catch (Throwable) {
            // Ignore errors
        }

        return [
            'item_anilox' => $itemAnilox,
            'detalle_anilox' => $detalleAnilox,
            'anilox_desc_map' => $aniloxDescMap,
            'anilox_ink_config_by_unit' => $aniloxInkConfigByUnit,
        ];
    }

    public function ensureFlexoAniloxInkSchema(): void
    {
        if ($this->trzPdo === null) {
            return;
        }

        try {
            $this->trzPdo->exec("
                CREATE TABLE IF NOT EXISTS flexo_anilox_ink_configs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    ag_id BIGINT UNSIGNED NOT NULL,
                    req_id BIGINT UNSIGNED NULL,
                    worker_ot_id BIGINT UNSIGNED NULL,
                    unidad INT NOT NULL,
                    posicion INT NOT NULL DEFAULT 0,
                    color_label VARCHAR(180) NULL,
                    anilox_id BIGINT UNSIGNED NULL,
                    ink_item_id BIGINT UNSIGNED NULL,
                    ink_code VARCHAR(80) NULL,
                    ink_name VARCHAR(255) NULL,
                    loaded_kg DECIMAL(10,3) NOT NULL DEFAULT 0.000,
                    leftover_kg DECIMAL(10,3) NOT NULL DEFAULT 0.000,
                    consumed_kg DECIMAL(10,3) NOT NULL DEFAULT 0.000,
                    is_mixed TINYINT(1) NOT NULL DEFAULT 0,
                    mixture_notes VARCHAR(255) NULL,
                    created_at BIGINT NOT NULL,
                    updated_at BIGINT NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_flexo_anilox_ink_ag_unit (ag_id, unidad),
                    KEY idx_flexo_anilox_ink_req (req_id),
                    KEY idx_flexo_anilox_ink_worker_ot (worker_ot_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            // Historial de cambios de anilox/tintas: nada se pierde aunque la configuración se reescriba.
            $this->trzPdo->exec("
                CREATE TABLE IF NOT EXISTS flexo_anilox_change_log (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    ag_id BIGINT UNSIGNED NOT NULL,
                    worker_ot_id BIGINT UNSIGNED NULL,
                    unidad INT NOT NULL,
                    action VARCHAR(20) NOT NULL,
                    anilox_id BIGINT UNSIGNED NULL,
                    previous_anilox_id BIGINT UNSIGNED NULL,
                    color_label VARCHAR(180) NULL,
                    ink_item_id BIGINT UNSIGNED NULL,
                    loaded_kg DECIMAL(10,3) NULL,
                    leftover_kg DECIMAL(10,3) NULL,
                    detail VARCHAR(255) NULL,
                    user_id BIGINT NULL,
                    created_at BIGINT NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_flexo_anilox_log_ag (ag_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $this->trzPdo->exec("
                CREATE TABLE IF NOT EXISTS flexo_anilox_ink_mix_components (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    config_id BIGINT UNSIGNED NOT NULL,
                    position_no INT NOT NULL DEFAULT 0,
                    item_id BIGINT UNSIGNED NULL,
                    item_code VARCHAR(80) NULL,
                    item_name VARCHAR(255) NULL,
                    qty_kg DECIMAL(10,3) NOT NULL DEFAULT 0.000,
                    created_at BIGINT NOT NULL,
                    updated_at BIGINT NOT NULL,
                    PRIMARY KEY (id),
                    KEY idx_flexo_anilox_mix_cfg (config_id),
                    KEY idx_flexo_anilox_mix_item (item_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Throwable) {}
    }

    /** Registra un cambio de anilox/tintas. Nunca interrumpe el guardado principal. */
    private function logFlexoAniloxChange(
        int $agId,
        int $unidad,
        string $action,
        ?int $aniloxId,
        ?int $previousAniloxId,
        string $colorLabel = '',
        ?int $inkItemId = null,
        ?float $loadedKg = null,
        ?float $leftoverKg = null,
        string $detail = '',
        int $userId = 0,
        ?int $workerOtId = null
    ): void {
        if ($this->trzPdo === null || $agId <= 0) {
            return;
        }
        try {
            $this->trzPdo->prepare("
                INSERT INTO flexo_anilox_change_log
                    (ag_id, worker_ot_id, unidad, action, anilox_id, previous_anilox_id, color_label, ink_item_id, loaded_kg, leftover_kg, detail, user_id, created_at)
                VALUES
                    (:ag, :wot, :unidad, :action, :anilox, :prev, :color, :ink, :loaded, :leftover, :detail, :user, :now)
            ")->execute([
                ':ag' => $agId,
                ':wot' => $workerOtId !== null && $workerOtId > 0 ? $workerOtId : null,
                ':unidad' => $unidad,
                ':action' => $action,
                ':anilox' => $aniloxId !== null && $aniloxId > 0 ? $aniloxId : null,
                ':prev' => $previousAniloxId !== null && $previousAniloxId > 0 ? $previousAniloxId : null,
                ':color' => $colorLabel !== '' ? mb_substr($colorLabel, 0, 180) : null,
                ':ink' => $inkItemId !== null && $inkItemId > 0 ? $inkItemId : null,
                ':loaded' => $loadedKg,
                ':leftover' => $leftoverKg,
                ':detail' => $detail !== '' ? mb_substr($detail, 0, 255) : null,
                ':user' => $userId > 0 ? $userId : null,
                ':now' => time(),
            ]);
        } catch (Throwable $e) {
            error_log('Historial de anilox: ' . $e->getMessage());
        }
    }

    /**
     * Intercambia entre dos unidades TODO lo registrado en la base local (tinta, cargas, mezcla, color y anilox),
     * para que siga a la unidad cuando el operador la mueve (el ERP intercambia color y anilox por su lado).
     *
     * @return bool|null true = intercambió, false = no había nada que intercambiar, null = error (no se cambió nada)
     */
    private function swapFlexoAniloxInkUnits(int $agId, int $unitA, int $unitB): ?bool
    {
        if ($this->trzPdo === null || $agId <= 0 || $unitA <= 0 || $unitB <= 0 || $unitA === $unitB) {
            return false;
        }
        try {
            $this->ensureFlexoAniloxInkSchema();
            $count = $this->trzPdo->prepare('SELECT COUNT(*) FROM flexo_anilox_ink_configs WHERE ag_id = :ag AND unidad IN (:a, :b)');
            $count->execute([':ag' => $agId, ':a' => $unitA, ':b' => $unitB]);
            if ((int)$count->fetchColumn() === 0) {
                return false;
            }
            $temp = -1000 - $unitA;
            $own = !$this->trzPdo->inTransaction();
            if ($own) {
                $this->trzPdo->beginTransaction();
            }
            $upd = $this->trzPdo->prepare('UPDATE flexo_anilox_ink_configs SET unidad = :to, posicion = :to WHERE ag_id = :ag AND unidad = :from');
            $upd->execute([':to' => $temp, ':ag' => $agId, ':from' => $unitA]);
            $upd->execute([':to' => $unitA, ':ag' => $agId, ':from' => $unitB]);
            $upd->execute([':to' => $unitB, ':ag' => $agId, ':from' => $temp]);
            if ($own) {
                $this->trzPdo->commit();
            }
            return true;
        } catch (Throwable $e) {
            if ($this->trzPdo->inTransaction()) {
                $this->trzPdo->rollBack();
            }
            error_log('Intercambio de tintas de anilox: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFlexoAniloxInkConfigByUnit(int $agId): array
    {
        if ($agId <= 0 || $this->trzPdo === null) {
            return [];
        }

        $this->ensureFlexoAniloxInkSchema();

        $rowsByUnit = [];
        try {
            $stmt = $this->trzPdo->prepare("
                SELECT *
                FROM flexo_anilox_ink_configs
                WHERE ag_id = :ag_id
                ORDER BY unidad ASC
            ");
            $stmt->execute([':ag_id' => $agId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($rows === []) {
                return [];
            }

            $configIds = [];
            foreach ($rows as $row) {
                $configId = (int)($row['id'] ?? 0);
                $unidad = (int)($row['unidad'] ?? 0);
                if ($configId <= 0 || $unidad <= 0) {
                    continue;
                }
                $configIds[] = $configId;
                $row['components'] = [];
                $row['loaded_kg'] = (float)($row['loaded_kg'] ?? 0);
                $row['leftover_kg'] = (float)($row['leftover_kg'] ?? 0);
                $row['consumed_kg'] = (float)($row['consumed_kg'] ?? 0);
                $row['is_mixed'] = (int)($row['is_mixed'] ?? 0);
                $rowsByUnit[$unidad] = $row;
            }

            if ($configIds !== []) {
                $placeholders = [];
                $params = [];
                foreach ($configIds as $idx => $configId) {
                    $placeholder = ':cfg_' . $idx;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $configId;
                }
                $stmtComponents = $this->trzPdo->prepare("
                    SELECT *
                    FROM flexo_anilox_ink_mix_components
                    WHERE config_id IN (" . implode(', ', $placeholders) . ")
                    ORDER BY config_id ASC, position_no ASC, id ASC
                ");
                $stmtComponents->execute($params);
                $components = $stmtComponents->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($components as $component) {
                    $configId = (int)($component['config_id'] ?? 0);
                    foreach ($rowsByUnit as &$unitRow) {
                        if ((int)($unitRow['id'] ?? 0) !== $configId) {
                            continue;
                        }
                        $unitRow['components'][] = [
                            'id' => (int)($component['id'] ?? 0),
                            'position_no' => (int)($component['position_no'] ?? 0),
                            'item_id' => (int)($component['item_id'] ?? 0),
                            'item_code' => (string)($component['item_code'] ?? ''),
                            'item_name' => (string)($component['item_name'] ?? ''),
                            'qty_kg' => (float)($component['qty_kg'] ?? 0),
                        ];
                        break;
                    }
                    unset($unitRow);
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $rowsByUnit;
    }

    public function saveFlexoAniloxInkConfig(
        int $agId,
        int $reqId,
        int $workerOtId,
        array $rows,
        int $userId = 0
    ): array {
        if ($agId <= 0) {
            return ['ok' => false, 'error' => 'No se pudo identificar la agenda de anilox para guardar las tintas.'];
        }
        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible para guardar tintas de anilox.'];
        }

        $this->ensureFlexoAniloxInkSchema();
        $now = time();

        try {
            $itemIds = [];
            foreach ($rows as $row) {
                $baseItemId = (int)($row['ink_item_id'] ?? 0);
                if ($baseItemId > 0) {
                    $itemIds[] = $baseItemId;
                }
                foreach (($row['components'] ?? []) as $component) {
                    $componentItemId = (int)($component['item_id'] ?? 0);
                    if ($componentItemId > 0) {
                        $itemIds[] = $componentItemId;
                    }
                }
            }
            $itemIds = array_values(array_unique($itemIds));

            $itemsById = [];
            if ($itemIds !== []) {
                $placeholders = [];
                $params = [];
                foreach ($itemIds as $idx => $itemId) {
                    $placeholder = ':item_' . $idx;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $itemId;
                }
                $stmtItems = $this->erpPdo->prepare("
                    SELECT id, item_number_prod, item_title
                    FROM item
                    WHERE id IN (" . implode(', ', $placeholders) . ")
                ");
                $stmtItems->execute($params);
                $itemRows = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($itemRows as $itemRow) {
                    $itemsById[(int)$itemRow['id']] = [
                        'code' => trim((string)($itemRow['item_number_prod'] ?? '')),
                        'name' => trim((string)($itemRow['item_title'] ?? '')),
                    ];
                }
            }

            if ($this->trzPdo->inTransaction()) {
                $this->trzPdo->rollBack();
            }
            $this->trzPdo->beginTransaction();

            $stmtConfigIds = $this->trzPdo->prepare("
                SELECT id, unidad, created_at, anilox_id, ink_item_id, loaded_kg, leftover_kg, is_mixed
                FROM flexo_anilox_ink_configs WHERE ag_id = :ag_id
            ");
            $stmtConfigIds->execute([':ag_id' => $agId]);
            $previousRows = $stmtConfigIds->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $existingConfigIds = array_map(static fn(array $r): int => (int)$r['id'], $previousRows);
            $previousByUnit = [];
            foreach ($previousRows as $prevRow) {
                $previousByUnit[(int)$prevRow['unidad']] = $prevRow;
            }
            $savedUnits = [];
            if ($existingConfigIds !== []) {
                $placeholders = [];
                $params = [];
                foreach ($existingConfigIds as $idx => $configId) {
                    $placeholder = ':cfg_' . $idx;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $configId;
                }
                $this->trzPdo->prepare("
                    DELETE FROM flexo_anilox_ink_mix_components
                    WHERE config_id IN (" . implode(', ', $placeholders) . ")
                ")->execute($params);
            }

            $this->trzPdo->prepare("
                DELETE FROM flexo_anilox_ink_configs WHERE ag_id = :ag_id
            ")->execute([':ag_id' => $agId]);

            $stmtInsertConfig = $this->trzPdo->prepare("
                INSERT INTO flexo_anilox_ink_configs
                    (ag_id, req_id, worker_ot_id, unidad, posicion, color_label, anilox_id, ink_item_id, ink_code, ink_name, loaded_kg, leftover_kg, consumed_kg, is_mixed, mixture_notes, created_at, updated_at)
                VALUES
                    (:ag_id, :req_id, :worker_ot_id, :unidad, :posicion, :color_label, :anilox_id, :ink_item_id, :ink_code, :ink_name, :loaded_kg, :leftover_kg, :consumed_kg, :is_mixed, :mixture_notes, :created_at, :updated_at)
            ");
            $stmtInsertComponent = $this->trzPdo->prepare("
                INSERT INTO flexo_anilox_ink_mix_components
                    (config_id, position_no, item_id, item_code, item_name, qty_kg, created_at, updated_at)
                VALUES
                    (:config_id, :position_no, :item_id, :item_code, :item_name, :qty_kg, :created_at, :updated_at)
            ");

            foreach ($rows as $row) {
                $unidad = (int)($row['unidad'] ?? 0);
                if ($unidad <= 0) {
                    continue;
                }

                $loadedKg = max(0.0, round((float)($row['loaded_kg'] ?? 0), 3));
                $leftoverKg = max(0.0, round((float)($row['leftover_kg'] ?? 0), 3));
                $consumedKg = max(0.0, round($loadedKg - $leftoverKg, 3));
                $isMixed = !empty($row['is_mixed']) ? 1 : 0;
                $mixtureNotes = trim((string)($row['mixture_notes'] ?? ''));
                $inkItemId = (int)($row['ink_item_id'] ?? 0);
                $colorLabel = trim((string)($row['color_label'] ?? ''));
                $components = [];

                foreach (($row['components'] ?? []) as $componentIndex => $component) {
                    $componentItemId = (int)($component['item_id'] ?? 0);
                    $componentQtyKg = max(0.0, round((float)($component['qty_kg'] ?? 0), 3));
                    if ($componentItemId <= 0 && $componentQtyKg <= 0) {
                        continue;
                    }
                    $componentItemMeta = $itemsById[$componentItemId] ?? ['code' => '', 'name' => ''];
                    $components[] = [
                        'position_no' => $componentIndex + 1,
                        'item_id' => $componentItemId > 0 ? $componentItemId : null,
                        'item_code' => (string)$componentItemMeta['code'],
                        'item_name' => (string)$componentItemMeta['name'],
                        'qty_kg' => $componentQtyKg,
                    ];
                }

                $hasMeaningfulData = (
                    $inkItemId > 0
                    || $loadedKg > 0
                    || $leftoverKg > 0
                    || $isMixed === 1
                    || $mixtureNotes !== ''
                    || $components !== []
                );
                if (!$hasMeaningfulData) {
                    continue;
                }

                $baseItemMeta = $itemsById[$inkItemId] ?? ['code' => '', 'name' => ''];
                $stmtInsertConfig->execute([
                    ':ag_id' => $agId,
                    ':req_id' => $reqId > 0 ? $reqId : null,
                    ':worker_ot_id' => $workerOtId > 0 ? $workerOtId : null,
                    ':unidad' => $unidad,
                    ':posicion' => (int)($row['posicion'] ?? $unidad),
                    ':color_label' => $colorLabel !== '' ? $colorLabel : null,
                    ':anilox_id' => (int)($row['anilox_id'] ?? 0) > 0 ? (int)$row['anilox_id'] : null,
                    ':ink_item_id' => $inkItemId > 0 ? $inkItemId : null,
                    ':ink_code' => $baseItemMeta['code'] !== '' ? $baseItemMeta['code'] : null,
                    ':ink_name' => $baseItemMeta['name'] !== '' ? $baseItemMeta['name'] : null,
                    ':loaded_kg' => $loadedKg,
                    ':leftover_kg' => $leftoverKg,
                    ':consumed_kg' => $consumedKg,
                    ':is_mixed' => $isMixed,
                    ':mixture_notes' => $mixtureNotes !== '' ? $mixtureNotes : null,
                    ':created_at' => (int)($previousByUnit[$unidad]['created_at'] ?? $now), // conserva cuándo se creó
                    ':updated_at' => $now,
                ]);
                $configId = (int)$this->trzPdo->lastInsertId();
                $savedUnits[$unidad] = true;

                // Historial: solo si algo cambió respecto de lo guardado antes
                $prev = $previousByUnit[$unidad] ?? null;
                $changed = $prev === null
                    || (int)($prev['anilox_id'] ?? 0) !== (int)($row['anilox_id'] ?? 0)
                    || (int)($prev['ink_item_id'] ?? 0) !== $inkItemId
                    || abs((float)($prev['loaded_kg'] ?? 0) - $loadedKg) > 0.0004
                    || abs((float)($prev['leftover_kg'] ?? 0) - $leftoverKg) > 0.0004
                    || (int)($prev['is_mixed'] ?? 0) !== $isMixed;
                if ($changed) {
                    $this->logFlexoAniloxChange($agId, $unidad, 'SAVE', (int)($row['anilox_id'] ?? 0), $prev !== null ? (int)($prev['anilox_id'] ?? 0) : null,
                        $colorLabel, $inkItemId, $loadedKg, $leftoverKg,
                        ($isMixed === 1 ? 'Mezcla. ' : '') . $mixtureNotes, $userId, $workerOtId);
                }

                foreach ($components as $component) {
                    $stmtInsertComponent->execute([
                        ':config_id' => $configId,
                        ':position_no' => (int)$component['position_no'],
                        ':item_id' => $component['item_id'],
                        ':item_code' => $component['item_code'] !== '' ? $component['item_code'] : null,
                        ':item_name' => $component['item_name'] !== '' ? $component['item_name'] : null,
                        ':qty_kg' => (float)$component['qty_kg'],
                        ':created_at' => $now,
                        ':updated_at' => $now,
                    ]);
                }
            }

            // Unidades que tenían datos y ahora se vaciaron: queda registrado qué había
            foreach ($previousByUnit as $prevUnit => $prevRow) {
                if (!isset($savedUnits[$prevUnit])) {
                    $this->logFlexoAniloxChange($agId, (int)$prevUnit, 'CLEAR', null, (int)($prevRow['anilox_id'] ?? 0), '',
                        (int)($prevRow['ink_item_id'] ?? 0), (float)($prevRow['loaded_kg'] ?? 0), (float)($prevRow['leftover_kg'] ?? 0),
                        'Tintas vaciadas', $userId, $workerOtId);
                }
            }

            $this->trzPdo->prepare("
                INSERT INTO events (type, payload) VALUES (:type, :payload)
            ")->execute([
                ':type' => 'FLEXO_ANILOX_INK_CONFIG_SAVED',
                ':payload' => json_encode([
                    'ag_id' => $agId,
                    'req_id' => $reqId,
                    'worker_ot_id' => $workerOtId,
                    'rows_count' => count($rows),
                    'saved_at' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $this->trzPdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            if ($this->trzPdo->inTransaction()) {
                $this->trzPdo->rollBack();
            }
            return ['ok' => false, 'error' => 'Error al guardar tintas por anilox: ' . $e->getMessage()];
        }
    }

    public function ensureMachineInkLoadsSchema(): void
    {
        if ($this->trzPdo === null) {
            return;
        }

        try {
            $this->trzPdo->exec("
                CREATE TABLE IF NOT EXISTS work_order_machine_ink_loads (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    worker_ot_id BIGINT UNSIGNED NOT NULL,
                    ag_id BIGINT UNSIGNED NULL,
                    warehouse_code INT NOT NULL DEFAULT 16,
                    item_id BIGINT UNSIGNED NOT NULL,
                    item_code VARCHAR(80) NULL,
                    item_name VARCHAR(255) NULL,
                    unit_name VARCHAR(40) NULL,
                    initial_weight_kg DECIMAL(10,3) NOT NULL DEFAULT 0.000,
                    loaded_qty DECIMAL(10,3) NOT NULL DEFAULT 0.000,
                    leftover_qty DECIMAL(10,3) NOT NULL DEFAULT 0.000,
                    consumed_qty DECIMAL(10,3) NOT NULL DEFAULT 0.000,
                    returned_qty DECIMAL(10,3) NOT NULL DEFAULT 0.000,
                    color_label VARCHAR(100) NULL,
                    machine_process VARCHAR(50) NULL DEFAULT 'SERIGRAFIA',
                    operator_name VARCHAR(150) NULL,
                    comments VARCHAR(255) NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
                    created_at BIGINT NOT NULL,
                    updated_at BIGINT NOT NULL,
                    closed_at BIGINT NULL,
                    PRIMARY KEY (id),
                    KEY idx_wo_machine_ink_worker_ot (worker_ot_id),
                    KEY idx_wo_machine_ink_ag (ag_id),
                    KEY idx_wo_machine_ink_item (item_id),
                    KEY idx_wo_machine_ink_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Asegurar columnas nuevas si la tabla ya existía previamente
            $cols = [
                "initial_weight_kg" => "DECIMAL(10,3) NOT NULL DEFAULT 0.000",
                "leftover_qty" => "DECIMAL(10,3) NOT NULL DEFAULT 0.000",
                "color_label" => "VARCHAR(100) NULL",
                "machine_process" => "VARCHAR(50) NULL DEFAULT 'SERIGRAFIA'",
            ];
            foreach ($cols as $col => $type) {
                try {
                    $this->trzPdo->exec("ALTER TABLE work_order_machine_ink_loads ADD COLUMN {$col} {$type}");
                } catch (Throwable) {}
            }
        } catch (Throwable) {}
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMachineInkLoadsForWorkOrder(int $workerOtId, bool $onlyOpen = false, int $agId = 0): array
    {
        if ($workerOtId <= 0 && $agId <= 0) {
            return [];
        }
        if ($this->trzPdo === null) {
            return [];
        }

        $this->ensureMachineInkLoadsSchema();

        try {
            $whereParts = [];
            $params = [];
            if ($workerOtId > 0 && $agId > 0) {
                $whereParts[] = "(worker_ot_id = :worker_ot_id OR ag_id = :ag_id)";
                $params[':worker_ot_id'] = $workerOtId;
                $params[':ag_id'] = $agId;
            } elseif ($workerOtId > 0) {
                $whereParts[] = "worker_ot_id = :worker_ot_id";
                $params[':worker_ot_id'] = $workerOtId;
            } else {
                $whereParts[] = "ag_id = :ag_id";
                $params[':ag_id'] = $agId;
            }

            if ($onlyOpen) {
                $whereParts[] = "(loaded_qty - consumed_qty - returned_qty) > 0.0001";
            }

            $sql = "
                SELECT *,
                       (loaded_qty - consumed_qty - returned_qty) AS available_qty
                FROM work_order_machine_ink_loads
                WHERE " . implode(' AND ', $whereParts) . "
                ORDER BY status = 'ACTIVE' DESC, id DESC
            ";

            $stmt = $this->trzPdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$row) {
                $row['loaded_qty'] = (float)($row['loaded_qty'] ?? 0);
                $row['consumed_qty'] = (float)($row['consumed_qty'] ?? 0);
                $row['returned_qty'] = (float)($row['returned_qty'] ?? 0);
                $row['initial_weight_kg'] = (float)($row['initial_weight_kg'] ?? 0);
                $row['leftover_qty'] = (float)($row['leftover_qty'] ?? 0);
                $row['available_qty'] = max(0.0, (float)($row['available_qty'] ?? 0));
            }
            unset($row);
            return $rows;
        } catch (Throwable) {
            return [];
        }
    }

    public function loadInkToMachine(
        int $workerOtId,
        int $agId,
        int $itemId,
        float $qty,
        string $operatorName,
        string $comments = '',
        int $warehouseCode = 16
    ): array {
        if ($workerOtId <= 0 || $itemId <= 0 || $qty <= 0) {
            return ['ok' => false, 'error' => 'Datos inválidos para cargar tinta a máquina.'];
        }
        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible para controlar tintas.'];
        }

        $this->ensureMachineInkLoadsSchema();
        $now = time();

        try {
            $stmtItem = $this->erpPdo->prepare("
                SELECT i.id, i.item_number_prod, i.item_title
                FROM item i
                WHERE i.id = :id
                LIMIT 1
            ");
            $stmtItem->execute([':id' => $itemId]);
            $item = $stmtItem->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                return ['ok' => false, 'error' => 'No se encontró la tinta seleccionada.'];
            }

            $itemCode = trim((string)($item['item_number_prod'] ?? ''));
            $itemName = trim((string)($item['item_title'] ?? ''));
            if ($itemCode === '' || stripos($itemCode, 'TIN') !== 0) {
                return ['ok' => false, 'error' => 'Solo se pueden cargar tintas con código iniciado en TIN desde 3000 Insumos de Producción.'];
            }

            $stmt = $this->trzPdo->prepare("
                INSERT INTO work_order_machine_ink_loads
                    (worker_ot_id, ag_id, warehouse_code, item_id, item_code, item_name, unit_name, loaded_qty, consumed_qty, returned_qty, operator_name, comments, status, created_at, updated_at)
                VALUES
                    (:worker_ot_id, :ag_id, :warehouse_code, :item_id, :item_code, :item_name, :unit_name, :loaded_qty, 0, 0, :operator_name, :comments, 'ACTIVE', :created_at, :updated_at)
            ");
            $stmt->execute([
                ':worker_ot_id' => $workerOtId,
                ':ag_id' => $agId > 0 ? $agId : null,
                ':warehouse_code' => $warehouseCode,
                ':item_id' => $itemId,
                ':item_code' => $itemCode !== '' ? $itemCode : null,
                ':item_name' => $itemName !== '' ? $itemName : null,
                ':unit_name' => 'KG',
                ':loaded_qty' => round($qty, 3),
                ':operator_name' => $operatorName !== '' ? $operatorName : null,
                ':comments' => $comments !== '' ? $comments : null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $loadId = (int)$this->trzPdo->lastInsertId();

            $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)")->execute([
                ':type' => 'MACHINE_INK_LOAD_CREATED',
                ':payload' => json_encode([
                    'load_id' => $loadId,
                    'worker_ot_id' => $workerOtId,
                    'ag_id' => $agId,
                    'warehouse_code' => $warehouseCode,
                    'item_id' => $itemId,
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'loaded_qty' => round($qty, 3),
                    'operator' => $operatorName,
                    'comments' => $comments,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $stmtErp = $this->erpPdo->prepare("
                INSERT INTO prod_worker_ot_events
                    (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                VALUES
                    (:ot_id, :amt, :crtdat, :enddat, 2, 'materiales', :comments)
            ");
            $stmtErp->execute([
                ':ot_id' => $workerOtId,
                ':amt' => round($qty, 3),
                ':crtdat' => $now,
                ':enddat' => $now,
                ':comments' => 'Carga Tinta: '
                    . ($itemCode !== '' ? $itemCode . ' - ' : '')
                    . $this->simplifyInkTitle($itemName)
                    . ' | ' . number_format($qty, 2, ',', '.') . ' kg'
                    . ($comments !== '' ? ' | Obs: ' . $comments : ''),
            ]);

            return ['ok' => true, 'load_id' => $loadId];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al cargar tinta a máquina: ' . $e->getMessage()];
        }
    }

    public function consumeMachineInkLoad(
        int $loadId,
        int $workerOtId,
        float $qty,
        string $operatorName,
        string $comments = ''
    ): array {
        return $this->adjustMachineInkLoad($loadId, $workerOtId, $qty, 'consume', $operatorName, $comments);
    }

    public function returnMachineInkLoad(
        int $loadId,
        int $workerOtId,
        float $qty,
        string $operatorName,
        string $comments = ''
    ): array {
        return $this->adjustMachineInkLoad($loadId, $workerOtId, $qty, 'return', $operatorName, $comments);
    }

    private function adjustMachineInkLoad(
        int $loadId,
        int $workerOtId,
        float $qty,
        string $action,
        string $operatorName,
        string $comments = ''
    ): array {
        if ($loadId <= 0 || $workerOtId <= 0 || $qty <= 0) {
            return ['ok' => false, 'error' => 'Datos inválidos para actualizar la tinta en máquina.'];
        }
        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible para controlar tintas.'];
        }

        $this->ensureMachineInkLoadsSchema();
        $now = time();

        try {
            $stmt = $this->trzPdo->prepare("
                SELECT *
                FROM work_order_machine_ink_loads
                WHERE id = :id AND worker_ot_id = :worker_ot_id
                LIMIT 1
            ");
            $stmt->execute([
                ':id' => $loadId,
                ':worker_ot_id' => $workerOtId,
            ]);
            $load = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$load) {
                return ['ok' => false, 'error' => 'No se encontró la carga de tinta indicada.'];
            }

            $loadedQty = (float)($load['loaded_qty'] ?? 0);
            $consumedQty = (float)($load['consumed_qty'] ?? 0);
            $returnedQty = (float)($load['returned_qty'] ?? 0);
            $availableQty = max(0.0, $loadedQty - $consumedQty - $returnedQty);
            if ($qty > $availableQty + 0.0001) {
                return ['ok' => false, 'error' => 'La cantidad excede lo disponible actualmente en máquina.'];
            }

            $newConsumedQty = $consumedQty;
            $newReturnedQty = $returnedQty;
            if ($action === 'consume') {
                $newConsumedQty += $qty;
            } else {
                $newReturnedQty += $qty;
            }
            $remainingQty = max(0.0, $loadedQty - $newConsumedQty - $newReturnedQty);
            $newStatus = $remainingQty > 0.0001 ? 'ACTIVE' : 'CLOSED';

            $stmtUpd = $this->trzPdo->prepare("
                UPDATE work_order_machine_ink_loads
                SET consumed_qty = :consumed_qty,
                    returned_qty = :returned_qty,
                    status = :status,
                    updated_at = :updated_at,
                    closed_at = :closed_at
                WHERE id = :id
            ");
            $stmtUpd->execute([
                ':consumed_qty' => round($newConsumedQty, 3),
                ':returned_qty' => round($newReturnedQty, 3),
                ':status' => $newStatus,
                ':updated_at' => $now,
                ':closed_at' => $newStatus === 'CLOSED' ? $now : null,
                ':id' => $loadId,
            ]);

            $eventType = $action === 'consume' ? 'MACHINE_INK_LOAD_CONSUMED' : 'MACHINE_INK_LOAD_RETURNED';
            $warehouseLabel = ((int)($load['warehouse_code'] ?? 0) === 16) ? '3000 Insumos de Producción' : ('Bodega ' . (string)($load['warehouse_code'] ?? ''));
            $label = $action === 'consume' ? 'Consumo tinta en máquina' : 'Devolución tinta a ' . $warehouseLabel;

            $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)")->execute([
                ':type' => $eventType,
                ':payload' => json_encode([
                    'load_id' => $loadId,
                    'worker_ot_id' => $workerOtId,
                    'item_id' => (int)($load['item_id'] ?? 0),
                    'item_code' => (string)($load['item_code'] ?? ''),
                    'item_name' => (string)($load['item_name'] ?? ''),
                    'qty' => round($qty, 3),
                    'remaining_qty' => round($remainingQty, 3),
                    'operator' => $operatorName,
                    'comments' => $comments,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $stmtErp = $this->erpPdo->prepare("
                INSERT INTO prod_worker_ot_events
                    (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                VALUES
                    (:ot_id, :amt, :crtdat, :enddat, 2, 'materiales', :comments)
            ");
            $stmtErp->execute([
                ':ot_id' => $workerOtId,
                ':amt' => round($qty, 3),
                ':crtdat' => $now,
                ':enddat' => $now,
                ':comments' => ($action === 'consume' ? 'Consumo Tinta: ' : 'Devolución Tinta: ')
                    . ($load['item_code'] !== '' ? $load['item_code'] . ' - ' : '')
                    . $this->simplifyInkTitle((string)($load['item_name'] ?? ''))
                    . ' | ' . number_format($qty, 2, ',', '.') . ' kg'
                    . ($action !== 'consume' ? ' a Bodega 3000' : '')
                    . ($comments !== '' ? ' | Obs: ' . $comments : ''),
            ]);

            return ['ok' => true, 'remaining_qty' => $remainingQty];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al actualizar la carga de tinta: ' . $e->getMessage()];
        }
    }

    /**
     * Limpia nombres comerciales de tintas para hacerlos más legibles para los operadores.
     */
    public function simplifyInkTitle(string $title): string
    {
        $t = trim($title);
        $t = preg_replace('/^(solvent|water)\s+based\s+ink\s+/i', '', $t);
        $t = preg_replace('/^tinta\s+(serigraf[ií]a|flexo(gr[aá]fica)?|al\s+agua|al\s+solvente)\s+/i', '', $t);
        $t = preg_replace('/\s+balde\s+\d+(\.\d+)?\s*(lts?|litros|kg)?/i', '', $t);
        $t = preg_replace('/\s+\d+(\.\d+)?\s*(kg|kilos|lt|litros)\b/i', '', $t);
        return trim($t);
    }

    /**
     * Registra el consumo completo de tinta para Serigrafía (Pulpo o Plana) desde Bodega 3000.
     * Permite registrar:
     * - Tinta seleccionada desde Bodega 3000 (Insumos en producción, st_id = 16)
     * - Color asignado / pasada
     * - Cuánto pesa al inicio (initial_weight_kg)
     * - Cuánto se cargó (loaded_qty)
     * - Cuánto sobró (leftover_qty)
     * - Consumo neto (consumed_qty)
     * - Descuenta el inventario real en item_shops_storehouses (st_id = 16)
     * - Registra el evento en prod_worker_ot_events (tipo materiales) y en work_order_machine_ink_loads
     */
    public function recordSerigrafiaInkConsumption(
        int $workerOtId,
        int $agId,
        int $itemId,
        float $initialWeightKg,
        float $loadedKg,
        float $leftoverKg,
        float $consumedKg,
        string $colorLabel,
        string $operatorName,
        string $comments = '',
        int $warehouseCode = 16
    ): array {
        if ($workerOtId <= 0 && $agId <= 0) {
            return ['ok' => false, 'error' => 'No se pudo identificar la orden de trabajo para registrar la tinta.'];
        }
        if ($itemId <= 0) {
            return ['ok' => false, 'error' => 'Debe seleccionar una tinta válida de Bodega 3000.'];
        }
        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible.'];
        }

        $this->ensureMachineInkLoadsSchema();
        $now = time();

        $initialWeightKg = max(0.0, round($initialWeightKg, 3));
        $loadedKg = max(0.0, round($loadedKg, 3));
        $leftoverKg = max(0.0, round($leftoverKg, 3));
        $consumedKg = round($consumedKg, 3);

        if ($initialWeightKg > 0.0) {
            $consumedKg = max(0.0, round($initialWeightKg - $leftoverKg, 3));
        } elseif ($loadedKg > 0.0) {
            $consumedKg = max(0.0, round($loadedKg - $leftoverKg, 3));
        }

        if ($loadedKg <= 0.0) {
            $loadedKg = $consumedKg;
        }

        if ($consumedKg <= 0.0 && $initialWeightKg <= 0.0 && $loadedKg <= 0.0) {
            return ['ok' => false, 'error' => 'Debe ingresar el peso al inicio del tarro de tinta.'];
        }

        try {
            // Obtener datos del ítem en ERP
            $stmtItem = $this->erpPdo->prepare("
                SELECT i.id, i.item_number_prod, i.item_title
                FROM item i
                WHERE i.id = :id
                LIMIT 1
            ");
            $stmtItem->execute([':id' => $itemId]);
            $item = $stmtItem->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                return ['ok' => false, 'error' => 'No se encontró la tinta seleccionada en el catálogo.'];
            }

            $itemCode = trim((string)($item['item_number_prod'] ?? ''));
            $itemName = trim((string)($item['item_title'] ?? ''));
            $colorLabel = trim($colorLabel);

            // 1. Insertar en work_order_machine_ink_loads
            $stmt = $this->trzPdo->prepare("
                INSERT INTO work_order_machine_ink_loads
                    (worker_ot_id, ag_id, warehouse_code, item_id, item_code, item_name, unit_name,
                     initial_weight_kg, loaded_qty, leftover_qty, consumed_qty, returned_qty,
                     color_label, machine_process, operator_name, comments, status, created_at, updated_at, closed_at)
                VALUES
                    (:worker_ot_id, :ag_id, :warehouse_code, :item_id, :item_code, :item_name, 'KG',
                     :initial_weight_kg, :loaded_qty, :leftover_qty, :consumed_qty, :returned_qty,
                     :color_label, 'SERIGRAFIA', :operator_name, :comments, 'CLOSED', :created_at, :updated_at, :closed_at)
            ");
            $stmt->execute([
                ':worker_ot_id' => $workerOtId,
                ':ag_id' => $agId > 0 ? $agId : null,
                ':warehouse_code' => $warehouseCode,
                ':item_id' => $itemId,
                ':item_code' => $itemCode !== '' ? $itemCode : null,
                ':item_name' => $itemName !== '' ? $itemName : null,
                ':initial_weight_kg' => $initialWeightKg,
                ':loaded_qty' => $loadedKg > 0 ? $loadedKg : $consumedKg,
                ':leftover_qty' => $leftoverKg,
                ':consumed_qty' => $consumedKg,
                ':returned_qty' => $leftoverKg,
                ':color_label' => $colorLabel !== '' ? $colorLabel : null,
                ':operator_name' => $operatorName !== '' ? $operatorName : null,
                ':comments' => $comments !== '' ? $comments : null,
                ':created_at' => $now,
                ':updated_at' => $now,
                ':closed_at' => $now,
            ]);
            $loadId = (int)$this->trzPdo->lastInsertId();

            // 2. Descontar inventario en Bodega 3000 (item_shops_storehouses st_id = 16)
            if ($consumedKg > 0.0) {
                try {
                    $updStock = $this->erpPdo->prepare("
                        UPDATE item_shops_storehouses
                        SET iss_inventory = GREATEST(0, iss_inventory - :amt)
                        WHERE item_id = :item_id AND st_id = 16
                    ");
                    $updStock->execute([
                        ':amt' => $consumedKg,
                        ':item_id' => $itemId,
                    ]);
                } catch (Throwable) {}
            }

            // 3. Registrar evento en bitácora de la OT en el ERP (prod_worker_ot_events)
            if ($workerOtId > 0) {
                $cleanName = $this->simplifyInkTitle($itemName);
                $header = "Tinta: " . ($itemCode !== '' ? $itemCode . ' - ' : '') . ($cleanName !== '' ? $cleanName : $itemName);
                if ($colorLabel !== '') {
                    $header .= " ({$colorLabel})";
                }

                $detailParts = [];
                $detailParts[] = "Consumo: " . number_format($consumedKg, 2, ',', '.') . " kg";
                if ($initialWeightKg > 0) {
                    $detailParts[] = "Tarro inicio " . number_format($initialWeightKg, 2, ',', '.') . " kg, Sobró " . number_format($leftoverKg, 2, ',', '.') . " kg";
                } elseif ($loadedKg > 0 && $leftoverKg > 0) {
                    $detailParts[] = "Cargó " . number_format($loadedKg, 2, ',', '.') . " kg, Sobró " . number_format($leftoverKg, 2, ',', '.') . " kg";
                } elseif ($loadedKg > 0) {
                    $detailParts[] = "Cargó " . number_format($loadedKg, 2, ',', '.') . " kg";
                }
                if ($comments !== '') {
                    $detailParts[] = "Obs: {$comments}";
                }

                $eventComment = $header . ' | ' . implode(' | ', $detailParts);

                $stmtErp = $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events
                        (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                    VALUES
                        (:ot_id, :amt, :crtdat, :enddat, 2, 'materiales', :comments)
                ");
                $stmtErp->execute([
                    ':ot_id' => $workerOtId,
                    ':amt' => $consumedKg,
                    ':crtdat' => $now,
                    ':enddat' => $now,
                    ':comments' => $eventComment,
                ]);
            }

            // 4. Registrar auditoría en TRZ events
            $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)")->execute([
                ':type' => 'SERIGRAFIA_INK_CONSUMED',
                ':payload' => json_encode([
                    'load_id' => $loadId,
                    'worker_ot_id' => $workerOtId,
                    'ag_id' => $agId,
                    'item_id' => $itemId,
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'color_label' => $colorLabel,
                    'initial_weight_kg' => $initialWeightKg,
                    'loaded_qty' => $loadedKg,
                    'leftover_qty' => $leftoverKg,
                    'consumed_qty' => $consumedKg,
                    'operator' => $operatorName,
                    'comments' => $comments,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            return [
                'ok' => true,
                'load_id' => $loadId,
                'consumed_kg' => $consumedKg,
                'item_code' => $itemCode,
                'item_name' => $itemName,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al registrar consumo de tinta de serigrafía: ' . $e->getMessage()];
        }
    }

    /**
     * Elimina / revierte un registro de tinta en máquina y reintegra el stock a Bodega 3000 si aplica.
     */
    public function deleteMachineInkLoad(int $loadId, int $workerOtId, string $operatorName = ''): array
    {
        if ($loadId <= 0 || $this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Identificador de carga de tinta inválido.'];
        }

        try {
            $stmt = $this->trzPdo->prepare("
                SELECT * FROM work_order_machine_ink_loads
                WHERE id = :id " . ($workerOtId > 0 ? "AND worker_ot_id = :wot" : "") . "
                LIMIT 1
            ");
            $params = [':id' => $loadId];
            if ($workerOtId > 0) {
                $params[':wot'] = $workerOtId;
            }
            $stmt->execute($params);
            $load = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$load) {
                return ['ok' => false, 'error' => 'No se encontró el registro de tinta a eliminar.'];
            }

            $itemId = (int)($load['item_id'] ?? 0);
            $consumedQty = (float)($load['consumed_qty'] ?? 0);
            $whCode = (int)($load['warehouse_code'] ?? 16);

            // Reintegrar stock a Bodega 3000 si fue consumido
            if ($itemId > 0 && $consumedQty > 0.0 && $whCode === 16) {
                try {
                    $this->erpPdo->prepare("
                        UPDATE item_shops_storehouses
                        SET iss_inventory = iss_inventory + :amt
                        WHERE item_id = :item_id AND st_id = 16
                    ")->execute([
                        ':amt' => $consumedQty,
                        ':item_id' => $itemId,
                    ]);

                    // Registrar evento de anulación claro para los operadores en la OT
                    if ($workerOtId > 0) {
                        $delCode = trim((string)($load['item_code'] ?? ''));
                        $delName = $this->simplifyInkTitle((string)($load['item_name'] ?? ''));
                        $delComment = "Anulación Tinta: " . ($delCode !== '' ? $delCode . ' - ' : '') . ($delName !== '' ? $delName : 'Tinta')
                            . " | Reintegrados " . number_format($consumedQty, 2, ',', '.') . " kg a Bodega 3000"
                            . ($operatorName !== '' ? " (por {$operatorName})" : "");

                        $this->erpPdo->prepare("
                            INSERT INTO prod_worker_ot_events
                                (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                            VALUES
                                (:ot_id, 0, :crtdat, :enddat, 2, 'materiales', :comments)
                        ")->execute([
                            ':ot_id' => $workerOtId,
                            ':crtdat' => time(),
                            ':enddat' => time(),
                            ':comments' => $delComment,
                        ]);
                    }
                } catch (Throwable) {}
            }

            // Eliminar registro
            $this->trzPdo->prepare("DELETE FROM work_order_machine_ink_loads WHERE id = :id")->execute([':id' => $loadId]);

            // Auditoría
            $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)")->execute([
                ':type' => 'MACHINE_INK_LOAD_DELETED',
                ':payload' => json_encode([
                    'load_id' => $loadId,
                    'item_id' => $itemId,
                    'consumed_reintegrated' => $consumedQty,
                    'operator' => $operatorName,
                    'timestamp' => time(),
                ], JSON_UNESCAPED_UNICODE),
            ]);

            return ['ok' => true, 'reintegrated_kg' => $consumedQty];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al revertir registro de tinta: ' . $e->getMessage()];
        }
    }



    /**
     * Guarda las asignaciones de anilox por unidad flexográfica (editxid.php 108-158).
     * Valida que no se seleccione el mismo anilox más de una vez en la misma CC.
     */
    public function saveAniloxAssignments(int $agId, int $reqId, array $aniloxMap, int $userId = 0): array
    {
        try {
            $stkId = 0;
            if ($reqId > 0) {
                $stmt = $this->erpPdo->prepare(
                    "SELECT id FROM prod_anilox_ot
                     WHERE prod_anilox_agid = :ag_id AND prod_anilox_reqid = :req_id
                     LIMIT 1"
                );
                $stmt->execute([':ag_id' => $agId, ':req_id' => $reqId]);
                $stkId = (int)$stmt->fetchColumn();
            }
            if ($stkId <= 0) {
                $stmt = $this->erpPdo->prepare(
                    "SELECT id FROM prod_anilox_ot
                     WHERE prod_anilox_agid = :ag_id
                     LIMIT 1"
                );
                $stmt->execute([':ag_id' => $agId]);
                $stkId = (int)$stmt->fetchColumn();
            }
            if ($stkId <= 0) {
                return ['ok' => false, 'error' => 'No se encontró el registro de anilox para la OT.'];
            }

            // Validar no duplicados entre unidades
            $used = [];
            foreach ($aniloxMap as $unidad => $aniloxId) {
                $aid = (int)$aniloxId;
                if ($aid > 0) {
                    if (in_array($aid, $used, true)) {
                        return ['ok' => false, 'error' => 'No se puede seleccionar mas de un Anilox en la misma CC'];
                    }
                    $used[] = $aid;
                }
            }

            // Estado anterior, para dejar historial de lo que cambió
            $previous = [];
            try {
                $stmtPrev = $this->erpPdo->prepare(
                    "SELECT paow_unidad, paow_anilox, paow_color FROM prod_anilox_ot_worker WHERE paow_ot_worker_id = :stk_id"
                );
                $stmtPrev->execute([':stk_id' => $stkId]);
                foreach ($stmtPrev->fetchAll(PDO::FETCH_ASSOC) ?: [] as $pr) {
                    $previous[(int)$pr['paow_unidad']] = $pr;
                }
            } catch (Throwable) {
                $previous = [];
            }

            $stmtUpd = $this->erpPdo->prepare(
                "UPDATE prod_anilox_ot_worker
                 SET paow_anilox = :aid
                 WHERE paow_ot_worker_id = :stk_id AND paow_unidad = :unidad"
            );
            foreach ($aniloxMap as $unidad => $aniloxId) {
                $stmtUpd->execute([
                    ':aid' => (int)$aniloxId,
                    ':stk_id' => $stkId,
                    ':unidad' => (int)$unidad,
                ]);
                $oldAnilox = (int)($previous[(int)$unidad]['paow_anilox'] ?? 0);
                if ($oldAnilox !== (int)$aniloxId) {
                    $this->ensureFlexoAniloxInkSchema();
                    $this->logFlexoAniloxChange($agId, (int)$unidad, 'ANILOX', (int)$aniloxId, $oldAnilox,
                        (string)($previous[(int)$unidad]['paow_color'] ?? ''), null, null, null,
                        $oldAnilox > 0 ? 'Cambio de anilox' : 'Anilox asignado', $userId);
                }
            }

            $now = time();
            $stmtPao = $this->erpPdo->prepare(
                "UPDATE prod_anilox_ot
                 SET prod_anilox_fecha_actualiza = :udate, prod_anilox_user_actualiza = :uuser
                 WHERE id = :id"
            );
            $stmtPao->execute([':udate' => $now, ':uuser' => $userId, ':id' => $stkId]);

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al guardar Anilox: ' . $e->getMessage()];
        }
    }

    /**
     * Mueve una posición de anilox hacia arriba (direction = 1) o abajo (direction = 2).
     * Replica exactamente editxid.php líneas 160-265.
     */
    public function moveAniloxUnit(int $agId, int $reqId, int $unidad, int $direction): array
    {
        try {
            $stkId = 0;
            if ($reqId > 0) {
                $stmt = $this->erpPdo->prepare(
                    "SELECT id FROM prod_anilox_ot
                     WHERE prod_anilox_agid = :ag_id AND prod_anilox_reqid = :req_id
                     LIMIT 1"
                );
                $stmt->execute([':ag_id' => $agId, ':req_id' => $reqId]);
                $stkId = (int)$stmt->fetchColumn();
            }
            if ($stkId <= 0) {
                $stmt = $this->erpPdo->prepare(
                    "SELECT id FROM prod_anilox_ot
                     WHERE prod_anilox_agid = :ag_id
                     LIMIT 1"
                );
                $stmt->execute([':ag_id' => $agId]);
                $stkId = (int)$stmt->fetchColumn();
            }
            if ($stkId <= 0) {
                return ['ok' => false, 'error' => 'Registro de anilox no encontrado.'];
            }

            $stmtWorkers = $this->erpPdo->prepare(
                "SELECT * FROM prod_anilox_ot_worker
                 WHERE paow_ot_worker_id = :stk_id
                 ORDER BY paow_unidad ASC"
            );
            $stmtWorkers->execute([':stk_id' => $stkId]);
            $rows = $stmtWorkers->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $byUnidad = [];
            foreach ($rows as $r) {
                $byUnidad[(int)$r['paow_unidad']] = $r;
            }

            if (!isset($byUnidad[$unidad])) {
                return ['ok' => false, 'error' => 'Unidad no encontrada.'];
            }

            $curr = $byUnidad[$unidad];

            if ($direction === 1) { // Mover arriba
                if ($unidad <= 1) {
                    return ['ok' => false, 'error' => 'No puede Mover, estas en la primera posición'];
                }
                $targetUnidad = $unidad - 1;
            } elseif ($direction === 2) { // Mover abajo
                if ($unidad >= count($byUnidad)) {
                    return ['ok' => false, 'error' => 'No puede Mover, estas en la última posición'];
                }
                $targetUnidad = $unidad + 1;
            } else {
                return ['ok' => false, 'error' => 'Dirección de movimiento inválida.'];
            }
            if (!isset($byUnidad[$targetUnidad])) {
                return ['ok' => false, 'error' => 'Unidad destino no existe.'];
            }
            $target = $byUnidad[$targetUnidad];

            // 1) Las tintas/cargas/mezclas locales viajan con la unidad (antes quedaban en la unidad vieja).
            $inkSwapped = $this->swapFlexoAniloxInkUnits($agId, $unidad, $targetUnidad);
            if ($inkSwapped === null) {
                return ['ok' => false, 'error' => 'No se pudo mover: error al intercambiar las tintas de las unidades. No se cambió nada.'];
            }

            // 2) Color y anilox en el ERP. Si falla, se devuelve lo local a su lugar para no perder coherencia.
            try {
                $stmtUpd = $this->erpPdo->prepare(
                    "UPDATE prod_anilox_ot_worker
                     SET paow_color = :color, paow_anilox = :anilox
                     WHERE paow_ot_worker_id = :stk_id AND paow_unidad = :unidad"
                );
                $stmtUpd->execute([
                    ':color' => $target['paow_color'],
                    ':anilox' => (int)$target['paow_anilox'],
                    ':stk_id' => $stkId,
                    ':unidad' => $unidad,
                ]);
                $stmtUpd->execute([
                    ':color' => $curr['paow_color'],
                    ':anilox' => (int)$curr['paow_anilox'],
                    ':stk_id' => $stkId,
                    ':unidad' => $targetUnidad,
                ]);
            } catch (Throwable $e) {
                if ($inkSwapped === true) {
                    $this->swapFlexoAniloxInkUnits($agId, $unidad, $targetUnidad);
                }
                throw $e;
            }

            $this->ensureFlexoAniloxInkSchema();
            $this->logFlexoAniloxChange($agId, $unidad, 'MOVE', (int)$target['paow_anilox'], (int)$curr['paow_anilox'],
                (string)$target['paow_color'], null, null, null, 'Intercambio con unidad ' . $targetUnidad);
            $this->logFlexoAniloxChange($agId, $targetUnidad, 'MOVE', (int)$curr['paow_anilox'], (int)$target['paow_anilox'],
                (string)$curr['paow_color'], null, null, null, 'Intercambio con unidad ' . $unidad);

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al mover unidad de anilox: ' . $e->getMessage()];
        }
    }



    /**
     * Retorna el resumen consolidado de las bobinas impresas semielaboradas generadas en la OT.
     * Incluye la suma total de peso (kg), la suma de metros lineales, el conteo de bobinas y el detalle.
     */
    public function getPrintedRollsSummary(int $workerOtId): array
    {
        if ($workerOtId <= 0) {
            return [
                'total_weight_kg' => 0.0,
                'total_meters' => 0.0,
                'rolls_count' => 0,
                'rolls_list' => [],
            ];
        }

        $rollsList = [];
        $totalWeight = 0.0;
        $totalMeters = 0.0;

        // 1. Consultar en TRZ (tabla rolls)
        if ($this->trzPdo !== null) {
            try {
                $stmt = $this->trzPdo->prepare("
                    SELECT id, roll_code, weight_kg, meters, parent_roll_id, created_at
                    FROM rolls
                    WHERE source_work_order_id = :ot_id
                      AND (process_stage = 'PRINTED' OR roll_code LIKE 'ROLL-SE-%')
                    ORDER BY id ASC
                ");
                $stmt->execute([':ot_id' => $workerOtId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $w = (float)($r['weight_kg'] ?? 0);
                    $m = (float)($r['meters'] ?? 0);
                    $totalWeight += $w;
                    $totalMeters += $m;
                    $rollsList[] = [
                        'id' => (int)$r['id'],
                        'roll_code' => (string)$r['roll_code'],
                        'weight_kg' => $w,
                        'meters' => $m,
                        'parent_roll_id' => $r['parent_roll_id'] !== null ? (int)$r['parent_roll_id'] : null,
                        'created_at' => (string)($r['created_at'] ?? ''),
                    ];
                }
            } catch (Throwable) {}
        }

        // 2. Si no hubo resultados en rolls, verificar en events (CHILD_ROLL_CREATED) de TRZ
        if (empty($rollsList) && $this->trzPdo !== null) {
            try {
                $stmtEvt = $this->trzPdo->prepare("
                    SELECT id, payload, created_at
                    FROM events
                    WHERE type = 'CHILD_ROLL_CREATED'
                    ORDER BY id ASC
                ");
                $stmtEvt->execute();
                while ($erow = $stmtEvt->fetch(PDO::FETCH_ASSOC)) {
                    $p = json_decode((string)($erow['payload'] ?? ''), true);
                    if (is_array($p) && (int)($p['worker_ot_id'] ?? 0) === $workerOtId) {
                        $w = (float)($p['weight_kg'] ?? 0);
                        $m = (float)($p['meters'] ?? 0);
                        $code = (string)($p['child_roll_code'] ?? ('ROLL-' . ($p['child_roll_id'] ?? $erow['id'])));
                        $totalWeight += $w;
                        $totalMeters += $m;
                        $rollsList[] = [
                            'id' => (int)($p['child_roll_id'] ?? $erow['id']),
                            'roll_code' => $code,
                            'weight_kg' => $w,
                            'meters' => $m,
                            'parent_roll_id' => !empty($p['parent_roll_id']) ? (int)$p['parent_roll_id'] : null,
                            'created_at' => (string)($erow['created_at'] ?? ''),
                        ];
                    }
                }
            } catch (Throwable) {}
        }

        // 3. Fallback en ERP: eventos que registran 'Bobina Semielaborada'
        if (empty($rollsList) && $this->erpPdo !== null) {
            try {
                $stmtErp = $this->erpPdo->prepare("
                    SELECT id, evt_amount, prod_bobina_kg, evt_amount_metros_lineales, evt_comments, evt_crtdat
                    FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id
                      AND (evt_comments LIKE '%Bobina Semielaborada%' OR evt_comments LIKE '%ROLL-SE-%')
                      AND evt_status > 0
                    ORDER BY id ASC
                ");
                $stmtErp->execute([':ot_id' => $workerOtId]);
                while ($erow = $stmtErp->fetch(PDO::FETCH_ASSOC)) {
                    $w = (float)($erow['prod_bobina_kg'] > 0 ? $erow['prod_bobina_kg'] : $erow['evt_amount']);
                    $m = (float)($erow['evt_amount_metros_lineales'] ?? 0);
                    if ($w > 0) {
                        $totalWeight += $w;
                        $totalMeters += $m;
                        $rollsList[] = [
                            'id' => (int)$erow['id'],
                            'roll_code' => 'BOB-SE-' . $erow['id'],
                            'weight_kg' => $w,
                            'meters' => $m,
                            'parent_roll_id' => null,
                            'created_at' => date('Y-m-d H:i:s', (int)$erow['evt_crtdat']),
                        ];
                    }
                }
            } catch (Throwable) {}
        }

        return [
            'total_weight_kg' => round($totalWeight, 3),
            'total_meters' => round($totalMeters, 2),
            'rolls_count' => count($rollsList),
            'rolls_list' => $rollsList,
        ];
    }

    /**
     * Determina si una máquina o agenda corresponde a Flexografía, Serigrafía o Pulpo Serigráfico.
     */
    public function isFlexoSeriPulpoMachine(?int $equipoId = null, ?array $agenda = null): bool
    {
        if ($agenda !== null) {
            $isFlexo = (int)($agenda['equipo_prod_isprinter_flexo'] ?? 0) === 1
                || (int)($agenda['equipo_type_id'] ?? 0) === 7
                || stripos((string)($agenda['type_ant_title'] ?? ''), 'flexo') !== false
                || stripos((string)($agenda['equipo_name'] ?? ''), 'flexo') !== false;
            $isSeri = (int)($agenda['equipo_prod_isprinter_seri'] ?? 0) === 1
                || in_array((int)($agenda['equipo_type_id'] ?? 0), [11, 22], true)
                || stripos((string)($agenda['type_ant_title'] ?? ''), 'seri') !== false
                || stripos((string)($agenda['equipo_name'] ?? ''), 'seri') !== false
                || stripos((string)($agenda['equipo_name'] ?? ''), 'pulpo') !== false;
            if ($isFlexo || $isSeri) {
                return true;
            }
        }

        if ($equipoId !== null && $equipoId > 0) {
            try {
                $stmt = $this->erpPdo->prepare("
                    SELECT e.id, e.equipo_name, e.equipo_type_id,
                           e.equipo_prod_isprinter_flexo, e.equipo_prod_isprinter_seri,
                           t.type_ant_title
                    FROM equipo e
                    LEFT JOIN equipo_type t ON e.equipo_type_id = t.id
                    WHERE e.id = :id
                    LIMIT 1
                ");
                $stmt->execute([':id' => $equipoId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    if ((int)$row['equipo_prod_isprinter_flexo'] === 1
                        || (int)$row['equipo_prod_isprinter_seri'] === 1
                        || in_array((int)$row['equipo_type_id'], [7, 11, 22], true)
                        || stripos((string)$row['equipo_name'], 'flexo') !== false
                        || stripos((string)$row['equipo_name'], 'seri') !== false
                        || stripos((string)$row['equipo_name'], 'pulpo') !== false
                        || stripos((string)($row['type_ant_title'] ?? ''), 'flexo') !== false
                        || stripos((string)($row['type_ant_title'] ?? ''), 'seri') !== false
                    ) {
                        return true;
                    }
                }
            } catch (Throwable) {}
        }

        return false;
    }

    /**
     * Lista todas las bobinas disponibles actualmente en Bodega 3000 (Insumos/MP en producción).
     * Incluye análisis de compatibilidad opcional con una OT si se pasa $agId.
     * Si la máquina corresponde a Flexografía, Serigrafía o Pulpo Serigráfico,
     * SOLO se incluyen códigos iniciados en TEL (telas vírgenes), excluyendo bolsas y bobinas ya impresas/procesadas.
     */
    public function listAvailableRollsInBodega3000(?int $agId = null, int $limit = 100, ?bool $unprintedOnly = null, ?int $equipoId = null, bool $compatibleOnly = false): array
    {
        $rolls = [];

        // Datos de la OT para compatibilidad si se proporciona
        $targetMat = '';
        $targetColor = '';
        $targetWidth = 0.0;
        $targetWidthMm = 0;
        $targetGrams = 0.0;
        $agenda = null;
        if ($agId !== null && $agId > 0) {
            $agenda = $this->getWorkOrderAgendaDetails($agId);
            if ($agenda !== null) {
                $targetMat = mb_strtoupper(trim((string)($agenda['fab_type'] ?? '')));
                $targetColor = mb_strtolower(trim((string)($agenda['fabric_color'] ?? $agenda['ag_color_tela'] ?? '')));
                $targetWidth = (float)($agenda['req_operador_tela_width'] ?? $agenda['fab_med_width'] ?? 0);
                $targetGrams = (float)($agenda['ot_grams'] ?? 0);
                $targetWidthMm = (int)round($targetWidth > 0 && $targetWidth < 100 ? $targetWidth * 10 : $targetWidth);
                if ($equipoId === null && !empty($agenda['ag_equipo_id'])) {
                    $equipoId = (int)$agenda['ag_equipo_id'];
                }
            }
        }

        $isFlexoSeriPulpo = $this->isFlexoSeriPulpoMachine($equipoId, $agenda);
        $isPrinter = $isFlexoSeriPulpo;
        if ($unprintedOnly === null) {
            $unprintedOnly = $isFlexoSeriPulpo;
        }
        $telOnly = $isFlexoSeriPulpo;

        // 1. Bobinas en ERP unibag_unibag en Bodega 3000 (st_id IN (16, 27) o LIKE '3000%') con stock positivo
        try {
            $whereErp = $telOnly
                ? "AND (i.item_number_prod LIKE 'TEL%' OR i.item_number_prod LIKE 'tel%')"
                : "AND (
                      i.item_number_prod LIKE 'TEL%'
                      OR i.item_number_prod LIKE 'BTE%'
                      OR i.item_number_prod LIKE 'BOB%'
                      OR i.item_title LIKE 'PLA/%'
                      OR i.item_title LIKE 'PP/%'
                      OR i.item_title LIKE 'TNT/%'
                      OR i.item_title LIKE '%BOBINA%'
                      OR i.item_title LIKE '%TELA%'
                  )";

            $sqlErp = "
                SELECT iss.iss_inventory, iss.st_id, css.st_name as warehouse_name,
                       i.id as item_id, i.item_number_prod as sku_code, i.item_title as sku_desc,
                       i.item_reg_width as width_cm, i.item_reg_kg as weight_kg, i.item_reg_length as meters
                FROM item_shops_storehouses iss
                JOIN item i ON i.id = iss.item_id
                JOIN company_shops_storehouses css ON css.id = iss.st_id
                WHERE (iss.st_id IN (16, 27) OR css.st_name LIKE '%3000%')
                  AND iss.iss_inventory > 0
                  {$whereErp}
                ORDER BY iss.iss_inventory DESC
                LIMIT :lim
            ";
            $stmtErp = $this->erpPdo->prepare($sqlErp);
            $stmtErp->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmtErp->execute();
            $erpRows = $stmtErp->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($erpRows as $row) {
                $desc = (string)$row['sku_desc'];
                $sku = (string)$row['sku_code'];

                // Si es Flexo, Seri o Pulpo: REGLA ESTRICTA -> Solo códigos iniciados en TEL
                if ($telOnly && !str_starts_with(strtoupper($sku), 'TEL')) {
                    continue;
                }

                // Detección de bobina impresa
                $isPrinted = (stripos($desc, 'IMPRES') !== false || stripos($sku, 'IMP') !== false);
                if ($unprintedOnly && $isPrinted) {
                    continue; // Para Flexo y Seri se excluyen bobinas ya impresas
                }

                // Parser de materialidad (PLA / PP / TNT)
                $rMat = 'PP';
                if (stripos($desc, 'PLA') !== false || stripos($sku, 'PLA') !== false) {
                    $rMat = 'PLA';
                }

                // Parser de color
                $parts = explode('/', $desc);
                $color = trim($parts[1] ?? '-');
                $color = trim((string)preg_replace('/\(pantone[^\)]*\)/i', '', $color)) ?: '-';

                $widthCm = (float)$row['width_cm'];
                $gramms = 80.0;
                $meters = (float)($row['meters'] ?: 1100);
                if (preg_match('/\/(\d+(?:\.\d+)?)[xX](\d+(?:\.\d+)?)[xX](\d+)/i', $desc, $mD)) {
                    if ($widthCm <= 0) $widthCm = (float)$mD[1];
                    $gramms = (float)$mD[2];
                    $meters = (float)$mD[3];
                } elseif (preg_match('/\/(\d{2,3})\/(?:(\d{2,3})GR|)/i', $desc, $mD2)) {
                    if ($widthCm <= 0) $widthCm = (float)$mD2[1];
                    if (!empty($mD2[2])) $gramms = (float)$mD2[2];
                }
                if ($widthCm > 0 && $widthCm < 20) {
                    $widthCm *= 10;
                }

                $weightKg = (float)$row['weight_kg'];
                if ($weightKg <= 0 && $widthCm > 0) {
                    $weightKg = round(($widthCm / 100) * $meters * ($gramms / 1000), 1);
                }
                if ($weightKg <= 0) {
                    $weightKg = ($rMat === 'PLA' ? 120.0 : 85.0);
                }

                // Compatibilidad con la OT
                $isComp = false;
                $matMatch = true;
                $colMatch = true;
                $gramMatch = true;
                $widthMatch = true;
                if ($targetMat !== '') {
                    $normTarget = str_contains($targetMat, 'PLA') ? 'PLA' : 'PP';
                    $matMatch = ($rMat === $normTarget);
                }
                if ($targetColor !== '') {
                    $colMatch = str_contains(mb_strtolower($color), $targetColor) || str_contains($targetColor, mb_strtolower($color));
                }
                if ($targetGrams > 0 && $gramms > 0) {
                    $gramMatch = (abs($gramms - $targetGrams) <= 5);
                }
                if ($targetWidthMm > 0) {
                    $rollWidthMm = (int)round($widthCm * 10);
                    $widthMatch = ($rollWidthMm > 0 && abs($rollWidthMm - $targetWidthMm) <= 80) || ($rollWidthMm >= $targetWidthMm);
                }
                $isComp = ($matMatch && $colMatch && $gramMatch && $widthMatch && (!$isPrinter || !$isPrinted));

                $rolls[] = [
                    'id' => "BOD3000_{$row['st_id']}_{$row['item_id']}",
                    'source' => 'ERP',
                    'erp_item_id' => (int)$row['item_id'],
                    'st_id' => (int)$row['st_id'],
                    'roll_code' => "BOB-{$sku}-B3000",
                    'sku_code' => $sku,
                    'sku_desc' => $desc,
                    'material' => $rMat,
                    'color' => $color,
                    'width_cm' => $widthCm,
                    'grammage' => $gramms,
                    'meters' => $meters,
                    'weight_kg' => $weightKg,
                    'stock_qty' => (float)$row['iss_inventory'],
                    'warehouse_name' => (string)$row['warehouse_name'],
                    'is_printed' => $isPrinted,
                    'is_compatible' => $isComp,
                    'is_material_match' => $matMatch,
                    'is_color_match' => $colMatch,
                    'is_gram_match' => $gramMatch,
                    'is_width_match' => $widthMatch,
                ];
            }
        } catch (Throwable) {}

        // 2. Bobinas registradas en TRZ en Bodega 3000 o en proceso activo de producción
        if ($this->trzPdo !== null) {
            try {
                $whereTrzTel = $telOnly
                    ? "AND (s.code LIKE 'TEL%' OR s.code LIKE 'tel%' OR r.roll_code LIKE 'TEL%' OR r.roll_code LIKE 'tel%')"
                    : "";

                $sqlTrz = "
                    SELECT r.*, s.code as sku_code, s.description as sku_desc, w.name as warehouse_name
                    FROM rolls r
                    LEFT JOIN skus s ON r.sku_id = s.id
                    LEFT JOIN warehouses w ON r.warehouse_id = w.id
                    WHERE (w.code IN (3000, 16, 27) OR w.name LIKE '%3000%')
                      AND r.status != 'CONSUMED'
                      AND r.current_work_order_id IS NULL
                      {$whereTrzTel}
                    ORDER BY r.id DESC
                    LIMIT :lim
                ";
                $stmtTrz = $this->trzPdo->prepare($sqlTrz);
                $stmtTrz->bindValue(':lim', $limit, PDO::PARAM_INT);
                $stmtTrz->execute();
                $trzRows = $stmtTrz->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($trzRows as $row) {
                    $rCode = (string)$row['roll_code'];
                    $rDesc = (string)($row['sku_desc'] ?? '');
                    $skuCode = (string)($row['sku_code'] ?? '');

                    // Si es Flexo, Seri o Pulpo: REGLA ESTRICTA -> Solo códigos iniciados en TEL
                    if ($telOnly) {
                        $codeUpper = strtoupper($skuCode);
                        $rollUpper = strtoupper($rCode);
                        if (!str_starts_with($codeUpper, 'TEL') && !str_starts_with($rollUpper, 'TEL')) {
                            continue;
                        }
                    }

                    // Detección de bobina impresa en TRZ (RP-, ROLL-SE-, process_stage=PRINTED, o con source_work_order_id de impresión)
                    $isPrinted = ($row['process_stage'] === 'PRINTED'
                        || str_starts_with($rCode, 'RP-')
                        || str_starts_with($rCode, 'ROLL-SE-')
                        || (!in_array($row['process_stage'], ['RAW', 'CUT'], true) && !empty($row['source_work_order_id']))
                        || stripos($rDesc, 'IMPRES') !== false);

                    if ($unprintedOnly && $isPrinted) {
                        continue; // Para Flexo y Seri se excluyen bobinas ya impresas
                    }

                    $color = (string)($row['color'] ?? '-');
                    $rMat = 'PP';
                    if (stripos((string)$row['sku_code'], 'PLA') !== false || stripos($rDesc, 'PLA') !== false) {
                        $rMat = 'PLA';
                    }

                    $matMatch = ($targetMat === '' || (str_contains($targetMat, 'PLA') ? ($rMat === 'PLA') : ($rMat === 'PP')));
                    $colMatch = ($targetColor === '' || str_contains(mb_strtolower($color), $targetColor) || str_contains($targetColor, mb_strtolower($color)));
                    $gramms = (float)($row['microns'] ?? 80);
                    $gramMatch = true;
                    $widthMatch = true;
                    if ($targetGrams > 0 && $gramms > 0) {
                        $gramMatch = (abs($gramms - $targetGrams) <= 5);
                    }
                    if ($targetWidthMm > 0) {
                        $rWidthMm = (int)($row['width_mm'] ?? 0);
                        $widthMatch = ($rWidthMm > 0 && abs($rWidthMm - $targetWidthMm) <= 80) || ($rWidthMm >= $targetWidthMm);
                    }
                    $isComp = ($matMatch && $colMatch && $gramMatch && $widthMatch && (!$isPrinter || !$isPrinted));

                    $rolls[] = [
                        'id' => (int)$row['id'],
                        'source' => 'TRZ',
                        'erp_item_id' => 0,
                        'st_id' => 16,
                        'roll_code' => $rCode,
                        'sku_code' => (string)($row['sku_code'] ?? 'S/C'),
                        'sku_desc' => $rDesc,
                        'material' => $rMat,
                        'color' => $color,
                        'width_cm' => round(((float)($row['width_mm'] ?? 0)) / 10, 1),
                        'grammage' => $gramms,
                        'meters' => (float)($row['meters'] ?? 0),
                        'weight_kg' => (float)($row['weight_kg'] ?? 0),
                        'stock_qty' => (float)($row['received_qty'] ?? 1),
                        'warehouse_name' => (string)($row['warehouse_name'] ?? 'Bodega 3000'),
                        'is_printed' => $isPrinted,
                        'is_compatible' => $isComp,
                        'is_material_match' => $matMatch,
                        'is_color_match' => $colMatch,
                        'is_gram_match' => $gramMatch,
                        'is_width_match' => $widthMatch,
                    ];
                }
            } catch (Throwable) {}
        }

        if ($compatibleOnly && $agId !== null && $agId > 0) {
            $rolls = array_values(array_filter(
                $rolls,
                static fn(array $roll): bool => !empty($roll['is_compatible'])
            ));
        }

        return $rolls;
    }

    private function adjustBodega3000InventoryBySku(string $skuCode, float $delta): bool
    {
        $skuCode = trim($skuCode);
        if ($skuCode === '' || abs($delta) < 0.0001) {
            return false;
        }

        try {
            $stmtItem = $this->erpPdo->prepare("
                SELECT id
                FROM item
                WHERE item_number_prod = :sku OR item_number = :sku
                LIMIT 1
            ");
            $stmtItem->execute([':sku' => $skuCode]);
            $itemId = (int)$stmtItem->fetchColumn();
            if ($itemId <= 0) {
                return false;
            }

            $stmtRow = $this->erpPdo->prepare("
                SELECT iss.st_id, COALESCE(iss.shop_id, 1) AS shop_id
                FROM item_shops_storehouses iss
                INNER JOIN company_shops_storehouses css ON css.id = iss.st_id
                WHERE iss.item_id = :item_id
                  AND (iss.st_id IN (16, 27) OR css.st_name LIKE '%3000%')
                ORDER BY iss.iss_inventory DESC, iss.st_id ASC
                LIMIT 1
            ");
            $stmtRow->execute([':item_id' => $itemId]);
            $row = $stmtRow->fetch(PDO::FETCH_ASSOC) ?: [];

            $stId = (int)($row['st_id'] ?? 16);
            $shopId = (int)($row['shop_id'] ?? 1);

            if ($delta < 0) {
                $qty = abs($delta);
                $stmtUpd = $this->erpPdo->prepare("
                    UPDATE item_shops_storehouses
                    SET iss_inventory = GREATEST(0, iss_inventory - :qty)
                    WHERE item_id = :item_id
                      AND st_id = :st_id
                ");
                $stmtUpd->execute([
                    ':qty' => $qty,
                    ':item_id' => $itemId,
                    ':st_id' => $stId,
                ]);
                return $stmtUpd->rowCount() > 0;
            }

            $stmtUpd = $this->erpPdo->prepare("
                UPDATE item_shops_storehouses
                SET iss_inventory = iss_inventory + :qty
                WHERE item_id = :item_id
                  AND st_id = :st_id
            ");
            $stmtUpd->execute([
                ':qty' => $delta,
                ':item_id' => $itemId,
                ':st_id' => $stId,
            ]);
            if ($stmtUpd->rowCount() > 0) {
                return true;
            }

            $stmtIns = $this->erpPdo->prepare("
                INSERT INTO item_shops_storehouses (item_id, shop_id, st_id, iss_inventory)
                VALUES (:item_id, :shop_id, :st_id, :qty)
            ");
            $stmtIns->execute([
                ':item_id' => $itemId,
                ':shop_id' => $shopId,
                ':st_id' => $stId,
                ':qty' => $delta,
            ]);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function normalizeTraceabilityCode(string $code): string
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
     * Procesa el escaneo de una bobina (código de trazabilidad, SKU o ID de Bodega 3000)
     * con pistola láser/código de barras/QR para máquinas de Flexografía, Serigrafía o Corte y Sellado.
     * Asigna la bobina a la OT y la monta directamente en la máquina.
     *
     * @param int $agId ID de agenda / OT
     * @param int $workerOtId ID de prod_worker_ot
     * @param string $code Código escaneado con la pistola láser
     * @param string $operatorName Nombre del operario
     * @param int $machineId ID del equipo
     * @param int $workerId ID del trabajador
     * @param int $plantaId ID de planta
     * @return array{ok: bool, roll_id?: int, roll_code?: string, weight_kg?: float, sku_code?: string, sku_desc?: string, mounted?: bool, already_mounted?: bool, error?: string, message?: string}
     */
    public function scanAndMountMachineRoll(
        int $agId,
        int $workerOtId,
        string $code,
        string $operatorName,
        int $machineId,
        int $workerId = 0,
        int $plantaId = 0
    ): array {
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'error' => 'Código de bobina vacío.'];
        }

        $normCode = $this->normalizeTraceabilityCode($code);

        // Si workerOtId no fue proporcionado pero hay agId, intentar obtener o iniciar OT
        if ($workerOtId <= 0 && $agId > 0 && $workerId > 0) {
            $init = $this->getOpenWorkerInit($workerId, $plantaId);
            if ($init !== null) {
                $openOt = $this->getOpenWorkerOtForInit((int)$init['id'], $agId);
                if ($openOt !== null) {
                    $workerOtId = (int)$openOt['id'];
                } else {
                    $startRes = $this->startWorkerOt($agId, (int)$init['id']);
                    if (!empty($startRes['ok']) && !empty($startRes['worker_ot_id'])) {
                        $workerOtId = (int)$startRes['worker_ot_id'];
                    }
                }
            }
        }

        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'Debe abrir la orden de trabajo en la máquina antes de cargar la bobina.'];
        }

        $agDetails = $this->getWorkOrderAgendaDetails($agId);
        $isFlexoSeri = $this->isFlexoSeriPulpoMachine($machineId, $agDetails);

        // 1. Buscar en rolls de trazabilidad por roll_code o id numérico
        // (también reconoce el código de la etiqueta de trazabilidad de bodega, no solo el roll_code)
        $trzRoll = null;
        if ($this->trzPdo !== null) {
            try {
                $trzRoll = TraceableRollLookup::find($this->trzPdo, $code);
            } catch (Throwable) {}
        }

        // Si se encontró en TRZ
        if ($trzRoll !== null) {
            $status = strtoupper(trim((string)($trzRoll['status'] ?? '')));
            if ($status === 'CONSUMED' || $status === 'SCRAPPED') {
                return ['ok' => false, 'error' => "La bobina '{$trzRoll['roll_code']}' figura como CONSUMIDA o dada de baja en el sistema."];
            }

            // Comprobar si ya está montada en esta misma OT
            if ($this->trzPdo !== null) {
                try {
                    $continuity = new MachineRollContinuityService($this->erpPdo, $this->trzPdo);
                    $physState = $continuity->state((int)$trzRoll['id']);
                    if (!empty($physState['loaded']) && (int)($physState['worker_ot_id'] ?? 0) === $workerOtId) {
                        return [
                            'ok' => true,
                            'already_mounted' => true,
                            'roll_id' => (int)$trzRoll['id'],
                            'roll_code' => (string)$trzRoll['roll_code'],
                            'weight_kg' => TraceableRollLookup::weightKg($this->trzPdo, $trzRoll, $code),
                            'message' => "La bobina '{$trzRoll['roll_code']}' ya se encuentra montada en esta máquina.",
                        ];
                    }
                } catch (Throwable) {}
            }

            // Comprobar si está asignada a otra OT activa
            $currentOwner = (int)($trzRoll['current_work_order_id'] ?? 0);
            $localTrzOtId = $this->resolveTraceabilityWorkOrderId($workerOtId, $agId, false);
            if ($currentOwner > 0 && $currentOwner !== $workerOtId && $currentOwner !== $localTrzOtId) {
                return ['ok' => false, 'error' => "La bobina '{$trzRoll['roll_code']}' ya está asignada a otra orden de trabajo activa."];
            }

            // Comprobar ubicación en Bodega 3000
            $whCode = (string)($trzRoll['warehouse_code'] ?? '');
            $whName = (string)($trzRoll['warehouse_name'] ?? '');
            $whId = (int)($trzRoll['warehouse_id'] ?? 0);
            $in3000 = in_array($whId, [18, 58], true) || in_array($whCode, ['3000', '16', '27'], true) || stripos($whName, '3000') !== false || $currentOwner === $workerOtId || $currentOwner === $localTrzOtId;
            if (!$in3000) {
                return ['ok' => false, 'error' => "La bobina '{$trzRoll['roll_code']}' se encuentra en '{$whName}'. Debe trasladarla a Bodega 3000 antes de montarla en máquina."];
            }

            // Restricciones para Flexo y Seri
            if ($isFlexoSeri) {
                $sku = (string)($trzRoll['sku_code'] ?? '');
                $rCode = (string)$trzRoll['roll_code'];
                if (!str_starts_with(strtoupper($sku), 'TEL') && !str_starts_with(strtoupper($rCode), 'TEL')) {
                    return ['ok' => false, 'error' => "En Flexografía y Serigrafía solo se pueden montar bobinas de tela virgen (códigos TEL...). La bobina {$rCode} ({$sku}) no corresponde a tela virgen."];
                }
                $isPrinted = ($trzRoll['process_stage'] === 'PRINTED'
                    || str_starts_with($rCode, 'RP-')
                    || str_starts_with($rCode, 'ROLL-SE-')
                    || (!in_array($trzRoll['process_stage'], ['RAW', 'CUT'], true) && !empty($trzRoll['source_work_order_id'])));
                if ($isPrinted) {
                    return ['ok' => false, 'error' => "La bobina {$rCode} ya fue impresa previamente. Las máquinas de impresión requieren bobinas vírgenes."];
                }
            }

            // Peso registrado en la trazabilidad (de la bobina o de su etiqueta de bodega)
            $wKg = TraceableRollLookup::weightKg($this->trzPdo, $trzRoll, $code);
            $assignRes = $this->assignBodega3000RollToMachine(
                $workerOtId,
                (string)$trzRoll['id'],
                $wKg,
                $operatorName,
                $machineId,
                'Cargada vía escáner láser',
                false,
                $agId,
                (string)$trzRoll['roll_code'],
                'with'
            );
            if (empty($assignRes['ok'])) {
                return $assignRes;
            }

            $mountRes = $this->mountRollOnMachine($workerOtId, (int)$trzRoll['id'], $operatorName, $wKg, 'Montada automáticamente tras escaneo láser');
            return [
                'ok' => true,
                'roll_id' => (int)$trzRoll['id'],
                'roll_code' => (string)$trzRoll['roll_code'],
                'weight_kg' => $wKg,
                'sku_code' => (string)($trzRoll['sku_code'] ?? ''),
                'sku_desc' => (string)($trzRoll['sku_desc'] ?? ''),
                'mounted' => true,
                'message' => "Bobina '{$trzRoll['roll_code']}' cargada y montada en máquina exitosamente.",
            ];
        }

        // 2. Si no es un roll_code de TRZ, buscar si coincide con un SKU en rolls de TRZ disponibles en Bodega 3000
        if ($this->trzPdo !== null) {
            try {
                $stmtSkuTrz = $this->trzPdo->prepare("
                    SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                    FROM rolls r
                    JOIN skus s ON r.sku_id = s.id
                    LEFT JOIN warehouses w ON r.warehouse_id = w.id
                    WHERE (s.code = :c OR s.code = :nc)
                      AND (w.code IN (3000, 16, 27) OR w.name LIKE '%3000%' OR r.warehouse_id IN (18, 58))
                      AND r.status != 'CONSUMED'
                      AND r.current_work_order_id IS NULL
                    ORDER BY r.id DESC LIMIT 1
                ");
                $stmtSkuTrz->execute([':c' => $code, ':nc' => $normCode]);
                $trzSkuRoll = $stmtSkuTrz->fetch(PDO::FETCH_ASSOC);
                if ($trzSkuRoll) {
                    if ($isFlexoSeri) {
                        $sku = (string)($trzSkuRoll['sku_code'] ?? '');
                        if (!str_starts_with(strtoupper($sku), 'TEL') && !str_starts_with(strtoupper((string)$trzSkuRoll['roll_code']), 'TEL')) {
                            return ['ok' => false, 'error' => "En Flexografía y Serigrafía solo se pueden montar bobinas de tela virgen (códigos TEL...)."];
                        }
                    }

                    $wKg = TraceableRollLookup::weightKg($this->trzPdo, $trzSkuRoll, $code);
                    $assignRes = $this->assignBodega3000RollToMachine(
                        $workerOtId,
                        (string)$trzSkuRoll['id'],
                        $wKg,
                        $operatorName,
                        $machineId,
                        'Cargada vía escáner láser por SKU',
                        false,
                        $agId,
                        (string)$trzSkuRoll['roll_code'],
                        'with'
                    );
                    if (!empty($assignRes['ok'])) {
                        $this->mountRollOnMachine($workerOtId, (int)$trzSkuRoll['id'], $operatorName, $wKg, 'Montada automáticamente tras escaneo láser');
                        return [
                            'ok' => true,
                            'roll_id' => (int)$trzSkuRoll['id'],
                            'roll_code' => (string)$trzSkuRoll['roll_code'],
                            'weight_kg' => $wKg,
                            'sku_code' => (string)($trzSkuRoll['sku_code'] ?? ''),
                            'sku_desc' => (string)($trzSkuRoll['sku_desc'] ?? ''),
                            'mounted' => true,
                            'message' => "Bobina '{$trzSkuRoll['roll_code']}' cargada y montada en máquina exitosamente.",
                        ];
                    }
                }
            } catch (Throwable) {}
        }

        // 3. Buscar en ERP item y almacén Bodega 3000 (st_id 16, 27)
        try {
            $stmtErp = $this->erpPdo->prepare("
                SELECT i.*, iss.st_id, iss.iss_inventory
                FROM item i
                JOIN item_shops_storehouses iss ON i.id = iss.item_id AND iss.st_id IN (16, 27)
                WHERE (i.item_number_prod = :c OR i.item_number_prod = :nc OR i.item_number = :c OR i.id = :cid)
                  AND iss.iss_inventory > 0
                ORDER BY iss.iss_inventory DESC LIMIT 1
            ");
            $stmtErp->execute([
                ':c' => $code,
                ':nc' => $normCode,
                ':cid' => is_numeric($code) ? (int)$code : 0,
            ]);
            $erpItem = $stmtErp->fetch(PDO::FETCH_ASSOC);

            // Búsqueda LIKE de respaldo
            if (!$erpItem) {
                $stmtErpLike = $this->erpPdo->prepare("
                    SELECT i.*, iss.st_id, iss.iss_inventory
                    FROM item i
                    JOIN item_shops_storehouses iss ON i.id = iss.item_id AND iss.st_id IN (16, 27)
                    WHERE (i.item_number_prod LIKE :c OR i.item_title LIKE :c)
                      AND iss.iss_inventory > 0
                    ORDER BY iss.iss_inventory DESC LIMIT 1
                ");
                $stmtErpLike->execute([':c' => "%{$code}%"]);
                $erpItem = $stmtErpLike->fetch(PDO::FETCH_ASSOC);
            }

            if ($erpItem) {
                $sku = (string)$erpItem['item_number_prod'];
                if ($isFlexoSeri && !str_starts_with(strtoupper($sku), 'TEL')) {
                    return ['ok' => false, 'error' => "En Flexografía y Serigrafía solo se pueden montar bobinas de tela virgen (códigos TEL...). El ítem '{$sku}' no corresponde a tela virgen."];
                }

                $stId = (int)$erpItem['st_id'];
                $itemId = (int)$erpItem['id'];
                $wKg = (float)($erpItem['item_reg_kg'] ?: 85.0);

                $assignRes = $this->assignBodega3000RollToMachine(
                    $workerOtId,
                    "BOD3000_{$stId}_{$itemId}",
                    $wKg,
                    $operatorName,
                    $machineId,
                    'Cargada vía escáner láser por SKU/ítem ERP',
                    false,
                    $agId,
                    '',
                    'auto'
                );

                if (empty($assignRes['ok'])) {
                    return $assignRes;
                }

                $assignedRollId = (int)($assignRes['roll_id'] ?? 0);
                if ($assignedRollId > 0) {
                    $this->mountRollOnMachine($workerOtId, $assignedRollId, $operatorName, $wKg, 'Montada automáticamente tras escaneo láser');
                }

                return [
                    'ok' => true,
                    'roll_id' => $assignedRollId,
                    'roll_code' => (string)($assignRes['roll_code'] ?? $sku),
                    'weight_kg' => $wKg,
                    'sku_code' => $sku,
                    'sku_desc' => (string)($erpItem['item_title'] ?? ''),
                    'mounted' => true,
                    'message' => "Bobina '{$sku}' asignada desde Bodega 3000 y montada en máquina exitosamente.",
                ];
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al consultar stock en Bodega 3000: ' . $e->getMessage()];
        }

        return [
            'ok' => false,
            'error' => "No se encontró ninguna bobina disponible en Bodega 3000 con el código o SKU '{$code}'. Verifique que esté ingresada a Bodega 3000.",
        ];
    }

    /**
     * Asigna una bobina de Bodega 3000 a la máquina y operario para una OT.
     * Soporta tanto trazabilidad estándar como saltarse la trazabilidad si la bobina no tiene código.
     */
    public function assignBodega3000RollToMachine(
        int $workerOtId,
        string $rollIdentifier,
        float $weightKg,
        string $operatorName,
        int $machineId,
        string $comments = '',
        bool $skipTraceability = false,
        ?int $agId = null,
        string $confirmedTraceabilityCode = '',
        string $traceabilityMode = 'auto'
    ): array {
        if ($this->trzPdo===null) return ['ok'=>false,'error'=>'Trazabilidad no disponible.'];
        $locks=[];
        try {
            $continuity=new MachineRollContinuityService($this->erpPdo,$this->trzPdo);
            $locks=$continuity->lockMachineForWorkOrder($workerOtId,$machineId);
            if (!$skipTraceability && ctype_digit($rollIdentifier)) {
                $locks=array_merge($locks,$this->lockRollState([(int)$rollIdentifier],$workerOtId));
            }
            return $this->assignBodega3000RollToMachineUnlocked($workerOtId,$rollIdentifier,$weightKg,$operatorName,
                $machineId,$comments,$skipTraceability,$agId,$confirmedTraceabilityCode,$traceabilityMode);
        } catch (Throwable $e) {
            error_log('Machine roll assignment: '.$e->getMessage());
            return ['ok'=>false,'error'=>$e instanceof PDOException?'No se pudo confirmar la carga de bobina. Solicita revision.':$e->getMessage()];
        } finally {
            $this->unlockRollState($locks);
        }
    }

    private function assignBodega3000RollToMachineUnlocked(
        int $workerOtId,
        string $rollIdentifier,
        float $weightKg,
        string $operatorName,
        int $machineId,
        string $comments = '',
        bool $skipTraceability = false,
        ?int $agId = null,
        string $confirmedTraceabilityCode = '',
        string $traceabilityMode = 'auto'
    ): array {
        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'Orden de trabajo inválida.'];
        }

        $now = time();
        $traceabilityMode = strtolower(trim($traceabilityMode));
        if (!in_array($traceabilityMode, ['auto', 'with', 'without'], true)) {
            $traceabilityMode = 'auto';
        }

        // 1. MODO: SALTARSEN LA TRAZABILIDAD (Bobina sin código QR o código de trazabilidad)
        if ($skipTraceability) {
            $traceabilityWorkOrderId = $this->resolveTraceabilityWorkOrderId($workerOtId, $agId, true);
            if ($traceabilityWorkOrderId <= 0) {
                return ['ok' => false, 'error' => 'No se pudo resolver la OT local de trazabilidad para registrar la bobina manual.'];
            }
            $manualWarehouseId = $this->resolveBodega3000WarehouseId();
            if ($manualWarehouseId <= 0) {
                return ['ok' => false, 'error' => 'No se pudo resolver la Bodega 3000 en trazabilidad.'];
            }

            $rollCodeDisplay = trim($rollIdentifier) !== '' ? trim($rollIdentifier) : 'BOBINA-MANUAL-' . date('ymd-His');
            $wKg = $weightKg > 0 ? $weightKg : 80.0;
            $manualMaterial = 'MANUAL';
            if (preg_match('/Material:\s*([A-Z0-9]+)/i', $comments, $mMaterial) === 1) {
                $manualMaterial = strtoupper(trim((string)$mMaterial[1]));
            } elseif (preg_match('/BOBINA-MANUAL-([A-Z0-9]+)/i', $rollCodeDisplay, $mMaterial) === 1) {
                $manualMaterial = strtoupper(trim((string)$mMaterial[1]));
            }
            $manualColor = '';
            if (preg_match('/Color:\s*([^,\.]+)/i', $comments, $mColor) === 1) {
                $manualColor = trim((string)$mColor[1]);
            }
            $manualWidthMm = 0;
            if (preg_match('/Ancho:\s*([\d\.,]+)\s*cm/i', $comments, $mWidth) === 1) {
                $manualWidthMm = (int)round(((float)str_replace(',', '.', (string)$mWidth[1])) * 10);
            }
            $manualMeters = 0.0;
            if (preg_match('/Metros:\s*([\d\.,]+)/i', $comments, $mMeters) === 1) {
                $manualMeters = (float)str_replace(',', '.', (string)$mMeters[1]);
            }
            $manualSkuCode = 'MANUAL-' . preg_replace('/[^A-Z0-9]+/', '-', strtoupper($manualMaterial));
            $manualSkuDesc = 'Bobina manual sin trazabilidad - ' . $manualMaterial;
            $manualRollCode = 'MAN-' . preg_replace('/[^A-Z0-9]+/', '-', strtoupper($manualMaterial)) . '-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $evtComment = "Carga de bobina sin código de trazabilidad: {$rollCodeDisplay} ({$wKg} kg) asignada a máquina por {$operatorName}";
            if (trim($comments) !== '') {
                $evtComment .= " [Detalle: " . trim($comments) . "]";
            }

            // Crear una bobina real ligada a la OT para que aparezca en "bobinas para trabajar"
            $newRollId = 0;
            if ($this->trzPdo === null) {
                return ['ok' => false, 'error' => 'La base de datos de trazabilidad no está disponible para registrar la bobina manual.'];
            }
            try {
                $this->trzPdo->prepare("
                    INSERT INTO skus (code, description, is_active)
                    VALUES (:c, :d, 1)
                    ON DUPLICATE KEY UPDATE description = VALUES(description)
                ")->execute([
                    ':c' => $manualSkuCode,
                    ':d' => $manualSkuDesc,
                ]);
                $stSkuManual = $this->trzPdo->prepare("SELECT id FROM skus WHERE code = :c LIMIT 1");
                $stSkuManual->execute([':c' => $manualSkuCode]);
                $manualSkuId = (int)$stSkuManual->fetchColumn();
                if ($manualSkuId <= 0) {
                    return ['ok' => false, 'error' => 'No se pudo resolver el SKU interno para la bobina manual.'];
                }

                $stmtInsManualRoll = $this->trzPdo->prepare("
                    INSERT INTO rolls
                        (roll_code, sku_id, warehouse_id, weight_kg, received_qty, reception_mode, width_mm, color, meters, status, current_work_order_id, source_work_order_id, process_stage, created_at)
                    VALUES
                        (:code, :sku_id, :warehouse_id, :weight_kg, 1, 'MANUAL_NO_TRACEABILITY', :width_mm, :color, :meters, 'IN_PROCESS', :ot_id, :source_ot_id, 'PRODUCTION', NOW())
                ");
                $stmtInsManualRoll->execute([
                    ':code' => $manualRollCode,
                    ':sku_id' => $manualSkuId,
                    ':warehouse_id' => $manualWarehouseId,
                    ':weight_kg' => $wKg,
                    ':width_mm' => $manualWidthMm,
                    ':color' => $manualColor,
                    ':meters' => $manualMeters,
                    ':ot_id' => $traceabilityWorkOrderId,
                    ':source_ot_id' => $workerOtId,
                ]);
                $newRollId = (int)$this->trzPdo->lastInsertId();
                if ($newRollId <= 0) {
                    return ['ok' => false, 'error' => 'No se pudo registrar la bobina manual en trazabilidad.'];
                }

                $stmtEvtTrz = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
                $stmtEvtTrz->execute([
                    ':type' => 'ROLL_LOADED_WITHOUT_TRACEABILITY',
                    ':payload' => json_encode([
                        'roll_id' => $newRollId,
                        'roll_code' => $manualRollCode,
                        'worker_ot_id' => $workerOtId,
                        'roll_identifier' => $rollCodeDisplay,
                        'weight_kg' => $wKg,
                        'operator' => $operatorName,
                        'machine_id' => $machineId,
                        'comments' => $comments,
                        'timestamp' => $now,
                        'note' => 'Cargada con salto de trazabilidad autorizado',
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => 'Error al registrar la bobina manual en trazabilidad: ' . $e->getMessage()];
            }

            // Registrar en eventos de la OT (ERP)
            try {
                $stmtErp = $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events
                     (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                    VALUES
                     (:ot_id, 0, :pkg, :crtdat, :enddat, 2, 'materiales', :comments)
                ");
                $stmtErp->execute([
                    ':ot_id' => $workerOtId,
                    ':pkg' => $wKg,
                    ':crtdat' => $now,
                    ':enddat' => $now,
                    ':comments' => $evtComment,
                ]);
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => 'Error al registrar evento en ERP: ' . $e->getMessage()];
            }

            return ['ok' => true, 'roll_id' => $newRollId, 'roll_code' => $manualRollCode, 'message' => 'Bobina sin trazabilidad cargada exitosamente a la máquina y OT.'];
        }

        // 2. MODO: CON TRAZABILIDAD (Seleccionada desde Bodega 3000 o código QR)
        $agDetails = ($agId !== null && $agId > 0) ? $this->getWorkOrderAgendaDetails($agId) : null;
        $isFlexoSeriPulpo = $this->isFlexoSeriPulpoMachine($machineId, $agDetails);
        $traceabilityWorkOrderId = $this->resolveTraceabilityWorkOrderId($workerOtId, $agId, true);
        if ($traceabilityWorkOrderId <= 0) {
            return ['ok' => false, 'error' => 'No se pudo resolver la OT local de trazabilidad para registrar la bobina.'];
        }

        // Caso A: Selección desde ERP_stId_itemId o BOD3000_stId_itemId
        if (preg_match('/^(?:BOD3000|ERP)_(\d+)_(\d+)$/', $rollIdentifier, $m)) {
            $stId = (int)$m[1];
            $itemId = (int)$m[2];

            $stmtItem = $this->erpPdo->prepare("
                SELECT i.*, css.st_name, iss.iss_inventory
                FROM item i
                JOIN item_shops_storehouses iss ON i.id = iss.item_id AND iss.st_id = :stid
                JOIN company_shops_storehouses css ON css.id = iss.st_id
                WHERE i.id = :itid LIMIT 1
            ");
            $stmtItem->execute([':stid' => $stId, ':itid' => $itemId]);
            $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                return ['ok' => false, 'error' => 'Material de Bodega 3000 no encontrado en ERP.'];
            }

            $sku = (string)$item['item_number_prod'];
            if ($isFlexoSeriPulpo && !str_starts_with(strtoupper($sku), 'TEL')) {
                return [
                    'ok' => false,
                    'error' => "En Flexografía, Serigrafía y Pulpo Serigráfico solo se pueden solicitar materiales de Bodega 3000 con códigos de tela (iniciados en TEL). El material {$sku} corresponde a bolsa o bobina ya hecha/procesada."
                ];
            }
            $desc = (string)$item['item_title'];
            $wKg = $weightKg > 0 ? $weightKg : (float)($item['item_reg_kg'] ?: 85.0);
            $rollCode = "BOB-{$sku}-" . date('ymd') . "-" . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $normalizedConfirmedTraceabilityCode = $this->normalizeTraceabilityCode($confirmedTraceabilityCode);
            $mustUseExistingTraceability = $traceabilityMode === 'with'
                || ($traceabilityMode === 'auto' && $normalizedConfirmedTraceabilityCode !== '');

            if ($mustUseExistingTraceability) {
                if ($normalizedConfirmedTraceabilityCode === '') {
                    return ['ok' => false, 'error' => 'Debe escanear o ingresar el código de trazabilidad de la bobina que va a consumir.'];
                }
                if ($this->trzPdo === null) {
                    return ['ok' => false, 'error' => 'La base de trazabilidad no está disponible para validar ese código.'];
                }

                $stmtExistingTraceableRoll = $this->trzPdo->prepare("
                    SELECT r.id, s.code AS sku_code
                    FROM rolls r
                    LEFT JOIN skus s ON r.sku_id = s.id
                    LEFT JOIN warehouses w ON r.warehouse_id = w.id
                    WHERE (UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(r.roll_code, ' ', ''), '\r', ''), '\n', ''), '\t', ''), '''', '-'), '`', '-')) = :code
                           OR r.id = (SELECT e.entity_id FROM traceability_warehouse_entries e
                                      WHERE UPPER(REPLACE(e.traceability_code, ' ', '')) = :code_lbl AND e.entity_type = 'ROLL' AND e.entity_id IS NOT NULL
                                      ORDER BY e.id DESC LIMIT 1))
                      AND r.current_work_order_id IS NULL
                      AND r.status != 'CONSUMED'
                      AND (w.code IN (3000, 16, 27) OR w.name LIKE '%3000%')
                    ORDER BY r.id DESC
                    LIMIT 1
                ");
                $stmtExistingTraceableRoll->execute([
                    ':code' => $normalizedConfirmedTraceabilityCode,
                    ':code_lbl' => $normalizedConfirmedTraceabilityCode,
                ]);
                $existingTraceableRoll = $stmtExistingTraceableRoll->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($existingTraceableRoll === null) {
                    return ['ok' => false, 'error' => 'No existe una bobina trazable disponible en Bodega 3000 con ese código.'];
                }

                $existingTraceableSku = strtoupper(trim((string)($existingTraceableRoll['sku_code'] ?? '')));
                if ($existingTraceableSku !== '' && strtoupper(trim($sku)) !== '' && $existingTraceableSku !== strtoupper(trim($sku))) {
                    return ['ok' => false, 'error' => 'El código trazable escaneado no corresponde al SKU seleccionado para esta OT.'];
                }

                return $this->assignBodega3000RollToMachine(
                    $workerOtId,
                    (string)((int)$existingTraceableRoll['id']),
                    $weightKg,
                    $operatorName,
                    $machineId,
                    $comments,
                    false,
                    $agId,
                    $confirmedTraceabilityCode,
                    'with'
                );
            }

            $stmtConsume3000 = $this->erpPdo->prepare("
                UPDATE item_shops_storehouses
                SET iss_inventory = GREATEST(0, iss_inventory - 1)
                WHERE item_id = :item_id
                  AND st_id = :st_id
                  AND iss_inventory >= 1
            ");
            $stmtConsume3000->execute([
                ':item_id' => $itemId,
                ':st_id' => $stId,
            ]);
            if ($stmtConsume3000->rowCount() <= 0) {
                return ['ok' => false, 'error' => 'La bobina ya no tiene stock disponible en Bodega 3000.'];
            }

            // Si TRZ disponible, crear roll en TRZ en Bodega 3000 con estado IN_PROCESS
            if ($this->trzPdo !== null) {
                try {
                    // Asegurar SKU
                    if (trim($sku) === '') {
                        $sku = trim((string)($item['item_number'] ?? ''));
                    }
                    if (trim($sku) === '') {
                        $sku = 'ERP-ITEM-' . $itemId;
                    }
                    $this->trzPdo->prepare("
                        INSERT INTO skus (code, description, is_active) VALUES (:c, :d, 1)
                        ON DUPLICATE KEY UPDATE description = VALUES(description)
                    ")->execute([':c' => $sku, ':d' => $desc]);
                    $skuId = (int)$this->trzPdo->lastInsertId();
                    if ($skuId <= 0) {
                        $stS = $this->trzPdo->prepare("SELECT id FROM skus WHERE code = :c LIMIT 1");
                        $stS->execute([':c' => $sku]);
                        $skuId = (int)$stS->fetchColumn();
                    }

                    // Asegurar Warehouse Bodega 3000
                    $whId = $this->resolveBodega3000WarehouseId();
                    if ($whId <= 0) {
                        throw new RuntimeException('No se pudo resolver la Bodega 3000 en trazabilidad.');
                    }

                    // Insertar roll
                    $insRoll = $this->trzPdo->prepare("
                        INSERT INTO rolls
                            (roll_code, sku_id, warehouse_id, weight_kg, received_qty, reception_mode, status, current_work_order_id, source_work_order_id, process_stage, created_at)
                        VALUES
                            (:code, :sku_id, :wh_id, :weight_kg, 1, 'BODEGA_3000', 'IN_PROCESS', :ot_id, :source_ot_id, 'PRODUCTION', NOW())
                    ");
                    $insRoll->execute([
                        ':code' => $rollCode,
                        ':sku_id' => $skuId,
                        ':wh_id' => $whId,
                        ':weight_kg' => $wKg,
                        ':ot_id' => $traceabilityWorkOrderId,
                        ':source_ot_id' => $workerOtId,
                    ]);
                    $newRollId = (int)$this->trzPdo->lastInsertId();

                    // Registrar evento en TRZ
                    $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)")->execute([
                        ':type' => 'ROLL_LOADED_TO_OT',
                        ':payload' => json_encode([
                            'roll_id' => $newRollId,
                            'roll_code' => $rollCode,
                            'sku_code' => $sku,
                            'weight_kg' => $wKg,
                            'worker_ot_id' => $workerOtId,
                            'operator' => $operatorName,
                            'machine_id' => $machineId,
                            'comments' => $comments,
                            'timestamp' => $now,
                            'warehouse' => 'Bodega 3000',
                        ], JSON_UNESCAPED_UNICODE),
                    ]);
                } catch (Throwable $e) {
                    $this->erpPdo->prepare("
                        UPDATE item_shops_storehouses
                        SET iss_inventory = iss_inventory + 1
                        WHERE item_id = :item_id
                          AND st_id = :st_id
                    ")->execute([
                        ':item_id' => $itemId,
                        ':st_id' => $stId,
                    ]);
                    return ['ok' => false, 'error' => 'No se pudo registrar la bobina en trazabilidad: ' . $e->getMessage()];
                }
            }

            // Registrar en eventos de la OT (ERP)
            $evtComment = "Carga Bobina Bodega 3000: {$rollCode} [{$sku}] - {$desc} ({$wKg} kg) asignada a máquina por {$operatorName}";
            if (trim($comments) !== '') {
                $evtComment .= " (" . trim($comments) . ")";
            }

            // Guardia anti-duplicidad: si ya se cargó esta misma bobina en los últimos 15 segundos para esta OT
            $stmtCheckDupCasoA = $this->erpPdo->prepare("
                SELECT id FROM prod_worker_ot_events
                WHERE evt_prod_worker_otid = :ot_id
                  AND evt_type = 'materiales'
                  AND evt_comments LIKE :roll_matcher
                  AND evt_crtdat >= :since_ts
                ORDER BY id DESC LIMIT 1
            ");
            $stmtCheckDupCasoA->execute([
                ':ot_id' => $workerOtId,
                ':roll_matcher' => "%{$rollCode}%",
                ':since_ts' => $now - 15,
            ]);
            if ($stmtCheckDupCasoA->fetchColumn()) {
                return ['ok' => true, 'roll_id' => (int)($newRollId ?? 0), 'roll_code' => $rollCode, 'message' => "Bobina {$rollCode} asignada exitosamente a la OT y máquina."];
            }

            $this->erpPdo->prepare("
                INSERT INTO prod_worker_ot_events
                 (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                VALUES
                 (:ot_id, 0, :pkg, :crtdat, :enddat, 2, 'materiales', :comments)
            ")->execute([
                ':ot_id' => $workerOtId,
                ':pkg' => $wKg,
                ':crtdat' => $now,
                ':enddat' => $now,
                ':comments' => $evtComment,
            ]);

            return ['ok' => true, 'roll_id' => (int)($newRollId ?? 0), 'roll_code' => $rollCode, 'message' => "Bobina {$rollCode} asignada exitosamente a la OT y máquina."];
        }

        // Caso B: ID numérico de roll en TRZ
        $trzRollId = (int)$rollIdentifier;
        if ($trzRollId > 0 && $this->trzPdo !== null) {
            $stR = $this->trzPdo->prepare("SELECT r.*, s.code as sku_code, s.description as sku_desc FROM rolls r LEFT JOIN skus s ON r.sku_id = s.id WHERE r.id = :id LIMIT 1");
            $stR->execute([':id' => $trzRollId]);
            $roll = $stR->fetch(PDO::FETCH_ASSOC);
            if ($roll) {
                $existingOtId = (int)($roll['current_work_order_id'] ?? 0);
                if ($existingOtId > 0 && $existingOtId !== $workerOtId && $existingOtId !== $traceabilityWorkOrderId) {
                    return ['ok' => false, 'error' => 'La bobina ya fue tomada por otra OT y no está disponible en Bodega 3000.'];
                }

                // Reenviar una carga vigente no debe descontar stock ni borrar su montaje.
                // Retrying a current load must not deduct stock or erase its mounting state.
                $physicalState=(new MachineRollContinuityService($this->erpPdo,$this->trzPdo))->state($trzRollId);
                if ($existingOtId>0 && $roll['status']==='IN_PROCESS' && $physicalState['loaded']
                    && $physicalState['worker_ot_id']===$workerOtId) {
                    if ($this->normalizeTraceabilityCode($confirmedTraceabilityCode)!==$this->normalizeTraceabilityCode((string)$roll['roll_code'])) {
                        return ['ok'=>false,'error'=>'El codigo de trazabilidad no corresponde a la bobina cargada.'];
                    }
                    return ['ok'=>true,'roll_id'=>$trzRollId,'roll_code'=>$roll['roll_code'],'message'=>'La bobina ya esta cargada en esta OT.'];
                }
                if (in_array((string)$roll['status'],['CONSUMED','SCRAPPED'],true)) {
                    return ['ok'=>false,'error'=>'La bobina ya no esta disponible para cargar.'];
                }

                $sku = (string)($roll['sku_code'] ?? 'S/C');
                $rCode = (string)$roll['roll_code'];
                if ($isFlexoSeriPulpo && !str_starts_with(strtoupper($sku), 'TEL') && !str_starts_with(strtoupper($rCode), 'TEL')) {
                    return [
                        'ok' => false,
                        'error' => "En Flexografía, Serigrafía y Pulpo Serigráfico solo se pueden solicitar materiales de Bodega 3000 con códigos de tela (iniciados en TEL). La bobina {$rCode} ({$sku}) corresponde a bolsa o bobina ya procesada."
                    ];
                }

                $isPrinted = ($roll['process_stage'] === 'PRINTED'
                    || str_starts_with((string)$roll['roll_code'], 'RP-')
                    || str_starts_with((string)$roll['roll_code'], 'ROLL-SE-')
                    || (!in_array($roll['process_stage'], ['RAW', 'CUT'], true) && !empty($roll['source_work_order_id'])));
                if ($isPrinted && $isFlexoSeriPulpo) {
                    return [
                        'ok' => false,
                        'error' => "La bobina {$roll['roll_code']} ya fue impresa previamente. Las máquinas de Flexografía y Serigrafía requieren bobinas vírgenes sin uso de impresión."
                    ];
                }

                $wKg = TraceableRollLookup::weightKg($this->trzPdo, $roll, $confirmedTraceabilityCode);
                $rCode = (string)$roll['roll_code'];
                $sku = (string)($roll['sku_code'] ?? 'S/C');
                $normalizedConfirmedTraceabilityCode = $this->normalizeTraceabilityCode($confirmedTraceabilityCode);
                $normalizedRollCode = $this->normalizeTraceabilityCode($rCode);

                if ($normalizedRollCode === '') {
                    return ['ok' => false, 'error' => 'La bobina trazable no tiene código válido para consumo.'];
                }
                if ($normalizedConfirmedTraceabilityCode === '') {
                    return ['ok' => false, 'error' => 'Debe escanear o ingresar el código de trazabilidad de la bobina que va a consumir.'];
                }
                if ($normalizedConfirmedTraceabilityCode !== $normalizedRollCode) {
                    return ['ok' => false, 'error' => 'El código de trazabilidad ingresado no corresponde a la bobina seleccionada.'];
                }

                $deductQty = (float)($roll['received_qty'] ?? 1);
                if ($deductQty <= 0) {
                    $deductQty = 1.0;
                }
                if ($sku !== '' && !$this->adjustBodega3000InventoryBySku($sku, -$deductQty)) {
                    return ['ok' => false, 'error' => 'No se pudo descontar la bobina del stock de Bodega 3000.'];
                }

                // Actualizar estado en TRZ
                $this->trzPdo->prepare("
                    UPDATE rolls
                    SET status = 'IN_PROCESS', current_work_order_id = :ot_id, process_stage = 'PRODUCTION'
                    WHERE id = :id
                ")->execute([':ot_id' => $traceabilityWorkOrderId, ':id' => $trzRollId]);

                // Registrar evento en TRZ
                $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)")->execute([
                    ':type' => 'ROLL_LOADED_TO_OT',
                    ':payload' => json_encode([
                        'roll_id' => $trzRollId,
                        'roll_code' => $rCode,
                        'sku_code' => $sku,
                        'weight_kg' => $wKg,
                        'worker_ot_id' => $workerOtId,
                        'operator' => $operatorName,
                        'machine_id' => $machineId,
                        'comments' => $comments,
                        'timestamp' => $now,
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                // Registrar evento en ERP
                $evtComment = "Carga Bobina Trazable: {$rCode} [{$sku}] ({$wKg} kg) asignada a máquina por {$operatorName}";
                if (trim($comments) !== '') {
                    $evtComment .= " (" . trim($comments) . ")";
                }

                // Guardia anti-duplicidad: si ya se cargó esta misma bobina en los últimos 15 segundos para esta OT
                $stmtCheckDupCasoB = $this->erpPdo->prepare("
                    SELECT id FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id
                      AND evt_type = 'materiales'
                      AND evt_comments LIKE :roll_matcher
                      AND evt_crtdat >= :since_ts
                    ORDER BY id DESC LIMIT 1
                ");
                $stmtCheckDupCasoB->execute([
                    ':ot_id' => $workerOtId,
                    ':roll_matcher' => "%{$rCode}%",
                    ':since_ts' => $now - 15,
                ]);
                if ($stmtCheckDupCasoB->fetchColumn()) {
                    return ['ok' => true, 'roll_id' => $trzRollId, 'roll_code' => $rCode, 'message' => "Bobina {$rCode} asignada exitosamente a la máquina y OT."];
                }

                $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events
                     (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                    VALUES
                     (:ot_id, 0, :pkg, :crtdat, :enddat, 2, 'materiales', :comments)
                ")->execute([
                    ':ot_id' => $workerOtId,
                    ':pkg' => $wKg,
                    ':crtdat' => $now,
                    ':enddat' => $now,
                    ':comments' => $evtComment,
                ]);

                return ['ok' => true, 'roll_id' => $trzRollId, 'roll_code' => $rCode, 'message' => "Bobina {$rCode} asignada exitosamente a la máquina y OT."];
            }
        }

        return ['ok' => false, 'error' => 'Identificador de bobina no válido.'];
    }

}

#[AllowDynamicProperties]
class ProductionMaterialsService
{
    use ProductionMaterialsServiceTrait;

    private ?ProductionService $bridge = null;

    public function __construct(private PDO $erpPdo, private ?PDO $trzPdo = null, ?ProductionService $bridge = null)
    {
        $this->bridge = $bridge;
    }

    public function __call(string $name, array $arguments): mixed
    {
        $this->bridge ??= new ProductionService($this->erpPdo, $this->trzPdo);
        return $this->bridge->$name(...$arguments);
    }
}
