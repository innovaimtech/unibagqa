<?php
declare(strict_types=1);
// Impresora en red por IP (puerto 9100) desde la aplicación web, sin programas en el PC (receptor TCP local de prueba).
$root = dirname(__DIR__, 2);
require_once $root . '/src/bootstrap.php';

$fail = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . ($extra !== '' && !$ok ? " [$extra]" : '') . "\n";
    if (!$ok) {
        $fail++;
    }
};

// Solo IPs de red local y puertos de impresora (el servidor nunca se conecta a otra cosa).
$check('acepta IP de red local con y sin puerto', PrintService::parseNetworkPrinter('192.168.1.177') === ['host' => '192.168.1.177', 'port' => 9100]
    && PrintService::parseNetworkPrinter('tcp://10.0.0.5:9101') === ['host' => '10.0.0.5', 'port' => 9101]
    && PrintService::parseNetworkPrinter('172.16.3.4:6101') === ['host' => '172.16.3.4', 'port' => 6101]);
$check('rechaza IP pública, loopback, puertos que no son de impresora y texto', PrintService::parseNetworkPrinter('8.8.8.8') === null
    && PrintService::parseNetworkPrinter('127.0.0.1') === null && PrintService::parseNetworkPrinter('192.168.1.177:22') === null
    && PrintService::parseNetworkPrinter('192.168.1.177:3306') === null && PrintService::parseNetworkPrinter('impresora') === null
    && PrintService::parseNetworkPrinter('999.1.1.1') === null);

$_COOKIE['unibag_print_ip'] = '192.168.1.177';
$check('la IP elegida en esta PC (cookie) se usa como impresora', PrintService::clientNetworkPrinter() === ['host' => '192.168.1.177', 'port' => 9100]);
$_COOKIE['unibag_print_ip'] = '8.8.8.8:9100';
$check('cookie manipulada con IP pública: se ignora', PrintService::clientNetworkPrinter() === null);
unset($_COOKIE['unibag_print_ip']);

// Envío real a un receptor TCP local.
$server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($server === false) {
    $check('receptor de prueba', false, $errstr);
} else {
    $name = stream_socket_get_name($server, false);
    $port = (int)substr((string)$name, strrpos((string)$name, ':') + 1);
    $label = (new PrintService(['PRINT_LANGUAGE' => 'epl']))->buildTraceabilityEntryCommand(['traceability_code' => 'TRZ5C4687', 'item_code' => 'BOL0352',
        'quantity' => 200, 'unit_name' => 'UNID', 'warehouse_name' => '400', 'created_at' => '2026-11-12 10:00:00']);
    $res = (new PrintService([]))->sendNetworkRaw(['host' => '127.0.0.1', 'port' => $port], $label, 2);
    $conn = @stream_socket_accept($server, 2);
    $got = '';
    if ($conn) {
        stream_set_timeout($conn, 2);
        while (!feof($conn)) {
            $chunk = fread($conn, 65536);
            if ($chunk === '' || $chunk === false) {
                break;
            }
            $got .= $chunk;
        }
        fclose($conn);
    }
    fclose($server);
    $check('envía la etiqueta completa (2 copias) a la impresora de red', $res['ok'] === true && $got === str_repeat($label, 2), strlen($got) . ' bytes');
    $closed = (new PrintService([]))->sendNetworkRaw(['host' => '127.0.0.1', 'port' => $port], 'X', 1);
    $check('impresora apagada o fuera de la red: error claro, sin imprimir', $closed['ok'] === false && str_contains((string)$closed['error'], 'no logra conectarse'));
}

$api = (string)file_get_contents($root . '/src/Http/ApiModule.php');
$check('endpoint /api/print/network con CSRF, sesión y solo IP validada', str_contains($api, "'/api/print/network'") && str_contains($api, 'hash_equals($sessionToken, $token)')
    && str_contains($api, 'PrintService::parseNetworkPrinter((string)($_POST[\'printer_ip\']'));
$hw = (string)file_get_contents($root . '/public/assets/js/unibag-hardware.js');
$check('navegador: con IP elegida en esta PC imprime por red primero (también Ingreso con Trazabilidad)', str_contains($hw, 'var netRes = await printViaNetwork(job);')
    && str_contains($hw, 'id="hw-inp-printer-ip"') && str_contains($hw, 'unibag_print_ip='));

echo $fail === 0 ? "\nIMPRESORA EN RED OK\n" : "\nIMPRESORA EN RED: $fail fallas\n";
exit($fail > 0 ? 1 : 0);
