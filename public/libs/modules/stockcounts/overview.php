<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stockcounts";
$_sesbasefilterstatus   = "1,2";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Número" => "2", "Descripción" => "3", "Creado" => "4", "Estado" => "5");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

if($_REQUEST["exec"] == "edit")
   require_once("overview.edit.php");
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
      $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
      $_SESSION[$_sesmodulename]["sql_showdels"]  = (int)$_REQUEST["sql_showdels"];
      $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
      $_SESSION[$_sesmodulename]["sql_item"]      = trim(addslashes(str_replace("*","%",$_REQUEST["sql_item"])));
      $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
      $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
      $_SESSION[$_sesmodulename]["sql_status"]    = $_REQUEST["sql_status"];
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["filter_status"] != 2)
   if(!is_array($_SESSION[$_sesmodulename]["sql_status"]))
      $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2);
   
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "del")
   {
      $sql = " SELECT id, stockchange_stc_id
               FROM stockchanges
               WHERE 
               stockchange_stc_id = {$_REQUEST["id"]} ";
      $stkid = $CON->select($sql);
      $stkid = $stkid[0]["id"]; 

      if($stkid)
      {
         $sql = " UPDATE stockchanges
                  SET
                  stk_status = 0
                  WHERE
                  id = {$stkid}";
         $res = $CON->no_result($sql);
         
         $sql = " DELETE FROM stockchanges_items 
                  WHERE 
                  stk_id = {$stkid}";
         $res = $CON->no_result($sql);
      }

      $sql = " UPDATE stockcounts
               SET
               stc_status = 0
               WHERE
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);
      
   }

   if((int)$_SESSION[$_sesmodulename]["sql_showdels"])
      $_SESSION[$_sesmodulename]["filter_status"] = 0;
      
   //----------------------------------------------------------------------------------
   $seasql = "";

   $joisql = " LEFT OUTER JOIN user t2                ON t1.stc_updusr = t2.id
               LEFT OUTER JOIN user t3                ON t1.stc_crtusr = t3.id ";

   if($_SESSION[$_sesmodulename]["sql_item"] != "")
      $joisql .= " INNER JOIN stockcounts_lists_items t6 ON t1.id = t6.stc_id
                   LEFT OUTER JOIN item t7            ON (t6.item_id = t7.id)
                   LEFT OUTER JOIN item_barcodes tx   ON ( t7.id = tx.item_id AND tx.item_type = 'item') ";

   $cntsql = " select count(distinct t1.id) 'cc'
               from stockcounts t1
               {$joisql}
               where
               t1.stc_num != 'DELETED' ";

   $datsql = " select distinct t1.id, t1.stc_num, t1.stc_annotation, t1.stc_crtdat, t1.stc_status
               from stockcounts t1
               {$joisql}
               where
               t1.stc_num != 'DELETED' " ;
               

   /* t1.stc_status IN ({$_SESSION[$_sesmodulename]["filter_status"]}) "; */
   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["filter_status"] == 3)
   {
      $datsql .= " and t1.stc_status IN (3) ";
      $cntsql .= " and t1.stc_status IN (3) ";
   }
   elseif($_SESSION[$_sesmodulename]["filter_status"] != 2)
   {
      $seastatstr = "";
      foreach($_SESSION[$_sesmodulename]["sql_status"] AS $seastat)
         $seastatstr .= $seastat.",";
      $seastatstr = substr($seastatstr, 0, -1);
      $datsql .= " and t1.stc_status IN ({$seastatstr}) ";
      $cntsql .= " and t1.stc_status IN ({$seastatstr}) ";
   }         

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t1.stc_companyid = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t1.stc_shopid = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and t1.stc_num like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' ";

   if($_SESSION[$_sesmodulename]["sql_item"] != "")
      $seasql .= " and (
                   t7.item_title        like '%{$_SESSION[$_sesmodulename]["sql_item"]}%' or
                   t7.item_number       like '%{$_SESSION[$_sesmodulename]["sql_item"]}%' or
                   t7.item_number_prod  like '%{$_SESSION[$_sesmodulename]["sql_item"]}%' or
                   tx.item_barcode      like '%{$_SESSION[$_sesmodulename]["sql_item"]}%' ) ";
                   
   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" || $_SESSION[$_sesmodulename]["sql_dateto"] != "")
   {
      if($_SESSION[$_sesmodulename]["sql_dateto"] == "")
         $_SESSION[$_sesmodulename]["sql_dateto"] = date('d.m.Y');
      if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
         $_SESSION[$_sesmodulename]["sql_datefrom"] = "01.01.".date('Y');

      $sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_datefrom"]);
      $sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_dateto"], false);

      $seasql .= " and t1.stc_crtdat between {$sqldate_from} and {$sqldate_to} ";
   }

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
   $stockcounts = $CON->select($datsql);

   //----------------------------------------------------------------------------------
   $companies  = getCompanies($CON);
   $shops      = getShops($CON);

   
   //----------------------------------------------------------------------------------
   
   function getStockCount($stat, $formated = false)
   {
      if($formated)
      {
         switch($stat)
         {
            case 1: return "<b class='msg_save_err'>Pendiente</b>"; break;
            case 2: return "<b style='color:darkorange'>Aprobado</b>"; break;
         }
      }
      else
      {
         switch($stat)
         {
            case 1: return "Pendiente"; break;
            case 2: return "Aprobado"; break;
         }
      }
   }


   //----------------------------------------------------------------------------------
   ?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <?php
   printJSsetCompanyShop($shops);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Resumen de recuentos</b></td>
      <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults($itemcount); else echo $savemsg;?></td>
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
         <?=Nifty_printH("box2", "980")?>
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
            <td class="content_rowl">Número</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:300px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Empresa</td>
            <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Artículo</td>
            <td class="content_row">
               <input type="text" style="width:300px" id="sql_item" name="sql_item" class="text"
               onfocus="markfield(this,0)" onblur="markfield(this,1)"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_item"])?>">
            </td>
            <td class="content_rowl">Sucursal</td>
            <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?php printOverviewPeriodSelect($_sesmodulename) ?></td>
            <td class="content_rowl">Datos borrados</td>
            <td class="content_row">
               <input type="checkbox" name="sql_showdels" value="1"
               <?if($_SESSION[$_sesmodulename]["sql_showdels"] == 1) echo "checked"?>>
               Mostrar recuentos eliminados
            </td>
         </tr>
         <tr>
         <tr>
            <td class="content_rowl">Estado</td>
            <td class="content_row" colspan="3">
                  <?php
                  for($x = 1; $x <= 2; $x++)
                  {  ?>
                     <input type="checkbox" name="sql_status[]" value="<?=$x?>"
                     <?php if(array_search($x, $_SESSION[$_sesmodulename]["sql_status"]) !== false) echo "checked"?>><?=getStockCount($x, true)?>
                     <?php
                  }
                  ?>
               </td>
            </td>
         </tr>
         </tr>
         <tr>
            <td class="content_row" align="right" colspan="4">
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
         <?=Nifty_printF(false)?>
         </form>
      </td>
   </tr>
   <tr>
      <td>
         <?=Nifty_printH("box1", "980")?>
         <?php
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="65">
            <col>
            <col width="150">
            <col width="25">
            <col width="85">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($stockcounts) && $stockcounts != false; $x++)
         {
            $statimg = "";
            switch((int)$stockcounts[$x]["stc_status"])
            {
               case 0: $statimg = "gray_active.gif"; break;
               case 1: $statimg = "red_active.gif"; break;
               case 2: $statimg = "orange_active.gif"; break;
               case 3: $statimg = "green_active.gif"; break;
            }
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=$stockcounts[$x]["stc_num"]?></td>
               <td class="content_row"><?=$stockcounts[$x]["stc_annotation"]?>&nbsp;</td>
               <td class="content_row"><?php if($stockcounts[$x]["stc_crtdat"] > 0) echo date('d.m.Y', $stockcounts[$x]["stc_crtdat"]); else echo "&nbsp;";?></td>
               <td class="content_row" align="center">
                  <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getStockcountStatus($stockcounts[$x]["stc_status"])?>">
               </td>
               <td class="content_row" align="center">
                  <?php
                  if(!(int)$stockcounts[$x]["stc_status"])
                     printButton("Recuperar", "postnav", "javascript: deactivateFormChange()", "if(askDel('')){location.href='index.php?mid=682&reactivateNumber={$stockcounts[$x]["stc_num"]}'}", "pencil");
                  else
                     printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&id={$stockcounts[$x]["id"]}", "", "pencil");
                  ?>
               </td>
            </tr>
            <?php
         }

         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" colspan="8" align="center">
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
      </td>
   </tr>
   </table>
   <?php
}
