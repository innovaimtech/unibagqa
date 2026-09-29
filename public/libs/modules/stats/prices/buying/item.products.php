<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_buy_evolution";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array();
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

unset($_SESSION["STATS"][$_sesmodulename]);
//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);
$sellers = getSellers($CON);

$curent = date("Y", time());
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_item_id"]       = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"]     = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_issue"]         = (int)$_REQUEST["sql_issue"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_seller"]        = (int)$_REQUEST["sql_seller"];
   $_SESSION[$_sesmodulename]["sql_yearcount"]     = (int)$_REQUEST["sql_yearcount"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = $curent;
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
if($_SESSION[$_sesmodulename]["sql_valtype"] == "")
   $_SESSION[$_sesmodulename]["sql_valtype"] = "invc_total_netto";
if(!(int)$_SESSION[$_sesmodulename]["sql_yearcount"])
   $_SESSION[$_sesmodulename]["sql_yearcount"] = 4;
if(!is_array($_SESSION[$_sesmodulename]["sql_trantype"]))
   $_SESSION[$_sesmodulename]["sql_trantype"] = Array( 0 => 0, 1 => 1, 2 => 2 );

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

$sql_year_init    = (int)date('Y', $exdatefrom);
$sql_month_init   = (int)date('m', $exdatefrom);
$sql_day_init     = (int)date('d', $exdatefrom);
$sql_year_end     = (int)date('Y', $sql_dateto);
$sql_month_end    = (int)date('m', $sql_dateto);
$sql_day_end      = (int)date('d', $sql_dateto);

$init_month = $_SESSION[$_sesmodulename]["sql_month1"];
$end_month  = $_SESSION[$_sesmodulename]["sql_month2"];
$end_year   = $_SESSION[$_sesmodulename]["sql_year1"];

$init_year  = $end_year - $_SESSION[$_sesmodulename]["sql_yearcount"];

$_SESSION["STATS"][$_sesmodulename]["INIT_YEAR"]   = $init_year;
$_SESSION["STATS"][$_sesmodulename]["END_YEAR"]    = $end_year;
$_SESSION["STATS"][$_sesmodulename]["INIT_MONTH"]  = $init_month;
$_SESSION["STATS"][$_sesmodulename]["END_MONTH"]   = $end_month;
$_SESSION["STATS"][$_sesmodulename]["DATA"]        = $_ITEMS;

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

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
         array_push($selshops, $shop);
}

//----------------------------------------------------------------------------------
$sql_startdate = mktime(0, 0, 0, $init_month, 1, $init_year);
$sql_enddate   = mktime(0, 0, 0, $end_month, 15, $end_year);
$sql_enddate   = mktime(0, 0, 0, $end_month, date("t",$sql_enddate) , $end_year);

// Ajustes con motivo "compras nacionales"
$sql = "";
if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
{
   if(array_search(0, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false)
   {
      $sql = " SELECT t1.stk_bookdate, t2.item_amount
               from stockchanges t1
               INNER JOIN stockchanges_items t2 ON t1.id = t2.stk_id
               where
               t1.stk_status     > 1 and
               t1.stk_issueid    = 4 and
               t1.stk_bookdate   between {$sql_startdate} and {$sql_enddate} and
               t2.item_id        = {$_SESSION[$_sesmodulename]["sql_item_id"]} and
               t2.item_type      = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
      if($_SESSION[$_sesmodulename]["sql_company"])
         $sql .= " and t1.stk_companyid = {$_SESSION[$_sesmodulename]["sql_company"]} ";
      if($_SESSION[$_sesmodulename]["sql_shop"])
         $sql .= " and t1.stk_shopid = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   }
   if(array_search(1, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false)
   {
      if($sql != "")
         $sql .= " UNION ALL ";

      $sql .= " SELECT t1.stk_bookdate, t2.item_amount
               from stockchanges t1
               INNER JOIN stockchanges_items t2 ON t1.id = t2.stk_id
               where
               t1.stk_status     > 1 and
               t1.stk_issueid    = 3 and
               t1.stk_bookdate   between {$sql_startdate} and {$sql_enddate} and
               t2.item_id        = {$_SESSION[$_sesmodulename]["sql_item_id"]} and
               t2.item_type      = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
      if($_SESSION[$_sesmodulename]["sql_company"])
         $sql .= " and t1.stk_companyid = {$_SESSION[$_sesmodulename]["sql_company"]} ";
      if($_SESSION[$_sesmodulename]["sql_shop"])
         $sql .= " and t1.stk_shopid = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   }
   if(array_search(2, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false)
   {
      if($sql != "")
         $sql .= " UNION ALL ";

      $sql .= " SELECT t1.invc_date 'stk_bookdate', t2.item_amount
               from invoices_buy t1
               INNER JOIN invoices_buy_parts_items t2 ON t1.id = t2.invc_id
               where
               t1.invc_status    > 1 and
               t1.invc_date   between {$sql_startdate} and {$sql_enddate} and
               t2.item_id        = {$_SESSION[$_sesmodulename]["sql_item_id"]} and
               t2.item_type      = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
      if($_SESSION[$_sesmodulename]["sql_company"])
         $sql .= " and t1.invc_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
      if($_SESSION[$_sesmodulename]["sql_shop"])
         $sql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   }
   $items = $CON->select($sql);

   for($x = 0; $x < count($items) && $items != false; $x++)
   {
      $row = $items[$x];

      $tran_year     = date("Y", $row["stk_bookdate"]);
      $tran_month    = (int)date("m", $row["stk_bookdate"]);
      $_ITEMS[$tran_year][$tran_month]["ENTRADA"] += $row["item_amount"];
      $_TOTAL[$tran_year]["ENTRADA"] += $row["item_amount"];
   }

   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $_CURRSTOCK = getItemShopCurrentStock($CON, $_SESSION[$_sesmodulename]["sql_shop"],
                                           $_SESSION[$_sesmodulename]["sql_item_id"],
                                           $_SESSION[$_sesmodulename]["sql_item_type"]);

   }
   else
   {
      foreach($selshops AS $selshop)
      {
         $_CURRSTOCK += getItemShopCurrentStock($CON, $selshop["id"],
                                                $_SESSION[$_sesmodulename]["sql_item_id"],
                                                $_SESSION[$_sesmodulename]["sql_item_type"]);
      }
   }
}

printJSsetCompanyShop($shops);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Compras por productos</b></td>
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
      onsubmit="return checkform(new Array(this.sql_company, this.item_id))">
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
                  &nbsp;-&nbsp;
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
                  <span style="<?if((int)$_SESSION[$_sesmodulename]["sql_mode"]) echo "display:none"?>">
                  <?/*?>
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
                  <?*/?>
                  </nobr>
                  </span>
               </td>
            </tr>
            </table>
         </td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Comparar</td>
         <td class="content_row" colspan="3">
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
         <td class="content_row" colspan="3">
            <input type="checkbox" name="sql_trantype[]" value="0" <?php if(array_search(0, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false) echo "checked"?>>Compras nacionales (ajustes)
            <input type="checkbox" name="sql_trantype[]" value="1" <?php if(array_search(1, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false) echo "checked"?>>Compras importación (ajustes)
            <input type="checkbox" name="sql_trantype[]" value="2" <?php if(array_search(2, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false) echo "checked"?>>Facturas
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
                  /*
                  if(count($items) > 0 && $items != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  */
                  ?>&nbsp;
               </td>
               <td align="left">
                  <?php
                  /*
                  if(count($items) > 0 && $items != false)
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  */
                  ?>&nbsp;
               </td>
               <td align="right" width="130" style="padding-right:3px">
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
      <table border="0" cellpadding="3" cellspacing="0" style="table-layout:fixed">
      <colgroup>
         <col width="85">
         <col width="40">
         <?php
         for($y = $init_year; $y <= $end_year; $y++)
         {  ?>
            <col width="80">
            <?php
         }
         ?>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="<?=(5 + ($_SESSION[$_sesmodulename]["sql_yearcount"] * 3))?>">
            Comparación de compras / Stock actual: <?=printPrice($_CURRSTOCK,0)?>
         </td>
      </tr>
      <tr>
         <td class="content_row_os content_tbl_subheader" colspan="2" rowspan="2"><b>Mes</b></td>
         <?php
         for($y = $init_year; $y <= $end_year; $y++)
         {  ?>
            <td class="content_row_os content_tbl_subheader" align="center" style="border-left:3px double black"><b><?=$y?></b></td>
            <?php
         }
         ?>
      </tr>
      <tr>
         <?php
         for($y = $init_year; $y <= $end_year; $y++)
         {  ?>
            <td class="content_row_os content_tbl_subheader" align="center" style="border-left:3px double black">Cantidad</td>
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
            for($y = $init_year; $y <= $end_year; $y++)
            {
               ?>
               <td class="content_row_os" align="center" style="border-left:3px double black"><?=printPrice($_ITEMS[$y][$x]["ENTRADA"],0,true)?></td>
               <?php
            }
            ?>
         </tr>
         <?php
      }
      ?>
      <tr>
         <td class="content_row_os content_row_totals" colspan="2" rowspan="2"><b>TOTAL</b></td>
         <?php
         for($y = $init_year; $y <= $end_year; $y++)
         {
            ?>
            <td class="content_row_os content_row_totals" align="center" style="border-left:3px double black"><?=printPrice($_TOTAL[$y]["ENTRADA"],0);?></td>
            <?php
         }
         ?>
      </tr>
      </table>
      <?=Nifty_printF()?>
      <br>
   </td>
</tr>
</table>
<?php
/*
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
*/
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
