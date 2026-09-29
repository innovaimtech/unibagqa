<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       30.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   // set the current time
   $currtme = time();

   // format parameters
   $_REQUEST["user_firstname"]   = trim(addslashes($_REQUEST["user_firstname"]));
   $_REQUEST["user_lastname"]    = trim(addslashes($_REQUEST["user_lastname"]));
   $_REQUEST["user_mail"]        = trim(addslashes($_REQUEST["user_mail"]));
   $_REQUEST["user_street"]      = trim(addslashes($_REQUEST["user_street"]));
   $_REQUEST["user_postcode"]    = trim(addslashes($_REQUEST["user_postcode"]));
   $_REQUEST["user_city"]        = trim(addslashes($_REQUEST["user_city"]));
   $_REQUEST["user_telephone"]   = trim(addslashes($_REQUEST["user_telephone"]));
   $_REQUEST["user_cellphone"]   = trim(addslashes($_REQUEST["user_cellphone"]));
   $_REQUEST["user_internet"]    = trim(addslashes($_REQUEST["user_internet"]));
   $_REQUEST["user_public"]      = (int)$_REQUEST["user_public"];

   if($_REQUEST["con_id"] != "")
   {
      // update user contact
      $sql = " update user_contacts
               set
               user_firstname    = '{$_REQUEST["user_firstname"]}',
               user_lastname     = '{$_REQUEST["user_lastname"]}',
               user_mail         = '{$_REQUEST["user_mail"]}',
               user_street       = '{$_REQUEST["user_street"]}',
               user_postcode     = '{$_REQUEST["user_postcode"]}',
               user_city         = '{$_REQUEST["user_city"]}',
               user_telephone    = '{$_REQUEST["user_telephone"]}',
               user_cellphone    = '{$_REQUEST["user_cellphone"]}',
               user_internet     = '{$_REQUEST["user_internet"]}',
               user_public       = {$_REQUEST["user_public"]},
               user_updusr       = {$_SESSION["user_id"]},
               user_upddat       = {$currtme}
               where
               id                = {$_REQUEST["con_id"]} and
               user_crtusr       = {$_SESSION["user_id"]}";
   }
   else
   {
     // insert new user contact
     $sql = " insert into user_contacts
              (user_firstname, user_lastname, user_mail, user_street,
               user_postcode, user_city, user_telephone, user_cellphone,
               user_internet, user_public, user_crtusr, user_crtdat)
              VALUES
              ('{$_REQUEST["user_firstname"]}', '{$_REQUEST["user_lastname"]}',
               '{$_REQUEST["user_mail"]}', '{$_REQUEST["user_street"]}',
               '{$_REQUEST["user_postcode"]}', '{$_REQUEST["user_city"]}',
               '{$_REQUEST["user_telephone"]}', '{$_REQUEST["user_cellphone"]}',
               '{$_REQUEST["user_internet"]}', {$_REQUEST["user_public"]},
                {$_SESSION["user_id"]}, {$currtme})";
   }
   $res = $CON->no_result($sql);

   // set Savemessage
   $savemsg = getSaveMessage($res);
}

// set title for new contact
$title = $_LANG["MODULE"]["CON"][11];

if($_REQUEST["con_id"] != "")
{
   // set title for existing contact
   $title = $_LANG["MODULE"]["CON"][12];

   // select data for existing contact
   $sql = " select *
            from user_contacts
            where
            id          = {$_REQUEST["con_id"]} and
            user_crtusr = {$_SESSION["user_id"]}";
   $userdata = $CON->select($sql);
}

//----------------------------------------------------------------------------------
// Print contact formular
//----------------------------------------------------------------------------------
?>
<table border="0" cellpadding="0" cellspacing="0" width="650">
<form action="index.php" method="post" class="fokusfirst" name="xform_contact"
onsubmit="return checkform(new Array(this.user_firstname, this.user_lastname))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="selchar" value="<?=$_REQUEST["selchar"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="con_id" value="<?=$_REQUEST["con_id"]?>">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="150">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["CON"][13]?></td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CON"][14]?></td>
   <td class="content_row">
      <input name="user_firstname" type="text" class="text" style="width:270px" value="<?=$userdata[0]["user_firstname"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CON"][15]?></td>
   <td class="content_row">
      <input name="user_lastname" type="text" class="text" style="width:270px" value="<?=$userdata[0]["user_lastname"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CON"][16]?></td>
   <td class="content_row">
      <input name="user_mail" type="text" class="text" style="width:270px" value="<?=$userdata[0]["user_mail"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CON"][17]?></td>
   <td class="content_row">
      <input name="user_street" type="text" class="text" style="width:270px" value="<?=$userdata[0]["user_street"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CON"][18]?></td>
   <td class="content_row">
      <input name="user_postcode" type="text" class="text" style="width:70px" value="<?=$userdata[0]["user_postcode"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
      &nbsp;
      <input name="user_city" type="text" class="text" style="width:191px" value="<?=$userdata[0]["user_city"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CON"][19]?></td>
   <td class="content_row">
      <input name="user_telephone" type="text" class="text" style="width:270px" value="<?=$userdata[0]["user_telephone"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CON"][20]?></td>
   <td class="content_row">
      <input name="user_cellphone" type="text" class="text" style="width:270px" value="<?=$userdata[0]["user_cellphone"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CON"][21]?></td>
   <td class="content_row">
      <input name="user_internet" type="text" class="text" style="width:270px" value="<?=$userdata[0]["user_internet"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CON"][22]?></td>
   <td class="content_row">
      <input type="radio" name="user_public" value="1" <?php if($userdata[0]["user_public"] == "" || $userdata[0]["user_public"] == "1") echo "checked"?>> <?=$_LANG["FORM"]["RADIO"][0]?>
      <input type="radio" name="user_public" value="0" <?php if($userdata[0]["user_public"] == "0") echo "checked"?>> <?=$_LANG["FORM"]["RADIO"][1]?>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td align="left" width="130">
      <ul class="postnav">
         <a href="index.php?mid=<?=$_REQUEST["mid"]?>&selchar=<?=$_REQUEST["selchar"]?>"><?=$_LANG["FORM"]["BUTTON"][1]?></a>
      </ul>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["con_id"] != "")
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <ul class="postnav_del">
            <a href="javascript: deactivateFormChange()"
            onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&exec=delete&con_id=<?=$_REQUEST["con_id"]?>')"><?=$_LANG["FORM"]["BUTTON"][2]?></a>
         </ul>
      </td>
      <?php
   }
   else
   {  ?>
      <td>&nbsp;</td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <ul class="postnav_save">
         <a href="javascript: submitForm(document.xform_contact)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
      </ul>
   </td>
</tr>
</form>
</table>
<?=Nifty_printF(false)?>