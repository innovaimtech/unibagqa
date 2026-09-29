<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stock_storehouses";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo/Familia" => "2", "Codigo Prov." => "10");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_company"]    = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]       = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_storehouse"] = (int)$_REQUEST["sql_storehouse"];
   $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]      = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_supplier"]  = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]   = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_stockmode"] = (int)$_REQUEST["sql_stockmode"];
   $_SESSION[$_sesmodulename]["sql_price_calc"] = (int)$_REQUEST["sql_price_calc"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON, true);
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
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;
if(!(int)$_SESSION[$_sesmodulename]["sql_stockmode"])
   $_SESSION[$_sesmodulename]["sql_stockmode"] = 1;

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
$joisql = " INNER JOIN item_shops      t2 ON t1.id = t2.item_id
            INNER JOIN company_shops   t3 ON ( t2.shop_id = t3.id and t3.shop_status = 1 )
            INNER JOIN company_data    t4 ON ( t3.shop_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN item_suppliers  t5 ON ( t1.id = t5.item_id and ";

if($_SESSION[$_sesmodulename]["sql_supplier"])
   $joisql .= " t5.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ) ";
else
   $joisql .= " t5.item_supp_act = 1 ) ";
   
$joisql .= " LEFT OUTER JOIN supplier t6 ON ( t5.supplier_id = t6.id )
             LEFT OUTER JOIN item_productcats t7 ON t1.id = t7.item_id 
             LEFT OUTER JOIN productcats t8   ON t7.cat_id = t8.id
             LEFT OUTER JOIN item_units t9    ON t1.item_unit = t9.id
             INNER JOIN item_shops_storehouses     t10 ON ( t2.item_id = t10.item_id and t2.shop_id = t10.shop_id )
             INNER JOIN company_shops_storehouses  t11 ON ( t11.id = t10.st_id and t2.shop_id = t11.st_shop_id and t11.st_status > 0 ) ";

//----------------------------------------------------------------------------------
$cntsql = " select count(distinct t1.id) 'cc'
            from item t1
            {$joisql}
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";

$datsql = " select distinct t1.item_number_prod, t1.item_title, t1.id, t4.company_short, t3.shop_name, 'item_type' 'I',
            t7.cat_id, t8.cat_title, t9.unit_name, t5.item_code
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
if($_SESSION[$_sesmodulename]["sql_supplier"])
   $seasql .= " and t5.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t7.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if($_SESSION[$_sesmodulename]["sql_stockmode"] == 1)
   $seasql .= " and t10.iss_inventory > 0 ";
if($_SESSION[$_sesmodulename]["sql_stockmode"] == 1 && $_SESSION[$_sesmodulename]["sql_storehouse"])
   $seasql .= " and t10.st_id = {$_SESSION[$_sesmodulename]["sql_storehouse"]} ";
   
//----------------------------------------------------------------------------------
$cntsql   .= $seasql;
$datsql   .= $seasql;

$itemcount = $CON->select($cntsql);
$itemcount = (int)$itemcount[0]["cc"] + (int)$itemcount[1]["cc"];

//----------------------------------------------------------------------------------
$_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
{
   $_SESSION[$_sesmodulename]["orderBy"] = "7";
   $_SESSION[$_sesmodulename]["orderSort"] = "asc";
}

$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";

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
if((int)$_SESSION[$_sesmodulename]["sql_shop"])
{
   $sql = " select *
            from company_shops_storehouses
            where
            st_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} and
            st_status  = 1 ";
   //if($_SESSION[$_sesmodulename]["sql_storehouse"])
   //   $sql .= " and id = {$_SESSION[$_sesmodulename]["sql_storehouse"]} ";
   $sql .= "order by st_name";
   $storehouses = $CON->select($sql);

   $selstorehouses = Array();
   foreach($storehouses AS $storehouse)
      if($storehouse["st_shop_id"] == $_SESSION[$_sesmodulename]["sql_shop"])
         array_push($selstorehouses, $storehouse);
}
$xselstorehouses = $selstorehouses;
if($_SESSION[$_sesmodulename]["sql_storehouse"])
{
   $temparr = Array();
   foreach($selstorehouses AS $storehouse)
      if($storehouse["id"] == $_SESSION[$_sesmodulename]["sql_storehouse"])
         $temparr[] = $storehouse;
   $selstorehouses = $temparr;
}

$_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"] = $selstorehouses;

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

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

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
{
   $temp = $items;
   unset($items);

   for($x = 0; $x < count($temp) && $temp != false; $x++)
   {
      $row = $temp[$x];
      $_PCAT[$row["cat_id"]]["item_title"]         = $row["cat_title"];
      $_PCAT[$row["cat_id"]]["item_number_prod"]   = sprintf("%03s",$row["cat_id"]);

      foreach($selstorehouses AS $storehouse)
      {
         unset($curr);
         unset($cost);
         
         $curr = getItemShopStorehouseCurrentStock($CON, $_SESSION[$_sesmodulename]["sql_shop"], $storehouse["id"],  $row["id"],  "item", true);
         $min  = getItemShopStorehouseMinStock($CON, $_SESSION[$_sesmodulename]["sql_shop"], $storehouse["id"],  $row["id"],  "item", true);

         if(!(int)$_SESSION[$_sesmodulename]["sql_price_calc"])
         {
            $cost["item_costprice_netto"] = getItemAverageCost($CON, $_SESSION[$_sesmodulename]["sql_company"], $row["id"]);
            if($cost["item_costprice_netto"] == false || $cost["item_costprice_netto"] == 0.00)
               $cost["item_costprice_netto"] = getSupplierFinalCostNetto($CON, 0, $row["id"]);
         }
         else
         {
            $cost["item_costprice_netto"] = getItemFiFoCost($CON, $_SESSION[$_sesmodulename]["sql_company"], $row["id"]);
            if($cost["item_costprice_netto"] == false || (float)$cost["item_costprice_netto"] == 0.00)
               $cost["item_costprice_netto"] = getSupplierFinalCostNetto($CON, 0, $row["id"]);
         }
            
         $cost = $cost["item_costprice_netto"] * $curr;
         $_PCATSTH[$row["cat_id"]][$storehouse["id"]]["CUR"] += $curr;
         $_PCATSTH[$row["cat_id"]][$storehouse["id"]]["MIN"] += $min;
         $_PCATSTH[$row["cat_id"]][$storehouse["id"]]["CST"] += $cost;

      }
   }
   $xcounter = 0;
   foreach(array_keys($_PCAT) AS $catid)
   {
      $items[$xcounter]["item_number_prod"]  = $_PCAT[$catid]["item_number_prod"];
      $items[$xcounter]["item_title"]        = $_PCAT[$catid]["item_title"];
      foreach(array_keys($_PCATSTH[$catid]) AS $stid)
      {
         $items[$xcounter]["CUR_{$stid}"] = $_PCATSTH[$catid][$stid]["CUR"];
         $items[$xcounter]["MIN_{$stid}"] = $_PCATSTH[$catid][$stid]["MIN"];
         $items[$xcounter]["CST_{$stid}"] = $_PCATSTH[$catid][$stid]["CST"];
      }
      $xcounter++;
   }
}

//----------------------------------------------------------------------------------
$sql = " select *
         from company_shops_storehouses
         where
         st_status  = 1
         order by st_name";
$storehouses = $CON->select($sql);

$_SESSION["STATS"][$_sesmodulename]["sql_price_calc_name"] = "FIFO";
if(!(int)$_SESSION[$_sesmodulename]["sql_price_calc"])
   $_SESSION["STATS"][$_sesmodulename]["sql_price_calc_name"] = "PPP";
   
printJSsetCompanyShop($shops);
?>
<script language="JavaScript">
function setCompanyShop(companyidx)
{
   var obj = document.all.sql_shop;
   obj.options.length = 1;
   document.all.sql_storehouse.options.length = 1;
   <?php
   foreach($shops AS $shop)
   {  ?>
      if(companyidx == '<?=$shop["shop_company_id"]?>')
      {
         var newIndex   = obj.options.length;
         var newOpt     = new Option('<?=addslashes($shop["shop_name"])?>');
         newOpt.value   = '<?=$shop["id"]?>';
         obj.options[newIndex] = newOpt;
      }
      <?php
   }
   ?>
}
function setCompanyShopStorehouse(shopidx)
{
   var obj = document.all.sql_storehouse;
   obj.options.length = 1;

   <?php
   foreach($storehouses AS $storehouse)
   {  ?>
      if(shopidx == '<?=$storehouse["st_shop_id"]?>')
      {
         var newIndex   = obj.options.length;
         var newOpt     = new Option('<?=addslashes($storehouse["st_name"])?>');
         newOpt.value   = '<?=$storehouse["id"]?>';
         obj.options[newIndex] = newOpt;
      }
      <?php
   }
   ?>
}
</script>
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
   <td height="30"><b class="content_header">Stock por bodega</b></td>
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
      onsubmit="return checkform(new Array(this.sql_company, this.sql_shop))">
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
         <td class="content_row">
            <select class="text" name="sql_company" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setCompanyShop(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($companies AS $company)
               {  ?>
                  <option value="<?=$company["id"]?>"
                  <?php if($company["id"] == $_SESSION[$_sesmodulename]["sql_company"]) echo "selected"?>><?=$company["company_short"]?></option><?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Proveedor</td>
         <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row">
            <select class="text" name="sql_shop" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setCompanyShopStorehouse(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($selshops AS $selshop)
               {  ?>
                  <option value="<?=$selshop["id"]?>"
                  <?php if($selshop["id"] == $_SESSION[$_sesmodulename]["sql_shop"]) echo "selected"?>><?=$selshop["shop_name"]?>
                  </option><?php
               }
               ?>
            </select>
         </td>
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
         <td class="content_rowl">Bodega</td>
         <td class="content_row">
            <select class="text" name="sql_storehouse" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($xselstorehouses AS $selstorehouse)
               {  ?>
                  <option value="<?=$selstorehouse["id"]?>"
                  <?php if($selstorehouse["id"] == $_SESSION[$_sesmodulename]["sql_storehouse"]) echo "selected"?>><?=$selstorehouse["st_name"]?>
                  </option><?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Modo</td>
         <td class="content_row">
            <input type="radio" name="sql_stockmode" value="1"
            <?php if($_SESSION[$_sesmodulename]["sql_stockmode"] == 1) echo "checked"?>> Solamente existencias
            <input type="radio" name="sql_stockmode" value="2"
            <?php if($_SESSION[$_sesmodulename]["sql_stockmode"] == 2) echo "checked"?>> Todos productos
         </td>
         <td class="content_rowl">Agrupar por</td>
         <td class="content_row">
            <input type="radio" name="sql_dspmode" value="1"
            <?php if($_SESSION[$_sesmodulename]["sql_dspmode"] == 1) echo "checked"?>> Productos
            <input type="radio" name="sql_dspmode" value="2"
            <?php if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2) echo "checked"?>> Familia
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Calculo</td>
         <td class="content_row" colspan="3">
            <input type="radio" name="sql_price_calc" value="0"
            <?if(!(int)$_SESSION[$_sesmodulename]["sql_price_calc"]) echo "checked"?>> PPP
            <input type="radio" name="sql_price_calc" value="1"
            <?if((int)$_SESSION[$_sesmodulename]["sql_price_calc"]) echo "checked"?>> FIFO
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
         <?php
         foreach($selstorehouses AS $storehouse)
         {  ?>
            <col width="40">
            <col width="40">
            <col width="40">
            <?php
         }

         if(!$_SESSION[$_sesmodulename]["sql_storehouse"])
         {  ?>
            <col width="60">
            <col width="60">
            <?php
         }
         ?>
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os" rowspan="2" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os" rowspan="2" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_subheader content_row_os" rowspan="2" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_tbl_subheader content_row_os" rowspan="2" valign="top" style="border-right:3px double black">Unidad</td>
         <?php
         foreach($selstorehouses AS $storehouse)
         {  ?>
            <td class="content_tbl_subheader content_row_os" colspan="3" align="center" style="border-right:3px double black"><nobr><b><?=$storehouse["st_name"]?></b></nobr></td>
            <?php
         }
         if(!$_SESSION[$_sesmodulename]["sql_storehouse"])
         {
            ?>
            <td class="content_tbl_subheader content_row_os" align="right" rowspan="2" valign="top">Total</td>
            <td class="content_tbl_subheader content_row_os" align="right" rowspan="2" valign="top">Total $</td>
            <?php
         }
         ?>
      </tr>
      <tr>
         <?php
         foreach($selstorehouses AS $storehouse)
         {  ?>
            <td class="content_tbl_subheader content_row_os" align="center" valign="top"><nobr>Actual</nobr></td>
            <td class="content_tbl_subheader content_row_os" align="center" valign="top"><nobr>Valor/U $</nobr></td>
            <td class="content_tbl_subheader content_row_os" align="center" valign="top" style="border-right:3px double black"><nobr>Valor $</nobr></td>
            <?php
         }
         ?>
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$items[$x]["item_number_prod"]?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["item_title"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$items[$x]["item_code"]?></nobr>&nbsp;</td>
            <td class="content_row_os" style="border-right:3px double black"><?=$items[$x]["unit_name"]?>&nbsp;</td>
            <?php
            $lineges = 0;
            $itemgescost = 0.00;
            foreach($selstorehouses AS $storehouse)
            {
               unset($curr);
               unset($cost);
               if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
               {
                  $curr = $items[$x]["CUR_{$storehouse["id"]}"];
                  $min  = $items[$x]["MIN_{$storehouse["id"]}"];
                  $cost = $items[$x]["CST_{$storehouse["id"]}"];
               }
               else
               {
                  $curr = getItemShopStorehouseCurrentStock($CON, $_SESSION[$_sesmodulename]["sql_shop"], $storehouse["id"],  $items[$x]["id"],  "item", true);
                  $min  = getItemShopStorehouseMinStock($CON, $_SESSION[$_sesmodulename]["sql_shop"], $storehouse["id"],  $items[$x]["id"],  "item", true);

                  if(!(int)$_SESSION[$_sesmodulename]["sql_price_calc"])
                  {
                     $cost["item_costprice_netto"] = getItemAverageCost($CON, $_SESSION[$_sesmodulename]["sql_company"], $items[$x]["id"]);
                     if($cost["item_costprice_netto"] == false)
                        $cost["item_costprice_netto"] = getSupplierFinalCostNetto($CON, 0, $items[$x]["id"]);
                  }
                  else
                  {
                     $cost["item_costprice_netto"] = getItemFiFoCost($CON, $_SESSION[$_sesmodulename]["sql_company"], $items[$x]["id"]);
                     if($cost["item_costprice_netto"] == false || (float)$cost["item_costprice_netto"] == 0.00)
                        $cost["item_costprice_netto"] = getSupplierFinalCostNetto($CON, 0, $items[$x]["id"]);
                  }
                  $cost = $cost["item_costprice_netto"] * $curr;
               }
               

               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["BODEGA"][$storehouse["st_name"]]["curr"]    = $curr;
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["BODEGA"][$storehouse["st_name"]]["costu"]   = $cost / $curr;
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["BODEGA"][$storehouse["st_name"]]["cost"]    = $cost;
               
               ?>
               <td class="content_row_os" align="center" <?php if($curr <= $min && $min > 0.00) echo "style='color:red'"?>><?=printPrice($curr,2, true)?></td>
               <td class="content_row_os" align="center" <?php if($curr <= $min && $min > 0.00) echo "style='color:red'"?>><?=printPrice($cost / $curr,2, true)?></td>
               <td class="content_row_os" align="center" style="border-right:3px double black"><?=printPrice($cost,2, true)?></td>
               <?php
               $lineges                            += $curr;
               $_TOTALSTH[$storehouse["id"]]       += $curr;
               $itemgescost                        += $cost;
               $_TOTALSTHCOST[$storehouse["id"]]   += $cost;
            }
            $_TOTAL     += $lineges;
            $_TOTALCOST += $itemgescost;

            if(!$_SESSION[$_sesmodulename]["sql_storehouse"])
            {  ?>
               <td class="content_row_os" align="right"><?=printPrice($lineges,2, true)?></td>
               <td class="content_row_os" align="right"><?=printPrice($itemgescost,2, true)?></td>
               <?php
            }
            ?>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]  = $items[$x]["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]        = $items[$x]["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_code"]         = $items[$x]["item_code"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitdesc"]          = $items[$x]["unit_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["total"]             = $lineges;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["totalcost"]         = $itemgescost;
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="7" align="center">
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
            <td class="content_row_totals content_row_os" colspan="4" style="border-right:3px double black">Total</td>
            <?php
            foreach($selstorehouses AS $storehouse)
            {
               ?>
               <td class="content_row_totals content_row_os" align="center"><?=printPrice($_TOTALSTH[$storehouse["id"]],2, true)?></td>
               <td class="content_row_totals content_row_os" align="center">&nbsp;</td>
               <td class="content_row_totals content_row_os" align="center" style="border-right:3px double black"><?=printPrice($_TOTALSTHCOST[$storehouse["id"]],2, true)?></td>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["BODEGA"][$storehouse["st_name"]]["curr"]    = $_TOTALSTH[$storehouse["id"]];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["BODEGA"][$storehouse["st_name"]]["cost"]    = $_TOTALSTHCOST[$storehouse["id"]];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["BODEGA"][$storehouse["st_name"]]["costu"]   = " ";
            }
            if(!$_SESSION[$_sesmodulename]["sql_storehouse"])
            {  ?>
               <td class="content_row_totals content_row_os" align="right"><?=printPrice($_TOTAL,2, true)?></td>
               <td class="content_row_totals content_row_os" align="right"><?=printPrice($_TOTALCOST,2, true)?></td>
               <?php
            }
            ?>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"] = "Total";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["total"] = $_TOTAL;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["totalcost"] = $_TOTALCOST;
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
  $pdffile = doc_createStatsItemStoreHouses($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsItemStoreHouses($CON);
  
if($pdffile != "")
{
   $doctitle = "Stock-por-bodega-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Stock-por-bodega-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>