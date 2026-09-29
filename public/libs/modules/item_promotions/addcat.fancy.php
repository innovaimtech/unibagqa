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

if($_REQUEST["exec"] == "save")
{
   $sql = " select pos_id
            from item_promotions_items
            where
            prom_id = {$_REQUEST["id"]}
            order by pos_id desc
            LIMIT 0,1";
   $pos_id = $CON->select($sql);
   if(count($pos_id) && $pos_id != false)
      $pos_id = $pos_id[0]["pos_id"]+1;
   else
      $pos_id = 0;
   
   $sql = " select t1.id
            from item t1
            INNER JOIN item_productcats t2 ON t1.id = t2.item_id
            where
            t1.item_status    > 0 and
            t1.item_sellable  = 1 and
            t2.cat_id         = {$_REQUEST["sql_pcat"]}";
   $items = $CON->select($sql);
   foreach($items AS $item)
   {
      $sql = " insert into item_promotions_items
               (prom_id, item_id, item_type, pos_id)
               VALUES
               ({$_REQUEST["id"]}, {$item["id"]}, 'item', {$pos_id})";
      $res = $CON->no_result($sql);
      if($res)
         $pos_id++;
   }
   ?>
   <script language="Javascript">
      parent.location.href='/index.php?mid=976&exec=edit&id=<?=$_REQUEST["id"]?>';
   </script>
   <?php
}

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));
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
<body class="page_content" onload="document.all.xuser_login.focus()">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
<tr>
   <td align="center" valign="top" height="100%">
      <form action="addcat.fancy.php" method="post" class="fokusfirst" name="xform_log" autocomplete="off">
      <input type="hidden" name="exec" value="save">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <br>
      <?=Nifty_printH("box1", "350")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_header" colspan="2">Seleccionar Familia</td>
      </tr>
      <tr>
         <td class="content_row">
            <select class="text" name="sql_pcat" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <?php
               foreach($pcats AS $pcat)
               {  ?>
                  <option value="<?=$pcat["id"]?>"
                  <?php if($pcat["id"] == $_SESSION[$_sesmodulename]["sql_pcat"]) echo "selected"?>><?=sprintf("%03s", $pcat["id"])?> - <?=$pcat["cat_title"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
      <br>
      <?=Nifty_printH("boxopt_b", "350")?>
      <tr>
         <td class="content_row_clear" align="right">
            <?php
            printButton("Ejecutar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_log)", "tick-circle-frame", 200);
            ?>
         </td>
      </tr>
      <?=Nifty_printF(false)?>
      </form>
   </td>
</tr>
</table>
</body>
</html>