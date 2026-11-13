<?php

declare(strict_types=1);

// QA · Bonificaciones (bonos de producción): solo Javier (4129) y Héctor (4077) ven el botón y las pantallas.

require_once __DIR__ . '/../../src/bootstrap.php';

$fail = 0;
$total = 0;
$check = function (string $name, bool $ok) use (&$fail, &$total): void {
    $total++;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
};
$as = static function (array $session): bool {
    $_SESSION = $session;
    return canViewProductionBonuses();
};
$logged = static fn(int $id, array $extra = []): array => array_merge(['auth_user_id' => $id, 'user_id' => $id], $extra);

$check('Javier (4129) ve Bonificaciones', $as($logged(4129)));
$check('Héctor (4077) ve Bonificaciones', $as($logged(4077)));
$check('otro usuario del ERP no lo ve', !$as($logged(4075)));
$check('otro trabajador que se llame Javier no lo ve (se valida por usuario, no por nombre)',
    !$as($logged(5001, ['user_firstname' => 'JAVIER', 'auth_display_name' => 'Javier Soto', 'user_login' => 'JAVIER2'])));
$check('sin sesión no lo ve', !$as([]));
$_SESSION = [];

$root = dirname(__DIR__, 2);
$flat = static fn(string $rel): string => (string)preg_replace('/\s+/', ' ', (string)file_get_contents($root . '/' . $rel));
$router = $flat('src/Http/Bonuses/BonusesRouter.php');
$guard = "str_starts_with(\$path, '/bonificaciones') && !canViewProductionBonuses()";
$check('todas las rutas /bonificaciones se bloquean antes de cualquier pantalla o descarga',
    str_contains($router, $guard) && strpos($router, $guard) < strpos($router, 'handleMaintenanceBonusRoutes($path'));
$check('el botón Bonificaciones del menú solo aparece para ellos',
    str_contains($flat('src/Views/layouts/erp_shell.php'), "canViewProductionBonuses()) { echo \$link('/bonificaciones', 'Bonificaciones'"));
$check('el acceso directo Bonoflexo del inicio también',
    str_contains($flat('src/Http/Reports/DashboardAndGraphicsModule.php'), "if (canViewProductionBonuses()) { \$body .= '<a class=\"btn secondary\" href=\"/bonificaciones?view=bonoflexo\">"));

echo "\n" . ($fail === 0 ? "ACCESO A BONOS OK ($total)" : "ACCESO A BONOS: $fail de $total con error") . "\n";
exit($fail === 0 ? 0 : 1);
