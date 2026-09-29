<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Bodegas (maestros)
//
// Este módulo implementa las rutas bajo /warehouses para administrar el maestro
// de bodegas del sistema (código, nombre y capacidades). Estas capacidades se
// usan principalmente para calcular ocupación (unidades o pallets) en el panel
// de inventario/ocupación.
//
// Convenciones:
// - Este proyecto no usa framework: el router central invoca handleWarehousesRoutes().
// - Las operaciones POST exigen CSRF (requireCsrf()).
// - La salida HTML se construye como string y se entrega vía render().
//
// ---
//
// HTTP Module · Warehouses (master data)
//
// This module implements routes under /warehouses to manage the warehouse master
// data (code, name, and capacities). Those capacities are primarily used to
// compute occupancy (units or pallets) in the inventory/occupancy screens.
//
// Conventions:
// - This project does not use a framework: the main router calls handleWarehousesRoutes().
// - POST operations require CSRF (requireCsrf()).
// - HTML output is built as a string and rendered via render().
// =============================================================================

/**
 * Renderiza el listado de bodegas (maestro).
 *
 * Responsabilidades:
 * - Leer mensajes (msg/error) desde querystring para feedback post-redirect.
 * - Consultar bodegas y capacidades vía ReceptionService.
 * - Mostrar ocupación y métricas agregadas (unidades/pallets/bobinas/cajas).
 *
 * ---
 *
 * Renders the warehouse master list.
 *
 * Responsibilities:
 * - Read flash-like messages (msg/error) from querystring for post-redirect feedback.
 * - Load warehouses and capacities through ReceptionService.
 * - Display occupancy and aggregated metrics (units/pallets/rolls/boxes).
 */
/**
 * Emite la planilla Excel con el maestro de bodegas y existencias en formato XLSX nativo.
 *
 * @param array<int, array<string, mixed>> $rows
 */
function unibagOutputWarehousesExcel(array $rows, float $totalStockUnits): void
{
    $filename = 'maestro-bodegas-' . date('Ymd-His') . '.xlsx';
    $generatedAt = date('d/m/Y H:i');
    $totalWh = count($rows);

    $html = '<html><head><meta charset="UTF-8"><style>';
    $html .= 'body{font-family:Arial,sans-serif;color:#0f172a;font-size:12px}';
    $html .= '.title{font-size:20px;font-weight:700;color:#0f172a}';
    $html .= '.sub{font-size:12px;color:#475569;margin-bottom:12px}';
    $html .= '.meta{margin:8px 0 16px 0;font-size:12px;color:#334155}';
    $html .= '.report{border-collapse:collapse;width:100%}';
    $html .= '.report td,.report th{border:1px solid #cbd5e1;padding:6px 8px;font-size:11px}';
    $html .= '.report th{background:#0f172a;color:#fff;text-align:center;font-weight:700}';
    $html .= '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    $html .= '.num-dec{mso-number-format:"\#\,\#\#0\.00";text-align:right}';
    $html .= '.text-cell{mso-number-format:"\@"}';
    $html .= '</style></head><body>';

    $html .= '<div class="title">Maestro de Bodegas y Stock Real</div>';
    $html .= '<div class="sub">Unibag · Sistema ERP & Trazabilidad</div>';
    $html .= '<div class="meta"><strong>Generado:</strong> ' . h($generatedAt) . ' · <strong>Total bodegas:</strong> ' . $totalWh . ' · <strong>Stock real total:</strong> ' . number_format($totalStockUnits, 0, ',', '.') . ' unid</div>';

    $html .= '<table class="report">';
    $html .= '<tr>';
    $html .= '<th>Código</th>';
    $html .= '<th>Nombre Bodega</th>';
    $html .= '<th>Clasificación ERP</th>';
    $html .= '<th>Descripción ERP</th>';
    $html .= '<th>Cap. Pallets</th>';
    $html .= '<th>Cap. Unidades</th>';
    $html .= '<th>Ocupación %</th>';
    $html .= '<th>Pallets (BD)</th>';
    $html .= '<th>Bobinas (BD)</th>';
    $html .= '<th>Cajas (BD)</th>';
    $html .= '<th>Unidades (BD)</th>';
    $html .= '<th>Stock Total</th>';
    $html .= '<th>Arts. con Stock ERP</th>';
    $html .= '<th>ID ERP</th>';
    $html .= '</tr>';

    foreach ($rows as $r) {
        $occupancyStr = $r['occupancy_percent'] !== null ? number_format((float)$r['occupancy_percent'], 2, ',', '.') . '%' : '—';
        $html .= '<tr>';
        $html .= '<td class="text-cell">' . h((string)$r['code']) . '</td>';
        $html .= '<td>' . h((string)$r['name']) . '</td>';
        $html .= '<td>' . h((string)($r['clasificacion'] ?? '')) . '</td>';
        $html .= '<td>' . h((string)($r['description'] ?? '')) . '</td>';
        $html .= '<td class="num-int">' . (int)($r['capacity_pallets'] ?? 0) . '</td>';
        $html .= '<td class="num-int">' . (int)round((float)($r['capacity_units_total'] ?? 0)) . '</td>';
        $html .= '<td style="text-align:right">' . $occupancyStr . '</td>';
        $html .= '<td class="num-int">' . (int)round((float)($r['pallets_count'] ?? 0)) . '</td>';
        $html .= '<td class="num-int">' . (int)round((float)($r['rolls_count'] ?? 0)) . '</td>';
        $html .= '<td class="num-int">' . (int)round((float)($r['boxes_count'] ?? 0)) . '</td>';
        $html .= '<td class="num-int">' . (int)round((float)($r['units_other_count'] ?? 0)) . '</td>';
        $html .= '<td class="num-int">' . (int)round((float)($r['stock_units_total'] ?? 0)) . '</td>';
        $html .= '<td class="num-int">' . (int)($r['erp_items_count'] ?? 0) . '</td>';
        $html .= '<td class="text-cell">' . (!empty($r['erp_storehouse_id']) ? '#' . (int)$r['erp_storehouse_id'] : '—') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</table>';
    $html .= '</body></html>';

    SimpleXlsx::streamHtml($filename, $html, 'Bodegas');
}

function unibagRenderWarehousesPage(ReceptionService $service): void
{
    $message = trim((string)($_GET['msg'] ?? ''));
    $error = trim((string)($_GET['error'] ?? ''));
    $rows = $service->listWarehousesWithCapacities();

    $totalWarehouses = count($rows);
    $totalStockUnits = 0.0;
    $whWithStock = 0;
    $whWithCapacity = 0;
    foreach ($rows as $r) {
        $st = (float)($r['stock_units_total'] ?? 0);
        $totalStockUnits += $st;
        if ($st > 0) {
            $whWithStock++;
        }
        if ((float)($r['capacity_units_total'] ?? 0) > 0 || (int)($r['capacity_pallets'] ?? 0) > 0) {
            $whWithCapacity++;
        }
    }

    if (isset($_GET['download']) && (string)$_GET['download'] === 'excel') {
        unibagOutputWarehousesExcel($rows, $totalStockUnits);
    }

    $body = '<div class="erp-prod-shell" style="max-width:none;width:100%">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:14px">
        <div style="background:#fff;padding:14px 16px;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.03)">
          <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Bodegas activas</div>
          <div style="font-size:22px;font-weight:800;color:#0f172a;margin-top:2px">' . $totalWarehouses . '</div>
          <div class="muted" style="font-size:11px">Registradas y sincronizadas</div>
        </div>
        <div style="background:#fff;padding:14px 16px;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.03)">
          <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Stock real total (unid)</div>
          <div style="font-size:22px;font-weight:800;color:#2563eb;margin-top:2px">' . h(number_format($totalStockUnits, 0, ',', '.')) . '</div>
          <div class="muted" style="font-size:11px">Existencias en inventario ERP</div>
        </div>
        <div style="background:#fff;padding:14px 16px;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.03)">
          <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Bodegas con existencias</div>
          <div style="font-size:22px;font-weight:800;color:#16a34a;margin-top:2px">' . $whWithStock . '</div>
          <div class="muted" style="font-size:11px">Bodegas con stock > 0</div>
        </div>
        <div style="background:#fff;padding:14px 16px;border-radius:10px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.03)">
          <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Capacidad configurada</div>
          <div style="font-size:22px;font-weight:800;color:#0891b2;margin-top:2px">' . $whWithCapacity . ' / ' . $totalWarehouses . '</div>
          <div class="muted" style="font-size:11px">Con capacidad máx. para ocupación</div>
        </div>
      </div>

      <div class="erp-prod-panel" style="margin-bottom:12px">
        <div class="erp-prod-panel-head" data-collapse-toggle style="cursor:pointer;display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid #e5e7eb;background:#f8fafc;border-radius:10px 10px 0 0">
          <div style="display:flex;align-items:center;gap:8px">
            <span class="erp-collapse-toggle" aria-hidden="true" style="display:inline-block;width:10px;height:10px;border-right:2px solid #475569;border-bottom:2px solid #475569;transform:rotate(45deg);margin-right:4px"></span>
            <div>
              <div style="font-size:15px;font-weight:800">Maestro de bodegas y ocupación</div>
              <div class="muted" style="font-size:12px">Monitorea el stock real completo del inventario ERP y gestiona las capacidades máximas de cada bodega.</div>
            </div>
          </div>
          <div style="display:flex;gap:8px;align-items:center">
            <a class="btn secondary" href="/warehouses?download=excel" style="display:inline-flex;align-items:center;gap:6px" title="Exportar listado completo de bodegas y existencias a Excel">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
              Descargar Excel
            </a>
            <a class="btn" href="/warehouses/new">+ Nueva bodega</a>
          </div>
        </div>
        <div class="erp-prod-panel-body" style="padding:14px">';

    if ($message !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($message) . '</div>';
    }
    if ($error !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($error) . '</div>';
    }

    $body .= '<div class="table-wrap" style="margin-top:0"><table class="erp-prod-table" style="width:100%;border-collapse:collapse;font-size:13px">
      <thead style="background:#0f172a;color:#fff">
        <tr>
          <th style="padding:8px 10px;text-align:left">Código</th>
          <th style="padding:8px 10px;text-align:left">Nombre</th>
          <th style="padding:8px 10px;text-align:left">Clasificación</th>
          <th style="padding:8px 10px;text-align:right" title="Capacidad máxima en pallets (Trazabilidad o ERP)">Cap. pallets</th>
          <th style="padding:8px 10px;text-align:right" title="Capacidad máxima en unidades (Trazabilidad o ERP)">Cap. unid</th>
          <th style="padding:8px 10px;text-align:right;min-width:120px" title="Porcentaje de ocupación calculado con el stock real">Ocupación</th>
          <th style="padding:8px 10px;text-align:right" title="Pallets en base de datos ERP">Pallets</th>
          <th style="padding:8px 10px;text-align:right" title="Bobinas en base de datos ERP">Bobinas</th>
          <th style="padding:8px 10px;text-align:right" title="Cajas en base de datos ERP">Cajas</th>
          <th style="padding:8px 10px;text-align:right" title="Otros productos / unidades restantes en base de datos ERP">Unidades</th>
          <th style="padding:8px 10px;text-align:right" title="Stock real completo de existencias en el ERP">Stock total</th>
          <th style="padding:8px 10px;text-align:center">Acciones</th>
        </tr>
      </thead>
      <tbody>';

    if ($rows === []) {
        $body .= '<tr><td colspan="12" class="muted" style="padding:20px;text-align:center">No hay bodegas registradas. Crea la primera usando el botón "Nueva bodega".</td></tr>';
    }

    foreach ($rows as $row) {
        $occupancy = $row['occupancy_percent'];
        $occupancyVal = $occupancy !== null ? (float)$occupancy : null;
        if ($occupancyVal !== null) {
            $color = $occupancyVal >= 90.0 ? '#dc2626' : ($occupancyVal >= 70.0 ? '#d97706' : '#16a34a');
            $barWidth = min(100.0, max(0.0, $occupancyVal));
            $occupancyHtml = '<div>
                <span style="font-weight:800;color:' . $color . '">' . h(number_format($occupancyVal, 2, ',', '.')) . '%</span>
                <div style="height:5px;width:100%;background:#e2e8f0;border-radius:3px;overflow:hidden;margin-top:2px">
                  <div style="height:100%;width:' . h(number_format($barWidth, 1, '.', '')) . '%;background:' . $color . ';border-radius:3px"></div>
                </div>
              </div>';
        } else {
            $occupancyHtml = '<span class="muted" title="Sin capacidad configurada. Haz clic en Editar para definirla">—</span>';
        }
        
        $erpBadge = !empty($row['erp_storehouse_id'])
            ? ' <span style="display:inline-block;padding:1px 5px;font-size:10px;font-weight:600;background:#e0f2fe;color:#0369a1;border-radius:4px;vertical-align:middle" title="Vinculada con ERP company_shops_storehouses #' . (int)$row['erp_storehouse_id'] . '">ERP #' . (int)$row['erp_storehouse_id'] . '</span>'
            : '';

        $clasifStr = !empty($row['clasificacion']) ? (string)$row['clasificacion'] : '-';

        $capPalletsVal = (int)($row['capacity_pallets'] ?? 0);
        $capUnitsVal = (float)($row['capacity_units_total'] ?? 0);

        $capPalletsHtml = $capPalletsVal > 0
            ? '<span style="font-weight:600">' . h((string)$capPalletsVal) . '</span>'
            : '<span class="muted">—</span>';

        $capUnitsHtml = $capUnitsVal > 0
            ? '<span style="font-weight:600">' . h(number_format((int)round($capUnitsVal), 0, ',', '.')) . '</span>'
            : '<span class="muted">—</span>';

        $stockUnitsTotal = (float)($row['stock_units_total'] ?? 0);
        $erpItemsCount = (int)($row['erp_items_count'] ?? 0);
        $erpStorehouseId = (int)($row['erp_storehouse_id'] ?? 0);

        $palletsCount = (float)($row['pallets_count'] ?? 0);
        $rollsCount = (float)($row['rolls_count'] ?? 0);
        $boxesCount = (float)($row['boxes_count'] ?? 0);
        $unitsCount = (float)($row['units_other_count'] ?? 0);

        $palletsHtml = $palletsCount > 0 ? '<span style="font-weight:600">' . h(number_format($palletsCount, 0, ',', '.')) . '</span>' : '<span class="muted">0</span>';
        $rollsHtml = $rollsCount > 0 ? '<span style="font-weight:600">' . h(number_format($rollsCount, 0, ',', '.')) . '</span>' : '<span class="muted">0</span>';
        $boxesHtml = $boxesCount > 0 ? '<span style="font-weight:600">' . h(number_format($boxesCount, 0, ',', '.')) . '</span>' : '<span class="muted">0</span>';
        $unitsHtml = $unitsCount > 0 ? '<span style="font-weight:600">' . h(number_format($unitsCount, 0, ',', '.')) . '</span>' : '<span class="muted">0</span>';

        if ($erpStorehouseId > 0 && $stockUnitsTotal > 0) {
            $stockHtml = '<div style="font-weight:800;font-size:14px">
                <a href="/stock?bodega=' . $erpStorehouseId . '" style="color:#2563eb;text-decoration:none" title="Ver artículos de esta bodega en Inventario ERP">
                  ' . h(number_format($stockUnitsTotal, 0, ',', '.')) . '
                </a>
              </div>';
            if ($erpItemsCount > 0) {
                $stockHtml .= '<div class="muted" style="font-size:10px">' . $erpItemsCount . ' arts. (ERP)</div>';
            }
        } elseif ($stockUnitsTotal > 0) {
            $stockHtml = '<div style="font-weight:800;font-size:14px;color:#0f172a">' . h(number_format($stockUnitsTotal, 0, ',', '.')) . '</div>';
        } else {
            $stockHtml = '<span class="muted">0</span>';
        }

        $body .= '<tr style="border-bottom:1px solid #e5e7eb">
          <td style="padding:8px 10px;font-weight:700;white-space:nowrap">' . h((string)$row['code']) . $erpBadge . '</td>
          <td style="padding:8px 10px;font-weight:600">' . h((string)$row['name']) . '</td>
          <td style="padding:8px 10px;white-space:nowrap">' . h($clasifStr) . '</td>
          <td style="padding:8px 10px;text-align:right">' . $capPalletsHtml . '</td>
          <td style="padding:8px 10px;text-align:right">' . $capUnitsHtml . '</td>
          <td style="padding:8px 10px;text-align:right">' . $occupancyHtml . '</td>
          <td style="padding:8px 10px;text-align:right">' . $palletsHtml . '</td>
          <td style="padding:8px 10px;text-align:right">' . $rollsHtml . '</td>
          <td style="padding:8px 10px;text-align:right">' . $boxesHtml . '</td>
          <td style="padding:8px 10px;text-align:right">' . $unitsHtml . '</td>
          <td style="padding:8px 10px;text-align:right">' . $stockHtml . '</td>
          <td style="padding:8px 10px;text-align:center;white-space:nowrap">
            <a class="btn secondary" href="/warehouses/' . (int)$row['id'] . '/edit" style="margin-right:6px">Editar</a>
            <form method="post" action="/warehouses/' . (int)$row['id'] . '/delete" onsubmit="return confirm(\'¿Eliminar bodega ' . (int)$row['code'] . '? Esta acción no se puede deshacer.\')" style="display:inline;margin:0">
              <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
              <button class="btn secondary" type="submit" style="background:#fef2f2;color:#dc2626;border-color:#fecaca">Eliminar</button>
            </form>
          </td>
        </tr>';
    }

    $body .= '</tbody></table></div>';
    $body .= '</div></div>';

    $body .= '<script>
      (function () {
        function attach(root) {
          var toggles = root.querySelectorAll("[data-collapse-toggle]");
          for (var i = 0; i < toggles.length; i++) {
            (function (toggle) {
              var panel = toggle.closest(".erp-prod-panel");
              if (!panel) return;
              var body = panel.querySelector(":scope > .erp-prod-panel-body");
              var arrow = toggle.querySelector(":scope > div:first-child > .erp-collapse-toggle");
              function applyCollapsed(collapsed) {
                if (!body) return;
                if (collapsed) {
                  panel.classList.add("is-collapsed");
                  body.style.display = "none";
                  if (arrow) arrow.style.transform = "rotate(-45deg)";
                } else {
                  panel.classList.remove("is-collapsed");
                  body.style.display = "";
                  if (arrow) arrow.style.transform = "rotate(45deg)";
                }
              }
              applyCollapsed(panel.classList.contains("is-collapsed"));
              toggle.addEventListener("click", function (e) {
                if (e.target.closest("a, button, input, select, textarea, label")) return;
                applyCollapsed(!panel.classList.contains("is-collapsed"));
              });
              toggle.addEventListener("keydown", function (e) {
                if (e.key === "Enter" || e.key === " ") {
                  e.preventDefault();
                  applyCollapsed(!panel.classList.contains("is-collapsed"));
                }
              });
            })(toggles[i]);
          }
        }
        if (document.readyState === "loading") {
          document.addEventListener("DOMContentLoaded", function () { attach(document); });
        } else {
          attach(document);
        }
      })();
    </script>';
    $body .= '</div>';

    render('Bodegas · Maestros', $body);
}

/**
 * Renderiza formulario de creación/edición de una bodega.
 *
 * Comportamiento:
 * - Si $id viene informado, carga la bodega; si no existe, muestra pantalla de error.
 * - Si $formValues viene informado, se usa como fuente de verdad (para re-render al validar).
 * - Si $errors viene informado, muestra la lista de errores arriba del formulario.
 *
 * Nota: en modo edición se advierte si hay stock asociado (bobinas/pallets/cajas),
 * porque ese stock impide eliminar la bodega.
 *
 * ---
 *
 * Renders the create/edit warehouse form.
 *
 * Behavior:
 * - If $id is provided, loads the warehouse; if missing, shows a not-found screen.
 * - If $formValues is provided, it becomes the source of truth (re-render after validation).
 * - If $errors is provided, shows the error list above the form.
 *
 * Note: in edit mode it warns if there is stock associated (rolls/pallets/boxes),
 * because associated stock prevents deletion.
 */
function unibagRenderWarehouseFormPage(ReceptionService $service, ?int $id = null, ?array $formValues = null, ?array $errors = null): void
{
    $warehouse = null;
    $isEdit = $id !== null;
    if ($isEdit) {
        $warehouse = $service->getWarehouseById((int)$id);
        if ($warehouse === null) {
            render('Bodega no encontrada', '<div class="card" style="text-align:center"><div style="font-size:18px;font-weight:800;margin-bottom:10px">La bodega no existe</div><a class="btn secondary" href="/warehouses">Volver al listado</a></div>');
            return;
        }
    }

    $defaults = $formValues ?? [
        'code' => $warehouse !== null ? (string)$warehouse['code'] : '',
        'name' => $warehouse !== null ? (string)$warehouse['name'] : '',
        'desc' => $warehouse !== null ? (string)($warehouse['desc'] ?? '') : '',
        'clasificacion' => $warehouse !== null ? (string)($warehouse['clasificacion'] ?? '') : '',
        'capacidad_erp' => $warehouse !== null ? (string)($warehouse['capacidad_erp'] ?? '0') : '0',
        'reserva' => $warehouse !== null ? !empty($warehouse['reserva']) : false,
        'repuestos' => $warehouse !== null ? !empty($warehouse['repuestos']) : false,
        'unibagreserva' => $warehouse !== null ? !empty($warehouse['unibagreserva']) : false,
        'flexo' => $warehouse !== null ? !empty($warehouse['flexo']) : false,
        'seri' => $warehouse !== null ? !empty($warehouse['seri']) : false,
        'selladora' => $warehouse !== null ? !empty($warehouse['selladora']) : false,
        'capacity_units_total' => $warehouse !== null ? (string)(int)round((float)$warehouse['capacity_units_total']) : '0',
        'capacity_pallets' => $warehouse !== null ? (string)$warehouse['capacity_pallets'] : '0',
    ];

    $errorsHtml = '';
    if (is_array($errors) && $errors !== []) {
        $errorsHtml .= '<div class="err" style="margin-bottom:12px"><div style="font-weight:700;margin-bottom:6px">Revisa los siguientes campos:</div><ul style="margin:0;padding-left:18px">';
        foreach ($errors as $e) {
            $errorsHtml .= '<li>' . h((string)$e) . '</li>';
        }
        $errorsHtml .= '</ul></div>';
    }

    $submitUrl = $isEdit ? '/warehouses/' . (int)$id : '/warehouses';
    $title = $isEdit ? 'Editar bodega #' . (int)$id : 'Nueva bodega';
    $submitLabel = $isEdit ? 'Guardar cambios' : 'Crear bodega';

    $body = '<div class="erp-prod-shell" style="max-width:780px;margin:0 auto">
      <div class="erp-prod-panel">
        <div class="erp-prod-panel-head" style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid #e5e7eb;background:#f8fafc;border-radius:10px 10px 0 0">
          <div style="display:flex;align-items:center;gap:8px">
            <div>
              <div style="font-size:15px;font-weight:800">' . h($title) . '</div>
              <div class="muted" style="font-size:12px">'
                . ($isEdit ? 'Actualiza los datos maestros y capacidades de la bodega.' : 'Registra una nueva bodega conectada al ERP con su capacidad máxima en trazabilidad.')
              . '</div>
            </div>
          </div>
          <a class="btn secondary" href="/warehouses">← Volver</a>
        </div>
        <div class="erp-prod-panel-body" style="padding:16px">';

    if ($isEdit && !empty($warehouse['erp_storehouse_id'])) {
        $erpStId = (int)$warehouse['erp_storehouse_id'];
        $stockUnitsErp = (float)($warehouse['stock_units_erp'] ?? 0);
        $itemsCountErp = (int)($warehouse['items_count_erp'] ?? 0);
        $qtyPallets = (float)($warehouse['qty_pallets'] ?? 0);
        $qtyBobinas = (float)($warehouse['qty_bobinas'] ?? 0);
        $qtyCajas = (float)($warehouse['qty_cajas'] ?? 0);
        $qtyUnidades = (float)($warehouse['qty_unidades'] ?? 0);

        $body .= '<div style="margin-bottom:14px;padding:14px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <div>
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#15803d">Stock Real en Bodega (Inventario ERP)</div>
              <div style="font-size:22px;font-weight:800;color:#166534;margin-top:2px">
                ' . h(number_format($stockUnitsErp, 0, ',', '.')) . ' <span style="font-size:13px;font-weight:600">unidades totales</span>
              </div>
              <div style="font-size:12px;color:#166534;margin-top:4px;display:flex;gap:12px;flex-wrap:wrap">
                <span><strong>Pallets:</strong> ' . h(number_format($qtyPallets, 0, ',', '.')) . '</span>
                <span><strong>Bobinas:</strong> ' . h(number_format($qtyBobinas, 0, ',', '.')) . '</span>
                <span><strong>Cajas:</strong> ' . h(number_format($qtyCajas, 0, ',', '.')) . '</span>
                <span><strong>Unidades:</strong> ' . h(number_format($qtyUnidades, 0, ',', '.')) . '</span>
              </div>
              <div class="muted" style="font-size:12px;color:#15803d;margin-top:4px">
                ' . $itemsCountErp . ' artículos con existencias en ERP · Bodega ERP #' . $erpStId . '
              </div>
            </div>
            <div>
              <a class="btn secondary" href="/stock?bodega=' . $erpStId . '" target="_blank" style="display:inline-flex;align-items:center;gap:6px;font-size:12px">
                Ver detalle en Inventario ↗
              </a>
            </div>
          </div>
          <div style="margin-top:10px;padding-top:10px;border-top:1px solid #dcfce7;font-size:12px;color:#166534">
            <strong>Cálculo de ocupación:</strong> Define abajo la <strong>Capacidad total (unidades)</strong> o la <strong>Capacidad máxima (pallets)</strong>. Con este valor el sistema calculará automáticamente el % de ocupación exacto de esta bodega.
          </div>
        </div>';

        $body .= '<div style="margin-bottom:14px;padding:10px 12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;font-size:12px;color:#0369a1">
          <span style="font-weight:700">Bodega vinculada al ERP:</span> Storehouse ID <strong>#' . $erpStId . '</strong> (company_shops_storehouses).
          El código, nombre, descripción y clasificación se sincronizan con el ERP (<code>unibag_unibag</code>). Las capacidades máximas y la ocupación se gestionan en la base de datos de trazabilidad.
        </div>';
    }

    $body .= $errorsHtml;
    $body .= '<form method="post" action="' . h($submitUrl) . '">';
    $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';

    $body .= '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
      <div class="erp-prod-field">
        <label>Código bodega <span style="color:#dc2626">*</span></label>
        <input name="code" type="number" min="1" step="1" placeholder="Ej: 100" value="' . h((string)($defaults['code'] ?? '')) . '" required>
        <div class="muted" style="font-size:11px">Código numérico único (ej: 100, 200, 500, 700).</div>
      </div>
      <div class="erp-prod-field">
        <label>Nombre bodega <span style="color:#dc2626">*</span></label>
        <input name="name" type="text" placeholder="Ej: Bodega 100 - Recepción MP" value="' . h((string)($defaults['name'] ?? '')) . '" required>
      </div>
      <div class="erp-prod-field" style="grid-column:1 / -1">
        <label>Descripción / Observación (ERP)</label>
        <input name="desc" type="text" placeholder="Descripción en maestro ERP (company_shops_storehouses)" value="' . h((string)($defaults['desc'] ?? '')) . '">
        <div class="muted" style="font-size:11px">Descripción sincronizada directamente con el ERP unibag_unibag.</div>
      </div>
      <div class="erp-prod-field">
        <label>Clasificación (ERP)</label>
        <input name="clasificacion" type="text" placeholder="Ej: Materia Prima, Producto Terminado..." value="' . h((string)($defaults['clasificacion'] ?? '')) . '">
        <div class="muted" style="font-size:11px">Clasificación en sistema ERP.</div>
      </div>
      <div class="erp-prod-field">
        <label>Capacidad ERP</label>
        <input name="capacidad_erp" type="number" min="0" step="1" placeholder="0" value="' . h((string)($defaults['capacidad_erp'] ?? '0')) . '">
        <div class="muted" style="font-size:11px">Capacidad registrada en el maestro ERP.</div>
      </div>

      <div class="erp-prod-field" style="grid-column:1 / -1;background:#f8fafc;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0">
        <label style="font-weight:700;margin-bottom:8px;display:block">Asignación y Roles en ERP</label>
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;font-size:13px">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
            <input type="checkbox" name="reserva" value="1" ' . (!empty($defaults['reserva']) ? 'checked' : '') . '> Bodega de Reserva
          </label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
            <input type="checkbox" name="repuestos" value="1" ' . (!empty($defaults['repuestos']) ? 'checked' : '') . '> Bodega de Repuestos
          </label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
            <input type="checkbox" name="unibagreserva" value="1" ' . (!empty($defaults['unibagreserva']) ? 'checked' : '') . '> Reserva Unibag
          </label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
            <input type="checkbox" name="flexo" value="1" ' . (!empty($defaults['flexo']) ? 'checked' : '') . '> Área Flexografía
          </label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
            <input type="checkbox" name="seri" value="1" ' . (!empty($defaults['seri']) ? 'checked' : '') . '> Área Serigrafía
          </label>
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
            <input type="checkbox" name="selladora" value="1" ' . (!empty($defaults['selladora']) ? 'checked' : '') . '> Área Selladoras
          </label>
        </div>
      </div>

      <div class="erp-prod-field">
        <label>Capacidad total (unidades) · Trazabilidad</label>
        <input name="capacity_units_total" type="number" min="0" step="1" placeholder="0" value="' . h((string)($defaults['capacity_units_total'] ?? '0')) . '">
        <div class="muted" style="font-size:11px">Base de datos trazabilidad: cálculo % ocupación por unidades.</div>
      </div>
      <div class="erp-prod-field">
        <label>Capacidad máxima (pallets) · Trazabilidad</label>
        <input name="capacity_pallets" type="number" min="0" step="1" placeholder="0" value="' . h((string)($defaults['capacity_pallets'] ?? '0')) . '">
        <div class="muted" style="font-size:11px">Base de datos trazabilidad: si > 0 se calcula ocupación por pallets.</div>
      </div>
    </div>';

    if ($isEdit && $warehouse !== null) {
        $rollsStmt = $GLOBALS['trzPdo'] ?? null;
        $rollsCount = 0;
        $palletsCount = 0;
        $boxesCount = 0;
        try {
            if ($rollsStmt !== null) {
                $s1 = $rollsStmt->prepare('SELECT COUNT(*) AS c FROM rolls WHERE warehouse_id = :id');
                $s1->execute([':id' => $warehouse['id']]);
                $rollsCount = (int)($s1->fetch()['c'] ?? 0);
                $s2 = $rollsStmt->prepare('SELECT COUNT(*) AS c FROM pallets WHERE warehouse_id = :id');
                $s2->execute([':id' => $warehouse['id']]);
                $palletsCount = (int)($s2->fetch()['c'] ?? 0);
                $s3 = $rollsStmt->prepare('SELECT COUNT(*) AS c FROM boxes WHERE warehouse_id = :id');
                $s3->execute([':id' => $warehouse['id']]);
                $boxesCount = (int)($s3->fetch()['c'] ?? 0);
            }
        } catch (Throwable $e) {
            $rollsCount = 0;
        }
        if ($rollsCount > 0 || $palletsCount > 0 || $boxesCount > 0) {
            $body .= '<div style="margin-top:12px;padding:10px 12px;border:1px solid #fde68a;background:#fffbeb;border-radius:8px;font-size:12px">
              <div style="font-weight:700;color:#92400e;margin-bottom:4px">Atención · Stock en Trazabilidad asociado</div>
              <div class="muted">Esta bodega tiene stock registrado en trazabilidad y no se podrá eliminar hasta que se trasladen los artículos:</div>
              <ul style="margin:4px 0 0;padding-left:18px">
                <li>' . h((string)$rollsCount) . ' bobina(s)</li>
                <li>' . h((string)$palletsCount) . ' pallet(s)</li>
                <li>' . h((string)$boxesCount) . ' caja(s)</li>
              </ul>
            </div>';
        }
    }

    $body .= '<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
      <a class="btn secondary" href="/warehouses">Cancelar</a>
      <button class="btn" type="submit">' . h($submitLabel) . '</button>
    </div>';

    $body .= '</form>';
    $body .= '</div></div></div>';

    render($title, $body);
}

/**
 * Normaliza y parsea el payload POST del formulario de bodega.
 *
 * @return array{code:int,name:string,desc:string,capacity_units_total:int,capacity_pallets:int,extra_erp:array}
 */
function unibagWarehouseParseFormPayload(): array
{
    return [
        'code' => isset($_POST['code']) ? (int)$_POST['code'] : 0,
        'name' => trim((string)($_POST['name'] ?? '')),
        'desc' => trim((string)($_POST['desc'] ?? '')),
        'capacity_units_total' => isset($_POST['capacity_units_total']) ? (int)round((float)$_POST['capacity_units_total']) : 0,
        'capacity_pallets' => isset($_POST['capacity_pallets']) ? (int)$_POST['capacity_pallets'] : 0,
        'extra_erp' => [
            'clasificacion' => trim((string)($_POST['clasificacion'] ?? '')),
            'capacidad_erp' => (int)($_POST['capacidad_erp'] ?? 0),
            'reserva' => !empty($_POST['reserva']) ? 1 : 0,
            'repuestos' => !empty($_POST['repuestos']) ? 1 : 0,
            'unibagreserva' => !empty($_POST['unibagreserva']) ? 1 : 0,
            'flexo' => !empty($_POST['flexo']) ? 1 : 0,
            'seri' => !empty($_POST['seri']) ? 1 : 0,
            'selladora' => !empty($_POST['selladora']) ? 1 : 0,
        ],
    ];
}

/**
 * Router de rutas /warehouses.
 */
function handleWarehousesRoutes(string $path, string $method, ReceptionService $service): bool
{
    if (!str_starts_with($path, '/warehouses')) {
        return false;
    }

    if ($path === '/warehouses' && $method === 'GET') {
        unibagRenderWarehousesPage($service);
        return true;
    }

    if ($path === '/warehouses/new' && $method === 'GET') {
        unibagRenderWarehouseFormPage($service);
        return true;
    }

    if ($path === '/warehouses' && $method === 'POST') {
        requireCsrf();
        $payload = unibagWarehouseParseFormPayload();
        $result = $service->createWarehouse(
            (int)$payload['code'],
            (string)$payload['name'],
            (float)$payload['capacity_units_total'],
            (int)$payload['capacity_pallets'],
            (string)$payload['desc'],
            (array)$payload['extra_erp']
        );
        if (!$result['ok']) {
            unibagRenderWarehouseFormPage($service, null, $payload, $result['errors'] ?? []);
            return true;
        }
        redirectResponse('/warehouses?msg=' . rawurlencode('Bodega creada correctamente (#' . (int)($result['id'] ?? 0) . ').'));
        return true;
    }

    if (preg_match('#^/warehouses/(\d+)/edit$#', $path, $matches) === 1 && $method === 'GET') {
        unibagRenderWarehouseFormPage($service, (int)$matches[1]);
        return true;
    }

    if (preg_match('#^/warehouses/(\d+)$#', $path, $matches) === 1 && $method === 'POST') {
        requireCsrf();
        $id = (int)$matches[1];
        $payload = unibagWarehouseParseFormPayload();
        $result = $service->updateWarehouse(
            $id,
            (int)$payload['code'],
            (string)$payload['name'],
            (float)$payload['capacity_units_total'],
            (int)$payload['capacity_pallets'],
            (string)$payload['desc'],
            (array)$payload['extra_erp']
        );
        if (!$result['ok']) {
            unibagRenderWarehouseFormPage($service, $id, $payload, $result['errors'] ?? []);
            return true;
        }
        redirectResponse('/warehouses?msg=' . rawurlencode('Bodega actualizada correctamente.'));
        return true;
    }

    if (preg_match('#^/warehouses/(\d+)/delete$#', $path, $matches) === 1 && $method === 'POST') {
        requireCsrf();
        $id = (int)$matches[1];
        $result = $service->deleteWarehouse($id);
        if (!$result['ok']) {
            redirectResponse('/warehouses?error=' . rawurlencode(implode(' ', (array)($result['errors'] ?? ['Error desconocido.']))));
            return true;
        }
        redirectResponse('/warehouses?msg=' . rawurlencode('Bodega eliminada correctamente.'));
        return true;
    }

    return false;
}
