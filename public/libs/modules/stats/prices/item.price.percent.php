<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "price_percent";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo" => "2", 
                                "Empresa/Sucursal" => "4,5", "Proveedor" => "6",
                                "P/Venta<br>Basico" => "7", "Costo<br>Basico" => "8", "Margen/C" => "9",
                                "Margen/V" => "10");
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
   $_SESSION[$_sesmodulename]["sql_invcsellmode"]      = (int)$_REQUEST["sql_invcsellmode"];
   $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]      = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_supplier"]  = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_customer"]  = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_financedsc"] = (int)$_REQUEST["sql_financedsc"];
   $_SESSION[$_sesmodulename]["sql_invcsellnum"] = trim(addslashes($_REQUEST["sql_invcsellnum"]));
   $_SESSION[$_sesmodulename]["sql_invcsellnumsel"] = trim(addslashes($_REQUEST["sql_invcsellnumsel"]));
   $_SESSION[$_sesmodulename]["sql_invcselldate"] = trim(addslashes($_REQUEST["sql_invcselldate"]));
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON);
$shops      = getShops($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
if(!(int)$_SESSION[$_sesmodulename]["sql_shop"])
{
   $first = false;
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"] && !$first)
      {
         $_SESSION[$_sesmodulename]["sql_shop"] = $shop["id"];
         $first = true;
      }
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 100);

//----------------------------------------------------------------------------------
$joisql = " INNER JOIN item_shops      t2 ON t1.id = t2.item_id
            INNER JOIN company_shops   t3 ON ( t2.shop_id = t3.id and t3.shop_status = 1 )
            INNER JOIN company_data    t4 ON ( t3.shop_company_id = t4.id and t4.company_status = 1 )
            INNER JOIN item_suppliers  t5 ON ( t1.id = t5.item_id and ";

if($_SESSION[$_sesmodulename]["sql_supplier"])
   $joisql .= " t5.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ) ";
else
   $joisql .= " t5.item_supp_act = 1 ) ";
   
$joisql .= " INNER JOIN supplier t6 ON ( t5.supplier_id = t6.id ) ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
      $joisql .= " INNER JOIN item_productcats t7 ON t1.id = t7.item_id ";

//----------------------------------------------------------------------------------
$joisql2 = " INNER JOIN itemlist_shops       t2 ON t1.id = t2.item_id
             INNER JOIN company_shops        t3 ON ( t2.shop_id = t3.id and t3.shop_status = 1 )
             INNER JOIN company_data         t4 ON ( t3.shop_company_id = t4.id and t4.company_status = 1 )
             INNER JOIN itemlist_suppliers   t5 ON ( t1.id = t5.item_id and ";
              
if($_SESSION[$_sesmodulename]["sql_supplier"])
   $joisql2 .= " t5.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ) ";
else
   $joisql2 .= " t5.item_supp_act = 1 ) ";

$joisql2 .= " INNER JOIN supplier t6 ON ( t5.supplier_id = t6.id ) ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $joisql2 .= " INNER JOIN item_productcats_itemlist t7 ON t1.id = t7.item_id ";

//----------------------------------------------------------------------------------
$cntsql = " select count(distinct t1.id) 'cc'
            from item t1
            {$joisql}
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";
            
if($_SESSION[$_sesmodulename]["sql_invcsellnum"] != "")
   $cntsql .= " and ( select count(*) 'cc' from invoices_sell tx
                      INNER JOIN invoices_sell_parts_items tx2 ON tx.id = tx2.invc_id
                      where tx.invc_status > 1 and tx.invc_status != 4 and tx.invc_docnumber = '{$_SESSION[$_sesmodulename]["sql_invcsellnum"]}'
                      and tx2.item_id = t1.id and tx2.item_type = 'item' ) > 0 ";

$datsql = " select t1.item_number_prod, t1.item_title, t1.id, t4.company_short, t3.shop_name,  t6.supp_short,
                   t2.itemshop_sellprice_netto, t5.item_costprice_netto,
                   ((t2.itemshop_sellprice_netto - t5.item_costprice_netto) / t5.item_costprice_netto * 100) 'percent',
                   ((t2.itemshop_sellprice_netto - t5.item_costprice_netto) / t2.itemshop_sellprice_netto * 100) 'percent_sell',
                   'item_type' 'I', t5.item_code
            from item t1
            {$joisql}
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";
            
if($_SESSION[$_sesmodulename]["sql_invcsellnum"] != "")
   $datsql .= " and ( select count(*) 'cc' from invoices_sell tx
                      INNER JOIN invoices_sell_parts_items tx2 ON tx.id = tx2.invc_id
                      where tx.invc_status > 1 and tx.invc_status != 4 and tx.invc_docnumber = '{$_SESSION[$_sesmodulename]["sql_invcsellnum"]}'
                      and tx2.item_id = t1.id and tx2.item_type = 'item' ) > 0 ";
                           
//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_item_type"] == "itemlist")
{
   $datsql .= " and 1 = 2 ";
   $cntsql .= " and 1 = 2 ";
}
elseif($_SESSION[$_sesmodulename]["sql_item_type"] == "item")
{
   $datsql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
   $cntsql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
}

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t3.shop_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t2.shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t7.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
      
//----------------------------------------------------------------------------------
$cntsql   .= $seasql;
$datsql   .= $seasql;

//----------------------------------------------------------------------------------
$cntsql .= "UNION ALL
            select count(distinct t1.id) 'cc'
            from itemlist t1
            {$joisql2}
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";

if($_SESSION[$_sesmodulename]["sql_invcsellnum"] != "")
   $cntsql .= " and ( select count(*) 'cc' from invoices_sell tx
                      INNER JOIN invoices_sell_parts_items tx2 ON tx.id = tx2.invc_id
                      where tx.invc_status > 1 and tx.invc_status != 4 and tx.invc_docnumber = '{$_SESSION[$_sesmodulename]["sql_invcsellnum"]}'
                      and tx2.item_id = t1.id and tx2.item_type = 'itemlist' ) > 0 ";
                      
$cntsql .= $seasql;

//----------------------------------------------------------------------------------
$datsql .= "UNION ALL
            select t1.item_number_prod, t1.item_title, t1.id, t4.company_short, t3.shop_name,  t6.supp_short,
                   t2.itemshop_sellprice_netto, t5.item_costprice_netto,
                   ((t2.itemshop_sellprice_netto - t5.item_costprice_netto) / t5.item_costprice_netto * 100) 'percent',
                   ((t2.itemshop_sellprice_netto - t5.item_costprice_netto) / t2.itemshop_sellprice_netto * 100) 'percent_sell',
                   'item_type' 'L', t5.item_code
            from itemlist t1
            {$joisql2}
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";
            
if($_SESSION[$_sesmodulename]["sql_invcsellnum"] != "")
   $datsql .= " and ( select count(*) 'cc' from invoices_sell tx
                      INNER JOIN invoices_sell_parts_items tx2 ON tx.id = tx2.invc_id
                      where tx.invc_status > 1 and tx.invc_status != 4 and tx.invc_docnumber = '{$_SESSION[$_sesmodulename]["sql_invcsellnum"]}'
                      and tx2.item_id = t1.id and tx2.item_type = 'itemlist' ) > 0 ";
                      
//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_item_type"] == "item")
{
   $datsql .= " and 1 = 2 ";
   $cntsql .= " and 1 = 2 ";
}
elseif($_SESSION[$_sesmodulename]["sql_item_type"] == "itemlist")
{
   $datsql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
   $cntsql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
}

$datsql .= $seasql;


$itemcount = $CON->select($cntsql);
$itemcount = (int)$itemcount[0]["cc"] + (int)$itemcount[1]["cc"];

//----------------------------------------------------------------------------------
$_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
$repsql  = $datsql;

//----------------------------------------------------------------------------------
$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
         array_push($selshops, $shop);
}

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
function loadInvcSells(xdate)
{
   $(document).ready(function()
   {
      $.get('/libs/modules/stats/prices/item.price.percent.getinvcs.php?xdate=' +xdate +'&sql_invcsellnum=<?=$_SESSION[$_sesmodulename]["sql_invcsellnum"]?>', '', function(data)
      {
         $("#sql_invcsellnumsel").html(data);
      });
   });
}
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Costos</b></td>
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
         <td class="content_rowl">
            Articulo
            <img src="./images/menu/icons/magnifier-zoom.png">
         </td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="105">
               <col>
            </colgroup>
            <tr>
               <td>
                  <input type="text" class="text" style="width:100px" onfocus="markfield(this,0)" name="xf_search"
                  onblur="markfield(this,1);if(this.value != '') document.all.idxifrsrc.src='./libs/modules/stats/searchitem.php?search=' +this.value"
                  onkeyup="detectEvent(event, 'stats')">
               </td>
               <td>
                  <select class="text" style="width:270px" name="item_id"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
                     {
                        if($_SESSION[$_sesmodulename]["sql_item_type"] == "item")
                        {
                           $sql = " select *
                                    from item
                                    where
                                    id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
                           $item = $CON->select($sql);
                           $item = $item[0];
                        }
                        else
                        {
                           $sql = " select *
                                    from itemlist
                                    where
                                    id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
                           $item = $CON->select($sql);
                           $item = $item[0];
                        }

                        $desc       = trim(addslashes($item["item_title"]));
                        $unitdesc   = getItemUnitDesc($CON, $_SESSION[$_sesmodulename]["sql_item_id"], $_SESSION[$_sesmodulename]["sql_item_type"]);
                        ?>
                        <option value="<?=$_SESSION[$_sesmodulename]["sql_item_id"]?>#<?=$_SESSION[$_sesmodulename]["sql_item_type"]?>">
                           <?=$item["item_number_prod"]?> - <?=$desc?> (<?=$unitdesc?>)
                        </option>
                        <?php
                     }
                     ?>
                  </select>
               </td>
            </tr>
            </table>
         </td>
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Proveedor</td>
         <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Familia</td>
         <td class="content_row">
            <select class="text" name="sql_pcat" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($pcats AS $pcat)
               {
                  $sql = " select *
                           from productcats
                           where
                           id = {$pcat["id"]}";
                  $catinfo = $CON->select($sql);
                  $catinfo = $catinfo[0];
                  ?>
                  <option value="<?=$pcat["id"]?>" <?php if($pcat["id"] == $_SESSION[$_sesmodulename]["sql_pcat"]) echo "selected"?>>
                  <?=sprintf("%03s", $pcat["id"])?> - <?=$pcat["cat_title"]?>
                  <?php
                  if($catinfo["cat_dsc_maxbuyperc"] > 0.00)
                  {  ?>
                     (Max Desc:<?=printPrice($catinfo["cat_dsc_maxbuyperc"],2)?>%)
                     <?php
                  }
                  ?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Descuento fin.</td>
         <td class="content_row">
            <input type="radio" value="0" name="sql_financedsc" <?php if((int)$_SESSION[$_sesmodulename]["sql_financedsc"] == 0) echo "checked"?>> Aplicar
            <input type="radio" value="1" name="sql_financedsc" <?php if((int)$_SESSION[$_sesmodulename]["sql_financedsc"] == 1) echo "checked"?>> No Aplicar
         </td>
         <td class="content_rowl">Factura Venta</td>
         <td class="content_row">
            <nobr>
            <input type="radio" value="0" name="sql_invcsellmode" <?php if((int)$_SESSION[$_sesmodulename]["sql_invcsellmode"] == 0) echo "checked"?>
            onclick="$('#idx_input').show();$('#idx_select').hide();"> Ingresar
            <input type="radio" value="1" name="sql_invcsellmode" <?php if((int)$_SESSION[$_sesmodulename]["sql_invcsellmode"] == 1) echo "checked"?>
            onclick="$('#idx_input').hide();$('#idx_select').show();"> Seleccionar
            &nbsp;
            <span style="<?php if((int)$_SESSION[$_sesmodulename]["sql_invcsellmode"] == 1) echo "display:none"?>" id="idx_input">
            <input type="text" class="text" style="width:100px" name="sql_invcsellnum" id="sql_invcsellnum"
            onblur="markfield(this,1);" onfocus="markfield(this,0)" value="<?=$_SESSION[$_sesmodulename]["sql_invcsellnum"]?>">
            </span>
            <span style="<?php if((int)$_SESSION[$_sesmodulename]["sql_invcsellmode"] == 0) echo "display:none"?>" id="idx_select">
               <input type="text" style="width:70px" id="sql_invcselldate" name="sql_invcselldate"
               class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
               onfocus="markfield(this,0)" onblur="markfield(this,1)"
               onchange="loadInvcSells(this.value)"
               value="<?=$_SESSION[$_sesmodulename]["sql_invcselldate"]?>">
               &nbsp;
               <select name="sql_invcsellnumsel" id="sql_invcsellnumsel" class="text" style="width:100px"
               onchange="$('#sql_invcsellnum').val($(this).val())">
               </select>
            </span>
            </nobr>
         </td>
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
                     printButton("Imprimir", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if($itemcount > 0)
                  {
                     printButton("Imprimir", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
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
      <tr>
         <td class="content_row_os content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_row_os content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_row_os content_tbl_subheader">Unidad</td>
         <td class="content_row_os content_tbl_subheader">Cod/Prov.</td>
         <td class="content_row_os content_tbl_subheader" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
         <td class="content_row_os content_tbl_subheader" align="right"><nobr>P/Venta<br>Final</nobr></td>
         <td class="content_row_os content_tbl_subheader" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
         <td class="content_row_os content_tbl_subheader" align="right">Costo<br>Final</td>
         <td class="content_row_os content_tbl_subheader" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
         <td class="content_row_os content_tbl_subheader" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
         <td class="content_row_os content_tbl_subheader" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         if($items[$x]["item_type"] == "item_typeI")
            $items[$x]["item_type"] = "item";
         else
            $items[$x]["item_type"] = "itemlist";
            
         $unitdesc   = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
         $buyval     = getSupplierFinalCostNetto($CON, 0, $items[$x]["id"], $items[$x]["item_type"], 0.00, $_SESSION[$_sesmodulename]["sql_financedsc"]);
         $selval     = getFinalSellNetto($CON, $items[$x]["id"], $items[$x]["item_type"]);

         if($_SESSION[$_sesmodulename]["sql_invcsellnum"] != "")
         {
            $sql = " select distinct tx2.*
                     from invoices_sell tx
                     INNER JOIN invoices_sell_parts_items tx2 ON tx.id = tx2.invc_id
                     where
                     tx.invc_status    > 1 and
                     tx.invc_status    != 4 and
                     tx.invc_docnumber = '{$_SESSION[$_sesmodulename]["sql_invcsellnum"]}' and
                     tx2.item_id       = {$items[$x]["id"]} and
                     tx2.item_type     = '{$items[$x]["item_type"]}'";
            $directsell = $CON->select($sql);
            $directsell = $directsell[0];
            if((int)$directsell["item_id"])
            {
               $items[$x]["itemshop_sellprice_netto"] = $directsell["item_sellprice_netto"] * $directsell["item_amount"];
               $selval = $directsell["item_sellprice_netto_dsc"];
               $items[$x]["item_costprice_netto"] = $items[$x]["item_costprice_netto"] * $directsell["item_amount"];
               $buyval = $buyval * $directsell["item_amount"];
            }
         }
         
         $items[$x]["percent"]      = ($selval - $buyval) / $buyval * 100;
         $items[$x]["percent_sell"] = ($selval - $buyval) / $selval * 100;
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$items[$x]["item_number_prod"]?></td>
            <td class="content_row_os"><?=$items[$x]["item_title"]?></td>
            <td class="content_row_os"><?=$unitdesc?></td>
            <td class="content_row_os"><?=$items[$x]["item_code"]?>&nbsp;</td>
            <td class="content_row_os" align="right"><?=printPrice($items[$x]["itemshop_sellprice_netto"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($selval)?></td>
            <td class="content_row_os" align="right"><?=printPrice($items[$x]["item_costprice_netto"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($buyval)?></td>
            <td class="content_row_os" align="right">
               <nobr>
               <?php
               if($items[$x]["percent"] > 0)
                  echo "<b class='msg_save_ok'>".printPrice($items[$x]["percent"],2)." %</b>";
               else
                  echo "<b class='msg_save_err'>".printPrice($items[$x]["percent"],2)." %</b>";
               ?>
               </nobr>
            </td>
            <td class="content_row_os" align="right">
               <nobr>
               <?php
               if($items[$x]["percent_sell"] > 0)
                  echo "<b class='msg_save_ok'>".printPrice($items[$x]["percent_sell"],2)." %</b>";
               else
                  echo "<b class='msg_save_err'>".printPrice($items[$x]["percent_sell"],2)." %</b>";
               ?>
               </nobr>
            </td>
            <td class="content_row_os" align="right"><?=$items[$x]["supp_short"]?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]         = $items[$x]["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]               = $items[$x]["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitdesc"]                 = $unitdesc;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["itemshop_sellprice_netto"] = printPrice($items[$x]["itemshop_sellprice_netto"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["selval"]                   = printPrice($selval);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_costprice_netto"]     = printPrice($items[$x]["item_costprice_netto"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["buyval"]                   = printPrice($buyval);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["supp_company"]             = $items[$x]["supp_short"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["percent"]                  = printPrice($items[$x]["percent"],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["percent_sell"]             = printPrice($items[$x]["percent_sell"],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_code"]                = $items[$x]["item_code"];
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="11" align="center">
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
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createItemPricePercent($CON, $repsql);

//----------------------------------------------------------------------------------
if($_REQUEST["printxls"])
  $xlsfile = xls_createItemPricePercent($CON, $repsql);

if($pdffile != "")
{
   $doctitle = "Costos-".time().".pdf";

   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}

if($xlsfile != "")
{
   $doctitle = "Costos-".time().".xls";

   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php
if($_SESSION[$_sesmodulename]["sql_invcselldate"] != "")
{  
   $_SESSION["JSEXEC"] .= ";loadInvcSells($('#sql_invcselldate').val())";
}
