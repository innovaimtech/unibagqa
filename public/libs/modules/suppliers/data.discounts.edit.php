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

   $sql = " delete from dscbuy_supplier_productcats
            where
            dct_supp_id = {$_REQUEST["id"]} and
            dct_cat_id  = {$_REQUEST["catid"]}";
   $CON->no_result($sql);

   if($_REQUEST["dct_scale_discount_1"] > 0 || $_REQUEST["dct_scale_discount_2"] > 0 || $_REQUEST["dct_scale_discount_3"] > 0 || $_REQUEST["dct_scale_discount_4"] > 0)
   {
      $sql = " insert into dscbuy_supplier_productcats
               (dct_supp_id, dct_cat_id, dct_scale_discount1, dct_scale_discount2, dct_scale_discount3, dct_scale_discount4,
                dct_scale_type1, dct_scale_type2, dct_scale_type3, dct_scale_type4, dct_apply_level, dct_apply_round)
               VALUES
               ({$_REQUEST["id"]}, {$_REQUEST["catid"]}, {$_REQUEST["dct_scale_discount_1"]}, {$_REQUEST["dct_scale_discount_2"]}, {$_REQUEST["dct_scale_discount_3"]},
                {$_REQUEST["dct_scale_discount_4"]}, {$_REQUEST["dct_scale_type_1"]}, {$_REQUEST["dct_scale_type_2"]}, {$_REQUEST["dct_scale_type_3"]},
                {$_REQUEST["dct_scale_type_4"]}, '{$_REQUEST["dct_apply_level"]}', '{$_REQUEST["dct_apply_round"]}')";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["catid"] == "")
{
   //----------------------------------------------------------------------------------
   $sql = " select id, cat_title
            from productcats
            where
            cat_status = 1 and
            id NOT IN
            (
               select t1.dct_cat_id
               from dscbuy_supplier_productcats t1
               where
               t1.dct_supp_id = {$_REQUEST["id"]}
            )
            order by id";
   $selcats = $CON->select($sql);
}
else
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from dscbuy_supplier_productcats
            where
            dct_supp_id = {$_REQUEST["id"]} and
            dct_cat_id  = {$_REQUEST["catid"]}";
   $suppproddisc = $CON->select($sql);
   $suppproddisc = $suppproddisc[0];

   //----------------------------------------------------------------------------------
   $sql = " select *
            from productcats
            where
            id = {$_REQUEST["catid"]}";
   $catdata = $CON->select($sql);
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
   <td class="content_tbl_subheader">Familia</td>
   <td class="content_tbl_subheader">Descuento 1</td>
   <td class="content_tbl_subheader">Descuento 2</td>
   <td class="content_tbl_subheader">Descuento 3</td>
   <td class="content_tbl_subheader">Descuento 4</td>
</tr>
<tr>
   <td class="content_row">
      <?php
      if($_REQUEST["catid"] == "")
      {  ?>
         <select name="selcatid" class="text" style="width:300px"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)"
         onchange="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=discountsedit&id=<?=$_REQUEST["id"]?>&catid=' +this.value">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($selcats AS $selcat)
            {  ?>
               <option value="<?=$selcat["id"]?>" <?php if($selcat["id"] == $_REQUEST["catid"]) echo "selected"?>><?=sprintf("%03s", $selcat["id"])?> <?=$selcat["cat_title"]?></option>
               <?php
            }
            ?>
         </select>
         <?php
      }
      else
      {  ?>
         <?=sprintf("%03s", $catdata[0]["id"])?> <?=$catdata[0]["cat_title"]?>
         <?php
      }
      ?>
   </td>
   <?php
   if($_REQUEST["catid"] != "")
   {
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
   }
   else
   {  ?>
      <td class="content_row" colspan="4">&nbsp;</td>
      <?php
   }
   ?>
</tr>
<?php
if($_REQUEST["catid"] != "")
{  ?>
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
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
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
      if((int)$_REQUEST["catid"])
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