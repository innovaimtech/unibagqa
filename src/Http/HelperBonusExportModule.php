<?php
declare(strict_types=1);

require_once __DIR__.'/../SimpleXlsx.php';
require_once __DIR__.'/../HelperBonusWorkbook.php';

/** Use saved evaluations and effective limits, never browser-supplied amounts.
 * Usar notas guardadas y topes vigentes, nunca montos enviados por el navegador. */
function renderHelperBonusExcelSheets(string $month, array $rows, array $period): array
{
    $number = static fn(float $value): string => number_format($value,3,',','');
    $cell = static fn(float $value): string => '<td class="num-dec3">'.$number($value).'</td>';
    $header = '<html><head><meta charset="UTF-8"></head><body><div class="title">Bono ayudante</div>'
        .'<div class="meta">Mes: '.h(substr($month,5,2).'/'.substr($month,0,4))
        .' | Periodo: '.h(date('d/m/Y',(int)$period['start_ts']).' al '.date('d/m/Y',(int)$period['end_ts']))
        .' | Generado: '.h(date('d/m/Y H:i:s')).' | Montos en CLP</div>';
    $summary = $header.'<div class="section">Resumen por ayudante</div><table class="report summary"><thead><tr>'
        .'<th>Ayudante</th><th>Proactividad</th><th>Eficiencia</th><th>Multitarea</th><th>Bono variable</th>'
        .'<th>Bono fijo</th><th>Incentivo adicional</th><th>Total a pagar</th><th>Estado</th></tr></thead><tbody>';
    $detail = $header;
    $totals = array_fill(0,7,0.0);
    foreach ($rows as $row) {
        $name = (string)$row['operator_name'];
        $status = !empty($row['has_evaluation']) ? 'Evaluado' : 'Sin evaluar';
        $paid = [];
        $detail .= '<div class="section">Detalle: '.h($name).'</div><table class="report"><thead><tr>'
            .'<th>Categoria</th><th>Maximo CLP</th><th>Nota / 10</th><th>Porcentaje</th><th>Bono CLP</th><th>Calculo</th></tr></thead><tbody>';
        foreach (['proactividad'=>'Proactividad','eficiencia'=>'Eficiencia','multitarea'=>'Multitarea'] as $key=>$label) {
            $maximum = (float)$row['matrix_'.$key.'_clp'];
            $score = (float)$row[$key.'_score'];
            $factor = BonusConfigurationService::helperScoreFactor($score);
            $amount = $maximum * $factor;
            $paid[] = $amount;
            $detail .= '<tr><td class="text-cell">'.h($label).'</td>'.$cell($maximum)
                .'<td class="num-dec">'.number_format($score,2,',','').'</td><td class="num-percent">'.(int)($factor*100).'%</td>'.$cell($amount)
                .'<td class="text-cell">'.h($number($maximum).' x '.(int)($factor*100).'% = '.$number($amount)).'</td></tr>';
        }
        $variable = array_sum($paid);
        $fixed = (float)$row['fixed_clp'];
        $additional = (float)$row['additional_clp'];
        $total = $variable+$fixed+$additional;
        $amounts = array_merge($paid,[$variable,$fixed,$additional,$total]);
        $summary .= '<tr><td class="text-cell">'.h($name).'</td>';
        foreach ($amounts as $i=>$amount) {
            $totals[$i] += $amount;
            $summary .= $cell($amount);
        }
        $summary .= '<td class="text-cell">'.h($status).'</td></tr>';
        foreach (['Bono variable'=>$variable,'Bono fijo'=>$fixed,'Incentivo adicional'=>$additional,'Total a pagar'=>$total] as $label=>$amount) {
            $detail .= '<tr><td colspan="4">'.h($label).'</td>'.$cell($amount).'<td class="text-cell">'
                .($label==='Total a pagar' ? h($number($variable).' + '.$number($fixed).' + '.$number($additional)) : '').'</td></tr>';
        }
        $detail .= '</tbody></table><table class="report"><tbody>';
        foreach (['Indicaciones'=>$row['instructions'] ?: 'Sin indicaciones',
            'Observaciones'=>$row['observations'] ?: 'Sin observaciones','Estado'=>$status] as $label=>$value) {
            $detail .= '<tr><th>'.h($label).'</th><td colspan="5" class="text-cell">'.h((string)$value).'</td></tr>';
        }
        $detail .= '</tbody></table>';
    }
    if (!$rows) {
        $summary .= '<tr><td colspan="9" class="text-cell">Sin ayudantes seleccionados para este mes.</td></tr>';
        $detail .= '<table><tr><td class="text-cell">Sin ayudantes seleccionados para este mes.</td></tr></table>';
    }
    $summary .= '<tr><td>TOTAL GENERAL</td>';
    foreach ($totals as $amount) $summary .= $cell($amount);
    $summary .= '<td class="text-cell">'.count($rows).' ayudantes</td></tr></tbody></table></body></html>';
    return [['title'=>'Resumen','html'=>$summary],['title'=>'Detalle','html'=>$detail.'</body></html>']];
}

function handleHelperBonusExcelExport(ReceptionService $service): void
{
    if (!unibagIsAuthenticated()) {
        http_response_code(403);
        render('Bonificaciones','<div class="card">Debes iniciar sesion para descargar el informe.</div>');
        exit;
    }
    $today = new DateTimeImmutable('now');
    $defaultMonth = ((int)$today->format('j')>=26 ? $today->modify('first day of next month') : $today)->format('Y-m');
    $month = trim((string)($_GET['month'] ?? $defaultMonth));
    try {
        if (!preg_match('/^(\d{4})-(\d{2})$/',$month,$parts) || !checkdate((int)$parts[2],1,(int)$parts[1])) {
            throw new RuntimeException('Selecciona un mes valido.');
        }
        if (!$service->listErpWorkerNames()) throw new RuntimeException('No se pudo cargar el listado del ERP.');
        $rows = $service->listBonusHelperMonthlyRows($month);
        $period = $service->getBonusPeriodByMonthFinal($month);
        $sheets = renderHelperBonusExcelSheets($month,$rows,$period);
        SimpleXlsx::stream('bono_ayudantes_'.$month.'.xlsx',HelperBonusWorkbook::build($month,$rows,$period,$sheets));
    } catch (Throwable $e) {
        error_log('Helper bonus Excel: '.$e->getMessage());
        $message = $e instanceof RuntimeException && !$e instanceof PDOException
            ? $e->getMessage() : 'No se pudo generar el Excel.';
        header('Location: /bonificaciones?'.http_build_query(['view'=>'bonoayudante','month'=>$month,'error'=>$message]));
        exit;
    }
}
