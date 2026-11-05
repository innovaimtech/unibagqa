<?php

declare(strict_types=1);

/**
 * Perfilador liviano de solicitudes (diagnóstico de lentitud).
 *
 * Mide el tiempo total de cada solicitud, cuántas consultas SQL hizo y cuánto tardaron, y deja una línea
 * JSON en storage/logs/slow-requests.log cuando la solicitud supera el umbral (SLOW_REQUEST_MS, 1200 ms por
 * defecto) o cuando se pide con ?profile=1. Con la línea se ve de inmediato si el tiempo se fue en la base de
 * datos (db_ms), en PHP (php_ms) y si OPcache está activo.
 */
final class RequestProfiler
{
    private static float $startedAt = 0.0;
    /** @var array<int,array{ms:float,sql:string,conn:string}> */
    private static array $queries = [];
    private static bool $registered = false;
    private static bool $forced = false;

    public static function start(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        self::$startedAt = (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        self::$forced = isset($_GET['profile']) && $_GET['profile'] === '1';
        register_shutdown_function([self::class, 'finish']);
    }

    public static function record(string $sql, float $ms, string $conn = ''): void
    {
        if (count(self::$queries) < 5000) {
            self::$queries[] = ['ms' => $ms, 'sql' => $sql, 'conn' => $conn];
        }
    }

    public static function finish(): void
    {
        if (self::$startedAt <= 0.0) {
            return;
        }
        $totalMs = (microtime(true) - self::$startedAt) * 1000.0;
        $threshold = (float)(Env::get('SLOW_REQUEST_MS', '1200') ?? 1200);
        if (!self::$forced && $totalMs < $threshold) {
            return;
        }
        $dbMs = 0.0;
        foreach (self::$queries as $q) {
            $dbMs += $q['ms'];
        }
        // Consultas repetidas: delatan bucles N+1 aunque cada una por separado sea rápida.
        $repeated = [];
        foreach (self::$queries as $q) {
            $key = mb_substr(preg_replace('/\s+/', ' ', $q['sql']) ?? '', 0, 120);
            $repeated[$key] = ($repeated[$key] ?? 0) + 1;
        }
        arsort($repeated);
        $repeated = array_filter(array_slice($repeated, 0, 5, true), static fn(int $n): bool => $n > 1);
        usort(self::$queries, static fn(array $a, array $b): int => $b['ms'] <=> $a['ms']);
        $opcache = ['enabled' => false];
        if (function_exists('opcache_get_status')) {
            $st = @opcache_get_status(false);
            if (is_array($st)) {
                $opcache = [
                    'enabled' => (bool)($st['opcache_enabled'] ?? false),
                    'hit_rate' => isset($st['opcache_statistics']['opcache_hit_rate']) ? round((float)$st['opcache_statistics']['opcache_hit_rate'], 1) : null,
                    'used_mb' => isset($st['memory_usage']['used_memory']) ? round($st['memory_usage']['used_memory'] / 1048576, 1) : null,
                    'free_mb' => isset($st['memory_usage']['free_memory']) ? round($st['memory_usage']['free_memory'] / 1048576, 1) : null,
                    'cached_scripts' => $st['opcache_statistics']['num_cached_scripts'] ?? null,
                ];
            }
        }
        $line = [
            'ts' => date('c'),
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'uri' => mb_substr((string)($_SERVER['REQUEST_URI'] ?? ''), 0, 200),
            'total_ms' => (int)round($totalMs),
            'db_ms' => (int)round($dbMs),
            'php_ms' => (int)round($totalMs - $dbMs),
            'queries' => count(self::$queries),
            'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            'files_loaded' => count(get_included_files()),
            'opcache' => $opcache,
            'top_queries' => array_map(static fn(array $q): array => [
                'ms' => (int)round($q['ms']),
                'conn' => $q['conn'],
                'sql' => mb_substr(preg_replace('/\s+/', ' ', $q['sql']) ?? '', 0, 220),
            ], array_slice(self::$queries, 0, 5)),
            'repeated' => $repeated,
        ];
        $dir = dirname(__DIR__) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/slow-requests.log', json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }
}

/** Sentencia PDO que mide cuánto tarda cada execute(). */
class ProfiledPDOStatement extends PDOStatement
{
    protected function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        $t = microtime(true);
        try {
            return parent::execute($params);
        } finally {
            RequestProfiler::record($this->queryString, (microtime(true) - $t) * 1000.0);
        }
    }
}

/** PDO que mide query() y exec() (las sentencias preparadas se miden en ProfiledPDOStatement). */
class ProfiledPDO extends PDO
{
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $t = microtime(true);
        try {
            return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
        } finally {
            RequestProfiler::record($query, (microtime(true) - $t) * 1000.0);
        }
    }

    public function exec(string $statement): int|false
    {
        $t = microtime(true);
        try {
            return parent::exec($statement);
        } finally {
            RequestProfiler::record($statement, (microtime(true) - $t) * 1000.0);
        }
    }
}
