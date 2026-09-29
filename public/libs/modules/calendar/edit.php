<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       22.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$appoints = getAppointments($CON, date('m', $_REQUEST["unixraw"]), date('Y', $_REQUEST["unixraw"]), $_SESSION["user_id"]);
$appoints = $appoints[date('d.m.Y', $_REQUEST["unixraw"])];

if($_REQUEST["saveok"] == "1")
   $savemsg = getSaveMessage(true);

?>
<table border="0" cellpadding="0" cellspacing="0" width="822">
<tr>
   <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["CAL"][24]?> <?=date('d.m.Y', $_REQUEST["unixraw"])?></b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "822")?>
<table class="content_table_calendar" border="0" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="60">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" align="center"><?=$_LANG["MODULE"]["CAL"][25]?></td>
   <td class="content_tbl_header" colspan="<?=count($appoints) +1?>"><?=$_LANG["MODULE"]["CAL"][26]?></td>
</tr>
<?php
//----------------------------------------------------------------------------------
for($x = 0; $x <= 23; $x++)
{
   if($x < 10)
      $disp_hour = "0{$x}";
   else
      $disp_hour = $x;

   ?>
   <tr id="idx_tr_cal_<?=$x?>" bgcolor="<?=getRowColor(0)?>">
      <td class="content_row_os" id="idx_td_cal_<?=$x?>" align="center"><?=$disp_hour?>&nbsp;&ordm;&ordm;</td>
      <?php
      $colcount = 0;
      foreach($appoints AS $appoint)
      {
         $thistime = mktime($x, 0, 0, date('m', $_REQUEST["unixraw"]), date('d', $_REQUEST["unixraw"]), date('Y', $_REQUEST["unixraw"]));
         
         //----------------------------------------------------------------------------------
         if(date('d.m.Y H', $thistime) == date('d.m.Y H', $appoint["cal_startdate"]) ||
            ($x == 0 && date('d.m.Y', $_REQUEST["unixraw"]) != date('d.m.Y', $appoint["cal_startdate"]) && (int)$appoint["cal_type"] == 1))
         {
            //----------------------------------------------------------------------------------
            $nexthours = getAppointmentHours($thistime, $appoint["cal_enddate"]);
            
            //----------------------------------------------------------------------------------
            if($nexthours > (24 -$x))
               $nexthours = (24 -$x);

            //----------------------------------------------------------------------------------
            if(date('d.m.Y', $appoint["cal_startdate"]) != date('d.m.Y', $appoint["cal_enddate"]))
            {
               if(date('d.m.Y', $appoint["cal_startdate"]) == date('d.m.Y', $_REQUEST["unixraw"]))
                  $disp_date = date('H:i', $appoint["cal_startdate"])." - ".date('(d.m.Y) H:i', $appoint["cal_enddate"]);
               elseif(date('d.m.Y', $appoint["cal_enddate"]) == date('d.m.Y', $_REQUEST["unixraw"]))
                  $disp_date = date('(d.m.Y) H:i', $appoint["cal_startdate"])." - ".date('H:i', $appoint["cal_enddate"]);
               else
                  $disp_date = date('(d.m.Y) H:i', $appoint["cal_startdate"])." - ".date('(d.m.Y) H:i', $appoint["cal_enddate"]);
            }
            else
               $disp_date = date('H:i', $appoint["cal_startdate"])." - ".date('H:i', $appoint["cal_enddate"]);

            //----------------------------------------------------------------------------------
            if($appoint["cal_startdate"] < time())
            {
               if($appoint["cal_enddate"] > time())
                  $datecolor = "darkorange";
               else
                  $datecolor = "red";
            }
            else
               $datecolor = "green";

            //----------------------------------------------------------------------------------
            ?>
            <td class="content_row_select_active" style="cursor:pointer;border-left:solid 4px <?=$datecolor?>"
            onmouseover="mark(this, 0)" onmouseout="mark(this,1)"
            <?php
            if($appoint["cal_crtusr"] != $_SESSION["user_id"])
            { ?>
              onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=show&cal_id=<?=$appoint["id"]?>&unixraw=<?=$_REQUEST["unixraw"]?>&returnTo=edit'"
              <?php
            }
            else
            {
              ?>
              onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=add&cal_id=<?=$appoint["id"]?>&unixraw=<?=$_REQUEST["unixraw"]?>&returnTo=edit'"
              <?php
            }
            ?>
            rowspan="<?=$nexthours?>" valign="top">
               <table border="0" cellpadding="0" cellspacing="0" width="100%">
               <tr>
                  <td class="content_row_clear">
                     <b><font color="<?=$datecolor?>"><?=$disp_date?></font></b>
                  </td>
                  <td class="content_row_clear" align="right">
                     <i><b><?=$_LANG["MODULE"]["CAL"][27]?></b> <?=$appoint["user_firstname"]?> <?=$appoint["user_lastname"]?></i>
                  </td>
               </tr>
               <tr>
                  <td class="content_row_clear"><?=$appoint["cal_header"]?></td>
               </tr>
               </table>
            </td>
            <?php

            //----------------------------------------------------------------------------------
            if($nexthours > 1)
               for($y = 1; $y < $nexthours; $y++)
                  $minuscols[($x +$y)] = (int)$minuscols[($x +$y)] +1;

            $allminuscols[$x] = 1;
            
            $colcount++;
         }
      }

      //----------------------------------------------------------------------------------
      $gescols = count($appoints) +1;
      $coldiff = $gescols - $colcount;
      
      if($gescols - $colcount > 0)
      {
         for($z = 0; $z < ($coldiff - (int)$minuscols[$x]); $z++)
         {  ?>
            <td class="content_row_os" style="border-right: 0px;cursor:pointer"
            onmouseover="mark(document.all.idx_tr_cal_<?=$x?>, 0); mark(document.all.idx_td_cal_<?=$x?>,0)"
            onmouseout="mark(document.all.idx_tr_cal_<?=$x?>,1); ; mark(document.all.idx_td_cal_<?=$x?>,1)"
            onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=add&unixraw=<?=$_REQUEST["unixraw"]?>&hour=<?=$x?>&returnTo=edit'">&nbsp;</td>
            <?php
         }
      }
      ?>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<script language="JavaScript">
<?php

//----------------------------------------------------------------------------------
$break = false;
for($x = 0; $x < 6 && !$break; $x++)
{
   if(!(int)$minuscols[$x] && !(int)$allminuscols[$x])
   {
      ?>
      document.all.idx_tr_cal_<?=$x?>.style.display = 'none';
      <?php
      $jsstr .= "showObject(document.all.idx_tr_cal_{$x});";
   }
   else
      $break = true;
}

//----------------------------------------------------------------------------------
$break = false;
for($x = 23; $x > 20 && !$break; $x--)
{
   if(!(int)$minuscols[$x] && !(int)$allminuscols[$x])
   {
      ?>
      document.all.idx_tr_cal_<?=$x?>.style.display = 'none';
      <?php
      $jsstr .= "showObject(document.all.idx_tr_cal_{$x});";
   }
   else
      $break = true;
}
?>
</script>
<?=Nifty_printH("boxopt_b", "822")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td align="left" width="130">
      <ul class="postnav">
         <a href="index.php?mid=<?=$_REQUEST["mid"]?>&selmonth=<?=date('m', $_REQUEST["unixraw"])?>&selyear=<?=date('Y', $_REQUEST["unixraw"])?>"><?=$_LANG["FORM"]["BUTTON"][1]?></a>
      </ul>
   </td>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <ul class="postnav">
         <a href="javascript: deactivateFormChange()"
         onclick="<?=$jsstr?>"><?=$_LANG["MODULE"]["CAL"][28]?></a>
      </ul>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>