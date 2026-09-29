<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2022 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_selling_areacom";
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
   $_SESSION[$_sesmodulename]["sql_pertype"]        = (int)$_REQUEST["sql_pertype"];
   $_SESSION[$_sesmodulename]["sql_item_id"]       = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"]     = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]          = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]       = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_chist"]         = (int)$_REQUEST["sql_chist"];
   $_SESSION[$_sesmodulename]["sql_xystate"]       = (int)$_REQUEST["sql_xystate"];
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

//----------------------------------------------------------------------------------
$datsql = " select t1.id, t1.req_number, t1.req_total_netto, t1.req_total_taxes, t1.req_total_brutto, t1.req_crtdat, t1.req_upddat,
                   t7.user_lastname 'sellername',
                   t4x.cust_name, t4x.cust_rut, t1.req_isreserva, t1.req_isfabricate,
                   t8.fab_type, t8.fab_med_width, t8.fab_med_height, t8.fab_med_fuelle,
                   t8.item_id, t8.item_amount, t9.item_number_prod, t9.item_title,
                   t8.item_desc, t8.item_type, t8.item_sellprice_netto_dsc, t8.item_sellprice_taxes_perc,
                   t11.cat_title, t12.nombre AS 'comuna', t13.name 'region', t7x.pay_title,
                   t8x.cat_name 'cust_cat_name', t9x.trans_name, t8.fab_mat_gramms, t11x.add_name 'bolsa_type',
                   t12x.shop_name, t8.item_pos, t1.req_despacho_desc,
                   (
                     select MAX(CONCAT(tsub2.invc_docnumber, '_', tsub2.invc_date))
                     from invoices_sell_parts tsub1
                     INNER JOIN invoices_sell tsub2 ON tsub1.part_invc_id = tsub2.id
                     where
                     tsub1.part_req_id = t1.id and
                     tsub1.part_invc_id = tsub2.id and
                     tsub2.invc_status > 1
                   ) AS 'invc_docnumber'
            from orders t1
            INNER JOIN company_data t4                ON ( t1.req_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN customer t4x              ON t1.req_cust_id    = t4x.id
            INNER JOIN user t7                        ON ( t1.req_userid_seller = t7.id )
            INNER JOIN orders_items t8                ON t1.id = t8.req_id
            LEFT OUTER JOIN item t9                   ON t8.item_id = t9.id and t8.item_type = 'item'
            LEFT OUTER JOIN item_productcats t10      ON t9.id = t10.item_id
            LEFT OUTER JOIN productcats t11           ON t10.cat_id = t11.id
            LEFT OUTER JOIN comunas t12               ON t4x.cust_comunaid = t12.id
            LEFT OUTER JOIN regions t13               ON t4x.cust_regionid = t13.id
            LEFT OUTER JOIN payments t7x              ON t1.req_paymentid = t7x.id
            LEFT OUTER JOIN customer_cats t8x         ON t4x.cust_catid = t8x.id
            LEFT OUTER JOIN transports t9x            ON t1.req_transportid = t9x.id
            LEFT OUTER JOIN tran_comments_item_vals t10x ON t10x.item_id = t8.item_id and t10x.com_id = 20
            LEFT OUTER JOIN tran_comments_vals t11x   ON t11x.add_com_id = t10x.com_id and t11x.id = t10x.val_id
            LEFT OUTER JOIN company_shops t12x        ON t1.req_shop_id = t12x.id
            where
            t1.req_status > 1 ";

if((int)$_SESSION[$_sesmodulename]["sql_pertype"] == 0)
   $seasql .= " and t1.req_crtdat between {$sql_datefrom} and {$sql_dateto} ";
elseif((int)$_SESSION[$_sesmodulename]["sql_pertype"] == 1)
   $seasql .= " and
               (
                  select count(tsub2x.id)
                  from invoices_sell_parts tsub1x
                  INNER JOIN invoices_sell tsub2x ON tsub1x.part_invc_id = tsub2x.id
                  where
                  tsub1x.part_req_id   = t1.id and
                  tsub1x.part_invc_id  = tsub2x.id and
                  tsub2x.invc_status   > 1 and
                  tsub2x.invc_date     between {$sql_datefrom} and {$sql_dateto}
               ) > 0 ";
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t1.req_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t1.req_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $seasql .= " and t1.req_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t10.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if($_SESSION[$_sesmodulename]["sql_item_id"])
   $datsql .= " and t8.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t8.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
   $seasql .= " and t1.req_userid_seller   = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
if($_SESSION[$_sesmodulename]["sql_xystate"] == 1)
   $seasql .= " and
               (
                  select MAX(CONCAT(tsub2.invc_docnumber, '_', tsub2.invc_date))
                  from invoices_sell_parts tsub1
                  INNER JOIN invoices_sell tsub2 ON tsub1.part_invc_id = tsub2.id
                  where
                  tsub1.part_req_id = t1.id and
                  tsub1.part_invc_id = tsub2.id and
                  tsub2.invc_status > 1
                ) IS NULL ";
if($_SESSION[$_sesmodulename]["sql_xystate"] == 2)
   $seasql .= " and
               (
                  select MAX(CONCAT(tsub2.invc_docnumber, '_', tsub2.invc_date))
                  from invoices_sell_parts tsub1
                  INNER JOIN invoices_sell tsub2 ON tsub1.part_invc_id = tsub2.id
                  where
                  tsub1.part_req_id = t1.id and
                  tsub1.part_invc_id = tsub2.id and
                  tsub2.invc_status > 1
                ) IS NOT NULL ";
$datsql .= $seasql;

$datsql .= " order by t1.req_number, t8.item_pos";
$items = $CON->select($datsql);

// echo "<pre>";
// print_r($items);

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
}
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Reporte area comercial</b></td>
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
         <td class="content_rowl">Tipo periodo</td>
         <td class="content_row">
            <input type="radio" name="sql_pertype" value="0" <?php if($_SESSION[$_sesmodulename]["sql_pertype"] == 0) echo "checked"?>> Fecha CC
            <input type="radio" name="sql_pertype" value="1" <?php if($_SESSION[$_sesmodulename]["sql_pertype"] == 1) echo "checked"?>> Fecha Factura
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
         <td class="content_rowl">Estado</td>
         <td class="content_row">
            <input type="radio" name="sql_xystate" value="0" <?php if($_SESSION[$_sesmodulename]["sql_xystate"] == 0) echo "checked"?>> Todos
            <input type="radio" name="sql_xystate" value="1" <?php if($_SESSION[$_sesmodulename]["sql_xystate"] == 1) echo "checked"?>> Colocado
            <input type="radio" name="sql_xystate" value="2" <?php if($_SESSION[$_sesmodulename]["sql_xystate"] == 2) echo "checked"?>> Facturado
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
               <td align="left"></td>
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
         <td class="content_tbl_header content_row_os">Nombre del cliente</td>
         <td class="content_tbl_header content_row_os">RUT</td>
         <td class="content_tbl_header content_row_os">Comuna</td>
         <td class="content_tbl_header content_row_os">Región</td>
         <td class="content_tbl_header content_row_os">F/Pago</td>
         <td class="content_tbl_header content_row_os">Categoría</td>
         <td class="content_tbl_header content_row_os">Vendedor</td>
         <td class="content_tbl_header content_row_os">Transportista</td>

         <td class="content_tbl_header content_row_os">Familia</td>
         <td class="content_tbl_header content_row_os">SKU</td>
         <td class="content_tbl_header content_row_os">Nombre producto</td>
         <td class="content_tbl_header content_row_os" align="center">Gramaje</td>
         <td class="content_tbl_header content_row_os">Tipo bolsa</td>
         <td class="content_tbl_header content_row_os">Material</td>
         <td class="content_tbl_header content_row_os">Medida</td>

         <td class="content_tbl_header content_row_os">Nro factura</td>
         <td class="content_tbl_header content_row_os">F/Factura</td>
         <td class="content_tbl_header content_row_os">Cantidad</td>
         <td class="content_tbl_header content_row_os">Precio/Unit</td>
         <td class="content_tbl_header content_row_os">$/Neto total</td>
         <td class="content_tbl_header content_row_os">IVA</td>
         <td class="content_tbl_header content_row_os">$/Total</td>
         <td class="content_tbl_header content_row_os">N° CC</td>
         <td class="content_tbl_header content_row_os" align="center">F/Entrega/Prod.</td>
         <td class="content_tbl_header content_row_os" align="center">F/Entrega/PTerm.</td>
         <td class="content_tbl_header content_row_os">Estado</td>
         <td class="content_tbl_header content_row_os">Sucursal</td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         if($items[$x]["item_type"] == "manual")
         {
            $items[$x]["item_number_prod"] = "[MANUAL]";
            $items[$x]["item_title"] = $items[$x]["item_desc"];
         }

         $medidas = (int)$items[$x]["fab_med_width"]."x".(int)$items[$x]["fab_med_height"];
         if(!(int)$items[$x]["fab_med_width"] || !(int)$items[$x]["fab_med_height"])
            $medidas = "- - -";

         $invc_num   = "- - -";
         $invc_dat   = "- - -";
         $unit_price = $items[$x]["item_sellprice_netto_dsc"] / $items[$x]["item_amount"];
         $neto_price = $items[$x]["item_sellprice_netto_dsc"];
         $tax_price  = round($neto_price / 100 * $items[$x]["item_sellprice_taxes_perc"]);
         $brto_price = round($neto_price + $tax_price);

         $estado  = "Colocado";
         $estadox = "<b class=msg_save_err>Colocado</b>";
         if(strpos($items[$x]["invc_docnumber"], "_") !== false)
         {
            $numarr = explode("_", $items[$x]["invc_docnumber"]);
            $invc_num = $numarr[0];
            $invc_dat = date("d.m.Y", $numarr[1]);
            $estado = "Facturado";
            $estadox = "<b class=msg_save_ok>Facturado</b>";
         }

         $despstamp1 = " ";
         $despstamp2 = " ";
         if((int)$items[$x]["req_isfabricate"])
         {
            $sql = " select t2.*
                     from prod_header t1
                     INNER JOIN prod_amtplan t2 ON t2.prodplan_prdid = t1.id
                     where
                     t1.prd_reqid  = {$items[$x]["id"]} and
                     t1.prd_status > 0
                     order by t2.prodplan_date";
            $prod_amtplans = $CON->select($sql);
            if(count($prod_amtplans) && $prod_amtplans != false)
            {
               $despstamp1 = "";
               foreach($prod_amtplans AS $prod_amtplan)
                  $despstamp1 .= date("d-m-Y", $prod_amtplan["prodplan_date"]).", ";
               $despstamp1 = substr($despstamp1, 0, -2);
            }

         }
         else
         {
            $req_despacho_desc = (int)$items[$x]["req_despacho_desc"];
            if((int)$items[$x]["req_upddat"])
               $despstamp2 = (int)$items[$x]["req_upddat"] + ($req_despacho_desc * 86400);
            else
               $despstamp2 = (int)$items[$x]["req_crtdat"] + ($req_despacho_desc * 86400);

            $despstamp2 = date("d-m-Y", $despstamp2);
         }

         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$items[$x]["cust_name"]?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["cust_rut"]?></nobr></td>
            <td class="content_row_os"><?=$items[$x]["comuna"]?></td>
            <td class="content_row_os"><?=$items[$x]["region"]?></td>
            <td class="content_row_os"><?=$items[$x]["pay_title"]?></td>
            <td class="content_row_os"><?=$items[$x]["cust_cat_name"]?></td>
            <td class="content_row_os"><?=$items[$x]["sellername"]?></td>
            <td class="content_row_os"><?=$items[$x]["trans_name"]?></td>
            <td class="content_row_os"><?=$items[$x]["cat_title"]?></td>
            <td class="content_row_os"><?=$items[$x]["item_number_prod"]?></td>
            <td class="content_row_os"><?=$items[$x]["item_title"]?></td>
            <td class="content_row_os" align="center"><?=printPrice($items[$x]["fab_mat_gramms"], 0, true)?></td>
            <td class="content_row_os"><?=$items[$x]["bolsa_type"]?>&nbsp;</td>
            <td class="content_row_os"><?=$items[$x]["fab_type"]?></td>
            <td class="content_row_os"><?=$medidas?></td>
            <td class="content_row_os"><?=$invc_num?></td>
            <td class="content_row_os"><?=$invc_dat?></td>
            <td class="content_row_os" align="center"><?=printPrice($items[$x]["item_amount"])?></td>
            <td class="content_row_os" align="center"><?=printPrice($unit_price)?></td>
            <td class="content_row_os" align="center"><?=printPrice($neto_price)?></td>
            <td class="content_row_os" align="center"><?=printPrice($tax_price)?></td>
            <td class="content_row_os" align="center"><?=printPrice($brto_price)?></td>
            <td class="content_row_os"><nobr><?=$items[$x]["req_number"]?></nobr></td>
            <td class="content_row_os" align="center"><?=$despstamp1?></td>
            <td class="content_row_os" align="center"><?=$despstamp2?></td>
            <td class="content_row_os"><?=$estadox?></td>
            <td class="content_row_os"><?=$items[$x]["shop_name"]?></td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_name"]         = $items[$x]["cust_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_rut"]          = $items[$x]["cust_rut"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["comuna"]            = $items[$x]["comuna"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["region"]            = $items[$x]["region"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["pay_title"]         = $items[$x]["pay_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_cat_name"]     = $items[$x]["cust_cat_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["sellername"]        = $items[$x]["sellername"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["trans_name"]        = $items[$x]["trans_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cat_title"]         = $items[$x]["cat_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]  = $items[$x]["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]        = $items[$x]["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fab_mat_gramms"]    = printPrice($items[$x]["fab_mat_gramms"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["bolsa_type"]        = $items[$x]["bolsa_type"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fab_type"]          = $items[$x]["fab_type"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["medidas"]           = $medidas;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_num"]          = $invc_num;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_dat"]          = $invc_dat;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_amount"]       = printPrice($items[$x]["item_amount"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unit_price"]        = printPrice($unit_price);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["neto_price"]        = printPrice($neto_price);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["tax_price"]         = printPrice($tax_price);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["brto_price"]        = printPrice($brto_price);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["req_number"]        = $items[$x]["req_number"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["despstamp1"]        = $despstamp1;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["despstamp2"]        = $despstamp2;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["estadox"]           = $estado;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["shop_nam"]          = $items[$x]["shop_name"];
      }
      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="25" align="center">
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
$_SESSION["JSEXEC"] .= ";$('#obitpanel').html('');";

//----------------------------------------------------------------------------------
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsSellingAreaComercial($CON);
if($xlsfile != "")
{
   $doctitle = "Reporte-area-comercial-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>