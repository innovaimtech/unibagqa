<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "invoicesbuy_combined";
$_sesbasefilterstatus   = "";
$_sesbaseorderby        = "3 desc,2 desc, 1";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Número" => "2", "N° Doc." => "9", "Proveedor" => "7",
                                 "Monto Doc." => "5,6",
                                 "Fecha Doc." => "3", "Venc./Creado" => "8",
                                 "N° OC" => "11", "Estado" => "4");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

if($_REQUEST["exec"] == "editinvoice")
   require_once("./libs/modules/invoices_buy/overview.edit.php");
elseif($_REQUEST["exec"] == "editshipment")
   require_once("./libs/modules/shipments/overview.edit.php");
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $sql_item = explode("#", $_REQUEST["item_id"]);

      $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
      $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
      $_SESSION[$_sesmodulename]["sql_supplier"]  = (int)$_REQUEST["sql_supplier"];
      $_SESSION[$_sesmodulename]["sql_xtype"]     = (int)$_REQUEST["sql_xtype"];
      $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
      $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
      $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
      $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
      $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);
      $_SESSION[$_sesmodulename]["sql_status"]    = $_REQUEST["sql_status"];
      $_SESSION[$_sesmodulename]["sql_invcstatus"] = $_REQUEST["sql_invcstatus"];
      $_SESSION[$_sesmodulename]["sql_shpstatus"]  = $_REQUEST["sql_shpstatus"];
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

   if(!is_array($_SESSION[$_sesmodulename]["sql_invcstatus"]))
      $_SESSION[$_sesmodulename]["sql_invcstatus"] = Array(0=>1,1=>2,2=>3);
   if(!is_array($_SESSION[$_sesmodulename]["sql_shpstatus"]))
      $_SESSION[$_sesmodulename]["sql_shpstatus"] = Array(0=>1,1=>2,2=>3);

   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "delinvc")
   {
      $sql = " update invoices_buy
               set
               invc_status = 0
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "delshipment")
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
   $seasql = "";

   //----------------------------------------------------------------------------------
   // FACTURAS
   //----------------------------------------------------------------------------------
   $joisql = " LEFT OUTER JOIN company_data t2  ON t1.invc_company_id   = t2.id
               LEFT OUTER JOIN company_shops t3 ON t1.invc_shop_id      = t3.id
               LEFT OUTER JOIN supplier t4      ON t1.invc_supplier_id  = t4.id ";

   if($_SESSION[$_sesmodulename]["sql_item_id"] != "")
      $joisql .= " INNER JOIN invoices_buy_parts_items t6 ON t1.id = t6.invc_id ";

   $cntsql = " select count(distinct t1.id) 'cc'
               from invoices_buy t1
               {$joisql}
               where
               t1.invc_status > 0 ";
   // and t1.invc_status IN ({$_SESSION[$_sesmodulename]["filter_status"]})

   $datsql = " select distinct t1.id, t1.invc_number, t1.invc_date, t1.invc_status, t1.invc_total_brutto,
                      t3.shop_name, t4.supp_company, t1.invc_estpay_date, t1.invc_docnumber,
                      'Factura' AS 'xdoctype',
                      (
                         select GROUP_CONCAT(distinct sub2.sord_number ORDER BY sub2.sord_number SEPARATOR ', ')
                         from invoices_buy_parts sub1
                         INNER JOIN supplier_order sub2 ON sub1.part_sord_id = sub2.id
                         where
                         sub1.part_invc_id = t1.id and
                         sub1.part_sord_id > 0
                      )
                      AS 'sord_number'
               from invoices_buy t1
               {$joisql}
               where
               t1.invc_status > 0 ";
   if((int)$_SESSION[$_sesmodulename]["sql_xtype"] == 1)
   {
      $cntsql .= " and 1 = 2 ";
      $datsql .= " and 1 = 2 ";
   }
   // and t1.invc_status IN ({$_SESSION[$_sesmodulename]["filter_status"]})

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t1.invc_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_supplier"])
      $seasql .= " and t1.invc_supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and ( t1.invc_number      like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                         t1.invc_docnumber   like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' ) ";

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

      $seasql .= " and t1.invc_date between {$sqldate_from} and {$sqldate_to} ";
   }

   //----------------------------------------------------------------------------------
   $seastatstr = "";
   foreach($_SESSION[$_sesmodulename]["sql_invcstatus"] AS $seastat)
      $seastatstr .= $seastat.",";
   $seastatstr = substr($seastatstr, 0, -1);
   $seasql .= " and t1.invc_status IN ({$seastatstr}) ";

   //----------------------------------------------------------------------------------
   $cntsql   .= $seasql;
   $datsql   .= $seasql;

   //----------------------------------------------------------------------------------
   // GUIAS
   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " LEFT OUTER JOIN company_data t2x    ON t1.shp_company_id   = t2x.id
               LEFT OUTER JOIN company_shops t3x   ON t1.shp_shop_id      = t3x.id
               LEFT OUTER JOIN supplier t2         ON t1.shp_supplier_id = t2.id
               LEFT OUTER JOIN supplier_order t9   ON t1.shp_supporder_id = t9.id";

   if($_SESSION[$_sesmodulename]["sql_item_id"] != "")
      $joisql .= " INNER JOIN shipment_items t6 ON t1.id = t6.shipment_id ";

   $cntsql .= " UNION ALL
               select count(distinct t1.id) 'cc'
               from shipment t1
               {$joisql}
               where
               t1.shp_status > 0 ";


   $datsql .= " UNION ALL
               select distinct t1.id, t1.shp_num 'invc_number', t1.shp_delivery_date 'invc_date',
                      t1.shp_status 'invc_status', t1.shp_total_netto 'invc_total_brutto',
                      t3x.shop_name, t2.supp_company, t1.shp_crtdat 'invc_estpay_date',
                      t1.shp_supplier_docnum, 'Guia de despacho' AS 'xdoctype',
                      t9.sord_number
               from shipment t1
               {$joisql}
               where
               t1.shp_status > 0 ";

   if((int)$_SESSION[$_sesmodulename]["sql_xtype"] == 2)
   {
      $cntsql .= " and 1 = 2 ";
      $datsql .= " and 1 = 2 ";
   }

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
   $seastatstr = "";
   foreach($_SESSION[$_sesmodulename]["sql_shpstatus"] AS $seastat)
      $seastatstr .= $seastat.",";
   $seastatstr = substr($seastatstr, 0, -1);
   $seasql .= " and t1.shp_status IN ({$seastatstr}) ";

   //----------------------------------------------------------------------------------
   $cntsql   .= $seasql;
   $datsql   .= $seasql;

   $itemcount = $CON->select($cntsql);
   $itemcount = (int)$itemcount[0]["cc"] + (int)$itemcount[1]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
   $datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";

   //----------------------------------------------------------------------------------
   $invoices = $CON->select($datsql);

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
   <table border="0" cellpadding="0" cellspacing="0" width="1180">
   <tr>
      <td height="30"><b class="content_header">Resumen de guias y facturas</b></td>
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
         <?=Nifty_printH("box2", "1180")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="100">
            <col>
            <col width="100">
            <col width="485">
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
            <td class="content_rowl">Estado facturas</td>
            <td class="content_row">
               <?php
               for($x = 1; $x <= 3; $x++)
               {  ?>
                  <input type="checkbox" name="sql_invcstatus[]" value="<?=$x?>"
                  <?php if(array_search($x, $_SESSION[$_sesmodulename]["sql_invcstatus"]) !== false) echo "checked"?>><?=getInvoiceBuyStatus($x, true)?>
                  <?php
               }
               ?>
            </td>
            <td class="content_rowl">Estado guias</td>
            <td class="content_row">
               <?php
               for($x = 1; $x <= 3; $x++)
               {  ?>
                  <input type="checkbox" name="sql_shpstatus[]" value="<?=$x?>"
                  <?php if(array_search($x, $_SESSION[$_sesmodulename]["sql_shpstatus"]) !== false) echo "checked"?>><?=getShipmentStatus($x, true)?>
                  <?php
               }
               ?>
            </td>
         </tr>
         <tr>
            <td class="content_rowl">Tipo</td>
            <td class="content_row">
               <input type="radio" name="sql_xtype" value="0" <?if((int)$_SESSION[$_sesmodulename]["sql_xtype"] == 0) echo "checked"?>> Todos
               <input type="radio" name="sql_xtype" value="1" <?if((int)$_SESSION[$_sesmodulename]["sql_xtype"] == 1) echo "checked"?>> Solo guias
               <input type="radio" name="sql_xtype" value="2" <?if((int)$_SESSION[$_sesmodulename]["sql_xtype"] == 2) echo "checked"?>> Solo facturas
            </td>
            <?php
            /*
            if($_SESSION[$_sesmodulename]["filter_status"] != 3)
            {  ?>
               <td class="content_rowl">Estado</td>
               <td class="content_row">
                  <?php
                  for($x = 1; $x <= 3; $x++)
                  {  ?>
                     <input type="checkbox" name="sql_status[]" value="<?=$x?>"
                     <?php if(array_search($x, $_SESSION[$_sesmodulename]["sql_status"]) !== false) echo "checked"?>><?=getInvoiceBuyStatus($x, true)?>
                     <?php
                  }
                  ?>
               </td>
               <?php
            }
            else
            {  ?>
               <td class="content_row" colspan="2">&nbsp;</td>
               <?php
            }
            */
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
         <?=Nifty_printH("box1", "1180")?>
         <?php
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="115">
            <col width="75">
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col width="25">
            <col width="85">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader">Tipo documento</td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader"><nobr><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></nobr></td>
            <td class="content_tbl_subheader"><nobr><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></nobr></td>
            <td class="content_tbl_subheader"><nobr><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></nobr></td>
            <td class="content_tbl_subheader" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php

         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($invoices) && $invoices != false; $x++)
         {
            $_SESSION[$_sesmodulename]["FLW"][$invoices[$x]["id"]]["L"] = (int)$invoices[($x -1)]["id"];
            $_SESSION[$_sesmodulename]["FLW"][$invoices[$x]["id"]]["N"] = (int)$invoices[($x +1)]["id"];

            $statimg = "";
            $execparam = "";
            if($invoices[$x]["xdoctype"] == "Factura")
            {
               $execparam = "editinvoice";
               switch((int)$invoices[$x]["invc_status"])
               {
                  case 1: $statimg = "red_active.gif"; break;
                  case 2: $statimg = "orange_active.gif"; break;
                  case 3: $statimg = "green_active.gif"; break;
               }
            }
            else
            {
               $execparam = "editshipment";
               switch((int)$invoices[$x]["invc_status"])
               {
                  case 1: $statimg = "red_active.gif"; break;
                  case 2: $statimg = "green_active.gif"; break;
                  case 3: $statimg = "blue_active.gif"; break;
               }
            }
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=$invoices[$x]["xdoctype"]?></td>
               <td class="content_row"><?=$invoices[$x]["invc_number"]?></td>
               <td class="content_row"><?=$invoices[$x]["invc_docnumber"]?>&nbsp;</td>
               <td class="content_row"><?=$invoices[$x]["supp_company"]?></td>
               <td class="content_row"><nobr>$ <?=printPrice($invoices[$x]["invc_total_brutto"],0)?></nobr></td>
               <td class="content_row"><?=date('d.m.Y',$invoices[$x]["invc_date"])?></td>
               <td class="content_row">
                  <?php
                  if($invoices[$x]["invc_estpay_date"] > 0)
                     echo date('d.m.Y',$invoices[$x]["invc_estpay_date"]);
                  else
                     echo "&nbsp;";
                  ?>
               </td>
               <td class="content_row"><?=$invoices[$x]["sord_number"]?>&nbsp;</td>
               <td class="content_row" align="center">
                  <?php
                  if($invoices[$x]["xdoctype"] == "Factura")
                  {  ?>
                     <img class="select" src="./images/content/<?=$statimg?>"
                     title="Estado: <?=getInvoiceBuyStatus($invoices[$x]["invc_status"])?>">
                     <?php
                  }
                  else
                  {  ?>
                     <img class="select" src="./images/content/<?=$statimg?>"
                     title="Estado: <?=getShipmentStatus($invoices[$x]["invc_status"])?>">
                     <?php
                  }
                  ?>
               </td>
               <td class="content_row" align="center">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec={$execparam}&subexec=edit&id={$invoices[$x]["id"]}", "", "pencil");
                  ?>
               </td>
            </tr>
            <?php
         }

         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" colspan="12" align="center">
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
      </td>
   </tr>
   </table>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?php
}