<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       22.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["exec"] == "send")
{
   // get current time
   $currtme = time();

   // format parameters
   $_REQUEST["msg_parent"] = (int)$_REQUEST["msg_parent"];
   $_REQUEST["msg_header"] = trim(addslashes($_REQUEST["msg_header"]));
   $_REQUEST["msg_body"]   = trim(addslashes($_REQUEST["msg_body"]));
   $_REQUEST["msg_docid"]  = (int)$_REQUEST["msg_docid"];

   // insert message
   $sql = " insert into message_data
            (msg_header, msg_body, msg_parent, msg_docid, msg_crtusr, msg_crtdat)
            VALUES
            ('{$_REQUEST["msg_header"]}', '{$_REQUEST["msg_body"]}',
             {$_REQUEST["msg_parent"]}, {$_REQUEST["msg_docid"]},
             {$_SESSION["user_id"]}, {$currtme})";
   $res = $CON->no_result($sql);

   if($res)
   {
      // get id of the message
      $sql = " select MAX(id) 'msgid'
               from message_data";
      $msgid = $CON->select($sql);
      $msgid = $msgid[0]["msgid"];

      // create a entry for sent messages
      $sql = " insert into message_users
               (user_id, msg_id, msg_type, msg_status)
               VALUES
               ({$_SESSION["user_id"]}, {$msgid}, 1, 1)";
      $res = $CON->no_result($sql);

      // set Savemessage
      $savemsg = getSaveMessage($res);
      
      if($res)
      {
         // message has groups
         if(is_array($_REQUEST["grpids"]))
         {
            foreach($_REQUEST["grpids"] AS $grpid)
            {
               // select user for the group
               $sql = " select user_id
                        from user_group
                        where
                        group_id = {$grpid}";
               $grpusr = $CON->select($sql);
               for($x = 0; $x < count($grpusr) && $grpusr != false; $x++)
                  $usersel[$grpusr[$x]["user_id"]] = 1;
            }
         }

         // message has users
         if(is_array($_REQUEST["usrids"]))
            foreach($_REQUEST["usrids"] AS $usrid)
               $usersel[$usrid] = 1;

         //----------------------------------------------------------------------------------
         // loop through selected users
         //----------------------------------------------------------------------------------
         foreach(array_keys($usersel) AS $usrid)
         {
            // create message entry
            $sql = " insert into message_users
                     (user_id, msg_id, msg_type, msg_status)
                     VALUES
                     ({$usrid}, {$msgid}, 0, 0)";
            $CON->no_result($sql);

            // get userdata
            $sql = " select user_firstname, user_lastname, user_mailforward, user_mail
                     from user
                     where
                     id = {$usrid}";
            $mailforw = $CON->select($sql);

            // if mailforwarding is active send notification
            if((int)$mailforw[0]["user_mailforward"])
            {
               // set title
               $title  = stripslashes($_REQUEST["msg_header"]);

               // set body
               $text    = '<html>
                           <head><style type="text/css">body{font-family:Arial;font-size:12px;}</style></head>
                           <body>'.stripslashes($_REQUEST["msg_body"]).'</body></html>';

               $attachfile = NULL;
               
               // get message attachment
               $sql = " select msg_docid
                        from message_data
                        where
                        id = {$msgid}";
               $msg_attachment = $CON->select($sql);
               $msg_attachment = $msg_attachment[0];
               if((int)$msg_attachment["msg_docid"])
               {
                  $sql = " select t2.*
                           from menu_items t1, menu_docs t2
                           where
                           t1.menu_docid = t2.id and
                           t1.id = {$msg_attachment["msg_docid"]}";
                  $docdata = $CON->select($sql);
                  $docdata = $docdata[0];
                  
                  if((int)$docdata["id"])
                  {
                     $attachfile[0]["FILE"] = "./docs/{$docdata["id"]}.{$docdata["doc_hash"]}";
                     $attachfile[0]["NAME"] = $docdata["doc_name"];
                  }
               }

               // send mail
               /*
               $_REQUEST["overrideFromAddr"] = true;
               $sentmails = sendExternalMail($title,
                                             $text,
                                             $mailforw[0]["user_mail"],
                                             "{$mailforw[0]["user_firstname"]} {$mailforw[0]["user_lastname"]}",
                                             $_SESSION["user_mail"],
                                             "{$_SESSION["user_firstname"]} {$_SESSION["user_lastname"]}",
                                             $attachfile);
               if($sentmails <= 0)
                  $mailerr++;
               */
            }
         }

         // throw error, if error occured with mail transfers
         if($mailerr > 0)
            $savemsg = "<b class='msg_save_err'>{$_LANG["MODULE"]["MSG"][47]}</b>";
      }
   }
}

//----------------------------------------------------------------------------------
// if message has a parent, get history data
//----------------------------------------------------------------------------------
if($_REQUEST["msg_parent"] != "")
{
   // get parent data
   $sql = " select t1.*, t2.id 'user_id', t2.user_firstname, t2.user_lastname
            from message_data t1, user t2
            where
            t1.id = {$_REQUEST["msg_parent"]} and
            t1.msg_crtusr = t2.id";
   $replydata = $CON->select($sql);

   // create message title
   $msg_header = "{$_LANG["MODULE"]["MSG"][48]} {$replydata[0]["msg_header"]}";

   // create message body
   $msg_body   = "<br><br>"
                ."- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -<br>"
                ."{$_LANG["MODULE"]["MSG"][49]} {$replydata[0]["user_firstname"]} {$replydata[0]["user_lastname"]}<br>"
                ."{$_LANG["MODULE"]["MSG"][50]} ".displayDate($replydata[0]["msg_crtdat"])."<br>"
                ."- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -<br>"
                ."{$replydata[0]["msg_body"]}";

   // overwrite userfilter for hidden accounts
   $replyprivsql = " or id = {$replydata[0]["user_id"]} ";
}

//----------------------------------------------------------------------------------
// if a reply will be send for an appointment, get appointment data
//----------------------------------------------------------------------------------
if($_REQUEST["cal_id"] != "")
{
   // get groups of session user
   for($x = 0; $x < count($_SESSION["user_groups"]); $x++)
      $grpstr .= "{$_SESSION["user_groups"][$x]},";
   $grpstr = substr($grpstr, 0, -1);

   // check if user has access to this appointment through group privs
   $sql = " select count(*) 'cc'
            from calendar_shared_grp
            where
            cal_id = {$_REQUEST["cal_id"]} and
            group_id IN ({$grpstr})";
   $grpchk = $CON->select($sql);

   // check if user has access to this appointment through user privs
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

   // No privs => exit
   if(!(int)$grpchk[0]["cc"] && !(int)$usrchk[0]["cc"] && !(int)$publicchk[0]["cal_public"])
      die("");

   // select appointment data
   $sql = " select t1.*, t2.user_firstname, t2.user_lastname
            from calendar_appointments t1, user t2
            where
            t1.id = {$_REQUEST["cal_id"]} and
            t1.cal_crtusr = t2.id";
   $caldata = $CON->select($sql);

   // create message title
   $msg_header = "{$_LANG["MODULE"]["MSG"][51]} {$caldata[0]["cal_header"]}";

   // create message body
   $msg_body   = "<br><br>"
                ."- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -<br>"
                ."<b>{$_LANG["MODULE"]["MSG"][52]}</b> {$caldata[0]["cal_header"]}<br>"
                ."<b>{$_LANG["MODULE"]["MSG"][53]}</b> {$caldata[0]["user_firstname"]} {$caldata[0]["user_lastname"]}<br>"
                ."<b>{$_LANG["MODULE"]["MSG"][54]}</b> ".date('d.m.Y H:i', $caldata[0]["cal_startdate"])."<br>"
                ."<b>{$_LANG["MODULE"]["MSG"][55]}</b> ".date('d.m.Y H:i', $caldata[0]["cal_enddate"])."<br>"
                ."- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -<br>"
                ."{$caldata[0]["cal_body"]}";

   // hide buttons for user/group selection
   $buttonshide  = "display:none";

   // overwrite userfilter for hidden accounts
   $replyprivsql = " or id = {$caldata[0]["cal_crtusr"]} ";
}

//----------------------------------------------------------------------------------
// if a message will be send for an user from the contactlist, get appointment data
//----------------------------------------------------------------------------------
if($_REQUEST["con_id"] != "")
{
   // get userdata
   $sql = " select id, user_firstname, user_lastname, user_type
            from user
            where
            id = {$_REQUEST["con_id"]} ";
            
   if($_SESSION["user_type"] != 1)
      $sql .= " and user_visible = 1 "; 
   
   $condata = $CON->select($sql);

   // hide buttons for user/group selection
   $buttonshide  = "display:none";

   // overwrite userfilter for hidden accounts
   $replyprivsql = " or id = {$condata[0]["id"]} ";
}

//----------------------------------------------------------------------------------
// get available groups
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
// get available users
//----------------------------------------------------------------------------------
$sql = " select id, user_firstname, user_lastname, user_type
         from user
         where
         (
            user_status = 1 or
            (
               user_login  = 'xxxxxxxxxxxxxxxx' and
               user_pass   = 'xxxxxxxxxxxxxxxx' and
               user_status = -1
            )
         ) and ";

$sql .= " ( 1 = 1 {$replyprivsql}) and ";
   
$sql .= "id != {$_SESSION["user_id"]}
         order by user_firstname, user_lastname asc";
         
$users = $CON->select($sql);

$sql = " select user_mail_signature_html
         from user
         where
         id = {$_SESSION["user_id"]}";
$mailsig = $CON->select($sql);
$mailsig = $mailsig[0]["user_mail_signature_html"];
if(trim($mailsig) != "")
   $msg_body = "<br><br>".$mailsig."<br><br>".$msg_body;
//----------------------------------------------------------------------------------
// print formular
//----------------------------------------------------------------------------------
?>
<script type="text/javascript" src="./libs/jscripts/tinymce_3_2_2_3/jscripts/tiny_mce/tiny_mce.js"></script>
<script type="text/javascript">
   tinyMCE.init({
      mode : "textareas", theme : "advanced",
      plugins : "safari,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",
      theme_advanced_buttons1 : "bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,bullist,numlist,outdent,indent,blockquote,|,forecolor,backcolor,tablecontrols",
      theme_advanced_buttons2 : "", theme_advanced_buttons3 : "", theme_advanced_buttons4 : "",
      theme_advanced_toolbar_location : "top", theme_advanced_toolbar_align : "left", 
      content_css : "css/content.css", template_external_list_url : "lists/template_list.js", external_link_list_url : "lists/link_list.js", external_image_list_url : "lists/image_list.js", media_external_list_url : "lists/media_list.js",
      width: "690px", height: "300px", force_br_newlines: true, forced_root_block: ''
   });
</script>
<table border="0" cellpadding="0" cellspacing="0" width="822">
<form action="index.php" class="fokusfirst" method="post" name="xform_msg"
 onsubmit="return checkmsgform(new Array(this.msg_header, this.msg_body))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="send">
<input type="hidden" name="msg_parent" value="<?=$_REQUEST["msg_parent"]?>">
<tr>
   <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["MSG"][18]?></b></td>
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
   <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["MSG"][18]?></td>
</tr>
<tr>
   <td class="content_row" valign="top"><?=$_LANG["MODULE"]["MSG"][19]?></td>
   <td class="content_row">
      <?php
      if($_REQUEST["cal_id"] != "")
         echo "{$caldata[0]["user_firstname"]} {$caldata[0]["user_lastname"]}";
      if($_REQUEST["con_id"] != "")
         echo "{$condata[0]["user_firstname"]} {$condata[0]["user_lastname"]}";
      ?>
      <input type="button" class="button" value="&lt; <?=$_LANG["MODULE"]["MSG"][20]?> &gt;" style="width:160px;<?=$buttonshide?>"
      onclick="showObject(document.all.idx_usr_sel)"
      onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)">
   
      <input type="button" class="button" value="&lt; <?=$_LANG["MODULE"]["MSG"][21]?> &gt;" style="width:160px;<?=$buttonshide?>"
      onclick="showObject(document.all.idx_grp_sel)"
      onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)">
      &nbsp;
      
      <div id="idx_grp_sel" style="margin-left:10px; margin-top:10px; margin-bottom: 5px; display:none">
      <table cellpadding="0" cellspacing="0" border="0" width="98%">
      <colgroup>
         <col width="40">
         <col>
         <col width="380">
      </colgroup>
      <tr>
         <td><b class="content_message"><?=$_LANG["MODULE"]["MSG"][22]?></b></td>
         <td><b class="content_message"><?=$_LANG["MODULE"]["MSG"][23]?></b></td>
         <td><b class="content_message"><?=$_LANG["MODULE"]["MSG"][24]?></b></td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      // loop through groups
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($groups) && $groups != false; $x++)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_subrow">
               <input type="checkbox" name="grpids[]" value="<?=$groups[$x]["id"]?>">
            </td>
            <td class="content_subrow"><?=$groups[$x]["group_name"]?></td>
            <td class="content_subrow"><?=$groups[$x]["group_desc"]?>&nbsp;</td>
         </tr>
         <?php
      }
      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>">
            <td class="content_subrow" colspan="3">
               <br>
               <b class='msg_save_err'><?=$_LANG["MODULE"]["MSG"][37]?></b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
      </div>
      <div id="idx_usr_sel" style="margin-left:10px; margin-top:10px; margin-bottom: 5px; display:none">
      <table cellpadding="0" cellspacing="0" border="0" width="98%">
      <colgroup>
         <col width="40">
         <col>
         <col width="380">
      </colgroup>
      <tr>
         <td><b class="content_message"><?=$_LANG["MODULE"]["MSG"][22]?></b></td>
         <td><b class="content_message"><?=$_LANG["MODULE"]["MSG"][25]?></b></td>
         <td><b class="content_message"><?=$_LANG["MODULE"]["MSG"][26]?></b></td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      // loop through users
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($users) && $users != false; $x++)
      {
         if($users[$x]["user_type"] == "1")
            $disp_type = $_LANG["MODULE"]["MSG"][27];
         else
            $disp_type = $_LANG["MODULE"]["MSG"][28];
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_subrow">
               <input type="checkbox" name="usrids[]" value="<?=$users[$x]["id"]?>"
               <?php if($users[$x]["id"] == $replydata[0]["msg_crtusr"] ||
                     $users[$x]["id"] == $caldata[0]["cal_crtusr"] ||
                     $users[$x]["id"] == $condata[0]["id"])
                     echo "checked"?>>
            </td>
            <td class="content_subrow"><?=$users[$x]["user_firstname"]?> <?=$users[$x]["user_lastname"]?></td>
            <td class="content_subrow"><?=$disp_type?></td>
         </tr>
         <?php
      }
      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>">
            <td class="content_subrow" colspan="3">
               <br>
               <b class='msg_save_err'><?=$_LANG["MODULE"]["MSG"][38]?></b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
      </div>
   </td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["MSG"][29]?></td>
   <td class="content_row">
      <input type="text" class="text" style="width:690px" maxlength="254" name="msg_header" value="<?=stripslashes($msg_header)?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_row" valign="top"><?=$_LANG["MODULE"]["MSG"][30]?></td>
   <td class="content_row">
      <textarea class="text" style="width:690px; height:300px" name="msg_body"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($msg_body)?></textarea>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "822")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton("Enviar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_msg)", "disk-black");
      ?>
   </td>
</tr>
</form>
</table>
<?=Nifty_printF(false)?>
<br>