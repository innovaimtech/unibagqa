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
   $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]      = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_supplier"]  = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_pricetype"] = (int)$_REQUEST["sql_pricetype"];
   $_SESSION[$_sesmodulename]["sql_prcmode"]   = (int)$_REQUEST["sql_prcmode"];
   $_SESSION[$_sesmodulename]["sql_applytosell"]   = (int)$_REQUEST["sql_applytosell"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

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
         $item_taxes = $idx[5];
         $item_price    = getPrice($_REQUEST["itemnewprice_{$item_type}_{$item_id}_{$supp_id}_{$item_taxes}"],4);
         $sellval_new   = getPrice($_REQUEST["sellval_new_{$item_type}_{$item_id}_{$supp_id}_{$item_taxes}"],4);
         
         if($item_type == "item")
         {
            if(!(int)$item_taxes)
            {
               $sql = " update item_suppliers
                        set
                        item_costprice_usd = {$item_price}
                        where
                        item_id     = {$item_id} and
                        supplier_id = {$supp_id}";
               $CON->no_result($sql);
            }
            else
            {
               $item_costprice_netto      = $item_price;
               $item_costprice_taxes_perc = $_SESSION["_CONF"]["conf_taxes"];
               $item_costprice_taxes      = (float)sprintf("%.2f", $item_costprice_netto / 100 * $item_costprice_taxes_perc);
               $item_costprice_brutto     = (float)sprintf("%.2f", $item_costprice_netto + $item_costprice_taxes);

               $sql = " update item_suppliers
                        set
                        item_costprice_netto       = {$item_costprice_netto},
                        item_costprice_brutto      = {$item_costprice_brutto},
                        item_costprice_taxes       = {$item_costprice_taxes}
                        where
                        item_id     = {$item_id} and
                        supplier_id = {$supp_id}";
               $CON->no_result($sql);
            }
            registerCostPriceHistory($CON, $item_id, "item");
            recalcAutomatedSellPrices($CON, $item_id, "item");

            if($sellval_new > 0.00)
            {
               $sql = " select * from item where id = {$item_id}";
               $idata = $CON->select($sql);
               $idata = $idata[0];

               $item_sellprice_netto      = $sellval_new;
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
         }
         elseif($item_type == "itemlist")
         {
            if(!(int)$item_taxes)
            {
               $sql = " update itemlist_suppliers
                        set
                        item_costprice_usd = {$item_price}
                        where
                        item_id     = {$item_id} and
                        supplier_id = {$supp_id}";
               $CON->no_result($sql);
            }
            else
            {
               $item_costprice_netto      = $item_price;
               $item_costprice_taxes_perc = $_SESSION["_CONF"]["conf_taxes"];
               $item_costprice_taxes      = (float)sprintf("%.2f", $item_costprice_netto / 100 * $item_costprice_taxes_perc);
               $item_costprice_brutto     = (float)sprintf("%.2f", $item_costprice_netto + $item_costprice_taxes);

               $sql = " update itemlist_suppliers
                        set
                        item_costprice_netto       = {$item_costprice_netto},
                        item_costprice_brutto      = {$item_costprice_brutto},
                        item_costprice_taxes       = {$item_costprice_taxes}
                        where
                        item_id     = {$item_id} and
                        supplier_id = {$supp_id}";
               $CON->no_result($sql);
            }
            registerCostPriceHistory($CON, $item_id, "itemlist");
            recalcAutomatedSellPrices($CON, $item_id, "itemlist");

            if($sellval_new > 0.00)
            {
               $sql = " select * from itemlist where id = {$item_id}";
               $idata = $CON->select($sql);
               $idata = $idata[0];

               $item_sellprice_netto      = $sellval_new;
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

$datsql = " select t1.item_number_prod, t1.item_title, t1.id, t4.company_short, t3.shop_name,
                   t5.item_costprice_netto, t5.item_costprice_usd, t6.supp_company, t6.id 'supplierid',
                   t5.item_code, 'item_type' 'I', t6.supp_short, t1.item_sellprice_netto
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
                   t5.item_costprice_netto, t5.item_costprice_usd, t6.supp_company, t6.id 'supplierid',
                   t5.item_code, 'item_type' 'L', t6.supp_short, t1.item_sellprice_netto
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
   <td height="30"><b class="content_header">Cambiar de precios de compra</b></td>
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
         <td class="content_rowl">Modo</td>
         <td class="content_row">
            <input type="radio" value="0" name="sql_prcmode" <?php if((int)$_SESSION[$_sesmodulename]["sql_prcmode"] == 0) echo "checked"?>> Aumentar/Bajar precios
            <input type="radio" value="1" name="sql_prcmode" <?php if((int)$_SESSION[$_sesmodulename]["sql_prcmode"] == 1) echo "checked"?>> Restablecer precios
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
         require_once("data.percent.php");
      elseif((int)$_SESSION[$_sesmodulename]["sql_prcmode"] == 1)
         require_once("data.restablecer.php");
      ?>
   </td>
</tr>
</table>

<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>