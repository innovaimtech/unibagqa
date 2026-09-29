<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_buying_products_details";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "4";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Codigo" => "4", "Artículo" => "3", "Unidad" => "8", "Cod/Prov" => "14", "Proveedor" => "9", "Tipo" => "12", "DOCTO" => "11","Orden de Compra"=>"15", "Fecha" => "7", "Cantidad" => "5",  "Neto/Total" => "6");
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
   $_SESSION[$_sesmodulename]["sql_supplier"]      = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_dlvact"]        = (int)$_REQUEST["sql_dlvact"];
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
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $seasql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   $neasql .= " and t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   $geasql .= " and t1.shp_company_id    = {$_SESSION[$_sesmodulename]["sql_company"]} ";
}
if($_SESSION[$_sesmodulename]["sql_shop"])
{
   $seasql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   $neasql .= " and t1.note_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   $geasql .= " and t1.shp_shop_id  = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
}
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
{
   $seasql .= " and t1.invc_supplier_id  = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
   $neasql .= " and t1.note_supplier_id  = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
   $geasql .= " and t1.shp_supplier_id   = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
}
if($_SESSION[$_sesmodulename]["sql_pcat"])
{
   $seasql .= " and t8.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
   $neasql .= " and t8.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
   $geasql .= " and t8.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
}
if($_SESSION[$_sesmodulename]["sql_item_id"])
{
   $seasql .= " and t3.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t3.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
   $neasql .= " and t3.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t3.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
   $geasql .= " and t3.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t3.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
}

//----------------------------------------------------------------------------------
$datsql = " select t3.item_id, t3.item_type, t5.item_title, t5.item_number_prod, t3.item_amount, t3.item_costprice_netto_dsc2,
                   t1.invc_date, t7.unit_name, t6.supp_short, t6.supp_rut, t1.invc_docnumber, 'note_type' 'invoice',
                   t3.item_costprice_taxes_perc, t9.item_code, t11.sord_number
            from invoices_buy t1
            INNER JOIN invoices_buy_parts t2          ON t1.id = t2.part_invc_id
            INNER JOIN invoices_buy_parts_items t3    ON ( t1.id = t3.invc_id and t2.id = t3.part_id )
            INNER JOIN company_data t4                ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            INNER JOIN item t5                        ON ( t3.item_id = t5.id and t3.item_type = 'item' )
            LEFT OUTER JOIN supplier t6               ON ( t1.invc_supplier_id = t6.id )
            LEFT OUTER JOIN item_units t7             ON t5.item_unit = t7.id
            LEFT OUTER JOIN item_productcats t8       ON t5.id = t8.item_id
            LEFT OUTER JOIN item_suppliers t9         ON ( t3.item_id = t9.item_id and t9.supplier_id = t1.invc_supplier_id )
            LEFT OUTER JOIN shipment t10              ON t2.part_shp_id = t10.id
            LEFT OUTER JOIN supplier_order t11        ON t2.part_sord_id = t11.id
            where
            t1.invc_status       > 1 and
            t1.invc_date between {$sql_datefrom} and {$sql_dateto}";
$datsql .= $seasql;

if((int)$_SESSION[$_sesmodulename]["sql_dlvact"])
{
   //----------------------------------------------------------------------------------
   $datsql .= " UNION ALL
                select t3.item_id, t3.item_type, t5.item_title, t5.item_number_prod,
                   t3.item_amount_shipped 'item_amount', t3.item_costprice_netto_dsc 'item_costprice_netto_dsc2',
                   t1.shp_delivery_date 'invc_date', t7.unit_name, t6.supp_short, t6.supp_rut,
                   t1.shp_supplier_docnum 'invc_docnumber', 'note_type' 'guia',
                   t3.item_costprice_taxes_perc, t9.item_code, t11.sord_number
               from shipment t1
               INNER JOIN shipment_items t3              ON ( t1.id = t3.shipment_id )
               INNER JOIN company_data t4                ON ( t1.shp_company_id = t4.id and t4.company_status = 1 )
               INNER JOIN item t5                        ON ( t3.item_id = t5.id and t3.item_type = 'item' )
               LEFT OUTER JOIN supplier t6               ON ( t1.shp_supplier_id = t6.id )
               LEFT OUTER JOIN item_units t7             ON t5.item_unit = t7.id
               LEFT OUTER JOIN item_productcats t8       ON t5.id = t8.item_id
               LEFT OUTER JOIN item_suppliers t9         ON ( t3.item_id = t9.item_id and t9.supplier_id = t1.shp_supplier_id )
               LEFT OUTER JOIN shipment t10              ON t2.part_shp_id = t10.id
               LEFT OUTER JOIN supplier_order t11        ON t2.part_sord_id = t11.id
               where
               t1.shp_status       > 1 and
               t1.shp_delivery_date between {$sql_datefrom} and {$sql_dateto} and
               (
                  select count(t1x.id)
                  from invoices_buy t1x
                  INNER JOIN invoices_buy_parts t2x          ON t1x.id = t2x.part_invc_id
                  INNER JOIN invoices_buy_parts_items t3x    ON ( t1x.id = t3x.invc_id and t2x.id = t3x.part_id )
                  where
                  t1x.invc_status > 1 and
                  t2x.part_shp_id = t1.id
               ) = 0";
   $datsql .= $geasql;
}

//----------------------------------------------------------------------------------
$datsql .= " UNION ALL
            select t3.item_id, t3.item_type, t5.item_title, t5.item_number_prod, t3.item_amount, t3.item_costprice_netto_dsc2,
                   t1.invc_date, t7.unit_name, t6.supp_short, t6.supp_rut, t1.invc_docnumber, 'note_type' 'invoice',
                   t3.item_costprice_taxes_perc, t9.item_code, t11.sord_number
            from invoices_buy t1
            INNER JOIN invoices_buy_parts t2             ON t1.id = t2.part_invc_id
            INNER JOIN invoices_buy_parts_items t3       ON ( t1.id = t3.invc_id and t2.id = t3.part_id )
            INNER JOIN company_data t4                   ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            INNER JOIN itemlist t5                       ON ( t3.item_id = t5.id and t3.item_type = 'itemlist' )
            LEFT OUTER JOIN supplier t6                  ON ( t1.invc_supplier_id = t6.id )
            LEFT OUTER JOIN item_units t7                ON t5.item_unit = t7.id
            LEFT OUTER JOIN item_productcats_itemlist t8 ON t5.id = t8.item_id
            LEFT OUTER JOIN itemlist_suppliers t9        ON ( t3.item_id = t9.item_id and t9.supplier_id = t1.invc_supplier_id )
            LEFT OUTER JOIN shipment t10         ON t2.part_shp_id = t10.id
            LEFT OUTER JOIN supplier_order t11   ON t2.part_sord_id = t11.id
            where
            t1.invc_status       > 1 and
            t1.invc_date between {$sql_datefrom} and {$sql_dateto}";
$datsql .= $seasql;

//----------------------------------------------------------------------------------
$datsql .= " UNION ALL
            select t3.item_id, t3.item_type, t5.item_title, t5.item_number_prod, t3.item_amount, t3.item_costprice_netto_dsc 'item_costprice_netto_dsc2',
                   t1.note_date 'invc_date', t7.unit_name, t6.supp_short, t6.supp_rut, t1.note_docnumber 'invc_docnumber', t1.note_type,
                   t3.item_costprice_taxes_perc, t9.item_code, '' sord_number
            from invoices_notes_buy t1
            INNER JOIN invoices_notes_buy_items t3    ON ( t1.id = t3.note_id )
            INNER JOIN company_data t4                ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
            INNER JOIN item t5                        ON ( t3.item_id = t5.id and t3.item_type = 'item' )
            LEFT OUTER JOIN supplier t6               ON ( t1.note_supplier_id = t6.id )
            LEFT OUTER JOIN item_units t7             ON t5.item_unit = t7.id
            LEFT OUTER JOIN item_productcats t8       ON t5.id = t8.item_id
            LEFT OUTER JOIN item_suppliers t9         ON ( t3.item_id = t9.item_id and t9.supplier_id = t1.note_supplier_id )
            where
            t1.note_status       > 1 and
            t1.note_date between {$sql_datefrom} and {$sql_dateto}";
$datsql .= $neasql;

//----------------------------------------------------------------------------------
$datsql .= " UNION ALL
            select t3.item_id, t3.item_type, t5.item_title, t5.item_number_prod, t3.item_amount, t3.item_costprice_netto_dsc 'item_costprice_netto_dsc2',
                   t1.note_date 'invc_date', t7.unit_name, t6.supp_short, t6.supp_rut, t1.note_docnumber 'invc_docnumber', t1.note_type,
                   t3.item_costprice_taxes_perc, t9.item_code, '' sord_number
            from invoices_notes_buy t1
            INNER JOIN invoices_notes_buy_items t3       ON ( t1.id = t3.note_id )
            INNER JOIN company_data t4                   ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
            INNER JOIN itemlist t5                       ON ( t3.item_id = t5.id and t3.item_type = 'itemlist' )
            LEFT OUTER JOIN supplier t6                  ON ( t1.note_supplier_id = t6.id )
            LEFT OUTER JOIN item_units t7                ON t5.item_unit = t7.id
            LEFT OUTER JOIN item_productcats_itemlist t8 ON t5.id = t8.item_id
            LEFT OUTER JOIN itemlist_suppliers t9        ON ( t3.item_id = t9.item_id and t9.supplier_id = t1.note_supplier_id )
            where
            t1.note_status       > 1 and
            t1.note_date between {$sql_datefrom} and {$sql_dateto}";
$datsql .= $neasql;
$datsql .= "order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}";
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
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
{
   $sql = " select supp_short
            from supplier
            where
            id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
   $custdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = $custdata[0]["supp_short"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = "TODO";

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

$_SESSION["HEADER"][$_sesmodulename]["FROM"] = date('d.m.Y', $sql_datefrom);
$_SESSION["HEADER"][$_sesmodulename]["TO"]   = date('d.m.Y', $sql_dateto);

printJSsetCompanyShop($shops);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Compras por producto / Detalle</b></td>
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
      <tr>
         <td class="content_rowl">Guias</td>
         <td class="content_row" colspan="3">
            <input type="checkbox" value="1" name="sql_dlvact"
            <?if((int)$_SESSION[$_sesmodulename]["sql_dlvact"]) echo "checked"?>> Considerar guias de despacho / no facturadas
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
<tr>
   <td>
      <?=Nifty_printH("box1", "980")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 8)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 9)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 10)?></td>
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      $x = 0;
      foreach($items AS $row)
      {
         if($row["note_type"] == "note_typeinvoice")
            $row["note_type"] = "Factura";
         if($row["note_type"] == "note_typeguia")
            $row["note_type"] = "Guia";
         elseif($row["note_type"] == "1")
            $row["note_type"] = "Nota/C";
         else
            $row["note_type"] = "Nota/D";
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$row["item_number_prod"]?></td>
            <td class="content_row_os"><nobr><?=$row["item_title"]?></nobr></td>
            <td class="content_row_os"><?=$row["unit_name"]?></td>
            <td class="content_row_os"><?=$row["item_code"]?>&nbsp;</td>
            <td class="content_row_os"><nobr><?=$row["supp_short"]?></nobr>;</td>
            <td class="content_row_os"><?=$row["note_type"]?>&nbsp;</td>
            <td class="content_row_os"><?=$row["invc_docnumber"]?>&nbsp;</td>
            <td class="content_row_os"><?=$row["sord_number"]?>&nbsp;</td>
            <td class="content_row_os"><?=date('d.m.Y', $row["invc_date"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($row["item_amount"], 2)?></td>
            <td class="content_row_os" align="right"><?=printPrice($row["item_costprice_netto_dsc2"], 2)?></td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]       = $row["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]             = $row["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unit_name"]              = $row["unit_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_code"]              = $row["item_code"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["supp_short"]             = $row["supp_short"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["note_type"]              = $row["note_type"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_docnumber"]         = $row["invc_docnumber"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_date"]              = date('d.m.Y', $row["invc_date"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_amount"]            = printPrice($row["item_amount"], 2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_costprice"]         = printPrice($row["item_costprice_netto_dsc2"], 2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["sord_number"]            = $row["sord_number"];
         
         $x++;
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="12" align="center">
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
  $pdffile = doc_createStatsItemProductsDetails($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsItemProductsDetails($CON);
  
if($pdffile != "")
{
   $doctitle = "Compras-producto-detalles-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Compras-producto-detalles-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>