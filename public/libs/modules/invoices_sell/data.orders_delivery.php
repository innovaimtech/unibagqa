<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from invoices_sell t1
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
if($_REQUEST["genmode"] == "addtxt")
{
   foreach($_REQUEST["txtadd"] AS $dlvid)
   {
      invoiceSellAddDelivery($CON, $_REQUEST["id"], $dlvid);
      if((int)$headdata["invc_dlv_addtxt"])
         invoiceSellConvertShpToDesc($CON, $_REQUEST["id"], $dlvid);
   }
}

//----------------------------------------------------------------------------------
$invcparts  = getInvoiceSellParts($CON, $_REQUEST["id"]);
foreach($invcparts AS $invcpart)
   $extshpids .= $invcpart["part_dlv_id"].",";
$extshpids = substr($extshpids, 0, -1);

//----------------------------------------------------------------------------------
if($_REQUEST["sql_datefrom"] == "")
   $_REQUEST["sql_datefrom"] = date('d.m.Y', time() - (86400 * 365));
if($_REQUEST["sql_dateto"] == "")
   $_REQUEST["sql_dateto"] = date('d.m.Y');

//----------------------------------------------------------------------------------
$_REQUEST["dlv_num"]    = trim(addslashes($_REQUEST["dlv_num"]));
$_REQUEST["dlv_docnum"] = trim(addslashes($_REQUEST["dlv_docnum"]));
$_REQUEST["req_number"] = trim(addslashes($_REQUEST["req_number"]));
$sqldate_from           = getDateFromString($_REQUEST["sql_datefrom"]);
$sqldate_to             = getDateFromString($_REQUEST["sql_dateto"], false);

//----------------------------------------------------------------------------------
$sql = " select t1.dlv_num, t1.dlv_docnum, t1.dlv_delivery_date, t1.dlv_delivery_date, t1.id, t2.req_number
         from orders_delivery t1
         LEFT OUTER JOIN orders t2 ON t1.dlv_order_id = t2.id
         where
         t1.dlv_status        = 2 and
         t1.dlv_invoice_generated = 0 and
         t1.dlv_mode          <= 2 and
         t1.dlv_invoiced      = 0 and
         t1.dlv_company_id    = {$headdata["invc_company_id"]} and
         t1.dlv_shop_id       = {$headdata["invc_shop_id"]} and
         t1.dlv_cust_id       = {$headdata["invc_cust_id"]} and
         t1.dlv_delivery_date between {$sqldate_from} and {$sqldate_to} ";
         
if($_REQUEST["dlv_num"] != "")
   $sql .= " and t1.dlv_num like '%{$_REQUEST["dlv_num"]}%' ";
if($_REQUEST["dlv_docnum"] != "")
   $sql .= " and t1.dlv_docnum like '%{$_REQUEST["dlv_docnum"]}%' ";
if($_REQUEST["req_number"] != "")
   $sql .= " and t2.req_number like '%{$_REQUEST["req_number"]}%' ";
if($extshpids != "")
   $sql .= " and t1.id NOT IN ({$extshpids}) ";

   
$sql .= " order by t1.id desc";
$shipments = $CON->select($sql);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
         <col width="130">
         <col>
         <col width="130">
         <col>
         <col width="100">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="8">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Número</td>
         <td class="content_row">
            <input name="dlv_num" type="text" class="text" style="width:75px"
            value="<?=str_replace("%","*",$_REQUEST["dlv_num"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_rowl">Numero guia</td>
         <td class="content_row">
            <input name="dlv_docnum" type="text" class="text" style="width:75px"
            value="<?=str_replace("%","*",$_REQUEST["dlv_docnum"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_rowl">Confirm. de Compra</td>
         <td class="content_row">
            <input name="req_number" type="text" class="text" style="width:75px"
            value="<?=str_replace("%","*",$_REQUEST["req_number"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
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
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="8">
            <table border="0" cellpadding="0" cellspacing="0" width="270">
            <tr>
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
      <form action="index.php" method="post" name="xform_addtxt" style="padding:0px;margin:0px">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="genmode" value="addtxt">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
      <?=Nifty_printH("box1", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="20">
         <col>
         <col>
         <col>
         <col>
         <col width="160">
      </colgroup>
      <tr>
         <td class="content_tbl_subheader" align="center">
            <input type="checkbox" class="checkbox" onclick="$('.selchk').attr('checked', this.checked)">
         </td>
         <td class="content_tbl_subheader">Numero</td>
         <td class="content_tbl_subheader">Numero guia</td>
         <td class="content_tbl_subheader">Confirm. compra</td>
         <td class="content_tbl_subheader">Fecha despacho</td>
         <td class="content_tbl_subheader" align="center">Opciones</td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($shipments) && $shipments != false; $x++)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row">
               <input type="checkbox" class="selchk" name="txtadd[]" value="<?=$shipments[$x]["id"]?>">
            </td>
            <td class="content_row"><?=$shipments[$x]["dlv_num"]?></td>
            <td class="content_row"><?=$shipments[$x]["dlv_docnum"]?>&nbsp;</td>
            <td class="content_row"><?=$shipments[$x]["req_number"]?>&nbsp;</td>
            <td class="content_row"><?=date('d.m.Y', $shipments[$x]["dlv_delivery_date"])?></td>
            <td class="content_row" align="center">
               <?php
               printButton("Agregar a la factura", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=basic&id={$_REQUEST["id"]}&addshp={$shipments[$x]["id"]}", "", "pencil");
               ?>
            </td>
         </tr>
         <?php
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="5" align="center">
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
      </form>
      <br>
   </td>
</tr>
<tr>
   <td>
      <?php
      printButton("Agregar guias marcadas", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_addtxt)", "gear", 130);
      ?>
   </td>
</tr>
</table>