<?php
declare(strict_types=1);

/** Contabilizacion nativa del recuento; no inventa bajas de bobinas individuales.
 * Native count posting; aggregate differences never invent individual roll removals. */
final class ErpInventoryCountPostingService
{
    private const WRITE_TABLES=['company_data','item_shops_storehouses','stockcounts','stockcounts_lists',
        'stockcounts_lists_items','stockcounts_storehouses','stockchanges','stockchanges_items','tran_data'];
    private const READ_TABLES=['company_shops_storehouses','company_shops','item','stockchanges_issues','tran_average_costprices'];

    public function __construct(private PDO $erp,private PDO $trz) {}

    /** Exactamente diez decimales, como el kardex ERP, sin aritmetica float.
     * Exact ten-decimal quantities matching ERP ledger precision. */
    public static function quantity(mixed $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new RuntimeException('Cantidad no valida: usa numeros con hasta 10 decimales.');
        }
        $v=trim((string)$value);
        if (str_starts_with($v,'.')) $v='0'.$v;
        if (str_starts_with($v,'-.')) $v='-0'.substr($v,1);
        if (!preg_match('/^(-?)(\d+)(?:\.(\d{0,10}))?$/D',$v,$m)) throw new RuntimeException('Cantidad no valida: usa numeros con hasta 10 decimales.');
        $integer=ltrim($m[2],'0')?:'0'; $fraction=str_pad($m[3]??'',10,'0');
        if (strlen($integer)>20) throw new RuntimeException('La cantidad supera el rango permitido por el ERP.');
        return (($integer!=='0' || trim($fraction,'0')!=='') ? $m[1] : '').$integer.'.'.$fraction;
    }

    public static function add(string $a,string $b): string
    {
        $a=self::quantity($a); $b=self::quantity($b);
        $sa=str_starts_with($a,'-'); $sb=str_starts_with($b,'-');
        $sameSign=$sa===$sb;
        $a=ltrim(str_replace(['-','.'],'',$a),'0')?:'0';
        $b=ltrim(str_replace(['-','.'],'',$b),'0')?:'0';
        $len=max(strlen($a),strlen($b)); $a=str_pad($a,$len,'0',STR_PAD_LEFT); $b=str_pad($b,$len,'0',STR_PAD_LEFT);
        if ($sa!==$sb && strcmp($a,$b)<0) { [$a,$b]=[$b,$a]; $sa=$sb; }
        $result=''; $carry=0;
        for ($i=$len-1;$i>=0;$i--) {
            $digit=(int)$a[$i]+($sameSign ? (int)$b[$i] : -(int)$b[$i])+$carry;
            if ($sameSign) { $carry=intdiv($digit,10); $digit%=10; }
            else { $carry=$digit<0?-1:0; if ($digit<0) $digit+=10; }
            $result=$digit.$result;
        }
        if ($carry>0) $result=$carry.$result;
        $result=str_pad(ltrim($result,'0')?:'0',11,'0',STR_PAD_LEFT);
        return self::quantity(($sa?'-':'').substr($result,0,-10).'.'.substr($result,-10));
    }

    private static function negate(string $v): string
    {
        return str_starts_with($v,'-') ? substr($v,1) : '-'.$v;
    }

    private function query(string $sql,array $values=[]): PDOStatement
    {
        $q=$this->erp->prepare($sql);
        foreach ($values as $i=>$value) $q->bindValue($i+1,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $q->execute(); return $q;
    }

    private function insert(string $table,array $row): int
    {
        $this->insertMany($table,[$row]);
        return (int)$this->erp->lastInsertId();
    }

    private function insertMany(string $table,array $rows): void
    {
        if (!$rows || !in_array($table,self::WRITE_TABLES,true)) return;
        $columns=array_keys($rows[0]); $tuple='('.implode(',',array_fill(0,count($columns),'?')).')';
        foreach (array_chunk($rows,40) as $chunk) {
            $values=[]; foreach ($chunk as $row) $values=array_merge($values,array_values($row));
            $this->query('INSERT INTO '.$table.' ('.implode(',',$columns).') VALUES '.implode(',',array_fill(0,count($chunk),$tuple)),$values);
        }
    }

    private function journal(string $key,array $data,string $state): void
    {
        $this->trz->prepare('UPDATE erp_inventory_count_postings SET state=?,data_json=? WHERE request_key=?')
            ->execute([$state,json_encode($data,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE),$key]);
    }

    private function beginJournal(string $key,string $database,int $warehouse,int $user): ?array
    {
        $this->trz->exec('CREATE TABLE IF NOT EXISTS erp_inventory_count_postings (
            request_key VARCHAR(64) PRIMARY KEY,database_name VARCHAR(64) NOT NULL,warehouse_id BIGINT NOT NULL,
            actor_id BIGINT NOT NULL,state VARCHAR(20) NOT NULL,data_json TEXT NOT NULL,created_at BIGINT NOT NULL)');
        $q=$this->trz->prepare('SELECT state,data_json FROM erp_inventory_count_postings WHERE request_key=?');
        $q->execute([$key]); $previous=$q->fetch(PDO::FETCH_ASSOC);
        if ($previous) {
            if ($previous['state']==='done') return json_decode($previous['data_json'],true,512,JSON_THROW_ON_ERROR)['result'];
            throw new RuntimeException('Esta toma ya fue enviada y requiere revision. No repitas el ajuste; carga una nueva planilla solo cuando se confirme su estado.');
        }
        $q=$this->trz->prepare("SELECT request_key FROM erp_inventory_count_postings WHERE database_name=? AND warehouse_id=? AND state='processing' LIMIT 1");
        $q->execute([$database,$warehouse]);
        if ($q->fetchColumn()) throw new RuntimeException('Hay una toma pendiente de conciliacion en esta bodega. Debe revisarse antes de aplicar otro ajuste.');
        $this->trz->prepare('INSERT INTO erp_inventory_count_postings VALUES (?,?,?,?,?,?,?)')
            ->execute([$key,$database,$warehouse,$user,'processing','{}',time()]);
        return null;
    }

    private function counter(int $company,string $kind,string $prefix): string
    {
        $field='company_numcounter_'.$kind;
        $row=$this->query('SELECT company_numformat_'.$kind.','.$field.' FROM company_data WHERE id=?',[$company])->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('No se encontro la empresa de la bodega.');
        $next=(int)$row[$field];
        $table=$kind==='stockcount'?'stockcounts':'stockchanges';
        $numberField=$kind==='stockcount'?'stc_num':'stk_num';
        $format=trim((string)$row['company_numformat_'.$kind])?:$prefix;
        // Legacy/manual counts can leave the company counter behind existing documents.
        // Recuentos manuales antiguos pueden dejar el numerador desactualizado.
        for ($attempt=0;$attempt<1000;$attempt++) {
            $next++;
            $number=$format.str_pad((string)$next,7,'0',STR_PAD_LEFT);
            if (strlen($number)>50) throw new RuntimeException('El formato del numerador de inventario supera el limite del kardex.');
            if (!$this->query('SELECT 1 FROM '.$table.' WHERE '.$numberField.'=? LIMIT 1',[$number])->fetchColumn()) break;
        }
        if ($attempt===1000) throw new RuntimeException('El numerador de inventario requiere revision antes de guardar.');
        $this->query('UPDATE company_data SET '.$field.'=? WHERE id=?',[$next,$company]);
        return $number;
    }

    /** MyISAM requiere bloqueo y compensacion; no admite rollbacks reales.
     * MyISAM needs table locks and compensation, not pretend transactions. */
    public function create(int $warehouse,string $annotation,string $operator,array $items,int $user,string $request=''): array
    {
        $locked=false; $transactional=false; $started=false; $claimed=false; $committed=false; $requestLock='';
        $backup=['count_id'=>0,'moves'=>[],'base'=>[],'company'=>[]]; $key='';
        try {
            if ($warehouse<=0 || $user<=0 || !$items) throw new RuntimeException('Selecciona una bodega, un responsable autenticado y al menos un articulo.');
            if ($this->erp->inTransaction()) throw new RuntimeException('No se puede iniciar la toma dentro de otra operacion de inventario.');
            $normalized=[];
            foreach ($items as $item) {
                $id=(int)($item['item_id']??0);
                if ($id<=0 || isset($normalized[$id])) throw new RuntimeException('Hay articulos invalidos o repetidos en la planilla.');
                $physical=self::quantity($item['physical_qty']??'');
                if (str_starts_with($physical,'-')) throw new RuntimeException('El conteo fisico no puede ser negativo.');
                $normalized[$id]=['item_id'=>$id,'system'=>self::quantity($item['system_qty']??''),
                    'physical'=>$physical,'comment'=>mb_substr(trim((string)($item['comment']??'')),0,1000)];
            }
            ksort($normalized);
            $database=$this->erp->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
                ? (string)$this->erp->query('SELECT DATABASE()')->fetchColumn() : 'sqlite';
            if ($this->erp->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
                $lockName='invcount:'.substr(hash('sha256',$database.'|'.$warehouse),0,48);
                if ((int)$this->query('SELECT GET_LOCK(?,0)',[$lockName])->fetchColumn()!==1) {
                    throw new RuntimeException('Otra toma de esta bodega se esta guardando. Espera su confirmacion antes de continuar.');
                }
                $requestLock=$lockName;
            }
            $key=hash('sha256',$database.'|'.$warehouse.'|'.$user.'|'.($request?:bin2hex(random_bytes(32))));
            $previous=$this->beginJournal($key,$database,$warehouse,$user);
            if ($previous!==null) return $previous;
            $claimed=true;
            if ($this->erp->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
                $engines=$this->erp->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
                    AND TABLE_NAME IN ('".implode("','",self::WRITE_TABLES)."')")->fetchAll(PDO::FETCH_COLUMN);
                $transactional=count($engines)===count(self::WRITE_TABLES) && count(array_filter($engines,static fn($v)=>strtoupper($v)!=='INNODB'))===0;
                if (!$transactional) {
                    $locks=array_merge(array_map(static fn($t)=>$t.' WRITE',self::WRITE_TABLES),array_map(static fn($t)=>$t.' READ',self::READ_TABLES));
                    $this->erp->exec('LOCK TABLES '.implode(',',$locks)); $locked=true;
                }
            } else $transactional=true;
            if ($transactional) { $this->erp->beginTransaction(); $started=true; }
            $warehouseRow=$this->query('SELECT company_shops_storehouses.st_name,company_shops_storehouses.st_shop_id,company_shops.shop_company_id
                FROM company_shops_storehouses INNER JOIN company_shops ON company_shops.id=company_shops_storehouses.st_shop_id
                WHERE company_shops_storehouses.id=? AND company_shops_storehouses.st_status=1',[$warehouse])->fetch(PDO::FETCH_ASSOC);
            if (!$warehouseRow) throw new RuntimeException('La bodega seleccionada no existe o esta inactiva.');
            $shop=(int)$warehouseRow['st_shop_id']; $company=(int)$warehouseRow['shop_company_id'];
            $suffix=$transactional && $this->erp->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $backup['company']=$this->query('SELECT id,company_numcounter_stockcount,company_numcounter_stockchange FROM company_data WHERE id=?'.$suffix,[$company])->fetch(PDO::FETCH_ASSOC);
            if (!$backup['company']) throw new RuntimeException('Empresa de bodega no valida.');
            $stock=$this->query('SELECT * FROM item_shops_storehouses WHERE st_id=? ORDER BY item_id,shop_id,iss_order_id'.$suffix,[$warehouse])->fetchAll(PDO::FETCH_ASSOC);
            $group=[];
            foreach ($stock as $row) {
                $id=(int)$row['item_id'];
                if (!isset($normalized[$id])) continue;
                if ((int)$row['shop_id']!==$shop) throw new RuntimeException('Hay existencias con sucursal incompatible. Revisa la bodega antes de contar.');
                $group[$id]['total']=self::add($group[$id]['total']??'0',(string)$row['iss_inventory']);
                if ((int)$row['iss_order_id']===0) $group[$id]['base']=$row;
            }
            $ids=array_keys($normalized); $in=implode(',',array_fill(0,count($ids),'?'));
            $catalog=array_column($this->query("SELECT id,item_title FROM item WHERE item_status=1 AND id IN ($in)",$ids)->fetchAll(PDO::FETCH_ASSOC),'item_title','id');
            $cost=array_column($this->query("SELECT item_id,item_costprice_avg_netto FROM tran_average_costprices WHERE company_id=? AND item_id IN ($in)",array_merge([$company],$ids))->fetchAll(PDO::FETCH_ASSOC),'item_costprice_avg_netto','item_id');
            foreach ($normalized as $id=>&$line) {
                if (!isset($catalog[$id],$group[$id])) throw new RuntimeException('El articulo #'.$id.' no pertenece al inventario de esta bodega.');
                if ($line['system']!==$group[$id]['total']) throw new RuntimeException('El stock del articulo #'.$id.' cambio desde que se cargo la planilla. Actualiza y verifica el conteo antes de guardar.');
                $line['diff']=self::add($line['physical'],self::negate($line['system']));
                $line['title']=$catalog[$id]; $line['cost']=(string)($cost[$id]??'0');
                $backup['base'][$id]=$group[$id]['base']??null;
            }
            unset($line);
            $annotation=mb_substr(trim($annotation)?:'Recuento fisico '.date('d/m/Y'),0,250);
            $operator=mb_substr(trim($operator),0,100);
            $now=time(); $stcNum=$this->counter($company,'stockcount','IR');
            $backup['planned_number']=$stcNum;
            $this->journal($key,$backup,'processing');
            $countId=$this->insert('stockcounts',['stc_num'=>$stcNum,'stc_annotation'=>$annotation.' | Responsable: '.$operator,
                'stc_companyid'=>$company,'stc_shopid'=>$shop,'stc_itemid'=>0,'stc_bookdate'=>$now,
                'stc_status'=>1,'stc_onlystock'=>1,'stc_crtdat'=>$now,'stc_crtusr'=>$user]);
            $backup['count_id']=$countId; $this->journal($key,$backup,'processing');
            $this->insert('stockcounts_lists',['stc_id'=>$countId,'lst_pos'=>1,'lst_name'=>mb_substr($annotation.' - '.$warehouseRow['st_name'],0,500),
                'lst_sthid'=>$warehouse,'lst_date'=>$now,'lst_status'=>1]);
            $this->insert('stockcounts_storehouses',['stc_id'=>$countId,'sth_id'=>$warehouse]);
            $countRows=[]; $buckets=[0=>[],1=>[]]; $pos=1;
            foreach ($normalized as $line) {
                $countRows[]=['stc_id'=>$countId,'stc_lst_posid'=>1,'item_id'=>$line['item_id'],'item_pos'=>$pos++,
                    'item_stid'=>$warehouse,'item_amount_stock'=>$line['system'],'item_amount_count'=>$line['physical'],
                    'item_amount_book'=>$line['diff'],'item_comment'=>$line['comment'],'item_finished'=>1,
                    'item_alternative_type'=>'','item_costprice_docnumber'=>'','item_costprice_avg_netto'=>$line['cost']];
                if ($line['diff']!=='0.0000000000') $buckets[str_starts_with($line['diff'],'-')?1:0][]=$line;
            }
            $this->insertMany('stockcounts_lists_items',$countRows);
            $movements=[];
            foreach ($buckets as $negative=>$lines) {
                if (!$lines) continue;
                $issue=$negative?13:14; $type=$negative?'stockchangedown':'stockchangeup';
                $validIssue=$this->query('SELECT id FROM stockchanges_issues WHERE id=? AND stkis_status=1 AND stkis_negative=?',[$issue,$negative])->fetchColumn();
                if (!$validIssue) throw new RuntimeException('Falta el motivo nativo de recuento de inventario ('.$issue.').');
                $number=$this->counter($company,'stockchange','IA');
                $moveId=$this->insert('stockchanges',['stk_num'=>$number,'stk_annotation'=>'Ajuste por toma de inventario '.$stcNum.' | '.$annotation,
                    'stk_issueid'=>$issue,'stk_negative'=>$negative,'stk_companyid'=>$company,'stk_shopid'=>$shop,'stk_bookdate'=>$now,
                    'stk_status'=>1,'stk_crtdat'=>$now,'stk_crtusr'=>$user,'stockchange_stc_id'=>$countId,'stockchange_lst_pos'=>1,
                    'stk_cinumber'=>'','stk_vintnumber'=>'','sth_order_num'=>'','sth_fromrebo_agid_grphash'=>'']);
                $backup['moves'][]=$moveId; $this->journal($key,$backup,'processing');
                $moveItems=[]; $transactions=[];
                foreach ($lines as $pos=>$line) {
                    $amount=ltrim($line['diff'],'-');
                    $moveItems[]=['stk_id'=>$moveId,'item_id'=>$line['item_id'],'item_pos'=>$pos,'item_amount'=>$amount,
                        'item_type'=>'item','item_st_id'=>$warehouse,'item_costprice_netto'=>$line['cost']];
                    $transactions[]=['tran_id'=>$moveId,'tran_pos'=>$pos,'tran_type'=>$type,'tran_number'=>$number,
                        'tran_currstock'=>$line['system'],'tran_amount'=>$amount,'tran_company_id'=>$company,'tran_shop_id'=>$shop,
                        'tran_st_id'=>$warehouse,'tran_issueid'=>$issue,'item_id'=>$line['item_id'],'item_title'=>$line['title'],
                        'item_type'=>'item','item_costprice_netto'=>$line['cost'],'item_costprice_avg_netto'=>$line['cost'],
                        'tran_day'=>(int)date('d',$now),'tran_month'=>(int)date('m',$now),'tran_year'=>(int)date('Y',$now),
                        'tran_crtusr'=>$user,'tran_crtdat'=>$now];
                }
                $this->insertMany('stockchanges_items',$moveItems); $this->insertMany('tran_data',$transactions);
                foreach ($lines as $line) {
                    $base=$backup['base'][$line['item_id']];
                    $balance=self::add($base===null?'0':(string)$base['iss_inventory'],$line['diff']);
                    // Only the unallocated balancing row changes; reservations/order lots remain intact.
                    // Solo fila sin OT asignada; se conservan reservas y repartos por pedido.
                    if ($base===null) $this->insert('item_shops_storehouses',['item_id'=>$line['item_id'],'shop_id'=>$shop,'st_id'=>$warehouse,'iss_order_id'=>0,'iss_inventory'=>$balance]);
                    else $this->query('UPDATE item_shops_storehouses SET iss_inventory=? WHERE item_id=? AND shop_id=? AND st_id=? AND iss_order_id=0',
                        [$balance,$line['item_id'],$shop,$warehouse]);
                }
                $this->query('UPDATE stockchanges SET stk_status=2,stk_upddat=?,stk_updusr=? WHERE id=?',[$now,$user,$moveId]);
                $movements[]=['id'=>$moveId,'number'=>$number,'direction'=>$negative?'SALIDA':'ENTRADA','items'=>count($lines)];
            }
            $this->query('UPDATE stockcounts_lists SET lst_status=2,lst_upddat=?,lst_updusr=? WHERE stc_id=? AND lst_pos=1',[$now,$user,$countId]);
            $this->query('UPDATE stockcounts SET stc_status=2,stc_upddat=?,stc_updusr=? WHERE id=?',[$now,$user,$countId]);
            if ($started) $this->erp->commit();
            $committed=true;
            if ($locked) { $this->erp->exec('UNLOCK TABLES'); $locked=false; }
            $result=['ok'=>true,'id'=>$countId,'stc_num'=>$stcNum,'adjustments'=>$movements,'warning'=>''];
            try { $this->journal($key,['result'=>$result,'before'=>$backup],'done'); }
            catch (Throwable $e) {
                error_log('Count posting journal pending '.$stcNum.': '.$e->getMessage());
                $result['warning']='La toma esta contabilizada; su confirmacion de auditoria requiere revision. No repitas el ajuste.';
            }
            return $result;
        } catch (Throwable $e) {
            error_log('Inventory count posting: '.$e->getMessage());
            if ($committed) {
                return ['ok'=>true,'id'=>$countId,'stc_num'=>$stcNum,'adjustments'=>$movements,
                    'warning'=>'La toma esta contabilizada; el cierre de auditoria requiere revision. No repitas el ajuste.'];
            }
            $restored=!$committed;
            try {
                if ($started && $this->erp->inTransaction()) $this->erp->rollBack();
                elseif ($locked) $this->restore($backup,$warehouse);
            } catch (Throwable $cleanup) {
                $restored=false; error_log('Inventory count compensation failed: '.$cleanup->getMessage());
            }
            if ($claimed) {
                try { $this->journal($key,$backup,$restored?'failed':'processing'); }
                catch (Throwable $journalError) { error_log('Count failure journal: '.$journalError->getMessage()); }
            }
            return ['ok'=>false,'errors'=>[$restored
                ? ($e instanceof PDOException?'No se pudo contabilizar la toma. No se aplicaron ajustes; contacta al administrador.':$e->getMessage())
                : 'La toma requiere conciliacion por un error parcial. No repitas el ajuste; solicita revision al administrador.']];
        } finally {
            if ($locked) { try { $this->erp->exec('UNLOCK TABLES'); } catch (Throwable $e) { error_log('Unlock count: '.$e->getMessage()); } }
            if ($requestLock!=='') { try { $this->query('SELECT RELEASE_LOCK(?)',[$requestLock]); } catch (Throwable $e) { error_log('Release count lock: '.$e->getMessage()); } }
        }
    }

    private function restore(array $backup,int $warehouse): void
    {
        foreach ($backup['base'] as $item=>$row) {
            if ($row===null) $this->query('DELETE FROM item_shops_storehouses WHERE item_id=? AND st_id=? AND iss_order_id=0',[(int)$item,$warehouse]);
            else $this->query('UPDATE item_shops_storehouses SET iss_inventory=? WHERE item_id=? AND shop_id=? AND st_id=? AND iss_order_id=0',
                [(string)$row['iss_inventory'],(int)$item,(int)$row['shop_id'],$warehouse]);
        }
        foreach ($backup['moves'] as $id) {
            $this->query("DELETE FROM tran_data WHERE tran_id=? AND tran_type IN ('stockchangeup','stockchangedown')",[$id]);
            $this->query('DELETE FROM stockchanges_items WHERE stk_id=?',[$id]);
            $this->query('DELETE FROM stockchanges WHERE id=?',[$id]);
        }
        if ($backup['count_id']>0) {
            foreach (['stockcounts_lists_items','stockcounts_storehouses','stockcounts_lists'] as $table) $this->query('DELETE FROM '.$table.' WHERE stc_id=?',[$backup['count_id']]);
            $this->query('DELETE FROM stockcounts WHERE id=?',[$backup['count_id']]);
        }
        if ($backup['company']) $this->query('UPDATE company_data SET company_numcounter_stockcount=?,company_numcounter_stockchange=? WHERE id=?',
            [(int)$backup['company']['company_numcounter_stockcount'],(int)$backup['company']['company_numcounter_stockchange'],(int)$backup['company']['id']]);
    }
}
