<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stock_prices";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Cod. Producto" => "2", "Nombre" => "3");
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
   $_SESSION[$_sesmodulename]["range_min_price"] = (int)$_REQUEST["range_min_price"];
   $_SESSION[$_sesmodulename]["range_max_price"] = (int)$_REQUEST["range_max_price"];
   $_SESSION[$_sesmodulename]["range_min_stock"] = (int)$_REQUEST["range_min_stock"];
   $_SESSION[$_sesmodulename]["range_max_stock"] = (int)$_REQUEST["range_max_stock"];
   $_SESSION[$_sesmodulename]["sql_stockmode"]   = (int)$_REQUEST["sql_stockmode"];
   $_SESSION[$_sesmodulename]["sql_price_type"]  = (int)$_REQUEST["sql_price_type"];
   $_SESSION[$_sesmodulename]["page"]            = 0;
   $_SESSION[$_sesmodulename]["search_active"]   = 1;
}

//----------------------------------------------------------------------------------
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
if(!(int)$_SESSION[$_sesmodulename]["sql_stockmode"])
   $_SESSION[$_sesmodulename]["sql_stockmode"] = 1;

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
/*
$joisql = " INNER JOIN item_shops t2 ON t1.id = t2.item_id
            INNER JOIN company_shops t3 ON t2.shop_id = t3.id AND t3.shop_status =1
            INNER JOIN company_data t4 ON ( t3.shop_company_id = t4.id AND t4.company_status =1 )
            LEFT OUTER JOIN item_productcats t7 ON t1.id = t7.item_id
            LEFT OUTER JOIN item_units t9 ON t1.item_unit = t9.id
            LEFT OUTER JOIN item_shops_storehouses t10 ON ( t2.item_id = t10.item_id AND t2.shop_id = t10.shop_id )
            LEFT OUTER JOIN company_shops_storehouses t11 ON ( t11.id = t10.st_id AND t2.shop_id = t11.st_shop_id AND t11.st_status >0 ) ";
*/
$joisql = " INNER JOIN item_shops t2 ON t1.id = t2.item_id
            INNER JOIN company_shops t3 ON t2.shop_id = t3.id AND t3.shop_status =1
            INNER JOIN company_data t4 ON ( t3.shop_company_id = t4.id AND t4.company_status =1 )
            LEFT OUTER JOIN item_productcats t7 ON t1.id = t7.item_id
            LEFT OUTER JOIN item_units t9 ON t1.item_unit = t9.id
            LEFT OUTER JOIN item_shops_storehouses t10 ON ( t2.item_id = t10.item_id AND t2.shop_id = t10.shop_id )
            LEFT OUTER JOIN company_shops_storehouses t11 ON ( t11.id = t10.st_id AND t2.shop_id = t11.st_shop_id AND t11.st_status >0 ) 
            LEFT OUTER JOIN item_suppliers t12 ON t1.id = t12.item_id";

$datsql = " SELECT t1.id, t1.item_number_prod, t1.item_title, t2.itemshop_sellprice_netto, sum(t10.iss_inventory ) AS sum_inventory, t4.company_short, t3.shop_name, t12.item_costprice_netto
            FROM item t1
            {$joisql}
            WHERE
            t1.item_status    = 1 and
            t1.item_released  = 1 ";

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_item_type"] == "itemlist")
   $datsql .= " and 1 = 2 ";
elseif($_SESSION[$_sesmodulename]["sql_item_type"] == "item")
   $datsql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t3.shop_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t2.shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t7.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
//----------------------------------------------------------------------------------

$datsql   .= $seasql;
$datsql .= " GROUP BY t1.id ";
if((int)$_SESSION[$_sesmodulename]["range_min_stock"] || (int)$_SESSION[$_sesmodulename]["range_max_stock"])
   $datsql .= " HAVING SUM(t10.iss_inventory) between {$_SESSION[$_sesmodulename]["range_min_stock"]} and {$_SESSION[$_sesmodulename]["range_max_stock"]} ";

//----------------------------------------------------------------------------------
$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
$items = $CON->select($datsql);

$_SESSION["STATS"][$_sesmodulename]["COMPANY"] = $items[0]["company_short"];
$_SESSION["STATS"][$_sesmodulename]["SHOP"]    = $items[0]["shop_name"];

//----------------------------------------------------------------------------------
$tempres = Array();
for($x = 0; $x < count($items) && $items != false; $x++)
{
   $sql = " select t3.*
            from price_lists t1
            INNER JOIN price_lists_shops t2 ON t1.id = t2.pl_id and t2.shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]}
            INNER JOIN price_lists_items t3 ON t1.id = t3.pl_id
            where
            t1.pl_status   > 0 and
            t3.item_id     = {$items[$x]["id"]} and
            t3.item_type   = 'item'";
   $prclist = $CON->select($sql);

   $items[$x]["_prcmayor"]    = $prclist[0]["item_sellprice_brutto2"];
   $items[$x]["_prcdetalle"]  = $prclist[0]["item_sellprice_brutto"];

   if((int)$_SESSION[$_sesmodulename]["range_min_price"] || (int)$_SESSION[$_sesmodulename]["range_max_price"])
   {
      switch($_SESSION[$_sesmodulename]["sql_price_type"])
      {
         case 0:  if((float)$items[$x]["itemshop_sellprice_netto"] >= $_SESSION[$_sesmodulename]["range_min_price"] &&
                     (float)$items[$x]["itemshop_sellprice_netto"] <= $_SESSION[$_sesmodulename]["range_max_price"])
                     $tempres[] = $items[$x];
                  break;
         case 1:  if((float)$items[$x]["_prcmayor"] >= $_SESSION[$_sesmodulename]["range_min_price"] &&
                     (float)$items[$x]["_prcmayor"] <= $_SESSION[$_sesmodulename]["range_max_price"])
                     $tempres[] = $items[$x];
                  break;
         case 2:  if((float)$items[$x]["_prcdetalle"] >= $_SESSION[$_sesmodulename]["range_min_price"] &&
                     (float)$items[$x]["_prcdetalle"] <= $_SESSION[$_sesmodulename]["range_max_price"])
                     $tempres[] = $items[$x];
                  break;
         case 3:  if((float)$items[$x]["item_costprice_netto"] >= $_SESSION[$_sesmodulename]["range_min_price"] &&
                     (float)$items[$x]["item_costprice_netto"] <= $_SESSION[$_sesmodulename]["range_max_price"])
                     $tempres[] = $items[$x];
                  break;
      }
   }
   else
      $tempres[] = $items[$x];
}
$items = $tempres;

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
            st_status  = 1
            order by st_name";
   $storehouses = $CON->select($sql);

   $selstorehouses = Array();
   foreach($storehouses AS $storehouse)
      if($storehouse["st_shop_id"] == $_SESSION[$_sesmodulename]["sql_shop"])
         array_push($selstorehouses, $storehouse);

}

$_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"] = $selstorehouses;

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

//----------------------------------------------------------------------------------
$stylehead = "border-bottom: 3px double #666666";

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
   <td height="30"><b class="content_header">Stock y precios</b></td>
   <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults(count($items)); else echo $savemsg;?></td>
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
         <td class="content_rowl">Sucursal</td>
         <td class="content_row">
            <select class="text" name="sql_shop" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
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
            <select class="text" name="sql_storehouse" style="display:none"></select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Rango precios</td>
         <td class="content_row">
            <input type="text" class="text" name="range_min_price" style="width: 100px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?php if((int)$_SESSION[$_sesmodulename]["range_min_price"]) echo $_SESSION[$_sesmodulename]["range_min_price"];?>">
            -
            <input type="text" class="text" name="range_max_price" style="width: 100px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?php if((int)$_SESSION[$_sesmodulename]["range_max_price"]) echo $_SESSION[$_sesmodulename]["range_max_price"];?>">

            <select name="sql_price_type" style="width:162px" class="text"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="0" <?php if((int)$_SESSION[$_sesmodulename]["sql_price_type"] == 0) echo "selected"?>>Precio Base</option>
               <option value="1" <?php if((int)$_SESSION[$_sesmodulename]["sql_price_type"] == 1) echo "selected"?>>Precio Mayorista</option>
               <option value="2" <?php if((int)$_SESSION[$_sesmodulename]["sql_price_type"] == 2) echo "selected"?>>Precio Detalle</option>
               <option value="3" <?php if((int)$_SESSION[$_sesmodulename]["sql_price_type"] == 3) echo "selected"?>>Precio Costo</option>
            </select>
         </td>
         <td class="content_rowl">Rango stock</td>
         <td class="content_row">
            <input type="text" class="text" name="range_min_stock" style="width: 100px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?php if((int)$_SESSION[$_sesmodulename]["range_min_stock"]) echo $_SESSION[$_sesmodulename]["range_min_stock"];?>">
            -
            <input type="text" class="text" name="range_max_stock" style="width: 100px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?php if((int)$_SESSION[$_sesmodulename]["range_max_stock"]) echo $_SESSION[$_sesmodulename]["range_max_stock"];?>">
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
                  if(count($items) && $items != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($items) && $items != false)
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
         <col width="100">
         <col>
         <col width="100">
         <col width="130">
         <col width="130">
         <col width="130">
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_subheader content_row_os"><b>Cantidad</b></td>
         <td class="content_tbl_subheader content_row_os"><b>Precio Costo/Neto</b></td>
         <td class="content_tbl_subheader content_row_os"><b>Precio Base/Neto</b></td>
         <td class="content_tbl_subheader content_row_os"><b>Mayorista/Bruto</b></td>
         <td class="content_tbl_subheader content_row_os"><b>Detalle/Bruto</b></td>
      </tr>
      <?php

         $counter = 0;
         foreach($items as $item)
         {  ?>
            <tr bgcolor="<?=getRowColor($counter)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os" valign="top" ><?=$item["item_number_prod"]?></td>
               <td class="content_row_os" valign="top" ><?=$item["item_title"]?></td>
               <td class="content_row_os" valign="top" ><?=printPrice($item["sum_inventory"],2)?></td>
               <td class="content_row_os" valign="top" ><?=printPrice($item["item_costprice_netto"])?></td>
               <td class="content_row_os" valign="top" ><?=printPrice($item["itemshop_sellprice_netto"])?></td>
               <td class="content_row_os" valign="top" ><?=printPrice($item["_prcmayor"])?></td>
               <td class="content_row_os" valign="top" ><?=printPrice($item["_prcdetalle"])?></td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$counter]["num"]    = $item["item_number_prod"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$counter]["title"]  = $item["item_title"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$counter]["stock"]  = printPrice($item["sum_inventory"],2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$counter]["neto"]   = printPrice($item["item_costprice_netto"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$counter]["costo"]  = printPrice($item["itemshop_sellprice_netto"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$counter]["bruto"]  = printPrice($item["_prcmayor"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$counter]["dbruto"] = printPrice($item["_prcdetalle"]);

            $counter++;
         }

         if(!$counter)
         {  ?>
            <tr>
               <td class="content_row" colspan="6" align="center" style="height:40px">
                  <b class="msg_save_err"><?=$_LANG["FORM"]["MESSAGE"][5]?></b>
               </td>
            </tr>
            <?php
         }  ?>
      </table>
      <?=Nifty_printF()?>
      <br>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsStockPrice($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsStockPrice($CON);

if($pdffile != "")
{
   $doctitle = "Stock-por-precio-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Stock-por-precio-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>