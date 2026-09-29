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
   
   $_REQUEST["dct_vol_active"] = (int)$_REQUEST["dct_vol_active"];

   //----------------------------------------------------------------------------------
   $sql = " delete from dscsell_customer_productcats_value
            where
            dct_cust_id    = {$_REQUEST["id"]} and
            dct_cat_id     = {$_REQUEST["catid"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $sql = " select count(*) 'cc'
            from dscsell_customer_productcats_base
            where
            dct_cust_id     = {$_REQUEST["id"]} and
            dct_cat_id      = {$_REQUEST["catid"]}";
   $exists = $CON->select($sql);

   if(!(int)$exists[0]["cc"])
   {
      $sql = " insert into dscsell_customer_productcats_base
               (dct_cust_id, dct_cat_id, dct_active, dct_vol_active)
               VALUES
               ({$_REQUEST["id"]}, {$_REQUEST["catid"]}, 0, {$_REQUEST["dct_vol_active"]})";
      $CON->no_result($sql);
   }
   else
   {
      $sql = " update dscsell_customer_productcats_base
               set
               dct_vol_active  = {$_REQUEST["dct_vol_active"]}
               where
               dct_cust_id     = {$_REQUEST["id"]} and
               dct_cat_id      = {$_REQUEST["catid"]}";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   if((int)$_REQUEST["dct_vol_active"])
   {
      $poscounter = 0;
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
               $sql = " insert into dscsell_customer_productcats_value
                        (dct_cust_id, dct_cat_id, dct_pos, dct_scale_amtfrom, dct_scale_amtto, dct_scale_discount, dct_scale_type)
                        VALUES
                        ({$_REQUEST["id"]}, {$_REQUEST["catid"]}, {$poscounter}, {$dct_scale_pricefrom}, {$dct_scale_priceto},
                         {$dct_scale_discount}, {$dct_scale_type})";
               $CON->no_result($sql);
               
               $poscounter++;
            }
         }
      }
   }
   else
   {
      $sql = " delete from dscsell_customer_productcats_value
               where
               dct_cust_id = {$_REQUEST["id"]} and 
               dct_cat_id  = {$_REQUEST["catid"]} ";
      $CON->no_result($sql);
   }
   
   $savemsg = getSaveMessage(true);
}

//----------------------------------------------------------------------------------
$catid = $_REQUEST["catid"];

//----------------------------------------------------------------------------------
$sql = " select *
         from dscsell_customer_productcats_base
         where
         dct_cust_id = {$_REQUEST["id"]} and
         dct_cat_id  = {$catid}";
$custcat = $CON->select($sql);
$custcat = $custcat[0];

//----------------------------------------------------------------------------------
$sql = " select *
         from payments
         where
         pay_status > 0
         order by pay_title";
$payments = $CON->select($sql);


//----------------------------------------------------------------------------------

if((int)$custcat["dct_vol_active"])
{
   $sql = " select *
            from dscsell_customer_productcats_value
            where
            dct_cust_id = {$_REQUEST["id"]} and
            dct_cat_id  = {$catid}
            order by dct_pos asc";
   $volpos = $CON->select($sql);
}
else
{
   $sql = " select *
            from dscsell_productcats_value
            where
            dct_cat_id = {$catid}
            order by dct_pos asc";
   $volpos = $CON->select($sql);
}

//----------------------------------------------------------------------------------
$sql = " select *
         from productcats
         where
         id = {$catid}";
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
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="catid" value="<?=$_REQUEST["catid"]?>">
<?=Nifty_printH("box2", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="30">
   <col width="110">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">Asignación de familia</td>
</tr>
<tr>
   <td class="content_row">&nbsp;</td>
   <td class="content_row">Familia</td>
   <td class="content_row"><?=sprintf("%03s", $catdata[0]["id"])?> <?=$catdata[0]["cat_title"]?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?php
if((int)$catid)
{  ?>
   <?=Nifty_printH("box3", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="20">
      <col width="100">
      <col width="110">
      <col width="110">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="5">Condiciones por monto</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">
         <input type="checkbox" name="dct_vol_active" value="1"
         <?php if((int)$custcat["dct_vol_active"]) echo "checked"?>>
      </td>
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
      <tr bgcolor="<?php if($custcat["dct_vol_active"]) echo "#E1FFD6"; else echo getRowColor($x)?>">
         <td class="content_row">&nbsp;</td>
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
   <?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_itemprices');" ?>
   <?php
}
?>
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
      if((int)$catid)
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemprices)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<br><br>