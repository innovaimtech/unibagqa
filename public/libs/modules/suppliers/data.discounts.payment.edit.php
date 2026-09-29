<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   //----------------------------------------------------------------------------------
   $sql = " delete from dscbuy_supplier_payment
            where
            dct_supplier_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "dct_scale_discount_") !== false && strpos($reqkey, "dct_scale_discount_") == 0)
      {
         $payid = substr($reqkey, strrpos($reqkey, "_") +1);

         $paysql[$payid]["DSC"] = getPrice($_REQUEST["dct_scale_discount_{$payid}"],2);
         $paysql[$payid]["TYP"] = (int)$_REQUEST["dct_scale_type_{$payid}"];
      }
   }
   
   //----------------------------------------------------------------------------------
   foreach(array_keys($paysql) AS $payid)
   {
      $sql = " insert into dscbuy_supplier_payment
               (dct_supplier_id, dct_payid, dct_scale_discount, dct_scale_type)
               VALUES
               ({$_REQUEST["id"]}, {$payid}, {$paysql[$payid]["DSC"]}, {$paysql[$payid]["TYP"]})";
      $CON->no_result($sql);
   }

   $savemsg = getSaveMessage(true);
}

//----------------------------------------------------------------------------------
$sql = " select *
         from payments
         where
         pay_status > 0
         order by pay_title";
$payments = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select *
         from dscbuy_supplier_payment
         where
         dct_supplier_id = {$_REQUEST["id"]}";
$paydata = $CON->select($sql);
for($x = 0; $x < count($paydata) && $paydata != false; $x++)
{
   $row = $paydata[$x];
   $paypos[$row["dct_payid"]] = $row;
}

//----------------------------------------------------------------------------------
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_itemprices">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="catid" value="<?=$_REQUEST["catid"]?>">
<?=Nifty_printH("box3", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="320">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Condiciones por forma de pago</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Forma de pago</td>
   <td class="content_tbl_subheader">Descuento</td>
</tr>
<?php
for($x = 0; $x < count($payments) && $payments != false; $x++)
{
   $payid = $payments[$x]["id"];
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row"><?=$payments[$x]["pay_title"]?></td>
      <td class="content_row">
         <input name="dct_scale_discount_<?=$payid?>" type="text" class="text" style="width:60px"
         value="<?php if($paypos[$payid]["dct_scale_discount"] > 0.00) echo printPrice($paypos[$payid]["dct_scale_discount"],2)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">

         <select class="text" style="width:40px" name="dct_scale_type_<?=$payments[$x]["id"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
            <option value="0" <?php if($paypos[$payid]["dct_scale_discount"] > 0.00 && !(int)$paypos[$payid]["dct_scale_type"]) echo "selected" ?>>%</option>
            <option value="1" <?php if($paypos[$payid]["dct_scale_discount"] > 0.00 && (int)$paypos[$payid]["dct_scale_type"]) echo "selected" ?>><?=$_SESSION["_CONF"]["conf_currency"]?></option>
         </select>

      </td>
   </tr>
   <?php
}  ?>
</table>
<?=Nifty_printF()?>
<br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_itemprices');" ?>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=discounts&id={$_REQUEST["id"]}", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemprices)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<br><br>