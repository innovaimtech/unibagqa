<?php

declare(strict_types=1);

/**
 * Cargador simple de variables de entorno desde un archivo .env.
 *
 * Objetivo:
 * - Permitir que el proyecto corra localmente (XAMPP / php -S) sin depender de herramientas externas.
 * - Mantener compatibilidad con entornos donde las variables vienen predefinidas (y en ese caso NO se sobreescriben).
 *
 * Reglas:
 * - Ignora líneas vacías y comentarios que comienzan con #.
 * - Acepta valores con comillas simples o dobles.
 * - Solo aplica la variable si no existe todavía en el entorno (getenv === false).
 *
 * ---
 *
 * Simple environment variable loader from a .env file.
 *
 * Goal:
 * - Allow the project to run locally (XAMPP / php -S) without external tooling.
 * - Keep compatibility with environments where variables already exist (they are NOT overwritten).
 *
 * Rules:
 * - Ignores empty lines and comments starting with #.
 * - Accepts values wrapped in single or double quotes.
 * - Only sets a variable if it does not exist yet (getenv === false).
 */
final class Env
{
    /**
     * Lee el archivo indicado y registra variables en el proceso.
     *
     * - No sobreescribe variables ya definidas.
     * - Registra en getenv/putenv y también en $_ENV para acceso desde PHP.
     *
     * ---
     *
     * Reads a .env file and registers variables into the current process.
     *
     * - Does not override already defined variables.
     * - Writes to getenv/putenv and also to $_ENV for PHP access.
     *
     * @param string $path Ruta absoluta o relativa al .env
     */
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            if ($key === '') {
                continue;
            }

            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }

    /**
     * Obtiene una variable de entorno por su key.
     *
     * ---
     *
     * Reads an environment variable by key.
     *
     * @param string      $key
     * @param string|null $default Se retorna si la variable no existe
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return $value;
    }
}
