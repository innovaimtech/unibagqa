<?php
declare(strict_types=1);
// Bonificación de bodega: reproduce los totales de la planilla de oct-26 y las reglas de montos, cumplimiento y vigencia.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
$root = dirname(__DIR__, 2);
require_once $root . '/src/WarehouseBonusService.php';

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $fail++;
    }
};
$throws = function (callable $fn, string $needle = ''): bool {
    try {
        $fn();
    } catch (Throwable $e) {
        return $needle === '' || stripos($e->getMessage(), $needle) !== false;
    }
    return false;
};

$trz = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$erp = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$erp->exec("CREATE TABLE workers (wrk_firstname TEXT, wrk_lastname TEXT, wrk_status INT);
CREATE TABLE user (user_firstname TEXT, user_lastname TEXT, user_status INT);
INSERT INTO workers VALUES ('Hernán Enrique','Orellana Astorga',1),('Hugo Andres','Ceballos Chamorro',1),('Trabajador','Inactivo',0);
INSERT INTO user VALUES ('Hernan Enrique','Orellana Astorga',1),('Oscar','Gajardo',1),('Usuario','Eliminado',-1);");
$s = new WarehouseBonusService($trz, $erp);
$oct = '2026-10';
$rowOf = function (string $month, string $name) use ($s): ?array {
    foreach ($s->rows($month) as $row) {
        if ($row['operator'] === $name) {
            return $row;
        }
    }
    return null;
};

// Listado de operadores
$names = $s->erpNames();
$check('ERP: reúne trabajadores y usuarios activos', in_array('Oscar Gajardo', $names, true) && in_array('Hugo Andres Ceballos Chamorro', $names, true));
$check('ERP: no lista inactivos ni eliminados', !in_array('Trabajador Inactivo', $names, true) && !in_array('Usuario Eliminado', $names, true));
$check('ERP: misma persona con y sin tilde sale una vez', count(array_filter($names, static fn(string $n): bool => str_contains($n, 'Orellana'))) === 1);

// Selección
$s->saveOperators($oct, ['Hernán Enrique Orellana Astorga', 'Hugo Andres Ceballos Chamorro', 'Oscar Gajardo'], 'Juan F. Gutierrez', 'Prueba');
$check('selección: incluye un nombre agregado a mano', $rowOf($oct, 'Juan F. Gutierrez') !== null && count($s->rows($oct)) === 4);
$check('selección: rechaza un nombre fuera del listado', $throws(fn() => $s->saveOperators($oct, ['Persona Inventada'], '', 'Prueba'), 'listado'));

// Montos de octubre, iguales a la planilla
$s->saveAmounts($oct, [
    'Hernán Enrique Orellana Astorga' => ['recepcion_embalaje' => '15000', 'descarga_contenedor' => '45000', 'despachos' => '45000', 'inventario' => '45000'],
    'Hugo Andres Ceballos Chamorro' => ['recepcion_embalaje' => '15000', 'descarga_contenedor' => '0', 'despachos' => '45000', 'inventario' => '45000', 'residuos' => '25000'],
    'Juan F. Gutierrez' => ['mantencion_limpieza' => '37500', 'apoyo_areas' => '37500', 'modulo_despachos' => '37500', 'recepcion_proveedores' => '37500'],
    'Oscar Gajardo' => ['recepcion_embalaje' => '25000', 'descarga_contenedor' => '25000', 'despachos' => '25000', 'inventario' => '25000'],
], 'Prueba');
$check('planilla oct-26: H. Orellana $150.000', $rowOf($oct, 'Hernán Enrique Orellana Astorga')['total'] === 150000.0);
$check('planilla oct-26: A. Ceballos $130.000', $rowOf($oct, 'Hugo Andres Ceballos Chamorro')['total'] === 130000.0);
$check('planilla oct-26: F. Gutierrez $150.000', $rowOf($oct, 'Juan F. Gutierrez')['total'] === 150000.0);
$check('planilla oct-26: O. Gajardo $100.000', $rowOf($oct, 'Oscar Gajardo')['total'] === 100000.0);
$check('sin evaluar: aparece sin evaluar, con notas 10 y 100 %', !$rowOf($oct, 'Oscar Gajardo')['has_evaluation'] && $rowOf($oct, 'Oscar Gajardo')['multitarea'] === 10.0 && $rowOf($oct, 'Oscar Gajardo')['kpis']['despachos']['pct'] === 100.0);
$ceballos = $rowOf($oct, 'Hugo Andres Ceballos Chamorro');
$check('NA: un KPI vacío no aplica; 0 sí aplica y no paga', !isset($ceballos['kpis']['mantencion_limpieza']) && $ceballos['kpis']['descarga_contenedor']['paid'] === 0.0);
$check('montos: rechaza decimales y negativos', $throws(fn() => $s->saveAmounts($oct, ['Oscar Gajardo' => ['despachos' => '37.5']], 'Prueba'), 'enteros') && $throws(fn() => $s->saveAmounts($oct, ['Oscar Gajardo' => ['despachos' => '-1']], 'Prueba'), 'enteros'));
$check('montos: un error no guarda nada', $rowOf($oct, 'Oscar Gajardo')['total'] === 100000.0);

// Cumplimiento: despachos al 50 % de la respuesta acordada
$orellana = ['operator' => 'Hernán Enrique Orellana Astorga', 'proactividad' => '10', 'eficiencia' => '10', 'multitarea' => '10', 'additional' => '0', 'observations' => '',
    'kpi' => ['recepcion_embalaje' => ['pct' => '100', 'obs' => ''], 'descarga_contenedor' => ['pct' => '100', 'obs' => '04 contenedores recibidos'], 'despachos' => ['pct' => '50', 'obs' => '75 despachos'], 'inventario' => ['pct' => '100', 'obs' => '']]];
$totals = $s->saveEvaluations($oct, [$orellana], 'Prueba');
$row = $rowOf($oct, 'Hernán Enrique Orellana Astorga');
$check('cumplimiento: despachos al 50 % paga $127.500', $totals['Hernán Enrique Orellana Astorga'] === 127500.0 && $row['total'] === 127500.0 && $row['kpis']['despachos']['paid'] === 22500.0);
$check('cumplimiento: guarda la observación del KPI', $row['kpis']['despachos']['obs'] === '75 despachos' && $row['has_evaluation']);
$withNotes = $orellana;
$withNotes['multitarea'] = '5,5';
$withNotes['additional'] = '10000';
$s->saveEvaluations($oct, [$withNotes], 'Prueba');
$row = $rowOf($oct, 'Hernán Enrique Orellana Astorga');
$check('notas: una nota baja queda registrada y no cambia el pago', $row['multitarea'] === 5.5 && $row['subtotal'] === 127500.0);
$check('incentivo adicional: se suma al total', $row['total'] === 137500.0);
$bad = $orellana;
$bad['kpi']['despachos']['pct'] = '101';
$check('cumplimiento: rechaza más de 100 %', $throws(fn() => $s->saveEvaluations($oct, [$bad], 'Prueba'), 'entre 0 y 100'));
$bad = $orellana;
$bad['proactividad'] = '11';
$check('notas: rechaza más de 10', $throws(fn() => $s->saveEvaluations($oct, [$bad], 'Prueba'), 'notas'));
$check('evaluación: los errores no guardan nada', $rowOf($oct, 'Hernán Enrique Orellana Astorga')['total'] === 137500.0);
$check('evaluación: rechaza a un operador que no participa', $throws(fn() => $s->saveEvaluations($oct, [['operator' => 'Otro']], 'Prueba'), 'cambió'));

// Vigencia por mes
$nov = '2026-11';
$dec = '2026-12';
$check('vigencia: el mes siguiente hereda montos y parte sin evaluar', $rowOf($nov, 'Oscar Gajardo')['total'] === 100000.0 && !$rowOf($nov, 'Hernán Enrique Orellana Astorga')['has_evaluation'] && $rowOf($nov, 'Hernán Enrique Orellana Astorga')['total'] === 150000.0);
$s->saveAmounts($nov, ['Oscar Gajardo' => ['recepcion_embalaje' => '30000', 'descarga_contenedor' => '25000', 'despachos' => '25000', 'inventario' => '25000']], 'Prueba');
$check('vigencia: un cambio de monto rige hacia adelante', $rowOf($dec, 'Oscar Gajardo')['total'] === 105000.0);
$check('vigencia: un cambio de monto no altera meses anteriores', $rowOf($oct, 'Oscar Gajardo')['total'] === 100000.0);
$s->saveOperators($nov, ['Hernán Enrique Orellana Astorga', 'Hugo Andres Ceballos Chamorro', 'Juan F. Gutierrez'], '', 'Prueba');
$check('quitar operador: sale desde ese mes y sigue en los anteriores', $rowOf($nov, 'Oscar Gajardo') === null && $rowOf($oct, 'Oscar Gajardo') !== null);
$s->saveOperators($dec, ['Hernán Enrique Orellana Astorga', 'Oscar Gajardo'], 'juan f. gutiérrez', 'Prueba');
$check('volver a seleccionar: recupera sus montos', $rowOf($dec, 'Oscar Gajardo')['total'] === 105000.0);
$check('nombre a mano con otra escritura: no duplica a la persona', $rowOf($dec, 'Juan F. Gutierrez') !== null && $rowOf($dec, 'juan f. gutiérrez') === null);

// Historial
$history = $s->history($oct);
$check('historial: registra selección, montos y evaluación', count(array_intersect(['SELECCION', 'MONTOS', 'EVALUACION'], array_column($history, 'action'))) === 3);
$latest = null;
foreach ($history as $entry) {
    if ($entry['action'] === 'EVALUACION' && $entry['before_json'] !== null) {
        $latest = $entry;
        break;
    }
}
$labels = array_column(WarehouseBonusService::historyChanges($latest), 0);
$check('historial: muestra solo lo que cambió', in_array('Nota Multitarea', $labels, true) && in_array('Incentivo adicional', $labels, true) && !in_array('Nota Eficiencia', $labels, true));
$count = count($s->history($oct));
$s->saveEvaluations($oct, [$withNotes], 'Prueba');
$check('historial: guardar sin cambios no agrega registros', count($s->history($oct)) === $count);

// Acceso y conexión con el sistema
$module = (string)file_get_contents($root . '/src/Http/WarehouseBonusModule.php');
$shell = (string)file_get_contents($root . '/src/Views/layouts/erp_shell.php');
$check('acceso: Gabriel por id exacto del ERP', str_contains($module, 'WAREHOUSE_BONUS_WAREHOUSE_HEAD_ID = 4074'));
$check('acceso: toda ruta revisa permiso antes de abrir datos', strpos($module, 'warehouseBonusCanAccess())') < strpos($module, 'new WarehouseBonusService('));
$check('acceso: guardar exige CSRF', str_contains($module, 'requireCsrf();'));
$check('menú: solo aparece a quien tiene acceso', (bool)preg_match("/if \\(warehouseBonusCanAccess\\(\\)\\) \\{\\s+echo '<a class=\"subitem' \\. \\(\\\$isWarehouseBonus/", $shell));
$check('rutas: registradas en el sistema', str_contains((string)file_get_contents($root . '/public/index.php'), 'handleWarehouseBonusRoutes(') && str_contains((string)file_get_contents($root . '/src/bootstrap.php'), 'WarehouseBonusModule.php'));

echo $fail === 0 ? "\nBONIFICACION BODEGA OK\n" : "\nBONIFICACION BODEGA: $fail fallas\n";
exit($fail === 0 ? 0 : 1);
