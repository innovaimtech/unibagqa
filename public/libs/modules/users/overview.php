<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       26.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
// delete user
//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "delete")
{
   $sql = " update user
            set
            user_status = -1
            where
            id = {$_REQUEST["uid"]}";
   $res = $CON->no_result($sql);
   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
// load library to edit an user
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "update")
{
   require_once("overview.edit.php");
}
else
{
   //----------------------------------------------------------------------------------
   $sql = " select   t1.*,
                     t2.user_lastname 'user_crtusr_name',
                     t3.user_lastname 'user_updusr_name'
            from user t1
            LEFT OUTER JOIN user t2 ON t1.user_crtusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.user_updusr = t3.id
            where
            t1.user_status >= 0
            order by t1.user_type asc, t1.user_login asc";
   $users = $CON->select($sql);

   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header">Resumen de personal</b></td>
      <td align="right"><?=$savemsg?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="30">
      <col>
      <col>
      <col>
      <col width="300">
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="6">Resumen de personal</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["USER"][21]?></td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["USER"][26]?></td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["USER"][27]?></td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["USER"][28]?></td>
      <td class="content_tbl_subheader">Roles</td>
      <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["USER"][32]?></td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($users) && $users != false; $x++)
   {
      if($users[$x]["user_type"] == "1")
         $disp_type = $_LANG["MODULE"]["USER"][34];
      else
         $disp_type = $_LANG["MODULE"]["USER"][33];

      if((int)$users[$x]["user_status"] == 0)
         $img_status = "status_red.gif";
      else
         $img_status = "status_green.gif";

      $sql = " select GROUP_CONCAT(distinct t2.group_name SEPARATOR ', ') 'cc'
               from user_group t1, `group` t2
               where
               t1.group_id = t2.id and
               t1.user_id = {$users[$x]["id"]} and
               t2.group_status = 1";
      $groupcount = $CON->select($sql);

      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row" align="center"><img src="./images/content/<?=$img_status?>"></td>
         <td class="content_row"><?=$users[$x]["user_login"]?></td>
         <td class="content_row"><?=$users[$x]["user_firstname"]?> <?=$users[$x]["user_lastname"]?></td>
         <td class="content_row"><?=$disp_type?></td>
         <td class="content_row"><?=$groupcount[0]["cc"]?></td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&subexec=update&uid={$users[$x]["id"]}", "", "pencil"); 
            ?>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <?php
}