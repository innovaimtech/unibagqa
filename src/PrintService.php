<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Servicio de Generación e Impresión de Etiquetas Térmicas Industriales
 * (src/PrintService.php)
 * =============================================================================
 *
 * Responsabilidad:
 * Genera el código de control crudo (ZPL II, TSPL o EPL) y coordina el envío
 * a impresoras térmicas de etiquetas adhesivas (tamaño estándar 100x50 mm y pallets).
 *
 * Tipos de etiquetas emitidas:
 * 1. Etiquetas de Bobina (Roll Label): Identificador RB-*, código de barras Code 128,
 *    gramaje/micrones, ancho, peso kg, fecha y lote.
 * 2. Etiquetas de Caja (Box Label / Work Order Finish): Identificador BX-*, unidades,
 *    OT, operador, máquina y fecha de confección.
 * 3. Etiquetas de Pallet (Pallet Label): Identificador PL-*, totales consolidados,
 *    peso neto/bruto y lista de cajas contenidas.
 *
 * Perfiles y lenguajes soportados:
 * - 'zebra'  -> Lenguaje ZPL (Zebra Programming Language II).
 * - 'barpos' -> Lenguaje TSPL (TSC / Barpos / impresoras genéricas POS).
 * - 'default'-> Fallback configurable por PRINT_LANGUAGE (por defecto ZPL).
 *
 * Mecanismo de envío:
 * Se comunica vía HTTP POST /print con el puente local (print/print_bridge.ps1 en
 * puerto 8767), el cual envía los bytes directamente al spooler nativo de Windows
 * (winspool.drv) sin requerir drivers gráficos complejos.
 */
final class PrintService
{
    /**
     * Código de barras a 203 ppp: módulo de 3 puntos (0,375 mm) y 100 puntos de alto (12,5 mm).
     * Si con ese módulo pasaría de 520 puntos (65 mm), usa 2 puntos (0,25 mm) para no ocupar toda la etiqueta.
     */
    private const BARCODE_MODULE_DOTS = 3;
    private const BARCODE_FINE_MODULE_DOTS = 2;
    private const BARCODE_MAX_WIDTH_DOTS = 520;
    private const BARCODE_HEIGHT_DOTS = 100;

    /**
     * @param array<string, mixed> $env Variables de entorno con credenciales y configuración de impresión.
     */
    public function __construct(private array $env)
    {
    }

    /**
     * Retorna una nueva instancia configurada con un perfil específico ('zebra' o 'barpos').
     */
    public function withProfile(string $profile): self
    {
        $env = $this->env;
        $env['PRINT_PROFILE'] = $this->normalizeProfile($profile);
        return new self($env);
    }

    /**
     * Obtiene el perfil de impresora actualmente configurado.
     */
    public function getConfiguredProfile(): string
    {
        return $this->getPrintProfile();
    }

    private function normalizeProfile(string $profile): string
    {
        $profile = strtolower(trim($profile));
        return in_array($profile, ['default', 'zebra', 'barpos'], true) ? $profile : 'default';
    }

    private function normalizeLanguage(string $language): string
    {
        $language = strtolower(trim($language));
        return in_array($language, ['zpl', 'tspl', 'epl'], true) ? $language : 'zpl';
    }

    private function getPrintProfile(): string
    {
        return $this->normalizeProfile((string)($this->env['PRINT_PROFILE'] ?? 'default'));
    }

    /**
     * Lenguaje de la impresora del computador que hace la petición (cookie `unibag_print_lang`, la fija el panel
     * de Hardware al elegir «Impresora de esta PC»). Así cada computador recibe las etiquetas en el lenguaje de SU
     * impresora (Barpos TSPL por COM en uno, HPRT EPL en otro) y no en el único PRINT_LANGUAGE del servidor.
     */
    public static function clientPrinterLanguage(): string
    {
        $lang = strtolower(trim((string)($_COOKIE['unibag_print_lang'] ?? '')));
        return in_array($lang, ['epl', 'tspl', 'zpl'], true) ? $lang : '';
    }

    private function getPrintLanguage(): string
    {
        $client = self::clientPrinterLanguage();
        if ($client !== '' && empty($this->env['PRINT_IGNORE_CLIENT_LANGUAGE'])) {
            return $client;
        }
        $profile = $this->getPrintProfile();
        if ($profile === 'zebra') {
            return $this->normalizeLanguage((string)($this->env['PRINT_ZEBRA_LANGUAGE'] ?? 'zpl'));
        }
        if ($profile === 'barpos') {
            return $this->normalizeLanguage((string)($this->env['PRINT_BARPOS_LANGUAGE'] ?? 'tspl'));
        }
        return $this->normalizeLanguage((string)($this->env['PRINT_LANGUAGE'] ?? 'zpl'));
    }

    public function getConfiguredPrintLanguage(): string
    {
        return $this->getPrintLanguage();
    }

    /** Valores vigentes de intensidad y velocidad; null = los define la propia impresora. */
    public function printTuningDefaults(): array
    {
        $useDevice = (string)($this->env['PRINT_EPL_USE_DEVICE_SETTINGS'] ?? '0') === '1';
        return [
            'epl' => ['density' => $useDevice ? null : $this->getEplDensity(), 'speed' => $useDevice ? null : $this->getEplSpeed()],
            'tspl' => ['density' => $this->getTsplDensity(), 'speed' => $this->getTsplSpeed()],
            // La etiqueta ZPL ajusta sobre la intensidad guardada en la impresora (^MD es relativo).
            'zpl' => ['density' => null, 'speed' => 3],
        ];
    }

    public function getConfiguredPrinterName(): string
    {
        $profile = $this->getPrintProfile();
        if ($profile === 'zebra') {
            $printerName = trim((string)($this->env['PRINT_ZEBRA_PRINTER_NAME'] ?? ''));
            if ($printerName !== '') {
                return $printerName;
            }
        }
        if ($profile === 'barpos') {
            $printerName = trim((string)($this->env['PRINT_BARPOS_PRINTER_NAME'] ?? ''));
            if ($printerName !== '') {
                return $printerName;
            }
        }
        return trim((string)($this->env['PRINT_PRINTER_NAME'] ?? ''));
    }

    public function isEnabled(): bool
    {
        $mode = strtolower(trim((string)($this->env['PRINT_MODE'] ?? 'none')));
        return !in_array($mode, ['', 'none', 'off', 'disabled'], true);
    }

    /**
     * Determina si la petición HTTP proviene de una estación cliente distinta al servidor PHP.
     */
    public static function isRemoteClientRequest(): bool
    {
        $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remoteAddr === '') {
            return false;
        }
        return !in_array($remoteAddr, ['127.0.0.1', '::1', 'localhost'], true);
    }

    /**
     * Encola un trabajo de impresión crudo (EPL/TSPL/ZPL) para despacho inmediato desde el navegador del operador.
     *
     * @param array<string, mixed> $job
     */
    public static function enqueueClientJob(array $job): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (!isset($_SESSION['unibag_client_print_queue']) || !is_array($_SESSION['unibag_client_print_queue'])) {
            $_SESSION['unibag_client_print_queue'] = [];
        }
        $_SESSION['unibag_client_print_queue'][] = $job;
        while (count($_SESSION['unibag_client_print_queue']) > 300) {
            array_shift($_SESSION['unibag_client_print_queue']);
        }
    }

    /**
     * Extrae el último trabajo encolado (usado en respuestas AJAX que entregan print_job en línea).
     *
     * @return array<string, mixed>|null
     */
    public static function popLastClientJob(): ?array
    {
        if (
            session_status() !== PHP_SESSION_ACTIVE
            || empty($_SESSION['unibag_client_print_queue'])
            || !is_array($_SESSION['unibag_client_print_queue'])
        ) {
            return null;
        }
        $job = array_pop($_SESSION['unibag_client_print_queue']);
        return is_array($job) ? $job : null;
    }

    /**
     * Obtiene y vacía todos los trabajos pendientes para inyectarlos en la siguiente vista HTML.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function drainClientJobs(): array
    {
        if (
            session_status() !== PHP_SESSION_ACTIVE
            || empty($_SESSION['unibag_client_print_queue'])
            || !is_array($_SESSION['unibag_client_print_queue'])
        ) {
            return [];
        }
        $jobs = array_values($_SESSION['unibag_client_print_queue']);
        $_SESSION['unibag_client_print_queue'] = [];
        return $jobs;
    }

    /**
     * Construye el payload estandarizado de impresión térmica para despacho cliente (WebUSB / WebSerial / Puente Local).
     *
     * @return array{printer: string, profile: string, language: string, copies: int, content: string, label: string}
     */
    public function buildClientJob(string $command, ?int $copies = null, string $label = ''): array
    {
        $resolvedCopies = $copies ?? (int)($this->env['PRINT_COPIES'] ?? 1);
        if ($resolvedCopies <= 0) {
            $resolvedCopies = 1;
        }
        $printerName = $this->getConfiguredPrinterName();
        return [
            'printer' => $printerName !== '' ? $printerName : 'DEFAULT',
            'profile' => $this->getPrintProfile(),
            'language' => $this->getPrintLanguage(),
            'copies' => $resolvedCopies,
            'content' => $command,
            'label' => $label,
            // Identificador único del trabajo: el puente de impresión ignora repeticiones.
            'job_id' => $this->newJobId(),
        ];
    }

    /**
     * Resuelve endpoint TCP/IP directo (puerto 9100 JetDirect/RAW) si la impresora es de red.
     *
     * @return array{host: string, port: int}|null
     */
    private function getConfiguredTcpEndpoint(): ?array
    {
        $profile = $this->getPrintProfile();
        $host = '';
        $port = (int)($this->env['PRINT_TCP_PORT'] ?? 9100);
        if ($profile === 'zebra') {
            $host = trim((string)($this->env['PRINT_ZEBRA_TCP_HOST'] ?? ''));
            $pPort = (int)($this->env['PRINT_ZEBRA_TCP_PORT'] ?? 0);
            if ($pPort > 0) {
                $port = $pPort;
            }
        } elseif ($profile === 'barpos') {
            $host = trim((string)($this->env['PRINT_BARPOS_TCP_HOST'] ?? ''));
            $pPort = (int)($this->env['PRINT_BARPOS_TCP_PORT'] ?? 0);
            if ($pPort > 0) {
                $port = $pPort;
            }
        }
        if ($host === '') {
            $host = trim((string)($this->env['PRINT_TCP_HOST'] ?? ''));
        }
        $rawUrl = trim((string)($this->env['PRINT_HTTP_URL'] ?? ''));
        if ($host === '' && str_starts_with(strtolower($rawUrl), 'tcp://')) {
            $parsedHost = (string)(parse_url($rawUrl, PHP_URL_HOST) ?? '');
            $parsedPort = (int)(parse_url($rawUrl, PHP_URL_PORT) ?: 9100);
            if ($parsedHost !== '') {
                $host = $parsedHost;
                $port = $parsedPort;
            }
        }
        if ($host === '') {
            return null;
        }
        return ['host' => $host, 'port' => $port > 0 ? $port : 9100];
    }

    /** Genera un identificador único para un trabajo de impresión. */
    private function newJobId(): string
    {
        try {
            return 'p' . bin2hex(random_bytes(8));
        } catch (Throwable) {
            return 'p' . str_replace('.', '', uniqid('', true));
        }
    }

    /**
     * Escribe TODOS los bytes en el socket (fwrite puede escribir menos de lo pedido).
     * Devuelve false si no se logró enviar el contenido completo.
     *
     * @param resource $fp
     */
    private function writeAll($fp, string $data): bool
    {
        $total = strlen($data);
        $sent = 0;
        while ($sent < $total) {
            $n = @fwrite($fp, substr($data, $sent));
            if ($n === false || $n === 0) {
                return false;
            }
            $sent += $n;
        }
        return true;
    }

    public function checkBridgeStatus(): array
    {
        if (!$this->isEnabled()) {
            return ['online' => false, 'message' => 'Impresión térmica deshabilitada'];
        }
        $tcp = $this->getConfiguredTcpEndpoint();
        if ($tcp !== null) {
            $fp = @stream_socket_client('tcp://' . $tcp['host'] . ':' . $tcp['port'], $errno, $errstr, 0.5);
            if (is_resource($fp)) {
                fclose($fp);
                return ['online' => true, 'message' => 'Impresora de red conectada (' . $tcp['host'] . ':' . $tcp['port'] . ')'];
            }
            return ['online' => false, 'message' => 'Impresora de red sin respuesta (' . $tcp['host'] . ':' . $tcp['port'] . ')'];
        }
        if (self::isRemoteClientRequest()) {
            return ['online' => false, 'client_mode' => true, 'message' => 'Modo cliente navegador activo'];
        }
        if (!function_exists('curl_init')) {
            return ['online' => false, 'message' => 'cURL no disponible en PHP'];
        }
        $url = (string)($this->env['PRINT_HTTP_URL'] ?? 'http://127.0.0.1:8767/print');
        $healthUrl = preg_replace('#/print$#i', '/health', $url);
        if ($healthUrl === $url) {
            $healthUrl = 'http://127.0.0.1:8767/health';
        }
        $ch = curl_init($healthUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_IPRESOLVE => defined('CURL_IPRESOLVE_V4') ? CURL_IPRESOLVE_V4 : 1,
            CURLOPT_CONNECTTIMEOUT_MS => 350,
            CURLOPT_TIMEOUT_MS => 500,
        ]);
        $res = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res !== false && $httpCode === 200) {
            return ['online' => true, 'message' => 'Puente conectado (puerto 8767)'];
        }
        return ['online' => false, 'message' => 'Listo para impresión desde navegador'];
    }

    public function printRollLabel(array $roll): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'error' => 'Impresión deshabilitada (PRINT_MODE).'];
        }

        $url = (string)($this->env['PRINT_HTTP_URL'] ?? 'http://127.0.0.1:8767/print');
        $printerName = $this->getConfiguredPrinterName();
        $copies = max(1, (int)($this->env['PRINT_COPIES'] ?? 1));
        $labelCode = trim((string)($roll['roll_code'] ?? ('#' . ($roll['id'] ?? ''))));

        try {
            $command = $this->buildRollCommand($roll);
        } catch (InvalidArgumentException $e) {
            // Peso inválido: la etiqueta no se genera ni se envía a ninguna impresora.
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        return $this->sendRaw($command, $copies, $printerName, $url, $this->getPrintLanguage(), $labelCode);
    }

    public function buildRollsBatchCommand(array $rolls): string
    {
        $chunks = [];
        foreach ($rolls as $roll) {
            if (!empty($roll) && is_array($roll)) {
                $chunks[] = trim($this->buildRollCommand($roll), "\r\n");
            }
        }
        return $chunks === [] ? '' : (implode("\r\n", $chunks) . "\r\n");
    }

    public function printRollLabels(array $rolls): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'error' => 'Impresión deshabilitada (PRINT_MODE).'];
        }

        $url = (string)($this->env['PRINT_HTTP_URL'] ?? 'http://127.0.0.1:8767/print');
        $printerName = $this->getConfiguredPrinterName();
        $copies = max(1, (int)($this->env['PRINT_COPIES'] ?? 1));

        try {
            $combinedCommand = $this->buildRollsBatchCommand($rolls);
        } catch (InvalidArgumentException $e) {
            // Si una sola etiqueta del lote tiene peso inválido no se imprime NINGUNA del lote.
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        if (trim($combinedCommand) === '') {
            return ['ok' => false, 'error' => 'No hay etiquetas para imprimir.'];
        }

        $count = count($rolls);
        $labelDesc = $count === 1
            ? trim((string)($rolls[0]['roll_code'] ?? 'Etiqueta'))
            : ('Lote de ' . $count . ' etiquetas');

        return $this->sendRaw($combinedCommand, $copies, $printerName, $url, $this->getPrintLanguage(), $labelDesc);
    }

    public function printWorkOrderBoxLabel(array $workOrder, ?array $finish = null): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'error' => 'Impresión deshabilitada (PRINT_MODE).'];
        }

        $url = (string)($this->env['PRINT_HTTP_URL'] ?? 'http://127.0.0.1:8767/print');
        $printerName = $this->getConfiguredPrinterName();
        $copies = max(1, (int)($this->env['PRINT_COPIES'] ?? 1));
        $labelCode = 'Caja OT ' . trim((string)($workOrder['ot_code'] ?? ''));

        $command = $this->buildWorkOrderBoxCommand($workOrder, $finish);
        return $this->sendRaw($command, $copies, $printerName, $url, $this->getPrintLanguage(), $labelCode);
    }

    public function printBoxLabel(array $box): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'error' => 'Impresión deshabilitada (PRINT_MODE).'];
        }

        $url = (string)($this->env['PRINT_HTTP_URL'] ?? 'http://127.0.0.1:8767/print');
        $printerName = $this->getConfiguredPrinterName();
        $copies = max(1, (int)($this->env['PRINT_COPIES'] ?? 1));
        $labelCode = 'Caja ' . trim((string)($box['box_code'] ?? ''));

        $command = $this->buildBoxCommand($box);
        return $this->sendRaw($command, $copies, $printerName, $url, $this->getPrintLanguage(), $labelCode);
    }

    public function printPalletLabel(array $pallet, array $boxes = []): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'error' => 'Impresión deshabilitada (PRINT_MODE).'];
        }

        $url = (string)($this->env['PRINT_HTTP_URL'] ?? 'http://127.0.0.1:8767/print');
        $printerName = $this->getConfiguredPrinterName();
        $copies = max(1, (int)($this->env['PRINT_COPIES'] ?? 1));
        $labelCode = 'Pallet ' . trim((string)($pallet['pallet_code'] ?? ''));

        $command = $this->buildPalletCommand($pallet, $boxes);
        return $this->sendRaw($command, $copies, $printerName, $url, $this->getPrintLanguage(), $labelCode);
    }

    private function sendRaw(string $content, int $copies, string $printerName, string $url, string $language, string $jobLabel = ''): array
    {
        $mode = strtolower(trim((string)($this->env['PRINT_MODE'] ?? 'none')));
        $effectivePrinter = $printerName !== '' ? $printerName : 'DEFAULT';
        $job = [
            'printer' => $effectivePrinter,
            'profile' => $this->getPrintProfile(),
            'language' => $language,
            'copies' => max(1, $copies),
            'content' => $content,
            'label' => $jobLabel,
            'job_id' => $this->newJobId(),
        ];

        // 1. Impresión directa por Red TCP/IP (Puerto 9100 RAW / JetDirect) si está configurada
        $tcp = $this->getConfiguredTcpEndpoint();
        if ($mode === 'tcp' || $tcp !== null) {
            if ($tcp === null) {
                self::enqueueClientJob($job);
                return ['ok' => false, 'client_queued' => true, 'print_job' => $job, 'error' => null];
            }
            $fp = @stream_socket_client('tcp://' . $tcp['host'] . ':' . $tcp['port'], $errno, $errstr, 1.8);
            if (is_resource($fp)) {
                stream_set_timeout($fp, 3);
                // Todas las copias se envían en una sola escritura continua.
                $okTcp = $this->writeAll($fp, str_repeat($content, max(1, $copies)));
                @fclose($fp);
                if ($okTcp) {
                    return ['ok' => true, 'error' => null, 'transport' => 'tcp'];
                }
                // La conexión se abrió pero el envío falló a mitad de camino: puede haber
                // salido parte de las etiquetas. NO se reenvía por otra vía (duplicaría).
                return [
                    'ok' => false,
                    'error' => 'Falló el envío a la impresora de red a mitad del trabajo. Revisa qué etiquetas salieron antes de reimprimir.',
                    'ambiguous' => true,
                ];
            }
            // Si la impresora TCP no es alcanzable desde el servidor (ej. VPS fuera de LAN), delegar al navegador cliente
            self::enqueueClientJob($job);
            return ['ok' => false, 'client_queued' => true, 'print_job' => $job, 'error' => null];
        }

        // 2. Modo explícito de despacho en navegador (WebUSB / WebSerial / Puente Cliente)
        if (in_array($mode, ['client', 'browser', 'usb', 'webusb'], true)) {
            self::enqueueClientJob($job);
            return ['ok' => false, 'client_queued' => true, 'print_job' => $job, 'error' => null];
        }

        // 3. Si la URL apunta a loopback (127.0.0.1 / localhost) pero el usuario accede desde otra PC (LAN o VPS),
        //    no intentar conectar al 127.0.0.1 del servidor: delegar en 0 ms al navegador de la PC del operador.
        $urlHost = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? '127.0.0.1'));
        $isLoopbackBridge = in_array($urlHost, ['127.0.0.1', 'localhost', '::1'], true);
        if ($isLoopbackBridge && self::isRemoteClientRequest()) {
            self::enqueueClientJob($job);
            return ['ok' => false, 'client_queued' => true, 'print_job' => $job, 'error' => null];
        }

        if (!function_exists('curl_init')) {
            self::enqueueClientJob($job);
            return ['ok' => false, 'client_queued' => true, 'print_job' => $job, 'error' => null];
        }

        $payload = json_encode([
            'printer' => $effectivePrinter,
            'copies' => max(1, $copies),
            'language' => $language,
            'content' => $content,
            'zpl' => $language === 'zpl' ? $content : null,
            'job_id' => $job['job_id'],
        ], JSON_UNESCAPED_UNICODE);

        $headers = ['Content-Type: application/json; charset=utf-8'];
        if (str_contains($url, '127.0.0.1')) {
            $parsedPort = parse_url($url, PHP_URL_PORT) ?: 8767;
            $headers[] = 'Host: localhost:' . $parsedPort;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_IPRESOLVE => defined('CURL_IPRESOLVE_V4') ? CURL_IPRESOLVE_V4 : 1,
            CURLOPT_CONNECTTIMEOUT_MS => 500,
            CURLOPT_TIMEOUT_MS => 2500,
            CURLOPT_FAILONERROR => false,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $curlErrNo = curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Tiempo de espera agotado (cURL 28): el puente pudo haber imprimido igualmente.
        // No se reenvía al navegador para no duplicar la etiqueta.
        if ($raw === false && $curlErrNo === 28) {
            return [
                'ok' => false,
                'error' => 'El servicio de impresión no respondió a tiempo. Revisa si la etiqueta salió antes de reimprimir.',
                'ambiguous' => true,
            ];
        }

        // Si el puente en el servidor local no está corriendo, delegar al navegador (permite WebUSB / WebSerial directo)
        if ($raw === false) {
            self::enqueueClientJob($job);
            return [
                'ok' => false,
                'client_queued' => true,
                'print_job' => $job,
                'error' => null,
            ];
        }

        $data = json_decode((string)$raw, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = is_array($data) && isset($data['error']) ? (string)$data['error'] : ('Error en puente de impresión (HTTP ' . $httpCode . ')');
            self::enqueueClientJob($job);
            return ['ok' => false, 'client_queued' => true, 'error' => $msg, 'raw' => (string)$raw, 'print_job' => $job];
        }

        if (!is_array($data) || ($data['ok'] ?? false) !== true) {
            $msg = is_array($data) && isset($data['error']) ? (string)$data['error'] : 'Respuesta inválida del servicio de impresión.';
            self::enqueueClientJob($job);
            return ['ok' => false, 'client_queued' => true, 'error' => $msg, 'raw' => (string)$raw, 'print_job' => $job];
        }

        return ['ok' => true, 'error' => null, 'transport' => 'server-bridge'];
    }

    /**
     * Texto del peso que se imprime en la etiqueta de bobina.
     *
     * - Sin peso (no_weight): "-".
     * - Peso mayor a 0: kilos con 2 decimales (ej. "18.45 KG").
     * - Recepción por unidades (QUANTITY o sin modo informado): "1 UNID".
     * - Recepción por PESO con peso <= 0 o inválido: se rechaza la etiqueta. Un peso
     *   inválido NUNCA debe imprimirse (antes salía como "1 UNID").
     *
     * @throws InvalidArgumentException si el peso es inválido en una recepción por peso.
     */
    private function rollWeightText(array $roll, bool $noWeight): string
    {
        if ($noWeight) {
            return '-';
        }
        $weightKg = (float)($roll['weight_kg'] ?? 0);
        if (!is_finite($weightKg)) {
            throw new InvalidArgumentException('Peso inválido para la etiqueta de ' . ($roll['roll_code'] ?? 'bobina') . '.');
        }
        if ($weightKg > 0) {
            return number_format(round($weightKg, 2), 2, '.', '') . ' KG';
        }
        if (strtoupper((string)($roll['reception_mode'] ?? '')) === 'WEIGHT') {
            throw new InvalidArgumentException('La bobina ' . ($roll['roll_code'] ?? '') . ' no tiene un peso válido; no se imprime la etiqueta.');
        }
        return '1 UNID';
    }

    /**
     * Limpia un texto para usarlo dentro de un comando ZPL (^FD...).
     *
     * Se eliminan los caracteres que ZPL interpreta como comando: "^" (inicio de comando)
     * y "~" (comando de control, p. ej. ~JA cancela la impresión), además de la barra
     * invertida y los caracteres de control. Evita etiquetas rotas o inyección de
     * comandos desde datos como la descripción del producto.
     */
    private function z(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';
        $value = str_replace(['^', '~', '\\'], [' ', ' ', '/'], $value);
        return $value;
    }

    private function t(string $value): string
    {
        $value = $this->z($value);
        $value = strtr($value, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            '°' => ' ', 'º' => ' ', 'ª' => ' ', '·' => '-', '•' => '-',
        ]);
        // Las comillas dobles cierran el texto en TSPL/EPL: se reemplazan por comilla simple.
        return str_replace('"', "'", $value);
    }

    /**
     * Resolución fija de las impresoras de etiquetas (HPRT, Barpos, Zebra): 203 dpi
     * = 8 puntos por milímetro. Es una constante a propósito: toda la geometría de las
     * etiquetas (coordenadas en puntos) está diseñada para esta resolución, por lo que
     * la variable PRINT_DPI ya no se utiliza.
     */
    private const LABEL_DPI = 203;

    /** Tamaño físico fijo de TODAS las etiquetas: 100 x 70 mm (812 x 560 puntos a 203 dpi). */
    private const LABEL_WIDTH_MM = 100.0;
    private const LABEL_HEIGHT_MM = 70.0;

    private function printDpi(): int
    {
        return self::LABEL_DPI;
    }

    private function dotsPerMm(): float
    {
        return $this->printDpi() / 25.4;
    }

    private function mmToDots(float $mm): int
    {
        return (int)round(max(0.0, $mm) * $this->dotsPerMm());
    }

    private function getLabelWidthMm(): float
    {
        return self::LABEL_WIDTH_MM;
    }

    private function getLabelHeightMm(): float
    {
        return self::LABEL_HEIGHT_MM;
    }

    private function getLabelGapMm(): float
    {
        $value = (float)($this->env['PRINT_LABEL_GAP_MM'] ?? 3);
        return $value >= 0 ? $value : 3.0;
    }

    private function getPrintDirection(): int
    {
        $value = (int)($this->env['PRINT_DIRECTION'] ?? 0);
        return $value === 1 ? 1 : 0;
    }

    private function getReferenceXDots(): int
    {
        return (int)($this->env['PRINT_REF_X_DOTS'] ?? 0);
    }

    private function getReferenceYDots(): int
    {
        return (int)($this->env['PRINT_REF_Y_DOTS'] ?? 0);
    }

    private function getTsplDensity(): int
    {
        $value = (int)($this->env['PRINT_TSPL_DENSITY'] ?? 12);
        return max(0, min(15, $value));
    }

    private function getTsplSpeed(): float
    {
        $value = (float)($this->env['PRINT_TSPL_SPEED'] ?? 3.0);
        return max(1.0, min(6.0, $value));
    }

    private function getEplDensity(): int
    {
        $value = (int)($this->env['PRINT_EPL_DENSITY'] ?? 14);
        return max(0, min(15, $value));
    }

    private function getEplSpeed(): int
    {
        $value = (int)($this->env['PRINT_EPL_SPEED'] ?? 2);
        return max(1, min(6, $value));
    }

    private function buildEplDeviceSettings(): array
    {
        // Match the Windows driver without overriding device heat/speed settings.
        // Igualar al controlador sin imponer calor/velocidad al equipo.
        if ((string)($this->env['PRINT_EPL_USE_DEVICE_SETTINGS'] ?? '0') === '1') {
            return ['O', 'JF', 'WN'];
        }
        return ['D' . $this->getEplDensity(), 'S' . $this->getEplSpeed()];
    }

    private function getZplDarkness(): int
    {
        $value = (int)($this->env['PRINT_ZPL_DARKNESS'] ?? 20);
        return max(0, min(30, $value));
    }

    private function formatLabelDate(?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '' || $value === '-') {
            return '-';
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return $this->z($value);
        }

        return date('d-m-Y', $ts);
    }

    private function resolveRollDate(array $roll): string
    {
        $rawDate = trim((string)($roll['arrival_date'] ?? ''));
        if ($rawDate === '' || $rawDate === '-') {
            $rawDate = trim((string)($roll['created_at'] ?? ''));
        }
        if ($rawDate === '' || $rawDate === '-') {
            $rawDate = date('Y-m-d');
        }
        return $this->formatLabelDate($rawDate);
    }

    public function getRollZpl(array $roll): string
    {
        return $this->buildZpl($roll);
    }

    public function getRollPrintCommand(array $roll): string
    {
        return $this->buildRollCommand($roll);
    }

    public function buildRollCommand(array $roll): string
    {
        $language = $this->getPrintLanguage();
        if ($language === 'tspl') {
            return $this->buildRollTspl($roll);
        }
        if ($language === 'epl') {
            return $this->buildRollEpl($roll);
        }
        return $this->buildZpl($roll);
    }

    public function buildWorkOrderBoxCommand(array $workOrder, ?array $finish = null): string
    {
        $lang = $this->getPrintLanguage();
        if ($lang === 'tspl') {
            return $this->buildWorkOrderBoxTspl($workOrder, $finish);
        }
        if ($lang === 'epl') {
            return $this->buildWorkOrderBoxEpl($workOrder, $finish);
        }
        return $this->buildWorkOrderBoxZpl($workOrder, $finish);
    }

    public function buildBoxCommand(array $box): string
    {
        $lang = $this->getPrintLanguage();
        if ($lang === 'tspl') {
            return $this->buildBoxTspl($box);
        }
        if ($lang === 'epl') {
            return $this->buildBoxEpl($box);
        }
        return $this->buildBoxZpl($box);
    }

    public function buildPalletCommand(array $pallet, array $boxes = []): string
    {
        $lang = $this->getPrintLanguage();
        if ($lang === 'tspl') {
            return $this->buildPalletTspl($pallet, $boxes);
        }
        if ($lang === 'epl') {
            return $this->buildPalletEpl($pallet, $boxes);
        }
        return $this->buildPalletZpl($pallet, $boxes);
    }

    /** Ancho en puntos de un carácter de las fuentes internas EPL (1..4) a escala 1. */
    private function eplCharWidth(int $font): int
    {
        return [1 => 8, 2 => 10, 3 => 12, 4 => 14, 5 => 32][$font] ?? 12;
    }

    /** Coordenada X que centra el texto en la etiqueta de 812 puntos de ancho. */
    private function eplCenterX(string $text, int $font, int $mult = 1): int
    {
        $width = strlen($text) * $this->eplCharWidth($font) * $mult;
        return max(8, (int)floor((812 - $width) / 2));
    }

    /** Módulos de un Code 128: cada carácter ocupa 11, más 35 de inicio, control y fin. */
    private function code128Modules(string $code): int
    {
        return 11 * max(1, strlen($code)) + 35;
    }

    private function code128Module(string $code): int
    {
        return $this->code128Modules($code) * self::BARCODE_MODULE_DOTS <= self::BARCODE_MAX_WIDTH_DOTS
            ? self::BARCODE_MODULE_DOTS
            : self::BARCODE_FINE_MODULE_DOTS;
    }

    /** X que centra el código en la etiqueta de 812 puntos de ancho. */
    private function code128X(string $code): int
    {
        return max(8, (int)floor((812 - $this->code128Modules($code) * $this->code128Module($code)) / 2));
    }

    private function zplBarcode(string $code, int $y): string
    {
        return '^BY' . $this->code128Module($code) . ',2,' . self::BARCODE_HEIGHT_DOTS . "\n"
            . '^FO' . $this->code128X($code) . ',' . $y . '^BCN,' . self::BARCODE_HEIGHT_DOTS . ',N,N,N^FD' . $code . '^FS';
    }

    private function tsplBarcode(string $code, int $y): string
    {
        $m = $this->code128Module($code);
        return 'BARCODE ' . $this->code128X($code) . ',' . $y . ',"128",' . self::BARCODE_HEIGHT_DOTS . ',0,0,' . $m . ',' . $m . ',"' . $code . '"';
    }

    private function eplBarcode(string $code, int $y): string
    {
        $m = $this->code128Module($code);
        return 'B' . $this->code128X($code) . ',' . $y . ',0,1,' . $m . ',' . $m . ',' . self::BARCODE_HEIGHT_DOTS . ',N,"' . $code . '"';
    }

    /** Coordenada X que centra un texto TSPL en la etiqueta de 812 puntos de ancho. */
    private function tsplCenterX(string $text, int $font, int $mult = 1): int
    {
        $charWidth = [1 => 8, 2 => 12, 3 => 16, 4 => 24, 5 => 32][$font] ?? 16;
        return max(8, (int)floor((812 - strlen($text) * $charWidth * $mult) / 2));
    }

    /** Texto de la unidad para la etiqueta (UNID / C/U / Unid. → UNIDADES; BOB → BOBINAS). */
    public static function entryUnitLabel(string $unit): string
    {
        $u = strtoupper(trim($unit));
        $u = rtrim($u, '.');
        if ($u === '' || in_array($u, ['UNID', 'UND', 'UN', 'U', 'C/U', 'CU', 'UNIDAD', 'UNIDADES', 'UNIDS'], true)) {
            return 'UNIDADES';
        }
        return ['BOB' => 'BOBINAS', 'CJ' => 'CAJAS', 'CAJA' => 'CAJAS', 'PAQ' => 'PAQUETES', 'ROLLO' => 'ROLLOS'][$u] ?? $u;
    }

    /** Cantidad con separador de miles chileno (1.500 · 2,5). */
    public static function entryQuantityText(float $qty): string
    {
        $dec = abs($qty - round($qty)) < 0.0005 ? 0 : (abs($qty * 10 - round($qty * 10)) < 0.005 ? 1 : (abs($qty * 100 - round($qty * 100)) < 0.05 ? 2 : 3));
        return number_format($qty, $dec, ',', '.');
    }

    /**
     * Etiqueta 100x70 mm (812x560 puntos) de un ingreso a bodega con código de trazabilidad.
     *
     *  ┌──────────────────────────────────────────────┐
     *  │                UNIBAG CHILE                  │
     *  │      400 (PROD TERMINADOS REVENTA)           │
     *  │           ║║│║║│║│║║│║ (código)            │
     *  │                TRZ5C4687                     │
     *  ├──────────────────────────────┬───────────────┤
     *  │ BOL0352                      │   CANTIDAD    │
     *  │ BOLSA KRAFT 30X40 ...        │      200      │
     *  │ (hasta 3 líneas)             │   UNIDADES    │
     *  │ CC 26-00808-1 · 50 CAJAS     │ BULTO 3 DE 10 │
     *  ├──────────────────────────────┴───────────────┤
     *  │      12/11/2026 10:15 - OP: GABRIEL MUÑOZ     │
     *  └──────────────────────────────────────────────┘
     * La cantidad es la ORIGINAL del bulto (quantity_initial), no el saldo que queda tras usarlo o despacharlo.
     *
     * @param array<string, mixed> $e traceability_code, item_code, item_description, quantity, quantity_initial,
     *        unit_name, package_number, total_packages, warehouse_name, operator_name, created_at, order_ref,
     *        lot_number, box_count, entity_type
     */
    public function buildTraceabilityEntryCommand(array $e): string
    {
        $code = strtoupper($this->t((string)($e['traceability_code'] ?? '')));
        $sku = strtoupper($this->t(trim((string)($e['item_code'] ?? ''))));
        $desc = strtoupper($this->t(trim((string)($e['item_description'] ?? ''))));
        $initial = $e['quantity_initial'] ?? null;
        $qtyNum = ($initial !== null && $initial !== '' && (float)$initial > 0) ? (float)$initial : (float)($e['quantity'] ?? 0);
        $qty = self::entryQuantityText($qtyNum);
        $unit = $this->t(self::entryUnitLabel((string)($e['unit_name'] ?? '')));
        $pkgNo = max(1, (int)($e['package_number'] ?? 1));
        $pkgTotal = (int)($e['total_packages'] ?? 1);
        $pkgLine = $pkgTotal > 1 ? 'BULTO ' . $pkgNo . ' DE ' . $pkgTotal : '';
        $wh = strtoupper($this->t((string)($e['warehouse_name'] ?? '')));
        $refParts = [];
        $orderRef = strtoupper($this->t(trim((string)($e['order_ref'] ?? ''))));
        if ($orderRef !== '') {
            $refParts[] = preg_match('/^\d{2}-\d+/', $orderRef) === 1 ? 'CC ' . $orderRef : $orderRef;
        }
        if (trim((string)($e['lot_number'] ?? '')) !== '') {
            $refParts[] = 'LOTE ' . strtoupper($this->t(trim((string)$e['lot_number'])));
        }
        if ((int)($e['box_count'] ?? 0) > 0 && strtoupper((string)($e['entity_type'] ?? '')) === 'PALLET') {
            $refParts[] = (int)$e['box_count'] . ' CAJAS';
        }
        $ref = implode(' - ', $refParts);
        $op = strtoupper($this->t((string)($e['operator_name'] ?? '')));
        $when = $this->t(trim((string)($e['created_at'] ?? '')));
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', $when, $m) === 1) {
            $when = "{$m[3]}/{$m[2]}/{$m[1]} {$m[4]}:{$m[5]}";
        }
        $meta = trim($when . ($op !== '' ? ' - OP: ' . $op : ''));
        $lang = $this->getPrintLanguage();

        // Geometría (puntos a 203 dpi): columna izquierda 20..476, recuadro de cantidad 492..792.
        $L = 20;
        $LW = 456;
        $BX = 492;
        $BW = 300;
        $top = 262;
        $boxBottom = 498;

        // Fuentes por tamaño: [ancho de carácter, alto] a escala 1.
        $fonts = [
            'tspl' => ['s' => [2, 12, 20], 'm' => [3, 16, 24], 'l' => [4, 24, 32], 'xl' => [5, 32, 48]],
            'epl' => ['s' => [2, 10, 16], 'm' => [3, 12, 20], 'l' => [4, 14, 24], 'xl' => [5, 32, 48]],
            'zpl' => ['s' => [0, 13, 22], 'm' => [0, 16, 26], 'l' => [0, 22, 34], 'xl' => [0, 30, 50]],
        ][$lang === 'tspl' || $lang === 'epl' ? $lang : 'zpl'];
        /** Mayor multiplicador (≤ $max) con el que el texto cabe en $w. */
        $fit = static function (string $text, int $w, string $size, int $max) use ($fonts): int {
            for ($mult = $max; $mult > 1; $mult--) {
                if (strlen($text) * $fonts[$size][1] * $mult <= $w) {
                    return $mult;
                }
            }
            return 1;
        };
        $rows = [];
        /** Texto en un bloque [x, x+w] alineado L/C; se recorta si no cabe. Devuelve el alto usado. */
        $text = function (int $x, int $y, int $w, string $align, string $size, int $mult, string $value) use ($lang, $fonts, &$rows): int {
            [$font, $cw, $ch] = $fonts[$size];
            $value = substr($value, 0, max(1, intdiv($w, $cw * $mult)));
            if ($value === '') {
                return 0;
            }
            $tx = $align === 'C' ? $x + max(0, intdiv($w - strlen($value) * $cw * $mult, 2)) : $x;
            if ($lang === 'tspl') {
                $rows[] = 'TEXT ' . $tx . ',' . $y . ',"' . $font . '",0,' . $mult . ',' . $mult . ',"' . $value . '"';
            } elseif ($lang === 'epl') {
                $rows[] = 'A' . $tx . ',' . $y . ',0,' . $font . ',' . $mult . ',' . $mult . ',N,"' . $value . '"';
            } else {
                $rows[] = '^FO' . $x . ',' . $y . '^FB' . $w . ',1,0,' . $align . ',0^A0N,' . ($ch * $mult) . ',' . ($cw * $mult) . '^FD' . $value . '^FS';
            }
            return $ch * $mult;
        };
        $line = function (int $x, int $y, int $w, int $h) use ($lang, &$rows): void {
            $rows[] = $lang === 'tspl' ? "BAR $x,$y,$w,$h" : ($lang === 'epl' ? "LO$x,$y,$w,$h" : "^FO$x,$y^GB$w,$h,$h^FS");
        };

        // Cabecera, código de barras y número.
        $text(0, 8, 812, 'C', 'l', 1, 'UNIBAG CHILE');
        $text(20, 46, 772, 'C', 'm', 1, $wh);
        $barY = 78;
        $rows[] = $lang === 'tspl' ? $this->tsplBarcode($code, $barY) : ($lang === 'epl' ? $this->eplBarcode($code, $barY) : $this->zplBarcode($code, $barY));
        $text(20, $barY + self::BARCODE_HEIGHT_DOTS + 6, 772, 'C', 'l', $fit($code, 772, 'l', 2), $code);
        $line(20, 252, 772, 3);

        // Columna izquierda: SKU, descripción (hasta 3 líneas) y referencia.
        $y = $top;
        $y += $text($L, $y, $LW, 'L', 'l', $fit($sku, $LW, 'l', 2), $sku) + 8;
        $perLine = intdiv($LW, $fonts['m'][1]);
        $refLines = $ref !== '' ? array_slice(explode("\n", wordwrap($ref, $perLine, "\n", true)), 0, 2) : [];
        $descLines = $desc !== '' ? array_slice(explode("\n", wordwrap($desc, $perLine, "\n", true)), 0, 4 - count($refLines)) : [];
        foreach ($descLines as $d) {
            $y += $text($L, $y, $LW, 'L', 'm', 1, $d) + 3;
        }
        // La referencia (CC, lote, cajas) va al pie de la columna, alineada con el fondo del recuadro de cantidad.
        $refY = $boxBottom - 4 - count($refLines) * ($fonts['m'][2] + 3);
        foreach ($refLines as $i => $r) {
            $text($L, max($y + 4, $refY) + $i * ($fonts['m'][2] + 3), $LW, 'L', 'm', 1, $r);
        }

        // Recuadro de cantidad.
        if ($lang === 'tspl') {
            $rows[] = 'BOX ' . $BX . ',' . $top . ',' . ($BX + $BW) . ',' . $boxBottom . ',4';
        } elseif ($lang === 'epl') {
            $rows[] = 'X' . $BX . ',' . $top . ',4,' . ($BX + $BW) . ',' . $boxBottom;
        } else {
            $rows[] = '^FO' . $BX . ',' . $top . '^GB' . $BW . ',' . ($boxBottom - $top) . ',4^FS';
        }
        $text($BX, $top + 10, $BW, 'C', 'm', 1, 'CANTIDAD');
        $qMult = $fit($qty, $BW - 24, 'xl', 2);
        $qHeight = $fonts['xl'][2] * $qMult;
        $text($BX + 4, $top + 40 + intdiv(96 - $qHeight, 2), $BW - 8, 'C', 'xl', $qMult, $qty);
        $text($BX + 4, $top + 144, $BW - 8, 'C', 'l', 1, $unit);
        if ($pkgLine !== '') {
            $line($BX + 14, $top + 184, $BW - 28, 2);
            $text($BX + 4, $top + 196, $BW - 8, 'C', 'm', 1, $pkgLine);
        }

        // Pie: fecha y operador.
        $line(20, 508, 772, 2);
        $text(20, 520, 772, 'C', 's', 1, $meta);

        if ($lang === 'tspl') {
            return $this->buildTsplHeader() . "\r\n" . implode("\r\n", $rows) . "\r\nPRINT 1,1";
        }
        if ($lang === 'epl') {
            return implode("\r\n", ['N', 'q812', 'Q560,24', ...$this->buildEplDeviceSettings(), ...$rows, 'P1']) . "\r\n";
        }
        return "^XA\n^CI28\n^PW812\n^LL560\n^PR3\n^MD" . $this->getZplDarkness() . "\n" . implode("\n", $rows) . "\n^XZ";
    }

    private function buildZpl(array $roll): string
    {
        $id = (string)($roll['id'] ?? '');
        $rollCode = trim((string)($roll['roll_code'] ?? ''));
        $barcodeValue = $this->z($rollCode !== '' ? $rollCode : $id);

        $sku = $this->z((string)($roll['sku_code'] ?? '-'));
        $product = $this->z((string)($roll['sku_description'] ?? '-'));
        $arrivalDate = $this->resolveRollDate($roll);

        $warehouseCode = $this->z((string)($roll['warehouse_code'] ?? '-'));
        $warehouseName = $this->z((string)($roll['warehouse_name'] ?? ''));

        $noWeight = !empty($roll['no_weight']) || (isset($roll['weight_str']) && trim((string)$roll['weight_str']) === '-');
        $weightStr = $this->rollWeightText($roll, $noWeight);

        $metersVal = (float)($roll['meters'] ?? 0);
        $metersStr = $metersVal > 0 ? number_format($metersVal, 0, ',', '.') . ' m' : '-';

        $widthMm = (float)($roll['width_mm'] ?? 0);
        $gramsVal = (float)($roll['grams'] ?? $roll['microns'] ?? 0);
        $specsStr = '';
        if ($widthMm > 0) {
            $specsStr .= round($widthMm) . 'mm';
        }
        if ($gramsVal > 0) {
            $specsStr .= ($specsStr !== '' ? ' / ' : '') . round($gramsVal) . 'g';
        }
        if ($specsStr === '') {
            $specsStr = '-';
        }
        $specsStr = $this->z($specsStr);

        $color = $this->z(trim((string)($roll['color'] ?? '')) ?: '-');
        $poCode = $this->z(trim((string)($roll['po_code'] ?? '')) ?: '-');
        $containerCode = $this->z(trim((string)($roll['container_code'] ?? '')) ?: '-');
        $supplierName = $this->z(trim((string)($roll['supplier_name'] ?? '')) ?: '-');
        $processStage = $this->z(strtoupper(trim((string)($roll['process_stage'] ?? 'RAW'))));

        return "^XA
^CI28
^PW812
^LL560
^LH0,0
^PR3
^MD" . $this->getZplDarkness() . "

^CF0,30
^FO25,20^FDUNIBAG CHILE^FS
^CF0,20
^FO25,56^FDRECEPCION MATERIA PRIMA^FS

^CF0,22
^FO440,20^FDBODEGA: {$warehouseCode}^FS
^CF0,20
^FO440,52^FDFECHA: {$arrivalDate}^FS

^FO18,80^GB776,2,2^FS

^CF0,22
^FO24,92^FDSKU:^FS
^CF0,42
^FO102,86^FD{$sku}^FS

^CF0,24
^FO24,134^FB770,2,3,L,0^FD{$product}^FS

^FO18,196^GB776,1,1^FS

^CF0,18
^FO24,206^FDPESO REAL:^FS
^CF0,34
^FO24,228^FD{$weightStr}^FS

^CF0,18
^FO278,206^FDMETROS:^FS
^CF0,28
^FO278,230^FD{$metersStr}^FS

^CF0,18
^FO500,206^FDANCHO / GR:^FS
^CF0,28
^FO500,230^FD{$specsStr}^FS

^CF0,18
^FO666,206^FDCOLOR:^FS
^CF0,26
^FO666,230^FD{$color}^FS

^FO18,272^GB776,1,1^FS

^CF0,18
^FO24,282^FDORDEN COMPRA: {$poCode}^FS
^CF0,18
^FO392,282^FDCONTENEDOR: {$containerCode}^FS
^CF0,17
^FO24,308^FB770,1,0,L,0^FDPROVEEDOR: {$supplierName}^FS

^FO18,336^GB776,1,1^FS

" . $this->zplBarcode($barcodeValue, 350) . "

^CF0,30
^FO0,462^FB812,1,0,C,0^FD{$barcodeValue}^FS

^CF0,17
^FO0,506^FB812,1,0,C,0^FDETAPA: {$processStage}  |  CONTROL DE CALIDAD UNIBAG^FS

^XZ";
    }

    private function buildTsplHeader(): string
    {
        $widthMm = number_format($this->getLabelWidthMm(), 2, '.', '');
        $heightMm = number_format($this->getLabelHeightMm(), 2, '.', '');
        $gapMm = number_format($this->getLabelGapMm(), 2, '.', '');
        $direction = $this->getPrintDirection();
        $refX = $this->getReferenceXDots();
        $refY = $this->getReferenceYDots();

        return implode("\r\n", [
            'SIZE ' . $widthMm . ' mm,' . $heightMm . ' mm',
            'GAP ' . $gapMm . ' mm,0 mm',
            'DIRECTION ' . $direction,
            'REFERENCE ' . $refX . ',' . $refY,
            'SPEED ' . number_format($this->getTsplSpeed(), 1, '.', ''),
            'DENSITY ' . $this->getTsplDensity(),
            'CLS',
        ]);
    }

    private function buildRollTspl(array $roll): string
    {
        $id = (string)($roll['id'] ?? '');
        $rollCode = trim((string)($roll['roll_code'] ?? ''));
        $barcodeValue = $this->t($rollCode !== '' ? $rollCode : $id);
        $sku = $this->t((string)($roll['sku_code'] ?? '-'));
        $product = $this->t((string)($roll['sku_description'] ?? '-'));
        $arrivalDate = $this->t($this->resolveRollDate($roll));
        $warehouseCode = $this->t((string)($roll['warehouse_code'] ?? '-'));
        $noWeight = !empty($roll['no_weight']) || (isset($roll['weight_str']) && trim((string)$roll['weight_str']) === '-');
        $weightStr = $this->rollWeightText($roll, $noWeight);
        $metersVal = (float)($roll['meters'] ?? 0);
        $metersStr = $metersVal > 0 ? number_format($metersVal, 0, ',', '.') . ' m' : '-';
        $widthMm = (float)($roll['width_mm'] ?? 0);
        $gramsVal = (float)($roll['grams'] ?? $roll['microns'] ?? 0);
        $specsStr = '';
        if ($widthMm > 0) {
            $specsStr .= round($widthMm) . 'mm';
        }
        if ($gramsVal > 0) {
            $specsStr .= ($specsStr !== '' ? ' / ' : '') . round($gramsVal) . 'g';
        }
        if ($specsStr === '') {
            $specsStr = '-';
        }
        $color = $this->t(trim((string)($roll['color'] ?? '')) ?: '-');
        $poCode = $this->t(trim((string)($roll['po_code'] ?? '')) ?: '-');
        $containerCode = $this->t(trim((string)($roll['container_code'] ?? '')) ?: '-');
        $supplierName = $this->t(trim((string)($roll['supplier_name'] ?? '')) ?: '-');
        $processStage = $this->t(strtoupper(trim((string)($roll['process_stage'] ?? 'RAW'))));

        return $this->buildTsplHeader() . "\r\n"
            . 'BAR 18,80,776,2' . "\r\n"
            . 'BAR 18,196,776,1' . "\r\n"
            . 'BAR 18,272,776,1' . "\r\n"
            . 'BAR 18,336,776,1' . "\r\n"
            . 'TEXT 24,18,"4",0,1,1,"UNIBAG CHILE"' . "\r\n"
            . 'TEXT 24,52,"3",0,1,1,"RECEPCION MATERIA PRIMA"' . "\r\n"
            . 'TEXT 450,18,"3",0,1,1,"BODEGA: ' . $warehouseCode . '"' . "\r\n"
            . 'TEXT 450,50,"3",0,1,1,"FECHA: ' . $arrivalDate . '"' . "\r\n"
            . 'TEXT 24,94,"3",0,1,1,"SKU:"' . "\r\n"
            . 'TEXT 102,86,"5",0,1,1,"' . $sku . '"' . "\r\n"
            . 'TEXT 24,136,"3",0,1,1,"' . $product . '"' . "\r\n"
            . 'TEXT 24,206,"2",0,1,1,"PESO REAL:"' . "\r\n"
            . 'TEXT 24,228,"4",0,1,1,"' . $this->t($weightStr) . '"' . "\r\n"
            . 'TEXT 278,206,"2",0,1,1,"METROS:"' . "\r\n"
            . 'TEXT 278,230,"3",0,1,1,"' . $this->t($metersStr) . '"' . "\r\n"
            . 'TEXT 500,206,"2",0,1,1,"ANCHO / GR:"' . "\r\n"
            . 'TEXT 500,230,"3",0,1,1,"' . $this->t($specsStr) . '"' . "\r\n"
            . 'TEXT 666,206,"2",0,1,1,"COLOR:"' . "\r\n"
            . 'TEXT 666,230,"3",0,1,1,"' . $color . '"' . "\r\n"
            . 'TEXT 24,282,"2",0,1,1,"ORDEN COMPRA: ' . $poCode . '"' . "\r\n"
            . 'TEXT 392,282,"2",0,1,1,"CONTENEDOR: ' . $containerCode . '"' . "\r\n"
            . 'TEXT 24,308,"2",0,1,1,"PROVEEDOR: ' . $supplierName . '"' . "\r\n"
            . $this->tsplBarcode($barcodeValue, 350) . "\r\n"
            . 'TEXT ' . $this->tsplCenterX($barcodeValue, 4) . ',466,"4",0,1,1,"' . $barcodeValue . '"' . "\r\n"
            . 'TEXT 120,510,"2",0,1,1,"ETAPA: ' . $processStage . ' | CONTROL DE CALIDAD UNIBAG"' . "\r\n"
            . 'PRINT 1,1';
    }

    private function buildRollEpl(array $roll): string
    {
        $id = (string)($roll['id'] ?? '');
        $rollCode = trim((string)($roll['roll_code'] ?? ''));
        $barcodeValue = strtoupper($this->t($rollCode !== '' ? $rollCode : $id));
        $sku = strtoupper($this->t((string)($roll['sku_code'] ?? '-')));
        $product = strtoupper($this->t((string)($roll['sku_description'] ?? '-')));
        $arrivalDate = strtoupper($this->t($this->resolveRollDate($roll)));
        $warehouseCode = strtoupper($this->t((string)($roll['warehouse_code'] ?? '-')));
        $noWeight = !empty($roll['no_weight']) || (isset($roll['weight_str']) && trim((string)$roll['weight_str']) === '-');
        $weightStr = strtoupper($this->t($this->rollWeightText($roll, $noWeight)));
        $metersVal = (float)($roll['meters'] ?? 0);
        $metersStr = strtoupper($this->t($metersVal > 0 ? number_format($metersVal, 0, ',', '.') . ' M' : '-'));
        $widthMm = (float)($roll['width_mm'] ?? 0);
        $gramsVal = (float)($roll['grams'] ?? $roll['microns'] ?? 0);
        $specsStr = '';
        if ($widthMm > 0) {
            $specsStr .= round($widthMm) . 'MM';
        }
        if ($gramsVal > 0) {
            $specsStr .= ($specsStr !== '' ? ' / ' : '') . round($gramsVal) . 'G';
        }
        if ($specsStr === '') {
            $specsStr = '-';
        }
        $specsStr = strtoupper($this->t($specsStr));
        $color = strtoupper($this->t(trim((string)($roll['color'] ?? '')) ?: '-'));
        $poCode = strtoupper($this->t(trim((string)($roll['po_code'] ?? '')) ?: '-'));
        $containerCode = strtoupper($this->t(trim((string)($roll['container_code'] ?? '')) ?: '-'));
        $supplierName = strtoupper($this->t(trim((string)($roll['supplier_name'] ?? '')) ?: '-'));
        $processStage = strtoupper($this->t(trim((string)($roll['process_stage'] ?? 'RAW'))));

        return implode("\r\n", [
            'N',
            'q812',
            'Q560,24',
            ...$this->buildEplDeviceSettings(),
            'LO18,80,776,2',
            'LO18,196,776,1',
            'LO18,272,776,1',
            'LO18,336,776,1',
            'A24,18,0,4,1,1,N,"UNIBAG CHILE"',
            'A24,50,0,3,1,1,N,"RECEPCION MATERIA PRIMA"',
            'A450,18,0,3,1,1,N,"BOD: ' . $warehouseCode . '"',
            'A450,46,0,3,1,1,N,"FECHA: ' . $arrivalDate . '"',
            'A24,92,0,3,1,1,N,"SKU: ' . $sku . '"',
            'A24,132,0,3,1,1,N,"' . $product . '"',
            'A24,206,0,2,1,1,N,"PESO REAL:"',
            'A24,230,0,4,1,1,N,"' . $weightStr . '"',
            'A284,206,0,2,1,1,N,"METROS:"',
            'A284,230,0,3,1,1,N,"' . $metersStr . '"',
            'A500,206,0,2,1,1,N,"ANCHO / GR:"',
            'A500,230,0,3,1,1,N,"' . $specsStr . '"',
            'A666,206,0,2,1,1,N,"COLOR:"',
            'A666,230,0,3,1,1,N,"' . $color . '"',
            'A24,282,0,2,1,1,N,"OC: ' . $poCode . '"',
            'A392,282,0,2,1,1,N,"CONT: ' . $containerCode . '"',
            'A24,308,0,2,1,1,N,"PROV: ' . $supplierName . '"',
            $this->eplBarcode($barcodeValue, 350),
            'A' . $this->eplCenterX($barcodeValue, 4) . ',468,0,4,1,1,N,"' . $barcodeValue . '"',
            'A120,510,0,2,1,1,N,"ETAPA: ' . $processStage . ' | CONTROL DE CALIDAD UNIBAG"',
            'P1',
        ]) . "\r\n";
    }

    private function buildWorkOrderBoxZpl(array $workOrder, ?array $finish = null): string
    {
        $otCode = $this->z((string)($workOrder['ot_code'] ?? ''));
        $product = $this->z((string)($workOrder['sku_final'] ?? ''));
        $boxQty = $this->z((string)($finish['box_qty'] ?? ''));
        $operator = $this->z((string)($finish['operator_name'] ?? ''));
        $finishedAt = $this->z((string)($finish['created_at'] ?? ''));
        $barcodeValue = $otCode !== '' ? $otCode : 'OT';

        return "^XA
^CI28
^PW812
^LL560
^PR3
^MD" . $this->getZplDarkness() . "
^CF0,32
^FO20,20^FDUNIBAG^FS
^CF0,22
^FO20,58^FDCAJA PRODUCTO TERMINADO^FS
^CF0,34
^FO20,92^FB770,2,0,L,0^FD{$product}^FS
^FO20,146^GB772,2,2^FS

^CF0,24
^FO20,166^FDOT: {$otCode}^FS
^FO20,194^FDFECHA CIERRE: {$finishedAt}^FS
^FO20,222^FDOPERADOR: {$operator}^FS
^FO20,250^FDCANTIDAD CAJAS: {$boxQty}^FS

" . $this->zplBarcode($barcodeValue, 308) . "
^CF0,32
^FO0,418^FB812,1,0,C,0^FD{$barcodeValue}^FS
^XZ";
    }

    private function buildBoxZpl(array $box): string
    {
        $boxCode = $this->z((string)($box['box_code'] ?? ''));
        $otCode = $this->z((string)($box['ot_code'] ?? ''));
        $product = $this->z((string)($box['final_sku'] ?? ''));
        $sourceRoll = $this->z((string)($box['source_roll_code'] ?? ''));
        $units = $this->z((string)($box['units_initial'] ?? $box['units_qty'] ?? ''));
        $palletCode = $this->z((string)($box['pallet_code'] ?? ''));
        $operator = $this->z((string)($box['operator_name'] ?? ''));
        $destinationMode = $this->z((string)($box['destination_mode'] ?? ''));
        $customerOrderRef = $this->z((string)($box['customer_order_ref'] ?? ''));
        $warehouseCode = $this->z((string)($box['warehouse_code'] ?? ''));

        $destinationLabel = $destinationMode === 'CUSTOMER_ORDER'
            ? ('OC cliente: ' . ($customerOrderRef !== '' ? $customerOrderRef : '-'))
            : ('Stock bodega: ' . ($warehouseCode !== '' ? $warehouseCode : '-'));
        $destinationLabel = $this->z($destinationLabel);
        // Cajas de bolsas: no tienen OT, sí CC.
        $otOrCc = $otCode === '' && $customerOrderRef !== '' ? 'CC: ' . $customerOrderRef : 'OT: ' . $otCode;

        return "^XA
^CI28
^PW812
^LL560
^PR3
^MD" . $this->getZplDarkness() . "
^CF0,32
^FO20,20^FDUNIBAG^FS
^CF0,22
^FO20,58^FDCAJA TRAZABLE^FS
^CF0,34
^FO20,92^FB770,2,0,L,0^FD{$product}^FS
^FO20,146^GB772,2,2^FS

^CF0,24
^FO20,166^FDCAJA: {$boxCode}^FS
^FO20,194^FD{$otOrCc}^FS
^FO20,222^FDBOBINA ORIGEN: {$sourceRoll}^FS
^FO20,250^FDUNIDADES: {$units}^FS
^FO20,278^FDPALLET: {$palletCode}^FS
^FO20,306^FDOPERADOR: {$operator}^FS
^FO20,334^FDDESTINO: {$destinationLabel}^FS

" . $this->zplBarcode($boxCode, 404) . "
^CF0,32
^FO0,514^FB812,1,0,C,0^FD{$boxCode}^FS
^XZ";
    }

    private function buildPalletZpl(array $pallet, array $boxes = []): string
    {
        $palletCode = $this->z((string)($pallet['pallet_code'] ?? ''));
        $otCode = $this->z((string)($pallet['ot_code'] ?? ''));
        $product = $this->z((string)($pallet['final_sku'] ?? ''));
        $sourceRoll = $this->z((string)($pallet['source_roll_code'] ?? ''));
        $boxCount = $this->z((string)($pallet['box_count'] ?? ''));
        $operator = $this->z((string)($pallet['operator_name'] ?? ''));
        $destinationMode = $this->z((string)($pallet['destination_mode'] ?? ''));
        $customerOrderRef = $this->z((string)($pallet['customer_order_ref'] ?? ''));
        $warehouseCode = $this->z((string)($pallet['warehouse_code'] ?? ''));

        $boxCodes = [];
        foreach ($boxes as $box) {
            $code = trim((string)($box['box_code'] ?? ''));
            if ($code !== '') {
                $boxCodes[] = $code;
            }
            if (count($boxCodes) >= 4) {
                break;
            }
        }
        $boxSummary = $boxCodes === [] ? '-' : implode(', ', $boxCodes);
        if (count($boxes) > count($boxCodes)) {
            $boxSummary .= '...';
        }
        $boxSummary = $this->z($boxSummary);

        $destinationLabel = $destinationMode === 'CUSTOMER_ORDER'
            ? ('OC cliente: ' . ($customerOrderRef !== '' ? $customerOrderRef : '-'))
            : ('Stock bodega: ' . ($warehouseCode !== '' ? $warehouseCode : '-'));
        $destinationLabel = $this->z($destinationLabel);

        return "^XA
^CI28
^PW812
^LL560
^PR3
^MD" . $this->getZplDarkness() . "
^CF0,32
^FO20,20^FDUNIBAG^FS
^CF0,22
^FO20,58^FDPALLET TRAZABLE^FS
^CF0,34
^FO20,92^FB770,2,0,L,0^FD{$product}^FS
^FO20,146^GB772,2,2^FS

^CF0,24
^FO20,166^FDPALLET: {$palletCode}^FS
^FO20,194^FDOT: {$otCode}^FS
^FO20,222^FDBOBINA ORIGEN: {$sourceRoll}^FS
^FO20,250^FDCAJAS: {$boxCount}^FS
^FO20,278^FDOPERADOR: {$operator}^FS
^FO20,306^FDDESTINO: {$destinationLabel}^FS
^FO20,334^FB770,2,0,L,0^FDCODIGOS CAJA: {$boxSummary}^FS

" . $this->zplBarcode($palletCode, 388) . "
^CF0,32
^FO0,496^FB812,1,0,C,0^FD{$palletCode}^FS
^XZ";
    }

    private function buildWorkOrderBoxTspl(array $workOrder, ?array $finish = null): string
    {
        $otCode = $this->t((string)($workOrder['ot_code'] ?? ''));
        $product = $this->t((string)($workOrder['sku_final'] ?? ''));
        $boxQty = $this->t((string)($finish['box_qty'] ?? ''));
        $operator = $this->t((string)($finish['operator_name'] ?? ''));
        $finishedAt = $this->t((string)($finish['created_at'] ?? ''));
        $barcodeValue = $otCode !== '' ? $otCode : 'OT';

        return $this->buildTsplHeader() . "\r\n"
            . 'BAR 20,146,560,2' . "\r\n"
            . 'TEXT 20,20,"3",0,1,1,"UNIBAG"' . "\r\n"
            . 'TEXT 20,52,"2",0,1,1,"CAJA PRODUCTO TERMINADO"' . "\r\n"
            . 'TEXT 20,90,"3",0,1,1,"' . $product . '"' . "\r\n"
            . 'TEXT 20,166,"2",0,1,1,"OT: ' . $otCode . '"' . "\r\n"
            . 'TEXT 20,194,"2",0,1,1,"FECHA CIERRE: ' . $finishedAt . '"' . "\r\n"
            . 'TEXT 20,222,"2",0,1,1,"OPERADOR: ' . $operator . '"' . "\r\n"
            . 'TEXT 20,250,"2",0,1,1,"CANTIDAD CAJAS: ' . $boxQty . '"' . "\r\n"
            . $this->tsplBarcode($barcodeValue, 308) . "\r\n"
            . 'TEXT ' . $this->tsplCenterX($barcodeValue, 4) . ',418,"4",0,1,1,"' . $barcodeValue . '"' . "\r\n"
            . 'PRINT 1,1';
    }

    private function buildBoxTspl(array $box): string
    {
        $boxCode = $this->t((string)($box['box_code'] ?? ''));
        $otCode = $this->t((string)($box['ot_code'] ?? ''));
        $product = $this->t((string)($box['final_sku'] ?? ''));
        $sourceRoll = $this->t((string)($box['source_roll_code'] ?? ''));
        $units = $this->t((string)($box['units_initial'] ?? $box['units_qty'] ?? ''));
        $palletCode = $this->t((string)($box['pallet_code'] ?? ''));
        $operator = $this->t((string)($box['operator_name'] ?? ''));
        $destinationMode = $this->t((string)($box['destination_mode'] ?? ''));
        $customerOrderRef = $this->t((string)($box['customer_order_ref'] ?? ''));
        $warehouseCode = $this->t((string)($box['warehouse_code'] ?? ''));
        $destinationLabel = $destinationMode === 'CUSTOMER_ORDER'
            ? ('OC cliente: ' . ($customerOrderRef !== '' ? $customerOrderRef : '-'))
            : ('Stock bodega: ' . ($warehouseCode !== '' ? $warehouseCode : '-'));
        $destinationLabel = $this->t($destinationLabel);

        return $this->buildTsplHeader() . "\r\n"
            . 'BAR 20,146,560,2' . "\r\n"
            . 'TEXT 20,20,"3",0,1,1,"UNIBAG"' . "\r\n"
            . 'TEXT 20,52,"2",0,1,1,"CAJA TRAZABLE"' . "\r\n"
            . 'TEXT 20,90,"3",0,1,1,"' . $product . '"' . "\r\n"
            . 'TEXT 20,166,"2",0,1,1,"CAJA: ' . $boxCode . '"' . "\r\n"
            . 'TEXT 20,194,"2",0,1,1,"OT: ' . $otCode . '"' . "\r\n"
            . 'TEXT 20,222,"2",0,1,1,"BOBINA ORIGEN: ' . $sourceRoll . '"' . "\r\n"
            . 'TEXT 20,250,"2",0,1,1,"UNIDADES: ' . $units . '"' . "\r\n"
            . 'TEXT 20,278,"2",0,1,1,"PALLET: ' . $palletCode . '"' . "\r\n"
            . 'TEXT 20,306,"2",0,1,1,"OPERADOR: ' . $operator . '"' . "\r\n"
            . 'TEXT 20,334,"2",0,1,1,"DESTINO: ' . $destinationLabel . '"' . "\r\n"
            . $this->tsplBarcode($boxCode, 404) . "\r\n"
            . 'TEXT ' . $this->tsplCenterX($boxCode, 4) . ',514,"4",0,1,1,"' . $boxCode . '"' . "\r\n"
            . 'PRINT 1,1';
    }

    private function buildPalletTspl(array $pallet, array $boxes = []): string
    {
        $palletCode = $this->t((string)($pallet['pallet_code'] ?? ''));
        $otCode = $this->t((string)($pallet['ot_code'] ?? ''));
        $product = $this->t((string)($pallet['final_sku'] ?? ''));
        $sourceRoll = $this->t((string)($pallet['source_roll_code'] ?? ''));
        $boxCount = $this->t((string)($pallet['box_count'] ?? ''));
        $operator = $this->t((string)($pallet['operator_name'] ?? ''));
        $destinationMode = $this->t((string)($pallet['destination_mode'] ?? ''));
        $customerOrderRef = $this->t((string)($pallet['customer_order_ref'] ?? ''));
        $warehouseCode = $this->t((string)($pallet['warehouse_code'] ?? ''));
        $boxCodes = [];
        foreach ($boxes as $box) {
            $code = trim((string)($box['box_code'] ?? ''));
            if ($code !== '') {
                $boxCodes[] = $code;
            }
            if (count($boxCodes) >= 4) {
                break;
            }
        }
        $boxSummary = $boxCodes === [] ? '-' : implode(', ', $boxCodes);
        if (count($boxes) > count($boxCodes)) {
            $boxSummary .= '...';
        }
        $boxSummary = $this->t($boxSummary);
        $destinationLabel = $destinationMode === 'CUSTOMER_ORDER'
            ? ('OC cliente: ' . ($customerOrderRef !== '' ? $customerOrderRef : '-'))
            : ('Stock bodega: ' . ($warehouseCode !== '' ? $warehouseCode : '-'));
        $destinationLabel = $this->t($destinationLabel);

        return $this->buildTsplHeader() . "\r\n"
            . 'BAR 20,146,560,2' . "\r\n"
            . 'TEXT 20,20,"3",0,1,1,"UNIBAG"' . "\r\n"
            . 'TEXT 20,52,"2",0,1,1,"PALLET TRAZABLE"' . "\r\n"
            . 'TEXT 20,90,"3",0,1,1,"' . $product . '"' . "\r\n"
            . 'TEXT 20,166,"2",0,1,1,"PALLET: ' . $palletCode . '"' . "\r\n"
            . 'TEXT 20,194,"2",0,1,1,"OT: ' . $otCode . '"' . "\r\n"
            . 'TEXT 20,222,"2",0,1,1,"BOBINA ORIGEN: ' . $sourceRoll . '"' . "\r\n"
            . 'TEXT 20,250,"2",0,1,1,"CAJAS: ' . $boxCount . '"' . "\r\n"
            . 'TEXT 20,278,"2",0,1,1,"OPERADOR: ' . $operator . '"' . "\r\n"
            . 'TEXT 20,306,"2",0,1,1,"DESTINO: ' . $destinationLabel . '"' . "\r\n"
            . 'TEXT 20,334,"2",0,1,1,"CODIGOS CAJA: ' . $boxSummary . '"' . "\r\n"
            . $this->tsplBarcode($palletCode, 410) . "\r\n"
            . 'TEXT ' . $this->tsplCenterX($palletCode, 4) . ',524,"4",0,1,1,"' . $palletCode . '"' . "\r\n"
            . 'PRINT 1,1';
    }

    private function buildWorkOrderBoxEpl(array $workOrder, ?array $finish = null): string
    {
        $otCode = strtoupper($this->t((string)($workOrder['ot_code'] ?? '')));
        $product = strtoupper($this->t((string)($workOrder['sku_final'] ?? '')));
        $boxQty = strtoupper($this->t((string)($finish['box_qty'] ?? '')));
        $operator = strtoupper($this->t((string)($finish['operator_name'] ?? '')));
        $finishedAt = strtoupper($this->t((string)($finish['created_at'] ?? '')));
        $barcodeValue = $otCode !== '' ? $otCode : 'OT';

        return implode("\r\n", [
            'N',
            'q812',
            'Q560,24',
            ...$this->buildEplDeviceSettings(),
            'LO20,68,750,2',
            'LO20,136,750,1',
            'LO20,276,750,1',
            'A20,18,0,3,1,1,N,"UNIBAG"',
            'A20,44,0,2,1,1,N,"CAJA PRODUCTO TERMINADO"',
            'A20,78,0,4,1,1,N,"' . $product . '"',
            'A20,152,0,3,1,1,N,"OT: ' . $otCode . '"',
            'A380,152,0,3,1,1,N,"CANTIDAD CAJAS: ' . $boxQty . '"',
            'A20,192,0,3,1,1,N,"FECHA CIERRE: ' . $finishedAt . '"',
            'A20,232,0,3,1,1,N,"OPERADOR: ' . $operator . '"',
            $this->eplBarcode($barcodeValue, 364),
            'A' . $this->eplCenterX($barcodeValue, 4, 2) . ',472,0,4,2,2,N,"' . $barcodeValue . '"',
            'P1',
        ]) . "\r\n";
    }

    private function buildBoxEpl(array $box): string
    {
        $boxCode = strtoupper($this->t((string)($box['box_code'] ?? '')));
        $otCode = strtoupper($this->t((string)($box['ot_code'] ?? '-')));
        $product = strtoupper($this->t((string)($box['final_sku'] ?? '')));
        $sourceRoll = strtoupper($this->t((string)($box['source_roll_code'] ?? '-')));
        $units = strtoupper($this->t((string)($box['units_initial'] ?? $box['units_qty'] ?? '0')));
        $palletCode = strtoupper($this->t((string)($box['pallet_code'] ?? '-')));
        $operator = strtoupper($this->t((string)($box['operator_name'] ?? '-')));
        $destinationMode = (string)($box['destination_mode'] ?? '');
        $customerOrderRef = (string)($box['customer_order_ref'] ?? '');
        $warehouseCode = (string)($box['warehouse_code'] ?? '');
        $destinationLabel = strtoupper($this->t(
            $destinationMode === 'CUSTOMER_ORDER'
                ? ('OC CLIENTE: ' . ($customerOrderRef !== '' ? $customerOrderRef : '-'))
                : ('BODEGA: ' . ($warehouseCode !== '' ? $warehouseCode : '-'))
        ));

        return implode("\r\n", [
            'N',
            'q812',
            'Q560,24',
            ...$this->buildEplDeviceSettings(),
            'LO20,68,750,2',
            'LO20,136,750,1',
            'LO20,286,750,1',
            'A20,18,0,3,1,1,N,"UNIBAG"',
            'A20,44,0,2,1,1,N,"CAJA TRAZABLE"',
            'A20,78,0,4,1,1,N,"' . $product . '"',
            'A20,150,0,2,1,1,N,"CAJA: ' . $boxCode . '"',
            'A380,150,0,2,1,1,N,"OT: ' . $otCode . '"',
            'A20,182,0,2,1,1,N,"BOBINA ORIGEN: ' . $sourceRoll . '"',
            'A380,182,0,2,1,1,N,"UNIDADES: ' . $units . '"',
            'A20,214,0,2,1,1,N,"PALLET: ' . $palletCode . '"',
            'A380,214,0,2,1,1,N,"OPERADOR: ' . $operator . '"',
            'A20,246,0,2,1,1,N,"DESTINO: ' . $destinationLabel . '"',
            $this->eplBarcode($boxCode, 364),
            'A' . $this->eplCenterX($boxCode, 4, 2) . ',474,0,4,2,2,N,"' . $boxCode . '"',
            'P1',
        ]) . "\r\n";
    }

    private function buildPalletEpl(array $pallet, array $boxes = []): string
    {
        $palletCode = strtoupper($this->t((string)($pallet['pallet_code'] ?? '')));
        $otCode = strtoupper($this->t((string)($pallet['ot_code'] ?? '-')));
        $product = strtoupper($this->t((string)($pallet['final_sku'] ?? '')));
        $sourceRoll = strtoupper($this->t((string)($pallet['source_roll_code'] ?? '-')));
        $boxCount = strtoupper($this->t((string)($pallet['box_count'] ?? '0')));
        $operator = strtoupper($this->t((string)($pallet['operator_name'] ?? '-')));
        $destinationMode = (string)($pallet['destination_mode'] ?? '');
        $customerOrderRef = (string)($pallet['customer_order_ref'] ?? '');
        $warehouseCode = (string)($pallet['warehouse_code'] ?? '');
        $destinationLabel = strtoupper($this->t(
            $destinationMode === 'CUSTOMER_ORDER'
                ? ('OC CLIENTE: ' . ($customerOrderRef !== '' ? $customerOrderRef : '-'))
                : ('BODEGA: ' . ($warehouseCode !== '' ? $warehouseCode : '-'))
        ));

        $boxCodes = [];
        foreach ($boxes as $box) {
            $code = trim((string)($box['box_code'] ?? ''));
            if ($code !== '') {
                $boxCodes[] = $code;
            }
            if (count($boxCodes) >= 4) {
                break;
            }
        }
        $boxSummary = $boxCodes === [] ? '-' : implode(', ', $boxCodes);
        if (count($boxes) > count($boxCodes)) {
            $boxSummary .= '...';
        }
        $boxSummary = strtoupper($this->t($boxSummary));

        return implode("\r\n", [
            'N',
            'q812',
            'Q560,24',
            ...$this->buildEplDeviceSettings(),
            'LO20,68,750,2',
            'LO20,136,750,1',
            'LO20,296,750,1',
            'A20,18,0,3,1,1,N,"UNIBAG"',
            'A20,44,0,2,1,1,N,"PALLET TRAZABLE"',
            'A20,78,0,4,1,1,N,"' . $product . '"',
            'A20,150,0,2,1,1,N,"PALLET: ' . $palletCode . '"',
            'A380,150,0,2,1,1,N,"OT: ' . $otCode . '"',
            'A20,182,0,2,1,1,N,"BOBINA ORIGEN: ' . $sourceRoll . '"',
            'A380,182,0,2,1,1,N,"CAJAS: ' . $boxCount . '"',
            'A20,214,0,2,1,1,N,"OPERADOR: ' . $operator . '"',
            'A380,214,0,2,1,1,N,"DESTINO: ' . $destinationLabel . '"',
            'A20,246,0,2,1,1,N,"CAJAS ORIGEN: ' . $boxSummary . '"',
            $this->eplBarcode($palletCode, 366),
            'A' . $this->eplCenterX($palletCode, 4, 2) . ',476,0,4,2,2,N,"' . $palletCode . '"',
            'P1',
        ]) . "\r\n";
    }
}
