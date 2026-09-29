
<?php
$_sesmodulename         = "stats_colacion";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "";
$_sortlinks             = Array();


unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_month1"]          = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]          = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]           = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]           = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_selmode"]         = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_date"]            = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_dateto"]          = trim($_REQUEST["sql_dateto"]);
   $_SESSION[$_sesmodulename]["sql_datefrom"]        = trim($_REQUEST["sql_datefrom"]);
   $_SESSION[$_sesmodulename]["sql_xstate"]          = (int)$_REQUEST["sql_xstate"];
   $_SESSION[$_sesmodulename]["sql_plantaid"]        = (int)$_REQUEST["sql_plantaid"];
   $_SESSION[$_sesmodulename]["sql_equipotypeid"]    = (int)$_REQUEST["sql_equipotypeid"];
   $_SESSION[$_sesmodulename]["sql_equipoid"]        = (int)$_REQUEST["sql_equipoid"];
   $_SESSION[$_sesmodulename]["sql_customer"]        = (int)$_REQUEST["sql_customer"];    
   $_SESSION[$_sesmodulename]["sql_number"]          = trim(addslashes($_REQUEST["sql_number"]));
   $_SESSION[$_sesmodulename]["sql_fab_design_name"] = trim(addslashes($_REQUEST["sql_fab_design_name"]));
   $_SESSION[$_sesmodulename]["page"]                = 0;
   $_SESSION[$_sesmodulename]["search_active"]       = 1;
}

//----------------------------------------------------------------------------------
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 1;

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);
//
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;
//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y')-10;
   $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y');
}
if($_SESSION[$_sesmodulename]["sql_date"] == "")
   $_SESSION[$_sesmodulename]["sql_date"] = date('d.m.Y');
if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
{
   $_SESSION[$_sesmodulename]["sql_datefrom"] = date('d.m.Y', time() - (86400 * 7));
   $_SESSION[$_sesmodulename]["sql_dateto"]   = date('d.m.Y');
}

/* -------------------------------------------------------------------------------------*/
$sql = "select * from parametros where tabla ='PROCESOBOBIN' and codigo = '1'";
$procesobobina = $CON->select($sql);
$procesobobina = $procesobobina[0];

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_selmode"] == 1)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 2)
{
   $sql_datefrom  = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], 1, $_SESSION[$_sesmodulename]["sql_year2"]);
   $datedays      = date('t', $sql_dateto);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], $datedays, $_SESSION[$_sesmodulename]["sql_year2"]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 3)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_datefrom"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_dateto"]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}

//----------------------------------------------------------------------------------
$plantas = getPlantas($CON);
if(!(int)$_SESSION[$_sesmodulename]["sql_plantaid"])
   $_SESSION[$_sesmodulename]["sql_plantaid"] = $plantas[0]["id"];
    $sql = "select distinct pwot.id   
               ,pwot.wok_crtdat    
               ,pwot.wok_enddat
               ,a.ctr_ctrusr
               ,a.ctr_ctrdat 
               ,pwoe.evt_crtdat
               ,pwoe.evt_enddat
               ,u.wrk_rut 
               ,u.wrk_firstname 
               ,u.wrk_lastname 
               ,u.id as IdCliente 
               ,t3.win_equipoid
               ,t7.*
               ,t8.*
               ,t9.*
         from prod_worker_ot pwot
         	inner join prod_worker_ot_autocontrol as a on a.ctr_init_id = pwot.id and a.ctr_type = 'worker' 
            inner join prod_worker_ot_events as pwoe ON pwot.id = pwoe.evt_prod_worker_otid and evt_type = 'pause' and evt_pause_id = '1'
            left join workers u on u.wrk_uid = a.ctr_ctrusr 
            inner join prod_worker_init t3 ON pwot.wok_init_id = t3.id 
            left outer join equipo t7 ON t3.win_equipoid = t7.id 
            left outer join equipo_type t8 ON t7.equipo_type_id = t8.id 
            inner join prod_agenda t2 ON pwot.wok_ag_id = t2.id
            inner join orders t9 ON t2.ag_reqid = t9.id 
         where pwot.wok_crtdat between {$sql_datefrom} and {$sql_dateto} ";
   
if($_SESSION[$_sesmodulename]["sql_number"] != "")
   $sql .= " and t9.req_number = '{$_SESSION[$_sesmodulename]["sql_number"]}' ";
//----------------------------------------------------------------------------------

if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $sql .= " and t9.req_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
 
$sql .=" order by 1";
$data = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select *
         from equipo_type
         where
         type_ant_status > 0 and
         type_ant_prod_dabl = 0
         order by type_ant_title";
$selequipotypes = $CON->select($sql);
$temp = Array();
for($x = 0; $x < count($selequipotypes) && $selequipotypes != false; $x++)
{
   $sql = " select *
            from equipo
            where
            equipo_status     > 0 and
            equipo_type_id    = {$selequipotypes[$x]["id"]} and
            equipo_prod_dabl = 0
            order by equipo_name";
   $equipos = $CON->select($sql);
   if(count($equipos) && $equipos != false)
   {
      $_SELEQUIPOTYPES[$selequipotypes[$x]["id"]] = $selequipotypes[$x]["type_ant_title"];

      foreach($equipos AS $equipo)
      {
         if((int)$_SESSION[$_sesmodulename]["sql_equipotypeid"] && (int)$_SESSION[$_sesmodulename]["sql_equipotypeid"] == $equipo["equipo_type_id"])
         {
            $_SELEQUIPOS[$equipo["id"]] = $equipo["equipo_name"];
         }
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.id, t1.equipo_name
         from equipo t1
         where
         t1.equipo_status > 0 and
         t1.equipo_planta_id = {$_SESSION[$_sesmodulename]["sql_plantaid"]} and
         t1.equipo_prod_dabl = 0 ";
if((int)$_SESSION[$_sesmodulename]["sql_equipotypeid"])
   $sql .= " and t1.equipo_type_id = {$_SESSION[$_sesmodulename]["sql_equipotypeid"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_equipoid"])
   $sql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_equipoid"]} ";
$sql .= " order by 2";
$equipos = $CON->select($sql);

//----------------------------------------------------------------------------------
$plantas = getPlantas($CON);
if(!(int)$_SESSION[$_sesmodulename]["sql_plantaid"])
   $_SESSION[$_sesmodulename]["sql_plantaid"] = $plantas[0]["id"];

//----------------------------------------------------------------------------------
?>
<div style="display:none">
   <select class="text" name="sql_plantaid" id="sql_plantaid" style="width:100%"
   onmousedown="markfield(this,0)" onblur="markfield(this,1)"
   onchange="jqLoadPlantaEquipoTypes(this.value)">
      <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
      <?php
      foreach ($plantas as $planta)
      {  ?>
         <option value="<?=$planta["id"]?>" <?php if($planta["id"] == $_SESSION[$_sesmodulename]["sql_plantaid"]) echo "selected"?>>
            <?=$planta["planta_name"]?>
         </option>
         <?php
      }
      ?>
   </select>
</div>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Informe de Colación</b></td>
   <td align="right" class="content_row_clear">&nbsp;</td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%"> 
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="80">
         <col>
         <col width="80">
         <col width="400">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl"><nobr>Periodo de Produccion *</nobr></td>
         <td class="content_row">
            <table border="0" class="content_table" cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="70">
                  <nobr>
                  <input type="radio" name="sql_selmode" value="2"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 2) echo "checked"?>> Meses
                  </nobr>
               </td>
               <td class="content_row_clear" width="205" id="idx_selmode2" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 2) echo "style='display:none'"?>>
                  <nobr>
                  <select class="text" name="sql_month1" id="sql_month1"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     for($x = 1; $x <= 12; $x++)
                     {
                        $dsp_month = $x;
                        if($dsp_month < 10)
                           $dsp_month = "0{$dsp_month}";
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_month1"]) echo "selected" ?>><?=$dsp_month?></option>
                        <?php
                     }
                     ?>
                  </select>
                  <select class="text" name="sql_year1" id="sql_year1"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     $startyear  = date('Y') -10;
                     $endyear    = date('Y');

                     for($x = $startyear; $x <= $endyear; $x++)
                     {
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_year1"]) echo "selected" ?>><?=$x?></option>
                        <?php
                     }
                     ?>
                  </select>
                  &nbsp;-&nbsp;
                  <select class="text" name="sql_month2" id="sql_month2"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     for($x = 1; $x <= 12; $x++)
                     {
                        $dsp_month = $x;
                        if($dsp_month < 10)
                           $dsp_month = "0{$dsp_month}";
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_month2"]) echo "selected" ?>><?=$dsp_month?></option>
                        <?php
                     }
                     ?>
                  </select>
                  <select class="text" name="sql_year2" id="sql_year2"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     $startyear  = date('Y') -10;
                     $endyear    = date('Y');

                     for($x = $startyear; $x <= $endyear; $x++)
                     {
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_year2"]) echo "selected" ?>><?=$x?></option>
                        <?php
                     }
                     ?>
                  </select>
                  </nobr>
               </td>
               <td class="content_row_clear" width="50">
                  <nobr>
                  <input type="radio" name="sql_selmode" value="1"
                  onclick="document.getElementById('idx_selmode1').style.display='';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 1) echo "checked"?>> Dia
                  </nobr>
               </td>
               <td class="content_row_clear" width="110" id="idx_selmode1" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 1) echo "style='display:none'"?>>
                  <nobr>
                  <input type="text" style="width:80px" id="sql_date" name="sql_date" 
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date"]?>">
                  </nobr>
               </td>
               <td class="content_row_clear" width="75">
                  <nobr>
                  <input type="radio" name="sql_selmode" value="3"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 3) echo "checked"?>> Periodo
                  </nobr>
               </td>
               <td class="content_row_clear" width="180" id="idx_selmode3" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 3) echo "style='display:none'"?>>
                  <nobr>
                  <input type="text" style="width:65px" id="sql_datefrom" name="sql_datefrom"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_datefrom"]?>"> 
                  -
                  <input type="text" style="width:65px" id="sql_dateto" name="sql_dateto"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_dateto"]?>">
                  </nobr>   
               </td>
            </tr>
            </table>
         </td>
         <td class="content_rowl">Nro CC</td>
         <td class="content_row">
            <input type="text" class="text" style="width:100%"
            name="sql_number" value="<?=$_SESSION[$_sesmodulename]["sql_number"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
         <td class="content_rowl">Diseño</td>
         <td class="content_row">
            <input type="text" class="text" style="width:375px"
            name="sql_fab_design_name" value="<?=$_SESSION[$_sesmodulename]["sql_fab_design_name"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width='132'>
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if(count($data) > 0 && $data != false)
                  {
                     printButton("Generar XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="right" style="padding-right:5px" width="1">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["search_active"])
                  {
                     printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                  }
                  ?>
               </td>
               <td align="right" width="1">
                  <?php
                  printButton("Buscar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
                  $_SESSION["_SUBMITBTN"] = 1;
                  ?>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      </form>
   </td>
</tr>
<tr>
   <td>
      <?=Nifty_printH("box1", "99%")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td colspan="12" class="content_row_os" align="center">Informacion de Colación</td>
      </tr>
      <tr>
         <td class="content_rowl content_row_os" align="center">1</td>
         <td class="content_rowl content_row_os" align="center">2</td>
         <td class="content_rowl content_row_os" align="center">3</td>
         <td class="content_rowl content_row_os" align="center">4</td>
         <td class="content_rowl content_row_os" align="center">5</td>
         <td class="content_rowl content_row_os" align="center">6</td>
         <td class="content_rowl content_row_os" align="center">7</td>
         <td class="content_rowl content_row_os" align="center">8</td>
         <td class="content_rowl content_row_os" align="center">9</td>
         <td class="content_rowl content_row_os" align="center">10</td>
         <td class="content_rowl content_row_os" align="center">11</td>
      </tr>
      <tr>
         <!-- 1  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Fecha Hora Inicio Turno</nobr></td>
         <!-- 2  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Fecha Hora Fin Turno</nobr></td>
         <!-- 3  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Código Turno</nobr></td>
         <!-- 4  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Nombre Maquina</nobr></td>
         <!-- 5  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Rut Colaborador</nobr></td>
         <!-- 6  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Codigo Colaborador</nobr></td>
         <!-- 7  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Nombre Colaborador</nobr></td>
         <!-- 8  --> <td rowspan="2" class="content_rowl content_row_os" align="center">Fecha Hora Inicio Colación</nobr></td>
         <!-- 9 --> <td rowspan="2" class="content_rowl content_row_os" align="center">Fecha Fin Inicio Colacion</nobr></td>
         <!-- 10 --> <td rowspan="2" class="content_rowl content_row_os" align="center">Total Tiempo Colacion</nobr></td>
         <!-- 11 --> <td rowspan="2" class="content_rowl content_row_os" align="center">Estado</nobr></td>
      </td>
      <tr>
      </tr>
      <?php
      for($x = 0; $x < count($data) && $data != false; $x++)
      {         
         /* realizar calculos */
         $estado        = "En curso";
         $fecha_fin     = " ";
         $minutospause  = " ";
         $dia           = date("d",$data[$x]["evt_crtdat"]);
         $mes           = date("m",$data[$x]["evt_crtdat"]);
         $ano           = date("Y",$data[$x]["evt_crtdat"]);
         if( $data[$x]["evt_enddat"] > 0)
         {
            $fecha_fin = $data[$x]["evt_enddat"];
            $estado    = "Terminado";

            $time_diff  = $data[$x]["evt_enddat"] - $data[$x]["evt_crtdat"];
            $time_diffx = $time_diff / 60;
            $hours_diff = (int)($time_diffx / 60);
            $min_diff   = (int)($time_diffx - ($hours_diff * 60));
            $_RET["_PAUSE_AMOUNT"]++;
            $_RET["_PAUSE_TIME_MINS"] += $time_diffx;
            $minutospause = ($hours_diff * 60 ) + $min_diff;
         }

         $sql = "select type_name
                        ,type_name_short 
                  from turnos_config_assign tca
                     inner join turnos_types tt on tt.id = tca.assign_turno_type_id
                     where assign_worker_id = 19 
                     and assign_year = {$ano}
                     and assign_month = {$mes}
                     and assign_day = {$dia}";

         $jornadas = $CON->select($sql);
         $jornadas = $jornadas[0];

         /* fin de los calculos */
         ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <!-- 01 -->   <td class="content_row_os"><nobr><?=date("d/m/Y H:i",$data[$x]["wok_crtdat"])?></nobr></td>  
            <?php
            if ($data[$x]["wok_enddat"] < strtotime('1970-01-02'))
            {
            ?>
               <!-- 02 -->   <td class="content_row_os"></td>  
            <?php
            }
            else
            {
            ?>
                <!-- 02 -->   <td class="content_row_os"><nobr><?=date("d/m/Y H:i",$data[$x]["wok_enddat"])?></nobr></td>  
            <?php
            }
            ?>
            <!-- 03 -->   <td class="content_row_os"><nobr><?=$jornadas["type_name_short"]?></nobr></td> 
            <!-- 04 -->   <td class="content_row_os"><nobr><?=$data[$x]["equipo_name"]?></nobr></td>
            <!-- 05 -->   <td class="content_row_os"><nobr><?=$data[$x]["wrk_rut"]?></nobr></td>
            <!-- 06 -->   <td class="content_row_os"><nobr><?=$data[$x]["IdCliente"]?></nobr></td>
            <!-- 07 -->   <td class="content_row_os"><nobr><?=$data[$x]["wrk_firstname"].' '.$data[$x]["wrk_lastname"]?></nobr></td>
            <!-- 08 -->   <td class="content_row_os"><nobr><?=date("d/m/Y H:i",$data[$x]["evt_crtdat"])?></nobr></td>
            <!-- 09 -->   <td class="content_row_os"><nobr><?=date("d/m/Y H:i",$fecha_fin)?></nobr></td>
            <?php
                $inicio   = $data[$x]["evt_crtdat"];   
                $termino = $fecha_fin; 
                $diferencia_segundos = $termino - $inicio;
                $horas   = floor($diferencia_segundos / 3600);
                $minutos = floor(($diferencia_segundos % 3600) / 60);
                $hhmm = str_pad($horas, 2, "0", STR_PAD_LEFT) . ":" . str_pad($minutos, 2, "0", STR_PAD_LEFT);
            ?>
            <!-- 10 -->   <td class="content_row_os"><nobr><?=$hhmm?></nobr></td>
            <!-- 11 -->  <td class="content_row_os"><nobr><?=$estado?></td>  
            </tr>
         <?php

         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FechaHoraInicioTurno"]    = $data[$x]["wok_crtdat"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FechaHoraFinTurno"]       = $data[$x]["wok_enddat"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CódigoTurno"]             = $jornadas["type_name_short"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NombreMaquina"]           = $data[$x]["equipo_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["RutColaborador"]          = $data[$x]["wrk_rut"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CodigoColaborador"]       = $data[$x]["IdCliente"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NombreColaborador"]       = $data[$x]["wrk_firstname"].' '.$data[$x]["wrk_lastname"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FechaHoraInicioColación"] = $data[$x]["evt_crtdat"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FechaFinInicioColacion"]  = $fecha_fin;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TotalTiempoColacion"]     = $hhmm;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["Estado"]                  = $estado;

      }
      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="11" align="center">
               <br>
               <b class="msg_save_err">No hay datos disponibles.</b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?php
      ?>
      <?=Nifty_printF()?>
      <br>
   </td>
</tr>
</table>
</form>
<?php

if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsColacion($CON);

if($xlsfile != "")
{
   $doctitle = "RporteColacionPlanta-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php
$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';
?>
