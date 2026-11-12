<?php

declare(strict_types=1);

// Nivel de servicio: días de atraso por día calendario (sin importar la hora). No usa ninguna base de datos.
require_once __DIR__ . '/../../src/bootstrap.php';

$failures = 0;
function checkSl(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures++;
    }
}

date_default_timezone_set('America/Santiago');
$d = static fn(string $a, string $b): int => ReceptionReportingService::calendarDaysBetween((int)strtotime($a), (int)strtotime($b));

checkSl('NV0002524: pactada 03-11 12:00, registro 10-11 00:00 → 7 días', $d('2026-11-03 12:00:00', '2026-11-10 00:00:00') === 7);
checkSl('mismo día, más tarde → 0 días (a tiempo)', $d('2026-11-03 12:00:00', '2026-11-03 18:30:00') === 0);
checkSl('día siguiente antes de la hora pactada → 1 día (antes quedaba a tiempo)', $d('2026-11-03 12:00:00', '2026-11-04 09:00:00') === 1);
checkSl('despacho antes de lo pactado → negativo', $d('2026-11-10 00:00:00', '2026-11-08 15:00:00') === -2);
checkSl('cruce de cambio de hora (sep 2026) → días exactos', $d('2026-09-05 00:00:00', '2026-09-08 00:00:00') === 3);

// Fecha pactada desde el texto del pedido (orders.req_despacho_desc).
$order = (int)strtotime('2026-10-29 10:03:09'); // jueves, como NV0002524
$p = static fn(string $t, int $o = 0): string => ($ts = ReceptionReportingService::parseAgreedDispatchDate($t, $o ?: $order)) === null ? 'null' : date('Y-m-d', $ts);
checkSl('«03-11» (NV0002524) → 2026-11-03', $p('03-11') === '2026-11-03');
checkSl('«19-11-2026» y «02/09/2026»', $p('19-11-2026') === '2026-11-19' && $p('02/09/2026') === '2026-09-02');
checkSl('«DESPACHO 02-12-2026» y «19-10-2026 (ó antes si se peude)»', $p('DESPACHO 02-12-2026') === '2026-12-02' && $p('19-10-2026 (ó antes si se peude)') === '2026-10-19');
checkSl('«01 octubre», «1 de Octubre», «11-Agosto», «04 noviembre»', $p('01 octubre', (int)strtotime('2026-09-20')) === '2026-10-01'
    && $p('1 de Octubre', (int)strtotime('2026-09-20')) === '2026-10-01' && $p('11-Agosto', (int)strtotime('2026-07-20')) === '2026-08-11' && $p('04 noviembre') === '2026-11-04');
checkSl('«10 días hábiles» desde jueves 29-10 → jueves 12-11 (lunes a viernes)', $p('10 dias hábiles') === '2026-11-12' && $p('10 DIAS HÁBILES') === '2026-11-12');
checkSl('«10 ds, habiles», «3 ds. habiles», «10/12 ds, habiles»', $p('10 ds, habiles') === '2026-11-12' && $p('3 ds. habiles') === '2026-11-03' && $p('10/12 ds, habiles') === '2026-11-12');
checkSl('sin año en diciembre → enero del año siguiente', $p('05-01', (int)strtotime('2026-12-20')) === '2027-01-05');
checkSl('«2.500 Unid. 04-Set / 2.500 unid. 11-Set» → primera fecha 04-09', $p('2.500 Unid. 04-Set / 2.500 unid. 11-Set', (int)strtotime('2026-08-20')) === '2026-09-04');
checkSl('no se entiende → null: vacío, «NOVIEMBRE», «AÑO 2026», «90 dias una vez VB°», fecha imposible', $p('') === 'null' && $p('NOVIEMBRE') === 'null'
    && $p('AÑO 2026') === 'null' && $p('90 dias una vez VB°') === 'null' && $p('31-02-2026') === 'null');

exit($failures > 0 ? 1 : 0);
