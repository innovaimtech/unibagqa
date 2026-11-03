<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Cálculo y Configuración de Bonificaciones (Flexo, Seri, CyS, Ayudantes)
// (src/Services/Reception/BonusCalculationService.php)
// =============================================================================

trait BonusCalculationServiceTrait
{


    /**
     * Limpia y normaliza una inicial eliminando tildes y caracteres especiales.
     */
    private function cleanBonusInitial(string $str): string
    {
        $str = mb_strtoupper(trim($str));
        if ($str === '') {
            return '';
        }
        $ch = mb_substr($str, 0, 1);
        $map = [
            'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Å' => 'A',
            'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
            'Ñ' => 'N',
        ];
        return $map[$ch] ?? $ch;
    }

    /**
     * Resuelve las partes (primer nombre, primer apellido, segundo apellido) de un operador.
     *
     * @return array{nombre: string, apellido1: string, apellido2: string}
     */
    public function resolveOperatorNameParts(string $fullName): array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return ['nombre' => '', 'apellido1' => '', 'apellido2' => ''];
        }

        if ($this->workersByNormNameCache === null) {
            $this->workersByNormNameCache = [];
            $allWorkers = $this->getAllActiveWorkers();
            foreach ($allWorkers as $w) {
                $fname = trim((string)($w['wrk_firstname'] ?? ''));
                $lname = trim((string)($w['wrk_lastname'] ?? ''));
                $full = trim($fname . ' ' . $lname);
                $norm = mb_strtolower((string)preg_replace('/\s+/', ' ', $full));
                if ($norm !== '') {
                    $this->workersByNormNameCache[$norm] = ['fname' => $fname, 'lname' => $lname];
                }
            }
        }

        $normInput = mb_strtolower((string)preg_replace('/\s+/', ' ', $fullName));
        if (isset($this->workersByNormNameCache[$normInput])) {
            $w = $this->workersByNormNameCache[$normInput];
            $fnParts = preg_split('/\s+/', $w['fname']) ?: [];
            $lnParts = preg_split('/\s+/', $w['lname']) ?: [];
            $pNombre = $fnParts[0] ?? '';
            $pApellido = $lnParts[0] ?? '';
            $sApellido = $lnParts[1] ?? '';
            if (in_array(mb_strtolower($pApellido), ['de', 'del', 'la', 'las', 'los'], true) && isset($lnParts[1])) {
                $pApellido = $lnParts[1];
                $sApellido = $lnParts[2] ?? '';
            }
            return ['nombre' => $pNombre, 'apellido1' => $pApellido, 'apellido2' => $sApellido];
        }

        // Fallback: parseo de la cadena por palabras
        $words = preg_split('/\s+/', $fullName) ?: [];
        $cnt = count($words);
        if ($cnt >= 4) {
            return ['nombre' => $words[0], 'apellido1' => $words[2], 'apellido2' => $words[3]];
        }
        if ($cnt === 3) {
            return ['nombre' => $words[0], 'apellido1' => $words[1], 'apellido2' => $words[2]];
        }
        if ($cnt === 2) {
            return ['nombre' => $words[0], 'apellido1' => $words[1], 'apellido2' => ''];
        }
        return ['nombre' => $words[0] ?? '', 'apellido1' => '', 'apellido2' => ''];
    }

    /**
     * Resuelve y construye el mapeo de códigos/iniciales de operadores para planillas de bonos.
     *
     * Regla oficial:
     * - Se toma la 1ª letra del primer nombre y la 1ª letra del primer apellido (ej. AS, MQ, HC, WE).
     * - Si hay dos o más operadores en la nómina con las mismas 2 letras, se toma la 1ª letra del segundo apellido
     *   (ej. MMS vs MMM, ASG, FGM vs FGT).
     *
     * @param list<string>|array<string, mixed> $names Lista o conjunto de nombres de operadores
     * @return array<string, string> Mapeo [nombre_completo => codigo_bono]
     */
    public function buildOperatorBonusCodesMap(array $names): array
    {
        $uniqueNames = [];
        foreach ($names as $k => $v) {
            $n = is_string($k) && !is_numeric($k) ? trim($k) : trim((string)$v);
            if ($n !== '') {
                $uniqueNames[$n] = true;
            }
        }
        if ($uniqueNames === []) {
            return [];
        }

        $parsed = [];
        $baseCounts = [];

        foreach (array_keys($uniqueNames) as $name) {
            $parts = $this->resolveOperatorNameParts($name);
            $l1 = $this->cleanBonusInitial($parts['nombre']);
            $l2 = $this->cleanBonusInitial($parts['apellido1']);
            $l3 = $this->cleanBonusInitial($parts['apellido2']);

            if ($l1 === '' && $l2 === '') {
                $base = 'OP';
            } elseif ($l2 === '') {
                $base = $this->cleanBonusInitial(mb_substr($parts['nombre'], 0, 1)) . $this->cleanBonusInitial(mb_substr($parts['nombre'], 1, 1));
                if ($base === '') {
                    $base = 'OP';
                }
            } else {
                $base = $l1 . $l2;
            }

            $parsed[$name] = [
                'base' => $base,
                'l1' => $l1,
                'l2' => $l2,
                'l3' => $l3,
            ];

            $baseCounts[$base] = ($baseCounts[$base] ?? 0) + 1;
        }

        $labels = [];
        $used = [];

        foreach ($parsed as $name => $info) {
            $base = $info['base'];
            if (($baseCounts[$base] ?? 1) > 1 && $info['l3'] !== '') {
                $code = $info['l1'] . $info['l2'] . $info['l3'];
            } else {
                $code = $base;
            }

            if (isset($used[$code])) {
                $disambig = $info['l3'] !== '' ? $info['l3'] : '2';
                $code = $info['l1'] . $info['l2'] . $disambig;
                if (isset($used[$code])) {
                    $code .= '2';
                }
            }

            $used[$code] = true;
            $labels[$name] = $code;
        }

        return $labels;
    }

    /**
     * Obtiene el código de bono para un operador específico dado un contexto de operadores.
     */
    public function getOperatorBonusCode(string $fullName, array $contextNames = []): string
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return 'SIN';
        }
        $set = $contextNames !== [] ? array_unique(array_merge($contextNames, [$fullName])) : [$fullName];
        $map = $this->buildOperatorBonusCodesMap($set);
        return $map[$fullName] ?? 'OP';
    }

    /**
     * @return list<string>
     */
    public function listBonusCodes(): array
    {
        return [
            'bonoflexo',
            'bonoseri',
            'bonocys',
            'bonopulp',
            'bonoayudante',
            'bonomantencion',
        ];
    }

    public function listActiveBonusHelpers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT operator_name
             FROM bonus_helper_roster
             WHERE is_active = 1
             ORDER BY operator_name ASC'
        );
        $rows = $stmt->fetchAll();
        if ($rows === false || $rows === []) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $name = trim((string)($r['operator_name'] ?? ''));
            if ($name !== '') {
                $out[] = $name;
            }
        }
        return $out;
    }

    public function saveActiveBonusHelpers(array $operatorNames): array
    {
        $names = [];
        foreach ($operatorNames as $n) {
            $n = trim((string)$n);
            if ($n === '' || mb_strlen($n) > 120) {
                continue;
            }
            $names[] = $n;
        }
        $names = array_values(array_unique($names));

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('UPDATE bonus_helper_roster SET is_active = 0');
            if ($names !== []) {
                $upsert = $this->pdo->prepare(
                    'INSERT INTO bonus_helper_roster (operator_name, is_active)
                     VALUES (:operator_name, 1)
                     ON DUPLICATE KEY UPDATE is_active = 1'
                );
                foreach ($names as $name) {
                    $upsert->execute([':operator_name' => $name]);
                }
            }
            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'errors' => ['No se pudo guardar ayudantes.']];
        }
    }

    /** Keep legacy amounts as a baseline; effective settings take precedence.
     * Conservar montos heredados como base; la configuracion vigente tiene prioridad. */
    public function getBonusHelperSettings(string $monthKey): array
    {
        $settings = [];
        $empty = ['active'=>true,'matrix_proactividad_clp'=>0,'matrix_eficiencia_clp'=>0,
            'matrix_multitarea_clp'=>0,'fixed_clp'=>0,'instructions'=>'','effective_from'=>''];
        foreach ($this->listActiveBonusHelpers() as $name) $settings[$name] = $empty;
        $stmt = $this->pdo->prepare('SELECT month_key,operator_name,matrix_proactividad_clp,
            matrix_eficiencia_clp,matrix_multitarea_clp,fixed_clp FROM bonus_helper_monthly
            WHERE month_key<=? ORDER BY month_key ASC');
        $stmt->execute([$monthKey]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = trim((string)$row['operator_name']);
            if ($name === '') continue;
            $settings[$name] = $empty;
            foreach (['matrix_proactividad_clp','matrix_eficiencia_clp','matrix_multitarea_clp','fixed_clp'] as $field) {
                $settings[$name][$field] = (float)$row[$field];
            }
            $settings[$name]['effective_from'] = $row['month_key'];
        }
        $effective = (new BonusConfigurationService($this->pdo))->getHelperConfiguration($monthKey) ?? [];
        return array_replace($settings, $effective);
    }

    private function ensureHelperScorePrecision(): void
    {
        if ($this->helperScoreSchemaReady) return;
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
            $changes = [];
            foreach ($this->pdo->query('SHOW COLUMNS FROM bonus_helper_monthly')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                if (in_array($column['Field'],['proactividad_score','eficiencia_score','multitarea_score'],true)
                    && preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)\b/i',$column['Type'])) {
                    $changes[] = 'MODIFY `'.$column['Field'].'` DECIMAL(4,2) UNSIGNED NOT NULL DEFAULT 0';
                }
            }
            // TRZ only; preserve existing grades instead of truncating decimal inputs.
            // Solo TRZ: conservar notas existentes y evitar truncar las nuevas.
            if ($changes) $this->pdo->exec('ALTER TABLE bonus_helper_monthly '.implode(', ',$changes));
        }
        $this->helperScoreSchemaReady = true;
    }

    public function listBonusHelperMonthlyRows(string $monthKey): array
    {
        $monthKey = trim($monthKey);
        if (!preg_match('/^(\d{4})-(\d{2})$/', $monthKey, $monthParts)
            || !checkdate((int)$monthParts[2], 1, (int)$monthParts[1])) {
            return [];
        }
        $this->ensureHelperScorePrecision();
        $configuration = $this->getBonusHelperSettings($monthKey);
        $erpNames = array_fill_keys($this->listErpWorkerNames(), true);
        $helperSet = [];
        foreach ($configuration as $name => $settings) {
            if (isset($erpNames[$name]) && !empty($settings['active'])) $helperSet[$name] = true;
            else unset($helperSet[$name]);
        }
        $helpers = array_keys($helperSet);
        sort($helpers, SORT_NATURAL | SORT_FLAG_CASE);
        if ($helpers === []) {
            return [];
        }

        $in = [];
        $params = [':month_key' => $monthKey];
        foreach ($helpers as $i => $name) {
            $k = ':op' . $i;
            $in[] = $k;
            $params[$k] = $name;
        }
        $sql = 'SELECT operator_name, proactividad_score, eficiencia_score, multitarea_score,
                       matrix_proactividad_clp, matrix_eficiencia_clp, matrix_multitarea_clp,
                       fixed_clp, additional_clp, observations
                FROM bonus_helper_monthly
                WHERE month_key = :month_key AND operator_name IN (' . implode(',', $in) . ')
                ORDER BY operator_name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $map = [];
        foreach ($rows as $r) {
            $name = trim((string)($r['operator_name'] ?? ''));
            if ($name !== '') {
                $map[$name] = $r;
            }
        }

        $out = [];
        foreach ($helpers as $name) {
            $r = $map[$name] ?? null;
            $out[] = [
                'operator_name' => $name,
                'proactividad_score' => (float)($r['proactividad_score'] ?? 0),
                'eficiencia_score' => (float)($r['eficiencia_score'] ?? 0),
                'multitarea_score' => (float)($r['multitarea_score'] ?? 0),
                'matrix_proactividad_clp' => (float)($r['matrix_proactividad_clp'] ?? 0.0),
                'matrix_eficiencia_clp' => (float)($r['matrix_eficiencia_clp'] ?? 0.0),
                'matrix_multitarea_clp' => (float)($r['matrix_multitarea_clp'] ?? 0.0),
                'fixed_clp' => (float)($r['fixed_clp'] ?? 0.0),
                'additional_clp' => (float)($r['additional_clp'] ?? 0.0),
                'observations' => (string)($r['observations'] ?? ''),
                'instructions' => (string)($configuration[$name]['instructions'] ?? ''),
                'has_evaluation' => $r !== null,
            ];
            if (isset($configuration[$name])) {
                $last = count($out) - 1;
                foreach (['matrix_proactividad_clp','matrix_eficiencia_clp','matrix_multitarea_clp','fixed_clp'] as $field) {
                    $out[$last][$field] = (float)$configuration[$name][$field];
                }
            }
        }
        return $out;
    }

    public function saveBonusHelperMonthlyRows(string $monthKey, array $rows, string $actor = 'Sistema'): array
    {
        $monthKey = trim($monthKey);
        if (!preg_match('/^(\d{4})-(\d{2})$/', $monthKey, $monthParts)
            || !checkdate((int)$monthParts[2], 1, (int)$monthParts[1])) {
            return ['ok' => false, 'errors' => ['Mes inválido.']];
        }
        $configuredRows = $this->listBonusHelperMonthlyRows($monthKey);
        if ($configuredRows === []) {
            return ['ok' => false, 'errors' => ['No hay ayudantes seleccionados.']];
        }
        $helperSet = array_column($configuredRows, null, 'operator_name');
        $seen = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['operator_name'] ?? ''));
            if (!isset($helperSet[$name]) || isset($seen[$name])) {
                return ['ok'=>false,'errors'=>['El listado de ayudantes cambio. Recarga el mes.']];
            }
            $seen[$name] = true;
            foreach (['proactividad_score','eficiencia_score','multitarea_score'] as $field) {
                try {
                    BonusConfigurationService::helperScore($row[$field] ?? null);
                } catch (RuntimeException $e) {
                    return ['ok'=>false,'errors'=>[$e->getMessage()]];
                }
            }
            $additional = $row['additional_clp'] ?? 0;
            if (!is_numeric($additional) || !is_finite((float)$additional) || (float)$additional < 0 || (float)$additional > 9999999999.99) {
                return ['ok'=>false,'errors'=>['Incentivo adicional no valido.']];
            }
        }
        if (!$rows) return ['ok'=>false,'errors'=>['No hay evaluaciones para guardar.']];

        $bonusConfig = new BonusConfigurationService($this->pdo);
        $bonusConfig->getHelperConfiguration($monthKey);
        $this->pdo->beginTransaction();
        try {
            $upsert = $this->pdo->prepare(
                'INSERT INTO bonus_helper_monthly (
                    month_key, operator_name,
                    proactividad_score, eficiencia_score, multitarea_score,
                    matrix_proactividad_clp, matrix_eficiencia_clp, matrix_multitarea_clp,
                    fixed_clp, additional_clp, observations
                 ) VALUES (
                    :month_key, :operator_name,
                    :proactividad_score, :eficiencia_score, :multitarea_score,
                    :matrix_proactividad_clp, :matrix_eficiencia_clp, :matrix_multitarea_clp,
                    :fixed_clp, :additional_clp, :observations
                 )
                 ON DUPLICATE KEY UPDATE
                    proactividad_score = VALUES(proactividad_score),
                    eficiencia_score = VALUES(eficiencia_score),
                    multitarea_score = VALUES(multitarea_score),
                    matrix_proactividad_clp = VALUES(matrix_proactividad_clp),
                    matrix_eficiencia_clp = VALUES(matrix_eficiencia_clp),
                    matrix_multitarea_clp = VALUES(matrix_multitarea_clp),
                    fixed_clp = VALUES(fixed_clp),
                    additional_clp = VALUES(additional_clp),
                    observations = VALUES(observations)'
            );

            foreach ($rows as $r) {
                $name = trim((string)($r['operator_name'] ?? ''));
                if ($name === '' || !isset($helperSet[$name])) {
                    continue;
                }
                $p = BonusConfigurationService::helperScore($r['proactividad_score']);
                $e = BonusConfigurationService::helperScore($r['eficiencia_score']);
                $m = BonusConfigurationService::helperScore($r['multitarea_score']);
                // Never accept payment limits from the evaluation form.
                // Los topes se leen de TRZ, nunca del formulario de notas.
                $mp = (float)$helperSet[$name]['matrix_proactividad_clp'];
                $me = (float)$helperSet[$name]['matrix_eficiencia_clp'];
                $mm = (float)$helperSet[$name]['matrix_multitarea_clp'];
                $fixed = (float)$helperSet[$name]['fixed_clp'];
                $add = max(0.0, (float)($r['additional_clp'] ?? 0.0));
                $obs = trim((string)($r['observations'] ?? ''));
                if (mb_strlen($obs) > 255) {
                    $obs = mb_substr($obs, 0, 255);
                }

                $upsert->execute([
                    ':month_key' => $monthKey,
                    ':operator_name' => $name,
                    ':proactividad_score' => $p,
                    ':eficiencia_score' => $e,
                    ':multitarea_score' => $m,
                    ':matrix_proactividad_clp' => $mp,
                    ':matrix_eficiencia_clp' => $me,
                    ':matrix_multitarea_clp' => $mm,
                    ':fixed_clp' => $fixed,
                    ':additional_clp' => $add,
                    ':observations' => $obs !== '' ? $obs : null,
                ]);
                $fields = ['proactividad_score'=>$p,'eficiencia_score'=>$e,'multitarea_score'=>$m,
                    'matrix_proactividad_clp'=>$mp,'matrix_eficiencia_clp'=>$me,'matrix_multitarea_clp'=>$mm,
                    'fixed_clp'=>$fixed,'additional_clp'=>$add,'observations'=>$obs];
                $before = null;
                if (!empty($helperSet[$name]['has_evaluation'])) {
                    $before = ['operator'=>$name];
                    foreach ($fields as $key=>$value) $before[$key] = $helperSet[$name][$key];
                }
                $bonusConfig->recordHelperEvaluation($monthKey, $before, ['operator'=>$name]+$fields, $actor);
            }

            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'errors' => ['No se pudo guardar bonificación.']];
        }
    }

    /**
     * Calcula el período estándar de bonificaciones (26 de un mes al 25 del mes siguiente).
     *
     * $monthKey se interpreta como “mes final” del período en formato YYYY-MM.
     * Ejemplo: monthKey=2026-08 => período 2026-07-26 00:00:00 a 2026-08-25 23:59:59.
    /**
     * Resuelve el período o rango de tiempo para bonificaciones:
     * - Si $filterType === 'range' y se proveen $startDate y $endDate válidos (Y-m-d),
     *   calcula el rango personalizado con sus timestamps exactos.
     * - Si es 'period' (o no viene rango), calcula el período 26–25 del mes indicado.
     *
     * @return array{filter_type:string,month_key:string,start_date:string,end_date:string,start_ts:int,end_ts:int,label:string}
     */
    public function resolveBonusFilterPeriod(
        string $filterType = 'period',
        ?string $monthKey = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $tz = new DateTimeZone(date_default_timezone_get());
        $filterType = strtolower(trim($filterType));
        if ($filterType !== 'range') {
            $filterType = 'period';
        }

        if ($filterType === 'range' && $startDate !== null && $endDate !== null) {
            $startDate = trim($startDate);
            $endDate = trim($endDate);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                $startObj = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startDate . ' 00:00:00', $tz);
                $endObj = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endDate . ' 23:59:59', $tz);
                if ($startObj instanceof DateTimeImmutable && $endObj instanceof DateTimeImmutable && $endObj >= $startObj) {
                    $derivedMonth = $endObj->format('Y-m');
                    return [
                        'filter_type' => 'range',
                        'month_key' => ($monthKey && preg_match('/^\d{4}-\d{2}$/', $monthKey)) ? $monthKey : $derivedMonth,
                        'start_date' => $startObj->format('Y-m-d'),
                        'end_date' => $endObj->format('Y-m-d'),
                        'start_ts' => $startObj->getTimestamp(),
                        'end_ts' => $endObj->getTimestamp(),
                        'label' => 'Rango: ' . $startObj->format('d/m/Y') . ' a ' . $endObj->format('d/m/Y'),
                    ];
                }
            }
        }

        // Modo Período (26 al 25)
        $monthKey = trim((string)$monthKey);
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $today = new DateTimeImmutable('now', $tz);
            $monthKey = ((int)$today->format('j') >= 26)
                ? $today->modify('+1 month')->format('Y-m')
                : $today->format('Y-m');
        }

        $monthStart = new DateTimeImmutable($monthKey . '-01 00:00:00', $tz);
        $previousMonth = $monthStart->modify('-1 month');
        $periodStart = $previousMonth->setDate((int)$previousMonth->format('Y'), (int)$previousMonth->format('m'), 26)->setTime(0, 0, 0);
        $periodEnd = $monthStart->setDate((int)$monthStart->format('Y'), (int)$monthStart->format('m'), 25)->setTime(23, 59, 59);

        return [
            'filter_type' => 'period',
            'month_key' => $monthKey,
            'start_date' => $periodStart->format('Y-m-d'),
            'end_date' => $periodEnd->format('Y-m-d'),
            'start_ts' => $periodStart->getTimestamp(),
            'end_ts' => $periodEnd->getTimestamp(),
            'label' => 'Período 26–25: ' . $periodStart->format('d/m/Y') . ' a ' . $periodEnd->format('d/m/Y'),
        ];
    }

    /**
     * Retorna fechas (Y-m-d) y timestamps (epoch) para usar en consultas ERP/DB local.
     * Soporta tanto string con mes ($monthKey) como array ya resuelto por resolveBonusFilterPeriod.
     *
     * @return array{month_key:string,start_date:string,end_date:string,start_ts:int,end_ts:int,filter_type?:string,label?:string}
     */
    public function getBonusPeriodByMonthFinal(string|array $monthKeyOrPeriod): array
    {
        if (is_array($monthKeyOrPeriod) && isset($monthKeyOrPeriod['start_ts'], $monthKeyOrPeriod['end_ts'])) {
            return $monthKeyOrPeriod;
        }
        return $this->resolveBonusFilterPeriod('period', (string)$monthKeyOrPeriod);
    }

    /**
     * Lista producción de Flexografía desde el ERP para el período 26–25 o rango personalizado.
     *
     * @return array{ok:bool, errors:string[], period:array{month_key:string,start_date:string,end_date:string,start_ts:int,end_ts:int}, rows:list<array<string,mixed>>}
     */
    public function listErpFlexoProductionForBonusPeriod(string|array $monthKey, ?string $operatorName = null, ?string $costCenter = null): array
    {
        $equipotypeIds = $this->resolveErpEquipotypeIdsForBonus('bonoflexo');
        $equipoIds = $equipotypeIds === null ? $this->resolveErpEquipoIdsForBonus('bonoflexo') : null;
        return $this->listErpProductionForBonusPeriod(
            $monthKey,
            $operatorName,
            $costCenter,
            $equipotypeIds,
            $equipoIds,
            null,
            false,
            null
        );
    }

    public function listErpFlexoProductionPreviewForBonusPeriod(string|array $monthKey, ?string $operatorName = null, ?string $costCenter = null, int $limit = 12): array
    {
        $limit = max(1, min(200, $limit));
        $equipotypeIds = $this->resolveErpEquipotypeIdsForBonus('bonoflexo');
        $equipoIds = $equipotypeIds === null ? $this->resolveErpEquipoIdsForBonus('bonoflexo') : null;
        return $this->listErpProductionForBonusPeriod(
            $monthKey,
            $operatorName,
            $costCenter,
            $equipotypeIds,
            $equipoIds,
            $limit,
            false,
            null
        );
    }

    /**
     * Lista producción de Serigrafía desde el ERP para el período 26–25 o rango personalizado.
     *
     * @return array{ok:bool, errors:string[], period:array{month_key:string,start_date:string,end_date:string,start_ts:int,end_ts:int}, rows:list<array<string,mixed>>}
     */
    public function listErpSeriProductionForBonusPeriod(string|array $monthKey, ?string $operatorName = null): array
    {
        $equipotypeIds = $this->resolveErpEquipotypeIdsForBonus('bonoseri');
        $equipoIds = $this->resolveErpEquipoIdsForBonus('bonoseri');
        return $this->listErpProductionForBonusPeriod(
            $monthKey,
            $operatorName,
            null,
            $equipotypeIds,
            $equipoIds,
            null,
            true,
            ['%PULPO%']
        );
    }

    public function listErpSeriProductionPreviewForBonusPeriod(string|array $monthKey, ?string $operatorName = null, int $limit = 12): array
    {
        $limit = max(1, min(200, $limit));
        $equipotypeIds = $this->resolveErpEquipotypeIdsForBonus('bonoseri');
        $equipoIds = $this->resolveErpEquipoIdsForBonus('bonoseri');
        return $this->listErpProductionForBonusPeriod(
            $monthKey,
            $operatorName,
            null,
            $equipotypeIds,
            $equipoIds,
            $limit,
            true,
            ['%PULPO%']
        );
    }

    /**
     * @return list<array{param_equipo_id:int,param_medida:int,param_corte:float,equipo_type_id:int}>
     */
    public function listErpEquipoParams(): array
    {
        if ($this->erpPdo === null) {
            return [];
        }
        try {
            $stmt = $this->erpPdo->query(
                'SELECT ep.param_equipo_id, ep.param_medida, ep.param_corte, eq.equipo_type_id
                 FROM equipo_params ep
                 LEFT JOIN equipo eq ON eq.id = ep.param_equipo_id
                 ORDER BY ep.param_equipo_id ASC, ep.param_medida ASC'
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                return [];
            }
            $out = [];
            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $out[] = [
                    'param_equipo_id' => (int)($r['param_equipo_id'] ?? 0),
                    'param_medida' => (int)($r['param_medida'] ?? 0),
                    'param_corte' => (float)($r['param_corte'] ?? 0.0),
                    'equipo_type_id' => (int)($r['equipo_type_id'] ?? 0),
                ];
            }
            return $out;
        } catch (Throwable) {
            return [];
        }
    }


    public function listErpCysProductionForBonusPeriod(string|array $monthKey, ?string $operatorName = null): array
    {
        $equipotypeIds = $this->resolveErpEquipotypeIdsForBonus('bonocys');
        $equipoIds = $equipotypeIds === null ? $this->resolveErpEquipoIdsForBonus('bonocys') : null;
        return $this->listErpProductionForBonusPeriod(
            $monthKey,
            $operatorName,
            null,
            $equipotypeIds,
            $equipoIds,
            null,
            false,
            null
        );
    }

    public function listErpCysProductionPreviewForBonusPeriod(string|array $monthKey, ?string $operatorName = null, int $limit = 12): array
    {
        $limit = max(1, min(200, $limit));
        $equipotypeIds = $this->resolveErpEquipotypeIdsForBonus('bonocys');
        $equipoIds = $equipotypeIds === null ? $this->resolveErpEquipoIdsForBonus('bonocys') : null;
        return $this->listErpProductionForBonusPeriod(
            $monthKey,
            $operatorName,
            null,
            $equipotypeIds,
            $equipoIds,
            $limit,
            false,
            null
        );
    }

    public function listErpCysConfigChangeCountsForBonusPeriod(string|array $monthKey, ?string $operatorName = null): array
    {
        $period = $this->getBonusPeriodByMonthFinal($monthKey);
        $startTs = (int)($period['start_ts'] ?? 0);
        $endTs = (int)($period['end_ts'] ?? 0);
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return ['ok' => false, 'errors' => ['Período inválido.'], 'period' => $period, 'counts' => []];
        }

        if (
            !$this->erpTableExists('prod_worker_ot_events')
            || !$this->erpTableExists('prod_worker_ot')
            || !$this->erpTableExists('prod_agenda')
            || !$this->erpTableExists('prod_worker_init')
            || !$this->erpTableExists('workers')
        ) {
            return ['ok' => true, 'errors' => [], 'period' => $period, 'counts' => []];
        }
        if (
            !$this->erpColumnExists('prod_worker_ot_events', 'evt_prod_worker_otid')
            || !$this->erpColumnExists('prod_worker_ot_events', 'evt_type')
            || !$this->erpColumnExists('prod_worker_ot_events', 'evt_crtdat')
            || !$this->erpColumnExists('prod_worker_ot_events', 'evt_medida_fromid')
            || !$this->erpColumnExists('prod_worker_ot_events', 'evt_medida_toid')
        ) {
            return ['ok' => true, 'errors' => [], 'period' => $period, 'counts' => []];
        }

        $equipotypeIds = $this->resolveErpEquipotypeIdsForBonus('bonocys');
        $equipoIds = $equipotypeIds === null ? $this->resolveErpEquipoIdsForBonus('bonocys') : null;

        $equipotypeFilterSql = '';
        $equipotypeParams = [];
        if (is_array($equipotypeIds) && $equipotypeIds !== []) {
            $equipotypePlaceholders = [];
            foreach (array_values(array_unique(array_map('intval', $equipotypeIds))) as $idx => $id) {
                if ($id <= 0) {
                    continue;
                }
                $ph = ':equipotype_id_' . $idx;
                $equipotypePlaceholders[] = $ph;
                $equipotypeParams[$ph] = $id;
            }
            if ($equipotypePlaceholders !== []) {
                $equipotypeFilterSql = ' AND pa.ag_equipotype_id IN (' . implode(',', $equipotypePlaceholders) . ')';
            }
        }

        $equipoFilterSql = '';
        $equipoParams = [];
        if (is_array($equipoIds) && $equipoIds !== []) {
            $equipoPlaceholders = [];
            foreach (array_values(array_unique(array_map('intval', $equipoIds))) as $idx => $id) {
                if ($id <= 0) {
                    continue;
                }
                $ph = ':equipo_id_' . $idx;
                $equipoPlaceholders[] = $ph;
                $equipoParams[$ph] = $id;
            }
            if ($equipoPlaceholders !== []) {
                $equipoFilterSql = ' AND pa.ag_equipo_id IN (' . implode(',', $equipoPlaceholders) . ')';
            }
        }

        $operatorName = $operatorName !== null ? trim($operatorName) : '';
        $canApplyRule = $this->erpTableExists('prod_medidas')
            && $this->erpTableExists('parametros')
            && $this->erpColumnExists('prod_medidas', 'id')
            && $this->erpColumnExists('prod_medidas', 'med_tipoproducto')
            && $this->erpColumnExists('parametros', 'tabla')
            && $this->erpColumnExists('parametros', 'codigo')
            && $this->erpColumnExists('parametros', 'valor1')
            && $this->erpColumnExists('parametros', 'valor2');
        $hasEventId = $this->erpColumnExists('prod_worker_ot_events', 'id');
        $eventIdExpr = $hasEventId ? 'e.id' : '0';
        $eventOrder = $hasEventId ? ', e.id' : '';
        $validStatusSql = '';
        if ($this->erpColumnExists('prod_worker_ot_events', 'evt_status')) {
            $validStatusSql .= ' AND e.evt_status > 0';
        }
        if ($this->erpColumnExists('prod_worker_ot', 'wok_status')) {
            $validStatusSql .= ' AND pwo.wok_status > 0';
        }
        $aplicaFilterSql = '';
        $validMeasuresSql = '';
        if ($canApplyRule) {
            // EXISTS checks eligibility without multiplying events through catalog joins.
            // EXISTS valida el pago sin multiplicar eventos por coincidencias del catalogo.
            // Missing product rules must not become a payable change through NULL defaults.
            // Una regla inexistente no debe convertirse en un cambio pagable.
            $aplicaFilterSql = <<<SQL
 AND EXISTS (
    SELECT 1
    FROM prod_medidas m_from
    CROSS JOIN prod_medidas m_to
    INNER JOIN parametros p_from ON p_from.tabla = 'TIPOPRODUCTO' AND p_from.codigo = CAST(m_from.med_tipoproducto AS CHAR)
    INNER JOIN parametros p_to ON p_to.tabla = 'TIPOPRODUCTO' AND p_to.codigo = CAST(m_to.med_tipoproducto AS CHAR)
    WHERE m_from.id = e.evt_medida_fromid
      AND m_to.id = e.evt_medida_toid
      AND (
        (COALESCE(p_from.valor2, 0) = 1 AND COALESCE(p_to.valor2, 0) = 1)
        OR ((COALESCE(p_from.valor2, 0) = 0 OR COALESCE(p_to.valor2, 0) = 0)
            AND COALESCE(p_from.valor1, '') <> COALESCE(p_to.valor1, ''))
      )
 )
SQL;
            // No-change placeholders without a product rule do not reset machine state.
            // Los comodines sin regla de producto no reinician la configuracion fisica.
            $validMeasuresSql = <<<SQL
 AND EXISTS (
    SELECT 1 FROM prod_medidas m
    INNER JOIN parametros p ON p.tabla = 'TIPOPRODUCTO' AND p.codigo = CAST(m.med_tipoproducto AS CHAR)
    WHERE m.id = e.evt_medida_fromid
 )
 AND EXISTS (
    SELECT 1 FROM prod_medidas m
    INNER JOIN parametros p ON p.tabla = 'TIPOPRODUCTO' AND p.codigo = CAST(m.med_tipoproducto AS CHAR)
    WHERE m.id = e.evt_medida_toid
 )
SQL;
        }
        $operatorExpr = 'TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, "")))';

        $sql = <<<SQL
SELECT DISTINCT
    {$eventIdExpr} AS event_id,
    e.evt_crtdat AS event_ts,
    pa.ag_equipo_id AS machine_id,
    e.evt_medida_fromid AS measure_from_id,
    e.evt_medida_toid AS measure_to_id,
    {$operatorExpr} AS operator_name,
    CASE WHEN e.evt_medida_fromid <> e.evt_medida_toid {$aplicaFilterSql} THEN 1 ELSE 0 END AS applies
FROM prod_worker_ot_events e
INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
LEFT JOIN workers w ON w.id = pwi.win_wrkid
WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
  AND LOWER(e.evt_type) = 'apertura'
  AND e.evt_medida_fromid > 0
  AND e.evt_medida_toid > 0
  AND pa.ag_equipo_id > 0
{$validStatusSql}
{$validMeasuresSql}
{$equipotypeFilterSql}
{$equipoFilterSql}
ORDER BY e.evt_crtdat {$eventOrder}
SQL;

        $counts = [];
        $excludedRepeats = [];
        try {
            $stmt = $this->erpPdo->prepare($sql);
            $params = array_merge([
                ':start_ts' => $startTs,
                ':end_ts' => $endTs,
            ], $equipotypeParams, $equipoParams);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $previous = [];
            // Seed each machine from its last valid setup before the period, without a
            // fixed lookback or operator filter. Other operators also change its state.
            // Recuperar la ultima configuracion previa sin limitar dias ni operadores.
            $historySql = str_replace(
                'e.evt_crtdat BETWEEN :start_ts AND :end_ts',
                'e.evt_crtdat < :start_ts AND pa.ag_equipo_id = :history_machine',
                $sql
            );
            $historySql = str_replace(
                "ORDER BY e.evt_crtdat {$eventOrder}",
                'ORDER BY e.evt_crtdat DESC' . ($hasEventId ? ', e.id DESC' : '') . ' LIMIT 1',
                $historySql
            );
            $historyStmt = $this->erpPdo->prepare($historySql);
            foreach (array_unique(array_column($rows, 'machine_id')) as $machineId) {
                $historyParams = $params;
                unset($historyParams[':end_ts']);
                $historyParams[':history_machine'] = $machineId;
                $historyStmt->execute($historyParams);
                $previous[$machineId] = $historyStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            foreach ($rows as $r) {
                $machineId = (int)$r['machine_id'];
                $prior = $previous[$machineId] ?? null;
                $repeated = $prior && (int)$prior['measure_to_id'] === (int)$r['measure_to_id'];
                $previous[$machineId] = $r;
                $name = trim((string)($r['operator_name'] ?? ''));
                if ($name === '' || ($operatorName !== '' && mb_strtolower($name) !== mb_strtolower($operatorName))
                    || empty($r['applies'])) continue;
                if ($repeated) {
                    $excludedRepeats[] = ['event_id'=>(int)$r['event_id'], 'operator_name'=>$name,
                        'machine_id'=>$machineId, 'measure_to_id'=>(int)$r['measure_to_id'],
                        'previous_event_id'=>(int)$prior['event_id']];
                } else {
                    $counts[$name] = ($counts[$name] ?? 0) + 1;
                }
            }
            ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
        } catch (Throwable $e) {
            return ['ok' => false, 'errors' => ['No se pudo consultar cambios de configuración.'], 'period' => $period, 'counts' => []];
        }

        return ['ok' => true, 'errors' => [], 'period' => $period, 'counts' => $counts, 'excluded_repeats'=>$excludedRepeats];
    }

    public function listErpCysPackagingUnitsForBonusPeriod(string|array $monthKey, ?string $operatorName = null): array
    {
        $rowsRes = $this->listErpCysPackagingRowsForBonusPeriod($monthKey, $operatorName);
        if (($rowsRes['ok'] ?? false) !== true) {
            return ['ok' => false, 'errors' => $rowsRes['errors'] ?? ['No se pudo consultar embalaje.'], 'period' => $rowsRes['period'] ?? [], 'units' => []];
        }

        $units = [];
        foreach ((array)($rowsRes['rows'] ?? []) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $name = trim((string)($r['operator_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $u = (float)($r['produced_units'] ?? 0.0);
            if ($u <= 0) {
                continue;
            }
            $units[$name] = (float)($units[$name] ?? 0.0) + $u;
        }

        return ['ok' => true, 'errors' => [], 'period' => $rowsRes['period'] ?? [], 'units' => $units, 'rows' => $rowsRes['rows'] ?? []];
    }

    /**
     * Lista filas detalladas de producción de Embalaje desde el ERP para el período de bono.
     *
     * @return array{ok:bool, errors:string[], period:array<string,mixed>, rows:list<array<string,mixed>>}
     */
    public function listErpCysPackagingRowsForBonusPeriod(string|array $monthKey, ?string $operatorName = null): array
    {
        $period = $this->getBonusPeriodByMonthFinal($monthKey);
        $startTs = (int)($period['start_ts'] ?? 0);
        $endTs = (int)($period['end_ts'] ?? 0);
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return ['ok' => false, 'errors' => ['Período inválido.'], 'period' => $period, 'rows' => []];
        }

        $equipotypeIds = $this->resolveErpPackagingEquipotypeIds();
        if ($equipotypeIds === null || $equipotypeIds === []) {
            return ['ok' => true, 'errors' => [], 'period' => $period, 'rows' => []];
        }

        $result = $this->listErpProductionForBonusPeriod($monthKey, $operatorName, null, $equipotypeIds, null, null, false, null);
        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'errors' => $result['errors'] ?? ['No se pudo consultar embalaje.'], 'period' => $period, 'rows' => []];
        }

        return ['ok' => true, 'errors' => [], 'period' => $period, 'rows' => (array)($result['rows'] ?? [])];
    }

    private function resolveErpPackagingEquipotypeIds(): ?array
    {
        if (array_key_exists('packaging', $this->erpEquipotypeIdsByFeatureCache)) {
            return $this->erpEquipotypeIdsByFeatureCache['packaging'];
        }

        $ids = [];
        try {
            if ($this->erpTableExists('equipo_type')) {
                $titleCol = null;
                if ($this->erpColumnExists('equipo_type', 'type_ant_title')) {
                    $titleCol = 'type_ant_title';
                } elseif ($this->erpColumnExists('equipo_type', 'title')) {
                    $titleCol = 'title';
                }
                if ($titleCol !== null) {
                    $sql = 'SELECT id FROM equipo_type WHERE UPPER(' . $titleCol . ') LIKE :p1 OR UPPER(' . $titleCol . ') LIKE :p2 OR UPPER(' . $titleCol . ') LIKE :p3 ORDER BY id';
                    $stmt = $this->erpPdo->prepare($sql);
                    $stmt->execute([
                        ':p1' => '%EMBAL%',
                        ':p2' => '%EMBALA%',
                        ':p3' => '%PACK%',
                    ]);
                    foreach ($stmt->fetchAll() as $r) {
                        if (!is_array($r)) {
                            continue;
                        }
                        $id = (int)($r['id'] ?? 0);
                        if ($id > 0) {
                            $ids[] = $id;
                        }
                    }
                }
            }
        } catch (Throwable) {
            $ids = [];
        }

        $ids = array_values(array_unique(array_values(array_filter($ids, static fn ($v) => (int)$v > 0))));
        sort($ids);
        if ($ids === []) {
            try {
                if ($this->erpTableExists('equipo_type')) {
                    $stmt = $this->erpPdo->prepare('SELECT COUNT(*) FROM equipo_type WHERE id = 15');
                    $stmt->execute();
                    $exists = (int)($stmt->fetchColumn() ?: 0) > 0;
                    if ($exists) {
                        $ids = [15];
                    }
                }
            } catch (Throwable) {
                $ids = [];
            }
        }

        $this->erpEquipotypeIdsByFeatureCache['packaging'] = $ids !== [] ? $ids : null;
        return $this->erpEquipotypeIdsByFeatureCache['packaging'];
    }


    public function getErpEquipotypeIdsForBonusCode(string $bonusCode): ?array
    {
        return $this->resolveErpEquipotypeIdsForBonus($bonusCode);
    }

    public function getErpEquipoIdsForBonusCode(string $bonusCode): ?array
    {
        return $this->resolveErpEquipoIdsForBonus($bonusCode);
    }

    private function resolveErpEquipoIdsForBonus(string $bonusCode): ?array
    {
        $bonusCode = strtolower(trim($bonusCode));
        if ($bonusCode === '') {
            return null;
        }
        if (array_key_exists($bonusCode, $this->erpEquipoIdsByBonusCache)) {
            return $this->erpEquipoIdsByBonusCache[$bonusCode];
        }
        if (!$this->erpTableExists('equipo')) {
            $this->erpEquipoIdsByBonusCache[$bonusCode] = null;
            return null;
        }

        $patterns = [];
        if ($bonusCode === 'bonoflexo') {
            $patterns = ['%FLEXO%'];
        } elseif ($bonusCode === 'bonoseri') {
            $patterns = ['%SERIG%','%SERI%'];
        } elseif ($bonusCode === 'bonocys') {
            $patterns = ['%SELLAD%'];
        } else {
            $this->erpEquipoIdsByBonusCache[$bonusCode] = null;
            return null;
        }

        $ids = [];
        try {
            $hasEquipoType = $this->erpTableExists('equipo_type');
            $equipoNameCol = null;
            if ($this->erpColumnExists('equipo', 'equipo_name')) {
                $equipoNameCol = 'equipo_name';
            } elseif ($this->erpColumnExists('equipo', 'name')) {
                $equipoNameCol = 'name';
            }
            $equipoTypeTitleCol = null;
            if ($hasEquipoType) {
                if ($this->erpColumnExists('equipo_type', 'type_ant_title')) {
                    $equipoTypeTitleCol = 'type_ant_title';
                } elseif ($this->erpColumnExists('equipo_type', 'title')) {
                    $equipoTypeTitleCol = 'title';
                }
            }

            $whereParts = [];
            $params = [];
            foreach ($patterns as $idx => $p) {
                if ($equipoNameCol !== null) {
                    $ph = ':p' . $idx . '_e';
                    $whereParts[] = 'UPPER(e.' . $equipoNameCol . ') LIKE ' . $ph;
                    $params[$ph] = $p;
                }
                if ($equipoTypeTitleCol !== null) {
                    $ph = ':p' . $idx . '_t';
                    $whereParts[] = 'UPPER(et.' . $equipoTypeTitleCol . ') LIKE ' . $ph;
                    $params[$ph] = $p;
                }
            }
            $where = $whereParts !== [] ? ('(' . implode(' OR ', $whereParts) . ')') : '1=0';

            $sql = 'SELECT e.id FROM equipo e';
            if ($hasEquipoType) {
                $sql .= ' LEFT JOIN equipo_type et ON et.id = e.equipo_type_id';
            }
            $sql .= ' WHERE ' . $where;
            if ($bonusCode === 'bonoseri') {
                $excludeParts = [];
                if ($equipoNameCol !== null) {
                    $excludeParts[] = 'UPPER(e.' . $equipoNameCol . ') LIKE :exclude_pulpo_e';
                    $params[':exclude_pulpo_e'] = '%PULPO%';
                }
                if ($equipoTypeTitleCol !== null) {
                    $excludeParts[] = 'UPPER(et.' . $equipoTypeTitleCol . ') LIKE :exclude_pulpo_t';
                    $params[':exclude_pulpo_t'] = '%PULPO%';
                }
                if ($excludeParts !== []) {
                    $sql .= ' AND NOT (' . implode(' OR ', $excludeParts) . ')';
                }
            }
            $sql .= ' ORDER BY e.id';

            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $id = (int)($r['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } catch (Throwable) {
            $ids = [];
        }

        $ids = array_values(array_unique(array_values(array_filter($ids, static fn ($v) => (int)$v > 0))));
        sort($ids);
        $this->erpEquipoIdsByBonusCache[$bonusCode] = $ids !== [] ? $ids : null;
        return $this->erpEquipoIdsByBonusCache[$bonusCode];
    }

    private function resolveErpEquipotypeIdsForBonus(string $bonusCode): ?array
    {
        $bonusCode = strtolower(trim($bonusCode));
        if ($bonusCode === '') {
            return null;
        }
        if (array_key_exists($bonusCode, $this->erpEquipotypeIdsByBonusCache)) {
            return $this->erpEquipotypeIdsByBonusCache[$bonusCode];
        }

        $ids = [];
        try {
            if (!$this->erpTableExists('equipo_type')) {
                $this->erpEquipotypeIdsByBonusCache[$bonusCode] = null;
                return null;
            }

            $titleCol = null;
            if ($this->erpColumnExists('equipo_type', 'type_ant_title')) {
                $titleCol = 'type_ant_title';
            } elseif ($this->erpColumnExists('equipo_type', 'title')) {
                $titleCol = 'title';
            }

            $hasFlexoAct = $this->erpColumnExists('equipo_type', 'type_ant_flexo_act');
            $hasSeriAct = $this->erpColumnExists('equipo_type', 'type_ant_seri_act');

            $whereParts = [];
            $params = [];
            if ($bonusCode === 'bonoflexo') {
                if ($titleCol !== null) {
                    $whereParts[] = 'UPPER(' . $titleCol . ') LIKE :title_like_1';
                    $whereParts[] = 'UPPER(' . $titleCol . ') LIKE :title_like_2';
                    $params[':title_like_1'] = '%FLEXO%';
                    $params[':title_like_2'] = '%FLEXOG%';
                } elseif ($hasFlexoAct) {
                    $whereParts[] = 'type_ant_flexo_act = 1';
                }
            } elseif ($bonusCode === 'bonoseri') {
                if ($titleCol !== null) {
                    $whereParts[] = 'UPPER(' . $titleCol . ') LIKE :title_like_1';
                    $whereParts[] = 'UPPER(' . $titleCol . ') LIKE :title_like_2';
                    $params[':title_like_1'] = '%SERIG%';
                    $params[':title_like_2'] = '%SERI%';
                } elseif ($hasSeriAct) {
                    $whereParts[] = 'type_ant_seri_act = 1';
                }
            } elseif ($bonusCode === 'bonocys') {
                if ($titleCol !== null) {
                    $whereParts[] = 'UPPER(' . $titleCol . ') LIKE :title_like_1';
                    $params[':title_like_1'] = '%SELLAD%';
                }
            } else {
                $this->erpEquipotypeIdsByBonusCache[$bonusCode] = null;
                return null;
            }

            if ($whereParts === []) {
                $this->erpEquipotypeIdsByBonusCache[$bonusCode] = null;
                return null;
            }

            $sql = 'SELECT id FROM equipo_type WHERE (' . implode(' OR ', $whereParts) . ')';
            if ($bonusCode === 'bonoseri' && $titleCol !== null) {
                $sql .= ' AND UPPER(' . $titleCol . ') NOT LIKE :exclude_pulpo_1';
                $params[':exclude_pulpo_1'] = '%PULPO%';
            }
            $sql .= ' ORDER BY id';
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $id = (int)($r['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } catch (Throwable) {
            $ids = [];
        }

        $ids = array_values(array_unique(array_values(array_filter($ids, static fn ($v) => (int)$v > 0))));
        sort($ids);
        $this->erpEquipotypeIdsByBonusCache[$bonusCode] = $ids !== [] ? $ids : null;
        return $this->erpEquipotypeIdsByBonusCache[$bonusCode];
    }

    public function listErpBonusOperatorNamesForPeriod(string|array $monthKey, ?array $equipotypeIds, ?array $equipoIds = null): array
    {
        $period = $this->getBonusPeriodByMonthFinal($monthKey);
        $startTs = (int)($period['start_ts'] ?? 0);
        $endTs = (int)($period['end_ts'] ?? 0);
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return ['ok' => false, 'errors' => ['Período inválido.'], 'period' => $period, 'names' => []];
        }

        $equipotypeIds = is_array($equipotypeIds) ? array_values(array_filter(array_map('intval', $equipotypeIds), static fn ($v) => $v > 0)) : null;
        $equipotypeIds = $equipotypeIds !== null ? array_values(array_unique($equipotypeIds)) : null;
        $equipotypeFilterSql = '';
        $equipotypeParams = [];
        if ($equipotypeIds !== null && $equipotypeIds !== []) {
            $equipotypePlaceholders = [];
            foreach ($equipotypeIds as $idx => $id) {
                $ph = ':equipotype_id_' . $idx;
                $equipotypePlaceholders[] = $ph;
                $equipotypeParams[$ph] = $id;
            }
            $equipotypeFilterSql = ' AND pa.ag_equipotype_id IN (' . implode(',', $equipotypePlaceholders) . ')';
        }

        $equipoIds = is_array($equipoIds) ? array_values(array_filter(array_map('intval', $equipoIds), static fn ($v) => $v > 0)) : null;
        $equipoIds = $equipoIds !== null ? array_values(array_unique($equipoIds)) : null;
        $equipoFilterSql = '';
        $equipoParams = [];
        if ($equipoIds !== null && $equipoIds !== []) {
            $equipoPlaceholders = [];
            foreach ($equipoIds as $idx => $id) {
                $ph = ':equipo_id_' . $idx;
                $equipoPlaceholders[] = $ph;
                $equipoParams[$ph] = $id;
            }
            $equipoFilterSql = ' AND pa.ag_equipo_id IN (' . implode(',', $equipoPlaceholders) . ')';
        }

        #region debug-point bonus-query-empty-operators
        $debugEnvPath = __DIR__ . '/../.dbg/bonus-query-empty.env';
        $debugUrl = '';
        if (is_file($debugEnvPath)) {
            $envRaw = (string)@file_get_contents($debugEnvPath);
            if ($envRaw !== '') {
                foreach (preg_split('/\r?\n/', $envRaw) ?: [] as $line) {
                    $line = trim((string)$line);
                    if ($line === '' || !str_contains($line, '=')) {
                        continue;
                    }
                    [$k, $v] = array_map('trim', explode('=', $line, 2));
                    if ($k === 'DEBUG_SERVER_URL') {
                        $debugUrl = $v;
                        break;
                    }
                }
            }
        }
        $dbgPost = static function (string $url, array $payload): void {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if (!is_string($body)) {
                return;
            }
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $body,
                    'timeout' => 2,
                ],
            ]);
            @file_get_contents($url, false, $ctx);
        };
        if ($debugUrl !== '') {
            $dbName = '';
            try {
                $dbName = (string)($this->erpPdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
            } catch (Throwable) {
                $dbName = '';
            }
            $dbgPost($debugUrl, [
                'ts' => date('c'),
                'sessionId' => 'bonus-query-empty',
                'runId' => 'pre',
                'event' => 'bonus_operators_query_start',
                'monthKey' => $monthKey,
                'db' => $dbName,
                'startTs' => $startTs,
                'endTs' => $endTs,
                'equipotypeIds' => $equipotypeIds,
            ]);
        }
        #endregion debug-point bonus-query-empty-operators

        $sql = <<<SQL
SELECT DISTINCT
    TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))) AS operator_name
FROM prod_worker_ot_events e
INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
LEFT JOIN workers w ON w.id = pwi.win_wrkid
WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
{$equipotypeFilterSql}
{$equipoFilterSql}
  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
ORDER BY operator_name ASC
SQL;

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute(array_merge([
                ':start_ts' => $startTs,
                ':end_ts' => $endTs,
            ], $equipotypeParams, $equipoParams));
            $names = [];
            foreach ($stmt->fetchAll() as $r) {
                $name = is_array($r) ? trim((string)($r['operator_name'] ?? '')) : '';
                if ($name !== '') {
                    $names[] = $name;
                }
            }
            $names = array_values(array_unique($names));
            sort($names, SORT_NATURAL | SORT_FLAG_CASE);
            #region debug-point bonus-query-empty-operators
            if ($debugUrl !== '') {
                $dbgPost($debugUrl, [
                    'ts' => date('c'),
                    'sessionId' => 'bonus-query-empty',
                    'runId' => 'pre',
                    'event' => 'bonus_operators_query_done',
                    'monthKey' => $monthKey,
                    'startTs' => $startTs,
                    'endTs' => $endTs,
                    'equipotypeIds' => $equipotypeIds,
                    'namesCount' => count($names),
                    'namesSample' => array_slice($names, 0, 12),
                ]);
            }
            #endregion debug-point bonus-query-empty-operators

            return ['ok' => true, 'errors' => [], 'period' => $period, 'names' => $names];
        } catch (PDOException $e) {
            $sqlState = (string)($e->errorInfo[0] ?? $e->getCode() ?? '');
            if ($sqlState === '42S02' || str_contains($e->getMessage(), 'Base table or view not found')) {
                #region debug-point bonus-query-empty-operators
                if ($debugUrl !== '') {
                    $dbgPost($debugUrl, [
                        'ts' => date('c'),
                        'sessionId' => 'bonus-query-empty',
                        'runId' => 'pre',
                        'event' => 'bonus_operators_query_error',
                        'monthKey' => $monthKey,
                        'sqlState' => $sqlState,
                        'error' => $e->getMessage(),
                    ]);
                }
                #endregion debug-point bonus-query-empty-operators
                return ['ok' => false, 'errors' => ['No se encuentran las tablas de producción del ERP (prod_*).'], 'period' => $period, 'names' => []];
            }
            #region debug-point bonus-query-empty-operators
            if ($debugUrl !== '') {
                $dbgPost($debugUrl, [
                    'ts' => date('c'),
                    'sessionId' => 'bonus-query-empty',
                    'runId' => 'pre',
                    'event' => 'bonus_operators_query_error',
                    'monthKey' => $monthKey,
                    'sqlState' => $sqlState,
                    'error' => $e->getMessage(),
                ]);
            }
            #endregion debug-point bonus-query-empty-operators
            return ['ok' => false, 'errors' => ['No se pudo consultar operadores del ERP.'], 'period' => $period, 'names' => []];
        }
    }

    /**
     * Implementación genérica de consulta ERP para producción en el período 26–25.
     *
     * @param int[]|null $equipotypeIds Si viene, filtra por prod_agenda.ag_equipotype_id
     * @return array{ok:bool, errors:string[], period:array{month_key:string,start_date:string,end_date:string,start_ts:int,end_ts:int}, rows:list<array<string,mixed>>}
     */
    private function listErpProductionForBonusPeriod(string|array $monthKey, ?string $operatorName, ?string $costCenter, ?array $equipotypeIds, ?array $equipoIds, ?int $limit, bool $filterEquipoOnWorker = false, ?array $excludeWorkerEquipoNameLike = null): array
    {
        $period = $this->getBonusPeriodByMonthFinal($monthKey);
        $startTs = (int)($period['start_ts'] ?? 0);
        $endTs = (int)($period['end_ts'] ?? 0);
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return ['ok' => false, 'errors' => ['Período inválido.'], 'period' => $period, 'rows' => []];
        }

        $operatorName = $operatorName !== null ? trim($operatorName) : '';
        $costCenter = $costCenter !== null ? trim($costCenter) : '';
        $equipotypeIds = is_array($equipotypeIds) ? array_values(array_filter(array_map('intval', $equipotypeIds), static fn ($v) => $v > 0)) : null;
        $equipotypeIds = $equipotypeIds !== null ? array_values(array_unique($equipotypeIds)) : null;
        $equipoIds = is_array($equipoIds) ? array_values(array_filter(array_map('intval', $equipoIds), static fn ($v) => $v > 0)) : null;
        $equipoIds = $equipoIds !== null ? array_values(array_unique($equipoIds)) : null;
        $limit = $limit !== null ? max(1, min(20000, (int)$limit)) : null;
        $limitSql = $limit !== null ? (' LIMIT ' . $limit) : '';

        $equipotypeFilterSql = '';
        $equipotypeParams = [];
        if ($equipotypeIds !== null && $equipotypeIds !== []) {
            $equipotypePlaceholders = [];
            foreach ($equipotypeIds as $idx => $id) {
                $ph = ':equipotype_id_' . $idx;
                $equipotypePlaceholders[] = $ph;
                $equipotypeParams[$ph] = $id;
            }
            $equipotypeFilterSql = $filterEquipoOnWorker
                ? (' AND eq.equipo_type_id IN (' . implode(',', $equipotypePlaceholders) . ')')
                : (' AND pa.ag_equipotype_id IN (' . implode(',', $equipotypePlaceholders) . ')');
        }

        $equipoFilterSql = '';
        $equipoParams = [];
        if ($equipoIds !== null && $equipoIds !== []) {
            $equipoPlaceholders = [];
            foreach ($equipoIds as $idx => $id) {
                $ph = ':equipo_id_' . $idx;
                $equipoPlaceholders[] = $ph;
                $equipoParams[$ph] = $id;
            }
            $equipoFilterSql = $filterEquipoOnWorker
                ? (' AND pwi.win_equipoid IN (' . implode(',', $equipoPlaceholders) . ')')
                : (' AND pa.ag_equipo_id IN (' . implode(',', $equipoPlaceholders) . ')');
        }

        $agendaEquipoJoinSql = '';
        $agendaEquipoExcludeSql = '';
        $agendaEquipoExcludeParams = [];
        $excludeWorkerEquipoNameLike = is_array($excludeWorkerEquipoNameLike)
            ? array_values(array_filter(array_map('strval', $excludeWorkerEquipoNameLike), static fn ($v) => trim($v) !== ''))
            : null;
        if ($excludeWorkerEquipoNameLike !== null && $excludeWorkerEquipoNameLike !== [] && $this->erpTableExists('equipo')) {
            $agendaEquipoNameCol = null;
            if ($this->erpColumnExists('equipo', 'equipo_name')) {
                $agendaEquipoNameCol = 'equipo_name';
            } elseif ($this->erpColumnExists('equipo', 'name')) {
                $agendaEquipoNameCol = 'name';
            }
            if ($agendaEquipoNameCol !== null) {
                $agendaEquipoJoinSql = 'LEFT JOIN equipo ag_eq ON ag_eq.id = pwi.win_equipoid';
                $excludeParts = [];
                foreach ($excludeWorkerEquipoNameLike as $idx => $pat) {
                    $ph = ':agenda_equipo_excl_' . $idx;
                    $excludeParts[] = 'UPPER(ag_eq.' . $agendaEquipoNameCol . ') LIKE ' . $ph;
                    $agendaEquipoExcludeParams[$ph] = strtoupper($pat);
                }
                if ($excludeParts !== []) {
                    $agendaEquipoExcludeSql = ' AND NOT (' . implode(' OR ', $excludeParts) . ')';
                }
            }
        }

        $defectJoinSql = '';
        $defectSelectSqlLegacy = '0 AS declared_waste_units, 0 AS declared_waste_kg, 0 AS waste_print_units, 0 AS waste_print_kg';
        $defectSelectSqlExtended = '0 AS declared_waste_units, 0 AS declared_waste_kg, 0 AS waste_print_units, 0 AS waste_print_kg';
        if ($this->erpTableExists('prod_worker_ot_defectunits')) {
            $mTypeJoin = $this->erpTableExists('prod_mermatypes') ? 'LEFT JOIN prod_mermatypes mt ON mt.id = d.evt_merma_typeid' : '';
            $defectSelectSqlLegacy = 'SUM(COALESCE(dw.waste_units, 0)) AS declared_waste_units, SUM(COALESCE(dw.waste_kg, 0)) AS declared_waste_kg, SUM(COALESCE(dw.waste_print_units, 0)) AS waste_print_units, SUM(COALESCE(dw.waste_print_kg, 0)) AS waste_print_kg';
            $defectSelectSqlExtended = 'SUM(COALESCE(dw.waste_units, 0)) AS declared_waste_units, SUM(COALESCE(dw.waste_kg, 0)) AS declared_waste_kg, SUM(COALESCE(dw.waste_print_units, 0)) AS waste_print_units, SUM(COALESCE(dw.waste_print_kg, 0)) AS waste_print_kg';
            $defectJoinSql = <<<SQL
LEFT JOIN (
    SELECT 
        e2.evt_prod_worker_otid AS wok_id,
        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' THEN d.evt_kgstounits ELSE 0 END) AS waste_kg,
        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' THEN d.evt_amount ELSE 0 END) AS waste_units,
        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND (d.evt_merma_typeid = 7 OR LOWER(COALESCE(mt.merma_title, '')) LIKE '%impresi%') THEN d.evt_kgstounits ELSE 0 END) AS waste_print_kg,
        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND (d.evt_merma_typeid = 7 OR LOWER(COALESCE(mt.merma_title, '')) LIKE '%impresi%') THEN d.evt_amount ELSE 0 END) AS waste_print_units
    FROM prod_worker_ot_defectunits d
    INNER JOIN prod_worker_ot_events e2 ON d.evt_refid = e2.id
    {$mTypeJoin}
    WHERE d.evt_status > 0
    GROUP BY e2.evt_prod_worker_otid
) dw ON dw.wok_id = pwo.id
SQL;
        }

        $bagTypeSelectSqlLegacy = "'' AS bag_type";
        $bagTypeSelectSqlExtended = "'' AS bag_type";
        $bagTypeJoinSql = '';
        if (
            $this->erpTableExists('tran_comments_item_vals')
            && $this->erpTableExists('tran_comments')
            && $this->erpTableExists('tran_comments_vals')
            && $this->erpColumnExists('tran_comments_item_vals', 'item_id')
            && $this->erpColumnExists('tran_comments_item_vals', 'com_id')
            && $this->erpColumnExists('tran_comments_item_vals', 'val_id')
            && $this->erpColumnExists('tran_comments', 'id')
            && $this->erpColumnExists('tran_comments_vals', 'id')
            && $this->erpColumnExists('tran_comments_vals', 'add_name')
        ) {
            $bagTypeSelectSqlExtended = 'MAX(tb.bag_type) AS bag_type';
            $bagTypeJoinSql = <<<SQL
LEFT JOIN (
    SELECT
        tciv.item_id,
        MAX(TRIM(tcv.add_name)) AS bag_type
    FROM tran_comments_item_vals tciv
    INNER JOIN tran_comments tc ON tc.id = tciv.com_id AND tc.id = 20
    INNER JOIN tran_comments_vals tcv ON tcv.id = tciv.val_id
    GROUP BY tciv.item_id
) tb ON tb.item_id = it.id
SQL;
        }

        #region debug-point bonus-query-empty-main
        $debugEnvPath = __DIR__ . '/../.dbg/bonus-query-empty.env';
        $debugUrl = '';
        if (is_file($debugEnvPath)) {
            $envRaw = (string)@file_get_contents($debugEnvPath);
            if ($envRaw !== '') {
                foreach (preg_split('/\r?\n/', $envRaw) ?: [] as $line) {
                    $line = trim((string)$line);
                    if ($line === '' || !str_contains($line, '=')) {
                        continue;
                    }
                    [$k, $v] = array_map('trim', explode('=', $line, 2));
                    if ($k === 'DEBUG_SERVER_URL') {
                        $debugUrl = $v;
                        break;
                    }
                }
            }
        }
        $dbgPost = static function (string $url, array $payload): void {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if (!is_string($body)) {
                return;
            }
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $body,
                    'timeout' => 2,
                ],
            ]);
            @file_get_contents($url, false, $ctx);
        };
        if ($debugUrl !== '') {
            $dbName = '';
            try {
                $dbName = (string)($this->erpPdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
            } catch (Throwable) {
                $dbName = '';
            }
            $dbgPost($debugUrl, [
                'ts' => date('c'),
                'sessionId' => 'bonus-query-empty',
                'runId' => 'pre',
                'event' => 'bonus_rows_query_start',
                'monthKey' => $monthKey,
                'db' => $dbName,
                'startTs' => $startTs,
                'endTs' => $endTs,
                'equipotypeIds' => $equipotypeIds,
                'operatorName' => $operatorName,
                'costCenter' => $costCenter,
                'limit' => $limit,
            ]);
        }
        #endregion debug-point bonus-query-empty-main

        $sqlLegacy = <<<SQL
SELECT
    pa.ag_equipo_id AS printer_no,
    ph.prd_reqid AS cost_center,
    ph.prd_number AS work_order_number,
    e.event_date AS event_date,
    ph.prd_desc AS erp_desc,
    '' AS item_title,
    {$bagTypeSelectSqlLegacy},
    TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))) AS operator_name,
    MAX(pa.ag_amount) AS requested_units,
    MAX(pa.ag_amount) AS requested_units_pa,
    0 AS requested_units_item,
    SUM(e.produced_units) AS produced_units,
    SUM(e.produced_linear_meters) AS produced_linear_meters,
    SUM(e.produced_machine_meters) AS produced_machine_meters,
    0 AS item_prodcalc_fuelle_act,
    MAX(COALESCE(pwi.win_equipoid, pa.ag_equipo_id, 0)) AS win_equipoid,
    {$defectSelectSqlLegacy}
FROM (
    SELECT
        x.evt_prod_worker_otid,
        x.event_date,
        SUM(x.sum_units) AS produced_units,
        SUM(x.sum_linear) AS produced_linear_meters,
        SUM(x.sum_machine) AS produced_machine_meters
    FROM (
        SELECT
            evt_prod_worker_otid,
            DATE(FROM_UNIXTIME(evt_crtdat)) AS event_date,
            LOWER(evt_type) AS evt_type,
            SUM(evt_amount) AS sum_units,
            SUM(evt_amount_metros_lineales) AS sum_linear,
            SUM(evt_amount_metros_maquina) AS sum_machine
        FROM prod_worker_ot_events
        WHERE evt_crtdat BETWEEN :start_ts AND :end_ts
          AND LOWER(evt_type) IN ('production','prod','prodsericolor')
          AND (evt_status IS NULL OR evt_status > 0)
        GROUP BY evt_prod_worker_otid, DATE(FROM_UNIXTIME(evt_crtdat)), LOWER(evt_type)
    ) x
    GROUP BY x.evt_prod_worker_otid, x.event_date
) e
INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
{$agendaEquipoJoinSql}
LEFT JOIN workers w ON w.id = pwi.win_wrkid
{$defectJoinSql}
WHERE 1=1
{$equipotypeFilterSql}
{$equipoFilterSql}
{$agendaEquipoExcludeSql}
  AND (:operator_name = '' OR TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))) = :operator_name_exact)
  AND (:cost_center = '' OR ph.prd_reqid = :cost_center_exact)
GROUP BY
    pa.ag_equipo_id,
    ph.prd_reqid,
    ph.prd_number,
    e.event_date,
    TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))),
    ph.prd_desc
ORDER BY event_date ASC, printer_no ASC, cost_center ASC, work_order_number ASC{$limitSql}
SQL;

        $sqlExtended = <<<SQL
SELECT
    pa.ag_equipo_id AS printer_no,
    COALESCE(o.req_number, ph.prd_reqid) AS cost_center,
    ph.prd_number AS work_order_number,
    e.event_date AS event_date,
    ph.prd_desc AS erp_desc,
    TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))) AS operator_name,
    MAX(CASE WHEN pa.ag_amount IS NOT NULL AND pa.ag_amount > 0 THEN pa.ag_amount ELSE oi.item_amount END) AS requested_units,
    MAX(pa.ag_amount) AS requested_units_pa,
    MAX(oi.item_amount) AS requested_units_item,
    SUM(e.produced_units) AS produced_units,
    SUM(e.produced_linear_meters) AS produced_linear_meters,
    SUM(e.produced_machine_meters) AS produced_machine_meters,
    {$defectSelectSqlExtended},
    MAX(c.cust_name) AS client_label,
    MAX(UPPER(cat.cat_prefix)) AS product_type,
    MAX(it.item_title) AS item_title,
    {$bagTypeSelectSqlExtended},
    MAX(
        CASE
            WHEN oi.fab_med_width IS NULL OR oi.fab_med_height IS NULL THEN ''
            WHEN oi.fab_med_fuelle IS NULL OR oi.fab_med_fuelle = 0 THEN CONCAT(CAST(oi.fab_med_width AS UNSIGNED), 'X', CAST(oi.fab_med_height AS UNSIGNED))
            ELSE CONCAT(CAST(oi.fab_med_width AS UNSIGNED), 'X', CAST(oi.fab_med_height AS UNSIGNED), 'X', CAST(oi.fab_med_fuelle AS UNSIGNED))
        END
    ) AS measure_cm,
    MAX(oi.fab_mat_gramms)      AS grammage_g,
    MAX(oi.fab_med_width)       AS dim_width_cm,
    MAX(oi.fab_med_height)      AS dim_height_cm,
    MAX(oi.fab_med_fuelle)      AS dim_fuelle_cm,
    MAX(oi.fab_manilla_length)  AS dim_manilla_length,
    MAX(it.item_weight)         AS item_weight,
    MAX(it.item_prodcalc_fuelle_act) AS item_prodcalc_fuelle_act,
    MAX(COALESCE(pwi.win_equipoid, pa.ag_equipo_id, 0)) AS win_equipoid
FROM (
    SELECT
        x.evt_prod_worker_otid,
        x.event_date,
        SUM(x.sum_units) AS produced_units,
        SUM(x.sum_linear) AS produced_linear_meters,
        SUM(x.sum_machine) AS produced_machine_meters
    FROM (
        SELECT
            evt_prod_worker_otid,
            DATE(FROM_UNIXTIME(evt_crtdat)) AS event_date,
            LOWER(evt_type) AS evt_type,
            SUM(evt_amount) AS sum_units,
            SUM(evt_amount_metros_lineales) AS sum_linear,
            SUM(evt_amount_metros_maquina) AS sum_machine
        FROM prod_worker_ot_events
        WHERE evt_crtdat BETWEEN :start_ts AND :end_ts
          AND LOWER(evt_type) IN ('production','prod','prodsericolor')
          AND (evt_status IS NULL OR evt_status > 0)
        GROUP BY evt_prod_worker_otid, DATE(FROM_UNIXTIME(evt_crtdat)), LOWER(evt_type)
    ) x
    GROUP BY x.evt_prod_worker_otid, x.event_date
) e
INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
{$agendaEquipoJoinSql}
LEFT JOIN workers w ON w.id = pwi.win_wrkid
{$defectJoinSql}
LEFT JOIN equipo eq ON eq.id = pwi.win_equipoid
LEFT JOIN orders o ON o.id = pa.ag_reqid
LEFT JOIN customer c ON c.id = o.req_cust_id
LEFT JOIN (
    SELECT
        req_id,
        MAX(item_amount)        AS item_amount,
        MAX(fab_med_width)      AS fab_med_width,
        MAX(fab_med_height)     AS fab_med_height,
        MAX(fab_med_fuelle)     AS fab_med_fuelle,
        MAX(item_id)            AS item_id,
        MAX(fab_mat_gramms)     AS fab_mat_gramms,
        MAX(fab_manilla_length) AS fab_manilla_length
    FROM orders_items
    GROUP BY req_id
) oi ON oi.req_id = o.id
LEFT JOIN item it ON it.id = oi.item_id
{$bagTypeJoinSql}
LEFT JOIN (
    SELECT ip.item_id, MIN(pc.cat_prefix) AS cat_prefix
    FROM item_productcats ip
    INNER JOIN productcats pc ON pc.id = ip.cat_id
    GROUP BY ip.item_id
) cat ON cat.item_id = it.id
WHERE 1=1
{$equipotypeFilterSql}
{$equipoFilterSql}
{$agendaEquipoExcludeSql}
  AND (:operator_name = '' OR TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))) = :operator_name_exact)
  AND (:cost_center = '' OR COALESCE(o.req_number, ph.prd_reqid) = :cost_center_exact)
GROUP BY
    pa.ag_equipo_id,
    COALESCE(o.req_number, ph.prd_reqid),
    ph.prd_number,
    e.event_date,
    TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))),
    ph.prd_desc
ORDER BY event_date ASC, printer_no ASC, cost_center ASC, work_order_number ASC{$limitSql}
SQL;

        $queryMode = 'extended';
        $queryFallbackReason = '';
        try {
            $params = [
                ':start_ts' => $startTs,
                ':end_ts' => $endTs,
                ':operator_name' => $operatorName,
                ':operator_name_exact' => $operatorName,
                ':cost_center' => $costCenter,
                ':cost_center_exact' => $costCenter,
            ];
            $params = array_merge($params, $equipotypeParams, $equipoParams, $agendaEquipoExcludeParams);

            try {
                $stmt = $this->erpPdo->prepare($sqlExtended);
                $stmt->execute($params);
                $rawRows = $stmt->fetchAll();
            } catch (PDOException $eExtended) {
                $sqlState = (string)($eExtended->errorInfo[0] ?? $eExtended->getCode() ?? '');
                $message = $eExtended->getMessage();
                if ($sqlState === '42S02' || str_contains($message, 'Base table or view not found')) {
                    $queryMode = 'legacy';
                    $queryFallbackReason = $sqlState !== '' ? ('SQLSTATE ' . $sqlState) : 'missing table';
                    $stmt = $this->erpPdo->prepare($sqlLegacy);
                    $stmt->execute($params);
                    $rawRows = $stmt->fetchAll();
                } else {
                    throw $eExtended;
                }
            }
        } catch (PDOException $e) {
            $sqlState = (string)($e->errorInfo[0] ?? $e->getCode() ?? '');
            $message = $e->getMessage();
            #region debug-point bonus-query-empty-main
            if ($debugUrl !== '') {
                $dbgPost($debugUrl, [
                    'ts' => date('c'),
                    'sessionId' => 'bonus-query-empty',
                    'runId' => 'pre',
                    'event' => 'bonus_rows_query_error',
                    'monthKey' => $monthKey,
                    'sqlState' => $sqlState,
                    'error' => $message,
                    'queryMode' => $queryMode,
                    'fallbackReason' => $queryFallbackReason,
                ]);
            }
            #endregion debug-point bonus-query-empty-main
            if ($sqlState === '42S02' || str_contains($message, 'Base table or view not found')) {
                return ['ok' => false, 'errors' => ['No se encuentran las tablas de producción del ERP (prod_*).'], 'period' => $period, 'rows' => []];
            }
            if ($sqlState !== '') {
                return ['ok' => false, 'errors' => ['No se pudo consultar producción ERP (SQLSTATE ' . $sqlState . ').'], 'period' => $period, 'rows' => []];
            }
            return ['ok' => false, 'errors' => ['No se pudo consultar producción ERP.'], 'period' => $period, 'rows' => []];
        }

        $rows = [];
        foreach ($rawRows as $r) {
            $desc = trim((string)($r['erp_desc'] ?? ''));
            $rows[] = [
                'printer_no' => (string)($r['printer_no'] ?? ''),
                'cost_center' => (string)($r['cost_center'] ?? ''),
                'work_order_number' => (string)($r['work_order_number'] ?? ''),
                'event_date' => (string)($r['event_date'] ?? ''),
                'client_label' => trim((string)($r['client_label'] ?? '')) !== '' ? (string)$r['client_label'] : $this->parseClientLabelFromErpDesc($desc),
                'product_type' => trim((string)($r['product_type'] ?? '')) !== '' ? (string)$r['product_type'] : $this->parseProductTypeFromErpDesc($desc),
                'item_title' => trim((string)($r['item_title'] ?? '')),
                'bag_type' => trim((string)($r['bag_type'] ?? '')),
                'measure_cm' => trim((string)($r['measure_cm'] ?? '')) !== '' ? (string)$r['measure_cm'] : $this->parseMeasureCmFromErpDesc($desc),
                'helper_label' => '',
                'bonification_label' => '',
                'operator_name' => trim((string)($r['operator_name'] ?? '')),
                'requested_units' => (float)($r['requested_units'] ?? 0),
                'requested_units_pa' => (float)($r['requested_units_pa'] ?? 0),
                'requested_units_item' => (float)($r['requested_units_item'] ?? 0),
                'produced_units' => (float)($r['produced_units'] ?? 0),
                'produced_linear_meters' => (float)($r['produced_linear_meters'] ?? 0),
                'produced_machine_meters' => (float)($r['produced_machine_meters'] ?? 0),
                'declared_waste_units'  => (float)($r['declared_waste_units'] ?? 0),
                'declared_waste_kg'    => (float)($r['declared_waste_kg']    ?? 0),
                'waste_print_units'    => (float)($r['waste_print_units']    ?? 0),
                'waste_print_kg'       => (float)($r['waste_print_kg']       ?? 0),
                'grammage_g'           => (float)($r['grammage_g']           ?? 0),
                'dim_width_cm'         => (float)($r['dim_width_cm']         ?? 0),
                'dim_height_cm'        => (float)($r['dim_height_cm']        ?? 0),
                'dim_fuelle_cm'        => (float)($r['dim_fuelle_cm']        ?? 0),
                'dim_manilla_length'   => (float)($r['dim_manilla_length']   ?? 0),
                'item_weight'          => (float)($r['item_weight']          ?? 0),
                'item_prodcalc_fuelle_act' => (int)($r['item_prodcalc_fuelle_act'] ?? 0),
                'win_equipoid'         => (int)($r['win_equipoid']         ?? 0),
                'erp_desc' => $desc,
            ];
        }

        #region debug-point bonus-query-empty-main
        if ($debugUrl !== '') {
            $evtTypeCounts = [];
            try {
                $stmt = $this->erpPdo->prepare(
                    'SELECT evt_type, COUNT(*) AS cnt
                     FROM prod_worker_ot_events
                     WHERE evt_crtdat BETWEEN :start_ts AND :end_ts
                     GROUP BY evt_type
                     ORDER BY cnt DESC
                     LIMIT 10'
                );
                $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
                foreach ($stmt->fetchAll() as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $evtTypeCounts[(string)($r['evt_type'] ?? '')] = (int)($r['cnt'] ?? 0);
                }
            } catch (Throwable) {
                $evtTypeCounts = [];
            }

            $sample = [];
            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $sample[] = [
                    'event_date' => (string)($r['event_date'] ?? ''),
                    'cc' => (string)($r['cost_center'] ?? ''),
                    'ot' => (string)($r['work_order_number'] ?? ''),
                    'operator' => (string)($r['operator_name'] ?? ''),
                    'produced_units' => (float)($r['produced_units'] ?? 0.0),
                    'requested_units' => (float)($r['requested_units'] ?? 0.0),
                ];
                if (count($sample) >= 5) {
                    break;
                }
            }

            $dbgPost($debugUrl, [
                'ts' => date('c'),
                'sessionId' => 'bonus-query-empty',
                'runId' => 'pre',
                'event' => 'bonus_rows_query_done',
                'monthKey' => $monthKey,
                'startTs' => $startTs,
                'endTs' => $endTs,
                'equipotypeIds' => $equipotypeIds,
                'operatorName' => $operatorName,
                'limit' => $limit,
                'queryMode' => $queryMode,
                'fallbackReason' => $queryFallbackReason,
                'rawRowCount' => is_array($rawRows) ? count($rawRows) : null,
                'rowCount' => count($rows),
                'evtTypeCounts' => $evtTypeCounts,
                'sample' => $sample,
            ]);
        }
        #endregion debug-point bonus-query-empty-main

        #region debug-point flexo-bonus-empty-query
        $debugEnvPath = __DIR__ . '/../.dbg/flexo-bonus-empty.env';
        $debugUrl = '';
        if (is_file($debugEnvPath)) {
            $envRaw = (string)@file_get_contents($debugEnvPath);
            if ($envRaw !== '') {
                foreach (preg_split('/\r?\n/', $envRaw) ?: [] as $line) {
                    $line = trim((string)$line);
                    if ($line === '' || !str_contains($line, '=')) {
                        continue;
                    }
                    [$k, $v] = array_map('trim', explode('=', $line, 2));
                    if ($k === 'DEBUG_SERVER_URL') {
                        $debugUrl = $v;
                        break;
                    }
                }
            }
        }
        if ($debugUrl !== '') {
            $dbgPost = static function (string $url, array $payload): void {
                $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
                if (!is_string($body)) {
                    return;
                }
                $ctx = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\n",
                        'content' => $body,
                        'timeout' => 1,
                    ],
                ]);
                @file_get_contents($url, false, $ctx);
            };

            $sample = [];
            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $sample[] = [
                    'event_date' => (string)($r['event_date'] ?? ''),
                    'cc' => (string)($r['cost_center'] ?? ''),
                    'ot' => (string)($r['work_order_number'] ?? ''),
                    'operator' => (string)($r['operator_name'] ?? ''),
                    'produced_units' => (float)($r['produced_units'] ?? 0.0),
                    'requested_units' => (float)($r['requested_units'] ?? 0.0),
                ];
                if (count($sample) >= 5) {
                    break;
                }
            }

            $dbName = '';
            try {
                $dbName = (string)($this->erpPdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
            } catch (Throwable) {
                $dbName = '';
            }

            $dbgPost($debugUrl, [
                'sessionId' => 'flexo-bonus-empty',
                'hypothesisId' => 'probe',
                'runId' => trim((string)($_GET['debug_run'] ?? 'pre')) !== '' ? trim((string)($_GET['debug_run'] ?? 'pre')) : 'pre',
                'event' => 'erp_bonus_query_snapshot',
                'ts' => time(),
                'bonus' => 'flexo',
                'db' => $dbName,
                'monthKey' => $monthKey,
                'startTs' => $startTs,
                'endTs' => $endTs,
                'equipotypeIds' => $equipotypeIds ?? null,
                'operatorFilter' => $operatorName,
                'queryMode' => $queryMode,
                'fallbackReason' => $queryFallbackReason,
                'rowCount' => count($rows),
                'sample' => $sample,
            ]);
        }
        #endregion debug-point flexo-bonus-empty-query

        return ['ok' => true, 'errors' => [], 'period' => $period, 'rows' => $rows];
    }

    private function parseClientLabelFromErpDesc(string $desc): string
    {
        $desc = trim($desc);
        if ($desc === '') {
            return '';
        }
        $pos = mb_strpos($desc, '(');
        if ($pos !== false && $pos > 0) {
            return trim(mb_substr($desc, 0, $pos));
        }
        return $desc;
    }

    private function parseProductTypeFromErpDesc(string $desc): string
    {
        $desc = strtoupper($desc);
        if (preg_match('/\b(BOU|PRO|TRO|IND|SAC|VAR|OTRO)\b/', $desc, $m) === 1) {
            return (string)$m[1];
        }
        return '';
    }

    private function parseMeasureCmFromErpDesc(string $desc): string
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)(?:\s*[xX]\s*(\d+(?:[.,]\d+)?))?/', $desc, $m) !== 1) {
            return '';
        }
        $a = rtrim(rtrim(str_replace(',', '.', (string)$m[1]), '0'), '.');
        $b = rtrim(rtrim(str_replace(',', '.', (string)$m[2]), '0'), '.');
        $c = trim((string)($m[3] ?? ''));
        if ($c !== '') {
            $c = rtrim(rtrim(str_replace(',', '.', $c), '0'), '.');
            return strtoupper($a . 'X' . $b . 'X' . $c);
        }
        return strtoupper($a . 'X' . $b);
    }

    /**
     * Obtiene la tabla de tramos (Desde/Hasta/Monto) configurada para un bono.
     *
     * En Bonoflexo, estos tramos determinan el bono base según “Total UNID. IMPRESAS”.
     *
     * @return list<array{id:int,bonus_code:string,range_from:int,range_to:int|null,amount_clp:string}>
     */
    public function listBonusBrackets(string $bonusCode): array
    {
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, bonus_code, range_from, range_to, amount_clp
             FROM bonus_brackets
             WHERE bonus_code = :bonus_code
             ORDER BY range_from ASC, range_to ASC, id ASC'
        );
        $stmt->execute([':bonus_code' => $bonusCode]);
        $rows = $stmt->fetchAll();
        return $rows !== false ? $rows : [];
    }

    /**
     * Obtiene el factor “Compartido” por operador.
     *
     * - Solo se guardan factores distintos de 1.0.
     * - Si un operador no está en el mapa, se asume 1.0.
     *
     * @return array<string,float>
     */
    public function listBonusOperatorFactors(string $bonusCode, string $monthKey = ''): array
    {
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            return [];
        }
        $monthKey = trim($monthKey);
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $monthKey = '';
        }
        $stmt = $this->pdo->prepare(
            'SELECT operator_name, factor
             FROM bonus_operator_factors
             WHERE bonus_code = :bonus_code AND month_key = :month_key
             ORDER BY operator_name ASC'
        );
        $stmt->execute([':bonus_code' => $bonusCode, ':month_key' => $monthKey]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows !== false ? $rows : [] as $row) {
            $name = trim((string)($row['operator_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[$name] = (float)($row['factor'] ?? 1.0);
        }
        return $out;
    }

    /**
     * Reemplaza completamente los factores “Compartido” por operador para un bono.
     *
     * - Valida que factor ∈ {1.0, 0.9, 0.8}.
     * - Omite (no persiste) los operadores con 1.0 para mantener la tabla liviana.
     *
     * @param array<string,float|int|string> $factorsByOperator
     * @return array{ok:bool, errors?:string[]}
     */
    public function replaceBonusOperatorFactors(string $bonusCode, string $monthKey, array $factorsByOperator): array
    {
        $errors = [];
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            $errors[] = 'Bono inválido.';
        }
        $monthKey = trim($monthKey);
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $errors[] = 'Mes inválido.';
        }

        $normalized = [];
        foreach ($factorsByOperator as $operatorName => $factorRaw) {
            $operatorName = trim((string)$operatorName);
            if ($operatorName === '') {
                continue;
            }
            if (mb_strlen($operatorName) > 120) {
                $errors[] = 'Operador inválido.';
                break;
            }
            $factor = (float)$factorRaw;
            $allowed = [1.0, 0.9, 0.8];
            $ok = false;
            foreach ($allowed as $a) {
                if (abs($factor - $a) < 0.0001) {
                    $factor = $a;
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $errors[] = 'Factor inválido para ' . $operatorName . '.';
                break;
            }
            if (abs($factor - 1.0) < 0.0001) {
                continue;
            }
            $normalized[$operatorName] = $factor;
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM bonus_operator_factors WHERE bonus_code = :bonus_code AND month_key = :month_key');
            $del->execute([':bonus_code' => $bonusCode, ':month_key' => $monthKey]);

            if ($normalized !== []) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO bonus_operator_factors (bonus_code, month_key, operator_name, factor)
                     VALUES (:bonus_code, :month_key, :operator_name, :factor)'
                );
                foreach ($normalized as $operatorName => $factor) {
                    $ins->execute([
                        ':bonus_code' => $bonusCode,
                        ':month_key' => $monthKey,
                        ':operator_name' => $operatorName,
                        ':factor' => $factor,
                    ]);
                }
            }

            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'errors' => ['No se pudo guardar la configuración.']];
        }
    }

    public function getBonusCoachConfig(string $bonusCode, string $monthKey): array
    {
        $bonusCode = strtolower(trim($bonusCode));
        $monthKey = trim($monthKey);
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            return [];
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            return [];
        }

        $cfg = [];
        try {
            $stmt = $this->pdo->prepare(
                'SELECT coach_name, share_percent
                 FROM bonus_operator_coach_configs
                 WHERE bonus_code = :bonus_code AND month_key = :month_key
                 LIMIT 1'
            );
            $stmt->execute([':bonus_code' => $bonusCode, ':month_key' => $monthKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $cfg = [
                    'coach_name' => trim((string)($row['coach_name'] ?? '')),
                    'share_percent' => (float)($row['share_percent'] ?? 0.0),
                ];
            }
        } catch (Throwable) {
            $cfg = [];
        }

        $trainees = [];
        try {
            $stmt = $this->pdo->prepare(
                'SELECT trainee_name
                 FROM bonus_operator_coach_trainees
                 WHERE bonus_code = :bonus_code AND month_key = :month_key
                 ORDER BY trainee_name ASC'
            );
            $stmt->execute([':bonus_code' => $bonusCode, ':month_key' => $monthKey]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $v) {
                $name = trim((string)$v);
                if ($name !== '') {
                    $trainees[] = $name;
                }
            }
        } catch (Throwable) {
            $trainees = [];
        }

        $cfg['trainees'] = $trainees;
        return $cfg;
    }

    public function replaceBonusCoachConfig(string $bonusCode, string $monthKey, string $coachName, float $sharePercent, array $trainees): array
    {
        $errors = [];
        $bonusCode = strtolower(trim($bonusCode));
        $monthKey = trim($monthKey);
        $coachName = trim($coachName);

        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            $errors[] = 'Bono inválido.';
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $errors[] = 'Mes inválido.';
        }

        $allowed = [0.0, 0.5];
        $ok = false;
        foreach ($allowed as $a) {
            if (abs($sharePercent - $a) < 0.0001) {
                $sharePercent = $a;
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            $errors[] = 'Porcentaje inválido.';
        }

        $traineeNames = [];
        foreach ($trainees as $t) {
            $t = trim((string)$t);
            if ($t !== '') {
                $traineeNames[$t] = true;
            }
        }
        $traineeList = array_keys($traineeNames);
        sort($traineeList, SORT_NATURAL | SORT_FLAG_CASE);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            $delT = $this->pdo->prepare('DELETE FROM bonus_operator_coach_trainees WHERE bonus_code = :bonus_code AND month_key = :month_key');
            $delT->execute([':bonus_code' => $bonusCode, ':month_key' => $monthKey]);

            $delC = $this->pdo->prepare('DELETE FROM bonus_operator_coach_configs WHERE bonus_code = :bonus_code AND month_key = :month_key');
            $delC->execute([':bonus_code' => $bonusCode, ':month_key' => $monthKey]);

            if ($coachName !== '' && $sharePercent > 0 && $traineeList !== []) {
                $insC = $this->pdo->prepare(
                    'INSERT INTO bonus_operator_coach_configs (bonus_code, month_key, coach_name, share_percent)
                     VALUES (:bonus_code, :month_key, :coach_name, :share_percent)'
                );
                $insC->execute([
                    ':bonus_code' => $bonusCode,
                    ':month_key' => $monthKey,
                    ':coach_name' => $coachName,
                    ':share_percent' => $sharePercent,
                ]);

                $insT = $this->pdo->prepare(
                    'INSERT INTO bonus_operator_coach_trainees (bonus_code, month_key, trainee_name)
                     VALUES (:bonus_code, :month_key, :trainee_name)'
                );
                foreach ($traineeList as $t) {
                    $insT->execute([
                        ':bonus_code' => $bonusCode,
                        ':month_key' => $monthKey,
                        ':trainee_name' => $t,
                    ]);
                }
            }

            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable) {
            $this->pdo->rollBack();
            return ['ok' => false, 'errors' => ['No se pudo guardar la configuración.']];
        }
    }

    /**
     * Resuelve el monto del bono base (sin “Compartido”) buscando el tramo correspondiente.
     *
     * - $units: normalmente corresponde a “Total UNID. IMPRESAS” (evt_amount sumado).
     * - Se redondea a entero para calzar la tabla de tramos.
     * - Los tramos se asumen ordenados por range_from ascendente.
     */
    public function resolveBonusBracketAmount(array $brackets, float $units): float
    {
        $u = (int)round($units);
        foreach ($brackets as $b) {
            if (!is_array($b)) {
                continue;
            }
            $from = (int)($b['range_from'] ?? 0);
            $to = ($b['range_to'] ?? null) !== null ? (int)$b['range_to'] : null;
            if ($u < $from) {
                break;
            }
            if ($to !== null && $u > $to) {
                continue;
            }
            return (float)($b['amount_clp'] ?? 0);
        }
        return 0.0;
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function saveBonusBracket(string $bonusCode, ?int $id, int $rangeFrom, ?int $rangeTo, float $amountClp): array
    {
        $errors = [];
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            $errors[] = 'Bono inválido.';
        }
        if ($rangeFrom < 0) {
            $errors[] = 'Desde (unidades) debe ser mayor o igual a 0.';
        }
        if ($rangeTo !== null && $rangeTo < $rangeFrom) {
            $errors[] = 'Hasta (unidades) debe ser mayor o igual a Desde.';
        }
        if ($amountClp < 0) {
            $errors[] = 'El monto no puede ser negativo.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $id = $id !== null ? (int)$id : null;
        if ($id !== null && $id > 0) {
            $stmt = $this->pdo->prepare(
                'UPDATE bonus_brackets
                 SET range_from = :range_from,
                     range_to = :range_to,
                     amount_clp = :amount_clp
                 WHERE id = :id AND bonus_code = :bonus_code'
            );
            $stmt->execute([
                ':range_from' => $rangeFrom,
                ':range_to' => $rangeTo,
                ':amount_clp' => $amountClp,
                ':id' => $id,
                ':bonus_code' => $bonusCode,
            ]);
            return ['ok' => true, 'id' => $id, 'errors' => []];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO bonus_brackets (bonus_code, range_from, range_to, amount_clp)
             VALUES (:bonus_code, :range_from, :range_to, :amount_clp)'
        );
        $stmt->execute([
            ':bonus_code' => $bonusCode,
            ':range_from' => $rangeFrom,
            ':range_to' => $rangeTo,
            ':amount_clp' => $amountClp,
        ]);
        $newId = (int)$this->pdo->lastInsertId();
        return ['ok' => true, 'id' => $newId, 'errors' => []];
    }

    public function deleteBonusBracket(string $bonusCode, int $id): array
    {
        $errors = [];
        $bonusCode = strtolower(trim($bonusCode));
        $id = (int)$id;
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            $errors[] = 'Bono inválido.';
        }
        if ($id <= 0) {
            $errors[] = 'Registro inválido.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        $stmt = $this->pdo->prepare('DELETE FROM bonus_brackets WHERE id = :id AND bonus_code = :bonus_code');
        $stmt->execute([':id' => $id, ':bonus_code' => $bonusCode]);
        return ['ok' => true];
    }

    /**
     * Reemplaza completamente la tabla de tramos de un bono.
     *
     * En Bonoflexo se usa como acción administrativa para cargar una tabla base
     * (o para dejar configurado el set de tramos completo por defecto).
     *
     * @param list<array{range_from:int,range_to:int|null,amount_clp:float}> $brackets
     * @return array{ok:bool, errors?:string[]}
     */
    public function replaceBonusBrackets(string $bonusCode, array $brackets): array
    {
        $errors = [];
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            $errors[] = 'Bono inválido.';
        }
        foreach ($brackets as $idx => $b) {
            $from = (int)($b['range_from'] ?? -1);
            $to = ($b['range_to'] ?? null) !== null ? (int)$b['range_to'] : null;
            $amount = (float)($b['amount_clp'] ?? -1);
            if ($from < 0) {
                $errors[] = 'Tramo inválido (Desde) en fila ' . ($idx + 1) . '.';
                break;
            }
            if ($to !== null && $to < $from) {
                $errors[] = 'Tramo inválido (Hasta) en fila ' . ($idx + 1) . '.';
                break;
            }
            if ($amount < 0) {
                $errors[] = 'Monto inválido en fila ' . ($idx + 1) . '.';
                break;
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM bonus_brackets WHERE bonus_code = :bonus_code');
            $del->execute([':bonus_code' => $bonusCode]);

            $ins = $this->pdo->prepare(
                'INSERT INTO bonus_brackets (bonus_code, range_from, range_to, amount_clp)
                 VALUES (:bonus_code, :range_from, :range_to, :amount_clp)'
            );
            foreach ($brackets as $b) {
                $ins->execute([
                    ':bonus_code' => $bonusCode,
                    ':range_from' => (int)$b['range_from'],
                    ':range_to' => ($b['range_to'] ?? null) !== null ? (int)$b['range_to'] : null,
                    ':amount_clp' => (float)$b['amount_clp'],
                ]);
            }

            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'errors' => ['No se pudo guardar la configuración.']];
        }
    }

    /**
     * @return array<string, array<string, float>>
     */
    public function getBonusUnitRates(string $bonusCode): array
    {
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT category_code, tier_code, rate_clp
             FROM bonus_unit_rates
             WHERE bonus_code = :bonus_code
             ORDER BY category_code ASC, tier_code ASC'
        );
        $stmt->execute([':bonus_code' => $bonusCode]);
        $rows = $stmt->fetchAll();
        if ($rows === false || $rows === []) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $cat = strtolower(trim((string)($r['category_code'] ?? '')));
            $tier = strtoupper(trim((string)($r['tier_code'] ?? '')));
            if ($cat === '' || $tier === '') {
                continue;
            }
            if (!isset($out[$cat])) {
                $out[$cat] = [];
            }
            $out[$cat][$tier] = (float)($r['rate_clp'] ?? 0.0);
        }
        return $out;
    }

    /**
     * @param array<string, array<string, float>> $rates
     * @return array{ok:bool, errors?:string[]}
     */
    public function replaceBonusUnitRates(string $bonusCode, array $rates): array
    {
        $errors = [];
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            $errors[] = 'Bono inválido.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $allowedTiers = ['LT_3000', 'GTE_3000', 'BASE', 'AMOUNT'];
        foreach ($rates as $category => $tiers) {
            $category = strtolower(trim((string)$category));
            if ($category === '') {
                $errors[] = 'Categoría inválida.';
                break;
            }
            foreach ($tiers as $tierCode => $rate) {
                $tierCode = strtoupper(trim((string)$tierCode));
                if (!in_array($tierCode, $allowedTiers, true)) {
                    $errors[] = 'Tier inválido.';
                    break 2;
                }
                $rate = (float)$rate;
                if ($rate < 0) {
                    $errors[] = 'La tarifa no puede ser negativa.';
                    break 2;
                }
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM bonus_unit_rates WHERE bonus_code = :bonus_code');
            $del->execute([':bonus_code' => $bonusCode]);

            $ins = $this->pdo->prepare(
                'INSERT INTO bonus_unit_rates (bonus_code, category_code, tier_code, rate_clp)
                 VALUES (:bonus_code, :category_code, :tier_code, :rate_clp)'
            );
            foreach ($rates as $category => $tiers) {
                $category = strtolower(trim((string)$category));
                foreach ($tiers as $tierCode => $rate) {
                    $tierCode = strtoupper(trim((string)$tierCode));
                    $ins->execute([
                        ':bonus_code' => $bonusCode,
                        ':category_code' => $category,
                        ':tier_code' => $tierCode,
                        ':rate_clp' => (float)$rate,
                    ]);
                }
            }

            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'errors' => ['No se pudo guardar la configuración.']];
        }
    }


}

#[AllowDynamicProperties]
class BonusCalculationService
{
    use BonusCalculationServiceTrait;

    private ?ReceptionService $bridge = null;

    public function __construct(private PDO $pdo, private PDO $erpPdo, ?ReceptionService $bridge = null)
    {
        $this->bridge = $bridge;
    }

    public function __call(string $name, array $arguments): mixed
    {
        $this->bridge ??= new ReceptionService($this->pdo, $this->erpPdo);
        return $this->bridge->$name(...$arguments);
    }
}
