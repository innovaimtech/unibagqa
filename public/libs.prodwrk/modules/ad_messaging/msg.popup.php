<?php
if((int)$_REQUEST["setmark"])
{
   $sql = " update message_users
            set
            msg_status = 1
            where
            msg_id   = {$_REQUEST["msgid"]} and
            user_id  = {$_SESSION["user_id"]}";
   $CON->no_result($sql);
   ?>
   <script language="JavaScript">
      $(document).ready(function()
      {
         <?php
         if((int)$_REQUEST["fromtens"])
         {  ?>
            parent.$.fancybox.close();
            <?php
         }
         else
         {  ?>
            parent.$.colorbox.close();
            <?php
         }
         ?>
      });
   </script>
   <?php
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
      width: "100%", height: "250px", force_br_newlines: true, forced_root_block: '', readonly: true
   });
</script>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td>
            <?=Nifty_printH("box1", "100%")?>
            <table border="0" class="aula_listtbl" cellpadding="3" cellspacing="0" width="100%" style="margin-top:0px">
            <colgroup>
               <col width="150">
               <col>
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["MSG"][31]?></td>
            </tr>
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
                  <textarea id="area" class="text" style="width:100%; height:200px;"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$message["msg_body"]?></textarea>
               </td>
            </tr>
            </table>
            <?=Nifty_printF()?>
            <br>
            <?=Nifty_printH("boxopt_b", "100%")?>
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td align="left" width="130">
                  <?php
                  if((int)$_REQUEST["fromtens"])
                     printButton2("Cerrar", "postnav", "javascript:void(0)", "parent.$.fancybox.close()", "fa-angle-left", 150);
                  else
                     printButton2("Cerrar", "postnav", "javascript:void(0)", "parent.top.frames['idx_main_menu'].resetTimer();parent.$.colorbox.close()", "fa-angle-left", 150);
                  ?>
               </td>
               <td>&nbsp;</td>
               <td align="right" width="130" style="padding-right:5px">
                  <?php
                  printButton2("Marcar leido", "postnav", "javascript:void(0)", "location.href='/iframe.fancy.php?module=messagepopup&msgid={$_REQUEST["msgid"]}&setmark=1&fromtens={$_REQUEST["fromtens"]}'", "fa-check", 150);
                  ?>
               </td>
               <?php
               if($message["msg_crtusr"] != $_SESSION["user_id"] && $_REQUEST["filter"] != "trash" && !(int)$_REQUEST["fromtens"])
               {  ?>
                  <td align="right" width="130">
                     <?php
                     printButton2($_LANG["FORM"]["BUTTON"][7], "postnav_green", "javascript:void(0)", "parent.top.frames['idx_main_menu'].resetTimer();parent.location.href='application.php?mid=99&exec=new&msg_parent={$_REQUEST["msgid"]}'", "fa-send", 150);
                     ?>
                  </td>
                  <?php
               }  ?>
            </tr>
            </table>
            <?=Nifty_printF(false)?>
         </td>
      </tr>
      </table>
      <br>
   </td>
</tr>
</table>