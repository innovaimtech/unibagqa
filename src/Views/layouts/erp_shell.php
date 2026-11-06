<?php
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<link rel="stylesheet" href="/assets/css/unibag-shell.css?v=20261105"><link rel="stylesheet" href="/assets/fontawesome/css/all.min.css"><link rel="stylesheet" href="/assets/css/unibag-ui.css?v=20261031"><link rel="stylesheet" href="/assets/css/unibag-responsive.css?v=20261103"></head><body>';
    $currentPath = (string)($GLOBALS['path'] ?? '/');
    $currentArea = normalizeErpArea((string)($_SESSION['erp_area'] ?? 'ERP'));
    $requestedArea = detectRequestedArea($currentPath);
    $areaPermissions = sessionAreaPermissions();
    $displayArea = ($currentArea === 'ERP' && userCanAccessArea('ERP', $areaPermissions))
        ? 'ERP'
        : (userCanAccessArea($requestedArea, $areaPermissions) ? $requestedArea : $currentArea);

    $link = function (string $href, string $label, bool $isActive) : string {
        return '<a href="' . h($href) . '"' . ($isActive ? ' class="active"' : '') . '>' . h($label) . '</a>';
    };

    $areaDefinitions = erpAreaDefinitions();
    $appModeLabel = (string)($areaDefinitions[$displayArea]['label'] ?? $_SESSION['erp_area_label'] ?? $_SESSION['app_mode_label'] ?? 'ERP');
    $companyName = (string)($_SESSION['company_name'] ?? 'UNIBAG CHILE');
    $displayName = (string)($_SESSION['auth_display_name'] ?? $_SESSION['operator_name'] ?? 'Operador');
    $isProductionArea = str_starts_with($currentPath, '/production')
        || str_starts_with($currentPath, '/waste')
        || ($currentArea === 'PRODUCTION' && isProductionPath($currentPath));

    if ($isProductionArea) {
        renderProductionShell($body, $currentPath, $displayName, $companyName);
        echo '<script src="/assets/js/unibag-ui.js?v=20261105"></script></body></html>';
        exit;
    }

    $activeModule = 'dashboard';
    if ($displayArea === 'ERP' || $displayArea === 'RECEPTION') {
        if (
            $currentPath === '/'
            || $currentPath === '/reports/production-dashboard'
            || $currentPath === '/reports/graphics'
            || $currentPath === '/reports/resumen-diario'
            || $currentPath === '/dashboard/resumen'
            || $currentPath === '/reports/daily-summary'
        ) {
            $activeModule = 'dashboard';
        } elseif (
            str_starts_with($currentPath, '/reception')
            || str_starts_with($currentPath, '/purchase-orders')
            || str_starts_with($currentPath, '/import-containers')
        ) {
            $activeModule = 'reception';
        } elseif (
            str_starts_with($currentPath, '/reports/inventory')
            || str_starts_with($currentPath, '/stock/inventory-counts')
        ) {
            $activeModule = 'inventory';
        } elseif (
            str_starts_with($currentPath, '/warehouses')
            || str_starts_with($currentPath, '/stock')
            || str_starts_with($currentPath, '/rolls')
            || str_starts_with($currentPath, '/boxes')
            || str_starts_with($currentPath, '/pallets')
            || str_starts_with($currentPath, '/maquila')
            || str_starts_with($currentPath, '/cliches')
        ) {
            $activeModule = 'warehouses';
        } elseif (str_starts_with($currentPath, '/reports')) {
            $activeModule = 'reports';
        } elseif (str_starts_with($currentPath, '/bonificaciones')) {
            $activeModule = 'bonificaciones';
        } elseif (
            str_starts_with($currentPath, '/trazabilidad')
            || str_starts_with($currentPath, '/traceability')
        ) {
            $activeModule = 'traceability';
        } elseif (
            str_starts_with($currentPath, '/work-orders')
            || str_starts_with($currentPath, '/chemicals')
            || str_starts_with($currentPath, '/cut')
        ) {
            $activeModule = 'production';
        }
    } elseif ($displayArea === 'SCALE') {
        $activeModule = 'scale';
    } else {
        $activeModule = 'production';
    }

    echo '<div class="topbar"><div class="inner">';
    echo '<button type="button" class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="nav-menu"><span class="bars" aria-hidden="true">☰</span> Menú</button>';
    echo '<nav class="menu" id="nav-menu">';
    if ($displayArea === 'ERP' && userCanAccessArea('ERP', $areaPermissions)) {
        echo $link('/', 'Dashboard', $activeModule === 'dashboard');
        echo $link('/reception', 'Recepción', $activeModule === 'reception');
        echo $link('/reports/operator-waste', 'Informes', $activeModule === 'reports');
        echo $link('/bonificaciones', 'Bonificaciones', $activeModule === 'bonificaciones');
        echo $link('/stock', 'Bodega', $activeModule === 'warehouses');
        echo $link('/reports/inventory', 'Inventario', $activeModule === 'inventory');
        echo $link('/trazabilidad', 'Trazabilidad', $activeModule === 'traceability');
    } elseif ($displayArea === 'RECEPTION') {
        echo $link('/purchase-orders?status=active&supplier_type=NATIONAL', 'Recepción', $activeModule === 'reception');
        echo $link('/stock', 'Bodega', $activeModule === 'warehouses');
        echo $link('/stock/inventory-counts', 'Inventario', $activeModule === 'inventory');
    } elseif ($displayArea === 'SCALE') {
        echo $link('/scale/sealing-waste', 'Balanza', str_starts_with($currentPath, '/scale'));
    } else {
        echo $link('/production/machines', 'Producción', str_starts_with($currentPath, '/production') || str_starts_with($currentPath, '/waste') || str_starts_with($currentPath, '/work-orders'));
    }
    echo '</nav>';
    $allowedAreas = [];
    foreach (erpAreaDefinitions() as $aId => $aDef) {
        if (userCanAccessArea($aId, $areaPermissions)) {
            $allowedAreas[$aId] = $aDef['label'];
        }
    }
    echo '<div class="top-right">';
    if (count($allowedAreas) > 1) {
        echo '<select aria-label="Cambiar de Área" onchange="if(this.value) location.href=\'/switch-area?area=\'+this.value" style="cursor:pointer" title="Cambiar área de trabajo">';
        foreach ($allowedAreas as $aId => $aLabel) {
            $sel = ($aId === $displayArea) ? ' selected' : '';
            echo '<option value="' . h($aId) . '"' . $sel . '>' . h($aLabel) . '</option>';
        }
        echo '</select>';
    } else {
        echo '<select aria-label="Sistema" disabled><option>' . h($appModeLabel) . '</option></select>';
    }
    echo '<a class="pill" href="/"><span>' . h($companyName) . '</span></a>';
    echo '<a class="pill" href="#"><span>' . h($displayName) . '</span></a>';
    echo '<a class="pill" href="/logout">Salir</a>';
    echo '</div>';
    echo '</div></div>';

    echo '<div class="subbar"><div class="inner">';
    echo '<div class="submenu">';
    if ($activeModule === 'dashboard') {
        $isPanel = $currentPath === '/' || $currentPath === '/reports/production-dashboard';
        $isDailySummary = $currentPath === '/reports/resumen-diario' || $currentPath === '/dashboard/resumen' || $currentPath === '/reports/daily-summary';
        $isGraphics = $currentPath === '/reports/graphics';
        $isLunch = str_starts_with($currentPath, '/reports/colaciones') || str_starts_with($currentPath, '/reports/lunch-breaks');
        echo '<a class="subitem' . ($isPanel ? ' active' : '') . '" href="/"><span>Panel Producción & Mermas</span></a>';
        echo '<a class="subitem' . ($isDailySummary ? ' active' : '') . '" href="/reports/resumen-diario"><span>📋 Resumen Diario</span></a>';
        echo '<a class="subitem' . ($isGraphics ? ' active' : '') . '" href="/reports/graphics"><span>Gráficos</span></a>';
        // "Control Colaciones" vive solo en el menú Informes (antes estaba repetido aquí).
    } elseif ($activeModule === 'reception') {
        $poStatus = (string)($_GET['status'] ?? 'active');
        $suppType = strtoupper((string)($_GET['supplier_type'] ?? 'NATIONAL'));
        $isImport = str_starts_with($currentPath, '/import-containers') || ($suppType === 'IMPORT' && $poStatus === 'active');
        $isComplete = str_starts_with($currentPath, '/purchase-orders') && $poStatus === 'complete';
        $isNatActive = !$isImport && !$isComplete;

        echo '<a class="subitem' . ($isNatActive ? ' active' : '') . '" href="/purchase-orders?status=active&supplier_type=NATIONAL"><span>OC Nacionales Activas</span></a>';
        echo '<a class="subitem' . ($isImport ? ' active' : '') . '" href="/import-containers?status=active"><span>OC Importación (Contenedores)</span></a>';
        echo '<a class="subitem' . ($isComplete ? ' active' : '') . '" href="/purchase-orders?status=complete&supplier_type=NATIONAL"><span>Recepciones Finalizadas</span></a>';
    } elseif ($activeModule === 'reports') {
        $isLunch = str_starts_with($currentPath, '/reports/colaciones') || str_starts_with($currentPath, '/reports/lunch-breaks');
        echo '<a class="subitem' . ($currentPath === '/reports/operator-waste' ? ' active' : '') . '" href="/reports/operator-waste"><span>Merma por Operador</span></a>';
        echo '<a class="subitem' . ($currentPath === '/reports/despachos' ? ' active' : '') . '" href="/reports/despachos"><span>Despachos</span></a>';
        echo '<a class="subitem' . ($currentPath === '/reports/machine-production' ? ' active' : '') . '" href="/reports/machine-production"><span>Producción Máquinas</span></a>';
        echo '<a class="subitem' . ($currentPath === '/reports/machine-events' ? ' active' : '') . '" href="/reports/machine-events"><span>Eventos de Máquina</span></a>';
        echo '<a class="subitem' . ($isLunch ? ' active' : '') . '" href="/reports/colaciones"><span>🍱 Control Colaciones</span></a>';
        echo '<a class="subitem' . ($currentPath === '/reports/nivel-servicio' ? ' active' : '') . '" href="/reports/nivel-servicio"><span>Nivel de Servicio</span></a>';
        echo '<a class="subitem' . (str_starts_with($currentPath, '/reports/stock-esperado') ? ' active' : '') . '" href="/reports/stock-esperado"><span>Stock Esperado</span></a>';
        echo '<a class="subitem' . (str_starts_with($currentPath, '/reports/reception-audit') ? ' active' : '') . '" href="/reports/reception-audit"><span>Auditoría Recepción</span></a>';
        echo '<a class="subitem' . (str_starts_with($currentPath, '/reports/monthly-presentation') ? ' active' : '') . '" href="/reports/monthly-presentation"><span>Informe Mensual PPT</span></a>';
    } elseif ($activeModule === 'bonificaciones') {
        $bonusView = (string)($_GET['view'] ?? 'bonoflexo');
        echo '<a class="subitem' . ($bonusView === 'bonoflexo' ? ' active' : '') . '" href="/bonificaciones?view=bonoflexo"><span>Bonoflexo</span></a>';
        echo '<a class="subitem' . ($bonusView === 'bonoseri' ? ' active' : '') . '" href="/bonificaciones?view=bonoseri"><span>Bono seri</span></a>';
        echo '<a class="subitem' . ($bonusView === 'bonocys' ? ' active' : '') . '" href="/bonificaciones?view=bonocys"><span>Bono CYS</span></a>';
        echo '<a class="subitem' . ($bonusView === 'bonopulp' ? ' active' : '') . '" href="/bonificaciones?view=bonopulp"><span>Bono pulp</span></a>';
        echo '<a class="subitem' . ($bonusView === 'bonoayudante' ? ' active' : '') . '" href="/bonificaciones?view=bonoayudante"><span>Bono ayudante</span></a>';
        echo '<a class="subitem' . ($bonusView === 'bonomantencion' ? ' active' : '') . '" href="/bonificaciones?view=bonomantencion"><span>Bono mantención</span></a>';
        echo '<a class="subitem' . ($bonusView === 'configuracion' ? ' active' : '') . '" href="/bonificaciones?view=configuracion"><span>Configuración</span></a>';
    } elseif ($activeModule === 'warehouses') {
        $isMatRequests = str_starts_with($currentPath, '/stock/material-requests') || str_starts_with($currentPath, '/mobile/picking');
        $isTrzEntry = str_starts_with($currentPath, '/stock/traceability-entry') || str_starts_with($currentPath, '/stock/ingreso-trazable');
        $isTrzTree = str_starts_with($currentPath, '/stock/traceability') && !$isTrzEntry;
        $isTransfers = str_starts_with($currentPath, '/stock/transfers') || str_starts_with($currentPath, '/stock/traspasos');
        $isDispatch = str_starts_with($currentPath, '/stock/dispatch');
        $isWarehouseBonus = str_starts_with($currentPath, '/stock/bonificacion');
        $isStock = str_starts_with($currentPath, '/stock') && !str_starts_with($currentPath, '/stock/inventory-counts') && !$isTrzEntry && !$isTrzTree && !$isTransfers && !$isMatRequests && !$isDispatch && !$isWarehouseBonus;
        $isWh = str_starts_with($currentPath, '/warehouses');
        echo '<a class="subitem' . ($isStock ? ' active' : '') . '" href="/stock"><span>Consulta de stock</span></a>';
        echo '<a class="subitem' . ($isMatRequests ? ' active' : '') . '" href="/stock/material-requests"><span>📋 Solicitudes MP (Bodega 3000)</span></a>';
        echo '<a class="subitem' . ($isTransfers ? ' active' : '') . '" href="/stock/transfers"><span>🔄 Traspasos a Producción</span></a>';
        echo '<a class="subitem' . ($isDispatch ? ' active' : '') . '" href="/stock/dispatch"><span>🚚 Despacho</span></a>';
        echo '<a class="subitem' . ($isTrzEntry ? ' active' : '') . '" href="/stock/traceability-entry"><span>📦 Ingreso con Trazabilidad</span></a>';
        echo '<a class="subitem' . ($isTrzTree ? ' active' : '') . '" href="/stock/traceability"><span>🌳 Árbol Trazabilidad</span></a>';
        if (warehouseBonusCanAccess()) {
            echo '<a class="subitem' . ($isWarehouseBonus ? ' active' : '') . '" href="/stock/bonificacion"><span>🏅 Bonificación</span></a>';
        }
        echo '<a class="subitem' . ($isWh ? ' active' : '') . '" href="/warehouses"><span>Configuración de bodega</span></a>';
        // "Recepción de bobinas (OC)" vive solo en el módulo Recepción (antes estaba repetido aquí).
    } elseif ($activeModule === 'inventory') {
        $isInvReport = str_starts_with($currentPath, '/reports/inventory');
        $isInvCount = str_starts_with($currentPath, '/stock/inventory-counts');
        // Traspasos a Producción, Ingreso con Trazabilidad y Árbol de Trazabilidad viven solo en el menú Bodega
        // (antes estaban repetidos aquí). Inventario queda solo con informe y toma de inventario.
        if (userCanAccessArea('ERP', $areaPermissions)) {
            echo '<a class="subitem' . ($isInvReport ? ' active' : '') . '" href="/reports/inventory"><span>Informe de inventario</span></a>';
        }
        echo '<a class="subitem' . ($isInvCount ? ' active' : '') . '" href="/stock/inventory-counts"><span>Toma de inventario</span></a>';
    } elseif ($activeModule === 'traceability') {
        $trzStage = (string)($_GET['stage'] ?? 'all');
        $isDetalle = str_starts_with($currentPath, '/trazabilidad/detalle') || isset($_GET['id']);
        echo '<a class="subitem' . ($trzStage === 'all' && !$isDetalle ? ' active' : '') . '" href="/trazabilidad?stage=all"><span>Todas las OTs</span></a>';
        echo '<a class="subitem' . ($trzStage === 'clises_espera' ? ' active' : '') . '" href="/trazabilidad?stage=clises_espera"><span>⏳ Esperando Clisés</span></a>';
        echo '<a class="subitem' . ($trzStage === 'preparacion_espera' ? ' active' : '') . '" href="/trazabilidad?stage=preparacion_espera"><span>📐 Pre-Prensa</span></a>';
        echo '<a class="subitem' . ($trzStage === 'planificacion_espera' ? ' active' : '') . '" href="/trazabilidad?stage=planificacion_espera"><span>📅 Por Planificar</span></a>';
        echo '<a class="subitem' . ($trzStage === 'produccion' ? ' active' : '') . '" href="/trazabilidad?stage=produccion"><span>⚙️ En Producción</span></a>';
        echo '<a class="subitem' . ($trzStage === 'despacho_pendiente' ? ' active' : '') . '" href="/trazabilidad?stage=despacho_pendiente"><span>🚚 Pendiente Despacho</span></a>';
        echo '<a class="subitem' . ($trzStage === 'entregado' ? ' active' : '') . '" href="/trazabilidad?stage=entregado"><span>✅ Finalizadas</span></a>';
    } elseif ($activeModule === 'scale') {
        echo '<a class="subitem" href="/scale/sealing-waste"><span>Merma Selladora</span></a>';
    } elseif ($activeModule === 'production') {
        echo '<a class="subitem" href="/production/machines"><span>Asignación máquina</span></a>';
        echo '<a class="subitem" href="/production/work-orders/new"><span>Iniciar nueva OT</span></a>';
        echo '<a class="subitem" href="/production/work-orders/active"><span>En cursos</span></a>';
        echo '<a class="subitem" href="/production/work-orders/history"><span>Histórico OTs</span></a>';
        echo '<a class="subitem" href="/cut"><span>Proceso de corte</span></a>';
        echo '<a class="subitem" href="/chemicals/weighings"><span>Pesajes de tintas</span></a>';
    }
    echo '</div>';
    echo '</div></div>';
    echo '<main>' . $body . '</main>';
    echo '<script>(function(){var b=document.getElementById("nav-toggle");if(!b)return;b.addEventListener("click",function(){var o=document.body.classList.toggle("nav-open");b.setAttribute("aria-expanded",o?"true":"false");});})();</script>';
    echo '<script>
      (function () {
        function attachLegacyCollapsiblePanels(root) {
          if (!root || typeof root.querySelectorAll !== "function") return;
          var cards = root.querySelectorAll(".legacy-sheet-card");
          for (var i = 0; i < cards.length; i++) {
            (function (card) {
              if (!card || card.classList.contains("has-legacy-collapse")) return;
              var table = card.querySelector(":scope > table.legacy-sheet-table");
              if (!table) {
                var anyTable = card.querySelector("table.legacy-sheet-table");
                if (!anyTable) return;
                table = anyTable;
              }
              var thead = table.querySelector(":scope > thead");
              if (!thead) return;
              var firstRow = thead.querySelector(":scope > tr:first-child");
              if (!firstRow) return;
              var rowCells = firstRow.querySelectorAll(":scope > th, :scope > td");
              if (rowCells.length === 0) return;
              var titleCell = null;
              var titleText = "";
              if (rowCells.length === 1) {
                titleCell = rowCells[0];
              } else {
                var mergedCell = firstRow.querySelector(":scope > th[colspan], :scope > td[colspan]");
                if (mergedCell) {
                  var cs = parseInt(mergedCell.getAttribute("colspan") || "0", 10);
                  if (cs >= 2) {
                    titleCell = mergedCell;
                  }
                }
                if (!titleCell) {
                  return;
                }
              }
              if (!titleCell) return;
              if (titleCell.querySelector("table")) return;
              titleText = ((titleCell.innerText || titleCell.textContent || "")).replace(/\s+/g, " ").trim();
              if (!titleText) return;

              firstRow.classList.add("legacy-sheet-title-row");

              var head = document.createElement("div");
              head.className = "legacy-sheet-head";
              head.setAttribute("role", "button");
              head.setAttribute("tabindex", "0");
              var titleWrap = document.createElement("div");
              titleWrap.className = "legacy-sheet-head-title";
              titleWrap.textContent = titleText;
              var arrow = document.createElement("div");
              arrow.className = "legacy-sheet-head-arrow";
              head.appendChild(titleWrap);
              head.appendChild(arrow);

              var body = document.createElement("div");
              body.className = "legacy-sheet-body";
              while (card.firstChild) {
                body.appendChild(card.firstChild);
              }
              card.appendChild(head);
              card.appendChild(body);
              card.classList.add("has-legacy-collapse");

              function applyCollapsed(collapsed) {
                if (collapsed) {
                  card.classList.add("is-collapsed");
                } else {
                  card.classList.remove("is-collapsed");
                }
                if (typeof window.Chart !== "undefined") {
                  var charts = window.Chart.instances || [];
                  try {
                    Object.keys(charts).forEach(function (ck) {
                      var ch = charts[ck];
                      if (ch && typeof ch.resize === "function" && body.contains(ch.canvas)) {
                        ch.resize();
                      }
                    });
                  } catch (e) {}
                }
              }
              head.addEventListener("click", function (e) {
                if (e.target.closest && e.target.closest("a, button, input, select, textarea, label")) return;
                applyCollapsed(!card.classList.contains("is-collapsed"));
              });
              head.addEventListener("keydown", function (e) {
                if (e.key === "Enter" || e.key === " ") {
                  e.preventDefault();
                  applyCollapsed(!card.classList.contains("is-collapsed"));
                }
              });
            })(cards[i]);
          }
        }

        function attachAllCollapsibles() {
          attachLegacyCollapsiblePanels(document.body || document.documentElement || document);
          if (typeof window.attachCollapsibleControls === "function") {
            try { window.attachCollapsibleControls(document); } catch (e) {}
          }
        }
        if (document.readyState === "loading") {
          document.addEventListener("DOMContentLoaded", attachAllCollapsibles);
        } else {
          attachAllCollapsibles();
        }
        window.attachLegacyCollapsiblePanels = attachLegacyCollapsiblePanels;
        window.attachAllCollapsibles = attachAllCollapsibles;
      })();
    </script>';
    echo '<script src="/assets/js/unibag-ui.js?v=20261105"></script></body></html>';

