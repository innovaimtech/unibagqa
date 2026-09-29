<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       14.02.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{

   // loop through post keys
   foreach(array_keys($_REQUEST) AS $rkey)
   {
      if(strpos($rkey, "se_name") !== false)
      {
         $idx        = substr($rkey, strrpos($rkey, "_") +1);

         $se_status  = (int)$_REQUEST["se_status_{$idx}"];
         $se_name    = trim(addslashes($_REQUEST["se_name_{$idx}"]));
         $se_desc    = trim(addslashes($_REQUEST["se_desc_{$idx}"]));
         $se_val     = trim(addslashes($_REQUEST["se_val_{$idx}"]));

         $sql = " update config_style_effects
                  set
                  style_effect_status  =  {$se_status},
                  style_effect_name    = '{$se_name}',
                  style_effect_desc    = '{$se_desc}',
                  style_effect_val     = '{$se_val}'
                  where
                  id = {$idx}";
         $res = $CON->no_result($sql);
         if(!$res)
            $errors++;
      }
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
         location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=effects&saveok=1';
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
$sql = " select *
         from config_style_effects
         order by style_effect_name asc";
$effects = $CON->select($sql);

//----------------------------------------------------------------------------------
?>
<form action="index.php" method="post" class="fokusfirst" name="xform_styleeffects">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="effects">
<input type="hidden" name="subexec" value="save">
<?=Nifty_printH("box1", "100%")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%" class="content_table">
<colgroup>
   <col width="30">
   <col>
   <col>
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4"><b><?=$_LANG["MODULE"]["STYLE"][8]?></b></td>
</tr>
<tr>
   <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["STYLE"][9]?></td>
   <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STYLE"][10]?></td>
   <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STYLE"][11]?></td>
   <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STYLE"][12]?></td>
</tr>
<?php

//----------------------------------------------------------------------------------
// loop through existing effects
//----------------------------------------------------------------------------------
for($x = 0; $x < count($effects) && $effects != false; $x++)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row" align="center">
         <input type="checkbox" name="se_status_<?=$effects[$x]["id"]?>" value="1"
         <?php if((int)$effects[$x]["style_effect_status"]) echo "checked"?>>
      </td>
      <td class="content_row">
         <input type="text" class="text" style="width:200px"
         name="se_name_<?=$effects[$x]["id"]?>"
         value="<?=$effects[$x]["style_effect_name"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row">
         <input type="text" class="text" style="width:300px"
         name="se_desc_<?=$effects[$x]["id"]?>"
         value="<?=$effects[$x]["style_effect_desc"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row">
         <input type="text" class="text color {hash:true, pickerMode:'HVS'}"  style="width:90px"
         name="se_val_<?=$effects[$x]["id"]?>"
         id="se_val_<?=$effects[$x]["id"]?>"
         value="<?=$effects[$x]["style_effect_val"]?>">
      </td>
   </tr><?php
}

//----------------------------------------------------------------------------------
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "100%")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td width="130" align="right">
      <ul class="postnav_save">
         <a href="javascript: submitForm(document.xform_styleeffects)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
      </ul>
   </td>
</tr>
</form>
</table>
<?=Nifty_printF(false)?>