<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

unset($_TOTALSHPCOST);
unset($_TOTALSHP);
unset($_TOTAL);
unset($_TOTALCOST);
unset($_PCAT);
unset($_STOCK);
unset($_ITEMS);
unset($_RESCK);
unset($_TOTALRES);
unset($_TOTALCOMP);
unset($_TOTALR);

//----------------------------------------------------------------------------------
$_sesmodulename         = "stock_shops";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo" => "2", "Codigo Prov." => "7");

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
   $_SESSION[$_sesmodulename]["sql_dspmode"]   = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_stockmode"] = (int)$_REQUEST["sql_stockmode"];
   $_SESSION[$_sesmodulename]["sql_price_calc"] = (int)$_REQUEST["sql_price_calc"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
$companies  = getCompanies($CON, true);
$shops      = getShops($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;
if(!(int)$_SESSION[$_sesmodulename]["sql_stockmode"])
   $_SESSION[$_sesmodulename]["sql_stockmode"] = 1;

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
$seasql = "";
$joisql = " INNER JOIN item_shops      t2 ON t1.id = t2.item_id
            INNER JOIN company_shops   t3 ON ( t2.shop_id = t3.id and t3.shop_status = 1 )
            INNER JOIN company_data    t4 ON ( t3.shop_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN item_suppliers  t5 ON ( t1.id = t5.item_id and ";

if($_SESSION[$_sesmodulename]["sql_supplier"])
   $joisql .= " t5.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ) ";
else
   $joisql .= " t5.item_supp_act = 1 ) ";
   
$joisql .= " LEFT OUTER JOIN supplier t6      ON ( t5.supplier_id = t6.id )
             INNER JOIN item_productcats t7   ON t1.id = t7.item_id
             LEFT OUTER JOIN item_units t8    ON t1.item_unit = t8.id
             LEFT OUTER JOIN productcats t9   ON t7.cat_id = t9.id
             LEFT OUTER JOIN item_shops_storehouses     t10 ON ( t2.item_id = t10.item_id and t2.shop_id = t10.shop_id )
             LEFT OUTER JOIN company_shops_storehouses  t11 ON ( t11.id = t10.st_id and t2.shop_id = t11.st_shop_id and t11.st_status > 0 ) ";

//----------------------------------------------------------------------------------
$cntsql = " select count(distinct t1.id) 'cc'
            from item t1
            {$joisql}
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";

$datsql = " select t1.item_number_prod, t1.item_title, t1.id, t7.cat_id, t9.cat_title, t8.unit_name, t5.item_code, t2.shop_id, SUM(t10.iss_inventory) 'currstock'
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
   $seasql .= " and t10.iss_inventory != 0 ";

//----------------------------------------------------------------------------------
$cntsql   .= $seasql;
$datsql   .= $seasql;

$itemcount = $CON->select($cntsql);
$itemcount = (int)$itemcount[0]["cc"];

//----------------------------------------------------------------------------------
$_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
{
   $_SESSION[$_sesmodulename]["orderBy"] = "4";
   $_SESSION[$_sesmodulename]["orderSort"] = "asc";
}

$datsql .= " group by t1.id, t2.shop_id
             order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}";

//----------------------------------------------------------------------------------
$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
for($x = 0; $x < count($items) && $items != false; $x++)
{
   $resv = getStockShared($CON, $items[$x]["id"], "item", $items[$x]["shop_id"]);
   $comp = getItemStockComp($CON, $items[$x]["shop_id"], $items[$x]["id"], "item");
   
   $shopid = $items[$x]["shop_id"];
   $itemid = $items[$x]["id"];
   $_STOCK[$itemid][$shopid] += $items[$x]["currstock"];
   $_RESCK[$itemid][$shopid] += $resv;
   $_COMP[$itemid][$shopid]  += $comp;
   $_ITEMS[$itemid] = $items[$x];
}
$items = array_values($_ITEMS);

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

      foreach($selshops AS $selshop)
      {
         unset($stock);
         unset($cost);

         $stock = getItemShopCurrentStock($CON, $selshop["id"], $row["id"], "item", true);

         $cost = $cost["item_costprice_netto"] * $stock;
         $_PCATSTH[$row["cat_id"]][$selshop["id"]]["CUR"] += $stock;
         $_PCATSTH[$row["cat_id"]][$selshop["id"]]["CST"] += $cost;
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
         $items[$xcounter]["CST_{$stid}"] = $_PCATSTH[$catid][$stid]["CST"];
      }
      $xcounter++;
   }
}

$_SESSION["STATS"][$_sesmodulename]["sql_price_calc_name"] = "FIFO";
if(!(int)$_SESSION[$_sesmodulename]["sql_price_calc"])
   $_SESSION["STATS"][$_sesmodulename]["sql_price_calc_name"] = "PPP";

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
<table border="0" cellpadding="0" cellspacing="0" width="1080">
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
      <?=Nifty_printH("box2", "1080")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col width="300">
         <col width="100">
         <col>
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
         <td class="content_rowl">Calculo</td>
         <td class="content_row">
            <input type="radio" name="sql_price_calc" value="0"
            <?if(!(int)$_SESSION[$_sesmodulename]["sql_price_calc"]) echo "checked"?>> PPP
            <input type="radio" name="sql_price_calc" value="1"
            <?if((int)$_SESSION[$_sesmodulename]["sql_price_calc"]) echo "checked"?>> FIFO
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
      <?=Nifty_printH("box1", "1080")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="75">
         <col>
         <col>
         <col>
         <?php
         foreach($selshops AS $selshop)
         {  ?>
            <col width="60">
            <col width="60">
            <col width="60">
            <?php
         }
         ?>
         <col width="60">
         <col width="60">
         <col width="60">
         <col width="60">
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os" rowspan="2"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os" rowspan="2"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_subheader content_row_os" rowspan="2"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_tbl_subheader content_row_os" rowspan="2" style="border-right:3px double black">Unidad</td>
         <?php
         foreach($selshops AS $selshop)
         {  ?>
            <td class="content_tbl_subheader content_row_os" align="center" colspan="3" style="border-right:3px double black"><nobr><?=$selshop["shop_name"]?></nobr></td>
            <?php
         }
         ?>
         <td class="content_tbl_subheader content_row_os" align="center" rowspan="2">T/Stock</td>
         <td class="content_tbl_subheader content_row_os" align="center" rowspan="2">T/Reserva</td>
         <td class="content_tbl_subheader content_row_os" align="center" rowspan="2" style="border-right:3px double black">T/Comp.</td>
         <td class="content_tbl_subheader content_row_os" align="center" rowspan="2">T/Dispo</td>
      </tr>
      <tr>
         <?php
         foreach($selshops AS $selshop)
         {  ?>
            <td class="content_tbl_subheader content_row_os" align="center">Stock</td>
            <td class="content_tbl_subheader content_row_os" align="center">Reserva</td>
            <td class="content_tbl_subheader content_row_os" align="center" style="border-right:3px double black">Comp.</td>
            <?php
         }
         ?>
      </tr>
      <?php
      unset($_TOTAL);
      unset($_TOTALCOST);
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items) && $items != false; $x++)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$items[$x]["item_number_prod"]?></td>
            <td class="content_row_os"><?=$items[$x]["item_title"]?></td>
            <td class="content_row_os"><?=$items[$x]["item_code"]?>&nbsp;</td>
            <td class="content_row_os" style="border-right:3px double black"><?=$items[$x]["unit_name"]?>&nbsp;</td>
            <?php
            $lineges = 0;
            $reslges = 0;
            $compges = 0;
            $itemgescost = 0.00;
            foreach($selshops AS $selshop)
            {
               unset($cost);
               unset($stock);
               unset($compro);
               if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
               {
                  $stock   = $items[$x]["CUR_{$selshop["id"]}"];
               }
               else
               {
                  $stock  = $_STOCK[$items[$x]["id"]][$selshop["id"]];
                  $restk  = $_RESCK[$items[$x]["id"]][$selshop["id"]];
                  $compro = $_COMP[$items[$x]["id"]][$selshop["id"]];
               }
               ?>
               <td class="content_row_os" align="center" <?php if($stock <= 0.00) echo "style='color:red'"?>><?=printPrice($stock,2)?></td>
               <td class="content_row_os" align="center" ><?=printPrice($restk,2)?></td>
               <td class="content_row_os" align="center" style="border-right:3px double black"><?=printPrice($compro,2)?></td>
               <?php
               $lineges                            += $stock;
               $reslges                            += $restk;
               $compges                            += $compro;
               $_TOTALSHP[$selshop["id"]]          += $stock;
               $_TOTALRES[$selshop["id"]]          += $restk;
               $_TOTALCOMP[$selshop["id"]]         += $compro;

               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["SHOP"][$selshop["shop_name"]]["curr"]    = printPrice($stock,2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["SHOP"][$selshop["shop_name"]]["resr"]    = printPrice($restk,2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["SHOP"][$selshop["shop_name"]]["comp"]    = printPrice($compro,2);
            }
            $_TOTAL     += $lineges;
            $_TOTALR    += $reslges;
            $_TOTALC    += $compges;

            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]  = $items[$x]["item_number_prod"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]        = $items[$x]["item_title"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_code"]         = $items[$x]["item_code"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unit_name"]         = $items[$x]["unit_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["lineges"]           = printPrice($lineges,2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["reslges"]           = printPrice($reslges,2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["compges"]           = printPrice($compges,2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["dispo"]             = printPrice($lineges - $reslges - $compges, 2);
            ?>
            <td class="content_row_os" align="center"><?=printPrice($lineges,2)?></td>
            <td class="content_row_os" align="center" ><?=printPrice($reslges,2)?></td>
            <td class="content_row_os" align="center" style="border-right:3px double black"><?=printPrice($compges,2)?></td>
            <td class="content_row_os" align="center"><?=printPrice($lineges - $reslges - $compges ,2)?></td>
         </tr>
         <?php
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
            foreach($selshops AS $selshop)
            {  ?>
               <td class="content_row_totals content_row_os" align="center"><?=printPrice($_TOTALSHP[$selshop["id"]],2, true)?></td>
               <td class="content_row_totals content_row_os" align="center"><?=printPrice($_TOTALRES[$selshop["id"]],2, true)?></td>
               <td class="content_row_totals content_row_os" align="center" style="border-right:3px double black"><?=printPrice($_TOTALCOMP[$selshop["id"]],2, true)?></td>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["SHOP"][$selshop["shop_name"]]["curr"]    = printPrice($_TOTALSHP[$selshop["id"]],2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["SHOP"][$selshop["shop_name"]]["resr"]    = printPrice($_TOTALRES[$selshop["id"]],2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["SHOP"][$selshop["shop_name"]]["comp"]   = printPrice($_TOTALCOMP[$selshop["id"]],2);
            }
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"] = "Total";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["lineges"]  = printPrice($_TOTAL,2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["reslges"]  = printPrice($_TOTALR,2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["compges"] = printPrice($_TOTALC,2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["dispo"]    = printPrice($_TOTAL - $_TOTALR - $_TOTALC,2);

            ?>
            <td class="content_row_totals content_row_os" align="center"><?=printPrice($_TOTAL,2, true)?></td>
            <td class="content_row_totals content_row_os" align="center"><?=printPrice($_TOTALR,2, true)?></td>
            <td class="content_row_totals content_row_os" align="center" style="border-right:3px double black"><?=printPrice($_TOTALC,2, true)?></td>
            <td class="content_row_totals content_row_os" align="center"><?=printPrice($_TOTAL - $_TOTALR - $_TOTALC ,2, true)?></td>
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
