<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "regions";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "4,2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("País" => "4,2", "Región" => "2", "Creado" => "3,4,2");

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
      $_SESSION[$_sesmodulename]["page"]           = 0;
      $_SESSION[$_sesmodulename]["search_active"]  = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);
   
   if($_REQUEST["exec"] == "delete")
   {
      $sql = " update regions
               set
               estado = 0
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = "INNER JOIN country t3 ON ( t1.id_pais = t3.id and t3.country_status > 0 ) ";
   
   //----------------------------------------------------------------------------------
   $cntsql = " select count(distinct t1.id) 'cc'
               from regions t1
               {$joisql}
               where
               t1.estado > 0 ";

   $datsql = " select t1.id, t1.name, t1.crtdat, t3.country_name
               from regions t1
               {$joisql}
               where
               t1.estado > 0 ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["country"])
      $seasql .= " and t1.id_pais = {$_SESSION[$_sesmodulename]["country"]} ";

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
   $regions = $CON->select($datsql);

   $sql = " select *
            from country
            where
            country_status > 0
            order by country_name";
   $countries = $CON->select($sql);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header">Resumen de regiónes</b></td>
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
      <col width="130">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="8">Opciones de búsqueda</td>
   </tr>
   <tr>
      <td class="content_rowl">País</td>
      <td class="content_row">
         <select name="country" class="text" style="width:250px">
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
      <col width="100">
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
      <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
      <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($regions) && $regions != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$regions[$x]["country_name"]?></td>
         <td class="content_row"><?=$regions[$x]["name"]?></td>
         <td class="content_row"><?=displayDate($regions[$x]["crtdat"])?></td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$regions[$x]["id"]}", "", "pencil");
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
   <?php
}