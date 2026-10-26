<?php
declare(strict_types=1);

require_once __DIR__.'/SimpleXlsx.php';
require_once __DIR__.'/BonusConfigurationService.php';

/** Dedicated legacy layout; other reports keep their existing styles.
 * Diseno exclusivo de ayudantes; no cambia los estilos de otros informes. */
final class HelperBonusWorkbook
{
    private static function text(mixed $value): string
    {
        return htmlspecialchars((string)$value,ENT_XML1,'UTF-8');
    }

    public static function build(string $month, array $rows, array $period, array $sheets): string
    {
        $file=tempnam(sys_get_temp_dir(),'helper_layout_');
        $zip=new ZipArchive();
        $opened=false;
        try {
            file_put_contents($file,SimpleXlsx::fromHtmlSheets($sheets));
            if ($zip->open($file)!==true) throw new RuntimeException('No se pudo crear la planilla de ayudantes.');
            $opened=true;
            [$styles,$ids]=self::styles((string)$zip->getFromName('xl/styles.xml'));
            $zip->addFromString('xl/styles.xml',$styles);
            $zip->addFromString('xl/worksheets/sheet1.xml',self::sheet($month,$rows,$period,$ids));
            $zip->close();
            $opened=false;
            return (string)file_get_contents($file);
        } finally {
            if ($opened) $zip->close();
            if (is_file($file)) unlink($file);
        }
    }

    private static function styles(string $source): array
    {
        $doc=new DOMDocument();
        $doc->loadXML($source);
        $ns=$doc->documentElement->namespaceURI;
        $add=static function(DOMElement $parent,string $tag,array $attrs=[]) use ($doc,$ns): DOMElement {
            $node=$doc->createElementNS($ns,$tag);
            foreach ($attrs as $key=>$value) $node->setAttribute($key,(string)$value);
            $parent->appendChild($node);
            return $node;
        };
        $fonts=$doc->getElementsByTagName('fonts')->item(0);
        $fills=$doc->getElementsByTagName('fills')->item(0);
        $borders=$doc->getElementsByTagName('borders')->item(0);
        $formats=$doc->getElementsByTagName('numFmts')->item(0);
        $xfs=$doc->getElementsByTagName('cellXfs')->item(0);
        $fontIds=[];
        foreach (['normal'=>false,'bold'=>true,'red'=>false] as $name=>$bold) {
            $fontIds[$name]=$fonts->getElementsByTagName('font')->length;
            $font=$add($fonts,'font');
            $add($font,'sz',['val'=>9]);$add($font,'name',['val'=>'Arial']);
            $add($font,'color',['rgb'=>$name==='red'?'FFFF0000':'FF000000']);
            if ($bold) $add($font,'b');
        }
        $fillIds=[];
        foreach (['gold'=>'FFDE66','blue'=>'8FAADC','white'=>'FFFFFF','green'=>'A9C38B'] as $name=>$color) {
            $fillIds[$name]=$fills->getElementsByTagName('fill')->length;
            $pattern=$add($add($fills,'fill'),'patternFill',['patternType'=>'solid']);
            $add($pattern,'fgColor',['rgb'=>'FF'.$color]);$add($pattern,'bgColor',['indexed'=>64]);
        }
        $borderId=$borders->getElementsByTagName('border')->length;
        $border=$add($borders,'border');
        foreach (['left','right','top','bottom'] as $side) $add($add($border,$side,['style'=>'thin']),'color',['rgb'=>'FF000000']);
        $add($border,'diagonal');
        $add($formats,'numFmt',['numFmtId'=>175,'formatCode'=>'"$" #,##0;[Red]"$" -#,##0;"$" -']);
        $add($formats,'numFmt',['numFmtId'=>176,'formatCode'=>'0.##']);
        $add($formats,'numFmt',['numFmtId'=>177,'formatCode'=>'0%']);
        $ids=[];
        foreach ([
            'gold'=>['bold','gold',0,'center'],'blue'=>['bold','blue',0,'center'],
            'body'=>['normal','white',0,'center'],'operator'=>['bold','white',0,'center'],
            'score'=>['normal','white',176,'center'],'percent'=>['normal','white',177,'center'],
            'money'=>['normal','white',175,'right'],'boldMoney'=>['bold','white',175,'right'],
            'total'=>['bold','green',175,'right'],'observation'=>['red','white',0,'left'],
        ] as $name=>[$font,$fill,$format,$alignment]) {
            $ids[$name]=$xfs->getElementsByTagName('xf')->length;
            $xf=$add($xfs,'xf',['numFmtId'=>$format,'fontId'=>$fontIds[$font],'fillId'=>$fillIds[$fill],
                'borderId'=>$borderId,'xfId'=>0,'applyNumberFormat'=>1,'applyFill'=>1,'applyFont'=>1,'applyBorder'=>1,'applyAlignment'=>1]);
            $add($xf,'alignment',['horizontal'=>$alignment,'vertical'=>'center','wrapText'=>1]);
        }
        foreach ([[$fonts,'font'],[$fills,'fill'],[$borders,'border'],[$formats,'numFmt'],[$xfs,'xf']] as [$node,$tag]) {
            $node->setAttribute('count',(string)$node->getElementsByTagName($tag)->length);
        }
        return [$doc->saveXML(),$ids];
    }

    private static function sheet(string $month, array $rows, array $period, array $styles): string
    {
        $months=['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
        $cell=static function(int $column,int $row,mixed $value,string $style,bool $numeric=false) use ($styles): string {
            $ref=chr(65+$column).$row;
            $xml='<c r="'.$ref.'" s="'.$styles[$style].'"';
            return $numeric ? $xml.'><v>'.(float)$value.'</v></c>'
                : $xml.' t="inlineStr"><is><t xml:space="preserve">'.self::text($value).'</t></is></c>';
        };
        $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView workbookViewId="0" showGridLines="0" zoomScale="80">'
            .'<pane xSplit="3" ySplit="2" topLeftCell="D3" activePane="bottomRight" state="frozen"/></sheetView></sheetViews><cols>';
        foreach ([9,16,18,14,14,14,15,15,15,14,14,14,14,14,14,14,14,15,17,15,65] as $i=>$width) {
            $xml.='<col min="'.($i+1).'" max="'.($i+1).'" width="'.$width.'" customWidth="1"/>';
        }
        $xml.='</cols><sheetData><row r="1" ht="18" customHeight="1">';
        $groups=[0=>'PLAZO',2=>'OPERADOR',3=>"PUNTUACI\u{00D3}N",6=>"PORCENTAJE POR PUNTUACI\u{00D3}N",
            9=>'MATRIZ DE BONO',12=>"BONO POR MEDICI\u{00D3}N",15=>'BONO',16=>'BONO',17=>'TOTAL',
            18=>"INCENTIVO\nADICIONAL\nPOR OTRAS\nTAREAS\n(H.MATUS)",19=>"TOTAL\nA\nPAGAR",20=>'OBSERVACIONES'];
        for ($i=0;$i<21;$i++) $xml.=$cell($i,1,$groups[$i] ?? '',$i<6?'gold':'blue');
        $xml.='</row><row r="2" ht="64" customHeight="1">';
        $headers=['SEMANA','FECHA','OPERADOR','PROACTIVIDAD','EFICIENCIA','MULTITAREA',
            'PROACTIVIDAD','EFICIENCIA','MULTITAREA','PROACTIVIDAD','EFICIENCIA','MULTITAREA',
            'PROACTIVIDAD','EFICIENCIA','MULTITAREA','VARIABLE','FIJO','A PAGAR','','',''];
        foreach ($headers as $i=>$value) $xml.=$cell($i,2,$value,$i<6?'gold':'blue');
        $xml.='</row>';
        $merges=['A1:B1','D1:F1','G1:I1','J1:L1','M1:O1','S1:S2','T1:T2','U1:U2'];
        $sums=array_fill(0,21,0.0);
        $r=3;
        foreach ($rows as $index=>$row) {
            $scores=[];$factors=[];$maximums=[];$paid=[];
            foreach (['proactividad','eficiencia','multitarea'] as $category) {
                $score=(float)$row[$category.'_score'];
                $factor=BonusConfigurationService::helperScoreFactor($score);
                $scores[]=$score;$factors[]=$factor;
                $maximums[]=(float)$row['matrix_'.$category.'_clp'];
                $paid[]=(float)$row['matrix_'.$category.'_clp']*$factor;
            }
            $variable=array_sum($paid);
            $fixed=(float)$row['fixed_clp'];$additional=(float)$row['additional_clp'];
            $observation=(string)$row['observations'];
            if (empty($row['has_evaluation'])) $observation='SIN EVALUAR'.($observation!=='' ? '. '.$observation : '');
            $values=array_merge([$index===0?'MES':'',$index===0?$months[(int)substr($month,5,2)-1]:'',$row['operator_name']],
                $scores,$factors,$maximums,$paid,[$variable,$fixed,$variable+$fixed,$additional,$variable+$fixed+$additional,$observation]);
            $height=max(20,min(130,ceil(max(1,mb_strlen($observation))/65)*13+8));
            $xml.='<row r="'.$r.'" ht="'.$height.'" customHeight="1">';
            foreach ($values as $i=>$value) {
                $style=$i===2?'operator':($i>=3&&$i<=5?'score':($i>=6&&$i<=8?'percent':
                    ($i>=9&&$i<=14?'money':($i>=15&&$i<=18?'boldMoney':($i===19?'total':($i===20?'observation':'body'))))));
                $numeric=$i>=3&&$i<=19;
                $xml.=$cell($i,$r,$value,$style,$numeric);
                if ($i>=12&&$i<=19) $sums[$i]+=(float)$value;
            }
            $xml.='</row>';$r++;
        }
        if (count($rows)>1) {$merges[]='A3:A'.($r-1);$merges[]='B3:B'.($r-1);}
        if (!$rows) {
            $xml.='<row r="3">'.$cell(0,3,'Sin ayudantes seleccionados para este mes.','body').'</row>';
            $merges[]='A3:U3';$r++;
        }
        $xml.='<row r="'.$r.'" ht="24" customHeight="1">';
        for ($i=0;$i<21;$i++) {
            $xml.=$cell($i,$r,$i===0?'TOTAL GENERAL':($i>=12&&$i<=19?$sums[$i]:''),
                $i===19?'total':($i>=12&&$i<=18?'boldMoney':'operator'),$i>=12&&$i<=19);
        }
        $merges[]='A'.$r.':L'.$r;
        $xml.='</row></sheetData><mergeCells count="'.count($merges).'">';
        foreach ($merges as $ref) $xml.='<mergeCell ref="'.$ref.'"/>';
        $xml.='</mergeCells><printOptions horizontalCentered="1"/><pageMargins left="0.25" right="0.25" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>'
            .'<pageSetup paperSize="8" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'<headerFooter><oddHeader>'.self::text('Bono ayudante | '.$month.' | '.date('d/m/Y',(int)$period['start_ts']).' al '.date('d/m/Y',(int)$period['end_ts']))
            .'</oddHeader><oddFooter>'.self::text('&RPagina &P de &N').'</oddFooter></headerFooter></worksheet>';
        return $xml;
    }
}
