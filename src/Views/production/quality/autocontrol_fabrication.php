<?php
    $typeId = (int)($machine['equipo_type_id'] ?? 0);
    $machineName = mb_strtolower((string)($machine['equipo_name'] ?? '') . ' ' . (string)($machine['type_ant_title'] ?? ''));
    $isSealing = $typeId === 8 || str_contains($machineName, 'sell');
    $isSerigraphy = in_array($typeId, [11, 22], true) || (int)($machine['equipo_prod_isprinter_seri'] ?? 0) === 1;
    $isRebo = $typeId === 12;
    $fields = [
        'N° O.T.' => $agenda['prd_number'] ?? '',
        'Cliente' => $agenda['cust_name'] ?? '',
        'N° C.C.' => $agenda['req_number'] ?? '',
        'Producto' => $agenda['item_title'] ?? '',
        'Materialidad' => $agenda['fab_type'] ?? '',
        'Color de tela' => $agenda['fabric_color'] ?? '-',
        'Gramaje' => $agenda['fab_mat_gramms'] ?? '-',
        'Ancho' => $agenda['fab_med_width'] ?? '-',
        'Alto (Tiro)' => $transcomInfos['alto_tiro'] ?? $agenda['fab_med_height'] ?? '-',
        'Alto (Retiro)' => $transcomInfos['alto_retiro'] ?? $agenda['fab_med_height'] ?? '-',
        'Fuelle' => $agenda['fab_med_fuelle'] ?? '-',
    ];
    if ($isSealing) {
        $fields += [
            'Corte de Bolsa' => number_format($cutMeters, 4, ',', '.') . ' m',
            'Doblez superior' => $transcomInfos['doblez_superior'] ?? '3',
            'Dado de Manillas' => $agenda['req_infoaddprd_dado_manillas'] ?? '-',
            'Cabezal' => $agenda['req_infoaddprd_cabezal_act'] ?? '-',
            'Código' => $agenda['item_sellprice_barcodenumber'] ?? '-',
            'Color Manillas' => $agenda['manilla_color'] ?? '-',
            'Medida Manillas' => $agenda['fab_manilla_length'] ?? '-',
        ];
    }
    if ($isSerigraphy) {
        $fields += [
            'Impresión' => $agenda['fab_printtype'] ?? '-',
            'Imágenes por bastidor' => $agenda['req_operador_bastidoramt'] ?? '-',
            'Dispositivo' => $agenda['fab_mat_dispositivo'] ?? '-',
        ];
        for ($colorIndex = 1; $colorIndex <= 10; $colorIndex++) {
            if ((int)($agenda["fab_print_colors_front_{$colorIndex}"] ?? 0) > 0
                || (int)($agenda["fab_print_colors_back_{$colorIndex}"] ?? 0) > 0) {
                $fields["Color {$colorIndex}"] = $agenda["fab_print_colordesc_{$colorIndex}"] ?? '-';
            }
        }
    }
    echo '<div style="background:white;border:1px solid #ccc;border-radius:4px;overflow:hidden;margin-bottom:12px">';
    echo '<div class="unibag-collapse-header" onclick="toggleCollapseSection(\'sec_autoctrl_machine_fab\', \'icon_autoctrl_machine_fab\')">'
        . '<span>Información de Fabricación</span><span id="icon_autoctrl_machine_fab">Expandir / Minimizar</span></div>';
    echo '<div id="sec_autoctrl_machine_fab"><table width="100%" cellpadding="6" cellspacing="0">';
    foreach ($fields as $label => $value) {
        echo '<tr><td class="tdleft" width="180">' . htmlspecialchars($label) . '</td><td class="tdnrm">'
            . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
    echo renderDesignAndAttachmentsHtml($agenda, '', '', 1, 1, 'Imagen Diseño');
    echo '</table>';
    renderMachineFabricationExtras($agenda, !$isSerigraphy && !$isRebo);
    echo '</div></div>';

