<link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
<?php
    $view = strtolower(trim((string)($_GET['view'] ?? 'pending')));
    $subLink = static function (?string $href, string $label, bool $isActive = false): string {
        if ($href === null || $href === '') {
            return '<span class="prod-nav-static">' . h($label) . '</span>';
        }
        return '<a class="prod-nav-sublink' . ($isActive ? ' active' : '') . '" href="' . h($href) . '">' . h($label) . '</a>';
    };
    $group = static function (string $label, string $iconClass, bool $isActive, array $items): string {
        return '<div class="prod-nav-folder' . ($isActive ? ' active' : '') . '">'
            . '<div class="prod-nav-folder-head"><span class="prod-nav-folder-label"><span class="prod-nav-folder-icon ' . h($iconClass) . '" aria-hidden="true"></span>' . h($label) . '</span><span class="prod-nav-folder-caret">v</span></div>'
            . '<div class="prod-nav-folder-body">' . implode('', $items) . '</div>'
            . '</div>';
    };

    echo '<div class="prod-shell" id="prodShell">';
    echo '<button class="prod-sidebar-backdrop" id="prodSidebarBackdrop" type="button" aria-label="Cerrar menú"></button>';
    echo '<aside class="prod-sidebar" id="prodSidebar">';
    echo '<div class="prod-brand">';
    echo '<div class="prod-brand-box"><span class="prod-brand-mark">U</span><span class="prod-brand-word">unibag</span></div>';
    echo '</div>';
    echo '<div class="prod-nav-title">Navegación</div>';
    echo $group('Asignación máquina', 'machine', str_starts_with($currentPath, '/production/machines') || str_starts_with($currentPath, '/production/shifts'), [
        $subLink('/production/machines', 'Iniciar / Terminar turno', str_starts_with($currentPath, '/production/machines') || str_starts_with($currentPath, '/production/shifts')),
    ]);
    echo $group('Ordenes de trabajo', 'orders', str_starts_with($currentPath, '/production/work-orders') || str_starts_with($currentPath, '/work-orders'), [
        $subLink('/production/work-orders/new', 'Iniciar nueva OT', str_starts_with($currentPath, '/production/work-orders/new')),
        $subLink('/production/work-orders/active', 'En cursos', str_starts_with($currentPath, '/production/work-orders/active')),
        $subLink('/production/work-orders/history', 'Historico', str_starts_with($currentPath, '/production/work-orders/history')),
    ]);
    echo $group('Trazabilidad', 'traceability', str_starts_with($currentPath, '/production/traceability'), [
        $subLink('/production/traceability', 'Árbol Genealógico 360°', str_starts_with($currentPath, '/production/traceability')),
    ]);
    echo $group('Gestión de residuos', 'waste', str_starts_with($currentPath, '/waste'), [
        $subLink('/waste/management', 'Panel de residuos', $currentPath === '/waste/management'),
        $subLink('/waste/molino', 'Molino', $currentPath === '/waste/molino'),
        $subLink('/waste/compactadora', 'Compactadora', $currentPath === '/waste/compactadora'),
    ]);

    echo '</aside>';
    echo '<div class="prod-main-wrap">';
    echo '<header class="prod-topbar">';
    echo '<div class="prod-topbar-title">';
    echo '<div class="prod-topbar-brand">Unibag</div>';
    echo '<button class="prod-toggle-btn" id="prodSidebarToggle" type="button" aria-expanded="false" aria-controls="prodSidebar" aria-label="Abrir menú">&#9776;</button>';
    echo '<span>' . h(productionSectionLabel($currentPath)) . '</span>';
    echo '</div>';
    $allowedAreas = [];
    $areaPermissions = sessionAreaPermissions();
    foreach (erpAreaDefinitions() as $aId => $aDef) {
        if (userCanAccessArea($aId, $areaPermissions)) {
            $allowedAreas[$aId] = $aDef['label'];
        }
    }
    echo '<div class="prod-topbar-right">';
    if (count($allowedAreas) > 1) {
        echo '<select aria-label="Cambiar de Área" onchange="if(this.value) location.href=\'/switch-area?area=\'+this.value" style="background:#0F766E;color:#FFFFFF;border:1px solid #14B8A6;border-radius:6px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer" title="Cambiar área de trabajo">';
        foreach ($allowedAreas as $aId => $aLabel) {
            $sel = ($aId === 'PRODUCTION') ? ' selected' : '';
            echo '<option value="' . h($aId) . '"' . $sel . '>' . h($aLabel) . '</option>';
        }
        echo '</select>';
    }
    echo '<div class="prod-user-pill">Usuario: ' . h($displayName) . '</div>';
    echo '<div class="prod-user-pill">Empresa: ' . h($companyName) . '</div>';
    echo '<a class="prod-user-pill logout" href="/logout">Salir</a>';
    echo '</div>';
    echo '</header>';
    echo '<main class="prod-main">' . $body . '</main>';
    echo '<script>
      (function () {
        var isMobileLayout = function () {
          return window.matchMedia("(max-width: 980px)").matches;
        };
        var shell = document.getElementById("prodShell");
        var sidebar = document.getElementById("prodSidebar");
        var toggle = document.getElementById("prodSidebarToggle");
        var backdrop = document.getElementById("prodSidebarBackdrop");
        if (!shell || !sidebar || !toggle || !backdrop) return;
        var sync = function (open) {
          var shouldOpen = isMobileLayout() ? open : false;
          shell.classList.toggle("menu-open", shouldOpen);
          toggle.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
          toggle.setAttribute("aria-label", shouldOpen ? "Cerrar menú" : "Abrir menú");
        };
        toggle.addEventListener("click", function () {
          if (!isMobileLayout()) return;
          sync(!shell.classList.contains("menu-open"));
        });
        backdrop.addEventListener("click", function () {
          sync(false);
        });
        document.addEventListener("keydown", function (event) {
          if (event.key === "Escape") {
            sync(false);
          }
        });
        window.addEventListener("resize", function () {
          sync(false);
        });
        sync(false);
      })();
    </script>';
    echo '</div>';
    echo '</div>';

