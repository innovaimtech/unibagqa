<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{

   $savemsg = getSaveMessage(true);

   registerCostPriceHistory($CON, $_REQUEST["id"], "itemlist");
   recalcAutomatedSellPrices($CON, $_REQUEST["id"], "itemlist");
   updateItemlistStorePrices($CON, $_REQUEST["id"]);
   registerSellPriceHistory($CON, $_REQUEST["id"], "itemlist");
}

//----------------------------------------------------------------------------------
$sql = " select *
         from itemlist_suppliers
         where
         item_id = {$_REQUEST["id"]}
         order by item_supp_act desc, item_costprice_brutto asc";
$posdata = $CON->select($sql);

$rowcount = 0;
if($posdata != false && count($posdata))
   $rowcount = count($posdata);

if($rowcount <= 6)
   $rowcount = 10;
else
   $rowcount += 4;
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_itemsupp">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">

<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsupp)", "disk-black");
      ?>
   </td>
</tr>
</form>
</table>
<?=Nifty_printF(false)?>
<br><br>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_itemsupp');" ?>
