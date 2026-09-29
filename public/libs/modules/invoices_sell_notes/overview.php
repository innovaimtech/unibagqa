<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "xinvoicessellnotes";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Número" => "2", "NC/ND" => "10", "Factura" => "9", "Cliente" => "7", "Monto Nota Crédito" => "5,6",
                                "Fecha" => "3", "Fecha Venc." => "8",
                                "Estado" => "4");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
                  
//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

if(!(int)$_SESSION[$_sesmodulename]["sql_note_type"])
   $_SESSION[$_sesmodulename]["sql_note_type"] = 1;
   
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
      $_SESSION[$_sesmodulename]["sql_note_type"] = (int)$_REQUEST["sql_note_type"];
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

   if($_SESSION[$_sesmodulename]["filter_status"] == "1,2,3,4")
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]) ||
         (array_search(1,$_SESSION[$_sesmodulename]["sql_status"]) === false &&
          array_search(2,$_SESSION[$_sesmodulename]["sql_status"]) === false &&
          array_search(3,$_SESSION[$_sesmodulename]["sql_status"]) === false &&
          array_search(4,$_SESSION[$_sesmodulename]["sql_status"]) === false))
      $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>3,3=>4);
         /*
   if($_SESSION[$_sesmodulename]["filter_status"] == "3,4")
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]) ||
         $_SESSION[$_sesmodulename]["sql_status"][0] == 1 ||
         $_SESSION[$_sesmodulename]["sql_status"][0] == 2)
         $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>3,1=>4);
         */
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "del")
   {
      $sql = " update invoices_notes_sell
               set
               note_status = 0
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $seasql = " and note_type = {$_SESSION[$_sesmodulename]["sql_note_type"]} ";
   
   $joisql = " LEFT OUTER JOIN company_data t2  ON t1.note_company_id   = t2.id
               LEFT OUTER JOIN company_shops t3 ON t1.note_shop_id      = t3.id
               LEFT OUTER JOIN customer t4      ON t1.note_cust_id      = t4.id ";

   if($_SESSION[$_sesmodulename]["sql_item_id"] != "")
      $joisql .= " INNER JOIN invoices_notes_sell_items t6 ON t1.id = t6.note_id ";

   $cntsql = " select count(distinct t1.id) 'cc'
               from invoices_notes_sell t1
               {$joisql}
               where
               t1.note_status IN ({$_SESSION[$_sesmodulename]["filter_status"]})";

   $datsql = " select distinct t1.id, t1.note_number, t1.note_date, t1.note_status, t1.note_total_brutto,
                      t3.shop_name, t4.cust_name, t1.note_estpay_date, t1.note_docnumber, t1.note_invcnumber,
                      t1.note_sgntr_doc1
               from invoices_notes_sell t1
               {$joisql}
               where
               t1.note_status IN ({$_SESSION[$_sesmodulename]["filter_status"]}) ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t1.note_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t1.note_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_customer"])
      $seasql .= " and t1.note_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and ( t1.note_number      like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                         t1.note_docnumber   like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                         t1.note_invcnumber  like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%') ";

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

      $seasql .= " and t1.note_date between {$sqldate_from} and {$sqldate_to} ";
   }

   //----------------------------------------------------------------------------------
   $seastatstr = "";
   foreach($_SESSION[$_sesmodulename]["sql_status"] AS $seastat)
      $seastatstr .= $seastat.",";
   $seastatstr = substr($seastatstr, 0, -1);
   $seasql .= " and note_status IN ({$seastatstr}) ";

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
   $customers  = getCustomers($CON);
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
      <td height="30"><b class="content_header">Resumen de notas</b></td>
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
               <input name="sql_stext" type="text" class="text" style="width:85px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               &nbsp;Tipo:
               <input type="radio" name="sql_note_type" value="1" <?if($_SESSION[$_sesmodulename]["sql_note_type"] == 1) echo "checked"?>> <?=getInvoiceBuyNoteType(1)?>
               <input type="radio" name="sql_note_type" value="2" <?if($_SESSION[$_sesmodulename]["sql_note_type"] == 2) echo "checked"?>> <?=getInvoiceBuyNoteType(2)?>
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
            {  ?>
               <td class="content_rowl">Estado</td>
               <td class="content_row">
                  <?php
                  for($x = 3; $x <= 4; $x++)
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
            <col>
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
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
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
            switch((int)$invoices[$x]["note_status"])
            {
               case 1: $statimg = "red_active.gif"; break;
               case 2: $statimg = "orange_active.gif"; break;
               case 3: $statimg = "green_active.gif"; break;
               case 4: $statimg = "gray_active.gif"; break;
            }
            $invcnumr = "";
            $invcnums = explode(",",$invoices[$x]["note_invcnumber"]);
            $subx     = 1;
            foreach($invcnums AS $invcnum)
            {
               $invcnum = substr($invcnum, 0, strpos($invcnum, "-"));
               $invcnumr .= $invcnum.",";
               if($subx % 4 == 0 && $subx < count($invcnums))
                  $invcnumr .= "<br>";
               $subx++;
            }
            $invcnumr = substr($invcnumr, 0, -1);
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row">
                  <?php
                  if($invoices[$x]["note_status"] > 1 && $invoices[$x]["note_sgntr_doc1"] != "")
                  {  ?>
                     <a href="javascript:void(0);" class="link"
                     onclick="document.all.idxifrsrc.src = './libs/modules/structure/document_file.php?type=0&hash=<?=$invoices[$x]["note_sgntr_doc1"]?>&name=Nota-<?=$invoices[$x]["note_docnumber"]?>.pdf&path=../../../docs.electrpdf/'"><?=$invoices[$x]["note_number"]?></a>
                     <?php
                  }
                  else
                     echo $invoices[$x]["note_number"];
                  ?>
               </td>
               <td class="content_row"><?=$invoices[$x]["note_docnumber"]?>&nbsp;</td>
               <td class="content_row"><?=$invcnumr?>&nbsp;</td>
               <td class="content_row"><?=$invoices[$x]["cust_name"]?></td>
               <td class="content_row">$ <?=printPrice($invoices[$x]["note_total_brutto"],0)?></td>
               <td class="content_row"><?=date('d.m.Y',$invoices[$x]["note_date"])?></td>
               <td class="content_row">
                  <?php
                  if($invoices[$x]["note_estpay_date"] > 0)
                     echo date('d.m.Y',$invoices[$x]["note_estpay_date"]);
                  else
                     echo "&nbsp;";
                  ?>
               </td>
               <td class="content_row" align="center">
                  <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getInvoiceBuyStatus($invoices[$x]["note_status"])?>">
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
               <td class="content_row" colspan="9" align="center">
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