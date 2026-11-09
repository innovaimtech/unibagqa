<?php

declare(strict_types=1);

/**
 * Descarga y guarda en la VPS los adjuntos del ERP (diseños docs.order, adjuntos de producción docs.prod).
 *
 * El ERP antiguo (www.erp-unibag.cl) quedó fuera de servicio por el traslado y responde 503 a todo; los archivos
 * están ahora en el servidor nuevo del ERP. Por eso se prueban varias direcciones en orden (ERP_DOCS_BASE_URLS en el
 * .env, separadas por coma) y nunca se guarda una página de error como si fuera el archivo.
 *
 * Los archivos quedan en src/Http/docs/order, src/Http/docs/prod y src/Http/docs_cache (los mismos lugares que ya
 * leía unibagServeErpDocument). Solo escribe archivos en disco; no toca ninguna base de datos.
 */
final class ErpDocumentFetcher
{
    private const DEFAULT_BASES = 'https://149.50.140.68,https://www.erp-unibag.cl';

    /** Nombre de archivo seguro (sin rutas). */
    public static function validHash(string $hash): bool
    {
        return $hash !== '' && $hash !== 'dummy' && preg_match('/^[a-zA-Z0-9_\-\.]+$/', $hash) === 1 && !str_contains($hash, '..');
    }

    /** @return string[] direcciones base del ERP, en el orden en que se prueban. */
    public static function baseUrls(): array
    {
        $raw = getenv('ERP_DOCS_BASE_URLS');
        if (!is_string($raw) || trim($raw) === '') {
            $raw = (string)($_ENV['ERP_DOCS_BASE_URLS'] ?? $_SERVER['ERP_DOCS_BASE_URLS'] ?? '');
        }
        if (trim($raw) === '') {
            $raw = self::DEFAULT_BASES;
        }
        $out = [];
        foreach (explode(',', $raw) as $u) {
            $u = rtrim(trim($u), '/');
            if ($u !== '' && preg_match('#^https?://#i', $u) === 1) {
                $out[] = $u;
            }
        }
        return $out !== [] ? array_values(array_unique($out)) : explode(',', self::DEFAULT_BASES);
    }

    public static function httpDir(): string
    {
        return __DIR__ . '/Http';
    }

    /** Ruta local donde se guarda el archivo de esa carpeta del ERP. */
    public static function localPath(string $folder, string $hash): string
    {
        return self::httpDir() . ($folder === 'docs.prod' ? '/docs/prod/' : '/docs/order/') . $hash;
    }

    /** Archivo ya guardado (caché, prod u order), o null. */
    public static function existing(string $hash): ?string
    {
        foreach ([self::httpDir() . '/docs_cache/' . $hash, self::httpDir() . '/docs/prod/' . $hash, self::httpDir() . '/docs/order/' . $hash] as $p) {
            if (is_file($p) && filesize($p) > 0) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Descarga el archivo probando cada dirección y carpeta. Devuelve ['data','mime','folder','url'] o null.
     *
     * @param string[] $folders docs.order y/o docs.prod
     */
    public static function download(string $hash, array $folders, int $timeout = 8): ?array
    {
        if (!self::validHash($hash) || !function_exists('curl_init')) {
            return null;
        }
        foreach (self::baseUrls() as $base) {
            foreach ($folders as $folder) {
                $url = $base . '/' . $folder . '/' . rawurlencode($hash);
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    // El servidor del ERP usa su IP con certificado que no corresponde al nombre.
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);
                $data = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $mime = strtolower(trim(explode(';', (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE))[0]));
                curl_close($ch);
                // Una página HTML (503 «fuera de servicio», 404, login) nunca es el adjunto.
                if ($code === 200 && is_string($data) && $data !== '' && !str_starts_with($mime, 'text/html')) {
                    return ['data' => $data, 'mime' => $mime, 'folder' => $folder, 'url' => $url];
                }
            }
        }
        return null;
    }

    /** Guarda el archivo descargado en su carpeta y en la caché. Devuelve la ruta guardada. */
    public static function store(string $hash, string $folder, string $data): ?string
    {
        if (!self::validHash($hash)) {
            return null;
        }
        $target = self::localPath($folder, $hash);
        $ok = false;
        foreach ([$target, self::httpDir() . '/docs_cache/' . $hash] as $p) {
            $dir = dirname($p);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $tmp = $p . '.tmp' . getmypid();
            if (@file_put_contents($tmp, $data) !== false && @rename($tmp, $p)) {
                $ok = true;
            } else {
                @unlink($tmp);
            }
        }
        return $ok ? $target : null;
    }

    /** Asegura que el archivo esté en la VPS: lo usa si ya está, si no lo descarga. Devuelve 'ok', 'downloaded' o 'missing'. */
    public static function ensure(string $hash, string $preferredFolder): string
    {
        if (!self::validHash($hash)) {
            return 'missing';
        }
        if (self::existing($hash) !== null) {
            return 'ok';
        }
        $other = $preferredFolder === 'docs.prod' ? 'docs.order' : 'docs.prod';
        $got = self::download($hash, [$preferredFolder, $other], 20);
        if ($got === null) {
            return 'missing';
        }
        return self::store($hash, $got['folder'], $got['data']) !== null ? 'downloaded' : 'missing';
    }
}
