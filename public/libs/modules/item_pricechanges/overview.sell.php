<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "pricechg_buying";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo" => "2", "Proveedor" => "8", "Precio actual" => "6,7");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_round"]     = (int)$_REQUEST["sql_round"];
   $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]      = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_supplier"]  = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_pricetype"] = (int)$_REQUEST["sql_pricetype"];
   $_SESSION[$_sesmodulename]["sql_listasact"]   = (int)$_REQUEST["sql_listasact"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

$_SESSION[$_sesmodulename]["sql_prcmode"] = 1;

//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);
$shops      = getShops($CON);
$suppliers  = getSuppliers($CON);

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

if(!(int)$_SESSION[$_sesmodulename]["sql_pricetype"])
   $_SESSION[$_sesmodulename]["sql_pricetype"] = 1;

//----------------------------------------------------------------------------------
if($_REQUEST["savechanges"] == "1")
{
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_act_item") !== false && strpos($reqkey, "item_act_item") == 0)
      {
         $idx = explode("_", $reqkey);
         $item_type  = $idx[2];
         $item_id    = $idx[3];
         $supp_id    = $idx[4];
         $item_price = (float)($_REQUEST["itemnewprice_{$item_type}_{$item_id}_{$supp_id}"]);
         $oldprice   = (float)($_REQUEST["itemcostprice_{$item_type}_{$item_id}_{$supp_id}"]);
         $shopprice  = (float)($_REQUEST["itemshopprice_{$item_type}_{$item_id}_{$supp_id}"]);
         $pdiff      = $item_price - $oldprice;
         $pperc      = $pdiff / $oldprice * 100;

         if((int)$_SESSION[$_sesmodulename]["sql_prcmode"] == 1)
         {
            if($item_type == "item")
            {
               $sql = " select * from item where id = {$item_id}";
               $idata = $CON->select($sql);
               $idata = $idata[0];

               $item_sellprice_brutto     = $item_price;
               $item_sellprice_taxes_perc = (float)$idata["item_sellprice_taxes_perc"];
               $item_sellprice_taxes      = round($item_price / (100 + $item_sellprice_taxes_perc) * $item_sellprice_taxes_perc);
               $item_sellprice_netto      = round($item_sellprice_brutto - $item_sellprice_taxes);

               $sql = " update item
                        set
                        item_sellprice_netto       = {$item_sellprice_netto},
                        item_sellprice_brutto      = {$item_sellprice_brutto},
                        item_sellprice_taxes       = {$item_sellprice_taxes}
                        where
                        id = {$item_id}";
               $CON->no_result($sql);

               updateItemStorePrices($CON, $item_id);
               registerSellPriceHistory($CON, $item_id, "item");

               foreach(array_keys($_REQUEST) AS $reqkey)
               {
                  if(strpos($reqkey, "plitemnewprice1_{$item_type}_{$item_id}_{$supp_id}_") !== false && strpos($reqkey, "plitemnewprice1_{$item_type}_{$item_id}_{$supp_id}_") == 0)
                  {
                     $plidx = explode("_", $reqkey);
                     $plid  = $plidx[4];

                     $newnetto  = getPrice($_REQUEST[$reqkey],2);
                     $newtaxes  = round($newnetto / 100 * $idata["item_sellprice_taxes_perc"]);
                     $newbrutto  = $newnetto + $newtaxes;
                     
                     $sql = " update price_lists_items
                              set
                              item_sellprice_brutto   = {$newbrutto},
                              item_sellprice_netto    = {$newnetto},
                              item_sellprice_taxes    = {$newtaxes}
                              where
                              pl_id       = {$plid} and 
                              item_id     = {$item_id} and
                              item_type   = '{$item_type}'";
                     $CON->no_result($sql);
                  }
                  /*
                  elseif(strpos($reqkey, "plitemnewprice2_{$item_type}_{$item_id}_{$supp_id}_") !== false && strpos($reqkey, "plitemnewprice2_{$item_type}_{$item_id}_{$supp_id}_") == 0)
                  {
                     $plidx = explode("_", $reqkey);
                     $plid  = $plidx[4];

                     $newbrutto = (float)$_REQUEST[$reqkey];
                     $newtaxes  = round($newbrutto / (100 + $idata["item_sellprice_taxes_perc"]) * $idata["item_sellprice_taxes_perc"]);
                     $newnetto  = $newbrutto - $newtaxes;
                     
                     $sql = " update price_lists_items
                              set
                              item_sellprice_brutto2   = {$newbrutto},
                              item_sellprice_netto2    = {$newnetto},
                              item_sellprice_taxes2    = {$newtaxes}
                              where
                              pl_id       = {$plid} and 
                              item_id     = {$item_id} and
                              item_type   = '{$item_type}'";
                     $CON->no_result($sql);
                  }
                  */
               }
            }
            elseif($item_type == "itemlist")
            {
               $sql = " select * from itemlist where id = {$item_id}";
               $idata = $CON->select($sql);
               $idata = $idata[0];

               $item_sellprice_netto      = $item_price;
               $item_sellprice_taxes_perc = (float)$idata["item_sellprice_taxes_perc"];
               $item_sellprice_taxes      = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $item_sellprice_netto / 100 * $item_sellprice_taxes_perc);
               $item_sellprice_brutto     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $item_sellprice_netto + $item_sellprice_taxes);

               $sql = " update itemlist
                        set
                        item_sellprice_netto       = {$item_sellprice_netto},
                        item_sellprice_brutto      = {$item_sellprice_brutto},
                        item_sellprice_taxes       = {$item_sellprice_taxes}
                        where
                        id = {$item_id}";
               $CON->no_result($sql);
               updateItemlistStorePrices($CON, $item_id);
               registerSellPriceHistory($CON, $item_id, "itemlist");
            }
         }
         else
         {
            if($item_type == "item")
            {
               $sql = " select * from item where id = {$item_id}";
               $idata = $CON->select($sql);
               $idata = $idata[0];
               
               $item_sellprice_netto      = $shopprice + round($shopprice / 100 * $pperc,0);
               $item_sellprice_taxes_perc = (float)$idata["item_sellprice_taxes_perc"];
               $item_sellprice_taxes      = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $item_sellprice_netto / 100 * $item_sellprice_taxes_perc);
               $item_sellprice_brutto     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $item_sellprice_netto + $item_sellprice_taxes);

               $sql = " update item
                        set
                        item_sellprice_netto       = {$item_sellprice_netto},
                        item_sellprice_brutto      = {$item_sellprice_brutto},
                        item_sellprice_taxes       = {$item_sellprice_taxes}
                        where
                        id = {$item_id}";
               $CON->no_result($sql);

               updateItemStorePrices($CON, $item_id);
               registerSellPriceHistory($CON, $item_id, "item");
            }
            elseif($item_type == "itemlist")
            {
               $sql = " select * from itemlist where id = {$item_id}";
               $idata = $CON->select($sql);
               $idata = $idata[0];

               $item_sellprice_netto      = $shopprice + round($shopprice / 100 * $pperc,0);;
               $item_sellprice_taxes_perc = (float)$idata["item_sellprice_taxes_perc"];
               $item_sellprice_taxes      = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $item_sellprice_netto / 100 * $item_sellprice_taxes_perc);
               $item_sellprice_brutto     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $item_sellprice_netto + $item_sellprice_taxes);

               $sql = " update itemlist
                        set
                        item_sellprice_netto       = {$item_sellprice_netto},
                        item_sellprice_brutto      = {$item_sellprice_brutto},
                        item_sellprice_taxes       = {$item_sellprice_taxes}
                        where
                        id = {$item_id}";
               $CON->no_result($sql);
               updateItemlistStorePrices($CON, $item_id);
               registerSellPriceHistory($CON, $item_id, "itemlist");
            }
         }
      }
   }
}

//----------------------------------------------------------------------------------
$joisql = " INNER JOIN item_shops      t2 ON t1.id = t2.item_id
            INNER JOIN company_shops   t3 ON ( t2.shop_id = t3.id and t3.shop_status = 1 )
            INNER JOIN company_data    t4 ON ( t3.shop_company_id = t4.id and t4.company_status = 1 )
            INNER JOIN item_productcats t7 ON t1.id = t7.item_id
            INNER JOIN productcats t8 ON t7.cat_id = t8.id ";

//----------------------------------------------------------------------------------
$joisql2 = " INNER JOIN itemlist_shops       t2 ON t1.id = t2.item_id
             INNER JOIN company_shops        t3 ON ( t2.shop_id = t3.id and t3.shop_status = 1 )
             INNER JOIN company_data         t4 ON ( t3.shop_company_id = t4.id and t4.company_status = 1 )
             INNER JOIN item_productcats_itemlist t7 ON t1.id = t7.item_id
             INNER JOIN productcats t8 ON t7.cat_id = t8.id ";

//----------------------------------------------------------------------------------
$cntsql = " select count(distinct t1.id) 'cc'
            from item t1
            {$joisql}
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";

$datsql = " select t1.item_number_prod, t1.item_title, t1.id, t4.company_short, t3.shop_name,
                   t1.item_sellprice_brutto 'itemshop_sellprice_brutto',
                   t2.shop_id, t8.cat_calc_price_perc, 'item_type' 'I'
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
$cntsql .= $seasql;

//----------------------------------------------------------------------------------
$datsql .= "UNION ALL
            select t1.item_number_prod, t1.item_title, t1.id, t4.company_short, t3.shop_name,
                   t1.item_sellprice_brutto 'itemshop_sellprice_brutto',
                   t2.shop_id, t8.cat_calc_price_perc, 'item_type' 'L'
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
</script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Cambiar de precios de venta</b></td>
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
         <td class="content_rowl" style="display:none">Modo</td>
         <td class="content_row" style="display:none">
            <input type="radio" value="0" name="sql_prcmode" <?php if((int)$_SESSION[$_sesmodulename]["sql_prcmode"] == 0) echo "checked"?>> Aplicar nuevo margen
            <input type="radio" value="1" name="sql_prcmode" <?php if((int)$_SESSION[$_sesmodulename]["sql_prcmode"] == 1) echo "checked"?>> Aumentar/Bajar precios 
         </td>
         <td class="content_rowl">Listas de Precio</td>
         <td class="content_row">
            <input type="radio" name="sql_listasact" value="0" <?php if((int)$_SESSION[$_sesmodulename]["sql_listasact"] == 0) echo "checked"?>> Desactivado
            <input type="radio" name="sql_listasact" value="1" <?php if((int)$_SESSION[$_sesmodulename]["sql_listasact"] == 1) echo "checked"?>> Activado
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width='132'>            
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if($itemcount > 0)
                  {
                     //printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if($itemcount > 0)
                  {
                     //printButton("Generar XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
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
      <?php
      if((int)$_SESSION[$_sesmodulename]["sql_prcmode"] == 0)
         require_once("data.sell.margen.php");
      elseif((int)$_SESSION[$_sesmodulename]["sql_prcmode"] == 1)
         require_once("data.sell.percent.php");
      ?>
   </td>
</tr>
</table>

<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>