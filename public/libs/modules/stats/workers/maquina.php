<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2021 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_prodmaq";
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
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_xstate"]        = (int)$_REQUEST["sql_xstate"];
   $_SESSION[$_sesmodulename]["sql_plantaid"]      = (int)$_REQUEST["sql_plantaid"];
   $_SESSION[$_sesmodulename]["sql_equipotypeid"]  = (int)$_REQUEST["sql_equipotypeid"];
   $_SESSION[$_sesmodulename]["sql_equipoid"]      = (int)$_REQUEST["sql_equipoid"];
   $_SESSION[$_sesmodulename]["sql_custname"]      = trim(addslashes($_REQUEST["sql_custname"]));
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
}

//----------------------------------------------------------------------------------
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 3;

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m',time());
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y',time());
   $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m',time());
   $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y',time());
}
if($_SESSION[$_sesmodulename]["sql_date"] == "")
   $_SESSION[$_sesmodulename]["sql_date"] = date('d.m.Y');
if($_SESSION[$_sesmodulename]["sql_date_pfrom"] == "")
{
   $_SESSION[$_sesmodulename]["sql_date_pfrom"] = date('d.m.Y', time());
   $_SESSION[$_sesmodulename]["sql_date_pto"]   = date('d.m.Y', time() + (86400 * 7));
}

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
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pfrom"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pto"]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
$plantas = getPlantas($CON);
if(!(int)$_SESSION[$_sesmodulename]["sql_plantaid"])
   $_SESSION[$_sesmodulename]["sql_plantaid"] = $plantas[0]["id"];

if((int)$_SESSION[$_sesmodulename]["sql_plantaid"])
{
   $sql = " select *
            from equipo_type
            where
            type_ant_status > 0
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
               equipo_planta_id  = {$_SESSION[$_sesmodulename]["sql_plantaid"]}
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
}

//----------------------------------------------------------------------------------
$sql = " select t1.id, t1.equipo_name
         from equipo t1
         where
         t1.equipo_status > 0 and
         t1.equipo_planta_id = {$_SESSION[$_sesmodulename]["sql_plantaid"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_equipotypeid"])
   $sql .= " and t1.equipo_type_id = {$_SESSION[$_sesmodulename]["sql_equipotypeid"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_equipoid"])
   $sql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_equipoid"]} ";
$sql .= " order by 2";
$equipos = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.turn_name, t6.type_libre_act,
                t4.cfi_inc_id, t7.equipo_name, t6.type_color_dom, t6.type_name_short,
                t6.type_color, t8.wrk_firstname, t8.wrk_lastname, t8.wrk_rut,
                t9.type_name 'cargo'
         from turnos_config_assign t1
         INNER JOIN turnos t3                            ON t1.assign_turno_id = t3.id
         LEFT OUTER JOIN turnos_config_incidencias t4    ON t1.incidencia_cfi_id = t4.id
         LEFT OUTER JOIN turnos_types t6                 ON t1.assign_turno_type_id = t6.id
         LEFT OUTER JOIN equipo t7                       ON t1.assign_equipoaid = t7.id
         LEFT OUTER JOIN workers t8                      ON t1.assign_worker_id = t8.id
         LEFT OUTER JOIN workers_types t9                ON t8.wrk_cargoid = t9.id
         LEFT OUTER JOIN equipo_type t10                 ON t7.equipo_type_id = t10.id
         where
         t1.assign_stamp      between {$sql_datefrom} and {$sql_dateto} and
         t1.assign_planta_id  = {$_SESSION[$_sesmodulename]["sql_plantaid"]}
         order by t1.assign_stamp, t1.ass_init_hour, t1.ass_init_min, t1.ass_end_hour, t1.ass_end_min";
$assdata = $CON->select($sql);
foreach($assdata AS $assdatarow)
{
   $idx = $assdatarow["assign_equipoaid"];
   $_DATA[$idx][] = $assdatarow;
}

//----------------------------------------------------------------------------------
$sql = " select t1.id, t1.cfi_startdate, t1.cfi_enddate, t1.cfi_workerid, t3.inc_name_short,
                t3.inc_name, t3.inc_color, t3.inc_color_dom
         from turnos_config_incidencias t1
         INNER JOIN incidencias t3  ON t1.cfi_inc_id = t3.id
         where
         t1.cfi_status = 2 and
         t3.inc_type   = 0 ";
$incs = $CON->select($sql);
foreach($incs AS $inc)
{
   for($x = $inc["cfi_startdate"]+3600; $x <= $inc["cfi_enddate"]; $x += 86400)
   {
      if($x >= $sql_datefrom && $x <= $sql_dateto)
      {
         $idx1 = $inc["cfi_workerid"];
         $idx2 = date("d.m.Y", $x);
         $_INCS[$idx1][$idx2] = $inc;
      }
   }
}
//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Informe personal por máquina</b></td>
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
         <td class="content_rowl">Periodo</td>
         <td class="content_row">
            <table border="0" class="content_table" cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="70">
                  <input type="radio" name="sql_selmode" value="2"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 2) echo "checked"?>> Meses
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
                     $startyear  = date('Y') -20;
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
                     $startyear  = date('Y') -20;
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
                  <input type="radio" name="sql_selmode" value="1"
                  onclick="document.getElementById('idx_selmode1').style.display='';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 1) echo "checked"?>> Dia
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
                  <input type="radio" name="sql_selmode" value="3"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 3) echo "checked"?>> Periodo
               </td>
               <td class="content_row_clear" width="180" id="idx_selmode3" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 3) echo "style='display:none'"?>>
                  <nobr>
                  <input type="text" style="width:75px" id="sql_date_pfrom" name="sql_date_pfrom"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pfrom"]?>">
                  -
                  <input type="text" style="width:75px" id="sql_date_pto" name="sql_date_pto"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pto"]?>">
                  </nobr>
               </td>
            </tr>
            </table>
         </td>
         <td class="content_rowl">Planta</td>
         <td class="content_row">
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
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo máquina</td>
         <td class="content_row">
            <select class="text" style="width:100%" name="sql_equipotypeid" id="sql_equipotypeid"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="jqLoadPlantaEquipos(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach(array_keys($_SELEQUIPOTYPES) AS $etypeid)
               {  ?>
                  <option value="<?=$etypeid?>"
                  <?php if($etypeid == $_SESSION[$_sesmodulename]["sql_equipotypeid"]) echo "selected"?>><?=$_SELEQUIPOTYPES[$etypeid]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Máquina</td>
         <td class="content_row">
            <select class="text" style="width:100%" name="sql_equipoid" id="sql_equipoid"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach(array_keys($_SELEQUIPOS) AS $eqid)
               {  ?>
                  <option value="<?=$eqid?>"
                  <?php if($eqid == $_SESSION[$_sesmodulename]["sql_equipoid"]) echo "selected"?>><?=$_SELEQUIPOS[$eqid]?></option>
                  <?php
               }
               ?>
            </select>
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
                  if(count($_DATA) > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($_DATA) > 0)
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
      <?php
      foreach($equipos AS $equipo)
      {
         if((int)count($_DATA[$equipo["id"]]))
         {
            $data = $_DATA[$equipo["id"]];
            ?>
            <?=Nifty_printH("box1", "980")?>
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="70">
               <col width="30">
               <col width="50">
               <col width="50">
               <col width="150">
               <col>
               <col width="90">
               <col width="130">
               <col width="100">
               <col width="130">
            </colgroup>
            <tr>
               <td class="content_tbl_header content_row_os" colspan="10">
                  <img src="/images/menu/icons/gear.png" style="vertical-align:bottom"> <?=$equipo["equipo_name"]?>
               </td>
            </tr>
            <tr>
               <td class="content_tbl_subheader content_row_os" align="center">Fecha</td>
               <td class="content_tbl_subheader content_row_os" align="center">Dia</td>
               <td class="content_tbl_subheader content_row_os" align="center">Entrada</td>
               <td class="content_tbl_subheader content_row_os" align="center">Salida</td>
               <td class="content_tbl_subheader content_row_os">Nombres</td>
               <td class="content_tbl_subheader content_row_os">Apellidos</td>
               <td class="content_tbl_subheader content_row_os">RUT</td>
               <td class="content_tbl_subheader content_row_os">Cargo</td>
               <td class="content_tbl_subheader content_row_os" align="center">Incidencia</td>
               <td class="content_tbl_subheader content_row_os" align="left">Comentarios</td>
            </tr>
            <?php
            for($x = 0; $x < count($data) && $data != false; $x++)
            {
               $datestm = (int)date("N", $data[$x]["assign_stamp"]);
               $datestr = date("d.m.Y", $data[$x]["assign_stamp"]);
               ?>
               <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os" align="center" style="<?=$trcss?>"><?=$datestr?></td>
                  <td class="content_row_os" align="center" style="<?=$trcss?>"><?=substr($_LANG["MODULE"]["CAL"][(11 + $datestm)],0,2)?></td>
                  <td class="content_row_os" align="center" style="<?=$trcss?>"><?=sprintf("%02s", $data[$x]["ass_init_hour"])?>:<?=sprintf("%02s", $data[$x]["ass_init_min"])?></td>
                  <td class="content_row_os" align="center" style="<?=$trcss?>"><?=sprintf("%02s", $data[$x]["ass_end_hour"])?>:<?=sprintf("%02s", $data[$x]["ass_end_min"])?></td>
                  <td class="content_row_os" style="<?=$trcss?>"><?=$data[$x]["wrk_firstname"]?></td>
                  <td class="content_row_os" style="<?=$trcss?>"><?=$data[$x]["wrk_lastname"]?></td>
                  <td class="content_row_os" style="<?=$trcss?>"><?=$data[$x]["wrk_rut"]?></td>
                  <td class="content_row_os" style="<?=$trcss?>"><?=$data[$x]["cargo"]?></td>
                  <?php
                  if((int)$_INCS[$data[$x]["assign_worker_id"]][$datestr]["id"])
                  {  ?>
                     <td class="content_row_os" align="center" style="<?=$trcss?>;background-color:<?=$_INCS[$data[$x]["assign_worker_id"]][$datestr]["inc_color"]?>">
                        <?=$_INCS[$data[$x]["assign_worker_id"]][$datestr]["inc_name"]?>
                     </td>
                     <?php
                  }
                  else
                  {  ?>
                     <td class="content_row_os" style="<?=$trcss?>">&nbsp;</td>
                     <?php
                  }
                  ?>
                  <td class="content_row_os" style="<?=$trcss?>"><?=$data[$x]["ass_comments"]?>&nbsp;</td>
                </tr>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$equipo["equipo_name"]][$x]["datestr"]        = $datestr;
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$equipo["equipo_name"]][$x]["day"]            = substr($_LANG["MODULE"]["CAL"][(11 + $datestm)],0,2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$equipo["equipo_name"]][$x]["ass_init_hour"]  = sprintf("%02s", $data[$x]["ass_init_hour"]).":".sprintf("%02s", $data[$x]["ass_init_min"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$equipo["equipo_name"]][$x]["ass_end_hour"]   = sprintf("%02s", $data[$x]["ass_end_hour"]).":".sprintf("%02s", $data[$x]["ass_end_min"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$equipo["equipo_name"]][$x]["wrk_firstname"]  = $data[$x]["wrk_firstname"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$equipo["equipo_name"]][$x]["wrk_lastname"]   = $data[$x]["wrk_lastname"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$equipo["equipo_name"]][$x]["wrk_rut"]        = $data[$x]["wrk_rut"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$equipo["equipo_name"]][$x]["cargo"]          = $data[$x]["cargo"];
               if((int)$_INCS[$data[$x]["assign_worker_id"]][$datestr]["id"])
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$equipo["equipo_name"]][$x]["incidencia"]  = $_INCS[$data[$x]["assign_worker_id"]][$datestr]["inc_name"];

               $_SESSION["STATS"][$_sesmodulename]["DATA"][$equipo["equipo_name"]][$x]["ass_comments"]   = $data[$x]["ass_comments"];
               
               $last_datestr = $datestr;
            }
            ?>
            </table>
            <?=Nifty_printF()?>
            <br>
            <?php
         }
      }
      ?>
   </td>
</tr>
</table>
</form>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsProdMaquinas($CON);

if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsProdMaquinas($CON);

if($pdffile != "")
{
   $doctitle = "Personal-por-maquina-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
?>
<?php
if($xlsfile != "")
{
   $doctitle = "Personal-por-maquina-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>