<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       22.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["exec"] == "save")
{
   $currtme = time();

   $_REQUEST["group_status"]  = (int)$_REQUEST["group_status"];
   $_REQUEST["group_visible"] = (int)$_REQUEST["group_visible"];
   $_REQUEST["group_name"]    = trim(addslashes($_REQUEST["group_name"]));
   $_REQUEST["group_desc"]    = trim(addslashes($_REQUEST["group_desc"]));

   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "update")
   {
      $sql = " update `group`
               set
               group_name     = '{$_REQUEST["group_name"]}',
               group_desc     = '{$_REQUEST["group_desc"]}',
               group_status   =  {$_REQUEST["group_status"]},
               group_visible  =  {$_REQUEST["group_visible"]},
               group_updusr   =  {$_SESSION["user_id"]},
               group_upddat   =  {$currtme}
               where
               id             = {$_REQUEST["gid"]}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);

      if($res)
      {
         $sql = " delete from user_group
                  where
                  group_id = {$_REQUEST["gid"]}";
         $CON->no_result($sql);
      }

      $groupid = $_REQUEST["gid"];
   }

   //----------------------------------------------------------------------------------
   else
   {
      $sql = " insert into `group`
               (group_name, group_desc, group_status, group_visible, group_crtusr, group_crtdat)
               VALUES
               ('{$_REQUEST["group_name"]}', '{$_REQUEST["group_desc"]}',
                 {$_REQUEST["group_status"]}, {$_REQUEST["group_visible"]},
                 {$_SESSION["user_id"]}, {$currtme})";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);

      if($res)
      {
         $sql = " select MAX(id) 'gid'
                  from `group`";
         $groupid = $CON->select($sql);
         $groupid = $groupid[0]["gid"];
      }
   }

   if($res && is_array($_REQUEST["userids"]))
   {
      foreach($_REQUEST["userids"] AS $userid)
      {
         $sql = " insert into user_group
                  (user_id, group_id)
                  VALUES
                  ({$userid}, {$groupid})";
         $CON->no_result($sql);
      }
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "update")
{
   $title = "Cambiar rol";

   $sql = " select *
            from `group`
            where
            id = {$_REQUEST["gid"]}";
   $groupdata = $CON->select($sql);

   $sql = " select t2.*
            from user_group t1
            INNER JOIN user t2 ON t1.user_id = t2.id and t2.user_status > 0
            where
            t1.group_id = {$_REQUEST["gid"]}
            order by t2.user_login asc";
   $usersel = $CON->select($sql);

   $usersel_str = "";
   for($x = 0; $x < count($usersel) && $usersel != false; $x++)
      $usersel_str .= "{$usersel[$x]["id"]}, ";

   $usersel_str = substr($usersel_str, 0, -2);

   if($usersel_str != "")
      $sql = " select *
               from user
               where
               id not in ({$usersel_str}) and
               user_status = 1
               order by user_login asc";
   else
      $sql = " select *
               from user
               where
               user_status = 1
               order by user_login asc";

   $users = $CON->select($sql);
}
else
{
   $title = "Agregar rol";

   $sql = " select *
            from user
            where
            user_status = 1
            order by user_login asc";
   $users = $CON->select($sql);
}

//----------------------------------------------------------------------------------
?>
<form action="index.php" method="post" class="fokusfirst" name="xform_grp"
 onsubmit="return checkform(new Array(this.group_name))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="save">
<input type="hidden" name="subexec" value="<?=$_REQUEST["subexec"]?>">
<input type="hidden" name="gid" value="<?=$_REQUEST["gid"]?>">
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="150">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Rol</td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["GROUP"][3]?></td>
   <td class="content_row">
      <input name="group_name" type="text" class="text" style="width:820px" value="<?=$groupdata[0]["group_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row" valign="top"><?=$_LANG["MODULE"]["GROUP"][4]?></td>
   <td class="content_row">
      <textarea name="group_desc" class="text" style="width:820px; height:100px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$groupdata[0]["group_desc"]?></textarea>
   </td>
</tr>
<tr style="display:none">
   <td class="content_row"><?=$_LANG["MODULE"]["GROUP"][5]?></td>
   <td class="content_row">
      <input name="group_status" type="checkbox" value="1"
      <?php if((int)$groupdata[0]["group_status"] || $_REQUEST["subexec"] != "update") echo "checked"?>>
   </td>
</tr>
<tr style="display:none">
   <td class="content_row"><?=$_LANG["MODULE"]["GROUP"][26]?></td>
   <td class="content_row">
      <input name="group_visible" type="checkbox" value="1"
      <?php if((int)$groupdata[0]["group_visible"] || $_REQUEST["subexec"] != "update") echo "checked"?>>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <?php
   if($_REQUEST["subexec"] == "update")
   {  ?>
      <td width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <?php
      if($_REQUEST["gid"] != 15)
      {  ?>
         <td align="right" width="130" style="padding-right:5px">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=delete&gid={$_REQUEST["gid"]}')", "cross-circle-frame");
            ?>
         </td>
         <?php
      }
   }
   else
   {  ?>
      <td>&nbsp;</td>
      <?php
   }
   ?>
   <td width="130" align="right">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_grp)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
//----------------------------------------------------------------------------------
if(count($usersel) && $usersel != false)
{  ?>
   <?=Nifty_printH("box2", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="30">
      <col width="300">
      <col width="400">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="4">Personal seleccionado</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["GROUP"][15]?></td>
      <td class="content_tbl_subheader">Personal</td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["GROUP"][13]?></td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["GROUP"][14]?></td>
   </tr>
   <?php
   for($x = 0; $x < count($usersel); $x++)
   {
      if($usersel[$x]["user_type"] == "1")
         $disp_type = $_LANG["MODULE"]["GROUP"][16];
      else
         $disp_type = $_LANG["MODULE"]["GROUP"][17];
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row" align="center">
            <input type="checkbox" name="userids[]" value="<?=$usersel[$x]["id"]?>" checked>
         </td>
         <td class="content_row"><?=$usersel[$x]["user_login"]?></td>
         <td class="content_row"><?="{$usersel[$x]["user_firstname"]} {$usersel[$x]["user_lastname"]}"?></td>
         <td class="content_row"><?=$disp_type?></td>
      </tr><?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
}
?>
<table border="0" width="980" cellpadding="0" cellspacing="0">
<colgroup>
   <col width="480">
   <col width="15">
   <col>
</colgroup>
<tr>
   <td class="content_row_clear" valign="top">
      <?php
      //----------------------------------------------------------------------------------
      if(count($users) && $users != false)
      {  ?>
         <?=Nifty_printH("box3", "100%")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="30">
            <col>
            <col width="100">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4">Personal disponible</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["GROUP"][15]?></td>
            <td class="content_tbl_subheader">Persona</td>
            <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["GROUP"][14]?></td>
         </tr>
         <?php
         for($x = 0; $x < count($users); $x++)
         {
            if($users[$x]["user_type"] == "1")
               $disp_type = $_LANG["MODULE"]["GROUP"][16];
            else
               $disp_type = $_LANG["MODULE"]["GROUP"][17];
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row" align="center">
                  <input type="checkbox" name="userids[]" value="<?=$users[$x]["id"]?>">
               </td>
               <td class="content_row"><?="{$users[$x]["user_firstname"]} {$users[$x]["user_lastname"]}"?></td>
               <td class="content_row"><?=$disp_type?></td>
            </tr><?php
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <?php
      }
      ?>
   </td>
   <td class="content_row_clear" valign="top">&nbsp;</td>
   <td class="content_row_clear" valign="top">
      <?php
      $sql = " select   distinct t1.id, t1.menu_name1 'menu_name', t1.menu_name1, t1.menu_name2,
                        t1.menu_name3, t1.menu_link, t1.menu_link_mod, t1.menu_mod_params,
                        t1.menu_adm, t1.menu_public, t1.menu_parent, t1.menu_docid, t1.menu_icon,
                        t1.menu_behavior, t1.menu_desc,
                        t1.menu_trancode, t1.menu_spacer
               from menu_items t1
               LEFT OUTER JOIN group_menu_items t2 ON ( t1.id = t2.menu_id )
               where
               t1.menu_link = 1 and
               (
                  t2.group_id = {$_REQUEST["gid"]} or
                  t1.menu_public = 1
               )
               order by t1.menu_parent, t1.menu_order asc, t1.menu_name1 asc";
      $mitems = $CON->select($sql);
      ?>
      <?=Nifty_printH("box3", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_header">Accesos del rol</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader">Ruta</td>
      </tr>
      <?php
      $x = 0;
      foreach($mitems AS $mitem)
      {
         $mname = $mitem["menu_name"];
         if((int)$mitem["menu_parent"])
         {
            $sql = " select menu_name1, menu_parent
                     from menu_items
                     where
                     id = {$mitem["menu_parent"]}";
            $pname1 = $CON->select($sql);
            $pname1 = $pname1[0];
            $mname = $pname1["menu_name1"]." &gt; ".$mname;

            if((int)$pname1["menu_parent"])
            {
               $sql = " select menu_name1, menu_parent
                        from menu_items
                        where
                        id = {$pname1["menu_parent"]}";
               $pname2 = $CON->select($sql);
               $pname2 = $pname2[0];
               $mname = $pname2["menu_name1"]." &gt; ".$mname;
            }
         }
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row"><img src="./images/menu/icons/gear.png" style="vertical-align:bottom">&nbsp;<?=$mname?></td>
         </tr>
         <?php
         $x++;
      }
      ?>
      </table>
      <?=Nifty_printF()?>
   </td>
</tr>
</table>
</form>
<?php