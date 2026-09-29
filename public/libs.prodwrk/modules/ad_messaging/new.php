<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       22.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$_REQUEST["MSGNEW_LIMIT"] = 0;

if((int)$_REQUEST["msg_parent"])
{
   $sql = " update message_users
            set
            msg_status = 1
            where
            msg_id   = {$_REQUEST["msg_parent"]} and
            user_id  = {$_SESSION["user_id"]}";
   $CON->no_result($sql);
}

if($_REQUEST["subexec"] == "send")
{
   // get current time
   $currtme = time();

   // format parameters
   $_REQUEST["msg_parent"]    = (int)$_REQUEST["msg_parent"];
   $_REQUEST["msg_header"]    = trim(addslashes($_REQUEST["msg_header"]));
   $_REQUEST["msg_body"]      = trim(addslashes($_REQUEST["msg_body"]));
   $_REQUEST["msg_docid"]     = (int)$_REQUEST["msg_docid"];
   $_REQUEST["msg_popupact"]  = (int)$_REQUEST["msg_popupact"];

   // insert message
   $sql = " insert into message_data
            (msg_header, msg_body, msg_parent, msg_docid, msg_crtusr, msg_crtdat, msg_popupact)
            VALUES
            ('{$_REQUEST["msg_header"]}', '{$_REQUEST["msg_body"]}',
             {$_REQUEST["msg_parent"]}, {$_REQUEST["msg_docid"]},
             {$_SESSION["user_id"]}, {$currtme}, {$_REQUEST["msg_popupact"]})"; 
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
      $savemsg = getSaveMessage(true);
      
      
      if($res)
      {
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
         }
      }
   }
   ?>
   <script language="JavaScript">
      alert("Mensaje enviado exitosamente.");
   </script>
   <?php
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
         user_status = 1 and ";

$sql .= " ( 1 = 1 {$replyprivsql}) ";
   
$sql .= " order by user_firstname, user_lastname asc";
         
$users = $CON->select($sql);

$sql = " select user_mail_signature_html
         from user
         where
         id = {$_SESSION["user_id"]}";
$mailsig = $CON->select($sql);
$mailsig = $mailsig[0]["user_mail_signature_html"];
if(trim($mailsig) != "" && $msg_body != "")
   $msg_body = "<br><br>".$mailsig."<br><br>".$msg_body;
elseif(trim($mailsig) != "")
   $msg_body = "<br><br>".$mailsig;

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

   function checkAll()
   {
      var chk = document.getElementById('all_usr').checked;
      <?php
      for($x = 0; $x < count($users) && $users != false; $x++)
      {  ?>
         document.getElementById('usr_<?=$users[$x]["id"]?>').checked = chk;
         <?php
      }
      ?>
   }
</script>
<font style="font-size:20px"><b>Nuevo mensaje</b></font>
<div style="clear:both;height:10px"></div>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;padding:10px;color:#666666;font-size:14px;border-radius:5px;padding:20px">
      <i class="fa fa-fw fa-laptop-code" style="font-size:14px;"></i> Mensajes
      <i class="fa fa-fw fa-chevron-right" style="font-size:14px;"></i>
      Nuevo mensaje
   </td>
</tr>
</table>
<link href="/libs/jscripts/chosen_v1.3.0/chosen.css?ruid=<?=md5(microtime())?>" rel="stylesheet"/>
<script src="/libs/jscripts/chosen_v1.3.0/chosen.jquery.js"></script>
<img src="/images/content/pixel.gif" height="10">
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td class="aula_content_module">
      <table border="0" cellpadding="0" cellspacing="0" width="822" style="">
      <tr>
         <td>
         <form action="prodwrk.php" class="fokusfirst" method="post" name="xform_msg"
          onsubmit="return checkmsgform(new Array(this.msg_header, this.msg_body))">
         <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
         <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
         <input type="hidden" name="subexec" value="send">
         <input type="hidden" name="msg_parent" value="<?=$_REQUEST["msg_parent"]?>">
         <?=Nifty_printH("box1", "822")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%" class="aula_listtbl">
         <colgroup>
            <col width="150">
            <col>
         </colgroup>
         <tr>
            <td class="content_row_osl" valign="top"><?=$_LANG["MODULE"]["MSG"][19]?></td>
            <td class="content_row_os">
               <?php
               if($_REQUEST["cal_id"] != "")
                  echo "{$caldata[0]["user_firstname"]} {$caldata[0]["user_lastname"]}";
               if($_REQUEST["con_id"] != "")
                  echo "{$condata[0]["user_firstname"]} {$condata[0]["user_lastname"]}";
      
               ?>
               <div id="idx_grp_sel" style="margin-left:10px; margin-top:10px; margin-bottom: 5px; display:none">
               </div>
               <?php
               if((int)$_REQUEST["MSGNEW_LIMIT"])
               {  ?>
                  <div id="idx_usr_sel">
                     <select class="text chosen-select" style="width:620px;font-size:12px;font-family:Arial;" name="usrids[]" id="xuserids"
                     data-placeholder="&lt; Seleccione Usuario &gt;" multiple>
                        <option value=""></option>
                        <?php
                        for($x = 0; $x < count($users) && $users != false; $x++)
                        {  ?>
                           <option value="<?=$users[$x]["id"]?>"><?=$users[$x]["user_firstname"]?> <?=$users[$x]["user_lastname"]?></option>
                           <?php
                        }
                        $_SESSION["JSEXEC"] .= ";$('#xuserids').chosen({allow_single_deselect: true});";
                        ?>
                     </select>
                     <input type="checkbox" onclick="$('#xuserids option').prop('selected', this.checked);$('#xuserids').trigger('chosen:updated');">Todos
                  </div>
                  <?php
               }
               else  
               {  ?>
                  <div id="idx_usr_sel" style="margin-left:0px; margin-top:0px; margin-bottom: 5px; display:xnone">
                  <table cellpadding="0" cellspacing="0" border="0" width="98%">
                  <colgroup>
                     <col width="40">
                     <col>
                     <col width="150">
                  </colgroup>
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
      
                     $sql = " select count(t1.id) 'cc'
                              from user t1
                              INNER JOIN user_group t2 ON t1.id = t2.user_id
                              where
                              t1.id                = '{$users[$x]["id"]}' and
                              t2.group_id          = 5";
                     $tdata = $CON->select($sql);
                     $tdata = $tdata[0]; 
      
                     $ismani = false;
                     if((int)$tdata["cc"])
                        $ismani = true;
                     ?>
                     <tr bgcolor="" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                        <td class="content_row_clear">
                           <input type="checkbox" name="usrids[]" id="usr_<?=$users[$x]["id"]?>" value="<?=$users[$x]["id"]?>"
                           <?php if($users[$x]["id"] == $replydata[0]["msg_crtusr"] ||
                                 $users[$x]["id"] == $caldata[0]["cal_crtusr"] ||
                                 $users[$x]["id"] == $condata[0]["id"])
                                 echo "checked"?>>
                        </td>
                        <td class="content_row_clear"><?=$users[$x]["user_firstname"]?> <?=$users[$x]["user_lastname"]?></td>
                        <td class="content_row_clear"><?=$disp_type?></td>
                     </tr>
                     <?php
                  }
                  if(!$x)
                  {  ?>
                     <tr bgcolor="">
                        <td class="content_row_clear" colspan="3">
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
                  <?php
               }
               ?>
            </td>
         </tr>
         <tr>
            <td class="content_row_osl"><?=$_LANG["MODULE"]["MSG"][29]?></td>
            <td class="content_row_os">
               <input type="text" class="inptxt" style="width:690px" maxlength="254" name="msg_header" value="<?=stripslashes($msg_header)?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr>
            <td class="content_row_osl" valign="top"><?=$_LANG["MODULE"]["MSG"][30]?></td>
            <td class="content_row_os">
               <textarea class="inptxt" style="width:690px; height:300px" name="msg_body"
               onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($msg_body)?></textarea>
            </td>
         </tr>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?=Nifty_printH("boxopt_b", "822")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <tr>
            <td>&nbsp;</td>
            <td align="right" width="130">
               <div class="btngreen" onclick="submitForm(document.xform_msg)">
                  <i class="fa fa-fw fa-paper-plane" style="color:white;"></i> Enviar&nbsp;
               </div>
            </td>
         </tr>
         </form>
         </table>
         <?=Nifty_printF(false)?>
         <br>
         </td>
      </tr>
      </table>
   </td>
</tr>
</table>