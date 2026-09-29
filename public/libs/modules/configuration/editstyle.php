<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       26.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["execgrp"] == "newgrp")
{
   $_REQUEST["style_group_name"] = trim(addslashes($_REQUEST["style_group_name"]));
   $_REQUEST["style_group_desc"] = trim(addslashes($_REQUEST["style_group_desc"]));
   
   $_REQUEST["style_group_status"] = (int)$_REQUEST["style_group_status"];

   $sql = " insert into config_style_group
            (style_group_name, style_group_status, style_group_desc)
            VALUES
            ('{$_REQUEST["style_group_name"]}', {$_REQUEST["style_group_status"]},
             '{$_REQUEST["style_group_desc"]}')";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);

   if($res)
   {
      $sql = " select MAX(id) 'id'
               from config_style_group";
      $maxid = $CON->select($sql);
   
      $_REQUEST["sgid"] = $maxid[0]["id"];
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "delete")
{
   $sql = " delete from config_style_item
            where
            id = {$_REQUEST["siid"]}";
   $CON->no_result($sql);

   $sql = " delete from config_style_item_val
            where
            style_item_id = {$_REQUEST["siid"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "deletegrp")
{
   $sql = " select id
            from config_style_item
            where
            style_group_id =  {$_REQUEST["sgid"]}";
   $gitems = $CON->select($sql);

   for($x = 0; $x < count($gitems) && $gitems != false; $x++)
   {
      $sql = " delete from config_style_item_val
               where
               style_item_id = {$gitems[$x]["id"]}";
      $CON->no_result($sql);
   }

   $sql = " delete from config_style_item
            where
            style_group_id =  {$_REQUEST["sgid"]}";
   $CON->no_result($sql);

   $sql = " delete from config_style_group
            where
            id = {$_REQUEST["sgid"]}";
   $CON->no_result($sql);

   $_REQUEST["sgid"] = "";

}

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "savegrp")
{
   $_REQUEST["style_group_status"]  = (int)$_REQUEST["style_group_status"];
   $_REQUEST["style_group_name"]    = trim(addslashes($_REQUEST["style_group_name"]));
   $_REQUEST["style_group_desc"]    = trim(addslashes($_REQUEST["style_group_desc"]));

   $sql = " update config_style_group
            set
            style_group_name     = '{$_REQUEST["style_group_name"]}',
            style_group_status   =  {$_REQUEST["style_group_status"]},
            style_group_desc     = '{$_REQUEST["style_group_desc"]}'
            where
            id = {$_REQUEST["sgid"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
$sql = " select   t1.*,
                  t2.user_lastname 'style_group_crtusr',
                  t3.user_lastname 'style_group_updusr'
         from config_style_group t1
         LEFT OUTER JOIN user t2 ON t1.style_group_crtusr = t2.id
         LEFT OUTER JOIN user t3 ON t1.style_group_updusr = t3.id
         order by t1.style_group_name ";
$groups = $CON->select($sql);

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] != "newgrp")
{
   if($_REQUEST["sgid"] == "")
   {
      $_REQUEST["sgid"] = $groups[0]["id"];
      $thisgroup        = $groups[0];
   }
   else
   {
      for($x = 0; $x < count($groups) && $groups != false; $x++)
         if($groups[$x]["id"] == $_REQUEST["sgid"])
            $thisgroup = $groups[$x];
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["sgid"] != "")
{
   $sql = " select   t1.*,
                     t2.user_lastname 'style_item_crtusr',
                     t3.user_lastname 'style_item_updusr'
            from config_style_item t1
            LEFT OUTER JOIN user t2 ON t1.style_item_crtusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.style_item_updusr = t3.id
            where
            t1.style_group_id = {$_REQUEST["sgid"]}
            order by t1.style_item_name";
   $items = $CON->select($sql);
}
else
   $_REQUEST["exec"] = "newgrp";

if($_REQUEST["saveok"] == "1")
   $savemsg = getSaveMessage(true);

//----------------------------------------------------------------------------------
?>
<script type="text/javascript" src="./libs/jscripts/jscolor/jscolor.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="822">
<tr>
   <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["STYLE"][13]?></b></td>
   <td align="right" id="idx_savemsg"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<table cellpadding="0" cellspacing="0" width="822" style="table-layout:fixed">
<colgroup>
   <col width="130" valign="top">
   <col width="15">
   <col width="677" valign="top">
</colgroup>
<tr>
   <td valign="top">
      <?=Nifty_printH("box1", "100%")?>
      <table cellpadding="2" cellspacing="0" class="content_table" width="100%">
      <tr>
         <td class="content_tbl_header">
            <b><?=$_LANG["MODULE"]["STYLE"][14]?></b>
         </td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($groups) && $groups != false; $x++)
      {
         if($groups[$x]["id"] == $_REQUEST["sgid"] && $_REQUEST["exec"] != "effects")
            $groupstyle = "content_row_select_active";
         else
            $groupstyle = "content_row_select";
         ?>
         <tr>
            <td class="<?=$groupstyle?>"
            <?php
            if($groups[$x]["id"] != $_REQUEST["sgid"] || $_REQUEST["exec"] == "effects")
            {  ?>
               onmouseover="mark(this,0)" onmouseout="mark(this,1)"<?php
            }  ?>
            onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&sgid=<?=$groups[$x]["id"]?>'">
            &nbsp;<img src="./images/menu/menu_prg.gif"><?=$groups[$x]["style_group_name"]?>
            </td>
         </tr><?php
      }

      //----------------------------------------------------------------------------------
      if($_REQUEST["exec"] == "effects")
         $groupstyle = "content_row_select_active";
      else
         $groupstyle = "content_row_select";

      //----------------------------------------------------------------------------------
      ?>
      <tr>
         <td class="<?=$groupstyle?>"
         <?php
         if($_REQUEST["exec"] != "effects")
         {  ?>
            onmouseover="mark(this,0)" onmouseout="mark(this,1)"<?php
         }  ?>
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=effects'">
         &nbsp;<img src="./images/menu/menu_prg.gif"><?=$_LANG["MODULE"]["STYLE"][15]?>
         </td>
      </tr>
      <?php
      
      //----------------------------------------------------------------------------------
      ?>
      <tr>
         <td class="content_row_select">
            <input type="button" class="button" value="<?=$_LANG["MODULE"]["STYLE"][16]?>" style="width:100%"
            onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=newgrp'"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)">
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
   </td>
   <td></td>
   <td valign="top">
      <?php
      //----------------------------------------------------------------------------------
      if($_REQUEST["exec"] == "edit")
      {
         require_once("editstyle.editclass.php");
      }
      //----------------------------------------------------------------------------------
      elseif($_REQUEST["exec"] == "effects")
      {
         require_once("editstyle.effects.php");
      }
      //----------------------------------------------------------------------------------
      else
      {  ?>
         <form action="index.php" method="post" class="fokusfirst" name="xform_estyle"
          onsubmit="return checkform(new Array(this.style_group_name))">
         <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
         <input type="hidden" name="sgid" value="<?=$_REQUEST["sgid"]?>">
         <input type="hidden" name="exec" value="savegrp">
         <input type="hidden" name="execgrp" value="<?=$_REQUEST["exec"]?>">
         <?=Nifty_printH("box1", "100%")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%" class="content_table">
         <colgroup>
            <col width="150">
            <col>
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["STYLE"][17]?></td>
         </tr>
         <tr>
            <td class="content_row"><?=$_LANG["MODULE"]["STYLE"][18]?></td>
            <td class="content_row">
               <input name="style_group_name" type="text" class="text" style="width:190px" value="<?=$thisgroup["style_group_name"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr>
            <td class="content_row"><?=$_LANG["MODULE"]["STYLE"][19]?></td>
            <td class="content_row">
               <input name="style_group_status" type="checkbox" value="1" <?php if($thisgroup["style_group_status"] == "1") echo "checked"?>>
            </td>
         </tr>
         <tr>
            <td class="content_row" valign="top"><?=$_LANG["MODULE"]["STYLE"][20]?></td>
            <td class="content_row">
               <textarea name="style_group_desc" class="text" style="width:350px; height:80px"
               onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$thisgroup["style_group_desc"]?></textarea>
            </td>
         </tr>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?=Nifty_printH("boxopt_b", "100%")?>
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td width="130">
               <?php
               //----------------------------------------------------------------------------------
               if($_REQUEST["exec"] != "newgrp")
               {  ?>
                  <ul class="postnav">
                     <a href="index.php?mid=<?=$_REQUEST["mid"]?>&sgid=<?=$_REQUEST["sgid"]?>&exec=edit"><?=$_LANG["MODULE"]["STYLE"][21]?></a>
                  </ul>
                  <?php
               }
               ?>
            </td>
            <td>&nbsp;</td>
            <td width="130" align="right" style="padding-right:5px">
               <?php
               //----------------------------------------------------------------------------------
               if($_REQUEST["exec"] != "newgrp")
               {  ?>
                  <ul class="postnav_del">
                     <a href="javascript: deactivateFormChange()"
                     onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&sgid=<?=$_REQUEST["sgid"]?>&exec=deletegrp')"><?=$_LANG["FORM"]["BUTTON"][2]?></a>
                  </ul>
                  <?php
               }
               ?>
            </td>
            <td align="right" width="130">
               <ul class="postnav_save">
                  <a href="javascript: submitForm(document.xform_estyle)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
               </ul>
            </td>
         </tr>
         </form>
         </table>
         <?=Nifty_printF(false)?>
         <?php
         //----------------------------------------------------------------------------------
         if($_REQUEST["exec"] != "newgrp")
         {  ?>
            <br>
            <?=Nifty_printH("box1", "100%")?>
            <table border="0" cellpadding="3" cellspacing="0" width="100%" class="content_table">
            <colgroup>
               <col width="30">
               <col>
               <col width="70">
               <col width="85">
               <col width="100">
               <col width="85">
               <col width="100">
               <col width="90" align="center">
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="8"><b><?=$_LANG["MODULE"]["STYLE"][22]?></b></td>
            </tr>
            <tr>
               <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["STYLE"][9]?></td>
               <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STYLE"][23]?></td>
               <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STYLE"][24]?></td>
               <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STYLE"][25]?></td>
               <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STYLE"][26]?></td>
               <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STYLE"][27]?></td>
               <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STYLE"][28]?></td>
               <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["STYLE"][29]?></td>
            </tr>
            <?php
            //----------------------------------------------------------------------------------
            for($x = 0; $x < count($items) && $items != false; $x++)
            {
               //----------------------------------------------------------------------------------
               if((int)$items[$x]["style_item_status"] == 0)
                  $img_status = "status_red.gif";
               else
                  $img_status = "status_green.gif";
               
               ?>
               <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row" align="center"><img src="./images/content/<?=$img_status?>"></td>
                  <td class="content_row"><?=$items[$x]["style_item_name"]?></td>
                  <td class="content_row"><?=$items[$x]["style_item_type"]?></td>
                  <td class="content_row"><?=$items[$x]["style_item_crtusr"]?>&nbsp;</td>
                  <td class="content_row"><?=date('d.m.y',$items[$x]["style_item_crtdat"])?>&nbsp;</td>
                  <td class="content_row"><?=$items[$x]["style_item_updusr"]?>&nbsp;</td>
                  <td class="content_row"><?=date('d.m.y',$items[$x]["style_item_upddat"])?>&nbsp;</td>
                  <td class="content_row" align="center">
                     <ul class="postnav">
                        <a href="index.php?mid=<?=$_REQUEST["mid"]?>&sgid=<?=$_REQUEST["sgid"]?>&siid=<?=$items[$x]["id"]?>&exec=edit"><?=$_LANG["FORM"]["BUTTON"][3]?></a>
                     </ul>
                  </td>
               </tr>
               <?php
            }
            ?>
            </table>
            <?=Nifty_printF()?>
            <?php
         }
      }  ?>
   </td>
</tr>
</table>
<br>