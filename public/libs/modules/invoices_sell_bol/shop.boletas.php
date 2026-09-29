<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2019 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "invoicessellbolshops";
$_sesbasefilterstatus   = "1,2";
$_sesbaseorderby        = "3 desc,2";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Boleta" => "9", "Cliente" => "7", "Empresa/Sucursal" => "5,6",
                                "Monto" => "10", "Fecha Boleta" => "3,2", "Estado" => "4");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);
   
if($_REQUEST["exec"] == "edit")
{
   require_once("shop.boletas.view.php");
}
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $sql_item = explode("#", $_REQUEST["item_id"]);
      
      $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
      $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
      $_SESSION[$_sesmodulename]["sql_customer"]  = (int)$_REQUEST["sql_customer"];
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

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " LEFT OUTER JOIN company_data t2  ON t1.invc_company_id   = t2.id
               LEFT OUTER JOIN company_shops t3 ON t1.invc_shop_id      = t3.id
               LEFT OUTER JOIN customer t4      ON t1.invc_cust_id      = t4.id
               LEFT OUTER JOIN company_shops_cashings t5 ON t1.invc_caid = t5.id
               LEFT OUTER JOIN user t6          ON t1.invc_userid_seller = t6.id ";

   if($_SESSION[$_sesmodulename]["sql_item_id"] != "")
      $joisql .= " INNER JOIN invoices_sell_bol_parts_items t6x ON t1.id = t6x.invc_id ";

   $cntsql = " select count(distinct t1.id) 'cc'
               from invoices_sell_bol t1
               {$joisql}
               where
               t1.invc_status > 1 and
               t3.shop_isremote = 1 ";

   $datsql = " select distinct t1.id, t1.invc_number, t1.invc_date, t1.invc_status, t2.company_short,
                      t3.shop_name, t4.cust_name, t1.invc_estpay_date, t1.invc_docnumber,
                      t1.invc_total_brutto, t1.invc_desc_intern, t5.ca_name,
                      t6.user_firstname, t6.user_lastname
               from invoices_sell_bol t1
               {$joisql}
               where
               t1.invc_status > 1 and
               t3.shop_isremote = 1  ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t1.invc_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_customer"])
      $seasql .= " and t1.invc_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and ( t1.invc_number      like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                         t1.invc_docnumber   like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' ) ";

   if($_SESSION[$_sesmodulename]["sql_item_id"] != "")
      $seasql .= " and t6x.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                   and t6x.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";

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
   $cntsql   .= $seasql;
   $datsql   .= $seasql;
   $itemcount = $CON->select($cntsql);
   $itemcount = (int)$itemcount[0]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}, t1.id desc ";
   $datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";

   //----------------------------------------------------------------------------------
   $invoices = $CON->select($datsql);

   //----------------------------------------------------------------------------------
   $companies  = getCompanies($CON);
   $shops      = getShops($CON, true, false);
   $customers  = getCustomers($CON);

   //----------------------------------------------------------------------------------
   ?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <?php
   printJSsetCompanyShop($shops);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Boletas de sucursales</b></td>
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
            <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Número</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:375px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Empresa</td>
            <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Artículo</td>
            <td class="content_row"><?php printOverviewItemSelect($_sesmodulename) ?></td>
            <td class="content_rowl">Sucursal</td>
            <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Cliente</td>
            <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?php printOverviewPeriodSelect($_sesmodulename) ?></td>
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
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="75">
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col width="85">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader content_row_os"><nobr><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></nobr></td>
            <td class="content_tbl_subheader content_row_os">Vendedor</td>
            <td class="content_tbl_subheader content_row_os">Caja</td>
            <td class="content_tbl_subheader content_row_os" align="center">Opciones</td>
         </tr>
         <?php
         
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($invoices) && $invoices != false; $x++)
         {  ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><?=$invoices[$x]["invc_docnumber"]?>&nbsp;</td>
               <td class="content_row_os">
                  <?=$invoices[$x]["cust_name"]?>
                  <?php
                  if($invoices[$x]["invc_desc_intern"] != "")
                  {
                     if($invoices[$x]["cust_name"] != "")
                        echo "<br>";
                     echo $invoices[$x]["invc_desc_intern"];
                  }
                  ?>
               </td>
               <td class="content_row_os"><nobr><?=$invoices[$x]["shop_name"]?></nobr></td>
               <td class="content_row_os"><nobr>$ <?=printPrice($invoices[$x]["invc_total_brutto"])?></nobr></td>
               <td class="content_row_os"><?=date('d.m.Y',$invoices[$x]["invc_date"])?></td>
               <td class="content_row_os"><nobr><?=$invoices[$x]["ca_name"]?></nobr></td>
               <td class="content_row_os"><nobr><?=$invoices[$x]["user_firstname"]?>&nbsp;<?=$invoices[$x]["user_lastname"]?></nobr></td>
               <td class="content_row_os" align="center">
                  <?php
                  printButton("Ver", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&id={$invoices[$x]["id"]}", "", "eye");
                  ?>
               </td>
            </tr>
            <?php
         }

         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row_os" colspan="8" align="center">
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