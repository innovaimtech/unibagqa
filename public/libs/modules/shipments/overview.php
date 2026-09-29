<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "shipments";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Numero" => "1", "Proveedor" => "2", "Numero de guia" => "3", "Numero OC" => "8", "Monto Neto" => "4", "Recibido" => "7", "Creado" => "5", "Estado" => "6");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

if($_REQUEST["exec"] == "edit")
   require_once("overview.edit.php");
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "del")
   {
      $sql = " update shipment
               set
               shp_status = 0
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }
   
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $sql_item = explode("#", $_REQUEST["item_id"]);
      
      $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
      $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
      $_SESSION[$_sesmodulename]["sql_supplier"]  = (int)$_REQUEST["sql_supplier"];
      $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
      $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
      $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
      $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
      $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);
      $_SESSION[$_sesmodulename]["sql_status"]    = $_REQUEST["sql_status"];
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);
   /*
   if($_SESSION[$_sesmodulename]["filter_status"] == "1,2,3,4")
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]) ||
         (array_search(1,$_SESSION[$_sesmodulename]["sql_status"]) === false &&
          array_search(2,$_SESSION[$_sesmodulename]["sql_status"]) === false &&
          array_search(3,$_SESSION[$_sesmodulename]["sql_status"]) === false &&
          array_search(4,$_SESSION[$_sesmodulename]["sql_status"]) === false))
      $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>3,3=>4);
*/
   if($_SESSION[$_sesmodulename]["filter_status"] == "1,2,3")
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]) ||
         (array_search(1,$_SESSION[$_sesmodulename]["sql_status"]) === false &&
          array_search(2,$_SESSION[$_sesmodulename]["sql_status"]) === false &&
          array_search(3,$_SESSION[$_sesmodulename]["sql_status"]) === false))
         $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>3);
         
         
         foreach($_SESSION[$_sesmodulename]["sql_status"] AS $sqlst)
            $sqlstatstr .= $sqlst.",";
         $sqlstatstr = substr($sqlstatstr, 0, -1);
   
   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " LEFT OUTER JOIN supplier t2         ON t1.shp_supplier_id = t2.id
               LEFT OUTER JOIN supplier_order t9   ON t1.shp_supporder_id = t9.id";

   if($_SESSION[$_sesmodulename]["sql_item_id"] != "")
      $joisql .= " INNER JOIN shipment_items t6 ON t1.id = t6.shipment_id ";

   $cntsql = " select count(distinct t1.id) 'cc'
               from shipment t1
               {$joisql}
               where
               t1.shp_status IN ({$sqlstatstr})";

   $datsql = " select distinct t1.shp_num, t2.supp_company, t1.shp_total_netto, t1.shp_supplier_docnum, t1.shp_delivery_date,
                      t1.shp_crtdat, t1.shp_status, t1.id, t9.sord_number
               from shipment t1
               {$joisql}
               where
               t1.shp_status IN ({$sqlstatstr}) ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t1.shp_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t1.shp_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_supplier"])
      $seasql .= " and t1.shp_supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and (t1.shp_supplier_docnum like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t1.shp_num like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t9.sord_number like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%') ";

   if($_SESSION[$_sesmodulename]["sql_item_id"] != "")
      $seasql .= " and t6.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                   and t6.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
   
   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" || $_SESSION[$_sesmodulename]["sql_dateto"] != "")
   {
      if($_SESSION[$_sesmodulename]["sql_dateto"] == "")
         $_SESSION[$_sesmodulename]["sql_dateto"] = date('d.m.Y');
      if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
         $_SESSION[$_sesmodulename]["sql_datefrom"] = "01.01.".date('Y');

      $sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_datefrom"]);
      $sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_dateto"], false);

      $seasql .= " and t1.shp_delivery_date between {$sqldate_from} and {$sqldate_to} ";
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
   $shipments = $CON->select($datsql);
   
   //----------------------------------------------------------------------------------
   $suppliers  = getSuppliers($CON);
   $companies  = getCompanies($CON);
   $shops      = getShops($CON);

   //----------------------------------------------------------------------------------
   ?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <?php
   printJSsetCompanyShop($shops);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Resumen de guias de despacho</b></td>
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
            <col width="385">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4">Opciones de busqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Numero</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:375px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Empresa</td>
            <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Articulo</td>
            <td class="content_row"><?php printOverviewItemSelect($_sesmodulename) ?></td>
            <td class="content_rowl">Sucursal</td>
            <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Proveedor</td>
            <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?php printOverviewPeriodSelect($_sesmodulename) ?></td>
         </tr>
         <tr>
            <?php
            if($_SESSION[$_sesmodulename]["filter_status"] != 3)
            {  ?>
               <td class="content_rowl">Estado</td>
               <td class="content_row">
                  <?php
                  for($x = 1; $x <= 3; $x++)
                  {  ?>
                     <input type="checkbox" name="sql_status[]" value="<?=$x?>"
                     <?php if(array_search($x, $_SESSION[$_sesmodulename]["sql_status"]) !== false) echo "checked"?>><?=getShipmentStatus($x, true)?>
                     <?php
                  }
                  ?>
               </td>
               <?php
            }
            else
            {  ?>
               <td class="content_rowl">&nbsp;</td>
               <td class="content_row">&nbsp;</td>
               <?php
            }
            ?>
            <td class="content_row" align="right" colspan="2">
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
            <col width="90">
            <col width="100">
            <col>
            <col width="90">
            <col width="25">
            <col width="85">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
            <td class="content_tbl_subheader" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
            <td class="content_tbl_subheader" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($shipments) && $shipments != false; $x++)
         {
            $_SESSION[$_sesmodulename]["FLW"][$shipments[$x]["id"]]["L"] = (int)$shipments[($x -1)]["id"];
            $_SESSION[$_sesmodulename]["FLW"][$shipments[$x]["id"]]["N"] = (int)$shipments[($x +1)]["id"];
            
            $statimg = "";
            switch((int)$shipments[$x]["shp_status"])
            {
               case 1: $statimg = "red_active.gif"; break;
               case 2: $statimg = "green_active.gif"; break;
               case 3: $statimg = "blue_active.gif"; break;
            }
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=$shipments[$x]["shp_num"]?></td>
               <td class="content_row"><?=$shipments[$x]["supp_company"]?></td>
               <td class="content_row"><?=$shipments[$x]["shp_supplier_docnum"]?></td>
               <td class="content_row"><?=$shipments[$x]["sord_number"]?>&nbsp;</td>
               <td class="content_row">$ <?=printPrice($shipments[$x]["shp_total_netto"],0)?>&nbsp;</td>
               <td class="content_row"><?=date('d.m.Y', $shipments[$x]["shp_crtdat"])?></td>
               <td class="content_row"><?=date('d.m.Y', $shipments[$x]["shp_delivery_date"])?></td>
               <td class="content_row" align="center">
                  <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getShipmentStatus($shipments[$x]["shp_status"])?>">
               </td>
               <td class="content_row" align="center">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&id={$shipments[$x]["id"]}", "", "pencil");
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
                  <b class="msg_save_err">No hay datos disponibles</b>
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
      </td>
   </tr>
   </table>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?php
}