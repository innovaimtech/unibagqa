<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       26.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
// format parameters
//----------------------------------------------------------------------------------
if($_REQUEST["unixraw"] != "")
{
   $_REQUEST["selmonth"]   = date('m', $_REQUEST["unixraw"]);
   $_REQUEST["selyear"]    = date('Y', $_REQUEST["unixraw"]);
}

//----------------------------------------------------------------------------------
// update vacation status
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   // get current time
   $currtme = time();

   // update database
   $sql = " update vacation
            set
            vac_status = {$_REQUEST["vac_status"]},
            vac_updusr = {$_SESSION["user_id"]},
            vac_upddat = {$currtme}
            where
            id = {$_REQUEST["vacid"]}";
   $res = $CON->no_result($sql);

   // set Savemessage
   $savemsg = getSaveMessage($res);

   // redirect to overview
   if($res)
   {  ?>
      <script language="Javascript">
         location.href='index.php?mid=<?=$_REQUEST["mid"]?>&selmonth=<?=$_REQUEST["selmonth"]?>&selyear=<?=$_REQUEST["selyear"]?>&saveok=1';
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
// select data for vacation (owner and leader)
//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.reason_desc,
                t3.user_firstname, t3.user_lastname,
                t4.user_firstname 'updusr_firstname',
                t4.user_lastname 'updusr_lastname'
         from vacation t1
         INNER JOIN vacation_reasons t2 ON t1.vac_reasonid = t2.id
         INNER JOIN user t3 ON t1.vac_crtusr = t3.id
         LEFT OUTER JOIN user t4 ON t1.vac_updusr = t4.id
         where
         t1.id = {$_REQUEST["vacid"]} and
         (
            t1.vac_crtusr = {$_SESSION["user_id"]} ";

if($approve_userstr != "")
   $sql .= " or t1.vac_crtusr IN ({$approve_userstr}) ";

$sql .= " )";
$vadata = $CON->select($sql);
$vadata = $vadata[0];

//----------------------------------------------------------------------------------
// print formular
//----------------------------------------------------------------------------------
?>
<table border="0" cellpadding="0" cellspacing="0" width="650">
<?php
if($approve_users_sel[$vadata["vac_crtusr"]] == 1)
{  ?>
   <form action="index.php" method="post" onsubmit="return checkform(new Array(this.vac_status))" name="xform_vacaddx">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
   <input type="hidden" name="subexec" value="save">
   <input type="hidden" name="vacid" value="<?=$_REQUEST["vacid"]?>">
   <input type="hidden" name="selmonth" value="<?=$_REQUEST["selmonth"]?>">
   <input type="hidden" name="selyear" value="<?=$_REQUEST["selyear"]?>">
   <?php
}
?>
<tr>
   <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["VAC"][20]?></b></td>
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
   <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["VAC"][21]?></td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["VAC"][22]?></td>
   <td class="content_row"><?=$vadata["user_firstname"]?> <?=$vadata["user_lastname"]?></td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["VAC"][23]?></td>
   <td class="content_row"><b><?=$vadata["reason_desc"]?></b></td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["VAC"][24]?></td>
   <td class="content_row"><?=date('d.m.Y', $vadata["vac_startdate"])?> - <?=date('d.m.Y', $vadata["vac_enddate"])?></td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["VAC"][39]?></td>
   <td class="content_row"><?=(int)$vadata["vac_days"]?></td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["VAC"][25]?></td>
   <td class="content_row">
      <?php
      //----------------------------------------------------------------------------------
      // add options for leader
      //----------------------------------------------------------------------------------
      if($approve_users_sel[$vadata["vac_crtusr"]] == 1)
      {  ?>
         <select name="vac_status" class="text" style="width:200px">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <option style="color:red" value="0"          <?php if($vadata["vac_status"] == "0") echo "selected"?>><?=getVacationStatus(0, true)?></option>
            <option style="color:darkorange" value="1"   <?php if($vadata["vac_status"] == "1") echo "selected"?>><?=getVacationStatus(1, true)?></option>
            <option style="color:green" value="2"        <?php if($vadata["vac_status"] == "2") echo "selected"?>><?=getVacationStatus(2, true)?></option>
         </select>
         <?php
      }
      else
      {  ?>
         <b><?=getVacationStatus($vadata["vac_status"])?></b>
         <?php
      }
      ?>
   </td>
</tr>
<?php
if($vadata["vac_desc"] != "")
{  ?>
   <tr>
      <td class="content_row" valign="top"><?=$_LANG["MODULE"]["VAC"][26]?></td>
      <td class="content_row"><?=nl2br(stripslashes($vadata["vac_desc"]))?>&nbsp;</td>
   </tr>
   <?php
}
?>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["VAC"][27]?></td>
   <td class="content_row"><?=date('d.m.Y H:i', $vadata["vac_crtdat"])?></td>
</tr>
<?php
if((int)$vadata["vac_upddat"] > 0)
{  ?>
   <tr>
      <td class="content_row"><?=$_LANG["MODULE"]["VAC"][36]?></td>
      <td class="content_row"><?=date('d.m.Y H:i', $vadata["vac_upddat"])?></td>
   </tr>
   <tr>
      <td class="content_row"><?=$_LANG["MODULE"]["VAC"][37]?></td>
      <td class="content_row"><?=$vadata["updusr_firstname"]?> <?=$vadata["updusr_lastname"]?></td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td align="left" width="130">
      <ul class="postnav">
         <a href="index.php?mid=<?=$_REQUEST["mid"]?>&selmonth=<?=$_REQUEST["selmonth"]?>&selyear=<?=$_REQUEST["selyear"]?>"><?=$_LANG["FORM"]["BUTTON"][1]?></a>
      </ul>
   </td>
   <td>&nbsp;</td>
   <?php
   if($approve_users_sel[$vadata["vac_crtusr"]] == 1)
   {  ?>
      <td width="130" align="right" style="padding-right:5px">
         <ul class="postnav_del">
            <a href="javascript: deactivateFormChange()"
            onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&selmonth=<?=$_REQUEST["selmonth"]?>&selyear=<?=$_REQUEST["selyear"]?>&vacid=<?=$_REQUEST["vacid"]?>&exec=delete')"><?=$_LANG["FORM"]["BUTTON"][2]?></a>
         </ul>
      </td>
      <td width="130" align="right">
         <ul class="postnav_save">
            <a href="javascript: submitForm(document.xform_vacaddx)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
         </ul>
      </td>
      <?php
   }
   ?>
</tr>
<?php
if($approve_users_sel[$vadata["vac_crtusr"]] == 1)
{  ?>
   </form>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>