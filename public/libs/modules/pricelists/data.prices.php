<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   //----------------------------------------------------------------------------------
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "pl_item_netto_") !== false && strpos($reqkey, "pl_item_netto_") == 0)
      {
         $idxarr     = explode("_", $reqkey);
         $item_type  = $idxarr[3];
         $item_id    = $idxarr[4];

         $sql = " delete from price_lists_items
                  where
                  pl_id       = {$_REQUEST["id"]} and
                  item_id     = {$item_id} and
                  item_type   = '{$item_type}'";
         $CON->no_result($sql);

         $item_netto = getPrice($_REQUEST[$reqkey],2);
         if($item_netto > 0)
         {
            $item_taxes_perc  = (float)$_REQUEST["pl_item_taxesperc_{$item_type}_{$item_id}"];
            $item_taxes       = round($item_netto / 100 * $item_taxes_perc, 0);
            $item_brutto       = $item_netto + $item_taxes;

            $item_brutto2     = getPrice($_REQUEST["pl_item_brutto2_{$item_type}_{$item_id}"]);
            $item_taxes_perc2 = (float)$_REQUEST["pl_item_taxesperc2_{$item_type}_{$item_id}"];
            $item_taxes2      = round($item_brutto2 / (100 + $item_taxes_perc2) * $item_taxes_perc2, 0);
            $item_netto2      = $item_brutto2 - $item_taxes2;

            $sql = " insert into price_lists_items
                     (pl_id, item_id, item_type,
                     item_sellprice_brutto, item_sellprice_netto, item_sellprice_taxes, item_sellprice_taxes_perc,
                     item_sellprice_brutto2, item_sellprice_netto2, item_sellprice_taxes2, item_sellprice_taxes_perc2)
                     VALUES
                     ({$_REQUEST["id"]}, {$item_id}, '{$item_type}',
                      {$item_brutto}, {$item_netto}, {$item_taxes}, {$item_taxes_perc},
                      {$item_brutto2}, {$item_netto2}, {$item_taxes2}, {$item_taxes_perc2})";
            $CON->no_result($sql);
         }
      }
   }
}

//----------------------------------------------------------------------------------
$_sesmodulename         = "pricelists_prices";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo" => "2", "P/Neto" => "4", "IVA" => "6", "P/Bruto" => "5");

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
   $_SESSION[$_sesmodulename]["sql_pcat"]      = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 50);

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_pcat"])
{
   $joisql  = " INNER JOIN item_productcats t7 ON t1.id = t7.item_id ";
   $joisql2 = " INNER JOIN item_productcats_itemlist t7 ON t1.id = t7.item_id ";
}

//----------------------------------------------------------------------------------
$cntsql = " select count(distinct t1.id) 'cc'
            from item t1
            {$joisql}
            LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item')
            where
            t1.item_status    = 1 ";

$datsql = " select distinct t1.item_number_prod, t1.item_title, t1.id, t1.item_sellprice_netto, t1.item_sellprice_brutto,
                   t1.item_sellprice_taxes, t1.item_sellprice_taxes_perc, 'item_type' 'I'
            from item t1
            {$joisql}
            LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item')
            where
            t1.item_status    = 1 ";
     
//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t7.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";

if($_SESSION[$_sesmodulename]["sql_stext"] != "")
{
   $seasql .= " and (t1.item_title        like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                     t1.item_number_prod  like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                     t1.item_number       like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                     tx.item_barcode      = '{$_SESSION[$_sesmodulename]["sql_stext"]}') ";
}
      
//----------------------------------------------------------------------------------
$cntsql   .= $seasql;
$datsql   .= $seasql;

//----------------------------------------------------------------------------------
$cntsql .= "UNION ALL
            select count(distinct t1.id) 'cc'
            from itemlist t1
            {$joisql2}
            LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'itemlist')
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";
$cntsql .= $seasql;

//----------------------------------------------------------------------------------
$datsql .= "UNION ALL
            select distinct t1.item_number_prod, t1.item_title, t1.id, t1.item_sellprice_netto, t1.item_sellprice_brutto,
                   t1.item_sellprice_taxes, t1.item_sellprice_taxes_perc, 'item_type' 'L'
            from itemlist t1
            {$joisql2}
            LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'itemlist')
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";

$datsql .= $seasql;


$itemcount = $CON->select($cntsql);
$itemcount = (int)$itemcount[0]["cc"] + (int)$itemcount[1]["cc"];

//----------------------------------------------------------------------------------
$_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
$repsql  = $datsql;
$datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";

//----------------------------------------------------------------------------------
$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

$pgcountadd = "&exec=edit&subcatexec=prices&id={$_REQUEST["id"]}";

$sql = " select *
         from price_lists
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

$sql = " select *
         from price_lists_items
         where
         pl_id = {$_REQUEST["id"]}";
$plitems = $CON->select($sql);
foreach($plitems AS $plitem)
   $plres[$plitem["item_type"]][$plitem["item_id"]] = $plitem;

unset($_SESSION["_STATS"]["pricelistdata"]["plres"]);
unset($_SESSION["_STATS"]["pricelistdata"]["plname"]);
$_SESSION["_STATS"]["pricelistdata"]["plres"] = $plres;
$_SESSION["_STATS"]["pricelistdata"]["plname"] = $headdata["pl_title"];

//----------------------------------------------------------------------------------
$sql = " select *
         from price_lists_shops
         where
         pl_id = {$_REQUEST["id"]}";
$selshops = $CON->select($sql);

$_SESSION["_STATS"]["pricelistdata"]["shopid"] = $selshops[0]["shop_id"];
?>
<script language="JavaScript">
function getIntegerFromValue(calval)
{
   if(calval == '') calval = '0';
   while(calval.indexOf(".") > 0) calval = calval.replace(".", "");
   while(calval.indexOf(",") > 0) calval = calval.replace(",", ".");
   if(calval.indexOf("0") > 0) while(calval.substr(0,1) == '0') calval = calval.substr(1);
   calval = parseFloat(calval);
   if(isNaN(calval)) calval = 0;
   return calval;
}
function recalcPrcs(idx, mode)
{
   var base_price = getIntegerFromValue($('#intcalc_' +idx).val());
   var margen_det = getIntegerFromValue($('#margendet_' +idx).val());
   var margen_may = getIntegerFromValue($('#margenmay_' +idx).val());

   var newprc_det = Math.round(base_price + (base_price / 100 * margen_det));
   var newprc_may = Math.round(base_price + (base_price / 100 * margen_may));

   if(mode == 'all' || mode == 'det')
      $('#prcdet_' +idx).val(newprc_det);
   if(mode == 'all' || mode == 'may')
      $('#prcmay_' +idx).val(newprc_may);
}
function setPrcs(idx)
{
   $('#pl_item_brutto_' +idx).val($('#prcdet_' +idx).val());
   $('#pl_item_brutto2_' +idx).val($('#prcmay_' +idx).val());
   $('#idx_calcitem_' +idx).toggle();
}
</script>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="exec" value="edit">
      <input type="hidden" name="subcatexec" value="prices">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <?=Nifty_printH("box2", "1180")?>
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
         <td class="content_rowl">Articulo</td>
         <td class="content_row">
            <input type="text" class="text" style="width:375px" onfocus="markfield(this,0)"
            name="sql_stext"
            value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
            onblur="markfield(this,1);">
         </td>
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
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="135">
               <col>
               <col width="135">
               <col width="135">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                  $_SESSION["_SUBMITBTN"] = 1;
                  ?>
               </td>
               <td align="left">
                  <?php
                  printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                  $_SESSION["_SUBMITBTN"] = 1;
                  ?>
               </td>
               <td align="right">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["search_active"])
                     printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset&exec=edit&subcatexec=prices&id={$_REQUEST["id"]} ", "", "arrow-circle-double-135", 130);
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
      <form action="index.php" method="post" class="fokusfirst" name="xform_pl">
      <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
      <input type="hidden" name="subexec" value="save">
      <input type="hidden" name="subcatexec" value="prices">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <?=Nifty_printH("box1", "1180")?>
      <?php
      printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"], $pgcountadd);
      ?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="90">
         <col>
         <col width="90">
         <col width="90">
         <col width="90">
         <col width="90">
         <col width="90">
         <col width="90">
         <col width="90">
         <col width="90">
<!--          <col> -->
<!--          <col> -->
<!--          <col> -->
<!--          <col width="20"> -->
<!--          <col width="20"> -->
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4" style="border-right:3px double #666666">Información Productos</td>
         <td class="content_tbl_header" colspan="3" align="center" style="border-right:3px double #666666">Precio Base</td>
         <td class="content_tbl_header" colspan="3" align="center" style="border-right:3px double #666666">Precio Lista</td>
<!--          <td class="content_tbl_header" colspan="3" align="center" style="border-right:3px double #666666">Precio Mayorista</td> -->
<!--          <td class="content_tbl_header" colspan="3" align="center">&nbsp;</td> -->
      </tr>
      <tr>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 0, $pgcountadd)?></td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 1, $pgcountadd)?></td>
         <td class="content_tbl_subheader content_row_os">Unidad</td>
         <td class="content_tbl_subheader content_row_os" style="border-right:3px double #666666" align="center">Stock</td>
         <td class="content_tbl_subheader content_row_os" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 2, $pgcountadd)?></td>
         <td class="content_tbl_subheader content_row_os" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 3, $pgcountadd)?></td>
         <td class="content_tbl_subheader content_row_os" align="right" style="border-right:3px double #666666"><?=printSortLink($_sesmodulename, $_sortlinks, 4, $pgcountadd)?></td>
         <td class="content_tbl_subheader content_row_os" align="right">P/Neto</td>
         <td class="content_tbl_subheader content_row_os" align="right">IVA</td>
         <td class="content_tbl_subheader content_row_os" align="right" style="border-right:3px double #666666">P/Bruto</td>
<!--          <td class="content_tbl_subheader content_row_os" align="right">P/Neto</td> -->
<!--          <td class="content_tbl_subheader content_row_os" align="right">IVA</td> -->
<!--          <td class="content_tbl_subheader content_row_os" align="right" style="border-right:3px double #666666">P/Bruto</td> -->
<!--          <td class="content_tbl_subheader content_row_os" align="center" colspan="2">Opciónes</td> -->
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         if($items[$x]["item_type"] == "item_typeI")
            $items[$x]["item_type"] = "item";
         else
            $items[$x]["item_type"] = "itemlist";

         $pl_item_sellprice_brutto  = 0.00;
         $pl_item_sellprice_netto   = 0.00;
         $pl_item_sellprice_taxes   = 0.00;
         $pl_item_sellprice_brutto2 = 0.00;
         $pl_item_sellprice_netto2  = 0.00;
         $pl_item_sellprice_taxes2  = 0.00;
         
         if(is_array($plres[$items[$x]["item_type"]][$items[$x]["id"]]))
         {
            $pldata = $plres[$items[$x]["item_type"]][$items[$x]["id"]];
            
            $pl_item_sellprice_brutto  = $pldata["item_sellprice_brutto"];
            $pl_item_sellprice_netto   = $pldata["item_sellprice_netto"];
            $pl_item_sellprice_taxes   = $pldata["item_sellprice_taxes"];

            $pl_item_sellprice_brutto2 = $pldata["item_sellprice_brutto2"];
            $pl_item_sellprice_netto2  = $pldata["item_sellprice_netto2"];
            $pl_item_sellprice_taxes2  = $pldata["item_sellprice_taxes2"];
         }

         $unitdesc = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$items[$x]["item_number_prod"]?></td>
            <td class="content_row_os"><?=$items[$x]["item_title"]?></td>
            <td class="content_row_os"><?=$unitdesc?></td>
            <td class="content_row_os" style="border-right:3px double #666666" align="center">
               <?php
               $stock = getItemShopCurrentStock($CON, $selshops[0]["shop_id"], $items[$x]["id"], $items[$x]["item_type"]);
               echo printPrice($stock,2);
               ?>
            </td>
            <td class="content_row_os" align="right"><?=printPrice($items[$x]["item_sellprice_netto"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($items[$x]["item_sellprice_taxes"])?></td>
            <td class="content_row_os" align="right" style="border-right:3px double #666666"><?=printPrice($items[$x]["item_sellprice_brutto"])?></td>
            <td class="content_row_os" align="right">
               <input type="text" class="text" onfocus="markfield(this,0)" onblur="markfield(this,1);"
               style="width:65px;text-align:right;background-color:<?if($pl_item_sellprice_netto > 0.00) echo "#D6F9DA"; else echo "#F9D6D9";?>"
               name="pl_item_netto_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>"
               id="pl_item_netto_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>"
               value="<?php if($pl_item_sellprice_netto > 0.00) echo printPrice($pl_item_sellprice_netto,2)?>">
            </td>
            <td class="content_row_os" align="right"><?=printPrice($pl_item_sellprice_taxes, 0, true)?></td>
            <td class="content_row_os" align="right" style="border-right:3px double #666666">
               <?=printPrice($pl_item_sellprice_brutto, 0, true)?>
               <input type="hidden" name="pl_item_taxesperc_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>"
               id="pl_item_taxesperc_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>"
               value="<?=$items[$x]["item_sellprice_taxes_perc"]?>">
            </td>
            <!--
            <td class="content_row_os" align="right">
               <?=printPrice($pl_item_sellprice_netto2, 0, true)?>
               <input type="hidden" name="pl_item_taxesperc2_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>"
               id="pl_item_taxesperc2_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>"
               value="<?=$items[$x]["item_sellprice_taxes_perc"]?>">
            </td>
            <td class="content_row_os" align="right"><?=printPrice($pl_item_sellprice_taxes2, 0, true)?></td>
            <td class="content_row_os" align="right" style="border-right:3px double #666666">
               <input type="text" class="text" onfocus="markfield(this,0)" onblur="markfield(this,1);"
               style="width:65px;text-align:right;background-color:<?if($pl_item_sellprice_brutto2 > 0.00) echo "#D6F9DA"; else echo "#F9D6D9";?>"
               name="pl_item_brutto2_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>"
               id="pl_item_brutto2_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>"
               value="<?php if($pl_item_sellprice_brutto2 > 0.00) echo printPrice($pl_item_sellprice_brutto2)?>">
            </td>
            
            <td class="content_row_os" align="center">
               <input type="button" value="Copiar a todos" class="button" id="btn_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>"
               onclick="document.getElementById('idxifrsrcitm').src='/libs/modules/pricelists/copyprices.php?itemid=<?=$items[$x]["id"]?>&itemtype=item&fromplid=<?=$_REQUEST["id"]?>&brutto=' +$('#pl_item_brutto_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>').val() +'&brutto2=' +$('#pl_item_brutto2_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>').val() +'&taxperc=' +$('#pl_item_taxesperc_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>').val()">
            </td>
            <td class="content_row_os" align="center">
               <img src="/images/menu/icons/calculator-scientific.png" style="cursor:pointer"
               onclick="$('#idx_calcitem_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>').toggle();$('#margendet_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>').focus();">
            </td>
            -->
         </tr>
         <tr id="idx_calcitem_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>" style="display:none">
            <td class="content_row_os" colspan="15" align="right">
               <?php
               $prc_base   = $items[$x]["item_sellprice_brutto"];
               $buysupname = "---";
               $buyprice   = 0.00;
               $itemsup    = getItemSuppliers($CON, $items[$x]["id"], "item");
               $itemsup    = $itemsup[0];
               if((int)$itemsup["supplier_id"])
               {
                  $buysupname = $itemsup["supp_short"];
                  $buyprice   = getSupplierItemLastBuyPrice($CON, $itemsup["supplier_id"], $items[$x]["id"], "item", 0, 0, $_SESSION["_STATS"]["pricelistdata"]["shopid"]);
                  if((int)$buyprice["id"])
                  {
                     $buyprice = $buyprice["item_costprice_brutto"];
                     $prc_base = $buyprice;
                  }
                  else
                     $buyprice = 0.00;
               }
               
               ?>
               <?=Nifty_printH("box1", "500")?>
               <table border="0" cellspacing="0" cellpadding="3" width="100%">
               <colgroup>
                  <col width="160">
                  <col width="120">
                  <col width="100">
                  <col>
               </colgroup>
               <tr>
                  <td class="content_tbl_header" colspan="4">Calculador de Precios</td>
               </tr>
               <tr>
                  <td class="content_rowl content_row_os">Precio/Bruto Lama</td>
                  <td class="content_row_os" colspan="3"><?=printPrice($items[$x]["item_sellprice_brutto"])?></td>
               </tr>
               <tr>
                  <td class="content_rowl content_row_os">Precio/Bruto Ult.Compra</td>
                  <td class="content_row_os" colspan="3">
                     <?php
                     if(!(int)$buyprice)
                        echo "<b class=msg_save_err>No encontrado</b>";
                     else
                        echo printPrice($buyprice);
                     ?>
                  </td>
               </tr>
               <tr>
                  <td class="content_rowl content_row_os">Proveedor</td>
                  <td class="content_row_os" colspan="3"><?=$buysupname?></td>
               </tr>
               <tr>
                  <td class="content_row_totals content_row_os content_rowl">Precio/Bruto/Base</td>
                  <td class="content_row_totals content_row_os" colspan="3">
                     <input id="intcalc_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>"
                     type="text" class="text" style="width:90px"
                     onchange="recalcPrcs('<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>', 'all')"
                     value="<?=printPrice($items[$x]["item_sellprice_brutto"])?>"
                     onfocus="markfield(this,0)" onblur="markfield(this,1)">
                  </td>
               </tr>
               <tr>
                  <td class="content_row_os content_rowl">Margen/Detalle</td>
                  <td class="content_row_os">
                     <input id="margendet_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>"
                     type="text" class="text" style="width:90px"
                     value="" onkeyup="recalcPrcs('<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>', 'det')"
                     onfocus="markfield(this,0)" onblur="markfield(this,1)"> %
                  </td>
                  <td class="content_row_os">Precio/Detalle</td>
                  <td class="content_row_os">
                     <input id="prcdet_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>"
                     type="text" class="text" style="width:90px"
                     value=""
                     onfocus="markfield(this,0)" onblur="markfield(this,1)"> $
                  </td>
               </tr>
               <tr>
                  <td class="content_row_os content_rowl">Margen/Mayorista</td>
                  <td class="content_row_os">
                     <input id="margenmay_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>"
                     type="text" class="text" style="width:90px"
                     value="" onchange="recalcPrcs('<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>', 'may')"
                     onfocus="markfield(this,0)" onblur="markfield(this,1)"> %
                  </td>
                  <td class="content_row_os">Precio/Mayorista</td>
                  <td class="content_row_os">
                     <input id="prcmay_<?=$items[$x]["item_type"]?>_<?=$items[$x]["id"]?>"
                     type="text" class="text" style="width:90px"
                     value=""
                     onfocus="markfield(this,0)" onblur="markfield(this,1)"> $
                  </td>
               </tr>
               <tr>
                  <td class="content_row_os" colspan="4" align="right">
                     <?php
                     printButton("Aplicar", "postnav_save", "javascript: deactivateFormChange()", "setPrcs('{$items[$x]["item_type"]}_{$items[$x]["id"]}')", "gear");
                     ?>
                  </td>
               </tr>
               </table>
               <?=Nifty_printF()?>
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
      printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"], $pgcountadd);
      ?>
      <?=Nifty_printF()?>
      <br>
      <?=Nifty_printH("boxopt_b", "1180")?>
      <table border="0" cellspacing="0" cellpadding="0" width="100%">
      <tr>
         <td>&nbsp;</td>
         <td align="right" width="130">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_pl)", "disk-black");
            ?>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      </form>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createPricelistData($CON, $repsql);
if($_REQUEST["printxls"])
  $xlsfile = xls_createPricelistData($CON, $repsql);
  
if($pdffile != "")
{
   $doctitle = "Lista-de-precios-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Lista-de-precios-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrcitm" height="0" width="0" frameborder="0"></iframe>
<?php