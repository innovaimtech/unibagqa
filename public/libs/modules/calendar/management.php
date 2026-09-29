<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       22.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
// set the user of the calendar
//----------------------------------------------------------------------------------
if($_REQUEST["calusrid"] == "")
   $_REQUEST["calusrid"] = $_SESSION["user_id"];

//----------------------------------------------------------------------------------
// define month names
//----------------------------------------------------------------------------------
$months[1]     = $_LANG["MODULE"]["CAL"][0];
$months[2]     = $_LANG["MODULE"]["CAL"][1];
$months[3]     = $_LANG["MODULE"]["CAL"][2];
$months[4]     = $_LANG["MODULE"]["CAL"][3];
$months[5]     = $_LANG["MODULE"]["CAL"][4];
$months[6]     = $_LANG["MODULE"]["CAL"][5];
$months[7]     = $_LANG["MODULE"]["CAL"][6];
$months[8]     = $_LANG["MODULE"]["CAL"][7];
$months[9]     = $_LANG["MODULE"]["CAL"][8];
$months[10]    = $_LANG["MODULE"]["CAL"][9];
$months[11]    = $_LANG["MODULE"]["CAL"][10];
$months[12]    = $_LANG["MODULE"]["CAL"][11];

//----------------------------------------------------------------------------------
// define weekdays
//----------------------------------------------------------------------------------
$weekdays[1]   = $_LANG["MODULE"]["CAL"][12];
$weekdays[2]   = $_LANG["MODULE"]["CAL"][13];
$weekdays[3]   = $_LANG["MODULE"]["CAL"][14];
$weekdays[4]   = $_LANG["MODULE"]["CAL"][15];
$weekdays[5]   = $_LANG["MODULE"]["CAL"][16];
$weekdays[6]   = $_LANG["MODULE"]["CAL"][17];
$weekdays[7]   = $_LANG["MODULE"]["CAL"][18];


//----------------------------------------------------------------------------------
// set default selection for date
//----------------------------------------------------------------------------------
if($_REQUEST["selmonth"] == "")
   $_REQUEST["selmonth"] = date('m');
if($_REQUEST["selyear"] == "")
   $_REQUEST["selyear"] = date('Y');

// get current year
$curryear   = date('Y');

//----------------------------------------------------------------------------------
// load library to add a new appointment
//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "add")
{
   require_once("add.php");
}

//----------------------------------------------------------------------------------
// load library to edit appointment for a day
//----------------------------------------------------------------------------------
elseif($_REQUEST["exec"] == "edit")
{
   require_once("edit.php");
}
//----------------------------------------------------------------------------------
// load library to show a external appointment
//----------------------------------------------------------------------------------
elseif($_REQUEST["exec"] == "show")
{
   require_once("show.php");
}

//----------------------------------------------------------------------------------
// show month
//----------------------------------------------------------------------------------
else
{
   //----------------------------------------------------------------------------------
   // delete an appointment
   //----------------------------------------------------------------------------------
   if($_REQUEST["exec"] == "delete")
   {
      // select old appointment data
      $sql = " select t1.*, t2.user_firstname, t2.user_lastname
               from calendar_appointments t1, user t2
               where
               t1.id = {$_REQUEST["cal_id"]} and
               t1.cal_crtusr = t2.id";
      $caldata = $CON->select($sql);
      $caldata = $caldata[0];

      // select old user privs
      $sql = " select t1.user_id, t2.user_firstname, t2.user_lastname
               from calendar_shared_usr t1, user t2
               where
               t1.cal_id = {$_REQUEST["cal_id"]} and
               t1.user_id = t2.id";
      $orguser = $CON->select($sql);

      $userstr = "";
      for($x = 0; $x < count($orguser) && $orguser != false; $x++)
      {
         $orgusers[$orguser[$x]["user_id"]] = 1;
         $userstr .= "{$orguser[$x]["user_firstname"]} {$orguser[$x]["user_lastname"]}, ";
      }
      $userstr = substr($userstr, 0, -2);

      // select old group privs
      $sql = " select t1.group_id, t2.group_name
               from calendar_shared_grp t1, `group` t2
               where
               t1.cal_id = {$_REQUEST["cal_id"]} and
               t1.group_id = t2.id";
      $orggrps = $CON->select($sql);

      $groupstr = "";
      for($x = 0; $x < count($orggrps) && $orggrps != false; $x++)
      {
         // save group users to userid-array
         $sql = " select t1.user_id, t2.user_firstname, t2.user_lastname
                  from user_group t1, user t2
                  where
                  t1.group_id = {$orggrps[$x]["group_id"]} and
                  t1.user_id = t2.id and
                  t2.user_status = 1";
         $grpusr = $CON->select($sql);
         for($y = 0; $y < count($grpusr) && $grpusr != false; $y++)
            $orgusers[$grpusr[$y]["user_id"]] = 1;

         $groupstr .= "{$orggrps[$x]["group_name"]}, ";
      }
      $groupstr = substr($groupstr, 0, -2);

      $sql = " delete from calendar_appointments
               where
               id = {$_REQUEST["cal_id"]}";
      $CON->no_result($sql);
   
      $sql = " delete from calendar_shared_grp
               where
               cal_id = {$_REQUEST["cal_id"]}";
      $CON->no_result($sql);
   
      $sql = " delete from calendar_shared_usr
               where
               cal_id = {$_REQUEST["cal_id"]}";
      $CON->no_result($sql);

      $sql = " select id
               from calendar_appointments
               where
               cal_parent = {$_REQUEST["cal_id"]}";
      $childs = $CON->select($sql);

      for($z = 0; $z < count($childs) && $childs != false; $z++)
      {
         $sql = " delete from calendar_appointments
                  where
                  id = {$childs[$z]["id"]}";
         $CON->no_result($sql);
         
         $sql = " delete from calendar_shared_grp
                  where
                  cal_id = {$childs[$z]["id"]}";
         $CON->no_result($sql);
         
         $sql = " delete from calendar_shared_usr
                  where
                  cal_id = {$childs[$z]["id"]}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      // inform users about appointment deletion
      //----------------------------------------------------------------------------------
      if(is_array($orgusers))
      {
         foreach(array_keys($orgusers) AS $usrid)
         {
            $sql = " select user_firstname, user_lastname, user_mailforward, user_mail
                     from user
                     where
                     id = {$usrid}";
            $mailforw = $CON->select($sql);
            if((int)$mailforw[0]["user_mailforward"])
            {
               $title   = $_LANG["MODULE"]["CAL"][58].stripslashes($caldata["cal_header"]);
               $text    = '<html>
                           <head><style type="text/css">body{font-family:Arial;font-size:12px;}</style></head>
                           <body class="page">
                           <table border="0" cellpadding="0" cellspacing="0" width="100%">
                           <tr>
                              <td width="15">&nbsp;</td>
                              <td>&nbsp;</td>
                           </tr>
                           <tr>
                              <td width="15">&nbsp;</td>
                              <td class="content_row_clear">
                                 '.$_LANG["MODULE"]["CAL"][59].' '.$mailforw[0]["user_firstname"].' '.$mailforw[0]["user_lastname"].',
                                 <br><br>
                                 <b class="msg_save_err">'.sprintf($_LANG["MODULE"]["CAL"][60], $_SESSION["user_firstname"].' '.$_SESSION["user_lastname"]).'</b>
                                 <br><br>
                                 '.getAppointmentText($caldata, $groupstr, $userstr, false).'
                              </td>
                           </tr>
                           </table>
                           </body>
                           </html>';
   
               $sentmails = sendExternalMail($title, $text, $mailforw[0]["user_mail"], "{$mailforw[0]["user_firstname"]} {$mailforw[0]["user_lastname"]}");
               if($sentmails <= 0)
                  $mailerr++;
            }
         }
      }
   
      if($mailerr > 0)
         $savemsg = "<b class='msg_save_err'>{$_LANG["MODULE"]["MSG"][47]}</b>";
      else
         $savemsg = getSaveMessage(true);
   }

   //----------------------------------------------------------------------------------
   // get days and appointment for the selected month
   //----------------------------------------------------------------------------------
   $dates      = getDaysofMonth($months, $weekdays, $_REQUEST["selmonth"], $_REQUEST["selyear"]);
   $appoints   = getAppointments($CON, $_REQUEST["selmonth"], $_REQUEST["selyear"], $_REQUEST["calusrid"]);
   $vacdays    = getVacations($CON, $_REQUEST["selmonth"], $_REQUEST["selyear"], $_REQUEST["calusrid"]);

   //----------------------------------------------------------------------------------
   // get back & forward data for years
   //----------------------------------------------------------------------------------
   $year_back = $_REQUEST["selyear"] -1;
   $year_forw = $_REQUEST["selyear"] +1;
   
   //----------------------------------------------------------------------------------
   // get back data for months
   //----------------------------------------------------------------------------------
   $month_back1 = (int)$_REQUEST["selmonth"] -1;
   if($month_back1 == 0)
   {
      $month_back1 = 12;
      $month_back2 = $year_back;
   }
   else
      $month_back2 = $_REQUEST["selyear"];
   
   //----------------------------------------------------------------------------------
   // get forward data for months
   //----------------------------------------------------------------------------------
   $month_forw1 = (int)$_REQUEST["selmonth"] +1;
   if($month_forw1 == 13)
   {
      $month_forw1 = 1;
      $month_forw2 = $year_forw;
   }
   else
      $month_forw2 = $_REQUEST["selyear"];

   //----------------------------------------------------------------------------------
   // print option panel for calendar
   //----------------------------------------------------------------------------------
   $sql = " select id, group_name, group_desc
            from `group`
            where
            group_status = 1 ";
            
   if($_SESSION["user_type"] != 1)
      $sql .= " and group_visible = 1 ";
      
   $sql .= " order by group_name asc";
   $mygroups = $CON->select($sql);
   ?>
   <script language="Javascript" src="./libs/jscripts/overlib/overlib.js"></script>
   <div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["CAL"][19]?> <?=$months[(int)$_REQUEST["selmonth"]]?> <?=$_REQUEST["selyear"]?></b></td>
      <td align="right"><?=$savemsg?></td>
      <td align="right" valign="middle">
         <nobr>
         <b><?=$_LANG["MODULE"]["CAL"][64]?></b>
         &nbsp;
         <select class="text" style="width:200px"
         onchange="if(this.value != '') location.href='index.php?mid=<?=$_REQUEST["mid"]?>&selyear=<?=$_REQUEST["selyear"]?>&selmonth=<?=$_REQUEST["selmonth"]?>&calusrid=' +this.value">
            <option value="<?=$_SESSION["user_id"]?>"><?=$_SESSION["user_firstname"]?> <?=$_SESSION["user_lastname"]?></option>
            <?php
            // select groups and users
            for($x = 0; $x < count($mygroups) && $mygroups != false; $x++)
            {
               $sql = " select t1.user_id, t2.user_firstname, t2.user_lastname
                        from user_group t1, user t2
                        where
                        t1.group_id = {$mygroups[$x]["id"]} and
                        t1.user_id = t2.id and
                        t2.user_status = 1 and
                        t1.user_id != {$_SESSION["user_id"]}
                        order by t2.user_firstname, t2.user_lastname";
               $mygroupusers = $CON->select($sql);
               
               if(count($mygroupusers) && $mygroupusers != false)
               {  ?>
                  <option style="background-Color:<?=getRowColor(1)?>;font-weight:bold" value="">- <?=$mygroups[$x]["group_name"]?></option>
                  <?php
               }
               
               for($y = 0; $y < count($mygroupusers) && $mygroupusers != false; $y++)
               {  ?>
                  <option value="<?=$mygroupusers[$y]["user_id"]?>"
                  <?php if($mygroupusers[$y]["user_id"] == $_REQUEST["calusrid"]) echo "selected"?>><?=$mygroupusers[$y]["user_firstname"]?> <?=$mygroupusers[$y]["user_lastname"]?></option>
                  <?php
               }
            }
            ?>
         </select>
         </nobr>
      </td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="3">&nbsp;</td>
   </tr>
   </table>
   <?=Nifty_printH("boxopt_t", "822")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <tr>
      <td class="content_tbl_header" align="center">
         <input type="button" class="button" value="&lt;&lt;" style="width:40px"
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&calusrid=<?=$_REQUEST["calusrid"]?>&selyear=<?=$year_back?>&selmonth=' +document.all.selmonth.value"
         onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
         <?php if($year_back < $curryear -2) echo "disabled" ?>>
   
         <input type="button" class="button" value="&lt;" style="width:30px"
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&calusrid=<?=$_REQUEST["calusrid"]?>&selyear=<?=$month_back2?>&selmonth=<?=$month_back1?>'"
         onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
         <?php if($month_back2 < $curryear -2) echo "disabled" ?>>
         
         <select class="text" id="selmonth" style="width:120px"
         onchange="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&calusrid=<?=$_REQUEST["calusrid"]?>&selyear=' +document.all.selyear.value +'&selmonth=' +this.value">
            <?php
            foreach(array_keys($months) AS $monthidx)
            {
               $optionval = $monthidx;
               if($optionval < 10)
                  $optionval = "0{$optionval}";
               ?>
               <option value="<?=$optionval?>" <?php if($optionval == $_REQUEST["selmonth"]) echo "selected"?>><?=$months[$monthidx]?></option>
               <?php
            }
            ?>
         </select>
         &nbsp;
         <select class="text" id="selyear" style="width:120px"
         onchange="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&calusrid=<?=$_REQUEST["calusrid"]?>&selyear=' +this.value +'&selmonth=' +document.all.selmonth.value">
         <?php
         for($x = ($curryear -2); $x <= ($curryear +2); $x++)
         {  ?>
            <option value="<?=$x?>" <?php if($x == $_REQUEST["selyear"]) echo "selected"?>><?=$x?></option>
            <?php
         }
         ?>
         </select>

         <input type="button" class="button" value="&gt;" style="width:30px"
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&calusrid=<?=$_REQUEST["calusrid"]?>&selyear=<?=$month_forw2?>&selmonth=<?=$month_forw1?>'"
         onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
         <?php if($month_forw2 > $curryear +2) echo "disabled" ?>>
         
         <input type="button" class="button" value="&gt;&gt;" style="width:40px"
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&calusrid=<?=$_REQUEST["calusrid"]?>&selyear=<?=$year_forw?>&selmonth=' +document.all.selmonth.value"
         onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
         <?php if($year_forw > $curryear +2) echo "disabled" ?>>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?php
   
   //----------------------------------------------------------------------------------
   // print calendar header
   //----------------------------------------------------------------------------------
   ?>
   
   <?=Nifty_printH("box1", "822")?>
   <table border="0" class="content_table_calendar" cellpadding="3" cellspacing="0" width="100%">
   <tr>
      <?php
      foreach($weekdays AS $weekday)
      {  ?>
         <td class="content_tbl_header" width="14%" align="center"><?=$weekday?></td>
         <?php
      }
      ?>
   </tr>
   <?php
   
   //----------------------------------------------------------------------------------
   // print calendar days
   //----------------------------------------------------------------------------------
   $rowcount = count($dates) -1;
   for($x = 0; $x <= $rowcount; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>">
         <?php
         foreach(array_keys($weekdays) AS $weekidx)
         {
            // get the formated day
            $fulldate = date('d.m.Y', $dates[$x][$weekidx]["RAWDATE"]);
            
            // get styleclass
            if($dates[$x][$weekidx]["DAY"] == "")
               $styleclass = "content_row_os";
            else
            {
               if($fulldate == date('d.m.Y'))
                  $styleclass = "content_row_select_active";
               else
                  $styleclass = "content_row_os";
            }

            //----------------------------------------------------------------------------------
            // print item
            //----------------------------------------------------------------------------------
            ?>
            <td class="<?=$styleclass?>" height="85" valign="top"
               <?php
               // check if day is within the month
               if($dates[$x][$weekidx]["DAY"] != "")
               {
                  if($vacdays[$fulldate]["ID"] != "" && $vacdays[$fulldate]["STAT"] == "2")
                  {  ?>
                     background="./images/content/cal_vacation.gif"
                     <?php
                  }
                  
                  //----------------------------------------------------------------------------------
                  // set fontstyle for today
                  //----------------------------------------------------------------------------------
                  if($fulldate == date('d.m.Y'))
                  {
                     $fontprefix = "<b>";
                     $fontsuffix = "</b>";
                  }
                  else
                  {
                     $fontprefix = "";
                     $fontsuffix = "";
                  }

                  //----------------------------------------------------------------------------------
                  // create overlib-table for appointment
                  //----------------------------------------------------------------------------------
                  if(is_array($appoints[$fulldate]))
                  {
                     // create table header
                     $overlibover  = "<table bgcolor=#FFFFFF class=content_table border=0 cellpadding=2 cellspacing=0 width=400>";
                     $overlibover .= "<colgroup><col width=85><col><col width=120></colgroup>";
                     $overlibover .= "<tr>";
                     $overlibover .= "<td class=content_tbl_header><b>{$_LANG["MODULE"]["CAL"][20]}</b></td>";
                     $overlibover .= "<td class=content_tbl_header><b>{$_LANG["MODULE"]["CAL"][21]}</b></td>";
                     $overlibover .= "<td class=content_tbl_header><b>{$_LANG["MODULE"]["CAL"][22]}</b></td>";
                     $overlibover .= "</tr>";

                     $appcounter = 0;

                     // loop through appointments
                     foreach($appoints[$fulldate] AS $appoint)
                     {
                        // set alignment for overlib
                        if($weekidx <= 3)
                           $overmouse = "RIGHT";
                        elseif($weekidx == 4)
                           $overmouse = "CENTER";
                        else
                           $overmouse = "LEFT";

                        // set start & enddate
                        $overstart    = date('H:i', $appoint["cal_startdate"]);
                        $overend      = date('H:i', $appoint["cal_enddate"]);

                        // reformat start & enddate
                        if(date('d.m.Y', $appoint["cal_startdate"]) != date('d.m.Y', $appoint["cal_enddate"]))
                        {
                           if($fulldate == date('d.m.Y', $appoint["cal_enddate"]))
                              $overstart = "...";
                           elseif($fulldate == date('d.m.Y', $appoint["cal_startdate"]))
                              $overend   = "...";
                           else
                           {
                              $overstart = "...";
                              $overend   = "...";
                           }
                        }

                        $appoint["cal_header"] = str_replace("'","",$appoint["cal_header"]);
                        $appoint["cal_header"] = str_replace('"',"",$appoint["cal_header"]);
                        
                        // print row
                        $overlibover .= "<tr bgcolor=".getRowColor($appcounter).">";
                        $overlibover .= "<td valign=top class=content_row><nobr>{$overstart} - {$overend}&nbsp;</nobr></td>";
                        $overlibover .= "<td valign=top class=content_row>{$appoint["cal_header"]}</td>";
                        $overlibover .= "<td valign=top class=content_row><nobr>{$appoint["user_firstname"]} {$appoint["user_lastname"]}</nobr></td>";
                        $overlibover .= "</tr>";

                        $appcounter++;
                     }

                     // print tablefooter & create linkdata
                     $overlibover  .= "</table>";
                     $overlibover   = "return overlib('{$overlibover}', WIDTH, 400, {$overmouse}, FGCOLOR, '#FFFFFF', BGCOLOR, '#333333')";
                     $overlibout    = "return nd()";
                  }
                  else
                  {
                     $overlibover   = "";
                     $overlibout    = "";
                  }

                  // set style & onmouse-events
                  if($_REQUEST["calusrid"] == $_SESSION["user_id"])
                  {  ?>
                     style="cursor:pointer"
                     <?php
                  }
                  ?>
                  onmouseover="mark(this, 0); <?=$overlibover?>" onmouseout="mark(this,1); <?=$overlibout?>"
                  <?php

                  if($_REQUEST["calusrid"] == $_SESSION["user_id"])
                  {
                     // if an appointment exist, link to edit-mode
                     if(is_array($appoints[$fulldate]))
                     {  ?>
                        onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&unixraw=<?=$dates[$x][$weekidx]["RAWDATE"]?>'"
                        <?php
                     }
   
                     // if no appointment exist, link to add-mode
                     else
                     {  ?>
                        onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=add&unixraw=<?=$dates[$x][$weekidx]["RAWDATE"]?>'"
                        <?php
                     }
                  }
               }
               else
               {
                  $fontprefix = "";
                  $fontsuffix = "";
               }
               ?>>

               <?php
               //----------------------------------------------------------------------------------
               // print item content
               //----------------------------------------------------------------------------------
               ?>
               <table border="0" cellpadding="0" cellspacing="0" width="100%">
               <tr>
                  <td valign="top" class="content_row_clear"><?=$fontprefix.$dates[$x][$weekidx]["DAY"].$fontsuffix?>&nbsp;</td>
                  <?php

                  // print additional infos, for appointments
                  if(is_array($appoints[$fulldate]) || ($vacdays[$fulldate]["ID"] != "" && $vacdays[$fulldate]["STAT"] == "2"))
                  {
                     // set style for appointments that are not in the past
                     if($dates[$x][$weekidx]["RAWDATE"] < mktime(0,0,0,date('m'),date('d'),date('Y')))
                     {
                        $bstyleclass   = 'class="content_inactive"';
                        $bellimgstr    = "cal_inactive.gif";
                     }
                     else
                     {
                        $bstyleclass   = "";
                        $bellimgstr    = "cal_active.gif";
                     }
                     
                     ?>
                        <td align="right">
                           <?php
                           if(is_array($appoints[$fulldate]))
                           {  ?>
                              <img src="./images/content/<?=$bellimgstr?>">&nbsp;
                              <?php
                           }
                           if($vacdays[$fulldate]["ID"] != "" && $vacdays[$fulldate]["STAT"] == "2")
                           {  ?>
                              <img src="./images/content/cal_vacation_icon.gif">
                              <?php
                           }
                           ?>
                        </td>
                     </tr>
                     <tr>
                        <td colspan="2" align="left" height="45" class="content_row_clear" valign="bottom">
                           <?php
                           if(is_array($appoints[$fulldate]))
                           {  ?>
                              <i><b <?=$bstyleclass?>><?=count($appoints[$fulldate])?> <?=$_LANG["MODULE"]["CAL"][23]?></i></b><br>
                              <?php
                           }
                           if($vacdays[$fulldate]["ID"] != "" && $vacdays[$fulldate]["STAT"] == "2")
                           {  ?>
                              <i><b class="msg_save_err"><?=$_LANG["MODULE"]["CAL"][63]?></i></b>
                              <?php
                           }
                           ?>
                     </td>
                     <?php
                  }
                  ?>
               </tr>
               </table>
            </td>
            <?php
         }
         ?>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <?php
}