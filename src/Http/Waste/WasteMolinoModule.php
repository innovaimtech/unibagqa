<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Operaciones y Pesaje de Molino (src/Http/Waste/WasteMolinoModule.php)
// =============================================================================

function renderWasteMolinoScreen(
    ?array $currentShiftSession,
    string $currentOperatorName,
    float $plaStockKg,
    ?array $activeMolinoOp,
    array $productionEvents,
    ?string $flashMessage = null,
    bool $flashIsError = false
): void {
    $body = '';
    if ($flashMessage !== null) {
        $body .= '<div class="alert ' . ($flashIsError ? 'error' : 'success') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }
    $body .= '<style>
.wm-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,0.55);display:none;align-items:flex-start;justify-content:center;z-index:9998;padding:28px 16px;overflow-y:auto}
.wm-modal-backdrop.is-open{display:flex}
.wm-modal{width:100%;max-width:820px;background:#fff;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.28);margin:auto 0;overflow:hidden}
.wm-modal-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid #e5e7eb;background:#f8fafc}
.wm-modal-title{font-size:18px;font-weight:900;margin:0;color:#0f172a}
.wm-modal-close{background:#fff;border:1px solid #d1d5db;color:#374151;border-radius:8px;padding:6px 12px;cursor:pointer;font-weight:700}
.wm-modal-close:hover{background:#f9fafb}
.wm-modal-body{padding:16px 18px}
</style>';

    $body .= '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px">';
    $body .= '<div><a class="btn secondary" href="/waste/management" style="margin-right:8px">← Volver Gestión Residuos</a><strong style="font-size:1.15rem">Molino</strong></div>';
    if ($currentShiftSession !== null) {
        $body .= '<form method="post" action="/production/shifts/end" style="margin:0">';
        $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
        $body .= '<input type="hidden" name="shift_session_id" value="' . (int)$currentShiftSession['id'] . '">';
        $body .= '<button class="btn secondary" type="submit" style="min-width:220px">Finalizar turno</button></form>';
    }
    $body .= '</div>';

    if ($currentShiftSession !== null) {
        $machineName = trim((string)($currentShiftSession['machine_name'] ?? ''));
        $machineType = trim((string)($currentShiftSession['machine_type_name'] ?? ''));
        $stageLabel = machineProcessStageLabel((string)($currentShiftSession['production_area'] ?? $currentShiftSession['machine_type_area'] ?? 'RESIDUOS'));
        $start = trim((string)($currentShiftSession['started_at'] ?? ''));
        $body .= '<div class="card" style="margin-bottom:12px"><div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;align-items:center">';
        $body .= '<div><div class="muted">Operador</div><div style="font-weight:700">' . h($currentOperatorName) . '</div></div>';
        $body .= '<div><div class="muted">Tipo</div><div style="font-weight:700">' . h($machineType !== '' ? $machineType : 'gestion residuo') . '</div></div>';
        $body .= '<div><div class="muted">Máquina</div><div style="font-weight:700">' . h($machineName !== '' ? $machineName : 'gestion 1') . '</div></div>';
        $body .= '<div><div class="muted">Etapa · Inicio</div><div style="font-weight:700">' . h($stageLabel) . ($start !== '' ? ' · ' . h($start) : '') . '</div></div>';
        $body .= '</div></div>';
    } else {
        $body .= '<div class="card" style="margin-bottom:12px"><div style="font-weight:800;margin-bottom:8px">Sin turno activo</div><div class="muted" style="margin-bottom:10px">Antes de registrar operaciones de molino, inicia un turno en la máquina de gestión de residuos.</div>';
        $body .= '<a class="btn" href="/production/shifts">Ir a iniciar turno</a></div>';
    }

    $activeOpId = $activeMolinoOp !== null ? (int)($activeMolinoOp['id'] ?? 0) : 0;
    $entryKg = $activeMolinoOp !== null ? (float)($activeMolinoOp['entry_kg'] ?? 0.0) : 0.0;

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="3">Inventario</th></tr></thead><tbody>';
    $body .= '<tr>';
    $body .= '<td class="legacy-label-cell" style="width:34%">Materialidad</td>';
    $body .= '<td class="legacy-label-cell" style="width:33%">Peso</td>';
    $body .= '<td class="legacy-label-cell" style="width:33%">Procesar</td>';
    $body .= '</tr><tr>';
    $body .= '<td class="legacy-value-cell"><strong>PLA</strong></td>';
    $body .= '<td class="legacy-value-cell"><strong>' . h(number_format($plaStockKg, 3, '.', '')) . ' kg</strong></td>';
    if ($activeOpId > 0) {
        $body .= '<td class="legacy-value-cell" style="text-align:center"><span class="muted" style="font-weight:700;color:#12805c">En proceso · ' . h(number_format($entryKg, 3, '.', '')) . ' kg ingresados</span></td>';
    } else {
        $disabled = $plaStockKg <= 0 || $currentShiftSession === null ? ' disabled style="opacity:.55;cursor:not-allowed"' : '';
        $body .= '<td class="legacy-value-cell" style="text-align:center"><button type="button" class="btn" style="padding:6px 14px" data-wm-open="molino-entry"' . $disabled . ($plaStockKg <= 0 ? ' title="Sin stock PLA disponible"' : ($currentShiftSession === null ? ' title="Inicia un turno primero"' : '')) . '>Procesar</button></td>';
    }
    $body .= '</tr></tbody></table></div>';

    $body .= '<div class="legacy-setup-actions" style="grid-template-columns:repeat(3,minmax(0,1fr));margin:0 0 12px">';
    if ($activeOpId > 0) {
        $body .= '<button type="button" class="legacy-setup-action pause" style="width:100%;border:0;cursor:pointer" data-wm-open="molino-pause">Pausar</button>';
        $body .= '<button type="button" class="legacy-setup-action maintenance" style="width:100%;border:0;cursor:pointer" data-wm-open="molino-maintenance">Mantencion</button>';
    } else {
        $body .= '<button type="button" class="legacy-setup-action pause" style="width:100%;border:0;cursor:not-allowed;opacity:.55" disabled title="Debe iniciar un proceso (Procesar) antes de pausar">Pausar</button>';
        $body .= '<button type="button" class="legacy-setup-action maintenance" style="width:100%;border:0;cursor:not-allowed;opacity:.55" disabled title="Debe iniciar un proceso (Procesar) antes de registrar mantención">Mantencion</button>';
    }
    if ($activeOpId > 0) {
        $body .= '<button type="button" class="legacy-setup-action consumption" style="width:100%;border:0;cursor:pointer" data-wm-open="molino-finalize">Finalizar</button>';
    } else {
        $body .= '<button type="button" class="legacy-setup-action consumption" style="width:100%;border:0;cursor:not-allowed;opacity:.55" disabled title="Debe iniciar un proceso (Procesar) antes de finalizar">Finalizar</button>';
    }
    $body .= '</div>';

    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><div class="table-wrap"><table class="legacy-sheet-table legacy-setup-event-table" style="margin:0"><thead><tr><th>Evento</th><th>Inicio</th><th>Termino</th><th>Tiempo</th><th>Cantidad</th><th>Comentarios</th><th>Opciones</th></tr></thead><tbody>';
    if ($productionEvents === []) {
        $body .= '<tr><td colspan="7" class="muted" style="text-align:center;padding:12px">Sin producción en curso.</td></tr>';
    }
    foreach ($productionEvents as $ev) {
        $badgeType = trim((string)($ev['option_badge_type'] ?? 'configured'));
        $badgeLabel = trim((string)($ev['option_label'] ?? '-'));
        $optionHtml = '';
        if ($badgeType === 'configured') {
            $optionHtml = '<span class="btn secondary legacy-setup-finish-btn" style="opacity:.75;cursor:default">' . h($badgeLabel) . '</span>';
        } elseif ($badgeType === 'finish-production') {
            $trigger = (string)($ev['option_trigger'] ?? 'molino-finalize');
            $optionHtml = '<button type="button" class="btn secondary legacy-setup-finish-btn" data-wm-open="' . h($trigger) . '">' . h($badgeLabel) . '</button>';
        } elseif ($badgeType === 'finish-event') {
            $formAction = (string)($ev['option_form_action'] ?? '/waste/operations');
            $formParams = (array)($ev['option_form_params'] ?? []);
            $inputs = '';
            foreach ($formParams as $k => $v) {
                $inputs .= '<input type="hidden" name="' . h((string)$k) . '" value="' . h((string)$v) . '">';
            }
            $optionHtml = '<form method="post" action="' . h($formAction) . '" style="margin:0">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . $inputs
                . '<button class="btn secondary legacy-setup-finish-btn" type="submit">' . h($badgeLabel) . '</button>'
                . '</form>';
        } else {
            $href = (string)($ev['option_href'] ?? '#');
            $optionHtml = '<a class="btn secondary legacy-setup-finish-btn" href="' . h($href) . '">' . h($badgeLabel) . '</a>';
        }
        $body .= '<tr>'
            . '<td>' . h((string)($ev['event'] ?? '-')) . '</td>'
            . '<td>' . h((string)($ev['start_at'] ?? '-')) . '</td>'
            . '<td>' . h((string)($ev['end_at'] ?? '-')) . '</td>'
            . '<td>' . h((string)($ev['duration_label'] ?? '-')) . '</td>'
            . '<td>' . h((string)($ev['quantity_label'] ?? '-')) . '</td>'
            . '<td>' . h((string)($ev['comments'] ?? '-')) . '</td>'
            . '<td>' . $optionHtml . '</td>'
            . '</tr>';
    }
    $body .= '</tbody></table></div></div>';

    // ====== MODAL PAUSA MOLINO ======
    $body .= '<div class="wm-modal-backdrop" id="wm-molino-pause" data-wm-modal="molino-pause" style="display:none !important;position:fixed !important;inset:0 !important;z-index:9998 !important">';
    $body .= '<div class="wm-modal"><div class="wm-modal-head"><h2 class="wm-modal-title">Registrar pausa</h2><button class="wm-modal-close" type="button" data-wm-close="molino-pause">Cerrar</button></div>';
    $body .= '<div class="wm-modal-body">';
    $body .= '<form method="post" action="/waste/operations" style="margin:0">';
    $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    $body .= '<input type="hidden" name="operation_code" value="PAUSA_MOLINO_INICIO">';
    $body .= '<div class="legacy-sheet-card" style="margin:0 0 12px"><table class="legacy-sheet-table"><thead><tr><th colspan="2">Información</th></tr></thead><tbody>';
    $body .= '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell"><strong>Pausa</strong></td></tr>';
    $body .= '<tr><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>';
    $body .= '<tr><td class="legacy-label-cell">Comentario <span style="color:#c53030">*</span></td><td class="legacy-value-cell"><textarea name="comments" rows="4" required placeholder="Indica el motivo de la pausa." style="width:100%;border:1px solid #cfd4dc;border-radius:6px;padding:8px;box-sizing:border-box"></textarea></td></tr>';
    $body .= '</tbody></table></div>';
    $body .= '<div style="display:flex;gap:10px;justify-content:space-between">';
    $body .= '<button type="button" class="btn secondary" data-wm-close="molino-pause" style="min-width:160px">Cancelar</button>';
    $body .= '<button type="submit" class="btn" style="min-width:200px;background:#a9a9a9;border-color:#a9a9a9;color:#fff;font-weight:800">Guardar</button>';
    $body .= '</div>';
    $body .= '</form></div></div></div>';

    // ====== MODAL MANTENCION MOLINO ======
    $body .= '<div class="wm-modal-backdrop" id="wm-molino-maintenance" data-wm-modal="molino-maintenance" style="display:none !important;position:fixed !important;inset:0 !important;z-index:9998 !important">';
    $body .= '<div class="wm-modal"><div class="wm-modal-head"><h2 class="wm-modal-title">Registrar mantención</h2><button class="wm-modal-close" type="button" data-wm-close="molino-maintenance">Cerrar</button></div>';
    $body .= '<div class="wm-modal-body">';
    $body .= '<form method="post" action="/waste/operations" style="margin:0">';
    $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    $body .= '<input type="hidden" name="operation_code" value="MANTENCION_MOLINO_INICIO">';
    $body .= '<div class="legacy-sheet-card" style="margin:0 0 12px"><table class="legacy-sheet-table"><thead><tr><th colspan="2">Información</th></tr></thead><tbody>';
    $body .= '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell"><strong>Mantención</strong></td></tr>';
    $body .= '<tr><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>';
    $body .= '<tr><td class="legacy-label-cell">Comentario <span style="color:#c53030">*</span></td><td class="legacy-value-cell"><textarea name="comments" rows="4" required placeholder="Describe la mantención realizada o el motivo del registro." style="width:100%;border:1px solid #cfd4dc;border-radius:6px;padding:8px;box-sizing:border-box"></textarea></td></tr>';
    $body .= '</tbody></table></div>';
    $body .= '<div style="display:flex;gap:10px;justify-content:space-between">';
    $body .= '<button type="button" class="btn secondary" data-wm-close="molino-maintenance" style="min-width:160px">Cancelar</button>';
    $body .= '<button type="submit" class="btn" style="min-width:200px;background:#d94841;border-color:#d94841;color:#fff;font-weight:800">Guardar</button>';
    $body .= '</div>';
    $body .= '</form></div></div></div>';

    // ====== MODAL INGRESO MOLINO (Inventario) ======
    $body .= '<div class="wm-modal-backdrop" id="wm-molino-entry" data-wm-modal="molino-entry" style="display:none !important;position:fixed !important;inset:0 !important;z-index:9998 !important">';
    $body .= '<div class="wm-modal"><div class="wm-modal-head"><h2 class="wm-modal-title">Ingreso Molino</h2><button class="wm-modal-close" type="button" data-wm-close="molino-entry">Cerrar</button></div>';
    $body .= '<div class="wm-modal-body">';
    $body .= '<form method="post" action="/waste/molino/ingreso" id="wm-molino-entry-form" data-wm-form="molino-entry" style="margin:0">';
    $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    $body .= '<div class="legacy-sheet-card" style="margin:0 0 12px"><table class="legacy-sheet-table"><thead><tr><th colspan="2">Inventario</th></tr></thead><tbody>';
    $body .= '<tr><td class="legacy-label-cell">Materialidad</td><td class="legacy-value-cell"><strong>PLA</strong><input type="hidden" name="material_code" value="PLA"></td></tr>';
    $body .= '<tr><td class="legacy-label-cell">Peso (disponible bodega)</td><td class="legacy-value-cell"><strong>' . h(number_format($plaStockKg, 3, '.', '')) . ' kg</strong></td></tr>';
    $body .= '<tr><td class="legacy-label-cell">Ingreso (kg) <span style="color:#c53030">*</span></td><td class="legacy-value-cell"><input type="number" name="entry_kg" min="0.001" step="0.001" max="' . h(number_format($plaStockKg, 3, '.', '')) . '" placeholder="Ej: 12.500" required style="width:100%;padding:6px 8px;border:1px solid #bbb;border-radius:4px;box-sizing:border-box" value="' . h(number_format($plaStockKg, 3, '.', '')) . '"></td></tr>';
    $body .= '</tbody></table></div>';
    $body .= '<div style="display:flex;justify-content:space-between;gap:10px">';
    $body .= '<button type="button" class="btn secondary" data-wm-close="molino-entry" style="min-width:160px">Cancelar</button>';
    $body .= '<button type="submit" class="btn" style="min-width:200px;background:#4256a5;border-color:#4256a5;color:#fff;font-weight:700">Finalizar ingreso</button>';
    $body .= '</div>';
    $body .= '</form></div></div></div>';

    // ====== MODAL FINALIZAR MOLINO (Información salida) ======
    if ($activeOpId > 0) {
        $body .= '<div class="wm-modal-backdrop" id="wm-molino-finalize" data-wm-modal="molino-finalize" style="display:none !important;position:fixed !important;inset:0 !important;z-index:9998 !important">';
        $body .= '<div class="wm-modal"><div class="wm-modal-head"><h2 class="wm-modal-title">Finalizar Molino</h2><button class="wm-modal-close" type="button" data-wm-close="molino-finalize">Cerrar</button></div>';
        $body .= '<div class="wm-modal-body">';
        $body .= '<form method="post" action="/waste/molino/finalizar" id="wm-molino-finalize-form" data-wm-form="molino-finalize" style="margin:0">';
        $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
        $body .= '<input type="hidden" name="operation_id" value="' . $activeOpId . '">';
        $body .= '<div class="legacy-sheet-card" style="margin:0 0 12px"><table class="legacy-sheet-table"><thead><tr><th colspan="2">Información</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Materialidad</td><td class="legacy-value-cell"><strong>PLA</strong></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">Peso (ingreso)</td><td class="legacy-value-cell"><strong>' . h(number_format($entryKg, 3, '.', '')) . ' kg</strong></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">Salida (kg) <span style="color:#c53030">*</span></td><td class="legacy-value-cell"><input type="number" name="exit_kg" min="0.001" step="0.001" placeholder="Ej: 11.800" required style="width:100%;padding:6px 8px;border:1px solid #bbb;border-radius:4px;box-sizing:border-box"></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">Cantidad de palet</td><td class="legacy-value-cell"><input type="number" name="pallet_count" min="0" step="1" value="1" style="width:100%;padding:6px 8px;border:1px solid #bbb;border-radius:4px;box-sizing:border-box"></td></tr>';
        $body .= '</tbody></table></div>';
        $body .= '<div style="display:flex;justify-content:space-between;gap:10px">';
        $body .= '<button type="button" class="btn secondary" data-wm-close="molino-finalize" style="min-width:160px">Cancelar</button>';
        $body .= '<button type="submit" class="btn" style="min-width:200px;background:#d8a103;border-color:#d8a103;color:#fff;font-weight:700">Finalizar</button>';
        $body .= '</div>';
        $body .= '</form></div></div></div>';
    }

    // ====== CONTROLADORES MODAL MOLINO ======
    $body .= '<script>(function(){
function openWm(id){var m=document.querySelector("[data-wm-modal=\x27"+id+"\x27]");if(m){m.classList.add("is-open");m.style.display="flex";document.body.style.overflow="hidden";}}
function closeWm(id){var m=document.querySelector("[data-wm-modal=\x27"+id+"\x27]");if(m){m.classList.remove("is-open");m.style.display="none";document.body.style.overflow="";}}
document.addEventListener("click",function(e){
  var t=e.target;var openr=t.closest && t.closest("[data-wm-open]");if(openr){e.preventDefault();openWm(openr.getAttribute("data-wm-open"));return;}
  var closr=t.closest && t.closest("[data-wm-close]");if(closr){e.preventDefault();closeWm(closr.getAttribute("data-wm-close"));return;}
  if(t.classList && t.classList.contains("wm-modal-backdrop") && t.getAttribute("data-wm-modal")){
    if(t===e.target){closeWm(t.getAttribute("data-wm-modal"));}
  }
});
var arr=document.querySelectorAll(".wm-modal-backdrop");arr.forEach(function(b){b.style.display="none";});
})();</script>';

    render('Molino - Gestión Residuos', $body);
}


function handleWasteMolinoRoutes(string $path, string $method, ReceptionService $service, string $currentOperatorName): bool
{
if ($path === '/waste/molino' && $method === 'GET') {
    $currentShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    $sessionId = $currentShiftSession !== null ? (int)$currentShiftSession['id'] : null;
    $inventoryTotals = $service->listWastePendingInventoryTotals();
    $plaStockKg = (float)($inventoryTotals['PLA'] ?? 0.0);
    $activeMolinoOp = $service->getActiveMolinoOperation($currentOperatorName, $sessionId);
    $productionEvents = $service->listMolinoProductionEvents($currentOperatorName, $sessionId);
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['entry_saved']) && $_GET['entry_saved'] === '1') {
        $flashMessage = 'Ingreso a molino registrado. Ya puedes procesar y finalizar cuando termines.';
    } elseif (isset($_GET['molino_finalized']) && $_GET['molino_finalized'] === '1') {
        $flashMessage = 'Molino finalizado correctamente. Volviendo a gestión de residuos.';
    } elseif (isset($_GET['operation_saved']) && $_GET['operation_saved'] === '1') {
        $flashMessage = 'Operación de molino registrada correctamente.';
    } elseif (isset($_GET['error']) && trim((string)$_GET['error']) !== '') {
        $flashMessage = trim((string)$_GET['error']);
        $flashIsError = true;
    }
    renderWasteMolinoScreen($currentShiftSession, $currentOperatorName, $plaStockKg, $activeMolinoOp, $productionEvents, $flashMessage, $flashIsError);
    exit;
}

if ($path === '/waste/molino/ingreso' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $currentShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    $sessionId = $currentShiftSession !== null ? (int)$currentShiftSession['id'] : null;
    $materialCode = trim((string)($_POST['material_code'] ?? ''));
    $entryKg = (float)($_POST['entry_kg'] ?? 0);
    $result = $service->recordMolinoEntry($materialCode, $entryKg, $currentOperatorName, $sessionId);
    if (($result['ok'] ?? false) === true) {
        header('Location: /waste/molino?entry_saved=1');
        exit;
    }
    $errList = $result['errors'] ?? [];
    $error = $errList !== [] ? trim((string)reset($errList)) : '';
    header('Location: /waste/molino?error=' . rawurlencode($error !== '' ? $error : 'No se pudo registrar el ingreso a molino.'));
    exit;
}

if ($path === '/waste/molino/finalizar' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $opId = (int)($_POST['operation_id'] ?? 0);
    $exitKg = (float)($_POST['exit_kg'] ?? 0);
    $palletCount = (int)($_POST['pallet_count'] ?? 0);
    $result = $service->finalizeMolinoOperation($opId, $exitKg, $palletCount, $currentOperatorName);
    if (($result['ok'] ?? false) === true) {
        header('Location: /waste/management?operation_saved=1');
        exit;
    }
    $errList = $result['errors'] ?? [];
    $error = $errList !== [] ? trim((string)reset($errList)) : '';
    header('Location: /waste/molino?error=' . rawurlencode($error !== '' ? $error : 'No se pudo finalizar el molino.'));
    exit;
}

    return false;
}
