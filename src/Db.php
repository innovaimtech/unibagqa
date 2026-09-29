<?php

declare(strict_types=1);

/**
 * Fábrica de conexiones PDO para el proyecto.
 *
 * El sistema usa dos bases:
 * - TRZ: base local del sistema (trazabilidad / recepción / bonificaciones, etc.)
 * - ERP: base externa de producción (tablas prod_*, workers, etc.)
 *
 * Características:
 * - Cachea conexiones por clave (singleton simple por request) para evitar
 *   múltiples conexiones en un mismo proceso.
 * - Normaliza strings de entorno (quita BOM / caracteres invisibles) para evitar
 *   problemas típicos al copiar credenciales.
 *
 * ---
 *
 * PDO connection factory for the project.
 *
 * The system uses two databases:
 * - TRZ: local system database (traceability / reception / bonuses, etc.)
 * - ERP: external production database (prod_* tables, workers, etc.)
 *
 * Features:
 * - Caches connections by key (a simple per-request singleton) to avoid multiple
 *   connections in the same process.
 * - Normalizes environment strings (removes BOM / invisible chars) to prevent
 *   common issues when copying credentials.
 */
final class Db
{
    /** @var array<string, PDO> */
    private static array $connections = [];

    /**
     * Normaliza valores de configuración provenientes de variables de entorno:
     * - Elimina BOM y caracteres invisibles.
     * - Aplica trim.
     *
     * ---
     *
     * Normalizes configuration values coming from environment variables:
     * - Removes BOM and invisible characters.
     * - Applies trim.
     */
    private static function clean(?string $value, string $default = ''): string
    {
        $value = $value ?? $default;
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value) ?? $value;
        return trim($value);
    }

    /**
     * Alias histórico: retorna la conexión principal del sistema (TRZ).
     *
     * ---
     *
     * Historical alias: returns the main system connection (TRZ).
     */
    public static function pdo(): PDO
    {
        return self::trzPdo();
    }

    /**
     * Conexión a la base local del sistema (TRZ).
     *
     * Se permite “fallback” a variables DB_* generales para simplificar despliegues.
     *
     * ---
     *
     * Connection to the local system database (TRZ).
     *
     * Allows fallback to generic DB_* variables to simplify deployments.
     */
    public static function trzPdo(): PDO
    {
        return self::connect('trz', [
            'host' => self::clean(Env::get('TRZ_DB_HOST', Env::get('DB_HOST', '127.0.0.1')), '127.0.0.1'),
            'port' => self::clean(Env::get('TRZ_DB_PORT', Env::get('DB_PORT', '3306')), '3306'),
            'name' => self::clean(Env::get('TRZ_DB_NAME', Env::get('DB_NAME', 'unibag_trazabilidad')), 'unibag_trazabilidad'),
            'user' => self::clean(Env::get('TRZ_DB_USER', Env::get('DB_USER', 'root')), 'root'),
            'pass' => self::clean(Env::get('TRZ_DB_PASS', Env::get('DB_PASS', '')), ''),
            'charset' => self::clean(Env::get('TRZ_DB_CHARSET', Env::get('DB_CHARSET', 'utf8mb4')), 'utf8mb4'),
        ]);
    }

    /**
     * Conexión a la base de datos del ERP.
     *
     * Nota: esta base contiene las tablas de producción (prod_*), operadores (workers),
     * eventos (prod_worker_ot_events), etc.
     *
     * ---
     *
     * Connection to the ERP database.
     *
     * Note: this DB contains production tables (prod_*), operators (workers),
     * events (prod_worker_ot_events), etc.
     */
    public static function erpPdo(): PDO
    {
        return self::connect('erp', [
            'host' => self::clean(Env::get('ERP_DB_HOST', '127.0.0.1'), '127.0.0.1'),
            'port' => self::clean(Env::get('ERP_DB_PORT', '3306'), '3306'),
            'name' => self::clean(Env::get('ERP_DB_NAME', 'unibag_unibag'), 'unibag_unibag'),
            'user' => self::clean(Env::get('ERP_DB_USER', 'root'), 'root'),
            'pass' => self::clean(Env::get('ERP_DB_PASS', ''), ''),
            'charset' => self::clean(Env::get('ERP_DB_CHARSET', 'utf8mb4'), 'utf8mb4'),
        ]);
    }

    /**
     * @param array{host:?string,port:?string,name:?string,user:?string,pass:?string,charset:?string} $config
     */
    private static function connect(string $key, array $config): PDO
    {
        // Cache de conexiones: si ya existe, verificar que siga viva antes de reusarla.
        // ---
        // Connection cache: if it exists, verify it is still alive before reusing.
        if (isset(self::$connections[$key])) {
            try {
                self::$connections[$key]->query('SELECT 1');
                return self::$connections[$key];
            } catch (Throwable) {
                unset(self::$connections[$key]);
            }
        }

        // DSN base para MySQL/MariaDB (sin charset). Se arma aparte para soportar fallback.
        // ---
        // Base DSN for MySQL/MariaDB (without charset). Built separately to support fallback.
        $baseDsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? '3306',
            $config['name'] ?? ''
        );
        $charset = trim((string)($config['charset'] ?? ''));
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_TIMEOUT => 15,
        ];

        // DSN final: cuando el charset es reconocido por el driver, se agrega al DSN.
        // ---
        // Final DSN: when the charset is accepted by the driver, it is appended to the DSN.
        $dsn = $baseDsn . ($charset !== '' ? ';charset=' . $charset : '');

        $maxAttempts = 3;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $pdo = new PDO($dsn, $config['user'] ?? 'root', $config['pass'] ?? '', $options);
                self::$connections[$key] = $pdo;
                return self::$connections[$key];
            } catch (PDOException $e) {
                $lastException = $e;
                if ($charset !== '' && str_contains($e->getMessage(), 'Unknown character set')) {
                    try {
                        $pdo = new PDO($baseDsn, $config['user'] ?? 'root', $config['pass'] ?? '', $options);
                        try {
                            $pdo->exec('SET NAMES ' . $pdo->quote($charset));
                        } catch (PDOException) {
                        }
                        self::$connections[$key] = $pdo;
                        return self::$connections[$key];
                    } catch (PDOException $fallbackErr) {
                        $lastException = $fallbackErr;
                    }
                }
                // Si el servidor remoto tuvo una intermitencia de red, esperar y reintentar
                if ($attempt < $maxAttempts && (str_contains($e->getMessage(), 'gone away') || str_contains($e->getMessage(), 'Lost connection'))) {
                    usleep(500000); // 500ms
                    continue;
                }
                break;
            }
        }

        throw $lastException ?? new PDOException("No se pudo conectar a la base de datos {$key}");
    }
}
