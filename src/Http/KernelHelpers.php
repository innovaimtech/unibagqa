<?php

declare(strict_types=1);

// =============================================================================
// Kernel Helpers · Funciones globales de UI, Layout, CSRF, Permisos y Visor ERP
// =============================================================================

/**
 * Inyecta un selector de fechas unificado en español para todos los HTML
 * que aún usen <input type="date"> nativo, preservando el valor Y-m-d
 * enviado al backend pero mostrando dd/mm/yyyy al usuario.
 */
function unibagDatePickerAssetsHtml(): string
{
    // IMPORTANTE: esta función se ejecuta DENTRO del manejador de salida (ob_start de public/index.php).
    // PHP prohíbe abrir otro buffer ahí (Fatal: "Cannot use output buffering in output buffering
    // display handlers"), por eso NO se usa renderView(): la plantilla solo devuelve un string
    // (nowdoc), así que se incluye directamente y se guarda en memoria para no releer el archivo.
    static $cache = null;
    if ($cache === null) {
        $file = __DIR__ . '/../Views/layouts/datepicker_assets.php';
        $result = is_file($file) ? include $file : '';
        $cache = is_string($result) ? $result : '';
    }
    return $cache;
}

function unibagIsHtmlResponseHeaders(): bool
{
    $headers = array_map('strtolower', headers_list());
    foreach ($headers as $header) {
        if (str_starts_with($header, 'content-type:')) {
            if (
                !str_contains($header, 'text/html')
                && !str_contains($header, 'application/xhtml+xml')
            ) {
                return false;
            }
            if (
                str_contains($header, 'application/vnd.ms-excel')
                || str_contains($header, 'application/vnd.openxmlformats-officedocument')
                || str_contains($header, 'application/octet-stream')
            ) {
                return false;
            }
        }
    }
    return true;
}

function unibagInjectHardwareAssets(string $html): string
{
    if ($html === '' || str_contains($html, 'unibag-hardware.js')) {
        return $html;
    }
    if (!str_contains($html, '<html') || !str_contains($html, '<body')) {
        return $html;
    }
    if (!unibagIsHtmlResponseHeaders()) {
        return $html;
    }

    $hwConfig = [
        'scaleMode' => strtolower(trim((string)(class_exists('Env') ? (Env::get('SCALE_MODE', 'http') ?? 'http') : 'http'))),
        'printMode' => strtolower(trim((string)(class_exists('Env') ? (Env::get('PRINT_MODE', 'http') ?? 'http') : 'http'))),
        'printerName' => trim((string)(class_exists('Env') ? (Env::get('PRINT_PRINTER_NAME', '') ?? '') : '')),
        'printLanguage' => strtolower(trim((string)(class_exists('Env') ? (Env::get('PRINT_LANGUAGE', 'zpl') ?? 'zpl') : 'epl'))),
    ];
    if (class_exists('PrintService')) {
        $hwPrintService = new PrintService($_ENV);
        $hwConfig['printLanguage'] = $hwPrintService->getConfiguredPrintLanguage();
        $hwConfig['printDefaults'] = $hwPrintService->printTuningDefaults();
    }
    $queuedJobs = class_exists('PrintService') ? PrintService::drainClientJobs() : [];

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP;
    $cfgJson = json_encode($hwConfig, $jsonFlags) ?: '{}';
    $queueJson = json_encode($queuedJobs, $jsonFlags) ?: '[]';

    $assets = '<script id="unibag-hardware-config">window.__UNIBAG_HW_CONFIG__=' . $cfgJson . ';window.__UNIBAG_PRINT_QUEUE__=' . $queueJson . ';</script>'
        . '<script src="/assets/js/unibag-hardware.js?v=20261105b"></script>';

    $headPos = stripos($html, '</head>');
    if ($headPos !== false) {
        return substr_replace($html, $assets . PHP_EOL, $headPos, 0);
    }

    $bodyEndPos = stripos($html, '</body>');
    if ($bodyEndPos !== false) {
        return substr_replace($html, $assets . PHP_EOL, $bodyEndPos, 0);
    }

    return $html . PHP_EOL . $assets;
}

/**
 * Libera el bloqueo de la sesión en las páginas de solo lectura.
 *
 * PHP bloquea el archivo de sesión durante toda la solicitud: si el usuario hace clic en otro enlace mientras una
 * página pesada sigue calculando, la página nueva queda esperando a que la anterior termine. Con esto la navegación
 * ya no se encola. Lo que una página escriba en $_SESSION después de liberar se guarda al terminar la solicitud.
 *
 * No aplica a Producción ni a Recepción (imprimen etiquetas por GET y usan la cola de impresión de la sesión), ni
 * cuando hay etiquetas pendientes de entregar al navegador.
 */
function unibagReleaseSessionLockForReadOnlyPage(string $path, string $method): void
{
    if (($method !== 'GET' && $method !== 'HEAD') || session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    if (!empty($_SESSION['unibag_client_print_queue'])) {
        return;
    }
    $readOnly = $path === '/';
    foreach (['/reports', '/stock', '/warehouses', '/trazabilidad', '/traceability', '/bonificaciones'] as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
            $readOnly = true;
            break;
        }
    }
    if (!$readOnly) {
        return;
    }
    $snapshot = $_SESSION;
    session_write_close();
    register_shutdown_function(static function () use ($snapshot): void {
        unibagSaveLateSessionChanges($snapshot);
    });
}

/** Guarda lo que cambió en $_SESSION después de liberar la sesión, sin pisar lo que otra pestaña haya escrito. */
function unibagSaveLateSessionChanges(array $snapshot): void
{
    $current = $_SESSION ?? [];
    if ($current === $snapshot || session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (headers_sent()) {
        error_log('Sesión: cambios sin guardar porque la respuesta ya se envió: ' . implode(',', array_keys(array_diff_key($current, $snapshot))));
        return;
    }
    @ini_set('session.use_cookies', '0');
    session_cache_limiter('');
    if (!@session_start()) {
        return;
    }
    foreach ($current as $key => $value) {
        if (!array_key_exists($key, $snapshot) || $snapshot[$key] !== $value) {
            $_SESSION[$key] = $value;
        }
    }
    foreach (array_keys($snapshot) as $key) {
        if (!array_key_exists($key, $current)) {
            unset($_SESSION[$key]);
        }
    }
    session_write_close();
}

function unibagInjectDatePickerAssets(string $html): string
{
    $html = unibagInjectHardwareAssets($html);
    return unibagInjectDatePickerAssetsInner($html);
}

function unibagInjectDatePickerAssetsInner(string $html): string
{
    if ($html === '') {
        return $html;
    }

    if (!str_contains($html, '<html') || !str_contains($html, '<body')) {
        return $html;
    }

    if (
        !str_contains($html, 'type="date"')
        && !str_contains($html, "type='date'")
    ) {
        return $html;
    }

    if (str_contains($html, 'unibag-date-picker-script')) {
        return $html;
    }

    $headers = array_map('strtolower', headers_list());
    foreach ($headers as $header) {
        if (str_starts_with($header, 'content-type:')) {
            if (
                !str_contains($header, 'text/html')
                && !str_contains($header, 'application/xhtml+xml')
            ) {
                return $html;
            }
            if (
                str_contains($header, 'application/vnd.ms-excel')
                || str_contains($header, 'application/vnd.openxmlformats-officedocument')
                || str_contains($header, 'application/octet-stream')
            ) {
                return $html;
            }
        }
    }

    $assets = unibagDatePickerAssetsHtml();
    if (stripos($html, '</head>') !== false) {
        $html = preg_replace('/<\/head>/i', $assets . PHP_EOL . '</head>', $html, 1) ?? $html;
    } elseif (stripos($html, '<body') !== false) {
        $html = preg_replace('/<body\b([^>]*)>/i', $assets . PHP_EOL . '<body$1>', $html, 1) ?? $html;
    }

    return $html;
}

/**
 * Redirige y finaliza la respuesta HTTP.
 *
 * Limpia buffers (si existen) para evitar “headers already sent”, setea headers
 * anti-cache y emite Location.
 *
 * ---
 *
 * Redirects and terminates the HTTP response.
 *
 * It clears output buffers (if any) to avoid “headers already sent”, sets
 * no-cache headers and outputs the Location header.
 */
function redirectResponse(string $location, int $statusCode = 303): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Location: ' . $location, true, $statusCode);
    exit;
}

/**
 * Busca metadatos comerciales y de producción asociados al hash de diseño (OT, CC, Cliente, Producto).
 */
function unibagLookupDesignMetadata(string $hash): ?array
{
    $clean = preg_replace('/^VER_|\.[a-zA-Z0-9]+$/', '', $hash);
    try {
        if (strlen($clean) >= 6) {
            $stmt = Db::erpPdo()->prepare("
                SELECT oi.req_id, oi.item_id, oi.fab_design_name, it.item_title, c.cust_name, ph.prd_number, ord.req_number
                FROM orders_items oi
                LEFT JOIN orders ord ON oi.req_id = ord.id
                LEFT JOIN customer c ON ord.req_cust_id = c.id
                LEFT JOIN item it ON oi.item_id = it.id
                LEFT JOIN prod_header ph ON ord.id = ph.prd_reqid AND ph.prd_status >= 1
                WHERE oi.fab_design_imagehash LIKE :hash
                ORDER BY oi.req_id DESC
                LIMIT 1
            ");
            $stmt->execute([':hash' => '%' . $clean . '%']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }

        // Buscar en adjuntos de producción de orders (ej. 11573_d59b5d33d2ccfc7ac5b944c4e338e13d.jpg)
        $stmtAdj = Db::erpPdo()->prepare("
            SELECT ord.id AS req_id, ord.req_number, c.cust_name, ph.prd_number, it.item_title
            FROM orders ord
            LEFT JOIN customer c ON ord.req_cust_id = c.id
            LEFT JOIN prod_header ph ON ord.id = ph.prd_reqid AND ph.prd_status >= 1
            LEFT JOIN orders_items oi ON ord.id = oi.req_id
            LEFT JOIN item it ON oi.item_id = it.id
            WHERE ord.req_prod_adjfile_0 LIKE :h
               OR ord.req_prod_adjfile_1 LIKE :h
               OR ord.req_prod_adjfile_2 LIKE :h
               OR ord.req_prod_adjfile_3 LIKE :h
               OR ord.req_prod_adjfile_4 LIKE :h
            ORDER BY ord.id DESC
            LIMIT 1
        ");
        $stmtAdj->execute([':h' => '%' . $hash . '%']);
        $rowAdj = $stmtAdj->fetch(PDO::FETCH_ASSOC);
        if (is_array($rowAdj)) {
            return $rowAdj;
        }

        // Si el nombre del archivo empieza con {order_id}_, buscar por ord.id directamente
        if (preg_match('/^(\d+)_[a-zA-Z0-9]+/', $hash, $mOrder)) {
            $orderId = (int)$mOrder[1];
            $stmtOrd = Db::erpPdo()->prepare("
                SELECT ord.id AS req_id, ord.req_number, c.cust_name, ph.prd_number, it.item_title
                FROM orders ord
                LEFT JOIN customer c ON ord.req_cust_id = c.id
                LEFT JOIN prod_header ph ON ord.id = ph.prd_reqid AND ph.prd_status >= 1
                LEFT JOIN orders_items oi ON ord.id = oi.req_id
                LEFT JOIN item it ON oi.item_id = it.id
                WHERE ord.id = :oid
                LIMIT 1
            ");
            $stmtOrd->execute([':oid' => $orderId]);
            $rowOrd = $stmtOrd->fetch(PDO::FETCH_ASSOC);
            if (is_array($rowOrd)) {
                return $rowOrd;
            }
        }

        return null;
    } catch (Throwable) {
        return null;
    }
}

/**
 * ¿El adjunto es una imagen? Extensión de imagen conocida, o sin extensión (el ERP guarda varios adjuntos
 * solo con el hash): en ese caso se abre en el visor con rotación y el servidor confirma por contenido.
 */
function unibagAttachmentIsImage(string $fileName): bool
{
    $ext = strtolower(pathinfo(trim($fileName), PATHINFO_EXTENSION));
    return $ext === '' || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'jfif'], true);
}

/**
 * Renderiza el visor interactivo de diseño e imágenes con rotación 360°, zoom, espejo, arrastre y atajos.
 */
function unibagRenderImageViewerHtml(string $hash, string $folder): string
{
    return renderView('layouts/image_viewer', get_defined_vars());
}

/**
 * Sirve y almacena en caché local las imágenes y documentos adjuntos del ERP (diseños, montajes, fotos).
 * Soporta: /docs/order/{hash}, /docs/prod/{hash}, /docs.order/{hash}, /docs.prod/{hash}, /docs/{hash}.
 */
function unibagServeErpDocument(string $path): bool
{
    $folder = null;
    $hash = null;

    if (preg_match('#^/docs/order/([a-zA-Z0-9_\-\.]+)$#i', $path, $m) === 1 || preg_match('#^/docs\.order/([a-zA-Z0-9_\-\.]+)$#i', $path, $m) === 1) {
        $folder = 'docs.order';
        $hash = $m[1];
    } elseif (preg_match('#^/docs/prod/([a-zA-Z0-9_\-\.]+)$#i', $path, $m) === 1 || preg_match('#^/docs\.prod/([a-zA-Z0-9_\-\.]+)$#i', $path, $m) === 1) {
        $folder = 'docs.prod';
        $hash = $m[1];
    } elseif (preg_match('#^/docs/([a-zA-Z0-9_\-\.]+)$#i', $path, $m) === 1) {
        $hash = $m[1];
        $folder = str_starts_with($hash, 'VER_') ? 'docs.order' : 'auto';
    } elseif ($path === '/production/design-image' || $path === '/api/document' || $path === '/doc') {
        $hash = trim((string)($_GET['hash'] ?? ''));
        $fReq = trim((string)($_GET['folder'] ?? 'docs.order'));
        $folder = ($fReq === 'prod' || $fReq === 'docs.prod') ? 'docs.prod' : 'docs.order';
    }

    if ($hash === null || $hash === '' || $hash === 'dummy') {
        return false;
    }

    $ext = strtolower(pathinfo($hash, PATHINFO_EXTENSION));
    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);

    // Cuando el usuario navega directamente al link en el navegador (no un tag <img> ni solicitud raw),
    // servir la página completa del visor interactivo con rotación y zoom.
    $shouldShowViewer = $isImage && empty($_GET['raw']) && (
        (!empty($_GET['view']))
        || (($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'document')
        || (($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'iframe')
        || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html'))
    );

    // Navegación directa al archivo (sin extensión de imagen conocida): se decide por el contenido más abajo.
    $viewerRequested = empty($_GET['raw']) && (
        (!empty($_GET['view']))
        || (($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'document')
        || (($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'iframe')
        || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html'))
    );
    $showViewer = static function () use ($hash, $folder): void {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/html; charset=utf-8');
        echo unibagRenderImageViewerHtml($hash, $folder ?? 'docs.order');
        exit;
    };

    if ($shouldShowViewer) {
        $showViewer();
    }

    $cacheBase = __DIR__ . '/docs_cache';
    if (!is_dir($cacheBase)) {
        @mkdir($cacheBase, 0777, true);
    }
    $mimeMap = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'pdf'  => 'application/pdf',
    ];
    $contentType = $mimeMap[$ext] ?? 'application/octet-stream';

    $cachedFile = $cacheBase . '/' . $hash;
    $orderStatic = __DIR__ . '/docs/order/' . $hash;
    $prodStatic = __DIR__ . '/docs/prod/' . $hash;

    $localExisting = null;
    if (file_exists($cachedFile) && filesize($cachedFile) > 0) {
        $localExisting = $cachedFile;
    } elseif (file_exists($prodStatic) && filesize($prodStatic) > 0) {
        $localExisting = $prodStatic;
    } elseif (file_exists($orderStatic) && filesize($orderStatic) > 0) {
        $localExisting = $orderStatic;
    }

    if ($localExisting !== null) {
        if (!file_exists($cachedFile)) {
            @copy($localExisting, $cachedFile);
        }
        // Adjuntos sin extensión: si el contenido es una imagen, usar su tipo real y el visor con rotación.
        if (!$isImage && $contentType === 'application/octet-stream') {
            $sniffed = @getimagesize($localExisting);
            if (is_array($sniffed) && !empty($sniffed['mime'])) {
                $contentType = (string)$sniffed['mime'];
                if ($viewerRequested) {
                    $showViewer();
                }
            }
        }
        if ($folder === 'docs.order' && !file_exists($orderStatic)) {
            $orderDir = __DIR__ . '/docs/order';
            if (!is_dir($orderDir)) @mkdir($orderDir, 0777, true);
            @copy($localExisting, $orderStatic);
        } elseif ($folder === 'docs.prod' && !file_exists($prodStatic)) {
            $prodDir = __DIR__ . '/docs/prod';
            if (!is_dir($prodDir)) @mkdir($prodDir, 0777, true);
            @copy($localExisting, $prodStatic);
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($localExisting));
        header('Cache-Control: public, max-age=604800');
        readfile($localExisting);
        exit;
    }

    $remoteFolders = ($folder === 'auto') ? ['docs.order', 'docs.prod'] : [$folder];
    $downloadedData = null;

    foreach ($remoteFolders as $f) {
        $remoteUrl = 'https://www.erp-unibag.cl/' . $f . '/' . rawurlencode($hash);
        $ch = curl_init($remoteUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $result = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $remoteMime = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode === 200 && is_string($result) && strlen($result) > 0) {
            $downloadedData = $result;
            if ($remoteMime !== '' && !str_contains($remoteMime, 'text/html')) {
                $contentType = explode(';', $remoteMime)[0];
            }
            break;
        }
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if ($downloadedData !== null) {
        @file_put_contents($cachedFile, $downloadedData);
        if ($folder === 'docs.order') {
            $orderDir = __DIR__ . '/docs/order';
            if (!is_dir($orderDir)) @mkdir($orderDir, 0777, true);
            @file_put_contents($orderDir . '/' . $hash, $downloadedData);
        } elseif ($folder === 'docs.prod') {
            $prodDir = __DIR__ . '/docs/prod';
            if (!is_dir($prodDir)) @mkdir($prodDir, 0777, true);
            @file_put_contents($prodDir . '/' . $hash, $downloadedData);
        }
        if (!$isImage && ($contentType === 'application/octet-stream' || $contentType === '')) {
            $sniffed = @getimagesizefromstring($downloadedData);
            if (is_array($sniffed) && !empty($sniffed['mime'])) {
                $contentType = (string)$sniffed['mime'];
                if ($viewerRequested) {
                    $showViewer();
                }
            }
        }
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . strlen($downloadedData));
        header('Cache-Control: public, max-age=604800');
        echo $downloadedData;
        exit;
    }

    http_response_code(404);
    header('Content-Type: image/svg+xml; charset=utf-8');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="150" viewBox="0 0 300 150">
        <rect width="100%" height="100%" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="2" rx="8"/>
        <text x="50%" y="45%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="14" font-weight="bold" fill="#64748b">Imagen no disponible</text>
        <text x="50%" y="65%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="11" fill="#94a3b8">' . htmlspecialchars($hash) . '</text>
    </svg>';
    exit;
}

/**
 * Guardia anti-duplicado para impresiones disparadas por una petición GET.
 *
 * Devuelve true si la etiqueta identificada por $key puede enviarse ahora y registra el
 * momento; devuelve false si la misma etiqueta ya se envió en los últimos $seconds
 * segundos (refresco de página, doble clic, prefetch del navegador).
 */
function unibagPrintGuard(string $key, int $seconds = 10): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return true;
    }
    $now = time();
    $guard = $_SESSION['unibag_print_guard'] ?? [];
    if (!is_array($guard)) {
        $guard = [];
    }
    // Se limpian las marcas vencidas para que el arreglo no crezca indefinidamente.
    foreach ($guard as $k => $ts) {
        if (!is_int($ts) || ($now - $ts) > 60) {
            unset($guard[$k]);
        }
    }
    if (isset($guard[$key]) && ($now - (int)$guard[$key]) < $seconds) {
        $_SESSION['unibag_print_guard'] = $guard;
        return false;
    }
    $guard[$key] = $now;
    $_SESSION['unibag_print_guard'] = $guard;
    return true;
}

function unibagNormalizePrinterProfile(?string $profile): string
{
    $profile = strtolower(trim((string)$profile));
    return in_array($profile, ['default', 'zebra', 'barpos'], true) ? $profile : 'default';
}

function unibagPrinterProfileQuery(string $profile): string
{
    $profile = unibagNormalizePrinterProfile($profile);
    return $profile === 'default' ? '' : ('&printer_profile=' . urlencode($profile));
}

/**
 * Escape HTML seguro para imprimir texto en vistas.
 *
 * ---
 *
 * Safe HTML escape helper for rendering text in views.
 */
function h(string|int|float|null $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Determina si una bobina puede trasladarse a otra bodega o asignarse a una OT.
 *
 * Reglas (resumen):
 * - Si la bobina ya está asignada a una OT, no se permite moverla.
 * - Si el status no es RECEIVED, no se permite moverla (según motivo).
 * - Si está RECEIVED: se permite traspaso de bodega; y se permite ingreso a OT
 *   solo si la etapa es RAW o PRINTED.
 *
 * ---
 *
 * Determines whether a roll can be transferred to another warehouse or attached to a work order.
 *
 * Rules (summary):
 * - If the roll is already assigned to a work order, it cannot be moved.
 * - If status is not RECEIVED, it cannot be moved (with a reason).
 * - If RECEIVED: warehouse transfer is allowed; and work-order attach is allowed
 *   only when stage is RAW or PRINTED.
 *
 * @param array<string, mixed> $roll
 * @return array{
 *   warehouse: array{allowed: bool, reason: string},
 *   work_order: array{allowed: bool, reason: string},
 *   can_transfer: bool
 * }
 */
function rollTransferAvailability(array $roll): array
{
    $status = strtoupper(trim((string)($roll['status'] ?? '')));
    $processStage = strtoupper(trim((string)($roll['process_stage'] ?? 'RAW')));
    $currentWorkOrderId = (int)($roll['current_work_order_id'] ?? 0);
    $currentWorkOrderCode = trim((string)($roll['work_order_code'] ?? ''));

    $warehouse = ['allowed' => false, 'reason' => ''];
    $workOrder = ['allowed' => false, 'reason' => ''];

    if ($currentWorkOrderId > 0) {
        $reason = 'La bobina ya está asignada a la OT ' . ($currentWorkOrderCode !== '' ? $currentWorkOrderCode : ('#' . $currentWorkOrderId)) . '.';
        $warehouse['reason'] = $reason;
        $workOrder['reason'] = $reason;
    } elseif ($status !== 'RECEIVED') {
        $reason = match ($status) {
            'IN_PROCESS' => 'La bobina está en proceso y no se puede mover.',
            'CONSUMED' => 'La bobina ya fue consumida y no se puede mover.',
            'BLOCKED' => 'La bobina está bloqueada y no se puede mover.',
            default => 'La bobina no está disponible para traslado.',
        };
        $warehouse['reason'] = $reason;
        $workOrder['reason'] = $reason;
    } else {
        $warehouse['allowed'] = true;
        if (in_array($processStage, ['RAW', 'PRINTED'], true)) {
            $workOrder['allowed'] = true;
        } else {
            $workOrder['reason'] = 'La etapa actual de la bobina no permite ingresarla a una OT.';
        }
    }

    return [
        'warehouse' => $warehouse,
        'work_order' => $workOrder,
        'can_transfer' => $warehouse['allowed'] || $workOrder['allowed'],
    ];
}

/**
 * Pantalla estándar de error cuando no se puede conectar a la BD.
 *
 * Se usa al inicio del request, antes de instanciar servicios, para dar un
 * checklist claro de configuración (TRZ/ERP) sin romper la aplicación con un fatal.
 *
 * ---
 *
 * Standard error screen when database connection cannot be established.
 *
 * Used early in the request (before services are instantiated) to show a clear
 * configuration checklist (TRZ/ERP) instead of throwing a fatal error.
 */
function renderDatabaseConnectionError(Throwable $e): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Error de Conexión - Unibag</title>';
    echo '<style>
        *{box-sizing:border-box}
        body{margin:0;background:#f2f4f7;font-family:Arial,sans-serif;color:#111;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
        .err-card{width:min(600px,100%);background:#fff;border:1px solid #d0d5dd;border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.08);overflow:hidden}
        .err-header{background:#00A9A6;color:#fff;padding:16px 20px;font-size:18px;font-weight:700}
        .err-body{padding:20px}
        .err-box{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px;border-radius:8px;font-size:13px;margin:14px 0;word-break:break-all}
        .err-list{margin:10px 0 0;padding-left:20px;font-size:13px;color:#374151;line-height:1.6}
        .btn-retry{display:inline-block;background:#00A9A6;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:700;font-size:13px;margin-top:16px}
    </style></head><body>';
    echo '<div class="err-card">';
    echo '<div class="err-header">No hay conexión a la base de datos</div>';
    echo '<div class="err-body">';
    echo '<p style="margin:0 0 10px;font-size:14px;color:#475467">Este módulo requiere MySQL configurado en el archivo <b>.env</b> para ERP y trazabilidad.</p>';
    echo '<div class="err-box"><b>Detalle del error:</b><br>' . h($e->getMessage()) . '</div>';
    echo '<div style="font-weight:700;font-size:13px;color:#111">Checklist de configuración (.env):</div>';
    echo '<ul class="err-list">';
    echo '<li>Verificar que el archivo <b>.env</b> tenga permisos de lectura (<code>chmod 644 .env</code>).</li>';
    echo '<li>Configurar <b>ERP_DB_*</b> para <b>unibag_unibag</b> (Host: 149.50.129.154).</li>';
    echo '<li>Configurar <b>TRZ_DB_*</b> para <b>unibag_trazabilidad</b> (Usuario: unibag_user).</li>';
    echo '</ul>';
    echo '<a class="btn-retry" href="/login">Reintentar</a>';
    echo '</div></div></body></html>';
    exit;
}

/**
 * Label amigable para el modo de recepción (por peso vs. por unidades).
 *
 * ---
 *
 * Human-friendly label for reception mode (by weight vs. by quantity).
 */
function receptionModeLabel(string $mode): string
{
    return strtoupper(trim($mode)) === 'WEIGHT' ? 'Por peso' : 'Por unidades';
}

/**
 * Label amigable para la etapa de proceso de una bobina.
 *
 * ---
 *
 * Human-friendly label for a roll process stage.
 */
function rollProcessStageLabel(?string $stage): string
{
    return match (strtoupper(trim((string)$stage))) {
        'RAW' => 'Materia prima',
        'PRINTED' => 'Impresa',
        'CUT' => 'Cortada',
        default => trim((string)$stage) !== '' ? (string)$stage : '-',
    };
}

/**
 * Label amigable para el estado de una bobina.
 *
 * ---
 *
 * Human-friendly label for a roll status.
 */
function rollStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'RECEIVED' => 'Disponible',
        'IN_PROCESS' => 'En proceso',
        'BLOCKED' => 'Bloqueada',
        'CONSUMED' => 'Consumida',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

/**
 * Label amigable para el estado de una OT.
 *
 * ---
 *
 * Human-friendly label for a work order status.
 */
function workOrderStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'PENDING' => 'Pendiente',
        'OPEN' => 'Pendiente',
        'ACTIVE' => 'En producción',
        'CUTTING' => 'Corte pendiente',
        'CLOSED' => 'Cerrada',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

/**
 * Label amigable para etapas/secciones del flujo de producción.
 *
 * ---
 *
 * Human-friendly label for production process stages/sections.
 */
function machineProcessStageLabel(?string $stage): string
{
    return match (strtoupper(trim((string)$stage))) {
        'PRODUCTION' => 'Producción',
        'REWINDING' => 'Rebobinado',
        'PACKAGING' => 'Embalaje',
        'SEALING' => 'Sellado',
        'CUTTING' => 'Corte',
        'RESIDUOS', 'WASTE' => 'Residuos',
        default => trim((string)$stage) !== '' ? trim((string)$stage) : '-',
    };
}

/**
 * Label amigable para el estado de un turno (shift session).
 *
 * ---
 *
 * Human-friendly label for a shift session status.
 */
function shiftSessionStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'ACTIVE' => 'En turno',
        'CLOSED' => 'Terminado',
        default => trim((string)$status) !== '' ? trim((string)$status) : '-',
    };
}

/**
 * Label amigable para el estado de un documento de recepción.
 *
 * ---
 *
 * Human-friendly label for a reception document status.
 */
function receptionDocumentStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'OPEN' => 'Abierta',
        'PARTIAL' => 'Parcial',
        'COMPLETE' => 'Completa',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

function renderReceptionNavTabs(string $activeTab): string
{
    $tabs = [
        'national_active' => [
            'label' => 'Nacional Activa',
            'icon' => '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
            'url' => '/purchase-orders?status=active&supplier_type=NATIONAL',
        ],
        'import_active' => [
            'label' => 'Importación Activa',
            'icon' => '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            'url' => '/import-containers?status=active',
        ],
        'national_complete' => [
            'label' => 'Nacionales Finalizadas',
            'icon' => '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            'url' => '/purchase-orders?status=complete&supplier_type=NATIONAL',
        ],
        'import_complete' => [
            'label' => 'Importación Finalizadas',
            'icon' => '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>',
            'url' => '/import-containers?status=complete',
        ],
        'reception_audit' => [
            'label' => 'Auditoría por Contenedor',
            'icon' => '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
            'url' => '/reports/reception-audit',
        ],
    ];

    $html = '<div class="rec-nav-bar">';
    foreach ($tabs as $key => $tab) {
        $isActive = ($key === $activeTab);
        $html .= '<a href="' . h($tab['url']) . '" class="rec-nav-tab' . ($isActive ? ' active' : '') . '">'
              . $tab['icon'] . '<span>' . h($tab['label']) . '</span></a>';
    }
    $html .= '</div>';
    return $html;
}

function renderReceptionStatusBadge(?string $status): string
{
    $st = strtoupper(trim((string)$status));
    if ($st === 'COMPLETE') {
        return '<span class="rec-badge rec-badge-complete"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Finalizada</span>';
    }
    if ($st === 'PARTIAL') {
        return '<span class="rec-badge rec-badge-partial"><span class="rec-dot"></span>Parcial</span>';
    }
    return '<span class="rec-badge rec-badge-open"><span class="rec-dot"></span>Abierta</span>';
}

function renderReceptionProgressCol(int $completed, int $total): string
{
    $pct = $total > 0 ? min(100, max(0, (int)round(($completed / $total) * 100))) : 0;
    $isAll = $total > 0 && $completed >= $total;
    $fillClass = $isAll ? ' rec-fill-complete' : '';
    return '<div class="rec-prog-container">'
         . '<div class="rec-prog-bar"><div class="rec-prog-fill' . $fillClass . '" style="width:' . $pct . '%"></div></div>'
         . '<span class="rec-prog-label' . ($isAll ? ' is-done' : '') . '">' . $completed . ' / ' . $total . '</span>'
         . '</div>';
}

function renderReceptionPagination(
    int $currentPage,
    int $totalPages,
    int $totalRecords,
    int $pageSize,
    string $baseUrl,
    array $queryParams
): string {
    if ($totalRecords <= 0) {
        return '';
    }

    $startItem = max(1, ($currentPage - 1) * $pageSize + 1);
    $endItem = min($totalRecords, $currentPage * $pageSize);

    $buildUrl = function(int $page) use ($baseUrl, $queryParams): string {
        $params = $queryParams;
        $params['page'] = $page;
        return $baseUrl . '?' . http_build_query($params);
    };

    if ($totalPages <= 7) {
        $items = range(1, $totalPages);
    } else {
        $items = [];
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        if ($start > 1) {
            $items[] = 1;
            if ($start > 2) {
                $items[] = '...';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $items[] = $i;
        }
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $items[] = '...';
            }
            $items[] = $totalPages;
        }
    }

    $html = '<div class="rec-pagination-bar">';
    $html .= '<div class="rec-pagination-info">';
    $html .= 'Mostrando <strong>' . $startItem . '</strong> - <strong>' . $endItem . '</strong> de <strong>' . $totalRecords . '</strong> registros';
    if ($totalPages > 1) {
        $html .= ' (Página ' . $currentPage . ' de ' . $totalPages . ')';
    }
    $html .= '</div>';

    if ($totalPages > 1) {
        $html .= '<div class="rec-pagination-nav">';
        if ($currentPage > 1) {
            $html .= '<a href="' . h($buildUrl($currentPage - 1)) . '" class="rec-page-btn">&laquo; Anterior</a>';
        } else {
            $html .= '<span class="rec-page-btn disabled">&laquo; Anterior</span>';
        }

        foreach ($items as $item) {
            if ($item === '...') {
                $html .= '<span class="rec-page-dots">...</span>';
            } elseif ((int)$item === $currentPage) {
                $html .= '<span class="rec-page-btn active">' . $item . '</span>';
            } else {
                $html .= '<a href="' . h($buildUrl((int)$item)) . '" class="rec-page-btn">' . $item . '</a>';
            }
        }

        if ($currentPage < $totalPages) {
            $html .= '<a href="' . h($buildUrl($currentPage + 1)) . '" class="rec-page-btn">Siguiente &raquo;</a>';
        } else {
            $html .= '<span class="rec-page-btn disabled">Siguiente &raquo;</span>';
        }
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

function renderReceptionClosureBanner(?array $closure): string
{
    return $closure === null ? '' : renderView('reception/closure_banner', ['closure' => $closure]);
}

function renderCloseReceptionModal(string $actionUrl, string $entityTitle, int $pendingUnits = 0): string
{
    return renderView('reception/close_modal', get_defined_vars());
}

/**
 * Label amigable para el estado de una solicitud de material.
 *
 * ---
 *
 * Human-friendly label for a material request status.
 */
function materialRequestStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'PENDING' => 'Pendiente',
        'ACCEPTED' => 'Aceptada',
        'PARTIAL' => 'Parcial',
        'DELIVERED' => 'Entregada',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

/**
 * Determina si una OT permite recibir/entregar materiales según su estado.
 *
 * ---
 *
 * Determines whether a work order can receive/deliver materials based on its status.
 */
function workOrderCanReceiveMaterials(?string $status): bool
{
    return in_array(strtoupper(trim((string)$status)), ['OPEN', 'ACTIVE', 'CUTTING'], true);
}

/**
 * Label amigable para el tipo de solicitud de material.
 *
 * ---
 *
 * Human-friendly label for a material request type.
 */
function materialRequestTypeLabel(?string $type): string
{
    return match (strtoupper(trim((string)$type))) {
        'ROLL' => 'Bobina',
        'CHEMICAL' => 'Tinta',
        'OTHER' => 'Otro insumo',
        default => trim((string)$type) !== '' ? (string)$type : '-',
    };
}

/**
 * Label amigable para el tipo de movimiento de inventario.
 *
 * ---
 *
 * Human-friendly label for an inventory movement type.
 */
function movementTypeLabel(?string $type): string
{
    return match (strtoupper(trim((string)$type))) {
        'RECEIPT' => 'Recepción',
        'TRANSFER' => 'Traspaso',
        default => trim((string)$type) !== '' ? (string)$type : '-',
    };
}

/**
 * Label amigable para el estado de una orden de maquila.
 *
 * ---
 *
 * Human-friendly label for a maquila order status.
 */
function maquilaStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'OPEN' => 'En maquila',
        'PARTIAL' => 'Retorno parcial',
        'RETURNED' => 'Retornada',
        'CANCELLED' => 'Cancelada',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

/**
 * Label amigable para el estado de un pallet.
 *
 * ---
 *
 * Human-friendly label for a pallet status.
 */
function palletStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'CREATED' => 'Pendiente',
        'STORED' => 'Almacenado',
        'IN_MAQUILA' => 'En maquila',
        'MAQUILA_RETURNED' => 'Retornado de maquila',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

/**
 * Label amigable para el estado de una caja.
 *
 * ---
 *
 * Human-friendly label for a box status.
 */
function boxStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'CREATED' => 'Pendiente',
        'STORED' => 'Almacenada',
        'IN_MAQUILA' => 'En maquila',
        'MAQUILA_RETURNED' => 'Retornada de maquila',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

/**
 * Label amigable para el estado de un cliché.
 *
 * ---
 *
 * Human-friendly label for a cliche status.
 */
function clicheStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'AVAILABLE' => 'Disponible',
        'IN_USE' => 'En uso',
        'MAINTENANCE' => 'Mantención',
        'INACTIVE' => 'Inactivo',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

/**
 * Label amigable para el tipo de evento (auditoría/bitácora).
 *
 * ---
 *
 * Human-friendly label for an event type (audit/log).
 */
function eventTypeLabel(?string $type): string
{
    return match (strtoupper(trim((string)$type))) {
        'WORK_ORDER_CREATED' => 'OT creada',
        'WORK_ORDER_ACTIVATED' => 'OT activada',
        'WORK_ORDER_STARTED' => 'OT iniciada',
        'WORK_ORDER_FINISHED' => 'OT finalizada',
        'WORK_ORDER_ROLL_ATTACHED' => 'Bobina ingresada a OT',
        'WORK_ORDER_ROLL_RELEASED' => 'Bobina salida de OT',
        'MATERIAL_REQUESTED' => 'Material solicitado',
        'MATERIAL_REQUEST_ACCEPTED' => 'Solicitud aceptada',
        'MATERIAL_DELIVERED' => 'Material entregado',
        'CHEMICAL_WEIGHING_CREATED' => 'Pesaje de tinta registrado',
        'CHEMICAL_INPUT_RECORDED' => 'Tinta de entrada registrada',
        'PRODUCTION_WASTE_RECORDED' => 'Merma registrada',
        'OUTPUT_ROLL_CREATED' => 'Bobina de salida creada',
        'CUT_COMPLETED' => 'Corte completado',
        'ROLL_TRANSFERRED' => 'Bobina transferida',
        'ROLL_RECEIVED' => 'Bobina recepcionada',
        'CLICHE_CREATED' => 'Clisé creado',
        'CLICHE_ASSIGNED' => 'Clisé asignado a OT',
        'CLICHE_RETURNED' => 'Clisé devuelto',
        'PALLET_TRANSFERRED' => 'Pallet trasladado',
        'SKU_CREATED' => 'SKU creado',
        'SKU_TOGGLED' => 'SKU actualizado',
        'SHIFT_SESSION_STARTED' => 'Turno iniciado',
        'SHIFT_SESSION_ENDED' => 'Turno terminado',
        'SHIFT_SESSION_WORK_ORDER_ASSIGNED' => 'OT asignada a máquina',
        'SHIFT_SESSION_WORK_ORDER_RELEASED' => 'OT liberada de máquina',
        'MAQUILA_SENT' => 'Salida a maquila',
        'MAQUILA_RETURN_RECORDED' => 'Retorno de maquila',
        default => trim((string)$type) !== '' ? trim((string)$type) : '-',
    };
}

/**
 * Abreviación/label corto para el área ERP activa (para headers y badges).
 *
 * ---
 *
 * Short label for the current ERP area (used in headers and badges).
 */
function erpAreaShortLabel(string $area): string
{
    return match (normalizeErpArea($area)) {
        'RECEPTION' => 'RECEPCIÓN',
        'PRODUCTION' => 'PRODUCCIÓN',
        'SCALE' => 'BALANZA',
        default => 'ERP',
    };
}

/**
 * Construye un resumen normalizado de una línea de recepción (ordenado/recibido/pendiente).
 *
 * Soporta:
 * - Recepción por peso (WEIGHT): usa ordered_weight_kg / received_weight_kg.
 * - Recepción por unidades (QUANTITY): usa ordered_rolls / received_qty.
 *
 * ---
 *
 * Builds a normalized summary for a reception line (ordered/received/pending).
 *
 * Supports:
 * - Weight-based reception (WEIGHT): uses ordered_weight_kg / received_weight_kg.
 * - Quantity-based reception (QUANTITY): uses ordered_rolls / received_qty.
 *
 * @param array<string, mixed> $line
 * @return array{mode:string,ordered:float,received:float,pending:float,unit:string,ordered_weight_kg:float,received_weight_kg:float,pending_weight_kg:float,ordered_rolls:float,received_rolls:float,pending_rolls:float}
 */
function receptionLineSummary(array $line): array
{
    $mode = strtoupper(trim((string)($line['reception_mode'] ?? 'QUANTITY'))) === 'WEIGHT' ? 'WEIGHT' : 'QUANTITY';
    $orderedWeight = round((float)($line['ordered_weight_kg'] ?? 0), 3);
    $receivedWeight = round((float)($line['received_weight_kg'] ?? 0), 3);
    $pendingWeight = max(0, round($orderedWeight - $receivedWeight, 3));

    $orderedRolls = round((float)($line['ordered_rolls'] ?? 0), 3);
    $receivedRolls = round((float)($line['received_qty'] ?? $line['received_rolls'] ?? 0), 3);
    $pendingRolls = max(0, round($orderedRolls - $receivedRolls, 3));

    if ($mode === 'WEIGHT') {
        $ordered = $orderedWeight;
        $received = $receivedWeight;
        $pending = $pendingWeight;
        $unit = 'Kg';
    } else {
        $ordered = $orderedRolls;
        $received = $receivedRolls;
        $pending = $pendingRolls;
        $unit = 'Unid.';
    }

    return [
        'mode' => $mode,
        'ordered' => $ordered,
        'received' => $received,
        'pending' => $pending,
        'unit' => $unit,
        'ordered_weight_kg' => $orderedWeight,
        'received_weight_kg' => $receivedWeight,
        'pending_weight_kg' => $pendingWeight,
        'ordered_rolls' => $orderedRolls,
        'received_rolls' => $receivedRolls,
        'pending_rolls' => $pendingRolls,
    ];
}

/**
 * Formatea un valor de recepción según unidad:
 * - Unid.: se muestra sin decimales cuando es entero.
 * - Otros: 3 decimales con separador local.
 *
 * ---
 *
 * Formats a reception value based on unit:
 * - Unid.: show as integer when the value is effectively whole.
 * - Others: 3 decimals using local separators.
 */
function formatReceptionValue(float $value, string $unit): string
{
    if ($unit === 'Unid.' && abs($value - round($value)) < 0.0001) {
        return (string)(int)round($value);
    }
    return number_format($value, 3, ',', '.');
}

/**
/**
 * Formatea una línea de recepción mostrando únicamente unidades para una visualización limpia en pantalla.
 */
function formatReceptionDual(array $lineSummary, string $type = 'ordered'): string
{
    $rollsKey = 'ordered_rolls';
    if ($type === 'received') {
        $rollsKey = 'received_rolls';
    } elseif ($type === 'pending') {
        $rollsKey = 'pending_rolls';
    }

    $rolls = (float)($lineSummary[$rollsKey] ?? 0);
    return (abs($rolls - round($rolls)) < 0.0001 ? (string)(int)round($rolls) : number_format($rolls, 2, ',', '.')) . ' Unid.';
}

/**
 * Formatea una fecha (string) a d-m-Y.
 *
 * Si no se puede parsear, devuelve el string original.
 *
 * ---
 *
 * Formats a date (string) as d-m-Y.
 *
 * If it cannot be parsed, returns the original string.
 */
function formatLabelDate(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }

    return date('d-m-Y', $ts);
}

/**
 * Formatea una duración “hh mm” a partir de timestamps (string).
 *
 * Se usa para labels en UI cuando hay inicio y fin de turno/proceso.
 *
 * ---
 *
 * Formats an elapsed duration “hh mm” from timestamp strings.
 *
 * Used for UI labels when there is a start/end time for a shift/process.
 */
function formatElapsedLabel(?string $startedAt, ?string $endedAt = null): string
{
    $startedTs = $startedAt !== null && trim($startedAt) !== '' ? strtotime($startedAt) : false;
    if ($startedTs === false) {
        return '0h 0m';
    }
    $endedTs = $endedAt !== null && trim($endedAt) !== '' ? strtotime($endedAt) : time();
    if ($endedTs === false || $endedTs < $startedTs) {
        $endedTs = $startedTs;
    }
    $diffSeconds = max(0, $endedTs - $startedTs);
    $hours = intdiv($diffSeconds, 3600);
    $minutes = intdiv($diffSeconds % 3600, 60);
    return $hours . 'h ' . $minutes . 'm';
}

/**
 * Construye un texto de especificación para una línea (gramos/ancho/color/metros).
 *
 * ---
 *
 * Builds a specification string for a line (grams/width/color/meters).
 *
 * @param array<string, mixed> $line
 */
function buildReceptionSpec(array $line): string
{
    $spec = [];
    $grams = $line['grams'] ?? $line['microns'] ?? null;
    if ($grams !== null && $grams !== '') { $spec[] = 'Gramos ' . (int)$grams; }
    if (($line['width_mm'] ?? null) !== null) { $spec[] = 'Ancho ' . (int)$line['width_mm']; }
    if (($line['color'] ?? '') !== '') { $spec[] = 'Color ' . h((string)$line['color']); }
    if (($line['meters'] ?? null) !== null) { $spec[] = 'ML ' . h((string)$line['meters']); }
    return $spec === [] ? '-' : implode(' · ', $spec);
}

/**
 * Construye un label para agrupar solicitudes de material.
 *
 * Se utiliza en pantallas donde se agrupan pedidos por SKU + atributos (gramos/ancho/color/etc).
 *
 * ---
 *
 * Builds a grouping label for material requests.
 *
 * Used on screens that group requests by SKU + attributes (grams/width/color/etc).
 *
 * @param array<string, mixed> $item
 */
function materialRequestGroupLabel(array $item): string
{
    $parts = [];
    $product = trim((string)($item['sku_description'] ?? $item['requested_item'] ?? 'Bobina'));
    if ($product !== '') {
        $parts[] = $product;
    }

    $spec = [];
    $grams = $item['grams'] ?? null;
    $width = $item['width_mm'] ?? null;
    $color = $item['color'] ?? null;
    $meters = $item['meters'] ?? null;
    $availableQty = $item['available_qty'] ?? null;

    if ($grams !== null && $grams !== '') { $spec[] = 'Gramos ' . (string)$grams; }
    if ($width !== null && $width !== '') { $spec[] = 'Ancho ' . (string)$width . ' mm'; }
    if ($color !== null && trim((string)$color) !== '') { $spec[] = 'Color ' . trim((string)$color); }
    if ($meters !== null && $meters !== '') { $spec[] = 'ML ' . (string)$meters; }
    if ($availableQty !== null && $availableQty !== '') { $spec[] = 'Disponibles ' . (string)$availableQty; }

    if ($spec !== []) {
        $parts[] = implode(' · ', $spec);
    }

    return implode(' | ', $parts);
}

/**
 * Parsea un groupKey (serializado con “|”) a un arreglo de campos.
 *
 * ---
 *
 * Parses a groupKey (serialized with “|”) into an array of fields.
 *
 * @return array{sku_code:string,sku_description:string,grams:string,width_mm:string,color:string,meters:string,process_stage:string}
 */
function parseMaterialRequestGroupKey(?string $groupKey): array
{
    $parts = explode('|', trim((string)$groupKey));
    return [
        'sku_code' => trim((string)($parts[0] ?? '')),
        'sku_description' => trim((string)($parts[1] ?? '')),
        'grams' => trim((string)($parts[2] ?? '')),
        'width_mm' => trim((string)($parts[3] ?? '')),
        'color' => trim((string)($parts[4] ?? '')),
        'meters' => trim((string)($parts[5] ?? '')),
        'process_stage' => trim((string)($parts[6] ?? '')),
    ];
}

/**
 * Genera un groupKey para una bobina basado en sus atributos relevantes.
 *
 * ---
 *
 * Builds a groupKey for a roll based on its relevant attributes.
 *
 * @param array<string, mixed> $roll
 */
function buildMaterialRequestGroupKeyFromRollData(array $roll): string
{
    return implode('|', [
        (string)($roll['sku_code'] ?? ''),
        (string)($roll['sku_description'] ?? ''),
        (string)($roll['grams'] ?? ''),
        (string)($roll['width_mm'] ?? ''),
        trim((string)($roll['color'] ?? '')),
        (string)($roll['meters'] ?? ''),
        (string)($roll['process_stage'] ?? ''),
    ]);
}

/**
 * Formatea fecha y hora (string) a d.m.Y H:i:s.
 *
 * Si no se puede parsear, devuelve el string original.
 *
 * ---
 *
 * Formats a date-time string as d.m.Y H:i:s.
 *
 * If parsing fails, returns the original string.
 */
function formatLabelDateTime(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }

    return date('d.m.Y H:i:s', $ts);
}

/** Código de barras a 203 ppp, con la misma regla de tamaño que PrintService. */
const BARCODE_MODULE_MM = 0.375;
const BARCODE_FINE_MODULE_MM = 0.25;
const BARCODE_MAX_WIDTH_MM = 65;
const BARCODE_HEIGHT_MM = 12.5;

/** Anchos de barras y espacios de cada símbolo Code 128. */
function code128Patterns(): array
{
    return [
        '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
        '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
        '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
        '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
        '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
        '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
        '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
        '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
        '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
        '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
        '114131','311141','411131','211412','211214','211232','2331112'
    ];
}

/**
 * Código de barras Code 128 (juego B) en SVG, con el mismo tamaño físico que en las etiquetas térmicas.
 */
function code128Svg(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $patterns = code128Patterns();
    $startCode = 104;
    $chars = [$startCode];
    $checkSum = $startCode;

    for ($i = 0; $i < strlen($text); $i++) {
        $val = ord($text[$i]) - 32;
        if ($val < 0 || $val > 95) {
            return '';
        }
        $chars[] = $val;
        $checkSum += $val * ($i + 1);
    }

    $chars[] = $checkSum % 103;
    $chars[] = 106; // Stop Code

    $bars = [];
    $x = 10; // Margen de 10 módulos que necesita la pistola
    foreach ($chars as $code) {
        $pat = $patterns[$code];
        for ($p = 0; $p < strlen($pat); $p++) {
            $w = (int)$pat[$p];
            if ($p % 2 === 0) {
                $bars[] = '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . BARCODE_HEIGHT_MM . '" fill="#000"/>';
            }
            $x += $w;
        }
    }
    $total = $x + 10;
    $module = ($total - 20) * BARCODE_MODULE_MM <= BARCODE_MAX_WIDTH_MM ? BARCODE_MODULE_MM : BARCODE_FINE_MODULE_MM;

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . ($total * $module) . 'mm" height="' . BARCODE_HEIGHT_MM . 'mm" viewBox="0 0 ' . $total . ' ' . BARCODE_HEIGHT_MM . '" preserveAspectRatio="none" shape-rendering="crispEdges" role="img" aria-label="' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '">' . implode('', $bars) . '</svg>';
}

/**
 * Obtiene o genera el token CSRF para la sesión actual.
 *
 * Estrategia:
 * - Prioriza cookie (persistencia entre requests).
 * - Si no hay cookie, usa sesión.
 * - Si no existe ninguno, genera un token seguro (random_bytes).
 * - Sincroniza cookie + sesión.
 *
 * ---
 *
 * Gets or generates the CSRF token for the current session.
 *
 * Strategy:
 * - Prefer cookie (persistence across requests).
 * - If missing, use session value.
 * - If neither exists, generate a secure token (random_bytes).
 * - Sync cookie + session.
 */
function csrfToken(): string
{
    $token = trim((string)($_COOKIE[csrfCookieName()] ?? ''));
    if ($token === '') {
        $token = trim((string)($_SESSION['csrf'] ?? ''));
    }
    if ($token === '') {
        $token = bin2hex(random_bytes(32));
    }

    $_SESSION['csrf'] = $token;
    setCsrfCookie($token);

    return $token;
}

/**
 * Valida CSRF para requests POST.
 *
 * Requiere que el token enviado en $_POST['_csrf'] coincida con:
 * - la cookie CSRF, o
 * - el token en sesión.
 *
 * Si falla:
 * - responde HTTP 400
 * - renderiza una pantalla de error
 *
 * ---
 *
 * Validates CSRF for POST requests.
 *
 * Requires the token sent in $_POST['_csrf'] to match:
 * - the CSRF cookie, or
 * - the session token.
 *
 * On failure:
 * - returns HTTP 400
 * - renders an error screen
 */
function requireCsrf(): void
{
    $token = (string)($_POST['_csrf'] ?? '');
    $cookieToken = trim((string)($_COOKIE[csrfCookieName()] ?? ''));
    $sessionToken = trim((string)($_SESSION['csrf'] ?? ''));

    $validCookieToken = $token !== '' && $cookieToken !== '' && hash_equals($cookieToken, $token);
    $validSessionToken = $token !== '' && $sessionToken !== '' && hash_equals($sessionToken, $token);

    if (!$validCookieToken && !$validSessionToken) {
        http_response_code(400);
        render('CSRF', '<div class="card"><div class="err">Solicitud inválida (CSRF).</div></div>');
        exit;
    }

    if ($cookieToken === '' || !$validCookieToken) {
        setCsrfCookie($token);
    }
    $_SESSION['csrf'] = $token;
}

/**
 * Nombre de la cookie CSRF de la aplicación.
 *
 * ---
 *
 * Application CSRF cookie name.
 */
function csrfCookieName(): string
{
    return 'unibag_csrf';
}

/**
 * Setea/actualiza la cookie CSRF con opciones seguras.
 *
 * Si headers ya fueron enviados, actualiza $_COOKIE como fallback para el request actual.
 *
 * ---
 *
 * Sets/updates the CSRF cookie with secure options.
 *
 * If headers were already sent, it updates $_COOKIE as a fallback for the current request.
 */
function setCsrfCookie(string $token): void
{
    if ($token === '') {
        return;
    }

    if ((string)($_COOKIE[csrfCookieName()] ?? '') === $token) {
        return;
    }

    if (headers_sent()) {
        $_COOKIE[csrfCookieName()] = $token;
        return;
    }

    $options = [
        'expires' => time() + 86400 * 30,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    setcookie(csrfCookieName(), $token, $options);
    $_COOKIE[csrfCookieName()] = $token;
}

/**
 * Expira la cookie CSRF del cliente.
 *
 * Si headers ya fueron enviados, solo elimina el valor en $_COOKIE (request actual).
 *
 * ---
 *
 * Expires the client CSRF cookie.
 *
 * If headers were already sent, it only unsets $_COOKIE (current request).
 */
function expireCsrfCookie(): void
{
    if (headers_sent()) {
        unset($_COOKIE[csrfCookieName()]);
        return;
    }

    $options = [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    setcookie(csrfCookieName(), '', $options);
    unset($_COOKIE[csrfCookieName()]);
}

/**
 * Detecta si el request actual se considera HTTPS.
 *
 * Soporta:
 * - $_SERVER['HTTPS'] (modo directo)
 * - HTTP_X_FORWARDED_PROTO (cuando hay reverse proxy)
 *
 * ---
 *
 * Detects whether the current request should be treated as HTTPS.
 *
 * Supports:
 * - $_SERVER['HTTPS'] (direct mode)
 * - HTTP_X_FORWARDED_PROTO (reverse proxy)
 */
function isHttpsRequest(): bool
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off') {
        return true;
    }

    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $forwardedProto === 'https';
}

/**
 * Define los “modos” de autenticación del menú (para login/landing).
 *
 * ---
 *
 * Defines authentication “modes” for the menu (used on login/landing).
 *
 * @return array<int, array{id:int,label:string,icon:string,active_bg:string,inactive_bg:string,show_plant:bool}>
 */
function authModeDefinitions(): array
{
    return [
        0 => ['id' => 0, 'label' => 'ERP', 'icon' => 'Documento', 'active_bg' => '#00A9A6', 'inactive_bg' => '#F9F9F9', 'show_plant' => false],
    ];
}

/**
 * Catálogo de “empresas” disponibles para login/navegación.
 *
 * Nota: hoy se usa principalmente como metadata visual (label); la lógica de permisos
 * y acceso real se basa en el usuario autenticado.
 *
 * ---
 *
 * “Companies” catalog used by login/navigation.
 *
 * Note: currently mostly used as visual metadata (label); real access control relies
 * on the authenticated user permissions.
 *
 * @return array<int, array{id:int,label:string}>
 */
function authCompanyDefinitions(): array
{
    return [
        20010 => ['id' => 20010, 'label' => 'UNIBAG CHILE'],
        20019 => ['id' => 20019, 'label' => 'UNIBAG PERÚ'],
        20020 => ['id' => 20020, 'label' => 'UNIBAG MÉXICO'],
    ];
}

/**
 * Catálogo de “plantas” (sitios) disponibles para login/navegación.
 *
 * Se presenta en UI cuando el modo/área lo requiere.
 *
 * ---
 *
 * “Plants” (sites) catalog used by login/navigation.
 *
 * It is shown in the UI when the selected mode/area requires it.
 *
 * @return array<int, array{id:int,label:string}>
 */
function authPlantDefinitions(): array
{
    return [
        1 => ['id' => 1, 'label' => 'Planta Extrusión'],
        2 => ['id' => 2, 'label' => 'Planta Conversión'],
        3 => ['id' => 3, 'label' => 'Bodega Central'],
    ];
}

/**
 * Resuelve la columna de permisos del usuario según el modo (appMode).
 *
 * Se usa al autenticar para exigir que el usuario tenga la marca (1) en la columna
 * correspondiente (ej. can_erp, can_production, etc.).
 *
 * ---
 *
 * Resolves the user permission column for a given mode (appMode).
 *
 * Used during authentication to require the user to have the corresponding flag set (1)
 * in the relevant column (e.g., can_erp, can_production, etc.).
 */
function authPermissionColumn(int $appMode): string
{
    return match ($appMode) {
        0 => 'can_erp',
        1 => 'can_production',
        2 => 'can_operator',
        3 => 'can_warehouse',
        4 => 'can_marketing',
        default => '',
    };
}

/**
 * Define el catálogo de “áreas” disponibles (ERP/Recepción/Producción/Balanza).
 *
 * Cada área define:
 * - label: nombre en UI
 * - home: ruta de inicio al entrar al área
 *
 * ---
 *
 * Defines the available “areas” catalog (ERP/Reception/Production/Scale).
 *
 * Each area defines:
 * - label: UI name
 * - home: landing route when entering the area
 *
 * @return array<string, array{id:string,label:string,home:string}>
 */
function erpAreaDefinitions(): array
{
    return [
        'ERP' => [
            'id' => 'ERP',
            'label' => 'ERP',
            'home' => '/',
        ],
        'RECEPTION' => [
            'id' => 'RECEPTION',
            'label' => 'Recepción',
            'home' => '/purchase-orders?status=active&supplier_type=NATIONAL',
        ],
        'PRODUCTION' => [
            'id' => 'PRODUCTION',
            'label' => 'Producción',
            'home' => '/production/machines',
        ],
        'SCALE' => [
            'id' => 'SCALE',
            'label' => 'Balanza',
            'home' => '/scale/sealing-waste',
        ],
    ];
}

/**
 * Normaliza un valor a un área válida del sistema.
 *
 * Si el valor no existe en erpAreaDefinitions(), retorna ERP.
 *
 * ---
 *
 * Normalizes a value into a valid system area.
 *
 * If the value is not present in erpAreaDefinitions(), it falls back to ERP.
 */
function normalizeErpArea(string $value): string
{
    $value = strtoupper(trim($value));
    return array_key_exists($value, erpAreaDefinitions()) ? $value : 'ERP';
}

/**
 * Devuelve el área “actual” almacenada en sesión (normalizada).
 *
 * ---
 *
 * Returns the current area stored in session (normalized).
 */
function currentSessionArea(): string
{
    return normalizeErpArea((string)($_SESSION['erp_area'] ?? 'ERP'));
}

/**
 * Indica si la navegación actual está en contexto de inventario desde Recepción.
 *
 * Se usa para permitir que ciertas rutas (/work-orders, /rolls, etc.) se “traten”
 * como parte del área RECEPTION cuando se navega desde inventario.
 *
 * ---
 *
 * Indicates whether the current navigation is in the inventory context from Reception.
 *
 * Used to treat certain routes (/work-orders, /rolls, etc.) as part of the RECEPTION
 * area when navigation originated from inventory.
 */
function inventoryNavigationContext(): bool
{
    return currentSessionArea() === 'RECEPTION'
        && strtolower(trim((string)($_GET['nav'] ?? ''))) === 'inventory';
}

/**
 * Construye parámetros “nav” para conservar contexto de inventario al navegar.
 *
 * Solo aplica cuando el área actual es RECEPTION. Opcionalmente, agrega el código
 * de bodega (bodega=...).
 *
 * ---
 *
 * Builds “nav” parameters to preserve inventory navigation context.
 *
 * Only applies when current area is RECEPTION. Optionally includes the selected
 * warehouse code (bodega=...).
 *
 * @return array<string, string>
 */
function inventoryNavigationParams(?int $warehouseCode = null): array
{
    if (currentSessionArea() !== 'RECEPTION') {
        return [];
    }
    $params = ['nav' => 'inventory'];
    $resolvedWarehouseCode = $warehouseCode ?? (isset($_GET['bodega']) ? (int)$_GET['bodega'] : 0);
    if ($resolvedWarehouseCode > 0) {
        $params['bodega'] = (string)$resolvedWarehouseCode;
    }
    return $params;
}

/**
 * Agrega querystring a una ruta, ignorando parámetros vacíos.
 *
 * ---
 *
 * Appends a querystring to a path, ignoring empty parameters.
 *
 * @param array<string, scalar|null> $params
 */
function withQuery(string $path, array $params = []): string
{
    $params = array_filter($params, static fn($value): bool => $value !== null && $value !== '');
    if ($params === []) {
        return $path;
    }
    return $path . (str_contains($path, '?') ? '&' : '?') . http_build_query($params);
}

/**
 * URL de retorno a la pantalla de inventario (/stock), preservando bodega si aplica.
 *
 * ---
 *
 * Return URL to inventory screen (/stock), preserving warehouse when applicable.
 */
function inventoryReturnUrl(): string
{
    $warehouseCode = isset($_GET['bodega']) ? (int)$_GET['bodega'] : 0;
    return withQuery('/stock', $warehouseCode > 0 ? ['bodega' => (string)$warehouseCode] : []);
}

/**
 * Export simple de inventario disponible a Excel (XLS vía HTML).
 *
 * ---
 *
 * Simple export of available inventory to Excel (HTML-based XLS).
 *
 * @param array<int, array<string, mixed>> $rows
 */
function outputInventoryExcel(string $filename, array $rows): void
{
    ob_start();
    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'body{font-family:Arial,sans-serif;color:#0f172a}';
    echo 'table{border-collapse:collapse;width:100%}';
    echo 'th,td{border:1px solid #cbd5e1;padding:6px 8px}';
    echo 'th{background:#0f172a;color:#fff;font-weight:700;text-align:center}';
    echo 'tr:nth-child(even){background:#f8fafc}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.num-dec3{mso-number-format:"\#\,\#\#0\.000";text-align:right}';
    echo '.text-cell{mso-number-format:"\@"}';
    echo '</style></head><body>';
    echo '<table border="1">';
    echo '<tr><th>Codigo SKU</th><th>Cantidad disponible</th></tr>';
    foreach ($rows as $row) {
        $qty = (float)($row['available_qty'] ?? 0);
        $isWhole = floor($qty) == $qty;
        $cls = $isWhole ? 'num-int' : 'num-dec3';
        $val = $isWhole ? (int)$qty : round($qty, 3);
        echo '<tr>';
        echo '<td class="text-cell">' . h((string)($row['sku_code'] ?? '')) . '</td>';
        echo '<td class="' . $cls . '">' . $val . '</td>';
        echo '</tr>';
    }
    if ($rows === []) {
        echo '<tr><td colspan="2">Sin stock disponible</td></tr>';
    }
    echo '</table>';
    echo '</body></html>';
    $html = (string)ob_get_clean();
    SimpleXlsx::streamHtml($filename, $html, 'Inventario');
    exit;
}

/**
 * Export del detalle de una toma de inventario a Excel (OpenXML .xlsx).
 *
 * Incluye cabecera de columnas con:
 * - SKU y atributos
 * - cantidades de sistema / físico / diferencia
 * - bodega (código + nombre)
 *
 * ---
 *
 * Inventory count detail export to Excel (OpenXML .xlsx).
 *
 * Includes a column header with:
 * - SKU and attributes
 * - system / physical / difference quantities
 * - warehouse (code + name)
 *
 * @param array<int, array<string, mixed>> $rows
 */
function outputInventoryCountDetailExcel(string $filename, array $rows, array $inventoryCount): void
{
    $warehouseLabel = trim((string)($inventoryCount['warehouse_code'] ?? '')) . ' (' . trim((string)($inventoryCount['warehouse_name'] ?? '')) . ')';
    ob_start();
    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'body{font-family:Arial,sans-serif;color:#0f172a}';
    echo 'table{border-collapse:collapse;width:100%}';
    echo 'th,td{border:1px solid #cbd5e1;padding:6px 8px}';
    echo 'th{background:#0f172a;color:#fff;font-weight:700;text-align:center}';
    echo 'tr:nth-child(even){background:#f8fafc}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.num-dec3{mso-number-format:"\#\,\#\#0\.000";text-align:right}';
    echo '.text-cell{mso-number-format:"\@"}';
    echo '</style></head><body>';
    echo '<table border="1">';
    echo '<tr><th>Numero</th><th>Articulo</th><th>Familia</th><th>Cod. color</th><th>Alto</th><th>Gramos</th><th>Metros</th><th>Unidad</th><th>Bodega</th><th>Sistema</th><th>Fisico</th><th>Dif</th></tr>';
    foreach ($rows as $row) {
        $sysQty = (float)($row['system_qty'] ?? 0);
        $phyQty = (float)($row['physical_qty'] ?? 0);
        $diffQty = (float)($row['diff_qty'] ?? 0);
        echo '<tr>';
        echo '<td class="text-cell">' . h((string)($row['sku_code'] ?? '')) . '</td>';
        echo '<td class="text-cell">' . h((string)($row['article_code'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['family_color'] ?? '')) . '</td>';
        echo '<td class="text-cell">' . h((string)($row['color_code'] ?? '')) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($row['height_mm'] ?? 0)) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($row['grams'] ?? 0)) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($row['meters'] ?? 0)) . '</td>';
        echo '<td class="text-cell">' . h((string)($row['unit_code'] ?? '')) . '</td>';
        echo '<td>' . h(trim($warehouseLabel) !== '()' ? $warehouseLabel : '') . '</td>';
        echo '<td class="' . (floor($sysQty) == $sysQty ? 'num-int' : 'num-dec3') . '">' . (floor($sysQty) == $sysQty ? (int)$sysQty : round($sysQty, 3)) . '</td>';
        echo '<td class="' . (floor($phyQty) == $phyQty ? 'num-int' : 'num-dec3') . '">' . (floor($phyQty) == $phyQty ? (int)$phyQty : round($phyQty, 3)) . '</td>';
        echo '<td class="' . (floor($diffQty) == $diffQty ? 'num-int' : 'num-dec3') . '">' . (floor($diffQty) == $diffQty ? (int)$diffQty : round($diffQty, 3)) . '</td>';
        echo '</tr>';
    }
    if ($rows === []) {
        echo '<tr><td colspan="12">Sin registros</td></tr>';
    }
    echo '</table>';
    echo '</body></html>';
    $html = (string)ob_get_clean();
    SimpleXlsx::streamHtml($filename, $html, 'Conteo');
    exit;
}

/**
 * Calcula permisos por área (ERP/RECEPTION/PRODUCTION/SCALE) desde un registro de usuario.
 *
 * Nota: hoy RECEPTION y PRODUCTION se derivan a partir de can_erp y flags específicos.
 *
 * ---
 *
 * Computes area permissions (ERP/RECEPTION/PRODUCTION/SCALE) from a user row.
 *
 * Note: RECEPTION and PRODUCTION are derived from can_erp plus specific flags.
 *
 * @param array<string, mixed> $user
 * @return array{ERP:bool,RECEPTION:bool,PRODUCTION:bool,SCALE:bool}
 */
function userAreaPermissions(array $user): array
{
    $canErp = (int)($user['can_erp'] ?? 0) === 1;
    $canReception = $canErp || (int)($user['can_warehouse'] ?? 0) === 1;
    $canProduction = $canErp || (int)($user['can_production'] ?? 0) === 1 || (int)($user['can_operator'] ?? 0) === 1;

    return [
        'ERP' => $canErp,
        'RECEPTION' => $canReception,
        'PRODUCTION' => $canProduction,
        'SCALE' => $canProduction,
    ];
}

/**
 * Lee permisos por área desde la sesión actual.
 *
 * ---
 *
 * Reads area permissions from the current session.
 *
 * @return array{ERP:bool,RECEPTION:bool,PRODUCTION:bool,SCALE:bool}
 */
function sessionAreaPermissions(): array
{
    $canErp = (int)($_SESSION['perm_area_erp'] ?? 0) === 1;
    return [
        'ERP' => $canErp,
        'RECEPTION' => (int)($_SESSION['perm_area_reception'] ?? ($canErp ? 1 : 0)) === 1,
        'PRODUCTION' => (int)($_SESSION['perm_area_production'] ?? 0) === 1,
        'SCALE' => (int)($_SESSION['perm_area_scale'] ?? $_SESSION['perm_area_production'] ?? 0) === 1,
    ];
}

/**
 * Indica si un set de permisos permite acceder a un área.
 *
 * ---
 *
 * Checks whether a given permissions set can access an area.
 */
function userCanAccessArea(string $area, array $permissions): bool
{
    $area = normalizeErpArea($area);
    return (bool)($permissions[$area] ?? false);
}

/**
 * Retorna el “home” de la primera área permitida para un usuario.
 *
 * Se usa como fallback cuando el usuario intenta entrar a un área sin permiso.
 *
 * ---
 *
 * Returns the “home” route for the first allowed area.
 *
 * Used as a fallback when the user tries to access a non-permitted area.
 */
function firstAllowedAreaHome(array $permissions): string
{
    $currentArea = currentSessionArea();
    if (userCanAccessArea($currentArea, $permissions)) {
        return erpAreaDefinitions()[$currentArea]['home'] ?? '/';
    }

    foreach (['PRODUCTION', 'ERP', 'RECEPTION', 'SCALE'] as $area) {
        if (userCanAccessArea($area, $permissions)) {
            return erpAreaDefinitions()[$area]['home'] ?? '/';
        }
    }

    return '/logout';
}

/**
 * Infere el área solicitada según la ruta del request.
 *
 * Canales:
 * - /scale -> SCALE
 * - /production, /waste -> PRODUCTION
 * - Todo lo demás canalizado a ERP
 *
 * ---
 *
 * Infers the requested area from the request path.
 */
function detectRequestedArea(string $path): string
{
    if ($path === '/' || $path === '/reports/production-dashboard') {
        return currentSessionArea();
    }
    if ($path === '/stock/inventory-counts') {
        return userCanAccessArea('ERP',sessionAreaPermissions()) ? 'ERP' : 'RECEPTION';
    }
    if (str_starts_with($path, '/scale')) {
        return 'SCALE';
    }
    if (
        str_starts_with($path, '/production')
        || str_starts_with($path, '/waste')
        || str_starts_with($path, '/prodwrk')
    ) {
        return 'PRODUCTION';
    }
    if (
        str_starts_with($path, '/work-orders')
        || str_starts_with($path, '/chemicals')
        || str_starts_with($path, '/cliches')
        || str_starts_with($path, '/cut')
        || str_starts_with($path, '/rolls')
        || str_starts_with($path, '/boxes')
        || str_starts_with($path, '/pallets')
    ) {
        // Un operador en sesion de Produccion permanece en Produccion; el resto
        // de sesiones conserva el comportamiento original (area ERP).
        return currentSessionArea() === 'PRODUCTION' ? 'PRODUCTION' : 'ERP';
    }
    if (
        str_starts_with($path, '/reception')
        || str_starts_with($path, '/purchase-orders')
        || str_starts_with($path, '/import-containers')
    ) {
        if (currentSessionArea() === 'ERP') {
            return 'ERP';
        }
        return 'RECEPTION';
    }

    // Sesion de Recepcion: su menu incluye Bodega/Inventario (stock, bobinas, cajas, pallets).
    // Estas rutas deben resolverse como RECEPTION para no rebotar al inicio de Recepcion.
    if (
        currentSessionArea() === 'RECEPTION'
        && (
            str_starts_with($path, '/stock')
            || str_starts_with($path, '/mobile/picking')
            || str_starts_with($path, '/rolls')
            || str_starts_with($path, '/boxes')
            || str_starts_with($path, '/pallets')
            || str_starts_with($path, '/maquila')
        )
    ) {
        return 'RECEPTION';
    }

    return 'ERP';
}

/**
 * Determina si una ruta se considera “Producción” (incluye / como dashboard).
 *
 * ---
 *
 * Determines whether a path is considered “Production” (including / as the dashboard).
 */
function isProductionPath(string $path): bool
{
    return $path === '/' || detectRequestedArea($path) === 'PRODUCTION';
}

/**
 * Indica si estamos en modo “solo lectura” de Producción desde el área ERP.
 *
 * Se usa para bloquear acciones operativas en la UI cuando el usuario está
 * navegando producción desde ERP (trazabilidad).
 *
 * ---
 *
 * Indicates whether we are in “read-only” Production mode from ERP area.
 *
 * Used to block operational actions when the user is browsing production from ERP (traceability).
 */
function isErpProductionReadOnlyMode(?string $path = null): bool
{
    $path = $path ?? (string)($GLOBALS['path'] ?? '/');
    return currentSessionArea() === 'ERP' && isProductionPath($path);
}

/**
 * Indica si el usuario puede acceder a trazabilidad de OTs desde ERP.
 *
 * ---
 *
 * Indicates whether the user can access work order traceability from ERP.
 */
function canAccessWorkOrderTraceability(): bool
{
    return userCanAccessArea('ERP', sessionAreaPermissions());
}

/**
 * Filtra OTs “pendientes” para dejar solo las que corresponden a la máquina (o tipo)
 * asociada al turno activo.
 *
 * ---
 *
 * Filters pending work orders to keep only those matching the active shift machine (or machine type).
 *
 * @param array<int, array<string, mixed>> $workOrders
 * @param array<string, mixed>|null $activeShiftSession
 * @return array<int, array<string, mixed>>
 */
function filterPendingWorkOrdersByShiftMachine(array $workOrders, ?array $activeShiftSession): array
{
    if ($activeShiftSession === null) {
        return $workOrders;
    }

    $shiftErpMachineId = (int)($activeShiftSession['erp_machine_id'] ?? 0);
    $shiftErpMachineTypeId = (int)($activeShiftSession['erp_machine_type_id'] ?? 0);

    if ($shiftErpMachineId <= 0 && $shiftErpMachineTypeId <= 0) {
        return $workOrders;
    }

    $filtered = array_values(array_filter(
        $workOrders,
        static function (array $workOrder) use ($shiftErpMachineId, $shiftErpMachineTypeId): bool {
            $workOrderMachineId = (int)($workOrder['erp_machine_id'] ?? 0);
            $workOrderMachineTypeId = (int)($workOrder['erp_machine_type_id'] ?? 0);

            if ($shiftErpMachineId > 0) {
                return $workOrderMachineId === $shiftErpMachineId;
            }

            return $shiftErpMachineTypeId > 0 && $workOrderMachineTypeId === $shiftErpMachineTypeId;
        }
    ));

    return $filtered;
}

/**
 * Bloquea acciones de escritura cuando se navega producción desde ERP en modo solo lectura.
 *
 * Responde 403 y muestra una pantalla explicativa.
 *
 * ---
 *
 * Blocks write actions when browsing production from ERP in read-only mode.
 *
 * Responds with 403 and renders an explanatory screen.
 */
function denyErpProductionWriteAccess(): void
{
    if (!isErpProductionReadOnlyMode()) {
        return;
    }

    http_response_code(403);
    render('Solo lectura', '<div class="card"><div style="font-size:18px;font-weight:800;margin-bottom:8px">Acceso de solo lectura</div><div class="muted">Desde ERP solo puedes revisar el avance, estado y movimientos de la OT. Las acciones operativas se realizan desde el área de Producción.</div><div style="margin-top:12px"><a class="btn secondary" href="/work-orders">Volver a trazabilidad</a></div></div>');
    exit;
}

/**
 * Indica si el usuario está en el área donde se asignan pallets a bodegas (Recepción).
 *
 * ---
 *
 * Indicates whether the user is in the area that assigns pallets to warehouses (Reception).
 */
function isWarehousePalletAssignmentArea(): bool
{
    return (currentSessionArea() === 'RECEPTION' || currentSessionArea() === 'ERP') && ((bool)(sessionAreaPermissions()['RECEPTION'] ?? false));
}

/**
 * Retorna el título/label de la sección de Producción según la ruta actual.
 *
 * Se usa en el header del “shell” de producción para mostrar contexto al usuario.
 *
 * ---
 *
 * Returns the Production section title/label based on the current path.
 *
 * Used in the production shell header to provide context to the user.
 */
function productionSectionLabel(string $path): string
{
    if ($path === '/') {
        return 'Panel de producción';
    }
    if (str_starts_with($path, '/production/machines') || str_starts_with($path, '/production/shifts')) {
        return 'Asignación máquina';
    }
    if (str_starts_with($path, '/production/work-orders') || str_starts_with($path, '/work-orders')) {
        return 'Órdenes de trabajo';
    }
    if (str_starts_with($path, '/waste/molino')) {
        return 'Molino · Gestión de residuos';
    }
    if (str_starts_with($path, '/waste/compactadora')) {
        return 'Compactadora · Gestión de residuos';
    }
    if (str_starts_with($path, '/waste')) {
        return 'Gestión de residuos';
    }
    if (str_starts_with($path, '/production/traceability')) {
        return 'Trazabilidad de Inicio a Fin';
    }
    if (str_starts_with($path, '/chemicals')) {
        return 'Pesajes de tintas';
    }
    if (str_starts_with($path, '/cut') || str_starts_with($path, '/boxes')) {
        return 'Proceso de corte';
    }
    if (str_starts_with($path, '/pallets')) {
        return 'Pallets terminados';
    }

    return 'Producción';
}

/**
 * Renderiza el “shell” (layout) del área Producción.
 *
 * Incluye:
 * - Sidebar de navegación (móvil y escritorio).
 * - Topbar con sección actual, usuario y empresa.
 * - Contenedor <main> donde se inyecta $body.
 *
 * ---
 *
 * Renders the Production area “shell” (layout).
 *
 * Includes:
 * - Navigation sidebar (mobile and desktop).
 * - Topbar showing current section, user and company.
 * - <main> container where $body is injected.
 */
function renderProductionShell(string $body, string $currentPath, string $displayName, string $companyName): void
{
    echo renderView('layouts/production_shell', get_defined_vars());
}

/**
 * Asegura el esquema mínimo para autenticación (tabla auth_users) en la BD TRZ.
 *
 * Características:
 * - Opera una sola vez por request (static $schemaReady).
 * - Crea la tabla si no existe.
 * - Inserta un usuario demo si no existe.
 * - Intenta registrar un “schema version” en app_settings si la tabla existe.
 *
 * ---
 *
 * Ensures the minimum authentication schema (auth_users table) in the TRZ database.
 *
 * Features:
 * - Runs once per request (static $schemaReady).
 * - Creates the table if missing.
 * - Inserts a demo user if missing.
 * - Tries to store a schema version in app_settings when available.
 */
function ensureAuthSchema(PDO $pdo): void
{
    static $schemaReady = false;
    if ($schemaReady) {
        return;
    }

    $schemaVersion = 'auth_v1';
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'auth_schema_version' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row !== false && (string)($row['setting_value'] ?? '') === $schemaVersion) {
            $schemaReady = true;
            return;
        }
    } catch (Throwable) {
        // Continue with the schema bootstrap if app_settings is not ready yet.
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS auth_users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(60) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(120) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            can_erp TINYINT(1) NOT NULL DEFAULT 1,
            can_production TINYINT(1) NOT NULL DEFAULT 1,
            can_operator TINYINT(1) NOT NULL DEFAULT 1,
            can_warehouse TINYINT(1) NOT NULL DEFAULT 1,
            can_marketing TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_auth_users_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO app_settings (setting_key, setting_value) VALUES ('auth_schema_version', :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $stmt->execute([':v' => $schemaVersion]);
    } catch (Throwable) {
        // The auth table is already available; a missing setting should not block the request.
    }

    $schemaReady = true;
}

/**
 * Renderiza la pantalla de login.
 *
 * Entradas:
 * - $error: mensaje a mostrar (credenciales inválidas, etc.)
 * - $state: valores para re-render (usuario, empresa, área) al fallar el login.
 *
 * Incluye el token CSRF dentro del formulario.
 *
 * ---
 *
 * Renders the login screen.
 *
 * Inputs:
 * - $error: message to display (invalid credentials, etc.)
 * - $state: values used to re-render (user, company, area) after a failed login.
 *
 * Includes the CSRF token in the form.
 */
function renderLoginPage(?string $error = null, array $state = []): void
{
    http_response_code(200);
    echo renderView('layouts/login', get_defined_vars());
    exit;
}

/**
 * Render HTML base para páginas del sistema (layout general).
 *
 * Este helper:
 * - Emite HTML <head> + estilos base (UI del sistema).
 * - Inserta una barra superior con navegación según área (ERP/Recepción/Producción).
 * - Imprime el contenido $body dentro del layout.
 *
 * Nota: el layout de Producción tiene su “shell” propio (renderProductionShell).
 *
 * ---
 *
 * Base HTML renderer for system pages (general layout).
 *
 * This helper:
 * - Outputs the HTML <head> + base styles (system UI).
 * - Inserts the top navigation depending on the current area (ERP/Reception/Production).
 * - Prints $body inside the layout.
 *
 * Note: Production uses its own shell (renderProductionShell).
 */
function render(string $title, string $body): void
{
    http_response_code(200);
    echo renderView('layouts/erp_shell', get_defined_vars());
    exit;
}


/**
 * Renderiza una plantilla HTML limpia ubicada en src/Views/.
 *
 * @param array<string, mixed> $data
 */
function renderView(string $__unibagViewName, array $__unibagViewData = []): string
{
    $__unibagViewFile = __DIR__ . '/../Views/' . ltrim($__unibagViewName, '/') . '.php';
    if (!is_file($__unibagViewFile)) {
        return '';
    }
    extract($__unibagViewData, EXTR_SKIP);
    ob_start();
    $__unibagViewResult = include $__unibagViewFile;
    $__unibagViewBuffered = (string)ob_get_clean();
    if ($__unibagViewBuffered === '' && is_string($__unibagViewResult)) {
        return $__unibagViewResult;
    }
    return $__unibagViewBuffered;
}
