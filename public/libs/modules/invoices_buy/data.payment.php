<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "invoices_buy")
{
   $_tblname   = "invoices_buy";
   $_colprefix = "invc";
   $_dspword   = "Factura";
   $_reltblnm  = "supplier";
   $_relkeynm  = "supplier";
   $_relcolnm  = "supp_company";
   $_relcolrut = "supp_rut";
}
elseif($_REQUEST["mode"] == "invoices_notes_buy")
{
   $_tblname   = "invoices_notes_buy";
   $_colprefix = "note";
   $_dspword   = "Nota";
   $_reltblnm  = "supplier";
   $_relkeynm  = "supplier";
   $_relcolnm  = "supp_company";
   $_relcolrut = "supp_rut";
}
elseif($_REQUEST["mode"] == "invoices_sell")
{
   $_tblname   = "invoices_sell";
   $_colprefix = "invc";
   $_dspword   = "Factura";
   $_reltblnm  = "customer";
   $_relkeynm  = "cust";
   $_relcolnm  = "cust_name";
   $_relcolrut = "cust_rut";
//    $_readonly  = true;
}
elseif($_REQUEST["mode"] == "invoices_sell_bol")
{
   $_tblname   = "invoices_sell_bol";
   $_colprefix = "invc";
   $_dspword   = "Boleta";
   $_reltblnm  = "customer";
   $_relkeynm  = "cust";
   $_relcolnm  = "cust_name";
   $_relcolrut = "cust_rut";
//    $_readonly  = true;
}
elseif($_REQUEST["mode"] == "invoices_notes_sell")
{
   $_tblname   = "invoices_notes_sell";
   $_colprefix = "note";
   $_dspword   = "Nota";
   $_reltblnm  = "customer";
   $_relkeynm  = "cust";
   $_relcolnm  = "cust_name";
   $_relcolrut = "cust_rut";
//    $_readonly  = true;
}


//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.{$_relcolnm}, t2.{$_relcolrut}, t3.company_short, t4.shop_name, t4.id 'shop_id'
         from {$_tblname} t1
         LEFT OUTER JOIN {$_reltblnm} t2     ON t1.{$_colprefix}_{$_relkeynm}_id  = t2.id
         LEFT OUTER JOIN company_data t3     ON t1.{$_colprefix}_company_id   = t3.id
         LEFT OUTER JOIN company_shops t4    ON t1.{$_colprefix}_shop_id      = t4.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "invoices_sell" || $_REQUEST["mode"] == "invoices_sell_bol" || $_REQUEST["mode"] == "invoices_notes_sell")
{
   $sql = " select shop_isremote
            from company_shops
            where
            id = {$headdata["shop_id"]}";
   $shop_isremote = $CON->select($sql);
   $shop_isremote = (int)$shop_isremote[0]["shop_isremote"];

   if($shop_isremote)
      $_readonly  = true;
}

//----------------------------------------------------------------------------------
if((int)$headdata["{$_colprefix}_importation"])
   $numberlim = "2";
else
   $numberlim = "0";
   
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   if(!$_readonly)
   {
      $sql = " delete from tran_payments
               where
               pay_tran_type  = '{$_REQUEST["mode"]}' and
               pay_tran_id    = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      
      foreach(array_keys($_REQUEST) AS $reqkey)
      {
         if(strpos($reqkey, "pay_brutto_") !== false && strpos($reqkey, "pay_brutto_") == 0)
         {
            $idx = substr($reqkey, strrpos($reqkey, "_") +1);

            $pay_paymentid = (int)$_REQUEST["pay_paymentid_{$idx}"];
            $pay_payed     = (int)$_REQUEST["pay_payed_{$idx}"];
            $pay_brutto    = getPrice($_REQUEST["pay_brutto_{$idx}"], $numberlim);
            $pay_date      = trim(addslashes($_REQUEST["pay_date_{$idx}"]));
            $pay_date      = explode(".", $pay_date);
            $pay_date      = (int)mktime(15, 0, 0, $pay_date[1], $pay_date[0], $pay_date[2]);
            $pay_docnum    = trim(addslashes($_REQUEST["pay_docnum_{$idx}"]));
            $pay_bank      = trim(addslashes($_REQUEST["pay_bank_{$idx}"]));
            $pay_comment   = trim(addslashes($_REQUEST["pay_comment_{$idx}"]));

            if($pay_payed && $pay_date == 0)
               $pay_date = (int)mktime(15, 0, 0, date('m'), date('d'), date('Y'));

            $pay_import_total = 0;
            if((int)$headdata["{$_colprefix}_importation"])
               $pay_import_total = round($pay_brutto * $headdata["{$_colprefix}_exc_rate"], 0);

            if($pay_brutto > 0.00 || $pay_paymentid > 0 || $pay_docnum != "" || $pay_comment != "")
            {
               $sql = " insert into tran_payments
                        (pay_tran_id, pay_tran_type, pay_paymentid, pay_brutto, pay_date, pay_payed,
                         pay_docnum, pay_bank, pay_comment, pay_import_total)
                        VALUES
                        ({$_REQUEST["id"]}, '{$_REQUEST["mode"]}', {$pay_paymentid}, {$pay_brutto},
                         {$pay_date}, {$pay_payed}, '{$pay_docnum}', '{$pay_bank}', '{$pay_comment}',
                         {$pay_import_total})";
               $CON->no_result($sql);
            }
         }
      }
   }

   //----------------------------------------------------------------------------------
   if($_REQUEST["setPayed"] != "")
   {
      $newstatus = 3;
      $_REQUEST["setPayed"] = (int)$_REQUEST["setPayed"];
      if(!$_REQUEST["setPayed"])
         $newstatus = 2;
         
      $sql = " update {$_tblname}
               set
               {$_colprefix}_payed  = {$_REQUEST["setPayed"]},
               {$_colprefix}_status = {$newstatus}
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);

      if(!$_readonly)
      {
         $posdata = getTranPayments($CON, $_REQUEST["id"], $_REQUEST["mode"]);
         if(($posdata == false || !count($posdata)) && $newstatus == 3)
         {
            $pay_paymentid = (int)$headdata["{$_colprefix}_paymentid"];
            $pay_payed     = 1;
            $pay_brutto    = $headdata["{$_colprefix}_total_brutto"];
            $pay_date      = date('d.m.Y');
            $pay_date      = explode(".", $pay_date);
            $pay_date      = (int)mktime(15, 0, 0, $pay_date[1], $pay_date[0], $pay_date[2]);
            $pay_docnum    = "";
            $pay_bank      = "";
            $pay_comment   = "";
            $pay_import_total = 0;
            if((int)$headdata["{$_colprefix}_importation"])
               $pay_import_total = round($pay_brutto * $headdata["{$_colprefix}_exc_rate"], 0);

            $sql = " insert into tran_payments
                     (pay_tran_id, pay_tran_type, pay_paymentid, pay_brutto, pay_date, pay_payed,
                      pay_docnum, pay_bank, pay_comment, pay_import_total)
                     VALUES
                     ({$_REQUEST["id"]}, '{$_REQUEST["mode"]}', {$pay_paymentid}, {$pay_brutto},
                      {$pay_date}, {$pay_payed}, '{$pay_docnum}', '{$pay_bank}', '{$pay_comment}',
                      {$pay_import_total})";
            $CON->no_result($sql);
         }
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.{$_relcolnm}, t2.{$_relcolrut}, t3.company_short, t4.shop_name
         from {$_tblname} t1
         LEFT OUTER JOIN {$_reltblnm} t2     ON t1.{$_colprefix}_{$_relkeynm}_id  = t2.id
         LEFT OUTER JOIN company_data t3     ON t1.{$_colprefix}_company_id   = t3.id
         LEFT OUTER JOIN company_shops t4    ON t1.{$_colprefix}_shop_id      = t4.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
if((int)$headdata["{$_colprefix}_importation"])
   $numberlim = "2";
else
   $numberlim = "0";

//----------------------------------------------------------------------------------
$payments = getPayments($CON);
$posdata  = getTranPayments($CON, $_REQUEST["id"], $_REQUEST["mode"]);

//----------------------------------------------------------------------------------
$rowcount = 0;
if($posdata != false && count($posdata))
   $rowcount = count($posdata);

if(!$_readonly)
   $rowcount += 2;

if($_REQUEST["mode"] == "invoices_buy" || $_REQUEST["mode"] == "invoices_sell" || $_REQUEST["mode"] == "invoices_sell_bol")
   $_dspstatus = getInvoiceBuyStatus($headdata["invc_status"], true);
if($_REQUEST["mode"] == "invoices_notes_buy" || $_REQUEST["mode"] == "invoices_notes_sell")
   $_dspstatus = getInvoiceBuyStatus($headdata["note_status"], true)
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col width="130">
   <col width="360">
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos básicos</td>
</tr>
<tr>
   <td class="content_rowl">Número</td>
   <td class="content_row"><?=$headdata["{$_colprefix}_number"]?></td>
   <td class="content_rowl">Número <?=$_dspword?></td>
   <td class="content_row"><?=$headdata["{$_colprefix}_docnumber"]?></td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$headdata["company_short"]?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$headdata["shop_name"]?></td>
</tr>
<tr>
   <td class="content_rowl">Cliente/Proveedor</td>
   <td class="content_row"><?=$headdata[$_relcolnm]?></td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][13]?></td>
   <td class="content_row"><?=$headdata[$_relcolrut]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Estado</td>
   <td class="content_row"><?=$_dspstatus?></td>
   <td class="content_rowl">&nbsp;</td>
   <td class="content_row">&nbsp;</td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?php
//----------------------------------------------------------------------------------
$sql = " select t1.*
         from invoices_sell t1
         LEFT OUTER JOIN customer t2 ON t1.invc_cust_id = t2.id
         where
         t1.id = {$_REQUEST["id"]} ";
$xinvoice = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from invoices_sell t1
         LEFT OUTER JOIN customer t2 ON t1.invc_cust_id = t2.id
         where
         t1.id = {$invoice[0]["invc_childid"]} ";
$xinvoicechild = $CON->select($sql);

$sql = " select count(*) 'cc'
         from invoices_notes_sell
         where
         note_parent_invcid = {$invoice[0]["id"]} and
         note_status > 1";
$xinvoiceHasNote = $CON->select($sql);
$xinvoiceHasNote = (int)$xinvoiceHasNote[0]["cc"];

$sql = " select pay_days_extra
         from payments
         where
         id = {$invoice[0]["invc_paymentid"]}";
$xpaydays = $CON->select($sql);
$xpaydays = (int)$xpaydays[0]["pay_days_extra"];

$highestpaydate = 0;
foreach($posdata AS $posrow)
{
   if($posrow["pay_date"] > $highestpaydate)
      $highestpaydate = $posrow["pay_date"];
}

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "invoices_sell" && (int)$invoice[0]["invc_childid"] && ($xinvoice[0]["invc_status"] == 3 || $xinvoice[0]["invc_status"] == 2) && (int)$xinvoicechild[0]["invc_status"] == -10 && $highestpaydate > 0 && !$xinvoiceHasNote)
{
   $maxavaildate = $invoice[0]["invc_estpay_date"] + (86400 * $xpaydays);
   if($highestpaydate <= $maxavaildate)
   {
      $bclass = "msg_save_ok";
      $pnavcl = "postnav_save";
      $picon  = "tick-circle-frame";
      $addtxt = "(".round((($highestpaydate - $maxavaildate) / 86400),2)." Dias)";
      $framec = "#41B546";
   }
   else
   {
      $bclass = "msg_save_err";
      $pnavcl = "postnav_del";
      $picon  = "cross-circle-frame";
      $addtxt = "(+".round((($highestpaydate - $maxavaildate) / 86400),2)." Dias)";
      $framec = "#FF4353";
   }
   ?>
   <script language="JavaScript">
      function createInvoiceSellNote()
      {
         location.href='index.php?mid=755&exec=edit&subexec=createFromDiscountSpec&invcidOrg=<?=$xinvoice[0]["id"]?>';
      }
      function createInvoiceSellNoteFancy()
      {
         parent.location.href='index.php?mid=755&exec=edit&subexec=createFromDiscountSpec&invcidOrg=<?=$xinvoice[0]["id"]?>';
      }
   </script>
   <?=Nifty_printH("box1' style='border:3px solid {$framec}", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
   <tr>
      <td align="left" class="content_row_clear" width="120"><b class="<?=$bclass?>"><blink>NOTA ADJUNTA:</blink></b></td>
      <td align="left" class="content_row_clear" width="320">
         <b>Factura/Vencimiento: <?=date('d.m.Y', $invoice[0]["invc_estpay_date"])?> (<?=date('d.m.Y', $maxavaildate)?> max)</b>
      </td>
      <td align="left" class="content_row_clear">
         <img src="./images/menu/icons/<?=$picon?>.png" style="vertical-align:bottom">&nbsp;
         <b class="<?=$bclass?>">Fecha Pago: <?=date('d.m.Y', $highestpaydate)?> <?=$addtxt?></b>
      </td>
      <td align="left" class="content_row_clear" width="130" style="padding-right:5px">
         <?php
         if($_REQUEST["from"] != "report")
            printButton("Generar Nota de Credito", $pnavcl, "javascript: deactivateFormChange()", "createInvoiceSellNote()", "balance", 180);
         else
            printButton("Generar Nota de Credito", $pnavcl, "javascript: deactivateFormChange()", "createInvoiceSellNoteFancy()", "balance", 180);
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
}
//----------------------------------------------------------------------------------
?>
<form action="<?php if($_REQUEST["from"] != "report") echo "index.php"; else echo "iframe.edit.php"?>" method="post" name="xform_tranpay">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="mode" value="<?=$_REQUEST["mode"]?>">
<input type="hidden" name="from" value="<?=$_REQUEST["from"]?>">
<input type="hidden" name="setPayed" value="">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col>
   <col>
   <col>
   <col>
   <col>
   <col>
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="7">Resumen de pagos</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Forma de pago</td>
   <td class="content_tbl_subheader">Monto</td>
   <td class="content_tbl_subheader">Fecha de pago</td>
   <td class="content_tbl_subheader" align="center">Estado/Pago</td>
   <td class="content_tbl_subheader">Comprobante</td>
   <td class="content_tbl_subheader">Banco</td>
   <td class="content_tbl_subheader">Observaciones</td>
</tr>
<?php

//----------------------------------------------------------------------------------
for($x = 0; $x < $rowcount; $x++)
{
   $ges_pay_brutto += $posdata[$x]["pay_brutto"];

   if((int)$posdata[$x]["id"])
      $ges_pay_amt ++;

   if((int)$posdata[$x]["pay_payed"])
      $ges_payed_brutto += $posdata[$x]["pay_brutto"];
   else
      $ges_nopayed_brutto += $posdata[$x]["pay_brutto"];

   if($_readonly)
   {
      ?>
      <tr bgcolor="<?=getRowColor($x)?>">
         <td class="content_row">
            <?php
            foreach($payments AS $payment)
               if($payment["id"] == $posdata[$x]["pay_paymentid"])
                  echo $payment["pay_title"];
            ?>
         </td>
         <td class="content_row"><?=printPrice($posdata[$x]["pay_brutto"], $numberlim)?>&nbsp;</td>
         <td class="content_row"><?=date('d.m.Y', $posdata[$x]["pay_date"])?>&nbsp;</td>
         <td class="content_row" align="center">
            <?php
            if($posdata[$x]["pay_payed"] == 1)
               echo "<b class=msg_save_ok>Pagado</b>";
            else
               echo "<b class=msg_save_err>Pendiente</b>";
            ?>
         </td>
         <td class="content_row"><?=$posdata[$x]["pay_docnum"]?>&nbsp;</td>
         <td class="content_row"><?=$posdata[$x]["pay_bank"]?>&nbsp;</td>
         <td class="content_row"><?=$posdata[$x]["pay_comment"]?>&nbsp;</td>
      </tr>
      <?php
   }
   else
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>">
         <td class="content_row">
            <select class="text" style="width:270px" name="pay_paymentid_<?=$x?>" id="pay_paymentid_<?=$x?>"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($payments as $payment)
               {  ?>
                  <option value="<?=$payment["id"]?>"
                  <?php if($payment["id"] == $posdata[$x]["pay_paymentid"]) echo "selected"?>><?=$payment["pay_title"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_row">
            <input type="text" class="text" style="width:85px;text-align:right"
            name="pay_brutto_<?=$x?>" id="pay_brutto_<?=$x?>"
            value="<?php if((int)$posdata[$x]["id"]) echo printPrice($posdata[$x]["pay_brutto"], $numberlim)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row">
            <input type="text" style="width:80px" id="pay_date_<?=$x?>" name="pay_date_<?=$x?>"
            class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?php if($posdata[$x]["pay_date"] > 0) echo date('d.m.Y', $posdata[$x]["pay_date"])?>">
         </td>
         <td class="content_row" align="center">
            <input type="checkbox" class="checkbox" id="pay_payed_<?=$x?>" name="pay_payed_<?=$x?>" value="1"
            <?php if((int)$posdata[$x]["pay_payed"]) echo "checked"?>>
         </td>
         <td class="content_row">
            <input type="text" class="text" style="width:130px;"
            name="pay_docnum_<?=$x?>" id="pay_docnum_<?=$x?>"
            value="<?=$posdata[$x]["pay_docnum"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row">
            <input type="text" class="text" style="width:130px;"
            name="pay_bank_<?=$x?>" id="pay_bank_<?=$x?>"
            value="<?=$posdata[$x]["pay_bank"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row">
            <input type="text" class="text" style="width:130px;"
            name="pay_comment_<?=$x?>" id="pay_comment_<?=$x?>"
            value="<?=$posdata[$x]["pay_comment"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <?php
   }
}
?>
<tr>
   <td class="content_rowl content_row_totals" style="border-top:2px solid #888888">Monto <?=$_dspword?></td>
   <td class="content_row_totals" style="border-top:2px solid #888888" align="right"><?=printPrice($headdata["{$_colprefix}_total_brutto"], $numberlim)?>&nbsp;</td>
   <td class="content_row_totals" style="border-top:2px solid #888888" align="right">&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl content_row_totals" style="border-top:2px solid #888888">Monto Pagos</td>
   <td class="content_row_totals" style="border-top:2px solid #888888" align="right"><b class="msg_save_ok"><?=printPrice($ges_pay_brutto, $numberlim)?></b>&nbsp;</td>
   <td class="content_row_totals" style="border-top:2px solid #888888" align="right">&nbsp;</td>
</tr>
<?php
if($_REQUEST["mode"] == "invoices_sell")
{
   $sql = " select distinct t1.*
            from invoices_notes_sell t1
            INNER JOIN invoices_notes_sell_items t2 ON ( t1.id = t2.note_id )
            where
            t1.note_status          > 1 and
            t1.note_status          < 4 and
            t1.note_type_contype    = 0 and
            t2.item_invc_docnumber  like '{$headdata["{$_colprefix}_docnumber"]}-%'
            group by t1.id
            order by t1.note_date desc, t1.note_estpay_date desc, t1.id desc";
   $notes = $CON->select($sql);
   foreach($notes AS $note)
   {
      if($note["note_type"] == 1)
      {
         $notesval -= $note["note_total_brutto"];
         $xtranpayed = getTranPayments($CON, $note["id"], "invoices_notes_sell");
         foreach($xtranpayed AS $xtranpayedrow)
            $notesval += $xtranpayedrow["pay_brutto"];
      }
      else
      {
         $notesval += $note["note_total_brutto"];
         $xtranpayed = getTranPayments($CON, $note["id"], "invoices_notes_sell");
         foreach($xtranpayed AS $xtranpayedrow)
            $notesval -= $xtranpayedrow["pay_brutto"];
      }
   }
   if($notesval != 0.00)
   {  ?>
      <tr>
         <td class="content_rowl content_row_totals">+/- Notas C/D</td>
         <td class="content_row_totals" align="right"><b class="msg_save_err"><?=printPrice($notesval, $numberlim)?></b>&nbsp;</td>
         <td class="content_row_totals" align="right">&nbsp;</td>
      </tr>
      <?php
      $headdata["{$_colprefix}_total_brutto"] += $notesval;
   }
}
//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "invoices_buy")
{
   $sql = " select t1.*
            from invoices_notes_buy t1
            where
            t1.note_status          > 1 and
            t1.note_status          < 4 and
            t1.note_parent_invcid   = {$_REQUEST["id"]}
            order by t1.note_date desc, t1.note_estpay_date desc, t1.id desc";
   $notes = $CON->select($sql);

   foreach($notes AS $note)
   {
      if($note["note_type"] == 1)
      {
         $ntype     = "Nota de Credito";
         if($note["note_multiinvc_desc"] > 0.00)
            $note["note_total_brutto"] -= $note["note_multiinvc_desc"];
         $notesval -= $note["note_total_brutto"];
      }
      else
      {
         $ntype     = "Nota de Debito";
         $notesval += $note["note_total_brutto"];
      }
      ?>
      <tr>
         <td class="content_rowl"><?=$ntype?>: <?=$note["note_docnumber"]?>, <?=date('d.m.Y',$note["note_date"])?></td>
         <td class="content_row" align="right"><?=printPrice($note["note_total_brutto"], $numberlim)?>&nbsp;</td>
         <td class="content_row" align="right">&nbsp;</td>
      </tr>
      <?php
   }
   if($notesval != 0.00)
   {  ?>
      <tr>
         <td class="content_rowl content_row_totals">Total Notas</td>
         <td class="content_row_totals" align="right"><b class="msg_save_err"><?=printPrice($notesval, $numberlim)?></b>&nbsp;</td>
         <td class="content_row_totals" align="right">&nbsp;</td>
      </tr>
      <?php
      $headdata["{$_colprefix}_total_brutto"] += $notesval;
   }
}

?>
<tr>
   <td class="content_rowl content_row_totals">Monto a pagar</td>
   <td class="content_row_totals" align="right"><b class="msg_save_err"><?=printPrice($headdata["{$_colprefix}_total_brutto"] - $ges_payed_brutto, $numberlim)?></b>&nbsp;</td>
   <td class="content_row_totals" align="right">&nbsp;</td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130" style="padding-right:5px">
      <?php
      if($_REQUEST["from"] != "report")
      {
         $editparam = "edit";
         if($_REQUEST["mode"] == "invoices_buy")
            $editparam = "editinvoice";
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec={$editparam}&id={$_REQUEST["id"]}", "", "arrow-180");
      }
      ?>
   </td>
   <td>&nbsp;</td>
   <td align="right" width="130" style="padding-right:5px">
      <?php
      if(!$shop_isremote)
      {
         if($headdata["{$_colprefix}_status"] == 3)
            printButton("Marcar como no aprobado", "postnav_del", "javascript: deactivateFormChange()", "document.xform_tranpay.setPayed.value='0';submitForm(document.xform_tranpay)", "cross-circle-frame", 220);
         else
            printButton("Marcar como aprobado", "postnav_save", "javascript: deactivateFormChange()", "document.xform_tranpay.setPayed.value='1';submitForm(document.xform_tranpay)", "tick-circle-frame", 220);
      }
      ?>
   </td>
   <?php
   if(!$_readonly && !$shop_isremote)
   {  ?>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.xform_tranpay)", "disk-black");
         ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>