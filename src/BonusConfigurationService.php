<?php
declare(strict_types=1);

/**
 * =============================================================================
 * Servicio de Configuración y Auditoría de Bonificaciones
 * (src/BonusConfigurationService.php)
 * =============================================================================
 *
 * Responsabilidad:
 * Administra las políticas, tramos, metas y evaluaciones para el cálculo de bonos:
 * 1. Bonos de Corte y Sellado (CyS): Reglas de medidas y categorías de producto
 *    (Bolsas, Bolsas XXL, Sacos, Basurines/Troqueles).
 * 2. Bonos de Ayudantes: Evaluación multidimensional (proactividad, eficiencia,
 *    multitarea) con notas de 0 a 10 y factor de liquidación por tramos.
 * 3. Bonos de Flexografía y Serigrafía: Metas de producción mensual y topes en CLP.
 * 4. Trazabilidad y Auditoría: Registro inmutable de cada cambio en la configuración
 *    (quién modificó, fecha, valor previo y valor nuevo en JSON).
 */
final class BonusConfigurationService
{
    private bool $schemaReady = false;
    private array $policyCache = [];
    private array $rulesCache = [];

    /** Categorías oficiales de productos para cálculo de bonos CyS */
    public const CATEGORIES = [
        'bolsas'=>'Bolsas', 'bolsas_xxl'=>'Bolsas XXL',
        'sacos'=>'Sacos', 'basurines_troqueles'=>'Basurines y troqueles',
    ];

    /**
     * @param PDO $pdo Conexión a la base de datos local TRZ (unibag_trazabilidad).
     */
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Valida y normaliza una calificación de evaluación (escala de 0.00 a 10.00).
     */
    public static function helperScore(mixed $value): float
    {
        if (is_string($value)) $value = str_replace(',','.',trim($value));
        if (!is_numeric($value) || !is_finite((float)$value) || (float)$value<0 || (float)$value>10
            || abs((float)$value-round((float)$value,2))>0.000001) {
            throw new RuntimeException('Las notas deben estar entre 0 y 10 y tener hasta dos decimales.');
        }
        return round((float)$value,2);
    }

    /** Legacy sheet uses integer bands: 7.25 -> 70%, 8.75 -> 80%.
     * La planilla original usa tramos enteros: 7,25 -> 70%, 8,75 -> 80%. */
    public static function helperScoreFactor(float $score): float
    {
        return floor($score)/10;
    }

    private function ensureSchema(): void
    {
        if ($this->schemaReady) return;
        $sqlite = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS bonus_process_configs (
            bonus_code VARCHAR(40) NOT NULL, month_key CHAR(7) NOT NULL, policy_json TEXT NOT NULL,
            PRIMARY KEY(bonus_code,month_key))$suffix");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS bonus_cys_measure_rules (
            id $id, month_key CHAR(7) NOT NULL, source_measure VARCHAR(80) NOT NULL,
            bonus_measure VARCHAR(80) NOT NULL, category_code VARCHAR(60) NOT NULL,
            active INTEGER NOT NULL DEFAULT 1, UNIQUE(month_key,source_measure))$suffix");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS bonus_configuration_history (
            id $id, month_key CHAR(7) NOT NULL, entity_key VARCHAR(120) NOT NULL,
            before_json TEXT NULL, after_json TEXT NULL, actor VARCHAR(120) NOT NULL,
            created_at VARCHAR(19) NOT NULL)$suffix");
        $this->schemaReady = true;
    }

    private static function validatePeriod(string $month): void
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $matches)
            || !checkdate((int)$matches[2], 1, (int)$matches[1])) {
            throw new RuntimeException('Mes de bonos no valido.');
        }
    }

    public static function monthForDate(string $date, string $fallback): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m)
            || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return $fallback;
        $day = new DateTimeImmutable(substr($date, 0, 10));
        // Use the first day to avoid PHP's month overflow on dates such as January 31.
        // Usar el primer dia evita saltar meses al calcular el periodo 26-25.
        return (int)$m[3] >= 26 ? $day->modify('first day of next month')->format('Y-m') : $day->format('Y-m');
    }

    public function getPolicy(string $code, string $month): array
    {
        self::validatePeriod($month);
        if (!in_array($code, ['bonoseri','bonocys'], true)) throw new RuntimeException('Bono no valido.');
        $this->ensureSchema();
        $key = $code . ':' . $month;
        if (!isset($this->policyCache[$key])) {
            $stmt = $this->pdo->prepare('SELECT policy_json FROM bonus_process_configs WHERE bonus_code=? AND month_key=?');
            $stmt->execute([$code,$month]);
            $json = $stmt->fetchColumn();
            $this->policyCache[$key] = $json ? json_decode((string)$json, true, 512, JSON_THROW_ON_ERROR)
                : ['mode'=>'unit','factors'=>[]];
        }
        return $this->policyCache[$key];
    }

    public function factor(string $code, string $operator, string $date, string $fallbackMonth): float
    {
        $policy = $this->getPolicy($code, self::monthForDate($date, $fallbackMonth));
        return $policy['mode'] === 'percentage' ? (float)($policy['factors'][trim($operator)] ?? 1.0) : 1.0;
    }

    private function audit(string $month, string $key, mixed $before, mixed $after, string $actor): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO bonus_configuration_history
            (month_key,entity_key,before_json,after_json,actor,created_at) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$month,$key,json_encode($before,JSON_THROW_ON_ERROR),
            json_encode($after,JSON_THROW_ON_ERROR),mb_substr($actor,0,120),date('Y-m-d H:i:s')]);
    }

    public function savePolicy(string $code, string $month, string $mode, array $percentages, string $actor): void
    {
        $this->getPolicy($code,$month);
        if (!in_array($mode,['unit','percentage'],true)) throw new RuntimeException('Modalidad de bono no valida.');
        $factors = [];
        foreach ($percentages as $name=>$value) {
            $name = trim((string)$name);
            if ($name === '' || mb_strlen($name)>120 || !is_numeric($value)
                || !in_array((float)$value,[80.0,90.0,100.0],true)) {
                throw new RuntimeException('Selecciona 80%, 90% o 100% para cada operador.');
            }
            if ((float)$value!==100.0) $factors[$name] = (float)$value/100;
        }
        $after = ['mode'=>$mode,'factors'=>$factors];
        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : '';
            $stmt = $this->pdo->prepare('SELECT policy_json FROM bonus_process_configs WHERE bonus_code=? AND month_key=?'.$lock);
            $stmt->execute([$code,$month]);
            $before = $stmt->fetchColumn();
            $json = json_encode($after,JSON_THROW_ON_ERROR);
            if ($before!==false) {
                $stmt = $this->pdo->prepare('UPDATE bonus_process_configs SET policy_json=? WHERE bonus_code=? AND month_key=?');
                $stmt->execute([$json,$code,$month]);
            } else {
                $stmt = $this->pdo->prepare('INSERT INTO bonus_process_configs(policy_json,bonus_code,month_key) VALUES(?,?,?)');
                $stmt->execute([$json,$code,$month]);
            }
            $this->audit($month,$code,$before!==false ? json_decode($before,true,512,JSON_THROW_ON_ERROR) : null,$after,$actor);
            $this->pdo->commit();
            unset($this->policyCache[$code.':'.$month]);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Resolve effective settings, without overwriting earlier months.
     * Resolver configuraciones vigentes sin sobrescribir los meses anteriores. */
    public function getHelperConfiguration(string $month): ?array
    {
        self::validatePeriod($month);
        $this->ensureSchema();
        $stmt = $this->pdo->prepare('SELECT month_key,policy_json FROM bonus_process_configs
            WHERE bonus_code=? AND month_key<=? ORDER BY month_key ASC');
        $stmt->execute(['bonoayudante', $month]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return null;
        $effective = [];
        foreach ($rows as $row) {
            foreach (json_decode($row['policy_json'], true, 512, JSON_THROW_ON_ERROR) as $name=>$settings) {
                $effective[$name] = array_replace($effective[$name] ?? [], $settings, ['effective_from'=>$row['month_key']]);
            }
        }
        return $effective;
    }

    private static function normalizeHelperSettings(array $settings): array
    {
        $after = ['active'=>!empty($settings['active'])];
        foreach (['matrix_proactividad_clp','matrix_eficiencia_clp','matrix_multitarea_clp','fixed_clp'] as $field) {
            $value = $settings[$field] ?? null;
            if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0
                || (float)$value > 9999999999.99) throw new RuntimeException('Los bonos maximos deben ser montos positivos o cero.');
            $after[$field] = round((float)$value, 2);
        }
        $after['instructions'] = trim((string)($settings['instructions'] ?? ''));
        if (mb_strlen($after['instructions']) > 2000) throw new RuntimeException('Las indicaciones permiten hasta 2000 caracteres.');
        return $after;
    }

    public function saveHelperConfiguration(string $month, string $operator, array $settings, string $actor): void
    {
        $operator = trim($operator);
        if ($operator === '' || mb_strlen($operator) > 120) throw new RuntimeException('Operador no valido.');
        $after = self::normalizeHelperSettings($settings);
        $current = $this->getHelperConfiguration($month)[$operator] ?? [];
        $changes = [];
        foreach ($after as $field=>$value) {
            if (!array_key_exists($field,$current) || $current[$field] != $value) $changes[$field] = $value;
        }
        if ($changes) $this->writeHelperConfigurations($month, [$operator=>$changes], $actor);
    }

    public function saveHelperRecipients(string $month, array $selected, array $availableSettings, string $actor): void
    {
        self::validatePeriod($month);
        $selected = array_fill_keys(array_unique($selected), true);
        foreach ($selected as $name=>$ignored) {
            if (!isset($availableSettings[$name])) throw new RuntimeException('Selecciona operadores del listado disponible.');
        }
        $effective = $this->getHelperConfiguration($month) ?? [];
        $changes = [];
        foreach ($availableSettings as $name=>$settings) {
            $active = isset($selected[$name]);
            if ($active === !empty($settings['active']) && (isset($effective[$name]) || !$active)) continue;
            $settings['active'] = $active;
            $changes[$name] = isset($effective[$name]) ? ['active'=>$active] : self::normalizeHelperSettings($settings);
        }
        if ($changes) $this->writeHelperConfigurations($month, $changes, $actor);
    }

    private function writeHelperConfigurations(string $month, array $changes, string $actor): void
    {
        self::validatePeriod($month);
        $this->ensureSchema();
        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $stmt = $this->pdo->prepare('SELECT policy_json FROM bonus_process_configs WHERE bonus_code=? AND month_key=?'.$lock);
            $stmt->execute(['bonoayudante',$month]);
            $json = $stmt->fetchColumn();
            $config = $json === false ? [] : json_decode((string)$json,true,512,JSON_THROW_ON_ERROR);
            $effective = $this->getHelperConfiguration($month) ?? [];
            foreach ($changes as $operator=>$after) {
                $before = $effective[$operator] ?? null;
                // Store only changed fields so later recipient edits do not freeze amounts.
                // Guardar solo cambios evita que una seleccion futura congele los montos.
                $config[$operator] = array_replace($config[$operator] ?? [], $after);
                $this->audit($month,'bonoayudante',$before ? ['operator'=>$operator]+$before : null,
                    ['operator'=>$operator]+array_replace($before ?? [],$after),$actor);
            }
            $payload = json_encode($config,JSON_THROW_ON_ERROR);
            if ($json === false) {
                $stmt = $this->pdo->prepare('INSERT INTO bonus_process_configs(policy_json,bonus_code,month_key) VALUES(?,?,?)');
            } else {
                $stmt = $this->pdo->prepare('UPDATE bonus_process_configs SET policy_json=? WHERE bonus_code=? AND month_key=?');
            }
            $stmt->execute([$payload,'bonoayudante',$month]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function recordHelperEvaluation(string $month, ?array $before, array $after, string $actor): void
    {
        self::validatePeriod($month);
        $this->ensureSchema();
        if ($before === $after) return;
        $this->audit($month, 'bonoayudante-evaluation', $before, $after, $actor);
    }

    public static function normalizeMeasure(string $measure): string
    {
        $measure = strtoupper(trim(str_replace(',', '.', $measure)));
        $measure = preg_replace('/\s+/', '', str_replace(['×','*'], 'X', $measure)) ?? '';
        // ERP labels can use 08 while production reports use 8 for the same dimension.
        // Las etiquetas ERP pueden usar 08 y las planillas 8 para la misma medida.
        if (!preg_match('/^\d+(?:\.\d+)?(?:X\d+(?:\.\d+)?){1,2}$/', $measure)) return $measure;
        return preg_replace_callback('/\d+(?:\.\d+)?/', static function(array $m): string {
            $parts = explode('.', $m[0], 2);
            $integer = ltrim($parts[0], '0');
            $fraction = rtrim($parts[1] ?? '', '0');
            return ($integer === '' ? '0' : $integer) . ($fraction === '' ? '' : '.' . $fraction);
        }, $measure) ?? $measure;
    }

    public static function prepareMeasureCatalog(array $measures): array
    {
        $catalog = [];
        foreach ($measures as $measure) {
            $label = trim((string)($measure['med_name'] ?? ''));
            if (!preg_match('/\d+(?:[.,]\d+)?\s*[xX×*]\s*\d+(?:[.,]\d+)?(?:\s*[xX×*]\s*\d+(?:[.,]\d+)?)?/', $label, $m)) continue;
            $source = self::normalizeMeasure($m[0]);
            $upper = mb_strtoupper($label);
            $category = 'bolsas';
            if (str_contains($upper, 'SAC')) $category = 'sacos';
            elseif (str_contains($upper, 'BASUR') || str_contains($upper, 'TROQ')) $category = 'basurines_troqueles';
            elseif (in_array($source, ['44X39X23','48X40X20','44X40X23'], true)) $category = 'bolsas_xxl';
            $key = $label . ':' . $source;
            $catalog[$key] = ['label'=>$label,'source_measure'=>$source,'category_code'=>$category];
        }
        return array_values($catalog);
    }

    public function listMeasureRules(string $month): array
    {
        self::validatePeriod($month);
        $this->ensureSchema();
        if (!isset($this->rulesCache[$month])) {
            $stmt = $this->pdo->prepare('SELECT * FROM bonus_cys_measure_rules WHERE month_key=? AND active=1 ORDER BY source_measure');
            $stmt->execute([$month]);
            $this->rulesCache[$month] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->rulesCache[$month];
    }

    public function applyMeasureRule(array $row, string $fallbackMonth): array
    {
        $month = self::monthForDate((string)($row['event_date']??''),$fallbackMonth);
        $source = self::normalizeMeasure((string)($row['measure_cm']??''));
        foreach ($this->listMeasureRules($month) as $rule) {
            if ($source === self::normalizeMeasure($rule['source_measure'])) {
                $row['bonus_original_measure'] = (string)($row['measure_cm']??'');
                $row['measure_cm'] = $rule['bonus_measure'];
                $row['bonus_category'] = $rule['category_code'];
                break;
            }
        }
        return $row;
    }

    public function saveMeasureRule(string $month, int $id, string $source, string $target, string $category, string $actor): void
    {
        self::validatePeriod($month);
        $source = self::normalizeMeasure($source);
        $target = self::normalizeMeasure($target);
        if ($source==='' || $target==='' || mb_strlen($source)>80 || mb_strlen($target)>80
            || !isset(self::CATEGORIES[$category])) throw new RuntimeException('Medida o categoria de bonos no valida.');
        $this->ensureSchema();
        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : '';
            $stmt = $this->pdo->prepare($id>0
                ? 'SELECT * FROM bonus_cys_measure_rules WHERE month_key=? AND id=?'.$lock
                : 'SELECT * FROM bonus_cys_measure_rules WHERE month_key=? AND source_measure=?'.$lock);
            $stmt->execute([$month,$id>0 ? $id : $source]);
            $before = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$before && $id <= 0) {
                $stmt = $this->pdo->prepare('SELECT * FROM bonus_cys_measure_rules WHERE month_key=? ORDER BY id DESC'.$lock);
                $stmt->execute([$month]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
                    if (self::normalizeMeasure($candidate['source_measure']) === $source) {
                        $before = $candidate;
                        break;
                    }
                }
            }
            if ($id>0 && !$before) throw new RuntimeException('La medida no corresponde a este mes.');
            if ($before) {
                $stmt = $this->pdo->prepare('UPDATE bonus_cys_measure_rules SET source_measure=?,bonus_measure=?,category_code=?,active=1 WHERE id=? AND month_key=?');
                $stmt->execute([$source,$target,$category,$before['id'],$month]);
                $id = (int)$before['id'];
            } else {
                $stmt = $this->pdo->prepare('INSERT INTO bonus_cys_measure_rules(month_key,source_measure,bonus_measure,category_code) VALUES(?,?,?,?)');
                $stmt->execute([$month,$source,$target,$category]);
                $id = (int)$this->pdo->lastInsertId();
            }
            $this->audit($month,'cys-measure:'.$id,$before?:null,
                ['source_measure'=>$source,'bonus_measure'=>$target,'category_code'=>$category,'active'=>1],$actor);
            $this->pdo->commit();
            unset($this->rulesCache[$month]);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function disableMeasureRule(string $month, int $id, string $actor): void
    {
        self::validatePeriod($month);
        $this->ensureSchema();
        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : '';
            $stmt = $this->pdo->prepare('SELECT * FROM bonus_cys_measure_rules WHERE month_key=? AND id=? AND active=1'.$lock);
            $stmt->execute([$month,$id]);
            $before = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$before) throw new RuntimeException('Medida de bonos no encontrada.');
            $stmt = $this->pdo->prepare('UPDATE bonus_cys_measure_rules SET active=0 WHERE month_key=? AND id=?');
            $stmt->execute([$month,$id]);
            $this->audit($month,'cys-measure:'.$id,$before,array_replace($before,['active'=>0]),$actor);
            $this->pdo->commit();
            unset($this->rulesCache[$month]);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Effective maintenance policy, independent of the ERP payroll tables.
     * Politica vigente de mantencion, independiente de las tablas de pago ERP. */
    public function getMaintenanceConfiguration(string $month): array
    {
        self::validatePeriod($month);
        $this->ensureSchema();
        $stmt = $this->pdo->prepare('SELECT month_key,policy_json FROM bonus_process_configs
            WHERE bonus_code=? AND month_key<=? ORDER BY month_key DESC LIMIT 1');
        $stmt->execute(['bonomantencion',$month]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return [];
        $policy = json_decode($row['policy_json'],true,512,JSON_THROW_ON_ERROR);
        $policy['effective_from'] = $row['month_key'];
        return $policy;
    }

    public function saveMaintenanceConfiguration(string $month, array $policy, string $actor): void
    {
        self::validatePeriod($month);
        $this->ensureSchema();
        unset($policy['effective_from']);
        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : '';
            $stmt = $this->pdo->prepare('SELECT policy_json FROM bonus_process_configs
                WHERE bonus_code=? AND month_key<=? ORDER BY month_key DESC LIMIT 1'.$lock);
            $stmt->execute(['bonomantencion',$month]);
            $previous = $stmt->fetchColumn();
            $before = $previous!==false ? json_decode($previous,true,512,JSON_THROW_ON_ERROR) : [];
            $stmt = $this->pdo->prepare('SELECT policy_json FROM bonus_process_configs WHERE bonus_code=? AND month_key=?'.$lock);
            $stmt->execute(['bonomantencion',$month]);
            $exists = $stmt->fetchColumn() !== false;
            $json = json_encode($policy,JSON_THROW_ON_ERROR);
            $sql = $exists
                ? 'UPDATE bonus_process_configs SET policy_json=? WHERE bonus_code=? AND month_key=?'
                : 'INSERT INTO bonus_process_configs(policy_json,bonus_code,month_key) VALUES(?,?,?)';
            $this->pdo->prepare($sql)->execute([$json,'bonomantencion',$month]);
            if (json_encode($before,JSON_THROW_ON_ERROR) !== $json) {
                $this->audit($month,'bonomantencion',$before?:null,$policy,$actor);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function listHistory(string $month, string $code): array
    {
        self::validatePeriod($month);
        $this->ensureSchema();
        $stmt = $this->pdo->prepare('SELECT * FROM bonus_configuration_history
            WHERE month_key=? AND (entity_key=? OR entity_key LIKE ?) ORDER BY id DESC LIMIT 50');
        $stmt->execute([$month,$code,$code==='bonocys' ? 'cys-measure:%' : ($code==='bonoayudante' ? 'bonoayudante-%' : $code)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
