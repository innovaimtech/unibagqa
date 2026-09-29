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

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "save")
{

   $sql = " delete from item_productcats
            where
            item_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
   $sql = " insert into item_productcats
            (item_id, cat_id) VALUES ({$_REQUEST["id"]}, {$_REQUEST["xcat"]})";
   $CON->no_result($sql);

   $catnum = sprintf("%03s", $_REQUEST["xcat"]);
   $inum   = $catnum.$_REQUEST["xnum"];
   $sql = " update item
            set
            item_number_prod = '{$inum}'
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $itemlists = getItemListsForItem($CON, $_REQUEST["id"]);
   foreach($itemlists AS $itemlist)
   {
      $sql = " delete from item_productcats_itemlist
               where
               item_id = {$itemlist["id"]}";
      $CON->no_result($sql);
      $sql = " insert into item_productcats_itemlist
               (item_id, cat_id) VALUES ({$itemlist["id"]}, {$_REQUEST["xcat"]})";
      $CON->no_result($sql);

      $sql = " update itemlist
               set
               item_number_prod = '{$inum}'
               where
               id = {$itemlist["id"]}";
      $CON->no_result($sql);
   }
   ?>
   <script language="JavaScript">
      parent.location.href='/index.php?mid=638&exec=edit&id=<?=$_REQUEST["id"]?>&reExecSave=1';
   </script>
   <?php
   exit;
}

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from item t1
         where
         t1.id = {$_REQUEST["id"]} ";
$item = $CON->select($sql);
$item = $item[0];

//----------------------------------------------------------------------------------
$sql = " select t2.id
         from item_productcats t1, productcats t2
         where
         t1.item_id     = {$_REQUEST["id"]} and
         t1.cat_id      = t2.id and
         t2.cat_status  = 1";
$selitemcatid = $CON->select($sql);
$selitemcatidstr = $selitemcatid[0]["id"];

//----------------------------------------------------------------------------------
$sql = " select *
         from productcats
         where
         cat_status = 1
         order by id";
$cats = $CON->select($sql);

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
<?php
if($_REQUEST["xcat"] == "")
{  ?>
   <?=Nifty_printH("box1", "430")?>
   <table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="50">
      <col>
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="3">Elige Familia</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Número</td>
      <td class="content_tbl_subheader">Familia</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   $x = 0;
   foreach($cats AS $cat)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$cat["cat_prefix"]?><?=sprintf("%03s",$cat["id"])?></td>
         <td class="content_row"><?=$cat["cat_title"]?></td>
         <td class="content_row" align="center">
            <?php
            printButton("Siguiente", "postnav", "javascript: deactivateFormChange()", "location.href='changeitemnumber.disp.php?id={$_REQUEST["id"]}&xcat={$cat["id"]}'", "pencil");
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
   <?php
}
else
{
   $sql = " select *
            from productcats
            where
            id = {$_REQUEST["xcat"]}";
   $catdata = $CON->select($sql);
   $catdata = $catdata[0];
   ?>
   <form action="changeitemnumber.disp.php" method="post" name="xform_chgitemnum">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="xcat" value="<?=$_REQUEST["xcat"]?>">
   <input type="hidden" name="xnum" value="">
   <input type="hidden" name="exec" value="save">
   <?=Nifty_printH("box1", "430")?>
   <table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="3">Elige Número</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Número</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   $x = 0;
   foreach(array_keys($_ITEMIDS) AS $itemnumber)
   {
      $newnum = sprintf("%04s",$itemnumber);
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$catdata["cat_prefix"]?><?=$newnum?></td>
         <td class="content_row" align="center">
            <?php
            printButton("Aplicar", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.xform_chgitemnum.xnum.value='{$newnum}';submitForm(document.xform_chgitemnum); }", "pencil");
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
   <?php
}
