<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$sql = " select *
         from user
         where
         id = {$_SESSION["user_id"]}";
$userdata = $CON->select($sql);
$userdata = $userdata[0];

//----------------------------------------------------------------------------------
$_sesmodulename         = "invoicessell";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Número" => "2", "Factura" => "9", "Cliente" => "7", "Monto Factura" => "5,6",
                                "Fecha Factura" => "3,2", "Fecha Venc." => "8",
                                "Estado" => "4", "Rut" => 12);
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);
   
if($_REQUEST["exec"] == "edit")
{
   require_once("overview.edit.php");
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
      $_SESSION[$_sesmodulename]["sql_vonlinenum"]    = trim(addslashes($_REQUEST["sql_vonlinenum"]));
      $_SESSION[$_sesmodulename]["sql_vorigen"]       = (int)$_REQUEST["sql_vorigen"];
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);
   /*
   if($_SESSION[$_sesmodulename]["filter_status"] == "1,2,3,4")
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]) ||
         $_SESSION[$_sesmodulename]["sql_status"][0] == 1 ||
         $_SESSION[$_sesmodulename]["sql_status"][0] == 2 ||
         $_SESSION[$_sesmodulename]["sql_status"][0] == 3 ||
         $_SESSION[$_sesmodulename]["sql_status"][0] == 4)
         $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>3,3=>4);


   if($_SESSION[$_sesmodulename]["filter_status"] == "3,4")
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]) ||
         $_SESSION[$_sesmodulename]["sql_status"][0] == 1 ||
         $_SESSION[$_sesmodulename]["sql_status"][0] == 2)
         $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>3,1=>4);
   */
   // $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>3,3=>4);

   if($_SESSION[$_sesmodulename]["filter_status"] != 5)
   if(!is_array($_SESSION[$_sesmodulename]["sql_status"]))
      $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>3,3=>4);

   foreach($_SESSION[$_sesmodulename]["sql_status"] AS $sqlst)
      $sqlstatstr .= $sqlst.",";
   $sqlstatstr = substr($sqlstatstr, 0, -1);

   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "del")
   {
      $sql = " update invoices_sell
               set
               invc_status = 0
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " LEFT OUTER JOIN company_data t2  ON t1.invc_company_id   = t2.id
               LEFT OUTER JOIN company_shops t3 ON t1.invc_shop_id      = t3.id
               LEFT OUTER JOIN customer t4      ON t1.invc_cust_id      = t4.id ";

   if($_SESSION[$_sesmodulename]["sql_item_id"] != "")
      $joisql .= " INNER JOIN invoices_sell_parts_items t6 ON t1.id = t6.invc_id ";

   $cntsql = " select count(distinct t1.id) 'cc'
               from invoices_sell t1
               {$joisql}
               where
               t1.invc_status IN ({$sqlstatstr}) and
               t3.shop_isremote = 0 ";

   $datsql = " select distinct t1.id
                             , t1.invc_number
                             , t1.invc_date
                             , t1.invc_status
                             , t1.invc_total_brutto
                             , t3.shop_name
                             , t4.cust_name
                             , t1.invc_estpay_date
                             , t1.invc_docnumber
                             , t1.invc_sgntr_doc1
                             , t1.invc_online_orderid
                             , t4.cust_rut
               from invoices_sell t1
               {$joisql}
               where
               t1.invc_status IN ({$sqlstatstr}) and
               t3.shop_isremote = 0  ";

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
      $seasql .= " and t6.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                   and t6.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
   if((int)$_SESSION[$_sesmodulename]["sql_vonlinenum"])
      $seasql .= " and t1.invc_online_orderid = ".(int)$_SESSION[$_sesmodulename]["sql_vonlinenum"]." ";
   if((int)$_SESSION[$_sesmodulename]["sql_vorigen"] == 1)
      $seasql .= " and t1.invc_online_orderid > 0 ";
   elseif((int)$_SESSION[$_sesmodulename]["sql_vorigen"] == 2)
      $seasql .= " and t1.invc_online_orderid = 0 ";
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
   foreach($_SESSION[$_sesmodulename]["sql_status"] AS $seastat)
      $seastatstr .= $seastat.",";
   $seastatstr = substr($seastatstr, 0, -1);
   $seasql .= " and invc_status IN ({$seastatstr}) ";

   if((int)$userdata["user_invclimit_perm"])
      $seasql .= " and
                     ( 
                        (
                           select count(txx2.id) 'cc'
                           from invoices_sell_parts txx
                           INNER JOIN orders txx2 ON txx.part_req_id = txx2.id
                           where
                           txx.part_invc_id  = t1.id and
                           txx.part_req_id   > 0 and
                           txx2.req_crtusr   = {$_SESSION["user_id"]}
                        ) > 0
                        or
                        (
                           select count(txx3.id) 'cc'
                           from invoices_sell_parts txx
                           INNER JOIN orders txx2 ON txx.part_req_id = txx2.id
                           INNER JOIN offers txx3 ON txx2.req_offerid = txx3.id
                           where
                           txx.part_invc_id  = t1.id and
                           txx.part_req_id   > 0 and
                           txx3.req_crtusr   = {$_SESSION["user_id"]}
                        )
                     ) ";

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
   $invoices = $CON->select($datsql);

   //----------------------------------------------------------------------------------
   $companies  = getCompanies($CON);
   $shops      = getShops($CON, false, true);
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
      <td height="30"><b class="content_header">Resumen de facturas</b></td>
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
            <?php
            if($_SESSION[$_sesmodulename]["filter_status"] != 5)
            {?>
               <td class="content_rowl">Estado</td>
               <td class="content_row">
                  <?php
                  for($x = 1; $x <= 4; $x++)
                  {  ?>
                     <input type="checkbox" name="sql_status[]" value="<?=$x?>"
                     <?php if(array_search($x, $_SESSION[$_sesmodulename]["sql_status"]) !== false) echo "checked"?>><?=getInvoiceBuyStatus($x, true)?>
                     <?php
                  }
                  ?>
               </td>
               <?php
            }
            /*
            else
            { 
             ?>
               <td class="content_rowl">Estado</td>
               <td class="content_row">
                  <?php
                  for($x = 1; $x <= 4; $x++)
                  {  ?>
                     <input type="checkbox" name="sql_status[]" value="<?=$x?>"
                     <?php if(array_search($x, $_SESSION[$_sesmodulename]["sql_status"]) !== false) echo "checked"?>><?=getInvoiceBuyStatus($x, true)?>
                     <?php
                  }
                  ?>
               </td>
               <?php
             }
            */
            ?>
            <td class="content_rowl">Origen Venta</td>
            <td class="content_row">
               <input type="radio" name="sql_vorigen" value="0" <?if((int)$_SESSION[$_sesmodulename]["sql_vorigen"] == 0) echo "checked"?>> Todos
               <input type="radio" name="sql_vorigen" value="1" <?if((int)$_SESSION[$_sesmodulename]["sql_vorigen"] == 1) echo "checked"?>> Solamente venta online
               <input type="radio" name="sql_vorigen" value="2" <?if((int)$_SESSION[$_sesmodulename]["sql_vorigen"] == 2) echo "checked"?>> Solamente venta local
            </td>
         </tr>
         <tr>
            <td class="content_rowl">Nº Venta online</td>
            <td class="content_row">
               <input name="sql_vonlinenum" type="text" class="text" style="width:375px"
               value="<?=$_SESSION[$_sesmodulename]["sql_vonlinenum"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
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
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
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
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader"><nobr><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></nobr></td>
            <td class="content_tbl_subheader"><nobr><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></nobr></td>
            <td class="content_tbl_subheader" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($invoices) && $invoices != false; $x++)
         {
            $_SESSION[$_sesmodulename]["FLW"][$invoices[$x]["id"]]["L"] = (int)$invoices[($x -1)]["id"];
            $_SESSION[$_sesmodulename]["FLW"][$invoices[$x]["id"]]["N"] = (int)$invoices[($x +1)]["id"];
            
            $statimg = "";
            switch((int)$invoices[$x]["invc_status"])
            {
               case 1: $statimg = "red_active.gif"; break;
               case 2: $statimg = "orange_active.gif"; break;
               case 3: $statimg = "green_active.gif"; break;
               case 4: $statimg = "gray_active.gif"; break;
            }
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row">
                  <?php
                  if($invoices[$x]["invc_status"] > 1 && $invoices[$x]["invc_sgntr_doc1"] != "")
                  {  ?>
                     <a href="javascript:void(0);" class="link"
                     onclick="document.all.idxifrsrc.src = './libs/modules/structure/document_file.php?type=0&hash=<?=$invoices[$x]["invc_sgntr_doc1"]?>&name=Factura-<?=$invoices[$x]["invc_docnumber"]?>.pdf&path=../../../docs.electrpdf/'"><?=$invoices[$x]["invc_number"]?></a>
                     <?php
                  }
                  else
                     echo $invoices[$x]["invc_number"];
                  ?>
               </td>
               <td class="content_row"><?=$invoices[$x]["invc_docnumber"]?>&nbsp;</td>
               <td class="content_row"><nobr><?=$invoices[$x]["cust_rut"]?></nobr>&nbsp;</td>
               <td class="content_row">
                  <?=$invoices[$x]["cust_name"]?>
                  <?php
                  if((int)$invoices[$x]["invc_online_orderid"])
                  {  ?>
                     <span style="border-radius:3px;background-color:#00A9A6;color:white;text-shadow:none;padding:2px;padding-left:5px;padding-right:5px">
                        <nobr><img src="/images/menu/icons/globe.png" style="height:13px;vertical-align:bottom">&nbsp;<?=$invoices[$x]["invc_online_orderid"]?></nobr>
                     </span>
                     <?php
                  }
                  ?>
               </td>
               <td class="content_row">$ <?= printPrice($invoices[$x]["invc_total_brutto"],0)?></td>
               <td class="content_row"><?=date('d.m.Y',$invoices[$x]["invc_date"])?></td>
               <td class="content_row">
                  <?php
                  if($invoices[$x]["invc_estpay_date"] > 0)
                     echo date('d.m.Y',$invoices[$x]["invc_estpay_date"]);
                  else
                     echo "&nbsp;";
                  ?>
               </td>
               <td class="content_row" align="center">
                  <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getInvoiceBuyStatus($invoices[$x]["invc_status"])?>">
               </td>
               <td class="content_row" align="center">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&id={$invoices[$x]["id"]}", "", "pencil");
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
         <br>
      </td>
   </tr>
   </table>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?php
}