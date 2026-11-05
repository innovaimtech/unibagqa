<?php
    $body = '<div class="card" style="margin-bottom:20px;background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%);color:#fff;padding:24px;border-radius:12px">';
    $body .= '<div style="font-size:22px;font-weight:900;margin-bottom:6px;display:flex;align-items:center;gap:10px">';
    $body .= '<i class="fa fa-fw fa-sitemap" style="color:#38bdf8"></i> Trazabilidad Integral "Inicio a Fin"';
    $body .= '</div>';
    $body .= '<div style="font-size:13px;color:#94a3b8;margin-bottom:20px">';
    $body .= 'Rastreo genealógico 360° desde la Orden de Compra y Proveedor remoto, pasando por la bobina pesada en balanza, su procesamiento en máquinas y operarios, hasta la caja o bobina hija resultante.';
    $body .= '</div>';

    $body .= '<form method="get" action="/production/traceability" style="display:flex;gap:10px;flex-wrap:wrap;margin:0">';
    $body .= '<input class="input" type="text" name="q" value="' . htmlspecialchars($query) . '" required placeholder="Ingresa Bobina (ROLL-...), Caja (BOX-...), OT (22079) u OC (OC0004205)" style="flex:1;min-width:280px;background:#1e293b;border-color:#334155;color:#fff;font-size:15px">';
    $body .= '<button class="btn" type="submit" style="background:#38bdf8;border-color:#0284c7;color:#0f172a;font-weight:800;padding:10px 24px"><i class="fa fa-fw fa-search"></i> Rastrear</button>';
    if ($query !== '') {
        $body .= '<a class="btn secondary" href="/production/traceability" style="background:#334155;color:#f8fafc;border-color:#475569">Limpiar</a>';
    }
    $body .= '</form>';
    $body .= '</div>';

    if ($query === '') {
        $body .= '<div class="card" style="text-align:center;padding:48px 20px">';
        $body .= '<div style="font-size:48px;color:#94a3b8;margin-bottom:16px"><i class="fa fa-project-diagram"></i></div>';
        $body .= '<div style="font-size:18px;font-weight:800;color:#1e293b;margin-bottom:8px">Consulta de Trazabilidad</div>';
        $body .= '<div class="muted" style="max-width:520px;margin:0 auto 20px">Ingresa en el buscador superior el número de bobina, código de barras de caja, orden de trabajo u orden de compra para auditar toda la cadena de custodia.</div>';
        $body .= '</div>';
        renderProdwrkShell('Trazabilidad Integral', $body, 6, $operatorName, $plantaName);
        return;
    }

    if ($tree === null || ($tree['roll'] === null && $tree['work_order'] === null && $tree['purchase_order'] === null && empty($tree['boxes']) && empty($tree['pallet']))) {
        $body .= '<div class="card" style="text-align:center;padding:40px 20px">';
        $body .= '<div style="font-size:40px;color:#ef4444;margin-bottom:12px"><i class="fa fa-exclamation-triangle"></i></div>';
        $body .= '<div style="font-size:18px;font-weight:800;color:#1e293b;margin-bottom:6px">No se encontraron registros para "' . htmlspecialchars($query) . '"</div>';
        $body .= '<div class="muted">Verifica que el identificador ingresado esté escrito correctamente o haya sido recepcionado en el sistema.</div>';
        $body .= '</div>';
        renderProdwrkShell('Trazabilidad Integral', $body, 6, $operatorName, $plantaName);
        return;
    }

    if (function_exists('unibagBuildTraceabilityTreeHtml')) {
        $treeForFlow = $tree;
        $treeForFlow['query'] = $query;

        $body .= '<div class="card" style="margin-bottom:18px;background:#ffffff;border:1px solid #dbeafe;border-radius:12px;padding:16px 18px;box-shadow:0 2px 8px rgba(15,23,42,0.05)">';
        $body .= '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">';
        $body .= '<div>';
        $body .= '<div style="font-size:17px;font-weight:900;color:#0f172a;margin-bottom:4px">Flujo real de planta</div>';
        $body .= '<div style="font-size:12.5px;color:#475569;max-width:820px">';
        $body .= 'La trazabilidad se muestra siguiendo el recorrido operativo del material: origen y compra, ingreso físico a bodega, uso en máquina, transformación a bobinas hijas o cajas, y finalmente cadena de custodia / despacho.';
        $body .= '</div>';
        $body .= '</div>';
        $body .= '<div style="display:flex;gap:8px;flex-wrap:wrap">';
        $body .= '<span style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;padding:5px 10px;border-radius:999px;font-size:11.5px;font-weight:800">Origen</span>';
        $body .= '<span style="background:#f0fdfa;color:#0f766e;border:1px solid #99f6e4;padding:5px 10px;border-radius:999px;font-size:11.5px;font-weight:800">Bodega</span>';
        $body .= '<span style="background:#fffbeb;color:#b45309;border:1px solid #fde68a;padding:5px 10px;border-radius:999px;font-size:11.5px;font-weight:800">Producción</span>';
        $body .= '<span style="background:#faf5ff;color:#6d28d9;border:1px solid #e9d5ff;padding:5px 10px;border-radius:999px;font-size:11.5px;font-weight:800">Bobinas / Cajas</span>';
        $body .= '<span style="background:#f8fafc;color:#334155;border:1px solid #cbd5e1;padding:5px 10px;border-radius:999px;font-size:11.5px;font-weight:800">Custodia</span>';
        $body .= '</div>';
        $body .= '</div>';
        $body .= '</div>';

        $body .= unibagBuildTraceabilityTreeHtml($treeForFlow, false);
        renderProdwrkShell('Trazabilidad ' . htmlspecialchars($query), $body, 6, $operatorName, $plantaName);
        return;
    }

    $po = $tree['purchase_order'];
    $supp = $tree['supplier'];
    $roll = $tree['roll'];
    $parentRoll = $tree['parent_roll'];
    $wo = $tree['work_order'];
    $boxes = $tree['boxes'] ?? [];
    $pallet = $tree['pallet'];
    $childRolls = $tree['child_rolls'] ?? [];
    $movs = $tree['movements'] ?? [];

    // Determinar la entidad primaria rastreada
    $primaryType = 'ELEMENTO EN PLANTA';
    $primaryCode = $query;
    $primaryBadgeColor = '#3b82f6';
    if ($tree['box'] !== null) {
        $primaryType = 'CAJA TERMINADA';
        $primaryCode = (string)$tree['box']['box_code'];
        $primaryBadgeColor = '#f59e0b';
    } elseif ($pallet !== null) {
        $primaryType = 'PALLET DE DESPACHO';
        $primaryCode = (string)$pallet['pallet_code'];
        $primaryBadgeColor = '#8b5cf6';
    } elseif ($roll !== null) {
        $stage = (string)($roll['process_stage'] ?? 'RAW');
        if ($stage === 'PRINTED') {
            $primaryType = 'BOBINA SEMIELABORADA (FLEXO)';
            $primaryBadgeColor = '#0284c7';
        } elseif ($stage === 'CUT') {
            $primaryType = 'BOBINA CORTADA (REBOBINADORA)';
            $primaryBadgeColor = '#7c3aed';
        } else {
            $primaryType = 'BOBINA VIRGEN (RECEPCIÓN)';
            $primaryBadgeColor = '#10b981';
        }
        $primaryCode = (string)$roll['roll_code'];
    } elseif ($wo !== null) {
        $primaryType = 'ORDEN DE TRABAJO (OT)';
        $primaryCode = 'OT #' . ($wo['prd_number'] ?? $query);
        $primaryBadgeColor = '#ea580c';
    } elseif ($po !== null) {
        $primaryType = 'ORDEN DE COMPRA (OC)';
        $primaryCode = 'OC #' . ($po['po_code'] ?? $query);
        $primaryBadgeColor = '#059669';
    }

    // Encabezado de Identificación y Diagnóstico
    $body .= '<div class="card" style="margin-bottom:20px;padding:20px;border-left:6px solid ' . $primaryBadgeColor . ';background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.06)">';
    $body .= '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px">';
    $body .= '<div>';
    $body .= '<div style="font-size:12px;font-weight:bold;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">Identificador Activo</div>';
    $body .= '<div style="font-size:24px;font-weight:900;color:#0f172a;display:flex;align-items:center;gap:10px">';
    $body .= htmlspecialchars($primaryCode);
    $body .= '<span style="font-size:12px;background:' . $primaryBadgeColor . ';color:#fff;padding:4px 10px;border-radius:12px;font-weight:bold">' . $primaryType . '</span>';
    $body .= '</div>';
    $body .= '</div>';

    $body .= '<div style="display:flex;gap:10px;flex-wrap:wrap">';
    if ($roll !== null) {
        $body .= '<a href="/rolls/' . (int)$roll['id'] . '/label" target="_blank" class="btn" style="background:#0284c7;color:#fff;text-decoration:none"><i class="fa fa-print"></i> Etiqueta Bobina 100×70</a>';
    }
    if ($tree['box'] !== null) {
        $body .= '<a href="/boxes/' . (int)$tree['box']['id'] . '/label" target="_blank" class="btn" style="background:#f59e0b;color:#fff;text-decoration:none"><i class="fa fa-print"></i> Etiqueta Caja 100×70</a>';
    }
    if ($pallet !== null) {
        $body .= '<a href="/pallets/' . (int)$pallet['id'] . '/label" target="_blank" class="btn" style="background:#8b5cf6;color:#fff;text-decoration:none"><i class="fa fa-print"></i> Etiqueta Pallet 100×70</a>';
    }
    if ($wo !== null && !empty($wo['wok_ag_id'])) {
        $body .= '<a href="/production/work-orders/operate?agid=' . (int)$wo['wok_ag_id'] . '" class="btn" style="background:#115452;color:#fff;text-decoration:none"><i class="fa fa-cogs"></i> Consola Planta</a>';
    }
    $body .= '</div>';
    $body .= '</div>';

    // Barra de etapas activas en la genealogía
    $hasStage1 = ($po !== null || $supp !== null || ($roll !== null && ($roll['process_stage'] ?? '') === 'RAW') || $parentRoll !== null || !empty($tree['virgin_roll']));
    $hasStage2 = ($wo !== null || ($roll !== null && ($roll['process_stage'] ?? '') === 'PRINTED') || ($parentRoll !== null && ($parentRoll['process_stage'] ?? '') === 'PRINTED'));
    $hasStage3 = (!empty($childRolls) || ($roll !== null && ($roll['process_stage'] ?? '') === 'CUT'));
    $hasStage4 = ($wo !== null && !empty($wo['type_ant_title']) && stripos($wo['type_ant_title'], 'seri') !== false);
    $hasStage5 = (!empty($boxes) || $pallet !== null);
    $hasStage6 = (!empty($movs));

    $body .= '<div style="margin-top:15px;padding-top:15px;border-top:1px solid #e2e8f0;display:flex;gap:8px;flex-wrap:wrap;font-size:12px">';
    $body .= '<span style="padding:4px 10px;border-radius:4px;font-weight:bold;background:' . ($hasStage1 ? '#dcfce7;color:#166534' : '#f1f5f9;color:#94a3b8') . '"><i class="fa fa-check-circle"></i> 1. Recepción MP</span>';
    $body .= '<span style="padding:4px 10px;border-radius:4px;font-weight:bold;background:' . ($hasStage2 ? '#e0f2fe;color:#0369a1' : '#f1f5f9;color:#94a3b8') . '"><i class="fa fa-check-circle"></i> 2. Flexografía</span>';
    $body .= '<span style="padding:4px 10px;border-radius:4px;font-weight:bold;background:' . ($hasStage3 ? '#f3e8ff;color:#6b21a8' : '#f1f5f9;color:#94a3b8') . '"><i class="fa fa-check-circle"></i> 3. Rebobinadora</span>';
    $body .= '<span style="padding:4px 10px;border-radius:4px;font-weight:bold;background:' . ($hasStage4 ? '#fce7f3;color:#9d174d' : '#f1f5f9;color:#94a3b8') . '"><i class="fa fa-check-circle"></i> 4. Serigrafía</span>';
    $body .= '<span style="padding:4px 10px;border-radius:4px;font-weight:bold;background:' . ($hasStage5 ? '#fef3c7;color:#92400e' : '#f1f5f9;color:#94a3b8') . '"><i class="fa fa-check-circle"></i> 5. Selladora / Cajas</span>';
    $body .= '<span style="padding:4px 10px;border-radius:4px;font-weight:bold;background:' . ($hasStage6 ? '#e0e7ff;color:#3730a3' : '#f1f5f9;color:#94a3b8') . '"><i class="fa fa-check-circle"></i> 6. Despacho PT</span>';
    $body .= '</div>';
    $body .= '</div>';

    // =========================================================================
    // ETAPA 1: RECEPCIÓN Y MATERIA PRIMA (PROVEEDOR & BOBINA VIRGEN)
    // =========================================================================
    $virgin = $tree['virgin_roll'] ?? ($parentRoll && ($parentRoll['process_stage'] ?? '') === 'RAW' ? $parentRoll : ($parentRoll ?? ($roll && ($roll['process_stage'] ?? '') === 'RAW' ? $roll : null)));
    $body .= '<div class="card" style="margin-bottom:18px;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1;background:#fff">';
    $body .= '<div style="background:#10b981;color:#fff;padding:12px 18px;font-weight:bold;font-size:15px;display:flex;align-items:center;justify-content:space-between">';
    $body .= '<div><i class="fa fa-truck-loading"></i> ETAPA 1: Ingreso de Mercadería y Proveedor (Materia Prima)</div>';
    if ($virgin) {
        $body .= '<a href="/rolls/' . (int)$virgin['id'] . '/label" target="_blank" style="color:#fff;font-size:12px;background:rgba(0,0,0,0.2);padding:4px 10px;border-radius:4px;text-decoration:none"><i class="fa fa-print"></i> Etiqueta Recepción</a>';
    }
    $body .= '</div>';
    $body .= '<div style="padding:18px">';

    $body .= '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:16px">';
    $body .= '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px">';
    $body .= '<div style="font-size:11px;font-weight:bold;color:#64748b;text-transform:uppercase;margin-bottom:4px">Proveedor / Origen</div>';
    $body .= '<div style="font-size:14px;font-weight:bold;color:#1e293b">' . htmlspecialchars((string)($supp['name'] ?? 'Proveedor Extranjero / Nacional')) . '</div>';
    $body .= '<div style="font-size:12px;color:#475569">RUT: ' . htmlspecialchars((string)($supp['rut'] ?? '-')) . ' · País: ' . htmlspecialchars((string)($supp['country'] ?? 'Chile')) . '</div>';
    if ($po) {
        $body .= '<div style="margin-top:6px;font-size:12px;color:#0369a1;font-weight:bold"><i class="fa fa-file-invoice"></i> Orden de Compra: ' . htmlspecialchars($po['po_code']) . ' (' . htmlspecialchars($po['date']) . ')</div>';
    }
    $body .= '</div>';

    $body .= '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px">';
    $body .= '<div style="font-size:11px;font-weight:bold;color:#64748b;text-transform:uppercase;margin-bottom:4px">Bobina Virgen Recepcionada</div>';
    if ($virgin) {
        $body .= '<div style="font-size:14px;font-weight:bold;color:#1e293b">' . htmlspecialchars((string)$virgin['roll_code']) . ' <span style="font-size:11px;color:#10b981;font-weight:bold">[VIRGEN]</span></div>';
        $body .= '<div style="font-size:12px;color:#475569">SKU: ' . htmlspecialchars((string)($virgin['sku_code'] ?? '-')) . ' · ' . htmlspecialchars((string)($virgin['sku_desc'] ?? '')) . '</div>';
        $body .= '<div style="font-size:12px;color:#0f172a;margin-top:4px"><b>' . number_format((float)($virgin['weight_kg'] ?? 0), 2, ',', '.') . ' Kg</b> · ' . number_format((float)($virgin['meters'] ?? 0), 0, ',', '.') . ' m · ' . (int)($virgin['width_mm'] ?? 0) . ' mm</div>';
    } else {
        $body .= '<div style="font-size:13px;color:#94a3b8;font-style:italic">Bobina madre asignada directamente desde inventario central (Bodega 3000).</div>';
    }
    $body .= '</div>';
    $body .= '</div>';
    $body .= '</div>';
    $body .= '</div>';

    // =========================================================================
    // ETAPA 2: FLEXOGRAFÍA (IMPRESIÓN EN MÁQUINA)
    // =========================================================================
    $printedRoll = ($roll && ($roll['process_stage'] ?? '') === 'PRINTED') ? $roll : (($parentRoll && ($parentRoll['process_stage'] ?? '') === 'PRINTED') ? $parentRoll : null);
    $stage2IsSeri = ($wo !== null && stripos((string)($wo['type_ant_title'] ?? $wo['equipo_name'] ?? ''), 'seri') !== false);
    $stage2Title = $stage2IsSeri ? 'Serigrafía' : 'Flexografía';
    $body .= '<div class="card" style="margin-bottom:18px;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1;background:#fff">';
    $body .= '<div style="background:#0284c7;color:#fff;padding:12px 18px;font-weight:bold;font-size:15px;display:flex;align-items:center;justify-content:space-between">';
    $body .= '<div><i class="fa fa-print"></i> ETAPA 2: ' . htmlspecialchars($stage2Title) . ' (Tiraje de Impresión)</div>';
    if ($printedRoll) {
        $body .= '<a href="/rolls/' . (int)$printedRoll['id'] . '/label" target="_blank" style="color:#fff;font-size:12px;background:rgba(0,0,0,0.2);padding:4px 10px;border-radius:4px;text-decoration:none"><i class="fa fa-print"></i> Etiqueta Bobina Impresa</a>';
    }
    $body .= '</div>';
    $body .= '<div style="padding:18px">';
    $body .= unibagRollLoadsHtml((array)($tree['roll_loads'] ?? []));

    if ($wo) {
        $body .= '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:16px">';
        $body .= '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px">';
        $body .= '<div style="font-size:11px;font-weight:bold;color:#0369a1;text-transform:uppercase;margin-bottom:4px">Orden de Trabajo ERP</div>';
        $body .= '<div style="font-size:15px;font-weight:bold;color:#0c4a6e">OT #' . htmlspecialchars((string)$wo['prd_number']) . ' &mdash; Pedido #' . htmlspecialchars((string)($wo['req_number'] ?? '-')) . '</div>';
        $body .= '<div style="font-size:12px;color:#0284c7;font-weight:bold">' . htmlspecialchars((string)($wo['cust_name'] ?? 'Cliente')) . '</div>';
        $body .= '<div style="font-size:12px;color:#475569;margin-top:4px">Máquina: <b>' . htmlspecialchars((string)($wo['equipo_name'] ?? '-')) . '</b> · Operador: <b>' . htmlspecialchars((string)(($wo['wrk_firstname'] ?? '') . ' ' . ($wo['wrk_lastname'] ?? ''))) . '</b></div>';
        $body .= '</div>';

        $body .= '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px">';
        $body .= '<div style="font-size:11px;font-weight:bold;color:#0369a1;text-transform:uppercase;margin-bottom:4px">Bobina Semielaborada Resultante (ROLL-SE)</div>';
        if ($printedRoll) {
            $body .= '<div style="font-size:14px;font-weight:bold;color:#0369a1">' . htmlspecialchars((string)$printedRoll['roll_code']) . ' <span style="font-size:11px;color:#0284c7;font-weight:bold">[IMPRESA]</span></div>';
            $body .= '<div style="font-size:12px;color:#475569">Peso: <b>' . number_format((float)($printedRoll['weight_kg'] ?? 0), 2, ',', '.') . ' Kg</b> · Metros: <b>' . number_format((float)($printedRoll['meters'] ?? 0), 0, ',', '.') . ' m</b></div>';
            $body .= '<div style="font-size:12px;color:#0284c7;margin-top:4px">Estado: <b>' . htmlspecialchars((string)$printedRoll['status']) . '</b></div>';
        } else {
            $body .= '<div style="font-size:13px;color:#64748b;font-style:italic">Tiraje procesado en línea o transferido a rebobinado.</div>';
        }
        $body .= '</div>';
        $body .= '</div>';
    } else {
        $body .= '<div style="font-size:13px;color:#94a3b8;font-style:italic">No se registran eventos de ' . strtolower($stage2Title) . ' para este código específico.</div>';
    }
    $body .= '</div>';
    $body .= '</div>';

    // =========================================================================
    // ETAPA 3: REBOBINADORA (CORTE / FRACCIONAMIENTO A BOBINAS HIJAS)
    // =========================================================================
    $body .= '<div class="card" style="margin-bottom:18px;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1;background:#fff">';
    $body .= '<div style="background:#7c3aed;color:#fff;padding:12px 18px;font-weight:bold;font-size:15px;display:flex;align-items:center;justify-content:space-between">';
    $body .= '<div><i class="fa fa-cut"></i> ETAPA 3: Rebobinadora (Fraccionamiento a Bobinas Hijas)</div>';
    $body .= '<span style="font-size:12px;background:rgba(0,0,0,0.2);padding:4px 10px;border-radius:4px">' . count($childRolls) . ' bobinas generadas</span>';
    $body .= '</div>';
    $body .= '<div style="padding:18px">';

    if (!empty($childRolls)) {
        $body .= '<div style="overflow-x:auto">';
        $body .= '<table style="width:100%;border-collapse:collapse;font-size:13px">';
        $body .= '<thead><tr style="background:#f5f3ff;color:#5b21b6;text-align:left;border-bottom:2px solid #ddd6fe">';
        $body .= '<th style="padding:8px">Código Bobina Hija</th><th style="padding:8px">SKU / Destino (Cargado a)</th><th style="padding:8px">Ancho (mm)</th><th style="padding:8px">Largo (m)</th><th style="padding:8px">Peso (Kg)</th><th style="padding:8px">Estado</th><th style="padding:8px;text-align:center">Acción</th>';
        $body .= '</tr></thead><tbody>';
        foreach ($childRolls as $cr) {
            $isCur = ($roll && (int)$roll['id'] === (int)$cr['id']);
            $crDesc = (string)($cr['sku_desc'] ?? '');
            $skuHtml = '<b>' . htmlspecialchars((string)($cr['sku_code'] ?? '')) . '</b>';
            if (str_contains($crDesc, 'Cargada a SKU:')) {
                $parts = explode(' - Cargada a SKU: ', $crDesc, 2);
                $skuHtml .= '<br><span style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;padding:1px 6px;border-radius:4px;font-size:11px;font-weight:700">🎯 Cargada a SKU: ' . htmlspecialchars($parts[1]) . '</span>';
            } elseif ($crDesc !== '') {
                $skuHtml .= '<br><span style="font-size:11px;color:#64748b">' . htmlspecialchars($crDesc) . '</span>';
            }
            $body .= '<tr style="border-bottom:1px solid #e2e8f0;' . ($isCur ? 'background:#ecfdf5;font-weight:bold' : '') . '">';
            $body .= '<td style="padding:8px"><b>' . htmlspecialchars((string)$cr['roll_code']) . '</b>' . ($isCur ? ' <span style="color:#059669">[CONSULTADA]</span>' : '') . '</td>';
            $body .= '<td style="padding:8px">' . $skuHtml . '</td>';
            $body .= '<td style="padding:8px">' . ((int)($cr['width_mm'] ?? 0) > 0 ? (int)$cr['width_mm'] . ' mm' : '-') . '</td>';
            $body .= '<td style="padding:8px">' . number_format((float)($cr['meters'] ?? 0), 0, ',', '.') . ' m</td>';
            $body .= '<td style="padding:8px">' . number_format((float)($cr['weight_kg'] ?? 0), 2, ',', '.') . ' Kg</td>';
            $body .= '<td style="padding:8px"><span style="background:#ddd6fe;color:#5b21b6;padding:2px 8px;border-radius:10px;font-size:11px">' . htmlspecialchars((string)$cr['status']) . '</span></td>';
            $body .= '<td style="padding:8px;text-align:center"><a href="/rolls/' . (int)$cr['id'] . '/label" target="_blank" class="btn" style="background:#7c3aed;color:#fff;padding:3px 8px;font-size:11px;text-decoration:none"><i class="fa fa-print"></i> 100×70</a></td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table>';
        $body .= '</div>';
    } elseif ($roll && ($roll['process_stage'] ?? '') === 'CUT') {
        $body .= '<div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:6px;padding:12px">';
        $body .= '<div style="font-weight:bold;color:#5b21b6;margin-bottom:4px"><i class="fa fa-info-circle"></i> Bobina Hija Seleccionada: ' . htmlspecialchars((string)$roll['roll_code']) . '</div>';
        $body .= '<div style="font-size:12px;color:#475569">Ancho: <b>' . (int)($roll['width_mm'] ?? 0) . ' mm</b> · Metros: <b>' . number_format((float)($roll['meters'] ?? 0), 0, ',', '.') . ' m</b> · Peso: <b>' . number_format((float)($roll['weight_kg'] ?? 0), 2, ',', '.') . ' Kg</b></div>';
        if ($parentRoll) {
            $body .= '<div style="margin-top:6px;font-size:12px;color:#7c3aed">Bobina Madre de origen: <a href="/production/traceability?q=' . urlencode((string)$parentRoll['roll_code']) . '" style="color:#7c3aed;font-weight:bold;text-decoration:underline">' . htmlspecialchars((string)$parentRoll['roll_code']) . '</a></div>';
        }
        $body .= '</div>';
    } else {
        $body .= '<div style="font-size:13px;color:#94a3b8;font-style:italic">No se registran cortes de rebobinadora para esta bobina.</div>';
    }
    $body .= '</div>';
    $body .= '</div>';

    // =========================================================================
    // ETAPA 4: SERIGRAFÍA (SI APLICA EN LA OT)
    // =========================================================================
    if ($hasStage4) {
        $body .= '<div class="card" style="margin-bottom:18px;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1;background:#fff">';
        $body .= '<div style="background:#ec4899;color:#fff;padding:12px 18px;font-weight:bold;font-size:15px">';
        $body .= '<i class="fa fa-palette"></i> ETAPA 4: Serigrafía Pulpo / Plana (Estampado)';
        $body .= '</div>';
        $body .= '<div style="padding:18px;font-size:13px;color:#334155">';
        $body .= 'Proceso de estampado serigráfico registrado en OT #' . htmlspecialchars((string)($wo['prd_number'] ?? '')) . ' con control de pasadas Frente y Dorso.';
        $body .= '</div>';
        $body .= '</div>';
    }

    // =========================================================================
    // ETAPA 5: SELLADORA / CONFECCIÓN (CAJAS Y PALLETIZADO)
    // =========================================================================
    $body .= '<div class="card" style="margin-bottom:18px;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1;background:#fff">';
    $body .= '<div style="background:#f59e0b;color:#fff;padding:12px 18px;font-weight:bold;font-size:15px;display:flex;align-items:center;justify-content:space-between">';
    $body .= '<div><i class="fa fa-box-open"></i> ETAPA 5: Confección / Selladora (Cajas y Pallets PT)</div>';
    if ($pallet) {
        $body .= '<a href="/pallets/' . (int)$pallet['id'] . '/label" target="_blank" style="color:#fff;font-size:12px;background:rgba(0,0,0,0.2);padding:4px 10px;border-radius:4px;text-decoration:none"><i class="fa fa-print"></i> Etiqueta Pallet ' . htmlspecialchars($pallet['pallet_code']) . '</a>';
    }
    $body .= '</div>';
    $body .= '<div style="padding:18px">';

    if (!empty($boxes)) {
        $totalUnitsInBoxes = 0;
        foreach ($boxes as $b) {
            $totalUnitsInBoxes += (int)($b['units_qty'] ?? 0);
        }
        $body .= '<div style="margin-bottom:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">';
        $body .= '<span style="font-size:13px;background:#fef3c7;color:#92400e;padding:6px 12px;border-radius:6px;font-weight:bold"><i class="fa fa-boxes"></i> ' . count($boxes) . ' Cajas Generadas</span>';
        $body .= '<span style="font-size:13px;background:#fef3c7;color:#92400e;padding:6px 12px;border-radius:6px;font-weight:bold"><i class="fa fa-shopping-bag"></i> ' . number_format($totalUnitsInBoxes, 0, ',', '.') . ' Unidades Totales</span>';
        if ($pallet) {
            $body .= '<span style="font-size:13px;background:#8b5cf6;color:#fff;padding:6px 12px;border-radius:6px;font-weight:bold"><i class="fa fa-pallet"></i> Pallet: ' . htmlspecialchars($pallet['pallet_code']) . ' (' . (int)($pallet['box_count'] ?? count($boxes)) . ' cajas)</span>';
        }
        $body .= '</div>';

        $body .= '<div style="overflow-x:auto">';
        $body .= '<table style="width:100%;border-collapse:collapse;font-size:13px">';
        $body .= '<thead><tr style="background:#fef3c7;color:#92400e;text-align:left;border-bottom:2px solid #fde68a">';
        $body .= '<th style="padding:8px">Código de Caja</th><th style="padding:8px">Unidades</th><th style="padding:8px">Pallet</th><th style="padding:8px">Destino</th><th style="padding:8px">Operario</th><th style="padding:8px;text-align:center">Acción</th>';
        $body .= '</tr></thead><tbody>';
        foreach ($boxes as $b) {
            $isCurBox = ($tree['box'] && (int)$tree['box']['id'] === (int)$b['id']);
            $body .= '<tr style="border-bottom:1px solid #e2e8f0;' . ($isCurBox ? 'background:#fef9c3;font-weight:bold' : '') . '">';
            $body .= '<td style="padding:8px"><b>' . htmlspecialchars((string)$b['box_code']) . '</b>' . ($isCurBox ? ' <span style="color:#b45309">[CONSULTADA]</span>' : '') . '</td>';
            $body .= '<td style="padding:8px">' . number_format((float)($b['units_qty'] ?? 0), 0, ',', '.') . ' uds</td>';
            $body .= '<td style="padding:8px">' . htmlspecialchars((string)($pallet['pallet_code'] ?? ($b['pallet_id'] ? 'PAL-' . $b['pallet_id'] : '-'))) . '</td>';
            $body .= '<td style="padding:8px">' . htmlspecialchars((string)($b['destination_mode'] ?? 'STOCK')) . '</td>';
            $body .= '<td style="padding:8px">' . htmlspecialchars((string)($b['operator_name'] ?? '-')) . '</td>';
            $body .= '<td style="padding:8px;text-align:center"><a href="/boxes/' . (int)$b['id'] . '/label" target="_blank" class="btn" style="background:#f59e0b;color:#fff;padding:3px 8px;font-size:11px;text-decoration:none"><i class="fa fa-print"></i> 100×70</a></td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table>';
        $body .= '</div>';
    } else {
        $body .= '<div style="font-size:13px;color:#94a3b8;font-style:italic">Esta orden de trabajo o bobina aún no tiene cajas de confección generadas.</div>';
    }
    $body .= '</div>';
    $body .= '</div>';

    // =========================================================================
    // ETAPA 6: BODEGA PT & HISTORIAL DE MOVIMIENTOS Y DESPACHO
    // =========================================================================
    $body .= '<div class="card" style="margin-bottom:18px;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1;background:#fff">';
    $body .= '<div style="background:#4f46e5;color:#fff;padding:12px 18px;font-weight:bold;font-size:15px">';
    $body .= '<i class="fa fa-dolly"></i> ETAPA 6: Bodega de Producto Terminado & Despacho';
    $body .= '</div>';
    $body .= '<div style="padding:18px">';

    if (!empty($movs)) {
        $body .= '<div style="overflow-x:auto">';
        $body .= '<table style="width:100%;border-collapse:collapse;font-size:13px">';
        $body .= '<thead><tr style="background:#eef2ff;color:#3730a3;text-align:left;border-bottom:2px solid #c7d2fe">';
        $body .= '<th style="padding:8px">Fecha</th><th style="padding:8px">Tipo de Movimiento</th><th style="padding:8px">Entidad</th><th style="padding:8px">Detalle / Destino</th>';
        $body .= '</tr></thead><tbody>';
        foreach ($movs as $mv) {
            $pld = !empty($mv['payload']) ? json_decode((string)$mv['payload'], true) : [];
            $body .= '<tr style="border-bottom:1px solid #e2e8f0">';
            $body .= '<td style="padding:8px">' . htmlspecialchars((string)($mv['created_at'] ?? '-')) . '</td>';
            $body .= '<td style="padding:8px"><b style="color:#4f46e5">' . htmlspecialchars((string)($mv['type'] ?? $mv['movement_type'] ?? 'TRASLADO')) . '</b></td>';
            $body .= '<td style="padding:8px">' . htmlspecialchars((string)($mv['entity_type'] ?? 'ITEM')) . ' #' . (int)($mv['entity_id'] ?? 0) . '</td>';
            $body .= '<td style="padding:8px">' . htmlspecialchars((string)($pld['comments'] ?? ($pld['traceability_code'] ?? '-'))) . '</td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table>';
        $body .= '</div>';
    } else {
        $body .= '<div style="font-size:13px;color:#64748b;line-height:1.6">';
        $body .= 'El producto se encuentra registrado en bodega de planta y disponible para consolidación o despacho final al cliente.';
        $body .= '</div>';
    }
    $body .= '</div>';
    $body .= '</div>';

    renderProdwrkShell('Trazabilidad ' . htmlspecialchars($query), $body, 6, $operatorName, $plantaName);


