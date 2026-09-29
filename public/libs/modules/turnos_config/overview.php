<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2017 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "turnos_cfg";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "4";
$_sesbaseordersort      = "asc";

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_jornid"]     = (int)$_REQUEST["sql_jornid"];
   $_SESSION[$_sesmodulename]["sql_plantaid"]   = (int)$_REQUEST["sql_plantaid"];
   $_SESSION[$_sesmodulename]["sql_month1"]     = (int)$_REQUEST["sql_month1"];
   $_SESSION[$_sesmodulename]["sql_year1"]      = (int)$_REQUEST["sql_year1"];
   $_SESSION[$_sesmodulename]["sql_month2"]     = (int)$_REQUEST["sql_month2"];
   $_SESSION[$_sesmodulename]["sql_year2"]      = (int)$_REQUEST["sql_year2"];
   $_SESSION[$_sesmodulename]["page"]           = 0;
   $_SESSION[$_sesmodulename]["search_active"]  = 1;
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');
   $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m')+1;
   $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y');

   if($_SESSION[$_sesmodulename]["sql_month2"] == 13)
   {
      $_SESSION[$_sesmodulename]["sql_month2"]  = 1;
      $_SESSION[$_sesmodulename]["sql_year2"]++;
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "savedata_") !== false && strpos($reqkey, "savedata_") == 0)
      {
         $valarr  = explode("_", $reqkey);
         $plantaid = $valarr[1];
         $turnoid = $valarr[2];
         $datestr = $valarr[3];
         $xday    = substr($datestr, 0, 2);
         $xmonth  = substr($datestr, 2, 2);
         $xyear   = substr($datestr, 4, 4);
         $typeid  = (int)$_REQUEST[$reqkey];

         $cfg_datestr   = sprintf("%02s", $xday).".".sprintf("%02s", $xmonth).".".sprintf("%04s", $xyear);
         $cfg_datestamp = mktime(15, 0, 0, $xmonth, $xday, $xyear);

         $sql = " delete from turnos_config
                  where
                  cfg_turno_id   = {$turnoid} and
                  cfg_datestr    = '{$cfg_datestr}' and
                  cfg_planta_id  = {$plantaid}";
         $CON->no_result($sql);

         if((int)$turnoid && (int)$typeid && $cfg_datestr != "")
         {
            $sql = " insert into turnos_config
                     (cfg_planta_id, cfg_turno_id, cfg_turno_type_id, cfg_datestr, cfg_datestamp)
                     VALUES
                     ({$plantaid}, {$turnoid}, {$typeid}, '{$cfg_datestr}', {$cfg_datestamp})";
            $CON->no_result($sql);
         }
      }
   }
}

//----------------------------------------------------------------------------------
$sql_datefrom  = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
$sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], 1, $_SESSION[$_sesmodulename]["sql_year2"]);
$datedays      = date('t', $sql_dateto);
$sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], $datedays, $_SESSION[$_sesmodulename]["sql_year2"]);

//----------------------------------------------------------------------------------
$date_init = $sql_datefrom;
$date_end  = $sql_dateto;
for($x = $date_init+3600; $x <= $date_end; $x += 86400)
{
   $idx_month = date("m/Y", $x);
   $_MONTH_COLSPAN[$idx_month]++;
   $_TOTAL_COLSPAN++;
}

//----------------------------------------------------------------------------------
$sql = " select *
         from turnos_jornadas
         where
         jorn_status > 0
         order by jorn_order, jorn_name";
$jornadas = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select *
         from turnos_config
         where
         cfg_datestamp between {$sql_datefrom} and {$sql_dateto}";
$cfgs = $CON->select($sql);
foreach($cfgs AS $cfg)
   $_CFGACT[$cfg["cfg_planta_id"]][$cfg["cfg_datestr"]][$cfg["cfg_turno_id"]] = $cfg["cfg_turno_type_id"];

$sql = " select *
         from turnos_types
         where
         type_status > 0
         order by type_name";
$ttypes = $CON->select($sql);
foreach($ttypes AS $ttype)
   $_TTYPES[$ttype["id"]] = $ttype;

$plantas       = getPlantas($CON);
$selplantas    = getPlantas($CON, $_SESSION[$_sesmodulename]["sql_plantaid"]);
?>
<style>
.content_row_os, .clstypes
{
   -webkit-touch-callout: none;
   -webkit-user-select: none;
   -khtml-user-select: none;
   -moz-user-select: none;
   -ms-user-select: none;
   user-select: none;
}
.clstypes
{
   cursor:pointer;
   color:#666666;
   text-align:center;
   border-radius:1px;
   margin-right:10px;
   margin-bottom:10px;
   font-family:Arial;
   font-size:12px;
   float:left;
   width:86px;
   height:51px;

   
}
.clstypes_inner
{
   border:1px solid #666666;
   color:#222222;
   margin-top:6px;
   display:inline-block;
   height:20px;
   width:20px;
   border-radius:50%;
   background-color:#EEEEEE;
}
</style>
<table border="0" cellpadding="0" cellspacing="0" width="99%">
<tr>
   <td height="30"><b class="content_header">Configuración de Turnos</b></td>
   <td align="right" class="content_row_clear"></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst" style="padding:0px;margin:0px">
<input type="hidden" name="subexec" value="search">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="printxls" value="0">
<table cellpadding="0" cellspacing="0" width="100%" border="0">
<colgroup>
   <col width="550">
   <col width="15">
   <col>
</colgroup>
<tr>
   <td valign="top">
      <?=Nifty_printH("box1", "100%")?>
      <table cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header">Jornada</td>
         <td class="content_tbl_header" align="right">
            <table border="0" cellpadding="0" cellspacing="0">
            <tr>
               <td width="25">
                  <img src="/images/menu/icons/document--plus.png" style="cursor:pointer"
                  onclick="showFancybox('/libs/modules/turnos_config/upload.xls.fancy.php', 'iframe', 400, 150, 'no')">
               </td>
               <td>
                  <img src="/images/menu/icons/document-excel.png" style="cursor:pointer"
                  onclick="document.xform_itemsearch.printxls.value='1';document.xform_itemsearch.subexec.value='search';submitForm(document.xform_itemsearch)">
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Seleccione Planta</td>
         <td class="content_row">
            <select class="text" style="width:100%" name="sql_plantaid" id="sql_plantaid"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($plantas AS $planta)
               {  ?>
                  <option value="<?=$planta["id"]?>"
                  <?php if($planta["id"] == $_SESSION[$_sesmodulename]["sql_plantaid"]) echo "selected"?>><?=$planta["planta_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Seleccione Jornada</td>
         <td class="content_row">
            <select class="text" style="width:100%" name="sql_jornid" id="sql_jornid"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($jornadas AS $jornada)
               {  ?>
                  <option value="<?=$jornada["id"]?>"
                  <?php if($jornada["id"] == $_SESSION[$_sesmodulename]["sql_jornid"]) echo "selected"?>><?=$jornada["jorn_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Seleccione Mes</td>
         <td class="content_row">
            <nobr>
            <input type="button" class="button" value="&lt;&lt;" style="width:30px;" onclick="setNextMonth(-1);setNextMonth(-1)">
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
            <input type="button" class="button" value="&gt;" style="width:30px;" onclick="setNextMonth(1)">
            <input type="button" class="button" value="&gt;&gt;" style="width:30px;" onclick="setNextMonth(1);setNextMonth(1)">
            </nobr>
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td align="left">
                  <?php
                  printButton("Guardar", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.xform_itemsearch.subexec.value='save';submitForm(document.xform_itemsearch) }", "disk", 130);
                  ?>
               </td>
               <td align="right" width="1" style="padding-right:3px">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["search_active"])
                     printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                  ?>
               </td>
               <td align="right" width="1">
                  <?php
                  printButton("Mostrar", "postnav", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
                  $_SESSION["_SUBMITBTN"] = 1;
                  ?>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
   <td width="15"><div style="width:15px"></div></td>
   <td valign="top" align="left">
      <?php
      foreach($ttypes AS $ttype)
      {  ?>
         <div class="clstypes" style="border:2px solid #AAAAAA;background-color:<?=$ttype["type_color"]?>"
         id="opttype_<?=$ttype["id"]?>" onclick="activateTypeOpt('<?=$ttype["id"]?>', '<?=$ttype["type_name_short"]?>', '<?=$ttype["type_color"]?>')">
            <div class="clstypes_inner">
               <span style="line-height:22px;font-size:10px"><?=$ttype["type_name_short"]?></span>
            </div>
            <div style="height:4px"></div>
            <div style="white-space:nowrap;overflow: hidden;text-overflow: ellipsis;color:#333333;padding:3px"><?=$ttype["type_name"]?></div>
         </div>
         <?php
      }
      ?>
      <div class="clstypes" style="border:2px solid #AAAAAA;background-color:#FFC5C5"
      id="opttype_0" onclick="activateTypeOpt('0', 'X', '#FFC5C5')">
         <div class="clstypes_inner">
            <span style="line-height:22px;font-size:10px">X</span>
         </div>
         <div style="height:4px"></div>
         <div style="white-space:nowrap;overflow: hidden;text-overflow: ellipsis;color:#333333;padding:3px">ELIMINAR</div>
      </div>
   </td>
</tr>
</table>
<br>
<?php
//----------------------------------------------------------------------------------
$sql = " select *
         from turnos_jornadas
         where
         jorn_status > 0 ";
if((int)$_SESSION[$_sesmodulename]["sql_jornid"])
   $sql .= " and id = {$_SESSION[$_sesmodulename]["sql_jornid"]} ";
$sql .= " order by jorn_order, jorn_name";
$jornadas = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select distinct assign_month, assign_year
         from turnos_config_assign";
$assmonths = $CON->select($sql);
foreach($assmonths AS $assmonth)
   $_ASSMONTHS[$assmonth["assign_year"]][$assmonth["assign_month"]] = 1;
?>
<div id="idx_fixedheader" style="position:fixed;top:0px">
<table cellpadding="2" cellspacing="0" width="100%" style="table-layout:fixed" id="idx_clonetable">
<colgroup>
   <col width="130">
</colgroup>
</table>
</div>

<?=Nifty_printH("box1", "")?>
<table cellpadding="2" cellspacing="0" width="100%" style="table-layout:fixed" id="idx_maintable">
<colgroup>
   <col width="130">
</colgroup>
<thead>
<tr>
   <td class="content_row_os content_tbl_header" rowspan="3" style="border-bottom:2px solid #666666">TURNOS</td>
   <?php
   $x = 0;
   foreach(array_keys($_MONTH_COLSPAN) AS $midx)
   {
      $bcss = "";
      if($x > 0)
         $bcss = "border-left:3px double #333333;";

      $marr = explode("/", $midx);
      ?><td class="content_row_os content_tbl_header" align="center" style="<?=$bcss?>background-color:#AAAAAA;padding:3px;font-weight:bold;" colspan="<?=$_MONTH_COLSPAN[$midx]?>"><?=$_LANG["MODULE"]["CAL"][((int)$marr[0]-1)]?> - <?=$midx?></td><?php
      $x++;
   }
   ?>
</tr>
<tr>
   <?php
   $lastmonth = date("m/Y", $date_init);
   for($x = $date_init+3600; $x <= $date_end; $x += 86400)
   {
      $bcss = "";
      if($lastmonth != date("m/Y", $x))
         $bcss = "border-left:3px double #333333;";
      ?><td class="content_row_os content_tbl_header" style="<?=$bcss?>padding:2px;font-weight:normal;font-size:11px" align="center"><?=substr($_LANG["MODULE"]["CAL"][(11 + date("N", $x))],0,2)?></td><?php
      $lastmonth = date("m/Y", $x);
   }
   ?>
</tr>
<tr>
   <?php
   $lastmonth = date("m/Y", $date_init);
   for($x = $date_init+3600; $x <= $date_end; $x += 86400)
   {
      $bcss = "";
      if($lastmonth != date("m/Y", $x))
         $bcss = "border-left:3px double #333333;";
      if(date("N", $x) == 7)
         $bcss .= "background-color:#EEEEEE;";
      else
         $bcss .= "background-color:#FFFFFF;";
      ?><td class="content_row_os content_tbl_subheader" align="center" style="<?=$bcss?>border-bottom:2px solid #666666;padding:2px;font-weight:normal;font-size:11px"><?=date("d", $x)?></td><?php
      $lastmonth = date("m/Y", $x);
   }
   ?>
</tr>
</thead>
<tbody>
<?php
foreach($selplantas AS $selplanta)
{
   ?>
   <tr>
      <td class="content_row_os" colspan="<?=$_TOTAL_COLSPAN+1?>" style="background-color:#00A9A6;color:white;text-shadow:none">
         <b>PLANTA: <?=$selplanta["planta_name"]?></b>
      </td>
   </tr>
   <?php
   foreach($jornadas AS $jornada)
   {
      ?><tr><td class="content_row_os" style="border-color:#666666;background-color:#FFDABD;font-weight:bold"><?=$jornada["jorn_name"]?></td><?php
         $lastmonth = date("m/Y", $date_init);
         for($x = $date_init+3600; $x <= $date_end; $x += 86400)
         {
            $bcss = "";
            if($lastmonth != date("m/Y", $x))
               $bcss = "border-left:3px double #333333;";
            if(date("N", $x) == 7)
               $bcss .= "background-color:#EEEEEE;";
            else
               $bcss .= "background-color:#FFDABD;";
            ?><td class="content_row_os" style="<?=$bcss?>cursor:pointer" align="center">&nbsp;</td><?php
            $lastmonth = date("m/Y", $x);
         }
         ?>
      </tr>
      <?php
      $sql = " select *
               from turnos
               where
               turn_jornada_id = {$jornada["id"]} and
               turn_status > 0
               order by turn_order, turn_name";
      $turnos = $CON->select($sql);
      foreach($turnos AS $turno)
      {
         ?><tr><td class="content_row_os" style="border-color:#666666;background-color:#D8D8D8;font-weight:bold"><?=$turno["turn_name"]?></td><?php
            $lastmonth = date("m/Y", $date_init);
            for($x = $date_init+3600; $x <= $date_end; $x += 86400)
            {
               $tdcls = "markme";

               if((int)$_ASSMONTHS[(int)date("Y", $x)][(int)date("m", $x)])
                  $tdcls = "";
               
               $bcss = "";
               if($lastmonth != date("m/Y", $x))
                  $bcss = "border-left:3px double #333333;";

               $dateidx    = date("dmY", $x);
               $cellidx    = date("d.m.Y", $x);
               $celldata   = "&nbsp;";
               $inputval   = "";
               if((int)$_CFGACT[$selplanta["id"]][$cellidx][$turno["id"]])
               {
                  $celldata   = $_TTYPES[$_CFGACT[$selplanta["id"]][$cellidx][$turno["id"]]]["type_name_short"];
                  $bcss      .= "background-color:{$_TTYPES[$_CFGACT[$selplanta["id"]][$cellidx][$turno["id"]]]["type_color"]};";
                  $inputval   = $_CFGACT[$selplanta["id"]][$cellidx][$turno["id"]];
               }
               elseif(date("N", $x) == 7)
                  $bcss .= "background-color:#EEEEEE;";
               ?><td class="content_row_os <?=$tdcls?>" style="<?=$bcss?>cursor:pointer;font-size:9px;font-shadow:1px 1px white;color:#000000;" align="center" relattr="<?=$selplanta["id"]?>_<?=$turno["id"]?>_<?=$dateidx?>"><?=$celldata?></td><input type="hidden" style="width:15px" name="savedata_<?=$selplanta["id"]?>_<?=$turno["id"]?>_<?=$dateidx?>" id="savedata_<?=$selplanta["id"]?>_<?=$turno["id"]?>_<?=$dateidx?>" value="<?=$inputval?>"><?php
               $lastmonth = date("m/Y", $x);
            }
            ?>
         </tr>
         <?php
      }
   }
}
?>
</tbody>
</table>
<?=Nifty_printF(false)?>
<?php
$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';
?>
<script language="JavaScript">
$(function ()
{
   var isMouseDown = false;
   var selidx      = 0;
   //-----------------------------------------------------------------------------------------
   $(".markme").mousedown(function ()
   {
      isMouseDown = true;
      if(optactivated == 1)
      {
         $(this).css('background-color',currentcolor);
         $(this).html(currentprefix);
         
         selidx = $(this).attr('relattr');
         $('#savedata_' +selidx).val(currenttype);
      }
      return false;
   })
   //-----------------------------------------------------------------------------------------
   .mouseover(function ()
   {
      if(isMouseDown)
      {
         if(optactivated == 1)
         {
            $(this).animate({'background-color':currentcolor}, 200);
            $(this).html(currentprefix);

            selidx = $(this).attr('relattr');
            $('#savedata_' +selidx).val(currenttype);
         }
      }
   })
   //-----------------------------------------------------------------------------------------
   .bind("selectstart", function ()
   {
      return false;
   });

   //-----------------------------------------------------------------------------------------
   $(document).mouseup(function ()
   {
      isMouseDown = false;
   });

});


var currenttype   = -1;
var currentprefix = '';
var currentcolor  = '';
var optactivated  = 0;
function activateTypeOpt(tidx, tprefix, tcolor)
{
   currenttype    = tidx;
   currentprefix  = tprefix;
   currentcolor   = tcolor;
   optactivated   = 1;
   
   $('.clstypes').css({'border-color':'#AAAAAA', 'box-shadow':'none'});
   $('#opttype_' +tidx).animate({'border-color':'#555555', 'box-shadow':'0px 3px 10px 4px rgba(0,0,0,0.2)'}, 200);
}

var fixedAct = false;
var ylimit   = 160;
$(document).ready(function()
{
   $(window).scroll(function (event)
   {
      var scroll = $(window).scrollTop();

      if(scroll >= ylimit && !fixedAct)
      {
         $('#idx_maintable').find('thead').clone().appendTo('#idx_clonetable');
         fixedAct = true;
      }
      else if(scroll < ylimit && fixedAct)
      {
         $('#idx_clonetable').find('thead').remove();
         fixedAct = false;
      }
   });
});
</script>
</form>
<?php
$_SESSION[$_sesmodulename]["XDATA"]["jornadas"]       = $jornadas;
$_SESSION[$_sesmodulename]["XDATA"]["_TTYPES"]        = $_TTYPES;
$_SESSION[$_sesmodulename]["XDATA"]["ttypes"]         = $ttypes;

if($_REQUEST["printxls"])
  $xlsfile = xls_createTurnosConfig($CON, $_SESSION[$_sesmodulename]["sql_year1"], $selplantas);
  
if($xlsfile != "")
{
   $doctitle = "Configuracion-Turnos-".time().".xlsx";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xlsx&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>