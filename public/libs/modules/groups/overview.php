<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "delete")
{
   $sql = " delete from user_group
            where
            group_id = {$_REQUEST["gid"]}";
   $CON->no_result($sql);

   $sql = " delete from `group`
            where
            id = {$_REQUEST["gid"]}";
   $CON->no_result($sql);

   $sql = " delete from group_menu_items
            where
            group_id = {$_REQUEST["gid"]}";
   $CON->no_result($sql);

   $sql = " delete from calendar_shared_grp
            where
            group_id = {$_REQUEST["gid"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "update")
{
   require_once("edit.php");
}

//----------------------------------------------------------------------------------
else
{
   // select groups
   $sql = " select   t1.*,
                     t2.user_lastname 'group_crtusr_name',
                     t3.user_lastname 'group_updusr_name'
            from `group` t1
            LEFT OUTER JOIN user t2 ON t1.group_crtusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.group_updusr = t3.id
            order by t1.group_name asc";
   $groups = $CON->select($sql);

   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header">Resumen de roles</b></td>
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
      <col width="70" align="center">
      <col width="100">
      <col width="100">
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="6">Resumen de roles</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["GROUP"][15]?></td>
      <td class="content_tbl_subheader">Nombre del role</td>
      <td class="content_tbl_subheader">Personal</td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["GROUP"][21]?></td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["GROUP"][22]?></td>
      <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["GROUP"][25]?></td>
   </tr>
   <?php
   
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($groups) && $groups != false; $x++)
   {
      $sql = " select count(t2.id) 'cc'
               from user_group t1, user t2
               where
               t1.group_id = {$groups[$x]["id"]} and
               t1.user_id = t2.id and
               t2.user_status > 0 ";
      $usercount = $CON->select($sql);

      // set status icon
      if((int)$groups[$x]["group_status"] == 0)
         $img_status = "status_red.gif";
      else
         $img_status = "status_green.gif";
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row" align="center"><img src="./images/content/<?=$img_status?>"></td>
         <td class="content_row"><?=$groups[$x]["group_name"]?></td>
         <td class="content_row"><?=(int)$usercount[0]["cc"]?></td>
         <td class="content_row"><?=$groups[$x]["group_crtusr_name"]?>&nbsp;</td>
         <td class="content_row"><?=displayDate($groups[$x]["group_crtdat"])?></td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&subexec=update&gid={$groups[$x]["id"]}", "", "pencil"); 
            ?>
         </td>
      </tr><?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <?php
}