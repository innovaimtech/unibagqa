<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../../libs/classes/page.php");
require_once("../../../../libs/classes/mysql.php");
require_once("../../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../../libs/functions.php");

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

if($_REQUEST["type"] == "NOTE")
{
   $title = "Notas/C: Mes ".sprintf("%02s", $_REQUEST["month"])."-".$_REQUEST["year"];
   $items = $_SESSION["_REBATES"]["DOCS"][$_REQUEST["year"]][$_REQUEST["month"]]["NOTE"];
}
elseif($_REQUEST["type"] == "NOTE_TOTAL")
{
   $title = "Total Notas/C";
   $items = $_SESSION["_REBATES"]["TOTAL"]["NOTE"];
}
elseif($_REQUEST["type"] == "INVC")
{
   $title = "Facturas: Mes ".sprintf("%02s", $_REQUEST["month"])."-".$_REQUEST["year"];
   $items = $_SESSION["_REBATES"]["DOCS"][$_REQUEST["year"]][$_REQUEST["month"]]["INVC"];
}
elseif($_REQUEST["type"] == "INVC_TOTAL")
{
   $title = "Total Facturas";
   $items = $_SESSION["_REBATES"]["TOTAL"]["INVC"];
}

//----------------------------------------------------------------------------------
?>
<html>
<head>
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <script language="Javascript">
      <?php
      require_once("../../../../libs/jscripts/sourcen.php");
      ?>
   </script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <script type="text/javascript" src="/libs/jscripts/jquery.table_navigation.js"></script>
   <style type="text/css">
   tr.selected {background-color: <?=$_SESSION["_PAGE"]->getEffectVal("js_content_hover")?>;}
   </style>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="<?php if(count($items) == 0 || $items == false) echo "document.xform_itemsearch.sql_stext.focus()"?>">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="200">
   <col>
   <col width="200">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">
      <table width="100%" cellpadding="0" cellspacing="0">
      <tr>
         <td class="content_tbl_header" style="padding:0px"><?=$title?></td>
         <td style="padding:0px" align="right" width="40">
            <img src="/images/menu/icons/document-pdf.png" style="cursor:pointer"
            onclick="parent.document.xform_itemsearch.printpdf.value='1';submitForm(parent.document.xform_itemsearch)">
         </td>
         <td style="padding:0px" align="right" width="40">
            <img src="/images/menu/icons/document-excel.png" style="cursor:pointer"
            onclick="parent.document.xform_itemsearch.printxls.value='1';submitForm(parent.document.xform_itemsearch)">
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td class="content_tbl_subheader">Número</td>
   <td class="content_tbl_subheader">Fecha</td>
   <td class="content_tbl_subheader">Total/neto</td>
</tr>
<tbody>
<?php
$_sesmodulename = "rebatesdet";
for($x = 0; $x < count($items) && $items != false; $x++)
{
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row"><nobr><?=$items[$x]["invc_docnumber"]?></nobr></td>
      <td class="content_row"><?=date('d.m.Y', $items[$x]["invc_date"])?>&nbsp;</td>
      <td class="content_row" align="left">$&nbsp;<?=printprice($items[$x]["invc_total_netto"])?></td>
   </tr>
   <?php
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["val1"] = $items[$x]["invc_docnumber"];
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["val2"] = date('d.m.Y', $items[$x]["invc_date"]);
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["val3"] = "$ ".printprice($items[$x]["invc_total_netto"]);
   $_TOTAL += $items[$x]["invc_total_netto"];
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="3" align="center" valign="middle" height="30">
         <br>
         <b class="msg_save_err">No hay datos disponibles.</b>
         <br><br>
      </td>
   </tr>
   <?php
}
else
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row_totals" colspan="2">TOTAL</td>
      <td class="content_row_totals">$&nbsp;<?=printprice($_TOTAL)?></td>
   </tr>
   <?php
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["val1"] = "<b>TOTAL</b>";
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["val2"] = " ";
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["val3"] = "<b>$ ".printprice($_TOTAL)."</b>";
}
?>
</tbody>
</table>
</form>
<?=Nifty_printF()?>
</body>
</html>

