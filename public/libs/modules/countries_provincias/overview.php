<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "provincias";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "4,2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("País" => "4,2", "Región" => "5", "Provincia" => "2", "Creado" => "3,4,2");

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

if($_REQUEST["exec"] == "edit")
   require_once("edit.php");
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $_SESSION[$_sesmodulename]["country"]        = (int)$_REQUEST["country"];
      $_SESSION[$_sesmodulename]["regions"]        = (int)$_REQUEST["regions"];
      $_SESSION[$_sesmodulename]["page"]           = 0;
      $_SESSION[$_sesmodulename]["search_active"]  = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);
   
   if($_REQUEST["exec"] == "delete")
   {
      $sql = " update provincias
               set
               pro_status = 0
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " INNER JOIN regions t2 ON ( t1.region_id = t2.id and t2.estado > 0 )
               INNER JOIN country t3 ON ( t2.id_pais = t3.id and t3.country_status > 0 ) ";
   
   //----------------------------------------------------------------------------------
   $cntsql = " select count(distinct t1.id) 'cc'
               from provincias t1
               {$joisql}
               where
               t1.pro_status > 0 ";

   $datsql = " select t1.id, t1.pro_name, t1.crtdat, t3.country_name, t2.name
               from provincias t1
               {$joisql}
               where
               t1.pro_status > 0 ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["country"])
      $seasql .= " and t2.id_pais = {$_SESSION[$_sesmodulename]["country"]} ";
   if($_SESSION[$_sesmodulename]["regions"])
      $seasql .= " and t1.region_id = {$_SESSION[$_sesmodulename]["regions"]} ";
      
   //----------------------------------------------------------------------------------
   $cntsql   .= $seasql;
   $datsql   .= $seasql;
   $itemcount = $CON->select($cntsql);
   $itemcount = (int)$itemcount[0]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

   $datsql .= " order by 4,5,2 ";

   //----------------------------------------------------------------------------------
   $provincias = $CON->select($datsql);

   $countries  = getCountries($CON);
   $regions    = getRegions($CON);
   $comunas    = getComunas($CON);
   $xrovincias = getProvincias($CON);
   
   //----------------------------------------------------------------------------------
   ?>
   <script language="JavaScript">
   <?php
   generateCountryJS($countries, $regions, $comunas, $xrovincias);
   ?>
   </script>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header">Resumen de provincias</b></td>
      <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults($itemcount); else echo $savemsg;?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <form action="index.php" method="post" name="xform_itemsearch">
   <input type="hidden" name="subexec" value="search">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <select style="display:none" name="provincias" id="provincias"></select>
   <select style="display:none" name="comunas" id="comunas"></select>
   <?=Nifty_printH("box1", "822")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="50">
      <col>
      <col width="50">
      <col>
      <col width="1">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="5">Opciones de búsqueda</td>
   </tr>
   <tr>
      <td class="content_rowl">País</td>
      <td class="content_row">
         <select name="country" class="text" style="width:220px"
         onchange="setRegions(this.value)">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($countries AS $country)
            {  ?>
               <option value="<?=$country["id"]?>" <?if($country["id"] == $_SESSION[$_sesmodulename]["country"]) echo "selected"?>>
                  <?=$country["country_name"]?>
               </option>
               <?php
            }
            ?>
         </select>
      </td>
      <td class="content_rowl">Región</td>
      <td class="content_row">
         <select class="text" style="width:220px" name="regions" id="regions"
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
      <td class="content_row">
         <table border="0" cellpadding="0" cellspacing="0" width="270">
         <tr>
            <td align="right">
               <?php
               if((int)$_SESSION[$_sesmodulename]["search_active"])
                  printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
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
   </form>
   <?=Nifty_printF()?>
   <br>
   <?=Nifty_printH("box1", "822")?>
   <?php
   printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
   ?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="80">
      <col>
      <col>
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_subheader">País</td>
      <td class="content_tbl_subheader">Region</td>
      <td class="content_tbl_subheader">Provincia</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($provincias) && $provincias != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$provincias[$x]["country_name"]?></td>
         <td class="content_row"><?=$provincias[$x]["name"]?></td>
         <td class="content_row"><?=$provincias[$x]["pro_name"]?></td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$provincias[$x]["id"]}", "", "pencil");
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" align="center" colspan="4">
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
   printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
   ?>
   <?=Nifty_printF()?>
   <br>
   <?php
}