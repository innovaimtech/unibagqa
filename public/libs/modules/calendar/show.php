<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       22.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
for($x = 0; $x < count($_SESSION["user_groups"]); $x++)
      $grpstr .= "{$_SESSION["user_groups"][$x]},";
   $grpstr = substr($grpstr, 0, -1);

//----------------------------------------------------------------------------------
$sql = " select count(*) 'cc'
         from calendar_shared_grp
         where
         cal_id = {$_REQUEST["cal_id"]} and
         group_id IN ({$grpstr})";
$grpchk = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select count(*) 'cc'
         from calendar_shared_usr
         where
         cal_id = {$_REQUEST["cal_id"]} and
         user_id = {$_SESSION["user_id"]}";
$usrchk = $CON->select($sql);

$sql = " select cal_public
         from calendar_appointments
         where
         id = {$_REQUEST["cal_id"]}";
$publicchk = $CON->select($sql);

// die without privs
if(!(int)$grpchk[0]["cc"] && !(int)$usrchk[0]["cc"] && !(int)$publicchk[0]["cal_public"])
   die("");

//----------------------------------------------------------------------------------
$sql = " select t2.user_firstname, t2.user_lastname
         from calendar_shared_usr t1, user t2
         where
         t1.cal_id = {$_REQUEST["cal_id"]} and
         t1.user_id = t2.id
         order by t2.user_firstname, t2.user_lastname";
$selusers = $CON->select($sql);

// create userstring
for($x = 0; $x < count($selusers) && $selusers != false; $x++)
   $userstr .= "{$selusers[$x]["user_firstname"]} {$selusers[$x]["user_lastname"]}, ";
$userstr = substr($userstr, 0, -2);

//----------------------------------------------------------------------------------
$sql = " select t2.group_name
         from calendar_shared_grp t1, `group` t2
         where
         t1.cal_id = {$_REQUEST["cal_id"]} and
         t1.group_id = t2.id
         order by t2.group_name";
$selgroups = $CON->select($sql);

// create groupstring
for($x = 0; $x < count($selgroups) && $selgroups != false; $x++)
   $groupstr .= "{$selgroups[$x]["group_name"]}, ";
$groupstr = substr($groupstr, 0, -2);

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.user_firstname, t2.user_lastname
         from calendar_appointments t1, user t2
         where
         t1.id = {$_REQUEST["cal_id"]} and
         t1.cal_crtusr = t2.id";
$caldata = $CON->select($sql);
$caldata = $caldata[0];

//----------------------------------------------------------------------------------
?>
<table border="0" cellpadding="0" cellspacing="0" width="822">
<tr>
   <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["CAL"][47]?></b></td>
   <td align="right"></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "822")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="150">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["CAL"][48]?></td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CAL"][49]?></td>
   <td class="content_row"><?=date('d.m.Y H:i', $caldata["cal_startdate"])?></td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CAL"][50]?></td>
   <td class="content_row"><?=date('d.m.Y H:i', $caldata["cal_enddate"])?></td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CAL"][65]?></td>
   <td class="content_row">
      <?php
      switch((int)$caldata["cal_type"])
      {
         case 1: echo $_LANG["MODULE"]["CAL"][66]; break;
         case 2: echo $_LANG["MODULE"]["CAL"][67]; break;
         case 3: echo $_LANG["MODULE"]["CAL"][68]; break;
      }

      if((int)$caldata["cal_type"] == 2)
      {
         $dayofweek  = date('w', $caldata["cal_startdate"]);

         if($dayofweek == 0)
            $dayofweek = 7;

         echo " / {$weekdays[$dayofweek]}";
      }
      ?>
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CAL"][51]?></td>
   <td class="content_row"><?=$caldata["user_firstname"]?> <?=$caldata["user_lastname"]?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?php

//----------------------------------------------------------------------------------
?>
<?=Nifty_printH("box2", "822")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="150">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["CAL"][52]?></td>
</tr>
<?php
if($userstr != "")
{  ?>
   <tr>
      <td class="content_row"><?=$_LANG["MODULE"]["CAL"][53]?></td>
      <td class="content_row"><?=$userstr?></td>
   </tr>
   <?php
}
if($groupstr != "")
{  ?>
   <tr>
      <td class="content_row"><?=$_LANG["MODULE"]["CAL"][54]?></td>
      <td class="content_row"><?=$groupstr?></td>
   </tr>
   <?php
}
?>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CAL"][55]?></td>
   <td class="content_row"><b><?=stripslashes($caldata["cal_header"])?></b></td>
</tr>
<?php
if(trim($caldata["cal_body"]) != "")
{  ?>
   <tr>
      <td class="content_row" valign="top"><?=$_LANG["MODULE"]["CAL"][56]?></td>
      <td class="content_row"><?=nl2br(stripslashes($caldata["cal_body"]))?></td>
   </tr>
   <?php
}
if((int)$caldata["cal_docid"])
{
   $sql = " select t2.*
            from menu_items t1, menu_docs t2
            where
            t1.menu_docid = t2.id and
            t1.id = {$caldata["cal_docid"]}";
   $docdata = $CON->select($sql);
   if(count($docdata) && $docdata != false)
   {
      $docdata = $docdata[0];
      ?>
      <tr>
         <td class="content_row"><?=$_LANG["MODULE"]["CAL"][57]?></td>
         <td class="content_row">
            <input type="button" class="button" value="<?=$docdata["doc_name"]?>"
            onclick="document.all.docfrm.src='./libs/modules/structure/document_file.php?type=0&id=<?=$docdata["id"]?>&hash=<?=$docdata["doc_hash"]?>&mime=<?=$docdata["doc_mimetype"]?>&name=<?=$docdata["doc_name"]?>'"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)">
            <iframe id="docfrm" src="" width="1" height="1" frameborder="0" marginheight="0" marginwidth="0"></iframe>
         </td>
      </tr>
      <?php
   }
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "822")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td align="left" width="130">
      <ul class="postnav">
         <a href="index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&unixraw=<?=$_REQUEST["unixraw"]?>"><?=$_LANG["FORM"]["BUTTON"][1]?></a>
      </ul>
   </td>
   <td align="left">&nbsp;</td>
   <td align="right" width="130">
      <ul class="postnav_save">
         <a href="index.php?mid=6&cal_id=<?=$_REQUEST["cal_id"]?>"><?=$_LANG["FORM"]["BUTTON"][7]?></a>
      </ul>
   </td>
</tr>
</form>
</table>
<?=Nifty_printF(false)?>