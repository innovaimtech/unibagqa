<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       15.02.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $errors  = 0;
   $currtme = time();

   $_REQUEST["style_item_name"]     = trim(addslashes($_REQUEST["style_item_name"]));
   $_REQUEST["style_item_type"]     = trim(addslashes($_REQUEST["style_item_type"]));
   $_REQUEST["style_item_desc"]     = trim(addslashes($_REQUEST["style_item_desc"]));
   $_REQUEST["style_group_id"]      = (int)$_REQUEST["style_group_id"];
   $_REQUEST["style_item_status"]   = (int)$_REQUEST["style_item_status"];

   if($_REQUEST["siid"] != "")
   {
      $sql = " update config_style_item
               set
               style_item_name   = '{$_REQUEST["style_item_name"]}',
               style_item_type   = '{$_REQUEST["style_item_type"]}',
               style_group_id    =  {$_REQUEST["style_group_id"]},
               style_item_status =  {$_REQUEST["style_item_status"]},
               style_item_desc   = '{$_REQUEST["style_item_desc"]}',
               style_item_updusr =  {$_SESSION["user_id"]},
               style_item_upddat =  {$currtme}
               where
               id = {$_REQUEST["siid"]}";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " delete from config_style_item_val
                  where
                  style_item_id = {$_REQUEST["siid"]}";
         $CON->no_result($sql);
      }
      else
         $errors++;
   }

   else
   {
      $sql = " insert into config_style_item
               (
                  style_item_name, style_item_type,
                  style_group_id, style_item_status,
                  style_item_desc, style_item_crtusr,
                  style_item_crtdat
               )
               VALUES
               (
                  '{$_REQUEST["style_item_name"]}', '{$_REQUEST["style_item_type"]}',
                   {$_REQUEST["style_group_id"]}, {$_REQUEST["style_item_status"]},
                  '{$_REQUEST["style_item_desc"]}', {$_SESSION["user_id"]},
                   {$currtme}
               )";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'siid'
                  from config_style_item";
         $siid = $CON->select($sql);

         $_REQUEST["siid"] = $siid[0]["siid"];
      }
      else
         $errors++;
   }

   foreach(array_keys($_REQUEST) AS $rkey)
   {
      if(strpos($rkey, "itm_col_") !== false)
      {
         $item_col = trim($_REQUEST[$rkey]);
         
         if($item_col != "" && !(int)$errors)
         {
            $idx = substr($rkey,strrpos($rkey,"_") +1);

            $item_val = trim($_REQUEST["itm_val_{$idx}"]);
            $item_chk = (int)$_REQUEST["itm_chk_{$idx}"];

            $sql = " insert into config_style_item_val
                     (style_item_id, style_item_col, style_item_val, style_item_status)
                     VALUES
                     ({$_REQUEST["siid"]}, '{$item_col}', '{$item_val}', {$item_chk})";
            $res = $CON->no_result($sql);

            if(!$res)
               $errors++;
         }
      }
   }

   if(trim($_REQUEST["itm_new_col"]) != "" && !(int)$errors)
   {
      $_REQUEST["itm_new_val"] = trim(addslashes($_REQUEST["itm_new_val"]));
      $_REQUEST["itm_new_col"] = trim(addslashes($_REQUEST["itm_new_col"]));
      $_REQUEST["itm_new_chk"] = (int)$_REQUEST["itm_new_chk"];

      $sql = " insert into config_style_item_val
               (style_item_id, style_item_col, style_item_val, style_item_status)
               VALUES
               ({$_REQUEST["siid"]}, '{$_REQUEST["itm_new_col"]}',
                '{$_REQUEST["itm_new_val"]}', {$_REQUEST["itm_new_chk"]})";
      $res = $CON->no_result($sql);

      if(!$res)
         $errors++;
   }

   if($errors)
   {  ?>
      <script language="JavaScript">
         document.all.idx_savemsg.innerHTML = "<?=getSaveMessage(false)?>";
      </script>
      <?php
   }
   else
   {  ?>
      <script language="JavaScript">
         location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&siid=<?=$_REQUEST["siid"]?>&sgid=<?=$_REQUEST["sgid"]?>&saveok=1';
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["siid"] != "")
{
   $sql = " select *
            from config_style_item
            where
            id = {$_REQUEST["siid"]}";
   $item = $CON->select($sql);
}
else
{
   $item[0]["style_item_status"] = "1";
   $item[0]["style_group_id"]    = $_REQUEST["sgid"];
}

//----------------------------------------------------------------------------------
$sql = " select *
         from config_style_group
         order by style_group_name";
$groups = $CON->select($sql);

//----------------------------------------------------------------------------------
?>
<form action="index.php" method="post" name="xform_eclass"
 onsubmit="return checkform(new Array(this.style_item_name, this.style_item_type, this.style_group_id))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="siid" value="<?=$_REQUEST["siid"]?>">
<input type="hidden" name="sgid" value="<?=$_REQUEST["sgid"]?>">
<?=Nifty_printH("box1", "100%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="150">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["STYLE"][0]?></td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["STYLE"][1]?></td>
   <td class="content_row">
      <input name="style_item_name" type="text" class="text" style="width:190px" value="<?=$item[0]["style_item_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["STYLE"][2]?></td>
   <td class="content_row">
      <input name="style_item_type" type="text" class="text" style="width:190px" value="<?=$item[0]["style_item_type"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["STYLE"][3]?></td>
   <td class="content_row">
      <select name="style_group_id" class="text" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($groups AS $group)
         {  ?>
            <option value="<?=$group["id"]?>" <?php if($group["id"] == $item[0]["style_group_id"]) echo "selected"?>><?=$group["style_group_name"]?></option><?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["STYLE"][4]?></td>
   <td class="content_row">
      <input name="style_item_status" type="checkbox" value="1"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" <?php if($item[0]["style_item_status"] == "1") echo "checked"?>>
   </td>
</tr>
<tr>
   <td class="content_row" valign="top"><?=$_LANG["MODULE"]["STYLE"][5]?></td>
   <td class="content_row">
      <textarea name="style_item_desc" class="text" style="width:350px; height:80px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$item[0]["style_item_desc"]?></textarea>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "100%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="40">
   <col width="160">
   <col width="160">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3"><?=$_LANG["MODULE"]["STYLE"][6]?></td>
</tr>
<tr>
   <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["STYLE"][7]?></td>
   <td class="content_tbl_subheader"><?=$_LANG["TABLE"]["HEADER"][0]?></td>
   <td class="content_tbl_subheader"><?=$_LANG["TABLE"]["HEADER"][1]?></td>
</tr>
<?php

//----------------------------------------------------------------------------------
if($item[0]["id"] != "")
{
   $sql = " select *
            from config_style_item_val
            where
            style_item_id = {$item[0]["id"]}
            order by id";
   $itemvals = $CON->select($sql);

   for($y = 0; $y < count($itemvals) && $itemvals != false; $y++)
   {  ?>
      <tr bgcolor="<?=getRowColor($y)?>">
         <td class="content_row" align="center">
            <input name="itm_chk_<?=$y?>" type="checkbox" value="1"
            <?php if((int)$itemvals[$y]["style_item_status"] > 0) echo "checked"?>>
         </td>
         <td class="content_row">
            <input name="itm_col_<?=$y?>" type="text" class="text" style="width:210px" value="<?=$itemvals[$y]["style_item_col"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row">
            <input name="itm_val_<?=$y?>" type="text" style="width:210px" value="<?=$itemvals[$y]["style_item_val"]?>"
            <?php
            if(strlen(trim($itemvals[$y]["style_item_val"])) == 7 && substr($itemvals[$y]["style_item_val"],0,1) == "#")
            {  ?>
               class="text color {hash:true, pickerMode:'HVS'}"
               <?php
            }
            else
            {  ?>
               class="text" onfocus="markfield(this,0)" onblur="markfield(this,1)"
               <?php
            }
            ?>>
         </td>
      </tr>
      <?php
   }
}

//----------------------------------------------------------------------------------
?>
<tr bgcolor="<?=getRowColor($y)?>">
   <td class="content_row" align="center">
      <input type="checkbox" name="itm_new_chk" id="id_chk_<?=$x?>_<?=$y?>" value="1" checked>
   </td>
   <td class="content_row">
      <input name="itm_new_col" type="text" class="text" style="width:210px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
   <td class="content_row">
      <input name="itm_new_val" type="text" class="text" style="width:210px" value=""
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "100%")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td width="130">
      <ul class="postnav">
         <a href="index.php?mid=<?=$_REQUEST["mid"]?>&sgid=<?=$_REQUEST["sgid"]?>&siid=<?=$_REQUEST["siid"]?>"><?=$_LANG["FORM"]["BUTTON"][1]?></a>
      </ul>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["siid"] != "")
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <ul class="postnav_del">
            <a href="javascript: deactivateFormChange()"
            onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&sgid=<?=$_REQUEST["sgid"]?>&siid=<?=$_REQUEST["siid"]?>&exec=delete')"><?=$_LANG["FORM"]["BUTTON"][2]?></a>
         </ul>
      </td>
      <?php
   }
   ?>
   <td width="130" align="right">
      <ul class="postnav_save">
         <a href="javascript: submitForm(document.xform_eclass)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
      </ul>
   </td>
</tr>
</form>
</table>
<?=Nifty_printF(false)?>
<script language="JavaScript">
   <?php
   if($_REQUEST["siid"] != "")
   {
      $_SESSION["JSEXEC"] .= "document.all.itm_new_col.focus();";
   }
   else
   {
      $_SESSION["JSEXEC"] .= "document.all.style_item_name.focus();";
   }  ?>
</script>