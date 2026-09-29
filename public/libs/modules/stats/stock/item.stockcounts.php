<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stock_stockcounts";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo/Familia" => "2");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_item_id"]       = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"]     = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]          = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]       = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_stcmode"]       = (int)$_REQUEST["sql_stcmode"];
   $_SESSION[$_sesmodulename]["sql_stockmode"]     = (int)$_REQUEST["sql_stockmode"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_stcnum"]        = trim($_REQUEST["sql_stcnum"]);
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
}

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON, true);
$shops      = getShops($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;
if(!(int)$_SESSION[$_sesmodulename]["sql_stcmode"])
   $_SESSION[$_sesmodulename]["sql_stcmode"] = 1;
$_SESSION[$_sesmodulename]["sql_stockmode"] = (int)$_SESSION[$_sesmodulename]["sql_stockmode"];
   
//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_date"] == "")
   $_SESSION[$_sesmodulename]["sql_date"] = date('d.m.Y');


//----------------------------------------------------------------------------------
$datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date"]);
$sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
$sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

if($_SESSION[$_sesmodulename]["sql_stcmode"] == 1 && $_SESSION[$_sesmodulename]["sql_stcnum"] != "")
{
   $sql = " select distinct t1.*, t2.lst_date
            from stockcounts t1
            INNER JOIN stockcounts_lists t2 ON t1.id = t2.stc_id
            where
            t2.lst_status = 2 ";
   if($_SESSION[$_sesmodulename]["sql_company"])
      $sql .= " and t1.stc_companyid = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $sql .= " and t1.stc_shopid = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   $sql .= " and t1.stc_num like '%{$_SESSION[$_sesmodulename]["sql_stcnum"]}' ";
   $sql .= " order by t1.id desc";
   $stockcounts = $CON->select($sql);

   if(!(int)$stockcounts[0]["stk_bookdate"])
      $stockcounts[0]["stc_bookdate"] = $stockcounts[0]["stc_crtdat"];
   if((int)$stockcounts[0]["lst_date"])
      $stockcounts[0]["stc_bookdate"] = $stockcounts[0]["lst_date"];
      
   $temparr[$stockcounts[0]["id"]] = $stockcounts[0]["stc_bookdate"];
   $stockcounts = $temparr;
   asort($stockcounts);
}
elseif($_SESSION[$_sesmodulename]["sql_stcmode"] == 2)
{
   $sql = " select distinct t1.*, t2.lst_date
            from stockcounts t1
            INNER JOIN stockcounts_lists t2 ON t1.id = t2.stc_id
            where
            t2.lst_status = 2 and
            (
               t1.stc_bookdate between {$sql_datefrom} and {$sql_dateto} or
               ( t2.lst_date > 0 and t2.lst_date between {$sql_datefrom} and {$sql_dateto} )
            ) ";
   if($_SESSION[$_sesmodulename]["sql_company"])
      $sql .= " and t1.stc_companyid = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $sql .= " and t1.stc_shopid = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   $sql .= " order by t1.id desc";
   $stockcounts = $CON->select($sql);

   for($x = 0; $x < count($stockcounts) && $stockcounts != false; $x++)
   {
      $temparr[$stockcounts[$x]["id"]] = $stockcounts[$x]["stc_bookdate"];
      if((int)$stockcounts[$x]["lst_date"])
      {
         $temparr[$stockcounts[$x]["id"]] = $stockcounts[$x]["lst_date"];
      }
   }
   $stockcounts = $temparr;
   asort($stockcounts);
}

/*
$sql = " select distinct t1.*
         from stockcounts t1
         INNER JOIN stockcounts_lists t2 ON t1.id = t2.stc_id
         where
         t1.stc_bookdate <= {$sql_dateto} and
         t2.lst_status = 2 ";
if($_SESSION[$_sesmodulename]["sql_company"])
   $sql .= " and t1.stc_companyid = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $sql .= " and t1.stc_shopid = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if($_SESSION[$_sesmodulename]["sql_stcmode"] == 1 && $_SESSION[$_sesmodulename]["sql_stcnum"] != "")
   $sql .= " and t1.stc_num like '%{$_SESSION[$_sesmodulename]["sql_stcnum"]}' ";
$sql .= " order by t1.id desc";
$stockcounts = $CON->select($sql);

//----------------------------------------------------------------------------------
$temparr = Array();
for($x = 0; $x < count($stockcounts) && $stockcounts != false; $x++)
{
   $sql = " select id, stk_bookdate
            from stockchanges
            where
            stk_companyid  = {$stockcounts[$x]["stc_companyid"]} and
            stk_shopid     = {$stockcounts[$x]["stc_shopid"]} and
            stk_annotation like '%Recuento%{$stockcounts[$x]["stc_num"]}%'";
   $stcs = $CON->select($sql);

   if((int)$stcs[0]["id"])
      $temparr[$stockcounts[$x]["id"]] = $stcs[0]["stk_bookdate"];
}
$stockcounts = $temparr;
asort($stockcounts);
*/

//----------------------------------------------------------------------------------
foreach(array_keys($stockcounts) AS $stcid)
{
   $sql = " select distinct t4.item_number_prod, t4.item_title, t6.st_name, t1.stc_num, t7.unit_name,
                   t5.cat_id, t8.cat_title, t3.*, t9.item_code, t1.stc_annotation, t10.ubi_name
            from stockcounts t1
            INNER JOIN stockcounts_lists t2              ON ( t1.id = t2.stc_id )
            INNER JOIN stockcounts_lists_items t3        ON ( t3.stc_id = t2.stc_id and t3.stc_lst_posid = t2.lst_pos )
            INNER JOIN item t4                           ON ( t3.item_id = t4.id )
            LEFT OUTER JOIN item_productcats t5          ON ( t3.item_id = t5.item_id )
            LEFT OUTER JOIN company_shops_storehouses t6 ON ( t3.item_stid = t6.id )
            LEFT OUTER JOIN item_units t7                ON ( t4.item_unit = t7.id )
            LEFT OUTER JOIN productcats t8               ON t5.cat_id = t8.id
            LEFT OUTER JOIN item_suppliers   t9          ON ( t3.item_id = t9.item_id and t9.item_supp_act = 1 )
            LEFT OUTER JOIN ubicacion t10                ON t1.stc_ubicid = t10.id
            where
            t1.id = {$stcid} and
            t2.lst_status = 2 ";
   if((int)$_REQUEST["printStatRep"])
      $sql .= " and t2.lst_pos = {$_REQUEST["lst_pos"]} ";
   if($_SESSION[$_sesmodulename]["sql_pcat"])
      $sql .= " and t5.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
      $sql .= " and t4.id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
   $items = $CON->select($sql);
   
   foreach($items AS $item)
   {
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["ubi_name"]                  = $item["ubi_name"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["item_amount_stock"]         = $item["item_amount_stock"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["item_amount_count"]         = $item["item_amount_count"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["item_amount_book"]          = $item["item_amount_book"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["item_costprice_avg_netto"]  = $item["item_costprice_avg_netto"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["item_costprice_docnumber"]  = $item["item_costprice_docnumber"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["item_costprice_docdate"]    = $item["item_costprice_docdate"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["item_number_prod"]          = $item["item_number_prod"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["item_title"]                = $item["item_title"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["unit_name"]                 = $item["unit_name"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["item_code"]                 = $item["item_code"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["st_name"]                   = $item["st_name"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["stc_num"]                   = $item["stc_num"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["stc_annotation"]            = $item["stc_annotation"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["date"]                      = $stockcounts[$stcid];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["cat_id"]                    = $item["cat_id"];
      $_ITEMS[$item["item_id"]][$item["item_stid"]]["cat_title"]                 = $item["cat_title"];
      

      if($_SESSION[$_sesmodulename]["orderBy"] == "2")
         $_ITEMIDS[$item["item_id"]] = $item["item_title"];
      else
         $_ITEMIDS[$item["item_id"]] = $item["item_number_prod"];
   }
}
if($_SESSION[$_sesmodulename]["orderSort"] == "asc")
   asort($_ITEMIDS);
else
   arsort($_ITEMIDS);

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
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
{
   $temp = $_ITEMS;
   unset($_ITEMS);
   unset($_ITEMIDS);

   foreach(array_keys($temp) AS $itemid)
   {
      foreach(array_keys($temp[$itemid]) AS $sthid)
      {
         $row = $temp[$itemid][$sthid];
         $_PCAT[$row["cat_id"]]["item_title"]         = $row["cat_title"];
         $_PCAT[$row["cat_id"]]["item_number_prod"]   = sprintf("%03s",$row["cat_id"]);

         $_PCAT[$row["cat_id"]]["item_amount_stock"]        += $row["item_amount_stock"];
         $_PCAT[$row["cat_id"]]["item_amount_count"]        += $row["item_amount_count"];
         $_PCAT[$row["cat_id"]]["item_amount_book"]         += $row["item_amount_book"];
         $_PCAT[$row["cat_id"]]["unit_name"]                 = $row["unit_name"];
         $_PCAT[$row["cat_id"]]["st_name"]                   = $row["st_name"];
         $_PCAT[$row["cat_id"]]["stc_num"]                   = $row["stc_num"];
         $_PCAT[$row["cat_id"]]["date"]                      = $row["date"];
         $_PCAT[$row["cat_id"]]["cat_id"]                    = $row["cat_id"];
         $_PCAT[$row["cat_id"]]["cat_title"]                 = $row["cat_title"];

         $stock_new = $row["item_amount_stock"] + $row["item_amount_book"];
         if($stock_new != 0.00)
         {
            $xvalue_total = $stock_new * $row["item_costprice_avg_netto"];
            $_PCAT[$row["cat_id"]]["x_avg_netto_total"] += $xvalue_total;
         }
      }
   }
   ksort($_PCAT);

   $xcounter = 0;
   foreach(array_keys($_PCAT) AS $catid)
   {
      $_ITEMS[$xcounter][""]["item_number_prod"]         = $_PCAT[$catid]["item_number_prod"];
      $_ITEMS[$xcounter][""]["item_title"]               = $_PCAT[$catid]["item_title"];
      $_ITEMS[$xcounter][""]["item_amount_stock"]        = $_PCAT[$catid]["item_amount_stock"];
      $_ITEMS[$xcounter][""]["item_amount_count"]        = $_PCAT[$catid]["item_amount_count"];
      $_ITEMS[$xcounter][""]["item_amount_book"]         = $_PCAT[$catid]["item_amount_book"];
      $_ITEMS[$xcounter][""]["x_avg_netto_total"]        = $_PCAT[$catid]["x_avg_netto_total"];
      $_ITEMS[$xcounter][""]["unit_name"]                = " ";
      $_ITEMS[$xcounter][""]["stc_num"]                  = $_PCAT[$catid]["stc_num"];
      $_ITEMS[$xcounter][""]["date"]                     = $_PCAT[$catid]["date"];
      $_ITEMS[$xcounter][""]["cat_id"]                   = $_PCAT[$catid]["cat_id"];
      $_ITEMS[$xcounter][""]["cat_title"]                = $_PCAT[$catid]["cat_title"];
      
      $_ITEMIDS[$xcounter] = $_PCAT[$catid]["item_number_prod"];
      $xcounter++;
   }
}

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
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Historia de recuentos</b></td>
   <td align="right" class="content_row_clear"></td>
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
         <td class="content_rowl">Modo</td>
         <td class="content_row">
            <table border="0" class="content_table" cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="110">
                  <nobr>
                  <input type="radio" name="sql_stcmode" value="1"
                  <?php if((int)$_SESSION[$_sesmodulename]["sql_stcmode"] == 1) echo "checked"?>> Recuento
                  <input type="text" style="width:80px" id="sql_stcnum" name="sql_stcnum" class="text"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_stcnum"]?>">
                  </nobr>
               </td>
               <td class="content_row_clear" width="110">
                  <nobr>
                  <input type="radio" name="sql_stcmode" value="2"
                  <?php if((int)$_SESSION[$_sesmodulename]["sql_stcmode"] == 2) echo "checked"?>> Dia
                  <input type="text" style="width:80px" id="sql_date" name="sql_date"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date"]?>">
                  </nobr>
               </td>
            </tr>
            </table>
         </td>
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
         <td class="content_rowl">Agrupar por</td>
         <td class="content_row">
            <input type="radio" name="sql_dspmode" value="1"
            <?php if($_SESSION[$_sesmodulename]["sql_dspmode"] == 1) echo "checked"?>> Productos
            <input type="radio" name="sql_dspmode" value="2"
            <?php if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2) echo "checked"?>> Familia
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Stock</td>
         <td class="content_row" colspan="3">
            <input type="radio" name="sql_stockmode" value="0"
            <?php if(!(int)$_SESSION[$_sesmodulename]["sql_stockmode"]) echo "checked"?>> Todos Artículos
            <input type="radio" name="sql_stockmode" value="1"
            <?php if($_SESSION[$_sesmodulename]["sql_stockmode"] == 1) echo "checked"?>> Solo con stock
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="135">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if(count($_ITEMS) > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($_ITEMS) > 0)
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
      <?=Nifty_printH("box1", "1200")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <?php
         if($_SESSION[$_sesmodulename]["sql_dspmode"] != 2)
         {  ?>
            <td class="content_tbl_subheader content_row_os">Unidad</td>
            <td class="content_tbl_subheader content_row_os">Cod/Prov</td>
            <?php
         }
         ?>
         <td class="content_tbl_subheader content_row_os">Recuento</td>
         <td class="content_tbl_subheader content_row_os">Fecha</td>
         <?php
         if($_SESSION[$_sesmodulename]["sql_dspmode"] != 2)
         {  ?>
            <td class="content_tbl_subheader content_row_os" align="center">Bodega</td>
            <td class="content_tbl_subheader content_row_os" align="center">Stock anterior</td>
            <td class="content_tbl_subheader content_row_os" align="center">Ajuste</td>
            <td class="content_tbl_subheader content_row_os" align="center">Stock nuevo</td>
            <td class="content_tbl_subheader content_row_os" align="center">Valor/U</td>
            <?php
         }
         ?>
         <td class="content_tbl_subheader content_row_os" align="center">Valor/Total</td>
         <?php
         if($_SESSION[$_sesmodulename]["sql_dspmode"] != 2)
         {  ?>
            <td class="content_tbl_subheader content_row_os" align="center">Factura</td>
            <td class="content_tbl_subheader content_row_os" align="center">Fecha/Fact.</td>
            <?php
         }
         ?>
         
      </tr>
      <?php
      $x  = 0;
      $cc = 0;
      //----------------------------------------------------------------------------------
      foreach(array_keys($_ITEMIDS) AS $itemid)
      {
         foreach(array_keys($_ITEMS[$itemid]) AS $sthid)
         {
            if($_SESSION[$_sesmodulename]["sql_dspmode"] != 2)
            {
               $stock_new     = $_ITEMS[$itemid][$sthid]["item_amount_stock"] + $_ITEMS[$itemid][$sthid]["item_amount_book"];
               $value_total   = round($stock_new * $_ITEMS[$itemid][$sthid]["item_costprice_avg_netto"],0);
               $ges_total += $value_total;
            }
            else
            {
               $stock_new     = $_ITEMS[$itemid][$sthid]["item_amount_stock"] + $_ITEMS[$itemid][$sthid]["item_amount_book"];
               $value_total   = round($_ITEMS[$itemid][$sthid]["x_avg_netto_total"],0);
               $_ITEMS[$itemid][$sthid]["item_costprice_avg_netto"] = $_ITEMS[$itemid][$sthid]["x_avg_netto_total"] / $stock_new;
               $ges_total += $value_total;
            }

            if(!(int)$_SESSION[$_sesmodulename]["sql_stockmode"] || ( (int)$_SESSION[$_sesmodulename]["sql_stockmode"] && $stock_new > 0.00) )
            {  ?>
               <tr bgcolor="<?=getRowColor($cc)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os"><?=$_ITEMS[$itemid][$sthid]["item_number_prod"]?></td>
                  <td class="content_row_os"><?=$_ITEMS[$itemid][$sthid]["item_title"]?></td>
                  <?php
                  if($_SESSION[$_sesmodulename]["sql_dspmode"] != 2)
                  {  ?>
                     <td class="content_row_os"><?=$_ITEMS[$itemid][$sthid]["unit_name"]?>&nbsp;</td>
                     <td class="content_row_os"><?=$_ITEMS[$itemid][$sthid]["item_code"]?>&nbsp;</td>
                     <?php
                  }
                  ?>
                  <td class="content_row_os"><?=$_ITEMS[$itemid][$sthid]["stc_num"]?>&nbsp;</td>
                  <td class="content_row_os"><?=date('d.m.Y', $_ITEMS[$itemid][$sthid]["date"])?>&nbsp;</td>
                  <?php
                  if($_SESSION[$_sesmodulename]["sql_dspmode"] != 2)
                  {  ?>
                     <td class="content_row_os" align="center"><?=$_ITEMS[$itemid][$sthid]["st_name"]?>&nbsp;</td>
                     <td class="content_row_os" align="center"><?=printPrice($_ITEMS[$itemid][$sthid]["item_amount_stock"],2)?></td>
                     <td class="content_row_os" align="center"><?=printPrice($_ITEMS[$itemid][$sthid]["item_amount_book"],2)?></td>
                     <td class="content_row_os" align="center"><?=printPrice($stock_new,2)?></td>
                     <td class="content_row_os" align="center"><?=printPrice($_ITEMS[$itemid][$sthid]["item_costprice_avg_netto"],2)?></td>
                     <?php
                  }
                  ?>
                  <td class="content_row_os" align="center"><?=printPrice($value_total)?></td>
                  <?php
                  if($_SESSION[$_sesmodulename]["sql_dspmode"] != 2)
                  {  ?>
                     <td class="content_row_os"><?=$_ITEMS[$itemid][$sthid]["item_costprice_docnumber"]?>&nbsp;</td>
                     <td class="content_row_os"><?if((int)$_ITEMS[$itemid][$sthid]["item_costprice_docdate"]) echo date('d.m.Y', $_ITEMS[$itemid][$sthid]["item_costprice_docdate"])?>&nbsp;</td>
                     <?php
                  }
                  ?>
               </tr>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["ubi_name"]                 = $_ITEMS[$itemid][$sthid]["ubi_name"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["item_number_prod"]         = $_ITEMS[$itemid][$sthid]["item_number_prod"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["item_title"]               = $_ITEMS[$itemid][$sthid]["item_title"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["unit_name"]                = $_ITEMS[$itemid][$sthid]["unit_name"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["item_code"]                = $_ITEMS[$itemid][$sthid]["item_code"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["stc_num"]                  = $_ITEMS[$itemid][$sthid]["stc_num"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["stc_annotation"]           = $_ITEMS[$itemid][$sthid]["stc_annotation"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["date"]                     = date('d.m.Y', $_ITEMS[$itemid][$sthid]["date"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["st_name"]                  = $_ITEMS[$itemid][$sthid]["st_name"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["item_amount_stock"]        = printPrice($_ITEMS[$itemid][$sthid]["item_amount_stock"],2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["item_amount_book"]         = printPrice($_ITEMS[$itemid][$sthid]["item_amount_book"],2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["stock_new"]                = printPrice($stock_new,2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["item_costprice_avg_netto"] = printPrice($_ITEMS[$itemid][$sthid]["item_costprice_avg_netto"],2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["value_total"]              = printPrice($value_total);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["item_costprice_docnumber"] = $_ITEMS[$itemid][$sthid]["item_costprice_docnumber"];
               if((int)$_ITEMS[$itemid][$sthid]["item_costprice_docdate"])
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["item_costprice_docdate"] = date('d.m.Y', $_ITEMS[$itemid][$sthid]["item_costprice_docdate"]);
               
               $cc++;
            }
            else
            {
               $ges_total -= $value_total;
            }
         }
         $x++;
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
      else
      {
         if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row_totals" colspan="4">Total</td>
               <td class="content_row_totals" align="center"><?=printPrice($ges_total)?></td>
            </tr>
            <?php
         }
         else
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row_totals" colspan="11">Total</td>
               <td class="content_row_totals" align="center"><?=printPrice($ges_total)?></td>
               <td class="content_row_totals">&nbsp;</td>
               <td class="content_row_totals">&nbsp;</td>
            </tr>
            <?php
         }
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["item_number_prod"] = "TOTAL";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["value_total"]      = printPrice($ges_total);
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
  $pdffile = doc_createStatsItemStockcounts($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsItemStockcounts($CON);

if($pdffile != "")
{
   $doctitle = "Historia-de-recuentos-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Historia-de-recuentos-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>