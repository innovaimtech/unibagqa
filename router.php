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
 * 1. Si la petición corresponde a un archivo estático real existente en /public
 *    (ej. imágenes, archivos CSS, JavaScript o fuentes), retorna false para que
 *    el servidor integrado de PHP lo entregue directamente sin pasar por PHP.
 * 2. Si la ruta es dinámica (ej. /reception, /production, /api/...), delega la
 *    ejecución a public/index.php (Front Controller principal).
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$publicFile = __DIR__ . '/public' . $uri;

// Servir archivos estáticos físicos directamente
if ($uri !== '/' && file_exists($publicFile) && !is_dir($publicFile)) {
    return false;
}

// Delegar todas las rutas de la aplicación al Front Controller
require_once __DIR__ . '/public/index.php';

