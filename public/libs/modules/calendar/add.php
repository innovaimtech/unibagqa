<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       22.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
   $daysec  = 86400;
   
   //----------------------------------------------------------------------------------
   $_REQUEST["date1"] = explode(".", $_REQUEST["date1"]);
   $_REQUEST["date2"] = explode(".", $_REQUEST["date2"]);
   
   $cal_startdate = mktime($_REQUEST["hour1"], $_REQUEST["min1"], 0, $_REQUEST["date1"][1], $_REQUEST["date1"][0], $_REQUEST["date1"][2]);
   $cal_enddate   = mktime($_REQUEST["hour2"], $_REQUEST["min2"], 0, $_REQUEST["date2"][1], $_REQUEST["date2"][0], $_REQUEST["date2"][2]);
   $cal_header    = trim(addslashes($_REQUEST["cal_header"]));
   $cal_body      = trim(addslashes($_REQUEST["cal_body"]));
   $cal_docid     = (int)$_REQUEST["cal_docid"];
   $cal_type      = (int)$_REQUEST["cal_type"];

   //----------------------------------------------------------------------------------
   if($_REQUEST["cal_id"] == "")
   {
      $sql = " insert into calendar_appointments
              (cal_startdate, cal_enddate, cal_crtusr,
               cal_crtdat, cal_header, cal_body,
               cal_startday, cal_startmonth, cal_startyear,
               cal_endday, cal_endmonth, cal_endyear,
               cal_docid, cal_type)
              VALUES
              ({$cal_startdate}, {$cal_enddate}, {$_SESSION["user_id"]},
               {$currtme}, '{$cal_header}', '{$cal_body}',
               {$_REQUEST["date1"][0]}, {$_REQUEST["date1"][1]}, {$_REQUEST["date1"][2]},
               {$_REQUEST["date2"][0]}, {$_REQUEST["date2"][1]}, {$_REQUEST["date2"][2]},
               {$cal_docid}, {$cal_type})";
      $res = $CON->no_result($sql);
      
      if($res)
      {
         $sql = " select MAX(id) 'thisid'
                  from calendar_appointments";
         $thisid = $CON->select($sql);
         $thisid = (int)$thisid[0]["thisid"];
      }
      
      if((int)$thisid && $cal_type == 2)
      {
         $loopdiff  = $cal_enddate - $cal_startdate;
         $loopstart = $cal_startdate +$daysec;
         
         while(date('Y', $loopstart) == date('Y', $cal_startdate))
         {
            if(date('w', $loopstart) == date('w', $cal_startdate))
            {
               $cal_startdate = mktime($_REQUEST["hour1"], $_REQUEST["min1"], 0, date('m', $loopstart), date('d', $loopstart), date('Y', $loopstart));
               $cal_enddate   = $cal_startdate + $loopdiff;
   
               $sql = " insert into calendar_appointments
                         (cal_startdate, cal_enddate, cal_crtusr,
                          cal_crtdat, cal_header, cal_body,
                          cal_startday, cal_startmonth, cal_startyear,
                          cal_endday, cal_endmonth, cal_endyear,
                          cal_docid, cal_type, cal_parent)
                         VALUES
                         ({$cal_startdate}, {$cal_enddate}, {$_SESSION["user_id"]},
                          {$currtme}, '{$cal_header}', '{$cal_body}',
                          ".date('d', $cal_startdate).",
                          ".date('m', $cal_startdate).",
                          ".date('Y', $cal_startdate).",
                          ".date('d', $cal_enddate).",
                          ".date('m', $cal_enddate).",
                          ".date('Y', $cal_enddate).",
                          {$cal_docid}, {$cal_type},
                          {$thisid})";
               $CON->no_result($sql);
            }
            $loopstart += $daysec;
         }
      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $sql = " select cal_startdate, cal_enddate, cal_header, cal_body, cal_docid
               from calendar_appointments
               where
               id = {$_REQUEST["cal_id"]}";
      $orgdata = $CON->select($sql);
      $orgdata = $orgdata[0];

      $sql = " select user_id
               from calendar_shared_usr
               where
               cal_id = {$_REQUEST["cal_id"]}";
      $orguser = $CON->select($sql);
      for($x = 0; $x < count($orguser) && $orguser != false; $x++)
         $orgusers[$orguser[$x]["user_id"]] = 1;

      $sql = " select group_id
               from calendar_shared_grp
               where
               cal_id = {$_REQUEST["cal_id"]}";
      $orggrps = $CON->select($sql);
      for($x = 0; $x < count($orggrps) && $orggrps != false; $x++)
      {
         $sql = " select t1.user_id
                  from user_group t1, user t2
                  where
                  t1.group_id = {$orggrps[$x]["group_id"]} and
                  t1.user_id = t2.id and
                  t2.user_status = 1";
         $grpusr = $CON->select($sql);
         for($y = 0; $y < count($grpusr) && $grpusr != false; $y++)
            $orgusers[$grpusr[$y]["user_id"]] = 1;
      }

      $sql = " update calendar_appointments
               set
               cal_startdate  = {$cal_startdate},
               cal_enddate    = {$cal_enddate},
               cal_header     = '{$cal_header}',
               cal_body       = '{$cal_body}',
               cal_startday   = {$_REQUEST["date1"][0]},
               cal_startmonth = {$_REQUEST["date1"][1]},
               cal_startyear  = {$_REQUEST["date1"][2]},
               cal_endday     = {$_REQUEST["date2"][0]},
               cal_endmonth   = {$_REQUEST["date2"][1]},
               cal_endyear    = {$_REQUEST["date2"][2]},
               cal_docid      = {$cal_docid}
               where
               id = {$_REQUEST["cal_id"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg    = getSaveMessage($res);
   $savestat   = $res;

   //----------------------------------------------------------------------------------
   if($res)
   {
      if($_REQUEST["cal_id"] == "")
      {
         $thiscalid = $thisid;
      }
      else
      {
         $thiscalid = $_REQUEST["cal_id"];

         $sql = " delete from calendar_shared_usr
                  where
                  cal_id IN ({$thiscalid})";
         $CON->no_result($sql);
         
         $sql = " delete from calendar_shared_grp
                  where
                  cal_id IN ({$thiscalid})";
         $CON->no_result($sql);
      }
   }

   //----------------------------------------------------------------------------------
   if($res && is_array($_REQUEST["userids"]))
   {
      foreach($_REQUEST["userids"] AS $userid)
      {
         $sql = " select user_firstname, user_lastname
                  from user
                  where
                  id = {$userid}";
         $username = $CON->select($sql);
         $userstr .= "{$username[0]["user_firstname"]} {$username[0]["user_lastname"]}, ";
         
         $sql = " insert into calendar_shared_usr
                  (cal_id, user_id, share_crtdat)
                  VALUES
                  ({$thiscalid}, {$userid}, {$currtme})";
         $CON->no_result($sql);

         if($_REQUEST["cal_id"] == "" && ($cal_type == 2 || $cal_type == 3))
         {
            $sql = " select id
                     from calendar_appointments
                     where
                     cal_parent = {$thiscalid}";
            $childs = $CON->select($sql);

            for($z = 0; $z < count($childs) && $childs != false; $z++)
            {
               $sql = " insert into calendar_shared_usr
                        (cal_id, user_id, share_crtdat)
                        VALUES
                        ({$childs[$z]["id"]}, {$userid}, {$currtme})";
               $CON->no_result($sql);
            }
         }

         $usersel[$userid] = 1;
      }
      $userstr = substr($userstr, 0, -2);
   }

   //----------------------------------------------------------------------------------
   if($res && is_array($_REQUEST["groupids"]))
   {
      foreach($_REQUEST["groupids"] AS $groupid)
      {
         $sql = " select group_name
                  from `group`
                  where
                  id = {$groupid}";
         $groupname = $CON->select($sql);
         $groupstr .= "{$groupname[0]["group_name"]}, ";

         $sql = " insert into calendar_shared_grp
                  (cal_id, group_id, share_crtdat)
                  VALUES
                  ({$thiscalid}, {$groupid}, {$currtme})";
         $CON->no_result($sql);

         $sql = " select t1.user_id
                  from user_group t1, user t2
                  where
                  t1.group_id = {$groupid} and
                  t1.user_id = t2.id and
                  t2.user_status = 1";
         $grpusr = $CON->select($sql);
         for($x = 0; $x < count($grpusr) && $grpusr != false; $x++)
            $usersel[$grpusr[$x]["user_id"]] = 1;

         if($_REQUEST["cal_id"] == "" && ($cal_type == 2 || $cal_type == 3))
         {
            $sql = " select id
                     from calendar_appointments
                     where
                     cal_parent = {$thiscalid}";
            $childs = $CON->select($sql);

            for($z = 0; $z < count($childs) && $childs != false; $z++)
            {
               $sql = " insert into calendar_shared_grp
                        (cal_id, group_id, share_crtdat)
                        VALUES
                        ({$childs[$z]["id"]}, {$groupid}, {$currtme})";
               $CON->no_result($sql);
            }
         }
      }
      $groupstr = substr($groupstr, 0, -2);
   }

   if($res)
   {
      $sql = " select t1.*, t2.user_firstname, t2.user_lastname
               from calendar_appointments t1, user t2
               where
               t1.id = {$thiscalid} and
               t1.cal_crtusr = t2.id";
      $caldata = $CON->select($sql);
      $caldata = $caldata[0];
   }

   //----------------------------------------------------------------------------------
   if($res && is_array($orgusers))
      foreach(array_keys($orgusers) AS $usrid)
         if($orgusers[$usrid] == 1 && !(int)$usersel[$usrid])
            $delusers[$usrid] = 1;

   //----------------------------------------------------------------------------------
   if($res && is_array($usersel) &&
      (
         $_REQUEST["cal_id"] == "" ||
         (
            $orgdata["cal_startdate"]  != $cal_startdate ||
            $orgdata["cal_enddate"]    != $cal_enddate   ||
            $orgdata["cal_header"]     != $cal_header    ||
            $orgdata["cal_body"]       != $cal_body      ||
            $orgdata["cal_docid"]      != $cal_docid     ||
            $orgusers                  != $usersel
         )
      ))
   {
      $attachfile = NULL;

      $sql = " select cal_docid
               from calendar_appointments
               where
               id = {$caldata["id"]}";

      $msg_attachment = $CON->select($sql);
      $msg_attachment = $msg_attachment[0];
      if((int)$msg_attachment["cal_docid"])
      {
         $sql = " select t2.*
                  from menu_items t1, menu_docs t2
                  where
                  t1.menu_docid = t2.id and
                  t1.id = {$msg_attachment["cal_docid"]}";
         $docdata = $CON->select($sql);
         $docdata = $docdata[0];

         if((int)$docdata["id"])
         {
            $attachfile[0]["FILE"] = "./docs/{$docdata["id"]}.{$docdata["doc_hash"]}";
            $attachfile[0]["NAME"] = $docdata["doc_name"];
         }
      }
            
      foreach(array_keys($usersel) AS $usrid)
      {
         $sql = " select user_firstname, user_lastname, user_mailforward, user_mail
                  from user
                  where
                  id = {$usrid}";
         $mailforw = $CON->select($sql);

         if((int)$mailforw[0]["user_mailforward"])
         {
            $title   = $_LANG["MODULE"]["CAL"][61].stripslashes($caldata["cal_header"]);

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
                              <b class="msg_save_ok">'.sprintf($_LANG["MODULE"]["CAL"][62], $_SESSION["user_firstname"].' '.$_SESSION["user_lastname"]).'</b>
                              <br><br>
                              '.getAppointmentText($caldata, $groupstr, $userstr, false).'
                           </td>
                        </tr>
                        </table>
                        </body>
                        </html>';

            $sentmails = sendExternalMail($title,
                                          $text,
                                          $mailforw[0]["user_mail"],
                                          "{$mailforw[0]["user_firstname"]} {$mailforw[0]["user_lastname"]}",
                                          "",
                                          "",
                                          $attachfile);
            if($sentmails <= 0)
               $mailerr++;
         }
      }
   }

   //----------------------------------------------------------------------------------
   if($res && is_array($delusers))
   {
      foreach(array_keys($delusers) AS $usrid)
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
                              '.getAppointmentText($caldata, $groupstr, $userstr, true).'
                           </td>
                        </tr>
                        </table>
                        </body>
                        </html>';

            $sentmails = sendExternalMail($title,
                                          $text,
                                          $mailforw[0]["user_mail"],
                                          "{$mailforw[0]["user_firstname"]} {$mailforw[0]["user_lastname"]}");
            if($sentmails <= 0)
               $mailerr++;
         }
      }
   }

   if($mailerr > 0)
      $savemsg = "<b class='msg_save_err'>{$_LANG["MODULE"]["MSG"][47]}</b>";

   if($savestat && (int)$mailerr == 0)
   {  ?>
      <script language="JavaScript">
         location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&unixraw=<?=$_REQUEST["unixraw"]?>&saveok=1'
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["cal_id"] != "")
{
   $title = $_LANG["MODULE"]["CAL"][29];

   $sql = " select *
            from calendar_appointments
            where
            id = {$_REQUEST["cal_id"]}";
   $caldata = $CON->select($sql);
   $caldata = $caldata[0];

   if($caldata["cal_crtusr"] != $_SESSION["user_id"])
      die("");

   $selday     = date('d', $caldata["cal_startdate"]);
   $selmonth   = date('m', $caldata["cal_startdate"]);
   $selyear    = date('Y', $caldata["cal_startdate"]);

   $selday2    = date('d', $caldata["cal_enddate"]);
   $selmonth2  = date('m', $caldata["cal_enddate"]);
   $selyear2   = date('Y', $caldata["cal_enddate"]);

   $selhour    = date('H', $caldata["cal_startdate"]);
   $selmin     = date('i', $caldata["cal_startdate"]);
   $selhour2   = date('H', $caldata["cal_enddate"]);
   $selmin2    = date('i', $caldata["cal_enddate"]);

   $sql = " select user_id
            from calendar_shared_usr
            where
            cal_id = {$_REQUEST["cal_id"]}";
   $selusers = $CON->select($sql);
   
   $sql = " select group_id
            from calendar_shared_grp
            where
            cal_id = {$_REQUEST["cal_id"]}";
   $selgroups = $CON->select($sql);
}

//----------------------------------------------------------------------------------
else
{
   $title = $_LANG["MODULE"]["CAL"][30];
   
   $selday     = date('d', $_REQUEST["unixraw"]);
   $selmonth   = date('m', $_REQUEST["unixraw"]);
   $selyear    = date('Y', $_REQUEST["unixraw"]);

   $selday2    = date('d', $_REQUEST["unixraw"]);
   $selmonth2  = date('m', $_REQUEST["unixraw"]);
   $selyear2   = date('Y', $_REQUEST["unixraw"]);

   if($_REQUEST["hour"] == "")
      $_REQUEST["hour"] = date('H') +1;
   
   if($_REQUEST["hour"] < 10)
      $_REQUEST["hour"] = "0{$_REQUEST["hour"]}";

   $selhour    = $_REQUEST["hour"];
   $selmin     = "00";
   $selhour2   = $_REQUEST["hour"];
   $selmin2    = "30";
}

//----------------------------------------------------------------------------------
$sql = " select id, group_name, group_desc
         from `group`
         where
         group_status = 1 ";
         
if($_SESSION["user_type"] != 1)
   $sql .= " and group_visible = 1 ";
   
$sql .= " order by group_name asc";
$groups = $CON->select($sql);

//----------------------------------------------------------------------------------
$groupstr = "";

for($x = 0; $x < count($groups) && $groups != false; $x++)
   for($y = 0; $y < count($selgroups) && $selgroups != false; $y++)
      if($selgroups[$y]["group_id"] == $groups[$x]["id"])
      {
         $groups[$x]["group_active"] = 1;
         $groupstr .= "{$groups[$x]["group_name"]}, ";
      }
$groupstr = substr($groupstr, 0, -2);

//----------------------------------------------------------------------------------
$sql = " select id, user_firstname, user_lastname, user_type
         from user
         where
         user_status = 1 and
         id != {$_SESSION["user_id"]} ";
         
if($_SESSION["user_type"] != 1)
   $sql .= " and user_visible = 1 ";
   
$sql .= " order by user_firstname, user_lastname asc";

$users = $CON->select($sql);

//----------------------------------------------------------------------------------
$userstr = "";

for($x = 0; $x < count($users) && $users != false; $x++)
   for($y = 0; $y < count($selusers) && $selusers != false; $y++)
      if($selusers[$y]["user_id"] == $users[$x]["id"])
      {
         $users[$x]["user_active"] = 1;
         $userstr .= "{$users[$x]["user_firstname"]} {$users[$x]["user_lastname"]}, ";
      }
$userstr = substr($userstr, 0, -2);

?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="822">
<form action="index.php" method="post" name="xform_msg"
onsubmit="return checkform(new Array(
this.date1, this.hour1, this.min1,
this.date2, this.hour2, this.min2,
this.cal_header))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="add">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="unixraw" value="<?=$_REQUEST["unixraw"]?>">
<input type="hidden" name="cal_id" value="<?=$_REQUEST["cal_id"]?>">
<input type="hidden" name="returnTo" value="<?=$_REQUEST["returnTo"]?>">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><?=$savemsg?></td>
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
   <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["CAL"][31]?></td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CAL"][32]?></td>
   <td class="content_row">
   
      <input type="text" name="date1" style="width:70px" value="<?="{$selday}.{$selmonth}.{$selyear}"?>"
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency" id="date1"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">

      &nbsp;&nbsp;
      
      <input type="text" class="text" style="width:30px" name="hour1"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?=$selhour?>">
      :
      <input type="text" class="text" style="width:30px" name="min1"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?=$selmin?>">
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CAL"][33]?></td>
   <td class="content_row">
      <input type="text" name="date2" style="width:70px" value="<?="{$selday2}.{$selmonth2}.{$selyear2}"?>"
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency" id="date2"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
      
      &nbsp;&nbsp;
      
      <input type="text" class="text" style="width:30px" name="hour2"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?=$selhour2?>">
      :
      <input type="text" class="text" style="width:30px" name="min2"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?=$selmin2?>">
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CAL"][65]?></td>
   <td class="content_row">
      <?php
      if($_REQUEST["cal_id"] == "")
      {  ?>
         <select class="text" name="cal_type"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            <option value="1" <?php if((int)$caldata["cal_type"] == 1) echo "selected"?>><?=$_LANG["MODULE"]["CAL"][66]?></option>
            <option value="2" <?php if((int)$caldata["cal_type"] == 2) echo "selected"?>><?=$_LANG["MODULE"]["CAL"][67]?></option>
         </select>
         <?php
      }
      else
      {
         switch((int)$caldata["cal_type"])
         {
            case 1: echo $_LANG["MODULE"]["CAL"][66]; break;
            case 2: echo $_LANG["MODULE"]["CAL"][67]; break;
         }

         if((int)$caldata["cal_type"] == 2)
         {
            $dayofweek  = date('w', $caldata["cal_startdate"]);
            
            if($dayofweek == 0)
               $dayofweek = 7;

            echo " / {$weekdays[$dayofweek]}";
         }
      }
      ?>
   </td>
</tr>
<?php
if($userstr != "")
{  ?>
   <tr>
      <td class="content_row"><?=$_LANG["MODULE"]["CAL"][34]?></td>
      <td class="content_row"><?=$userstr?></td>
   </tr>
   <?php
}
if($groupstr != "")
{  ?>
   <tr>
      <td class="content_row"><?=$_LANG["MODULE"]["CAL"][35]?></td>
      <td class="content_row"><?=$groupstr?></td>
   </tr>
   <?php
}
?>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CAL"][36]?></td>
   <td class="content_row">
      <input type="text" class="text" style="width:660px" maxlength="254" name="cal_header" value="<?=stripslashes($caldata["cal_header"])?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row" valign="top"><?=$_LANG["MODULE"]["CAL"][37]?></td>
   <td class="content_row">
      <textarea class="text" style="width:660px; height:140px" name="cal_body"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($caldata["cal_body"])?></textarea>
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["CAL"][38]?></td>
   <td class="content_row">
      <select class="text" name="cal_docid"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         $docs = $_SESSION["_MENU"]->getDocuments();
         if(is_array($docs) && count($docs) > 0)
            foreach($docs AS $doc)
            {  ?>
               <option value="<?=$doc["id"]?>"
               <?php if($doc["id"] == $caldata["cal_docid"]) echo "selected"?>><?=$doc["path"]?></option>
               <?php
            }
         ?>
      </select>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<table border="0" cellpadding="0" cellspacing="0" width="822">
<tr>
   <td width="404" valign="top">
      <?=Nifty_printH("box2", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td style="cursor:pointer" class="content_tbl_header"
         onclick="showObject(document.all.idx_tbl_grp); showObject(document.all.idx_tbl_grp_btn)">
            <img src="./images/content/content_plus.gif">
            <b><?=$_LANG["MODULE"]["CAL"][39]?></b></td>
      </tr>
      </table>
      <table id="idx_tbl_grp" style="display:none" border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="30">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["CAL"][40]?></td>
         <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["CAL"][41]?></td>
      </tr>
      <?php
      for($x = 0; $x < count($groups) && $groups != false; $x++)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row" align="center">
               <input type="checkbox" name="groupids[]" value="<?=$groups[$x]["id"]?>"
               <?php if((int)$groups[$x]["group_active"] > 0) echo "checked"?>>
            </td>
            <td class="content_row"><?=$groups[$x]["group_name"]?></td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
   </td>
   <td width="14">
   <td width="404" valign="top">
      <?=Nifty_printH("box2", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td style="cursor:pointer" class="content_tbl_header"
         onclick="showObject(document.all.idx_tbl_usr); showObject(document.all.idx_tbl_usr_btn)">
            <img src="./images/content/content_plus.gif">
            <b><?=$_LANG["MODULE"]["CAL"][42]?></b>
         </td>
      </tr>
      </table>
      <table id="idx_tbl_usr" style="display:none" border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="30">
         <col>
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["CAL"][40]?></td>
         <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["CAL"][43]?></td>
         <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["CAL"][44]?></td>
      </tr>
      <?php
      for($x = 0; $x < count($users) && $users != false; $x++)
      {
         if((int)$users[$x]["user_type"] == 1)
            $utype = $_LANG["MODULE"]["CAL"][45];
         else
            $utype = $_LANG["MODULE"]["CAL"][46];
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row" align="center">
               <input type="checkbox" name="userids[]" value="<?=$users[$x]["id"]?>"
               <?php if((int)$users[$x]["user_active"] > 0) echo "checked"?>>
            </td>
            <td class="content_row"><?=$users[$x]["user_firstname"]?> <?=$users[$x]["user_lastname"]?></td>
            <td class="content_row"><?=$utype?></td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
   </td>
</tr>
</table>
<br>
<?php
//----------------------------------------------------------------------------------
?>
<?=Nifty_printH("boxopt_b", "822")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td align="left" width="130">
      <?php
      if($_REQUEST["returnTo"] == "edit")
      {  ?>
         <ul class="postnav">
            <a href="index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&unixraw=<?=$_REQUEST["unixraw"]?>"><?=$_LANG["FORM"]["BUTTON"][1]?></a>
         </ul>
         <?php
      }
      else
      {  ?>
         <ul class="postnav">
            <a href="index.php?mid=<?=$_REQUEST["mid"]?>&selmonth=<?=$selmonth?>&selyear=<?=$selyear?>"><?=$_LANG["FORM"]["BUTTON"][1]?></a>
         </ul>
         <?php
      }
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["cal_id"] != "")
   {
      if(((int)$caldata["cal_type"] == 2 || (int)$caldata["cal_type"] == 3) && !(int)$caldata["cal_parent"])
         $val_dsp = $_LANG["MODULE"]["CAL"][69];
      else
         $val_dsp = $_LANG["FORM"]["BUTTON"][2];
      ?>
      <td align="right" width="130" style="padding-right:5px">
         <ul class="postnav_del">
            <a href="javascript: deactivateFormChange()"
            onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&exec=delete&cal_id=<?=$_REQUEST["cal_id"]?>&selmonth=<?=$selmonth?>&selyear=<?=$selyear?>')"><?=$val_dsp?></a>
         </ul>
      </td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <ul class="postnav_save">
         <a href="javascript: submitForm(document.xform_msg)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
      </ul>
   </td>
</tr>
</form>
</table>
<?=Nifty_printF(false)?>
<?php $_SESSION["JSEXEC"] .= "document.all.hour1.focus();"; ?>