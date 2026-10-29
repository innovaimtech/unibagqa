<?php
declare(strict_types=1);

/** Calendario nacional chileno / Chilean national business calendar.
 * Las fechas moviles y excepcionales se consultan por ano, no se suponen fijas.
 */
final class ChileBusinessCalendar
{
    private array $years = [];
    private ?Closure $fetcher;

    public function __construct(private ?string $cacheDirectory = null, ?callable $fetcher = null)
    {
        $this->cacheDirectory ??= __DIR__.'/../storage/calendar';
        $this->fetcher = $fetcher === null ? null : Closure::fromCallable($fetcher);
    }

    private static function validated(array $rows, int $year): array
    {
        $dates=[];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new RuntimeException('Calendario de feriados no valido.');
            $date=(string)($row['date']??'');
            $name=trim((string)($row['title']??''));
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$date,$m)
                || (int)$m[1]!==$year || !checkdate((int)$m[2],(int)$m[3],$year) || $name==='') {
                throw new RuntimeException('Calendario de feriados incompleto.');
            }
            // La fuente separa feriados regionales; solo se aplican nacionales.
            $scope=mb_strtolower((string)($row['extra']??'').' '.(string)($row['type']??''));
            if (str_contains($scope,'regional') || str_contains($scope,'comunal')
                || str_contains(mb_strtolower($name),'todos los d')) continue;
            $dates[$date]=$name;
        }
        foreach (['01-01','05-01','09-18','09-19','12-25'] as $fixed) {
            if (!isset($dates[$year.'-'.$fixed])) throw new RuntimeException('No se pudo validar el calendario de feriados.');
        }
        if (count($dates)<12) throw new RuntimeException('Calendario de feriados incompleto.');
        ksort($dates);
        return $dates;
    }

    private function request(int $year): array
    {
        $url='https://api.boostr.cl/holidays/'.$year.'.json';
        if ($this->fetcher) $json=($this->fetcher)($year);
        elseif (function_exists('curl_init')) {
            $curl=curl_init($url);
            curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>6,
                CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false]);
            $json=curl_exec($curl);
            $status=curl_getinfo($curl,CURLINFO_HTTP_CODE);
            curl_close($curl);
            if ($status!==200) throw new RuntimeException('No se pudo consultar el calendario de feriados de Chile.');
        } else {
            $context=stream_context_create(['http'=>['timeout'=>6],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
            $json=@file_get_contents($url,false,$context);
        }
        if (!is_string($json) || strlen($json)>200000) throw new RuntimeException('No se pudo consultar el calendario de feriados de Chile.');
        $payload=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        if (($payload['status']??'')!=='success' || !is_array($payload['data']??null)) {
            throw new RuntimeException('El proveedor no entrego un calendario completo.');
        }
        return self::validated($payload['data'],$year);
    }

    private function holidays(int $year): array
    {
        if (isset($this->years[$year])) return $this->years[$year];
        $path=$this->cacheDirectory.'/chile-national-'.$year.'.json';
        $cache=null;
        if (is_file($path)) {
            try {
                $cache=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
                $cache['dates']=self::validated($cache['rows']??[],$year);
            } catch (Throwable) { $cache=null; }
        }
        if ($cache && (int)($cache['updated_at']??0)>time()-604800) return $this->years[$year]=$cache;
        try {
            $dates=$this->request($year);
            $source='https://api.boostr.cl/holidays/'.$year.'.json';
        } catch (Throwable $e) {
            if ($cache) return $this->years[$year]=$cache;
            // Respaldo 2026 contrastado con el calendario oficial de Gob.cl.
            // Verified 2026 fallback; never assume another year's holiday dates.
            if ($year!==2026) throw new RuntimeException('No se pudo validar los feriados de Chile para '.$year.'. No se calculo el bono.');
            $names=['01-01'=>'Ano Nuevo','04-03'=>'Viernes Santo','04-04'=>'Sabado Santo',
                '05-01'=>'Dia del Trabajo','05-21'=>'Glorias Navales','06-21'=>'Pueblos Indigenas',
                '06-29'=>'San Pedro y San Pablo','07-16'=>'Virgen del Carmen','08-15'=>'Asuncion de la Virgen',
                '09-18'=>'Independencia Nacional','09-19'=>'Glorias del Ejercito','10-12'=>'Encuentro de Dos Mundos',
                '10-31'=>'Iglesias Evangelicas y Protestantes','11-01'=>'Todos los Santos',
                '12-08'=>'Inmaculada Concepcion','12-25'=>'Navidad'];
            $dates=[];
            foreach ($names as $date=>$name) $dates['2026-'.$date]=$name;
            $source='https://www.gob.cl/noticias/feriados-2026-revisa-cuantos-habra-y-cuales-son-irrenunciables/';
        }
        $rows=[];
        foreach ($dates as $date=>$name) $rows[]=['date'=>$date,'title'=>$name];
        $cache=['dates'=>$dates,'rows'=>$rows,'source'=>$source,'updated_at'=>time()];
        if ((is_dir($this->cacheDirectory) || @mkdir($this->cacheDirectory,0775,true)) && is_writable($this->cacheDirectory)) {
            // LOCK_EX + rename prevents readers from observing a partial cache.
            $temporary=tempnam($this->cacheDirectory,'calendar-');
            if ($temporary!==false) {
                if (file_put_contents($temporary,json_encode($cache,JSON_THROW_ON_ERROR),LOCK_EX)!==false) @rename($temporary,$path);
                if (is_file($temporary)) @unlink($temporary);
            }
        }
        return $this->years[$year]=$cache;
    }

    public function forPeriod(array $period): array
    {
        $zone=new DateTimeZone('America/Santiago');
        $start=(new DateTimeImmutable('@'.$period['start']))->setTimezone($zone)->setTime(0,0);
        $end=(new DateTimeImmutable('@'.$period['end']))->setTimezone($zone)->setTime(0,0);
        $all=[]; $sources=[];
        for ($year=(int)$start->format('Y');$year<=(int)$end->format('Y');$year++) {
            $calendar=$this->holidays($year);
            $all=array_replace($all,$calendar['dates']);
            $sources[$year]=$calendar['source'];
        }
        $dates=[]; $holidays=[]; $weekdays=0;
        for ($day=$start;$day<$end;$day=$day->modify('+1 day')) {
            $date=$day->format('Y-m-d');
            $weekday=(int)$day->format('N')<=5;
            if ($weekday) $weekdays++;
            if (isset($all[$date])) $holidays[]=['date'=>$date,'name'=>$all[$date],'deducted'=>$weekday];
            if ($weekday && !isset($all[$date])) $dates[]=$date;
        }
        return ['dates'=>$dates,'days'=>count($dates),'weekdays'=>$weekdays,'holidays'=>$holidays,'sources'=>$sources,
            'lunch_seconds'=>count($dates)*1800];
    }
}
