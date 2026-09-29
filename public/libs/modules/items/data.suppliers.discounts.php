<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   //----------------------------------------------------------------------------------
   $sql = " delete from dscbuy_supplier_item_volume
            where
            dct_item_id       = {$_REQUEST["id"]} and
            dct_item_type     = '{$_REQUEST["itemtype"]}' and
            dct_supplier_id   = {$_REQUEST["supplierid"]}";
   $CON->no_result($sql);

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
            $sql = " insert into dscbuy_supplier_item_volume
                     (dct_item_id, dct_supplier_id, dct_item_type, dct_pos, dct_scale_amtfrom, dct_scale_amtto, dct_scale_discount, dct_scale_type)
                     VALUES
                     ({$_REQUEST["id"]}, {$_REQUEST["supplierid"]}, '{$_REQUEST["itemtype"]}', {$poscounter}, {$dct_scale_pricefrom},
                      {$dct_scale_priceto}, {$dct_scale_discount}, {$dct_scale_type})";
            $CON->no_result($sql);

            $poscounter++;
         }
      }
   }

   //----------------------------------------------------------------------------------
   //echo "<pre>";
   //print_r($_REQUEST);

   $_REQUEST["dct_scale_override"]   = (int)$_REQUEST["dct_scale_override"];
   $_REQUEST["dct_dsc_off"]          = (int)$_REQUEST["dct_dsc_off"];
   $_REQUEST["dct_scale_discount_1"] = getPrice($_REQUEST["dct_scale_discount_1"], 2);
   $_REQUEST["dct_scale_discount_2"] = getPrice($_REQUEST["dct_scale_discount_2"], 2);
   $_REQUEST["dct_scale_discount_3"] = getPrice($_REQUEST["dct_scale_discount_3"], 2);
   $_REQUEST["dct_scale_discount_4"] = getPrice($_REQUEST["dct_scale_discount_4"], 2);

   $_REQUEST["dct_scale_type_1"] = (int)$_REQUEST["dct_scale_type_1"];
   $_REQUEST["dct_scale_type_2"] = (int)$_REQUEST["dct_scale_type_2"];
   $_REQUEST["dct_scale_type_3"] = (int)$_REQUEST["dct_scale_type_3"];
   $_REQUEST["dct_scale_type_4"] = (int)$_REQUEST["dct_scale_type_4"];

   $_REQUEST["dct_apply_level"] = trim(addslashes($_REQUEST["dct_apply_level"]));
   $_REQUEST["dct_apply_round"] = trim(addslashes($_REQUEST["dct_apply_round"]));
   
   $sql = " delete from dscbuy_supplier_item
            where
            dct_item_id       = {$_REQUEST["id"]} and
            dct_supplier_id   = {$_REQUEST["supplierid"]} and
            dct_item_type     = '{$_REQUEST["itemtype"]}'";
   $CON->no_result($sql);

   $sql = " insert into dscbuy_supplier_item
            (dct_item_id, dct_item_type, dct_supplier_id, dct_scale_override, dct_dsc_off,
             dct_scale_discount1, dct_scale_discount2, dct_scale_discount3, dct_scale_discount4,
             dct_scale_type1, dct_scale_type2, dct_scale_type3, dct_scale_type4,
             dct_apply_level, dct_apply_round)
            VALUES
            ({$_REQUEST["id"]}, '{$_REQUEST["itemtype"]}', {$_REQUEST["supplierid"]},
             {$_REQUEST["dct_scale_override"]}, {$_REQUEST["dct_dsc_off"]},
             {$_REQUEST["dct_scale_discount_1"]}, {$_REQUEST["dct_scale_discount_2"]}, {$_REQUEST["dct_scale_discount_3"]},
             {$_REQUEST["dct_scale_discount_4"]}, {$_REQUEST["dct_scale_type_1"]}, {$_REQUEST["dct_scale_type_2"]},
             {$_REQUEST["dct_scale_type_3"]}, {$_REQUEST["dct_scale_type_4"]}, '{$_REQUEST["dct_apply_level"]}',
             '{$_REQUEST["dct_apply_round"]}')";
   $CON->no_result($sql);
   
   $savemsg = getSaveMessage(true);
}

$sql = " select *
         from dscbuy_supplier_item_volume
         where
         dct_item_id       = {$_REQUEST["id"]} and
         dct_item_type     = '{$_REQUEST["itemtype"]}' and
         dct_supplier_id   = {$_REQUEST["supplierid"]}
         order by dct_pos asc";
$volpos = $CON->select($sql);

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

$sql = " select t1.*, t2.cat_id
         from item t1
         INNER JOIN item_productcats t2 ON t1.id = t2.item_id
         where
         t1.id = {$_REQUEST["id"]} ";
$item = $CON->select($sql);
$item = $item[0];
   
$sql = " select *
         from dscbuy_supplier_item
         where
         dct_item_id       = {$_REQUEST["id"]} and
         dct_item_type     = '{$_REQUEST["itemtype"]}' and
         dct_supplier_id   = {$_REQUEST["supplierid"]}";
$suppproddisc = $CON->select($sql);
$suppproddisc = $suppproddisc[0];
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
<input type="hidden" name="supplierid" value="<?=$_REQUEST["supplierid"]?>">
<input type="hidden" name="itemtype" value="<?=$_REQUEST["itemtype"]?>">
<?=Nifty_printH("box2", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col>
   <col width="160">
   <col width="160">
   <col width="160">
   <col width="160">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="5">Condiciones por familia</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Activación</td>
   <td class="content_tbl_subheader">Descuento 1</td>
   <td class="content_tbl_subheader">Descuento 2</td>
   <td class="content_tbl_subheader">Descuento 3</td>
   <td class="content_tbl_subheader">Descuento 4</td>
</tr>
<tr <?php if((int)$suppproddisc["dct_scale_override"]) echo "bgcolor='#E1FFD6'"?>>
   <td class="content_row">
      <input type="checkbox" name="dct_scale_override" value="1" <?php if((int)$suppproddisc["dct_scale_override"]) echo "checked"?>> Sobreescribir Descuentos
   </td>
   <?php
   for($y = 1; $y <= 4; $y++)
   {  ?>
      <td class="content_row">
         <input name="dct_scale_discount_<?=$y?>" type="text" class="text" style="width:60px"
         value="<?php
         if($suppproddisc["dct_scale_discount{$y}"] > 0.00)
            echo printPrice($suppproddisc["dct_scale_discount{$y}"],2);
         ?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         <select class="text" style="width:40px" name="dct_scale_type_<?=$y?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
            <option value="0" <?php if(!(int)$suppproddisc["dct_scale_type{$y}"]) echo "selected" ?>>%</option>
            <option value="1" <?php if((int)$suppproddisc["dct_scale_type{$y}"]) echo "selected" ?>><?=$_SESSION["_CONF"]["conf_currency"]?></option>
         </select>
      </td>
      <?php
   }
   ?>
</tr>
<tr>
   <td class="content_rowl"><b>Calculo de descuentos</b></td>
   <td class="content_rowl" colspan="4">
      <table border="0" cellspacing="4" cellpadding="0" width="100%">
      <colgroup>
         <col width="40">
         <col width="250">
         <col width="60">
         <col>
      </colgroup>
      <tr>
         <td class="content_rowl" style="border-top:0px"><b>Nivel</b></td>
         <td class="content_row_clear">
            <input type="radio" name="dct_apply_level" value="ITEM"
            <?php if($suppproddisc["dct_apply_level"] == "ITEM" || $suppproddisc["dct_apply_level"] == "") echo "checked"?>>Precio unitario
            <input type="radio" name="dct_apply_level" value="TOTAL"
            <?php if($suppproddisc["dct_apply_level"] == "TOTAL") echo "checked"?>>Precio total
         </td>
         <td class="content_rowl" style="border-top:0px"><b>Redondeo</b></td>
         <td class="content_row_clear">
            <input type="radio" name="dct_apply_round" value="UP"
            <?php if($suppproddisc["dct_apply_round"] == "UP" || $suppproddisc["dct_apply_round"] == "") echo "checked"?>>Mayor
            <input type="radio" name="dct_apply_round" value="DOWN"
            <?php if($suppproddisc["dct_apply_round"] == "DOWN") echo "checked"?>>Menor
            <input type="radio" name="dct_apply_round" value="NORMAL"
            <?php if($suppproddisc["dct_apply_round"] == "NORMAL") echo "checked"?>>Científico
         </td>
      </tr>
      </table>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="120">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Desactivación descuentos</td>
</tr>
<tr <?php if((int)$suppproddisc["dct_dsc_off"]) echo "bgcolor='#E1FFD6'"?>>
   <td class="content_rowl">Descuentos</td>
   <td class="content_row">
      <input type="checkbox" name="dct_dsc_off" value="1" <?php if((int)$suppproddisc["dct_dsc_off"]) echo "checked"?>> Descuentos desactivados
   </td>
</tr>
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
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Condiciones por volumen</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Condición</td>
   <td class="content_tbl_subheader">Cantidad de</td>
   <td class="content_tbl_subheader">Cantidad hasta</td>
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
   <td align="left" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=basic&id={$_REQUEST["id"]}", "", "arrow-180");
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
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_itemprices');" ?>