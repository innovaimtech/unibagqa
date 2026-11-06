<?php

declare(strict_types=1);

require_once __DIR__ . '/BonusConfigurationService.php';

/**
 * Bonificación de bodega.
 *
 * Cada operador tiene un monto por KPI, vigente desde el mes en que se guarda hasta el próximo cambio.
 * Pago de cada KPI = monto × % de cumplimiento del mes. Las notas de Proactividad, Eficiencia y Multitarea
 * quedan registradas pero no cambian el pago.
 *
 * Tablas TRZ: warehouse_bonus_settings, warehouse_bonus_evaluations y warehouse_bonus_history.
 * Del ERP solo se leen nombres de trabajadores y usuarios activos.
 */
final class WarehouseBonusService
{
    private const SCHEMA_VERSION = 'wh-bonus-v1';
    private const MAX_AMOUNT = 9999999999.0;
    private const BODEGA = 'Jefe de bodega';

    /** Orden y textos de la planilla de bodega y de las fichas de KPI. */
    public const KPIS = [
        'recepcion_embalaje' => ['label' => 'Recepción embalaje', 'kpi' => 'Recepción PT embalaje ERP',
            'objective' => 'Recepción de PT de embalaje y carga en ERP', 'indicator' => 'Stock actualizado',
            'periodicity' => 'Diaria', 'failure' => 'Cuando no hay registros', 'supervisor' => self::BODEGA],
        'descarga_contenedor' => ['label' => 'Descarga contenedor', 'kpi' => 'Descarga de contenedores',
            'objective' => 'Cumplir con tiempos óptimos de descarga (4 horas máximo).', 'indicator' => 'Horario de arribo y salida de contenedor.',
            'periodicity' => 'Mensual', 'failure' => 'Excede del tiempo establecido, más de 4 horas.', 'supervisor' => self::BODEGA],
        'despachos' => ['label' => 'Despachos', 'kpi' => 'Cumplimiento de despacho',
            'objective' => 'Cumplimiento en plazos, códigos y cantidades correctas de productos. Tiempos de carga Rampla: 30 minutos. Camión 3/4 y otros 20 minutos.',
            'indicator' => 'Revisión de tiempos de carga transporte. Exactitud despacho', 'periodicity' => 'Mensual',
            'failure' => 'Registros de reclamos y devoluciones de productos. Excede tiempos de carga transporte', 'supervisor' => self::BODEGA],
        'inventario' => ['label' => 'Inventario', 'kpi' => 'Toma inventario cíclico materias primas y productos terminados',
            'objective' => 'Realización de inventario cíclico según plan entregado por jefatura', 'indicator' => 'Informe de stock provenientes del área de bodega (ERP)',
            'periodicity' => 'Mensual', 'failure' => 'Cuando existen diferencias de sus registros v/s reporte del área de producción.', 'supervisor' => self::BODEGA],
        'residuos' => ['label' => 'Control y pesaje residuos', 'kpi' => 'Control y pesaje de residuos',
            'objective' => '', 'indicator' => '', 'periodicity' => '', 'failure' => '', 'supervisor' => ''],
        'mantencion_limpieza' => ['label' => 'Mantención y limpieza', 'kpi' => 'Limpieza - Orden del entorno',
            'objective' => 'Mantención del orden y limpieza del frontis de la empresa (jardín, estacionamiento, patio frontal)', 'indicator' => 'Checklist',
            'periodicity' => 'Semanal', 'failure' => 'El frontis se encuentra libre de basura y en estado ordenado.', 'supervisor' => self::BODEGA],
        'apoyo_areas' => ['label' => 'Tarea apoyo otras áreas', 'kpi' => 'Tarea de apoyo a las distintas áreas',
            'objective' => 'Realizar actividades de orden, limpieza y logística que puedan surgir desde las distintas áreas.', 'indicator' => 'Feedback de jefatura de área que requiere el trabajo.',
            'periodicity' => 'Mensual', 'failure' => 'Se concreta la tarea específica requerida por su jefatura para el apoyo de otras áreas.', 'supervisor' => self::BODEGA],
        'modulo_despachos' => ['label' => 'Módulo despachos ERP', 'kpi' => 'Completar archivo despachos',
            'objective' => 'Completar correctamente archivo "Despacho en ERP"', 'indicator' => 'Planilla control "Despachos ERP"',
            'periodicity' => 'Diaria', 'failure' => 'Cuando no se va completando este archivo de forma diaria, semanal o mensual, según sistema', 'supervisor' => self::BODEGA],
        'recepcion_proveedores' => ['label' => 'Recepción proveedores', 'kpi' => 'Recepción proveedores',
            'objective' => 'Recepción, revisión, cuadratura de unidades y control de documentos tributarios', 'indicator' => 'Feedback de jefatura de área que requiere el trabajo.',
            'periodicity' => 'Diaria', 'failure' => 'Cuando no se recepciona a proveedores', 'supervisor' => self::BODEGA],
    ];

    private bool $ready = false;

    public function __construct(private PDO $trz, private ?PDO $erp = null)
    {
    }

    public static function defaultMonth(): string
    {
        return BonusConfigurationService::monthForDate(date('Y-m-d'), date('Y-m'));
    }

    public static function validMonth(string $month): bool
    {
        return preg_match('/^(\d{4})-(\d{2})$/D', $month, $m) === 1 && checkdate((int)$m[2], 1, (int)$m[1]);
    }

    private static function requireMonth(string $month): void
    {
        if (!self::validMonth($month)) {
            throw new RuntimeException('Selecciona un mes válido.');
        }
    }

    /** Pago de un KPI: monto × porcentaje, en pesos enteros. */
    public static function kpiPay(float $amount, float $percent): float
    {
        return round($amount * $percent / 100);
    }

    /** Clave para comparar nombres sin importar mayúsculas, tildes ni espacios. */
    public static function nameKey(string $name): string
    {
        $name = mb_strtolower(trim((string)preg_replace('/\s+/u', ' ', $name)));
        return strtr($name, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    private function ensureSchema(): void
    {
        if ($this->ready) {
            return;
        }
        $sqlite = $this->trz->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
        if (!$sqlite && SchemaGate::done('warehouse-bonus', self::SCHEMA_VERSION)) {
            $this->ready = true;
            return;
        }
        $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $suffix = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $this->trz->exec("CREATE TABLE IF NOT EXISTS warehouse_bonus_settings (
            month_key CHAR(7) NOT NULL, operator_name VARCHAR(120) NOT NULL, active INT NOT NULL DEFAULT 1,
            amounts_json TEXT NOT NULL, updated_by VARCHAR(120) NOT NULL, updated_at VARCHAR(19) NOT NULL,
            PRIMARY KEY (month_key, operator_name))$suffix");
        $this->trz->exec("CREATE TABLE IF NOT EXISTS warehouse_bonus_evaluations (
            month_key CHAR(7) NOT NULL, operator_name VARCHAR(120) NOT NULL,
            proactividad_score DECIMAL(5,2) NOT NULL, eficiencia_score DECIMAL(5,2) NOT NULL, multitarea_score DECIMAL(5,2) NOT NULL,
            kpis_json TEXT NOT NULL, additional_clp DECIMAL(12,2) NOT NULL DEFAULT 0, observations VARCHAR(255) NOT NULL DEFAULT '',
            updated_by VARCHAR(120) NOT NULL, updated_at VARCHAR(19) NOT NULL,
            PRIMARY KEY (month_key, operator_name))$suffix");
        $this->trz->exec("CREATE TABLE IF NOT EXISTS warehouse_bonus_history (
            id $id, month_key CHAR(7) NOT NULL, operator_name VARCHAR(120) NOT NULL, action VARCHAR(20) NOT NULL,
            before_json TEXT NULL, after_json TEXT NULL, actor VARCHAR(120) NOT NULL, created_at VARCHAR(19) NOT NULL)$suffix");
        if (!$sqlite) {
            SchemaGate::mark('warehouse-bonus', self::SCHEMA_VERSION);
        }
        $this->ready = true;
    }

    /** Trabajadores y usuarios activos del ERP, sin repetir a la misma persona. */
    public function erpNames(): array
    {
        if ($this->erp === null) {
            return [];
        }
        $names = [];
        $queries = [
            "SELECT wrk_firstname f, wrk_lastname l FROM workers WHERE wrk_status = 1",
            "SELECT user_firstname f, user_lastname l FROM `user` WHERE user_status = 1",
        ];
        foreach ($queries as $sql) {
            try {
                foreach ($this->erp->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $name = trim((string)preg_replace('/\s+/u', ' ', trim((string)$row['f']) . ' ' . trim((string)$row['l'])));
                    if ($name !== '' && !isset($names[self::nameKey($name)])) {
                        $names[self::nameKey($name)] = $name;
                    }
                }
            } catch (Throwable $e) {
                error_log('Bonificación bodega, listado ERP: ' . $e->getMessage());
            }
        }
        $names = array_values($names);
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        return $names;
    }

    /** Configuración vigente de cada operador en el mes: la última guardada hasta ese mes. */
    public function settings(string $month): array
    {
        self::requireMonth($month);
        $this->ensureSchema();
        $stmt = $this->trz->prepare('SELECT month_key, operator_name, active, amounts_json FROM warehouse_bonus_settings WHERE month_key <= ? ORDER BY month_key ASC');
        $stmt->execute([$month]);
        $settings = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[(string)$row['operator_name']] = [
                'active' => (int)$row['active'] === 1,
                'amounts' => array_map('floatval', json_decode((string)$row['amounts_json'], true) ?: []),
                'effective_from' => (string)$row['month_key'],
            ];
        }
        return $settings;
    }

    /** Nombres que se pueden elegir: ERP más los ya configurados (por ejemplo, agregados a mano). */
    public function selectableNames(string $month): array
    {
        $names = [];
        foreach (array_merge(array_keys($this->settings($month)), $this->erpNames()) as $name) {
            $key = self::nameKey((string)$name);
            if (!isset($names[$key])) {
                $names[$key] = (string)$name;
            }
        }
        $names = array_values($names);
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        return $names;
    }

    private function writeSetting(string $month, string $name, bool $active, array $amounts, ?array $before, string $action, string $actor): void
    {
        $now = date('Y-m-d H:i:s');
        $actor = mb_substr($actor, 0, 120);
        $json = json_encode($amounts, JSON_THROW_ON_ERROR);
        $stmt = $this->trz->prepare('UPDATE warehouse_bonus_settings SET active = ?, amounts_json = ?, updated_by = ?, updated_at = ? WHERE month_key = ? AND operator_name = ?');
        $stmt->execute([$active ? 1 : 0, $json, $actor, $now, $month, $name]);
        $exists = $this->trz->prepare('SELECT 1 FROM warehouse_bonus_settings WHERE month_key = ? AND operator_name = ?');
        $exists->execute([$month, $name]);
        if ($exists->fetchColumn() === false) {
            $stmt = $this->trz->prepare('INSERT INTO warehouse_bonus_settings (month_key, operator_name, active, amounts_json, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$month, $name, $active ? 1 : 0, $json, $actor, $now]);
        }
        $beforeData = $before === null ? null : ['active' => (bool)$before['active'], 'amounts' => $before['amounts']];
        $this->audit($month, $name, $action, $beforeData, ['active' => $active, 'amounts' => $amounts], $actor);
    }

    private function audit(string $month, string $name, string $action, ?array $before, array $after, string $actor): void
    {
        $stmt = $this->trz->prepare('INSERT INTO warehouse_bonus_history (month_key, operator_name, action, before_json, after_json, actor, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$month, $name, $action, $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            json_encode($after, JSON_THROW_ON_ERROR), mb_substr($actor, 0, 120), date('Y-m-d H:i:s')]);
    }

    private function inTransaction(callable $work): void
    {
        $this->trz->beginTransaction();
        try {
            $work();
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
    }

    /** Guarda quiénes participan desde el mes indicado. Quitar a alguien no borra sus montos. */
    public function saveOperators(string $month, array $selected, string $manualName, string $actor): void
    {
        self::requireMonth($month);
        $this->ensureSchema();
        $settings = $this->settings($month);
        $available = [];
        foreach ($this->selectableNames($month) as $name) {
            $available[self::nameKey($name)] = $name;
        }
        $chosen = [];
        foreach ($selected as $name) {
            $key = is_string($name) ? self::nameKey($name) : '';
            if (!isset($available[$key])) {
                throw new RuntimeException('Selecciona operadores del listado.');
            }
            $chosen[$key] = $available[$key];
        }
        $manualName = trim((string)preg_replace('/\s+/u', ' ', $manualName));
        if ($manualName !== '') {
            if (mb_strlen($manualName) < 3 || mb_strlen($manualName) > 120) {
                throw new RuntimeException('El nombre debe tener entre 3 y 120 caracteres.');
            }
            $key = self::nameKey($manualName);
            $chosen[$key] = $available[$key] ?? $manualName;
        }
        $this->inTransaction(function () use ($month, $settings, $chosen, $actor): void {
            foreach ($settings as $name => $data) {
                $active = isset($chosen[self::nameKey($name)]);
                if ($active !== $data['active']) {
                    $this->writeSetting($month, $name, $active, $data['amounts'], $data, 'SELECCION', $actor);
                }
            }
            foreach ($chosen as $name) {
                if (!isset($settings[$name])) {
                    $this->writeSetting($month, $name, true, [], null, 'SELECCION', $actor);
                }
            }
        });
    }

    /**
     * Guarda montos desde el mes indicado. Vacío = el KPI no aplica al operador; 0 = aplica y no paga.
     *
     * @param array<string, array<string, mixed>> $amountsByOperator
     */
    public function saveAmounts(string $month, array $amountsByOperator, string $actor): void
    {
        self::requireMonth($month);
        $this->ensureSchema();
        $settings = $this->settings($month);
        $changes = [];
        foreach ($amountsByOperator as $name => $amounts) {
            $name = (string)$name;
            if (empty($settings[$name]['active']) || !is_array($amounts)) {
                throw new RuntimeException('El listado de operadores cambió. Recarga el mes.');
            }
            $clean = [];
            foreach (self::KPIS as $code => $kpi) {
                $raw = trim((string)($amounts[$code] ?? ''));
                if ($raw === '') {
                    continue;
                }
                if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > self::MAX_AMOUNT || floor((float)$raw) !== (float)$raw) {
                    throw new RuntimeException('Monto no válido en ' . $name . ' · ' . $kpi['label'] . ': usa pesos enteros, sin puntos.');
                }
                $clean[$code] = (float)$raw;
            }
            if ($clean != $settings[$name]['amounts']) {
                $changes[$name] = $clean;
            }
        }
        $this->inTransaction(function () use ($month, $settings, $changes, $actor): void {
            foreach ($changes as $name => $amounts) {
                $this->writeSetting($month, $name, true, $amounts, $settings[$name], 'MONTOS', $actor);
            }
        });
    }

    private static function percent(mixed $value, string $label): float
    {
        $text = str_replace(',', '.', trim((string)$value));
        if (!is_numeric($text) || (float)$text < 0 || (float)$text > 100 || abs((float)$text - round((float)$text, 2)) > 0.000001) {
            throw new RuntimeException('El cumplimiento de ' . $label . ' debe estar entre 0 y 100, con hasta dos decimales.');
        }
        return round((float)$text, 2);
    }

    private static function text(mixed $value, int $max, string $label): string
    {
        $text = trim(is_scalar($value) ? (string)$value : '');
        if (mb_strlen($text) > $max) {
            throw new RuntimeException($label . ' permite hasta ' . $max . ' caracteres.');
        }
        return $text;
    }

    /** Evaluaciones guardadas del mes, por operador. */
    private function evaluations(string $month): array
    {
        $stmt = $this->trz->prepare('SELECT * FROM warehouse_bonus_evaluations WHERE month_key = ?');
        $stmt->execute([$month]);
        $evaluations = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $evaluations[(string)$row['operator_name']] = self::evaluationData($row);
        }
        return $evaluations;
    }

    private static function evaluationData(array $row): array
    {
        return [
            'proactividad' => (float)$row['proactividad_score'], 'eficiencia' => (float)$row['eficiencia_score'], 'multitarea' => (float)$row['multitarea_score'],
            'kpis' => json_decode((string)$row['kpis_json'], true) ?: [],
            'additional' => (float)$row['additional_clp'], 'observations' => (string)$row['observations'],
        ];
    }

    /**
     * Guarda la evaluación de uno o varios operadores, todo o nada. Devuelve el total a pagar de cada uno.
     *
     * @param list<array<string, mixed>> $rows operator, proactividad, eficiencia, multitarea, kpi[codigo][pct|obs], additional, observations
     * @return array<string, float>
     */
    public function saveEvaluations(string $month, array $rows, string $actor): array
    {
        self::requireMonth($month);
        $this->ensureSchema();
        $settings = $this->settings($month);
        $saved = $this->evaluations($month);
        $prepared = [];
        $totals = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['operator'] ?? ''));
            if (empty($settings[$name]['active'])) {
                throw new RuntimeException('El listado de operadores cambió. Recarga el mes.');
            }
            $data = [];
            foreach (['proactividad', 'eficiencia', 'multitarea'] as $field) {
                $data[$field] = BonusConfigurationService::helperScore($row[$field] ?? null);
            }
            $data['kpis'] = [];
            $subtotal = 0.0;
            foreach ($settings[$name]['amounts'] as $code => $amount) {
                $input = is_array($row['kpi'][$code] ?? null) ? $row['kpi'][$code] : [];
                $stored = $saved[$name]['kpis'][$code] ?? ['pct' => 100, 'obs' => ''];
                $label = self::KPIS[$code]['label'] ?? $code;
                $pct = self::percent($input['pct'] ?? $stored['pct'], $name . ' · ' . $label);
                $data['kpis'][$code] = ['pct' => $pct, 'obs' => self::text($input['obs'] ?? $stored['obs'], 255, 'La observación de ' . $label)];
                $subtotal += self::kpiPay($amount, $pct);
            }
            $additional = str_replace(',', '.', trim((string)($row['additional'] ?? '0')));
            $additional = $additional === '' ? '0' : $additional;
            if (!is_numeric($additional) || (float)$additional < 0 || (float)$additional > self::MAX_AMOUNT) {
                throw new RuntimeException('Incentivo adicional no válido para ' . $name . '.');
            }
            $data['additional'] = round((float)$additional, 2);
            $data['observations'] = self::text($row['observations'] ?? '', 255, 'Observaciones');
            $prepared[$name] = $data;
            $totals[$name] = $subtotal + $data['additional'];
        }
        $this->inTransaction(function () use ($month, $prepared, $saved, $actor): void {
            $now = date('Y-m-d H:i:s');
            $actorName = mb_substr($actor, 0, 120);
            foreach ($prepared as $name => $data) {
                $before = $saved[$name] ?? null;
                if ($before !== null && $before == $data) {
                    continue;
                }
                $values = [$data['proactividad'], $data['eficiencia'], $data['multitarea'], json_encode($data['kpis'], JSON_THROW_ON_ERROR),
                    $data['additional'], $data['observations'], $actorName, $now, $month, $name];
                $sql = $before === null
                    ? 'INSERT INTO warehouse_bonus_evaluations (proactividad_score, eficiencia_score, multitarea_score, kpis_json, additional_clp, observations, updated_by, updated_at, month_key, operator_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    : 'UPDATE warehouse_bonus_evaluations SET proactividad_score = ?, eficiencia_score = ?, multitarea_score = ?, kpis_json = ?, additional_clp = ?, observations = ?, updated_by = ?, updated_at = ? WHERE month_key = ? AND operator_name = ?';
                $this->trz->prepare($sql)->execute($values);
                $this->audit($month, $name, 'EVALUACION', $before, $data, $actorName);
            }
        });
        return $totals;
    }

    /** Filas del mes con montos, cumplimiento y pago calculados en el servidor. Sin evaluar: notas 10 y cumplimiento 100 %. */
    public function rows(string $month): array
    {
        $settings = $this->settings($month);
        $evaluations = $this->evaluations($month);
        $names = array_keys(array_filter($settings, static fn(array $s): bool => $s['active']));
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        $rows = [];
        foreach ($names as $name) {
            $evaluation = $evaluations[$name] ?? null;
            $kpis = [];
            $subtotal = 0.0;
            foreach (self::KPIS as $code => $kpi) {
                if (!array_key_exists($code, $settings[$name]['amounts'])) {
                    continue;
                }
                $amount = $settings[$name]['amounts'][$code];
                $pct = (float)($evaluation['kpis'][$code]['pct'] ?? 100);
                $paid = self::kpiPay($amount, $pct);
                $subtotal += $paid;
                $kpis[$code] = ['amount' => $amount, 'pct' => $pct, 'obs' => (string)($evaluation['kpis'][$code]['obs'] ?? ''), 'paid' => $paid];
            }
            $additional = (float)($evaluation['additional'] ?? 0);
            $rows[] = [
                'operator' => $name, 'has_evaluation' => $evaluation !== null, 'effective_from' => $settings[$name]['effective_from'],
                'proactividad' => (float)($evaluation['proactividad'] ?? 10), 'eficiencia' => (float)($evaluation['eficiencia'] ?? 10), 'multitarea' => (float)($evaluation['multitarea'] ?? 10),
                'kpis' => $kpis, 'subtotal' => $subtotal, 'additional' => $additional, 'total' => $subtotal + $additional,
                'observations' => (string)($evaluation['observations'] ?? ''),
            ];
        }
        return $rows;
    }

    public function history(string $month): array
    {
        self::requireMonth($month);
        $this->ensureSchema();
        $stmt = $this->trz->prepare('SELECT * FROM warehouse_bonus_history WHERE month_key = ? ORDER BY id DESC LIMIT 200');
        $stmt->execute([$month]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Traduce un registro del historial a filas [dato, antes, después]; solo lo que cambió. */
    public static function historyChanges(array $entry): array
    {
        $flat = static function (?array $data): array {
            $out = [];
            if ($data === null) {
                return $out;
            }
            if (array_key_exists('active', $data)) {
                $out['Participa'] = $data['active'] ? 'Sí' : 'No';
            }
            foreach ($data['amounts'] ?? [] as $code => $amount) {
                $out['Monto ' . (self::KPIS[$code]['label'] ?? $code)] = '$ ' . number_format((float)$amount, 0, ',', '.');
            }
            foreach (['proactividad' => 'Nota Proactividad', 'eficiencia' => 'Nota Eficiencia', 'multitarea' => 'Nota Multitarea'] as $field => $label) {
                if (isset($data[$field])) {
                    $out[$label] = str_replace('.', ',', (string)(float)$data[$field]);
                }
            }
            foreach ($data['kpis'] ?? [] as $code => $kpi) {
                $label = self::KPIS[$code]['label'] ?? $code;
                $out['Cumplimiento ' . $label] = str_replace('.', ',', (string)(float)$kpi['pct']) . ' %';
                if ((string)($kpi['obs'] ?? '') !== '') {
                    $out['Observación ' . $label] = (string)$kpi['obs'];
                }
            }
            if (isset($data['additional'])) {
                $out['Incentivo adicional'] = '$ ' . number_format((float)$data['additional'], 0, ',', '.');
            }
            if ((string)($data['observations'] ?? '') !== '') {
                $out['Observaciones'] = (string)$data['observations'];
            }
            return $out;
        };
        $before = $flat(json_decode((string)($entry['before_json'] ?? 'null'), true));
        $after = $flat(json_decode((string)($entry['after_json'] ?? 'null'), true));
        $changes = [];
        foreach (array_keys($before + $after) as $label) {
            if (($before[$label] ?? null) !== ($after[$label] ?? null)) {
                $changes[] = [$label, $before[$label] ?? '—', $after[$label] ?? '—'];
            }
        }
        return $changes;
    }
}
