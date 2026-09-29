<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $sql = " delete from supplier_company_balance
            where
            supplier_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "init_balance_") !== false)
      {
         $company_id          = substr($reqkey, strrpos($reqkey, "_") +1);
         $init_balance        = getPrice($_REQUEST["init_balance_{$company_id}"]);
         $init_balance_usd    = getPrice($_REQUEST["init_usd_balance_{$company_id}"],2);

         $sql = " insert into supplier_company_balance
                  (supplier_id, company_id, init_balance, init_balance_usd)
                  VALUES
                  ({$_REQUEST["id"]}, {$company_id}, {$init_balance}, {$init_balance_usd})";
         $res = $CON->no_result($sql);
         $savemsg = getSaveMessage($res);
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " select *
         from company_data
         where
         company_status = 1
         order by company_name";
$companies = $CON->select($sql);

$sql = " select *
         from supplier
         where
         id = {$_REQUEST["id"]} ";
$supplier = $CON->select($sql);
$supplier = $supplier[0];
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" name="js_item_form">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col>
   <col>
   <col width="130">
   <col width="130">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Saldo inicial de cuenta corriente</td>
<tr>
   <td class="content_tbl_subheader">Empresa</td>
   <td class="content_tbl_subheader">Proveedor</td>
   <td class="content_tbl_subheader">Saldo inicial</td>
   <td class="content_tbl_subheader">Saldo inicial (US)</td>
</tr>
<?php
foreach($companies AS $company)
{
   $sql = " select *
            from supplier_company_balance
            where
            supplier_id = {$_REQUEST["id"]} and
            company_id  = {$company["id"]}";
   $initdata = $CON->select($sql);
   $init_balance     = (float)$initdata[0]["init_balance"];
   $init_balance_usd = (float)$initdata[0]["init_balance_usd"];
   ?>
   <tr>
      <td class="content_row"><?=$company["company_name"]?></td>
      <td class="content_row"><?=$supplier["supp_company"]?></td>
      <td class="content_row">
         <input name="init_balance_<?=$company["id"]?>" type="text"
         class="text" style="width:120px;text-align:right"
         value="<?=printPrice($init_balance)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row">
         <input name="init_usd_balance_<?=$company["id"]?>" type="text"
         class="text" style="width:120px;text-align:right"
         value="<?=printPrice($init_balance_usd,2)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.js_item_form)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('js_item_form');" ?>
<?php
$_REQUEST["_MODE"] = "supplier";
require_once("./libs/modules/stats/payment/buy.supplier.php");
?>