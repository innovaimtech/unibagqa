<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../classes/page.php");
require_once("../../classes/mysql.php");
require_once("../../config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../functions.php");

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

//----------------------------------------------------------------------------------
$_sesmodulename         = "transports";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Nombre" => "2", "Dirección" => "3");

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);


//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["country"]        = (int)$_REQUEST["country"];
   $_SESSION[$_sesmodulename]["regions"]        = (int)$_REQUEST["regions"];
   $_SESSION[$_sesmodulename]["comunas"]        = (int)$_REQUEST["comunas"];
   $_SESSION[$_sesmodulename]["provincias"]     = (int)$_REQUEST["provincias"];
   $_SESSION[$_sesmodulename]["sql_stext"]      = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
   $_SESSION[$_sesmodulename]["page"]           = 0;
   $_SESSION[$_sesmodulename]["search_active"]  = 1;
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "del" && (int)$_REQUEST["id"])
{
   $currtme = time();
   
   $sql = " update transports
            set
            trans_status = 0,
            trans_updusr = {$_SESSION["user_id"]},
            trans_upddat = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $shops   = getShops($CON, true);
   $shwmsg  = true;

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
$cntsql = " select count(distinct t1.id) 'cc'
            from transports t1
            where
            t1.trans_status = 1 ";
            
$datsql = " select distinct t1.id, t1.trans_name, t1.trans_street
            from transports t1
            where
            t1.trans_status = 1 ";
            
//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["country"])
   $seasql .= " and t1.trans_countryid = {$_SESSION[$_sesmodulename]["country"]} ";
if($_SESSION[$_sesmodulename]["regions"])
   $seasql .= " and t1.trans_regionid = {$_SESSION[$_sesmodulename]["regions"]} ";
if($_SESSION[$_sesmodulename]["comunas"])
   $seasql .= " and t1.trans_comunaid = {$_SESSION[$_sesmodulename]["comunas"]} ";
if($_SESSION[$_sesmodulename]["provincias"])
   $seasql .= " and t1.trans_provinciaid = {$_SESSION[$_sesmodulename]["provincias"]} ";
if($_SESSION[$_sesmodulename]["sql_stext"] != "")
   $seasql .= " and (t1.trans_name   like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%') ";

//----------------------------------------------------------------------------------
$cntsql   .= $seasql;
$datsql   .= $seasql;
$itemcount = $CON->select($cntsql);
$itemcount = (int)$itemcount[0]["cc"];

//----------------------------------------------------------------------------------
$_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
$datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";
  
//----------------------------------------------------------------------------------
$transports  = $CON->select($datsql);

//----------------------------------------------------------------------------------
$countries  = getCountries($CON);
$regions    = getRegions($CON);
$provincias = getProvincias($CON);
$comunas    = getComunas($CON);

//----------------------------------------------------------------------------------
?>

<html>
<head>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <style type="text/css" media="print">
      .no-print { display:none; }
      .page-break { display:block; page-break-before:always; }
      .watermark { display:block; }
      a { text-decoration:none; }
      a:link:after, a:visited:after { content:" [" attr(href) "] "; }
   </style>
   <script language="JavaScript">
   <?php
   generateCountryJS($countries, $regions, $comunas, $provincias);
   ?>
   <?php
   require_once("../../jscripts/sourcen.php");
   ?>
   </script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr id="idx_tr_custsearch">
   <td>
      <form action="show.transport.dispo.fancy.php" method="post" name="xform_itemsearch" class="fokusfirst"
      onsubmit="return checkform(new Array(this))">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
      <?=Nifty_printH("box2", "822")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
         <col width="100">
         <col width="290">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Palabra</td>
         <td class="content_row">
            <input name="sql_stext" type="text" class="text" style="width:280px"
            value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_rowl">País</td>
         <td class="content_row">
            <select class="text" style="width:280px" name="country" id="country"
            onchange="setRegions(this.value)"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($countries as $country)
               {  ?>
                  <option value="<?=$country["id"]?>"
                  <?php if($country["id"] == $_SESSION[$_sesmodulename]["country"]) echo "selected"?>><?=$country["country_name"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Región</td>
         <td class="content_row">
            <select class="text" style="width:280px" name="regions" id="regions"
            onchange="setProvincias(this.value);"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$_SESSION[$_sesmodulename]["country"])
               {
                  foreach($regions as $region)
                  {
                     if($region["id_pais"] == $_SESSION[$_sesmodulename]["country"])
                     {  ?>
                        <option value="<?=$region["id"]?>"
                        <?php if($region["id"] == $_SESSION[$_sesmodulename]["regions"]) echo "selected"?>><?=$region["name"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Provincia</td>
         <td class="content_row">
            <select class="text" style="width:280px" name="provincias" id="provincias" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setComunas(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$_SESSION[$_sesmodulename]["regions"])
               {
                  foreach($provincias as $provincia)
                  {
                     if($provincia["region_id"] == $_SESSION[$_sesmodulename]["regions"])
                     {  ?>
                        <option value="<?=$provincia["id"]?>"
                        <?php if($provincia["id"] == $_SESSION[$_sesmodulename]["provincias"]) echo "selected"?>><?=$provincia["pro_name"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Comuna</td>
         <td class="content_row">
            <select class="text" style="width:280px" name="comunas" id="comunas" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               if((int)$_SESSION[$_sesmodulename]["provincias"])
               {
                  foreach($comunas as $comuna)
                  {
                     if($comuna["prov_id"] == $_SESSION[$_sesmodulename]["provincias"])
                     {  ?>
                        <option value="<?=$comuna["id"]?>"
                        <?php if($comuna["id"] == $_SESSION[$_sesmodulename]["comunas"]) echo "selected"?>><?=$comuna["nombre"]?></option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
         <td class="content_row" align="right" colspan="2">
            <table border="0" cellpadding="0" cellspacing="0" width="270">
            <tr>
               <td align="right">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["search_active"])
                     printButton("Resetear", "postnav", "show.transport.dispo.fancy.php?searchexec=reset", "", "arrow-circle-double-135", 130);
                  ?>
               </td>
               <td align="right">
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
      <?=Nifty_printF()?>
      </form>
   </td>
</tr>
</table>
<?=Nifty_printH("box1", "822")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="350">
   <col width="350">
</colgroup>
<tr>
   <td class="content_tbl_subheader">Nombre</td>
   <td class="content_tbl_subheader">Dirección</td>
</tr>
<?php
//----------------------------------------------------------------------------------
for($x = 0; $x < count($transports) && $transports != false; $x++)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row"><?=$transports[$x]["trans_name"]?></td>
      <td class="content_row"><nobr><?=$transports[$x]["trans_street"]?>&nbsp;</nobr></td>
   </tr>
   <?php
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row" colspan="2" align="center" style="height:40px">
         <b class="msg_save_err"><?=$_LANG["FORM"]["MESSAGE"][5]?></b>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
</body>
</html>
<?php