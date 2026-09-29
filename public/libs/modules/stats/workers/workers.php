<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2021 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_prodwrk";
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
   $_SESSION[$_sesmodulename]["sql_planta_id"]     = (int)$_REQUEST["sql_planta_id"];
   $_SESSION[$_sesmodulename]["sql_wrk_id"]        = (int)$_REQUEST["sql_wrk_id"];
   $_SESSION[$_sesmodulename]["sql_viewmode"]      = (int)$_REQUEST["sql_viewmode"];
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
if(!(int)$_SESSION[$_sesmodulename]["sql_planta_id"])
   $_SESSION[$_sesmodulename]["sql_planta_id"] = $plantas[0]["id"];

//----------------------------------------------------------------------------------
$sql = " select t1.id, t1.wrk_firstname, t1.wrk_crtdat, t1.wrk_lastname, t1.wrk_rut,
                t1.wrk_folio, t1.wrk_turno_state, t2.type_name
         FROM workers t1
         LEFT OUTER JOIN workers_types t2 ON t1.wrk_cargoid = t2.id
         WHERE
         t1.wrk_status           > 0 and
         t1.wrk_turno_state      = 1
         order by t1.wrk_firstname, t1.wrk_lastname";
$selworkers = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select t1.id, t1.wrk_firstname, t1.wrk_crtdat, t1.wrk_lastname, t1.wrk_rut,
                t1.wrk_folio, t1.wrk_turno_state, t2.type_name, t1.wrk_email
         FROM workers t1
         LEFT OUTER JOIN workers_types t2 ON t1.wrk_cargoid = t2.id
         WHERE
         t1.wrk_status           > 0 and
         t1.wrk_turno_state      = 1 ";
if((int)$_SESSION[$_sesmodulename]["sql_wrk_id"])
   $sql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_wrk_id"]} ";
$sql .= " order by t1.wrk_firstname, t1.wrk_lastname";
$workers = $CON->select($sql);


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
         t1.assign_planta_id  = {$_SESSION[$_sesmodulename]["sql_planta_id"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_wrk_id"])
   $sql .= " and t1.assign_worker_id = {$_SESSION[$_sesmodulename]["sql_wrk_id"]} ";
$sql .= " order by t1.assign_stamp, t1.ass_init_hour, t1.ass_init_min, t1.ass_end_hour, t1.ass_end_min";
$assdata = $CON->select($sql);
foreach($assdata AS $assdatarow)
{
   $idx = $assdatarow["assign_worker_id"];
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
if((int)$_SESSION[$_sesmodulename]["sql_wrk_id"])
   $sql .= " and t1.cfi_workerid = {$_SESSION[$_sesmodulename]["sql_wrk_id"]} ";
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
if((int)$_SESSION[$_sesmodulename]["sql_viewmode"] == 0)
{
   $_TEMP = Array();
   foreach($workers AS $worker)
   {
      $data = $_DATA[$worker["id"]];
      if(count($data) && $data != false)
      {
         $key  = array_keys($data);
         $key  = $key[0];
         $data = $data[$key];

         unset($row);
         if(count($_INCS[$worker["id"]]) > 0)
         {
            $key        = array_keys($_INCS[$worker["id"]]);
            $key        = $key[0];
            $row["INC"] = $_INCS[$worker["id"]][$key]["inc_name"];
         }

         $row["WRK"] = $worker;
         $row["ROW"] = $data;
         $_TEMP[]    = $row;
      }
   }

   $_DATA = $_TEMP;
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
      <input type="hidden" name="sendmail" value="">
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
            <select class="text" name="sql_planta_id" id="sql_planta_id" style="width:100%"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach ($plantas as $planta)
               {  ?>
                  <option value="<?=$planta["id"]?>" <?php if($planta["id"] == $_SESSION[$_sesmodulename]["sql_planta_id"]) echo "selected"?>>
                     <?=$planta["planta_name"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Trabajador</td>
         <td class="content_row">
            <select class="text" name="sql_wrk_id" id="sql_wrk_id" style="width:100%"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($selworkers as $worker)
               {  ?>
                  <option value="<?=$worker["id"]?>" <?php if($worker["id"] == $_SESSION[$_sesmodulename]["sql_wrk_id"]) echo "selected"?>>
                     <?=$worker["wrk_firstname"]?> <?=$worker["wrk_lastname"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Vista</td>
         <td class="content_row">
            <input type="radio" name="sql_viewmode" value="0"
            <?php if($_SESSION[$_sesmodulename]["sql_viewmode"] == 0) echo "checked"?>> Semanal, no agrupado
            <input type="radio" name="sql_viewmode" value="1"
            <?php if($_SESSION[$_sesmodulename]["sql_viewmode"] == 1) echo "checked"?>> Fechas, agrupado por trabajador
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td align="left" width="1" style="padding-right:5px">
                  <?php
                  if(count($_DATA) > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left" width="1" style="padding-right:5px">
                  <?php
                  if(count($_DATA) > 0)
                  {
                     printButton("Generar XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
               <?php
               if($_SESSION[$_sesmodulename]["sql_viewmode"] == 1 && count($_DATA) > 0)
               {
                  printButton("Enviar turnos a los trabajadores", "postnav", "javascript: deactivateFormChange()", "if(askDel('')) { document.xform_itemsearch.sendmail.value='1';submitForm(document.xform_itemsearch); } ", "envelope", 130);
                  $_SESSION["_SUBMITBTN"] = 1;
               }
               ?>
               </td>
               <?php
               if((int)$_SESSION[$_sesmodulename]["search_active"])
               {  ?>
                  <td align="right" width="1" style="padding-right:5px">
                  <?php
                  printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                  ?>
                  </td>
                  <?php
               }
               ?>
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
      if((int)$_SESSION[$_sesmodulename]["sql_viewmode"] == 0)
      {  ?>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="60">
            <col width="60">
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
         </colgroup>
         <tr>
            <td class="content_tbl_header content_row_os" colspan="8">
               Turno planta <?=date("d.m.Y", $sql_datefrom)?> al <?=date("d.m.Y", $sql_dateto)?>
            </td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os" align="center">Entrada</td>
            <td class="content_tbl_subheader content_row_os" align="center">Salida</td>
            <td class="content_tbl_subheader content_row_os">Nombres</td>
            <td class="content_tbl_subheader content_row_os">Apellidos</td>
            <td class="content_tbl_subheader content_row_os">RUT</td>
            <td class="content_tbl_subheader content_row_os">Máquina</td>
            <td class="content_tbl_subheader content_row_os" align="center">Incidencia</td>
            <td class="content_tbl_subheader content_row_os" align="left">Comentarios</td>
         </tr>
         <?php
         for($x = 0; $x < count($_DATA) && $_DATA != false; $x++)
         {
            $datestm = (int)date("N", $_DATA[$x]["ROW"]["assign_stamp"]);
            $datestr = date("d.m.Y", $_DATA[$x]["ROW"]["assign_stamp"]);

            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os" align="center"><?=sprintf("%02s", $_DATA[$x]["ROW"]["ass_init_hour"])?>:<?=sprintf("%02s", $_DATA[$x]["ROW"]["ass_init_min"])?></td>
               <td class="content_row_os" align="center"><?=sprintf("%02s", $_DATA[$x]["ROW"]["ass_end_hour"])?>:<?=sprintf("%02s", $_DATA[$x]["ROW"]["ass_end_min"])?></td>
               <td class="content_row_os"><?=$_DATA[$x]["WRK"]["wrk_firstname"]?></td>
               <td class="content_row_os"><?=$_DATA[$x]["WRK"]["wrk_lastname"]?></td>
               <td class="content_row_os"><?=$_DATA[$x]["WRK"]["wrk_rut"]?></td>
               <td class="content_row_os"><?=$_DATA[$x]["ROW"]["equipo_name"]?></td>
               <td class="content_row_os" align="center"><?=$_DATA[$x]["INC"]?>&nbsp;</td>
               <td class="content_row_os"><?=$_DATA[$x]["ROW"]["ass_comments"]?>&nbsp;</td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["ass_init_hour"]        = sprintf("%02s", $_DATA[$x]["ROW"]["ass_init_hour"]).":".sprintf("%02s", $_DATA[$x]["ROW"]["ass_init_min"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["ass_end_hour"]         = sprintf("%02s", $_DATA[$x]["ROW"]["ass_end_hour"]).":".sprintf("%02s", $_DATA[$x]["ROW"]["ass_end_min"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["wrk_firstname"]        = $_DATA[$x]["WRK"]["wrk_firstname"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["wrk_lastname"]         = $_DATA[$x]["WRK"]["wrk_lastname"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["wrk_rut"]              = $_DATA[$x]["WRK"]["wrk_rut"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["equipo_name"]          = $_DATA[$x]["ROW"]["equipo_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["INC"]                  = $_DATA[$x]["INC"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["ass_comments"]         = $_DATA[$x]["ROW"]["ass_comments"];
         }
         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" align="center" colspan="8">
                  <br>
                  <b class="msg_save_err">No hay datos disponibles.</b>
                  <br><br>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?php
      }
      else
      {
         foreach($workers AS $worker)
         {
            $idx = $worker["wrk_firstname"]." ".$worker["wrk_lastname"]." | ".$worker["wrk_rut"]." | ".$worker["type_name"];
            ?>
            <?=Nifty_printH("box1", "980")?>
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="80">
               <col width="40">
               <col width="60">
               <col width="60">
               <col>
               <col width="120">
               <col width="180">
            </colgroup>
            <tr>
               <td class="content_tbl_header content_row_os" colspan="7">
                  <img src="/images/menu/icons/user.png" style="vertical-align:bottom">
                  <?=$worker["wrk_firstname"]?> <?=$worker["wrk_lastname"]?> | <?=$worker["wrk_rut"]?> | <?=$worker["type_name"]?>
               </td>
            </tr>
            <tr>
               <td class="content_tbl_subheader content_row_os" align="center">Fecha</td>
               <td class="content_tbl_subheader content_row_os" align="center">Dia</td>
               <td class="content_tbl_subheader content_row_os" align="center">Entrada</td>
               <td class="content_tbl_subheader content_row_os" align="center">Salida</td>
               <td class="content_tbl_subheader content_row_os">Máquina</td>
               <td class="content_tbl_subheader content_row_os" align="center">Incidencia</td>
               <td class="content_tbl_subheader content_row_os" align="left">Comentarios</td>
            </tr>
            <?php
            $data = $_DATA[$worker["id"]];
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
                  <td class="content_row_os" style="<?=$trcss?>"><?=$data[$x]["equipo_name"]?></td>
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
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx][$x]["datestr"]        = $datestr;
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx][$x]["datestm"]        = substr($_LANG["MODULE"]["CAL"][(11 + $datestm)],0,2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx][$x]["ass_init_hour"]  = sprintf("%02s", $data[$x]["ass_init_hour"]).":".sprintf("%02s", $data[$x]["ass_init_min"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx][$x]["ass_end_hour"]   = sprintf("%02s", $data[$x]["ass_end_hour"]).":".sprintf("%02s", $data[$x]["ass_end_min"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx][$x]["equipo_name"]    = $data[$x]["equipo_name"];
               if((int)$_INCS[$data[$x]["assign_worker_id"]][$datestr]["id"])
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx][$x]["inc_name"] = $_INCS[$data[$x]["assign_worker_id"]][$datestr]["inc_name"];

               $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx][$x]["ass_comments"] = $data[$x]["ass_comments"];
               
               $last_datestr = $datestr;
            }
            if(!$x)
            {  ?>
               <tr bgcolor="<?=getRowColor(0)?>">
                  <td class="content_row" align="center" colspan="7">
                     <br>
                     <b class="msg_save_err">No hay datos disponibles.</b>
                     <br><br>
                  </td>
               </tr>
               <?php
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
if((int)$_REQUEST["sendmail"])
{
   foreach($workers AS $worker)
   {
      if(strpos($worker["wrk_email"], "@") !== false)
      {
         $idx = $worker["wrk_firstname"]." ".$worker["wrk_lastname"]." | ".$worker["wrk_rut"]." | ".$worker["type_name"];
         if(count($_SESSION["STATS"][$_sesmodulename]["DATA"][$idx]))
         {
            $title = "Jornada: Semana ".date("d.m.Y", $sql_datefrom)." - ".date("d.m.Y", $sql_dateto);

            $postxt = "";
            foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$idx] AS $row)
            {
               $postxt .= "<tr>
                              <td style='border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666' align='center'>{$row["datestr"]}</td>
                              <td style='border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666' align='center'>{$row["datestm"]}</td>
                              <td style='border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666' align='center'>{$row["ass_init_hour"]}</td>
                              <td style='border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666' align='center'>{$row["ass_end_hour"]}</td>
                              <td style='border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666' align='left'>{$row["equipo_name"]}</td>
                              <td style='border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#666666' align='center'>{$row["inc_name"]}</td>";
               $postxt .= "</tr>";
      
            }

            $headtxt = "<tr>
                           <td width=80 style='background-color:#00A9A6;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#FFFFFF' align='center'><b>Fecha</b></td>
                           <td width=60 style='background-color:#00A9A6;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#FFFFFF' align='center'><b>Dia</b></td>
                           <td width=70 style='background-color:#00A9A6;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#FFFFFF' align='center'><b>Entrada</b></td>
                           <td width=70 style='background-color:#00A9A6;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#FFFFFF' align='center'><b>Salida</b></td>
                           <td style='background-color:#00A9A6;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#FFFFFF' align='left'><b>Máquina</b></td>
                           <td width=170 style='background-color:#00A9A6;border-left:1px solid #CCCCCC;border-bottom:1px solid #CCCCCC;font-size:12px;font-family:Arial;color:#FFFFFF' align='center'><b>Incidencia</b></td>
                        </tr>";
             
            $body  = "<html>
                      <body leftmargin='0' marginwidth='0' topmargin='0' marginheight='0' rightmargin='0' offset='0'>
                      <center>
                      <table width='800' cellpadding='3' cellspacing='0' border='0' style='border-right:1px solid #CCCCCC;border-top:1px solid #CCCCCC;'>
                      {$headtxt}
                      {$postxt}
                      </table>
                      <br>";

            sendExternalMail($title, $body, $worker["wrk_email"], $worker["wrk_firstname"]." ".$worker["wrk_lastname"]);
         }
      }
   }
   ?>
   <script language="JavaScript">
      alert("LOS EMAILS FUERON ENVIADOS EXITOSAMENTE");
   </script>
   <?php
}

//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsProdJornadas($CON);

if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsProdJornadas($CON);

if($pdffile != "")
{
   $doctitle = "Personal-Jornadas-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
?>
<?php
if($xlsfile != "")
{
   $doctitle = "Personal-Jornadas-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>