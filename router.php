<?php
declare(strict_types=1);

/**
 * =============================================================================
 * Enrutador de Servidor Local PHP (router.php)
 * =============================================================================
 *
 * Propósito:
 * Este archivo actúa como punto de entrada cuando el proyecto se ejecuta
 * localmente usando el servidor web embebido de PHP:
 *   php.exe -S 127.0.0.1:8000 router.php
 * (invocado automáticamente por iniciar_proyecto.bat).
 *
 * Comportamiento:
 * 1. Si la ruta es de documentos o imágenes ERP (/docs/, /docs., /production/design-image):
 *    Delega inmediatamente a public/index.php para que unibagServeErpDocument gestione
 *    la caché local, descarga remota si no existe, o el visor interactivo completo
 *    con rotación 360° y zoom cuando se abre en el navegador.
 * 2. Si la petición corresponde a un archivo estático real existente en /public
 *    (ej. CSS, JavaScript, fuentes, librerías, favicon), lo sirve directamente
 *    con el tipo MIME adecuado y cabeceras de caché.
 * 3. Para todas las demás rutas dinámicas de la aplicación, delega la ejecución
 *    a public/index.php (Front Controller principal).
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$publicFile = __DIR__ . '/public' . $uri;

// Rutas de documentos/ERP: deben pasar siempre por unibagServeErpDocument en index.php
$isErpDoc = str_starts_with($uri, '/docs/')
    || str_starts_with($uri, '/docs.')
    || $uri === '/production/design-image'
    || $uri === '/api/document'
    || $uri === '/doc';

if (!$isErpDoc && $uri !== '/' && file_exists($publicFile) && !is_dir($publicFile)) {
    $ext = strtolower(pathinfo($publicFile, PATHINFO_EXTENSION));
    if ($ext !== 'php') {
        $mimes = [
            'css'   => 'text/css; charset=UTF-8',
            'js'    => 'application/javascript; charset=UTF-8',
            'json'  => 'application/json; charset=UTF-8',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'eot'   => 'application/vnd.ms-fontobject',
            'pdf'   => 'application/pdf',
        ];
        $mime = $mimes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($publicFile));
        header('Cache-Control: public, max-age=86400');
        readfile($publicFile);
        exit;
    }
}

// Delegar todas las rutas dinámicas y de documentos al Front Controller
require_once __DIR__ . '/public/index.php';
