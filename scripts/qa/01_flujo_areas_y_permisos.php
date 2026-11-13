<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

/** Replica fiel de unibagEnforceAuthenticatedAreaAccess sin exit/redirect. Devuelve null si permite, o la URL destino. */
function guard(string $path): ?string {
    $perms = sessionAreaPermissions();
    $req = detectRequestedArea($path);
    return userCanAccessArea($req, $perms) ? null : firstAllowedAreaHome($perms);
}

$profiles = [
    'ERP puro'            => ['perm_area_erp'=>1,'perm_area_reception'=>1,'perm_area_production'=>0,'perm_area_scale'=>0],
    'Produccion puro'     => ['perm_area_erp'=>0,'perm_area_reception'=>0,'perm_area_production'=>1,'perm_area_scale'=>1],
    'Recepcion puro'      => ['perm_area_erp'=>0,'perm_area_reception'=>1,'perm_area_production'=>0,'perm_area_scale'=>0],
    'Balanza puro'        => ['perm_area_erp'=>0,'perm_area_reception'=>0,'perm_area_production'=>0,'perm_area_scale'=>1],
    'Multi (todo)'        => ['perm_area_erp'=>1,'perm_area_reception'=>1,'perm_area_production'=>1,'perm_area_scale'=>1],
];

// ruta => areas de sesion en las que DEBE quedar permitida (si el perfil tiene permiso del area)
$prodPaths = ['/production/machines','/production/work-orders/new','/production/work-orders/active','/production/work-orders/history',
    '/production/work-orders/operate','/production/work-orders/123/operate','/production/materials/request','/production/materials/bodega3000',
    '/production/traceability','/waste/management','/waste/molino','/waste/compactadora','/prodwrk.php','/prodwrk'];
$prodSessionExtra = ['/work-orders','/work-orders/5/start','/work-orders/5/finish','/chemicals/weighings','/cliches','/cut','/rolls/10','/boxes/3','/pallets/2'];
$erpPaths = ['/','/reports/operator-waste','/reports/inventory','/reports/graphics','/reports/colaciones','/bonificaciones','/stock','/stock/transfers','/stock/traspasos',
    '/warehouses','/trazabilidad','/purchase-orders','/import-containers','/reception','/stock/inventory-counts'];
$scalePaths = ['/scale/sealing-waste'];

$fail = 0; $pass = 0;
$check = function(string $profile, string $session, string $path, bool $expectAllowed, ?string $expectNotRedirectTo = null) use (&$fail,&$pass) {
    $_SESSION['erp_area'] = $session;
    $r = guard($path);
    $ok = $expectAllowed ? ($r === null) : true;
    if ($expectNotRedirectTo !== null && $r !== null && str_contains($r, $expectNotRedirectTo)) $ok = false;
    if ($ok) { $pass++; } else { $fail++; echo "[FALLO] perfil=$profile sesion=$session $path -> " . ($r ?? 'permitido') . "\n"; }
};

foreach ($profiles as $name => $p) {
    $_SESSION = $p + ['user_id'=>1];
    $perms = sessionAreaPermissions();

    // 1. Usuario en sesion PRODUCTION con permiso: ninguna ruta de planta puede rebotar, y NUNCA a recepcion
    if ($perms['PRODUCTION']) {
        foreach (array_merge($prodPaths, $prodSessionExtra) as $path) {
            $check($name, 'PRODUCTION', $path, true, 'purchase-orders');
        }
    }
    // 2. Sesion ERP con permiso ERP: rutas ERP y rutas de bodega permitidas
    if ($perms['ERP']) {
        foreach (array_merge($erpPaths, ['/work-orders','/chemicals/weighings','/cliches','/cut','/rolls/10','/boxes/3','/pallets/2']) as $path) {
            $check($name, 'ERP', $path, true);
        }
    }
    // 3. Sesion RECEPTION
    if ($perms['RECEPTION'] && !$perms['ERP']) {
        foreach (['/purchase-orders','/import-containers','/reception','/stock','/stock/inventory-counts'] as $path) {
            $check($name, 'RECEPTION', $path, true);
        }
    }
    // 4. Sesion SCALE
    if ($perms['SCALE']) {
        foreach ($scalePaths as $path) $check($name, 'SCALE', $path, true);
    }
    // 5. Sin bucles: el home de cada area permitida debe ser accesible para ese usuario
    foreach (erpAreaDefinitions() as $aId => $def) {
        if (!$perms[$aId]) continue;
        $_SESSION['erp_area'] = $aId;
        $homePath = parse_url($def['home'], PHP_URL_PATH) ?: '/';
        $r = guard($homePath);
        if ($r !== null) { $fail++; echo "[BUCLE] perfil=$name area=$aId home=$homePath rebota a $r\n"; } else { $pass++; }
    }
    // 6. Fallback siempre aterriza en una ruta permitida (no en /logout) cuando hay algun permiso
    $_SESSION['erp_area'] = 'ERP';
    $fb = firstAllowedAreaHome($perms);
    if (array_filter($perms) && $fb === '/logout') { $fail++; echo "[FALLO] $name fallback=/logout con permisos\n"; } else { $pass++; }
}

// 7. Regresion del modo solo lectura: sesion ERP NO debe volverse solo-lectura en rutas de bodega/corte
$_SESSION = $profiles['ERP puro'] + ['user_id'=>1]; $_SESSION['erp_area'] = 'ERP';
foreach (['/cut','/cut/process','/rolls/10','/boxes/3','/pallets/2','/stock','/work-orders'] as $path) {
    if (isErpProductionReadOnlyMode($path)) { $fail++; echo "[REGRESION] ERP en $path quedo solo-lectura\n"; } else { $pass++; }
}
// 8. Sesion ERP sigue en solo lectura en trazabilidad de produccion (comportamiento original)
if (!isErpProductionReadOnlyMode('/production/traceability')) { $fail++; echo "[REGRESION] ERP deberia ser solo-lectura en /production/traceability\n"; } else { $pass++; }

echo "\nPruebas de flujo: $pass OK, $fail fallos\n";
exit($fail === 0 ? 0 : 1);
