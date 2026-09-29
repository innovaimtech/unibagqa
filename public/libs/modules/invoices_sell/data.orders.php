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
$invcparts  = getInvoiceSellParts($CON, $_REQUEST["id"]);
foreach($invcparts AS $invcpart)
   $extshpids .= $invcpart["part_req_id"].",";
$extshpids = substr($extshpids, 0, -1);

//----------------------------------------------------------------------------------
if($_REQUEST["sql_datefrom"] == "")
   $_REQUEST["sql_datefrom"] = date('d.m.Y', time() - (86400 * 365));
if($_REQUEST["sql_dateto"] == "")
   $_REQUEST["sql_dateto"] = date('d.m.Y');

//----------------------------------------------------------------------------------
$_REQUEST["req_number"] = trim(addslashes($_REQUEST["req_number"]));
$sqldate_from           = getDateFromString($_REQUEST["sql_datefrom"]);
$sqldate_to             = getDateFromString($_REQUEST["sql_dateto"], false);

//----------------------------------------------------------------------------------
$sql = " select t1.req_number, t1.id, t1.req_crtdat
         from orders t1
         where
         t1.req_status        between 2 and 3 and
         t1.req_company_id    = {$headdata["invc_company_id"]} and
         t1.req_shop_id       = {$headdata["invc_shop_id"]} and
         t1.req_cust_id       = {$headdata["invc_cust_id"]} and
         t1.req_crtdat between {$sqldate_from} and {$sqldate_to} ";
         
if($_REQUEST["req_number"] != "")
   $sql .= " and t2.req_number like '%{$_REQUEST["req_number"]}%' ";
if($extshpids != "")
   $sql .= " and t1.id NOT IN ({$extshpids}) ";

$sql .= " order by t1.id desc";
$orders = $CON->select($sql);
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
         <col width="130">
         <col>
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="8">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Confirm. de compra</td>
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
         <td class="content_row" align="right">
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
      <?=Nifty_printH("box1", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col>
         <col width="160">
      </colgroup>
      <tr>
         <td class="content_tbl_subheader">Confirm. de compra</td>
         <td class="content_tbl_subheader">Fecha creación</td>
         <td class="content_tbl_subheader" align="center">Opciones</td>
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($orders) && $orders != false; $x++)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row"><?=$orders[$x]["req_number"]?>&nbsp;</td>
            <td class="content_row"><?=date('d.m.Y', $orders[$x]["req_crtdat"])?></td>
            <td class="content_row" align="center">
               <?php
               printButton("Agregar a la factura", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=basic&id={$_REQUEST["id"]}&addsord={$orders[$x]["id"]}", "", "pencil");
               ?>
            </td>
         </tr>
         <?php
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="3" align="center">
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