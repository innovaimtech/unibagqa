<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "comunas";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "4,3,2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("País" => "4,3,2", "Región" => "3,2", "Comuna" => "2");

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
      $_SESSION[$_sesmodulename]["provincias"]     = (int)$_REQUEST["provincias"];
      $_SESSION[$_sesmodulename]["page"]           = 0;
      $_SESSION[$_sesmodulename]["search_active"]  = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);
   
   if($_REQUEST["exec"] == "delete")
   {
      $sql = " update comunas
               set
               estado = -1
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " INNER JOIN regions t3 ON ( t1.id_region = t3.id and t3.estado > 0 )
               INNER JOIN country t4 ON ( t3.id_pais = t4.id  and t4.country_status > 0 )
               INNER JOIN provincias t5 ON ( t1.prov_id = t5.id  and t5.pro_status > 0 )  ";

   //----------------------------------------------------------------------------------
   $cntsql = " select count(distinct t1.id) 'cc'
               from comunas t1
               {$joisql}
               where
               t1.estado > 0 ";
   
   //----------------------------------------------------------------------------------
   $datsql = " select t1.id, t1.nombre, t3.name, t4.country_name, t5.pro_name
               from comunas t1
               {$joisql}
               where
               t1.estado >= 0 ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["country"])
      $seasql .= " and t3.id_pais = {$_SESSION[$_sesmodulename]["country"]} ";
   if($_SESSION[$_sesmodulename]["regions"])
      $seasql .= " and t1.id_region = {$_SESSION[$_sesmodulename]["regions"]} ";
   if($_SESSION[$_sesmodulename]["provincias"])
      $seasql .= " and t1.prov_id = {$_SESSION[$_sesmodulename]["provincias"]} ";
            
   //----------------------------------------------------------------------------------
   $cntsql   .= $seasql;
   $datsql   .= $seasql;
   $itemcount = $CON->select($cntsql);
   $itemcount = (int)$itemcount[0]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

   $datsql .= " order by 4,3,5,2";

   //----------------------------------------------------------------------------------
   $comunas = $CON->select($datsql);

   //----------------------------------------------------------------------------------
   $countries  = getCountries($CON);
   $regions    = getRegions($CON);
   $provincias = getProvincias($CON);
   //----------------------------------------------------------------------------------
   ?>
   <script language="JavaScript">
      <?php
      generateCountryJS($countries, $regions, $comunas, $provincias);
      ?>
   </script>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header">Resumen de comunas</b></td>
      <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults($itemcount); else echo $savemsg;?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <form action="index.php" method="post" name="xform_itemsearch">
   <input type="hidden" name="subexec" value="search">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <?=Nifty_printH("box1", "822")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="100">
      <col>
      <col width="100">
      <col width="300">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
   </tr>
   <tr>
      <td class="content_rowl">País</td>
      <td class="content_row">
         <select class="text" style="width:300px" name="country" id="country"
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
      <td class="content_rowl">Región</td>
      <td class="content_row">
         <select class="text" style="width:300px" name="regions" id="regions"
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
   </tr>
   <tr style="display:none">
      <td class="content_row">Comuna *</td>
      <td class="content_row" colspan="3">
         <select class="text" style="width:280px" name="comunas" id="comunas" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         </select>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Provincia</td>
      <td class="content_row">
         <select class="text" style="width:300px" name="provincias" id="provincias" onmousedown="markfield(this,0)" onblur="markfield(this,1)"
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
      <td class="content_row" colspan="2" align="right">
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
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col>
      <col>
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_subheader">País</td>
      <td class="content_tbl_subheader">Región</td>
      <td class="content_tbl_subheader">Provincia</td>
      <td class="content_tbl_subheader">Comuna</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($comunas) && $comunas != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$comunas[$x]["country_name"]?></td>
         <td class="content_row"><?=$comunas[$x]["name"]?></td>
         <td class="content_row"><?=$comunas[$x]["pro_name"]?></td>
         <td class="content_row"><?=$comunas[$x]["nombre"]?></td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$comunas[$x]["id"]}", "", "pencil");
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" align="center" colspan="5">
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