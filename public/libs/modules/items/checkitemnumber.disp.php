<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

if((int)$_REQUEST["xcat"])
{
   $sql_catid = sprintf("%03s", $_REQUEST["xcat"]);

   $sql = " select item_number_prod
            from item
            where
            item_number_prod like '{$sql_catid}%' and
            item_status > 0";
   $items = $CON->select($sql);
   foreach($items AS $item)
   {
      $thisid  = substr($item["item_number_prod"], 3);
      $_IDB[$thisid] = 1;
   }

   $sql = " select MAX(item_number_prod) 'item_number_prod'
            from item
            where
            item_number_prod like '{$sql_catid}%'
            order by id";
   $itemmax = $CON->select($sql);
   $itemmax = (int)substr($itemmax[0]["item_number_prod"], 3);

   for($x = 1; $x <= $itemmax; $x++)
   {
      $thisid = sprintf("%04s", $x);
      if(!(int)$_IDB[$thisid])
         $_ITEMIDS[$thisid] = 1;
   }
   $maxid = (int)$itemmax +1;
   $thisid = sprintf("%04s", $maxid);
   $_ITEMIDS[$thisid] = 1;
}
?>
<html>
<head>
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <script language="Javascript">
      <?php
      require_once("../../../libs/jscripts/sourcen.php");
      ?>
   </script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="<?php if(count($items) == 0 || $items == false) echo "document.xform_itemsearch.sql_stext.focus()"?>">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<div style="display:none"><?=md5(microtime())?></div>
<?=Nifty_printH("box1", "430")?>
<table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col>
   <col width="120">
</colgroup>
<tr>
   <td class="content_tbl_subheader">Número</td>
   <td class="content_tbl_subheader" align="center">Opciones</td>
</tr>
<?php
$x = 0;
foreach(array_keys($_ITEMIDS) AS $itemnumber)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row"><?=sprintf("%04s",$itemnumber)?></td>
      <td class="content_row" align="center">
         <?php
         printButton("Aplicar", "postnav_save", "javascript: deactivateFormChange()", "parent.document.getElementById('item_number_prod2').value='".sprintf("%04s",$itemnumber)."';parent.$.fancybox.close();", "pencil");
         ?>
      </td>
   </tr>
   <?php
   $x++;
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row" colspan="2" align="center"><br><b class="msg_save_err">No hay datos disponibles.</b><br><br></td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
