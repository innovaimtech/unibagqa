<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2021 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "mgmt_balance";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "4";
$_sesbaseordersort      = "asc";
$currtme = time();
//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_month1"]        = (int)$_REQUEST["sql_month1"];
   $_SESSION[$_sesmodulename]["sql_year1"]         = (int)$_REQUEST["sql_year1"];
   $_SESSION[$_sesmodulename]["sql_month2"]        = (int)$_REQUEST["sql_month2"];
   $_SESSION[$_sesmodulename]["sql_year2"]         = (int)$_REQUEST["sql_year2"];
   $_SESSION[$_sesmodulename]["sql_assdataact"]    = (int)$_REQUEST["sql_assdataact"];
   $_SESSION[$_sesmodulename]["sql_planta_id"]     = (int)$_REQUEST["sql_planta_id"];
   $_SESSION[$_sesmodulename]["sql_monthcompact"]  = (int)$_REQUEST["sql_monthcompact"];
   $_SESSION[$_sesmodulename]["sql_dtlviewact"]    = (int)$_REQUEST["sql_dtlviewact"];
   $_SESSION[$_sesmodulename]["sql_wrkids"]        = $_REQUEST["sql_wrkids"];
   $_SESSION[$_sesmodulename]["sql_jornids"]       = $_REQUEST["sql_jornids"];
   $_SESSION[$_sesmodulename]["sql_turnids"]       = $_REQUEST["sql_turnids"];
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
}

if($_SESSION[$_sesmodulename]["sql_month1"] < date("m") && $_SESSION[$_sesmodulename]["sql_year1"] == date("Y"))
   $_SESSION[$_sesmodulename]["sql_monthcompact"] = 1;
if($_SESSION[$_sesmodulename]["sql_year1"] < date("Y"))
   $_SESSION[$_sesmodulename]["sql_monthcompact"] = 1;
      
//----------------------------------------------------------------------------------
$_ALLTURNOS = getAllTurnos($CON, $sql_datefrom, $sql_dateto);
$_TTYPES    = getAllTtypes($CON, true);

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');
   $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y');
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

//----------------------------------------------------------------------------------
$sql_datefrom  = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
$sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
$datedays      = date('t', $sql_dateto);
$sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month1"], $datedays, $_SESSION[$_sesmodulename]["sql_year1"]);

//----------------------------------------------------------------------------------
$date_init = $sql_datefrom;
$date_end  = $sql_dateto;
for($x = $date_init+3600; $x <= $date_end; $x += 86400)
{
   $idx = date("W-m-Y", $x);
   $_WEEKS[$idx]["START_STAMP"]  = date("d.m.Y", $x);
   $_WEEKS[$idx]["END_STAMP"]    = date("d.m.Y", $x);
}

//----------------------------------------------------------------------------------
$_MIN_DOCS_SQL_DATE = 9999999999999999999999;
$_MAX_DOCS_SQL_DATE = 0;

//----------------------------------------------------------------------------------
foreach(array_keys($_WEEKS) AS $idx)
{
   $idxarr        = explode(".", $_WEEKS[$idx]["START_STAMP"]);
   $stamp         = mktime(15, 0, 0, $idxarr[1], $idxarr[0], $idxarr[2]);
   $dayofweek     = date("N", $stamp);
   $daydsc        = 7 - $dayofweek;
   $daydsc        = 6 - $daydsc;

   $startstamp    = $stamp - ($daydsc * 86400);
   $_WEEKS[$idx]["START_STAMP"] = mktime(0, 0, 0, date("m", $startstamp), date("d", $startstamp), date("Y", $startstamp));

   $end_stamp     = mktime(15, 0, 0, date("m", $startstamp), date("d", $startstamp), date("Y", $startstamp));
   $end_stamp    += (6 * 86400);
   $_WEEKS[$idx]["END_STAMP"] = mktime(23, 59, 59, date("m", $end_stamp), date("d", $end_stamp), date("Y", $end_stamp));

   for($x = $_WEEKS[$idx]["START_STAMP"]+3600; $x <= $_WEEKS[$idx]["END_STAMP"]; $x += 86400)
   {
      $dayidx = date("d.m.Y", $x);
      $_WEEK_DAYS[$idx][$dayidx] = 1;
   }

   if(!(int)$_SESSION[$_sesmodulename]["sql_monthcompact"])
   {
      if($_WEEKS[$idx]["END_STAMP"] < $currtme)
      {
         unset($_WEEKS[$idx]);
         unset($_WEEK_DAYS[$idx]);
      }
   }

   if($_WEEKS[$idx]["START_STAMP"] > 0 && $_WEEKS[$idx]["START_STAMP"] < $_MIN_DOCS_SQL_DATE)
      $_MIN_DOCS_SQL_DATE = $_WEEKS[$idx]["START_STAMP"];
   if($_WEEKS[$idx]["END_STAMP"] > 0 && $_WEEKS[$idx]["END_STAMP"] > $_MAX_DOCS_SQL_DATE)
      $_MAX_DOCS_SQL_DATE = $_WEEKS[$idx]["END_STAMP"];
}


//----------------------------------------------------------------------------------
$plantas = getPlantas($CON);
if(!(int)$_SESSION[$_sesmodulename]["sql_planta_id"])
   $_SESSION[$_sesmodulename]["sql_planta_id"] = $plantas[0]["id"];

//----------------------------------------------------------------------------------
if($_REQUEST["delexec"] != "")
{
   $_DEL_WEEK     = $_WEEKS[$_REQUEST["delexec"]];
   $startstamp    = $_DEL_WEEK["START_STAMP"];
   $end_stamp     = $_DEL_WEEK["END_STAMP"];

   $sql = " delete from turnos_config_assign
            where
            assign_planta_id  = {$_SESSION[$_sesmodulename]["sql_planta_id"]} and
            assign_stamp      between {$startstamp} and {$end_stamp}";
   $CON->no_result($sql);

   ?>
   <script language="JavaScript">
      location.href = 'index.php?mid=<?=$_REQUEST["mid"]?>';
   </script>
   <?php
   exit;
}

//----------------------------------------------------------------------------------
if($_REQUEST["cloneexec"] != "")
{
   $_CLONE_WEEK = $_WEEKS[$_REQUEST["cloneexec"]];

   $stamp               = $_CLONE_WEEK["START_STAMP"] - (3 * 86400);
   $dayofweek           = date("N", $stamp);
   $daydsc              = 7 - $dayofweek;
   $daydsc              = 6 - $daydsc;

   $startstamp          = $stamp - ($daydsc * 86400);
   $_WEEK_START_STAMP   = mktime(0, 0, 0, date("m", $startstamp), date("d", $startstamp), date("Y", $startstamp));

   $end_stamp           = mktime(15, 0, 0, date("m", $startstamp), date("d", $startstamp), date("Y", $startstamp));
   $end_stamp           += (6 * 86400);
   $_WEEKS_END_STAMP    = mktime(23, 59, 59, date("m", $end_stamp), date("d", $end_stamp), date("Y", $end_stamp));

   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from turnos_config_assign t1
            where
            t1.assign_planta_id  = {$_SESSION[$_sesmodulename]["sql_planta_id"]} and
            t1.assign_stamp      between {$_CLONE_WEEK["START_STAMP"]} and {$_CLONE_WEEK["END_STAMP"]}
            order by t1.ass_init_hour, t1.ass_init_min, t1.ass_end_hour, t1.ass_end_min";
   $currassigns = $CON->select($sql);
   foreach($currassigns AS $currassign)
      $_IGNORE_WRKS[$currassign["assign_worker_id"]] = 1;

   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from turnos_config_assign t1
            where
            t1.assign_planta_id  = {$_SESSION[$_sesmodulename]["sql_planta_id"]} and
            t1.assign_stamp      between {$_WEEK_START_STAMP} and {$_WEEKS_END_STAMP}
            order by t1.ass_init_hour, t1.ass_init_min, t1.ass_end_hour, t1.ass_end_min";
   $oldassigns = $CON->select($sql);
   foreach($oldassigns AS $oldassign)
   {
      if(!(int)$_IGNORE_WRKS[$oldassign["assign_worker_id"]])
      {
         $assign_stamp  = $oldassign["assign_stamp"] + (7 * 86400);
         $assign_day    = (int)date("d", $assign_stamp);
         $assign_month  = (int)date("m", $assign_stamp);
         $assign_year   = (int)date("Y", $assign_stamp);

         $sql = " insert into turnos_config_assign
                  (assign_planta_id, assign_day, assign_month, assign_year, assign_stamp,
                  assign_turno_id, assign_turno_type_id, assign_worker_id, assign_worker_type,
                  assign_equipoaid, ass_init_hour, ass_init_min, ass_end_hour, ass_end_min)
                  VALUES
                  ({$oldassign["assign_planta_id"]}, {$assign_day}, {$assign_month}, {$assign_year}, {$assign_stamp},
                   6, {$oldassign["assign_turno_type_id"]}, {$oldassign["assign_worker_id"]}, '{$oldassign["assign_worker_type"]}',
                   {$oldassign["assign_equipoaid"]}, {$oldassign["ass_init_hour"]}, {$oldassign["ass_init_min"]},
                   {$oldassign["ass_end_hour"]}, {$oldassign["ass_end_min"]})";
         $CON->no_result($sql);
      }
   }
   ?>
   <script language="JavaScript">
      location.href = 'index.php?mid=<?=$_REQUEST["mid"]?>';
   </script>
   <?php
   exit;
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.turn_name, t5.inc_name_short, t5.inc_color, t5.inc_color_dom, t6.type_libre_act,
                t5.inc_type, t4.cfi_inc_id, t7.equipo_name, t6.type_color_dom, t6.type_name_short,
                t6.type_color
         from turnos_config_assign t1
         INNER JOIN turnos t3                            ON t1.assign_turno_id = t3.id
         LEFT OUTER JOIN turnos_config_incidencias t4    ON t1.incidencia_cfi_id = t4.id
         LEFT OUTER JOIN incidencias t5                  ON t4.cfi_inc_id = t5.id
         LEFT OUTER JOIN turnos_types t6                 ON t1.assign_turno_type_id = t6.id
         LEFT OUTER JOIN equipo t7                       ON t1.assign_equipoaid = t7.id
         where
         t1.assign_stamp      between {$_MIN_DOCS_SQL_DATE} and {$_MAX_DOCS_SQL_DATE} and
         t1.assign_planta_id  = {$_SESSION[$_sesmodulename]["sql_planta_id"]}
         order by t3.turn_name, t7.equipo_name, t1.assign_special_slot, t1.assign_worker_id desc, t6.type_libre_act desc";
$assdata = $CON->select($sql);
foreach($assdata AS $assdatarow)
{
   $idx1 = $assdatarow["assign_worker_id"];
   $idx2 = date("d.m.Y", $assdatarow["assign_stamp"]);
   $_DATA[$idx1][$idx2][] = $assdatarow;
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
      if($x >= $_MIN_DOCS_SQL_DATE && $x <= $_MAX_DOCS_SQL_DATE)
      {
         $idx1 = $inc["cfi_workerid"];
         $idx2 = date("d.m.Y", $x);
         $_INCS[$idx1][$idx2] = $inc;
      }
   }
}

//----------------------------------------------------------------------------------
foreach(array_keys($_INCS) AS $wid)
{
   foreach(array_keys($_INCS[$wid]) AS $dayidx)
   {
      foreach(array_keys($_DATA[$wid][$dayidx]) AS $subidx)
      {
         $_DATA[$wid][$dayidx][$subidx]["type_color"]       = $_INCS[$wid][$dayidx]["inc_color"];
         $_DATA[$wid][$dayidx][$subidx]["type_color_dom"]   = $_INCS[$wid][$dayidx]["inc_color_dom"];
         $_DATA[$wid][$dayidx][$subidx]["type_name_short"]  = $_INCS[$wid][$dayidx]["inc_name_short"];
      }

      if(!count($_DATA[$wid][$dayidx]))
      {
         unset($temp);
         $temp["id"]                = 1;
         $temp["type_color"]        = $_INCS[$wid][$dayidx]["inc_color"];
         $temp["type_color_dom"]    = $_INCS[$wid][$dayidx]["inc_color_dom"];
         $temp["type_name_short"]   = $_INCS[$wid][$dayidx]["inc_name_short"];
         $_DATA[$wid][$dayidx][]    = $temp;
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " SELECT t1.id, t1.wrk_firstname, t1.wrk_crtdat, t1.wrk_lastname, t1.wrk_rut,
                t1.wrk_folio, t1.wrk_turno_state, t2.type_name
         FROM workers t1
         LEFT OUTER JOIN workers_types t2 ON t1.wrk_cargoid = t2.id
         WHERE
         t1.wrk_status           > 0 and
         t1.wrk_turno_state      = 1 and
         t1.wrk_turno_startdate  < {$_MAX_DOCS_SQL_DATE}
         order by t1.wrk_firstname, t1.wrk_lastname";
$workers = $CON->select($sql);
?>
<script language="JavaScript">
function setTurno(xidx, tid)
{
}
function reloadSummary()
{
}
function showAssistenciaOptions(xevent, slotid)
{
   var xleft = xevent.pageX -360;
   var xtop  = xevent.pageY -180;

   $.fancybox.close();
   $.fancybox('/iframe.fancy.php?mid=<?=$_REQUEST["mid"]?>&module=workerassist&slotid=' +slotid,
   {
      'autoCenter': false,
      'width'        : 300,
      'height'       : 320,
      'autoScale'    : false,
      'transitionIn' : 'none',
      'transitionOut': 'none',
      'type'         : 'iframe',
      'overlayShow'  : false,
      'scrolling'    : 'auto',
      'centerOnScroll': false,
      onStart: function() { $('#myStyleTag').html('#fancybox-wrap { opacity:0; left:' +xleft +'px !important; top:' +xtop +'px !important }'); },
      onCancel: function() { $('#myStyleTag').html(''); },
      onCleanup: function() { $('#myStyleTag').html(''); },
      onClosed: function() { $('#myStyleTag').html(''); }
   });
}
function externExecFancy(xurl, xtype, xwidth, xheight, xscroll)
{
   $.fancybox.close();
   showFancybox(xurl, xtype, xwidth, xheight, xscroll);
}
function externExecFancyReload(xurl, xtype, xwidth, xheight, xscroll)
{
   $.fancybox.close();
   setTimeout(function()
   {
      showFancyboxReload(xurl, xtype, xwidth, xheight, xscroll);
   }, 500);
}
var colprefix = 'ass';
</script>
<script language="Javascript" src="./libs/jscripts/overlib/overlib.js"></script>
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
<table border="0" cellpadding="0" cellspacing="0" width="99%">
<tr>
   <td height="30"><b class="content_header">Planificar trabajadores</b></td>
   <td align="right" class="content_row_clear"></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" name="xform_itemsearch" style="padding:0px;margin:0px">
<input type="hidden" name="subexec" value="search">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="sql_jornids" value="<?=$_SESSION[$_sesmodulename]["sql_jornids"]?>">
<input type="hidden" name="sql_turnids" value="<?=$_SESSION[$_sesmodulename]["sql_turnids"]?>">
<input type="hidden" name="sql_wrkids" value="<?=$_SESSION[$_sesmodulename]["sql_wrkids"]?>">
<input type="hidden" name="execautocalc" value="">
<input type="hidden" name="execcopymonth" value="">
<input type="hidden" name="execcreateteslots" value="">
<input type="hidden" name="cloneexec" value="">
<input type="hidden" name="delexec" value="">
<input type="hidden" name="printpdf" value="">
<input type="hidden" name="printxls" value="0">
<?=Nifty_printH("box1", "900")?>
<table cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Opciones</td>
</tr>
<tr>
   <td class="content_rowl">Seleccione Mes</td>
   <td class="content_row">
      <nobr>
      <input type="button" class="button" value="&lt;" style="width:30px;" onclick="setNextMonth(-1)">
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
         $startyear  = date('Y') -5;
         $endyear    = date('Y') +3;

         for($x = $startyear; $x <= $endyear; $x++)
         {
            ?>
            <option value="<?=$x?>"
            <?php if($x == $_SESSION[$_sesmodulename]["sql_year1"]) echo "selected" ?>><?=$x?></option>
            <?php
         }
         ?>
      </select>
      <div style="display:none">
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
         $startyear  = date('Y') -5;
         $endyear    = date('Y') +3;

         for($x = $startyear; $x <= $endyear; $x++)
         {
            ?>
            <option value="<?=$x?>"
            <?php if($x == $_SESSION[$_sesmodulename]["sql_year2"]) echo "selected" ?>><?=$x?></option>
            <?php
         }
         ?>
      </select>
      </div>
      <input type="button" class="button" value="&gt;" style="width:30px;" onclick="setNextMonth(1)">
      </nobr>
   </td>
</tr>
<tr>
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
<tr style="display:none">
   <td class="content_rowl">Asistencia</td>
   <td class="content_row">
      <span style="float:left;">
      <input type="checkbox" value="1" name="sql_assdataact"
      <?php if((int)$_SESSION[$_sesmodulename]["sql_assdataact"]) echo "checked"?>>
      Mostrar Indicadores de Asistencia
      </span>
      <?php
      if((int)$_SESSION[$_sesmodulename]["sql_assdataact"])
      {  ?>
         <nobr>
         <span style="float:left;">&nbsp;
         <img src="/images/menu/icons/tick-circle-frame.png"> Asiste&nbsp;
         <img src="/images/menu/icons/alarm-clock-blue.png"> Atraso &nbsp;
         </span>
         <div style="padding-left:2px;float:left;width:16px;height:16px;background-color:red;color:black;text-shadow:none;font-size:11px;line-height:16px">&nbsp;F</div>
         <div style="float:left;vertical-align:bottom;margin-top:3px">&nbsp;Falta</div>
         </nobr>
         <?php
      }
      ?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Datos del mes</td>
   <td class="content_row">
      <span style="float:left;">
      <input type="checkbox" value="1" name="sql_monthcompact"
      <?php if((int)$_SESSION[$_sesmodulename]["sql_monthcompact"]) echo "checked"?>>
      Mostrar el mes completo (incluyendo semanas pasadas)
      </span>
   </td>
</tr>
<tr>
   <td class="content_rowl">Datos de máquinas</td>
   <td class="content_row">
      <span style="float:left;">
      <input type="checkbox" value="1" name="sql_dtlviewact"
      <?php if((int)$_SESSION[$_sesmodulename]["sql_dtlviewact"]) echo "checked"?>>
      Mostrar detalles de asignaciones a las máquinas
      </span>
   </td>
</tr>
<tr>
   <td class="content_row" align="right" colspan="2">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td align="right" style="padding-right:3px" valign="top">
            <?php
            if((int)$_SESSION[$_sesmodulename]["search_active"])
               printButton("Eliminar Filtros", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
            ?>
         </td>
         <td align="right" width="1" valign="top">
            <?php
            printButton("Actualizar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
            $_SESSION["_SUBMITBTN"] = 1;
            ?>
         </td>
      </tr>
      </table>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("box1 noframe", "' style='min-width:99%")?>
<table cellpadding="2" cellspacing="0" style="min-width:100%" id="idx_maintable">
<tr>
   <td class="content_tbl_header content_row_os" colspan="4" style="background-color:#CBE4FF;border-left:1px solid #CCCCCC">DATOS TRABAJADOR</td>
   <?php
   $x = 0;
   foreach(array_keys($_WEEKS) AS $midx)
   {
      $bcss = "border-left:2px solid #333333;background-color:#AAAAAA;";
      if($currtme >= $_WEEKS[$midx]["START_STAMP"] && $currtme <= $_WEEKS[$midx]["END_STAMP"])
         $bcss = "border-left:2px solid #333333;background-color:#00A9A6;";
      ?>
      <td class="content_tbl_header content_row_os" align="center" style="<?=$bcss?>;padding:2px;font-weight:bold;" colspan="7">
         <?=date("d.m.y", $_WEEKS[$midx]["START_STAMP"])?>-<?=date("d.m.y", $_WEEKS[$midx]["END_STAMP"])?>

         
         <img src="/images/menu/icons/applications.png" style="cursor:pointer;float:right"
         onclick="if(askDel('')) { document.xform_itemsearch.cloneexec.value='<?=$midx?>';document.xform_itemsearch.submit(); } ">
         <img src="/images/menu/icons/cross-circle-frame.png" style="cursor:pointer;float:right;padding-right:5px"
         onclick="if(confirm('Estas seguro, que quieres eliminar la semana?')) { deactivateFormChange();document.xform_itemsearch.delexec.value='<?=$midx?>';document.xform_itemsearch.submit(); } ">
      </td><?php
      $x++;
   }
   ?>
</tr>
<tr>
   <td class="content_tbl_subheader content_row_os" rowspan="2" style="background-color:#DDDDDD;">Nombres</td>
   <td class="content_tbl_subheader content_row_os" rowspan="2" style="background-color:#DDDDDD;">Apellidos</td>
   <td class="content_tbl_subheader content_row_os" rowspan="2" style="background-color:#DDDDDD;">RUT</td>
   <td class="content_tbl_subheader content_row_os" rowspan="2" style="background-color:#DDDDDD;">Cargo</td>
   <?php
   foreach(array_keys($_WEEKS) AS $midx)
   {
      $x = 0;
      foreach(array_keys($_WEEK_DAYS[$midx]) AS $dayidx)
      {
         $bcss = "border-left:1px solid #999999;";
         if($x == 0)
            $bcss = "border-left:2px solid #333333;";

         $datearr = explode(".", $dayidx);
         ?>
         <td class="content_row_os content_tbl_header" style="<?=$bcss?>padding:2px;font-weight:normal;font-size:11px" align="center">
            <?=$datearr[0]?>
         </td>
         <?php
         $x++;
      }
   }
   ?>
</tr>
<tr>
   <?php
   foreach(array_keys($_WEEKS) AS $midx)
   {
      $x = 0;
      foreach(array_keys($_WEEK_DAYS[$midx]) AS $dayidx)
      {
         $bcss = "border-left:1px solid #999999;";
         if($x == 0)
            $bcss = "border-left:2px solid #333333;";

         $datearr = explode(".", $dayidx);
         $datestm = mktime(15, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
         $datestm = (int)date("N", $datestm);
         ?>
         <td class="content_row_os content_tbl_header" style="<?=$bcss?>padding:2px;font-weight:normal;font-size:11px" align="center">
            <?=substr($_LANG["MODULE"]["CAL"][(11 + $datestm)],0,2)?>
         </td>
         <?php
         $x++;
      }
   }
   ?>
</tr>
<?php
$y = 0;
foreach($workers AS $worker)
{
   $boldcss = "";
   if((int)$_SESSION[$_sesmodulename]["sql_dtlviewact"])
      $boldcss = "font-weight:bold;";
   ?>
   <tr bgcolor="<?=getRowColor($y)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_os" style="border-left:1px solid #CCCCCC;cursor:pointer;<?=$boldcss?>"><?=$worker["wrk_lastname"]?></td>
      <td class="content_row_os" style="cursor:pointer;<?=$boldcss?>"><?=$worker["wrk_firstname"]?></td>
      <td class="content_row_os" style="cursor:pointer;<?=$boldcss?>"><?=$worker["wrk_rut"]?></td>
      <td class="content_row_os" style="cursor:pointer;<?=$boldcss?>"><?=$worker["type_name"]?></td>
      <?php
      if((int)$_SESSION[$_sesmodulename]["sql_dtlviewact"])
      {
         $_TOTAL_COLSPAN = 0;
         foreach(array_keys($_WEEKS) AS $midx)
         {
            foreach(array_keys($_WEEK_DAYS[$midx]) AS $dayidx)
               $_TOTAL_COLSPAN++;
         }
         ?>
         <td class="content_row_os" style="border-left:2px solid #333333;" colspan="<?=$_TOTAL_COLSPAN?>">&nbsp;</td>
         <?php
      }
      else
      {
         foreach(array_keys($_WEEKS) AS $midx)
         {
            $x = 0;
            foreach(array_keys($_WEEK_DAYS[$midx]) AS $dayidx)
            {
               $bcss = "border-left:1px solid #999999;";
               if($x == 0)
                  $bcss = "border-left:2px solid #333333;";

               $clickurl = "showFancyboxReload('/iframe.fancy.php?mid={$_REQUEST["mid"]}&module=workermodify&wid={$worker["id"]}&startdate={$_WEEKS[$midx]["START_STAMP"]}&enddate={$_WEEKS[$midx]["END_STAMP"]}&plantaid={$_SESSION[$_sesmodulename]["sql_planta_id"]}','iframe', 1024, 600, 'auto')";

               if(count($_DATA[$worker["id"]][$dayidx]))
               {
                  $row = $_DATA[$worker["id"]][$dayidx][0];

                  $bdcss = "background-color:{$row["type_color"]}";
                  if((int)date("N", $row["assign_stamp"]) == 7)
                     $bdcss = "background-color:{$row["type_color_dom"]}";
                  ?>
                  <td class="content_row_os" style="<?=$bcss?>;<?=$bdcss?>;padding:2px;font-weight:normal;font-size:11px;cursor:pointer" align="center"
                  onclick="<?=$clickurl?>"><?=$row["type_name_short"]?></td>
                  <?php
               }
               else
               {  ?>
                  <td class="content_row_os" style="<?=$bcss?>;padding:2px;font-weight:normal;font-size:11px;cursor:pointer" align="center"
                  onclick="<?=$clickurl?>">&nbsp;</td>
                  <?php
               }
               $x++;
            }
         }
      }
      ?>
   </tr>
   <?php
   unset($_EQUIPOS);
   if((int)$_SESSION[$_sesmodulename]["sql_dtlviewact"])
   {
      foreach(array_keys($_WEEKS) AS $midx)
      {
         foreach(array_keys($_WEEK_DAYS[$midx]) AS $dayidx)
         {
            $rows = $_DATA[$worker["id"]][$dayidx];
            foreach($rows AS $row)
            {
               $_EQUIPOS[$row["assign_equipoaid"]] = $row["equipo_name"];
            }
         }
      }

      $first   = true;
      $ecc     = count(array_keys($_EQUIPOS));
      $ex      = 0;
      foreach(array_keys($_EQUIPOS) AS $equipoid)
      {
         $trcss = "";
         if($ex == $ecc-1)
            $trcss .= "border-bottom:1px dashed #666666;";
         ?>
         <tr bgcolor="<?=getRowColor($y)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os" style="border-left:1px solid #CCCCCC;<?=$trcss?>" colspan="4">
               <img src="/images/menu/icons/gear.png" style="vertical-align:bottom">
               <?=$_EQUIPOS[$equipoid]?> <?if($_EQUIPOS[$equipoid] == "") echo "SIN MÁQUINA"?>
            </td>
            <?php
            foreach(array_keys($_WEEKS) AS $midx)
            {
               $x = 0;
               foreach(array_keys($_WEEK_DAYS[$midx]) AS $dayidx)
               {
                  $clickurl = "showFancyboxReload('/iframe.fancy.php?mid={$_REQUEST["mid"]}&module=workermodify&wid={$worker["id"]}&startdate={$_WEEKS[$midx]["START_STAMP"]}&enddate={$_WEEKS[$midx]["END_STAMP"]}&plantaid={$_SESSION[$_sesmodulename]["sql_planta_id"]}','iframe', 980, 600, 'auto')";
                  
                  $bcss = "border-left:1px solid #999999;";
                  if($x == 0)
                     $bcss = "border-left:2px solid #333333;";
               
                  $rows    = $_DATA[$worker["id"]][$dayidx];
                  $found   = Array();
                  foreach($rows AS $row)
                  {
                     if(!(int)$found["id"] && (int)$row["assign_equipoaid"] == $equipoid)
                        $found = $row;
                  }

                  if((int)$found["id"])
                  {
                     $bdcss = "background-color:{$found["type_color"]}";
                     if((int)date("N", $found["assign_stamp"]) == 7)
                        $bdcss = "background-color:{$found["type_color_dom"]}";
                     ?>
                     <td class="content_row_os" style="<?=$bcss?>;<?=$bdcss?>;padding:2px;font-weight:normal;font-size:11px;cursor:pointer;<?=$trcss?>" align="center"
                     title="De <?=sprintf("%02s", $found["ass_init_hour"])?>:<?=sprintf("%02s", $found["ass_init_min"])?> hasta <?=sprintf("%02s", $found["ass_end_hour"])?>:<?=sprintf("%02s", $found["ass_end_min"])?>"
                     onclick="<?=$clickurl?>">
                        <?=$found["type_name_short"]?>
                     </td>
                     <?php
                  }
                  else
                  {  ?>
                     <td class="content_row_os" style="<?=$bcss?>;padding:2px;font-weight:normal;font-size:11px;<?=$trcss?>;cursor:pointer"
                     onclick="<?=$clickurl?>" align="center">&nbsp;</td>
                     <?php
                  }
                  $x++;
               }
            }
            ?>
         </tr>
         <?php
         $first = false;
         $ex++;
      }
   }
   $y++;
}
?>
</table>
<?=Nifty_printF()?>
<?php
$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';
?>