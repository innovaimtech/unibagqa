<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Módulo HTTP: Control y Gestión de Horarios de Colación de Operarios
 * (src/Http/OperatorLunchModule.php)
 * =============================================================================
 *
 * Permite a los supervisores:
 * 1. Visualizar todos los operarios que han iniciado turno en el día (hayan ido o no a colación).
 * 2. Identificar alertas de faltas de colación (operarios que pasadas las 14:00 no han registrado colación).
 * 3. Modificar o registrar el horario de colación individualmente.
 * 4. Modificar de forma masiva por operarios seleccionados o por máquina.
 */

function unibagHandleOperatorLunchBreaks(ReceptionService $service): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $currentArea = normalizeErpArea((string)($_SESSION['erp_area'] ?? 'ERP'));
    $areaPerms = sessionAreaPermissions();

    if (!userCanAccessArea('ERP', $areaPerms)) {
        redirectResponse(firstAllowedAreaHome($areaPerms));
        return;
    }

    $date = trim((string)($_GET['date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    $currentUser = (string)($_SESSION['display_name'] ?? ($_SESSION['user_name'] ?? 'Supervisor'));

    // -------------------------------------------------------------------------
    // POST: Guardar registros individuales o masivos
    // -------------------------------------------------------------------------
    if ($method === 'POST') {
        requireCsrf();

        if (!unibagCanUserPerformModifications()) {
            $postDate = trim((string)($_POST['date'] ?? $date));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $postDate)) {
                $postDate = $date;
            }
            redirectResponse('/reports/colaciones?date=' . urlencode($postDate) . '&error=' . rawurlencode('Permiso denegado: únicamente los usuarios autorizados (HECTOR y JAVIER) pueden realizar modificaciones en colaciones.'));
            return;
        }

        $action = trim((string)($_POST['action'] ?? 'save_single'));
        $postDate = trim((string)($_POST['date'] ?? $date));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $postDate)) {
            $postDate = $date;
        }

        if ($action === 'save_single') {
            $opName = trim((string)($_POST['operator_name'] ?? ''));
            $startTime = trim((string)($_POST['start_time'] ?? ''));
            $endTime = trim((string)($_POST['end_time'] ?? ''));
            $workerId = (int)($_POST['worker_id'] ?? 0);
            $initId = (int)($_POST['init_id'] ?? 0);
            $machineId = (int)($_POST['machine_id'] ?? 0);
            $machineName = trim((string)($_POST['machine_name'] ?? ''));
            $comments = trim((string)($_POST['comments'] ?? ''));

            $res = $service->saveOperatorLunchBreak(
                $postDate,
                $opName,
                $startTime,
                $endTime,
                $workerId,
                $initId,
                $machineId,
                $machineName,
                $comments,
                $currentUser
            );

            if ($res['ok']) {
                redirectResponse('/reports/colaciones?date=' . urlencode($postDate) . '&saved=1');
            } else {
                redirectResponse('/reports/colaciones?date=' . urlencode($postDate) . '&error=' . rawurlencode((string)($res['error'] ?? 'Error al guardar colación.')));
            }
            return;
        }

        if ($action === 'save_bulk_selection') {
            $startDate = trim((string)($_POST['start_date'] ?? $postDate));
            $endDate = trim((string)($_POST['end_date'] ?? $startDate));
            $startTime = trim((string)($_POST['start_time'] ?? '12:00'));
            $endTime = trim((string)($_POST['end_time'] ?? '12:30'));
            $comments = trim((string)($_POST['comments'] ?? ''));
            $itemsJson = trim((string)($_POST['operators_payload'] ?? ''));
            $items = json_decode($itemsJson, true);

            if (!is_array($items) || empty($items)) {
                redirectResponse('/reports/colaciones?date=' . urlencode($postDate) . '&error=' . rawurlencode('No se seleccionó ningún operario.'));
                return;
            }

            if ($startDate === $endDate) {
                $count = 0;
                $errors = [];
                foreach ($items as $it) {
                    $opName = trim((string)($it['operator_name'] ?? ''));
                    if ($opName === '') continue;
                    $wId = (int)($it['worker_id'] ?? 0);
                    $initId = (int)($it['init_id'] ?? 0);
                    $mId = (int)($it['machine_id'] ?? 0);
                    $mName = (string)($it['machine_name'] ?? '');
                    $res = $service->saveOperatorLunchBreak(
                        $startDate,
                        $opName,
                        $startTime,
                        $endTime,
                        $wId,
                        $initId,
                        $mId,
                        $mName,
                        $comments,
                        $currentUser
                    );
                    if ($res['ok']) {
                        $count++;
                    } else {
                        $errors[] = $opName . ': ' . ($res['error'] ?? 'Error desconocido');
                    }
                }
                $msg = 'Se asignó horario de colación a ' . $count . ' operario(s) seleccionado(s).';
                if (!empty($errors)) {
                    $msg .= ' Hubo errores en: ' . implode(', ', $errors);
                }
                redirectResponse('/reports/colaciones?date=' . urlencode($postDate) . '&custom_msg=' . rawurlencode($msg));
                return;
            }

            $workerIds = [];
            foreach ($items as $it) {
                $wid = (int)($it['worker_id'] ?? 0);
                if ($wid > 0) $workerIds[] = $wid;
            }

            $res = $service->saveBulkLunchDateRange($workerIds, null, $startDate, $endDate, $startTime, $endTime, $comments, $currentUser);
            if ($res['ok']) {
                $msg = 'Se procesó la asignación masiva (' . $res['start_date'] . ' a ' . $res['end_date'] . '): ' . (int)$res['updated_count'] . ' colaciones registradas (para días donde el operario tuvo turno asignado o iniciado).';
                redirectResponse('/reports/colaciones?date=' . urlencode($postDate) . '&custom_msg=' . rawurlencode($msg));
            } else {
                redirectResponse('/reports/colaciones?date=' . urlencode($postDate) . '&error=' . rawurlencode((string)($res['error'] ?? 'Error en asignación masiva.')));
            }
            return;
        }

        if ($action === 'save_bulk_machine') {
            $rawWorkerIds = $_POST['worker_ids'] ?? [];
            $workerIds = [];
            if (is_array($rawWorkerIds)) {
                foreach ($rawWorkerIds as $wid) {
                    $widInt = (int)$wid;
                    if ($widInt > 0) $workerIds[] = $widInt;
                }
            }

            $startDate = trim((string)($_POST['start_date'] ?? $postDate));
            $endDate = trim((string)($_POST['end_date'] ?? $startDate));
            $startTime = trim((string)($_POST['start_time'] ?? '12:00'));
            $endTime = trim((string)($_POST['end_time'] ?? '12:30'));
            $comments = trim((string)($_POST['comments'] ?? ''));

            if (empty($workerIds)) {
                redirectResponse('/reports/colaciones?date=' . urlencode($postDate) . '&error=' . rawurlencode('Debe seleccionar al menos un operario del listado.'));
                return;
            }

            $res = $service->saveBulkLunchDateRange($workerIds, null, $startDate, $endDate, $startTime, $endTime, $comments, $currentUser);
            if ($res['ok']) {
                $msg = 'Se procesó la asignación masiva (' . $res['start_date'] . ' a ' . $res['end_date'] . ') para ' . count($workerIds) . ' operario(s): ' . (int)$res['updated_count'] . ' colaciones registradas (para días donde tuvieron turno asignado o iniciado).';
                redirectResponse('/reports/colaciones?date=' . urlencode($postDate) . '&custom_msg=' . rawurlencode($msg));
            } else {
                redirectResponse('/reports/colaciones?date=' . urlencode($postDate) . '&error=' . rawurlencode((string)($res['error'] ?? 'Error al actualizar las colaciones.')));
            }
            return;
        }
    }

    // -------------------------------------------------------------------------
    // GET: Renderizar pantalla de control de colaciones
    // -------------------------------------------------------------------------
    $report = $service->getOperatorLunchReport($date);
    $prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
    $nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
    $isToday = ($date === date('Y-m-d'));

    $flashMessage = '';
    $flashIsError = false;
    if (isset($_GET['custom_msg'])) {
        $flashMessage = (string)$_GET['custom_msg'];
    } elseif (isset($_GET['saved'])) {
        $flashMessage = 'Horario de colación actualizado correctamente.';
    } elseif (isset($_GET['bulk_saved'])) {
        $flashMessage = 'Se actualizaron las colaciones de ' . (int)$_GET['bulk_saved'] . ' operarios exitosamente.';
    } elseif (isset($_GET['machine_saved'])) {
        $flashMessage = 'Se asignó horario de colación a todos los operarios de ' . htmlspecialchars((string)$_GET['machine_saved']) . ' (' . (int)($_GET['count'] ?? 0) . ' actualizados).';
    } elseif (!empty($_GET['error'])) {
        $flashMessage = (string)$_GET['error'];
        $flashIsError = true;
    }

    $editOpParam = trim((string)($_GET['edit_op'] ?? ''));
    $canEdit = unibagCanUserPerformModifications();

    ob_start();
    ?>
    <style>
        .lunch-page-wrap { max-width: 1400px; margin: 0 auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e293b; }
        .lunch-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
        .lunch-title-group h1 { margin: 0; font-size: 24px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px; }
        .lunch-title-group p { margin: 4px 0 0; color: #64748b; font-size: 13.5px; }

        .date-nav { display: inline-flex; align-items: center; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .date-nav a, .date-nav button { padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 700; color: #334155; border: none; background: transparent; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .date-nav a:hover, .date-nav button:hover { background: #f1f5f9; color: #0f172a; }
        .date-nav input[type="date"] { border: 1px solid #cbd5e1; border-radius: 6px; padding: 5px 10px; font-size: 13px; font-weight: 700; color: #0f172a; background: #f8fafc; outline: none; }

        .lunch-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .kpi-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); position: relative; overflow: hidden; }
        .kpi-box::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; }
        .kpi-box.total::before { background: #3b82f6; }
        .kpi-box.success::before { background: #10b981; }
        .kpi-box.alert::before { background: #ea580c; }
        .kpi-box.machines::before { background: #8b5cf6; }
        .kpi-box .label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 6px; }
        .kpi-box .val { font-size: 28px; font-weight: 800; color: #0f172a; line-height: 1; }
        .kpi-box .sub { font-size: 12px; color: #94a3b8; margin-top: 6px; }

        .toolbar { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px 18px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .filter-chips { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .filter-chip { padding: 6px 14px; border-radius: 20px; font-size: 12.5px; font-weight: 700; cursor: pointer; border: 1px solid #e2e8f0; background: #f8fafc; color: #475569; transition: all 0.15s; }
        .filter-chip:hover { background: #f1f5f9; }
        .filter-chip.active { background: #0f172a; color: #ffffff; border-color: #0f172a; }
        .filter-chip.active-alert { background: #ea580c; color: #ffffff; border-color: #ea580c; }
        .filter-chip.active-success { background: #059669; color: #ffffff; border-color: #059669; }

        .action-btns { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .btn-modern { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; border: 1px solid transparent; text-decoration: none; transition: all 0.15s; }
        .btn-modern.primary { background: #ea580c; color: #ffffff; }
        .btn-modern.primary:hover { background: #c2410c; }
        .btn-modern.secondary { background: #ffffff; border-color: #cbd5e1; color: #334155; }
        .btn-modern.secondary:hover { background: #f8fafc; border-color: #94a3b8; }
        .btn-modern.blue { background: #0284c7; color: #ffffff; }
        .btn-modern.blue:hover { background: #0369a1; }

        .table-wrap { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.03); }
        .table-lunch { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
        .table-lunch thead th { background: #f8fafc; padding: 12px 16px; font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; border-bottom: 1px solid #e2e8f0; }
        .table-lunch tbody td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .table-lunch tbody tr:hover { background: #f8fafc; }
        .table-lunch tbody tr.row-alert { background: #fffaf5; }
        .table-lunch tbody tr.row-alert:hover { background: #fff5eb; }

        .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 800; }
        .status-badge.alert { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .status-badge.pending { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .status-badge.progress { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .status-badge.success { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }

        .preset-btn { padding: 4px 10px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; font-weight: 700; color: #334155; cursor: pointer; transition: all 0.15s; }
        .preset-btn:hover { background: #e2e8f0; color: #0f172a; }

        .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 12px; }
        .modal-card { background: #ffffff; border-radius: 16px; width: min(580px, 96vw); max-height: 88vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; border: 1px solid #e2e8f0; animation: modalIn 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
        .modal-card form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; overflow: hidden; margin: 0; }
        @keyframes modalIn { from { transform: scale(0.96); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-header { padding: 12px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
        .modal-header h3 { margin: 0; font-size: 15px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; }
        .modal-close { background: none; border: none; font-size: 22px; cursor: pointer; color: #64748b; line-height: 1; }
        .modal-body { padding: 14px 18px; overflow-y: auto; flex: 1 1 auto; min-height: 0; }
        .modal-footer { padding: 12px 18px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; flex-shrink: 0; }
        .form-row { margin-bottom: 10px; }
        .form-row label { display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 4px; }
        .form-control { width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 12.5px; color: #0f172a; box-sizing: border-box; }
        .form-control:focus { outline: none; border-color: #ea580c; box-shadow: 0 0 0 3px rgba(234,88,12,0.15); }
    </style>

    <div class="lunch-page-wrap">
        <?php if ($flashMessage !== ''): ?>
            <div style="margin-bottom:20px; padding:12px 18px; border-radius:10px; font-size:13px; font-weight:700; display:flex; align-items:center; gap:10px; <?=$flashIsError ? 'background:#fef2f2; color:#991b1b; border:1px solid #fecaca;' : 'background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0;'?>">
                <span><?=$flashIsError ? '⚠️' : '✅'?></span>
                <span><?=htmlspecialchars($flashMessage)?></span>
            </div>
        <?php endif; ?>

        <!-- Encabezado y Navegación de Fechas -->
        <div class="lunch-header">
            <div class="lunch-title-group">
                <h1><span>🍱</span> Control de Horario de Colación</h1>
                <p>Gestión y supervisión de colaciones para maquinistas de producción con turno en máquina (excluye embalaje, ayudantes y puestos sin máquina).</p>
            </div>

            <div class="date-nav">
                <a href="/reports/colaciones?date=<?=$prevDate?>" title="Día Anterior">←</a>
                <form method="get" action="/reports/colaciones" style="margin:0; display:inline-flex;">
                    <input type="date" name="date" value="<?=htmlspecialchars($date)?>" onchange="this.form.submit()">
                </form>
                <a href="/reports/colaciones?date=<?=$nextDate?>" title="Día Siguiente">→</a>
                <?php if (!$isToday): ?>
                    <a href="/reports/colaciones?date=<?=date('Y-m-d')?>" style="background:#e2e8f0; color:#0f172a; margin-left:4px;">Ir a Hoy</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tarjetas de KPIs -->
        <div class="lunch-kpis">
            <div class="kpi-box total">
                <div class="label">Maquinistas en Turno</div>
                <div class="val"><?=(int)$report['total_operators']?></div>
                <div class="sub">Jornada <?=date('d/m/Y', strtotime($date))?></div>
            </div>

            <div class="kpi-box success">
                <div class="label">Colaciones Registradas</div>
                <div class="val" style="color:#059669;"><?=(int)$report['registered_count']?></div>
                <div class="sub"><?=number_format((float)$report['compliance_percent'], 1, ',', '.')?>% de cumplimiento</div>
            </div>

            <div class="kpi-box alert">
                <div class="label">Faltas de Colación</div>
                <div class="val" style="color:#ea580c;"><?=(int)$report['missing_count']?></div>
                <div class="sub">
                    <?php if ($report['is_past_deadline']): ?>
                        <strong style="color:#dc2626;">⚠️ Pasaron las 14:00 hrs (Alerta Activa)</strong>
                    <?php else: ?>
                        <span>Pendientes (Horario límite: 14:00)</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="kpi-box machines">
                <div class="label">Máquinas Activas</div>
                <div class="val"><?=count($report['machines'])?></div>
                <div class="sub">Máquinas de producción activas</div>
            </div>
        </div>

        <!-- Barra de Herramientas y Acciones Masivas -->
        <div class="toolbar">
            <div class="filter-chips">
                <button type="button" class="filter-chip active" onclick="filterTable('all', this)">
                    Todos (<?=(int)$report['total_operators']?>)
                </button>
                <button type="button" class="filter-chip" onclick="filterTable('missing', this)">
                    ⚠️ Faltas de Colación (<?=(int)$report['missing_count']?>)
                </button>
                <button type="button" class="filter-chip" onclick="filterTable('registered', this)">
                    ✅ Registradas (<?=(int)$report['registered_count']?>)
                </button>
                <select id="machineFilter" class="form-control" style="width:auto; padding:5px 10px; font-weight:700; height:32px; font-size:12.5px;" onchange="filterByMachine(this.value)">
                    <option value="">Todas las Máquinas</option>
                    <?php foreach ($report['machines'] as $mName): ?>
                        <option value="<?=htmlspecialchars($mName)?>"><?=htmlspecialchars($mName)?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="action-btns">
                <?php if ($canEdit): ?>
                    <button type="button" class="btn-modern secondary" onclick="openMachineBulkModal()">
                        <span>👥</span> Asignar Masivo por Turno de Colación
                    </button>
                    <button type="button" class="btn-modern primary" id="btnBulkSelected" onclick="openSelectionBulkModal()" disabled style="opacity:0.6;">
                        <span>📝</span> Asignar Masivo a Seleccionados (<span id="selectedCount">0</span>)
                    </button>
                <?php else: ?>
                    <div style="font-size:12px; font-weight:700; color:#475569; background:#f1f5f9; padding:8px 14px; border-radius:8px; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:6px;">
                        🔒 Modificaciones restringidas exclusivamente a HECTOR y JAVIER
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabla Completa de Operarios -->
        <div class="table-wrap">
            <table class="table-lunch" id="lunchTable">
                <thead>
                    <tr>
                        <th style="width:36px; text-align:center;">
                            <?php if ($canEdit): ?>
                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" style="cursor:pointer;">
                            <?php else: ?>
                                <span style="font-size:11px; color:#94a3b8;">#</span>
                            <?php endif; ?>
                        </th>
                        <th>Maquinista</th>
                        <th>Máquina de Producción</th>
                        <th style="text-align:center;">Horario Turno</th>
                        <th style="text-align:center;">Inicio Colación</th>
                        <th style="text-align:center;">Fin Colación</th>
                        <th style="text-align:center;">Duración</th>
                        <th style="text-align:center;">Estado</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report['operators'])): ?>
                        <tr>
                            <td colspan="9" style="text-align:center; padding:40px; color:#64748b;">
                                <div style="font-size:32px; margin-bottom:10px;">📋</div>
                                <strong>No hay maquinistas con turno iniciado en máquina en la fecha seleccionada (<?=htmlspecialchars($date)?>).</strong>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($report['operators'] as $op): 
                            $isMissing = !$op['has_lunch'];
                            $rowClass = ($isMissing && $report['is_past_deadline']) ? 'row-alert' : '';
                        ?>
                            <tr class="<?=$rowClass?>" 
                                data-status="<?=$isMissing ? 'missing' : 'registered'?>" 
                                data-machine="<?=htmlspecialchars($op['machine_name'])?>"
                                data-op='<?=htmlspecialchars(json_encode($op, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, "UTF-8")?>'>
                                <td style="text-align:center;">
                                    <?php if ($canEdit): ?>
                                        <input type="checkbox" class="op-checkbox" onchange="updateSelectedCount()" style="cursor:pointer;">
                                    <?php else: ?>
                                        <span style="color:#cbd5e1;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                                        <span style="width:8px; height:8px; border-radius:50%; background:<?=$op['has_lunch'] ? '#10b981' : ($report['is_past_deadline'] ? '#ef4444' : '#f59e0b')?>;"></span>
                                        <?=htmlspecialchars($op['operator_name'])?>
                                    </div>
                                    <?php if ($op['worker_id'] > 0): ?>
                                        <div style="font-size:11px; color:#94a3b8; margin-left:16px;">ID: <?=$op['worker_id']?> · Turno #<?=$op['init_id']?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="display:inline-block; padding:3px 8px; background:#f1f5f9; border-radius:6px; font-weight:700; font-size:12px; color:#334155;">
                                        <?=htmlspecialchars($op['machine_name'])?>
                                    </span>
                                </td>
                                <td style="text-align:center; font-family:monospace; font-size:12.5px;">
                                    <strong><?=htmlspecialchars($op['shift_start'])?></strong>
                                    <span style="color:#94a3b8;">→</span>
                                    <span style="color:#64748b;"><?=htmlspecialchars($op['shift_end'])?></span>
                                </td>
                                <td style="text-align:center; font-family:monospace; font-size:13px; font-weight:700; color:#0f172a;">
                                    <?=$op['lunch_start'] !== '' ? htmlspecialchars($op['lunch_start']) : '<span style="color:#cbd5e1;">-</span>'?>
                                </td>
                                <td style="text-align:center; font-family:monospace; font-size:13px; font-weight:700; color:#0f172a;">
                                    <?=$op['lunch_end'] !== '' ? htmlspecialchars($op['lunch_end']) : '<span style="color:#cbd5e1;">-</span>'?>
                                </td>
                                <td style="text-align:center; font-size:12.5px; font-weight:600;">
                                    <?php if ($op['duration_minutes'] > 0): ?>
                                        <span style="color:#059669;"><?=(int)$op['duration_minutes']?> min</span>
                                    <?php else: ?>
                                        <span style="color:#cbd5e1;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($op['lunch_status'] === 'COMPLETADA'): ?>
                                        <span class="status-badge success">✅ Registrada</span>
                                    <?php elseif ($op['lunch_status'] === 'EN_CURSO'): ?>
                                        <span class="status-badge progress">⏳ En Colación</span>
                                    <?php elseif ($op['lunch_status'] === 'FALTA_CRITICA'): ?>
                                        <span class="status-badge alert">⚠️ Falta (>14:00)</span>
                                    <?php else: ?>
                                        <span class="status-badge pending">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($canEdit): ?>
                                        <button type="button" class="btn-modern secondary" style="padding:4px 10px; font-size:12px;" onclick='openSingleEditModal(this)'>
                                            ✏️ <?=!$op['has_lunch'] ? 'Asignar' : 'Editar'?>
                                        </button>
                                    <?php else: ?>
                                        <span style="font-size:11.5px; font-weight:700; color:#94a3b8;" title="Modificación restringida a HECTOR y JAVIER">🔒 Bloqueado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($canEdit): ?>
    <!-- MODAL 1: EDICIÓN INDIVIDUAL -->
    <div class="modal-backdrop" id="singleEditModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3><span>🍱</span> Asignar Horario de Colación</h3>
                <button type="button" class="modal-close" onclick="closeModal('singleEditModal')">&times;</button>
            </div>
            <form method="post" action="/reports/colaciones" style="margin:0;">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="action" value="save_single">
                <input type="hidden" name="date" value="<?=htmlspecialchars($date)?>">
                <input type="hidden" name="worker_id" id="singleWorkerId" value="">
                <input type="hidden" name="init_id" id="singleInitId" value="">
                <input type="hidden" name="machine_id" id="singleMachineId" value="">
                <input type="hidden" name="machine_name" id="singleMachineName" value="">
                <input type="hidden" name="operator_name" id="singleOperatorNameHidden" value="">

                <div class="modal-body">
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; margin-bottom:16px;">
                        <div style="font-size:11px; text-transform:uppercase; font-weight:700; color:#64748b;">Operario Seleccionado</div>
                        <div style="font-size:15px; font-weight:800; color:#0f172a; margin-top:2px;" id="singleOperatorNameDisplay">-</div>
                        <div style="font-size:12px; color:#475569; margin-top:2px;" id="singleMachineDisplay">-</div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                        <div class="form-row">
                            <label>Hora Inicio Colación *</label>
                            <input type="time" name="start_time" id="singleStartTime" class="form-control" required>
                        </div>
                        <div class="form-row">
                            <label>Hora Fin Colación *</label>
                            <input type="time" name="end_time" id="singleEndTime" class="form-control" required>
                        </div>
                    </div>

                    <!-- Presets de 30 minutos -->
                    <div style="margin-bottom:16px;">
                        <label style="font-size:11.5px; font-weight:700; color:#64748b; margin-bottom:6px; display:block;">Horarios frecuentes (30 min):</label>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <button type="button" class="preset-btn" onclick="applyPreset('singleStartTime','singleEndTime','12:00','12:30')">12:00 - 12:30</button>
                            <button type="button" class="preset-btn" onclick="applyPreset('singleStartTime','singleEndTime','12:45','13:15')">12:45 - 13:15</button>
                            <button type="button" class="preset-btn" onclick="applyPreset('singleStartTime','singleEndTime','13:30','14:00')">13:30 - 14:00</button>
                        </div>
                    </div>

                    <div class="form-row">
                        <label>Observaciones (opcional)</label>
                        <textarea name="comments" id="singleComments" class="form-control" rows="2" placeholder="Motivo o notas del supervisor..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern secondary" onclick="closeModal('singleEditModal')">Cancelar</button>
                    <button type="submit" class="btn-modern primary">💾 Guardar Horario</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: ASIGNACIÓN MASIVA POR SELECCIÓN -->
    <div class="modal-backdrop" id="selectionBulkModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3><span>📝</span> Asignación Masiva a Seleccionados</h3>
                <button type="button" class="modal-close" onclick="closeModal('selectionBulkModal')">&times;</button>
            </div>
            <form method="post" action="/reports/colaciones" style="margin:0;">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="action" value="save_bulk_selection">
                <input type="hidden" name="date" value="<?=htmlspecialchars($date)?>">
                <input type="hidden" name="operators_payload" id="bulkOperatorsPayload" value="">

                <div class="modal-body">
                    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:8px 12px; margin-bottom:12px;">
                        <span style="font-size:12.5px; font-weight:700; color:#1e40af;">
                            Se aplicará a <span id="modalSelectedCount" style="font-size:14px; font-weight:800;">0</span> operarios seleccionados
                        </span>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:10px;">
                        <div class="form-row">
                            <label>Fecha Desde *</label>
                            <input type="date" name="start_date" id="bulkStartDate" class="form-control" value="<?=htmlspecialchars($date)?>" required>
                        </div>
                        <div class="form-row">
                            <label>Fecha Hasta *</label>
                            <input type="date" name="end_date" id="bulkEndDate" class="form-control" value="<?=htmlspecialchars($date)?>" required>
                        </div>
                    </div>

                    <div style="margin-bottom:10px;">
                        <label style="font-size:12px; font-weight:700; color:#334155; margin-bottom:4px; display:block;">Turno de Colación (30 min):</label>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <button type="button" class="preset-btn" onclick="applyPreset('bulkStartTime','bulkEndTime','12:00','12:30')">12:00 - 12:30</button>
                            <button type="button" class="preset-btn" onclick="applyPreset('bulkStartTime','bulkEndTime','12:45','13:15')">12:45 - 13:15</button>
                            <button type="button" class="preset-btn" onclick="applyPreset('bulkStartTime','bulkEndTime','13:30','14:00')">13:30 - 14:00</button>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:10px;">
                        <div class="form-row">
                            <label>Hora Inicio Colación *</label>
                            <input type="time" name="start_time" id="bulkStartTime" class="form-control" value="12:00" required>
                        </div>
                        <div class="form-row">
                            <label>Hora Fin Colación *</label>
                            <input type="time" name="end_time" id="bulkEndTime" class="form-control" value="12:30" required>
                        </div>
                    </div>

                    <div class="form-row" style="margin-bottom:0;">
                        <label>Observaciones (opcional)</label>
                        <textarea name="comments" class="form-control" rows="2" placeholder="Observaciones o notas del turno..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern secondary" onclick="closeModal('selectionBulkModal')">Cancelar</button>
                    <button type="submit" class="btn-modern primary">⚡ Aplicar a Seleccionados</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: ASIGNACIÓN MASIVA POR TURNO DE COLACIÓN -->
    <div class="modal-backdrop" id="machineBulkModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3><span>👥</span> Asignar Masivo por Turno de Colación</h3>
                <button type="button" class="modal-close" onclick="closeModal('machineBulkModal')">&times;</button>
            </div>
            <form method="post" action="/reports/colaciones" style="margin:0;">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="action" value="save_bulk_machine">
                <input type="hidden" name="date" value="<?=htmlspecialchars($date)?>">

                <div class="modal-body">
                    <!-- Listado de Todos los Operarios para Seleccionar -->
                    <div class="form-row">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label style="margin:0; font-size:12.5px; font-weight:800; color:#0f172a;">Listado de Operadores para el Turno *</label>
                            <span id="bulkModalOpsCount" style="font-size:11.5px; font-weight:800; color:#0284c7; background:#e0f2fe; padding:2px 8px; border-radius:10px;">0 seleccionados</span>
                        </div>
                        <div style="display:flex; gap:8px; margin-bottom:6px;">
                            <input type="text" id="bulkOpSearch" placeholder="🔍 Buscar por nombre o cargo..." oninput="filterBulkModalOps(this.value)" class="form-control" style="font-size:12px; padding:5px 8px;">
                            <button type="button" class="preset-btn" onclick="bulkModalSelectAll(true)" style="white-space:nowrap;">Todos</button>
                            <button type="button" class="preset-btn" onclick="bulkModalSelectAll(false)" style="white-space:nowrap;">Ninguno</button>
                        </div>
                        <div id="bulkOpsListContainer" style="max-height:135px; overflow-y:auto; border:1px solid #cbd5e1; border-radius:8px; background:#ffffff; padding:4px;">
                            <?php foreach ($report['all_workers'] as $w): ?>
                                <label class="bulk-op-item" style="display:flex; align-items:center; gap:8px; padding:5px 8px; cursor:pointer; border-radius:6px; font-size:12px; border-bottom:1px solid #f8fafc; transition:background 0.1s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                    <input type="checkbox" name="worker_ids[]" value="<?=$w['id']?>" class="bulk-worker-checkbox" onchange="updateBulkModalOpsCount()" style="cursor:pointer; width:15px; height:15px;">
                                    <span class="bulk-op-name" style="font-weight:700; color:#0f172a;"><?=htmlspecialchars($w['name'])?></span>
                                    <span class="bulk-op-cargo" style="font-size:11px; color:#64748b; margin-left:auto; background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?=htmlspecialchars($w['cargo_name'] ?: 'Operario')?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:8px;">
                        <div class="form-row">
                            <label>Fecha Desde *</label>
                            <input type="date" name="start_date" class="form-control" value="<?=htmlspecialchars($date)?>" required>
                        </div>
                        <div class="form-row">
                            <label>Fecha Hasta *</label>
                            <input type="date" name="end_date" class="form-control" value="<?=htmlspecialchars($date)?>" required>
                        </div>
                    </div>

                    <div style="margin-bottom:8px;">
                        <label style="font-size:12px; font-weight:800; color:#334155; margin-bottom:4px; display:block;">Seleccione Turno de Colación (30 min):</label>
                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <button type="button" class="preset-btn" style="padding:5px 10px; font-weight:800; background:#e0f2fe; color:#0369a1; border-color:#bae6fd;" onclick="applyPreset('machineStartTime','machineEndTime','12:00','12:30')">Turno 1: 12:00 a 12:30</button>
                            <button type="button" class="preset-btn" style="padding:5px 10px; font-weight:800; background:#fef3c7; color:#b45309; border-color:#fde68a;" onclick="applyPreset('machineStartTime','machineEndTime','12:45','13:15')">Turno 2: 12:45 a 13:15</button>
                            <button type="button" class="preset-btn" style="padding:5px 10px; font-weight:800; background:#dcfce7; color:#15803d; border-color:#86efac;" onclick="applyPreset('machineStartTime','machineEndTime','13:30','14:00')">Turno 3: 13:30 a 14:00</button>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:8px;">
                        <div class="form-row">
                            <label>Hora Inicio Colación *</label>
                            <input type="time" name="start_time" id="machineStartTime" class="form-control" value="12:00" required>
                        </div>
                        <div class="form-row">
                            <label>Hora Fin Colación *</label>
                            <input type="time" name="end_time" id="machineEndTime" class="form-control" value="12:30" required>
                        </div>
                    </div>

                    <div class="form-row" style="margin-bottom:0;">
                        <label>Observaciones (opcional)</label>
                        <textarea name="comments" class="form-control" rows="2" placeholder="Observaciones o notas del turno..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern secondary" onclick="closeModal('machineBulkModal')">Cancelar</button>
                    <button type="submit" class="btn-modern primary">⚡ Aplicar Turno a Seleccionados</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function filterTable(status, chip) {
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active', 'active-alert', 'active-success'));
            if (status === 'missing') chip.classList.add('active-alert');
            else if (status === 'registered') chip.classList.add('active-success');
            else chip.classList.add('active');

            var machineVal = document.getElementById('machineFilter').value.toLowerCase();
            var rows = document.querySelectorAll('#lunchTable tbody tr[data-status]');
            rows.forEach(function(row) {
                var rStatus = row.getAttribute('data-status');
                var rMachine = (row.getAttribute('data-machine') || '').toLowerCase();
                var matchStatus = (status === 'all' || rStatus === status);
                var matchMachine = (machineVal === '' || rMachine === machineVal);
                row.style.display = (matchStatus && matchMachine) ? '' : 'none';
            });
            updateSelectedCount();
        }

        function filterByMachine(mVal) {
            var activeChip = document.querySelector('.filter-chip.active, .filter-chip.active-alert, .filter-chip.active-success');
            var status = 'all';
            if (activeChip && activeChip.textContent.includes('Faltas')) status = 'missing';
            if (activeChip && activeChip.textContent.includes('Registradas')) status = 'registered';

            mVal = (mVal || '').toLowerCase();
            var rows = document.querySelectorAll('#lunchTable tbody tr[data-status]');
            rows.forEach(function(row) {
                var rStatus = row.getAttribute('data-status');
                var rMachine = (row.getAttribute('data-machine') || '').toLowerCase();
                var matchStatus = (status === 'all' || rStatus === status);
                var matchMachine = (mVal === '' || rMachine === mVal);
                row.style.display = (matchStatus && matchMachine) ? '' : 'none';
            });
            updateSelectedCount();
        }

        function toggleSelectAll(master) {
            var cbs = document.querySelectorAll('#lunchTable tbody tr:not([style*="display: none"]) .op-checkbox');
            cbs.forEach(cb => cb.checked = master.checked);
            updateSelectedCount();
        }

        function updateSelectedCount() {
            var checked = document.querySelectorAll('#lunchTable tbody tr:not([style*="display: none"]) .op-checkbox:checked');
            var count = checked.length;
            document.getElementById('selectedCount').innerText = count;
            var btn = document.getElementById('btnBulkSelected');
            if (count > 0) {
                btn.removeAttribute('disabled');
                btn.style.opacity = '1';
            } else {
                btn.setAttribute('disabled', 'disabled');
                btn.style.opacity = '0.6';
            }
        }

        function applyPreset(startId, endId, startVal, endVal) {
            document.getElementById(startId).value = startVal;
            document.getElementById(endId).value = endVal;
        }

        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function openSingleEditModal(btn) {
            var tr = btn.closest('tr');
            var data = JSON.parse(tr.getAttribute('data-op'));
            document.getElementById('singleWorkerId').value = data.worker_id || 0;
            document.getElementById('singleInitId').value = data.init_id || 0;
            document.getElementById('singleMachineId').value = data.machine_id || 0;
            document.getElementById('singleMachineName').value = data.machine_name || '';
            document.getElementById('singleOperatorNameHidden').value = data.operator_name || '';
            document.getElementById('singleOperatorNameDisplay').innerText = data.operator_name || '';
            document.getElementById('singleMachineDisplay').innerText = 'Máquina: ' + (data.machine_name || 'Sin máquina') + ' · Turno: ' + data.shift_start + ' a ' + data.shift_end;
            
            document.getElementById('singleStartTime').value = data.lunch_start || '12:00';
            document.getElementById('singleEndTime').value = data.lunch_end || '12:30';
            document.getElementById('singleComments').value = data.comments || '';

            openModal('singleEditModal');
        }

        function openSelectionBulkModal() {
            var checked = document.querySelectorAll('#lunchTable tbody tr:not([style*="display: none"]) .op-checkbox:checked');
            if (checked.length === 0) return;

            var payload = [];
            checked.forEach(function(cb) {
                var tr = cb.closest('tr');
                var data = JSON.parse(tr.getAttribute('data-op'));
                payload.push(data);
            });

            document.getElementById('bulkOperatorsPayload').value = JSON.stringify(payload);
            document.getElementById('modalSelectedCount').innerText = payload.length;
            openModal('selectionBulkModal');
        }

        function openMachineBulkModal() {
            openModal('machineBulkModal');
        }

        function filterBulkModalOps(query) {
            query = query.toLowerCase().trim();
            var items = document.querySelectorAll('.bulk-op-item');
            items.forEach(function(item) {
                var name = (item.querySelector('.bulk-op-name')?.textContent || '').toLowerCase();
                var cargo = (item.querySelector('.bulk-op-cargo')?.textContent || '').toLowerCase();
                if (query === '' || name.includes(query) || cargo.includes(query)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function bulkModalSelectAll(check) {
            var cbs = document.querySelectorAll('.bulk-op-item:not([style*="display: none"]) .bulk-worker-checkbox');
            cbs.forEach(function(cb) { cb.checked = check; });
            updateBulkModalOpsCount();
        }

        function updateBulkModalOpsCount() {
            var count = document.querySelectorAll('.bulk-worker-checkbox:checked').length;
            var badge = document.getElementById('bulkModalOpsCount');
            if (badge) {
                badge.innerText = count + ' seleccionados';
            }
        }



        // Auto abrir modal si viene parámetro edit_op
        <?php if ($editOpParam !== ''): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var targetName = <?=json_encode($editOpParam)?>;
                var rows = document.querySelectorAll('#lunchTable tbody tr[data-op]');
                rows.forEach(function(row) {
                    var data = JSON.parse(row.getAttribute('data-op'));
                    if (data.operator_name && data.operator_name.toLowerCase() === targetName.toLowerCase()) {
                        var btn = row.querySelector('button[onclick*="openSingleEditModal"]');
                        if (btn) btn.click();
                    }
                });
            });
        <?php endif; ?>
    </script>
    <?php
    $html = ob_get_clean();
    render('🍱 Control de Horario de Colación', $html);
}
