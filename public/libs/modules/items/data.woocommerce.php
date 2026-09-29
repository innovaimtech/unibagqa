<?php
if($_REQUEST["subexec"] == "save")
{
   $sql = " delete from item_woocommerce
            where
            item_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_woocode_") !== false && strpos($reqkey, "item_woocode_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);

         $item_woocode     = trim(addslashes($_REQUEST["item_woocode_{$idx}"]));
         $item_amt         = getPrice($_REQUEST["item_amt_{$idx}"]);

         if($item_woocode != "" && $item_amt > 0.00)
         {
            $sql = " insert into item_woocommerce
                     (item_id, item_woocode, item_amt, item_pos)
                     VALUES
                     ({$_REQUEST["id"]}, '{$item_woocode}', {$item_amt}, {$poscounter})";
            $CON->no_result($sql);
            $poscounter++;
         }
      }
   }
}

$sql = " select *
         from item_woocommerce
         where
         item_id = {$_REQUEST["id"]}
         order by item_pos";
$woos = $CON->select($sql);

$rowcount = count($woos) + 9;
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_woos">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="500">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Códigos y cantidades de Woocommerce</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Código Woocommerce</td>
   <td class="content_tbl_subheader">Cantidad descuento stock</td>
</tr>
<?php
for($x = 0; $x < $rowcount; $x++)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row">
         <input type="text" class="text" style="width:100%" name="item_woocode_<?=$x?>"
         value="<?=$woos[$x]["item_woocode"]?>">
      </td>
      <td class="content_row">
         <input type="text" class="text" style="width:100%" name="item_amt_<?=$x?>"
         value="<?if((int)$woos[$x]["item_amt"]) echo printPrice($woos[$x]["item_amt"])?>">
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130" valign="top">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_woos)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_woos');" ?>