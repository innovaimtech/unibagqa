<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       22.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
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
<table border="0" cellpadding="0" cellspacing="0" width="822">
<tr>
   <td height="30"><b class="content_header"><?=$_LANG["MODULE"]["MSG"][30]?></b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>

<?=Nifty_printH("box1", "822")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<tr>
   <?php
   if((int)$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["msgid"]]["L"] && $_REQUEST["from"] != "report")
   {?>
      <td>
         <?php
            printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=show&msgid={$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["msgid"]]["L"]}", "", "arrow-180", 53);
         ?>
      </td>
    <?php
   }
   ?>
   <?php
    if((int)$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["msgid"]]["N"] && $_REQUEST["from"] != "report")
    {?>

      <td align="right">
        <?php
         printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=show&msgid={$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["msgid"]]["N"]}", "", "arrow", 53);
         ?>
     </td>
    <?php
    }
    ?>
</tr>  
</table>
<?=Nifty_printF()?>   


<?=Nifty_printH("box1", "822", 0)?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="150">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2"><?=$_LANG["MODULE"]["MSG"][31]." (Id:".$_REQUEST["msgid"].")"?></td>
</tr>
<tr>
   <td class="content_row" valign="top"><?=$_LANG["MODULE"]["MSG"][32]?></td>
   <td class="content_row"><?=$message["user_firstname"]?> <?=$message["user_lastname"]?></td>
</tr>
<tr>
   <td class="content_row" valign="top"><?=$_LANG["MODULE"]["MSG"][33]?></td>
   <td class="content_row"><?=$msguserstr?></td>
</tr>
<tr>
   <td class="content_row"><?=$_LANG["MODULE"]["MSG"][34]?></td>
   <td class="content_row"><b><?=$message["msg_header"]?></b></td>
</tr>
<tr>
   <td class="content_row" valign="top"><?=$_LANG["MODULE"]["MSG"][35]?></td>
   <td class="content_row">
      <textarea class="text" style="width:690px; height:300px"
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
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "822")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td align="left" width="130">
      <ul class="postnav">
         <a href="index.php?mid=<?=$_REQUEST["mid"]?>&filter=<?=$_REQUEST["filter"]?>"><?=$_LANG["FORM"]["BUTTON"][1]?></a>
      </ul>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["filter"] != "trash")
   {  ?>
      <td align="right" width="200" style="padding-right:5px">
         <ul class="postnav_del">
            <a href="javascript: deactivateFormChange()"
            onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&filter=<?=$_REQUEST["filter"]?>&msgexec=TRASH&msgid[]=<?=$message["id"]?>-<?=$_REQUEST["type"]?>')"><?=$_LANG["MODULE"]["MSG"][36]?></a>
         </ul>
      </td>
      <?php
   }
   if($message["msg_crtusr"] != $_SESSION["user_id"] && $_REQUEST["filter"] != "trash")
   {  ?>
      <td align="right" width="130">
         <ul class="postnav_save">
            <a href="index.php?mid=796&msg_parent=<?=$_REQUEST["msgid"]?>"><?=$_LANG["FORM"]["BUTTON"][7]?></a>
         </ul>
      </td>
      <?php
   }  ?>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
$_SESSION["JSEXEC"] .= ";parent.loadMainMsgData();";
?>