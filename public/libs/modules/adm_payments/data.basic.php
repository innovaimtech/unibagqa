<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$_sesmodulename = "make_adm_payments";

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $currtme = time();

   $_REQUEST["company_id"] = (int)$_REQUEST["company_id"];
   $_REQUEST["shop_id"]    = (int)$_REQUEST["shop_id"];

   $sql = " insert into adm_payment
            (adm_company_id, adm_shop_id, adm_crtusr, adm_crtdat, adm_status, adm_date)
            values
            ({$_REQUEST["company_id"]}, {$_REQUEST["shop_id"]}, {$_SESSION["user_id"]}, {$currtme}, 1, {$currtme})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select max(id) as id
               from adm_payment
               where
               adm_crtusr = {$_SESSION["user_id"]}";
      $id = $CON->select($sql);
      $_REQUEST["id"] = $id[0]["id"];
   }

   $currtme = time();
   $savemsg = getSaveMessage($res);
}

//-------------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
   $_REQUEST["adm_supplier"]           = addslashes(trim($_REQUEST["adm_supplier"]));
   $_REQUEST["adm_supplier_rut"]       = addslashes(trim($_REQUEST["adm_supplier_rut"]));
   $_REQUEST["adm_address"]            = addslashes(trim($_REQUEST["adm_address"]));
   $_REQUEST["adm_comuna"]             = addslashes(trim($_REQUEST["adm_comuna"]));
   $_REQUEST["adm_region"]             = addslashes(trim($_REQUEST["adm_region"]));
   $_REQUEST["adm_country"]            = addslashes(trim($_REQUEST["adm_country"]));
   $_REQUEST["adm_telephone"]          = addslashes(trim($_REQUEST["adm_telephone"]));
   $_REQUEST["adm_notes"]              = addslashes(trim($_REQUEST["adm_notes"]));
   $_REQUEST["adm_payment_id"]         = (int)$_REQUEST["adm_payment_id"];
   $_REQUEST["adm_bank_id"]            = (int)$_REQUEST["adm_bank_id"];
   $_REQUEST["adm_amount"]             = (int)str_replace(".", "", $_REQUEST["adm_amount"]);
   $_REQUEST["adm_transaction_number"] = (int)$_REQUEST["adm_transaction_number"];
   $_REQUEST["adm_account_id"]         = (int)$_REQUEST["adm_account_id"];
   $_REQUEST["adm_payment_type"]       = (int)$_REQUEST["adm_payment_type"];
   $_REQUEST["adm_doc_number"]         = (int)$_REQUEST["adm_doc_number"];
   $_REQUEST["adm_payment_status"]     = (int)$_REQUEST["adm_payment_status"];

   //---------------------------------------------------------------------------------
   $dates = explode(".", $_REQUEST["adm_date"]);
   $date  = mktime(0, 0, 0, $dates[1], $dates[0], $dates[2]);

   //---------------------------------------------------------------------------------
   $sql = " update adm_payment
            set
            adm_bank_id             = {$_REQUEST["adm_bank_id"]},
            adm_date                = {$date},
            adm_transaction_number  = {$_REQUEST["adm_transaction_number"]},
            adm_amount              = {$_REQUEST["adm_amount"]},
            adm_payment_id          = {$_REQUEST["adm_payment_id"]},
            adm_payment_status      = {$_REQUEST["adm_payment_status"]},
            adm_supplier            = '{$_REQUEST["adm_supplier"]}',
            adm_account_id          = {$_REQUEST["adm_account_id"]},
            adm_doc_number          = {$_REQUEST["adm_doc_number"]},
            adm_updusr              = {$_SESSION["user_id"]},
            adm_supplier_rut        = '{$_REQUEST["adm_supplier_rut"]}',
            adm_address             = '{$_REQUEST["adm_address"]}',
            adm_comuna              = '{$_REQUEST["adm_comuna"]}',
            adm_region              = '{$_REQUEST["adm_region"]}', 
            adm_country             = '{$_REQUEST["adm_country"]}',
            adm_telephone           = '{$_REQUEST["adm_telephone"]}',
            adm_notes               = '{$_REQUEST["adm_notes"]}',
            adm_upddat              = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $sql = " delete from adm_payment_detail
            where
            adm_payment_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $adm_total_netto  = 0.00;
   $adm_total_taxes  = 0.00;
   $adm_total_brutto = 0.00;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "adm_item_id_") !== false && strpos($reqkey, "adm_item_id_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);

         $amount           = getPrice($_REQUEST["item_amount_{$idx}"],2);
         $sellprice_neto   = getPrice($_REQUEST["item_sellprice_neto_{$idx}"]);
         $sellprice_tax    = getPrice($_REQUEST["item_sellprice_tax_{$idx}"]);
         $sellprice_bruto  = getPrice($_REQUEST["item_sellprice_bruto_{$idx}"]);
         $item_unit_price  = getPrice($_REQUEST["item_unit_price_{$idx}"]);
         $itemid           = (int)$_REQUEST["adm_item_id_{$idx}"];

         if($_REQUEST["adm_payment_type"] == "1")
            $sellprice_neto = $sellprice_bruto;
         elseif($sellprice_bruto == 0)
            $sellprice_bruto = $sellprice_neto;
         elseif($sellprice_neto == 0)
            $sellprice_neto = $sellprice_bruto;

         $adm_total_netto  += $sellprice_neto;
         $adm_total_taxes  += $sellprice_tax;
         $adm_total_brutto += $sellprice_bruto;

         if($itemid)
         {
            $sql = " insert into adm_payment_detail
                     (adm_payment_id, adm_item_id, adm_item_amount, adm_item_sellprice_neto, adm_item_sellprice_tax, adm_item_sellprice_bruto, adm_item_unit_price )
                     VALUES
                     ({$_REQUEST["id"]}, {$itemid}, {$amount}, {$sellprice_neto}, {$sellprice_tax}, {$sellprice_bruto}, {$item_unit_price})";
            $CON->no_result($sql);
         }
      }
   }

   //---------------------------------------------------------------------------------
   $sql = " update adm_payment
            set
            adm_total_netto   = {$adm_total_netto},
            adm_total_taxes   = {$adm_total_taxes},
            adm_total_brutto  = {$adm_total_brutto}
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

//---------------------------------------------------------------------------------
$sql = " select t1.*, t2.shop_name, t4.company_short,
         CONCAT(t5.user_firstname, ' ', t5.user_lastname) as 'crt_username',
         CONCAT(t6.user_firstname, ' ', t6.user_lastname) as 'upd_username'
         from adm_payment t1
         LEFT OUTER JOIN company_shops t2 ON t2.id = t1.adm_shop_id
         LEFT OUTER JOIN company_data  t4 ON t4.id = t1.adm_company_id
         LEFT OUTER JOIN user          t5 ON t5.id = t1.adm_crtusr
         LEFT OUTER JOIN user          t6 ON t6.id = t1.adm_updusr
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);

if($headdata[0]["adm_payment_type"] == 0)
   $headdata[0]["adm_payment_type"] = 2;

//----------------------------------------------------------------------------------
$payments  = getPayments($CON);
$banks     = getBanks($CON);
$suppliers = getOtherSuppliers($CON);

$sql = " select *
         from adm_payitems
         where
         item_status > 0
         order by item_name";
$accounts = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.item_name
         from adm_payment_detail t1
         LEFT OUTER JOIN adm_payitems t2 ON t1.adm_item_id = t2.id
         where
         t1.adm_payment_id = {$_REQUEST["id"]}
         order by t1.id ASC";
$items = $CON->select($sql);
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<?php
printJSsetCompanyShop($shops);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="form_payments"
onsubmit="return checkform(new Array(this.adm_date, this.adm_amount, this.adm_account_id))" >
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="req_status" value="1">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col width="150">
   <col width="360">
   <col width="120">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos básicos</td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row" ><?=$headdata[0]["company_short"]?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$headdata[0]["shop_name"]?></td>
</tr>
<tr>
   <td class="content_rowl">Fecha *</td>
   <td class="content_row">
      <input type="text" style="width:80px" id="adm_date" name="adm_date" 
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?if($_REQUEST["id"] != "") echo date('d.m.Y', $headdata[0]["adm_date"])?>">
   </td>
   <td class="content_rowl" rowspan="3" valign="top">Observaciones</td>
   <td class="content_row" rowspan="3">
      <textarea name="adm_notes" class="text" style="width:100%; height:75px"><?=$headdata[0]["adm_notes"]?></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl">Monto *</td>
   <td class="content_row">
      <input type="text" style="width:80px;text-align:right" id="adm_amount" name="adm_amount"
      class="text" onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?=printPrice($headdata[0]["adm_amount"])?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Item *</td>
   <td class="content_row">
      <select class="text" style="width:100%" name="adm_account_id" id="adm_account_id"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($accounts as $account)
         {  ?>
            <option value="<?=$account["id"]?>" <?if($headdata[0]["adm_account_id"] == $account["id"]) echo "selected='selected'"; ?>><?=$account["item_name"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row">
      <?=$headdata[0]["crt_username"]?>
   </td>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row">
      <?=$headdata[0]["upd_username"]?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row">
      <?=displayDate($headdata[0]["adm_crtdat"])?>
   </td>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row">
      <?=displayDate($headdata[0]["adm_upddat"])?>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td align="left" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subexec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   else
   {  ?>
      <td>&nbsp;</td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.form_payments)", "disk-black");
      ?>
   </td>
</tr>
</table>
</form>
<?=Nifty_printF(false)?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('form_payments');" ?>