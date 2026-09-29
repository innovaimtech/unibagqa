<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2019 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "statscompletedocprod";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Cliente" => "1", "Nombre" => "2", "RUT" => "3", "Extento" => "4", "Neto" => "5", "IVA" => "6", "Total" => "7");
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_type"]          = (int)$_REQUEST["sql_type"];
   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]       = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_seller"]        = (int)$_REQUEST["sql_seller"];
   $_SESSION[$_sesmodulename]["sql_xitemtype"]     = (int)$_REQUEST["sql_xitemtype"];
   $_SESSION[$_sesmodulename]["sql_item_id"]       = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"]     = $sql_item[1];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
}

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON, true);
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
   $_SESSION[$_sesmodulename]["sql_selmode"] = 1;
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;

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

$_SESSION["STATS"][$_sesmodulename]["HEAD"]["sql_date"] = date("d.m.Y", $sql_datefrom)." - ".date("d.m.Y", $sql_dateto);
if(!(int)$_SESSION[$_sesmodulename]["sql_type"])
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["sql_type"] = "Todo";
elseif((int)$_SESSION[$_sesmodulename]["sql_type"] == 1)
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["sql_type"] = "Factura";
elseif((int)$_SESSION[$_sesmodulename]["sql_type"] == 2)
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["sql_type"] = "Boleta";
elseif((int)$_SESSION[$_sesmodulename]["sql_type"] == 3)
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["sql_type"] = "NC/ND";

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
$datsql = " select t1.invc_number, t1.invc_shop_id, t1.invc_docnumber, t1.invc_date, t1.invc_total_netto, t1.invc_total_taxes,
            t1.invc_total_taxes_exclude, t1.invc_total_brutto, 'invc' 'invc', t2.shop_name, t3.company_short,
            t4.cust_name, t5.user_firstname, t5.user_lastname, t1.invc_upddat, t1.id, 
            t1.invc_discount_perc, t4.cust_canal, t4.cust_crtdat, p1.descripcion, t4.cust_presupuesto, f.total_factura_cliente
            ,t4.cust_permanente
            from invoices_sell t1
            LEFT OUTER JOIN company_shops t2 ON t1.invc_shop_id = t2.id
            LEFT OUTER JOIN company_data t3 ON t1.invc_company_id = t3.id
            LEFT OUTER JOIN customer t4 ON t1.invc_cust_id = t4.id
            LEFT OUTER JOIN user t5 ON t1.invc_userid_seller = t5.id
            LEFT OUTER JOIN parametros p1 on p1.tabla = 'CANAL' and p1.codigo = t4.cust_canal
            LEFT JOIN (SELECT invc_cust_id, COUNT(*) AS total_factura_cliente FROM invoices_sell where invc_status > 1 and
                                                                                                       invc_status  < 4 
                       GROUP BY invc_cust_id) f ON f.invc_cust_id = t4.id
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_date between {$sql_datefrom} and {$sql_dateto} ";
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $datsql .= " and t1.invc_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";

if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
   $datsql .= " and t1.invc_userid_seller = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
   $datsql .= " and (
                  select count(*)
                  from invoices_sell_parts_items tx
                  where
                  tx.invc_id     = t1.id and
                  tx.item_id     = {$_SESSION[$_sesmodulename]["sql_item_id"]} and
                  tx.item_type   = 'item'
                ) > 0 ";

if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
   $datsql .= " and (
                  select count(*)
                  from invoices_sell_parts_items txx1
                  INNER JOIN item txx2 ON txx1.item_id = txx2.id
                  where
                  txx1.invc_id     = t1.id and
                  txx1.item_type   = 'item' and
                  txx2.item_ventaonline_act = 1
                ) > 0 ";
if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
   $datsql .= " and (
                  select count(*)
                  from invoices_sell_parts_items txx1
                  INNER JOIN item txx2 ON txx1.item_id = txx2.id
                  where
                  txx1.invc_id     = t1.id and
                  txx1.item_type   = 'item' and
                  txx2.item_ventaonline_act = 0
                ) > 0 ";

$datsql .= " order by 11, 10, 4, 3";
$invcs = $CON->select($datsql);

$_RES = array();
if(!(int)$_SESSION[$_sesmodulename]["sql_type"] || $_SESSION[$_sesmodulename]["sql_type"] == 1)
{
   foreach($invcs AS $invc)
   {
      $sql = " select t1.item_pos,
                      t1.item_sellprice_netto_dsc,
                      t1.item_amount,
                      t2.item_title, t2.item_number_prod
               from invoices_sell_parts_items t1
               INNER JOIN item t2 ON t1.item_id = t2.id
               where
               t1.invc_id     = {$invc["id"]} and
               t1.item_type   = 'item' ";
      if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
         $sql .= " and t2.item_ventaonline_act = 1 ";
      elseif((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
         $sql .= " and t2.item_ventaonline_act = 0 ";
      $sql .= " UNION ALL
               select t1.item_pos,
                      t1.item_sellprice_netto_dsc,
                      t1.item_amount,
                      t1.item_desc 'item_title', '' AS 'item_number_prod'
               from invoices_sell_parts_items t1
               where
               t1.invc_id     = {$invc["id"]} and
               t1.item_type   = 'manual' ";
      if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
         $sql .= " and 1 = 2 ";
      $sql .= " order by 1,4";
      $invc["_pos"] = $CON->select($sql);

      $_RES[] = $invc;
   }
}

//----------------------------------------------------------------------------------
$datsql = " select t1.invc_number, t1.invc_shop_id, t1.invc_docnumber, t1.invc_date, t1.invc_total_netto, t1.invc_total_taxes,
            t1.invc_total_taxes_exclude, t1.invc_total_brutto, 'invc' 'bol', t2.shop_name, t3.company_short,
            t4.cust_name, t5.user_firstname, t5.user_lastname, t1.invc_upddat, t1.id,
            t1.invc_discount_perc, t4.cust_canal, t4.cust_crtdat, p1.descripcion, t4.cust_presupuesto, f.total_factura_cliente,
            t4.cust_permanente
            from invoices_sell_bol t1
            LEFT OUTER JOIN company_shops t2 ON t1.invc_shop_id = t2.id
            LEFT OUTER JOIN company_data t3 ON t1.invc_company_id = t3.id
            LEFT OUTER JOIN customer t4 ON t1.invc_cust_id = t4.id
            LEFT OUTER JOIN user t5 ON t1.invc_userid_seller = t5.id
            LEFT OUTER JOIN parametros p1 on p1.tabla = 'CANAL' and p1.codigo = t4.cust_canal
            LEFT JOIN ( SELECT invc_cust_id, COUNT(*) AS total_factura_cliente FROM invoices_sell_bol where invc_status > 1 and
                                                                                                      invc_status  < 4  GROUP BY invc_cust_id) f ON f.invc_cust_id = t4.id
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_vtype        = 0 and
            t1.invc_anulado_act  = 0 and
            t1.invc_iscredito    = 0 and
            t1.invc_date between {$sql_datefrom} and {$sql_dateto}";
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $datsql .= " and t1.invc_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
   $datsql .= " and t1.invc_userid_seller = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
   $datsql .= " and (
                  select count(*)
                  from invoices_sell_bol_parts_items tx
                  where
                  tx.invc_id     = t1.id and
                  tx.item_id     = {$_SESSION[$_sesmodulename]["sql_item_id"]} and
                  tx.item_type   = 'item'
                ) > 0 ";

if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
   $datsql .= " and (
                  select count(*)
                  from invoices_sell_bol_parts_items txx1
                  INNER JOIN item txx2 ON txx1.item_id = txx2.id
                  where
                  txx1.invc_id     = t1.id and
                  txx1.item_type   = 'item' and
                  txx2.item_ventaonline_act = 1
                ) > 0 ";
if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
   $datsql .= " and (
                  select count(*)
                  from invoices_sell_bol_parts_items txx1
                  INNER JOIN item txx2 ON txx1.item_id = txx2.id
                  where
                  txx1.invc_id     = t1.id and
                  txx1.item_type   = 'item' and
                  txx2.item_ventaonline_act = 0
                ) > 0 ";

$datsql .= " order by t3.company_short, t2.shop_name, t1.invc_date, t1.invc_docnumber";
$invcsbol = $CON->select($datsql);

if(!(int)$_SESSION[$_sesmodulename]["sql_type"] || $_SESSION[$_sesmodulename]["sql_type"] == 2)
{
   foreach($invcsbol AS $invc)
   {
      $sql = " select t1.item_pos,
                      t1.item_sellprice_netto_dsc,
                      t1.item_amount,
                      t2.item_title, t2.item_number_prod
               from invoices_sell_bol_parts_items t1
               INNER JOIN item t2 ON t1.item_id = t2.id
               where
               t1.invc_id     = {$invc["id"]} and
               t1.item_type   = 'item' ";
      if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
         $sql .= " and t2.item_ventaonline_act = 1 ";
      elseif((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
         $sql .= " and t2.item_ventaonline_act = 0 ";
      $sql .= " UNION ALL
               select t1.item_pos,
                      t1.item_sellprice_netto_dsc,
                      t1.item_amount,
                      t1.item_desc 'item_title', '' AS 'item_number_prod'
               from invoices_sell_bol_parts_items t1
               where
               t1.invc_id     = {$invc["id"]} and
               t1.item_type   = 'manual' ";
      if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
         $sql .= " and 1 = 2 ";
      $sql .= " order by 1,4";
      $invc["_pos"] = $CON->select($sql);

      $_RES[] = $invc;
   }
}

//----------------------------------------------------------------------------------
$datsql = " select t1.note_number 'invc_number', t1.note_shop_id 'invc_shop_id', t1.note_docnumber 'invc_docnumber', t1.note_date 'invc_date',
            t1.note_total_netto 'invc_total_netto', t1.note_total_taxes 'invc_total_taxes',
            t1.note_total_taxes_exclude 'invc_total_taxes_exclude', t1.note_total_brutto 'invc_total_brutto', 'invc' 'nc',
            t2.shop_name, t3.company_short, t1.note_type,
            t4.cust_name, t5.user_firstname, t5.user_lastname, t1.note_upddat 'invc_upddat', t1.id,
            t1.note_discount_perc 'invc_discount_perc', t4.cust_canal, t4.cust_crtdat, p1.descripcion, t4.cust_presupuesto, f.total_factura_cliente,
            t4.cust_permanente
            from invoices_notes_sell t1
            LEFT OUTER JOIN company_shops t2 ON t1.note_shop_id = t2.id
            LEFT OUTER JOIN company_data t3 ON t1.note_company_id = t3.id
            LEFT OUTER JOIN customer t4 ON t1.note_cust_id = t4.id
            LEFT OUTER JOIN user t5 ON t1.note_userid_seller = t5.id
            LEFT OUTER JOIN parametros p1 on p1.tabla = 'CANAL' and p1.codigo = t4.cust_canal
            LEFT JOIN ( SELECT note_cust_id, COUNT(*) AS total_factura_cliente FROM invoices_notes_sell where note_status > 1 and
                                                                                                       note_status  < 4 
            GROUP BY note_cust_id) f ON f.note_cust_id = t4.id
            where
            t1.note_status       > 1 and
            t1.note_status       < 4 and
            t1.note_date between {$sql_datefrom} and {$sql_dateto}";
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $datsql .= " and t1.note_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.note_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
   $datsql .= " and t1.note_userid_seller = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
   $datsql .= " and (
                  select count(*)
                  from invoices_notes_sell_items tx
                  where
                  tx.invc_id     = t1.id and
                  tx.item_id     = {$_SESSION[$_sesmodulename]["sql_item_id"]} and
                  tx.item_type   = 'item'
                ) > 0 ";

if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
   $datsql .= " and (
                  select count(*)
                  from invoices_notes_sell_items txx1
                  INNER JOIN item txx2 ON txx1.item_id = txx2.id
                  where
                  txx1.note_id     = t1.id and
                  txx1.item_type   = 'item' and
                  txx2.item_ventaonline_act = 1
                ) > 0 ";
if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
   $datsql .= " and (
                  select count(*)
                  from invoices_notes_sell_items txx1
                  INNER JOIN item txx2 ON txx1.item_id = txx2.id
                  where
                  txx1.note_id     = t1.id and
                  txx1.item_type   = 'item' and
                  txx2.item_ventaonline_act = 0
                ) > 0 ";

$datsql .= " order by t3.company_short, t2.shop_name, t1.note_date, t1.note_docnumber";
$notes = $CON->select($datsql);

if(!(int)$_SESSION[$_sesmodulename]["sql_type"] || $_SESSION[$_sesmodulename]["sql_type"] == 3)
{
   foreach($notes AS $invc)
   {
      $sql = " select t1.item_pos,
                      t1.item_sellprice_netto_dsc,
                      t1.item_amount,
                      t2.item_title, t2.item_number_prod
               from invoices_notes_sell_items t1
               INNER JOIN item t2 ON t1.item_id = t2.id
               where
               t1.note_id     = {$invc["id"]} and
               t1.item_type   = 'item' ";
      if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
         $sql .= " and t2.item_ventaonline_act = 1 ";
      elseif((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
         $sql .= " and t2.item_ventaonline_act = 0 ";
      $sql .= " UNION ALL
               select t1.item_pos,
                      t1.item_sellprice_netto_dsc,
                      t1.item_amount,
                      t1.item_desc 'item_title', '' AS 'item_number_prod'
               from invoices_notes_sell_items t1
               where
               t1.note_id     = {$invc["id"]} and
               t1.item_type   = 'manual' ";
      if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
         $sql .= " and 1 = 2 ";
      $sql .= " order by 1,4";
      $invc["_pos"] = $CON->select($sql);

      if($invc["invc"] == "invcnc" && $invc["note_type"] == 1)
      {
         for($x = 0; $x < count($invc["_pos"]) && $invc["_pos"] != false; $x++)
         {
            $invc["_pos"][$x]["item_sellprice_netto_dsc"]   = $invc["_pos"][$x]["item_sellprice_netto_dsc"] * -1;
         }
      }

      $_RES[] = $invc;
   }
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
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
         array_push($selshops, $shop);
}

printJSsetCompanyShop($shops);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Ventas Por Doc./Productos</b></td>
   <td align="right" class="content_row_clear"></td>
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
         <col width="80">
         <col>
         <col width="100">
         <col width="300">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
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
               <td class="content_row_clear" width="185" id="idx_selmode2" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 2) echo "style='display:none'"?>>
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
                  -
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
                  <input type="text" style="width:72px" id="sql_date_pfrom" name="sql_date_pfrom"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pfrom"]?>">
                  -
                  <input type="text" style="width:72px" id="sql_date_pto" name="sql_date_pto"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pto"]?>">
                  </nobr>
               </td>
            </tr>
            </table>
         </td>
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
         <td class="content_rowl">Tipo</td>
         <td class="content_row">
            <input type="radio" name="sql_type" value="0"
            <?if(!(int)$_SESSION[$_sesmodulename]["sql_type"]) echo "checked"?>>&nbsp;Todo
            <input type="radio" name="sql_type" value="1"
            <?if((int)$_SESSION[$_sesmodulename]["sql_type"] == 1) echo "checked"?>>&nbsp;Factura
            <input type="radio" name="sql_type" value="2"
            <?if((int)$_SESSION[$_sesmodulename]["sql_type"] == 2) echo "checked"?>>&nbsp;Boleta
            <input type="radio" name="sql_type" value="3"
            <?if((int)$_SESSION[$_sesmodulename]["sql_type"] == 3) echo "checked"?>>&nbsp;Nota Credito/Debito
         </td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Artículo</td>
         <td class="content_row"><?php printOverviewItemSelect($_sesmodulename) ?></td>
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
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
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
                  if(count($_RES) > 0 && $_RES != false)
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
      <?=Nifty_printH("box1", "99%")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
      </colgroup>
      <tr>
         <td class="content_tbl_header content_row_os">Tipo</td>
         <td class="content_tbl_header content_row_os">Número</td>
         <td class="content_tbl_header content_row_os">Fecha</td>
         <td class="content_tbl_header content_row_os">Hora</td>
         <td class="content_tbl_header content_row_os">Cliente</td>
         <td class="content_tbl_header content_row_os">Canal</td>
         <td class="content_tbl_header content_row_os">Fec. Creación</td>
         <td class="content_tbl_header content_row_os">Presuesto</td>
         <td class="content_tbl_header content_row_os">Permanente</td>
         
         <td class="content_tbl_header content_row_os">Cantidad Documentos</td>
         <td class="content_tbl_header content_row_os">Sucursal</td>
         <td class="content_tbl_header content_row_os" style="border-right:3px double #666666">Vendedor</td>
         <td class="content_tbl_header content_row_os">Código</td>
         <td class="content_tbl_header content_row_os">Producto</td>
         <td class="content_tbl_header content_row_os" align="right">Cantidad</td>
         <td class="content_tbl_header content_row_os" align="right">Neto/Unit</td>
         <td class="content_tbl_header content_row_os" align="right">Descuento</td>
         <td class="content_tbl_header content_row_os" align="right">Neto/Total</td>
      </tr>
      <?php
      $px = 0;
      $xx = 0;
      for($x = 0; $x < count($_RES) && $_RES != false; $x++)
      {
         $invc = $_RES[$x];

         $invc["shop_name"] = str_replace("Casa Central", "", $invc["shop_name"]);

         foreach($invc["_pos"] AS $item)
         {
            ?>
            <tr bgcolor="<?=getRowColor($px)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os">
                  <?php
                  $typestr = "Factura";
                  if($invc["invc"] == "invcbol")
                     $typestr = "Boleta";
                  if($invc["invc"] == "invcnc")
                     $typestr = "NC/ND";
                  echo $typestr;

                  
                  if($invc["invc"] == "invcnc" && $invc["note_type"] == 1)
                  {
                     $invc["invc_total_taxes_exclude"]  = $invc["invc_total_taxes_exclude"] * -1;
                     $invc["invc_total_netto"]          = $invc["invc_total_netto"] * -1;
                     $invc["invc_total_taxes"]          = $invc["invc_total_taxes"] * -1;
                     $invc["invc_total_brutto"]         = $invc["invc_total_brutto"] * -1;
                  }
                  if($invc["note_type"] == 1)
                     $cantidad = $item["item_amount"] * -1;
                  else
                     $cantidad = $item["item_amount"];

                  $descuento = 0;
                  $descuento = ($item["item_sellprice_netto_dsc"] * $invc["invc_discount_perc"]  / 100) * -1;
                  $total     = $item["item_sellprice_netto_dsc"] - abs($descuento);

                  ?>
               </td>
               <td class="content_row_os"><?=$invc["invc_docnumber"]?></td>
               <td class="content_row_os"><nobr><?=date("d.m.Y", $invc["invc_date"])?></nobr></td>
               <td class="content_row_os"><nobr><?=date("H:i", $invc["invc_upddat"])?></nobr></td>
               <td class="content_row_os"><?=$invc["cust_name"]?>&nbsp;</td>
               <td class="content_row_os"><?=$invc["descripcion"]?>&nbsp;</td>
               <td class="content_row_os"><?=date("d/m/Y",$invc["cust_crtdat"])?>&nbsp;</td>
               <td class="content_row_os"><?=$invc["cust_presupuesto"] == 1 ? "Sí" : "No" ?></td>
               <td class="content_row_os"><?=$invc["cust_permanente"] == 1 ? "Sí" : "No" ?></td>
               <td class="content_row_os"><?=$invc["total_factura_cliente"]?></td>
               <td class="content_row_os"><nobr><?=$invc["shop_name"]?></nobr>&nbsp;</td>
               <td class="content_row_os" style="border-right:3px double #666666"><nobr><?=$invc["user_firstname"] ." ". $invc["user_lastname"]?></nobr>&nbsp;</td>
               <td class="content_row_os"><?=$item["item_number_prod"]?>&nbsp;</td>
               <td class="content_row_os"><?=$item["item_title"]?>&nbsp;</td>
               <td class="content_row_os" align="right"><?=printPrice($cantidad,2)?></td>
               <td class="content_row_os" align="right"><?=printPrice($item["item_sellprice_netto_dsc"] / $item["item_amount"])?></td>
               <td class="content_row_os" align="right"><?=printPrice($descuento)?></td>
               <td class="content_row_os" align="right"><?=printPrice($total)?></td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["typestr"]                   = $typestr;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["invc_docnumber"]            = $invc["invc_docnumber"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["invc_date"]                 = date("d.m.Y", $invc["invc_date"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["invc_upddat"]               = date("H:i", $invc["invc_upddat"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["cust_name"]                 = $invc["cust_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["shop_name"]                 = $invc["shop_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["user_lastname"]             = $invc["user_firstname"].' '.$invc["user_lastname"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["item_number_prod"]          = $item["item_number_prod"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["item_title"]                = $item["item_title"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["item_amount"]               = printPrice($cantidad,2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["item_sellprice_netto"]      = printPrice($item["item_sellprice_netto_dsc"] / $item["item_amount"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["item_sellprice_descuento"]  = printPrice($descuento);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["item_sellprice_netto_dsc"]  = printPrice($total);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["canal"]                     = $invc["descripcion"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["fecha_creacion"]            = $invc["cust_crtdat"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["presupuesto"]               = $invc["cust_presupuesto"] == 1 ? "Sí" : "No";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["ndocumentos"]               = $invc["total_factura_cliente"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["cust_permanente"]           = $invc["cust_permanente"] == 1 ? "Sí" : "No";
            
            
            $_TOTAL_AMOUNT += $item["item_amount"];
            $_TOTAL_NETTO  += $total;
            $_TOTAL_DESCUENTO    += $descuento;
            $xx++;
         }
         $px++;
      }
      ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row_os content_row_totals" colspan="7" style="border-right:3px double #666666">TOTAL</td>
         <td class="content_row_os content_row_totals" align="right" colspan="2">&nbsp;</td>
         <td class="content_row_os content_row_totals" align="right"><?=printPrice($_TOTAL_AMOUNT,2)?></td>
         <td class="content_row_os content_row_totals" align="right">&nbsp;</td>
         <td class="content_row_os content_row_totals" align="right"><?=printPrice($_TOTAL_DESCUENTO)?></td>
         <td class="content_row_os content_row_totals" align="right"><?=printPrice($_TOTAL_NETTO, 0, true)?></td>
      </tr>
      <?php
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["typestr"]                   = "TOTAL";
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["item_amount"]               = printPrice($_TOTAL_AMOUNT,2);
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["item_sellprice_descuento"]  = printPrice($_TOTAL_DESCUENTO);
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$xx]["item_sellprice_netto_dsc"]  = printPrice($_TOTAL_NETTO);
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
  $xlsfile = xls_createStatsSellingCompleteProd($CON);

if($xlsfile != "")
{
   $doctitle = "Ventas-documento-producto-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>