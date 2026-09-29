<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       26.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

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

// get group string for session user
for($x = 0; $x < count($_SESSION["user_groups"]) && $_SESSION["user_groups"] != false; $x++)
   $sesgrpstr .= "{$_SESSION["user_groups"][$x]}, ";
$sesgrpstr = substr($sesgrpstr, 0, -2);

if($sesgrpstr != "")
{
   // select users the session user can approve
   $sql = " select distinct t2.user_id
            from user_group t1, user_group t2
            where
            t1.user_id = {$_SESSION["user_id"]} and
            t1.group_id IN ($sesgrpstr) and
            t1.is_adm = 1 and
            t1.group_id = t2.group_id";
   $approve_users = $CON->select($sql);

   // save users
   for($x = 0; $x < count($approve_users) && $approve_users != false; $x++)
   {
      $approve_userstr .= "{$approve_users[$x]["user_id"]}, ";
      $approve_users_sel[$approve_users[$x]["user_id"]] = 1;
   }
   $approve_userstr = substr($approve_userstr, 0, -2);
}

// Set Savemessage
if($_REQUEST["saveok"] == "1")
   $savemsg = getSaveMessage(true);

//----------------------------------------------------------------------------------
// delete vacation
//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "delete")
{
   // check if user has privs to delete a vacation
   $sql = " select vac_crtusr
            from vacation
            where
            id = {$_REQUEST["vacid"]}";
   $delvacusr = $CON->select($sql);

   // delete the vacation
   if($approve_users_sel[$delvacusr[0]["vac_crtusr"]] == 1)
   {
      $sql = " delete from vacation
               where
               id = {$_REQUEST["vacid"]}";
      $CON->no_result($sql);

      $savemsg = getSaveMessage(true);
   }
}

//----------------------------------------------------------------------------------
// load library for adding a new vacation
//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "add")
{
   require_once("add.php");
}
//----------------------------------------------------------------------------------
// load library for showing a existing vacation
//----------------------------------------------------------------------------------
elseif($_REQUEST["exec"] == "show")
{
   require_once("show.php");
}
//----------------------------------------------------------------------------------
// show vacation overview
//----------------------------------------------------------------------------------
else
{
   //----------------------------------------------------------------------------------
   // get pending vacation to approve
   //----------------------------------------------------------------------------------
   if($approve_userstr != "")
   {
      $sql = " select t1.*, t2.user_firstname, t2.user_lastname, t3.reason_desc
               from vacation t1, user t2, vacation_reasons t3
               where
               t1.vac_status IN (0,1) and
               t1.vac_crtusr IN ({$approve_userstr}) and
               t1.vac_crtusr = t2.id and
               t2.user_status = 1 and
               t1.vac_reasonid = t3.id
               order by t2.user_firstname, t2.user_lastname";
      $toapprovevacas = $CON->select($sql);

      //----------------------------------------------------------------------------------
      // show vacations
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($toapprovevacas) && $toapprovevacas != false; $x++)
      {
         //----------------------------------------------------------------------------------
         // show table header
         //----------------------------------------------------------------------------------
         if(!$x)
         {  ?>
            <table border="0" cellpadding="0" cellspacing="0" width="822">
            <tr>
               <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["VAC"][0]?></b></td>
               <td align="right"><?=$savemsg?></td>
            </tr>
            <tr>
               <td class="content_headerline" colspan="2">&nbsp;</td>
            </tr>
            </table>
            <?=Nifty_printH("box1", "822")?>
            <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col>
               <col width="140">
               <col width="160">
               <col width="135">
               <col width="120">
               <col width="85">
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="6"><?=$_LANG["MODULE"]["VAC"][1]?></td>
            </tr>
            <tr>
               <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["VAC"][2]?></td>
               <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["VAC"][3]?></td>
               <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["VAC"][4]?></td>
               <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["VAC"][5]?></td>
               <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["VAC"][6]?></td>
               <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["VAC"][7]?></td>
            </tr>
            <?php
         }
         //----------------------------------------------------------------------------------
         // show row
         //----------------------------------------------------------------------------------
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row"><?=$toapprovevacas[$x]["user_firstname"]?> <?=$toapprovevacas[$x]["user_lastname"]?></td>
            <td class="content_row"><b><?=$toapprovevacas[$x]["reason_desc"]?></b></td>
            <td class="content_row">
               <?=date('d.m.Y', $toapprovevacas[$x]["vac_startdate"])?> - <?=date('d.m.Y', $toapprovevacas[$x]["vac_enddate"])?>
            </td>
            <td class="content_row"><b><?=getVacationStatus($toapprovevacas[$x]["vac_status"])?></b></td>
            <td class="content_row"><?=date('d.m.Y H:i', $toapprovevacas[$x]["vac_crtdat"])?></td>
            <td class="content_row" align="center">
               <ul class="postnav">
                  <a href="index.php?mid=<?=$_REQUEST["mid"]?>&exec=show&selmonth=<?=$_REQUEST["selmonth"]?>&selyear=<?=$_REQUEST["selyear"]?>&vacid=<?=$toapprovevacas[$x]["id"]?>"><?=$_LANG["FORM"]["BUTTON"][5]?></a>
               </ul>
            </td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         // show table footer
         //----------------------------------------------------------------------------------
         if($x == (count($toapprovevacas) -1))
         {  ?>
            </table>
            <?=Nifty_printF()?>
            <br>
            <?php
         }
      }
   }

   //----------------------------------------------------------------------------------
   // get days of month
   //----------------------------------------------------------------------------------
   $days       = Array();
   $dates      = getDaysofMonth($months, $weekdays, $_REQUEST["selmonth"], $_REQUEST["selyear"]);
   foreach($dates AS $week)
      foreach($week AS $day)
         array_push($days, $day);
   
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
   // show month/year selection
   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["VAC"][11]?> <?=$months[(int)$_REQUEST["selmonth"]]?> <?=$_REQUEST["selyear"]?></b></td>
      <td align="right"><?=$savemsg?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <script language="Javascript" src="./libs/jscripts/overlib/overlib.js"></script>
   <div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
   <?=Nifty_printH("boxopt_t", "822")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <tr>
      <td class="content_tbl_header" align="center">
         <input type="button" class="button" value="&lt;&lt;" style="width:40px"
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&selyear=<?=$year_back?>&selmonth=' +document.all.selmonth.value"
         onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
         <?php if($year_back < $curryear -2) echo "disabled" ?>>
   
         <input type="button" class="button" value="&lt;" style="width:30px"
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&selyear=<?=$month_back2?>&selmonth=<?=$month_back1?>'"
         onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
         <?php if($month_back2 < $curryear -2) echo "disabled" ?>>
   
         <select class="text" id="selmonth" style="width:120px"
         onchange="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&selyear=' +document.all.selyear.value +'&selmonth=' +this.value">
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
         onchange="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&selyear=' +this.value +'&selmonth=' +document.all.selmonth.value">
         <?php
         for($x = ($curryear -2); $x <= ($curryear +2); $x++)
         {  ?>
            <option value="<?=$x?>" <?php if($x == $_REQUEST["selyear"]) echo "selected"?>><?=$x?></option>
            <?php
         }
         ?>
         </select>

         <input type="button" class="button" value="&gt;" style="width:30px"
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&selyear=<?=$month_forw2?>&selmonth=<?=$month_forw1?>'"
         onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
         <?php if($month_forw2 > $curryear +2) echo "disabled" ?>>
   
         <input type="button" class="button" value="&gt;&gt;" style="width:40px"
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&selyear=<?=$year_forw?>&selmonth=' +document.all.selmonth.value"
         onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
         <?php if($year_forw > $curryear +2) echo "disabled" ?>>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?=Nifty_printH("box1", "822")?>
   <table border="0" class="content_table" cellpadding="2" cellspacing="0" width="100%">
   <tr>
      <td class="content_tbl_header" rowspan="2"><?=$_LANG["MODULE"]["VAC"][12]?></td>
      <td class="content_tbl_header" rowspan="2" align="center">
         <img src="./images/menu/icons/status_green.gif">
      </td>
      <?php
      // print weekday names
      $daycounter = 0;
      foreach($days AS $day)
      {
         if($day["WEEKDAY"] == 6 || $day["WEEKDAY"] == 7)
            $styleclass = "content_tbl_header";
         else
            $styleclass = "content_tbl_subheader";
         ?>
         <td class="<?=$styleclass?>" align="center" style="width:13px"><?=substr($day["DAYSTRING"],0,2)?></td>
         <?php
         $daycounter++;
      }
      ?>
   </tr>
   <tr>
      <?php
      // print day numbers
      foreach($days AS $day)
      {
         if($day["WEEKDAY"] == 6 || $day["WEEKDAY"] == 7)
            $styleclass = "content_tbl_header";
         else
            $styleclass = "content_tbl_subheader";
         ?>
         <td class="<?=$styleclass?>" align="center" style="width:13px"><?=$day["DAY"]?></td>
         <?php
      }
      ?>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   // select groups
   //----------------------------------------------------------------------------------
   $sql = " select id, group_name, group_desc
            from `group`
            where
            group_status = 1 ";
            
   if($_SESSION["user_type"] != 1)
      $sql .= " and ( group_visible = 1 or id IN ({$sesgrpstr})) ";
   
   $sql .= " order by group_name asc";
   $groups = $CON->select($sql);

   //----------------------------------------------------------------------------------
   // loop through groups
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($groups) && $groups != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor(1)?>">
         <td class="content_row_os" colspan="<?=($daycounter +2)?>">
            <img src="./images/menu/icons/group_overview.gif">&nbsp;
            <b><?=$groups[$x]["group_name"]?></b>
         </td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      // select users of the group
      //----------------------------------------------------------------------------------
      $sql = " select t2.id, t2.user_firstname, t2.user_lastname, t1.is_adm
               from user_group t1, user t2
               where
               t2.user_status = 1 and
               t1.user_id = t2.id and
               t1.group_id = {$groups[$x]["id"]} ";
      
      if($_SESSION["user_type"] != 1)
         $sql .= " and ( t2.user_visible = 1 or t2.id = {$_SESSION["user_id"]} ) ";
   
      $sql .= " order by t1.is_adm desc, t2.user_firstname asc, t2.user_lastname asc";
      $users = $CON->select($sql);

      //----------------------------------------------------------------------------------
      // loop through users
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($users) && $users != false; $y++)
      {
         // set index of column
         $tdid = "idx_vactd{$groups[$x]["id"]}_{$users[$y]["id"]}";

         // get vacation days
         $vacdays = getVacations($CON, $_REQUEST["selmonth"], $_REQUEST["selyear"], $users[$y]["id"]);

         // get used days of vacations per year
         $vacdayssum = getVacationsOfYear($CON, $_REQUEST["selyear"], $users[$y]["id"]);
                  
         ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_os" id="<?=$tdid?>">
               <?=$users[$y]["user_firstname"]?> <?=$users[$y]["user_lastname"]?>
               <?php
               if($users[$y]["is_adm"] == "1")
               {  ?>
                  <img src="./images/menu/icons/star.gif">
                  <?php
               }
               ?>
            </td>
            <td class="content_row_os" align="center" width="20"><?=$vacdayssum?></td>
            <?php
            foreach($days AS $day)
            {  ?>
               <td class="content_row_os" align="center"
               <?php
               $chkidx = date('d.m.Y', $day["RAWDATE"]);

               //----------------------------------------------------------------------------------
               // days is vacation
               //----------------------------------------------------------------------------------
               if((int)$vacdays[$chkidx]["ID"] > 0)
               {
                  if(date('w', $day["RAWDATE"]) == 6 || date('w', $day["RAWDATE"]) == 0)
                  {
                     switch((int)$vacdays[$chkidx]["STAT"])
                     {
                        case 0: $bgcolor = "bgcolor=#BF4545"; break;
                        case 1: $bgcolor = "bgcolor=#DB7500"; break;
                        case 2: $bgcolor = "bgcolor=#128512"; break;
                     }
                  }
                  else
                  {
                     switch((int)$vacdays[$chkidx]["STAT"])
                     {
                        case 0: $bgcolor = "bgcolor=#FF5B5B"; break;
                        case 1: $bgcolor = "bgcolor=orange"; break;
                        case 2: $bgcolor = "bgcolor=#1BC81B"; break;
                     }
                  }
                  
                  $tdcnt = $vacdays[$chkidx]["SHRT"];

                  //----------------------------------------------------------------------------------
                  // create overlib data
                  //----------------------------------------------------------------------------------
                  $overlibover   = "<table bgcolor=#FFFFFF class=content_table border=0 cellpadding=2 cellspacing=0 width=350>";
                  $overlibover  .= "<tr ".getRowColor(0).">";
                  $overlibover  .= "<td class=content_row>{$_LANG["MODULE"]["VAC"][13]}</td>";
                  $overlibover  .= "<td class=content_row>{$users[$y]["user_firstname"]} {$users[$y]["user_lastname"]}</td>";
                  $overlibover  .= "</tr>";
                  $overlibover  .= "<tr ".getRowColor(0).">";
                  $overlibover  .= "<td class=content_row>{$_LANG["MODULE"]["VAC"][14]}</td>";
                  $overlibover  .= "<td class=content_row><b>{$vacdays[$chkidx]["DESC"]}</b></td>";
                  $overlibover  .= "</tr>";
                  $overlibover  .= "<tr ".getRowColor(0).">";
                  $overlibover  .= "<td class=content_row>{$_LANG["MODULE"]["VAC"][15]}</td>";
                  $overlibover  .= "<td class=content_row>".date('d.m.Y', $vacdays[$chkidx]["STARTDATE"])." - ".date('d.m.Y', $vacdays[$chkidx]["ENDDATE"])."</td>";
                  $overlibover  .= "</tr>";
                  $overlibover  .= "<tr ".getRowColor(0).">";
                  $overlibover  .= "<td class=content_row>{$_LANG["MODULE"]["VAC"][40]}</td>";
                  $overlibover  .= "<td class=content_row>{$vacdays[$chkidx]["DAYS"]}</td>";
                  $overlibover  .= "</tr>";
                  $overlibover  .= "<tr ".getRowColor(0).">";
                  $overlibover  .= "<td class=content_row>{$_LANG["MODULE"]["VAC"][16]}</td>";
                  $overlibover  .= "<td class=content_row><b>{$vacdays[$chkidx]["STATDESC"]}</b></td>";
                  $overlibover  .= "</tr>";
                  $overlibover  .= "<tr ".getRowColor(0).">";
                  $overlibover  .= "<td class=content_row>{$_LANG["MODULE"]["VAC"][17]}</td>";
                  $overlibover  .= "<td class=content_row>".date('d.m.Y H:i', $vacdays[$chkidx]["CREATED"])."</td>";
                  $overlibover  .= "</tr>";
                  if($vacdays[$chkidx]["STAT"] == "2")
                  {
                     $overlibover  .= "<tr ".getRowColor(0).">";
                     $overlibover  .= "<td class=content_row>{$_LANG["MODULE"]["VAC"][18]}</td>";
                     $overlibover  .= "<td class=content_row>".date('d.m.Y H:i', $vacdays[$chkidx]["UPDDAT"])."</td>";
                     $overlibover  .= "</tr>";
                     $overlibover  .= "<tr ".getRowColor(0).">";
                     $overlibover  .= "<td class=content_row>{$_LANG["MODULE"]["VAC"][19]}</td>";
                     $overlibover  .= "<td class=content_row>{$vacdays[$chkidx]["UPDUSR"]}</td>";
                     $overlibover  .= "</tr>";
                  }
                  $overlibover  .= "</table>";
                  $overlibover   = "return overlib('{$overlibover}', WIDTH, 350, LEFT, FGCOLOR, '#FFFFFF', BGCOLOR, '#333333', ABOVE)";
                  $overlibout    = "return nd()";
                  $thisvacid     = $vacdays[$chkidx]["ID"];
               }
               else
               {
                  $bgcolor       = "";
                  $tdcnt         = "&nbsp;";
                  $overlibover   = "";
                  $overlibover   = "";
                  $thisvacid     = "";

                  if(date('w', $day["RAWDATE"]) == 6 || date('w', $day["RAWDATE"]) == 0)
                     $bgcolor = "bgcolor=".getRowColor(1);
               }

               //----------------------------------------------------------------------------------
               // print effects and events
               //----------------------------------------------------------------------------------
               if($users[$y]["id"] == $_SESSION["user_id"] || $approve_users_sel[$users[$y]["id"]] == 1)
               {
                  if($thisvacid != "")
                  {  ?>
                     style="cursor:pointer"
                     onmouseover="mark(this, 0); mark(document.all.<?=$tdid?>, 0); <?=$overlibover?>"
                     onmouseout="mark(this,1); mark(document.all.<?=$tdid?>, 1); <?=$overlibout?>"
                     onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=show&unixraw=<?=$day["RAWDATE"]?>&vacid=<?=$thisvacid?>'"
                     <?php
                  }
                  elseif($users[$y]["id"] == $_SESSION["user_id"])
                  {  ?>
                     style="cursor:pointer"
                     onmouseover="mark(this, 0); mark(document.all.<?=$tdid?>, 0); <?=$overlibover?>"
                     onmouseout="mark(this,1); mark(document.all.<?=$tdid?>, 1); <?=$overlibout?>"
                     onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=add&unixraw=<?=$day["RAWDATE"]?>&vacid=<?=$thisvacid?>'"
                     <?php
                  }
               }
               else
               {  ?>
                  onmouseover="<?=$overlibover?>"
                  onmouseout="<?=$overlibout?>"
                  <?php
               }
               echo $bgcolor;
               ?>><?=$tdcnt?></td>
               <?php
            }
            ?>
         </tr>
         <?php
      }
   }
   ?>
   </table>
   <br>
   <?php
}