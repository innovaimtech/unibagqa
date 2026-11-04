<?php
declare(strict_types=1);
// Multiplicador de producción 120% del Bono Corte y Sellado: configuración y cálculo (sqlite en memoria).
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
require_once dirname(__DIR__, 2) . '/src/BonusConfigurationService.php';

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $fail++;
    }
};
$throws = function (callable $fn, string $needle = '') {
    try {
        $fn();
    } catch (Throwable $e) {
        return $needle === '' || stripos($e->getMessage(), $needle) !== false;
    }
    return false;
};
$near = fn(float $a, float $b): bool => abs($a - $b) < 0.005;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$svc = new BonusConfigurationService($pdo);
$M = '2026-11';
$D = '2026-11-10';

// ---- configuración
$check('sin configuración: factor 1 y sin extra', $svc->factor('bonocys', 'Ana', $D, $M) === 1.0 && $svc->productionBonusRate('bonocys', 'Ana', $D, $M) === 0.0);
$svc->savePolicy('bonocys', $M, 'percentage', ['Ana' => '120', 'Beto' => '90', 'Carla' => '100', 'Dani' => '80'], 'tester');
$check('120%: el pago base no se multiplica (factor 1,0)', $svc->factor('bonocys', 'Ana', $D, $M) === 1.0);
$check('120%: extra = 0,20', $near($svc->productionBonusRate('bonocys', 'Ana', $D, $M), 0.20));
$check('90% sigue igual que hoy (factor 0,9 y sin extra)', $near($svc->factor('bonocys', 'Beto', $D, $M), 0.9) && $svc->productionBonusRate('bonocys', 'Beto', $D, $M) === 0.0);
$check('80% sigue igual que hoy', $near($svc->factor('bonocys', 'Dani', $D, $M), 0.8) && $svc->productionBonusRate('bonocys', 'Dani', $D, $M) === 0.0);
$check('100%: factor 1 y sin extra', $svc->factor('bonocys', 'Carla', $D, $M) === 1.0 && $svc->productionBonusRate('bonocys', 'Carla', $D, $M) === 0.0);
$check('operador sin configurar: factor 1 y sin extra', $svc->factor('bonocys', 'Eva', $D, $M) === 1.0 && $svc->productionBonusRate('bonocys', 'Eva', $D, $M) === 0.0);
$check('Seri rechaza 120%', $throws(fn() => $svc->savePolicy('bonoseri', $M, 'percentage', ['Ana' => '120'], 'tester'), '80%, 90% o 100%'));
$check('CYS rechaza otros valores', $throws(fn() => $svc->savePolicy('bonocys', $M, 'percentage', ['Ana' => '110'], 'tester'), '120%'));
$svc->savePolicy('bonoseri', $M, 'percentage', ['Ana' => '90'], 'tester');
$check('Seri no cambia (90% sigue multiplicando)', $near($svc->factor('bonoseri', 'Ana', $D, $M), 0.9) && $svc->productionBonusRate('bonoseri', 'Ana', $D, $M) === 0.0);
$check('modalidad por unidad ignora los porcentajes', (function () use ($svc, $M, $D) {
    $svc->savePolicy('bonocys', '2026-12', 'unit', ['Ana' => '120'], 'tester');
    return $svc->factor('bonocys', 'Ana', '2026-12-10', '2026-12') === 1.0 && $svc->productionBonusRate('bonocys', 'Ana', '2026-12-10', '2026-12') === 0.0;
})());
$check('el período 26→25 usa la política del mes de término', $near($svc->productionBonusRate('bonocys', 'Ana', '2026-10-27', '2026-11'), 0.20));

// ---- cálculo del adicional
$extra = fn(float $u, float $r, float $b): float => BonusConfigurationService::extraProductionPay($u, $r, $b);
$check('100.000 bolsas: +20.000', $near($extra(100000, 0.20, 1.0), 20000));
$check('sin producción o sin multiplicador: 0', $extra(0, 0.20, 1.0) === 0.0 && $extra(100000, 0.0, 1.0) === 0.0 && $extra(-5, 0.2, 1.0) === 0.0);
$check('sigue la tarifa base si cambia', $near($extra(100000, 0.20, 1.5), 30000));

// ---- ejemplos del plan (tarifas: bolsas 1, XXL 1,5, sacos 2, basurines 0,5, cambio $5.000, embalaje 0,5)
$rates = ['bolsas' => 1.0, 'xxl' => 1.5, 'sacos' => 2.0, 'basurines' => 0.5];
$pay = fn(array $units, float $rate) => array_sum(array_map(fn($k, $u) => $u * $rates[$k], array_keys($units), $units)) + $extra(array_sum($units), $rate, $rates['bolsas']);
$check('100.000 bolsas → $120.000', $near($pay(['bolsas' => 100000], 0.20), 120000));
$check('100.000 XXL → $170.000', $near($pay(['xxl' => 100000], 0.20), 170000));
$check('100.000 sacos → $220.000', $near($pay(['sacos' => 100000], 0.20), 220000));
$check('100.000 basurines/troqueles → $70.000', $near($pay(['basurines' => 100000], 0.20), 70000));
$check('varias bolsas → base $170.000 + $25.000 = $195.000', $near($pay(['bolsas' => 60000, 'xxl' => 40000, 'sacos' => 25000], 0.20), 195000)
    && $near($extra(125000, 0.20, 1.0), 25000));
$check('sumar por línea da lo mismo que por total', $near($extra(60000, 0.2, 1) + $extra(40000, 0.2, 1) + $extra(25000, 0.2, 1), $extra(125000, 0.2, 1)));

$units = ['bolsas' => 100000, 'sacos' => 50000, 'basurines' => 20000];
$base = 100000 * 1 + 50000 * 2 + 20000 * 0.5;
$adicional = $extra(array_sum($units), 0.20, 1.0);
$cambios = 3 * 5000;
$embalaje = 30000 * 0.5;
$check('ejemplo completo: base $210.000 y adicional $34.000', $near($base, 210000) && $near($adicional, 34000));
$check('ejemplo completo: total a pagar $259.000', $near($base + $adicional + $cambios, 259000));
$check('ejemplo completo: total bolsa y embalaje $274.000', $near($base + $adicional + $cambios + $embalaje, 274000));
$check('mismo operador al 100%: $225.000 y $240.000', $near($base + $cambios, 225000) && $near($base + $cambios + $embalaje, 240000));

echo $fail === 0 ? "\nBONO CYS 120% OK\n" : "\nBONO CYS 120%: $fail fallas\n";
exit($fail === 0 ? 0 : 1);
