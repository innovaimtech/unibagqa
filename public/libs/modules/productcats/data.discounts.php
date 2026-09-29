<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["exec"] == "save")
{
   $currtme = time();
   
   $_REQUEST["dct_alltype"]   = (int)$_REQUEST["dct_alltype"];
   $_REQUEST["dct_allprice"]  = (float)sprintf("%.2f", (float)str_replace(",", ".", str_replace(".", "", $_REQUEST["dct_allprice"])));

   //----------------------------------------------------------------------------------
   $sql = " update productcats
            set
            cat_dct_allprice  = {$_REQUEST["dct_allprice"]},
            cat_dct_alltype   = {$_REQUEST["dct_alltype"]},
            cat_updusr        = {$_SESSION["user_id"]},
            cat_upddat        = {$currtme}
            where
            id                = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $sql = " delete from dscsell_productcats_payment
            where
            dct_cat_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $sql = " delete from dscsell_productcats_value
            where
            dct_cat_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "dct_scale_discount_") !== false && strpos($reqkey, "dct_scale_discount_") == 0)
      {
         $idx     = substr($reqkey, strrpos($reqkey, "_") +1);
         $treqkey = substr($reqkey, 0, strrpos($reqkey, "_"));
         $payid   = substr($treqkey, strrpos($treqkey, "_") +1);
         $paysql[$payid]["DSC{$idx}"] = getPrice($_REQUEST["dct_scale_discount_{$payid}_{$idx}"],2);
         $paysql[$payid]["TYP{$idx}"] = (int)$_REQUEST["dct_scale_type_{$payid}_{$idx}"];
         $paysql[$payid]["MOD{$idx}"] = (int)$_REQUEST["dct_scale_mode_{$payid}_{$idx}"];
      }
   }
   
   //----------------------------------------------------------------------------------
   foreach(array_keys($paysql) AS $payid)
   {
      $sql = " insert into dscsell_productcats_payment
               (dct_cat_id, dct_payid, dct_scale_discount1, dct_scale_discount2, dct_scale_discount3,
                dct_scale_discount4, dct_scale_type1, dct_scale_type2, dct_scale_type3, dct_scale_type4,
                dct_scale_mode1, dct_scale_mode2, dct_scale_mode3, dct_scale_mode4)
               VALUES
               ({$_REQUEST["id"]}, {$payid}, {$paysql[$payid]["DSC1"]}, {$paysql[$payid]["DSC2"]}, {$paysql[$payid]["DSC3"]},
                {$paysql[$payid]["DSC4"]}, {$paysql[$payid]["TYP1"]}, {$paysql[$payid]["TYP2"]}, {$paysql[$payid]["TYP3"]},
                {$paysql[$payid]["TYP4"]}, {$paysql[$payid]["MOD1"]}, {$paysql[$payid]["MOD2"]}, {$paysql[$payid]["MOD3"]},
                {$paysql[$payid]["MOD4"]})";
      $CON->no_result($sql);
   }

   $poscounter = 0;
   //----------------------------------------------------------------------------------
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "vol_scale_discount_") !== false && strpos($reqkey, "vol_scale_discount_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);

         $dct_scale_pricefrom = getPrice($_REQUEST["vol_scale_amtfrom_{$idx}"],2);
         $dct_scale_priceto   = getPrice($_REQUEST["vol_scale_amtto_{$idx}"],2);
         $dct_scale_discount  = getPrice($_REQUEST["vol_scale_discount_{$idx}"],2);
         $dct_scale_type      = (int)$_REQUEST["vol_scale_type_{$idx}"];

         if($dct_scale_pricefrom > 0.00 || $dct_scale_priceto > 0.00)
         {
            $sql = " insert into dscsell_productcats_value
                     (dct_cat_id, dct_pos, dct_scale_amtfrom, dct_scale_amtto, dct_scale_discount, dct_scale_type)
                     VALUES
                     ({$_REQUEST["id"]}, {$poscounter}, {$dct_scale_pricefrom}, {$dct_scale_priceto},
                      {$dct_scale_discount}, {$dct_scale_type})";
            $CON->no_result($sql);
            
            $poscounter++;
         }
      }
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
         from dscsell_productcats_payment
         where
         dct_cat_id = {$_REQUEST["id"]}";
$paydata = $CON->select($sql);

for($x = 0; $x < count($paydata) && $paydata != false; $x++)
{
   $row = $paydata[$x];
   $paypos[$row["dct_payid"]] = $row;
}

//----------------------------------------------------------------------------------
$sql = " select *
         from dscsell_productcats_value
         where
         dct_cat_id = {$_REQUEST["id"]}
         order by dct_pos asc";
$volpos = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select *
         from productcats
         where
         id = {$_REQUEST["id"]}";
$catdata = $CON->select($sql);

//----------------------------------------------------------------------------------
for($x = 0; $x < 10; $x++)
{
   $startval   = $volpos[$x]["dct_scale_amtfrom"];
   $endval     = $volpos[$x]["dct_scale_amtto"];

   if($startval > 0.00 || $endval > 0.00)
   {
      for($y = 0; $y < 10; $y++)
      {
         if($y != $x)
         {
            $substartval   = $volpos[$y]["dct_scale_amtfrom"];
            $subendval     = $volpos[$y]["dct_scale_amtto"];

            if($substartval > 0.00 || $subendval > 0.00)
            {
               if($substartval >= $startval && $substartval <= $endval)
                  $errscales[$y] = 1;
            }
         }
      }
   }
}

//----------------------------------------------------------------------------------
for($x = 0; $x < 10; $x++)
{
   $startval   = $volpos[$x]["dct_scale_amtfrom"];
   $endval     = $volpos[$x]["dct_scale_amtto"];

   if($startval > 0.00 || $endval > 0.00)
      if($endval < $startval)
         $errvals[$x] = 1;
}

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_itemprices">
<input type="hidden" name="exec" value="save">
<input type="hidden" name="subexec" value="<?=$_REQUEST["subexec"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?=Nifty_printH("box2", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="110">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Condición global</td>
</tr>
<tr>
   <td class="content_row">Descuento global</td>
   <td class="content_row">
      <input name="dct_allprice" type="text" class="text" style="width:90px" value="<?=printPrice($catdata[0]["cat_dct_allprice"],2)?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
      <select class="text" style="width:40px" name="dct_alltype"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
         <option value="0" <?php if(!(int)$catdata[0]["cat_dct_alltype"]) echo "selected" ?>>%</option>
         <option value="1" <?php if( (int)$catdata[0]["cat_dct_alltype"]) echo "selected" ?>><?=$_SESSION["_CONF"]["conf_currency"]?></option>
      </select>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box3", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="320">
   <col>
   <col>
   <col>
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="5">Condiciones por forma de pago</td>
</tr>
<tr>
   <td class="content_tbl_subheader content_row_os">Forma de pago</td>
   <td class="content_tbl_subheader content_row_os">Descuento 1</td>
   <td class="content_tbl_subheader content_row_os">Descuento 2</td>
   <td class="content_tbl_subheader content_row_os">Descuento 3</td>
   <td class="content_tbl_subheader content_row_os">Descuento 4</td>
</tr>
<?php
for($x = 0; $x < count($payments) && $payments != false; $x++)
{
   $payid = $payments[$x]["id"];
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row_os"><?=$payments[$x]["pay_title"]?></td>
      <?php
      for($y = 1; $y <= 4; $y ++)
      {  ?>
         <td class="content_row_os">
            <input type="checkbox" name="dct_scale_mode_<?=$payments[$x]["id"]?>_<?=$y?>"
            title="Activado = Nota adjunta, Desactivado = Factura" value="1"
            <?php if((int)$paypos[$payid]["dct_scale_mode{$y}"]) echo "checked"?>>
            
            <input name="dct_scale_discount_<?=$payments[$x]["id"]?>_<?=$y?>" type="text" class="text" style="width:60px"
            value="<?php if($paypos[$payid]["dct_scale_discount{$y}"] > 0.00) echo printPrice($paypos[$payid]["dct_scale_discount{$y}"],2)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
            
            <select class="text" style="width:40px;display:none" name="dct_scale_type_<?=$payments[$x]["id"]?>_<?=$y?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
               <option value="0" <?php if(!(int)$paypos[$payid]["dct_scale_type{$y}"]) echo "selected" ?>>%</option>
               <option value="1" <?php if( (int)$paypos[$payid]["dct_scale_type{$y}"]) echo "selected" ?>><?=$_SESSION["_CONF"]["conf_currency"]?></option>
            </select>%
            
         </td>
         <?php
      }
      ?>
   </tr>
   <?php
}  ?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box3", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="100">
   <col width="110">
   <col width="110">
   <col>
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="7">Condiciones por monto</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Condición</td>
   <td class="content_tbl_subheader">Monto de</td>
   <td class="content_tbl_subheader">Monto hasta</td>
   <td class="content_tbl_subheader">Descuento</td>
</tr>
<?php
for($x = 0; $x < 10; $x++)
{
   if((float)$volpos[$x]["dct_scale_amtfrom"] > 0.00 || (float)$volpos[$x]["dct_scale_amtto"] > 0.00)
   {
      $dsp_pricefrom    = printPrice($volpos[$x]["dct_scale_amtfrom"],2);
      $dsp_priceto      = printPrice($volpos[$x]["dct_scale_amtto"],2);
      $dsp_discount     = printPrice($volpos[$x]["dct_scale_discount"],2);
   }
   else
   {
      $dsp_pricefrom    = "";
      $dsp_priceto      = "";
      $dsp_discount     = "";
   }
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row">
         <?php
         if(array_search($x, array_keys($errscales)) !== false || array_search($x, array_keys($errvals)) !== false)
            echo "<b class='msg_save_err'>";
         elseif($dsp_priceto != "")
            echo "<b class='msg_save_ok'>";
            
         ?>
         Condición <?=($x + 1)?>
         <?php
         if(array_search($x, array_keys($errscales)) !== false || array_search($x, array_keys($errvals)) !== false || $dsp_pricefrom != "")
            echo "</b>";
         ?>
      </td>
      <td class="content_row">
         <input name="vol_scale_amtfrom_<?=$x?>" type="text" class="text" style="width:90px" value="<?=$dsp_pricefrom?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row">
         <input name="vol_scale_amtto_<?=$x?>" type="text" class="text" style="width:90px" value="<?=$dsp_priceto?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row">
         <input name="vol_scale_discount_<?=$x?>" type="text" class="text" style="width:60px" value="<?=$dsp_discount?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         <select class="text" style="width:40px" name="vol_scale_type_<?=$x?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
            <option value="0" <?php if(!(int)$volpos[$x]["dct_scale_type"]) echo "selected" ?>>%</option>
            <option value="1" <?php if( (int)$volpos[$x]["dct_scale_type"]) echo "selected" ?>><?=$_SESSION["_CONF"]["conf_currency"]?></option>
         </select>
      </td>
   </tr>
   <?php
}  ?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
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
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_itemprices');" ?>