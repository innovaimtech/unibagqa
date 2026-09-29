<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$sql = " select t1.*
         from item t1
         where
         t1.id = {$_REQUEST["id"]} ";
$item = $CON->select($sql);
$item = $item[0];
   
if($_REQUEST["ccom"] == "save")
{
   $sql = " delete from item_volprices
            where
            item_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_amount_to_") !== false && strpos($reqkey, "item_amount_to_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);

         $item_amount_to      = getPrice($_REQUEST["item_amount_to_{$idx}"]);
         $item_prices_netto   = getPrice($_REQUEST["item_prices_netto_{$idx}"]);

         if($item_amount_to > 0.00 && $item_prices_netto > 0.00)
         {
            $temp["item_amount_to"]    = $item_amount_to;
            $temp["item_prices_netto"] = $item_prices_netto;
            $_PRICES[$item_amount_to] = $temp;
         }
      }
   }
   asort($_PRICES);

   $_LASTAMT = 1;
   foreach(array_keys($_PRICES) AS $item_amount_to)
   {
      $_PRICES[$item_amount_to]["item_amount_from"] = $_LASTAMT;
      $_LASTAMT = $_PRICES[$item_amount_to]["item_amount_to"] + 1;
   }
   
   foreach(array_keys($_PRICES) AS $item_amount_to)
   {
      $item_prices_taxes   = round($_PRICES[$item_amount_to]["item_prices_netto"] / 100 * $item["item_sellprice_taxes_perc"]);
      $item_prices_brutto  = round($_PRICES[$item_amount_to]["item_prices_netto"] + $item_prices_taxes);
      
      $sql = " insert into item_volprices
               (item_id, item_amount_from, item_amount_to, item_prices_netto, item_prices_taxes, item_prices_brutto)
               VALUES
               ({$_REQUEST["id"]}, {$_PRICES[$item_amount_to]["item_amount_from"]}, {$_PRICES[$item_amount_to]["item_amount_to"]},
                {$_PRICES[$item_amount_to]["item_prices_netto"]}, {$item_prices_taxes}, {$item_prices_brutto})";
      $CON->no_result($sql);
   }
}

$sql = " select *
         from item_volprices
         where
         item_id = {$_REQUEST["id"]}
         order by item_amount_from";
$vols = $CON->select($sql);

$rowcount = count($vols) + 6;
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_volprices">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="add">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="ccom" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="33%">
   <col width="33%">
   <col width="33%">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">Precios por cantidad</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Cantidad desde</td>
   <td class="content_tbl_subheader">Cantidad Hasta</td>
   <td class="content_tbl_subheader">Precio/Neto unitario</td>
</tr>
<?php
for($x = 0; $x < $rowcount; $x++)
{
   if(!(int)$vols[$x]["item_id"] && (int)$vols[($x-1)]["item_id"])
      $vols[$x]["item_amount_from"] = $vols[($x-1)]["item_amount_to"] +1;
   if($x == 0 && !(int)$vols[$x]["item_id"])
      $vols[$x]["item_amount_from"] = 1;
   ?>
   <tr bgcolor="<?=getRowColor(0)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row"><?if((int)$vols[$x]["item_amount_from"]) echo printPrice($vols[$x]["item_amount_from"])?></td>
      <td class="content_row">
         <input type="text" class="text" style="width:100%" name="item_amount_to_<?=$x?>"
         value="<?if((int)$vols[$x]["item_amount_to"]) echo printPrice($vols[$x]["item_amount_to"])?>">
      </td>
      <td class="content_row">
         <input type="text" class="text" style="width:100%" name="item_prices_netto_<?=$x?>"
         value="<?if((int)$vols[$x]["item_prices_netto"]) echo printPrice($vols[$x]["item_prices_netto"])?>">
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
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_volprices)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_volprices');" ?>