<?php
declare(strict_types=1);
// Correo de facturación de embalaje: la tabla por CC solo en cierre mixto y nunca con la fila principal en 0.
require_once __DIR__ . '/../../src/PackagingBillingMail.php';

$failures = 0;
function checkMail(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures++;
    }
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$mail = new PackagingBillingMail($pdo);
$detail = ['req_embalaje_cajas_completas_amt' => 14, 'req_embalaje_bolsas_por_caja_amt' => 1000,
    'req_embalaje_caja_final' => 560, 'req_embalaje_bolsas_sobrantes_amt' => 0];
// Lo que envía el formulario en un cierre estándar: la fila del CC principal en 0 (caso CC 26-00967-1).
$zeroRow = [['req_number' => '26-00967-1', 'total_cajas' => 0, 'bolsas_por_caja' => 0, 'bolsas_caja_final' => 0, 'bolsas_sobrante' => 0, 'total_bolsas' => 0]];

checkMail('cierre estándar (20) con fila mixta en 0: usa el detalle estándar', $mail->mixedRows('20', '26-00967-1', $detail, $zeroRow) === []);
checkMail('cierre sin código con fila mixta en 0: usa el detalle estándar', $mail->mixedRows('', '26-00967-1', $detail, $zeroRow) === []);

$mixed = $zeroRow;
$mixed[] = ['req_number' => '26-00977-1', 'total_cajas' => 3, 'bolsas_por_caja' => 500, 'bolsas_caja_final' => 120, 'bolsas_sobrante' => 0, 'total_bolsas' => 1620];
$rows = $mail->mixedRows('50', '26-00967-1', $detail, $mixed);
checkMail('mixto: dos filas', count($rows) === 2);
checkMail('mixto: la fila principal se llena con lo registrado arriba (14 cajas × 1.000 + 560)',
    ($rows[0]['total_cajas'] ?? 0) === 14 && ($rows[0]['bolsas_por_caja'] ?? 0) === 1000 && ($rows[0]['total_bolsas'] ?? 0) === 14560);
checkMail('mixto: el otro CC queda igual', ($rows[1]['total_bolsas'] ?? 0) === 1620 && ($rows[1]['req_number'] ?? '') === '26-00977-1');

$filled = $mixed;
$filled[0] = ['req_number' => '26-00967-1', 'total_cajas' => 2, 'bolsas_por_caja' => 100, 'bolsas_caja_final' => 0, 'bolsas_sobrante' => 5, 'total_bolsas' => 205];
$rows = $mail->mixedRows('50', '26-00967-1', $detail, $filled);
checkMail('mixto: si la fila principal ya trae unidades, no se reemplaza', ($rows[0]['total_bolsas'] ?? 0) === 205);

$empty = [['req_number' => '26-00967-1', 'total_cajas' => 0, 'bolsas_por_caja' => 0, 'bolsas_caja_final' => 0, 'bolsas_sobrante' => 0, 'total_bolsas' => 0]];
checkMail('mixto sin ninguna unidad: usa el detalle estándar', $mail->mixedRows('50', '26-00967-1', [], $empty) === []);

exit($failures > 0 ? 1 : 0);
