<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       26.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

// include libraries
require_once("../../../libs/classes/menu.php");
require_once("../../../libs/classes/page.php");

// start session
session_start();

// include libraries
require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");

//----------------------------------------------------------------------------------
error_reporting(0);

//----------------------------------------------------------------------------------
// force browser to reload page
//----------------------------------------------------------------------------------
header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

//----------------------------------------------------------------------------------
// Show page
//----------------------------------------------------------------------------------
?>
<html>
<head>
   <title><?=$_LANG["MODULE"]["STRUCT"][36]?></title>
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
<center>
<?=Nifty_printH("box1", "100%")?>
<table border="0" cellpadding="3" cellspacing="0" class="content_table" width="100%">
<colgroup>
   <col>
   <col width="125">
   <col width="105">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3" align="center"><?=$_LANG["MODULE"]["STRUCT"][37]?></td>
</tr>
<tr>
   <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STRUCT"][38]?></td>
   <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STRUCT"][39]?></td>
   <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["STRUCT"][40]?></td>
</tr>
<?php

//----------------------------------------------------------------------------------
// set defaults
//----------------------------------------------------------------------------------
$moddirs    = Array();
$modfiles   = Array();
$basepath   = "../../../libs/modules/";

//----------------------------------------------------------------------------------
// read module directories
//----------------------------------------------------------------------------------
if ($handle = opendir($basepath))
{
   while (false !== ($file = readdir($handle)))
      if ($file != "." && $file != "..")
         array_push($moddirs, $file);
   closedir($handle);
}

//----------------------------------------------------------------------------------
// loop through module directories
//----------------------------------------------------------------------------------
foreach($moddirs AS $moddir)
{
   // read modules
   if ($handle = opendir("{$basepath}{$moddir}"))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != "..")
            array_push($modfiles, "{$moddir}/{$file}");
      closedir($handle);
   }
}

// sort modules
sort($modfiles);

//----------------------------------------------------------------------------------
// show modules
//----------------------------------------------------------------------------------
$modcount = 0;
foreach($modfiles AS $modfile)
{  ?>
   <tr bgcolor="<?=getRowColor($modcount)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row"><?=$modfile?></td>
      <td class="content_row"><?=displaySize(filesize("{$basepath}{$modfile}"))?></td>
      <td class="content_row">
         <ul class="postnav">
            <a href="javascript: parent.opener.document.all.menu_link_mod.value='<?=$modfile?>'; window.close()"><?=$_LANG["FORM"]["BUTTON"][8]?></a>
         </ul>
      </td>
   </tr>
   <?php
   $modcount++;
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
</body>
</html>