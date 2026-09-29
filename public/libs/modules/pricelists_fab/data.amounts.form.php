<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------


if($_REQUEST["ccom"] == "save")
{
   $currtme = time();

   $_REQUEST["amt_val"] = getPrice(trim($_REQUEST["amt_val"]));
   $_REQUEST["amt_dsc"] = getPrice(trim($_REQUEST["amt_dsc"]));

   if((int)$_REQUEST["cid"])
   {
      $sql = " update price_lists_fab_amounts
               set
               amt_val  = {$_REQUEST["amt_val"]},
               amt_dsc  = {$_REQUEST["amt_dsc"]}
               where
               id = {$_REQUEST["cid"]}";
      $res = $CON->no_result($sql);
      
      $savemsg = getSaveMessage($res);
   }
   else
   {
      $sql = " insert into price_lists_fab_amounts
               (pl_id, amt_val, amt_dsc)
               VALUES
               ({$_REQUEST["id"]}, {$_REQUEST["amt_val"]}, {$_REQUEST["amt_dsc"]})";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
      if($res)
         $_REQUEST["cid"] = mysql_insert_id();
   }
}



//----------------------------------------------------------------------------------
if($_REQUEST["cid"] != "")
{
   $sql = " select *
            from price_lists_fab_amounts
            where
            id = {$_REQUEST["cid"]}";
   $data = $CON->select($sql);
   $data = $data[0];

}

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_customer" onsubmit="return checkform(new Array(this.amt_val))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="add">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="ccom" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="add_pos" value="<?=$data["add_pos"]?>">
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="160">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos Básicos</td>
</tr>
<tr>
   <td class="content_rowl">Cantidad *</td>
   <td class="content_row">
      <input name="amt_val" type="text" class="text" style="width:100%"
      value="<?if((int)$data["amt_val"]) echo printPrice($data["amt_val"])?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Descuento sin impresión</td>
   <td class="content_row">
      <input name="amt_dsc" type="text" class="text" style="width:100%"
      value="<?if((int)$data["amt_dsc"]) echo printPrice($data["amt_dsc"])?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130" valign="top">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&subcatexec=amounts", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["cid"] != "")
   {  ?>
      <td align="right" width="130" style="padding-right:5px" valign="top">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=amounts&id={$_REQUEST["id"]}&clearData={$_REQUEST["cid"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   ?>
   <td align="right" width="130" valign="top">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_customer)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_customer');" ?>