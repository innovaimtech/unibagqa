<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2019 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stock_minimumx";
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
   $_SESSION[$_sesmodulename]["sql_xseltyp"]   = (int)$_REQUEST["sql_xseltyp"];
   $_SESSION[$_sesmodulename]["sql_xselres"]   = (int)$_REQUEST["sql_xselres"];
   $_SESSION[$_sesmodulename]["sql_xitemtype"]     = (int)$_REQUEST["sql_xitemtype"];
   $_SESSION[$_sesmodulename]["sql_xhasstock"]     = (int)$_REQUEST["sql_xhasstock"];
   $_SESSION[$_sesmodulename]["sql_equvals"]    = $_REQUEST["sql_equvals"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;

   unset($_SESSION[$_sesmodulename]["sql_comvals"]);
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "sql_comvals_") !== false && strpos($reqkey, "sql_comvals_") == 0)
      {
         $compid = substr($reqkey, strrpos($reqkey, "_") +1);

         foreach($_REQUEST[$reqkey] AS $compvalid)
            $_SESSION[$_sesmodulename]["sql_comvals"][$compid][(int)$compvalid] = 1;
      }
   }
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
if((int)$_SESSION[$_sesmodulename]["sql_shop"] == 30010 && !(int)$_SESSION[$_sesmodulename]["sql_storehouse"])
   $_SESSION[$_sesmodulename]["sql_storehouse"] = $_CONFIG["_REPORTS_DEFAULT_STHID"];

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
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
                LEFT OUTER JOIN item_shops_storehouses     t10 ON ( t2.item_id = t10.item_id and t2.shop_id = t10.shop_id )
                LEFT OUTER JOIN company_shops_storehouses  t11 ON ( t11.id = t10.st_id and t2.shop_id = t11.st_shop_id and t11.st_status > 0 ) ";

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
   if($_SESSION[$_sesmodulename]["sql_storehouse"])
      $seasql .= " and t10.st_id = {$_SESSION[$_sesmodulename]["sql_storehouse"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
      $seasql .= " and t1.item_ventaonline_act = 1 ";
   elseif((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
      $seasql .= " and t1.item_ventaonline_act = 0 ";

   if((int)$_SESSION[$_sesmodulename]["sql_xhasstock"] == 1)
      $seasql .= " and t10.iss_inventory > 0 ";
   elseif((int)$_SESSION[$_sesmodulename]["sql_xhasstock"] == 2)
      $seasql .= " and t10.iss_inventory <= 0 ";

   //----------------------------------------------------------------------------------
   $sql_filter_equval = "";
   foreach($_SESSION[$_sesmodulename]["sql_equvals"] AS $sql_equval)
      $sql_filter_equval .= "{$sql_equval},";
   $sql_filter_equval = substr($sql_filter_equval, 0, -1);
   if($sql_filter_equval != "")
   {
      $seasql .= " and
                   (
                      select count(*) 'cc'
                      from item_equipos_rel txx10
                      where
                      txx10.item_id    = t1.id and
                      txx10.equipo_id  IN ({$sql_filter_equval})
                   ) > 0 ";
   }

   //----------------------------------------------------------------------------------
   $sql_filter_comval = "";
   foreach(array_keys($_SESSION[$_sesmodulename]["sql_comvals"]) AS $sql_comid)
   {
      $sql_subfilter_comvalids = implode(",", array_keys($_SESSION[$_sesmodulename]["sql_comvals"][$sql_comid]));
      $sql_filter_comval .= " and
                              (
                                 select count(*) 'cc'
                                 from tran_comments_item_vals txx11
                                 where
                                 txx11.item_id = t1.id and
                                 txx11.val_id  IN ({$sql_subfilter_comvalids})
                              ) > 0 ";
   }
   if($sql_filter_comval != "")
      $seasql .= $sql_filter_comval;

   if((int)$_SESSION[$_sesmodulename]["sql_xselres"] == 1)
   {
      if((int)$_SESSION[$_sesmodulename]["sql_xseltyp"] == 0)
         $seasql .= " and t10.iss_inventory <= t10.iss_inventory_min ";
      else
         $seasql .= " and t10.iss_inventory <= t10.iss_order_amount ";
   }

   //----------------------------------------------------------------------------------
   $cntsql   .= $seasql;
   $datsql   .= $seasql;

   $itemcount = $CON->select($cntsql);
   $itemcount = (int)$itemcount[0]["cc"] + (int)$itemcount[1]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];


   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";

   //----------------------------------------------------------------------------------
   $items = $CON->select($datsql);
}

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
$sql = " select *
         from company_shops_storehouses
         where
         st_status  = 1
         order by st_name";
$storehouses = $CON->select($sql);
   
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
   <td height="30"><b class="content_header">Stock critico</b></td>
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
            onchange="unibLoadSpecCharFilters(this.value)"
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
      <tr id="idx_charact_opts" style="<?if(!(int)$_SESSION[$_sesmodulename]["sql_pcat"]) echo "display:none"?>">
         <td class="content_rowl" valign="top">Caracteristicas</td>
         <td class="content_row" colspan="4">
            <div id="idx_charact_jqres">
               <?php
               printPcatFilters($CON, $_SESSION[$_sesmodulename]["sql_pcat"], $_sesmodulename)
               ?>
            </div>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo</td>
         <td class="content_row">
            <input type="radio" value="0" name="sql_xseltyp" <?php if((int)$_SESSION[$_sesmodulename]["sql_xseltyp"] == 0) echo "checked"?>> Minimo Bodega
            <input type="radio" value="1" name="sql_xseltyp" <?php if((int)$_SESSION[$_sesmodulename]["sql_xseltyp"] == 1) echo "checked"?>> Minimo Pedido
         </td>
         <td class="content_rowl">Resultados</td>
         <td class="content_row">
            <input type="radio" value="0" name="sql_xselres" <?php if((int)$_SESSION[$_sesmodulename]["sql_xselres"] == 0) echo "checked"?>> Todos
            <input type="radio" value="1" name="sql_xselres" <?php if((int)$_SESSION[$_sesmodulename]["sql_xselres"] == 1) echo "checked"?>> Solo critico
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo producto</td>
         <td class="content_row">
            <input type="radio" name="sql_xitemtype" value="0" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 0) echo "checked"?>> Todos
            <input type="radio" name="sql_xitemtype" value="1" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 1) echo "checked"?>> Solo venta online
            <input type="radio" name="sql_xitemtype" value="2" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 2) echo "checked"?>> Solo otros
         </td>
         <td class="content_rowl">Stock</td>
         <td class="content_row">
            <input type="radio" name="sql_xhasstock" value="0" <?php if($_SESSION[$_sesmodulename]["sql_xhasstock"] == 0) echo "checked"?>> Todos
            <input type="radio" name="sql_xhasstock" value="1" <?php if($_SESSION[$_sesmodulename]["sql_xhasstock"] == 1) echo "checked"?>> Solo con stock
            <input type="radio" name="sql_xhasstock" value="2" <?php if($_SESSION[$_sesmodulename]["sql_xhasstock"] == 2) echo "checked"?>> Solo sin stock
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
         <col width="100">
         <col>
         <col>
         <col>
         <?php
         foreach($selstorehouses AS $storehouse)
         {  ?>
            <col width="40">
            <col width="40">
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
            <td class="content_tbl_subheader content_row_os" colspan="2" align="center" style="border-right:3px double black"><nobr><b><?=$storehouse["st_name"]?></b></nobr></td>
            <?php
         }
         ?>
      </tr>
      <tr>
         <?php
         foreach($selstorehouses AS $storehouse)
         {  ?>
            <td class="content_tbl_subheader content_row_os" align="center" valign="top"><nobr>Actual</nobr></td>
            <td class="content_tbl_subheader content_row_os" align="center" valign="top" style="border-right:3px double black"><nobr>Min.</nobr></td>
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

               $curr = getItemShopStorehouseCurrentStock($CON, $_SESSION[$_sesmodulename]["sql_shop"], $storehouse["id"],  $items[$x]["id"],  "item", true);

               if((int)$_SESSION[$_sesmodulename]["sql_xseltyp"] == 0)
                  $min = getItemShopStorehouseMinStock($CON, $_SESSION[$_sesmodulename]["sql_shop"], $storehouse["id"],  $items[$x]["id"],  "item", true);
               else
                  $min = getItemShopStorehouseMinStock($CON, $_SESSION[$_sesmodulename]["sql_shop"], $storehouse["id"],  $items[$x]["id"],  "item", true, true);

               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["BODEGA"][$storehouse["st_name"]]["curr"]    = $curr;
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["BODEGA"][$storehouse["st_name"]]["min"]     = $min;
               
               ?>
               <td class="content_row_os" align="center" <?php if($curr < $min) echo "style='color:red'"?>><?=printPrice($curr,2)?></td>
               <td class="content_row_os" align="center" style="border-right:3px double black;<?php if($curr < $min) echo "color:red"?>"><?=printPrice($min,2)?></td>
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
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="30" align="center">
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
  $pdffile = doc_createStatsItemStoreHousesCritics($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsItemStoreHousesCritics($CON);
  
if($pdffile != "")
{
   $doctitle = "Stock-critico-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Stock-critico-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>