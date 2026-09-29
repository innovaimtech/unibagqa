<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "price_itemmargen";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "3,2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Factura" => "3,2", "Fecha" => "2,3", "Cliente" => "10", "Número" => "11",
                                "Artículo" => "4", "Cantidad" => "7", "$/Venta/U" => "9", "$/Venta/T" => "8");
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
   $_SESSION[$_sesmodulename]["sql_customer"]  = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_month1"]    = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]    = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]     = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]     = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]      = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]= trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]  = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_docnumber"] = trim($_REQUEST["sql_docnumber"]);
   $_SESSION[$_sesmodulename]["sql_selmode"]   = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);
$shops      = getShops($CON);
$suppliers  = getSuppliers($CON);

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
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 100);

$datsql = " select t1.id, t1.invc_date, t1.invc_docnumber, t4.item_title, t3.item_id, t3.item_type,
                   t3.item_amount, t3.item_sellprice_netto_dsc, (t3.item_sellprice_netto_dsc / t3.item_amount) 'unitsellprice',
                   t5.cust_name, t4.item_number_prod, t1.invc_childid, t1.invc_cust_id, t7.cat_id
            from invoices_sell t1
            INNER JOIN invoices_sell_parts t2         ON ( t1.id = t2.part_invc_id )
            INNER JOIN invoices_sell_parts_items t3   ON ( t2.id = t3.part_id and t2.part_invc_id = t3.invc_id )
            INNER JOIN item t4                        ON ( t3.item_id = t4.id )
            INNER JOIN customer t5                    ON ( t1.invc_cust_id = t5.id )
            LEFT OUTER JOIN item_productcats t7       ON ( t3.item_id = t7.item_id )
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_date         between {$sql_datefrom} and {$sql_dateto} and
            t3.item_type         = 'item' ";
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $datsql .= " and t1.invc_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   $datsql .= " and t7.cat_id  = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if($_SESSION[$_sesmodulename]["sql_item_type"] == "itemlist")
   $datsql .= " and 1 = 2 ";
if($_SESSION[$_sesmodulename]["sql_item_id"])
   $datsql .= " and t3.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
if($_SESSION[$_sesmodulename]["sql_docnumber"] != "")
   $datsql .= " and t1.invc_docnumber = {$_SESSION[$_sesmodulename]["sql_docnumber"]} ";
   
$datsql .= " UNION ALL
             select t1.id, t1.invc_date, t1.invc_docnumber, t4.item_title, t3.item_id, t3.item_type,
                   t3.item_amount, t3.item_sellprice_netto_dsc, (t3.item_sellprice_netto_dsc / t3.item_amount) 'unitsellprice',
                   t5.cust_name, t4.item_number_prod, t1.invc_childid, t1.invc_cust_id, t7.cat_id
            from invoices_sell t1
            INNER JOIN invoices_sell_parts t2         ON ( t1.id = t2.part_invc_id )
            INNER JOIN invoices_sell_parts_items t3   ON ( t2.id = t3.part_id and t2.part_invc_id = t3.invc_id )
            INNER JOIN itemlist t4                    ON ( t3.item_id = t4.id )
            INNER JOIN customer t5                    ON ( t1.invc_cust_id = t5.id )
            LEFT OUTER JOIN item_productcats_itemlist t7 ON ( t3.item_id = t7.item_id )
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_date         between {$sql_datefrom} and {$sql_dateto} and
            t3.item_type         = 'itemlist' ";
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $datsql .= " and t1.invc_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   $datsql .= " and t7.cat_id  = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if($_SESSION[$_sesmodulename]["sql_item_type"] == "item")
   $datsql .= " and 1 = 2 ";
if($_SESSION[$_sesmodulename]["sql_item_id"])
   $datsql .= " and t3.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
if($_SESSION[$_sesmodulename]["sql_docnumber"] != "")
   $datsql .= " and t1.invc_docnumber = {$_SESSION[$_sesmodulename]["sql_docnumber"]} ";
   
$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
//----------------------------------------------------------------------------------
$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
for($x = 0; $x < count($items) && $items != false; $x++)
{
   //----------------------------------------------------------------------------------
   if((int)$items[$x]["invc_childid"])
   {
      $sql = " select t3.item_sellprice_netto_dsc, (t3.item_sellprice_netto_dsc / t3.item_amount) 'unitsellprice'
               from invoices_sell t1
               INNER JOIN invoices_sell_parts t2         ON ( t1.id = t2.part_invc_id )
               INNER JOIN invoices_sell_parts_items t3   ON ( t2.id = t3.part_id and t2.part_invc_id = t3.invc_id )
               where
               t1.id          = {$items[$x]["invc_childid"]} and
               t3.item_id     = {$items[$x]["item_id"]} and
               t3.item_type   = '{$items[$x]["item_type"]}'
               LIMIT 0,1";
      $nadj = $CON->select($sql);
      $nadj = $nadj[0];

      $items[$x]["item_sellprice_netto_dsc"] = $nadj["item_sellprice_netto_dsc"];
      $items[$x]["unitsellprice"]            = $nadj["unitsellprice"];
   }

   //----------------------------------------------------------------------------------
   $ndscs = getCustomerNoteDiscounts($CON, $items[$x]["invc_cust_id"]);
   if($ndscs[$items[$x]["cat_id"]] > 0.00)
   {
      $items[$x]["item_sellprice_netto_dsc"] = round($items[$x]["item_sellprice_netto_dsc"] - ($items[$x]["item_sellprice_netto_dsc"] / 100 * $ndscs[$items[$x]["cat_id"]]),0);
      $items[$x]["unitsellprice"]            = round($items[$x]["item_sellprice_netto_dsc"] / $items[$x]["item_amount"],0);
   }
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.invc_importation, t1.invc_exc_rate, t3.item_costprice_netto_dsc2,
                   t3.item_costprice_import_item, t3.item_costprice_import_total,
                   t3.item_amount
            from invoices_buy t1
            INNER JOIN invoices_buy_parts t2         ON ( t1.id = t2.part_invc_id )
            INNER JOIN invoices_buy_parts_items t3   ON ( t2.id = t3.part_id and t2.part_invc_id = t3.invc_id )
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_date        <= {$items[$x]["invc_date"]} and
            t3.item_type         = '{$items[$x]["item_type"]}' and
            t3.item_id           = {$items[$x]["item_id"]}
            LIMIT 0,1";
   $itembuy = $CON->select($sql);
   $itembuy = $itembuy[0];
   if((int)$itembuy["invc_importation"])
      $items[$x]["item_costprice"] = (float)round($itembuy["item_costprice_import_item"],0);
   else
      $items[$x]["item_costprice"] = (float)round($itembuy["item_costprice_netto_dsc2"] / $itembuy["item_amount"],0);

   $items[$x]["item_costprice_total"] = $items[$x]["item_costprice"] * $items[$x]["item_amount"];
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
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

printJSsetCompanyShop($shops);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
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
   <td height="30"><b class="content_header">Margen de facturación</b></td>
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
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Número Factura</td>
         <td class="content_row">
            <input type="text" class="text" style="width:160px" onfocus="markfield(this,0)" onblur="markfield(this,1)"
            name="sql_docnumber" value="<?=$_SESSION[$_sesmodulename]["sql_docnumber"]?>">
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width='132'>            
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
                     printButton("Generar XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
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
      <?=Nifty_printH("box1", "")?>
      <table border="0" cellpadding="3" cellspacing="0" width="">
      <colgroup>
         <col>
         <col>
         <col>
         <col>
         <col>
      </colgroup>
      <tr>
         <td class="content_row_os content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_row_os content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_row_os content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_row_os content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
         <td class="content_row_os content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
         <td class="content_row_os content_tbl_subheader">Unidad</td>
         <td class="content_row_os content_tbl_subheader" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
         <td class="content_row_os content_tbl_subheader" align="right" style="border-left:3px double #333333">$/Venta/U</td>
         <td class="content_row_os content_tbl_subheader" align="right">$/Compra/U</td>
         <td class="content_row_os content_tbl_subheader" align="right" style="border-left:3px double #333333">$/Venta/T</td>
         <td class="content_row_os content_tbl_subheader" align="right">$/Compra/T</td>
         <td class="content_row_os content_tbl_subheader" align="right" style="border-left:3px double #333333">%/Margen</td>
         <td class="content_row_os content_tbl_subheader" align="right">$/Margen</td>
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         $unitdesc   = getItemUnitDesc($CON, $items[$x]["item_id"], $items[$x]["item_type"]);
         $marge_perc = 0.00;
         $marge_val  = 0.00;
         if($items[$x]["unitsellprice"] > 0.00 && $items[$x]["item_costprice"] > 0.00)
         {
            $marge_perc = round(($items[$x]["unitsellprice"] - $items[$x]["item_costprice"]) / $items[$x]["item_costprice"] * 100,2);
            $marge_val  = (float)round($items[$x]["item_sellprice_netto_dsc"] - $items[$x]["item_costprice_total"],0);
         }
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$items[$x]["invc_docnumber"]?></td>
            <td class="content_row_os"><?=date('d.m.Y', $items[$x]["invc_date"])?></td>
            <td class="content_row_os"><?=$items[$x]["cust_name"]?></td>
            <td class="content_row_os"><?=$items[$x]["item_number_prod"]?></td>
            <td class="content_row_os"><?=$items[$x]["item_title"]?></td>
            <td class="content_row_os"><?=$unitdesc?></td>
            <td class="content_row_os" align="center"><?=printPrice($items[$x]["item_amount"],2,true)?></td>
            <td class="content_row_os" align="right" style="border-left:3px double #333333"><?=printPrice($items[$x]["unitsellprice"],0,true)?></td>
            <td class="content_row_os" align="right"><?=printPrice($items[$x]["item_costprice"],0,true)?></td>
            <td class="content_row_os" align="right" style="border-left:3px double #333333"><?=printPrice($items[$x]["item_sellprice_netto_dsc"],0,true)?></td>
            <td class="content_row_os" align="right"><?=printPrice($items[$x]["item_costprice_total"],0,true)?></td>
            <td class="content_row_os" align="right" style="border-left:3px double #333333"><?=printPrice($marge_perc,2,true)?></td>
            <td class="content_row_os" align="right"><?=printPrice($marge_val,0,true)?></td>
         </tr>
         <?php
         $ges_item_amount += $items[$x]["item_amount"];
         $ges_item_sellprice_netto_dsc += $items[$x]["item_sellprice_netto_dsc"];
         $ges_item_costprice_total += $items[$x]["item_costprice_total"];

         $ges_marge_val    = (float)round($ges_item_sellprice_netto_dsc - $ges_item_costprice_total,0);
         $ges_marge_perc   = round(($ges_item_sellprice_netto_dsc - $ges_item_costprice_total) / $ges_item_costprice_total * 100,2);

         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_docnumber"]          = $items[$x]["invc_docnumber"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_date"]               = date('d.m.Y', $items[$x]["invc_date"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_name"]               = $items[$x]["cust_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]        = $items[$x]["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]              = $items[$x]["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitdesc"]                = $unitdesc;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_amount"]             = printPrice($items[$x]["item_amount"],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitsellprice"]           = printPrice($items[$x]["unitsellprice"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_costprice"]          = printPrice($items[$x]["item_costprice"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_sellprice_netto_dsc"]= printPrice($items[$x]["item_sellprice_netto_dsc"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_costprice_total"]    = printPrice($items[$x]["item_costprice_total"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["marge_perc"]              = printPrice($marge_perc,2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["marge_val"]               = printPrice($marge_val);
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="13" align="center">
               <br>
               <b class="msg_save_err">No hay datos disponibles.</b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      else
      {  ?>
         <tr>
            <td class="content_row_os content_row_totals" colspan="6">TOTAL</td>
            <td class="content_row_os content_row_totals" align="center"><?=printPrice($ges_item_amount, 2)?></td>
            <td class="content_row_os content_row_totals" align="center" style="border-left:3px double #333333">&nbsp;</td>
            <td class="content_row_os content_row_totals" align="center">&nbsp;</td>
            <td class="content_row_os content_row_totals" align="right" style="border-left:3px double #333333"><?=printPrice($ges_item_sellprice_netto_dsc, 0)?></td>
            <td class="content_row_os content_row_totals" align="right"><?=printPrice($ges_item_costprice_total, 0)?></td>
            <td class="content_row_os content_row_totals" align="right" style="border-left:3px double #333333"><?=printPrice($ges_marge_perc, 2)?></td>
            <td class="content_row_os content_row_totals" align="right"><?=printPrice($ges_marge_val, 0)?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_docnumber"]          = "TOTAL";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_amount"]             = printPrice($ges_item_amount, 2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitsellprice"]           = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_costprice"]          = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_sellprice_netto_dsc"]= printPrice($ges_item_sellprice_netto_dsc);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_costprice_total"]    = printPrice($ges_item_costprice_total);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["marge_perc"]              = printPrice($ges_marge_perc,2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["marge_val"]               = printPrice($ges_marge_val);
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
  $pdffile = doc_createItemPriceMargen($CON);

if($_REQUEST["printxls"])
  $xlsfile = xls_createItemPriceMargen($CON);

if($pdffile != "")
{
   $doctitle = "Margen-de-facturacion.pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
?>
<?php
if($xlsfile != "")
{
   $doctitle = "Margen-de-facturacion.xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>