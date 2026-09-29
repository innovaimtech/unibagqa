<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       26.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   // get current time
   $currtme = time();

   // format parameters
   $_REQUEST["date1"] = explode(".", $_REQUEST["date1"]);
   $_REQUEST["date2"] = explode(".", $_REQUEST["date2"]);
   
   $vac_startday  = mktime(0, 0, 0, $_REQUEST["date1"][1], $_REQUEST["date1"][0], $_REQUEST["date1"][2]);
   $vac_endday    = mktime(0, 0, 0, $_REQUEST["date2"][1], $_REQUEST["date2"][0], $_REQUEST["date2"][2]);

   //----------------------------------------------------------------------------------
   // check for date inputs
   //----------------------------------------------------------------------------------
   if($_REQUEST["date1"][0] == "" || $_REQUEST["date1"][1] == "" || $_REQUEST["date1"][2] == "" ||
      $_REQUEST["date2"][0] == "" || $_REQUEST["date2"][1] == "" || $_REQUEST["date2"][2] == "")
      $savemsg = "<b class='msg_save_err'>{$_LANG["MODULE"]["VAC"][34]}</b>";
   elseif($vac_startday > $vac_endday)
      $savemsg = "<b class='msg_save_err'>{$_LANG["MODULE"]["VAC"][35]}</b>";
   else
   {
      // format parameters
      $_REQUEST["vac_reasonid"]  = (int)$_REQUEST["vac_reasonid"];
      $_REQUEST["vac_days"]      = (int)trim($_REQUEST["vac_days"]);
      $_REQUEST["vac_desc"]      = trim(addslashes($_REQUEST["vac_desc"]));

      // insert vacation into database
      $sql = " insert into vacation
               (vac_startdate, vac_enddate, vac_crtusr, vac_crtdat, vac_status,
                vac_startday, vac_startmonth, vac_startyear, vac_endday, vac_endmonth,
                vac_endyear, vac_reasonid, vac_desc, vac_days)
               VALUES
               ({$vac_startday}, {$vac_endday}, {$_SESSION["user_id"]}, {$currtme}, 0,
                {$_REQUEST["date1"][0]}, {$_REQUEST["date1"][1]}, {$_REQUEST["date1"][2]},
                {$_REQUEST["date2"][0]}, {$_REQUEST["date2"][1]}, {$_REQUEST["date2"][2]},
                {$_REQUEST["vac_reasonid"]}, '{$_REQUEST["vac_desc"]}', {$_REQUEST["vac_days"]})";
      $res = $CON->no_result($sql);

      // No error => redirect to overview
      if($res)
      {  ?>
         <script language="Javascript">
            location.href='index.php?mid=<?=$_REQUEST["mid"]?>&selmonth=<?=date('m', $_REQUEST["unixraw"])?>&selyear=<?=date('Y', $_REQUEST["unixraw"])?>&saveok=1';
         </script>
         <?php
      }
      else
         $savemsg = getSaveMessage($res);
   }
}

//----------------------------------------------------------------------------------
// format parameters for back button
//----------------------------------------------------------------------------------
$selday     = date('d', $_REQUEST["unixraw"]);
$selmonth   = date('m', $_REQUEST["unixraw"]);
$selyear    = date('Y', $_REQUEST["unixraw"]);

//----------------------------------------------------------------------------------
// get reasons
//----------------------------------------------------------------------------------
$sql = " select *
         from vacation_reasons
         where
         reason_status = 1
         order by reason_desc";
$reasons = $CON->select($sql);

//----------------------------------------------------------------------------------
// print formular
//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="650">
<form action="index.php" method="post" name="xform_vacadd"
onsubmit="return checkform(new Array(this.date1, this.date2, this.vac_reasonid, this.vac_days))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="add">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="unixraw" value="<?=$_REQUEST["unixraw"]?>">
<tr>
   <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["VAC"][28]?></b></td>
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
   <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["VAC"][29]?></td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["VAC"][30]?></td>
   <td class="content_row">
      <input type="text" name="date1" style="width:70px" value="<?="{$selday}.{$selmonth}.{$selyear}"?>"
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency" id="date1"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["VAC"][31]?></td>
   <td class="content_row">
      <input type="text" name="date2" style="width:70px" value="<?="{$selday}.{$selmonth}.{$selyear}"?>"
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency" id="date2"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["VAC"][32]?></td>
   <td class="content_row">
      <select class="text" name="vac_reasonid" style="width:200px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         for($x = 0; $x < count($reasons) && $reasons != false; $x++)
         {  ?>
            <option value="<?=$reasons[$x]["id"]?>"><?=$reasons[$x]["reason_desc"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["VAC"][38]?></td>
   <td class="content_row">
      <input type="text" name="vac_days" style="width:80px" value="0" class="text"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
      &nbsp;
      <input type="button" class="button" style="width:110px" value="<?=$_LANG["FORM"]["BUTTON"][9]?>"
      onclick="document.all.vac_days.value = calculateDays(document.all.date1.value, document.all.date2.value)"
      onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row" valign="top"><?=$_LANG["MODULE"]["VAC"][33]?></td>
   <td class="content_row">
      <textarea class="text" style="width:450px; height:100px" name="vac_desc"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"></textarea>
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
         <a href="index.php?mid=<?=$_REQUEST["mid"]?>&selmonth=<?=$selmonth?>&selyear=<?=$selyear?>"><?=$_LANG["FORM"]["BUTTON"][1]?></a>
      </ul>
   </td>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <ul class="postnav_save">
         <a href="javascript: submitForm(document.xform_vacadd)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
      </ul>
   </td>
</tr>
</form>
</table>
<?=Nifty_printF(false)?>
<?php $_SESSION["JSEXEC"] .= "document.all.date2.focus();";