<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stock_shops_charges";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo" => "2", "Unidad" => "2", "Lote" => "2", "Producción" => "2", "Vencimiento" => "2",
                                "Entrada" => "2", "Salida" => "2", "Disponible" => "2",);

$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]      = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_supplier"]  = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON, true);
$shops      = getShops($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
$seasql = "";

$joisql = " INNER JOIN item_suppliers  t5 ON ( t1.tran_item_id = t5.item_id and ";

if($_SESSION[$_sesmodulename]["sql_supplier"])
   $joisql .= " t5.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ) ";
else
   $joisql .= " t5.item_supp_act = 1 ) ";
   
$joisql .= " INNER JOIN item_productcats t7   ON t1.tran_item_id = t7.item_id
             LEFT OUTER JOIN item_units t8    ON t2.item_unit = t8.id
             LEFT OUTER JOIN productcats t9   ON t7.cat_id = t9.id";

//----------------------------------------------------------------------------------
$datsql = " select t1.tran_company_id, t1.tran_shop_id, t1.tran_item_id, t1.tran_prod_date,
                   t1.tran_expire_date, t2.item_number_prod, t2.item_title,
                   t1.tran_charge_number, t8.unit_name,
                   SUM(t1.tran_amount_avail) + SUM(t1.tran_amount_used)  'tran_amount',
                   SUM(t1.tran_amount_used) 'tran_amount_used',
                   SUM(t1.tran_amount_avail) 'tran_amount_avail'
            from tran_charges t1
            INNER JOIN item t2 ON t1.tran_item_id = t2.id
            {$joisql}
            where
            t1.tran_booked    = 1 ";
     
//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_item_type"] == "itemlist")
{
   $datsql .= " and 1 = 2 ";
}
elseif($_SESSION[$_sesmodulename]["sql_item_type"] == "item")
{
   $datsql .= " and t1.tran_item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
}

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t1.tran_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t1.tran_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t7.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";

//----------------------------------------------------------------------------------
$cntsql   .= $seasql;
$datsql   .= $seasql;

$itemcount = $CON->select($cntsql);
$itemcount = (int)$itemcount[0]["cc"];

//----------------------------------------------------------------------------------
$_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

$datsql .= " group by 1,2,3,4,5,6,7,8,9
             order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";

//----------------------------------------------------------------------------------
$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
      {
         if(!(int)$_SESSION[$_sesmodulename]["sql_shop"] || ((int)$_SESSION[$_sesmodulename]["sql_shop"] && $_SESSION[$_sesmodulename]["sql_shop"] == $shop["id"]))
            array_push($selshops, $shop);
      }
}

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
{
   $sql = " select supp_company
            from supplier
            where
            id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
   $suppdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"] = $suppdata[0]["supp_company"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"] = "TODO";

$_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"] = $selshops;
//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

printJSsetCompanyShop($shops);
?>
<script language="JavaScript">
function detectEvent (event, mode)
{
   var xurl = './libs/modules/items/searchitem.fancy.php?mode=' +mode;
   var keyCode = ('which' in event) ? event.which : event.keyCode;
   if(keyCode == 112)
      showFancybox(xurl, 'iframe', 1000, 450, 'auto');
}
</script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Stock por sucursal</b></td>
   <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults($itemcount); else echo $savemsg;?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst"
      onsubmit="return checkform(new Array(this.sql_company))">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
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
         <td class="content_rowl">Artículo</td>
         <td class="content_row"><?php printOverviewItemSelect($_sesmodulename) ?></td>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Proveedor</td>
         <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Familia</td>
         <td class="content_row">
            <select class="text" name="sql_pcat" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($pcats AS $pcat)
               {  ?>
                  <option value="<?=$pcat["id"]?>"
                  <?php if($pcat["id"] == $_SESSION[$_sesmodulename]["sql_pcat"]) echo "selected"?>><?=sprintf("%03s", $pcat["id"])?> - <?=$pcat["cat_title"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">&nbsp;</td>
         <td class="content_row">&nbsp;</td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="132">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if($itemcount > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if($itemcount > 0)
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td> 
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
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="75">
         <col>
         <col>
         <col>
         <col width="90">
         <col width="90">
         <col>
         <col>
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
         <td class="content_tbl_subheader content_row_os" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
         <td class="content_tbl_subheader content_row_os" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
         <td class="content_tbl_subheader content_row_os" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 8)?></td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items) && $items != false; $x++)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$items[$x]["item_number_prod"]?></td>
            <td class="content_row_os"><?=$items[$x]["item_title"]?></td>
            <td class="content_row_os"><?=$items[$x]["unit_name"]?></td>
            <td class="content_row_os"><?=$items[$x]["tran_charge_number"]?>&nbsp;</td>
            <td class="content_row_os"><?php if($items[$x]["tran_prod_date"] > 0) echo date('d.m.Y', $items[$x]["tran_prod_date"]); else echo "&nbsp"; ?></td>
            <td class="content_row_os"><?php if($items[$x]["tran_expire_date"] > 0) echo date('d.m.Y', $items[$x]["tran_expire_date"]); else echo "&nbsp"; ?></td>
            <td class="content_row_os" align="center"><?=printPrice($items[$x]["tran_amount"],10)?></td>
            <td class="content_row_os" align="center"><?=printPrice($items[$x]["tran_amount_used"],10)?></td>
            <td class="content_row_os" align="center"><?=printPrice($items[$x]["tran_amount_avail"],10)?></td>
         </tr>
         <?php
         $_TOTAL_AMT    += $items[$x]["tran_amount"];
         $_TOTAL_USED   += $items[$x]["tran_amount_used"];
         $_TOTAL_AVAIL  += $items[$x]["tran_amount_avail"];
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
      else
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_totals content_row_os" colspan="6">Total</td>
            <td class="content_row_totals content_row_os" align="center"><?=printPrice($_TOTAL_AMT,10)?></td>
            <td class="content_row_totals content_row_os" align="center"><?=printPrice($_TOTAL_USED,10)?></td>
            <td class="content_row_totals content_row_os" align="center"><?=printPrice($_TOTAL_AVAIL,10)?></td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsItemShops($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsItemShops($CON);
  
if($pdffile != "")
{
   $doctitle = "Stock-por-sucursal-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Stock-por-sucursal-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>