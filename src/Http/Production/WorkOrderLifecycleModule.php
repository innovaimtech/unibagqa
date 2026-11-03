<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Ciclo de Vida de OT (Iniciar Nueva OT, OTs Activas, Historial y Trazabilidad)
// (src/Http/Production/WorkOrderLifecycleModule.php)
// =============================================================================

function renderProdwrkShell(
    string $title,
    string $content,
    int $mid,
    string $operatorName,
    string $plantaName
): void {
    echo renderView('layouts/prodwrk_shell', get_defined_vars());
}

function renderDesignPreviewModal(): string
{
    static $rendered = false;
    if ($rendered) {
        return '';
    }
    $rendered = true;

    return renderView('production/partials/design_preview_modal');
}

function productionWorkOrderTableProcess(array $machine): string
{
    $typeId = (int)($machine['equipo_type_id'] ?? 0);
    $name = (string)($machine['equipo_name'] ?? '');
    $typeName = (string)($machine['type_ant_title'] ?? '');
    if (stripos($name, 'pulpo') !== false || (int)($machine['win_equipoid'] ?? 0) === 36) return 'pulpo';
    if ($typeId === 15 || stripos($name, 'embalaje') !== false || stripos($typeName, 'embalaje') !== false) return 'embalaje';
    if ($typeId === 8 || stripos($name, 'sellad') !== false || stripos($typeName, 'corte') !== false) return 'cys';
    return '';
}

function renderMachineWorkOrderTable(array $orders, string $view, string $process): void
{
    echo renderView('production/work_orders/machine_table', get_defined_vars());
}

function renderProductionNewWorkOrderScreen(
    array $activeInit,
    array $scheduledOrders,
    ?array $activeOt,
    string $operatorName,
    string $plantaName,
    ?array $activeStandby = null,
    ?string $dateFilter = null,
    array $unscheduledOrders = [],
    string $activeTab = 'scheduled',
    ?string $searchQuery = null,
    ?array $activeLunch = null
): void {
    $equipoTypeId = (int)($activeInit['equipo_type_id'] ?? 0);
    $isRebobinadora = ($equipoTypeId === 12 || stripos((string)($activeInit['type_ant_title'] ?? ''), 'rebobin') !== false || stripos((string)($activeInit['equipo_name'] ?? ''), 'rebobin') !== false);
    $tableProcess = productionWorkOrderTableProcess($activeInit);
    $isFlexo = ($equipoTypeId === 7
        || (int)($activeInit['equipo_prod_isprinter_flexo'] ?? 0) === 1
        || stripos((string)($activeInit['type_ant_title'] ?? ''), 'flexo') !== false
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'flexo') !== false);
    $isSeri = ((int)($activeInit['equipo_prod_isprinter_seri'] ?? 0) === 1)
        || ($equipoTypeId === 11 || $equipoTypeId === 22)
        || stripos((string)($activeInit['type_ant_title'] ?? ''), 'seri') !== false
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'seri') !== false
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'pulpo') !== false;

    $machineName = (string)($activeInit['equipo_name'] ?? ($isFlexo ? 'Flexografía' : ($isRebobinadora ? 'Rebobinadora' : ($isSeri ? 'Serigrafía' : 'Máquina'))));

    $content = renderView('production/work_orders/new_screen', get_defined_vars());
    renderProdwrkShell('Iniciar nueva OT', $content, 1, $operatorName, $plantaName);
}

function renderProductionActiveOrdersScreen(
    array $activeList,
    string $operatorName,
    string $plantaName,
    ?array $activeInit = null,
    ?array $activeStandby = null,
    ?string $dateFilter = null,
    ?array $activeLunch = null
): void {
    $equipoTypeId = (int)($activeInit['equipo_type_id'] ?? 0);
    $tableProcess = productionWorkOrderTableProcess($activeInit ?? []);
    $isRebobinadora = ($equipoTypeId === 12 || stripos((string)($activeInit['type_ant_title'] ?? ''), 'rebobin') !== false || stripos((string)($activeInit['equipo_name'] ?? ''), 'rebobin') !== false);
    $isFlexo = ($equipoTypeId === 7
        || (int)($activeInit['equipo_prod_isprinter_flexo'] ?? 0) === 1
        || stripos((string)($activeInit['type_ant_title'] ?? ''), 'flexo') !== false
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'flexo') !== false);
    $isSeri = ((int)($activeInit['equipo_prod_isprinter_seri'] ?? 0) === 1)
        || ($equipoTypeId === 11 || $equipoTypeId === 22)
        || stripos((string)($activeInit['type_ant_title'] ?? ''), 'seri') !== false
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'seri') !== false
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'pulpo') !== false;

    $content = renderView('production/work_orders/active_screen', get_defined_vars());
    renderProdwrkShell('En cursos', $content, 5, $operatorName, $plantaName);
}

function renderProductionHistoryScreen(array $historyList, string $operatorName, string $plantaName): void
{
    $content = renderView('production/work_orders/history_screen', get_defined_vars());
    renderProdwrkShell('Historico OTs', $content, 3, $operatorName, $plantaName);
}

function renderProductionTraceabilityScreen(
    string $query,
    ?array $tree,
    string $operatorName = 'Operador Planta',
    string $plantaName = 'Planta Santiago'
): void {
    echo renderView('production/work_orders/traceability_screen', get_defined_vars());
}

function handleWorkOrderLifecycleRoutes(
    string $path,
    string $method,
    ProductionService $prodService,
    ReceptionService $receptionService,
    string $currentOperatorName,
    int $workerId,
    int $plantaId,
    string $plantaName
): bool {
    // Peso registrado en trazabilidad de una bobina (roll_code o etiqueta de bodega): para completar el peso al escanear.
    if ($path === '/production/api/roll-weight' && $method === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        if (function_exists('session_write_close')) {
            session_write_close();
        }
        $code = trim((string)($_GET['code'] ?? ''));
        if ($code === '') {
            echo json_encode(['ok' => false, 'error' => 'Ingrese un código.']);
            return true;
        }
        try {
            $trz = Db::trzPdo();
            $roll = TraceableRollLookup::find($trz, $code);
            if (!$roll) {
                echo json_encode(['ok' => false, 'error' => 'No existe una bobina con ese código de trazabilidad.']);
                return true;
            }
            $kg = TraceableRollLookup::weightKg($trz, $roll, $code);
            echo json_encode(['ok' => true, 'roll_code' => (string)$roll['roll_code'], 'sku_code' => (string)($roll['sku_code'] ?? ''),
                'weight_kg' => $kg, 'has_weight' => $kg > 0], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('roll-weight: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'No se pudo consultar la trazabilidad.']);
        }
        return true;
    }

    if ($path === '/production/api/search-cc' && $method === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q === '') {
            echo json_encode(['ok' => false, 'error' => 'Ingrese un número de CC']);
            return true;
        }

        $stmt = Db::erpPdo()->prepare("SELECT t1.id, t1.req_number, t1.req_embalaje_medidas_caja,
                   t1.req_embalaje_bolsas_por_caja_amt, t1.req_embalaje_cajas_completas_amt,
                   t1.req_embalaje_caja_final, t1.req_operador_mermaperc,
                   c.cust_name, t1x.item_id, t1x.fab_design_imagehash,
                   i.item_number_prod, i.item_title
            FROM orders t1
            INNER JOIN customer c ON c.id = t1.req_cust_id
            INNER JOIN orders_items t1x ON t1.id = t1x.req_id
            LEFT JOIN item i ON i.id = t1x.item_id
            WHERE t1.req_number LIKE :q OR t1.id = :exact_id
            ORDER BY t1.id DESC LIMIT 1");
        $stmt->execute([
            ':q' => '%' . $q . '%',
            ':exact_id' => is_numeric($q) ? (int)$q : 0,
        ]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            echo json_encode(['ok' => false, 'error' => 'No se encontró la orden ' . htmlspecialchars($q)]);
            return true;
        }

        $stWh = Db::erpPdo()->prepare("SELECT st.id, st.st_name, ifnull(iss.iss_inventory, 0) AS stock
            FROM orders o
            INNER JOIN orders_items oi ON oi.req_id = o.id
            INNER JOIN item i1 ON i1.id = oi.item_id
            INNER JOIN customer c ON c.id = o.req_cust_id
            INNER JOIN bodegacanal bc ON c.cust_canal = bc.bodegacanal_codigo_canal
            INNER JOIN company_shops_storehouses st ON st.id = bc.bodegacanal_id_bodega
            LEFT JOIN item_shops_storehouses iss ON iss.item_id = i1.id AND iss.iss_order_id = o.id AND iss.st_id = st.id
            WHERE o.id = ?");
        $stWh->execute([(int)$order['id']]);
        $order['bodegas'] = $stWh->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode(['ok' => true, 'order' => $order]);
        return true;
    }

    // -------------------------------------------------------------------------
    // 1. ASIGNACIÓN DE MÁQUINAS / TURNOS (/production/machines · mid=0)

    if ($path === '/production/work-orders/new' && $method === 'GET') {
        $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
        if ($activeInit === null) {
            renderProductionNeedMachineScreen($currentOperatorName, $plantaName);
            return true;
        }

        $equipoId = (int)$activeInit['win_equipoid'];
        $equipoTypeId = (int)($activeInit['equipo_type_id'] ?? 0);
        $isFlexo = ($equipoTypeId === 7
            || (int)($activeInit['equipo_prod_isprinter_flexo'] ?? 0) === 1
            || stripos((string)($activeInit['type_ant_title'] ?? ''), 'flexo') !== false
            || stripos((string)($activeInit['equipo_name'] ?? ''), 'flexo') !== false);
        $isSeri = ((int)($activeInit['equipo_prod_isprinter_seri'] ?? 0) === 1)
            || ($equipoTypeId === 11 || $equipoTypeId === 22)
            || stripos((string)($activeInit['type_ant_title'] ?? ''), 'seri') !== false
            || stripos((string)($activeInit['equipo_name'] ?? ''), 'seri') !== false
            || stripos((string)($activeInit['equipo_name'] ?? ''), 'pulpo') !== false;

        $dateFilter = trim((string)($_GET['plan_date'] ?? $_GET['date'] ?? ''));
        if ($dateFilter === '') {
            $dateFilter = date('d.m.Y');
        } elseif ($dateFilter === 'all') {
            $dateFilter = null;
        }

        $activeTab = 'scheduled';
        $searchQuery = trim((string)($_GET['search'] ?? $_GET['q'] ?? ''));

        $scheduledOrders = $prodService->listScheduledOrdersForMachine($equipoId, $plantaId, $dateFilter);
        $unscheduledOrders = [];

        $activeOt = $prodService->getOpenWorkerOt($workerId, $plantaId);
        $activeStandby = $prodService->getActiveMachineStandby($equipoId, (int)$activeInit['id']);

        renderProductionNewWorkOrderScreen(
            $activeInit,
            $scheduledOrders,
            $activeOt,
            $currentOperatorName,
            $plantaName,
            $activeStandby,
            $dateFilter,
            $unscheduledOrders,
            $activeTab,
            $searchQuery,
            $receptionService->getActiveOperatorLunchBreak($workerId, (int)$activeInit['id'], $currentOperatorName)
        );
        return true;
    }

    if ($path === '/production/work-orders/init' && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_POST['ag_id'] ?? 0);

        $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
        if ($activeInit === null) {
            redirectResponse('/production/machines?error=' . rawurlencode('Debes iniciar turno en una máquina primero.'));
            return true;
        }

        $res = $prodService->startWorkerOt($agId, (int)$activeInit['id']);
        if ($res['ok']) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&started=1');
        } else {
            redirectResponse('/production/work-orders/new?error=' . rawurlencode((string)($res['error'] ?? 'Error al iniciar OT.')));
        }
        return true;
    }

    if ($path === '/production/work-orders/init-unscheduled' && $method === 'POST') {
        requireCsrf();
        redirectResponse('/production/work-orders/new?error=' . rawurlencode('Productos fuera de planificación deshabilitados para evitar sobrecarga del sistema.'));
        return true;
    }

    // -------------------------------------------------------------------------
    // 3. ACCIONES Y FORMULARIOS DE LA OT (POST o GET especiales)
    // -------------------------------------------------------------------------
    // Guardar asignación de Anilox (editxid.php líneas 108-158)
    if (isset($_REQUEST['save_anilox']) && (int)$_REQUEST['save_anilox'] === 1) {
        if ($method === 'POST') {
            requireCsrf();
        }
        $agId = (int)($_REQUEST['agid'] ?? 0);
        if ($agId <= 0) {
            $activeOt = $prodService->getOpenWorkerOt($workerId, $plantaId);
            if ($activeOt !== null && (int)$activeOt['wok_ag_id'] > 0) {
                $agId = (int)$activeOt['wok_ag_id'];
            }
        }
        $agenda = $prodService->getAgendaById($agId);
        $reqId = (int)($agenda['ag_reqid'] ?? 0);
        $aniloxMap = [];
        for ($x = 1; $x <= 6; $x++) {
            if (isset($_REQUEST['anilox' . $x])) {
                $aniloxMap[$x] = (int)$_REQUEST['anilox' . $x];
            }
        }
        $res = $prodService->saveAniloxAssignments($agId, $reqId, $aniloxMap, (int)($_SESSION['user_id'] ?? 0));
        if ($res['ok'] && $method === 'POST') {
            $openOt = $prodService->getOpenWorkerOtByAgId($workerId, $plantaId, $agId);
            $workerOtId = (int)($openOt['id'] ?? 0);
            $aniloxInkRows = [];
            $unitCount = max(6, (int)($_REQUEST['anilox_unit_count'] ?? 0));
            for ($x = 1; $x <= $unitCount; $x++) {
                $row = [
                    'unidad' => $x,
                    'posicion' => $x,
                    'color_label' => trim((string)($_REQUEST['color' . $x] ?? '')),
                    'anilox_id' => (int)($aniloxMap[$x] ?? 0),
                    'ink_item_id' => (int)($_REQUEST['ink_item_' . $x] ?? 0),
                    'loaded_kg' => (float)str_replace(',', '.', (string)($_REQUEST['ink_total_loaded_' . $x] ?? $_REQUEST['ink_loaded_' . $x] ?? 0)),
                    'leftover_kg' => (float)str_replace(',', '.', (string)($_REQUEST['ink_leftover_' . $x] ?? 0)),
                    'is_mixed' => !empty($_REQUEST['ink_is_mix_' . $x]) ? 1 : 0,
                    'mixture_notes' => trim((string)($_REQUEST['mix_notes_' . $x] ?? '')),
                    'components' => [],
                ];

                $componentItems = $_REQUEST['mix_component_item_' . $x] ?? [];
                $componentQtys = $_REQUEST['mix_component_qty_' . $x] ?? [];
                if (is_array($componentItems) || is_array($componentQtys)) {
                    $componentItems = is_array($componentItems) ? $componentItems : [];
                    $componentQtys = is_array($componentQtys) ? $componentQtys : [];
                    $componentsCount = max(count($componentItems), count($componentQtys));
                    for ($mixIdx = 0; $mixIdx < $componentsCount; $mixIdx++) {
                        $row['components'][] = [
                            'item_id' => (int)($componentItems[$mixIdx] ?? 0),
                            'qty_kg' => (float)str_replace(',', '.', (string)($componentQtys[$mixIdx] ?? 0)),
                        ];
                    }
                }

                $aniloxInkRows[] = $row;
            }

            $inkRes = $prodService->saveFlexoAniloxInkConfig($agId, $reqId, $workerOtId, $aniloxInkRows, (int)($_SESSION['user_id'] ?? 0));
            if (!($inkRes['ok'] ?? false)) {
                // El anilox ya quedó guardado en el ERP; se avisa que faltan las tintas para que reintente sin perder lo escrito.
                $res = ['ok' => false, 'error' => 'El anilox se guardó, pero las tintas NO. No cierre la pantalla y vuelva a presionar "Registrar Anilox". Detalle: ' . (string)($inkRes['error'] ?? '')];
            }
        }
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_REQUEST['ajax']) && (int)$_REQUEST['ajax'] === 1)
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            return true;
        }
        if (!$res['ok']) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&error=' . rawurlencode($res['error']));
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&saved_anilox=1');
        }
        return true;
    }

    // Mover unidades de Anilox (editxid.php líneas 160-265)
    if (isset($_REQUEST['mover']) && in_array((int)$_REQUEST['mover'], [1, 2], true) && isset($_REQUEST['unidad'])) {
        $agId = (int)($_REQUEST['agid'] ?? 0);
        if ($agId <= 0) {
            $activeOt = $prodService->getOpenWorkerOt($workerId, $plantaId);
            if ($activeOt !== null && (int)$activeOt['wok_ag_id'] > 0) {
                $agId = (int)$activeOt['wok_ag_id'];
            }
        }
        $unidad = (int)$_REQUEST['unidad'];
        $mover = (int)$_REQUEST['mover'];
        $agenda = $prodService->getAgendaById($agId);
        $reqId = (int)($agenda['ag_reqid'] ?? 0);
        $res = $prodService->moveAniloxUnit($agId, $reqId, $unidad, $mover);
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_REQUEST['ajax']) && (int)$_REQUEST['ajax'] === 1)
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            return true;
        }
        if (!$res['ok']) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&error=' . rawurlencode($res['error']));
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId);
        }
        return true;
    }


    if ($path === '/production/work-orders/active' && $method === 'GET') {
        $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
        $showAll = !empty($_GET['all']);
        $filterEquipoId = (!$showAll && $activeInit !== null) ? (int)$activeInit['win_equipoid'] : 0;

        $equipoTypeId = (int)($activeInit['equipo_type_id'] ?? 0);
        $isFlexo = ($equipoTypeId === 7
            || (int)($activeInit['equipo_prod_isprinter_flexo'] ?? 0) === 1
            || stripos((string)($activeInit['type_ant_title'] ?? ''), 'flexo') !== false
            || stripos((string)($activeInit['equipo_name'] ?? ''), 'flexo') !== false);
        $isSeri = ((int)($activeInit['equipo_prod_isprinter_seri'] ?? 0) === 1)
            || ($equipoTypeId === 11 || $equipoTypeId === 22)
            || stripos((string)($activeInit['type_ant_title'] ?? ''), 'seri') !== false
            || stripos((string)($activeInit['equipo_name'] ?? ''), 'seri') !== false
            || stripos((string)($activeInit['equipo_name'] ?? ''), 'pulpo') !== false;

        $isRebobinadora = ($equipoTypeId === 12
            || stripos((string)($activeInit['type_ant_title'] ?? ''), 'rebobin') !== false
            || stripos((string)($activeInit['equipo_name'] ?? ''), 'rebobin') !== false);

        $dateFilter = null;
        $reqPlanDate = trim((string)($_GET['plan_date'] ?? ''));
        if ($reqPlanDate === 'all') {
            $dateFilter = null;
        } elseif ($reqPlanDate !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $reqPlanDate, $m)) {
                $dateFilter = date('d.m.Y', strtotime($reqPlanDate));
            } else {
                $dateFilter = $reqPlanDate;
            }
        } else {
            $dateFilter = date('d.m.Y');
        }

        $activeList = $prodService->listActiveOrdersInCourse($plantaId, $filterEquipoId, $dateFilter);
        $activeStandby = ($activeInit !== null) ? $prodService->getActiveMachineStandby((int)$activeInit['win_equipoid'], (int)$activeInit['id']) : null;
        $activeLunch = $receptionService->getActiveOperatorLunchBreak($workerId, (int)($activeInit['id'] ?? 0), $currentOperatorName);
        renderProductionActiveOrdersScreen($activeList, $currentOperatorName, $plantaName, $activeInit, $activeStandby, $dateFilter, $activeLunch);
        return true;
    }

    if ($path === '/production/work-orders/history' && $method === 'GET') {
        $historyList = $prodService->listOperatorEventHistory($workerId, $plantaId, 100);
        renderProductionHistoryScreen($historyList, $currentOperatorName, $plantaName);
        return true;
    }

    if (($path === '/production/traceability' || $path === '/stock/traceability') && $method === 'GET') {
        $query = trim((string)($_GET['q'] ?? ($_GET['query'] ?? '')));
        $tree = $query !== '' ? $prodService->getTraceabilityTree($query) : null;
        renderProductionTraceabilityScreen($query, $tree, $currentOperatorName, $plantaName);
        return true;
    }

    // -------------------------------------------------------------------------
    // SOLICITAR MATERIALES / BOBINAS EN BODEGA 3000 (Menú lateral · mid=7 y mid=8)

    return false;
}
