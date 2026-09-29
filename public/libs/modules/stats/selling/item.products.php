<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_selling_products";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Numero" => "1", "Artículo" => "2", "Unidad" => "3", "Cantidad" => "4", "Monto/Neto" => "5",
                                "Monto/IVA" => "6", "Monto/Bruto" => "7",  "% Bruto/Total" => "8", "Código contable" => "9");
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
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_storehouse"] = (int)$_REQUEST["sql_storehouse"];
   $_SESSION[$_sesmodulename]["sql_seller"]        = (int)$_REQUEST["sql_seller"];
   $_SESSION[$_sesmodulename]["sql_item_id"]       = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"]     = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_xitemtype"]     = (int)$_REQUEST["sql_xitemtype"];
   $_SESSION[$_sesmodulename]["sql_pcat"]          = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]       = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_chist"]         = (int)$_REQUEST["sql_chist"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON);
$shops      = getShops($CON);
$sellers    = getSellers($CON);

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
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;
if(!(int)$_SESSION[$_sesmodulename]["sql_chist"])
   $_SESSION[$_sesmodulename]["sql_chist"] = 1;

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = 1;
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

//----------------------------------------------------------------------------------
$datsql = " select t3.item_id, t3.item_type, t5.item_title, t5.item_number_prod, t3.item_amount, t3.item_sellprice_netto_dsc,
                   t1.invc_date, t7.unit_name, t1.invc_cust_id, t6.cust_company, t3.item_sellprice_taxes_perc, t1.invc_total_brutto,
                   t9.cc_title
            from invoices_sell t1
            INNER JOIN invoices_sell_parts t2         ON t1.id = t2.part_invc_id
            INNER JOIN invoices_sell_parts_items t3   ON ( t1.id = t3.invc_id and t2.id = t3.part_id )
            INNER JOIN company_data t4                ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            INNER JOIN item t5                        ON ( t3.item_id = t5.id and t3.item_type = 'item' )
            LEFT OUTER JOIN customer t6               ON ( t1.invc_cust_id = t6.id )
            LEFT OUTER JOIN item_units t7             ON t5.item_unit = t7.id
            LEFT OUTER JOIN item_productcats t8       ON t5.id = t8.item_id
            LEFT OUTER JOIN codigo_contable t9        ON t5.item_codcont = t9.id
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_date between {$sql_datefrom} and {$sql_dateto}";

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $seasql .= " and t1.invc_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t8.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if($_SESSION[$_sesmodulename]["sql_item_id"])
   $seasql .= " and t3.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t3.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
if((int)$_SESSION[$_sesmodulename]["sql_storehouse"])
   $seasql .= " and t3.item_st_id = {$_SESSION[$_sesmodulename]["sql_storehouse"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
   $seasql .= " and t1.invc_userid_seller   = {$_SESSION[$_sesmodulename]["sql_seller"]} ";

if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
   $seasql .= " and t5.item_ventaonline_act = 1 ";
elseif((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
   $seasql .= " and t5.item_ventaonline_act = 0 ";

$datsql .= $seasql;
$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
for($x = 0; $x < count($items) && $items != false; $x++)
{
   $row = $items[$x];

   $IVA = round($row["item_sellprice_netto_dsc"] / 100 * $row["item_sellprice_taxes_perc"],0);
   $BRT = $row["item_sellprice_netto_dsc"] + $IVA;

   $_RES[$row["item_id"]]["ID"]          = $row["item_id"];
   $_RES[$row["item_id"]]["NUMBER"]      = $row["item_number_prod"];
   $_RES[$row["item_id"]]["UNIT"]        = $row["unit_name"];
   $_RES[$row["item_id"]]["TITLE"]       = $row["item_title"];
   $_RES[$row["item_id"]]["AMOUNT"]     += $row["item_amount"];
   $_RES[$row["item_id"]]["SELL"]       += $row["item_sellprice_netto_dsc"];
   $_RES[$row["item_id"]]["IVA"]        += $IVA;
   $_RES[$row["item_id"]]["BRUTO"]      += $BRT;
   $_RES[$row["item_id"]]["TOT_BRUTTO"] += $row["invc_total_brutto"];
   $_RES[$row["item_id"]]["CODE"]        = $row["cc_title"];

}

//----------------------------------------------------------------------------------
$datsql = " select t3.item_id, t3.item_type, t5.item_title, t5.item_number_prod, t3.item_amount, t3.item_sellprice_brutto_dsc,
                   t1.invc_date, t7.unit_name, t1.invc_cust_id, t6.cust_company, t3.item_sellprice_taxes_perc, t1.invc_total_brutto,
                   t9.cc_title
            from invoices_sell_bol t1
            INNER JOIN invoices_sell_bol_parts t2         ON t1.id = t2.part_invc_id
            INNER JOIN invoices_sell_bol_parts_items t3   ON ( t1.id = t3.invc_id and t2.id = t3.part_id )
            INNER JOIN company_data t4                ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            INNER JOIN item t5                        ON ( t3.item_id = t5.id and t3.item_type = 'item' )
            LEFT OUTER JOIN customer t6               ON ( t1.invc_cust_id = t6.id )
            LEFT OUTER JOIN item_units t7             ON t5.item_unit = t7.id
            LEFT OUTER JOIN item_productcats t8       ON t5.id = t8.item_id
            LEFT OUTER JOIN codigo_contable t9        ON t5.item_codcont = t9.id
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_anulado_act  = 0 and
            t1.invc_date between {$sql_datefrom} and {$sql_dateto}";

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $seasql .= " and t1.invc_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t8.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if($_SESSION[$_sesmodulename]["sql_item_id"])
   $seasql .= " and t3.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t3.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
if((int)$_SESSION[$_sesmodulename]["sql_storehouse"])
   $seasql .= " and t3.item_st_id = {$_SESSION[$_sesmodulename]["sql_storehouse"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
   $seasql .= " and t1.invc_userid_seller   = {$_SESSION[$_sesmodulename]["sql_seller"]} ";

if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
   $seasql .= " and t5.item_ventaonline_act = 1 ";
elseif((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
   $seasql .= " and t5.item_ventaonline_act = 0 ";

$datsql .= $seasql;
$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
for($x = 0; $x < count($items) && $items != false; $x++)
{
   $row = $items[$x];

   $BRT = $row["item_sellprice_brutto_dsc"];
   $IVA = round($row["item_sellprice_brutto_dsc"] / (100 + $row["item_sellprice_taxes_perc"]) *  $row["item_sellprice_taxes_perc"],0);
   $NET = $row["item_sellprice_brutto_dsc"] - $IVA;

   $_RES[$row["item_id"]]["ID"]          = $row["item_id"];
   $_RES[$row["item_id"]]["NUMBER"]      = $row["item_number_prod"];
   $_RES[$row["item_id"]]["UNIT"]        = $row["unit_name"];
   $_RES[$row["item_id"]]["TITLE"]       = $row["item_title"];
   $_RES[$row["item_id"]]["AMOUNT"]     += $row["item_amount"];
   $_RES[$row["item_id"]]["SELL"]       += $NET;
   $_RES[$row["item_id"]]["IVA"]        += $IVA;
   $_RES[$row["item_id"]]["BRUTO"]      += $BRT;
   $_RES[$row["item_id"]]["TOT_BRUTTO"] += $row["invc_total_brutto"];
   $_RES[$row["item_id"]]["CODE"]        = $row["cc_title"];
}

//----------------------------------------------------------------------------------
$datsql = " select t3.item_id, t3.item_type, t5.item_title, t5.item_number_prod
                   , case when t1.note_type = 1 then t3.item_amount * -1 else t3.item_amount end as item_amount
                   , t3.item_sellprice_netto_dsc,
                   t1.note_date, t7.unit_name, t1.note_type, t1.note_cust_id, t6.cust_company, t3.item_sellprice_taxes_perc,
                   t1.note_total_brutto, t9.cc_title
            from invoices_notes_sell t1
            INNER JOIN invoices_notes_sell_items t3   ON ( t1.id = t3.note_id )
            INNER JOIN company_data t4                ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
            INNER JOIN item t5                        ON ( t3.item_id = t5.id and t3.item_type = 'item' )
            LEFT OUTER JOIN customer t6               ON ( t1.note_cust_id = t6.id )
            LEFT OUTER JOIN item_units t7                ON t5.item_unit = t7.id
            LEFT OUTER JOIN item_productcats t8       ON t5.id = t8.item_id
            LEFT OUTER JOIN codigo_contable t9        ON t5.item_codcont = t9.id
            where
            t1.note_status       > 1 and
            t1.note_status       < 4 and
            t1.note_date between {$sql_datefrom} and {$sql_dateto}";
$datsql .= str_replace("t1.invc_", "t1.note_", $seasql);
$noteitems = $CON->select($datsql);

// echo($datsql);

//----------------------------------------------------------------------------------
for($x = 0; $x < count($noteitems) && $noteitems != false; $x++)
{
   $row = $noteitems[$x];
   $_RES[$row["item_id"]]["ID"]        = $row["item_id"];
   $_RES[$row["item_id"]]["NUMBER"]    = $row["item_number_prod"];
   $_RES[$row["item_id"]]["UNIT"]      = $row["unit_name"];
   $_RES[$row["item_id"]]["TITLE"]     = $row["item_title"];

   $IVA = round($row["item_sellprice_netto_dsc"] / 100 * $row["item_sellprice_taxes_perc"],0);
   $BRT = $row["item_sellprice_netto_dsc"] + $IVA;

   if($row["note_type"] == 1)
   {
      $_RES[$row["item_id"]]["AMOUNT"]       -= $row["item_amount"];
      $_RES[$row["item_id"]]["SELL"]         -= $row["item_sellprice_netto_dsc"];
      $_RES[$row["item_id"]]["IVA"]          -= $IVA;
      $_RES[$row["item_id"]]["BRUTO"]        -= $BRT;
   }
   else
   {
      $_RES[$row["item_id"]]["AMOUNT"]       += $row["item_amount"];
      $_RES[$row["item_id"]]["SELL"]         += $row["item_sellprice_netto_dsc"];
      $_RES[$row["item_id"]]["IVA"]          += $IVA;
      $_RES[$row["item_id"]]["BRUTO"]        += $BRT;
   }
   $_RES[$row["item_id"]]["TOT_BRUTTO"]      += $row["note_total_brutto"];
   $_RES[$row["item_id"]]["CODE"]            = $row["cc_title"];
}

$_RES2 = array();
foreach ($_RES as $row)
{
   $_RES2[$row["ID"]]["ID"]         = $row["ID"];
   $_RES2[$row["ID"]]["NUMBER"]     = $row["NUMBER"];
   $_RES2[$row["ID"]]["UNIT"]       = $row["UNIT"];
   $_RES2[$row["ID"]]["TITLE"]      = $row["TITLE"];
   $_RES2[$row["ID"]]["AMOUNT"]     = $row["AMOUNT"];
   $_RES2[$row["ID"]]["SELL"]       = $row["SELL"];
   $_RES2[$row["ID"]]["IVA"]        = $row["IVA"];
   $_RES2[$row["ID"]]["BRUTO"]      = $row["BRUTO"];
   $_RES2["T_BRUTO"]               += $row["BRUTO"];
   // $_RES2[$row["ID"]]["TOT_BRUTTO"] = $row["TOT_BRUTTO"];
   $_RES2[$row["ID"]]["PERC"]       = ($row["BRUTO"] * 100 ) / $row["TOT_BRUTTO"] ;
   $_RES2[$row["ID"]]["CODE"]       = $row["CODE"];
}

$_RES3 = array();
foreach ($_RES2 as $row)
{
   if($row["ID"])
   {
      $_RES3[$row["ID"]]["NUMBER"]     = $row["NUMBER"];
      $_RES3[$row["ID"]]["UNIT"]       = $row["UNIT"];
      $_RES3[$row["ID"]]["TITLE"]      = $row["TITLE"];
      $_RES3[$row["ID"]]["AMOUNT"]     = $row["AMOUNT"];
      $_RES3[$row["ID"]]["SELL"]       = $row["SELL"];
      $_RES3[$row["ID"]]["IVA"]        = $row["IVA"];
      $_RES3[$row["ID"]]["BRUTO"]      = $row["BRUTO"];

      // $_RES2[$row["ID"]]["TOT_BRUTTO"] = $row["TOT_BRUTTO"];
      $_RES3[$row["ID"]]["PERC"]       = ($row["BRUTO"] * 100 ) / $_RES2["T_BRUTO"];
      $_RES3[$row["ID"]]["CODE"]       = $row["CODE"];
   }
}

//----------------------------------------------------------------------------------
$orderarr = Array("1" => "NUMBER", "2" => "TITLE", "3" => "UNIT", "4" => "AMOUNT", "5" => "SELL",
                  "6" => "IVA", "7" => "BRUTO", "8" => "PERC", "9" => "CODE");

$orderfield = $orderarr[$_SESSION[$_sesmodulename]["orderBy"]];

function xgetResultOrderCallback($a, $b)
{
   global $orderfield;
   global $_sesmodulename;
   if(trim($_SESSION[$_sesmodulename]["orderSort"]) == "asc")
      return ($a[$orderfield] > $b[$orderfield]);
   else
      return ($a[$orderfield] < $b[$orderfield]);
}
usort($_RES3, "xgetResultOrderCallback");

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

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

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
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Ventas por producto</b></td>
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
               foreach($selstorehouses AS $selstorehouse)
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
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
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
      </tr>
      <tr>
         <td class="content_rowl">Tipo producto</td>
         <td class="content_row">
            <input type="radio" name="sql_xitemtype" value="0" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 0) echo "checked"?>> Todos
            <input type="radio" name="sql_xitemtype" value="1" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 1) echo "checked"?>> Solo venta online
            <input type="radio" name="sql_xitemtype" value="2" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 2) echo "checked"?>> Solo otros
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
                  if(count($_RES3) > 0 && $_RES3 != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($_RES3) > 0 && $_RES3 != false)
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
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" style="border-right:3px double black"><?=printSortLink($_sesmodulename, $_sortlinks, 8)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
         <!-- <td class="content_tbl_subheader content_row_os" valign="top" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td> -->
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      $x = 0;
      foreach($_RES3 AS $row)
      {
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$row["NUMBER"]?></td>
            <td class="content_row_os"><?=$row["TITLE"]?></td>
            <td class="content_row_os"><?=$row["UNIT"]?></td>
            <td class="content_row_os" style="border-right:3px double black"><?=$row["CODE"]?>&nbsp;</td>
            <td class="content_row_os" align="right"><?=printPrice($row["AMOUNT"], 2, true)?></td>
            <td class="content_row_os" align="right"><?=printPrice($row["SELL"], 0, true)?></td>
            <td class="content_row_os" align="right"><?=printPrice($row["IVA"], 0, true)?></td>
            <td class="content_row_os" align="right"><?=printPrice($row["BRUTO"], 0, true)?></td>
            <?/*<td class="content_row_os" align="right"><?=printPrice($row["TOT_BRUTTO"], 2, true)?></td>*/?>
            <td class="content_row_os" align="right"><?=printPrice($row["PERC"], 5, true)?></td>

         </tr>
         <?php
         $ges_lines++;

         $ges_amount    += $row["AMOUNT"];
         $ges_sell      += $row["SELL"];
         $ges_iva       += $row["IVA"];
         $ges_bruto     += $row["BRUTO"];
         $ges_tot_bruto += $row["TOT_BRUTTO"];
         $ges_perc      += $row["PERC"];

         //----------------------------------------------------------------------------------
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NUMBER"]            = $row["NUMBER"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TITLE"]             = $row["TITLE"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["UNIT"]              = $row["UNIT"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CODE"]              = $row["CODE"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["AMOUNT"]            = printPrice($row["AMOUNT"], 2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["SELL"]              = printPrice($row["SELL"], 0);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["IVA"]               = printPrice($row["IVA"], 0);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["BRUTO"]             = printPrice($row["BRUTO"], 0);
         // $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TOT_BRUTTO"]        = printPrice($row["TOT_BRUTTO"], 2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["PERC"]              = printPrice($row["PERC"], 2);

         $x++;
      }
      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="10" align="center">
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
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_amount, 2, true)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_sell, 0, true)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_iva, 0, true)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_bruto, 0, true)?></td>
            <?/*<td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_tot_bruto, 0, true)?></td>*/?>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_perc, 2, true)?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NUMBER"]      = "<b>TOTAL</b>";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["AMOUNT"]      = "<b>".printPrice($ges_amount, 2)."</b>";;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["SELL"]        = "<b>".printPrice($ges_sell, 0)."</b>";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["IVA"]         = "<b>".printPrice($ges_iva, 0)."</b>";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["BRUTO"]       = "<b>".printPrice($ges_bruto, 0)."</b>";
         // $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TOT_BRUTTO"]  = "<b>".printPrice($ges_tot_bruto, 0)."</b>";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["PERC"]       = "<b>".printPrice($ges_perc, 2)."</b>";

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
  $pdffile = doc_createStatsSellingProducts($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsSellingProducts($CON);
  
if($pdffile != "")
{
   $doctitle = "Ventas-por-producto-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Ventas-por-producto-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>