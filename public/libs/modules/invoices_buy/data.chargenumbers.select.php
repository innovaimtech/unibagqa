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

$needamount       = getPrice($_REQUEST["needamount"], 10);
$itemdataarr      = explode("#", $_REQUEST["itemdata"]);
$cloneToSthChange = false;

//----------------------------------------------------------------------------------
if($_REQUEST["trantype"] == "invoicesnotessbuy")
{
   $sql = " select *
            from invoices_notes_buy 
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
if($_REQUEST["trantype"] == "stockchangedown")
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
if($_REQUEST["trantype"] == "sthdown")
{
   $sql = " select *
            from storehousechanges
            where
            id = {$_REQUEST["tranid"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $tran_company_id  = $headdata["strc_company_id"];
   $tran_shop_id     = $headdata["strc_shop_id"];
   $tran_item_id     = $itemdataarr[0];
   $tran_item_type   = $itemdataarr[1];
   $js_tranpos       = $_REQUEST["tranpos"];
   $sql_tranpos      = $_REQUEST["tranpos"];
   $sql_partid       = 0;
   $cloneToSthChange = true;

   if($headdata["strc_status"] > 1)
      $saveblocked = true;
}

//----------------------------------------------------------------------------------
if($_REQUEST["trantype"] == "ordersdelivery")
{
   $sql = " select *
            from orders_delivery 
            where
            id = {$_REQUEST["tranid"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $tran_company_id  = $headdata["dlv_company_id"];
   $tran_shop_id     = $headdata["dlv_shop_id"];
   $tran_item_id     = $itemdataarr[0];
   $tran_item_type   = $itemdataarr[1];
   $js_tranpos       = $_REQUEST["tranpos"];
   $sql_tranpos      = $_REQUEST["tranpos"];
   $sql_partid       = 0;

   if($headdata["dlv_status"] > 1)
      $saveblocked = true;
}

//----------------------------------------------------------------------------------
if($_REQUEST["trantype"] == "invoicesell")
{
   $sql = " select *
            from invoices_sell 
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
if($_REQUEST["exec"] == "delete")
   clearItemChargeDataUsed($CON, $_REQUEST["tranid"], $_REQUEST["trantype"], $sql_tranpos, $sql_partid, $cloneToSthChange);

//----------------------------------------------------------------------------------
$posdata = getAvailableTranCharges($CON, $tran_company_id, $tran_shop_id, $tran_item_id, $tran_item_type);
$seldata = getItemChargeTransUsed($CON, $_REQUEST["tranid"], $_REQUEST["trantype"], $sql_tranpos, $sql_partid);

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
<?php
if(count($seldata) && $seldata != false)
{  ?>
   <?=Nifty_printH("box1", "730")?>
   <table border="0" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="85">
      <col>
      <col>
      <col>
      <col>
      <col>
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="8">Lotes seleccionados</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Lote</td>
      <td class="content_tbl_subheader">Fecha</td>
      <td class="content_tbl_subheader">Tipo</td>
      <td class="content_tbl_subheader" align="center">Salida</td>
      <td class="content_tbl_subheader" align="center">Bodega</td>
      <td class="content_tbl_subheader">Producción</td>
      <td class="content_tbl_subheader">Vencimiento</td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($seldata) && $seldata != false; $x++)
   {  ?>
      <td class="content_row" valign="top"><?=$seldata[$x]["tran_charge_number"]?></td>
         <td class="content_row" valign="top"><?=date('d.m.Y', $seldata[$x]["tran_crtdat"])?></td>
         <td class="content_row" valign="top"><?=$_CONFIGTRANTYPESNAMES[$seldata[$x]["tran_type"]]?></td>
         <td class="content_row" valign="top" align="center"><?=printPrice($seldata[$x]["tran_amount"],10)?></td>
         <td class="content_row" valign="top" align="center"><?=$seldata[$x]["st_name"]?></td>
         <td class="content_row" valign="top">
            <?php
            if($seldata[$x]["tran_prod_date"] > 0)
               echo date('d.m.Y', $seldata[$x]["tran_prod_date"]);
            else
               echo "&nbsp;";
            ?>
         </td>
         <td class="content_row" valign="top">
            <?php
            if($seldata[$x]["tran_expire_date"] > 0)
               echo date('d.m.Y', $seldata[$x]["tran_expire_date"]);
            else
               echo "&nbsp;";
            ?>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
   $delurl  = "'data.chargenumbers.select.php?exec=delete&trantype={$_REQUEST["trantype"]}&needamount={$_REQUEST["needamount"]}&tranid={$_REQUEST["tranid"]}";
   $delurl .= "&tranpos={$_REQUEST["tranpos"]}&itemdata= ' +escape('{$_REQUEST["itemdata"]}')";
   ?>
   <?=Nifty_printH("boxopt_b", "730")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td class="content_row_clear">&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         if(!$saveblocked)
            printButton("Eliminar Lotes", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')) location.href={$delurl}", "cross-circle-frame");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   </form>
   <?php
}
else
{  ?>
   <form action="data.chargenumbers.php" method="post" name="form_shppos" id="form_shppos" class="fokusfirst"
   onsubmit="<?php
   if($saveblocked)
   {  ?>return false;<?php }
   else
   {  ?>parent.$('#item_charges_data_<?=$js_tranpos?>').val($('#form_shppos').serialize());return true;<?php } ?>">
   <input type="hidden" name="exec" value="savecharges">
   <?=Nifty_printH("box1", "730")?>
   <table border="0" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="85">
      <col>
      <col>
      <col>
      <col>
      <col>
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="8">Seleccionar Lotes</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Lote</td>
      <td class="content_tbl_subheader">Fecha</td>
      <td class="content_tbl_subheader">Tipo</td>
      <td class="content_tbl_subheader" align="center">Disponible</td>
      <td class="content_tbl_subheader" align="center">Salida</td>
      <td class="content_tbl_subheader" align="center">Bodega</td>
      <td class="content_tbl_subheader">Producción</td>
      <td class="content_tbl_subheader">Vencimiento</td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   if($saveblocked)
   {
      $rowcounter = count($posdata);
      $rdlo       = "readonly";
      $dabl       = "disabled";
   }

   $preamount  = $needamount;
   $leftamount = $needamount;
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_amount_{$x}";

      if($leftamount > $posdata[$x]["tran_amount_avail"])
      {
         $preamount  = $posdata[$x]["tran_amount_avail"];
         $leftamount = $leftamount - $posdata[$x]["tran_amount_avail"];
      }
      elseif($leftamount <= $posdata[$x]["tran_amount_avail"])
      {
         $preamount  = $leftamount;
         $leftamount = 0.00;
      }
      ?>
      <tr class="prodint" bgcolor="<?=getRowColor($x)?>">
         <td class="content_row" valign="top"><?=$posdata[$x]["tran_charge_number"]?></td>
         <td class="content_row" valign="top"><?=date('d.m.Y', $posdata[$x]["tran_crtdat"])?></td>
         <td class="content_row" valign="top"><?=$_CONFIGTRANTYPESNAMES[$posdata[$x]["tran_type"]]?></td>
         <td class="content_row" valign="top" align="center"><?=printPrice($posdata[$x]["tran_amount_avail"],10)?></td>
         <td class="content_row" valign="top" align="center">
            <input type="hidden" name="tran_refid_<?=$x?>" value="<?=$posdata[$x]["id"]?>">
            <input type="text" class="text" style="width:50px;text-align:right"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" autocomplete="off"
            name="tran_amount_<?=$x?>" id="tran_amount_<?=$x?>" <?=$rdlo?>
            value="<?=printPrice($preamount,2)?>">
         </td>
         <td class="content_row" valign="top" align="center"><?=$posdata[$x]["st_name"]?></td>
         <td class="content_row" valign="top">
            <?php
            if($posdata[$x]["tran_prod_date"] > 0)
               echo date('d.m.Y', $posdata[$x]["tran_prod_date"]);
            else
               echo "&nbsp;";
            ?>
         </td>
         <td class="content_row" valign="top">
            <?php
            if($posdata[$x]["tran_expire_date"] > 0)
               echo date('d.m.Y', $posdata[$x]["tran_expire_date"]);
            else
               echo "&nbsp;";
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="8" align="center">
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
   <br>
   <?=Nifty_printH("boxopt_b", "730")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td class="content_row_clear">
         <?php
         if($leftamount > 0.00)
            echo "<b class='msg_save_err'>FALTAN <u>".printPrice($leftamount,10)."</u> UNIDADES";
         ?>
      </td>
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
   <?php
}
?>
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