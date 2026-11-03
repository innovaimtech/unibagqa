<?php

declare(strict_types=1);

class MonthlyPresentationService
{
    private ReceptionService $receptionService;
    private PDO $erpPdo;

    public function __construct(ReceptionService $receptionService, PDO $erpPdo)
    {
        $this->receptionService = $receptionService;
        $this->erpPdo = $erpPdo;
    }

    /** Ruta del historial mensual de KPIs (cifras ya publicadas + meses calculados). */
    private function historyPath(): string
    {
        return __DIR__ . '/../data/kpi_historial_2026.json';
    }

    /**
     * Lee el historial mensual. Estructura: ['meses' => ['2026-01' => [...], ...]].
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadHistory(): array
    {
        $path = $this->historyPath();
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded['meses'] ?? null) ? $decoded['meses'] : [];
    }

    /** Guarda el historial conservando el comentario y el orden cronológico. */
    private function saveHistory(array $months): void
    {
        ksort($months);
        $path = $this->historyPath();
        $current = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
        $payload = ['_comentario' => (string)($current['_comentario'] ?? ''), 'meses' => $months];
        @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Calcula con datos EN VIVO del ERP los indicadores de un ciclo (26 del mes anterior al 25 del mes).
     * Se usa el mismo ciclo para producción, nivel de servicio y merma: es el que reproduce exactamente
     * las cifras de la plantilla (ej. sep-26: 883.422 unid., 202/162 CC, 0,68 % de merma).
     *
     * @return array<string, mixed>
     */
    private function computeLiveCycle(string $monthKey): array
    {
        $period = $this->receptionService->resolveBonusFilterPeriod('period', $monthKey);
        $startDate = (string)$period['start_date'] . ' 00:00:00';
        $endDate = (string)$period['end_date'] . ' 23:59:59';

        // 1. Producción real: lo que pasó por CORTE Y SELLADO.
        $cysRes = $this->receptionService->listErpCysProductionForBonusPeriod($period);
        $prodUnits = 0.0;
        foreach ((array)($cysRes['rows'] ?? []) as $r) {
            $prodUnits += (float)($r['produced_units'] ?? 0);
        }

        // 2. Nivel de servicio. Cada fila trae 'is_on_time' y 'dispatched_units' (no existe 'is_delayed':
        //    antes se buscaba ese campo, nunca se encontraba y todo mes mostraba 708.572 atrasadas).
        $slReport = $this->receptionService->getServiceLevelReport($startDate, $endDate, null, null, 'all', '');
        $slSummary = (array)($slReport['summary'] ?? []);
        $slTotalUnits = 0.0;
        $slDelayedUnits = 0.0;
        $lateRows = [];
        foreach ((array)($slReport['rows'] ?? []) as $r) {
            $u = (float)($r['dispatched_units'] ?? 0);
            $slTotalUnits += $u;
            if (empty($r['is_on_time'])) {
                $slDelayedUnits += $u;
                $lateRows[] = $r;
            }
        }
        usort($lateRows, static function (array $a, array $b): int {
            return [(int)($b['delay_days'] ?? 0), (float)($b['dispatched_units'] ?? 0)] <=> [(int)($a['delay_days'] ?? 0), (float)($a['dispatched_units'] ?? 0)];
        });
        $delayedOrders = [];
        foreach (array_slice($lateRows, 0, 12) as $r) {
            $delayedOrders[] = [
                'cc_number' => (string)($r['cc_number'] ?? '-'),
                'ot_number' => '-', // el informe de nivel de servicio no trae el N° de OT
                'customer_name' => (string)($r['customer_name'] ?? '-'),
                'product_type' => (string)($r['product_code'] ?? '-'),
                'units' => (float)($r['dispatched_units'] ?? 0),
                'required_date' => (string)($r['committed_date'] ?? '-'),
                'delay_days' => (int)($r['delay_days'] ?? 0),
            ];
        }

        // 3. Merma global del período.
        $wasteReport = $this->receptionService->getErpOperatorWasteReport($startDate, $endDate);
        $wasteSummary = (array)($wasteReport['summary'] ?? []);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'period_label' => (string)($period['label'] ?? ''),
            'prod_units' => $prodUnits,
            'sl_total_units' => $slTotalUnits,
            'sl_delayed_units' => $slDelayedUnits,
            'sl_total_cc' => (int)($slSummary['total_dispatches'] ?? 0),
            'sl_delayed_cc' => (int)($slSummary['delayed_dispatches'] ?? 0),
            'sl_percent_cc' => (float)($slSummary['service_level_percent'] ?? 0),
            'delayed_orders' => $delayedOrders,
            'waste_pct' => (float)($wasteSummary['global_waste_percent'] ?? 0),
            'total_waste_kg' => (float)($wasteSummary['total_waste_kg'] ?? 0),
        ];
    }

    /**
     * Obtiene todos los indicadores del mes y el historial (enero a ese mes) para los gráficos.
     *
     * Reglas (automatización):
     * - El mes pedido se calcula SIEMPRE en vivo con el ERP.
     * - Los meses anteriores salen del historial (data/kpi_historial_2026.json): cifras ya publicadas.
     * - Si falta un mes anterior, se calcula en vivo y se guarda en el historial (origen 'calculado'),
     *   así el informe del mes siguiente lo encuentra solo.
     * - No hay valores de respaldo inventados: si no hay datos, se informa en 'warnings'.
     *
     * @param string $monthKey Formato 'YYYY-MM' (mes en que TERMINA el ciclo 26-25)
     * @return array<string, mixed>
     */
    public function getMonthlyMetrics(string $monthKey = ''): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthKey)) {
            $monthKey = date('Y-m');
        }
        $year = (int)substr($monthKey, 0, 4);
        $monthNum = (int)substr($monthKey, 5, 2);
        $warnings = [];
        $capacityDefault = (float)(Env::get('PPTX_CAPACIDAD_MENSUAL_UNIDADES', '1345000') ?? 1345000);

        $stored = $this->loadHistory();
        $storedChanged = false;
        $live = $this->computeLiveCycle($monthKey);

        $history = [];
        for ($m = 1; $m <= $monthNum; $m++) {
            $key = sprintf('%04d-%02d', $year, $m);
            if ($key === $monthKey) {
                $entry = [
                    'origen' => 'calculado',
                    'prod_units' => $live['prod_units'],
                    'prod_cap' => (float)($stored[$key]['prod_cap'] ?? $capacityDefault),
                    'sl_total_units' => $live['sl_total_units'],
                    'sl_delayed_units' => $live['sl_delayed_units'],
                    'sl_total_cc' => $live['sl_total_cc'],
                    'sl_delayed_cc' => $live['sl_delayed_cc'],
                    'waste_pct' => $live['waste_pct'],
                ];
                if (isset($stored[$key]['procesos'])) {
                    $entry['procesos'] = $stored[$key]['procesos'];
                }
            } elseif (isset($stored[$key])) {
                $entry = $stored[$key];
            } else {
                $past = $this->computeLiveCycle($key);
                $entry = [
                    'origen' => 'calculado',
                    'prod_units' => $past['prod_units'],
                    'prod_cap' => $capacityDefault,
                    'sl_total_units' => $past['sl_total_units'],
                    'sl_delayed_units' => $past['sl_delayed_units'],
                    'sl_total_cc' => $past['sl_total_cc'],
                    'sl_delayed_cc' => $past['sl_delayed_cc'],
                    'waste_pct' => $past['waste_pct'],
                ];
                $stored[$key] = $entry;
                $storedChanged = true;
            }
            $entry['month_key'] = $key;
            $history[] = $entry;
        }
        if ($storedChanged) {
            $this->saveHistory($stored);
        }

        $prodUnits = (float)$live['prod_units'];
        $prodMeta = 1000000.0;
        if ($prodUnits <= 0) {
            $warnings[] = 'El ERP no devolvió producción de Corte y Sellado para el ciclo ' . $live['period_label'] . '.';
        }
        if ((int)$live['sl_total_cc'] <= 0) {
            $warnings[] = 'El ERP no devolvió despachos para el ciclo ' . $live['period_label'] . '.';
        }
        $slPercentUnits = $live['sl_total_units'] > 0
            ? max(0.0, min(100.0, (($live['sl_total_units'] - $live['sl_delayed_units']) / $live['sl_total_units']) * 100.0))
            : 0.0;

        $metrics = [
            'month_key' => $monthKey,
            'start_date' => $live['start_date'],
            'end_date' => $live['end_date'],
            'period_label' => $live['period_label'],
            'prod_units' => $prodUnits,
            'prod_meta' => $prodMeta,
            'prod_cumpl' => $prodMeta > 0 ? ($prodUnits / $prodMeta * 100.0) : 0.0,
            'prod_cap' => (float)end($history)['prod_cap'],
            'sl_total_units' => $live['sl_total_units'],
            'sl_delayed_units' => $live['sl_delayed_units'],
            'sl_percent_units' => $slPercentUnits,
            'sl_total_cc' => $live['sl_total_cc'],
            'sl_delayed_cc' => $live['sl_delayed_cc'],
            'sl_percent_cc' => $live['sl_percent_cc'],
            'delayed_orders' => $live['delayed_orders'],
            'global_waste_percent' => $live['waste_pct'],
            'total_waste_kg' => $live['total_waste_kg'],
            'history' => $history,
            'has_process_balance' => isset($stored[$monthKey]['procesos']),
            'warnings' => $warnings,
        ];
        // Balance de masa por proceso: solo si el mes lo trae en el historial (no hay fuente automática).
        foreach (['flexo', 'seri', 'cys', 'emb'] as $p) {
            $pr = $stored[$monthKey]['procesos'][$p] ?? null;
            if (is_array($pr)) {
                $metrics[$p . '_prog_kg'] = (float)($pr['prog'] ?? 0);
                $metrics[$p . '_proc_kg'] = (float)($pr['proc'] ?? 0);
                $metrics[$p . '_waste_kg'] = (float)($pr['waste'] ?? 0);
            }
        }
        return $metrics;
    }

    /**
     * Genera la presentación PowerPoint en disco y retorna la ruta completa al archivo generado.
     */
    public function generatePresentation(string $monthKey = ''): string
    {
        $metrics = $this->getMonthlyMetrics($monthKey);
        $monthKey = (string)$metrics['month_key'];
        
        $templatePath = __DIR__ . '/../data/KP´S OPERACIONES AGOSTO 2026.pptx';
        if (!file_exists($templatePath)) {
            throw new RuntimeException("Plantilla maestra PPTX no encontrada en {$templatePath}");
        }

        $tmpJson = tempnam(sys_get_temp_dir(), 'kpis_') . '.json';
        file_put_contents($tmpJson, json_encode($metrics, JSON_UNESCAPED_UNICODE));

        $outputFilename = 'KPIS_OPERACIONES_' . strtoupper(date('F_Y', strtotime($monthKey . '-01'))) . '.pptx';
        $targetDataPath = __DIR__ . '/../data/' . $outputFilename;
        $uniqueOutput = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kpis_pres_' . $monthKey . '_' . uniqid() . '.pptx';

        $scriptPath = __DIR__ . '/../scripts/generate_monthly_pptx.py';
        $pythonBin = $this->resolvePythonBinary();

        $cmd = sprintf(
            '%s "%s" --template="%s" --month="%s" --data="%s" --output="%s" 2>&1',
            escapeshellcmd($pythonBin),
            $scriptPath,
            $templatePath,
            $monthKey,
            $tmpJson,
            $uniqueOutput
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);
        $actualOutput = $uniqueOutput;
        if (!file_exists($actualOutput)) {
            $altOutput = preg_replace('/\.pptx$/i', '_SOLO_MODIFICADAS.pptx', $uniqueOutput);
            if (file_exists($altOutput)) {
                $actualOutput = $altOutput;
            }
        }

        if ($returnCode !== 0 || !file_exists($actualOutput)) {
            $err = implode("\n", $output);
            if (str_contains($err, 'ModuleNotFoundError') || str_contains($err, 'No module named')) {
                $err .= "\n[Tip]: En la VPS ejecute: 'cd /var/www/innovaimtech/unibagqa && ./venv/bin/pip install -r requirements.txt' o 'pip3 install -r requirements.txt'";
            }
            throw new RuntimeException("Error al ejecutar generador Python: " . $err);
        }

        // Intentar actualizar la copia en data/ si no está abierta por otro programa
        @copy($actualOutput, $targetDataPath);

        return $actualOutput;
    }

    /**
     * Resuelve el binario de Python a ejecutar.
     * Prioriza variable de entorno PYTHON_BIN, venv local del proyecto y finalmente python3/python del sistema.
     */
    private function resolvePythonBinary(): string
    {
        // 1. Variable de entorno personalizada
        $envBinary = getenv('PYTHON_BIN') ?: getenv('PYTHON_PATH') ?: ($_ENV['PYTHON_BIN'] ?? ($_ENV['PYTHON_PATH'] ?? null));
        if (!empty($envBinary) && (is_file((string)$envBinary) || $this->commandExists((string)$envBinary))) {
            return (string)$envBinary;
        }

        // 2. Entorno virtual (venv o .venv) en la raíz del proyecto
        $projectRoot = dirname(__DIR__);
        $isWindows = DIRECTORY_SEPARATOR === '\\';

        $venvCandidates = $isWindows
            ? [
                $projectRoot . '/venv/Scripts/python.exe',
                $projectRoot . '/.venv/Scripts/python.exe',
            ]
            : [
                $projectRoot . '/venv/bin/python3',
                $projectRoot . '/venv/bin/python',
                $projectRoot . '/.venv/bin/python3',
                $projectRoot . '/.venv/bin/python',
            ];

        foreach ($venvCandidates as $cand) {
            if (file_exists($cand) && (is_executable($cand) || $isWindows)) {
                return $cand;
            }
        }

        // 3. Binarios del sistema
        if ($isWindows) {
            return 'python';
        }

        if ($this->commandExists('python3')) {
            return 'python3';
        }

        return 'python';
    }

    private function commandExists(string $cmd): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $where = @shell_exec('where ' . escapeshellarg($cmd) . ' 2>NUL');
            return !empty(trim((string)$where));
        }

        $which = @shell_exec('which ' . escapeshellarg($cmd) . ' 2>/dev/null');
        return !empty(trim((string)$which));
    }
}

