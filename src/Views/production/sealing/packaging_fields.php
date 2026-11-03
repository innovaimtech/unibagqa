<?php
    $escape = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $fields = [
        'req_embalaje_medidas_caja' => 'Medida de caja',
        'req_embalaje_bolsas_por_caja_amt' => 'Unidades por caja',
        'req_embalaje_cajas_por_pallet_amt' => 'Cajas por pallet',
        'req_embalaje_pallets_completos_amt' => 'Pallets completos',
        'req_embalaje_palletcajas_incompletos_amt' => 'Cajas en pallet incompleto',
        'req_embalaje_cajas_completas_amt' => 'Cajas completas',
        'req_embalaje_caja_final' => 'Unidades en caja final',
        'req_embalaje_bolsas_sobrantes_amt' => 'Unidades sobrantes',
        'req_embalaje_showroom' => 'Unidades para showroom',
    ];

    $savedCode = (string)($saved['req_codigo_cierre'] ?? '');
    // Cinta según la caja: SIN logo (genérica, "Logotipo empresa" = No) = INS0038; CON logo (impresa) = INS0073.
    $boxMeasuresUpper = mb_strtoupper((string)($saved['req_embalaje_medidas_caja'] ?? $agenda['req_embalaje_medidas_caja'] ?? ''));
    $boxWithoutLogo = strtoupper(trim((string)($agenda['req_caja_impresa'] ?? ''))) === 'NO'
        || str_contains($boxMeasuresUpper, 'SIN LOGO') || str_contains($boxMeasuresUpper, 'SIN IMPRESION') || str_contains($boxMeasuresUpper, 'SIN IMPRESIÓN');
    $savedTape = $boxWithoutLogo ? 'INS0038' : 'INS0073';
    // Regla solicitada: El Cierre Mixto lo selecciona el operador, no debe estar abierto en todo momento por defecto.
    // Solo se activa inicialmente si ya fue guardado previamente con código 50.
    $isMixtoSelected = ($savedCode === '50');

    // Preparar lista de bodegas
    $warehouses = [];
    try {
        // Destino del embalaje: solo las bodegas 400, 500, 700 y 1000 del ERP.
        $warehouses = array_values(array_filter(
            Db::erpPdo()->query('SELECT id, st_name FROM company_shops_storehouses WHERE st_status = 1 ORDER BY st_name')->fetchAll(PDO::FETCH_ASSOC) ?: [],
            static fn(array $w): bool => WarehouseVisibility::isPackagingDestination((string)$w['st_name'])
        ));
    } catch (Throwable) {}

    echo '<style>
        .pack-mode-container {
            background: #F8FAFC;
            border: 1.5px solid #E2E8F0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(15,23,42,0.03);
        }
        .pack-mode-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        .pack-mode-title {
            font-size: 13.5px;
            font-weight: 800;
            color: #0F766E;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .pack-mode-hint {
            font-size: 12px;
            color: #64748B;
        }
        .pack-mode-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        @media(max-width: 650px) {
            .pack-mode-options { grid-template-columns: 1fr; }
        }
        .pack-mode-card {
            border: 2px solid #CBD5E1;
            background: #FFFFFF;
            border-radius: 8px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            text-align: left;
            transition: all 0.15s ease;
            position: relative;
        }
        .pack-mode-card:hover {
            border-color: #0D9488;
            background: #F0FDFA;
        }
        .pack-mode-card.active {
            border-color: #0F766E;
            background: #F0FDFA;
            box-shadow: 0 0 0 1px #0F766E, 0 2px 6px rgba(15,118,110,0.12);
        }
        .pack-mode-icon {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            background: #E2E8F0;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
            transition: all 0.15s ease;
        }
        .pack-mode-card.active .pack-mode-icon {
            background: #0F766E;
            color: #FFFFFF;
        }
        .pack-mode-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex: 1;
        }
        .pack-mode-name {
            font-size: 13.5px;
            font-weight: 800;
            color: #1E293B;
        }
        .pack-mode-card.active .pack-mode-name {
            color: #0F766E;
        }
        .pack-mode-desc {
            font-size: 11.5px;
            color: #64748B;
        }
        .pack-mode-check {
            font-size: 18px;
            color: #CBD5E1;
            flex-shrink: 0;
        }
        .pack-mode-card.active .pack-mode-check {
            color: #0F766E;
        }

        /* Grid principal de embalaje */
        .pack-close-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(320px, 1fr);
            gap: 20px;
            align-items: start;
            margin-bottom: 20px;
        }
        @media(max-width: 950px) {
            .pack-close-grid { grid-template-columns: 1fr; }
        }
        .pack-close-block {
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            overflow: hidden;
            background: #FFFFFF;
            box-shadow: 0 1px 3px rgba(15,23,42,0.04);
        }
        .pack-close-title {
            background: #0F766E;
            color: #FFFFFF;
            font-size: 13.5px;
            font-weight: 800;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.3px;
        }
        .pack-close-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .pack-close-table th, .pack-close-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #E2E8F0;
            text-align: left;
            vertical-align: middle;
        }
        .pack-close-table th {
            background: #F1F5F9;
            color: #334155;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .pack-close-table input {
            width: 100% !important;
            box-sizing: border-box;
            height: 34px;
            padding: 4px 8px;
            font-size: 13px;
            font-weight: 600;
            border: 1.5px solid #CBD5E1;
            border-radius: 6px;
            background: #FFFFFF;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .pack-close-table input:focus {
            border-color: #0F766E;
            outline: none;
            box-shadow: 0 0 0 3px rgba(15,118,110,0.15);
        }
        .pack-close-total {
            padding: 14px 16px;
            background: #F0FDF4;
            border-bottom: 1.5px solid #BBF7D0;
        }
        .pack-close-total input {
            width: 100% !important;
            box-sizing: border-box;
            font-size: 26px;
            font-weight: 800;
            height: 44px;
            background: #FFFFFF;
            color: #15803D;
            border: 1.5px solid #86EFAC;
            border-radius: 6px;
            padding: 2px 12px;
            text-align: right;
            margin-top: 6px;
        }
        .pack-close-auth {
            padding: 16px;
            display: grid;
            gap: 12px;
        }
        .pack-close-auth label {
            display: grid;
            gap: 5px;
            font-weight: 700;
            font-size: 12.5px;
            color: #334155;
        }
        .pack-close-auth select, .pack-close-auth input {
            width: 100% !important;
            box-sizing: border-box;
            height: 34px;
            border: 1.5px solid #CBD5E1;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 13px;
            background: #FFFFFF;
        }
        .pack-close-auth select:focus, .pack-close-auth input:focus {
            border-color: #0F766E;
            outline: none;
            box-shadow: 0 0 0 3px rgba(15,118,110,0.15);
        }
        .pack-close-auth textarea {
            width: 100% !important;
            box-sizing: border-box;
            height: 65px;
            min-height: 55px;
            max-height: 140px;
            resize: vertical;
            border: 1.5px solid #CBD5E1;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 13px;
            font-family: inherit;
        }
        .pack-close-auth textarea:focus {
            border-color: #0F766E;
            outline: none;
            box-shadow: 0 0 0 3px rgba(15,118,110,0.15);
        }
        .pack-close-credentials {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* Tabla Pallet Mixto */
        .pack-mixto-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            min-width: 950px;
        }
        .pack-mixto-table th, .pack-mixto-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #E2E8F0;
            text-align: left;
            vertical-align: middle;
        }
        .pack-mixto-table th {
            background: #E2E8F0;
            color: #1E293B;
            font-weight: 700;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .pack-mixto-table input {
            box-sizing: border-box;
            height: 32px;
            border: 1.5px solid #CBD5E1;
            border-radius: 5px;
            padding: 2px 6px;
            font-size: 12.5px;
        }
        .pack-mixto-table input:focus {
            border-color: #0D9488;
            outline: none;
            box-shadow: 0 0 0 2px rgba(13,148,136,0.15);
        }

        /* Cierre mixto en tablet: cada CC pasa a tarjeta para que los campos no se sobrepongan */
        #panel_cierre_mixto { container-type: inline-size; }
        @container (max-width: 1000px) {
        #panel_cierre_mixto .pack-mixto-table { min-width: 0; width: 100%; display: block; }
        #panel_cierre_mixto .pack-mixto-table thead { display: none; }
        #panel_cierre_mixto .pack-mixto-table tbody { display: block; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] {
            display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px 12px;
            padding: 14px; margin: 0 0 12px; border: 1px solid #CBD5E1; border-radius: 10px; background: #FFFFFF;
        }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td { display: block; border: 0; padding: 0; min-width: 0; text-align: left !important; white-space: normal !important; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(1) { grid-column: 1 / 4; font-size: 15px; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(2) { grid-column: 4; grid-row: 1 / 3; justify-self: end; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(3) { grid-column: 1 / 4; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(9) { grid-column: 1 / 4; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(10) { grid-column: 4; align-self: end; justify-self: end; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(n+4):nth-child(-n+9)::before {
            display: block; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] input,
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] select {
            width: 100% !important; min-width: 0; height: 44px !important; font-size: 16px !important; box-sizing: border-box;
        }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] .btnred { min-height: 44px; min-width: 44px; }
        #panel_cierre_mixto .pack-mixto-table tfoot, #panel_cierre_mixto .pack-mixto-table tfoot tr { display: block; }
        #panel_cierre_mixto .pack-mixto-table tfoot tr { padding: 12px 14px; border: 2px solid #CBD5E1; border-radius: 10px; }
        #panel_cierre_mixto .pack-mixto-table tfoot td { display: block; border: 0; padding: 3px 0; text-align: left !important; }
        #panel_cierre_mixto .pack-mixto-table tfoot td:last-child { display: none; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(4)::before { content: "Cajas"; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(5)::before { content: "Unid. x caja"; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(6)::before { content: "Caja final"; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(7)::before { content: "Sobrantes"; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(8)::before { content: "Total bolsas"; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(9)::before { content: "Bodega origen"; }
        }
        @supports not (container-type: inline-size) {
            @media (max-width: 1450px) {
        #panel_cierre_mixto .pack-mixto-table { min-width: 0; width: 100%; display: block; }
        #panel_cierre_mixto .pack-mixto-table thead { display: none; }
        #panel_cierre_mixto .pack-mixto-table tbody { display: block; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] {
            display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px 12px;
            padding: 14px; margin: 0 0 12px; border: 1px solid #CBD5E1; border-radius: 10px; background: #FFFFFF;
        }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td { display: block; border: 0; padding: 0; min-width: 0; text-align: left !important; white-space: normal !important; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(1) { grid-column: 1 / 4; font-size: 15px; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(2) { grid-column: 4; grid-row: 1 / 3; justify-self: end; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(3) { grid-column: 1 / 4; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(9) { grid-column: 1 / 4; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(10) { grid-column: 4; align-self: end; justify-self: end; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(n+4):nth-child(-n+9)::before {
            display: block; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] input,
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] select {
            width: 100% !important; min-width: 0; height: 44px !important; font-size: 16px !important; box-sizing: border-box;
        }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] .btnred { min-height: 44px; min-width: 44px; }
        #panel_cierre_mixto .pack-mixto-table tfoot, #panel_cierre_mixto .pack-mixto-table tfoot tr { display: block; }
        #panel_cierre_mixto .pack-mixto-table tfoot tr { padding: 12px 14px; border: 2px solid #CBD5E1; border-radius: 10px; }
        #panel_cierre_mixto .pack-mixto-table tfoot td { display: block; border: 0; padding: 3px 0; text-align: left !important; }
        #panel_cierre_mixto .pack-mixto-table tfoot td:last-child { display: none; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(4)::before { content: "Cajas"; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(5)::before { content: "Unid. x caja"; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(6)::before { content: "Caja final"; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(7)::before { content: "Sobrantes"; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(8)::before { content: "Total bolsas"; }
        #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(9)::before { content: "Bodega origen"; }
            }
        }
        @media (max-width: 1280px) {
            .pack-close-grid { grid-template-columns: minmax(0, 1fr); }
        }
        @media (max-width: 640px) {
            #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(1),
            #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(3),
            #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(9) { grid-column: 1 / 3; }
            #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(2) { grid-column: 2; grid-row: auto; }
            #panel_cierre_mixto .pack-mixto-table tr[id^="mixed_row_"] > td:nth-child(10) { grid-column: 2; }
        }
    </style>';

    // 1. Selector de Modalidad (Simple y claro para los operadores)
    echo '<div class="pack-mode-container">
        <div class="pack-mode-header">
            <span class="pack-mode-title"><i class="fa fa-layer-group"></i> Modalidad de Embalaje / Pallet:</span>
            <span class="pack-mode-hint">Haga clic para alternar fácilmente entre Cierre Estándar o Cierre Mixto</span>
        </div>
        <div class="pack-mode-options">
            <button type="button" class="pack-mode-card ' . (!$isMixtoSelected ? 'active' : '') . '" id="btn_mode_standard" onclick="setPackagingMode(\'standard\')">
                <div class="pack-mode-icon"><i class="fa fa-box"></i></div>
                <div class="pack-mode-info">
                    <span class="pack-mode-name">1. Pallet Estándar (Monoproducto)</span>
                    <span class="pack-mode-desc">Embalaje y pallet exclusivo para este pedido / CC</span>
                </div>
                <div class="pack-mode-check"><i class="fa fa-check-circle"></i></div>
            </button>
            <button type="button" class="pack-mode-card ' . ($isMixtoSelected ? 'active' : '') . '" id="btn_mode_mixed" onclick="setPackagingMode(\'mixed\')">
                <div class="pack-mode-icon"><i class="fa fa-boxes"></i></div>
                <div class="pack-mode-info">
                    <span class="pack-mode-name">2. Pallet Mixto (Múltiples CCs / Diseños)</span>
                    <span class="pack-mode-desc">Consolidar y desglosar varias órdenes combinadas en el pallet</span>
                </div>
                <div class="pack-mode-check"><i class="fa fa-check-circle"></i></div>
            </button>
        </div>
    </div>';

    // 2. Grid de dos columnas: Cantidades y Resumen
    echo '<div class="pack-close-grid">
        <section class="pack-close-block">
            <div class="pack-close-title"><i class="fa fa-cubes"></i> Cantidades de Embalaje</div>
            <table class="pack-close-table">
                <colgroup>
                    <col style="width:44%">
                    <col style="width:26%">
                    <col style="width:30%">
                </colgroup>
                <thead>
                    <tr><th>Dato</th><th>Pedido</th><th>Registrado</th></tr>
                </thead>
                <tbody>';

    foreach ($fields as $field => $label) {
        $isText = $field === 'req_embalaje_medidas_caja';
        $value = $saved[$field] ?? (in_array($field, ['req_embalaje_medidas_caja', 'req_embalaje_bolsas_por_caja_amt', 'req_embalaje_cajas_por_pallet_amt'], true) ? ($agenda[$field] ?? ($isText ? '' : 0)) : 0);
        echo '<tr><td><b>' . $escape($label) . '</b></td><td style="color:#64748B">' . $escape($agenda[$field] ?? '-') . '</td><td>'
            . '<input class="inptxt" name="' . $field . '" id="' . $field . '" type="' . ($isText ? 'text' : 'number') . '" '
            . ($isText ? 'maxlength="255"' : 'min="0" step="1"') . ' value="' . $escape($value) . '" required oninput="calculatePackagingUnits(this.form, this.name)"></td></tr>';
    }

    echo '      </tbody>
            </table>
        </section>

        <section class="pack-close-block">
            <div class="pack-close-title"><i class="fa fa-clipboard-check"></i> Resumen y Autorización</div>
            <div class="pack-close-total">
                <div style="display:flex;align-items:center;justify-content:space-between">
                    <span style="font-size:12px;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:0.5px">Total de unidades a embalar</span>
                    <span id="pack_badge_mode" style="font-size:11px;padding:2px 8px;border-radius:12px;font-weight:bold;background:' . ($isMixtoSelected ? '#CCFBF1' : '#DCFCE7') . ';color:' . ($isMixtoSelected ? '#0F766E' : '#15803D') . '">'
                        . ($isMixtoSelected ? 'Cierre Mixto' : 'Estándar')
                    . '</span>
                </div>
                <input class="inptxt" name="prod_amount" id="pack_total_units" type="number" min="1" readonly required>
                <small id="pack_total_units_help" style="font-size:11.5px;color:#166534;margin-top:6px;display:block">'
                    . ($isMixtoSelected ? 'Cierre mixto: total calculado automáticamente según las cajas y bolsas de todos los CCs en el pallet.' : 'Cajas completas &times; unidades por caja + caja final + sobrantes + showroom')
                . '</small>
            </div>
            <div style="padding:14px 16px;background:#F8FAFC;border-bottom:1.5px solid #E2E8F0">
                <div style="font-size:12px;font-weight:800;color:#0F766E;display:flex;align-items:center;gap:7px;margin-bottom:8px">
                    <i class="fa fa-boxes"></i> Consumo Automático en Bodega 3000 al Cierre:
                </div>
                <div style="display:grid;gap:6px;font-size:12.5px;color:#334155">
                    <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px dashed #E2E8F0;padding-bottom:4px">
                        <span><i class="fa fa-pallet" style="color:#0284C7"></i> <b>Pallets:</b></span>
                        <span style="font-weight:800;color:#0F766E" id="lbl_preview_pallets">0 pallet</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px dashed #E2E8F0;padding-bottom:4px">
                        <span><i class="fa fa-box" style="color:#D97706"></i> <b>Cajas:</b></span>
                        <span style="font-weight:800;color:#0F766E" id="lbl_preview_boxes">0 cajas</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span><i class="fa fa-tape" style="color:#059669"></i> <b>Cinta papel (conversión por pasada):</b></span>
                        <span style="font-weight:800;color:#0F766E" id="lbl_preview_tape">0.00 m</span>
                    </div>
                    <div id="lbl_preview_tape_detail" style="font-size:11px;color:#047857;margin-top:2px;line-height:1.25"></div>
                </div>
                <small id="lbl_preview_rule_note" style="display:block;font-size:11px;color:#64748B;margin-top:6px;line-height:1.3">
                    Regla: cajas sobrantes &le; 5 quedan en el mismo pallet. Descuento automático directo en Bodega 3000.
                </small>
            </div>
            <div class="pack-close-auth">
                <label>Tipo de cinta papel (automático según la caja) *
                    <select name="req_embalaje_tipo_cinta" id="req_embalaje_tipo_cinta" class="inptxt" required style="pointer-events:none;background:#F1F5F9" tabindex="-1" onchange="updatePackagingSuppliesPreview(this.form)" title="Caja con logo = INS0073 · caja sin logo = INS0038">
                        <option value="INS0073" data-meters="40" ' . ($savedTape === 'INS0073' || $savedTape === '' ? 'selected' : '') . '>Cinta Impresa Papel 48mm × 40 MT (Rollo 40m - INS0073)</option>
                        <option value="INS0038" data-meters="50" ' . ($savedTape === 'INS0038' ? 'selected' : '') . '>Cinta Eco Paper 48mm × 50 MT (Rollo 50m - INS0038)</option>
                    </select>
                </label>
                <label>Bodega de destino *
                    <select name="req_id_bodega" id="req_id_bodega" class="inptxt" required>
                        <option value="">Seleccionar bodega...</option>';
    foreach ($warehouses as $warehouse) {
        $wId = (int)$warehouse['id'];
        $wSelected = ((int)($saved['req_id_bodega'] ?? 0) === $wId) ? 'selected' : '';
        echo '<option value="' . $wId . '" ' . $wSelected . '>' . $escape($warehouse['st_name']) . '</option>';
    }
    echo '          </select>
                </label>
                <label>Clasificación de cierre *
                    <select name="req_codigo_cierre" id="req_codigo_cierre" class="inptxt" required onchange="togglePackagingClosureMode(this.value)">
                        <option value="">Seleccionar clasificación...</option>';
    foreach ($closureTypes as $type) {
        $tCode = (string)$type['codigo'];
        // Si no hay código guardado, por defecto seleccionar PEDIDO CERRADO (20) o la opción guardada
        $selected = ($savedCode === $tCode || ($savedCode === '' && !$isMixtoSelected && $tCode === '20') || ($isMixtoSelected && $tCode === '50')) ? 'selected' : '';
        echo '<option value="' . $escape($tCode) . '" ' . $selected . '>' . $escape($type['descripcion']) . '</option>';
    }
    echo '          </select>
                </label>
                <div class="pack-close-credentials">
                    <label>Usuario supervisor *
                        <input name="pack_supervisor_user" class="inptxt" autocomplete="off" required placeholder="Usuario">
                    </label>
                    <label>Clave supervisor *
                        <input type="password" name="pack_supervisor_pass" class="inptxt" autocomplete="new-password" required placeholder="••••••••">
                    </label>
                </div>
                <label>Observación del supervisor
                    <textarea name="req_observacion" class="inptxt" placeholder="Notas u observaciones del supervisor...">' . $escape($saved['req_observacion'] ?? '') . '</textarea>
                </label>
            </div>
        </section>
    </div>';

    // -------------------------------------------------------------------------
    // 3. PANEL CIERRE MIXTO: Desglose por CC / Diseño (Aparece solo si el operador elige Cierre Mixto)
    // -------------------------------------------------------------------------
    echo '<section id="panel_cierre_mixto" class="pack-close-block" style="margin-top:24px;margin-bottom:24px;' . ($isMixtoSelected ? 'display:block;' : 'display:none;') . 'border:2px solid #0D9488;box-shadow:0 4px 14px rgba(13,148,136,0.12)">
        <div class="pack-close-title" style="background:linear-gradient(135deg, #0F766E, #115E59);padding:12px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
            <span style="font-size:14px"><i class="fa fa-boxes"></i> Desglose de Pallet Mixto (Múltiples Órdenes / Diseños)</span>
            <span style="font-size:11.5px;background:rgba(255,255,255,0.22);padding:3px 10px;border-radius:12px;font-weight:600">Código 50 · Cierre Mixto</span>
        </div>
        <div style="padding:12px 18px;background:#F0FDFA;border-bottom:1px solid #CCFBF1;font-size:13px;color:#115E59;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div style="display:flex;align-items:center;gap:8px;max-width:620px">
                <i class="fa fa-info-circle" style="font-size:17px;color:#0D9488;flex-shrink:0"></i>
                <span>Ingrese las cajas y unidades de cada CC que conforma este pallet. Los totales se consolidan y sincronizan automáticamente con el cierre.</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <input type="text" id="inp_search_mixed_cc" class="inptxt" placeholder="Buscar CC (ej: 26-00578-1)" style="width:220px;max-width:100%;height:34px;font-size:12.5px;border:1.5px solid #99F6E4;border-radius:6px;padding:4px 10px" onkeydown="if(event.key===\'Enter\'){event.preventDefault();searchAndAddMixedCC();}">
                <button type="button" class="btnblue" style="padding:7px 14px;font-size:12.5px;border-radius:6px;font-weight:bold;height:34px;display:inline-flex;align-items:center;gap:6px" onclick="searchAndAddMixedCC()"><i class="fa fa-plus"></i> Agregar CC</button>
            </div>
        </div>
        <div style="overflow-x:auto;padding:2px">
            <table class="pack-mixto-table" id="table_mixto">
                <thead>
                    <tr>
                        <th style="width:130px">C.C / Pedido</th>
                        <th style="width:75px;text-align:center">Diseño</th>
                        <th style="min-width:240px">Producto / Descripción</th>
                        <th style="width:85px;text-align:center">Cajas</th>
                        <th style="width:85px;text-align:center">Unid./Caja</th>
                        <th style="width:85px;text-align:center">Caja Final</th>
                        <th style="width:85px;text-align:center">Sobrantes</th>
                        <th style="width:110px;text-align:center">Total Bolsas</th>
                        <th style="width:180px">Bodega Origen</th>
                        <th style="width:50px;text-align:center">Quitar</th>
                    </tr>
                </thead>
                <tbody id="table_mixto_body">';

    // Preparar lista de filas iniciales
    $savedItemsMap = [];
    if (!empty($savedMixed['items'])) {
        foreach ($savedMixed['items'] as $si) {
            $savedItemsMap[trim((string)$si['req_number'])] = $si;
        }
    }

    $rowIdx = 0;
    $renderRow = static function(
        int $idx,
        array $ord,
        array $savedItem = [],
        bool $isMain = false
    ) use ($escape): void {
        $reqNum = trim((string)($ord['req_number'] ?? $savedItem['req_number'] ?? ''));
        $orderId = (int)($ord['id'] ?? $savedItem['order_id'] ?? 0);
        $itemId = (int)($ord['item_id'] ?? $savedItem['id_item'] ?? 0);
        $itemNum = trim((string)($ord['item_number_prod'] ?? ''));
        $itemTitle = trim((string)($ord['item_title'] ?? $savedItem['item_title'] ?? ''));
        $imageHash = trim((string)($ord['fab_design_imagehash'] ?? $savedItem['fab_design_imagehash'] ?? ''));

        $cajas = isset($savedItem['total_cajas']) ? (int)$savedItem['total_cajas'] : (int)($ord['req_embalaje_cajas_completas_amt'] ?? 0);
        $bpc = isset($savedItem['bolsas_por_caja']) ? (int)$savedItem['bolsas_por_caja'] : (int)($ord['req_embalaje_bolsas_por_caja_amt'] ?? 0);
        $bcf = isset($savedItem['bolsas_caja_final']) ? (int)$savedItem['bolsas_caja_final'] : (int)($ord['req_embalaje_caja_final'] ?? 0);
        $bsobr = isset($savedItem['bolsas_sobrante']) ? (int)$savedItem['bolsas_sobrante'] : 0;
        $totBolsas = isset($savedItem['total_bolsas']) ? (int)$savedItem['total_bolsas'] : (($cajas * $bpc) + $bcf + $bsobr);

        $imgHtml = '<span style="color:#94a3b8">-</span>';
        if ($imageHash !== '') {
            $viewerUrl = '/production/design-image?folder=order&hash=' . urlencode($imageHash);
            $rawImgUrl = '/production/design-image?folder=order&hash=' . urlencode($imageHash) . '&raw=1';
            $imgHtml = '<a href="' . $escape($viewerUrl) . '" target="_blank" title="Ver diseño (con rotación 360° y zoom)">'
                . '<img src="' . $escape($rawImgUrl) . '" style="height:42px;max-width:55px;object-fit:contain;border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc;cursor:pointer" alt="Diseño">'
                . '</a>';
        }

        $whOptions = '<option value="">-- Misma bodega --</option>';
        if (!empty($ord['bodegas'])) {
            foreach ($ord['bodegas'] as $b) {
                $whId = (int)$b['id'];
                $whName = $escape((string)$b['st_name']);
                $stk = (int)round((float)($b['stock'] ?? 0));
                $whOptions .= '<option value="' . $whId . '">' . $whName . ' (Stock: ' . $stk . ')</option>';
            }
        }

        $badge = $isMain ? '<span style="font-size:10px;background:#0F766E;color:white;padding:2px 6px;border-radius:4px;margin-left:5px;font-weight:700">OT Actual</span>' : '';
        $removeBtn = $isMain
            ? '<span style="color:#94a3b8;font-size:11px">-</span>'
            : '<button type="button" class="btnred" style="padding:5px 9px;font-size:11px;border-radius:4px" onclick="removeMixedRow(' . $idx . ')" title="Quitar este CC del pallet"><i class="fa fa-trash"></i></button>';

        echo '<tr id="mixed_row_' . $idx . '" data-idx="' . $idx . '" style="' . ($isMain ? 'background:#F0FDFA;font-weight:600' : '') . '">'
            . '<td style="white-space:nowrap"><b>' . $escape($reqNum) . '</b>' . $badge
            . '<input type="hidden" name="mixed_req_number_' . $idx . '" value="' . $escape($reqNum) . '">'
            . '<input type="hidden" name="mixed_order_id_' . $idx . '" value="' . $orderId . '">'
            . '<input type="hidden" name="mixed_item_id_' . $idx . '" value="' . $itemId . '">'
            . '</td>'
            . '<td style="text-align:center">' . $imgHtml . '</td>'
            . '<td><b>' . $escape($itemNum) . '</b>' . ($itemTitle !== '' ? ('<br><small style="color:#64748b">' . $escape($itemTitle) . '</small>') : '') . '</td>'
            . '<td style="text-align:center"><input type="number" min="0" step="1" name="total_cajas_' . $idx . '" id="total_cajas_' . $idx . '" class="inptxt" style="width:75px;text-align:center" value="' . $cajas . '" oninput="calcEmbalajeMixto()"></td>'
            . '<td style="text-align:center"><input type="number" min="0" step="1" name="bolsas_por_caja_' . $idx . '" id="bolsas_por_caja_' . $idx . '" class="inptxt" style="width:75px;text-align:center" value="' . $bpc . '" oninput="calcEmbalajeMixto()"></td>'
            . '<td style="text-align:center"><input type="number" min="0" step="1" name="bolsas_caja_final_' . $idx . '" id="bolsas_caja_final_' . $idx . '" class="inptxt" style="width:75px;text-align:center" value="' . $bcf . '" oninput="calcEmbalajeMixto()"></td>'
            . '<td style="text-align:center"><input type="number" min="0" step="1" name="bolsas_sobrante_' . $idx . '" id="bolsas_sobrante_' . $idx . '" class="inptxt" style="width:75px;text-align:center" value="' . $bsobr . '" oninput="calcEmbalajeMixto()"></td>'
            . '<td style="text-align:center"><input type="number" name="total_bolsas_' . $idx . '" id="total_bolsas_' . $idx . '" class="inptxt" style="width:95px;text-align:center;background:#F8FAFC;font-weight:800;color:#0F766E" value="' . $totBolsas . '" readonly></td>'
            . '<td><select name="req_id_bodega_' . $idx . '" class="inptxt" style="width:100%;height:32px;font-size:12px">' . $whOptions . '</select></td>'
            . '<td style="text-align:center">' . $removeBtn . '</td>'
            . '</tr>';
    };

    if (!empty($mixedOrders)) {
        foreach ($mixedOrders as $ord) {
            $rNum = trim((string)($ord['req_number'] ?? ''));
            $sItem = $savedItemsMap[$rNum] ?? [];
            $renderRow($rowIdx, $ord, $sItem, $rowIdx === 0);
            $rowIdx++;
        }
    } else {
        // Fallback al menos la orden actual
        $renderRow(0, [
            'id' => (int)($agenda['ag_reqid'] ?? 0),
            'req_number' => (string)($agenda['req_number'] ?? ''),
            'item_id' => (int)($agenda['item_id'] ?? 0),
            'item_number_prod' => (string)($agenda['item_number_prod'] ?? ''),
            'item_title' => (string)($agenda['item_title'] ?? ''),
            'fab_design_imagehash' => (string)($agenda['fab_design_imagehash'] ?? ''),
            'req_embalaje_cajas_completas_amt' => (int)($saved['req_embalaje_cajas_completas_amt'] ?? $agenda['req_embalaje_cajas_completas_amt'] ?? 0),
            'req_embalaje_bolsas_por_caja_amt' => (int)($saved['req_embalaje_bolsas_por_caja_amt'] ?? $agenda['req_embalaje_bolsas_por_caja_amt'] ?? 0),
            'req_embalaje_caja_final' => (int)($saved['req_embalaje_caja_final'] ?? 0),
        ], [], true);
        $rowIdx = 1;
    }

    echo '      </tbody>
                <tfoot>
                    <tr style="background:#F8FAFC;font-weight:bold;border-top:2px solid #CBD5E1">
                        <td colspan="3" style="text-align:right;padding-right:14px;color:#1E293B;font-size:12.5px">TOTALES COMBINADOS PALLET MIXTO:</td>
                        <td style="text-align:center"><span id="lbl_mixed_total_boxes" style="color:#0F766E;font-size:15px;font-weight:800">0</span> <small style="color:#64748B">cajas</small></td>
                        <td colspan="3" style="text-align:right;color:#64748B;font-weight:normal;font-size:12px">Suma bolsas de todos los CCs:</td>
                        <td style="text-align:center"><span id="lbl_mixed_total_units" style="color:#0F766E;font-size:16px;font-weight:800">0</span></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>';

    // Scripts de interacción y cálculo dinámico
    echo '<script>
        function setPackagingMode(mode) {
            var sel = document.getElementById("req_codigo_cierre");
            if (mode === "mixed") {
                if (sel) sel.value = "50";
                togglePackagingClosureMode("50");
            } else {
                if (sel && sel.value === "50") {
                    var defaultOpt = sel.querySelector(\'option[value="20"]\') || sel.querySelector(\'option:not([value="50"]):not([value=""])\');
                    sel.value = defaultOpt ? defaultOpt.value : "";
                }
                togglePackagingClosureMode(sel ? sel.value : "");
            }
        }

        function calculatePackagingUnits(form, changedField) {
            function n(key) { return Math.max(0, Number(form.elements[key]?.value) || 0); }
            var isMixto = (document.getElementById("req_codigo_cierre") && document.getElementById("req_codigo_cierre").value === "50");
            if (!isMixto) {
                form.elements.prod_amount.value = n("req_embalaje_cajas_completas_amt") * n("req_embalaje_bolsas_por_caja_amt")
                    + n("req_embalaje_caja_final") + n("req_embalaje_bolsas_sobrantes_amt") + n("req_embalaje_showroom");
                if (["req_embalaje_cajas_completas_amt", "req_embalaje_caja_final", "req_embalaje_cajas_por_pallet_amt"].indexOf(changedField) !== -1) {
                    var boxes = n("req_embalaje_cajas_completas_amt") + (n("req_embalaje_caja_final") > 0 ? 1 : 0);
                    var perPallet = n("req_embalaje_cajas_por_pallet_amt");
                    if (perPallet > 0) {
                        form.elements.req_embalaje_pallets_completos_amt.value = Math.floor(boxes / perPallet);
                        form.elements.req_embalaje_palletcajas_incompletos_amt.value = boxes % perPallet;
                    }
                }
            } else {
                calcEmbalajeMixto();
            }
            updatePackagingSuppliesPreview(form);
        }

        function togglePackagingClosureMode(val) {
            var isMixto = (val === "50");
            var p = document.getElementById("panel_cierre_mixto");
            var help = document.getElementById("pack_total_units_help");
            var badge = document.getElementById("pack_badge_mode");
            var btnStd = document.getElementById("btn_mode_standard");
            var btnMix = document.getElementById("btn_mode_mixed");

            if (btnStd && btnMix) {
                if (isMixto) {
                    btnStd.classList.remove("active");
                    btnMix.classList.add("active");
                } else {
                    btnStd.classList.add("active");
                    btnMix.classList.remove("active");
                }
            }

            if (badge) {
                badge.innerText = isMixto ? "Cierre Mixto" : "Estándar";
                badge.style.background = isMixto ? "#CCFBF1" : "#DCFCE7";
                badge.style.color = isMixto ? "#0F766E" : "#15803D";
            }

            if (p) {
                p.style.display = isMixto ? "block" : "none";
            }

            if (isMixto) {
                if (help) help.innerHTML = "<i class=\'fa fa-boxes\'></i> <b>Cierre mixto activo:</b> total calculado automáticamente según las cajas y bolsas de todos los CCs en el pallet.";
                calcEmbalajeMixto();
            } else {
                if (help) help.innerHTML = "Cajas completas &times; unidades por caja + caja final + sobrantes + showroom";
                var f = document.getElementById("pack_total_units")?.form;
                if (f) calculatePackagingUnits(f, "");
            }
        }

        function calcEmbalajeMixto() {
            var rows = document.querySelectorAll("#table_mixto_body tr");
            var sumBoxes = 0;
            var sumUnits = 0;
            rows.forEach(function(row) {
                var idx = row.getAttribute("data-idx");
                if (idx === null) return;
                var cajas = Math.max(0, parseInt(document.getElementById("total_cajas_" + idx)?.value) || 0);
                var bpc = Math.max(0, parseInt(document.getElementById("bolsas_por_caja_" + idx)?.value) || 0);
                var bcf = Math.max(0, parseInt(document.getElementById("bolsas_caja_final_" + idx)?.value) || 0);
                var bsobr = Math.max(0, parseInt(document.getElementById("bolsas_sobrante_" + idx)?.value) || 0);
                var rowTotal = (cajas * bpc) + bcf + bsobr;
                var inpTot = document.getElementById("total_bolsas_" + idx);
                if (inpTot) inpTot.value = rowTotal;
                sumBoxes += cajas + (bcf > 0 ? 1 : 0);
                sumUnits += rowTotal;

                if (idx === "0") {
                    var f = document.getElementById("pack_total_units")?.form;
                    if (f) {
                        if (f.elements["req_embalaje_cajas_completas_amt"]) f.elements["req_embalaje_cajas_completas_amt"].value = cajas;
                        if (f.elements["req_embalaje_bolsas_por_caja_amt"]) f.elements["req_embalaje_bolsas_por_caja_amt"].value = bpc;
                        if (f.elements["req_embalaje_caja_final"]) f.elements["req_embalaje_caja_final"].value = bcf;
                        if (f.elements["req_embalaje_bolsas_sobrantes_amt"]) f.elements["req_embalaje_bolsas_sobrantes_amt"].value = bsobr;
                    }
                }
            });

            var lblBoxes = document.getElementById("lbl_mixed_total_boxes");
            if (lblBoxes) lblBoxes.innerText = sumBoxes;
            var lblUnits = document.getElementById("lbl_mixed_total_units");
            if (lblUnits) lblUnits.innerText = sumUnits.toLocaleString("es-CL");

            var f = document.getElementById("pack_total_units")?.form;
            if (f) {
                var perPallet = Math.max(0, parseInt(f.elements["req_embalaje_cajas_por_pallet_amt"]?.value) || 0);
                if (perPallet > 0) {
                    f.elements["req_embalaje_pallets_completos_amt"].value = Math.floor(sumBoxes / perPallet);
                    f.elements["req_embalaje_palletcajas_incompletos_amt"].value = sumBoxes % perPallet;
                }
                var mainCajas = Math.max(0, parseInt(document.getElementById("total_cajas_0")?.value) || 0);
                var mainBpc = Math.max(0, parseInt(document.getElementById("bolsas_por_caja_0")?.value) || 0);
                var mainBcf = Math.max(0, parseInt(document.getElementById("bolsas_caja_final_0")?.value) || 0);
                var mainSobr = Math.max(0, parseInt(document.getElementById("bolsas_sobrante_0")?.value) || 0);
                var showroom = Math.max(0, parseInt(f.elements["req_embalaje_showroom"]?.value) || 0);
                var mainTotal = (mainCajas * mainBpc) + mainBcf + mainSobr + showroom;
                if (f.elements["prod_amount"]) f.elements["prod_amount"].value = mainTotal > 0 ? mainTotal : (sumUnits > 0 ? sumUnits : 1);
            }
            updatePackagingSuppliesPreview(f);
        }

        function updatePackagingSuppliesPreview(form) {
            if (!form) form = document.getElementById("pack_total_units")?.form;
            if (!form) return;
            // El total de unidades se asigna por código (no dispara "input"): se refresca el panel "Total CC Actualizado".
            if (typeof window.updateClosingPanelFromCurrentInput === "function") window.updateClosingPanelFromCurrentInput();
            var medUp = (form.elements["req_embalaje_medidas_caja"]?.value || "").toUpperCase();
            if (/SIN LOGO|SIN IMPRESI/.test(medUp) && form.elements["req_embalaje_tipo_cinta"]) {
                form.elements["req_embalaje_tipo_cinta"].value = "INS0038";
            }
            function n(key) { return Math.max(0, Number(form.elements[key]?.value) || 0); }
            var isMixto = (document.getElementById("req_codigo_cierre") && document.getElementById("req_codigo_cierre").value === "50");
            var totalBoxes = 0;
            if (isMixto) {
                var lblBoxes = document.getElementById("lbl_mixed_total_boxes");
                totalBoxes = lblBoxes ? (parseInt(lblBoxes.innerText) || 0) : 0;
            } else {
                totalBoxes = n("req_embalaje_cajas_completas_amt") + (n("req_embalaje_caja_final") > 0 ? 1 : 0);
            }

            var cap = n("req_embalaje_cajas_por_pallet_amt") || 18;
            var fullPallets = Math.floor(totalBoxes / cap);
            var rem = totalBoxes % cap;
            var pallets = 0;
            var note = "Regla: cajas sobrantes &le; 5 quedan en el mismo pallet. Descuento directo en Bodega 3000.";

            if (totalBoxes > 0) {
                if (fullPallets === 0) {
                    pallets = 1;
                    note = totalBoxes + " cajas totales (" + totalBoxes + " &le; " + cap + ") &rarr; 1 pallet físico.";
                } else {
                    if (rem > 0 && rem <= 5) {
                        pallets = fullPallets;
                        note = totalBoxes + " cajas: " + fullPallets + " pallet(s) de " + cap + " + " + rem + " sobrante(s) (&le; 5) &rarr; " + pallets + " pallet(s) físico(s).";
                    } else if (rem > 5) {
                        pallets = fullPallets + 1;
                        note = totalBoxes + " cajas: " + fullPallets + " pallet(s) de " + cap + " + " + rem + " sobrante(s) (> 5) &rarr; " + pallets + " pallets físicos.";
                    } else {
                        pallets = fullPallets;
                        note = totalBoxes + " cajas: " + pallets + " pallet(s) completo(s) de " + cap + ".";
                    }
                }
            }

            var medStr = (form.elements["req_embalaje_medidas_caja"]?.value || "").toUpperCase();
            var matches = medStr.match(/\d+/g) || [];
            var widthCm = 40.0;
            if (matches.length >= 2) {
                var rawW = parseFloat(matches[1]);
                widthCm = rawW >= 100 ? (rawW / 10.0) : rawW;
            } else if (matches.length === 1) {
                var rawW = parseFloat(matches[0]);
                widthCm = rawW >= 100 ? (rawW / 10.0) : rawW;
            }
            if (widthCm <= 0) widthCm = 40.0;

            var tapeTypeSel = form.elements["req_embalaje_tipo_cinta"];
            var tapeRollLengthM = 40.0;
            if (tapeTypeSel && tapeTypeSel.selectedOptions && tapeTypeSel.selectedOptions[0]) {
                tapeRollLengthM = parseFloat(tapeTypeSel.selectedOptions[0].getAttribute("data-meters")) || 40.0;
            } else if (tapeTypeSel && tapeTypeSel.value === "INS0038") {
                tapeRollLengthM = 50.0;
            }

            var passLengthM = (widthCm + 10.0) / 100.0;
            var tapePerPassRoll = passLengthM / tapeRollLengthM;
            var passesPerRoll = passLengthM > 0 ? Math.floor(tapeRollLengthM / passLengthM) : 0;
            var totalTapeMeters = totalBoxes * passLengthM;
            var tapeRolls = (totalTapeMeters / tapeRollLengthM).toFixed(2);

            var elP = document.getElementById("lbl_preview_pallets");
            var elB = document.getElementById("lbl_preview_boxes");
            var elT = document.getElementById("lbl_preview_tape");
            var elTD = document.getElementById("lbl_preview_tape_detail");
            var elN = document.getElementById("lbl_preview_rule_note");

            if (elP) elP.innerText = pallets + " pallet" + (pallets !== 1 ? "s" : "");
            if (elB) elB.innerText = totalBoxes + " caja" + (totalBoxes !== 1 ? "s" : "");
            if (elT) elT.innerText = totalTapeMeters.toFixed(2) + " m (" + tapeRolls + " rollos " + tapeRollLengthM + "m)";
            if (elTD) elTD.innerHTML = "Pasada: <b>" + passLengthM.toFixed(2) + " m</b> (" + widthCm.toFixed(1) + "cm + 10cm) &rarr; <b>" + tapePerPassRoll.toFixed(4) + " rollo/pasada</b> (rinde <b>" + passesPerRoll + "</b> cajas por rollo de " + tapeRollLengthM + "m).";
            if (elN) elN.innerHTML = \'<i class="fa fa-info-circle"></i> \' + note;
        }

        function searchAndAddMixedCC() {
            var q = (document.getElementById("inp_search_mixed_cc")?.value || "").trim();
            if (!q) {
                alert("Ingresa un número de CC a buscar.");
                return;
            }
            fetch("/production/api/search-cc?q=" + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.ok || !data.order) {
                        alert(data.error || "No se encontró la orden solicitada.");
                        return;
                    }
                    var o = data.order;
                    var existingInputs = document.querySelectorAll("#table_mixto_body input[name^=\'mixed_req_number_\']");
                    for (var i = 0; i < existingInputs.length; i++) {
                        if (existingInputs[i].value === o.req_number) {
                            alert("El CC " + o.req_number + " ya está agregado en la tabla.");
                            return;
                        }
                    }
                    var tbody = document.getElementById("table_mixto_body");
                    var nextIdx = tbody.querySelectorAll("tr").length;
                    var whOptions = "<option value=\'\'>-- Misma bodega --</option>";
                    if (o.bodegas && o.bodegas.length) {
                        o.bodegas.forEach(function(b) {
                            whOptions += "<option value=\'" + b.id + "\'>" + (b.st_name || ("Bodega " + b.id)) + " (Stock: " + Math.round(b.stock) + ")</option>";
                        });
                    }
                    var imgHtml = o.fab_design_imagehash
                        ? "<a href=\'/production/design-image?folder=order&hash=" + encodeURIComponent(o.fab_design_imagehash) + "\' target=\'_blank\' title=\'Ver diseño (con rotación)\'><img src=\'/production/design-image?folder=order&hash=" + encodeURIComponent(o.fab_design_imagehash) + "&raw=1\' style=\'height:42px;max-width:55px;object-fit:contain;border:1px solid #cbd5e1;border-radius:4px;background:#f8fafc\'></a>"
                        : "<span style=\'color:#94a3b8\'>-</span>";

                    var tr = document.createElement("tr");
                    tr.id = "mixed_row_" + nextIdx;
                    tr.setAttribute("data-idx", nextIdx);
                    tr.innerHTML = "<td style=\'white-space:nowrap\'><b>" + (o.req_number || "-") + "</b><br><small style=\'color:#64748b\'>" + (o.cust_name || "") + "</small>"
                        + "<input type=\'hidden\' name=\'mixed_req_number_" + nextIdx + "\' value=\'" + (o.req_number || "") + "\'>"
                        + "<input type=\'hidden\' name=\'mixed_order_id_" + nextIdx + "\' value=\'" + (o.id || 0) + "\'>"
                        + "<input type=\'hidden\' name=\'mixed_item_id_" + nextIdx + "\' value=\'" + (o.item_id || 0) + "\'>"
                        + "</td><td style=\'text-align:center\'>" + imgHtml + "</td>"
                        + "<td><b>" + (o.item_number_prod || "") + "</b>" + (o.item_title ? ("<br><small style=\'color:#64748b\'>" + o.item_title + "</small>") : "") + "</td>"
                        + "<td style=\'text-align:center\'><input type=\'number\' min=\'0\' step=\'1\' name=\'total_cajas_" + nextIdx + "\' id=\'total_cajas_" + nextIdx + "\' class=\'inptxt\' style=\'width:75px;text-align:center\' value=\'" + (o.req_embalaje_cajas_completas_amt || 0) + "\' oninput=\'calcEmbalajeMixto()\'></td>"
                        + "<td style=\'text-align:center\'><input type=\'number\' min=\'0\' step=\'1\' name=\'bolsas_por_caja_" + nextIdx + "\' id=\'bolsas_por_caja_" + nextIdx + "\' class=\'inptxt\' style=\'width:75px;text-align:center\' value=\'" + (o.req_embalaje_bolsas_por_caja_amt || 0) + "\' oninput=\'calcEmbalajeMixto()\'></td>"
                        + "<td style=\'text-align:center\'><input type=\'number\' min=\'0\' step=\'1\' name=\'bolsas_caja_final_" + nextIdx + "\' id=\'bolsas_caja_final_" + nextIdx + "\' class=\'inptxt\' style=\'width:75px;text-align:center\' value=\'" + (o.req_embalaje_caja_final || 0) + "\' oninput=\'calcEmbalajeMixto()\'></td>"
                        + "<td style=\'text-align:center\'><input type=\'number\' min=\'0\' step=\'1\' name=\'bolsas_sobrante_" + nextIdx + "\' id=\'bolsas_sobrante_" + nextIdx + "\' class=\'inptxt\' style=\'width:75px;text-align:center\' value=\'0\' oninput=\'calcEmbalajeMixto()\'></td>"
                        + "<td style=\'text-align:center\'><input type=\'number\' name=\'total_bolsas_" + nextIdx + "\' id=\'total_bolsas_" + nextIdx + "\' class=\'inptxt\' style=\'width:95px;text-align:center;background:#F8FAFC;font-weight:800;color:#0F766E\' value=\'0\' readonly></td>"
                        + "<td><select name=\'req_id_bodega_" + nextIdx + "\' class=\'inptxt\' style=\'width:100%;height:32px;font-size:12px\'>" + whOptions + "</select></td>"
                        + "<td style=\'text-align:center\'><button type=\'button\' class=\'btnred\' style=\'padding:5px 9px;font-size:11px;border-radius:4px\' onclick=\'removeMixedRow(" + nextIdx + ")\' title=\'Quitar este CC\'><i class=\'fa fa-trash\'></i></button></td>";

                    tbody.appendChild(tr);
                    document.getElementById("inp_search_mixed_cc").value = "";
                    calcEmbalajeMixto();
                })
                .catch(function(err) {
                    alert("Error al buscar orden: " + err.message);
                });
        }

        function removeMixedRow(idx) {
            var r = document.getElementById("mixed_row_" + idx);
            if (r) {
                r.parentNode.removeChild(r);
                calcEmbalajeMixto();
            }
        }

        (function initPackagingForm() {
            var sel = document.getElementById("req_codigo_cierre");
            if (sel) {
                togglePackagingClosureMode(sel.value);
            }
            updatePackagingSuppliesPreview();
        })();
    </script>';

