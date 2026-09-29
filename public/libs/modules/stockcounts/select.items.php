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

$pcats = formatFullProductCats(getFullProductCats($CON, 0));
$_REQUEST["sql_text"] = trim(addslashes(str_replace("*","%",$_REQUEST["sql_text"])));

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql = " select t1.id, t1.item_number_prod, t1.item_title, t1.item_number, t4.unit_name
            from item t1
            INNER JOIN item_shops t2            ON t1.id = t2.item_id
            LEFT OUTER JOIN item_productcats t3 ON t1.id = t3.item_id
            LEFT OUTER JOIN item_units t4       ON t1.item_unit = t4.id
            where
            t2.shop_id        = {$_REQUEST["shopid"]} and
            t1.item_status    = 1 and
            t1.item_released  = 1 ";

   if($_REQUEST["sql_text"] != "")
   {
      $sql .= " and (t1.item_title        like '%{$_REQUEST["sql_text"]}%' or
                     t1.item_number_prod  like '%{$_REQUEST["sql_text"]}%' or
                     t1.item_number       like '%{$_REQUEST["sql_text"]}%') ";
   }
   if($_REQUEST["sql_pcat"] != 0)
      $sql .= " and t3.cat_id = {$_REQUEST["sql_pcat"]}";

   $sql.= " order by t1.id asc";
   $data = $CON->select($sql);
}
?>
<html>
<head>
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <script type="text/javascript" src="../../../libs/jscripts/jquery-1.4.2.js"></script>
   <script language="JavaScript">
      <?php
      require_once("../../../libs/jscripts/sourcen.php");
      ?>
      function setStockcountItemid(selitemid)
      {
         var probj   = parent.document.getElementById('stc_selitem');
         probj.value = selitemid;
         parent.jQuery.fancybox.close();
      }
   </script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content">
<form action="select.items.php" method="post" name="xform_itemsearch">
<input type="hidden" name="subexec" value="search">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="shopid" value="<?=$_REQUEST["shopid"]?>">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<?=Nifty_printH("box2", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="100">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
</tr>
<tr>
   <td class="content_rowl">Palabra</td>
   <td class="content_row">
      <input name="sql_text" type="text" class="text" style="width:375px"
      value="<?=str_replace("%","*",$_REQUEST["sql_text"])?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Familia</td>
   <td class="content_row">
      <select class="text" name="sql_pcat" style="width:375px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($pcats AS $pcat)
         {  ?>
            <option value="<?=$pcat["id"]?>"
            <?php if($pcat["id"] == $_REQUEST["sql_pcat"]) echo "selected"?>><?=sprintf("%03s", $pcat["id"])?> - <?=$pcat["cat_title"]?>
            </option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_row" align="right" colspan="4">
      <table border="0" cellpadding="0" cellspacing="0" width="270">
      <tr>
         <td align="right">
            <?php
            printButton("Buscar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
            $_SESSION["_SUBMITBTN"] = 1;
            ?>
         </td>
      </tr>
      </table>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="30">
   <col width="70">
   <col>
   <col width="90">
   <col width="90">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="5">Recuento del inventario: Selección del artículo</td>
</tr>
<tr>
   <td class="content_tbl_subheader">&nbsp;</td>
   <td class="content_tbl_subheader">Número</td>
   <td class="content_tbl_subheader">Artículo</td>
   <td class="content_tbl_subheader">Unidad</td>
   <td class="content_tbl_subheader" align="center">Opciones</td>
</tr>
<?php
for($x = 0; $x < count($data) && $data != false; $x++)
{
   $row = $data[$x];
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row">
         <input type="radio" class="radio" id="stc_selitemid_<?=$row["id"]?>" name="stc_selitemid" value="<?=$row["id"]?>"
         onclick="setStockcountItemid('<?=$row["id"]?>');">
      </td>
      <td class="content_row"><?=$row["item_number_prod"]?></td>
      <td class="content_row"><?=$row["item_title"]?></td>
      <td class="content_row"><?=$row["unit_name"]?></td>
      <td class="content_row" align="center">
         <?php
         printButton("Seleccione", "postnav_save", "javascript: void(0)", "setStockcountItemid('{$row["id"]}');", "plus");
         ?>
      </td>
   </tr>
   <?php
}
if($_REQUEST["subexec"] == "")
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="5" align="center">
         <br>
         <b class="msg_save_ok">Búsqueda no ejecutada</b>
         <br><br>
      </td>
   </tr>
   <?php
}
elseif(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="5" align="center">
         <br>
         <b class="msg_save_err">No hay datos disponibles.</b>
         <br><br>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br><br>
</body>
</html>