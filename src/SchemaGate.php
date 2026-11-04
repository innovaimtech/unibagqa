<?php

declare(strict_types=1);

/**
 * Evita repetir en cada página las verificaciones de esquema (CREATE TABLE IF NOT EXISTS, SHOW COLUMNS...).
 * Tras una verificación exitosa se deja un archivo marcador en storage/cache; mientras exista (misma versión
 * y misma base de datos) la verificación se omite. Al subir una versión nueva basta cambiar el texto de $version.
 */
final class SchemaGate
{
    private static function file(string $name, string $version): string
    {
        $db = (string)(Env::get('TRZ_DB_NAME', '') ?? '') . '@' . (string)(Env::get('TRZ_DB_HOST', '') ?? '');
        return dirname(__DIR__) . '/storage/cache/schema-' . preg_replace('/[^a-z0-9_-]/i', '', $name) . '-' . substr(hash('sha256', $version . '|' . $db), 0, 16) . '.ok';
    }

    public static function done(string $name, string $version): bool
    {
        return is_file(self::file($name, $version));
    }

    public static function mark(string $name, string $version): void
    {
        $file = self::file($name, $version);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, date('c'));
    }
}
