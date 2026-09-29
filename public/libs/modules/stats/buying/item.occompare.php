<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stock_oc_cumplc";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo" => "2", "Unidad" => "3", "Número OC" => "4",
                                "Proveedor" => "5", "Fecha" => "6", "Pendiente" => "7", "Solicitado" => "10",
                                "Recibido" => "11");

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
   $_SESSION[$_sesmodulename]["sql_format"]        = (int)$_REQUEST["sql_format"];
   $_SESSION[$_sesmodulename]["sql_number"]        = trim(addslashes($_REQUEST["sql_number"]));
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

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
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON, true);
$shops      = getShops($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t1.sord_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t1.sord_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if($_SESSION[$_sesmodulename]["sql_supplier"])
   $seasql .= " and t1.sord_supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
if($_SESSION[$_sesmodulename]["sql_number"] != "")
   $seasql .= " and t1.sord_number = '{$_SESSION[$_sesmodulename]["sql_number"]}' ";

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.supp_short
         from supplier_order t1
         INNER JOIN supplier t2 ON t1.sord_supplier_id = t2.id
         where
         t1.sord_date between {$sql_datefrom} and {$sql_dateto} and
         t1.sord_status > 1 and
         t1.sord_order_shipped = 0 and
         t1.sord_status != 4
         {$seasql}
         order by t1.id asc";
$orders = $CON->select($sql);

//----------------------------------------------------------------------------------
for($x = 0; $x < count($orders) && $orders != false; $x++)
{
   unset($ipos);
   unset($fdat);
   $sql = " select t1.invc_docnumber, t1.invc_total_netto, t1.invc_total_taxes, t1.invc_total_brutto, t3.*
            from invoices_buy t1
            INNER JOIN invoices_buy_parts t2       ON ( t1.id = t2.part_invc_id )
            INNER JOIN invoices_buy_parts_items t3 ON ( t2.part_invc_id = t3.invc_id and t3.part_id = t2.id )
            where
            t1.invc_status          > 1 and
            t1.invc_status          < 4 and
            t2.part_sord_id         = {$orders[$x]["id"]} ";
   $invoices = $CON->select($sql);
   foreach($invoices AS $invoice)
   {
      $ipos[$invoice["item_id"]][$invoice["item_type"]]["AMOUNT"] += $invoice["item_amount"];
      $ipos[$invoice["item_id"]][$invoice["item_type"]]["PRICE"]  += $invoice["item_costprice_netto_dsc2"];
      $fdat[$invoice["invc_docnumber"]]["NETTO"]   = $invoice["invc_total_netto"];
      $fdat[$invoice["invc_docnumber"]]["BRUTTO"]  = $invoice["invc_total_brutto"];
      $fdat[$invoice["invc_docnumber"]]["TAXES"]   = $invoice["invc_total_taxes"];
   }

   //----------------------------------------------------------------------------------
   $posdata = getSupplierOrderPos($CON, $orders[$x]["id"]);
   for($y = 0; $y < count($posdata) && $posdata != false; $y++)
   {
      $row = $posdata[$y];
      $posdata[$y]["_item_code"]    = $row["item_code"];
      $posdata[$y]["_invc_amount"]  = $ipos[$row["item_id"]][$row["item_type"]]["AMOUNT"];
      $posdata[$y]["_invc_price"]   = $ipos[$row["item_id"]][$row["item_type"]]["PRICE"];
   }
   $orders[$x]["_posdata"] = $posdata;
   $orders[$x]["_ivcdata"] = $fdat;
}

//----------------------------------------------------------------------------------
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
   <td height="30"><b class="content_header">Comparación Orden de compra vs Facturas</b></td>
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
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Proveedor</td>
         <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Formato OC</td>
         <td class="content_row">
            <select class="text" name="sql_format" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="0" <?php if(0 == $_SESSION[$_sesmodulename]["sql_format"]) echo "selected"?>>Solamente diferencias</option>
               <option value="1" <?php if(1 == $_SESSION[$_sesmodulename]["sql_format"]) echo "selected"?>>Orden de compra entero</option>
            </select>
         </td>
         <td class="content_rowl">Número OC</td>
         <td class="content_row">
            <input type="text" class="text" style="width:195px"
            name="sql_number" value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_number"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
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
                  if(count($orders) > 0 && $orders != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($orders) > 0 && $orders != false)
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
      <?php
      foreach($orders AS $order)
      {
         $pos_total_netto   = 0.00;
         $order_total_netto = $order["sord_total_netto"];

         foreach($order["_posdata"] AS $item)
            $pos_total_netto += $item["item_costprice_netto"] * $item["item_amount"];

         $diff_price = $pos_total_netto - $order_total_netto;
         $diff_perc  = round($diff_price / $pos_total_netto * 100,2);
         ?>
         <?=Nifty_printH("box1", "980", "idxoc_{$order["id"]}")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="90">
            <col>
            <col>
            <col>
            <col width="60">
            <col width="60">
            <col width="60">
            <col width="60">
            <col width="60">
            <col width="60">
            <col width="100">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="12"><?=$order["supp_short"]?>, <?=$order["sord_number"]?>, <?=date('d.m.Y', $order["sord_crtdat"])?>, Descuento: <?=printPrice($diff_perc,2)?>%</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os" rowspan="2">Número</td>
            <td class="content_tbl_subheader content_row_os" rowspan="2">Artículo</td>
            <td class="content_tbl_subheader content_row_os" rowspan="2">Unidad</td>
            <td class="content_tbl_subheader content_row_os" rowspan="2">Codigo/Prov.</td>
            <td class="content_tbl_subheader content_row_os" align="center" colspan="3" style="border-left:3px double black">OC</td>
            <td class="content_tbl_subheader content_row_os" align="center" colspan="3" style="border-left:3px double black">Facturas</td>
            <td class="content_tbl_subheader content_row_os" rowspan="2" align="center" style="border-left:3px double black">Estado</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os" align="center" style="border-left:3px double black">Cant.</td>
            <td class="content_tbl_subheader content_row_os" align="center">Precio/U</td>
            <td class="content_tbl_subheader content_row_os" align="center">Precio/F</td>
            <td class="content_tbl_subheader content_row_os" align="center" style="border-left:3px double black">Cant.</td>
            <td class="content_tbl_subheader content_row_os" align="center">Precio/U</td>
            <td class="content_tbl_subheader content_row_os" align="center">Precio/F</td>
         </tr>
         <?php
         $x = 0;
         $incumplcc      = 0;
         $sordid         = $order["id"];
         $order_head_dsc = $order["sord_item_netto_total"] - $order["sord_total_netto"];
         $order_head_dsc = $order_head_dsc / $order["sord_item_netto_total"] * 100;

         $ges_order_amt          = 0.00;
         $ges_order_price        = 0.00;
         $ges_order_price_total  = 0.00;
         $ges_invc_amt           = 0.00;
         $ges_invc_price         = 0.00;
         $ges_invc_price_total   = 0.00;
         
         foreach($order["_posdata"] AS $item)
         {
            $thisincumpl         = false;
            $item["item_costprice_netto_dsc"] = $item["item_costprice_netto_dsc"] - round(($item["item_costprice_netto_dsc"] / 100 * $order_head_dsc),0);
            $item["unit_name"]   = getItemUnitDesc($CON, $item["item_id"], $item["item_type"]);
            $order_amt           = $item["item_amount"];
            $order_price         = $item["item_costprice_netto_dsc"]/$item["item_amount"];
            $order_price_total   = $item["item_costprice_netto_dsc"];
            $invc_amt            = $item["_invc_amount"];
            $invc_price          = $item["_invc_price"]/$item["_invc_amount"];
            $invc_price_total    = $item["_invc_price"];

            if(round($order_amt,2) != round($invc_amt,2) || round($order_price_total,2) != round($invc_price_total,2))
            {
               $statecss = "#FFCED4";
               $statetxt = "Incumplido";
               $incumplcc++;
               $thisincumpl = true;
            }
            else
            {
               $statecss = "#ADFFB6";
               $statetxt = "Cumplido";
            }

            if((int)$_SESSION[$_sesmodulename]["sql_format"] || (!(int)$_SESSION[$_sesmodulename]["sql_format"] && $thisincumpl))
            {  ?>
               <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os"><?=$item["item_number_prod"]?></td>
                  <td class="content_row_os"><?=$item["item_title"]?></td>
                  <td class="content_row_os"><?=$item["unit_name"]?>&nbsp;</td>
                  <td class="content_row_os"><?=$item["_item_code"]?>&nbsp;</td>
                  <td class="content_row_os" align="center" style="border-left:3px double black"><?=printPrice($order_amt,2)?></td>
                  <td class="content_row_os" align="center"><?=printPrice($order_price,2)?></td>
                  <td class="content_row_os" align="center"><?=printPrice($order_price_total,2)?></td>
                  <td class="content_row_os" align="center" style="border-left:3px double black"><?=printPrice($invc_amt,2)?></td>
                  <td class="content_row_os" align="center"><?=printPrice($invc_price,2)?></td>
                  <td class="content_row_os" align="center"><?=printPrice($invc_price_total,2)?></td>
                  <td class="content_row_os" align="center" style="border-left:3px double black;background-color:<?=$statecss?>"><?=$statetxt?></td>
               </tr>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["item_number_prod"]     = $item["item_number_prod"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["item_title"]           = $item["item_title"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["_item_code"]           = $item["_item_code"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["unit_name"]            = $item["unit_name"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["order_amt"]            = printPrice($order_amt,2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["order_price"]          = printPrice($order_price,2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["order_price_total"]    = printPrice($order_price_total,2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["invc_amt"]             = printPrice($invc_amt,2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["invc_price"]           = printPrice($invc_price,2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["invc_price_total"]     = printPrice($invc_price_total,2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["state"]                = $statetxt;
               
               $ges_order_amt           += $order_amt;
               $ges_order_price         += $order_price;
               $ges_order_price_total   += $order_price_total;
               $ges_invc_amt            += $invc_amt;
               $ges_invc_price          += $invc_price;
               $ges_invc_price_total    += $invc_price_total;
               $x++;
            }
         }
         if((int)$_SESSION[$_sesmodulename]["sql_format"] || (!(int)$_SESSION[$_sesmodulename]["sql_format"] && (int)$incumplcc))
         {
            ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row_totals content_row_os" colspan="4">Total</td>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["item_number_prod"]     = "<b>Total</b>";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["order_amt"]            = "<b>".printPrice($ges_order_amt,2)."</b>";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["order_price_total"]    = "<b>".printPrice($ges_order_price_total,2)."</b>";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["invc_amt"]             = "<b>".printPrice($ges_invc_amt,2)."</b>";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["invc_price_total"]     = "<b>".printPrice($ges_invc_price_total,2)."</b>";
               $x++;
               ?>
               <td class="content_row_totals content_row_os" align="center" style="border-left:3px double black"><?=printPrice($ges_order_amt,2)?></td>
               <td class="content_row_totals content_row_os" align="center">&nbsp;</td>
               <td class="content_row_totals content_row_os" align="center"><?=printPrice($ges_order_price_total,2)?></td>
               <td class="content_row_totals content_row_os" align="center" style="border-left:3px double black"><?=printPrice($ges_invc_amt,2)?></td>
               <td class="content_row_totals content_row_os" align="center">&nbsp;</td>
               <td class="content_row_totals content_row_os" align="center"><?=printPrice($ges_invc_price_total,2)?></td>
               <td class="content_row_totals content_row_os" align="center" style="border-left:3px double black">&nbsp;</td>
            </tr>
            <?php
            foreach(array_keys($order["_ivcdata"]) AS $sinvc)
            {  ?>
               <tr>
                  <td class="content_row_os" colspan="4">Factura: <?=$sinvc?></td>
                  <td class="content_row_os" colspan="2" style="border-left:3px double black" align="center"><nobr>NETO:  $ <?=printPrice($order["_ivcdata"][$sinvc]["NETTO"],2)?></nobr></td>
                  <td class="content_row_os" colspan="2" align="center"><nobr>IVA:   $ <?=printPrice($order["_ivcdata"][$sinvc]["TAXES"],2)?></nobr></td>
                  <td class="content_row_os" colspan="2" align="center"><nobr>BRUTO: $ <?=printPrice($order["_ivcdata"][$sinvc]["BRUTTO"],2)?></nobr></td>
                  <td class="content_row_os" align="center" style="border-left:3px double black">&nbsp;</td>
               </tr>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["item_title"]           = "<b>Factura: {$sinvc}</b>";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["order_amt"]            = "<b>".printPrice($order["_ivcdata"][$sinvc]["NETTO"],2)."</b>";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["order_price"]          = "<b>".printPrice($order["_ivcdata"][$sinvc]["TAXES"],2)."</b>";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["order_price_total"]    = "<b>".printPrice($order["_ivcdata"][$sinvc]["BRUTTO"],2)."</b>";
               
               $x++;
            }
            ?>
            </table>
            <?=Nifty_printF()?>
            <br>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["INCLUDEDATA"][$sordid]["SUPP"] = $order["supp_short"];
            $_SESSION["STATS"][$_sesmodulename]["INCLUDEDATA"][$sordid]["NUMB"] = $order["sord_number"];
            $_SESSION["STATS"][$_sesmodulename]["INCLUDEDATA"][$sordid]["DATE"] = date('d.m.Y', $order["sord_crtdat"]);
         }
         else
         {  ?>
            </table>
            <?=Nifty_printF()?>
            <script language="JavaScript">
               $('#idxoc_<?=$order["id"]?>').hide();
            </script>
            <?php
         }
      }
      ?>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsOCCompare($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsOCCompare($CON);
  
if($pdffile != "")
{
   $doctitle = "Cumplimiento-OC-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Cumplimiento-OC-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>