<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Panel Central de Residuos, Inventario y Retiros (src/Http/Waste/WasteManagementModule.php)
// =============================================================================

require_once __DIR__ . '/WasteMolinoModule.php';
require_once __DIR__ . '/WasteCompactadoraModule.php';

function wasteOperationLabel(string $code): string
{
    $labels = [
        'MOLINO' => 'Molino',
        'COMPACTADORA' => 'Compactadora',
        'RETIRO' => 'Retiro',
        'CREACION_MERMA' => 'Creación merma',
        'RESPEL' => 'Respel',
        'PAUSA_MOLINO' => 'Pausa Molino',
        'MANTENCION_MOLINO' => 'Mantención Molino',
    ];
    return $labels[strtoupper(trim($code))] ?? trim($code);
}


function renderWasteManagementScreen(
    ?array $currentShiftSession,
    string $currentOperatorName,
    array $inventoryTotals,
    array $recentOperations,
    array $pendingWithdrawals,
    array $creationMaterialOptions,
    array $creationAreaOptions,
    array $creationMotivoOptions,
    ?string $flashMessage = null,
    bool $flashIsError = false
): void {
    $body = '';

    $body .= '<style>
.wm-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,0.55);display:none;align-items:flex-start;justify-content:center;z-index:9998;padding:28px 16px;overflow-y:auto}
.wm-modal-backdrop.is-open{display:flex}
.wm-modal{width:100%;max-width:1060px;background:#fff;border-radius:10px;box-shadow:0 20px 60px rgba(0,0,0,0.28);margin:auto 0}
.wm-modal-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #e5e7eb}
.wm-modal-title{font-size:18px;font-weight:800;margin:0}
.wm-modal-close{background:transparent;border:1px solid #d1d5db;color:#374151;border-radius:6px;padding:6px 12px;cursor:pointer;font-weight:600}
.wm-modal-close:hover{background:#f9fafb}
.wm-modal-body{padding:16px 18px}
.wm-filter-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:flex-end}
.wm-filter-row .wm-filter{flex:1 1 220px;min-width:180px}
.wm-filter label{display:block;font-size:12px;color:#374151;margin-bottom:4px;font-weight:600}
.wm-filter select,.wm-filter input{width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box}
.wm-withdraw-table{width:100%;border-collapse:collapse;font-size:13px}
.wm-withdraw-table th,.wm-withdraw-table td{padding:10px 8px;border-bottom:1px solid #eef0f3;text-align:left;vertical-align:middle}
.wm-withdraw-table thead th{background:#f9fafb;font-weight:700;color:#111827;font-size:12px;text-transform:uppercase;letter-spacing:0.3px}
.wm-withdraw-table tbody tr:hover{background:#fafbfc}
.wm-chip{display:inline-block;padding:3px 8px;border-radius:999px;background:#eef2ff;color:#4338ca;font-weight:600;font-size:11px}
.wm-chip.off{background:#f3f4f6;color:#6b7280}
.wm-nest-title{font-weight:700;margin:0 0 10px;padding-bottom:8px;border-bottom:1px solid #eef0f3}
.wm-nest-grid{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;margin-bottom:14px}
.wm-nest-grid .wm-nest-field{display:flex;flex-direction:column;gap:4px}
.wm-nest-field label{font-size:12px;color:#374151;font-weight:600}
.wm-nest-field input,.wm-nest-field select{padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box}
.wm-nest-field input[readonly]{background:#f3f4f6;color:#111827}
.wm-nest-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:8px}
.wm-check-td{text-align:center}
</style>';

    if ($flashMessage !== null && trim($flashMessage) !== '' && $flashIsError) {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    } elseif ($flashMessage !== null && trim($flashMessage) !== '' && !$flashIsError && !str_contains(mb_strtolower($flashMessage), 'turno iniciado')) {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    if ($currentShiftSession !== null) {
        $body .= '<div class="card" style="margin-bottom:12px"><div style="font-weight:800;margin-bottom:8px">Finalizar turno</div><div class="ot-meta-grid">';
        $body .= '<div class="ot-meta"><div class="muted">Operador</div><div class="value">' . h((string)($currentShiftSession['operator_name'] ?? $currentOperatorName)) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Tipo</div><div class="value">' . h((string)($currentShiftSession['machine_type_name'] ?? '-')) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Máquina</div><div class="value">' . h((string)($currentShiftSession['machine_name'] ?? '-')) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Etapa</div><div class="value">' . h(machineProcessStageLabel((string)($currentShiftSession['process_stage'] ?? ''))) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Turno</div><div class="value">' . h((string)($currentShiftSession['shift_label'] ?? '-')) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Inicio</div><div class="value">' . h((string)($currentShiftSession['started_at'] ?? '-')) . '</div></div>';
        $body .= '</div>';
        $body .= '<form method="post" action="/production/shifts/' . (int)$currentShiftSession['id'] . '/end" style="margin-top:12px">';
        $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
        $body .= '<button class="btn secondary" type="submit" style="min-width:220px">Finalizar turno</button></form></div>';
    } else {
        $body .= '<div class="card" style="margin-bottom:12px"><div style="font-weight:800;margin-bottom:8px">Sin turno activo</div><div class="muted" style="margin-bottom:10px">Antes de registrar mermas o operaciones, inicia un turno en la máquina de gestión de residuos.</div>';
        $body .= '<a class="btn" href="/production/shifts">Ir a iniciar turno</a></div>';
    }

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Inventario merma (bodega transitoria)</th></tr></thead><tbody>';
    $materials = [
        ['code' => 'PP', 'label' => 'PP', 'value' => (float)($inventoryTotals['PP'] ?? 0.0)],
        ['code' => 'PLA', 'label' => 'PLA', 'value' => (float)($inventoryTotals['PLA'] ?? 0.0)],
        ['code' => 'FILM', 'label' => 'FILM', 'value' => (float)($inventoryTotals['FILM'] ?? 0.0)],
    ];
    $totalKg = 0.0;
    foreach ($materials as $mat) {
        $totalKg += (float)$mat['value'];
    }
    $body .= '<tr>';
    $body .= '<td class="legacy-label-cell">PP</td><td class="legacy-value-cell"><strong>' . h(number_format((float)$materials[0]['value'], 3, '.', '')) . ' kg</strong></td>';
    $body .= '<td class="legacy-label-cell">PLA</td><td class="legacy-value-cell"><strong>' . h(number_format((float)$materials[1]['value'], 3, '.', '')) . ' kg</strong></td>';
    $body .= '</tr>';
    $body .= '<tr>';
    $body .= '<td class="legacy-label-cell">FILM</td><td class="legacy-value-cell"><strong>' . h(number_format((float)$materials[2]['value'], 3, '.', '')) . ' kg</strong></td>';
    $body .= '<td class="legacy-label-cell">Total merma</td><td class="legacy-value-cell"><strong>' . h(number_format((float)$totalKg, 3, '.', '')) . ' kg</strong></td>';
    $body .= '</tr>';
    $body .= '</tbody></table></div>';

    $body .= '<div class="legacy-setup-actions" style="grid-template-columns:repeat(5,minmax(0,1fr));margin:0 0 12px">';
    $body .= '<a href="/waste/molino" class="legacy-setup-action materials" style="display:block;text-align:center;text-decoration:none;padding:12px 8px;border:0;cursor:pointer">Molino</a>';
    $body .= '<a href="/waste/compactadora" class="legacy-setup-action production" style="display:block;text-align:center;text-decoration:none;padding:12px 8px;border:0;cursor:pointer">Compactadora</a>';
    $body .= '<button type="button" class="legacy-setup-action maintenance" style="width:100%;border:0;cursor:pointer" data-wm-open="creation-main">Creación merma</button>';
    $body .= '<form method="post" action="/waste/operations" style="margin:0"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="operation_code" value="RESPEL"><button type="submit" class="legacy-setup-action pause" style="width:100%;border:0;cursor:pointer">Respel</button></form>';
    $body .= '<button type="button" class="legacy-setup-action consumption" style="width:100%;border:0;cursor:pointer" data-wm-open="withdrawal-main">Retiro</button>';
    $body .= '</div>';

    $machineOptions = [];
    $operatorOptions = [];
    $materialOptions = [];
    foreach ($pendingWithdrawals as $e) {
        $m = trim((string)($e['supplier_machine_name'] ?? ''));
        $o = trim((string)($e['supplier_operator_name'] ?? ''));
        $mat = strtoupper(trim((string)($e['material_code'] ?? '')));
        if ($m !== '') {
            $machineOptions[$m] = true;
        }
        if ($o !== '') {
            $operatorOptions[$o] = true;
        }
        if ($mat !== '' && in_array($mat, ['PP', 'PLA', 'FILM'], true)) {
            $materialOptions[$mat] = true;
        }
    }
    $machineOptions = array_keys($machineOptions);
    $operatorOptions = array_keys($operatorOptions);
    $materialOptions = array_keys($materialOptions);
    natcasesort($machineOptions);
    natcasesort($operatorOptions);
    natcasesort($materialOptions);
    $machineOptions = array_values($machineOptions);
    $operatorOptions = array_values($operatorOptions);
    $materialOptions = array_values($materialOptions);

    $body .= '<div class="wm-modal-backdrop" id="wm-creation-main" data-wm-modal="creation-main">';
    $body .= '<div class="wm-modal"><div class="wm-modal-head"><h2 class="wm-modal-title">Creación de merma</h2><button class="wm-modal-close" type="button" data-wm-close="creation-main">Cerrar</button></div>';
    $body .= '<div class="wm-modal-body">';
    $body .= '<form method="post" action="/waste/creation" id="wm-creation-form" data-wm-form="creation-main" style="margin:0">';
    $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    $body .= '<div class="legacy-sheet-card" style="margin:0 0 12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Datos de la merma</th></tr></thead><tbody>';
    $body .= '<tr>';
    $body .= '<td class="legacy-label-cell">Materialidad <span style="color:#c53030">*</span></td>';
    $body .= '<td class="legacy-value-cell" style="padding:0"><select name="material_code" required style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"><option value="">Selecciona</option>';
    foreach ($creationMaterialOptions as $opt) {
        $code = trim((string)($opt['code'] ?? ''));
        $label = trim((string)($opt['label'] ?? ''));
        if ($code === '' || $label === '') { continue; }
        $body .= '<option value="' . h($code) . '">' . h($label) . '</option>';
    }
    $body .= '</select></td>';
    $body .= '<td class="legacy-label-cell">Peso (kg) <span style="color:#c53030">*</span></td>';
    $body .= '<td class="legacy-value-cell" style="padding:0"><input type="number" name="weight_kg" min="0.001" step="0.001" required placeholder="Ej: 12.500" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"></td>';
    $body .= '</tr>';
    $body .= '<tr>';
    $body .= '<td class="legacy-label-cell">Solicitante <span style="color:#c53030">*</span></td>';
    $body .= '<td class="legacy-value-cell" colspan="3" style="padding:0"><input type="text" name="solicitante" required maxlength="120" placeholder="Nombre de quién solicita la merma" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"></td>';
    $body .= '</tr>';
    $body .= '<tr>';
    $body .= '<td class="legacy-label-cell">Área <span style="color:#c53030">*</span></td>';
    $body .= '<td class="legacy-value-cell" style="padding:0"><select name="area" required style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"><option value="">Selecciona</option>';
    foreach ($creationAreaOptions as $opt) {
        $code = trim((string)($opt['code'] ?? ''));
        $label = trim((string)($opt['label'] ?? ''));
        if ($code === '' || $label === '') { continue; }
        $body .= '<option value="' . h($code) . '">' . h($label) . '</option>';
    }
    $body .= '</select></td>';
    $body .= '<td class="legacy-label-cell">Motivo <span style="color:#c53030">*</span></td>';
    $body .= '<td class="legacy-value-cell" style="padding:0"><select name="motivo" required style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"><option value="">Selecciona</option>';
    foreach ($creationMotivoOptions as $opt) {
        $code = trim((string)($opt['code'] ?? ''));
        $label = trim((string)($opt['label'] ?? ''));
        if ($code === '' || $label === '') { continue; }
        $body .= '<option value="' . h($code) . '">' . h($label) . '</option>';
    }
    $body .= '</select></td>';
    $body .= '</tr>';
    $body .= '</tbody></table></div>';
    $body .= '<div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap">';
    $body .= '<button type="button" class="btn" style="background:#a9a9a9;color:#fff" data-wm-close="creation-main">Cancelar</button>';
    $body .= '<button type="submit" class="btn" style="background:#d94841;color:#fff" id="wm-creation-submit">Registrar merma</button>';
    $body .= '</div>';
    $body .= '</form>';
    $body .= '</div></div></div>';

    $body .= '<div class="wm-modal-backdrop" id="wm-withdrawal-main" data-wm-modal="withdrawal-main">';
    $body .= '<div class="wm-modal"><div class="wm-modal-head"><h2 class="wm-modal-title">Retiro de merma</h2><button class="wm-modal-close" type="button" data-wm-close="withdrawal-main">Cerrar</button></div>';
    $body .= '<div class="wm-modal-body">';

    $body .= '<div class="wm-filter-row">';
    $body .= '<div class="wm-filter"><label for="wm-f-machine">Máquina</label><select id="wm-f-machine"><option value="">Todas</option>';
    foreach ($machineOptions as $mn) {
        $body .= '<option value="' . h($mn) . '">' . h($mn) . '</option>';
    }
    $body .= '</select></div>';
    $body .= '<div class="wm-filter"><label for="wm-f-operator">Operador</label><select id="wm-f-operator"><option value="">Todos</option>';
    foreach ($operatorOptions as $opn) {
        $body .= '<option value="' . h($opn) . '">' . h($opn) . '</option>';
    }
    $body .= '</select></div>';
    $body .= '<div class="wm-filter"><label for="wm-f-material">Materialidad</label><select id="wm-f-material"><option value="">Todas</option>';
    foreach ($materialOptions as $matn) {
        $body .= '<option value="' . h($matn) . '">' . h($matn) . '</option>';
    }
    $body .= '</select></div>';
    $body .= '</div>';

    $body .= '<div class="erp-prod-table-wrap" style="margin-bottom:14px"><table class="wm-withdraw-table" id="wm-withdraw-list"><thead><tr>';
    $body .= '<th class="wm-check-td"><input type="checkbox" id="wm-check-all" title="Seleccionar todo"></th>';
    $body .= '<th>Hora</th><th>Máquina</th><th>Operador</th><th>Materialidad</th><th>Peso merma</th><th style="width:180px"></th>';
    $body .= '</tr></thead><tbody>';
    foreach ($pendingWithdrawals as $e) {
        $entryId = (int)($e['id'] ?? 0);
        $mName = trim((string)($e['supplier_machine_name'] ?? ''));
        $mCode = trim((string)($e['supplier_machine_code'] ?? ''));
        $operatorName = trim((string)($e['supplier_operator_name'] ?? ''));
        $material = strtoupper(trim((string)($e['material_code'] ?? '')));
        $weight = (float)($e['weight_kg'] ?? 0);
        $created = trim((string)($e['created_at'] ?? ''));
        $timeDisplay = $created !== '' ? mb_substr($created, 11, 5) : '';
        $body .= '<tr data-entry="' . $entryId . '" data-machine="' . h($mName) . '" data-operator="' . h($operatorName) . '" data-material="' . h($material) . '">';
        $body .= '<td class="wm-check-td"><input type="checkbox" class="wm-row-check" value="' . $entryId . '"></td>';
        $body .= '<td class="muted" style="white-space:nowrap">' . h($timeDisplay) . '</td>';
        $body .= '<td><strong>' . h($mName !== '' ? $mName : '—') . '</strong></td>';
        $body .= '<td><span class="wm-chip' . ($operatorName === '' ? ' off' : '') . '">' . h($operatorName !== '' ? $operatorName : 'Sin asignar') . '</span></td>';
        $body .= '<td class="wm-row-material"><strong>' . h($material) . '</strong></td>';
        $body .= '<td class="wm-row-weight" data-weight="' . h(number_format($weight, 3, '.', '')) . '"><strong>' . h(number_format($weight, 3, '.', '')) . ' kg</strong></td>';
        $body .= '<td style="text-align:right"><button type="button" class="btn wm-row-retire"
            data-entry-id="' . $entryId . '"
            data-machine-code="' . h($mCode) . '"
            data-machine-name="' . h($mName) . '"
            data-operator-name="' . h($operatorName) . '"
            data-material="' . h($material) . '"
            data-weight="' . h(number_format($weight, 3, '.', '')) . '">Retirar</button></td>';
        $body .= '</tr>';
    }
    if ($pendingWithdrawals === []) {
        $body .= '<tr><td colspan="7" class="muted" style="text-align:center;padding:20px">No hay sacos de merma pendientes por retirar hoy.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px">';
    $body .= '<button type="button" class="btn secondary" data-wm-close="withdrawal-main">Cancelar</button>';
    $body .= '<button type="button" class="btn" id="wm-combined-retire" style="background:#0f766e;color:#fff">Retiro combinado</button>';
    $body .= '</div>';

    $body .= '<hr style="margin:16px 0;border:0;border-top:1px solid #eef0f3">';
    $body .= '<h3 class="wm-nest-title" id="wm-nest-head">Confirmar retiro</h3>';
    $body .= '<form id="wm-nest-form" method="post" action="/waste/withdrawal">';
    $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    $body .= '<input type="hidden" name="entry_ids" id="wm-n-entry-ids">';
    $body .= '<input type="hidden" name="machine_code" id="wm-n-machine-code">';
    $body .= '<div class="wm-nest-grid">';
    $body .= '<div class="wm-nest-field"><label>Máquina</label><input type="text" id="wm-n-machine-name" name="machine_name" readonly></div>';
    $body .= '<div class="wm-nest-field"><label>Operador</label><input type="text" id="wm-n-operator" name="supplier_operator_name" readonly></div>';
    $body .= '<div class="wm-nest-field"><label for="wm-n-material">Materialidad</label><select id="wm-n-material" name="material_code" required><option value="">Selecciona</option><option value="PP">PP</option><option value="PLA">PLA</option><option value="FILM">FILM</option></select></div>';
    $body .= '<div class="wm-nest-field"><label for="wm-n-weight">Peso merma (kg)</label><input id="wm-n-weight" name="weight_kg" type="number" min="0" step="0.001" placeholder="Ingresa peso merma" required></div>';
    $body .= '</div>';
    $body .= '<div id="wm-n-combined-area" style="display:none;margin-bottom:14px">';
    $body .= '<div class="muted" style="margin-bottom:6px">Retiro combinado: marcarás como retirados todos los sacos seleccionados. Material y peso se usan como resumen del lote.</div>';
    $body .= '</div>';
    $body .= '<div class="wm-nest-actions">';
    $body .= '<button type="button" class="btn secondary" id="wm-nest-cancel">Cancelar retiro</button>';
    $body .= '<button type="submit" class="btn" style="background:#0f766e;color:#fff">Confirmar retiro</button>';
    $body .= '</div>';
    $body .= '</form>';

    $body .= '</div></div></div>';

    $body .= '<script>(function(){
var openers = document.querySelectorAll("[data-wm-open]");
openers.forEach(function(b){b.addEventListener("click", function(){var id = b.getAttribute("data-wm-open"); var m = document.querySelector("[data-wm-modal=\x27"+id+"\x27]"); if(m){m.classList.add("is-open"); document.body.style.overflow = "hidden";}});});
var closers = document.querySelectorAll("[data-wm-close]");
closers.forEach(function(b){b.addEventListener("click", function(){var id = b.getAttribute("data-wm-close"); var m = document.querySelector("[data-wm-modal=\x27"+id+"\x27]"); if(m){m.classList.remove("is-open"); document.body.style.overflow = ""; resetNest();}});});
var backdrop = document.getElementById("wm-withdrawal-main");
if(backdrop){ backdrop.addEventListener("click", function(e){if(e.target === backdrop){backdrop.classList.remove("is-open"); document.body.style.overflow = ""; resetNest();}});}
function resetNest(){
document.getElementById("wm-n-entry-ids").value = "";
document.getElementById("wm-n-machine-code").value = "";
document.getElementById("wm-n-machine-name").value = "";
document.getElementById("wm-n-operator").value = "";
document.getElementById("wm-n-material").value = "";
document.getElementById("wm-n-weight").value = "";
document.getElementById("wm-nest-head").textContent = "Confirmar retiro";
document.getElementById("wm-n-combined-area").style.display = "none";
}
var fMachine = document.getElementById("wm-f-machine");
var fOperator = document.getElementById("wm-f-operator");
var fMaterial = document.getElementById("wm-f-material");
function applyFilters(){
var vm = (fMachine.value || "").trim().toLowerCase();
var vo = (fOperator.value || "").trim().toLowerCase();
var vmat = (fMaterial.value || "").trim().toLowerCase();
var rows = document.querySelectorAll("#wm-withdraw-list tbody tr");
rows.forEach(function(r){
var m = (r.getAttribute("data-machine") || "").toLowerCase();
var o = (r.getAttribute("data-operator") || "").toLowerCase();
var mt = (r.getAttribute("data-material") || "").toLowerCase();
var hit = true;
if(vm && m.indexOf(vm) < 0){ hit = false; }
if(vo && o.indexOf(vo) < 0){ hit = false; }
if(vmat && mt.indexOf(vmat) < 0){ hit = false; }
r.style.display = hit ? "" : "none";
});
}
if(fMachine){ fMachine.addEventListener("change", applyFilters); }
if(fOperator){ fOperator.addEventListener("change", applyFilters); }
if(fMaterial){ fMaterial.addEventListener("change", applyFilters); }
var checkAll = document.getElementById("wm-check-all");
if(checkAll){ checkAll.addEventListener("change", function(){var all = document.querySelectorAll(".wm-row-check"); all.forEach(function(c){if(!c.disabled){c.checked = checkAll.checked;}});}); }
var rowBtns = document.querySelectorAll(".wm-row-retire");
rowBtns.forEach(function(btn){
btn.addEventListener("click", function(){
var entryId = btn.getAttribute("data-entry-id") || "";
var code = btn.getAttribute("data-machine-code") || "";
var name = btn.getAttribute("data-machine-name") || "";
var op = btn.getAttribute("data-operator-name") || "";
var mat = btn.getAttribute("data-material") || "";
var wei = btn.getAttribute("data-weight") || "";
document.getElementById("wm-nest-head").textContent = "Retiro de merma - saco #" + entryId;
document.getElementById("wm-n-entry-ids").value = entryId;
document.getElementById("wm-n-machine-code").value = code;
document.getElementById("wm-n-machine-name").value = name;
document.getElementById("wm-n-operator").value = op;
document.getElementById("wm-n-material").value = mat;
document.getElementById("wm-n-weight").value = wei;
document.getElementById("wm-n-combined-area").style.display = "none";
window.scrollTo({top: 0, behavior: "smooth"});
});
});
var cancelNest = document.getElementById("wm-nest-cancel");
if(cancelNest){ cancelNest.addEventListener("click", resetNest); }
var combinedBtn = document.getElementById("wm-combined-retire");
if(combinedBtn){
combinedBtn.addEventListener("click", function(){
var checked = Array.prototype.slice.call(document.querySelectorAll(".wm-row-check:checked"));
if(checked.length === 0){ alert("Selecciona al menos un saco para retiro combinado."); return; }
var ids = [];
var sumWeight = 0;
var materials = {};
checked.forEach(function(cb){
var tr = cb.closest("tr");
if(!tr){ return; }
ids.push(cb.value);
var w = parseFloat((tr.getAttribute("data-weight") || tr.querySelector(".wm-row-weight")?.getAttribute("data-weight")) || "0");
if(!isNaN(w)){ sumWeight += w; }
var m = (tr.getAttribute("data-material") || "").toUpperCase();
if(m){ materials[m] = true; }
});
if(ids.length === 0){ alert("No hay sacos válidos seleccionados."); return; }
var firstEntryId = ids[0];
var firstTr = document.querySelector("#wm-withdraw-list tbody tr[data-entry=\"" + firstEntryId + "\"]");
var firstMachine = firstTr ? (firstTr.getAttribute("data-machine") || "") : "";
var firstOp = firstTr ? (firstTr.getAttribute("data-operator") || "") : "";
var matKeys = Object.keys(materials);
var materialSummary = matKeys.length === 1 ? matKeys[0] : "";
document.getElementById("wm-nest-head").textContent = "Retiro combinado (" + ids.length + " sacos)";
document.getElementById("wm-n-entry-ids").value = ids.join(",");
document.getElementById("wm-n-machine-code").value = "__COMBINED__";
document.getElementById("wm-n-machine-name").value = (firstMachine ? firstMachine : "") + (ids.length>1 ? " +" + (ids.length-1) : "");
document.getElementById("wm-n-operator").value = (firstOp ? firstOp : "") + (ids.length>1 ? " +" + (ids.length-1) : "");
document.getElementById("wm-n-material").value = materialSummary;
document.getElementById("wm-n-weight").value = (Math.round(sumWeight*1000)/1000).toFixed(3);
document.getElementById("wm-n-combined-area").style.display = "block";
window.scrollTo({top: 0, behavior: "smooth"});
});
}
})();</script>';

    render('Gestión Residuos', $body);
}


function handleWasteRoutes(string $path, string $method, ProductionService $prodService, ReceptionService $service, ?ScaleService $scale = null, string $currentOperatorName = ''): bool
{
    if ($currentOperatorName === '') {
        $currentOperatorName = trim((string)($_SESSION['operator_name'] ?? $_SESSION['auth_display_name'] ?? 'Operador'));
        if ($currentOperatorName === '') {
            $currentOperatorName = 'Operador';
        }
    }

if ($path === '/waste/management' && $method === 'GET') {
    $currentShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    $inventoryTotals = $service->listWastePendingInventoryTotals();
    $recentOperations = $service->listRecentWasteOperations(8);
    $pendingWithdrawals = $service->listPendingWasteWithdrawalsOfDay();
    $creationMaterialOptions = $service->listWasteCreationMaterialOptions();
    $creationAreaOptions = $service->listWasteCreationAreaOptions();
    $creationMotivoOptions = $service->listWasteCreationMotivoOptions();
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['started']) && $_GET['started'] === '1') {
        $flashMessage = 'Turno iniciado. Ya puedes registrar operaciones de merma y retiros.';
    } elseif (isset($_GET['inventory_saved']) && $_GET['inventory_saved'] === '1') {
        $flashMessage = 'Entrega de merma registrada correctamente.';
    } elseif (isset($_GET['withdrawal_saved']) && $_GET['withdrawal_saved'] === '1') {
        $count = isset($_GET['count']) ? (int)$_GET['count'] : 1;
        $flashMessage = $count > 1 ? ('Retiros combinados registrados: ' . $count . ' sacos de merma.') : 'Retiro de merma registrado correctamente.';
    } elseif (isset($_GET['creation_saved']) && $_GET['creation_saved'] === '1') {
        $flashMessage = 'Creación de merma registrada correctamente.';
    } elseif (isset($_GET['operation_saved']) && $_GET['operation_saved'] === '1') {
        $flashMessage = 'Operación registrada correctamente.';
    } elseif (isset($_GET['error']) && trim((string)$_GET['error']) !== '') {
        $flashMessage = trim((string)$_GET['error']);
        $flashIsError = true;
    }
    renderWasteManagementScreen($currentShiftSession, $currentOperatorName, $inventoryTotals, $recentOperations, $pendingWithdrawals, $creationMaterialOptions, $creationAreaOptions, $creationMotivoOptions, $flashMessage, $flashIsError);
    exit;
}

    if (handleWasteMolinoRoutes($path, $method, $service, $currentOperatorName)) {
        return true;
    }
    if (handleWasteCompactadoraRoutes($path, $method, $service, $currentOperatorName)) {
        return true;
    }
if ($path === '/waste/inventory' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $currentShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    $sessionId = $currentShiftSession !== null ? (int)$currentShiftSession['id'] : null;
    $result = $service->recordWasteInventoryEntry(
        (string)($_POST['material_code'] ?? ''),
        (float)($_POST['weight_kg'] ?? 0),
        $currentOperatorName,
        $sessionId,
        (string)($_POST['comments'] ?? ''),
        (string)($_POST['supplier_operator_name'] ?? ''),
        (string)($_POST['supplier_machine_code'] ?? ''),
        (string)($_POST['supplier_machine_name'] ?? '')
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /waste/management?inventory_saved=1');
        exit;
    }
    $error = trim((string)reset($result['errors']));
    header('Location: /waste/management?error=' . rawurlencode($error !== '' ? $error : 'No se pudo registrar la merma.'));
    exit;
}

if ($path === '/waste/withdrawal' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $currentShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    $sessionId = $currentShiftSession !== null ? (int)$currentShiftSession['id'] : null;
    $entryIdsRaw = trim((string)($_POST['entry_ids'] ?? ''));
    $entryIds = [];
    if ($entryIdsRaw !== '') {
        $parts = preg_split('/\s*,\s*/', $entryIdsRaw);
        foreach ($parts as $p) {
            $v = (int)$p;
            if ($v > 0) {
                $entryIds[] = $v;
            }
        }
    }
    if ($entryIds === []) {
        header('Location: /waste/management?error=' . rawurlencode('Selecciona al menos un saco de merma para retirar.'));
        exit;
    }
    $materialCode = trim((string)($_POST['material_code'] ?? ''));
    $weightKg = (float)($_POST['weight_kg'] ?? 0);
    $mCode = trim((string)($_POST['machine_code'] ?? ''));
    $mName = trim((string)($_POST['machine_name'] ?? ''));
    $opName = trim((string)($_POST['supplier_operator_name'] ?? ''));
    if ($materialCode === '' || $weightKg <= 0) {
        header('Location: /waste/management?error=' . rawurlencode('Debes indicar materialidad y peso mayor a 0.'));
        exit;
    }
    $result = $service->recordWasteWithdrawalOperation(
        $entryIds,
        $currentOperatorName,
        $sessionId,
        $materialCode,
        $weightKg,
        $opName !== '' ? $opName : null,
        $mCode !== '' && $mCode !== '__COMBINED__' ? $mCode : null,
        $mName !== '' ? $mName : null,
        null
    );
    if (($result['ok'] ?? false) === true) {
        $count = (int)($result['affected'] ?? count($entryIds));
        header('Location: /waste/management?withdrawal_saved=1&count=' . $count);
        exit;
    }
    $errList = $result['errors'] ?? [];
    $error = $errList !== [] ? trim((string)reset($errList)) : '';
    header('Location: /waste/management?error=' . rawurlencode($error !== '' ? $error : 'No se pudo registrar el retiro.'));
    exit;
}

if ($path === '/waste/creation' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $currentShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    $sessionId = $currentShiftSession !== null ? (int)$currentShiftSession['id'] : null;
    $materialCode = trim((string)($_POST['material_code'] ?? ''));
    $weightKg = (float)($_POST['weight_kg'] ?? 0);
    $solicitante = trim((string)($_POST['solicitante'] ?? ''));
    $area = trim((string)($_POST['area'] ?? ''));
    $motivo = trim((string)($_POST['motivo'] ?? ''));
    $result = $service->recordWasteCreationOperation(
        $materialCode,
        $weightKg,
        $solicitante,
        $area,
        $motivo,
        $currentOperatorName,
        $sessionId
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /waste/management?creation_saved=1');
        exit;
    }
    $errList = $result['errors'] ?? [];
    $error = $errList !== [] ? trim((string)reset($errList)) : '';
    header('Location: /waste/management?error=' . rawurlencode($error !== '' ? $error : 'No se pudo registrar la creación de merma.'));
    exit;
}

if ($path === '/waste/operations' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $currentShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    $sessionId = $currentShiftSession !== null ? (int)$currentShiftSession['id'] : null;
    $result = $service->recordWasteOperation(
        (string)($_POST['operation_code'] ?? ''),
        $currentOperatorName,
        $sessionId,
        (string)($_POST['comments'] ?? '')
    );
    if (($result['ok'] ?? false) === true) {
        $opCode = strtoupper(trim((string)($_POST['operation_code'] ?? '')));
        if ($opCode === 'PAUSA_MOLINO' || $opCode === 'MANTENCION_MOLINO' || $opCode === 'PAUSA_MOLINO_INICIO' || $opCode === 'PAUSA_MOLINO_FIN' || $opCode === 'MANTENCION_MOLINO_INICIO' || $opCode === 'MANTENCION_MOLINO_FIN') {
            header('Location: /waste/molino?operation_saved=1');
        } elseif ($opCode === 'PAUSA_COMPACTADORA' || $opCode === 'MANTENCION_COMPACTADORA' || $opCode === 'PAUSA_COMPACTADORA_INICIO' || $opCode === 'PAUSA_COMPACTADORA_FIN' || $opCode === 'MANTENCION_COMPACTADORA_INICIO' || $opCode === 'MANTENCION_COMPACTADORA_FIN') {
            header('Location: /waste/compactadora?operation_saved=1');
        } else {
            header('Location: /waste/management?operation_saved=1');
        }
        exit;
    }
    $error = trim((string)reset($result['errors']));
    $opCode = strtoupper(trim((string)($_POST['operation_code'] ?? '')));
    if ($opCode === 'PAUSA_MOLINO' || $opCode === 'MANTENCION_MOLINO' || $opCode === 'PAUSA_MOLINO_INICIO' || $opCode === 'PAUSA_MOLINO_FIN' || $opCode === 'MANTENCION_MOLINO_INICIO' || $opCode === 'MANTENCION_MOLINO_FIN') {
        header('Location: /waste/molino?error=' . rawurlencode($error !== '' ? $error : 'No se pudo registrar la operación.'));
    } elseif ($opCode === 'PAUSA_COMPACTADORA' || $opCode === 'MANTENCION_COMPACTADORA' || $opCode === 'PAUSA_COMPACTADORA_INICIO' || $opCode === 'PAUSA_COMPACTADORA_FIN' || $opCode === 'MANTENCION_COMPACTADORA_INICIO' || $opCode === 'MANTENCION_COMPACTADORA_FIN') {
        header('Location: /waste/compactadora?error=' . rawurlencode($error !== '' ? $error : 'No se pudo registrar la operación.'));
    } else {
        header('Location: /waste/management?error=' . rawurlencode($error !== '' ? $error : 'No se pudo registrar la operación.'));
    }
    exit;
}

if ($path === '/scale/sealing-waste' && $method === 'GET') {
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['saved']) && $_GET['saved'] === '1') {
        $flashMessage = 'Merma registrada correctamente.';
    } elseif (isset($_GET['error']) && trim((string)$_GET['error']) !== '') {
        $flashMessage = trim((string)$_GET['error']);
        $flashIsError = true;
    }

    $wasteType = trim((string)($_GET['waste_type'] ?? 'setup'));
    $allowedWasteTypes = [
        'setup' => 'Ajuste / Aprobación',
        'printing' => 'Impresión',
        'other' => 'Otros',
        'repair' => 'Reparación',
    ];
    if (!array_key_exists($wasteType, $allowedWasteTypes)) {
        $wasteType = 'setup';
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:800">Balanza merma Selladora</div>
          <div class="muted">Ingresa la OT que estás trabajando en sellado. El peso de merma se toma únicamente desde la balanza.</div>
        </div>
      </div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= '<div class="card"><form method="post" action="/scale/sealing-waste" id="sealing_scale_form" style="margin:0" autocomplete="off">'
        . '<input type="hidden" name="scale_captured_weight_kg" id="sealing_scale_weight_hidden" value="">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<div class="row" style="gap:12px;align-items:end;flex-wrap:wrap">'
        . '<div style="min-width:220px;flex:0 0 220px">'
        . '<label>OT (número)</label>'
        . '<input name="work_order_id" type="number" min="1" step="1" value="' . h((string)($_GET['work_order_id'] ?? '')) . '" placeholder="Ej: 95" required>'
        . '</div>'
        . '<div style="min-width:240px;flex:1">'
        . '<label>Tipo de merma</label>'
        . '<select name="waste_type">';
    foreach ($allowedWasteTypes as $key => $label) {
        $selected = $key === $wasteType ? ' selected' : '';
        $body .= '<option value="' . h($key) . '"' . $selected . '>' . h($label) . '</option>';
    }
    $body .= '</select>'
        . '</div>'
        . '<div style="min-width:280px;flex:1">'
        . '<label>Comentario (opcional)</label>'
        . '<input name="comment" type="text" value="' . h((string)($_GET['comment'] ?? '')) . '" placeholder="Ej: ajuste de tensión">'
        . '</div>'
        . '</div>'
        . '<div class="row" style="gap:12px;align-items:end;flex-wrap:wrap;margin-top:12px">'
        . '<div style="min-width:220px;flex:0 0 220px">'
        . '<label>Peso merma (Kg)</label>'
        . '<input id="sealing_scale_weight_preview" type="text" value="-" readonly style="background:#f3f4f6">'
        . '<div class="muted" id="sealing_scale_status" style="margin-top:6px">Balanza: sin lectura</div>'
        . '</div>'
        . '<div class="row" style="gap:8px;align-items:center">'
        . '<button class="btn secondary" type="button" id="sealing_scale_read">Leer balanza</button>'
        . '<button class="btn" type="submit" style="min-width:180px">Guardar merma</button>'
        . '</div>'
        . '</div>'
        . '<div class="muted" style="margin-top:10px">El valor se registra leyendo la balanza al momento de guardar.</div>'
        . '</form></div>';

    $body .= '<script>
      (function () {
        var btn = document.getElementById("sealing_scale_read");
        var out = document.getElementById("sealing_scale_weight_preview");
        var status = document.getElementById("sealing_scale_status");
        if (!btn || !out) return;
        var setStatus = function (text, isError) {
          if (!status) return;
          status.textContent = text;
          status.style.color = isError ? "#b91c1c" : "#555";
        };
        btn.addEventListener("click", async function () {
          try {
            btn.disabled = true;
            setStatus("Leyendo balanza...", false);
            // Lectura con estabilidad: varias lecturas iguales, estables y > 0 (no se acepta peso en movimiento).
            var data = await (window.UnibagHardware ? window.UnibagHardware.readStableWeight() : fetch("/api/scale/weight", { cache: "no-store" }).then(function (r) { return r.json(); }));
            if (!data || data.ok !== true) {
              out.value = "-";
              setStatus((data && data.error) ? data.error : "No se pudo leer la balanza.", true);
              return;
            }
            var wVal = Number(data.weight_kg) || 0;
            out.value = wVal.toFixed(3);
            var hInput = document.getElementById("sealing_scale_weight_hidden");
            if (hInput) hInput.value = wVal > 0 ? wVal.toFixed(3) : "";
            setStatus("Lectura OK", false);
          } catch (e) {
            out.value = "-";
            setStatus("No se pudo leer la balanza.", true);
          } finally {
            btn.disabled = false;
          }
        });
        var sForm = document.getElementById("sealing_scale_form");
        if (sForm) {
          var autoSubmitted = false;
          sForm.addEventListener("submit", async function (ev) {
            var hInput = document.getElementById("sealing_scale_weight_hidden");
            if (autoSubmitted || (hInput && parseFloat(hInput.value || "0") > 0)) return;
            ev.preventDefault();
            btn.click();
            await new Promise(function (r) { setTimeout(r, 60); });
            while (btn.disabled) { await new Promise(function (r) { setTimeout(r, 80); }); }
            if (hInput && parseFloat(hInput.value || "0") > 0) {
              autoSubmitted = true;
              sForm.submit();
            }
          });
        }
      })();
    </script>';

    render('Balanza merma Selladora', $body);
    exit;
}

if ($path === '/scale/sealing-waste' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();

    $workOrderId = (int)($_POST['work_order_id'] ?? 0);
    $wasteType = trim((string)($_POST['waste_type'] ?? 'setup'));
    $comment = trim((string)($_POST['comment'] ?? ''));

    $allowedWasteTypes = [
        'setup' => 'Ajuste / Aprobación',
        'printing' => 'Impresión',
        'other' => 'Otros',
        'repair' => 'Reparación',
    ];
    if (!array_key_exists($wasteType, $allowedWasteTypes)) {
        $wasteType = 'setup';
    }

    if ($workOrderId <= 0) {
        redirectResponse(withQuery('/scale/sealing-waste', [
            'error' => 'Debes ingresar una OT válida.',
            'work_order_id' => (string)$workOrderId,
            'waste_type' => $wasteType,
            'comment' => $comment,
        ]));
    }

    if ($service instanceof ReceptionService && !$service->isWorkOrderInSealingStage($workOrderId)) {
        redirectResponse(withQuery('/scale/sealing-waste', [
            'error' => 'La OT no está en etapa de Selladora.',
            'work_order_id' => (string)$workOrderId,
            'waste_type' => $wasteType,
            'comment' => $comment,
        ]));
    }

    $capturedClientWeight = (isset($_POST['scale_captured_weight_kg']) && is_numeric($_POST['scale_captured_weight_kg']))
        ? round((float)$_POST['scale_captured_weight_kg'], 3)
        : 0.0;
    $scaleResult = $capturedClientWeight > 0
        ? ['ok' => true, 'weight_kg' => $capturedClientWeight, 'error' => null]
        : ($scale instanceof ScaleService ? $scale->readWeightKg() : ['ok' => false, 'error' => 'Balanza no disponible.']);
    if (($scaleResult['ok'] ?? false) !== true) {
        redirectResponse(withQuery('/scale/sealing-waste', [
            'error' => (string)($scaleResult['error'] ?? 'No se pudo leer la balanza.'),
            'work_order_id' => (string)$workOrderId,
            'waste_type' => $wasteType,
            'comment' => $comment,
        ]));
    }

    $weightKg = (float)($scaleResult['weight_kg'] ?? 0);
    if ($weightKg <= 0) {
        redirectResponse(withQuery('/scale/sealing-waste', [
            'error' => 'La balanza devolvió un peso inválido.',
            'work_order_id' => (string)$workOrderId,
            'waste_type' => $wasteType,
            'comment' => $comment,
        ]));
    }

    $reason = 'Selladora - ' . (string)($allowedWasteTypes[$wasteType] ?? 'Merma') . ($comment !== '' ? (' · ' . $comment) : '');
    $result = $service->createProductionWaste($workOrderId, null, 'SEALING', $reason, $weightKg, $currentOperatorName);
    if (($result['ok'] ?? false) !== true) {
        $err = trim((string)reset($result['errors']));
        redirectResponse(withQuery('/scale/sealing-waste', [
            'error' => $err !== '' ? $err : 'No se pudo registrar la merma.',
            'work_order_id' => (string)$workOrderId,
            'waste_type' => $wasteType,
            'comment' => $comment,
        ]));
    }

    redirectResponse(withQuery('/scale/sealing-waste', [
        'saved' => '1',
        'work_order_id' => (string)$workOrderId,
    ]));
}

    return false;
}
