<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stock_cards";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo" => "2");
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
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_price_calc"]    = (int)$_REQUEST["sql_price_calc"];
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
}

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON, true);
$shops      = getShops($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');
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

$sql_year_init    = (int)date('Y', $sql_datefrom);
$sql_month_init   = (int)date('m', $sql_datefrom);
$sql_day_init     = (int)date('d', $sql_datefrom);
$sql_year_end     = (int)date('Y', $sql_dateto);
$sql_month_end    = (int)date('m', $sql_dateto);
$sql_day_end      = (int)date('d', $sql_dateto);

//----------------------------------------------------------------------------------
$stop = false;
$xsql_date = " and ( ";
for($xmonth = $sql_month_init, $xyear = $sql_year_init; $stop == false; $xmonth++)
{
   if($xmonth == 13)
   {
      $xmonth = 1;
      $xyear++;
   }

   $xsql_date .= " ( t1.tran_month = {$xmonth} and t1.tran_year = {$xyear}  ";
   $xsql_date_arr[$xyear][$xmonth]["INIT"]   = 0;
   $xsql_date_arr[$xyear][$xmonth]["END"]    = 0;

   
   if($xmonth == $sql_month_init && $xyear == $sql_year_init)
   {
      $xsql_date .= " and t1.tran_day >= {$sql_day_init} ";
      $xsql_date_arr[$xyear][$xmonth]["INIT"] = $sql_day_init;
   }
   if($xmonth == $sql_month_end && $xyear == $sql_year_end)
   {
      $xsql_date .= " and t1.tran_day <= {$sql_day_end} ";
      $xsql_date_arr[$xyear][$xmonth]["END"] = $sql_day_end;
   }
   $xsql_date .= " ) or ";


   
   if($xyear > $sql_year_end || ($xyear == $sql_year_end && $xmonth == $sql_month_end))
      $stop = true;
}
$xsql_date = substr($xsql_date, 0, -3);
$xsql_date .= " ) ";

//----------------------------------------------------------------------------------
$joisql = " INNER JOIN item t2               ON t1.item_id = t2.id
            INNER JOIN item_productcats t3   ON t1.item_id = t3.item_id
            LEFT OUTER JOIN item_units t4    ON t2.item_unit = t4.id
            LEFT OUTER JOIN productcats t6   ON t3.cat_id = t6.id ";

$datsql = " select t2.item_number_prod, t2.item_title, t1.item_id, t4.unit_name, t1.tran_amount, t1.tran_type, t3.cat_id, t6.cat_title
            from tran_data t1
            {$joisql}
            where
            t1.tran_st_id > 0
            {$xsql_date} ";

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t1.tran_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t3.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if($_SESSION[$_sesmodulename]["sql_item_id"])
   $seasql .= " and t1.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t1.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
      
//----------------------------------------------------------------------------------
$datsql   .= $seasql;
//----------------------------------------------------------------------------------
$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";

//----------------------------------------------------------------------------------
$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
$_RES[0] = Array();
$_RES[1] = Array();

for($x = 0; $x < count($items) && $items != false; $x++)
{
   $row = $items[$x];
   $idx = (int)$_CONFIGTRANTYPES[$row["tran_type"]];

   $_RES_TOTAL[$idx]                              += $row["tran_amount"];
   $_RES_ITEMS[$row["item_id"]][$idx]             += $row["tran_amount"];
   $_ITEMS[$row["item_id"]]["item_number_prod"]    = $row["item_number_prod"];
   $_ITEMS[$row["item_id"]]["item_title"]          = $row["item_title"];
   $_ITEMS[$row["item_id"]]["unit_name"]           = $row["unit_name"];
}


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
  
printJSsetCompanyShop($shops);

$_SESSION["STATS"][$_sesmodulename]["_ITEMS"]   = $_REQUEST["items"];
$_SESSION["STATS"][$_sesmodulename]["_SQLDATE"] = $xsql_date_arr;

$_SESSION["STATS"][$_sesmodulename]["sql_price_calc_name"] = "FIFO";
if(!(int)$_SESSION[$_sesmodulename]["sql_price_calc"])
   $_SESSION["STATS"][$_sesmodulename]["sql_price_calc_name"] = "PMP";

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
   <td height="30"><b class="content_header">Tarjetas de existencia</b></td>
   <td align="right" class="content_row_clear"></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst"
onsubmit="return checkform(new Array(this.sql_company))">
<input type="hidden" name="subexec" value="search">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="printpdf" value="0">
<input type="hidden" name="printxls" value="0">
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
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
         <td class="content_rowl">Calculo</td>
         <td class="content_row" colspan="3">
            <input type="radio" name="sql_price_calc" value="0"
            <?if(!(int)$_SESSION[$_sesmodulename]["sql_price_calc"]) echo "checked"?>> PMP
            <input type="radio" name="sql_price_calc" value="1"
            <?if((int)$_SESSION[$_sesmodulename]["sql_price_calc"]) echo "checked"?>> FIFO
         </td>
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
               <td align="left">
                  <?php
                  if(count($_ITEMS) > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
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
   </td>
</tr>
<tr>
   <td class="content_row_clear">
      <br>
      <?=Nifty_printH("box1", "980")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="25">
         <col width="75">
         <col>
         <col width="80">
         <col width="100">
         <col width="100">
         <col width="100">
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os" align="center">Impr.</td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_subheader content_row_os">Unidad</td>
         <td class="content_tbl_subheader content_row_os" align="right">Entradas</td>
         <td class="content_tbl_subheader content_row_os" align="right">Salidas</td>
         <td class="content_tbl_subheader content_row_os" align="right">Costo/Ø</td>
      </tr>
      <?php
      $x = 0;
      //----------------------------------------------------------------------------------
      foreach(array_keys($_ITEMS) AS $itemid)
      {
         if(!(int)$_SESSION[$_sesmodulename]["sql_price_calc"])
         {
            $avgcost = getItemAverageCost($CON, $_SESSION[$_sesmodulename]["sql_company"], $itemid);
            if($avgcost == false || (float)$avgcost == 0.00)
               $avgcost = getSupplierFinalCostNetto($CON, 0, $itemid);
         }
         else
         {
            $avgcost = getItemFiFoCost($CON, $_SESSION[$_sesmodulename]["sql_company"], $itemid);
            if($avgcost == false || (float)$avgcost == 0.00)
               $avgcost = getSupplierFinalCostNetto($CON, 0, $itemid);
         }
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os" align="center">
               <input type="checkbox" class="checkbox" name="items[]" value="<?=$itemid?>" checked>
            </td>
            <td class="content_row_os"><a id="itm_<?=$itemid?>" href="index.php?mid=<?=$_REQUEST["mid"]?>&showItem=<?=$itemid?>#itm_<?=$itemid?>" class="link"><?=$_ITEMS[$itemid]["item_number_prod"]?></a></td>
            <td class="content_row_os"><?=$_ITEMS[$itemid]["item_title"]?></td>
            <td class="content_row_os"><?=$_ITEMS[$itemid]["unit_name"]?>&nbsp;</td>
            <td class="content_row_os" align="right"><font color="green"><?=printPrice($_RES_ITEMS[$itemid][1],2)?></font></td>
            <td class="content_row_os" align="right"><font color="red"><?=printPrice($_RES_ITEMS[$itemid][0],2)?></font></td>
            <td class="content_row_os" align="right"><?=printPrice($avgcost,0)?></td>
         </tr>
         <?php
         if($_REQUEST["showItem"] == $itemid)
         {
            $details = doc_createStatsItemStockCard($CON);
            ?>
            <tr>
               <td class="content_row_is" colspan="7">
                  <table border="0" cellpadding="3" cellspacing="0" width="100%">
                  <colgroup>
                     <col width="105">
                     <col width="200">
                     <col width="80">
                     <col width="80">
                     <col width="80">
                     <col>
                     <col width="100">
                     <col width="100">
                     <col width="97">
                  </colgroup>
                  <tr>
                     <td class="content_row_clear content_row_os"><b>Fecha</b></td>
                     <td class="content_row_clear content_row_os"><b>Numero Docto</b></td>
                     <td class="content_row_clear content_row_os"><b>Entrada</b></td>
                     <td class="content_row_clear content_row_os"><b>Salida</b></td>
                     <td class="content_row_clear content_row_os"><b>Saldo</b></td>
                     <td class="content_row_clear content_row_os"><b><nobr>Costo/Unidad/Docto.</nobr></b></td>
                     <td class="content_row_clear content_row_os"><b>Costo/<?=$_SESSION["STATS"][$_sesmodulename]["sql_price_calc_name"]?></b></td>
                     <td class="content_row_clear content_row_os"><b>Total/<?=$_SESSION["STATS"][$_sesmodulename]["sql_price_calc_name"]?></b></td>
                     <td class="content_row_clear content_row_os"><b>Saldo/<?=$_SESSION["STATS"][$_sesmodulename]["sql_price_calc_name"]?></b></td>
                  </tr>
                  <?php
                  $colidx = $_SESSION["STATS"][$_sesmodulename]["sql_price_calc_name"];
                  foreach($details AS $detail)
                  {  ?>
                     <tr>
                        <td class="content_row_os"><?=$detail["FECHA"]?>&nbsp;</td>
                        <td class="content_row_os"><?=$detail["NUMERO DOCTO."]?>&nbsp;</td>
                        <td class="content_row_os"><?=$detail["ENTRADA"]?>&nbsp;</td>
                        <td class="content_row_os"><?=$detail["SALIDA"]?>&nbsp;</td>
                        <td class="content_row_os"><?=$detail["SALDO"]?>&nbsp;</td>
                        <td class="content_row_os"><?=$detail["COSTO/UNIDAD/DOC"]?>&nbsp;</td>
                        <td class="content_row_os"><?=$detail["COSTO/{$colidx}"]?>&nbsp;</td>
                        <td class="content_row_os"><?=$detail["TOTAL/{$colidx}"]?>&nbsp;</td>
                        <td class="content_row_os"><?=$detail["SALDO/{$colidx}"]?>&nbsp;</td>
                     </tr>
                     <?php
                  }
                  ?>
                  </table>
               </td>
            </tr>
            <?php
         }
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]  = $_ITEMS[$itemid]["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]        = $_ITEMS[$itemid]["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitdesc"]          = $_ITEMS[$itemid]["unit_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["plus"]              = printPrice($_RES_ITEMS[$itemid][1],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["minus"]             = printPrice($_RES_ITEMS[$itemid][0],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["total"]             = printPrice($_RES_ITEMS[$itemid][1] - $_RES_ITEMS[$itemid][0],2);
         $x++;
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="6" align="center">
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
            <td class="content_row_totals content_row_os" align="center">
               <input type="checkbox" checked
               onclick="$('.checkbox').attr('checked', $(this).attr('checked'))">
            </td>
            <td class="content_row_totals content_row_os" colspan="3">Total</td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($_RES_TOTAL[1],2)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($_RES_TOTAL[0],2)?></td>
            <td class="content_row_totals content_row_os" align="right">&nbsp;</td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]  = "TOTAL";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]        = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitdesc"]          = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["plus"]              = printPrice($_RES_TOTAL[1],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["minus"]             = printPrice($_RES_TOTAL[0],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["total"]             = printPrice($_RES_TOTAL[1] - $_RES_TOTAL[0],2);
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
   </td>
</tr>
</table>
<?php
$_REQUEST["showItem"] = "";
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsItemStockCard($CON);
  
if($pdffile != "")
{
   $doctitle = "Tarjeta-de-existencia-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Tarjeta-de-existencia-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
</form>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>