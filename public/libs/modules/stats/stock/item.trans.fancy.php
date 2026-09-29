<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
$_sesmodulename = "stock_trans";
$items = $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$_REQUEST["itemid"]];

$key   = array_keys($items);
$key   = $key[0];
//----------------------------------------------------------------------------------
?>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130" style="padding-right:5px">
      <?php
      printButton("Imprimir PDF", "postnav", "iframe.fancy.php?module=itemtrans&itemid={$_REQUEST["itemid"]}&printpdf=1", "", "document-pdf");
      ?>
   </td>
   <td align="left" width="130">
      <?php
      printButton("Imprimir XLS", "postnav", "iframe.fancy.php?module=itemtrans&itemid={$_REQUEST["itemid"]}&printxls=1", "", "document-excel");
      ?>
   </td>
   <td>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("box1", "980")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="100">
   <col>
   <col width="100">
   <col width="120">
   <col width="100">
   <col width="120">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="6">Artículo</td>
</tr>
<tr>
   <td class="content_rowl">Nombre</td>
   <td class="content_row"><?=$items[$key]["item_title"]?></td>
   <td class="content_rowl">Número</td>
   <td class="content_row"><?=$items[$key]["item_number_prod"]?></td>
   <td class="content_rowl">Unidad</td>
   <td class="content_row"><?=$items[$key]["unit_name"]?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "980")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="80">
   <col width="150">
   <col width="65">
   <col width="65">
   <col width="65">
</colgroup>
<tr>
   <td class="content_tbl_subheader content_row_os">Fecha</td>
   <td class="content_tbl_subheader content_row_os">Documento</td>
   <td class="content_tbl_subheader content_row_os" align="center" style="border-left:3px double #333333">Entrada</td>
   <td class="content_tbl_subheader content_row_os" align="center">Salida</td>
   <td class="content_tbl_subheader content_row_os" align="center" style="border-right:3px double #333333">Saldo</td>
   <td class="content_tbl_subheader content_row_os">Cliente</td>
   <td class="content_tbl_subheader content_row_os">Observaciones</td>
   <td class="content_tbl_subheader content_row_os">Destino</td>
</tr>
<?php
$items = array_reverse($items);
for($x = 0; $x < count($items) && $items != false; $x++)
{
   $row = $items[$x];
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_os"><?=$row["FECHA"]?>&nbsp;</td>
      <td class="content_row_os"><?=$row["NUMERO DOCTO."]?>&nbsp;</td>
      <td class="content_row_os" align="center" style="color:green;border-left:3px double #333333"><?=printprice($row["ENTRADA"])?></td>
      <td class="content_row_os" align="center" style="color:red"><?=printprice($row["SALIDA"])?></td>
      <td class="content_row_os" align="center" style="border-right:3px double #333333"><?=printprice($row["SALDO"])?></td>
      <td class="content_row_os"><?=$row["CUSTNAME"]?>&nbsp;</td>
      <td class="content_row_os"><?=$row["OBSERV"]?>&nbsp;</td>
      <td class="content_row_os"><?=$row["DESTNAME"]?>&nbsp;</td>
   </tr>
   <?php
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="10" align="center" valign="middle" height="30">
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
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsItemTransDetail($CON, $_REQUEST["itemid"]);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsItemTransDetail($CON, $_REQUEST["itemid"]);

if($pdffile != "")
{
   $doctitle = "Movimientos-del-stock-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Movimientos-del-stock-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
