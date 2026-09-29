<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$sql = " select t1.*, t7.pay_title
         from invoices_sell t1
         LEFT OUTER JOIN payments t7 ON t1.invc_paymentid = t7.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

/*
//----------------------------------------------------------------------------------
if($_REQUEST["createNoteSell"] == "1")
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_sell 
            where
            invc_parentid = {$_REQUEST["id"]}";
   $headdata2 = $CON->select($sql);
   $headdata2 = $headdata2[0];

   $netto_base1 = (float)$headdata["invc_total_netto"];
   $netto_base2 = (float)$headdata2["invc_total_netto"];
   $netto_diff  = (float)$netto_base1 - $netto_base2;

   //----------------------------------------------------------------------------------
   if($headdata["invc_taxes"])
   {
      $sql_sellnetto = $netto_diff;
      $sql_taxesperc = $_SESSION["_CONF"]["conf_taxes"];
      $sql_taxes     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_sellnetto / 100 * $sql_taxesperc);
      $sql_sellprice = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_sellnetto + $sql_taxes);
   }
   else
   {
      $sql_sellnetto = $netto_diff;
      $sql_taxesperc = 0;
      $sql_taxes     = 0;
      $sql_sellprice = $netto_diff;
   }

   //----------------------------------------------------------------------------------
   $currtme       = time();
   $note_number   = createTransactionNumber($CON, $headdata["invc_company_id"], "invoicesellnotecred");
   $note_date     = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
   $item_title    = trim(addslashes("POR CONCEPTO DE CONDICION DE PAGO. FACTURA {$headdata["invc_docnumber"]}"));

   //----------------------------------------------------------------------------------
   $sql = " insert into invoices_notes_sell
            (note_number, note_invcnumber, note_cust_id, note_company_id, note_shop_id, note_date,
             note_taxes, note_type, note_crtdat, note_crtusr)
            VALUES
            ('{$note_number}', '{$headdata["invc_docnumber"]}', {$headdata["invc_cust_id"]}, {$headdata["invc_company_id"]},
              {$headdata["invc_shop_id"]}, {$note_date}, {$headdata["invc_taxes"]}, 1, 
              {$currtme}, {$_SESSION["user_id"]})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from invoices_notes_sell
               where
               note_crtusr = {$_SESSION["user_id"]}";
      $invoicenote = $CON->select($sql);
      $invoicenoteid = (int)$invoicenote[0]["thisid"];

      print_r($headdata);

      $sql = " insert into invoices_notes_sell_items
               (note_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
                item_sellprice_netto_dsc, item_desc, item_invc_id, item_invc_docnumber)
               VALUES
               ({$invoicenoteid}, 9999999, 0, 1, 'manual',
               {$sql_sellprice}, {$sql_taxesperc}, {$sql_sellnetto}, {$sql_taxes},
               {$sql_sellnetto}, '{$item_title}', {$headdata["id"]}, '{$headdata["invc_docnumber"]}')";
      $CON->no_result($sql);

      recalcInvoiceSellNote($CON, $invoicenoteid);
      
      ?>
      <script language="JavaScript">
         slocation.href = 'index.php?mid=755&exec=edit&id=<?=$invoicenoteid?>'
      </script>
      <?php
   }
}
*/

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "delete")
{
   $sql = " delete from invoices_sell where id = {$_REQUEST["delnoteid"]}";
   $CON->no_result($sql);
   $sql = " delete from invoices_sell_parts where part_invc_id = {$_REQUEST["delnoteid"]}";
   $CON->no_result($sql);
   $sql = " delete from invoices_sell_parts_items where invc_id = {$_REQUEST["delnoteid"]}";
   $CON->no_result($sql);

   $sql = " update invoices_sell
            set
            invc_childid = 0
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "xsave")
   createNotaAdjuntaForInvoice($CON, $_REQUEST["id"], $_REQUEST["invc_paymentid"]);

//----------------------------------------------------------------------------------
$sql = " select t1.*, t7.pay_title
         from invoices_sell t1
         LEFT OUTER JOIN payments t7 ON t1.invc_paymentid = t7.id
         where
         t1.invc_parentid = {$_REQUEST["id"]} and
         t1.invc_status   = -10";
$hasnote = $CON->select($sql);
$hasnote = $hasnote[0];

//----------------------------------------------------------------------------------
$payments = getPayments($CON);

?>
<form action="index.php" method="post" name="xform_dscspec"
<?php
if($rdlo != "")
   echo "onsubmit='return false'";
else
{  ?>
   onsubmit="return checkform(new Array(this.invc_paymentid));"
   <?php
}
//----------------------------------------------------------------------------------
$rdlo = "readonly";
$dabl = "disabled";
if((int)$_SESSION["user_pricesell_perm"] || (int)$_REQUEST["user_pricesell_perm"])
{
   $rdlo = "";
   $dabl = "";
}
?>>
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="xsave">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="delnoteid" value="<?=$hasnote["id"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="210">
   <col width="260">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">Nota adjunta</td>
</tr>
<tr>
   <td class="content_rowl">Forma de pago: Original</td>
   <td class="content_row" colspan="2"><?=$headdata["pay_title"]?></td>
</tr>
<tr>
   <td class="content_rowl">Forma de pago: Nota adjunta</td>
   <td class="content_row">
      <?php
      if((int)$hasnote["id"])
      {
         echo $hasnote["pay_title"];
         ?>
         <input type="hidden" name="invc_paymentid" id="invc_paymentid" value="<?=$hasnote["invc_paymentid"]?>">
         <?php
      }
      else
      {  ?>
         <select class="text" style="width:250px" name="invc_paymentid" id="invc_paymentid"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($payments as $payment)
            {  ?>
               <option value="<?=$payment["id"]?>" <?php if($payment["id"] == $headdata["invc_paymentid"]) echo "selected"?>><?=$payment["pay_title"]?></option>
               <?php
            }
            ?>
         </select>
         <?php
      }
      ?>
   </td>
   <td class="content_row" align="right">
      <?php
      if(!(int)$hasnote["id"])
      {
         printButton("Generar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_dscspec)", "document", 160);
      }
      else
      {  ?>
         <table border="0" class="content_table" cellpadding="0" cellspacing="0">
         <tr>
            <td class="content_row_clear" style="padding-right:5px">
               <?php
               printButton("Imprimir nota adjunta", "postnav", "javascript: deactivateFormChange()", "openDocWindow('./libs/modules/invoices_sell/printraw.data.php?id={$hasnote["id"]}&mode=preview', 900, 730)", "document", 160);
               ?>
            </td>
            <td class="content_row_clear" style="padding-right:5px">
               <?php
               printButton("Eliminar nota adjunta", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')) { document.xform_dscspec.subexec.value='delete';submitForm(document.xform_dscspec); }", "cross-circle-frame", 160);
               ?>
            </td>
            <!--<td class="content_row_clear" style="display:none">
               <?php
               printButton("Generar nota de credito", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { location.href='index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=discountspec&id={$_REQUEST["id"]}&createNoteSell=1'  }", "arrow", 170);
               ?>
            </td>-->
         </tr>
         </table>
         <?php
      }
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php
if((int)$hasnote["id"])
{
   $_REQUEST["_CALCMODE"]              = "INVOICE";
   $_REQUEST["returnToDiscountSpec"]   = $_REQUEST["id"];
   $_REQUEST["id"]                     = $hasnote["id"];
   require_once("./libs/modules/orders/data.calculate.php");
   $_REQUEST["id"]                     = $_REQUEST["returnToDiscountSpec"];
}