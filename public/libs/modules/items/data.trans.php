<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_prod_movis";
unset($_SESSION["STATS"][$_sesmodulename]);
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
$_REQUEST["sql_company"] = $_SESSION["user_company_id"];

if($_REQUEST["sql_datefrom"] == "")
   $_REQUEST["sql_datefrom"] = date('01.01.Y');
if($_REQUEST["sql_dateto"] == "")
   $_REQUEST["sql_dateto"] = date('d.m.Y');

//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);
$shops      = getShops($CON);

//----------------------------------------------------------------------------------
if((int)$_REQUEST["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_REQUEST["sql_company"])
         array_push($selshops, $shop);
}
   
//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
printJSsetCompanyShop($shops);
?>
<form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="search">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="itemtype" value="<?=$_REQUEST["itemtype"]?>">
<input type="hidden" name="printpdf" value="0">
<input type="hidden" name="printxls" value="0">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="100">
   <col>
   <col width="100">
   <col width="400">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
</tr>
<tr>
   <td class="content_rowl">Periodo</td>
   <td class="content_row">
      <input type="text" style="width:70px" id="sql_datefrom" name="sql_datefrom"
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$_REQUEST["sql_datefrom"]?>">
      &nbsp;-&nbsp;
      <input type="text" style="width:70px" id="sql_dateto" name="sql_dateto"
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$_REQUEST["sql_dateto"]?>">
   </td>
   <td class="content_rowl">Empresa</td>
   <td class="content_row">
      <select class="text" name="sql_company" style="width:300px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)"
      onchange="setCompanyShop(this.value)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($companies AS $company)
         {  ?>
            <option value="<?=$company["id"]?>"
            <?php if($company["id"] == $_REQUEST["sql_company"]) echo "selected"?>><?=$company["company_short"]?></option><?php
         }
         ?>
      </select>
   </td>

</tr>
<tr>
   <td class="content_rowl">&nbsp;</td>
   <td class="content_row">&nbsp;</td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row">
      <select class="text" name="sql_shop" style="width:300px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)"
      onchange="setCompanyShopStorehouse(this.value)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($selshops AS $selshop)
         {  ?>
            <option value="<?=$selshop["id"]?>"
            <?php if($selshop["id"] == $_REQUEST["sql_shop"]) echo "selected"?>><?=$selshop["shop_name"]?>
            </option><?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_row" align="right" colspan="4">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <colgroup>
         <col width="132">
         <col>
         <col width="132">
      </colgroup>
      <tr>
         <td align="left">
            <?php
            printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
            $_SESSION["_SUBMITBTN"] = 1;
            ?>
         </td>
         <td align="left">
            <?php
            printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
            $_SESSION["_SUBMITBTN"] = 1;
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
<?php
//----------------------------------------------------------------------------------
$_REQUEST["sql_company"]   = (int)$_REQUEST["sql_company"];
$_REQUEST["sql_shop"]      = (int)$_REQUEST["sql_shop"];
$sqldate_from = getDateFromString($_REQUEST["sql_datefrom"]);
$sqldate_to   = getDateFromString($_REQUEST["sql_dateto"], false);


$_SESSION["HEADER"][$_sesmodulename]["PROD"] = $item[0]["item_number_prod"]." - ".$item[0]["item_title"];
$_SESSION["HEADER"][$_sesmodulename]["FROM"] = $_REQUEST["sql_datefrom"];
$_SESSION["HEADER"][$_sesmodulename]["TO"]   = $_REQUEST["sql_dateto"];

$sql = " select t1.id, t1.invc_docnumber, t1.invc_date, 'type' 'invoicesell', t4.item_amount,
                t5.cust_name, t5.cust_rut, t4.item_sellprice_netto, t4.item_sellprice_netto_dsc
         from invoices_sell t1
         LEFT OUTER JOIN company_shops t2          ON t1.invc_shop_id = t2.id
         LEFT OUTER JOIN company_data  t3          ON t1.invc_company_id = t3.id
         INNER JOIN invoices_sell_parts_items t4   ON t1.id = t4.invc_id
         LEFT OUTER JOIN customer t5               ON t1.invc_cust_id = t5.id
         where
         t1.invc_status          > 1 and
         t1.invc_status          < 4 and
         t1.invc_date            between {$sqldate_from} and {$sqldate_to} and
         t4.item_id              = {$_REQUEST["id"]} and
         t4.item_type            = '{$_REQUEST["itemtype"]}' ";
if($_REQUEST["sql_company"])
   $sql .= " and t1.invc_company_id = {$_REQUEST["sql_company"]} ";
if($_REQUEST["sql_shop"])
   $sql .= " and t1.invc_shop_id = {$_REQUEST["sql_shop"]} ";
$sql .= " UNION ALL
         select t1.id, t1.invc_docnumber, t1.invc_date, 'type' 'invoicebuy', t4.item_amount,
                t5.supp_short 'cust_name', t5.supp_rut 'cust_rut', t4.item_costprice_netto 'item_sellprice_netto',
                t4.item_costprice_netto_dsc2 'item_sellprice_netto_dsc'
         from invoices_buy t1
         LEFT OUTER JOIN company_shops t2          ON t1.invc_shop_id = t2.id
         LEFT OUTER JOIN company_data  t3          ON t1.invc_company_id = t3.id
         INNER JOIN invoices_buy_parts_items t4    ON t1.id = t4.invc_id
         LEFT OUTER JOIN supplier t5               ON t1.invc_supplier_id = t5.id
         where
         t1.invc_status          > 1 and
         t1.invc_status          < 4 and
         t1.invc_date            between {$sqldate_from} and {$sqldate_to} and
         t4.item_id              = {$_REQUEST["id"]} and
         t4.item_type            = '{$_REQUEST["itemtype"]}' ";
if($_REQUEST["sql_company"])
   $sql .= " and t1.invc_company_id = {$_REQUEST["sql_company"]} ";
if($_REQUEST["sql_shop"])
   $sql .= " and t1.invc_shop_id = {$_REQUEST["sql_shop"]} ";
$sql .= " UNION ALL
         select t1.id, t1.note_docnumber 'invc_docnumber', t1.note_date 'invc_date', 'type' 'notesellcred', t4.item_amount,
                t5.cust_name, t5.cust_rut, t4.item_sellprice_netto, t4.item_sellprice_netto_dsc
         from invoices_notes_sell t1
         LEFT OUTER JOIN company_shops t2          ON t1.note_shop_id = t2.id
         LEFT OUTER JOIN company_data  t3          ON t1.note_company_id = t3.id
         INNER JOIN invoices_notes_sell_items t4   ON t1.id = t4.note_id
         LEFT OUTER JOIN customer t5               ON t1.note_cust_id = t5.id
         where
         t1.note_status          > 1 and
         t1.note_status          < 4 and
         t1.note_type            = 1 and
         t1.note_date            between {$sqldate_from} and {$sqldate_to} and
         t4.item_id              = {$_REQUEST["id"]} and
         t4.item_type            = '{$_REQUEST["itemtype"]}' ";
if($_REQUEST["sql_company"])
   $sql .= " and t1.note_company_id = {$_REQUEST["sql_company"]} ";
if($_REQUEST["sql_shop"])
   $sql .= " and t1.note_shop_id = {$_REQUEST["sql_shop"]} ";
$sql .= " UNION ALL
         select t1.id, t1.note_docnumber 'invc_docnumber', t1.note_date 'invc_date', 'type' 'noteselldeb', t4.item_amount,
                t5.cust_name, t5.cust_rut, t4.item_sellprice_netto, t4.item_sellprice_netto_dsc
         from invoices_notes_sell t1
         LEFT OUTER JOIN company_shops t2          ON t1.note_shop_id = t2.id
         LEFT OUTER JOIN company_data  t3          ON t1.note_company_id = t3.id
         INNER JOIN invoices_notes_sell_items t4   ON t1.id = t4.note_id
         LEFT OUTER JOIN customer t5               ON t1.note_cust_id = t5.id
         where
         t1.note_status          > 1 and
         t1.note_status          < 4 and
         t1.note_type            = 2 and
         t1.note_date            between {$sqldate_from} and {$sqldate_to} and
         t4.item_id              = {$_REQUEST["id"]} and
         t4.item_type            = '{$_REQUEST["itemtype"]}' ";
if($_REQUEST["sql_company"])
   $sql .= " and t1.note_company_id = {$_REQUEST["sql_company"]} ";
if($_REQUEST["sql_shop"])
   $sql .= " and t1.note_shop_id = {$_REQUEST["sql_shop"]} ";
$sql .= " UNION ALL
         select t1.id, t1.note_docnumber 'invc_docnumber', t1.note_date 'invc_date', 'type' 'notebuycred', t4.item_amount,
                t5.supp_short 'cust_name', t5.supp_rut 'cust_rut', t4.item_costprice_netto 'item_sellprice_netto',
                t4.item_costprice_netto_dsc 'item_sellprice_netto_dsc'
         from invoices_notes_buy t1
         LEFT OUTER JOIN company_shops t2          ON t1.note_shop_id = t2.id
         LEFT OUTER JOIN company_data  t3          ON t1.note_company_id = t3.id
         INNER JOIN invoices_notes_buy_items t4    ON t1.id = t4.note_id
         LEFT OUTER JOIN supplier t5               ON t1.note_supplier_id = t5.id
         where
         t1.note_status          > 1 and
         t1.note_status          < 4 and
         t1.note_type            = 1 and
         t1.note_date            between {$sqldate_from} and {$sqldate_to} and
         t4.item_id              = {$_REQUEST["id"]} and
         t4.item_type            = '{$_REQUEST["itemtype"]}' ";
if($_REQUEST["sql_company"])
   $sql .= " and t1.note_company_id = {$_REQUEST["sql_company"]} ";
if($_REQUEST["sql_shop"])
   $sql .= " and t1.note_shop_id = {$_REQUEST["sql_shop"]} ";
$sql .= " UNION ALL
         select t1.id, t1.note_docnumber 'invc_docnumber', t1.note_date 'invc_date', 'type' 'notebuydeb', t4.item_amount,
                t5.supp_short 'cust_name', t5.supp_rut 'cust_rut', t4.item_costprice_netto 'item_sellprice_netto',
                t4.item_costprice_netto_dsc 'item_sellprice_netto_dsc'
         from invoices_notes_buy t1
         LEFT OUTER JOIN company_shops t2          ON t1.note_shop_id = t2.id
         LEFT OUTER JOIN company_data  t3          ON t1.note_company_id = t3.id
         INNER JOIN invoices_notes_buy_items t4    ON t1.id = t4.note_id
         LEFT OUTER JOIN supplier t5               ON t1.note_supplier_id = t5.id
         where
         t1.note_status          > 1 and
         t1.note_status          < 4 and
         t1.note_type            = 2 and
         t1.note_date            between {$sqldate_from} and {$sqldate_to} and
         t4.item_id              = {$_REQUEST["id"]} and
         t4.item_type            = '{$_REQUEST["itemtype"]}' ";
if($_REQUEST["sql_company"])
   $sql .= " and t1.note_company_id = {$_REQUEST["sql_company"]} ";
if($_REQUEST["sql_shop"])
   $sql .= " and t1.note_shop_id = {$_REQUEST["sql_shop"]} ";
$sql .= " UNION ALL
          select tran_id 'id', tran_number 'invc_docnumber',
          UNIX_TIMESTAMP(CONCAT(tran_year, '-', tran_month, '-', tran_day, ' 13:00:00')) 'invc_date',
          tran_type 'type', tran_amount 'item_amount',
          '' '', '' '',  item_costprice_avg_netto 'item_sellprice_netto', item_costprice_avg_netto * tran_amount 'item_sellprice_netto_dsc'
          from tran_data t1
          where
          item_id       = {$_REQUEST["id"]} and
          tran_crtdat   between {$sqldate_from} and {$sqldate_to} and
          tran_type     IN ('stockchangeup', 'transup', 'sthup', 'sthdown', 'stockchangedown', 'transdown' ) ";
if($_REQUEST["sql_company"])
   $sql .= " and t1.tran_company_id = {$_REQUEST["sql_company"]} ";
if($_REQUEST["sql_shop"])
   $sql .= " and t1.tran_shop_id = {$_REQUEST["sql_shop"]} ";
$sql .= " order by 3 desc,1 desc";
$data = $CON->select($sql);
?>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col>
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="13">Movimientos</td>
</tr>
<tr>
   <td class="content_row_os content_tbl_subheader">Fecha</td>
   <td class="content_row_os content_tbl_subheader">Tipo</td>
   <td class="content_row_os content_tbl_subheader">Número</td>
   <td class="content_row_os content_tbl_subheader" align="center">Entrada</td>
   <td class="content_row_os content_tbl_subheader" align="center">Salida</td>
<!--    <td class="content_row_os content_tbl_subheader" align="center">Stock</td> -->
   <td class="content_row_os content_tbl_subheader" align="right">Precio/V</td>
   <td class="content_row_os content_tbl_subheader" align="right">Precio/C</td>
   <td class="content_row_os content_tbl_subheader" align="right">Descuentos</td>
   <td class="content_row_os content_tbl_subheader" align="right">Desc/%</td>
   <td class="content_row_os content_tbl_subheader" align="right">Total</td>
   <td class="content_row_os content_tbl_subheader">RUT</td>
   <td class="content_row_os content_tbl_subheader">Cliente/Prov.</td>
</tr>
<?php
for($x = 0; $x < count($data) && $data != false; $x++)
{
   $amtpos = 0.00;
   $negpos = 0.00;
   $amtprc = 0.00;
   $negprc = 0.00;

   switch($data[$x]["type"])
   {
      case "typeinvoicesell":    $addamount = false;  $type = "Factura"; break;
      case "typeinvoicebuy":     $addamount = true;   $type = "Factura"; break;
      case "typenotesellcred":   $addamount = true;   $type = "Nota de credito"; break;
      case "typenoteselldeb":    $addamount = false;  $type = "Nota de debito"; break;
      case "typenotebuycred":    $addamount = false;  $type = "Nota de credito"; break;
      case "typenotebuydeb":     $addamount = true;   $type = "Nota de debito"; break;
      default:                   $addamount = $_CONFIGTRANTYPES[$data[$x]["type"]];
                                 $type = $_CONFIGTRANTYPESNAMES[$data[$x]["type"]];
                                 break;
   }
   if($addamount)
   {
      $amtpos = $data[$x]["item_amount"];
      $negprc = $data[$x]["item_sellprice_netto"];
   }
   else
   {
      $negpos = $data[$x]["item_amount"];
      $amtprc = $data[$x]["item_sellprice_netto"];
   }

   $dscgespos = ($data[$x]["item_sellprice_netto"] * $data[$x]["item_amount"]) - $data[$x]["item_sellprice_netto_dsc"];

   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_os"><?=date('d.m.Y', $data[$x]["invc_date"])?></td>
      <td class="content_row_os"><?=$type?></td>
      <td class="content_row_os"><?=$data[$x]["invc_docnumber"]?></td>
      <td class="content_row_os" align="center"><?=printPrice($amtpos,2,true)?></td>
      <td class="content_row_os" align="center"><?=printPrice($negpos,2,true)?></td>
<!--       <td class="content_row_os" align="center">- - -</td> -->
      <td class="content_row_os" align="right"><?=printPrice($amtprc,2)?></td>
      <td class="content_row_os" align="right"><?=printPrice($negprc,2)?></td>
      <td class="content_row_os" align="right"><?=printPrice($dscgespos,2)?></td>
      <td class="content_row_os" align="right"><?=printPrice($dscgespos/($data[$x]["item_sellprice_netto_dsc"]+$dscgespos)*100,2)?></td>
      <td class="content_row_os" align="right"><?=printPrice($data[$x]["item_sellprice_netto_dsc"],2)?></td>
      <td class="content_row_os"><?=$data[$x]["cust_rut"]?>&nbsp;</td>
      <td class="content_row_os"><?=$data[$x]["cust_name"]?>&nbsp;</td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["DATE"]     = date('d.m.Y', $data[$x]["invc_date"]);
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TYPE"]     = $type;
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NUMBR"]    = $data[$x]["invc_docnumber"];
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["amtpos"]   = printPrice($amtpos,2);
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["negpos"]   = printPrice($negpos,2);
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["amtprc"]   = printPrice($amtprc,2);
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["negprc"]   = printPrice($negprc,2);
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["dscges"]   = printPrice($dscgespos,2);
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["dsc"]      = printPrice($dscgespos/($data[$x]["item_sellprice_netto_dsc"]+$dscgespos)*100,2);
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["netto"]    = printPrice($data[$x]["item_sellprice_netto_dsc"],2);
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["RUT"]      = $data[$x]["cust_rut"];
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NAME"]     = $data[$x]["cust_name"];
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="13" align="center" valign="middle" height="30">
         <b class="msg_save_err">No hay datos disponibles.</b>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsProductsMovis($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsProductsMovis($CON);
  
if($pdffile != "")
{
   $doctitle = "Movimientos-producto-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Movimientos-producto-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>