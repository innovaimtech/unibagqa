<?php
    $orders = (new ProductionService(Db::erpPdo()))->attachWorkOrderFabricationDetails($orders);
    $escape = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $headers = ['N° C.C.', 'N° O.T.', 'Cliente', 'Diseño', 'Material'];
    if ($process === 'cys') $headers[] = 'Manillas';
    array_push($headers, 'Producto', 'Medidas');
    if ($process === 'pulpo') $headers[] = 'Colores';
    if ($process === 'embalaje') array_push($headers, 'Embalaje', 'Und. Por Caja');
    array_push($headers, 'Cantidad programada', 'Cantidad producida', 'Falta producir', 'Imagen', 'Opciones');
    // Totales por CC/OT (misma lógica que dentro de la OT): programado = suma de entregas (prod_amtplan) o cantidad de agenda;
    // producido = todo lo producido de la CC en agendas del mismo tipo de máquina, nunca menos que lo de esta agenda.
    $mtAgIds = [];
    $mtPrdIds = [];
    foreach ($orders as $o) {
        $aid = (int)($o['ag_id'] ?? 0);
        if ($aid > 0) $mtAgIds[$aid] = $aid;
        $pid = (int)($o['prdid'] ?? $o['prd_id'] ?? 0);
        if ($pid > 0) $mtPrdIds[$pid] = $pid;
    }
    $mtPhase = $process === 'embalaje' ? 1 : 0;
    $evtFilter = "t3.evt_type = 'prod' AND t3.evt_status > 0 AND t3.overrideembalaje_act = " . (int)$mtPhase
        . " AND (t3.evt_comments IS NULL OR (t3.evt_comments NOT LIKE '%Bobina Semielaborada%' AND t3.evt_comments NOT LIKE '%ROLL-SE-%'))";
    $mtPlanByAg = [];
    $mtProdByAg = [];
    $mtPlanByPrd = [];
    $mtProdByPrd = [];
    $mtQuery = static function (string $sql): array {
        try {
            $q = Db::erpPdo()->query($sql);
            return $q ? $q->fetchAll(PDO::FETCH_KEY_PAIR) : [];
        } catch (Throwable) {
            return [];
        }
    };
    if ($mtAgIds !== []) {
        $in = implode(',', array_map('intval', $mtAgIds));
        $mtPlanByAg = $mtQuery("SELECT a.id, SUM(p.prodplan_amt) FROM prod_agenda a
            INNER JOIN prod_amtplan p ON p.prodplan_prdid = a.ag_prdid WHERE a.id IN ($in) GROUP BY a.id");
        $mtProdByAg = $mtQuery("SELECT a.id, COALESCE(SUM(t3.evt_amount),0) FROM prod_agenda a
            INNER JOIN prod_agenda pa ON pa.ag_prdid = a.ag_prdid AND pa.ag_equipotype_id = a.ag_equipotype_id
            INNER JOIN prod_worker_ot t2 ON t2.wok_ag_id = pa.id
            INNER JOIN prod_worker_ot_events t3 ON t3.evt_prod_worker_otid = t2.id
            WHERE a.id IN ($in) AND $evtFilter GROUP BY a.id");
    }
    if ($mtPrdIds !== []) {
        $in = implode(',', array_map('intval', $mtPrdIds));
        $mtPlanByPrd = $mtQuery("SELECT prodplan_prdid, SUM(prodplan_amt) FROM prod_amtplan WHERE prodplan_prdid IN ($in) GROUP BY prodplan_prdid");
        $mtProdByPrd = $mtQuery("SELECT pa.ag_prdid, COALESCE(SUM(t3.evt_amount),0) FROM prod_agenda pa
            INNER JOIN prod_worker_ot t2 ON t2.wok_ag_id = pa.id
            INNER JOIN prod_worker_ot_events t3 ON t3.evt_prod_worker_otid = t2.id
            WHERE pa.ag_prdid IN ($in) AND $evtFilter GROUP BY pa.ag_prdid");
    }
    echo '<div style="overflow-x:auto"><table width="100%" cellpadding="8" cellspacing="0" style="min-width:1300px">';
    echo '<thead><tr>';
    foreach ($headers as $header) {
        echo '<th class="tdheader" style="text-align:left">' . $escape($header) . '</th>';
    }
    echo '</tr></thead><tbody>';
    if ($orders === []) echo '<tr><td class="tdnrm" colspan="' . count($headers) . '" style="text-align:center;padding:25px">No hay OTs para mostrar.</td></tr>';
    foreach ($orders as $order) {
        $agId = (int)($order['ag_id'] ?? 0);
        $prdId = (int)($order['prd_id'] ?? 0);
        $design = trim((string)($order['fab_design_name'] ?? '')) ?: (string)($order['item_title'] ?? '-');
        $height = $order['alto_tiro'] ?? $order['fab_med_height'] ?? '-';
        $backHeight = $order['alto_retiro'] ?? $order['fab_med_height'] ?? '-';
        $dimensions = (string)($order['ancho'] ?? $order['fab_med_width'] ?? '-') . ' x ' . $height . ' x ' . (string)($order['fab_med_fuelle'] ?? 0) . ' cm';
        $material = $escape($order['fab_type'] ?? '-') . '<br><b>' . $escape($order['fabric_color'] ?? '-') . '</b>'
            . '<br><small>' . $escape($order['req_operador_tela_width'] ?? '-') . ' cm · ' . $escape($order['fab_mat_gramms'] ?? '-') . ' gr</small>';
        $handles = '<b>' . $escape($order['manilla_color'] ?? '-') . '</b><br><small>Ancho: ' . $escape($order['ancho_manillas'] ?? '-')
            . ' cm<br>Largo: ' . $escape($order['fab_manilla_length'] ?? '-') . ' cm</small>';
        echo '<tr onmouseover="this.style.backgroundColor=\'#F0F7F6\'" onmouseout="this.style.backgroundColor=\'transparent\'">';
        foreach ([$order['req_number'] ?? '-', $order['prd_number'] ?? '-', $order['cust_name'] ?? '-'] as $value) {
            echo '<td class="tdnrm"><b>' . $escape($value) . '</b></td>';
        }
        echo '<td class="tdnrm" style="max-width:220px;font-size:12px;overflow-wrap:anywhere">' . $escape($design) . '</td>';
        echo '<td class="tdnrm">' . $material . '</td>';
        if ($process === 'cys') echo '<td class="tdnrm">' . $handles . '</td>';
        echo '<td class="tdnrm">' . $escape($order['bag_type'] ?? '-') . '</td>';
        echo '<td class="tdnrm" style="white-space:nowrap"><b>' . $escape($dimensions) . '</b><br><small>Retiro: ' . $escape($backHeight)
            . ' cm<br>Doblez: ' . $escape($order['doblez_superior'] ?? '-') . ' cm</small></td>';
        if ($process === 'pulpo') {
            echo '<td class="tdnrm">';
            $colors = [];
            for ($i = 1; $i <= 6; $i++) {
                $color = trim((string)($order['fab_print_colordesc_' . $i] ?? ''));
                if ($color !== '') $colors[] = '<div style="font-size:11px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:3px;padding:2px 5px;margin-bottom:2px"><b>' . $i . ':</b> ' . $escape($color) . '</div>';
            }
            echo ($colors === [] ? '<span style="color:#94a3b8">Sin colores</span>' : implode('', $colors)) . '</td>';
        }
        if ($process === 'embalaje') {
            echo '<td class="tdnrm">' . $escape($order['req_embalaje_medidas_caja'] ?? '-') . '</td>';
            echo '<td class="tdnrm" style="text-align:right"><b>' . number_format((float)($order['req_embalaje_bolsas_por_caja_amt'] ?? 0), 0, ',', '.') . '</b></td>';
        }
        $rowPrd = (int)($order['prdid'] ?? $order['prd_id'] ?? 0);
        $rowPlan = (float)($agId > 0 ? ($mtPlanByAg[$agId] ?? 0) : 0);
        if ($rowPlan <= 0) $rowPlan = (float)($mtPlanByPrd[$rowPrd] ?? 0);
        $rowProgramada = $rowPlan > 0 ? $rowPlan : (float)($order['ag_amount'] ?? $order['item_amount'] ?? 0);
        $rowProducida = max(
            (float)($order['total_produced'] ?? 0),
            (float)($agId > 0 ? ($mtProdByAg[$agId] ?? 0) : ($mtProdByPrd[$rowPrd] ?? 0))
        );
        $rowFalta = max(0.0, $rowProgramada - $rowProducida);
        echo '<td class="tdnrm" style="text-align:right"><b>' . number_format($rowProgramada, 0, ',', '.') . '</b></td>';
        echo '<td class="tdnrm" style="text-align:right"><b>' . number_format($rowProducida, 0, ',', '.') . '</b></td>';
        echo '<td class="tdnrm" style="text-align:right"><b style="color:' . ($rowFalta > 0 ? '#B45309' : '#047857') . '">' . number_format($rowFalta, 0, ',', '.') . '</b></td>';
        $hash = trim((string)($order['fab_design_imagehash'] ?? ''));
        if ($hash === '' || $hash === 'dummy') $hash = trim((string)($order['item_img_hash'] ?? ''));
        echo '<td class="tdnrm" style="text-align:center">';
        if ($hash !== '' && $hash !== 'dummy') {
            $url = '/production/design-image?folder=docs.order&hash=' . rawurlencode($hash);
            $args = [$url, (string)($order['prd_number'] ?? ''), (string)($order['cust_name'] ?? ''), $design, $dimensions];
            $jsArgs = implode(',', array_map(static fn($v) => json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), $args));
            echo '<button type="button" style="border:0;background:transparent;cursor:pointer" onclick="' . $escape('openDesignModal(' . $jsArgs . ')') . '" title="Ver diseño">'
                . '<img src="' . $escape($url) . '" alt="Diseño" style="width:36px;height:36px;object-fit:contain;border:1px solid #cbd5e1;border-radius:4px"></button>';
        } else {
            echo '<span style="color:#94a3b8;font-size:12px">Sin foto</span>';
        }
        echo '</td><td class="tdnrm"><div style="display:flex;gap:6px;flex-wrap:wrap">';
        if ($view === 'active') {
            echo !empty($order['locked_by'])
                ? productionLockedOperateBadge((string)$order['locked_by'])
                : '<a class="btngreen" style="padding:6px 12px;text-decoration:none" href="/production/work-orders/operate?agid=' . $agId . '">Operar OT</a>';
        } else {
            $unscheduled = $view === 'unscheduled';
            echo '<form method="post" action="/production/work-orders/' . ($unscheduled ? 'init-unscheduled' : 'init') . '" style="margin:0">'
                . '<input type="hidden" name="_csrf" value="' . $escape(csrfToken()) . '">'
                . '<input type="hidden" name="' . ($unscheduled ? 'prd_id' : 'ag_id') . '" value="' . ($unscheduled ? $prdId : $agId) . '">'
                . '<button type="submit" class="btngreen" style="padding:6px 12px"' . ($unscheduled ? ' onclick="return confirm(\'¿Confirmas iniciar esta OT fuera de planificación?\')"' : '') . '>Iniciar OT</button></form>';
        }
        echo '<a class="btnblue" style="padding:6px 10px;text-decoration:none;font-size:11px" href="/production/materials/request?'
            . ($view === 'unscheduled' ? 'prd_id=' . $prdId : 'agid=' . $agId) . '">Materiales</a></div></td></tr>';
    }
    echo '</tbody></table></div>';

