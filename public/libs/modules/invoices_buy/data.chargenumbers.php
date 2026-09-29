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

//----------------------------------------------------------------------------------
header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

$itemdataarr = explode("#", $_REQUEST["itemdata"]);

//----------------------------------------------------------------------------------
if($_REQUEST["trantype"] == "shipment")
{
   $sql = " select *
            from shipment
            where
            id = {$_REQUEST["tranid"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $tran_company_id  = $headdata["shp_company_id"];
   $tran_shop_id     = $headdata["shp_shop_id"];
   $tran_item_id     = $itemdataarr[0];
   $tran_item_type   = $itemdataarr[1];
   $js_tranpos       = $_REQUEST["tranpos"];
   $sql_tranpos      = $_REQUEST["tranpos"];
   $sql_partid       = 0;

   if($headdata["shp_status"] > 1)
      $saveblocked = true;
}

//----------------------------------------------------------------------------------
if($_REQUEST["trantype"] == "stockchangeup")
{
   $sql = " select *
            from stockchanges
            where
            id = {$_REQUEST["tranid"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $tran_company_id  = $headdata["stk_companyid"];
   $tran_shop_id     = $headdata["stk_shopid"];
   $tran_item_id     = $itemdataarr[0];
   $tran_item_type   = $itemdataarr[1];
   $js_tranpos       = $_REQUEST["tranpos"];
   $sql_tranpos      = $_REQUEST["tranpos"];
   $sql_partid       = 0;

   if($headdata["stk_status"] > 1)
      $saveblocked = true;
}

//----------------------------------------------------------------------------------
if($_REQUEST["trantype"] == "invoicesbuy")
{
   $sql = " select *
            from invoices_buy 
            where
            id = {$_REQUEST["tranid"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $tran_company_id  = $headdata["invc_company_id"];
   $tran_shop_id     = $headdata["invc_shop_id"];
   $tran_item_id     = $itemdataarr[0];
   $tran_item_type   = $itemdataarr[1];
   $js_tranpos       = $_REQUEST["tranpos"];
   $temp_tranpos     = explode("_", $_REQUEST["tranpos"]);
   $sql_tranpos      = $temp_tranpos[1];
   $sql_partid       = $temp_tranpos[0];

   if($headdata["invc_status"] > 1)
      $saveblocked = true;
}

//----------------------------------------------------------------------------------
if($_REQUEST["trantype"] == "invoicesnotessell")
{
   $sql = " select *
            from invoices_notes_sell
            where
            id = {$_REQUEST["tranid"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $tran_company_id  = $headdata["note_company_id"];
   $tran_shop_id     = $headdata["note_shop_id"];
   $tran_item_id     = $itemdataarr[0];
   $tran_item_type   = $itemdataarr[1];
   $js_tranpos       = $_REQUEST["tranpos"];
   $sql_tranpos      = $_REQUEST["tranpos"];
   $sql_partid       = 0;

   if($headdata["note_status"] > 1)
      $saveblocked = true;
}


//----------------------------------------------------------------------------------
if($tran_item_type == "item")
   $itemsts = getItemStorehouses($CON, $tran_shop_id, $tran_item_id, $tran_item_type);
else
{
   $itemlistpos = getItemListContent($CON, $tran_item_id);
   $itemsts     = getItemStorehouses($CON, $tran_shop_id, $itemlistpos[0]["item_id"], "item");
}
if(count($itemsts))
{
   foreach(array_keys($itemsts) AS $itemstid)
   {
      $currstock = getItemShopStorehouseCurrentStock($CON, $tran_shop_id, $itemstid, $tran_item_id, $tran_item_type, true);
      $_SELSTDS[$itemstid] = $itemsts[$itemstid]." (".printPrice($currstock,2).")";

   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "savecharges")
{  ?>
   <script language="JavaScript">
      parent.$('#idx_fin_button').hide();
      parent.$.fancybox.close();
   </script>
   <?php
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
      require_once("../../../libs/jscripts/sourcen.php");
      ?>
   </script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <style type="text/css"><!-- @import url(/libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="/libs/jscripts/datepicker/datepicker.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="autofocus()">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<form action="data.chargenumbers.php" method="post" name="form_shppos" id="form_shppos" class="fokusfirst"
onsubmit="<?php
if($saveblocked)
{  ?>return false;<?php }
else
{  ?>parent.$('#item_charges_data_<?=$js_tranpos?>').val($('#form_shppos').serialize());return true;<?php } ?>">
<input type="hidden" name="exec" value="savecharges">
<?=Nifty_printH("box1", "630")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="120">
   <col>
   <col width="180">
   <col width="110">
   <col width="110">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="5">Ingresar Lotes</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Número Lote</td>
   <td class="content_tbl_subheader" align="right">Cantidad</td>
   <td class="content_tbl_subheader">Bodega</td>
   <td class="content_tbl_subheader">Fecha/Producción</td>
   <td class="content_tbl_subheader">Fecha/Vencimiento</td>
</tr>
<?php
//----------------------------------------------------------------------------------
$posdata    = getItemChargeTrans($CON, $_REQUEST["tranid"], $_REQUEST["trantype"], $sql_tranpos, $sql_partid);
$rowcounter = 12;

if($saveblocked)
{
   $rowcounter = count($posdata);
   $rdlo       = "readonly";
   $dabl       = "disabled";
}
   
//----------------------------------------------------------------------------------
for($x = 0; $x < $rowcounter; $x++)
{
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "tran_charge_number_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "tran_amount_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "tran_st_id_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "tran_prod_date_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "tran_expire_date_{$x}";
   ?>
   <tr class="prodint" bgcolor="<?=getRowColor($x)?>">
      <td class="content_row" valign="top">
         <input type="text" class="text" name="tran_charge_number_<?=$x?>" id="tran_charge_number_<?=$x?>"
         value="<?=$posdata[$x]["tran_charge_number"]?>" <?=$rdlo?>
         style="width:120px" onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>>
      </td>
      <td class="content_row" valign="top">
         <input type="text" class="text" name="tran_amount_<?=$x?>" id="tran_amount_<?=$x?>" style="width:130px;text-align:right"
         value="<?if($posdata[$x]["tran_amount"] > 0) echo printPrice($posdata[$x]["tran_amount"],10)?>" <?=$rdlo?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" valign="top">
         <select class="text" style="width:130px;" name="tran_st_id_<?=$x?>" id="tran_st_id_<?=$x?>" <?=$dabl?>
         onmousedown="markfield(this,0)" onblur="markfield(this,1);removeSelStyle(this);" onfocus="addSelStyle(this);">
            <?php
            if($posdata[$x]["id"] > 0)
            {  ?>
               <option value="<?=$posdata[$x]["tran_st_id"]?>"><?=$posdata[$x]["st_name"]?></option>
               <?php
            }
            else
            {
               foreach(array_keys($_SELSTDS) AS $stdid)
               {  ?>
                  <option value="<?=$stdid?>"><?=$_SELSTDS[$stdid]?></option>
                  <?php
               }
            }
            ?>
         </select>
      </td>
      <td class="content_row" valign="top">
         <input type="text" style="width:80px" id="tran_prod_date_<?=$x?>" name="tran_prod_date_<?=$x?>" <?=$rdlo?>
         class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         value="<?php if($posdata[$x]["tran_prod_date"] > 0) echo date('d.m.Y', $posdata[$x]["tran_prod_date"])?>">
      </td>
      <td class="content_row" valign="top">
         <input type="text" style="width:80px" id="tran_expire_date_<?=$x?>" name="tran_expire_date_<?=$x?>" <?=$rdlo?>
         class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"
         value="<?php if($posdata[$x]["tran_expire_date"] > 0) echo date('d.m.Y', $posdata[$x]["tran_expire_date"])?>">
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "630")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130" style="padding-right:5px">
      <?php
      if(!$saveblocked)
         printButton($_LANG["FORM"]["BUTTON"][11], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "tick-circle-frame");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<script language="JavaScript">
function xgo(evt, objname)
{
   if(xvi(objname))
   {
      evt.preventDefault();
      evt.target.blur();
      setTimeout(function() { $('#' +objname).focus().select(); }, 50);
   }
}
function xvi(objname)
{
   if($('#' +objname).length > 0 &&
      document.getElementById(objname).style.display != 'none' &&
      document.getElementById(objname).type != 'hidden')
      return true;
   return false;
}
<?php
$xkeys = array_keys($_FIELDREGS);;
for($z = 0; $z < count($xkeys); $z++)
{
   $x = $xkeys[$z];
   $jqrowfields = array_values($_FIELDREGS[$x]);
   for($y = 0; $y < count($jqrowfields); $y++)
   {
      if(!(int)$_FIELDIGNORES[$jqrowfields[$y]])
      {
         ?>$('#<?=$jqrowfields[$y]?>').bind('keydown',function(evt){if(!evt.altKey&&!evt.shiftKey){if(evt.target.type=='select-one')var ISB=1;else var ISB=0;var EC=evt.keyCode;<?php
         $_NAV = fieldNavNextFields($_FIELDREGS, $_FIELDIGNORES, $x, $y);
         if($_NAV["UP"] != "")      { ?>if(EC==38){if(!ISB||(ISB && evt.target.options.length<=1)){xgo(evt,'<?=$_NAV["UP"]?>')}}<?php }
         if($_NAV["DOWN"] != "")    { ?>if(EC==40){if(!ISB||(ISB && evt.target.options.length<=1)){xgo(evt,'<?=$_NAV["DOWN"]?>')}}<?php }
         if($_NAV["RIGHT"] != "")   { ?>if((EC==13&&ISB)||EC==39){xgo(evt,'<?=$_NAV["RIGHT"]?>');}<?php }
         if($_NAV["LEFT"] != "")    { ?>if(EC==37){xgo(evt,'<?=$_NAV["LEFT"]?>')}<?php }
         ?>}});<?php
      }
   }
}
?>
</script> 
</body>
</html>