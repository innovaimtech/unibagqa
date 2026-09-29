<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
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
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   $origpaytitle = trim(str_replace("'", "", $_REQUEST["pay_title"]));
   $_REQUEST["pay_title"]        = trim(addslashes($_REQUEST["pay_title"]));
   $_REQUEST["pay_desc"]         = trim(addslashes($_REQUEST["pay_desc"]));
   $_REQUEST["pay_sii_code"]     = trim(addslashes($_REQUEST["pay_sii_code"]));
   $_REQUEST["pay_days"]         = (int)$_REQUEST["pay_days"];
   $_REQUEST["pay_days_extra"]   = (int)$_REQUEST["pay_days_extra"];
   $_REQUEST["pay_boleta_act"]   = (int)$_REQUEST["pay_boleta_act"];
   $_REQUEST["pay_factura_act"]  = (int)$_REQUEST["pay_factura_act"];

   //----------------------------------------------------------------------------------
   $sql = " insert into payments
            (pay_title, pay_desc, pay_days, pay_days_extra, pay_crtusr, pay_crtdat,
             pay_sii_code, pay_boleta_act, pay_factura_act)
            VALUES
            ('{$_REQUEST["pay_title"]}', '{$_REQUEST["pay_desc"]}', {$_REQUEST["pay_days"]},
              {$_REQUEST["pay_days_extra"]}, {$_SESSION["user_id"]}, {$currtme},
              '{$_REQUEST["pay_sii_code"]}', {$_REQUEST["pay_boleta_act"]}, {$_REQUEST["pay_factura_act"]})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'maxid'
               from payments";
      $thisid = $CON->select($sql);
      $thisid = (int)$thisid[0]["maxid"];

      foreach($_REQUEST["shop_act"] AS $shopid)
      {
         $sql = " insert into payments_shops
                  (pay_id, shop_id)
                  VALUES
                  ({$thisid}, {$shopid})";
         $res = $CON->no_result($sql);
      }
      ?>
      <script language="JavaScript">
         parent.$('#req_paymentid').append('<option value="<?=$thisid?>"><?=$origpaytitle?></option>');
         parent.form_reqpos.req_paymentid.value = '<?=$thisid?>';
         parent.$.fancybox.close();
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
$sql = " select *
         from company_data
         where
         company_status = 1
         order by company_short";
$companies = $CON->select($sql);
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
   <script type="text/javascript" src="/libs/jscripts/jquery.table_navigation.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="<?php if(count($customers) == 0 || $customers == false) echo "document.xform_itemsearch.sql_stext.focus()"?>">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">

<div style="height:3px"></div>
<form action="fancy.edit.php" method="post" class="fokusfirst" name="xform_payment"
 onsubmit="return checkform(new Array(this.pay_title))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de forma de pago</td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input name="pay_title" id="pay_title" type="text" class="text" style="width:510px" value="<?=$payment[0]["pay_title"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Tipo Documento</td>
   <td class="content_row">
      <input type="checkbox" value="1" name="pay_boleta_act" checked>
      Boleta fiscal
      <input type="checkbox" value="1" name="pay_factura_act" checked>
      Factura
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripción</td>
   <td class="content_row">
      <textarea name="pay_desc" class="text" style="width:510px;height:50px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$payment[0]["pay_desc"]?></textarea>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Opciones: Factura</td>
</tr>
<tr>
   <td class="content_rowl">Dias Vencimiento</td>
   <td class="content_row">
      <input name="pay_days" type="text" class="text" style="width:80px" value="<?=$payment[0]["pay_days"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?php
foreach($companies AS $company)
{
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from company_shops t1
            where
            t1.shop_status = 1 and
            t1.shop_company_id = {$company["id"]}
            order by t1.shop_name";
   $shops = $CON->select($sql);

   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($shops) && $shops != false; $x++)
   {
      $shopid = $shops[$x]["id"];
      ?>
      <input type="checkbox" name="shop_act[]" value="<?=$shops[$x]["id"]?>" style="display:none" checked>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
}
?>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_payment)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_payment');" ?>
<script language="JavaScript">
$(document).ready(function()
{
   setTimeout(function()
   {
      $('#pay_title').focus();
   }, 300)
};
</script>
</body>
</html>