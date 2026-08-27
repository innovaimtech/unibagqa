<?php

declare(strict_types=1);

function unibagRenderWarehousesPage(ReceptionService $service): void
{
    $message = trim((string)($_GET['msg'] ?? ''));
    $error = trim((string)($_GET['error'] ?? ''));
    $rows = $service->listWarehousesWithCapacities();

    $body = '<div class="erp-prod-shell" style="max-width:none;width:100%">
      <div class="erp-prod-panel" style="margin-bottom:12px">
        <div class="erp-prod-panel-head" data-collapse-toggle style="cursor:pointer;display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid #e5e7eb;background:#f8fafc;border-radius:10px 10px 0 0">
          <div style="display:flex;align-items:center;gap:8px">
            <span class="erp-collapse-toggle" aria-hidden="true" style="display:inline-block;width:10px;height:10px;border-right:2px solid #475569;border-bottom:2px solid #475569;transform:rotate(45deg);margin-right:4px"></span>
            <div>
              <div style="font-size:15px;font-weight:800">Maestro de bodegas</div>
              <div class="muted" style="font-size:12px">Administra las bodegas y sus capacidades máximas usadas por el panel de ocupación.</div>
            </div>
          </div>
          <div>
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
          <th style="padding:8px 10px;text-align:right">Cap. unidades</th>
          <th style="padding:8px 10px;text-align:right">Cap. pallets</th>
          <th style="padding:8px 10px;text-align:right">Stock unidades</th>
          <th style="padding:8px 10px;text-align:right">Pallets</th>
          <th style="padding:8px 10px;text-align:right">Bobinas</th>
          <th style="padding:8px 10px;text-align:right">Cajas</th>
          <th style="padding:8px 10px;text-align:right">Ocupación</th>
          <th style="padding:8px 10px;text-align:center">Acciones</th>
        </tr>
      </thead>
      <tbody>';

    if ($rows === []) {
        $body .= '<tr><td colspan="10" class="muted" style="padding:20px;text-align:center">No hay bodegas registradas. Crea la primera usando el botón "Nueva bodega".</td></tr>';
    }

    foreach ($rows as $row) {
        $occupancy = $row['occupancy_percent'];
        $occupancyHtml = $occupancy === null
            ? '<span class="muted">—</span>'
            : '<span style="font-weight:700;' . ((float)$occupancy >= 90 ? 'color:#dc2626' : ((float)$occupancy >= 70 ? 'color:#d97706' : 'color:#16a34a')) . '">' . h(number_format((float)$occupancy, 2, ',', '.')) . '%</span>';
        $body .= '<tr style="border-bottom:1px solid #e5e7eb">
          <td style="padding:8px 10px;font-weight:700">' . h((string)$row['code']) . '</td>
          <td style="padding:8px 10px">' . h((string)$row['name']) . '</td>
          <td style="padding:8px 10px;text-align:right">' . h(number_format((int)round((float)$row['capacity_units_total']), 0, ',', '.')) . '</td>
          <td style="padding:8px 10px;text-align:right">' . h((string)$row['capacity_pallets']) . '</td>
          <td style="padding:8px 10px;text-align:right">' . h(number_format((int)round((float)$row['stock_units_total']), 0, ',', '.')) . '</td>
          <td style="padding:8px 10px;text-align:right">' . h((string)$row['pallets_count']) . '</td>
          <td style="padding:8px 10px;text-align:right">' . h((string)$row['rolls_count']) . '</td>
          <td style="padding:8px 10px;text-align:right">' . h((string)$row['boxes_count']) . '</td>
          <td style="padding:8px 10px;text-align:right">' . $occupancyHtml . '</td>
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
                . ($isEdit ? 'Actualiza los datos y capacidades de la bodega.' : 'Registra una nueva bodega con su capacidad máxima.')
              . '</div>
            </div>
          </div>
          <a class="btn secondary" href="/warehouses">← Volver</a>
        </div>
        <div class="erp-prod-panel-body" style="padding:16px">';

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
      <div class="erp-prod-field">
        <label>Capacidad total (unidades)</label>
        <input name="capacity_units_total" type="number" min="0" step="1" placeholder="0" value="' . h((string)($defaults['capacity_units_total'] ?? '0')) . '">
        <div class="muted" style="font-size:11px">Se usa para calcular % ocupación si no hay capacidad por pallets.</div>
      </div>
      <div class="erp-prod-field">
        <label>Capacidad máxima (pallets)</label>
        <input name="capacity_pallets" type="number" min="0" step="1" placeholder="0" value="' . h((string)($defaults['capacity_pallets'] ?? '0')) . '">
        <div class="muted" style="font-size:11px">Si es mayor a 0, la ocupación se calcula por pallets (prioridad).</div>
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
              <div style="font-weight:700;color:#92400e;margin-bottom:4px">Atención · Stock asociado</div>
              <div class="muted">Esta bodega tiene stock registrado y no se podrá eliminar hasta que se trasladen los artículos:</div>
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

function unibagWarehouseParseFormPayload(): array
{
    return [
        'code' => isset($_POST['code']) ? (int)$_POST['code'] : 0,
        'name' => trim((string)($_POST['name'] ?? '')),
        'capacity_units_total' => isset($_POST['capacity_units_total']) ? (int)round((float)$_POST['capacity_units_total']) : 0,
        'capacity_pallets' => isset($_POST['capacity_pallets']) ? (int)$_POST['capacity_pallets'] : 0,
    ];
}

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
            (int)$payload['capacity_pallets']
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
            (int)$payload['capacity_pallets']
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
