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
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "gen")
{
   $_REQUEST["gen_val"] = getPrice($_REQUEST["gen_val"]);
   $_REQUEST["gen_dsc"] = getPrice($_REQUEST["gen_dsc"]);
   
   $cid = (int)$_REQUEST["gen_catid"];
   $val = $_REQUEST["gen_val"];
   $dsc = $_REQUEST["gen_dsc"];
   $doc = trim(addslashes($_REQUEST["gen_docnumber"]));
   $amt = round($val / 100 * $dsc,0);

   $sql = " select cat_title
            from productcats
            where
            id = {$cid}";
   $catname = $CON->select($sql);

   $gen =  "POR DESCUENTO ADICIONAL DE {$dsc}%\n";
   $gen .= "EN FAMILIA (".sprintf("%03s",$cid).") {$catname[0]["cat_title"]}\n";
   $gen .= "FACTURA AFECTA {$doc}\n";
   $gen .= "SOBRE VALOR $ ".printPrice($val);
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
      function setDscText()
      {
         var xtxt = $('#gen_text').val();
         var xcat = $('#gen_catid').val();
         parent.document.getElementById('item_desc_<?=$_REQUEST["rowid"]?>').value = xtxt;
         parent.document.getElementById('item_com_catid_<?=$_REQUEST["rowid"]?>').value = xcat;
         parent.document.getElementById('item_amount_<?=$_REQUEST["rowid"]?>').value = '1';
         parent.document.getElementById('item_sellprice_netto_<?=$_REQUEST["rowid"]?>').value = '<?=printPrice($amt)?>';
         parent.$.fancybox.close();
      }
   </script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="document.xform_gen.gen_docnumber.focus()">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<form action="fancy.generatepos.php" method="post" name="xform_gen">
<input type="hidden" name="exec" value="gen">
<input type="hidden" name="rowid" value="<?=$_REQUEST["rowid"]?>">
<?=Nifty_printH("box1", "590")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="90">
   <col width="120">
   <col width="90">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Ingresar datos</td>
</tr>
<tr>
   <td class="content_rowl">Nº Factura</td>
   <td class="content_row">
      <input type="text" style="width:120px" id="gen_docnumber" name="gen_docnumber" class="text"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$_REQUEST["gen_docnumber"]?>">
   </td>
   <td class="content_rowl">Familia</td>
   <td class="content_row">
      <select class="text" style="width:270px" name="gen_catid" id="gen_catid"
      onmousedown="markfield(this,0)" onblur="markfield(this,1);">
         <?php
         foreach($pcats AS $pcat)
         {  ?>
            <option value="<?=$pcat["id"]?>"
            <?php if($pcat["id"] == $_REQUEST["gen_catid"]) echo "selected"?>><?=sprintf("%03s", $pcat["id"])?> - <?=$pcat["cat_title"]?>
            </option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Monto</td>
   <td class="content_row">
      <input type="text" style="width:120px" id="gen_val" name="gen_val" class="text"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=printPrice($_REQUEST["gen_val"])?>">
   </td>
   <td class="content_rowl">Descuento</td>
   <td class="content_row">
      <input type="text" style="width:80px" id="gen_dsc" name="gen_dsc" class="text"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$_REQUEST["gen_dsc"]?>"> %
      = $ <?=printPrice($amt)?>
   </td>
</tr>
<tr>
   <td class="content_row_totals content_rowl" valign="top">Resultado</td>
   <td class="content_row_totals" colspan="3">
      <textarea class="text" id="gen_text" name="gen_text" style="width:500px;height:160px"><?=$gen?></textarea>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "590")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td width="130">
      <?php
      printButton("Cerrar ventana", "postnav", "javascript: deactivateFormChange()", "parent.$.fancybox.close();", "cross-circle-frame");
      ?>
   </td>
   <td>&nbsp;</td>
   <td width="130" style="padding-right:5px">
      <?php
      printButton("Generar Texto", "postnav", "javascript: deactivateFormChange()", "submitForm(document.xform_gen)", "arrow-circle-double-135");
      ?>
   </td>
   <?php
   if($gen != "")
   {  ?>
      <td width="130">
         <?php
         printButton("Aplicar Texto", "postnav_save", "javascript: deactivateFormChange()", "setDscText()", "tick-circle-frame");
         ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
</body>
</html>