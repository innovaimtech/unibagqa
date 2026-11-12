<?php
declare(strict_types=1);
// Informe mensual PPTX: balance de masa, bodega y bobinas con un ERP falso (sqlite), y una generación completa
// del PPTX con datos ficticios (si hay Python con python-pptx). No toca ninguna base real.
require_once __DIR__ . '/../../src/bootstrap.php';

$failures = 0;
function checkMr(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures++;
    }
}

$erp = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$erp->exec('CREATE TABLE equipo (id INTEGER PRIMARY KEY, equipo_type_id INTEGER)');
$erp->exec('CREATE TABLE prod_agenda (id INTEGER PRIMARY KEY, ag_equipo_id INTEGER, ag_reqid INTEGER, ag_amount REAL)');
$erp->exec('CREATE TABLE prod_worker_ot (id INTEGER PRIMARY KEY, wok_ag_id INTEGER)');
$erp->exec('CREATE TABLE prod_worker_ot_events (id INTEGER PRIMARY KEY, evt_prod_worker_otid INTEGER, evt_type TEXT, evt_amount REAL, evt_crtdat INTEGER, evt_status INTEGER, evt_comments TEXT)');
$erp->exec('CREATE TABLE prod_worker_ot_defectunits (id INTEGER PRIMARY KEY, evt_refid INTEGER, evt_type TEXT, evt_amount REAL, evt_kgstounits REAL, evt_crtdat INTEGER)');
$erp->exec('CREATE TABLE item (id INTEGER PRIMARY KEY, item_title TEXT, item_weight REAL, item_reg_kg REAL, item_reg_width REAL DEFAULT 0, item_reg_gsm REAL DEFAULT 0, item_reg_length REAL DEFAULT 0)');
$erp->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, req_cust_id INTEGER)');
$erp->exec('CREATE TABLE orders_items (req_id INTEGER, item_pos INTEGER, item_id INTEGER, item_amount REAL, fab_med_width REAL, fab_med_height REAL, fab_med_fuelle REAL, fab_mat_gramms REAL, fab_manilla_length REAL)');
$erp->exec('CREATE TABLE customer (id INTEGER PRIMARY KEY, cust_name TEXT)');
$erp->exec('CREATE TABLE item_shops_storehouses (item_id INTEGER, shop_id INTEGER, st_id INTEGER, iss_inventory REAL, iss_order_id INTEGER)');
$erp->exec('CREATE TABLE tran_data (tran_id INTEGER, item_id INTEGER, tran_st_id INTEGER, tran_type TEXT, tran_amount REAL, tran_crtdat INTEGER)');

$t0 = strtotime('2026-09-26 00:00:00');
$t1 = strtotime('2026-10-25 23:59:59');
$in = strtotime('2026-10-10 10:00:00');
$before = strtotime('2026-09-20 10:00:00');

// Máquinas: 1 flexo (7), 2 selladora (8), 3 embalaje (15).
$erp->exec('INSERT INTO equipo VALUES (1,7),(2,8),(3,15)');
// CC 100: bolsa con peso de artículo 40 g, pedido 10.000. CC 200: sin peso de artículo, por fórmula.
$erp->exec("INSERT INTO item (id, item_title, item_weight, item_reg_kg) VALUES (10,'Bolsa A',40,0),(20,'Bolsa B',0,0)");
$erp->exec('INSERT INTO orders_items VALUES (100,1,10,10000,0,0,0,0,0),(100,2,10,99999,0,0,0,0,0),(200,1,20,4000,30,40,10,70,0)');
// CC 100 en selladora: 6.000 antes del período y 4.000 dentro → programado dentro = 10.000 × 4.000/10.000.
$erp->exec('INSERT INTO prod_agenda VALUES (1,2,100,10000),(2,2,200,4000),(3,1,100,10000),(4,3,100,10000)');
$erp->exec('INSERT INTO prod_worker_ot VALUES (1,1),(2,2),(3,3),(4,4)');
$erp->exec("INSERT INTO prod_worker_ot_events VALUES
    (1,1,'prod',6000,$before,1,NULL),
    (2,1,'prod',4000,$in,1,NULL),
    (3,2,'prod',4000,$in,1,NULL),
    (4,2,'prod',999,$in,1,'ROLL-SE-1 Bobina Semielaborada'),
    (5,3,'prod',10000,$in,1,NULL),
    (6,4,'prod',10000,$in,0,NULL),
    (7,1,'apertura',0,$in,1,NULL)");
// Merma: selladora 2 kg; embalaje sin kg, 50 unidades (× 40 g = 2 kg).
$erp->exec("INSERT INTO prod_worker_ot_defectunits VALUES (1,2,'merma',10,2,$in),(2,6,'merma',50,0,$in),(3,2,'repair',5,9,$in)");

$rs = (new ReflectionClass(ReceptionService::class))->newInstanceWithoutConstructor();
$svc = new MonthlyPresentationService($rs, $erp);

// Peso por fórmula: 2 × (30 + 10) × 40 / 10000 = 0,32 m² × 70 g/m² = 0,0224 kg.
checkMr('peso por fórmula de la ficha', abs(MonthlyPresentationService::unitWeightKg(['fab_med_width' => 30, 'fab_med_height' => 40, 'fab_med_fuelle' => 10, 'fab_mat_gramms' => 70]) - 0.0224) < 1e-9);
checkMr('peso del artículo tiene prioridad', abs(MonthlyPresentationService::unitWeightKg(['item_weight' => 40, 'fab_med_width' => 30]) - 0.04) < 1e-9);

$b = $svc->computeProcessBalance($t0, $t1);
// Selladora: procesados 4.000 × 0,04 + 4.000 × 0,0224 = 160 + 89,6 = 249,6; programados 10.000 × 0,4 × 0,04 + 4.000 × 1 × 0,0224 = 249,6.
checkMr('CyS procesados = unidades × peso (sin bobina semielaborada) (' . $b['cys']['proc'] . ')', abs($b['cys']['proc'] - 249.6) < 0.01);
checkMr('CyS programados reparte lo pedido según la producción del mes (' . $b['cys']['prog'] . ')', abs($b['cys']['prog'] - 249.6) < 0.01);
checkMr('CyS merma solo «merma», no «repair» (' . $b['cys']['waste'] . ')', abs($b['cys']['waste'] - 2.0) < 0.001);
checkMr('flexo: 10.000 × 0,04 = 400 kg', abs($b['flexo']['proc'] - 400.0) < 0.01 && abs($b['flexo']['prog'] - 400.0) < 0.01);
checkMr('embalaje: evento anulado no cuenta; merma sin kg = unidades × peso', $b['emb']['proc'] == 0.0 && abs($b['emb']['waste'] - 2.0) < 0.001);
checkMr('seri sin datos queda en 0', $b['seri'] === ['prog' => 0.0, 'proc' => 0.0, 'waste' => 0.0]);

// Bobinas: PLA en bodega 5, PP en 11. Stock de hoy y kardex posterior al cierre.
$erp->exec("INSERT INTO item (id, item_title, item_weight, item_reg_kg) VALUES (30,'PLA/BEIGE/Y26/100X70X1650',0,100),(31,'PLA/BLANCO/W80/100X70X1650',0,120),(32,'PP/ROJO/R01/100X80X1100',0,500),(33,'PRODUCTO GENERICO',0,0)");
$erp->exec('INSERT INTO item_shops_storehouses VALUES (30,30010,5,3,0),(31,30010,5,2,0),(32,30010,11,4,0),(33,30010,5,-100,0)');
$cut = strtotime('2026-10-25 23:59:59');
$after = strtotime('2026-11-02 10:00:00');
// Después del cierre: salió 1 bobina beige (sthdown) y entraron 2 PP (stockchangeup) → al cierre había 4 beige y 2 PP.
$erp->exec("INSERT INTO tran_data VALUES (1,30,5,'sthdown',1,$after),(2,32,11,'stockchangeup',2,$after),(3,31,5,'sthdown',5,$before)");
$rolls = $svc->computeRollStock(['2026-10' => $cut]);
$r = $rolls['2026-10'];
checkMr('bobinas al cierre reconstruidas con el kardex (' . json_encode($r) . ')',
    $r['pla_beige'] == 400.0 && $r['pla_blanco'] == 240.0 && $r['pla'] == 640.0 && $r['pp'] == 1000.0);

// Producto terminado: bodegas 1, 14 y 10; cliente = el que más lo pidió.
$erp->exec("INSERT INTO item (id, item_title, item_weight, item_reg_kg) VALUES (40,'Bolsa Walmart',0,0),(41,'Bolsa Tottus',0,0),(42,'Bolsa sin pedidos',0,0)");
$erp->exec('INSERT INTO item_shops_storehouses VALUES (40,30010,14,1000,0),(40,30010,1,500,0),(41,30010,10,300,0),(42,30010,14,50,0)');
$erp->exec("INSERT INTO customer VALUES (1,'Walmart Chile S.A.'),(2,'Hipermercados Tottus S.A.'),(3,'Otro')");
$erp->exec('INSERT INTO orders VALUES (501,1),(502,1),(503,3),(504,2)');
$erp->exec('INSERT INTO orders_items (req_id,item_pos,item_id,item_amount) VALUES (501,1,40,1),(502,1,40,1),(503,1,40,1),(504,1,41,1)');
// El artículo 40 tuvo una salida 20 días antes del cierre; el 41 hace 200 días.
$erp->exec("INSERT INTO tran_data VALUES (10,40,14,'ordersdelivery',10," . ($cut - 20 * 86400) . "),(11,41,10,'ordersdelivery',10," . ($cut - 200 * 86400) . ')');
$fg = $svc->computeFinishedGoods($cut);
checkMr('stock por cliente con nombre corto (' . json_encode($fg['by_customer'], JSON_UNESCAPED_UNICODE) . ')',
    ($fg['by_customer']['Walmart'] ?? 0) == 1500 && ($fg['by_customer']['Tottus'] ?? 0) == 300 && ($fg['by_customer']['Sin cliente'] ?? 0) == 50);
checkMr('rotación: con salidas en 90 días vs sin rotación', $fg['total'] == 1850 && $fg['moved'] == 1500 && $fg['still'] == 350);
checkMr('nombre corto sin razón social', MonthlyPresentationService::shortCustomerName('Cadena Comercial OXXO Chile S.A.') === 'OXXO');

// Ciclo en curso de la diapositiva 3 (igual al período por defecto del panel).
$cyc = static fn(string $d) => array_map(static fn($x) => $x->format('Y-m-d H:i'), MonthlyPresentationService::currentCycle(new DateTimeImmutable($d)));
checkMr('mes en curso: 11/11 → 01/11 a 30/11', $cyc('2026-11-11') === ['2026-11-01 00:00', '2026-11-30 23:59']);
checkMr('mes en curso: el 26 sigue en el mismo mes; el 31 cierra diciembre', $cyc('2026-11-26') === ['2026-11-01 00:00', '2026-11-30 23:59']
    && $cyc('2026-12-31') === ['2026-12-01 00:00', '2026-12-31 23:59']);
checkMr('mes en curso: febrero 28 (y 29 en bisiesto)', $cyc('2027-02-10') === ['2027-02-01 00:00', '2027-02-28 23:59'] && $cyc('2028-02-10') === ['2028-02-01 00:00', '2028-02-29 23:59']);
// Período del informe: mes calendario, del 1 al último día.
checkMr('período: octubre 1 a 31, noviembre 1 a 30', MonthlyPresentationService::monthBounds('2026-10') === ['2026-10-01', '2026-10-31']
    && MonthlyPresentationService::monthBounds('2026-11') === ['2026-11-01', '2026-11-30']);
checkMr('período: febrero 1 a 28 (29 en 2028)', MonthlyPresentationService::monthBounds('2027-02') === ['2027-02-01', '2027-02-28']
    && MonthlyPresentationService::monthBounds('2028-02') === ['2028-02-01', '2028-02-29']);
$_GET = [];
checkMr('pantalla: sin mes elegido usa el mes actual (aunque sea día 26 o más)', unibagMonthlyPresentationMonthKey() === date('Y-m'));

// Generación completa con datos ficticios.
$py = getenv('PYTHON_BIN') ?: (is_file('C:/Users/Axiliarmu/AppData/Local/Programs/Python/Python313/python.exe') ? 'C:/Users/Axiliarmu/AppData/Local/Programs/Python/Python313/python.exe' : 'python3');
$template = __DIR__ . '/../../data/plantilla_informe_mensual.pptx';
$hasPptx = is_file($template) && trim((string)@shell_exec('"' . $py . '" -c "import pptx; print(1)" 2>&1')) === '1';
if (!$hasPptx) {
    echo "SKIP generación PPTX (sin Python con python-pptx o sin plantilla)\n";
} else {
    $hist = [];
    foreach (range(1, 10) as $m) {
        $proc = ['prog' => 1000 * $m, 'proc' => 1010 * $m, 'waste' => 9 * $m];
        $hist[] = ['month_key' => sprintf('2026-%02d', $m), 'origen' => $m < 10 ? 'plantilla' : 'calculado', 'prod_units' => 900000 + $m, 'prod_cap' => 1345000,
            'sl_total_units' => 1000000, 'sl_delayed_units' => 10000, 'sl_total_cc' => 90, 'sl_delayed_cc' => 5, 'waste_pct' => 1.1,
            'procesos' => ['flexo' => $proc, 'seri' => $proc, 'cys' => $proc, 'emb' => $proc]];
    }
    $rollsData = [];
    for ($i = 12; $i >= 0; $i--) {
        $rollsData[] = ['month_key' => date('Y-m', strtotime("2026-10-01 -$i months")), 'pp' => 1000 + $i, 'pla' => 2000 + $i, 'pla_beige' => 500, 'pla_blanco' => 1500,
            'pp_bolsas' => 741255 + $i, 'pla_bolsas' => 2196811 + $i, 'pla_beige_bolsas' => 52241, 'pla_blanco_bolsas' => 1757326];
    }
    $data = ['month_key' => '2026-10', 'start_date' => '2026-09-26 00:00:00', 'end_date' => '2026-10-25 23:59:59', 'prod_meta' => 1000000,
        'issued_at' => '10-11-2026', 'stock_date' => '25-10-2026', 'history' => $hist, 'rolls' => $rollsData,
        // Informe de octubre (cerrado) descargado el 11/11: la diapositiva 3 muestra el mes en curso 01/11 → 30/11.
        'projection' => ['made' => 400000, 'queue' => 600000, 'projected' => 1000000, 'closed' => false, 'start' => '2026-11-01', 'end' => '2026-11-30',
            'today' => '11/11/2026', 'elapsed_days' => 10, 'total_days' => 21, 'remaining_days' => 11, 'daily_avg' => 40000],
        'finished_goods' => ['total' => 1850, 'moved' => 1500, 'still' => 350, 'by_customer' => ['Walmart' => 1500, 'Tottus' => 300, 'Sin cliente' => 50], 'still_by_customer' => ['Sin cliente' => 50, 'Tottus' => 300]],
        'delayed_orders' => [['cc_number' => '26-00001-1', 'customer_name' => 'Cliente X', 'product_type' => 'BOL0001', 'units' => 1234, 'required_date' => '01/10/2026', 'delay_days' => 3]],
        // Disponibilidad de mantención: 22 días × 8:24, colación 11:00, preventiva 8 h (Selladora I: 4 h).
        'month_closed' => true,
        'maintenance' => ['days' => 22, 'daily' => 30240, 'lunch' => 39600,
            'preventive_hours' => ['8' => ['FLEXO II', 'SERI II', 'SELLADORA II'], '4' => ['SELLADORA I']],
            'inactive' => ['flexo' => ['FLEXO I'], 'seri' => ['SERI I', 'SERI III'], 'sellado' => ['SELLADORA III']],
            'groups' => [
                'flexo' => [['name' => 'FLEXO II', 'total' => 665280, 'available' => 596880, 'preventive' => 28800, 'breakdown' => 2901, 'setup' => 291875,
                    'external' => 12885, 'gross' => 289219, 'maintenance' => 593979, 'real' => 99.51, 'target' => 95, 'meets' => true, 'production' => 272792, 'utilization' => 45.7]],
                'seri' => [['name' => 'SERI II', 'total' => 665280, 'available' => 596880, 'preventive' => 28800, 'breakdown' => 0, 'setup' => 288628,
                    'external' => 12480, 'gross' => 295772, 'maintenance' => 596880, 'real' => 100.0, 'target' => 95, 'meets' => true, 'production' => 246725, 'utilization' => 41.3]],
                'sellado' => [
                    ['name' => 'SELLADORA I', 'total' => 665280, 'available' => 611280, 'preventive' => 14400, 'breakdown' => 10771, 'setup' => 46354,
                        'external' => 0, 'gross' => 554155, 'maintenance' => 600509, 'real' => 98.24, 'target' => 85, 'meets' => true, 'production' => 488944, 'utilization' => 80.0],
                    ['name' => 'SELLADORA II', 'total' => 665280, 'available' => 596880, 'preventive' => 28800, 'breakdown' => 40000, 'setup' => 13598,
                        'external' => 4880, 'gross' => 538402, 'maintenance' => 556880, 'real' => 93.3, 'target' => 95, 'meets' => false, 'production' => 589877, 'utilization' => 98.8]]]]];
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qa_monthly_' . uniqid();
    file_put_contents($tmp . '.json', json_encode($data, JSON_UNESCAPED_UNICODE));
    $out = $tmp . '.pptx';
    $cmd = '"' . $py . '" "' . __DIR__ . '/../generate_monthly_pptx.py" --template="' . $template . '" --month=2026-10 --data="' . $tmp . '.json" --output="' . $out . '" 2>&1';
    exec($cmd, $lines, $rc);
    checkMr('el generador termina sin error (' . trim(implode(' ', $lines)) . ')', $rc === 0 && is_file($out));
    if (is_file($out)) {
        $check = <<<'PY'
import sys, json
from pptx import Presentation
p = Presentation(sys.argv[1])
res = {'n': len(p.slides)}
def txt(s):
    return ' | '.join(sh.text_frame.text for sh in s.shapes if sh.has_text_frame)
res['portada'] = txt(p.slides[0])
res['layout'] = [sh.text_frame.text for l in p.slide_layouts for sh in l.shapes if sh.has_text_frame and 'REPORTE' in sh.text_frame.text.upper()]
t = [sh for sh in p.slides[1].shapes if sh.has_table][0].table
res['prod'] = [t.cell(0, 2).text, t.cell(4, 2).text]
charts = [sh for sh in p.slides[6].shapes if getattr(sh, 'has_chart', False) and sh.has_chart]
res['merma_series'] = [list(s.values) for s in charts[0].chart.plots[0].series] if charts else []
res['bad_dash'] = sum(1 for s in p.slides for sh in s.shapes if getattr(sh, 'has_chart', False) and sh.has_chart and ',' in sh.chart._chartSpace.xml.split('prstDash val="')[-1][:30] and 'prstDash val="solid,' in sh.chart._chartSpace.xml)
res['atrasados_rows'] = len([sh for sh in p.slides[5].shapes if sh.has_table][0].table.rows)
pt = [sh for sh in p.slides[2].shapes if sh.has_table][0].table
res['proy'] = [[pt.cell(r, 1).text, pt.cell(r, 2).text] for r in range(1, 4)]
res['proy_txt'] = txt(p.slides[2])
res['titles'] = [next((sh.text_frame.text for sh in sl.shapes if sh.is_placeholder and sh.has_text_frame and sh.text_frame.text.strip()), '') for sl in p.slides]
def mant(i):
    sl = p.slides[i]
    t = [sh for sh in sl.shapes if sh.has_table][0].table
    boxes = {sh.name: [pa.text for pa in sh.text_frame.paragraphs] for sh in sl.shapes if sh.has_text_frame and sh.name.startswith('Utiliz')}
    nota = [sh.text_frame.text for sh in sl.shapes if sh.has_text_frame and sh.name.startswith('Nota')]
    return {'rows': [[c.text for c in r.cells] for r in t.rows], 'boxes': boxes, 'nota': nota}
def by_title(start):
    return next(sl for sl in p.slides if any(sh.is_placeholder and sh.has_text_frame and sh.text_frame.text.strip().startswith(start) for sh in sl.shapes))
bt = [sh for sh in by_title('BODEGA – BOBINAS PLANTA').shapes if sh.has_table][0].table
res['bob'] = [c.text for c in bt.rows[0].cells] + [c.text for c in bt.rows[len(bt.rows) - 1].cells]
bt = [sh for sh in by_title('BODEGA – BOBINAS PLA:').shapes if sh.has_table][0].table
res['bob_pla'] = [c.text for c in bt.rows[len(bt.rows) - 1].cells]
res['mant'] = [mant(i) for i, x in enumerate(res['titles']) if x.upper().startswith('MANTENCIÓN – DISPONIBILIDAD')]
print(json.dumps(res))
PY;
        file_put_contents($tmp . '_check.py', $check);
        $raw = (string)shell_exec('"' . $py . '" "' . $tmp . '_check.py" "' . $out . '" 2>&1');
        $json = json_decode($raw, true) ?: [];
        if (!$json) {
            echo "Salida de la revisión del PPTX:
" . $raw . "
";
        }
        checkMr('22 diapositivas (18 + mantención: divisoria y 3 de disponibilidad)', ($json['n'] ?? 0) === 22, json_encode($json['titles'] ?? null, JSON_UNESCAPED_UNICODE));
        $titles = (array)($json['titles'] ?? []);
        checkMr('mantención va después de mermas y antes de bodega', array_search('MANTENCIÓN.', $titles, true) === 11
            && str_starts_with((string)($titles[15] ?? ''), 'BODEGA DE MATERIAS'), json_encode($titles, JSON_UNESCAPED_UNICODE));
        [$mf, $ms, $mse] = array_pad((array)($json['mant'] ?? []), 3, []);
        checkMr('flexo: fila con horas H:MM, % real, esperado y cumple', ($mf['rows'][1] ?? [])
            === ['Flexo II', '184:48', '165:48', '0:48', '81:05', '3:35', '80:20', '165:00', '99,51%', '95%', 'Cumple'],
            json_encode($mf['rows'] ?? null, JSON_UNESCAPED_UNICODE));
        checkMr('flexo: una sola fila de máquina (Flexo I sin registros no va en la tabla)', count($mf['rows'] ?? []) === 2);
        checkMr('flexo: utilización en el cuadro de la imagen y Flexo III sin registros', ($mf['boxes']['Utilización FLEXO 2 Utilización: 59%'] ?? []) === ['FLEXO 2', 'Utilización: 46%']
            && ($mf['boxes']['Utilización FLEXO 3 Utilización: 44%'] ?? []) === ['FLEXO 3', 'Sin registros'], json_encode($mf['boxes'] ?? null, JSON_UNESCAPED_UNICODE));
        $nota = implode(' ', (array)($mf['nota'] ?? []));
        checkMr('flexo: base de cálculo con 22 días, 8:24 h, colación 11:00 y preventiva 8:00', str_contains($nota, '22 días disponibles · 8:24 h programadas por día · colación de 11:00 h · mantención preventiva programada de 8:00 h')
            && str_contains($nota, 'Sin programación en el período: Flexo I.'), $nota);
        checkMr('seri: Seri I sin programación en su cuadro y en la nota', ($ms['boxes']['Utilización SERI 1 Utilización: 0%'] ?? [])[1] === 'Sin programación'
            && str_contains(implode(' ', (array)($ms['nota'] ?? [])), 'Sin programación en el período: Seri I, Seri III.'), json_encode($ms, JSON_UNESCAPED_UNICODE));
        checkMr('sellado: 2 máquinas, no cumple, y preventiva de 4 h de Selladora I en la nota', count($mse['rows'] ?? []) === 3
            && ($mse['rows'][2][8] ?? '') === '93,30%' && str_contains(implode(' ', (array)($mse['nota'] ?? [])), 'Selladora I: mantención preventiva de 4:00 h.'),
            json_encode($mse, JSON_UNESCAPED_UNICODE));
        checkMr('bobinas PP y PLA en bolsas proyectadas (oct-26: 741.255 / 2.196.811)', ($json['bob'] ?? []) === ['Mes', 'Stock PP', 'Stock PLA', 'Total', 'oct-26', '741.255', '2.196.811', '2.938.066'], json_encode($json['bob'] ?? null, JSON_UNESCAPED_UNICODE));
        checkMr('bobinas PLA beige y blanco en bolsas proyectadas', ($json['bob_pla'] ?? []) === ['oct-26', '52.241', '1.757.326', '1.809.567'], json_encode($json['bob_pla'] ?? null, JSON_UNESCAPED_UNICODE));
        checkMr('portada con el mes', str_contains((string)($json['portada'] ?? ''), 'OCTUBRE 2026'));
        checkMr('encabezado del diseño con el mes', in_array('REPORTE DE OPERACIONES OCTUBRE 2026', (array)($json['layout'] ?? []), true));
        checkMr('tabla de producción con oct-26 y 900.010', ($json['prod'] ?? []) === ['oct-26', '900.010']);
        $ms = (array)($json['merma_series'] ?? []);
        checkMr('gráfico de merma flexo: 10 meses + Prom', count($ms) === 2 && count($ms[0]) === 11 && (float)$ms[0][9] === 10000.0);
        checkMr('sin estilos de línea inválidos', ($json['bad_dash'] ?? 1) === 0);
        checkMr('atrasados: 1 pedido + encabezado + total', ($json['atrasados_rows'] ?? 0) === 3);
        $proy = (array)($json['proy'] ?? []);
        checkMr('proyección: fabricado del 1 a hoy, lo que falta y el cierre proyectado (mes en curso)', array_column($proy, 0) === ['400.000', '600.000', '1.000.000']
            && str_contains((string)($proy[0][1] ?? ''), 'del 01/11/2026 al 11/11/2026') && str_contains((string)($proy[2][1] ?? ''), '30/11/2026'), json_encode($proy, JSON_UNESCAPED_UNICODE));
        checkMr('proyección: tarjeta con el cierre proyectado y % ya fabricado', str_contains((string)($json['proy_txt'] ?? ''), 'unidades de cierre proyectado al 30/11/2026')
            && str_contains((string)($json['proy_txt'] ?? ''), '40%'), (string)($json['proy_txt'] ?? ''));
        // Mismo informe pedido por rango de fechas: portada, encabezado y columna del período con el rango.
        $hist[9]['label'] = '01/10–31/10';
        $data['history'] = $hist;
        $data['range'] = ['from' => '2026-10-01', 'to' => '2026-10-31'];
        $data['period_title'] = 'del 01/10/2026 al 31/10/2026';
        $data['column_label'] = '01/10–31/10';
        file_put_contents($tmp . '.json', json_encode($data, JSON_UNESCAPED_UNICODE));
        @unlink($out);
        $lines = [];
        exec($cmd, $lines, $rc);
        $raw = (string)shell_exec('"' . $py . '" "' . $tmp . '_check.py" "' . $out . '" 2>&1');
        $json = json_decode($raw, true) ?: [];
        if (!$json) {
            echo "Salida de la revisión del PPTX:
" . $raw . "
";
        }
        checkMr('rango: portada con «DEL 01/10/2026 AL 31/10/2026»', $rc === 0 && str_contains((string)($json['portada'] ?? ''), 'DEL 01/10/2026 AL 31/10/2026'));
        checkMr('rango: encabezado del diseño con el rango', in_array('REPORTE DE OPERACIONES DEL 01/10/2026 AL 31/10/2026', (array)($json['layout'] ?? []), true));
        checkMr('rango: columna del período «01/10–31/10» en la tabla de producción', ($json['prod'] ?? []) === ['01/10–31/10', '900.010']);
        // Vista previa en HTML del PPTX generado (lo que se ve al apretar una lámina).
        $pv = $tmp . '_preview.html';
        exec('"' . $py . '" "' . __DIR__ . '/../pptx_preview.py" "' . $out . '" "' . $pv . '" 2>&1', $pvLines, $pvRc);
        $pvHtml = is_file($pv) ? (string)file_get_contents($pv) : '';
        preg_match_all('/class="(im[0-9a-f]{12}) im"/', $pvHtml, $imgs);
        checkMr('vista previa: 22 láminas con el período, tablas, gráficos e imágenes (' . trim(implode(' ', $pvLines)) . ')', $pvRc === 0
            && substr_count($pvHtml, '<section class="pg"') === 22 && str_contains($pvHtml, 'REPORTE DE OPERACIONES DEL 01/10/2026 AL 31/10/2026')
            && str_contains($pvHtml, '01/10–31/10') && str_contains($pvHtml, '<table class="tb">') && str_contains($pvHtml, '<svg viewBox')
            && $imgs[1] !== [] && array_diff(array_unique($imgs[1]), array_map(static fn($c) => $c, array_filter(array_unique($imgs[1]), static fn($c) => str_contains($pvHtml, '.' . $c . '{background-image:url(data:image/')))) === []);
        @unlink($pv);
        @unlink($tmp . '_check.py');
        @unlink($out);
    }
    @unlink($tmp . '.json');
}

// Rango de fechas del informe.
checkMr('rango válido', MonthlyPresentationService::normalizeRange('2026-10-01', '2026-10-31') === ['from' => '2026-10-01', 'to' => '2026-10-31']);
checkMr('rango de un día', MonthlyPresentationService::normalizeRange('2026-10-05', '2026-10-05') !== null);
checkMr('rango inválido: desde > hasta, fecha imposible, vacío o más de 1 año', MonthlyPresentationService::normalizeRange('2026-10-31', '2026-10-01') === null
    && MonthlyPresentationService::normalizeRange('2026-02-30', '2026-03-10') === null && MonthlyPresentationService::normalizeRange('', '2026-03-10') === null
    && MonthlyPresentationService::normalizeRange('2025-01-01', '2026-06-01') === null);
$_GET = ['modo' => 'rango', 'desde' => '2026-10-01', 'hasta' => '2026-10-31'];
checkMr('pantalla: lee modo=rango, desde y hasta', unibagMonthlyPresentationRange() === ['from' => '2026-10-01', 'to' => '2026-10-31']);
$_GET = ['month' => '2026-10', 'desde' => '2026-10-01', 'hasta' => '2026-10-31'];
checkMr('pantalla: sin modo=rango se usa el mes operativo', unibagMonthlyPresentationRange() === null);
$_GET = [];

// Peso por bobina: se corrigen pesos imposibles del ERP (p. ej. 11440 por 114,40).
$w = static fn(float $kg, float $wd, float $gsm, float $len): float => MonthlyPresentationService::rollWeightKg(['item_reg_kg' => $kg, 'item_reg_width' => $wd, 'item_reg_gsm' => $gsm, 'item_reg_length' => $len]);
checkMr('bobina: peso normal del ERP', abs($w(88, 100, 80, 1100) - 88) < 0.01);
checkMr('bobina: peso x100 (11440) usa la ficha 130x80x1100 = 114,4', abs($w(11440, 130, 80, 1100) - 114.4) < 0.01);
checkMr('bobina: ficha mal (ancho 13) pero peso bueno se respeta', abs($w(114.4, 13, 80, 1100) - 114.4) < 0.01);
checkMr('bobina: sin peso usa la ficha', abs($w(0, 108, 90, 1350) - 131.22) < 0.01);
checkMr('bobina: peso x100 sin ficha se divide por 100', abs($w(9350, 0, 0, 0) - 93.5) < 0.01);

// Bolsas proyectadas por bobina (Libro1): largo ÷ metros por bolsa, promedio de 9 formatos.
$b = static fn(string $t, float $wd = 0, float $len = 0): float => MonthlyPresentationService::rollBags(['item_title' => $t, 'item_reg_width' => $wd, 'item_reg_length' => $len]);
checkMr('bolsas: 13 bobinas PLA 100X55X2300 = 65.630,8 (fila del Libro1)', abs(13 * $b('PLA/BLANCO/W80/100X55X2300') - 65630.822) < 0.01);
checkMr('bolsas: 483 bobinas 100X80X1500 = 1.590.285 (fila del Libro1)', abs(483 * $b('PLA/BLANCO/W80/100X80X1500') - 1590285.305) < 0.01);
checkMr('bolsas: PP 130 de ancho cuenta como una bobina (Libro1)', abs($b('PP/AMARILLO/Y20/130X80X1100') - $b('PP/NEGRO/C90/100X80X1100')) < 0.001);
checkMr('bolsas: manillas (6 cm) no cuentan', $b('PLA/BLANCO/W80/6X85X1400') === 0.0);
checkMr('bolsas: 51 cm cuenta en proporción (media bobina en el Libro1)', abs($b('PLA/BLANCO/W80/51X80X1446') - 0.51 * 1446 * 2.1950009) < 0.01);
checkMr('bolsas: el título manda sobre una ficha con ancho mal (13)', abs($b('PP/NARANJO/Y23/130X80X1100', 13, 1100) - $b('PP/NEGRO/C90/100X80X1100')) < 0.001);

exit($failures > 0 ? 1 : 0);
