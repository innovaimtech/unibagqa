<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       26.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

require_once("../../../libs/classes/menu.php");
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");

session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

error_reporting(0);

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");

//----------------------------------------------------------------------------------
// force browser to reload page
//----------------------------------------------------------------------------------
header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

if($_SESSION["_MENU"]->getModuleName(21) == "")
   die("Zugriff verweigert.");

if($_REQUEST["exec"] == "save")
{
   $_REQUEST["user_mail_signature_html"] = trim(addslashes($_REQUEST["user_mail_signature_html"]));

   $sql = " update user
            set
            user_mail_signature_html = '{$_REQUEST["user_mail_signature_html"]}'
            where
            id = {$_REQUEST["uid"]}";
   $res = $CON->no_result($sql);
   if($res)
   {  ?>
      <script language="JavaScript">
         window.close();
      </script>
      <?php
   }
}

$sql = " select user_mail_signature_html
         from user
         where
         id = {$_REQUEST["uid"]}";
$data = $CON->select($sql);
$data = $data[0];

//----------------------------------------------------------------------------------
// Show page
//----------------------------------------------------------------------------------
?>
<html>
<head>
   <title><?=$_LANG["MODULE"]["USER"][50]?></title>
   <script type="text/javascript" src="../../../libs/jscripts/NiftyCube/niftycube.js"></script>
   <link href="../../../libs/jscripts/NiftyCube/niftyCorners.css" rel="stylesheet" type="text/css" />
   <script language="Javascript">
      <?php
      //----------------------------------------------------------------------------------
      // print the javascript functions
      //----------------------------------------------------------------------------------
      require_once("../../../libs/jscripts/sourcen.php");
      ?>
   </script>
   <script type="text/javascript" src="../../../libs/jscripts/tinymce_3_2_2_3/jscripts/tiny_mce/tiny_mce.js"></script>
   <script type="text/javascript">
      tinyMCE.init({
         mode : "textareas", theme : "advanced",
         plugins : "safari,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",
         theme_advanced_buttons1 : "bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,bullist,numlist,outdent,indent,|,forecolor,backcolor,fontselect,fontsizeselect",
         theme_advanced_buttons2 : "", theme_advanced_buttons3 : "", theme_advanced_buttons4 : "",
         theme_advanced_toolbar_location : "top", theme_advanced_toolbar_align : "left",
         content_css : "css/content.css", template_external_list_url : "lists/template_list.js", external_link_list_url : "lists/link_list.js", external_image_list_url : "lists/image_list.js", media_external_list_url : "lists/media_list.js",
         width: "570px", height: "300px", paste_remove_styles: true, paste_auto_cleanup_on_paste : true, force_br_newlines: true, forced_root_block: ''
      });
   </script>
   <style type="text/css">
      <?php
      //----------------------------------------------------------------------------------
      // print the style of the page
      //----------------------------------------------------------------------------------
      $_SESSION["_PAGE"]->printStyle();
      ?>
   </style>
</head>
<body class="page" style="margin:5px" onload="<?=Nifty_printJS("ALL")?>">
<script language="JavaScript">
   showLoading();
</script>
<div id="idx_loadinghide" style="display:none">
<center>
<form action="signature.php" method="post" class="fokusfirst" name="xform_sig">
<input type="hidden" name="exec" value="save">
<input type="hidden" name="uid" value="<?=$_REQUEST["uid"]?>">
<?=Nifty_printH("box1", "100%")?>
<table border="0" cellpadding="3" cellspacing="0" class="content_table" width="100%">
<colgroup>
   <col>
   <col width="125">
   <col width="105">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3" align="center"><?=$_LANG["MODULE"]["USER"][50]?></td>
</tr>
<tr>
   <td>
      <textarea class="text" style="width:570px; height:300px" name="user_mail_signature_html"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($data["user_mail_signature_html"])?></textarea>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "tinyMCE.triggerSave(); document.xform_sig.submit()", "disk-black");
      ?>
   </td>
</tr>
</form>
</table>
</div>
<script language="Javascript">
   function execAfterLoad()
   {  <?php
         echo $_SESSION["JSEXEC"];
         $_SESSION["JSEXEC"] = "";
      ?>
   }
</script>
</body>
</html>