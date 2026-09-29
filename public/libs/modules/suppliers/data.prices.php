<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "supp_price_buying";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo" => "2", "Proveedor" => "6", "Precio" => "4,5");

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]      = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_pricetype"] = (int)$_REQUEST["sql_pricetype"];
   $_SESSION[$_sesmodulename]["sql_financedsc"] = (int)$_REQUEST["sql_financedsc"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

$_SESSION[$_sesmodulename]["sql_supplier"]  = (int)$_REQUEST["id"];

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 100);

if(!(int)$_SESSION[$_sesmodulename]["sql_pricetype"])
   $_SESSION[$_sesmodulename]["sql_pricetype"] = 1;

//----------------------------------------------------------------------------------
$joisql = " INNER JOIN item_shops      t2 ON t1.id = t2.item_id
            INNER JOIN item_suppliers  t5 ON ( t1.id = t5.item_id and ";

$joisql .= " t5.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ) ";
$joisql .= " INNER JOIN supplier t6 ON ( t5.supplier_id = t6.id ) ";

if($_SESSION[$_sesmodulename]["sql_pcat"])
   $joisql .= " INNER JOIN item_productcats t7 ON t1.id = t7.item_id ";

//----------------------------------------------------------------------------------
$joisql2 = " INNER JOIN itemlist_shops       t2 ON t1.id = t2.item_id
             INNER JOIN itemlist_suppliers   t5 ON ( t1.id = t5.item_id and ";

$joisql2 .= " t5.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ) ";
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

$datsql = " select t1.item_number_prod, t1.item_title, t1.id, 
                   t5.item_costprice_netto, t5.item_costprice_usd, t6.supp_company, t6.id 'supplierid',
                   'item_type' 'I', t5.item_costprice_brutto
            from item t1
            {$joisql}
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";
     
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
$cntsql .= $seasql;

//----------------------------------------------------------------------------------
$datsql .= "UNION ALL
            select t1.item_number_prod, t1.item_title, t1.id, 
                   t5.item_costprice_netto, t5.item_costprice_usd, t6.supp_company, t6.id 'supplierid',
                   'item_type' 'L', t5.item_costprice_brutto
            from itemlist t1
            {$joisql2}
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";
            
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
$datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";

//----------------------------------------------------------------------------------
$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));
$pgcountadd = "&exec=edit&subcatexec=prices&id={$_REQUEST["id"]}";
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
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
      <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
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
         <td class="content_rowl">Articulo</td>
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
         <td class="content_rowl">Descuento fin.</td>
         <td class="content_row">
            <input type="radio" value="0" name="sql_financedsc" <?php if((int)$_SESSION[$_sesmodulename]["sql_financedsc"] == 0) echo "checked"?>> Aplicar
            <input type="radio" value="1" name="sql_financedsc" <?php if((int)$_SESSION[$_sesmodulename]["sql_financedsc"] == 1) echo "checked"?>> No Aplicar
         </td>
         <td class="content_rowl">&nbsp;</td>
         <td class="content_row">&nbsp;</td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="right">&nbsp;</td>
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
      <?=Nifty_printH("box1", "980")?>
      <?php
      printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"], $pgcountadd);
      ?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_os content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0, $pgcountadd)?></td>
         <td class="content_row_os content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1, $pgcountadd)?></td>
         <td class="content_row_os content_tbl_subheader">Unidad</td>
         <td class="content_row_os content_tbl_subheader">Precio/Basico</td>
         <td class="content_row_os content_tbl_subheader">Descuentos</td>
         <td class="content_row_os content_tbl_subheader">Precio/Final</td>
         <td class="content_row_os content_tbl_subheader">Ultim.Compra<br>Precio/Neto</td>
         <td class="content_row_os content_tbl_subheader">Ultim.Compra<br>Fecha/Neto</td>
         <td class="content_row_os content_tbl_subheader">Fecha/Ultim.<br>Cambio Precio</td>
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         if($items[$x]["item_type"] == "item_typeI")
            $items[$x]["item_type"] = "item";
         else
            $items[$x]["item_type"] = "itemlist";
            
         $unitdesc      = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
         $taxes         = getSupplierTaxes($CON, $items[$x]["supplierid"]);
         $buytstamp     = 0;
         $lastbuyprice  = getSupplierItemLastBuyPrice($CON, $items[$x]["supplierid"], $items[$x]["id"], $items[$x]["item_type"]);

         if($taxes)
         {
            $buy_costprice_netto = round($lastbuyprice["item_costprice_netto_dsc2"] / $lastbuyprice["item_amount"],0);
            $buy_costprice_final = getSupplierFinalCostNetto($CON, $_REQUEST["id"], $items[$x]["id"], $items[$x]["item_type"], 0.00, $_SESSION[$_sesmodulename]["sql_financedsc"]);
         }
         else
         {
            $buy_costprice_netto = round($lastbuyprice["item_costprice_import_total"] / $lastbuyprice["item_amount"],0);
            $buy_costprice_final = getSupplierFinalCostNetto($CON, $_REQUEST["id"], $items[$x]["id"], $items[$x]["item_type"], 0.00, $_SESSION[$_sesmodulename]["sql_financedsc"]);
         }
         
         /*
         if($_SESSION[$_sesmodulename]["sql_pricetype"] == 2)
         {
            $lastbuyprice = getSupplierItemLastBuyPrice($CON, $items[$x]["supplierid"], $items[$x]["id"], $items[$x]["item_type"]);
            if($taxes)
            {
               $item_costprice_netto   = round($lastbuyprice["item_costprice_netto_dsc2"] / $lastbuyprice["item_amount"],0);
               $item_costprice_usd     = 0.00;
            }
            else
            {
               $item_costprice_netto   = $lastbuyprice["item_costprice_import_item"];
               $item_costprice_usd     = round($lastbuyprice["item_costprice_netto_dsc2"] / $lastbuyprice["item_amount"],2);
            }
            $buytstamp = $lastbuyprice["invc_date"];
         }
         else
         {
            $item_costprice_usd     = $items[$x]["item_costprice_usd"];
            $item_costprice_netto   = $items[$x]["item_costprice_netto"];
         }
         */

         $sql = " select t1.prc_item_id, t1.prc_costprice_netto, t1.prc_crtdat
                  from pricehist_buy t1
                  where
                  t1.prc_item_id    = {$items[$x]["id"]} and
                  t1.prc_item_type  = '{$items[$x]["item_type"]}' and
                  t1.prc_supplier_id = {$_REQUEST["id"]}
                  order by t1.prc_crtdat desc
                  LIMIT 0,1";
         $buyhist = $CON->select($sql);
         $buyhist = $buyhist[0];

         $descperc = round(($items[$x]["item_costprice_netto"] - $buy_costprice_final) / $items[$x]["item_costprice_netto"] * 100,2);
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$items[$x]["item_number_prod"]?></td>
            <td class="content_row_os"><?=$items[$x]["item_title"]?></td>
            <td class="content_row_os"><?=$unitdesc?></td>
            <td class="content_row_os"><?=printPrice($items[$x]["item_costprice_netto"])?></td>
            <td class="content_row_os"><?=printPrice($descperc,2)?> %</td>
            <td class="content_row_os"><?=printPrice($buy_costprice_final,4)?></td>
            <td class="content_row_os"><?=printPrice($buy_costprice_netto,4, true)?></td>
            <td class="content_row_os">
               <?php
               if((int)$lastbuyprice["invc_date"])
                  echo date('d.m.Y', $lastbuyprice["invc_date"]);
               else
                  echo "&nbsp;";
               ?>
            </td>
            <td class="content_row_os">
               <?php
               if((int)$buyhist["prc_crtdat"])
                  echo date('d.m.Y', $buyhist["prc_crtdat"]);
               else
                  echo "&nbsp;";
               ?>
            </td>
         </tr>
         <?php
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="10" align="center">
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
   </td>
</tr>
</table>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>