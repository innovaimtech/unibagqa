<?php
declare(strict_types=1);

require_once __DIR__.'/SimpleXlsx.php';
require_once __DIR__.'/MaintenanceBonusService.php';

/** Plantilla nativa de disponibilidad / Native availability workbook template.
 * A:Q conserva la estructura de la imagen; los anexos no alteran la hoja principal.
 */
final class MaintenanceBonusWorkbook
{
    private const NS='http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const HEADERS=[
        'MES', "MÁQUINA", "N°\nMÁQUINA",
        "HORAS\nMANTENIMIENTO\nPREVENTIVO\n(PROGRAMADO)", "COLACIÓN\n(HORAS)",
        "DÍAS\nDISPONIBLES\n(MES)", "HORAS\nPROGRAMADAS\n(DÍA)", "HORAS\nTOTALES\n(MES)",
        "TIEMPO\nDISPONIBLE", "AVERÍAS\n(HORAS)", "ALISTAMIENTOS\nOPERATIVOS\n(HORAS)",
        "PAROS\nEXTERNOS", "TIEMPO\nOPERATIVO\n(BRUTO)", "TIEMPO\nOPERATIVO\n(MANTENIMIENTO)",
        "%\nDISPONIBILIDAD\nREAL", "%\nDISPONIBILIDAD\nESPERADA", "CUMPLE /\nNO\nCUMPLE",
    ];

    private static function escape(mixed $value): string
    {
        return htmlspecialchars((string)$value,ENT_XML1 | ENT_QUOTES,'UTF-8');
    }

    private static function xml(string $source): DOMDocument
    {
        $document=new DOMDocument();
        if (!$document->loadXML($source,LIBXML_NONET)) throw new RuntimeException('Plantilla Excel no valida.');
        return $document;
    }

    public static function machineNumber(array $row): ?int
    {
        if (isset($row['machine_number']) && preg_match('/^\d+$/',(string)$row['machine_number'])
            && (int)$row['machine_number']>0) return (int)$row['machine_number'];
        $name=trim(mb_strtoupper((string)$row['name'],'UTF-8'));
        if (preg_match('/(?:\s|\.)(\d+)\.?$/u',$name,$match)) return (int)$match[1];
        if (preg_match('/\s([IVXLCDM]+)\.?$/u',$name,$match)
            && preg_match('/^(?=[MDCLXVI])M{0,3}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$/',$match[1])) {
            $values=['I'=>1,'V'=>5,'X'=>10,'L'=>50,'C'=>100,'D'=>500,'M'=>1000];
            $number=0; $previous=0;
            foreach (array_reverse(str_split($match[1])) as $letter) {
                $value=$values[$letter];
                $number += $value<$previous ? -$value : $value;
                $previous=max($previous,$value);
            }
            return $number;
        }
        return $name==='PULPO' || $name==='PULPO.' ? 1 : null;
    }

    private static function machineName(string $name): string
    {
        $name=mb_convert_case(trim($name),MB_CASE_TITLE,'UTF-8');
        return preg_replace_callback('/\b[ivxlcdm]+\b\.?$/iu',
            static fn($m)=>strtoupper($m[0]),$name);
    }

    private static function number(mixed $value): string
    {
        if (!is_numeric($value) || !is_finite((float)$value)) {
            throw new RuntimeException('El informe contiene un valor numerico no valido.');
        }
        // Locale-independent literals for cached values and formulas.
        return json_encode((float)$value,JSON_THROW_ON_ERROR);
    }

    /** $annexes contains only Detalle and Calculo y fuentes, not another summary. */
    public static function build(array $report, array $annexes): string
    {
        if (count($report['rows'])>1048574) throw new RuntimeException('Demasiadas filas para el archivo Excel.');
        $file=tempnam(sys_get_temp_dir(),'maintenance_xlsx_');
        if ($file===false) throw new RuntimeException('No se pudo crear el archivo temporal Excel.');
        $zip=new ZipArchive(); $opened=false;
        try {
            $base=SimpleXlsx::fromHtmlSheets(array_merge([
                ['title'=>'Disponibilidad','html'=>'<table><tr><th>Disponibilidad</th></tr></table>'],
            ],$annexes));
            if (file_put_contents($file,$base)===false || $zip->open($file)!==true) {
                throw new RuntimeException('No se pudo construir el archivo Excel.');
            }
            $opened=true;
            [$styles,$ids]=self::styles((string)$zip->getFromName('xl/styles.xml'));
            $sheet=self::sheet($report,$ids);
            self::xml($sheet);
            if (!$zip->addFromString('xl/styles.xml',$styles)
                || !$zip->addFromString('xl/worksheets/sheet1.xml',$sheet)) {
                throw new RuntimeException('No se pudo aplicar el formato Excel.');
            }
            // All calendar dates are text. 1904 allows negative durations in pending rows.
            // Fechas de anexos como texto; 1904 permite mostrar duraciones negativas.
            $workbook=self::xml((string)$zip->getFromName('xl/workbook.xml'));
            $properties=$workbook->createElementNS(self::NS,'workbookPr');
            $properties->setAttribute('date1904','1');
            $workbook->documentElement->insertBefore($properties,$workbook->documentElement->firstChild);
            $calculation=$workbook->createElementNS(self::NS,'calcPr');
            $calculation->setAttribute('calcMode','auto');
            $calculation->setAttribute('fullCalcOnLoad','1');
            $workbook->documentElement->appendChild($calculation);
            if (!$zip->addFromString('xl/workbook.xml',$workbook->saveXML()) || !$zip->close()) {
                throw new RuntimeException('No se pudo finalizar el archivo Excel.');
            }
            $opened=false;
            $binary=file_get_contents($file);
            if ($binary===false || !str_starts_with($binary,'PK')) throw new RuntimeException('Archivo Excel incompleto.');
            return $binary;
        } finally {
            if ($opened) $zip->close();
            if (is_file($file)) unlink($file);
        }
    }

    private static function styles(string $source): array
    {
        $doc=self::xml($source);
        // Some spreadsheet readers treat comments as stylesheet list entries.
        // Algunos lectores interpretan comentarios como elementos de estilo.
        $xpath=new DOMXPath($doc);
        foreach ($xpath->query('//comment()') as $comment) $comment->parentNode->removeChild($comment);
        $add=static function(DOMElement $parent,string $tag,array $attributes=[]) use ($doc): DOMElement {
            $node=$doc->createElementNS(self::NS,$tag);
            foreach ($attributes as $key=>$value) $node->setAttribute($key,(string)$value);
            $parent->appendChild($node);
            return $node;
        };
        $fonts=$doc->getElementsByTagName('fonts')->item(0);
        $fills=$doc->getElementsByTagName('fills')->item(0);
        $borders=$doc->getElementsByTagName('borders')->item(0);
        $formats=$doc->getElementsByTagName('numFmts')->item(0);
        $xfs=$doc->getElementsByTagName('cellXfs')->item(0);
        $fontIds=[];
        foreach (['normal'=>['000000',false,false],'bold'=>['000000',true,false],
            'blue'=>['0000FF',false,false],'percent'=>['0000FF',true,true],'red'=>['FF0000',true,false]] as $key=>[$color,$bold,$underline]) {
            $fontIds[$key]=$fonts->getElementsByTagName('font')->length;
            $font=$add($fonts,'font');
            $add($font,'sz',['val'=>8]); $add($font,'name',['val'=>'Arial']);
            $add($font,'color',['rgb'=>'FF'.$color]);
            if ($bold) $add($font,'b');
            if ($underline) $add($font,'u');
        }
        $fillId=$fills->getElementsByTagName('fill')->length;
        $pattern=$add($add($fills,'fill'),'patternFill',['patternType'=>'solid']);
        $add($pattern,'fgColor',['rgb'=>'FFD9D9D9']); $add($pattern,'bgColor',['indexed'=>64]);
        $borderIds=[];
        foreach (['grid'=>'FFD9D9D9','bar'=>'FF63BE7B'] as $key=>$color) {
            $borderIds[$key]=$borders->getElementsByTagName('border')->length;
            $border=$add($borders,'border');
            foreach (['left','right','top','bottom'] as $side) {
                $add($add($border,$side,['style'=>'thin']),'color',['rgb'=>$color]);
            }
            $add($border,'diagonal');
        }
        $durationId=164;
        foreach ($formats->getElementsByTagName('numFmt') as $format) {
            $durationId=max($durationId,(int)$format->getAttribute('numFmtId')+1);
        }
        $add($formats,'numFmt',['numFmtId'=>$durationId,'formatCode'=>'[h]:mm:ss;-[h]:mm:ss']);
        $ids=[];
        foreach ([
            'header'=>['bold',0,'center','grid',$fillId],
            'month'=>['bold',0,'center','grid',0], 'text'=>['normal',0,'left','grid',0],
            'integer'=>['normal',1,'center','grid',0], 'body'=>['normal',0,'center','grid',0],
            'duration'=>['normal',$durationId,'center','grid',0],
            'blueDuration'=>['blue',$durationId,'center','grid',0],
            'percent'=>['percent',10,'center','bar',0], 'target'=>['normal',9,'center','grid',0],
            'totalDuration'=>['bold',$durationId,'center','grid',0],
            'averageLabel'=>['red',0,'right','grid',0], 'average'=>['red',10,'center','bar',0],
            'averageTarget'=>['red',10,'center','grid',0], 'result'=>['red',0,'center','grid',0],
        ] as $key=>[$font,$format,$align,$border,$fill]) {
            $ids[$key]=$xfs->getElementsByTagName('xf')->length;
            $xf=$add($xfs,'xf',['fontId'=>$fontIds[$font],'numFmtId'=>$format,'fillId'=>$fill,'borderId'=>$borderIds[$border],
                'xfId'=>0,'applyFont'=>1,'applyNumberFormat'=>1,'applyFill'=>1,'applyBorder'=>1,'applyAlignment'=>1]);
            $add($xf,'alignment',['horizontal'=>$align,'vertical'=>'center','wrapText'=>$key==='header' ? 1 : 0]);
        }
        foreach ([[$fonts,'font'],[$fills,'fill'],[$borders,'border'],[$formats,'numFmt'],[$xfs,'xf']] as [$parent,$tag]) {
            $parent->setAttribute('count',(string)$parent->getElementsByTagName($tag)->length);
        }
        if (!$doc->getElementsByTagName('cellStyles')->length) {
            $named=$add($doc->documentElement,'cellStyles',['count'=>1]);
            $add($named,'cellStyle',['name'=>'Normal','xfId'=>0,'builtinId'=>0]);
        }
        return [$doc->saveXML(),$ids];
    }

    private static function sheet(array $report, array $styles): string
    {
        $cell=static function(int $column,int $row,mixed $value,string $style,bool $numeric=false,?string $formula=null) use ($styles): string {
            $ref=chr(65+$column).$row;
            $xml='<c r="'.$ref.'" s="'.$styles[$style].'"'.($numeric ? '' : ($formula!==null ? ' t="str"' : ' t="inlineStr"')).'>';
            if ($formula!==null) $xml.='<f>'.self::escape($formula).'</f>';
            return $xml.($numeric ? '<v>'.self::number($value).'</v>'
                : ($formula!==null ? '<v>'.self::escape($value).'</v>' : '<is><t xml:space="preserve">'.self::escape($value).'</t></is>')).'</c>';
        };
        $months=['ene','feb','mar','abr','may','jun','jul','ago','sept','oct','nov','dic'];
        $month=$months[(int)substr($report['month'],5,2)-1].'-'.substr($report['month'],2,2);
        $last=count($report['rows'])+2;
        $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="'.self::NS.'"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<dimension ref="A1:Q'.$last.'"/><sheetViews><sheetView workbookViewId="0" showGridLines="0" zoomScale="85">'
            .'<pane xSplit="3" ySplit="1" topLeftCell="D2" activePane="bottomRight" state="frozen"/>'
            .'</sheetView></sheetViews><sheetFormatPr defaultRowHeight="12.75"/><cols>';
        foreach ([6.3,10.5,6.2,16,10,8.7,10.5,10.5,11,10,14.5,10,14,15.5,14,12,10] as $i=>$width) {
            $xml.='<col min="'.($i+1).'" max="'.($i+1).'" width="'.$width.'" customWidth="1"/>';
        }
        $xml.='</cols><sheetData><row r="1" ht="61" customHeight="1">';
        foreach (self::HEADERS as $i=>$header) $xml.=$cell($i,1,$header,'header');
        $xml.='</row>';
        $totals=['total'=>0.0,'available'=>0.0,'breakdown'=>0.0];
        foreach ($report['rows'] as $i=>$row) {
            $r=$i+2;
            $number=self::machineNumber($row);
            $xml.='<row r="'.$r.'" ht="12.75" customHeight="1">'
                .$cell(0,$r,$month,'month').$cell(1,$r,self::machineName($row['name']),'text')
                .$cell(2,$r,$number??'','integer',$number!==null);
            $fields=['preventive','lunch','days','daily','total','available','breakdown','setup','external','gross','maintenance'];
            $formulas=['daily'=>MaintenanceBonusService::WEEKLY_HOURS.'/'.MaintenanceBonusService::WORKDAYS_PER_WEEK.'/24',
                'lunch'=>"F$r*".MaintenanceBonusService::DAILY_LUNCH_SECONDS.'/86400',
                'total'=>"F$r*G$r",'available'=>"H$r-E$r-D$r",'gross'=>"I$r-J$r-K$r-L$r",'maintenance'=>"I$r-J$r"];
            foreach ($fields as $index=>$field) {
                $column=$index+3;
                $value=$field==='days' ? $row[$field] : $row[$field]/86400;
                $style=$field==='days' ? 'integer' : (in_array($column,[4,7,8,12,13],true) ? 'blueDuration' : 'duration');
                $xml.=$cell($column,$r,$value,$style,true,$formulas[$field]??null);
                if (isset($totals[$field])) $totals[$field]+=$row[$field];
            }
            $xml.=$cell(14,$r,$row['real']===null ? '' : $row['real']/100,'percent',$row['real']!==null,
                $row['real']===null ? null : "IF(I$r>0,N$r/I$r,0)")
                .$cell(15,$r,$row['target']/100,'target',true);
            $formula=$row['status']==='PENDIENTE' ? null
                : "IF(OR(I$r<=0,M$r<0),\"PENDIENTE\",IF(O$r>=P$r,\"CUMPLE\",\"NO CUMPLE\"))";
            $xml.=$cell(16,$r,$row['status'],'body',false,$formula).'</row>';
        }
        $xml.='<row r="'.$last.'" ht="13.5" customHeight="1">';
        for ($i=0;$i<17;$i++) {
            if (in_array($i,[7,8,9],true)) {
                $field=[7=>'total',8=>'available',9=>'breakdown'][$i];
                $column=chr(65+$i);
                $formula=$last>2 ? 'SUM('.$column.'2:'.$column.($last-1).')' : null;
                $xml.=$cell($i,$last,$totals[$field]/86400,'totalDuration',true,$formula);
            } elseif ($i===13) $xml.=$cell($i,$last,'PROMEDIO','averageLabel');
            elseif ($i===14 || $i===15) {
                $value=$report['summary'][$i===14 ? 'real' : 'target'];
                $column=chr(65+$i);
                $xml.=$cell($i,$last,$value===null ? 'PENDIENTE' : $value/100,$i===14 ? 'average' : 'averageTarget',
                    $value!==null,$value!==null && $last>2 ? 'AVERAGE('.$column.'2:'.$column.($last-1).')' : null);
            } elseif ($i===16) {
                $formula=$report['summary']['status']==='PENDIENTE' ? null : 'IF(O'.$last.'>=P'.$last.',"CUMPLE","NO CUMPLE")';
                $xml.=$cell($i,$last,$report['summary']['status'],'result',false,$formula);
            } else $xml.=$cell($i,$last,'','body');
        }
        $xml.='</row></sheetData>';
        if ($last>2) {
            $xml.='<conditionalFormatting sqref="O2:O'.$last.'">'
                .'<cfRule type="dataBar" priority="1"><dataBar showValue="1" minLength="0" maxLength="100">'
                .'<cfvo type="num" val="0"/><cfvo type="num" val="1"/><color rgb="FF63BE7B"/>'
                .'</dataBar></cfRule></conditionalFormatting>';
        }
        $period=date('d/m/Y',$report['period']['start']).' al '.date('d/m/Y',$report['period']['end']-1);
        return $xml.'<printOptions horizontalCentered="1"/><pageMargins left="0.2" right="0.2" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>'
            .'<pageSetup paperSize="8" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'<headerFooter><oddHeader>'.self::escape('&CBono mantención | '.$period)
            .'</oddHeader><oddFooter>'.self::escape('&RPágina &P de &N').'</oddFooter></headerFooter></worksheet>';
    }
}
