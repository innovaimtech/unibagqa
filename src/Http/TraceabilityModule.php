<?php

declare(strict_types=1);

/**
 * HTTP controller and view renderer for the Traceability Module.
 * Displays the 360° order lifecycle, bottleneck tracker, search filters, and detail dossier.
 */

function handleTraceabilityRoutes(
    string $path,
    string $method,
    ReceptionService $receptionService,
    ProductionService $prodService
): bool {
    $cleanPath = rtrim($path, '/');
    if ($cleanPath === '') {
        $cleanPath = '/';
    }

    if ($cleanPath !== '/trazabilidad' && $cleanPath !== '/traceability' && !str_starts_with($cleanPath, '/trazabilidad/')) {
        return false;
    }

    $pdo = Db::erpPdo();
    $traceService = new TraceabilityService($pdo);

    // Sub-route: API Guía de Despacho (JSON for floating modal)
    if ($cleanPath === '/trazabilidad/api/guia') {
        header('Content-Type: application/json; charset=utf-8');
        $dlvId = (int)($_GET['id'] ?? 0);
        if ($dlvId <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de guía no especificado']);
            return true;
        }
        $dlv = $traceService->getDeliveryDetail($dlvId);
        if (!$dlv) {
            echo json_encode(['success' => false, 'error' => 'Guía de despacho no encontrada']);
            return true;
        }
        echo json_encode(['success' => true, 'delivery' => $dlv]);
        return true;
    }

    // Sub-route: API Evento de Producción (JSON for floating modal)
    if ($cleanPath === '/trazabilidad/api/evento') {
        header('Content-Type: application/json; charset=utf-8');
        $evtId = (int)($_GET['id'] ?? 0);
        if ($evtId <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de evento no especificado']);
            return true;
        }
        $sql = "
            SELECT 
                evt.*,
                e.id as ag_equipo_id,
                e.equipo_name,
                e.equipo_code,
                e.equipo_desc,
                pa.id as agenda_id,
                pa.ag_date,
                pa.ag_equipotype_id,
                pa.ag_amount as planned_amount,
                ph.prd_number as ot_number,
                o.req_number as nv_number,
                o.req_oc_number as oc_number,
                c.cust_name,
                COALESCE(NULLIF(TRIM(CONCAT(w.wrk_firstname, ' ', w.wrk_lastname)), ''), TRIM(CONCAT(u.user_firstname, ' ', u.user_lastname)), 'No asignado') as operator_name,
                COALESCE(TRIM(CONCAT(w_ay.wrk_firstname, ' ', w_ay.wrk_lastname)), '') as helper_name
            FROM prod_worker_ot_events evt
            INNER JOIN prod_worker_ot pwo ON evt.evt_prod_worker_otid = pwo.id
            INNER JOIN prod_agenda pa ON pwo.wok_ag_id = pa.id
            INNER JOIN prod_header ph ON pa.ag_prdid = ph.id
            LEFT JOIN orders o ON ph.prd_reqid = o.id
            LEFT JOIN customer c ON o.req_cust_id = c.id
            LEFT JOIN equipo e ON pa.ag_equipo_id = e.id
            LEFT JOIN prod_worker_init pwi ON pwo.wok_init_id = pwi.id
            LEFT JOIN workers w ON pwi.win_wrkid = w.id
            LEFT JOIN user u ON (pwo.wok_idusuario > 0 AND pwo.wok_idusuario = u.id)
            LEFT JOIN workers w_ay ON evt.evt_idayudante = w_ay.id
            WHERE evt.id = :evt_id
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':evt_id' => $evtId]);
        $ev = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ev) {
            echo json_encode(['success' => false, 'error' => 'Evento de producción no encontrado']);
            return true;
        }

        $crtdat = (int)$ev['evt_crtdat'];
        $enddat = (int)$ev['evt_enddat'];
        $durSec = ($enddat > $crtdat && $enddat > 0) ? ($enddat - $crtdat) : 0;
        $hrs = floor($durSec / 3600);
        $mins = floor(($durSec % 3600) / 60);

        $ev['duration_sec'] = $durSec;
        $ev['duration_text'] = ($enddat > 0 && $durSec > 0) ? ($hrs > 0 ? "{$hrs}h {$mins}m" : "{$mins} min") : ($enddat > 0 ? "Menos de 1 min" : "En curso");
        $ev['start_fmt'] = $crtdat > 0 ? date('d/m/Y H:i', $crtdat) : '-';
        $ev['end_fmt'] = $enddat > 0 ? date('d/m/Y H:i', $enddat) : 'En proceso';

        echo json_encode(['success' => true, 'event' => $ev]);
        return true;
    }

    // Sub-route: Order Detail Dossier
    if ($cleanPath === '/trazabilidad/detalle' || isset($_GET['id'])) {
        $orderId = (int)($_GET['id'] ?? 0);
        if ($orderId <= 0 && !empty($_GET['oc'])) {
            $orderId = (int)($traceService->findOrderIdByOc((string)$_GET['oc']) ?? 0);
        }
        if ($orderId <= 0 && !empty($_GET['ot'])) {
            $orderId = (int)($traceService->findOrderIdByOt((string)$_GET['ot']) ?? 0);
        }
        if ($orderId <= 0 && !empty($_GET['cc'])) {
            $orderId = (int)($traceService->findOrderIdByNv((string)$_GET['cc']) ?? 0);
        }

        if ($orderId > 0) {
            $detail = $traceService->getOrderDetail($orderId);
            if ($detail) {
                renderTraceabilityDetailView($detail, $traceService);
                return true;
            }
        }
    }

    // Default: Main Traceability Search & Pipeline Dashboard
    $filters = [
        'q' => trim((string)($_GET['q'] ?? '')),
        'oc' => trim((string)($_GET['oc'] ?? '')),
        'cc' => trim((string)($_GET['cc'] ?? '')),
        'ot' => trim((string)($_GET['ot'] ?? '')),
        'customer' => trim((string)($_GET['customer'] ?? '')),
        'print_type' => trim((string)($_GET['print_type'] ?? '')),
        'stage' => trim((string)($_GET['stage'] ?? 'all')),
        'date_from' => trim((string)($_GET['date_from'] ?? '')),
        'date_to' => trim((string)($_GET['date_to'] ?? '')),
    ];

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;

    $stats = $traceService->getStats($filters);
    $searchResult = $traceService->searchOrders($filters, $page, $perPage);

    renderTraceabilityListView($searchResult, $stats, $filters);
    return true;
}

/**
 * Render the main search & pipeline view.
 */
function renderTraceabilityListView(array $searchResult, array $stats, array $filters): void
{
    $items = $searchResult['items'] ?? [];
    $total = $searchResult['total'] ?? 0;
    $page = $searchResult['page'] ?? 1;
    $totalPages = $searchResult['total_pages'] ?? 1;

    $activeStage = $filters['stage'] ?? 'all';
    $q = $filters['q'] ?? '';

    ob_start();
    ?>
    <div class="trz-container">
        <!-- Page Header -->
        <div class="trz-header">
            <div class="trz-header-main">
                <div class="trz-title-group">
                    <span class="trz-tag">ERP Unibag</span>
                    <h1 class="trz-title">Trazabilidad 360° de Pedidos & OTs</h1>
                    <p class="trz-desc">
                        Seguimiento continuo de todo el proceso productivo: Orden de Compra, Clisés, Pre-prensa, Planificación OT, Fabricación en Planta y Despacho.
                    </p>
                </div>
                <div class="trz-header-actions">
                    <button type="button" class="trz-btn trz-btn-outline" onclick="window.print()">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Imprimir Listado
                    </button>
                    <a href="/trazabilidad" class="trz-btn trz-btn-primary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Actualizar Vista
                    </a>
                </div>
            </div>

            <!-- KPI Summary Cards -->
            <div class="trz-kpi-grid">
                <a href="/trazabilidad?stage=all" class="trz-kpi-card <?= $activeStage === 'all' ? 'active' : '' ?>">
                    <div class="trz-kpi-header">
                        <span class="trz-kpi-icon icon-blue">📋</span>
                        <span class="trz-kpi-badge">Total</span>
                    </div>
                    <div class="trz-kpi-value"><?= number_format($stats['total_active'], 0, ',', '.') ?></div>
                    <div class="trz-kpi-label">Pedidos Activos</div>
                    <div class="trz-kpi-sub">En flujo comercial / planta</div>
                </a>

                <a href="/trazabilidad?stage=clises_espera" class="trz-kpi-card <?= $activeStage === 'clises_espera' ? 'active' : '' ?>">
                    <div class="trz-kpi-header">
                        <span class="trz-kpi-icon icon-orange">🎨</span>
                        <span class="trz-kpi-badge badge-warning">Esperando</span>
                    </div>
                    <div class="trz-kpi-value"><?= number_format($stats['clises_espera'], 0, ',', '.') ?></div>
                    <div class="trz-kpi-label">Compra / Recepción Clisés</div>
                    <div class="trz-kpi-sub">Proveedores de fotopolímeros</div>
                </a>

                <a href="/trazabilidad?stage=preparacion_espera" class="trz-kpi-card <?= $activeStage === 'preparacion_espera' ? 'active' : '' ?>">
                    <div class="trz-kpi-header">
                        <span class="trz-kpi-icon icon-yellow">📐</span>
                        <span class="trz-kpi-badge badge-warning">Pre-Prensa</span>
                    </div>
                    <div class="trz-kpi-value"><?= number_format($stats['preparacion_espera'], 0, ',', '.') ?></div>
                    <div class="trz-kpi-label">Checklist Técnico</div>
                    <div class="trz-kpi-sub">Validación pre-prensa</div>
                </a>

                <a href="/trazabilidad?stage=planificacion_espera" class="trz-kpi-card <?= $activeStage === 'planificacion_espera' ? 'active' : '' ?>">
                    <div class="trz-kpi-header">
                        <span class="trz-kpi-icon icon-purple">📅</span>
                        <span class="trz-kpi-badge badge-info">Por Agendar</span>
                    </div>
                    <div class="trz-kpi-value"><?= number_format($stats['planificacion_espera'], 0, ',', '.') ?></div>
                    <div class="trz-kpi-label">Planificación Máquinas</div>
                    <div class="trz-kpi-sub">Sin OT o sin agenda activa</div>
                </a>

                <a href="/trazabilidad?stage=produccion" class="trz-kpi-card <?= $activeStage === 'produccion' ? 'active' : '' ?>">
                    <div class="trz-kpi-header">
                        <span class="trz-kpi-icon icon-cyan">⚙️</span>
                        <span class="trz-kpi-badge badge-primary">En Máquina</span>
                    </div>
                    <div class="trz-kpi-value"><?= number_format($stats['produccion'], 0, ',', '.') ?></div>
                    <div class="trz-kpi-label">Fabricación en Planta</div>
                    <div class="trz-kpi-sub">Turnos flexo, seri, CYS</div>
                </a>

                <a href="/trazabilidad?stage=despacho_pendiente" class="trz-kpi-card <?= $activeStage === 'despacho_pendiente' ? 'active' : '' ?>">
                    <div class="trz-kpi-header">
                        <span class="trz-kpi-icon icon-green">🚚</span>
                        <span class="trz-kpi-badge badge-amber">Por Despachar</span>
                    </div>
                    <div class="trz-kpi-value"><?= number_format($stats['despacho_pendiente'], 0, ',', '.') ?></div>
                    <div class="trz-kpi-label">Pendiente Despacho</div>
                    <div class="trz-kpi-sub">Producción lista / Saldo pendiente</div>
                </a>
            </div>
        </div>

        <!-- Filter Form Bar -->
        <div class="trz-filter-card">
            <form method="GET" action="/trazabilidad" id="trzFilterForm">
                <input type="hidden" name="stage" value="<?= h($activeStage) ?>">

                <div class="trz-search-main">
                    <div class="trz-search-box">
                        <svg class="trz-search-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input 
                            type="text" 
                            name="q" 
                            class="trz-search-input" 
                            placeholder="Buscar por N° Orden de Compra (OC), Nota de Venta (NV), N° OT, Cliente, SKU, Código de Clisé..." 
                            value="<?= h($q) ?>"
                            autocomplete="off"
                        >
                        <?php if ($q !== ''): ?>
                            <a href="/trazabilidad?stage=<?= h($activeStage) ?>" class="trz-search-clear" title="Limpiar búsqueda">✕</a>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="trz-btn trz-btn-submit">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Buscar
                    </button>
                    <button type="button" class="trz-btn trz-btn-toggle" onclick="toggleTrzAdvancedFilters()">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                        Filtros Avanzados
                    </button>
                </div>

                <!-- Advanced Filters Panel -->
                <div class="trz-advanced-filters" id="trzAdvancedFilters" style="display: <?= (!empty($filters['oc']) || !empty($filters['cc']) || !empty($filters['ot']) || !empty($filters['customer']) || !empty($filters['print_type']) || !empty($filters['date_from']) || !empty($filters['date_to'])) ? 'grid' : 'none' ?>;">
                    <div class="trz-field">
                        <label>N° Orden Compra (OC Cliente)</label>
                        <input type="text" name="oc" class="trz-input" placeholder="Ej: 32994, 6509040080" value="<?= h($filters['oc']) ?>">
                    </div>

                    <div class="trz-field">
                        <label>N° Nota de Venta (NV / CC)</label>
                        <input type="text" name="cc" class="trz-input" placeholder="Ej: 26-00705-1, NV0002347" value="<?= h($filters['cc']) ?>">
                    </div>

                    <div class="trz-field">
                        <label>N° Orden de Trabajo (OT)</label>
                        <input type="text" name="ot" class="trz-input" placeholder="Ej: 003772" value="<?= h($filters['ot']) ?>">
                    </div>

                    <div class="trz-field">
                        <label>Cliente / Razón Social</label>
                        <input type="text" name="customer" class="trz-input" placeholder="Nombre cliente o RUT" value="<?= h($filters['customer']) ?>">
                    </div>

                    <div class="trz-field">
                        <label>Tipo de Impresión</label>
                        <select name="print_type" class="trz-input">
                            <option value="">-- Todos los Tipos --</option>
                            <option value="FLEX" <?= $filters['print_type'] === 'FLEX' ? 'selected' : '' ?>>Flexografía (FLEX)</option>
                            <option value="SERI" <?= $filters['print_type'] === 'SERI' ? 'selected' : '' ?>>Serigrafía (SERI)</option>
                            <option value="PULP" <?= $filters['print_type'] === 'PULP' ? 'selected' : '' ?>>Pulpo (PULP)</option>
                            <option value="SIN" <?= $filters['print_type'] === 'SIN' ? 'selected' : '' ?>>Sin Impresión</option>
                        </select>
                    </div>

                    <div class="trz-field">
                        <label>Fecha Desde</label>
                        <input type="date" name="date_from" class="trz-input" value="<?= h($filters['date_from']) ?>">
                    </div>

                    <div class="trz-field">
                        <label>Fecha Hasta</label>
                        <input type="date" name="date_to" class="trz-input" value="<?= h($filters['date_to']) ?>">
                    </div>

                    <div class="trz-field trz-field-actions">
                        <label>&nbsp;</label>
                        <div class="trz-btn-row">
                            <button type="submit" class="trz-btn trz-btn-primary" style="flex:1">Aplicar</button>
                            <a href="/trazabilidad" class="trz-btn trz-btn-outline">Reestablecer</a>
                        </div>
                    </div>
                </div>

                <!-- Stage Quick Tabs -->
                <div class="trz-stage-tabs">
                    <span class="trz-tabs-label">Etapa:</span>
                    <a href="/trazabilidad?stage=all<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="trz-tab <?= $activeStage === 'all' ? 'active' : '' ?>">Todas las OTs</a>
                    <a href="/trazabilidad?stage=clises_espera<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="trz-tab <?= $activeStage === 'clises_espera' ? 'active' : '' ?>">⏳ Esperando Clisés</a>
                    <a href="/trazabilidad?stage=preparacion_espera<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="trz-tab <?= $activeStage === 'preparacion_espera' ? 'active' : '' ?>">📐 Pre-Prensa</a>
                    <a href="/trazabilidad?stage=planificacion_espera<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="trz-tab <?= $activeStage === 'planificacion_espera' ? 'active' : '' ?>">📅 Por Planificar</a>
                    <a href="/trazabilidad?stage=produccion<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="trz-tab <?= $activeStage === 'produccion' ? 'active' : '' ?>">⚙️ En Producción</a>
                    <a href="/trazabilidad?stage=despacho_pendiente<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="trz-tab <?= $activeStage === 'despacho_pendiente' ? 'active' : '' ?>">🚚 Pendiente Despacho</a>
                    <a href="/trazabilidad?stage=entregado<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="trz-tab <?= $activeStage === 'entregado' ? 'active' : '' ?>">✅ Finalizadas</a>
                </div>
            </form>
        </div>

        <!-- Results Counter & Info -->
        <div class="trz-results-info">
            <div>
                Mostrando <strong><?= count($items) ?></strong> de <strong><?= number_format($total, 0, ',', '.') ?></strong> pedidos registrados
                <?php if ($activeStage !== 'all'): ?>
                    en etapa <span class="trz-highlight-stage"><?= h($activeStage) ?></span>
                <?php endif; ?>
                <?php if ($q !== ''): ?>
                    para búsqueda "<em><?= h($q) ?></em>"
                <?php endif; ?>
            </div>
            <div class="trz-results-right">
                Página <?= $page ?> de <?= max(1, $totalPages) ?>
            </div>
        </div>

        <!-- Orders Table / List -->
        <?php if (empty($items)): ?>
            <div class="trz-empty-state">
                <div class="trz-empty-icon">🔍</div>
                <h3>No se encontraron pedidos con los criterios especificados</h3>
                <p>Intente con otro término de búsqueda (ej. N° de OC, Nota de Venta, OT o Cliente), o limpie los filtros avanzados.</p>
                <a href="/trazabilidad" class="trz-btn trz-btn-primary">Ver Todos los Pedidos Activos</a>
            </div>
        <?php else: ?>
            <div class="trz-table-card">
                <div class="trz-table-responsive">
                    <table class="trz-table">
                        <thead>
                            <tr>
                                <th style="width: 140px;">Identificadores</th>
                                <th style="width: 220px;">Cliente & Producto</th>
                                <th style="width: 65px; text-align: center;">Diseño</th>
                                <th style="width: 160px;">Etapa Actual</th>
                                <th>¿Qué está esperando? (Bloqueador)</th>
                                <th style="width: 160px;">Avance Producción</th>
                                <th style="width: 120px; text-align: center;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <?php
                                    $orderId = (int)$item['id'];
                                    $nv = trim((string)$item['req_number']);
                                    $oc = trim((string)$item['req_oc_number']);
                                    $ot = trim((string)$item['ot_number']);
                                    $custName = trim((string)$item['cust_name']);
                                    $itemTitle = trim((string)$item['item_titles']);
                                    $printTypes = trim((string)$item['print_types']);
                                    $imgHash = trim((string)($item['fab_design_imagehash'] ?? ''));
                                    $totalAmt = (float)$item['total_order_amount'];
                                    $producedAmt = (float)$item['produced_units'];
                                    $shippedAmt = (float)$item['shipped_units'];
                                    $prodPct = (float)$item['prod_pct'];
                                    $waitingFor = $item['waiting_for'];
                                    $stageLabel = $item['stage_label'];
                                    $stageKey = $item['stage_key'];
                                    $badgeClass = $item['stage_badge_class'];
                                    $isBlocked = (bool)$item['is_blocked'];
                                ?>
                                <tr class="trz-row <?= $isBlocked ? 'is-blocked' : '' ?>">
                                    <!-- Identificadores -->
                                    <td class="trz-cell-ids">
                                        <?php if ($oc !== ''): ?>
                                            <div class="trz-oc-pill" title="Orden de Compra del Cliente">
                                                <span class="trz-oc-label">OC</span>
                                                <span class="trz-oc-num"><?= h($oc) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="trz-text-muted" style="font-size:11px;">(Sin OC Cliente)</span>
                                        <?php endif; ?>

                                        <div class="trz-nv-sub">
                                            <strong>NV:</strong> <a href="/trazabilidad/detalle?id=<?= $orderId ?>" class="trz-link-nv"><?= h($nv) ?></a>
                                        </div>

                                        <?php if ($ot !== ''): ?>
                                            <div class="trz-ot-badge">
                                                <span>OT #<?= h($ot) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="trz-no-ot">Sin OT generada</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Cliente & Producto -->
                                    <td class="trz-cell-client">
                                        <div class="trz-client-name" title="<?= h($custName) ?>">
                                            <?= h($custName !== '' ? $custName : 'Cliente no especificado') ?>
                                        </div>
                                        <?php if (!empty($item['cust_rut'])): ?>
                                            <div class="trz-client-rut"><?= h($item['cust_rut']) ?></div>
                                        <?php endif; ?>
                                        <div class="trz-product-title" title="<?= h($itemTitle) ?>">
                                            <?= h($itemTitle !== '' ? $itemTitle : 'Bolsa personalizada') ?>
                                        </div>
                                        <?php if ($printTypes !== ''): ?>
                                            <span class="trz-print-badge"><?= h($printTypes) ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Diseño Thumbnail -->
                                    <td class="trz-cell-thumb" style="text-align: center;">
                                        <?php if ($imgHash !== '' && $imgHash !== 'dummy'): ?>
                                            <?php $imgUrl = '/production/design-image?folder=order&hash=' . rawurlencode($imgHash); ?>
                                            <div class="trz-thumb-wrap" onclick="openTrzDesignModal('<?= h($imgUrl) ?>', '<?= h(addslashes($itemTitle)) ?>', '<?= h($nv) ?>')" title="Click para ver diseño con rotación 360°">
                                                <img src="<?= h($imgUrl) ?>" alt="Diseño" class="trz-thumb-img" loading="lazy">
                                                <span class="trz-thumb-zoom">🔍</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="trz-no-thumb" title="Sin imagen de diseño">
                                                <span>📐</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Etapa Actual -->
                                    <td class="trz-cell-stage">
                                        <span class="trz-badge <?= h($badgeClass) ?>">
                                            <?= h($stageLabel) ?>
                                        </span>
                                        <?php if ($item['req_crtdat'] > 0): ?>
                                            <div class="trz-date-sub">
                                                Ingreso: <?= date('d/m/Y', (int)$item['req_crtdat']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- ¿Qué está esperando? -->
                                    <td class="trz-cell-blocker">
                                        <div class="trz-blocker-box <?= $isBlocked ? 'box-blocked' : 'box-ok' ?>">
                                            <div class="trz-blocker-icon">
                                                <?= $isBlocked ? '⏳' : '✓' ?>
                                            </div>
                                            <div class="trz-blocker-text">
                                                <?= h($waitingFor) ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Avance Producción & Despacho -->
                                    <td class="trz-cell-progress">
                                        <div class="trz-prog-header">
                                            <span class="trz-prog-pct"><?= number_format($prodPct, 1) ?>%</span>
                                            <span class="trz-prog-counts"><?= number_format($producedAmt, 0, ',', '.') ?> / <?= number_format($totalAmt, 0, ',', '.') ?></span>
                                        </div>
                                        <div class="trz-bar-track">
                                            <div class="trz-bar-fill" style="width: <?= min(100, $prodPct) ?>%;"></div>
                                        </div>
                                        <div class="trz-ship-sub">
                                            <?php if ($shippedAmt > 0): ?>
                                                <span class="trz-shipped-tag">🚚 Despachado: <?= number_format($shippedAmt, 0, ',', '.') ?></span>
                                            <?php else: ?>
                                                <span class="trz-text-muted" style="font-size:11px;">Pendiente de despacho</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="trz-cell-actions" style="text-align: center;">
                                        <a href="/trazabilidad/detalle?id=<?= $orderId ?>" class="trz-btn trz-btn-detail" title="Ver dossier completo 360°">
                                            <span>Ver 360°</span>
                                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Bar -->
                <?php if ($totalPages > 1): ?>
                    <div class="trz-pagination">
                        <div class="trz-pagination-info">
                            Página <?= $page ?> de <?= $totalPages ?>
                        </div>
                        <div class="trz-pagination-links">
                            <?php if ($page > 1): ?>
                                <a href="<?= buildTrzUrl($filters, $page - 1) ?>" class="trz-page-link">« Anterior</a>
                            <?php endif; ?>

                            <?php
                                $startP = max(1, $page - 3);
                                $endP = min($totalPages, $page + 3);
                                for ($p = $startP; $p <= $endP; $p++):
                            ?>
                                <a href="<?= buildTrzUrl($filters, $p) ?>" class="trz-page-link <?= $p === $page ? 'active' : '' ?>">
                                    <?= $p ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= buildTrzUrl($filters, $page + 1) ?>" class="trz-page-link">Siguiente »</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modals for Designs, Deliveries and Production -->
    <?php renderTrzRotatorModalHtml(); ?>
    <?php renderTrzDeliveryModalHtml(); ?>
    <?php renderTrzProdModalHtml(); ?>

    <!-- Traceability Styles -->
    <?php renderTraceabilityStyles(); ?>

    <script>
        function toggleTrzAdvancedFilters() {
            var el = document.getElementById('trzAdvancedFilters');
            if (el.style.display === 'none' || el.style.display === '') {
                el.style.display = 'grid';
            } else {
                el.style.display = 'none';
            }
        }
    </script>
    <?php
    $body = ob_get_clean();
    render('Trazabilidad de Pedidos | ERP Unibag', $body);
}

/**
 * Render the 360° Detail Dossier for a single order.
 */
function renderTraceabilityDetailView(array $detail, TraceabilityService $traceService): void
{
    $order = $detail['order'];
    $analysis = $detail['analysis'];
    $items = $detail['items'];
    $agendas = $detail['agendas'];
    $events = $detail['events'];
    $deliveries = $detail['deliveries'];
    $attachments = $detail['attachments'];
    $timeline = $detail['timeline'];

    $orderId = (int)$order['id'];
    $nv = trim((string)$order['req_number']);
    $oc = trim((string)$order['req_oc_number']);
    $ot = trim((string)$order['ot_number']);
    $custName = trim((string)$order['cust_name']);
    $custRut = trim((string)$order['cust_rut']);
    $totalAmount = (float)$order['total_order_amount'];
    $produced = (float)$order['produced_units'];
    $shipped = (float)$order['shipped_units'];
    $balance = max(0, $totalAmount - $shipped);
    $prodPct = (float)$analysis['prod_pct'];
    $waitingFor = $analysis['waiting_for'];
    $stageLabel = $analysis['stage_label'];
    $isBlocked = (bool)$analysis['is_blocked'];

    // Primary item for specs
    $primaryItem = !empty($items) ? $items[0] : [];
    $imgHash = trim((string)($primaryItem['fab_design_imagehash'] ?? ''));

    ob_start();
    ?>
    <div class="trz-container">
        <!-- Top Navigation Back Bar -->
        <div class="trz-back-bar">
            <a href="/trazabilidad" class="trz-back-link">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Volver a la búsqueda de Trazabilidad
            </a>
            <div class="trz-back-actions">
                <button type="button" class="trz-btn trz-btn-outline" onclick="window.print()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Imprimir Informe 360°
                </button>
            </div>
        </div>

        <!-- Dossier Header -->
        <div class="trz-dossier-card">
            <div class="trz-dossier-head">
                <div class="trz-dossier-left">
                    <div class="trz-dossier-tags">
                        <span class="trz-badge-dossier badge-nv">Nota de Venta: <?= h($nv) ?></span>
                        <?php if ($oc !== ''): ?>
                            <span class="trz-badge-dossier badge-oc">OC Cliente: <?= h($oc) ?></span>
                        <?php else: ?>
                            <span class="trz-badge-dossier badge-empty">(Sin OC Cliente)</span>
                        <?php endif; ?>
                        <?php if ($ot !== ''): ?>
                            <span class="trz-badge-dossier badge-ot">OT Planta: #<?= h($ot) ?></span>
                        <?php endif; ?>
                    </div>
                    <h1 class="trz-dossier-title"><?= h($custName) ?></h1>
                    <div class="trz-dossier-meta">
                        <span><strong>RUT:</strong> <?= h($custRut !== '' ? $custRut : 'No registrado') ?></span>
                        <span>•</span>
                        <span><strong>Fecha Ingreso:</strong> <?= (int)$order['req_crtdat'] > 0 ? date('d/m/Y', (int)$order['req_crtdat']) : '-' ?></span>
                        <span>•</span>
                        <span><strong>Entrega Prometida:</strong> <?= h($order['req_despacho_desc'] ?: '-') ?></span>
                    </div>
                </div>
                <div class="trz-dossier-right">
                    <div class="trz-dossier-stage-badge <?= h($analysis['stage_badge_class']) ?>">
                        <span class="stage-dot"></span>
                        <?= h($stageLabel) ?>
                    </div>
                </div>
            </div>

            <!-- Prominent Blocker Alert Box -->
            <div class="trz-callout <?= $isBlocked ? 'callout-blocked' : 'callout-ok' ?>">
                <div class="trz-callout-icon">
                    <?= $isBlocked ? '🚨' : '✅' ?>
                </div>
                <div class="trz-callout-content">
                    <div class="trz-callout-title">
                        <?= $isBlocked ? '¿QUÉ ESTÁ ESPERANDO ESTE PEDIDO? (CUELLO DE BOTELLA)' : 'ESTADO DEL PEDIDO: EN FLUJO NORMAL' ?>
                    </div>
                    <div class="trz-callout-text">
                        <?= h($waitingFor) ?>
                    </div>
                </div>
            </div>

            <!-- Milestones Stepper (6 Etapas del Ciclo) -->
            <div class="trz-stepper-wrap">
                <h3 class="trz-stepper-heading">Línea de Vida del Pedido (Proceso Completo)</h3>
                <div class="trz-stepper">
                    <?php foreach ($timeline as $st): ?>
                        <?php
                            $stepClass = 'step-' . $st['status'];
                        ?>
                        <div class="trz-step <?= $stepClass ?>">
                            <div class="trz-step-indicator">
                                <span class="trz-step-icon"><?= $st['icon'] ?></span>
                                <span class="trz-step-num"><?= $st['step'] ?></span>
                            </div>
                            <div class="trz-step-body">
                                <div class="trz-step-name"><?= h($st['name']) ?></div>
                                <div class="trz-step-date"><?= h($st['date']) ?></div>
                                <div class="trz-step-desc"><?= h($st['desc']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 360° Detailed Cards Grid -->
        <div class="trz-grid-dossier">
            <!-- Card 1: Comercial & Especificación de Producto -->
            <div class="trz-card">
                <div class="trz-card-head">
                    <span class="trz-card-icon">🛒</span>
                    <h3>1. Información Comercial & Producto</h3>
                </div>
                <div class="trz-card-body">
                    <div class="trz-detail-item">
                        <span class="detail-label">Cliente:</span>
                        <span class="detail-value"><strong><?= h($custName) ?></strong> (<?= h($custRut) ?>)</span>
                    </div>
                    <div class="trz-detail-item">
                        <span class="detail-label">N° Nota de Venta:</span>
                        <span class="detail-value"><code><?= h($nv) ?></code></span>
                    </div>
                    <div class="trz-detail-item">
                        <span class="detail-label">N° Orden Compra (OC):</span>
                        <span class="detail-value"><strong><?= h($oc !== '' ? $oc : 'Sin registrar') ?></strong></span>
                    </div>
                    <div class="trz-detail-item">
                        <span class="detail-label">Fecha de Ingreso:</span>
                        <span class="detail-value"><?= (int)$order['req_crtdat'] > 0 ? date('d/m/Y H:i', (int)$order['req_crtdat']) : '-' ?></span>
                    </div>
                    <div class="trz-detail-item">
                        <span class="detail-label">Fecha Prometida:</span>
                        <span class="detail-value"><strong><?= h($order['req_despacho_desc'] ?: '-') ?></strong></span>
                    </div>
                    <div class="trz-detail-item">
                        <span class="detail-label">Cantidad Requerida:</span>
                        <span class="detail-value" style="font-size:16px; font-weight:800; color:#00a9a6;">
                            <?= number_format($totalAmount, 0, ',', '.') ?> unidades
                        </span>
                    </div>

                    <hr class="trz-card-divider">

                    <!-- Product Items List -->
                    <h4 class="trz-subheading">Ítems / Medidas de Bolsa</h4>
                    <?php foreach ($items as $idx => $it): ?>
                        <div class="trz-item-row">
                            <div class="item-title"><strong><?= h($it['item_title'] ?: 'Bolsa') ?></strong> (SKU: <?= h($it['item_number_prod'] ?: 'N/A') ?>)</div>
                            <div class="item-specs">
                                <span>Medidas: <strong><?= (float)$it['fab_med_width'] ?> x <?= (float)$it['fab_med_height'] ?> + <?= (float)$it['fab_med_fuelle'] ?> cm</strong></span>
                                <span>•</span>
                                <span>Tela: <strong><?= h($it['fab_type'] ?: 'TNT') ?></strong></span>
                                <span>•</span>
                                <span>Impresión: <strong><?= h($it['fab_printtype'] ?: 'SIN') ?></strong></span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($imgHash !== '' && $imgHash !== 'dummy'): ?>
                        <?php $imgUrl = '/production/design-image?folder=order&hash=' . rawurlencode($imgHash); ?>
                        <div class="trz-design-preview-box">
                            <div class="preview-thumb" onclick="openTrzDesignModal('<?= h($imgUrl) ?>', '<?= h(addslashes($custName)) ?>', '<?= h($nv) ?>')">
                                <img src="<?= h($imgUrl) ?>" alt="Diseño">
                                <span class="thumb-hint">🔍 Ampliar / Rotar</span>
                            </div>
                            <div class="preview-info">
                                <strong>Diseño de Impresión</strong>
                                <p>Haga click para inspeccionar medidas y diseño con herramientas de rotación de 360°.</p>
                                <button type="button" class="trz-btn trz-btn-outline trz-btn-sm" onclick="openTrzDesignModal('<?= h($imgUrl) ?>', '<?= h(addslashes($custName)) ?>', '<?= h($nv) ?>')">
                                    Abrir en Rotador
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 2: Clisés & Pre-Prensa Técnica -->
            <div class="trz-card">
                <div class="trz-card-head">
                    <span class="trz-card-icon">🎨</span>
                    <h3>2. Clisés & Pre-Prensa Técnica</h3>
                </div>
                <div class="trz-card-body">
                    <?php if (!$analysis['needs_cliche']): ?>
                        <div class="trz-notice-box notice-neutral">
                            ℹ️ Este pedido <strong>no requiere clisés</strong> (Sin Impresión).
                        </div>
                    <?php else: ?>
                        <div class="trz-detail-item">
                            <span class="detail-label">Tipo de Clisé:</span>
                            <span class="detail-value">
                                <?php if ((int)$order['req_cliche_peli_solic_repeat_act'] === 1): ?>
                                    <strong class="text-success">Repetición existente en bodega</strong>
                                <?php else: ?>
                                    <strong class="text-warning">Clisé Nuevo / Compra a Proveedor</strong>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="trz-detail-item">
                            <span class="detail-label">Código en Bodega:</span>
                            <span class="detail-value"><?= h($order['req_infoaddprd_cliche_codigo'] ?: 'Pendiente asignación') ?></span>
                        </div>

                        <div class="trz-detail-item">
                            <span class="detail-label">Ubicación Rack:</span>
                            <span class="detail-value"><?= h($order['req_infoaddprd_cliche_ubicacion'] ?: 'Sin ubicar') ?></span>
                        </div>

                        <div class="trz-detail-item">
                            <span class="detail-label">Proveedor Clisé:</span>
                            <span class="detail-value"><?= h($order['cliche_supplier_name'] ?: ($order['req_custnameprov'] ?: 'No asignado')) ?></span>
                        </div>

                        <div class="trz-detail-item">
                            <span class="detail-label">Seudónimo / Matriz:</span>
                            <span class="detail-value"><?= h($order['req_solic_supp_seudonimo'] ?: 'Sin seudónimo') ?></span>
                        </div>

                        <div class="trz-detail-item">
                            <span class="detail-label">Fecha Solicitud Compra:</span>
                            <span class="detail-value">
                                <?php if ((int)$order['req_cliche_peli_solic_dat'] > 0): ?>
                                    <strong class="text-success"><?= date('d/m/Y H:i', (int)$order['req_cliche_peli_solic_dat']) ?></strong>
                                <?php else: ?>
                                    <strong class="text-danger">Pendiente solicitud a proveedor</strong>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="trz-detail-item">
                            <span class="detail-label">Fecha Recepción en Planta:</span>
                            <span class="detail-value">
                                <?php if ((int)$order['req_cliche_peli_recep_dat'] > 0): ?>
                                    <strong class="text-success"><?= date('d/m/Y H:i', (int)$order['req_cliche_peli_recep_dat']) ?></strong>
                                <?php else: ?>
                                    <strong class="text-warning">⏳ En espera de entrega por proveedor</strong>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="trz-detail-item">
                            <span class="detail-label">Espesor Polímero:</span>
                            <span class="detail-value"><?= h($order['req_solic_devprints_poltype'] ?: 'Estándar') ?></span>
                        </div>
                    <?php endif; ?>

                    <hr class="trz-card-divider">

                    <h4 class="trz-subheading">Validación Pre-Prensa</h4>
                    <div class="trz-detail-item">
                        <span class="detail-label">Checklist Técnico:</span>
                        <span class="detail-value">
                            <?php if ((int)$order['req_prod_checklist_term'] === 1): ?>
                                <span class="trz-badge badge-success">✓ Aprobado y Validado</span>
                            <?php else: ?>
                                <span class="trz-badge badge-warning">⏳ Pendiente de Aprobación</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="trz-detail-item">
                        <span class="detail-label">Diseñador Asignado:</span>
                        <span class="detail-value">
                            <?= h(trim(($order['designer_firstname'] ?? '') . ' ' . ($order['designer_lastname'] ?? '')) ?: 'Sin diseñador asignado') ?>
                        </span>
                    </div>

                    <?php if (!empty($attachments)): ?>
                        <div class="trz-attachments-box">
                            <strong>Archivos Adjuntos Pre-Prensa (<?= count($attachments) ?>):</strong>
                            <div class="trz-attach-list">
                                <?php foreach ($attachments as $att): ?>
                                    <div class="trz-attach-pill">
                                        <span>📄 <?= h($att['filename']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 3: Planificación & Agendas de Máquinas -->
            <div class="trz-card">
                <div class="trz-card-head">
                    <span class="trz-card-icon">📅</span>
                    <h3>3. Planificación & Agendas de Máquinas</h3>
                </div>
                <div class="trz-card-body">
                    <div class="trz-detail-item">
                        <span class="detail-label">N° Orden de Trabajo (OT):</span>
                        <span class="detail-value">
                            <?php if ($ot !== ''): ?>
                                <strong class="text-primary" style="font-size:15px;">OT #<?= h($ot) ?></strong>
                            <?php else: ?>
                                <strong class="text-danger">Pendiente de Generación de OT</strong>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="trz-detail-item">
                        <span class="detail-label">Estado de la OT:</span>
                        <span class="detail-value">
                            <?php
                                $otStatus = (int)$order['prd_status'];
                                if ($otStatus === 1) echo '<span class="trz-badge badge-warning">En Preparación</span>';
                                elseif ($otStatus === 2) echo '<span class="trz-badge badge-primary">Activa / Programada</span>';
                                elseif ($otStatus === 3) echo '<span class="trz-badge badge-success">Terminada</span>';
                                else echo '<span class="trz-text-muted">Sin OT</span>';
                            ?>
                        </span>
                    </div>

                    <hr class="trz-card-divider">

                    <h4 class="trz-subheading">Turnos y Máquinas Agendadas (<?= count($agendas) ?>)</h4>
                    <?php if (empty($agendas)): ?>
                        <div class="trz-notice-box notice-warning">
                            ⚠️ Este pedido <strong>aún no tiene turnos programados en máquinas</strong>.
                        </div>
                    <?php else: ?>
                        <div class="trz-agenda-list">
                            <?php foreach ($agendas as $ag): ?>
                                <div class="trz-agenda-card">
                                    <div class="agenda-top">
                                        <span class="agenda-machine"><?= h($ag['equipo_name']) ?></span>
                                        <span class="agenda-status status-<?= (int)$ag['ag_status'] ?>">
                                            <?= (int)$ag['ag_status'] === 3 ? 'Terminado' : ((int)$ag['ag_status'] === 2 ? 'En curso' : 'Programado') ?>
                                        </span>
                                    </div>
                                    <div class="agenda-meta">
                                        <span>Fecha: <strong><?= h($ag['ag_date']) ?></strong></span>
                                        <span>•</span>
                                        <span>Programado: <strong><?= number_format((float)$ag['ag_amount'], 0, ',', '.') ?> un.</strong></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 4: Registro de Fabricación en Planta -->
            <div class="trz-card">
                <div class="trz-card-head">
                    <span class="trz-card-icon">⚙️</span>
                    <h3>4. Fabricación en Planta & Mermas</h3>
                </div>
                <div class="trz-card-body">
                    <!-- Progress Bar (Basado estrictamente en lo que se EMBALÓ como bolsas terminadas) -->
                    <div class="trz-prog-section">
                        <div class="prog-label-row">
                            <span>Avance de Fabricación (Terminado en Embalaje)</span>
                            <strong style="color:#00a9a6; font-size:16px;"><?= number_format($prodPct, 1) ?>%</strong>
                        </div>
                        <div class="trz-bar-track bar-lg">
                            <div class="trz-bar-fill fill-cyan" style="width: <?= min(100, $prodPct) ?>%;"></div>
                        </div>
                        <div class="prog-stats-row">
                            <span>Terminado (Embalaje): <strong style="color:#00a9a6; font-size:14px;"><?= number_format($produced, 0, ',', '.') ?> un.</strong></span>
                            <span>Requerido Pedido: <strong><?= number_format($totalAmount, 0, ',', '.') ?> un.</strong></span>
                        </div>
                    </div>

                    <!-- Desglose por Proceso (Evita sumar 2000 x 3 = 6000 cuando pasa por 3 etapas) -->
                    <div class="trz-process-breakdown">
                        <span class="breakdown-title">📊 Producción Registrada por Etapa:</span>
                        <div class="breakdown-grid">
                            <div class="process-pill pill-flexo">
                                <span class="proc-icon">🎨</span>
                                <div class="proc-info">
                                    <span class="proc-label">Flexografía</span>
                                    <strong class="proc-val"><?= number_format((float)($order['produced_flexo'] ?? 0), 0, ',', '.') ?> un.</strong>
                                </div>
                            </div>
                            <div class="process-pill pill-sellado">
                                <span class="proc-icon">✂️</span>
                                <div class="proc-info">
                                    <span class="proc-label">Corte y Sellado</span>
                                    <strong class="proc-val"><?= number_format((float)($order['produced_sellado'] ?? 0), 0, ',', '.') ?> un.</strong>
                                </div>
                            </div>
                            <div class="process-pill pill-embalaje">
                                <span class="proc-icon">📦</span>
                                <div class="proc-info">
                                    <span class="proc-label">Embalaje (Final)</span>
                                    <strong class="proc-val text-success"><?= number_format((float)($order['produced_embalaje'] ?? 0), 0, ',', '.') ?> un.</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="trz-metrics-mini-grid">
                        <div class="metric-mini">
                            <div class="metric-val"><?= number_format($order['produced_meters'], 0, ',', '.') ?> m</div>
                            <div class="metric-lbl">Metros Lineales</div>
                        </div>
                        <div class="metric-mini">
                            <div class="metric-val"><?= number_format($order['consumed_kg'], 1, ',', '.') ?> kg</div>
                            <div class="metric-lbl">Kilos Procesados</div>
                        </div>
                        <div class="metric-mini">
                            <div class="metric-val"><?= count($events) ?></div>
                            <div class="metric-lbl">Eventos / Turnos</div>
                        </div>
                    </div>

                    <hr class="trz-card-divider">

                    <div class="trz-head-with-hint">
                        <h4 class="trz-subheading" style="margin:0;">Historial de Eventos de Producción</h4>
                        <span class="trz-table-hint">💡 Clic en cualquier fila para ver operador y tiempos</span>
                    </div>

                    <?php if (empty($events)): ?>
                        <p class="trz-text-muted" style="font-size:12px; margin-top:8px;">No hay eventos de producción registrados aún en esta OT.</p>
                    <?php else: ?>
                        <div class="trz-events-table-wrap">
                            <table class="trz-events-table trz-table-clickable">
                                <thead>
                                    <tr>
                                        <th>Fecha / Hora</th>
                                        <th>Máquina</th>
                                        <th>Operador Responsable</th>
                                        <th>Tiempo Empleado</th>
                                        <th style="text-align:right;">Cantidad</th>
                                        <th style="text-align:right;">Kilos</th>
                                        <th style="text-align:center;">Detalle</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $idx => $ev): ?>
                                        <tr onclick="openTrzProdModal(<?= $idx ?>)" style="cursor:pointer;" title="Click para abrir detalle de tiempos y operador">
                                            <td><?= h($ev['start_fmt']) ?></td>
                                            <td>
                                                <strong><?= h($ev['equipo_name'] ?: 'Máquina') ?></strong>
                                                <span class="trz-ev-tag" style="margin-left:4px;"><?= h($ev['evt_type']) ?></span>
                                            </td>
                                            <td>
                                                <span class="trz-worker-name"><?= h($ev['operator_name']) ?></span>
                                                <?php if (!empty($ev['helper_name'])): ?>
                                                    <span class="trz-helper-sub">+ <?= h($ev['helper_name']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="trz-duration-badge <?= ($ev['duration_sec'] ?? 0) > 0 ? 'dur-done' : 'dur-prog' ?>">
                                                    ⏱️ <?= h($ev['duration_text']) ?>
                                                </span>
                                            </td>
                                            <td style="text-align:right; font-weight:700;">
                                                <?= number_format((float)$ev['evt_amount'], 0, ',', '.') ?> un.
                                            </td>
                                            <td style="text-align:right;">
                                                <?= number_format((float)$ev['prod_bobina_kg'], 1, ',', '.') ?> kg
                                            </td>
                                            <td style="text-align:center;">
                                                <button type="button" class="trz-btn trz-btn-outline trz-btn-xs" onclick="event.stopPropagation(); openTrzProdModal(<?= $idx ?>)">
                                                    👁️ Ver
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 5: Despachos y Facturación -->
            <div class="trz-card" style="grid-column: 1 / -1;">
                <div class="trz-card-head">
                    <span class="trz-card-icon">🚚</span>
                    <h3>5. Despachos, Guías de Entrega y Facturación</h3>
                </div>
                <div class="trz-card-body">
                    <div class="trz-delivery-summary-grid">
                        <div class="summary-box">
                            <div class="sum-title">Total Pedido</div>
                            <div class="sum-val"><?= number_format($totalAmount, 0, ',', '.') ?> un.</div>
                        </div>
                        <div class="summary-box">
                            <div class="sum-title">Total Despachado</div>
                            <div class="sum-val text-success"><?= number_format($shipped, 0, ',', '.') ?> un.</div>
                        </div>
                        <div class="summary-box">
                            <div class="sum-title">Saldo Pendiente de Entrega</div>
                            <div class="sum-val <?= $balance > 0 ? 'text-amber' : 'text-success' ?>">
                                <?= number_format($balance, 0, ',', '.') ?> un.
                            </div>
                        </div>
                        <div class="summary-box">
                            <div class="sum-title">% Entrega Cumplida</div>
                            <div class="sum-val text-primary"><?= number_format($analysis['ship_pct'], 1) ?>%</div>
                        </div>
                    </div>

                    <div class="trz-head-with-hint" style="margin-top:18px;">
                        <h4 class="trz-subheading" style="margin:0;">Guías de Despacho Emitidas</h4>
                        <span class="trz-table-hint">💡 Clic en el N° de Guía para ver todos los productos despachados</span>
                    </div>

                    <?php if (empty($deliveries)): ?>
                        <div class="trz-notice-box notice-warning" style="margin-top:8px;">
                            ⚠️ Aún <strong>no se han emitido Guías de Despacho</strong> para este pedido.
                        </div>
                    <?php else: ?>
                        <div class="trz-events-table-wrap">
                            <table class="trz-events-table">
                                <thead>
                                    <tr>
                                        <th>N° Guía de Despacho</th>
                                        <th>Fecha Despacho</th>
                                        <th>OC Despacho</th>
                                        <th>Ítems en Guía</th>
                                        <th>Estado Guía</th>
                                        <th style="text-align:right;">Cantidad Despachada</th>
                                        <th style="text-align:center;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deliveries as $idx => $dlv): ?>
                                        <tr onclick="openTrzDeliveryModal(<?= $idx ?>)" style="cursor:pointer;" title="Click para ver qué más se despachó en esta guía">
                                            <td>
                                                <button type="button" class="trz-badge-dlv-btn" onclick="event.stopPropagation(); openTrzDeliveryModal(<?= $idx ?>)">
                                                    🚚 <?= h($dlv['dlv_num']) ?> 🔍
                                                </button>
                                            </td>
                                            <td><?= h($dlv['date_fmt']) ?></td>
                                            <td><?= h($dlv['dlv_oc_number'] ?: $oc) ?></td>
                                            <td>
                                                <span class="trz-badge badge-info"><?= count($dlv['items']) ?> producto(s)</span>
                                            </td>
                                            <td>
                                                <span class="trz-badge badge-success">Entregado</span>
                                            </td>
                                            <td style="text-align:right; font-weight:800; color:#00a9a6;">
                                                <?= number_format((float)$dlv['total_shipped'], 0, ',', '.') ?> un.
                                            </td>
                                            <td style="text-align:center;">
                                                <button type="button" class="trz-btn trz-btn-primary trz-btn-xs" onclick="event.stopPropagation(); openTrzDeliveryModal(<?= $idx ?>)">
                                                    Ver Despacho ↗
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals for Designs, Deliveries and Production -->
    <?php renderTrzRotatorModalHtml(); ?>
    <?php renderTrzDeliveryModalHtml(); ?>
    <?php renderTrzProdModalHtml(); ?>

    <!-- Client-side data stores for instant modal opening -->
    <script>
        var trzDeliveriesData = <?= json_encode($deliveries, JSON_UNESCAPED_UNICODE) ?>;
        var trzEventsData = <?= json_encode($events, JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <!-- Traceability Styles -->
    <?php renderTraceabilityStyles(); ?>
    <?php
    $body = ob_get_clean();
    render("Trazabilidad {$nv} | ERP Unibag", $body);
}

/**
 * Helper to build pagination URL while keeping existing query parameters.
 */
function buildTrzUrl(array $filters, int $page): string
{
    $params = $filters;
    $params['page'] = $page;
    return '/trazabilidad?' . http_build_query($params);
}

/**
 * Render the rich 360° rotator modal for inspecting designs with rotation and scaling.
 */
function renderTrzRotatorModalHtml(): void
{
    ?>
    <div id="trzDesignModal" class="trz-modal-backdrop" onclick="closeTrzDesignModal(event)">
        <div class="trz-modal-box" onclick="event.stopPropagation()">
            <div class="trz-modal-top">
                <div class="trz-modal-title-wrap">
                    <span class="trz-modal-icon">🎨</span>
                    <div>
                        <h4 id="trzModalTitle" class="trz-modal-title">Diseño de Impresión</h4>
                        <span id="trzModalSub" class="trz-modal-sub">Inspección de Arte y Medidas</span>
                    </div>
                </div>
                <div class="trz-modal-tools">
                    <button type="button" class="trz-tool-btn" onclick="rotateTrzModal(-90)" title="Rotar 90° a la izquierda (tecla L)">
                        ↺ -90°
                    </button>
                    <button type="button" class="trz-tool-btn" onclick="rotateTrzModal(90)" title="Rotar 90° a la derecha (tecla R)">
                        ↻ +90°
                    </button>
                    <button type="button" class="trz-tool-btn" onclick="resetTrzModalRotation()" title="Restablecer rotación (tecla 0)">
                        0° Reset
                    </button>
                    <span id="trzRotAngleBadge" class="trz-angle-badge">0°</span>
                    <button type="button" class="trz-modal-close" onclick="closeTrzDesignModal(event)" title="Cerrar (Escape)">✕</button>
                </div>
            </div>
            <div class="trz-modal-viewport" id="trzModalViewport">
                <img id="trzModalImg" src="" alt="Diseño en alta resolución">
            </div>
            <div class="trz-modal-footer">
                <span class="trz-modal-hint">Atajos de teclado: <strong>R</strong> rotar derecha | <strong>L</strong> rotar izquierda | <strong>0</strong> reestablecer | <strong>Esc</strong> cerrar</span>
                <a id="trzModalOpenTab" href="#" target="_blank" class="trz-btn trz-btn-outline trz-btn-sm">Abrir en pestaña nueva</a>
            </div>
        </div>
    </div>

    <script>
        var currentTrzAngle = 0;

        function openTrzDesignModal(url, title, orderNv) {
            currentTrzAngle = 0;
            var modal = document.getElementById('trzDesignModal');
            var img = document.getElementById('trzModalImg');
            var titleEl = document.getElementById('trzModalTitle');
            var subEl = document.getElementById('trzModalSub');
            var openTab = document.getElementById('trzModalOpenTab');
            var badge = document.getElementById('trzRotAngleBadge');

            if (!modal || !img) return;

            img.src = url;
            if (openTab) openTab.href = url;
            if (titleEl) titleEl.textContent = title ? title : 'Diseño de Impresión';
            if (subEl) subEl.textContent = orderNv ? 'Nota de Venta: ' + orderNv : 'Inspección de Arte';
            if (badge) badge.textContent = '0°';

            img.style.transform = 'rotate(0deg) scale(1)';
            modal.classList.add('open');
        }

        function closeTrzDesignModal(e) {
            var modal = document.getElementById('trzDesignModal');
            if (modal) modal.classList.remove('open');
        }

        function rotateTrzModal(delta) {
            currentTrzAngle = (currentTrzAngle + delta) % 360;
            if (currentTrzAngle < 0) currentTrzAngle += 360;
            applyTrzTransform();
        }

        function resetTrzModalRotation() {
            currentTrzAngle = 0;
            applyTrzTransform();
        }

        function applyTrzTransform() {
            var img = document.getElementById('trzModalImg');
            var badge = document.getElementById('trzRotAngleBadge');
            if (!img) return;

            var isSideways = (currentTrzAngle === 90 || currentTrzAngle === 270);
            var scale = isSideways ? 0.78 : 1.0;
            img.style.transform = 'rotate(' + currentTrzAngle + 'deg) scale(' + scale + ')';

            if (badge) {
                badge.textContent = currentTrzAngle + '°';
                badge.style.color = currentTrzAngle !== 0 ? '#00a9a6' : '#94a3b8';
            }
        }

        document.addEventListener('keydown', function(e) {
            var modal = document.getElementById('trzDesignModal');
            if (!modal || !modal.classList.contains('open')) return;

            if (e.key === 'Escape') {
                closeTrzDesignModal();
            } else if (e.key === 'r' || e.key === 'R') {
                rotateTrzModal(90);
            } else if (e.key === 'l' || e.key === 'L') {
                rotateTrzModal(-90);
            } else if (e.key === '0') {
                resetTrzModalRotation();
            }
        });
    </script>
    <?php
}

/**
 * Render the floating modal for Delivery Guides (Despachos) with detailed items.
 */
function renderTrzDeliveryModalHtml(): void
{
    ?>
    <div id="trzDeliveryModal" class="trz-modal-backdrop" onclick="closeTrzDeliveryModal(event)">
        <div class="trz-modal-box trz-modal-wide" onclick="event.stopPropagation()">
            <div class="trz-modal-top">
                <div class="trz-modal-title-wrap">
                    <span class="trz-modal-icon">🚚</span>
                    <div>
                        <h4 id="trzDlvModalTitle" class="trz-modal-title">Guía de Despacho N° GV000000</h4>
                        <span id="trzDlvModalSub" class="trz-modal-sub">Detalle de mercadería despachada y datos de entrega</span>
                    </div>
                </div>
                <div class="trz-modal-tools">
                    <span id="trzDlvModalBadge" class="trz-badge badge-success">Entregado</span>
                    <button type="button" class="trz-modal-close" onclick="closeTrzDeliveryModal(event)" title="Cerrar (Escape)">✕</button>
                </div>
            </div>

            <div class="trz-modal-body-content">
                <!-- Header Meta Grid -->
                <div class="trz-modal-meta-grid">
                    <div class="meta-card">
                        <span class="meta-label">Cliente / Razón Social:</span>
                        <strong id="trzDlvCustName" class="meta-val">-</strong>
                        <span id="trzDlvCustRut" class="meta-sub">-</span>
                    </div>
                    <div class="meta-card">
                        <span class="meta-label">Fecha de Despacho:</span>
                        <strong id="trzDlvDate" class="meta-val">-</strong>
                        <span id="trzDlvOc" class="meta-sub">OC: -</span>
                    </div>
                    <div class="meta-card">
                        <span class="meta-label">Dirección de Destino:</span>
                        <strong id="trzDlvAddress" class="meta-val">-</strong>
                    </div>
                    <div class="meta-card">
                        <span class="meta-label">Total Unidades Despachadas:</span>
                        <strong id="trzDlvTotalShipped" class="meta-val text-primary" style="font-size:18px;">-</strong>
                    </div>
                </div>

                <!-- Delivery Notes / Annotation Box -->
                <div id="trzDlvAnnotationBox" class="trz-modal-annotation-box">
                    <span class="annot-label">📦 Anotación de Entrega & Bultos:</span>
                    <p id="trzDlvAnnotationText" class="annot-text">-</p>
                </div>

                <!-- Dispatched Items Table -->
                <h5 class="trz-modal-section-title">Ítems Despachados en esta Guía</h5>
                <div class="trz-table-responsive" style="max-height: 280px; overflow-y: auto;">
                    <table class="trz-table trz-modal-table">
                        <thead>
                            <tr>
                                <th style="width: 120px;">Código SKU</th>
                                <th>Descripción del Producto / Medidas</th>
                                <th style="width: 140px;">Nota de Venta / OC</th>
                                <th style="width: 130px; text-align: right;">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody id="trzDlvItemsTableBody">
                            <tr>
                                <td colspan="4" class="trz-text-muted" style="text-align: center; padding: 20px;">
                                    Cargando información del despacho...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="trz-modal-footer">
                <span class="trz-modal-hint">Presione <strong>Escape</strong> para cerrar esta ventana flotante</span>
                <button type="button" class="trz-btn trz-btn-outline trz-btn-sm" onclick="closeTrzDeliveryModal(event)">Cerrar</button>
            </div>
        </div>
    </div>

    <script>
        function openTrzDeliveryModal(ref) {
            var modal = document.getElementById('trzDeliveryModal');
            if (!modal) return;

            var dlv = null;
            if (typeof ref === 'object' && ref !== null) {
                dlv = ref;
            } else if (typeof ref === 'number' && typeof trzDeliveriesData !== 'undefined' && trzDeliveriesData[ref]) {
                dlv = trzDeliveriesData[ref];
            }

            if (dlv) {
                populateTrzDeliveryModal(dlv);
                modal.classList.add('open');
            } else if (typeof ref === 'number' || typeof ref === 'string') {
                fetch('/trazabilidad/api/guia?id=' + encodeURIComponent(ref))
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data && data.success && data.delivery) {
                            populateTrzDeliveryModal(data.delivery);
                            modal.classList.add('open');
                        } else {
                            alert('No se pudo cargar la información de la guía de despacho.');
                        }
                    })
                    .catch(function(err) {
                        console.error('Error al cargar guía:', err);
                        alert('Error al consultar los datos del despacho.');
                    });
            }
        }

        function populateTrzDeliveryModal(dlv) {
            document.getElementById('trzDlvModalTitle').textContent = 'Guía de Despacho N° ' + (dlv.dlv_num || 'Sin número');
            document.getElementById('trzDlvModalSub').textContent = 'Doc. Folio #' + (dlv.dlv_docnum || dlv.id) + ' | Emisión Unibag';
            document.getElementById('trzDlvCustName').textContent = dlv.cust_name || 'Cliente registrado';
            document.getElementById('trzDlvCustRut').textContent = dlv.cust_rut ? 'RUT: ' + dlv.cust_rut : '';
            document.getElementById('trzDlvDate').textContent = dlv.date_fmt || '-';
            document.getElementById('trzDlvOc').textContent = dlv.dlv_oc_number ? 'OC: ' + dlv.dlv_oc_number : '(Sin OC)';
            document.getElementById('trzDlvAddress').textContent = dlv.dlv_direccion_destino || 'Entrega en planta / Según acuerdo';
            
            var totalShipped = parseFloat(dlv.total_shipped || 0);
            document.getElementById('trzDlvTotalShipped').textContent = totalShipped.toLocaleString('es-CL') + ' un.';

            var annotEl = document.getElementById('trzDlvAnnotationText');
            var annotBox = document.getElementById('trzDlvAnnotationBox');
            if (dlv.dlv_annotation && dlv.dlv_annotation.trim() !== '') {
                annotEl.textContent = dlv.dlv_annotation;
                annotBox.style.display = 'block';
            } else {
                annotBox.style.display = 'none';
            }

            var tbody = document.getElementById('trzDlvItemsTableBody');
            tbody.innerHTML = '';
            var items = dlv.items || [];
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="trz-text-muted" style="text-align: center; padding: 16px;">No hay ítems detallados para esta guía.</td></tr>';
            } else {
                items.forEach(function(it) {
                    var tr = document.createElement('tr');
                    var sku = it.item_number_prod || 'PROD';
                    var title = it.item_title || it.item_desc || 'Bolsa';
                    var nv = it.order_nv || '';
                    var oc = it.order_oc || '';
                    var orderRef = nv ? ('NV: ' + nv + (oc ? ' | OC: ' + oc : '')) : '-';
                    var amt = parseFloat(it.item_amount_shipped || 0);

                    tr.innerHTML = '<td><code>' + escapeTrzHtml(sku) + '</code></td>' +
                        '<td><strong>' + escapeTrzHtml(title) + '</strong></td>' +
                        '<td><span class="trz-text-muted" style="font-size:12px;">' + escapeTrzHtml(orderRef) + '</span></td>' +
                        '<td style="text-align: right; font-weight: 800; color: #00a9a6;">' + amt.toLocaleString('es-CL') + ' un.</td>';
                    tbody.appendChild(tr);
                });
            }
        }

        function closeTrzDeliveryModal(e) {
            var modal = document.getElementById('trzDeliveryModal');
            if (modal) modal.classList.remove('open');
        }
    </script>
    <?php
}

/**
 * Render the floating modal for Production Events and Machine execution details.
 */
function renderTrzProdModalHtml(): void
{
    ?>
    <div id="trzProdModal" class="trz-modal-backdrop" onclick="closeTrzProdModal(event)">
        <div class="trz-modal-box trz-modal-wide" onclick="event.stopPropagation()">
            <div class="trz-modal-top">
                <div class="trz-modal-title-wrap">
                    <span class="trz-modal-icon">⚙️</span>
                    <div>
                        <h4 id="trzProdModalTitle" class="trz-modal-title">Detalle de Fabricación en Máquina</h4>
                        <span id="trzProdModalSub" class="trz-modal-sub">Información técnica de tirada, operador responsable y tiempos</span>
                    </div>
                </div>
                <div class="trz-modal-tools">
                    <span id="trzProdModalBadge" class="trz-badge badge-primary">Terminado</span>
                    <button type="button" class="trz-modal-close" onclick="closeTrzProdModal(event)" title="Cerrar (Escape)">✕</button>
                </div>
            </div>

            <div class="trz-modal-body-content">
                <!-- Operator & Duration Meta Grid -->
                <div class="trz-modal-meta-grid">
                    <div class="meta-card">
                        <span class="meta-label">👷 Operador Responsable:</span>
                        <strong id="trzProdOperator" class="meta-val">-</strong>
                        <span id="trzProdHelper" class="meta-sub" style="color: #00a9a6;">-</span>
                    </div>
                    <div class="meta-card">
                        <span class="meta-label">⏱️ Tiempo Empleado (Duración):</span>
                        <strong id="trzProdDuration" class="meta-val text-primary" style="font-size: 19px;">-</strong>
                        <span id="trzProdTimeRange" class="meta-sub">-</span>
                    </div>
                    <div class="meta-card">
                        <span class="meta-label">Máquina / Equipo:</span>
                        <strong id="trzProdMachine" class="meta-val">-</strong>
                        <span id="trzProdMaqCode" class="meta-sub">Código: -</span>
                    </div>
                    <div class="meta-card">
                        <span class="meta-label">Bolsas Fabricadas:</span>
                        <strong id="trzProdAmount" class="meta-val text-success" style="font-size: 19px;">-</strong>
                        <span id="trzProdMetersKg" class="meta-sub">-</span>
                    </div>
                </div>

                <!-- Technical Details Grid -->
                <h5 class="trz-modal-section-title">Parámetros de Turno & Ejecución</h5>
                <div class="trz-modal-details-grid">
                    <div class="detail-row">
                        <span class="detail-name">Tipo de Proceso / Evento:</span>
                        <strong id="trzProdType" class="detail-val">-</strong>
                    </div>
                    <div class="detail-row">
                        <span class="detail-name">Fecha y Hora de Inicio:</span>
                        <span id="trzProdStart" class="detail-val">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-name">Fecha y Hora de Término:</span>
                        <span id="trzProdEnd" class="detail-val">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-name">Metros Lineales Fabricados:</span>
                        <strong id="trzProdMeters" class="detail-val">-</strong>
                    </div>
                    <div class="detail-row">
                        <span class="detail-name">Kilos Bobina Consumidos:</span>
                        <strong id="trzProdKg" class="detail-val">-</strong>
                    </div>
                    <div class="detail-row">
                        <span class="detail-name">Cantidad Planificada en Turno:</span>
                        <span id="trzProdPlanned" class="detail-val">-</span>
                    </div>
                </div>

                <!-- Observations / Comments Box -->
                <div id="trzProdCommentsBox" class="trz-modal-annotation-box" style="margin-top: 14px;">
                    <span class="annot-label">📝 Observaciones & Comentarios del Operador:</span>
                    <p id="trzProdCommentsText" class="annot-text">-</p>
                </div>
            </div>

            <div class="trz-modal-footer">
                <span class="trz-modal-hint">Presione <strong>Escape</strong> para cerrar esta ventana flotante</span>
                <button type="button" class="trz-btn trz-btn-outline trz-btn-sm" onclick="closeTrzProdModal(event)">Cerrar</button>
            </div>
        </div>
    </div>

    <script>
        function openTrzProdModal(ref) {
            var modal = document.getElementById('trzProdModal');
            if (!modal) return;

            var ev = null;
            if (typeof ref === 'object' && ref !== null) {
                ev = ref;
            } else if (typeof ref === 'number' && typeof trzEventsData !== 'undefined' && trzEventsData[ref]) {
                ev = trzEventsData[ref];
            }

            if (ev) {
                populateTrzProdModal(ev);
                modal.classList.add('open');
            } else if (typeof ref === 'number' || typeof ref === 'string') {
                fetch('/trazabilidad/api/evento?id=' + encodeURIComponent(ref))
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data && data.success && data.event) {
                            populateTrzProdModal(data.event);
                            modal.classList.add('open');
                        } else {
                            alert('No se pudo cargar la información del evento de producción.');
                        }
                    })
                    .catch(function(err) {
                        console.error('Error al cargar evento:', err);
                        alert('Error al consultar los datos de producción.');
                    });
            }
        }

        function populateTrzProdModal(ev) {
            var machineName = ev.equipo_name || 'Máquina';
            document.getElementById('trzProdModalTitle').textContent = 'Detalle de Fabricación: ' + machineName;
            document.getElementById('trzProdModalSub').textContent = 'Evento #' + (ev.id || '-') + (ev.ag_date ? ' | Fecha: ' + ev.ag_date : '');
            
            document.getElementById('trzProdOperator').textContent = ev.operator_name || 'No asignado';
            var helperEl = document.getElementById('trzProdHelper');
            if (ev.helper_name && ev.helper_name.trim() !== '') {
                helperEl.textContent = 'Ayudante: ' + ev.helper_name;
                helperEl.style.display = 'block';
            } else {
                helperEl.style.display = 'none';
            }

            document.getElementById('trzProdDuration').textContent = ev.duration_text || 'En curso';
            document.getElementById('trzProdTimeRange').textContent = (ev.start_fmt || '-') + ' hasta ' + (ev.end_fmt || '-');

            document.getElementById('trzProdMachine').textContent = machineName;
            document.getElementById('trzProdMaqCode').textContent = ev.equipo_code ? 'Código: ' + ev.equipo_code : (ev.equipo_desc ? ev.equipo_desc : '');

            var amt = parseFloat(ev.evt_amount || 0);
            var meters = parseFloat(ev.evt_amount_metros_lineales || 0);
            var kg = parseFloat(ev.prod_bobina_kg || 0);

            document.getElementById('trzProdAmount').textContent = amt.toLocaleString('es-CL') + ' un.';
            document.getElementById('trzProdMetersKg').textContent = meters.toLocaleString('es-CL') + ' m | ' + kg.toLocaleString('es-CL', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + ' kg';

            document.getElementById('trzProdType').textContent = (ev.evt_type || 'prod').toUpperCase();
            document.getElementById('trzProdStart').textContent = ev.start_fmt || '-';
            document.getElementById('trzProdEnd').textContent = ev.end_fmt || '-';
            document.getElementById('trzProdMeters').textContent = meters.toLocaleString('es-CL') + ' metros lineales';
            document.getElementById('trzProdKg').textContent = kg.toLocaleString('es-CL', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + ' kg';

            var plannedAmt = parseFloat(ev.planned_amount || 0);
            document.getElementById('trzProdPlanned').textContent = plannedAmt > 0 ? (plannedAmt.toLocaleString('es-CL') + ' un.') : 'No especificado';

            var commentsBox = document.getElementById('trzProdCommentsBox');
            var commentsEl = document.getElementById('trzProdCommentsText');
            if (ev.evt_comments && ev.evt_comments.trim() !== '') {
                commentsEl.textContent = ev.evt_comments;
                commentsBox.style.display = 'block';
            } else {
                commentsEl.textContent = 'Sin observaciones adicionales registradas por el operador.';
                commentsBox.style.display = 'block';
            }
        }

        function closeTrzProdModal(e) {
            var modal = document.getElementById('trzProdModal');
            if (modal) modal.classList.remove('open');
        }

        function escapeTrzHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Global Escape key handler for all traceability modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeTrzDeliveryModal();
                closeTrzProdModal();
                if (typeof closeTrzDesignModal === 'function') closeTrzDesignModal();
            }
        });
    </script>
    <?php
}

/**
 * CSS styles for the Traceability Module.
 */
function renderTraceabilityStyles(): void
{
    ?>
    <style>
        /* Container */
        .trz-container {
            max-width: 1340px;
            margin: 0 auto;
            padding: 20px 16px 60px;
            font-family: inherit;
        }

        /* Header */
        .trz-header {
            margin-bottom: 22px;
        }
        .trz-header-main {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .trz-tag {
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #00a9a6;
            background: rgba(0, 169, 166, 0.08);
            border: 1px solid rgba(0, 169, 166, 0.2);
            padding: 3px 8px;
            border-radius: 4px;
            margin-bottom: 6px;
        }
        .trz-title {
            font-size: 24px;
            font-weight: 900;
            color: #0f172a;
            margin: 0 0 6px;
            letter-spacing: -0.02em;
        }
        .trz-desc {
            font-size: 14px;
            color: #64748b;
            margin: 0;
            max-width: 720px;
            line-height: 1.45;
        }
        .trz-header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* Buttons */
        .trz-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            font-weight: 700;
            padding: 9px 15px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            border: 1px solid transparent;
            line-height: 1.2;
            white-space: nowrap;
        }
        .trz-btn-primary {
            background: #00a9a6;
            color: #fff;
        }
        .trz-btn-primary:hover {
            background: #008f8c;
            color: #fff;
        }
        .trz-btn-outline {
            background: #fff;
            border-color: #cbd5e1;
            color: #334155;
        }
        .trz-btn-outline:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }
        .trz-btn-submit {
            background: #0f172a;
            color: #fff;
            padding: 0 20px;
            height: 44px;
        }
        .trz-btn-submit:hover {
            background: #1e293b;
            color: #fff;
        }
        .trz-btn-toggle {
            background: #f1f5f9;
            border-color: #e2e8f0;
            color: #475467;
            height: 44px;
            padding: 0 16px;
        }
        .trz-btn-toggle:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .trz-btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        .trz-btn-detail {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #0f172a;
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }
        .trz-btn-detail:hover {
            background: #00a9a6;
            border-color: #00a9a6;
            color: #fff;
        }

        /* KPI Grid */
        .trz-kpi-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
        }
        @media (max-width: 1080px) {
            .trz-kpi-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 600px) {
            .trz-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .trz-kpi-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
            text-decoration: none;
            color: inherit;
            transition: all 0.18s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
        }
        .trz-kpi-card:hover {
            border-color: #00a9a6;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 169, 166, 0.12);
        }
        .trz-kpi-card.active {
            border-color: #00a9a6;
            background: #f0fdfc;
            box-shadow: 0 0 0 2px rgba(0, 169, 166, 0.25);
        }
        .trz-kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .trz-kpi-icon {
            font-size: 18px;
        }
        .trz-kpi-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            background: #f1f5f9;
            color: #475569;
        }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #e0e7ff; color: #3730a3; }
        .badge-primary { background: #e0f2fe; color: #0369a1; }
        .badge-amber { background: #ffedd5; color: #9a3412; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .trz-kpi-value {
            font-size: 26px;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
            margin-bottom: 4px;
        }
        .trz-kpi-label {
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
            line-height: 1.25;
        }
        .trz-kpi-sub {
            font-size: 11px;
            color: #64748b;
        }

        /* Filter Card */
        .trz-filter-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 18px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .trz-search-main {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .trz-search-box {
            position: relative;
            flex: 1;
            display: flex;
            align-items: center;
        }
        .trz-search-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            pointer-events: none;
        }
        .trz-search-input {
            width: 100%;
            height: 44px;
            padding: 0 38px 0 42px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            color: #0f172a;
            outline: none;
            transition: all 0.15s ease;
            background: #f8fafc;
        }
        .trz-search-input:focus {
            background: #fff;
            border-color: #00a9a6;
            box-shadow: 0 0 0 3px rgba(0, 169, 166, 0.15);
        }
        .trz-search-clear {
            position: absolute;
            right: 12px;
            color: #94a3b8;
            text-decoration: none;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
        }
        .trz-search-clear:hover { color: #0f172a; }

        /* Advanced Filters */
        .trz-advanced-filters {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
        }
        @media (max-width: 900px) {
            .trz-advanced-filters { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 550px) {
            .trz-advanced-filters { grid-template-columns: 1fr; }
        }
        .trz-field label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .trz-input {
            width: 100%;
            height: 38px;
            padding: 0 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            background: #fff;
            color: #0f172a;
            outline: none;
        }
        .trz-input:focus {
            border-color: #00a9a6;
            box-shadow: 0 0 0 2px rgba(0, 169, 166, 0.15);
        }
        .trz-btn-row {
            display: flex;
            gap: 8px;
        }

        /* Stage Quick Tabs */
        .trz-stage-tabs {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
            flex-wrap: wrap;
        }
        .trz-tabs-label {
            font-size: 12px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
        }
        .trz-tab {
            font-size: 12px;
            font-weight: 700;
            padding: 5px 11px;
            border-radius: 20px;
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .trz-tab:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .trz-tab.active {
            background: #00a9a6;
            color: #fff;
        }

        /* Results Info */
        .trz-results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 10px;
            padding: 0 4px;
        }
        .trz-highlight-stage {
            font-weight: 700;
            color: #0f172a;
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
        }

        /* Table Card */
        .trz-table-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .trz-table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .trz-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .trz-table th {
            background: #0f172a;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #1e293b;
        }
        .trz-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .trz-row:hover {
            background: #f8fafc;
        }
        .trz-row.is-blocked {
            background: #fffbf5;
        }
        .trz-row.is-blocked:hover {
            background: #fef7ee;
        }

        /* Cell Content Styles */
        .trz-oc-pill {
            display: inline-flex;
            align-items: center;
            background: #0f172a;
            color: #fff;
            border-radius: 6px;
            overflow: hidden;
            font-size: 12px;
            margin-bottom: 4px;
        }
        .trz-oc-label {
            background: #00a9a6;
            color: #fff;
            font-weight: 900;
            font-size: 10px;
            padding: 2px 5px;
        }
        .trz-oc-num {
            padding: 2px 7px;
            font-weight: 800;
            letter-spacing: .02em;
        }
        .trz-nv-sub {
            font-size: 12px;
            color: #475569;
            margin-bottom: 4px;
        }
        .trz-link-nv {
            color: #0369a1;
            font-weight: 700;
            text-decoration: none;
        }
        .trz-link-nv:hover { text-decoration: underline; }
        .trz-ot-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            background: #e0e7ff;
            color: #3730a3;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .trz-no-ot {
            font-size: 11px;
            color: #94a3b8;
            font-style: italic;
        }

        .trz-client-name {
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 220px;
        }
        .trz-client-rut {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 4px;
        }
        .trz-product-title {
            font-size: 12px;
            color: #334155;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 220px;
            margin-bottom: 4px;
        }
        .trz-print-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 800;
            background: #f1f5f9;
            color: #475569;
            padding: 1px 5px;
            border-radius: 3px;
        }

        /* Thumbnail */
        .trz-thumb-wrap {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            cursor: pointer;
            margin: 0 auto;
            background: #f8fafc;
        }
        .trz-thumb-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.2s ease;
        }
        .trz-thumb-wrap:hover .trz-thumb-img {
            transform: scale(1.1);
        }
        .trz-thumb-zoom {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.4);
            color: #fff;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.15s ease;
        }
        .trz-thumb-wrap:hover .trz-thumb-zoom { opacity: 1; }
        .trz-no-thumb {
            width: 44px;
            height: 44px;
            border-radius: 6px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 18px;
            margin: 0 auto;
        }

        /* Badges */
        .trz-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 5px;
            line-height: 1.25;
            white-space: nowrap;
        }
        .trz-date-sub {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }

        /* Blocker Box */
        .trz-blocker-box {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 7px;
            font-size: 12px;
            line-height: 1.35;
        }
        .box-blocked {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-weight: 600;
        }
        .box-ok {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .trz-blocker-icon {
            font-size: 13px;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .trz-blocker-text {
            flex: 1;
        }

        /* Progress Bar */
        .trz-prog-header {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .trz-prog-pct { color: #00a9a6; font-size: 12px; }
        .trz-prog-counts { color: #64748b; }
        .trz-bar-track {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 4px;
        }
        .bar-lg { height: 10px; border-radius: 5px; }
        .trz-bar-fill {
            height: 100%;
            background: #00a9a6;
            border-radius: 3px;
            transition: width 0.3s ease;
        }
        .fill-cyan { background: linear-gradient(90deg, #00a9a6, #06b6d4); }
        .trz-ship-sub {
            font-size: 11px;
        }
        .trz-shipped-tag {
            color: #15803d;
            font-weight: 700;
        }

        /* Pagination */
        .trz-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            border-top: 1px solid #f1f5f9;
            font-size: 13px;
        }
        .trz-pagination-info { color: #64748b; }
        .trz-pagination-links {
            display: flex;
            gap: 4px;
        }
        .trz-page-link {
            padding: 5px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: #334155;
            text-decoration: none;
            font-weight: 700;
            font-size: 12px;
        }
        .trz-page-link:hover { background: #f8fafc; border-color: #94a3b8; }
        .trz-page-link.active {
            background: #00a9a6;
            border-color: #00a9a6;
            color: #fff;
        }

        /* Empty State */
        .trz-empty-state {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 48px 24px;
            text-align: center;
        }
        .trz-empty-icon { font-size: 42px; margin-bottom: 12px; }
        .trz-empty-state h3 { font-size: 18px; color: #0f172a; margin: 0 0 8px; }
        .trz-empty-state p { color: #64748b; font-size: 14px; max-width: 500px; margin: 0 auto 20px; }

        /* Dossier Detail View */
        .trz-back-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .trz-back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: #0369a1;
            text-decoration: none;
        }
        .trz-back-link:hover { text-decoration: underline; }
        .trz-dossier-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px 22px;
            margin-bottom: 20px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
        }
        .trz-dossier-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .trz-dossier-tags {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        .trz-badge-dossier {
            font-size: 12px;
            font-weight: 800;
            padding: 3px 9px;
            border-radius: 5px;
        }
        .badge-nv { background: #0f172a; color: #fff; }
        .badge-oc { background: #00a9a6; color: #fff; }
        .badge-ot { background: #4f46e5; color: #fff; }
        .badge-empty { background: #f1f5f9; color: #64748b; }
        .trz-dossier-title {
            font-size: 26px;
            font-weight: 900;
            color: #0f172a;
            margin: 0 0 6px;
            letter-spacing: -0.02em;
        }
        .trz-dossier-meta {
            display: flex;
            gap: 10px;
            font-size: 13px;
            color: #64748b;
            flex-wrap: wrap;
        }
        .trz-dossier-stage-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 14px;
            font-weight: 800;
            padding: 8px 16px;
            border-radius: 20px;
        }
        .stage-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }

        /* Callout */
        .trz-callout {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 22px;
        }
        .callout-blocked {
            background: #fff7ed;
            border: 2px solid #fdba74;
            color: #9a3412;
        }
        .callout-ok {
            background: #f0fdf4;
            border: 2px solid #86efac;
            color: #166534;
        }
        .trz-callout-icon { font-size: 22px; line-height: 1; }
        .trz-callout-title {
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .05em;
            margin-bottom: 4px;
        }
        .trz-callout-text {
            font-size: 15px;
            font-weight: 700;
            line-height: 1.4;
        }

        /* Milestones Stepper */
        .trz-stepper-heading {
            font-size: 14px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin: 0 0 16px;
        }
        .trz-stepper {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            position: relative;
        }
        @media (max-width: 990px) {
            .trz-stepper { grid-template-columns: repeat(3, 1fr); gap: 14px; }
        }
        @media (max-width: 550px) {
            .trz-stepper { grid-template-columns: 1fr; }
        }
        .trz-step {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            transition: all 0.15s ease;
        }
        .trz-step.step-completed {
            border-color: #86efac;
            background: #f0fdf4;
        }
        .trz-step.step-in_progress {
            border-color: #93c5fd;
            background: #eff6ff;
        }
        .trz-step.step-blocked {
            border-color: #fdba74;
            background: #fff7ed;
        }
        .trz-step.step-skipped {
            opacity: 0.6;
        }
        .trz-step-indicator {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .trz-step-icon { font-size: 20px; }
        .trz-step-num {
            font-size: 11px;
            font-weight: 800;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e2e8f0;
            color: #475569;
        }
        .step-completed .trz-step-num { background: #22c55e; color: #fff; }
        .step-in_progress .trz-step-num { background: #3b82f6; color: #fff; }
        .step-blocked .trz-step-num { background: #f97316; color: #fff; }
        .trz-step-name {
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 2px;
            line-height: 1.25;
        }
        .trz-step-date {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 6px;
        }
        .trz-step-desc {
            font-size: 11px;
            color: #475569;
            line-height: 1.35;
        }

        /* 360° Grid Dossier */
        .trz-grid-dossier {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        @media (max-width: 900px) {
            .trz-grid-dossier { grid-template-columns: 1fr; }
        }
        .trz-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .trz-card-head {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .trz-card-icon { font-size: 18px; }
        .trz-card-head h3 {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.01em;
        }
        .trz-card-body {
            padding: 16px;
        }
        .trz-detail-item {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 10px;
            font-size: 13px;
            padding: 6px 0;
            border-bottom: 1px dashed #f1f5f9;
        }
        .trz-detail-item:last-child { border-bottom: none; }
        .detail-label { color: #64748b; }
        .detail-value { color: #0f172a; text-align: right; }
        .trz-card-divider {
            border: 0;
            border-top: 1px solid #e2e8f0;
            margin: 14px 0;
        }
        .trz-subheading {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            margin: 0 0 10px;
        }

        /* Product items */
        .trz-item-row {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 8px;
        }
        .item-title { font-size: 13px; color: #0f172a; margin-bottom: 4px; }
        .item-specs { font-size: 12px; color: #64748b; display: flex; gap: 8px; flex-wrap: wrap; }

        /* Design Preview Box */
        .trz-design-preview-box {
            display: flex;
            gap: 14px;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            margin-top: 12px;
        }
        .preview-thumb {
            width: 64px;
            height: 64px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            position: relative;
            cursor: pointer;
            flex-shrink: 0;
            background: #fff;
        }
        .preview-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .thumb-hint {
            position: absolute;
            inset: 0;
            background: rgba(15,23,42,0.6);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            opacity: 0;
            transition: opacity 0.15s ease;
        }
        .preview-thumb:hover .thumb-hint { opacity: 1; }
        .preview-info strong { display: block; font-size: 13px; color: #0f172a; margin-bottom: 2px; }
        .preview-info p { font-size: 12px; color: #64748b; margin: 0 0 8px; }

        /* Agenda cards */
        .trz-agenda-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .trz-agenda-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
        }
        .agenda-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }
        .agenda-machine { font-weight: 800; font-size: 13px; color: #0f172a; }
        .agenda-status {
            font-size: 10px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .status-1 { background: #e0e7ff; color: #3730a3; }
        .status-2 { background: #e0f2fe; color: #0369a1; }
        .status-3 { background: #dcfce7; color: #166534; }
        .agenda-meta { font-size: 12px; color: #64748b; display: flex; gap: 8px; }

        /* Metrics mini */
        .trz-metrics-mini-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin: 12px 0;
        }
        .metric-mini {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
        }
        .metric-val { font-size: 18px; font-weight: 900; color: #0f172a; }
        .metric-lbl { font-size: 11px; color: #64748b; font-weight: 700; }

        /* Events & Deliveries Table */
        .trz-events-table-wrap {
            overflow-x: auto;
        }
        .trz-events-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .trz-events-table th {
            background: #f1f5f9;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .trz-events-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .trz-ev-tag {
            background: #f1f5f9;
            font-weight: 800;
            font-size: 10px;
            padding: 1px 5px;
            border-radius: 3px;
        }

        /* Delivery Summary Grid */
        .trz-delivery-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        @media (max-width: 768px) {
            .trz-delivery-summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
        }
        .sum-title { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        .sum-val { font-size: 22px; font-weight: 900; color: #0f172a; line-height: 1; }

        /* Notice Boxes */
        .trz-notice-box {
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.4;
        }
        .notice-warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .notice-neutral { background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; }

        /* Attachments */
        .trz-attachments-box {
            margin-top: 12px;
            font-size: 12px;
        }
        .trz-attach-list {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        .trz-attach-pill {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 700;
            color: #334155;
        }

        /* Text colors */
        .text-success { color: #16a34a !important; }
        .text-warning { color: #d97706 !important; }
        .text-danger { color: #dc2626 !important; }
        .text-primary { color: #0284c7 !important; }
        .text-amber { color: #ea580c !important; }

        /* Rotator Modal */
        .trz-modal-backdrop {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.75);
            z-index: 99999;
            padding: 16px;
            backdrop-filter: blur(4px);
        }
        .trz-modal-backdrop.open { display: flex; }
        .trz-modal-box {
            width: min(980px, calc(100vw - 32px));
            max-height: calc(100vh - 32px);
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 14px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .trz-modal-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 18px;
            background: #1e293b;
            border-bottom: 1px solid #334155;
            color: #fff;
            gap: 12px;
            flex-wrap: wrap;
        }
        .trz-modal-title-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .trz-modal-icon { font-size: 20px; }
        .trz-modal-title { font-size: 14px; font-weight: 800; margin: 0; color: #fff; }
        .trz-modal-sub { font-size: 11px; color: #94a3b8; }
        .trz-modal-tools {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .trz-tool-btn {
            background: #334155;
            border: 1px solid #475569;
            color: #f8fafc;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .trz-tool-btn:hover {
            background: #00a9a6;
            border-color: #00a9a6;
            color: #fff;
        }
        .trz-angle-badge {
            font-size: 12px;
            font-weight: 800;
            color: #94a3b8;
            padding: 0 4px;
            min-width: 32px;
            text-align: center;
        }
        .trz-modal-close {
            background: #ef4444;
            border: 0;
            color: #fff;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 900;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 6px;
        }
        .trz-modal-close:hover { background: #dc2626; }
        .trz-modal-viewport {
            flex: 1;
            min-height: 440px;
            max-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
            background: #020617;
        }
        #trzModalImg {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            transition: transform 0.25s cubic-bezier(0.2, 0, 0, 1);
            transform-origin: center center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.6);
            border-radius: 6px;
        }
        .trz-modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 18px;
            background: #1e293b;
            border-top: 1px solid #334155;
            color: #94a3b8;
            font-size: 11px;
        }
        .trz-modal-footer .trz-btn-outline {
            background: #334155;
            border-color: #475569;
            color: #fff;
        }
        /* Process Breakdown Pills */
        .trz-process-breakdown {
            margin: 14px 0 16px;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .breakdown-title {
            display: block;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #64748b;
            margin-bottom: 8px;
        }
        .breakdown-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
        }
        .process-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #fff;
        }
        .pill-flexo { border-color: rgba(245, 158, 11, 0.35); background: rgba(245, 158, 11, 0.05); }
        .pill-sellado { border-color: rgba(2, 132, 199, 0.35); background: rgba(2, 132, 199, 0.05); }
        .pill-embalaje { border-color: rgba(16, 185, 129, 0.45); background: rgba(16, 185, 129, 0.07); }
        .proc-icon { font-size: 20px; }
        .proc-info { display: flex; flex-direction: column; }
        .proc-label { font-size: 11px; color: #64748b; font-weight: 600; }
        .proc-val { font-size: 14px; font-weight: 800; color: #0f172a; }

        /* Headings with hints */
        .trz-head-with-hint {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .trz-table-hint {
            font-size: 11px;
            color: #00a9a6;
            font-weight: 700;
        }

        /* Clickable tables and row highlights */
        .trz-table-clickable tbody tr {
            transition: background 0.12s ease;
        }
        .trz-table-clickable tbody tr:hover {
            background: rgba(0, 169, 166, 0.05) !important;
        }
        .trz-worker-name {
            font-weight: 700;
            color: #0f172a;
            display: block;
            font-size: 12px;
        }
        .trz-helper-sub {
            font-size: 11px;
            color: #00a9a6;
            font-weight: 600;
            display: block;
        }
        .trz-duration-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
        }
        .dur-done {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        .dur-prog {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        /* Guía badge button */
        .trz-badge-dlv-btn {
            background: #f0fdfa;
            border: 1px solid #99f6e4;
            color: #0f766e;
            font-weight: 800;
            font-size: 13px;
            padding: 5px 11px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .trz-badge-dlv-btn:hover {
            background: #00a9a6;
            color: #fff;
            border-color: #00a9a6;
            box-shadow: 0 2px 8px rgba(0, 169, 166, 0.25);
        }
        .trz-guia-hint {
            font-size: 10px;
            font-weight: 600;
            opacity: 0.85;
        }

        /* Wide floating modals */
        .trz-modal-wide {
            max-width: 820px !important;
            width: 95% !important;
            max-height: 90vh;
        }
        .trz-modal-body-content {
            padding: 18px 20px;
            background: #0f172a;
            color: #f8fafc;
            overflow-y: auto;
            max-height: calc(85vh - 120px);
        }
        .trz-modal-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .meta-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
        }
        .meta-label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .meta-val {
            font-size: 13px;
            color: #f8fafc;
            line-height: 1.25;
        }
        .meta-sub {
            font-size: 11px;
            color: #64748b;
            margin-top: 3px;
        }
        .trz-modal-annotation-box {
            background: rgba(30, 41, 59, 0.7);
            border-left: 3px solid #00a9a6;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 16px;
        }
        .annot-label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            color: #00a9a6;
            margin-bottom: 4px;
        }
        .annot-text {
            font-size: 12px;
            color: #cbd5e1;
            margin: 0;
            line-height: 1.4;
        }
        .trz-modal-section-title {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #94a3b8;
            margin: 14px 0 8px;
            border-bottom: 1px solid #334155;
            padding-bottom: 5px;
        }
        .trz-modal-table {
            background: #1e293b !important;
            color: #e2e8f0 !important;
            border-radius: 6px;
            border: 1px solid #334155;
        }
        .trz-modal-table th {
            background: #0f172a !important;
            color: #94a3b8 !important;
            border-bottom: 1px solid #334155 !important;
            font-size: 11px !important;
        }
        .trz-modal-table td {
            border-bottom: 1px solid #334155 !important;
            color: #f1f5f9 !important;
            font-size: 12px !important;
        }
        .trz-modal-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 12px 16px;
        }
        .detail-row {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .detail-name {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 600;
        }
        .detail-val {
            font-size: 13px;
            color: #f8fafc;
        }
        .trz-btn-xs {
            padding: 4px 9px !important;
            font-size: 11px !important;
            border-radius: 5px !important;
        }

        /* Print styles */
        @media print {
            .topbar, .subbar, .trz-header-actions, .trz-filter-card, .trz-pagination, .trz-back-bar, .trz-modal-backdrop {
                display: none !important;
            }
            .trz-container { max-width: 100%; padding: 0; }
            .trz-table-card, .trz-dossier-card, .trz-card { box-shadow: none; border-color: #ccc; }
        }
    </style>
    <?php
}
