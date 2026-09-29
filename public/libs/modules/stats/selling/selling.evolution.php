<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_sell_evolution";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array();
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);
$sellers = getSellers($CON);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);



   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_seller"]        = (int)$_REQUEST["sql_seller"];
   $_SESSION[$_sesmodulename]["sql_yearcount"]     = (int)$_REQUEST["sql_yearcount"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_valtype"]       = trim($_REQUEST["sql_valtype"]);
   $_SESSION[$_sesmodulename]["sql_trantype"]      = $_REQUEST["sql_trantype"];

   $_SESSION[$_sesmodulename]["sql_mode"]          = $_REQUEST["sql_mode"];
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;


}
if((int)$_SESSION[$_sesmodulename]["sql_mode"])
{
   $_SESSION[$_sesmodulename]["sql_month2"] = $_SESSION[$_sesmodulename]["sql_month1"];
   $_SESSION[$_sesmodulename]["sql_year2"]  = $_SESSION[$_sesmodulename]["sql_year1"];
}

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON);
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

if($_SESSION[$_sesmodulename]["sql_valtype"] == "")
   $_SESSION[$_sesmodulename]["sql_valtype"] = "invc_total_netto";
if(!(int)$_SESSION[$_sesmodulename]["sql_yearcount"])
   $_SESSION[$_sesmodulename]["sql_yearcount"] = 4;
if(!is_array($_SESSION[$_sesmodulename]["sql_trantype"]))
   $_SESSION[$_sesmodulename]["sql_trantype"] = Array(0=>0,1=>1,2=>2);

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
      $_SESSION[$_sesmodulename]["sql_month1"]  = 1;
      $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');
      $_SESSION[$_sesmodulename]["sql_month2"]  = 12;
      $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y');
}

//----------------------------------------------------------------------------------
$sql_datefrom  = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
$sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], 1, $_SESSION[$_sesmodulename]["sql_year2"]);
$datedays      = date('t', $sql_dateto);
$sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], $datedays, $_SESSION[$_sesmodulename]["sql_year2"]);
$exdatefrom    = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"] - $_SESSION[$_sesmodulename]["sql_yearcount"]);

$days_ini  = date("d", mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]));
$days_end  = date("t", $exdatefrom);

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
if(array_search(0, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false)
{
   $datsql = " select t1.invc_date, SUM(t1.invc_total_netto) 'invc_total_netto',
                      SUM(t1.invc_total_taxes) 'invc_total_taxes', SUM(t1.invc_total_taxes_exclude) 'invc_total_taxes_exclude',
                      SUM(t1.invc_total_brutto) 'invc_total_brutto', COUNT(t1.id) 'invc_count'
               from invoices_sell t1
               INNER JOIN company_data t4    ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
               LEFT OUTER JOIN customer t6   ON ( t1.invc_cust_id = t6.id )
               LEFT OUTER JOIN user t2 ON ( t1.invc_userid_seller = t2.id )
               where
               t1.invc_status       > 1 and
               t1.invc_status       < 4 and
               t1.invc_date between {$exdatefrom} and {$sql_dateto} ";
   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
      $datsql .= " and t1.invc_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_seller"])
      $datsql .= " and t1.invc_userid_seller  = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
   $datsql .= " group by 1 ";
}

if(array_search(0, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false && array_search(1, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false)
   $datsql .= " UNION ALL ";

if(array_search(1, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false)
{
   $datsql .= " select t1.invc_date, SUM(t1.invc_total_netto) 'invc_total_netto',
                     SUM(t1.invc_total_taxes) 'invc_total_taxes', SUM(t1.invc_total_taxes_exclude) 'invc_total_taxes_exclude',
                     SUM(t1.invc_total_brutto) 'invc_total_brutto', COUNT(t1.id) 'invc_count'
               from invoices_sell_bol t1
               INNER JOIN company_data t4    ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
               LEFT OUTER JOIN user t2 ON ( t1.invc_userid_seller = t2.id )
               where
               t1.invc_status       > 1 and
               t1.invc_status       < 4 and
               t1.invc_date between {$exdatefrom} and {$sql_dateto} ";
   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
      $datsql .= " and t1.invc_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_seller"])
      $datsql .= " and t1.invc_userid_seller  = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
   $datsql .= " group by 1 ";
}
$datsql .= " order by 1 asc";
$items = $CON->select($datsql);

$init_month = $_SESSION[$_sesmodulename]["sql_month1"];
$end_month  = $_SESSION[$_sesmodulename]["sql_month2"];
$end_year   = $_SESSION[$_sesmodulename]["sql_year1"];
$init_year  = $end_year - $_SESSION[$_sesmodulename]["sql_yearcount"];


if(!(int)$_SESSION[$_sesmodulename]["sql_mode"])     
{
   for($x = 0; $x < count($items) && $items != false; $x++)
   {
      $row = $items[$x];
      $idx = date('m-Y', $row["invc_date"]);
      $yea = date('Y', $row["invc_date"]);

      $_RES[$idx]["invc_total_netto"]  += round($row["invc_total_netto"],0);
      $_RES[$idx]["invc_total_taxes"]  += round($row["invc_total_taxes"],0);
      $_RES[$idx]["invc_total_brutto"] += round($row["invc_total_brutto"],0);
      $_RES[$idx]["invc_count"]        += $row["invc_count"];

      if((int)date('m', $row["invc_date"]) >= $init_month && (int)date('m', $row["invc_date"]) <= $end_month)
      {
         $_TOTAL[$yea]["invc_total_netto"]   += round($row["invc_total_netto"],0);
         $_TOTAL[$yea]["invc_total_taxes"]   += round($row["invc_total_taxes"],0);
         $_TOTAL[$yea]["invc_total_brutto"]  += round($row["invc_total_brutto"],0);
         $_TOTAL[$yea]["invc_count"]         += $row["invc_count"];
      }
   }
}
else
{
   for($x = 0; $x < count($items) && $items != false; $x++)
   {
      $row   = $items[$x];
      $idx   = date('d-Y', $row["invc_date"]);
      $month = date('m', $row["invc_date"]);
      $yea   = date('Y', $row["invc_date"]);

      if($month == $init_month)
      {
         $_RES[$idx]["invc_total_netto"]  += round($row["invc_total_netto"],0);
         $_RES[$idx]["invc_total_taxes"]  += round($row["invc_total_taxes"],0);
         $_RES[$idx]["invc_total_brutto"] += round($row["invc_total_brutto"],0);
         $_RES[$idx]["invc_count"]        += $row["invc_count"];

         $_TOTAL[$yea]["invc_total_netto"]   += round($row["invc_total_netto"],0);
         $_TOTAL[$yea]["invc_total_taxes"]   += round($row["invc_total_taxes"],0);
         $_TOTAL[$yea]["invc_total_brutto"]  += round($row["invc_total_brutto"],0);
         $_TOTAL[$yea]["invc_count"]         += $row["invc_count"];
      }
   }

}

//----------------------------------------------------------------------------------
if(array_search(2, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false)
{
   $datsql = " select t1.note_date, t1.note_type, SUM(t1.note_total_netto) 'invc_total_netto',
                      SUM(t1.note_total_taxes) 'invc_total_taxes', SUM(t1.note_total_taxes_exclude) 'invc_total_taxes_exclude',
                      SUM(t1.note_total_brutto) 'invc_total_brutto'
               from invoices_notes_sell t1
               INNER JOIN company_data t4    ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
               LEFT OUTER JOIN customer t6   ON ( t1.note_cust_id = t6.id )
               where
               t1.note_status       > 1 and
               t1.note_status       < 4 and
               t1.note_date between {$exdatefrom} and {$sql_dateto}";
   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $datsql .= " and t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $datsql .= " and t1.note_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
      $datsql .= " and t1.note_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_seller"])
      $datsql .= " and t1.note_userid_seller  = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
   $datsql .= " group by 1,2";
   $noteitems = $CON->select($datsql);
}

if(!(int)$_SESSION[$_sesmodulename]["sql_mode"])     
{
   for($x = 0; $x < count($noteitems) && $noteitems != false; $x++)
   {
      $row = $noteitems[$x];
      $idx = date('m-Y', $row["note_date"]);
      $yea = date('Y', $row["note_date"]);

      if($row["note_type"] == 1)
      {
         $_RES[$idx]["invc_total_netto"]     -= round($row["invc_total_netto"],0);
         $_RES[$idx]["invc_total_taxes"]     -= round($row["invc_total_taxes"],0);
         $_RES[$idx]["invc_total_brutto"]    -= round($row["invc_total_brutto"],0);
         if((int)date('m', $row["note_date"]) >= $init_month && (int)date('m', $row["note_date"]) <= $end_month)
         {
            $_TOTAL[$yea]["invc_total_netto"]   -= round($row["invc_total_netto"],0);
            $_TOTAL[$yea]["invc_total_taxes"]   -= round($row["invc_total_taxes"],0);
            $_TOTAL[$yea]["invc_total_brutto"]  -= round($row["invc_total_brutto"],0);
         }
      }
      else
      {
         $_RES[$idx]["invc_total_netto"]     += round($row["invc_total_netto"],0);
         $_RES[$idx]["invc_total_taxes"]     += round($row["invc_total_taxes"],0);
         $_RES[$idx]["invc_total_brutto"]    += round($row["invc_total_brutto"],0);
         if((int)date('m', $row["note_date"]) >= $init_month && (int)date('m', $row["note_date"]) <= $end_month)
         {
            $_TOTAL[$yea]["invc_total_netto"]   += round($row["invc_total_netto"],0);
            $_TOTAL[$yea]["invc_total_taxes"]   += round($row["invc_total_taxes"],0);
            $_TOTAL[$yea]["invc_total_brutto"]  += round($row["invc_total_brutto"],0);
         }
      }
   }
}
else
{
   for($x = 0; $x < count($noteitems) && $noteitems != false; $x++)
   {
      $row = $noteitems[$x];

      $idx   = date('d-Y', $row["note_date"]);
      $month = date('m', $row["note_date"]);
      $yea   = date('Y', $row["note_date"]);

      if($month == $init_month)
      {
         if($row["note_type"] == 1)
         {
            $_RES[$idx]["invc_total_netto"]     -= round($row["invc_total_netto"],0);
            $_RES[$idx]["invc_total_taxes"]     -= round($row["invc_total_taxes"],0);
            $_RES[$idx]["invc_total_brutto"]    -= round($row["invc_total_brutto"],0);
            if((int)date('m', $row["note_date"]) >= $init_month && (int)date('m', $row["note_date"]) <= $end_month)
            {
               $_TOTAL[$yea]["invc_total_netto"]   -= round($row["invc_total_netto"],0);
               $_TOTAL[$yea]["invc_total_taxes"]   -= round($row["invc_total_taxes"],0);
               $_TOTAL[$yea]["invc_total_brutto"]  -= round($row["invc_total_brutto"],0);
            }
         }
         else
         {
            $_RES[$idx]["invc_total_netto"]     += round($row["invc_total_netto"],0);
            $_RES[$idx]["invc_total_taxes"]     += round($row["invc_total_taxes"],0);
            $_RES[$idx]["invc_total_brutto"]    += round($row["invc_total_brutto"],0);
            if((int)date('m', $row["note_date"]) >= $init_month && (int)date('m', $row["note_date"]) <= $end_month)
            {
               $_TOTAL[$yea]["invc_total_netto"]   += round($row["invc_total_netto"],0);
               $_TOTAL[$yea]["invc_total_taxes"]   += round($row["invc_total_taxes"],0);
               $_TOTAL[$yea]["invc_total_brutto"]  += round($row["invc_total_brutto"],0);
            }
         }
      }
   }
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
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
{
   $sql = " select cust_name
            from customer
            where
            id = {$_SESSION[$_sesmodulename]["sql_customer"]}";
   $custdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = $custdata[0]["cust_name"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = "TODO";


printJSsetCompanyShop($shops);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Comparación Anual</b></td>
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
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Vendedor</td>
         <td class="content_row">
            <select name="sql_seller" id="sql_seller" class="text" style="width:375px">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($sellers AS $seller)
               {  ?>
                  <option value="<?=$seller["id"]?>" <?php if($seller["id"] == $_SESSION[$_sesmodulename]["sql_seller"]) echo "selected"; ?>>
                  <?=$seller["user_firstname"]?> <?=$seller["user_lastname"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Periodo</td>
         <td class="content_row">
            <table border="0" class="content_table" cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="205" id="idx_selmode2">
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
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)"
                  onchange="document.getElementById('sql_year2').value=this.value">
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
                  <span style="<?if((int)$_SESSION[$_sesmodulename]["sql_mode"]) echo "display:none"?>">
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
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)"
                  onchange="document.getElementById('sql_year1').value=this.value">
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
                  </span>
               </td>
            </tr>
            </table>
         </td>
         <td class="content_rowl">Comparar</td>
         <td class="content_row">
            <select class="text" name="sql_yearcount" id="sql_yearcount"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <?php
               for($x = 1; $x <= 20; $x++)
               {
                  ?>
                  <option value="<?=$x?>"
                  <?php if($x == $_SESSION[$_sesmodulename]["sql_yearcount"]) echo "selected" ?>><?=$x?></option>
                  <?php
               }
               ?>
            </select> Años anteriores
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo</td>
         <td class="content_row">
            <input type="checkbox" name="sql_trantype[]" value="0" <?php if(array_search(0, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false) echo "checked"?>>Facturas
            <input type="checkbox" name="sql_trantype[]" value="1" <?php if(array_search(1, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false) echo "checked"?>>Boletas
            <input type="checkbox" name="sql_trantype[]" value="2" <?php if(array_search(2, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false) echo "checked"?>>Notas de credito/debito
         </td>
         <td class="content_rowl">Tipo</td>
         <td class="content_row">
            <input type="radio" name="sql_mode" value="0" <?php if(!(int)$_SESSION[$_sesmodulename]["sql_mode"]) echo "checked"?>>Anual
            <input type="radio" name="sql_mode" value="1" <?php if((int)$_SESSION[$_sesmodulename]["sql_mode"]) echo "checked"?>>Mensual
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Monto</td>
         <td class="content_row">
            <input type="radio" name="sql_valtype" value="invc_total_netto"   <?php if($_SESSION[$_sesmodulename]["sql_valtype"] == "invc_total_netto") echo "checked"?>> Neto
            <input type="radio" name="sql_valtype" value="invc_total_taxes"   <?php if($_SESSION[$_sesmodulename]["sql_valtype"] == "invc_total_taxes") echo "checked"?>> IVA
            <input type="radio" name="sql_valtype" value="invc_total_brutto"  <?php if($_SESSION[$_sesmodulename]["sql_valtype"] == "invc_total_brutto") echo "checked"?>> Bruto
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
                  if(count($items) > 0 && $items != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($items) > 0 && $items != false)
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
<?php
if($_SESSION[$_sesmodulename]["sql_mode"] == 0)
{  ?>
   <tr>
      <td>
         <?=Nifty_printH("box1", "")?>
         <table border="0" cellpadding="3" cellspacing="0" style="table-layout:fixed">
         <colgroup>
            <col width="85">
            <col width="40">
            <?php
            for($y = $init_year; $y <= $end_year; $y++)
            {  ?>
               <col width="50">
               <col width="80">
               <col width="35">
               <?php
            }
            ?>
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="<?=(5 + ($_SESSION[$_sesmodulename]["sql_yearcount"] * 3))?>">Evolución de ventas</td>
         </tr>
         <tr>
            <td class="content_row_os content_tbl_subheader" colspan="2" rowspan="2"><b>Mes</b></td>
            <?php
            for($y = $init_year; $y <= $end_year; $y++)
            {  ?>
               <td class="content_row_os content_tbl_subheader" colspan="3" align="center" style="border-left:3px double black"><b><?=$y?></b></td>
               <?php
            }
            ?>
         </tr>
         <tr>
            <?php
            for($y = $init_year; $y <= $end_year; $y++)
            {  ?>
               <td class="content_row_os content_tbl_subheader" align="center" style="border-left:3px double black">Cant.</td>
               <td class="content_row_os content_tbl_subheader" align="center">$ Monto</td>
               <td class="content_row_os content_tbl_subheader" align="center">%</td>
               <?php
            }
            ?>
         </tr>
         <?php
         for($x = $init_month; $x <= $end_month; $x++)
         {
            $month_name = $_LANG["MODULE"]["CAL"][($x-1)];
            $idx_month  = sprintf("%02s", $x);
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><?=$month_name?></td>
               <td class="content_row_os" align="center"><?=$idx_month?></td>
               <?php

               //----------------------------------------------------------------------------------
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MONTHNAME"]         = $month_name;
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MONTH"]             = $idx_month;

               //----------------------------------------------------------------------------------
               for($y = $init_year; $y <= $end_year; $y++)
               {
                  $idx_res    = $idx_month."-".$y;
                  $tvalue     = $_RES[$idx_res][$_SESSION[$_sesmodulename]["sql_valtype"]];
                  $avalue     = $_RES[$idx_month."-".($y-1)][$_SESSION[$_sesmodulename]["sql_valtype"]];
                  if($avalue < 0)
                     $avalue = 0;
                  $diffyear   = round((($tvalue - $avalue) / $avalue * 100),0);
                  if($diffyear > 0.00)
                  {
                     $diffpdf = "+".printPrice($diffyear,0);
                     $dspdiff = "<font color=green>+".printPrice($diffyear,0,true)."</font>";
                  }
                  elseif($avalue == "" || $tvalue == "")
                  {
                     $diffpdf = "- - -";
                     $dspdiff = "- - -";
                  }
                  elseif($diffyear < 0.00)
                  {
                     $diffpdf = printPrice($diffyear,0);
                     $dspdiff = "<font color=red>".printPrice($diffyear,0,true)."</font>";
                  }
                  else
                  {
                     $diffpdf = "0";
                     $dspdiff = "0";
                  }
                  ?>
                  <td class="content_row_os" align="center" style="border-left:3px double black"><?=printPrice($_RES[$idx_res]["invc_count"],0,true)?></td>
                  <td class="content_row_os" align="center"><?=printPrice($tvalue,0,true)?></td>
                  <td class="content_row_os" align="center"><nobr><?=$dspdiff?></nobr></td>
                  <?php

                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x][$idx_res]["COUNT"]    = printPrice($_RES[$idx_res]["invc_count"],0);
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x][$idx_res]["VALUE"]    = printPrice($tvalue,0);
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x][$idx_res]["DIFF"]     = $diffpdf;
               }
               ?>
            </tr>
            <?php
         }
         ?>
         <tr>
            <td class="content_row_os content_row_totals" colspan="2" rowspan="2"><b>TOTAL</b></td>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MONTHNAME"] = "<b>TOTAL</b>";
            
            for($y = $init_year; $y <= $end_year; $y++)
            {
               $tvalue     = $_TOTAL[$y][$_SESSION[$_sesmodulename]["sql_valtype"]];
               $avalue     = $_TOTAL[($y-1)][$_SESSION[$_sesmodulename]["sql_valtype"]];
               if($avalue < 0)
                  $avalue = 0;
               $diffyear   = round((($tvalue - $avalue) / $avalue * 100),0);
               if($diffyear > 0.00)
               {
                  $diffpdf = "+".printPrice($diffyear,0);
                  $dspdiff = "<font color=green>+".printPrice($diffyear,0,true)."</font>";
               }
               elseif($avalue == "" || $tvalue == "")
               {
                  $diffpdf = "- - -";
                  $dspdiff = "- - -";
               }
               elseif($diffyear < 0.00)
               {
                  $diffpdf = printPrice($diffyear,0);
                  $dspdiff = "<font color=red>".printPrice($diffyear,0,true)."</font>";
               }
               else
               {
                  $diffpdf = "0";
                  $dspdiff = "0";
               }
               ?>
               <td class="content_row_os content_row_totals" align="center" style="border-left:3px double black"><?=printPrice($_TOTAL[$y]["invc_count"],0,true)?></td>
               <td class="content_row_os content_row_totals" align="center"><?=printPrice($tvalue,0,true)?></td>
               <td class="content_row_os content_row_totals" align="center"><nobr><?=$dspdiff?></nobr></td>
               <?php

               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["-".$y]["COUNT"]    = "<b>".printPrice($_TOTAL[$y]["invc_count"],0)."</b>";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["-".$y]["VALUE"]    = "<b>".printPrice($tvalue,0)."</b>";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["-".$y]["DIFF"]     = "<b>".$diffpdf."</b>";
            }
            ?>
         </tr>
         </table>
         <?=Nifty_printF()?>
         <br>
      </td>
   </tr>
   <?php   
}
else
{ ?>
   <tr>
      <td>
         <?=Nifty_printH("box1", "")?>
         <table border="0" cellpadding="3" cellspacing="0" style="table-layout:fixed">
         <colgroup>
            <col width="40">
            <?php
            for($y = $init_year; $y <= $end_year; $y++)
            {  ?>
               <col width="60">
               <col width="100">
               <col width="40">
               <?php
            }
            ?>
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="<?=(4 + ($_SESSION[$_sesmodulename]["sql_yearcount"] * 3))?>">Evolución de ventas</td>
         </tr>
         <tr>
            <td class="content_row_os content_tbl_subheader" rowspan="2"><b>Dia</b></td>
            <?php
            for($y = $init_year; $y <= $end_year; $y++)
            {  
               ?>
               <td class="content_row_os content_tbl_subheader" colspan="3" align="center" style="border-left:3px double black"><b><?=$y?></b></td>
               <?php
            }
            ?>
         </tr>
         <tr>
            <?php
            for($y = $init_year; $y <= $end_year; $y++)
            {  ?>
               <td class="content_row_os content_tbl_subheader" align="center" style="border-left:3px double black">Cant.</td>
               <td class="content_row_os content_tbl_subheader" align="center">$ Monto</td>
               <td class="content_row_os content_tbl_subheader" align="center">%</td>
               <?php
            }
            ?>
         </tr>
         <?php
         for($x = $days_ini; $x <= $days_end; $x++) 
         {
            //$month_name = $_LANG["MODULE"]["CAL"][($x-1)];
            $idx_day  = sprintf("%02s", $x);
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os" align="center"><?=$idx_day?></td>
               <?php
               //----------------------------------------------------------------------------------
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MONTHNAME"]         = $idx_day;
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MONTH"]             = $idx_day;

               //----------------------------------------------------------------------------------
               for($y = $init_year; $y <= $end_year; $y++)
               {
                  $idx_res    = $idx_day."-".$y;
                  $tvalue     = $_RES[$idx_res][$_SESSION[$_sesmodulename]["sql_valtype"]];
                  $avalue     = $_RES[$idx_day."-".($y-1)][$_SESSION[$_sesmodulename]["sql_valtype"]];
                  if($avalue < 0)
                     $avalue = 0;
                  $diffyear   = round((($tvalue - $avalue) / $avalue * 100),0);

                  if($diffyear > 0.00)
                  {
                     $diffpdf = "+".printPrice($diffyear,0);
                     $dspdiff = "<font color=green>+".printPrice($diffyear,0,true)."</font>";
                  }
                  elseif($avalue == "" || $tvalue == "")
                  {
                     $diffpdf = "- - -";
                     $dspdiff = "- - -";
                  }
                  elseif($diffyear < 0.00)
                  {
                     $diffpdf = printPrice($diffyear,0);
                     $dspdiff = "<font color=red>".printPrice($diffyear,0,true)."</font>";
                  }
                  else
                  {
                     $diffpdf = "0";
                     $dspdiff = "0";
                  }
                  ?>
                  <td class="content_row_os" align="center" style="border-left:3px double black"><?=printPrice($_RES[$idx_res]["invc_count"],0,true)?></td>
                  <td class="content_row_os" align="center"><?=printPrice($tvalue,0,true)?></td>
                  <td class="content_row_os" align="center"><nobr><?=$dspdiff?></nobr></td>
                  <?php
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x][$idx_res]["COUNT"]    = printPrice($_RES[$idx_res]["invc_count"],0);
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x][$idx_res]["VALUE"]    = printPrice($tvalue,0);
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x][$idx_res]["DIFF"]     = $diffpdf;
               }
               ?>
            </tr>
            <?php
         }
         ?>
         <tr>
            <td class="content_row_os content_row_totals" rowspan="2"><b>TOTAL</b></td>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["MONTHNAME"] = "<b>TOTAL</b>";
            
            for($y = $init_year; $y <= $end_year; $y++)
            {
               $tvalue     = $_TOTAL[$y][$_SESSION[$_sesmodulename]["sql_valtype"]];
               $avalue     = $_TOTAL[($y-1)][$_SESSION[$_sesmodulename]["sql_valtype"]];
               if($avalue < 0)
                  $avalue = 0;
               $diffyear   = round((($tvalue - $avalue) / $avalue * 100),0);
               if($diffyear > 0.00)
               {
                  $diffpdf = "+".printPrice($diffyear,0);
                  $dspdiff = "<font color=green>+".printPrice($diffyear,0,true)."</font>";
               }
               elseif($avalue == "" || $tvalue == "")
               {
                  $diffpdf = "- - -";
                  $dspdiff = "- - -";
               }
               elseif($diffyear < 0.00)
               {
                  $diffpdf = printPrice($diffyear,0);
                  $dspdiff = "<font color=red>".printPrice($diffyear,0,true)."</font>";
               }
               else
               {
                  $diffpdf = "0";
                  $dspdiff = "0";
               }
               ?>
               <td class="content_row_os content_row_totals" align="center" style="border-left:3px double black"><?=printPrice($_TOTAL[$y]["invc_count"],0,true)?></td>
               <td class="content_row_os content_row_totals" align="center"><?=printPrice($tvalue,0,true)?></td>
               <td class="content_row_os content_row_totals" align="center"><nobr><?=$dspdiff?></nobr></td>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["-".$y]["COUNT"]    = "<b>".printPrice($_TOTAL[$y]["invc_count"],0)."</b>";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["-".$y]["VALUE"]    = "<b>".printPrice($tvalue,0)."</b>";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["-".$y]["DIFF"]     = "<b>".$diffpdf."</b>";
            }
            ?>
         </tr>
         </table>
         <?=Nifty_printF()?>
         <br>
      </td>
   </tr>
   <?php
}
?>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsSellingEvo($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsSellingEvo($CON);

if($pdffile != "")
{
   $doctitle = "Comparacion-Anual-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Comparacion-Anual-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
