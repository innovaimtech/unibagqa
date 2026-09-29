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

session_start();

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");

//----------------------------------------------------------------------------------
// force browser to reload page
//----------------------------------------------------------------------------------
header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');
?>
<html>
<head>
   <title><?=$_LANG["MODULE"]["STRUCT"][33]?></title>
   <script language="Javascript">
      <?php
      //----------------------------------------------------------------------------------
      // print the javascript functions
      //----------------------------------------------------------------------------------
      require_once("../../../libs/jscripts/sourcen.php");
      ?>
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
<body class="page_content">
<center>
<table border="0" cellpadding="1" cellspacing="0" class="content_table" width="100%">
<tr>
   <td class="content_tbl_header" colspan="14" height="20" align="center"><?=$_LANG["MODULE"]["STRUCT"][34]?></td>
</tr>
<tr>
<?php
$imgfiles = Array();
if ($handle = opendir('../../../images/menu/icons/'))
{
   while (false !== ($file = readdir($handle)))
      if ($file != "." && $file != "..")
         array_push($imgfiles, $file);
   closedir($handle);
}
sort($imgfiles);

$imgcounter = 0;
foreach($imgfiles AS $imgfile)
{  ?>
   <td class="content_row" width="30" height="25" align="center" style="cursor:pointer"
   onmouseover="mark(this,0)" onmouseout="mark(this,1)"
   onclick="parent.opener.document.all.idx_img_icon.src = './images/menu/icons/<?=$imgfile?>';
            parent.opener.document.all.menu_icon.value='<?=$imgfile?>';
            window.close()">
      <img alt="<?=$imgfile?>" border="0" src="../../../images/menu/icons/<?=$imgfile?>">
   </td>
   <?php
   $imgcounter++;
   if($imgcounter % 14 == 0)
   {  ?>
      </tr>
      <tr>
      <?php
   }
}
?>
</tr>
</table>
</center>
</body>
</html>