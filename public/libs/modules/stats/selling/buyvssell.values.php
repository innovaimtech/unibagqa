<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "buyvssellmargin";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Numero" => "2", "Artículo" => "3", "Unidad" => "4");
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
   $_SESSION[$_sesmodulename]["sql_supplier"]      = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_item_id"]       = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"]     = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]          = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_financedsc"]    = (int)$_REQUEST["sql_financedsc"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON);
$shops      = getShops($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
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
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');

   for($x = 0; $x < 2; $x++)
   {
      $_SESSION[$_sesmodulename]["sql_month1"]--;
      if($_SESSION[$_sesmodulename]["sql_month1"] == 0)
      {
         $_SESSION[$_sesmodulename]["sql_month1"] = 12;
         $_SESSION[$_sesmodulename]["sql_year1"]--;
      }
   }
   
   $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y');
}
if($_SESSION[$_sesmodulename]["sql_date"] == "")
   $_SESSION[$_sesmodulename]["sql_date"] = date('d.m.Y');
if($_SESSION[$_sesmodulename]["sql_date_pfrom"] == "")
{
   $_SESSION[$_sesmodulename]["sql_date_pfrom"] = date('d.m.Y', time() - (86400 * 7));
   $_SESSION[$_sesmodulename]["sql_date_pto"]   = date('d.m.Y');
}

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_selmode"] == 1)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 2)
{
   $sql_datefrom  = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], 1, $_SESSION[$_sesmodulename]["sql_year2"]);
   $datedays      = date('t', $sql_dateto);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], $datedays, $_SESSION[$_sesmodulename]["sql_year2"]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 3)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pfrom"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pto"]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_pcat"])
{
   $seasql1 .= " and t3.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
   $seasql2 .= " and t3.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
}
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
{
   $seasql1 .= " and t4.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
   $seasql2 .= " and t4.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
}
if($_SESSION[$_sesmodulename]["sql_item_id"])
{
   if($_SESSION[$_sesmodulename]["sql_item_type"] == "item")
   {
      $seasql1 .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
      $seasql2 .= " and 1 = 2 ";
   }
   else
   {
      $seasql1 .= " and 1 = 2 ";
      $seasql2 .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
   }
}

//----------------------------------------------------------------------------------
for($x = $sql_datefrom; $x <= $sql_dateto; $x += 20000)
{
   $idx = date('m-Y', $x);
   $_MONTHS[$idx] = 1;
}

//------------------------.----------------------------------------------------------
$sql = " select t1.invc_date, t2.item_id, t2.item_type, SUM(t2.item_amount) 'item_amount', SUM(t2.item_sellprice_netto_dsc) 'item_value'
         from invoices_sell t1
         INNER JOIN invoices_sell_parts_items t2 ON t1.id = t2.invc_id
         where
         t1.invc_status > 1 and
         t1.invc_status < 4 and
         t1.invc_date between {$sql_datefrom} and {$sql_dateto} and
         t2.item_type IN ('item','itemlist') 
         group by 1,2,3";
$sellings = $CON->select($sql);

foreach($sellings AS $selling)
{
   $idx = date('m-Y', $selling["invc_date"]);
   $_SELLING[$idx][$selling["item_type"]][$selling["item_id"]]["VAL"] += $selling["item_value"];
   $_SELLING[$idx][$selling["item_type"]][$selling["item_id"]]["AMT"] += $selling["item_amount"];
   $_SELLING[$idx][$selling["item_type"]][$selling["item_id"]]["PRC"]  = $_SELLING[$idx][$selling["item_type"]][$selling["item_id"]]["VAL"] / $_SELLING[$idx][$selling["item_type"]][$selling["item_id"]]["AMT"];
}

//------------------------.----------------------------------------------------------
$sql = " select t1.invc_date, t2.item_id, t2.item_type, SUM(t2.item_amount) 'item_amount', SUM(t2.item_sellprice_netto_dsc) 'item_value'
         from invoices_sell_bol t1
         INNER JOIN invoices_sell_bol_parts_items t2 ON t1.id = t2.invc_id
         where
         t1.invc_status > 1 and
         t1.invc_status < 4 and
         t1.invc_date between {$sql_datefrom} and {$sql_dateto} and
         t2.item_type IN ('item','itemlist') 
         group by 1,2,3";
$sellings = $CON->select($sql);

foreach($sellings AS $selling)
{
   $idx = date('m-Y', $selling["invc_date"]);
   $_SELLING[$idx][$selling["item_type"]][$selling["item_id"]]["VAL"] += $selling["item_value"];
   $_SELLING[$idx][$selling["item_type"]][$selling["item_id"]]["AMT"] += $selling["item_amount"];
   $_SELLING[$idx][$selling["item_type"]][$selling["item_id"]]["PRC"]  = $_SELLING[$idx][$selling["item_type"]][$selling["item_id"]]["VAL"] / $_SELLING[$idx][$selling["item_type"]][$selling["item_id"]]["AMT"];
}

//----------------------------------------------------------------------------------
$sql = " select distinct t1.id, t1.item_number_prod, t1.item_title, t2.unit_name, 'type' 'item'
         from item t1
         LEFT OUTER JOIN item_units t2          ON t1.item_unit = t2.id
         LEFT OUTER JOIN item_productcats t3    ON t1.id = t3.item_id
         LEFT OUTER JOIN item_suppliers t4      ON t1.id = t4.item_id 
         where
         t1.item_status = 1
         {$seasql1}
         UNION ALL
         select distinct t1.id, t1.item_number_prod, t1.item_title, t2.unit_name, 'type' 'itemlist'
         from itemlist t1
         LEFT OUTER JOIN item_units t2                   ON t1.item_unit = t2.id
         LEFT OUTER JOIN item_productcats_itemlist t3    ON t1.id = t3.item_id
         LEFT OUTER JOIN itemlist_suppliers t4           ON t1.id = t4.item_id
         where
         t1.item_status = 1
         {$seasql2}
         order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}";
$items = $CON->select($sql);

for($x = 0; $x < count($items) && $items != false; $x++)
{
   if($items[$x]["type"] == "typeitem")
      $items[$x]["item_type"] = "item";
   else
      $items[$x]["item_type"] = "itemlist";

   $custock = getItemShopCurrentStock($CON, $_SESSION[$_sesmodulename]["sql_shop"], $items[$x]["id"], $items[$x]["item_type"], true);
   $items[$x]["stock"] = $custock;

   $buyprc = getSupplierFinalCostNetto($CON, (int)$_SESSION[$_sesmodulename]["sql_supplier"], $items[$x]["id"], $items[$x]["item_type"], 0.00, $_SESSION[$_sesmodulename]["sql_financedsc"]);
   $items[$x]["buyprc"] = $buyprc;
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
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
{
   $sql = " select supp_short
            from supplier
            where
            id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
   $custdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"] = $custdata[0]["cust_name"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"] = "TODO";

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

$_SESSION["STATS"][$_sesmodulename]["DATA2"]["TIME"] = $_MONTHS;

printJSsetCompanyShop($shops);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Compra/Venta/Precios por mes</b></td>
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
         <td class="content_rowl">Periodo</td>
         <td class="content_row">
            <table border="0" class="content_table" cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="70">
                  <input type="radio" name="sql_selmode" value="2"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 2) echo "checked"?>> Meses
               </td>
               <td class="content_row_clear" width="205" id="idx_selmode2" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 2) echo "style='display:none'"?>>
                  <nobr>
                  <select class="text" name="sql_month1" id="sql_month1"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     for($x = 1; $x <= 12; $x++)
                     {
                        $dsp_month = $x;
                        if($dsp_month < 10)
                           $dsp_month = "0{$dsp_month}";
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_month1"]) echo "selected" ?>><?=$dsp_month?></option>
                        <?php
                     }
                     ?>
                  </select>
                  <select class="text" name="sql_year1" id="sql_year1"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     $startyear  = date('Y') -3;
                     $endyear    = date('Y');

                     for($x = $startyear; $x <= $endyear; $x++)
                     {
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_year1"]) echo "selected" ?>><?=$x?></option>
                        <?php
                     }
                     ?>
                  </select>
                  &nbsp;-&nbsp;
                  <select class="text" name="sql_month2" id="sql_month2"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     for($x = 1; $x <= 12; $x++)
                     {
                        $dsp_month = $x;
                        if($dsp_month < 10)
                           $dsp_month = "0{$dsp_month}";
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_month2"]) echo "selected" ?>><?=$dsp_month?></option>
                        <?php
                     }
                     ?>
                  </select>
                  <select class="text" name="sql_year2" id="sql_year2"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     $startyear  = date('Y') -3;
                     $endyear    = date('Y');

                     for($x = $startyear; $x <= $endyear; $x++)
                     {
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_year2"]) echo "selected" ?>><?=$x?></option>
                        <?php
                     }
                     ?>
                  </select>
                  </nobr>
               </td>
               <td class="content_row_clear" width="50">
                  <input type="radio" name="sql_selmode" value="1"
                  onclick="document.getElementById('idx_selmode1').style.display='';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 1) echo "checked"?>> Dia
               </td>
               <td class="content_row_clear" width="110" id="idx_selmode1" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 1) echo "style='display:none'"?>>
                  <nobr>
                  <input type="text" style="width:80px" id="sql_date" name="sql_date" 
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date"]?>">
                  </nobr>
               </td>
               <td class="content_row_clear" width="75">
                  <input type="radio" name="sql_selmode" value="3"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 3) echo "checked"?>> Periodo
               </td>
               <td class="content_row_clear" width="180" id="idx_selmode3" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 3) echo "style='display:none'"?>>
                  <nobr>
                  <input type="text" style="width:65px" id="sql_date_pfrom" name="sql_date_pfrom"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pfrom"]?>">
                  -
                  <input type="text" style="width:65px" id="sql_date_pto" name="sql_date_pto"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pto"]?>">
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
         <td class="content_rowl">Proveedor</td>
         <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
      </tr>
      <tr style="display:none">
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
               <col width="132">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if(count($items) > 0 && $items != false)
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">&nbsp;</td>              
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
      <?=Nifty_printH("box1", "")?>
      <table border="0" cellpadding="3" cellspacing="0">
      <colgroup>
         <col width="70">
         <col width="290">
         <col width="75">
         <col width="55">
         <col width="55">
         <?php
         foreach(array_keys($_MONTHS) AS $idx)
         {  ?>
            <col width="30">
            <col width="30">
            <?php
         }
         ?>
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os" valign="top" rowspan="2"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" rowspan="2" style="min-width:290px"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" rowspan="2" style="border-right:3px double black"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" rowspan="2" align="right">Stock</td>
         <td class="content_tbl_subheader content_row_os" valign="top" rowspan="2" align="right" style="border-right:3px double black">Precio<br>Compra<br>Final</td>
         <?php
         foreach(array_keys($_MONTHS) AS $idx)
         {  ?>
            <td class="content_tbl_subheader content_row_os" valign="top" align="center" colspan="4" style="border-right:3px double black"><?=$idx?></td>
            <?php
         }
         ?>
      </tr>
      <tr>
         <?php
         foreach(array_keys($_MONTHS) AS $idx)
         {  ?>
            <td class="content_tbl_subheader content_row_os" valign="top" align="center">Cant<br>Venta</td>
            <td class="content_tbl_subheader content_row_os" valign="top" align="center">Monto<br>Venta</td>
            <td class="content_tbl_subheader content_row_os" valign="top" align="center">%<br>Margen</td>
            <td class="content_tbl_subheader content_row_os" valign="top" align="center" style="border-right:3px double black">$<br>Margen</td>
            <?php
         }
         ?>
      </tr>
      <?php
      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         $row = $items[$x];
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os" valign="top"><?=$row["item_number_prod"]?></td>
            <td class="content_row_os" valign="top"><?=$row["item_title"]?></td>
            <td class="content_row_os" valign="top" style="border-right:3px double black"><?=$row["unit_name"]?></td>
            <td class="content_row_os" valign="top" align="right"><?=printPrice($row["stock"],2)?></td>
            <td class="content_row_os" valign="top" style="border-right:3px double black" align="right"><?=printPrice($row["buyprc"],2)?></td>
            <?php
            foreach(array_keys($_MONTHS) AS $idx)
            {
               $buy_value  = $row["buyprc"] * $_SELLING[$idx][$row["item_type"]][$row["id"]]["AMT"];
               $sell_value = $_SELLING[$idx][$row["item_type"]][$row["id"]]["VAL"];
               $gain_value = $sell_value - $buy_value;
               $gain_perc  = $gain_value / $buy_value * 100;
               ?>
               <td class="content_row_os" valign="top" align="center"><?=printPrice($_SELLING[$idx][$row["item_type"]][$row["id"]]["AMT"],0,true)?></td>
               <td class="content_row_os" valign="top" align="center"><?=printPrice($_SELLING[$idx][$row["item_type"]][$row["id"]]["VAL"],0,true)?></td>
               <td class="content_row_os" valign="top" align="center"><?=printPrice($gain_perc,2,true)?></td>
               <td class="content_row_os" valign="top" align="center" style="border-right:3px double black"><?=printPrice($gain_value,0,true)?></td>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TIME"][$idx][0] = printPrice($_SELLING[$idx][$row["item_type"]][$row["id"]]["AMT"],0);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TIME"][$idx][1] = printPrice($_SELLING[$idx][$row["item_type"]][$row["id"]]["VAL"],0);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TIME"][$idx][2] = printPrice($gain_perc,2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TIME"][$idx][3] = printPrice($gain_value,0);
            }
            ?>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]  = $row["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]        = $row["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitdesc"]          = $row["unit_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["stock"]             = printPrice($row["stock"],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["buyprc"]            = printPrice($row["buyprc"],2);
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
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsBuyVsSellValues($CON);
  
if($xlsfile != "")
{
   $doctitle = "Compras-Ventas-Margen-Por-Mes-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php
$_SESSION["JSEXEC"] .= ";$('#obitpanel').html('');";
?>