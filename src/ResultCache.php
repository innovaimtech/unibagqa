<?php

declare(strict_types=1);

/**
 * Caché de resultados en archivo (storage/cache/rc-*.json) con vencimiento.
 *
 * Para cálculos de solo lectura que tardan segundos (panel de inicio, informe mensual, existencia de columnas
 * del ERP). Si el disco falla, simplemente se calcula de nuevo: el caché nunca es obligatorio.
 * Mientras un proceso calcula una clave, los demás esperan su resultado (candado de archivo) en vez de lanzar
 * la misma consulta pesada en paralelo.
 */
final class ResultCache
{
    private static function dir(): string
    {
        return dirname(__DIR__) . '/storage/cache';
    }

    private static function file(string $key): string
    {
        return self::dir() . '/rc-' . preg_replace('/[^a-z0-9_-]/i', '', substr($key, 0, 40)) . '-'
            . substr(hash('sha256', $key), 0, 16) . '.json';
    }

    /** Lee una clave vigente. */
    public static function get(string $key, int $ttlSeconds, &$found = null): mixed
    {
        $found = false;
        $file = self::file($key);
        clearstatcache(true, $file);
        $mtime = @filemtime($file);
        if ($mtime === false || $ttlSeconds <= 0 || (time() - $mtime) > $ttlSeconds) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !array_key_exists('v', $data)) {
            return null;
        }
        $found = true;
        return $data['v'];
    }

    public static function put(string $key, mixed $value): void
    {
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $json = json_encode(['v' => $value], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === false) {
            return;
        }
        $file = self::file($key);
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $json) !== false) {
            @rename($tmp, $file);
        }
        @unlink($tmp);
    }

    /**
     * Devuelve el valor guardado si sigue vigente; si no, lo calcula, lo guarda y lo devuelve.
     *
     * @template T
     * @param callable():T $compute
     * @return T
     */
    public static function remember(string $key, int $ttlSeconds, callable $compute): mixed
    {
        $value = self::get($key, $ttlSeconds, $found);
        if ($found) {
            return $value;
        }
        $lock = null;
        $dir = self::dir();
        if (is_dir($dir) || @mkdir($dir, 0775, true)) {
            $lock = @fopen(self::file($key) . '.lock', 'c');
        }
        if ($lock !== false && $lock !== null) {
            @flock($lock, LOCK_EX);
            // Otro proceso pudo terminar el cálculo mientras se esperaba el candado.
            $value = self::get($key, $ttlSeconds, $found);
            if ($found) {
                @flock($lock, LOCK_UN);
                @fclose($lock);
                return $value;
            }
        }
        try {
            $value = $compute();
            self::put($key, $value);
            return $value;
        } finally {
            if ($lock !== false && $lock !== null) {
                @flock($lock, LOCK_UN);
                @fclose($lock);
            }
        }
    }

    /**
     * Como remember(), pero si el valor venció hace poco (menos de $staleSeconds) se entrega igual y se recalcula
     * DESPUÉS de enviar la página (fastcgi_finish_request en la VPS). Así nadie espera el cálculo pesado: la
     * siguiente carga ya trae el valor nuevo. Sin PHP-FPM (p. ej. php -S) se recalcula al final de la solicitud.
     *
     * @template T
     * @param callable():T $compute
     * @return T
     */
    public static function rememberStale(string $key, int $ttlSeconds, int $staleSeconds, callable $compute): mixed
    {
        $value = self::get($key, $ttlSeconds, $found);
        if ($found) {
            return $value;
        }
        $stale = self::get($key, $ttlSeconds + $staleSeconds, $foundStale);
        if (!$foundStale) {
            return self::remember($key, $ttlSeconds, $compute);
        }
        static $scheduled = [];
        if (!isset($scheduled[$key])) {
            $scheduled[$key] = true;
            register_shutdown_function(static function () use ($key, $ttlSeconds, $compute): void {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                try {
                    self::remember($key, $ttlSeconds, $compute);
                } catch (Throwable $e) {
                    error_log('ResultCache: no se pudo recalcular ' . $key . ': ' . $e->getMessage());
                }
            });
        }
        return $stale;
    }

    /** Borra una clave (p. ej. tras guardar datos que la cambian). */
    public static function forget(string $key): void
    {
        @unlink(self::file($key));
    }
}
