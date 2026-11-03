<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Consola Operativa de Serigrafía (Pulpo y Plana)
// (src/Http/Production/SerigrafiaConsoleModule.php)
// =============================================================================

function renderSerigrafiaInkConsumptionSection(array $ctx, string $returnTo = '', bool $isCollapsible = true): void
{
    echo renderView('production/serigrafia/ink_consumption', get_defined_vars());
}


function renderSerigrafiaConsoleInfoSection(array $__vars): void
{
    echo renderView('production/serigrafia/info_section', $__vars);
}

function handleSerigrafiaConsoleRoutes(
    string $path,
    string $method,
    ProductionService $prodService,
    int $workerId
): bool {
    if (($path === '/production/work-orders/events/pulpo-fabricacion' || (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'pulposave')) && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $colorMode = trim((string)($_REQUEST['colormode'] ?? 'Frente'));
        if ($colorMode !== 'Dorso') $colorMode = 'Frente';
        $header = [
            'temp_horno' => (int)($_POST['temp_horno'] ?? 0),
            'maq_speed' => (float)str_replace(',', '.', (string)($_POST['maq_speed'] ?? 0)),
            'horno_speed' => (float)str_replace(',', '.', (string)($_POST['horno_speed'] ?? 0)),
            'medidas_camillas' => trim((string)($_POST['medidas_camillas'] ?? '')),
        ];
        $units = [];
        for ($px = 0; $px < 10; $px++) {
            $units[$px] = [
                'color_id' => trim((string)($_POST["unit_color_{$px}"] ?? '')),
                'val_temp_secador' => (float)str_replace(',', '.', (string)($_POST["unit_temp_sec_{$px}"] ?? 0)),
                'val_num_bastidor' => trim((string)($_POST["unit_bastidor_{$px}"] ?? '')),
                'val_mesh' => (float)str_replace(',', '.', (string)($_POST["unit_mesh_{$px}"] ?? 0)),
                'val_total_tinta' => (float)str_replace(',', '.', (string)($_POST["unit_tinta_{$px}"] ?? 0)),
                'val_carga_tinta' => (float)str_replace(',', '.', (string)($_POST["unit_carga_tinta_{$px}"] ?? 0)),
                'val_sobrante_tinta' => (float)str_replace(',', '.', (string)($_POST["unit_sobrante_tinta_{$px}"] ?? 0)),
            ];
        }
        $ok = $prodService->savePulpoV2Data($agId, $colorMode, $header, $units);
        if ($ok) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&colormode=' . rawurlencode($colorMode) . '&saved_pulpo=1');
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&colormode=' . rawurlencode($colorMode) . '&error=' . rawurlencode('Error al guardar fabricación Pulpo.'));
        }
        return true;
    }


    return false;
}
