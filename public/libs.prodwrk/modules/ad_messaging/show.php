<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       22.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
// mark message as read
//----------------------------------------------------------------------------------
if($_REQUEST["filter"] != "trash")
{
   $sql = " update message_users
            set
            msg_status = 1
            where
            msg_id   = {$_REQUEST["msgid"]} and
            user_id  = {$_SESSION["user_id"]} and
            msg_type = {$_REQUEST["type"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
// get message data
//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.user_firstname, t2.user_lastname
         from message_data t1, user t2
         where
         t1.id = {$_REQUEST["msgid"]} and
         t1.msg_crtusr = t2.id";
$message = $CON->select($sql);
$message = $message[0]; 

//----------------------------------------------------------------------------------
// get rcpts of the message
//----------------------------------------------------------------------------------
$sql = " select t2.user_firstname, t2.user_lastname
         from message_users t1, user t2
         where
         t1.msg_id = {$message["id"]} and
         t1.msg_type = 0 and
         t1.user_id = t2.id
         order by t2.user_firstname, t2.user_lastname";
$msgusers = $CON->select($sql);

for($y = 0; $y < count($msgusers) && $msgusers != false; $y++)
   $msguserstr .= "{$msgusers[$y]["user_firstname"]} {$msgusers[$y]["user_lastname"]}, ";

$msguserstr = substr($msguserstr, 0, -2);

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
      width: "690px", height: "300px", force_br_newlines: true, forced_root_block: '', readonly: true
   });
</script>
<img src="/images/content/pixel.gif" height="10">
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td class="aula_content_module">
      <table border="0" cellpadding="0" cellspacing="0" width="822">
      <tr>
         <td>
            <table border="0" class="aula_listtbl" cellpadding="3" cellspacing="0" width="100%" >
            <colgroup>
               <col width="150">
               <col>
            </colgroup>
            <tr>
               <td class="content_row_osl" valign="top"><?=$_LANG["MODULE"]["MSG"][32]?></td>
               <td class="content_row_os"><?=$message["user_firstname"]?> <?=$message["user_lastname"]?></td>
            </tr>
            <?if(strlen($msguserstr) > 95)
            {  ?>
               <tr>
                  <td class="content_row_os" valign="top">
                     <table width="100%" cellpadding="0" cellspacing="0">
                     <tr>
                        <td class="content_row_clear" style="padding:0px"><?=$_LANG["MODULE"]["MSG"][33]?></td>
                        <td class="content_row_clear" style="padding:0px" align="right">
                           <img src="./images/menu/icons/eye.png" style="cursor:pointer" onclick="$('#alluser').toggle(0);$('#nouser').toggle(0);">
                        </td>
                     </tr>
                     </table>
                  </td>
                  <td class="content_row_os" id="alluser" style="display:none"><?=$msguserstr?></td>
                  <td class="content_row_os" id="nouser">Lista oculta</td>
               </tr>
            <?php
            }
            else
            {  ?>
               <tr>
                  <td class="content_row_osl" valign="top">
                     <table width="100%" cellpadding="0" cellspacing="0">
                     <tr>
                        <td class="content_row_clear" style="padding:0px"><?=$_LANG["MODULE"]["MSG"][33]?></td>
                     </tr>
                     </table>
                  </td>
                  <td class="content_row_os"><?=$msguserstr?></td>
               </tr>
               <?php
            }
            ?>
            <tr>
               <td class="content_row_osl"><?=$_LANG["MODULE"]["MSG"][34]?></td>
               <td class="content_row_os"><b><?=$message["msg_header"]?></b></td>
            </tr>
            <tr>
               <td class="content_row_osl" valign="top"><?=$_LANG["MODULE"]["MSG"][35]?></td>
               <td class="content_row_os">
                  <textarea id="area" class="text" style="width:690px; height:300px;"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$message["msg_body"]?></textarea>
               </td>
            </tr>
            <?php
            if((int)$message["msg_docid"])
            {
               $sql = " select t2.*
                        from menu_items t1, menu_docs t2
                        where
                        t1.menu_docid = t2.id and
                        t1.id = {$message["msg_docid"]}";
               $docdata = $CON->select($sql);
               if(count($docdata) && $docdata != false)
               {
                  $docdata = $docdata[0];
                  ?>
                  <tr bgcolor="<?=getRowColor(0)?>">
                     <td class="content_row"><?=$_LANG["MODULE"]["MSG"][57]?></td>
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
            <br>
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td align="left" width="130"></td>
               <td align="left" width="130">
                  <div class="btngrey" onclick="location.href = 'prodwrk.php?mid=999&filter=<?=$_REQUEST["filter"]?>';">
                     <i class="fa fa-fw fa-angle-left" style="color:white;"></i> Volver&nbsp;
                  </div>
               </td>
               <td>&nbsp;</td>
               <?php
               if($_REQUEST["filter"] != "trash")
               {  ?>
                  <td align="right" width="180" style="padding-right:5px">
                     <div class="btnred" onclick="location.href = 'prodwrk.php?mid=999&filter=<?=$_REQUEST["filter"]?>&msgexec=TRASH&msgid[]=<?=$message["id"]?>-<?=$_REQUEST["type"]?>';">
                     <i class="fa fa-fw fa-trash" style="color:white;"></i> <?=$_LANG["MODULE"]["MSG"][36]?>&nbsp;
                     </div>
                  </td>
                  <?php
               }
               if($message["msg_crtusr"] != $_SESSION["user_id"] && $_REQUEST["filter"] != "trash")
               {  ?>
                  <td align="right" width="130">
                     <div class="btngreen" onclick="location.href = 'prodwrk.php?mid=999&exec=new&msg_parent=<?=$_REQUEST["msgid"]?>';">
                        <i class="fa fa-fw fa-paper-plane" style="color:white;"></i> <?=$_LANG["FORM"]["BUTTON"][7]?>&nbsp;
                     </div>
                  </td>
                  <?php
               }  ?>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <br>
      <?php
      $_SESSION["JSEXEC"] .= ";top.document.getElementById('idx_main_menu').contentWindow.loadMainMsgData('{$_SESSION["user_id"]}');";
      ?>
   </td>
</tr>
</table>