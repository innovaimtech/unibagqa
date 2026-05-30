<?php

declare(strict_types=1);

final class Db
{
    /** @var array<string, PDO> */
    private static array $connections = [];

    private static function clean(?string $value, string $default = ''): string
    {
        $value = $value ?? $default;
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value) ?? $value;
        return trim($value);
    }

    public static function pdo(): PDO
    {
        return self::trzPdo();
    }

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

    public static function erpPdo(): PDO
    {
        return self::connect('erp', [
            'host' => self::clean(Env::get('ERP_DB_HOST', '127.0.0.1'), '127.0.0.1'),
            'port' => self::clean(Env::get('ERP_DB_PORT', '3306'), '3306'),
            'name' => self::clean(Env::get('ERP_DB_NAME', 'unibagqa'), 'unibagqa'),
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
        if (isset(self::$connections[$key])) {
            return self::$connections[$key];
        }

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
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $dsn = $baseDsn . ($charset !== '' ? ';charset=' . $charset : '');
        try {
            $pdo = new PDO($dsn, $config['user'] ?? 'root', $config['pass'] ?? '', $options);
        } catch (PDOException $e) {
            if ($charset !== '' && str_contains($e->getMessage(), 'Unknown character set')) {
                $pdo = new PDO($baseDsn, $config['user'] ?? 'root', $config['pass'] ?? '', $options);
                try {
                    $pdo->exec('SET NAMES ' . $pdo->quote($charset));
                } catch (PDOException) {
                    // Some managed runtimes reject charset negotiation; the connection still works without it.
                }
            } else {
                throw $e;
            }
        }

        self::$connections[$key] = $pdo;
        return self::$connections[$key];
    }
}
