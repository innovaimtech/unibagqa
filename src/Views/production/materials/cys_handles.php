<?php
    $escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    $base = '/production/work-orders/operate?agid=' . $agId . '&mode=materiales';
    $workerId = (int)($_SESSION['wrk_id'] ?? 0);
    $plantId = (int)($_SESSION['user_planta_id'] ?? 0);
    echo '<section style="background:#fff;border:1px solid #cbd5e1;border-radius:6px;padding:16px;margin-bottom:16px">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">'
        . '<h3 style="color:#165552;margin:0"><i class="fa fa-shopping-bag"></i> Consumo de Manillas</h3>'
        . '<button type="button" class="btnblue" onclick="document.getElementById(\'cys_handle_scan\').showModal()"><i class="fa fa-search"></i> Buscar Manual</button></div>';
    echo '<div style="background:#F0FDF4;border:2px dashed #16A34A;border-radius:6px;padding:12px 16px;margin:12px 0 16px 0">'
        . '<form method="post" action="/production/work-orders/handles/manage" style="margin:0;display:flex;align-items:center;gap:10px;flex-wrap:wrap">'
        . '<input type="hidden" name="_csrf" value="' . $escape(csrfToken()) . '">'
        . '<input type="hidden" name="agid" value="' . $agId . '">'
        . '<input type="hidden" name="worker_ot_id" value="' . $otId . '">'
        . '<input type="hidden" name="handle_action" value="scan">'
        . '<div style="font-size:22px;color:#16A34A"><i class="fa fa-barcode"></i> <i class="fa fa-qrcode"></i></div>'
        . '<div style="font-weight:bold;color:#166534;font-size:13.5px;white-space:nowrap">Lector de Manillas:</div>'
        . '<input type="text" name="handle_code" placeholder="Escanee rollo de manillas con la pistola láser (Ej: ROLL-MAN-XXXXX)..." '
        . 'style="flex:1;min-width:260px;padding:8px 12px;font-size:14px;border:1px solid #16A34A;border-radius:4px;font-weight:bold;background:#FFFFFF" '
        . 'onkeydown="if(event.key === \'Enter\'){ this.form.submit(); }">'
        . '<button type="submit" class="btngreen" style="padding:8px 16px;font-size:13px;font-weight:bold;cursor:pointer;border:none;border-radius:4px">'
        . '<i class="fa fa-search"></i> Escanear / Consumir</button></form></div>';
    if (isset($_GET['handles_saved'])) echo '<p style="color:#166534;font-weight:700">Operacion registrada. Revisa el saldo y estado en el historial de manillas.</p>';
    if (!empty($_GET['handle_error'])) echo '<p style="color:#b91c1c">' . $escape($_GET['handle_error']) . '</p>';
    $scanned = null;
    $confirmation = null;
    $confirmKey = trim((string)($_GET['handle_confirm'] ?? ''));
    try {
        $service = new HandleConsumptionService(Db::erpPdo(), Db::trzPdo());
        $service->operatorContext($otId, $workerId, $plantId);
        $history = $service->history($otId);
        if ($confirmKey !== '') {
            $confirmation = $_SESSION['cys_handle_confirmations'][$confirmKey] ?? null;
            if (!$confirmation || (int)$confirmation['ot_id'] !== $otId
                || (int)$confirmation['worker_id'] !== $workerId || time() - (int)$confirmation['time'] > 7200) {
                throw new RuntimeException('La confirmacion vencio. Escanea nuevamente.');
            }
            $scanned = $service->scan($otId, $workerId, $plantId, $confirmation['code']);
            if (abs((float)$scanned['weight_kg'] - (float)$confirmation['weight_kg']) > 0.0001
                || abs((float)$scanned['received_qty'] - (float)$confirmation['received_qty']) > 0.0001) {
                $scanned = null;
                throw new RuntimeException('El saldo cambio desde el escaneo. Escanea nuevamente.');
            }
        }
    } catch (Throwable $e) {
        $history = $history ?? [];
        echo '<p style="color:#b91c1c">' . $escape($e->getMessage()) . '</p>';
    }
    echo '<style>.cys-handle-dialog{border:1px solid #cbd5e1;border-radius:10px;padding:22px;width:min(640px,90vw);max-height:85vh;overflow:auto;color:#0f172a}'
        . '.cys-handle-dialog::backdrop{background:rgba(15,23,42,.65)}'
        . '.cys-handle-dialog label{display:block;margin:12px 0;font-weight:700}'
        . '.cys-handle-dialog [hidden]{display:none!important}'
        . '.cys-handle-dialog input:not([type=checkbox]),.cys-handle-dialog select,.cys-handle-dialog textarea{display:block;width:100%;box-sizing:border-box;margin-top:5px;padding:8px}'
        . '.cys-handle-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px}</style>';
    $hidden = '<input type="hidden" name="_csrf" value="' . $escape(csrfToken()) . '">'
        . '<input type="hidden" name="agid" value="' . $agId . '">'
        . '<input type="hidden" name="worker_ot_id" value="' . $otId . '">';
    echo '<dialog id="cys_handle_scan" class="cys-handle-dialog"><h2>Escanear manillas</h2>'
        . '<form method="post" action="/production/work-orders/handles/manage">' . $hidden
        . '<input type="hidden" name="handle_action" value="scan">'
        . '<label>Codigo de trazabilidad<input name="handle_code" required autocomplete="off" autofocus placeholder="ROLL-MAN-XXXXX"></label>'
        . '<div class="cys-handle-actions"><button type="button" class="btngrey" onclick="this.closest(\'dialog\').close()">Volver</button>'
        . '<button class="btngreen" type="submit">Continuar</button></div></form></dialog>';
    if ($scanned && $confirmation) {
        echo '<dialog id="cys_handle_consume" class="cys-handle-dialog"><h2>Consumir manillas</h2>'
            . '<div style="font-size:22px;font-weight:800">' . $escape($scanned['roll_code']) . '</div>'
            . '<p><b>' . $escape($scanned['color'] ?? '-') . '</b> · Ancho: ' . number_format((float)($scanned['width_mm'] ?? 0) / 10, 2, ',', '.') . ' cm'
            . '<br>' . $escape($scanned['warehouse_name']) . '</p>'
            . '<p>Peso disponible: <b>' . number_format((float)$scanned['weight_kg'], 3, ',', '.') . ' kg</b>'
            . '<br>Metros disponibles: <b>' . ($scanned['meters'] === null ? '-' : number_format((float)$scanned['meters'], 2, ',', '.')) . '</b></p>'
            . '<form method="post" action="/production/work-orders/handles/manage">' . $hidden
            . '<input type="hidden" name="handle_action" value="consume"><input type="hidden" name="operation_key" value="' . $escape($confirmKey) . '">'
            . '<label>Tipo de consumo<select name="handle_mode" id="cys_handle_mode" onchange="updateCysHandleBalance()"><option value="full">Completo</option><option value="partial">Parcial</option></select></label>'
            . '<label id="cys_handle_leftover_wrap" hidden>Peso sobrante (kg)<input type="number" name="handle_leftover_kg" id="cys_handle_leftover" step="0.001" min="0.001" max="'
            . $escape(max(0, (float)$scanned['weight_kg'] - 0.001)) . '" oninput="updateCysHandleBalance()"></label>'
            . '<div style="background:#ecfdf5;padding:12px;border-radius:6px"><b id="cys_handle_total"></b>'
            . '<br><small>Consumo = peso disponible - sobrante. Los metros y la fraccion de rollo se ajustan proporcionalmente.</small></div>';
        if ($scanned['product_warning']) {
            echo '<label style="color:#b45309"><input type="checkbox" name="accept_other_product" value="1" required> Confirmo usar estas manillas, preparadas para otro producto.</label>';
        }
        echo '<div class="cys-handle-actions"><a class="btngrey" style="text-decoration:none" href="' . $escape($base) . '">Volver</a>'
            . '<button type="submit" class="btngreen">Consumir</button></div></form></dialog>';
        echo '<script>function updateCysHandleBalance(){const kg=' . json_encode((float)$scanned['weight_kg']) . ';'
            . 'const partial=document.getElementById("cys_handle_mode").value==="partial";const input=document.getElementById("cys_handle_leftover");'
            . 'document.getElementById("cys_handle_leftover_wrap").hidden=!partial;input.required=partial;input.disabled=!partial;'
            . 'const left=partial?Number(input.value):0;document.getElementById("cys_handle_total").textContent="Consumo: "+Math.max(0,kg-left).toLocaleString("es-CL",{minimumFractionDigits:3,maximumFractionDigits:3})+" kg";}'
            . 'updateCysHandleBalance();document.getElementById("cys_handle_consume").showModal();</script>';
    }
    echo '<div style="overflow:auto;max-height:300px;margin-top:14px"><table width="100%" cellpadding="8" cellspacing="0"><thead><tr>';
    foreach (['Codigo', 'Fecha', 'Operacion', 'Consumo kg', 'Saldo kg', 'Estado', 'Opciones'] as $label) echo '<th class="tdheader">' . $escape($label) . '</th>';
    echo '</tr></thead><tbody>';
    $dialogs = '';
    foreach ($history as $index => $op) {
        $detail = $op['detail'];
        $stateLabel = ['DONE' => 'Confirmado', 'PENDING' => 'Pendiente', 'REVERSED' => 'Devuelto', 'CANCELLED' => 'Cancelado'][$op['state']] ?? $op['state'];
        echo '<tr><td class="tdnrm"><b>' . $escape($detail['code']) . '</b></td>'
            . '<td class="tdnrm">' . date('d/m/Y H:i', (int)$op['created_at']) . '</td>'
            . '<td class="tdnrm">' . ($op['action'] === 'CONSUME' ? 'Consumo' : 'Devolucion') . '</td>'
            . '<td class="tdnrm">' . number_format((float)$detail['after']['used_kg'], 3, ',', '.') . '</td>'
            . '<td class="tdnrm">' . number_format((float)$detail['after']['weight_kg'], 3, ',', '.') . '</td>'
            . '<td class="tdnrm">' . $escape($stateLabel) . '</td><td class="tdnrm">';
        if ($op['state'] === 'PENDING') {
            echo '<form method="post" action="/production/work-orders/handles/manage" style="display:inline">' . $hidden
                . '<input type="hidden" name="handle_action" value="resume"><input type="hidden" name="operation_key" value="' . $escape($op['operation_key']) . '">'
                . '<button type="submit" class="btnblue">Reintentar</button></form> ';
        }
        $canReverse = $op['state'] === 'DONE' && $op['action'] === 'CONSUME';
        if ($canReverse || $op['state'] === 'PENDING') {
            $id = 'cys_handle_return_' . $index;
            $action = $canReverse ? 'reverse' : 'cancel';
            echo '<button type="button" class="btngrey" onclick="document.getElementById(\'' . $id . '\').showModal()">' . ($canReverse ? 'Devolver' : 'Cancelar') . '</button>';
            $dialogs .= '<dialog id="' . $id . '" class="cys-handle-dialog"><h2>' . ($canReverse ? 'Devolver a Bodega 3000' : 'Cancelar pendiente') . '</h2>'
                . '<b>' . $escape($detail['code']) . '</b><p>' . ($canReverse ? 'Solo confirmar si el material utilizado se recupero fisicamente. Se restauraran stock y saldo del codigo.'
                    : 'Solo se puede cancelar si ERP aun no registro el movimiento. Si ya lo registro, reintenta primero.') . '</p>'
                . '<form method="post" action="/production/work-orders/handles/manage">' . $hidden
                . '<input type="hidden" name="handle_action" value="' . $action . '">'
                . '<input type="hidden" name="operation_key" value="' . ($canReverse ? bin2hex(random_bytes(16)) : $escape($op['operation_key'])) . '">'
                . '<input type="hidden" name="original_key" value="' . $escape($op['operation_key']) . '">'
                . '<label>Motivo<textarea name="handle_reason" required></textarea></label>'
                . '<label>Usuario supervisor<input name="handle_supervisor" required autocomplete="off"></label>'
                . '<label>Clave supervisor<input type="password" name="handle_password" required autocomplete="new-password"></label>'
                . '<div class="cys-handle-actions"><button type="button" class="btngrey" onclick="this.closest(\'dialog\').close()">Volver</button>'
                . '<button type="submit" class="btnorange">Confirmar</button></div></form></dialog>';
        }
        echo '</td></tr>';
    }
    if ($history === []) echo '<tr><td colspan="7" class="tdnrm">No hay consumos de manillas en esta OT.</td></tr>';
    echo '</tbody></table></div>' . $dialogs . '</section>';

