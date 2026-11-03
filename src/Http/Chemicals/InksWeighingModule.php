<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Químicos y Pesaje de Tintas (src/Http/Chemicals/InksWeighingModule.php)
// =============================================================================

function handleChemicalsRoutes(string $path, string $method, ReceptionService $service): bool
{
if ($path === '/chemicals/weighings' && $method === 'GET') {
    $active = $service->getActiveWorkOrder();
    $activeId = is_array($active) ? (int)$active['id'] : 0;
    $chemicals = $service->listChemicals();
    $weighings = $service->listRecentChemicalWeighings();

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Pesaje de tintas</div>
          <div class="muted">Registro de peso inicial y retorno para calcular consumo neto por OT.</div>
        </div>
        <a class="btn" href="/chemicals/weighings/new">Nuevo pesaje</a>
      </div>';

    if ($activeId <= 0) {
        $body .= '<div class="err" style="margin-bottom:12px">No hay OT activa. Activa una OT en <a href="/work-orders">OT</a> para registrar consumos.</div>';
    }

    $body .= '<div class="card"><table><thead><tr>
        <th>ID</th><th>OT</th><th>SKU final</th><th>Tinta</th><th>Inicial (Kg)</th><th>Retorno (Kg)</th><th>Consumo (Kg)</th><th>Fecha</th>
      </tr></thead><tbody>';
    foreach ($weighings as $w) {
        $body .= '<tr>';
        $body .= '<td>' . (int)$w['id'] . '</td>';
        $body .= '<td>' . h((string)$w['ot_code']) . '</td>';
        $body .= '<td>' . h((string)$w['sku_final']) . '</td>';
        $body .= '<td>' . h((string)$w['chemical_code']) . '</td>';
        $body .= '<td>' . h((string)$w['initial_weight_kg']) . '</td>';
        $body .= '<td>' . h((string)$w['return_weight_kg']) . '</td>';
        $body .= '<td>' . h((string)$w['net_consumption_kg']) . '</td>';
        $body .= '<td>' . h((string)$w['created_at']) . '</td>';
        $body .= '</tr>';
    }
    if ($weighings === []) {
        $body .= '<tr><td colspan="8" class="muted">Sin registros todavía.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Tintas', $body);
    exit;
}

if ($path === '/chemicals/weighings/new' && $method === 'GET') {
    $active = $service->getActiveWorkOrder();
    $activeId = is_array($active) ? (int)$active['id'] : 0;
    $chemicals = $service->listChemicals();

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Nuevo pesaje de tintas</div>
        <a class="btn secondary" href="/chemicals/weighings">Volver</a>
      </div>';

    if ($activeId <= 0) {
        $body .= '<div class="err" style="margin-bottom:12px">No hay OT activa. Activa una OT en <a href="/work-orders">OT</a>.</div>';
        render('Nuevo pesaje', $body);
        exit;
    }

    $body .= '<div class="card">
        <form method="post" action="/chemicals/weighings">
          <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
          <input type="hidden" name="work_order_id" value="' . (int)$activeId . '">
          <div class="row">
            <div style="flex:1;min-width:260px">
              <label>OT activa</label>
              <input type="text" value="' . h((string)$active['ot_code']) . ' - ' . h((string)$active['sku_final']) . '" disabled>
            </div>
            <div style="flex:1;min-width:260px">
              <label>Tinta</label>
              <select name="chemical_id" required>
                <option value="">Seleccionar</option>';
    foreach ($chemicals as $c) {
        $body .= '<option value="' . (int)$c['id'] . '">' . h((string)$c['code']) . ' - ' . h((string)$c['name']) . '</option>';
    }
    $body .= '</select>
            </div>
          </div>

          <div class="row" style="margin-top:10px;align-items:end">
            <div style="flex:1;min-width:220px">
              <label>Peso inicial (Kg)</label>
              <input id="initial_weight_kg" name="initial_weight_kg" type="number" step="0.001" min="0" required>
            </div>
            <div style="min-width:220px">
              <button class="btn secondary" type="button" id="read_scale_initial">Leer balanza</button>
            </div>
          </div>

          <div class="row" style="margin-top:10px;align-items:end">
            <div style="flex:1;min-width:220px">
              <label>Peso retorno (Kg)</label>
              <input id="return_weight_kg" name="return_weight_kg" type="number" step="0.001" min="0" required>
            </div>
            <div style="min-width:220px">
              <button class="btn secondary" type="button" id="read_scale_return">Leer balanza</button>
            </div>
          </div>

          <div style="margin-top:12px">
            <button class="btn" type="submit">Guardar pesaje</button>
          </div>
        </form>
        <script>
          (function () {
            async function readTo(inputId, btnId) {
              var btn = document.getElementById(btnId);
              var input = document.getElementById(inputId);
              if (!btn || !input) return;
              btn.addEventListener("click", async function () {
                btn.disabled = true;
                btn.textContent = "Leyendo...";
                try {
                  // Lectura con estabilidad: varias lecturas iguales, estables y > 0 (no se acepta peso en movimiento).
                  var data = await (window.UnibagHardware ? window.UnibagHardware.readStableWeight() : fetch("/api/scale/weight", { cache: "no-store" }).then(function (r) { return r.json(); }));
                  if (!data || data.ok !== true) throw new Error((data && data.error) ? data.error : "No se pudo leer la balanza.");
                  input.value = data.weight_kg;
                } catch (e) {
                  alert(e && e.message ? e.message : "Error leyendo la balanza.");
                } finally {
                  btn.disabled = false;
                  btn.textContent = "Leer balanza";
                }
              });
            }
            readTo("initial_weight_kg", "read_scale_initial");
            readTo("return_weight_kg", "read_scale_return");
          })();
        </script>
      </div>';

    render('Nuevo pesaje', $body);
    exit;
}

if ($path === '/chemicals/weighings' && $method === 'POST') {
    requireCsrf();
    $result = $service->createChemicalWeighing($_POST);
    if ($result['ok'] === true) {
        header('Location: /chemicals/weighings');
        exit;
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Nuevo pesaje de tintas</div>
        <a class="btn secondary" href="/chemicals/weighings">Volver</a>
      </div>';
    $body .= '<div class="err" style="margin-bottom:12px"><ul style="margin:0;padding-left:16px">';
    foreach ($result['errors'] as $msg) {
        $body .= '<li>' . h((string)$msg) . '</li>';
    }
    $body .= '</ul></div>';
    $body .= '<div class="card"><a class="btn secondary" href="/chemicals/weighings/new">Volver</a></div>';
    render('Nuevo pesaje', $body);
    exit;
}


if ($path === '/stock/chemicals' && $method === 'GET') {
    header('Location: /stock');
    exit;
}

    return false;
}
