<?php
//----------------------------------------------------------------------------------
require_once("../../../libs/classes/menu.php");
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

//----------------------------------------------------------------------------------
unset($_SESSION["_CONF"]);

$sql = " select *
         from config_system";
$conf = $CON->select($sql);
$conf = $conf[0];
foreach(array_keys($conf) AS $ckey)
   $_SESSION["_CONF"][$ckey] = trim($conf[$ckey]);

require_once("../../../libs/lang/es.php");
require_once("../../../libs/functions.php");
require_once("../../../libs/functions.erp.php");

?>
<html style="padding:0px;margin:0px;width:100%;height:100%">
<head>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <script type="text/javascript" src="/libs/jscripts/jquery.fancybox-1.3.4/fancybox/jquery.mousewheel-3.0.4.pack.js"></script>
   <script type="text/javascript" src="/libs/jscripts/jquery.fancybox-1.3.4/fancybox/jquery.fancybox-1.3.4.pack.js"></script>
   <link rel="stylesheet" type="text/css" href="/libs/jscripts/jquery.fancybox-1.3.4/fancybox/jquery.fancybox-1.3.4.css" media="screen" />
   <script language="Javascript"><?php require_once("../../../libs/jscripts/sourcen.php") ?></script>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
</head>
<?php
$_REQUEST["itemid"] = (int)$_REQUEST["itemid"];

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.cat_id, t3.cat_title
         from item t1
         INNER JOIN item_productcats t2   ON t1.id = t2.item_id
         INNER JOIN productcats t3        ON t2.cat_id = t3.id
         where
         t1.id = {$_REQUEST["itemid"]}";
$item = $CON->select($sql);
$item = $item[0];

//----------------------------------------------------------------------------------
$sql = " select *
         from productcats
         where
         id = {$item["cat_id"]}";
$pcatdata = $CON->select($sql);
$pcatdata = $pcatdata[0];

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "save")
{
   $_REQUEST["item_amount"] = getPrice(trim($_REQUEST["item_amount"]), 10);
  
   //METER
   if((int)$_REQUEST["stock_opt"] == 1)
   {
      $unit_full_length          = (int)$item["item_reg_length"];
      $inp_length                = (int)$_REQUEST["item_amount_meter"];
      $_REQUEST["item_amount"]   = round($inp_length / $unit_full_length,10);
   }
   //KILOS
   elseif((int)$_REQUEST["stock_opt"] == 2)
   {
      if((int)$item["item_reg_kg"])
      {
         $inp_kilos                 = (int)$_REQUEST["item_amount_kg"];
         $_REQUEST["item_amount"]   = round($inp_kilos / $item["item_reg_kg"],10);
      }
      else
      {
         $unit_full_kilos           = ($item["item_reg_length"] * $item["item_reg_width"] * $item["item_reg_gsm"]) / 1000;
         $inp_kilos                 = (int)$_REQUEST["item_amount_kg"];
         $_REQUEST["item_amount"]   = round($inp_kilos / $unit_full_kilos,10);
      }
      
   }

   if($_REQUEST["item_amount"] > 0.00)
   {  ?>
      <script language="Javascript">
         parent.document.getElementById('item_amount_<?=$_REQUEST["idx"]?>').value = '<?=printPrice($_REQUEST["item_amount"],10)?>';
         parent.$.fancybox.close();
      </script>
      <?php
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.com_name, t3.*
         from tran_comments t1
         INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
         INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
         where
         t1.com_status  > 0 and
         t2.cat_id      = {$item["cat_id"]} and
         t3.add_status  > 0
         order by t1.com_name, t3.add_name";
$trancoms = $CON->select($sql);
foreach($trancoms AS $trancom)
{
   $_TRANSCOM[$trancom["add_com_id"]]["NAME"] = $trancom["com_name"];
   $_TRANSCOM[$trancom["add_com_id"]]["OPTS"][$trancom["id"]] = $trancom["add_name"];
}

//----------------------------------------------------------------------------------
unset($_COMVALS);
$sql = " select t1.*, t2.add_name
         from tran_comments_item_vals t1
         INNER JOIN tran_comments_vals t2 ON t1.val_id = t2.id
         where
         t1.item_id = {$item["id"]}";
$comvals = $CON->select($sql);
foreach($comvals AS $comval)
   $_COMVALS[$comval["com_id"]] = $comval["add_name"];

?>
<script language="Javascript">
   function checkSthReg(xform)
   {
      if(document.getElementById('stock_opt0').checked)
      {
         return checkform(new Array(xform.item_amount));
      }
      if(document.getElementById('stock_opt1').checked)
      {
         return checkform(new Array(xform.item_amount_meter));
      }
      if(document.getElementById('stock_opt2').checked)
      {
         return checkform(new Array(xform.item_amount_kg));
      }
      return false;
   }
</script>
<body style="background-color:#FFFFFF;padding:0px;margin:0px;width:100%;height:100%">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<input type="hidden" name="jschk_obitpanel" id="jschk_obitpanel" value="<?=(int)$_SESSION["jschk_obitpanel"]?>">
<input type="hidden" name="jschk_currenturl" id="jschk_currenturl" value="<?=$_SERVER["REQUEST_URI"]?>">
<form action="fancy.materiales.calc.php" method="post" name="xform_inp" id="xform_inp" onsubmit="return checkSthReg(this)">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="mode" value="save">
<input type="hidden" name="refid" value="<?=$_REQUEST["refid"]?>">
<input type="hidden" name="itemid" value="<?=$_REQUEST["itemid"]?>">
<input type="hidden" name="idx" value="<?=$_REQUEST["idx"]?>">
<input type="hidden" name="deletemode" value="">
<table border="0" width="100%" cellpadding="6" cellspacing="0" style="border:1px solid #CCCCCC;">
<colgroup>
   <col width="140">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2" style="color: white;background-color: #0EA9A4;">Información del producto</td>
</tr>
<tr>
   <td class="content_rowl">Categoria</td>
   <td class="content_row"><?=$item["cat_title"]?></td>
</tr>
<tr>
   <td class="content_rowl">Material</td>
   <td class="content_row"><?=$item["item_title"]?></td>
</tr>
<tr>
   <td class="content_rowl">Código</td>
   <td class="content_row"><?=$item["item_number_prod"]?></td>
</tr>
<?php
if((int)$pcatdata["cat_itemreg_width"])
{  ?>
   <tr>
      <td class="content_rowl">Ancho</td>
      <td class="content_row"><?=printPrice($item["item_reg_width"])?> cm</td>
   </tr>
   <?php
}
if((int)$pcatdata["cat_itemreg_length"])
{  ?>
   <tr>
      <td class="content_rowl">Longitud</td>
      <td class="content_row"><?=printPrice($item["item_reg_length"])?> m</td>
   </tr>
   <?php
}
if((int)$pcatdata["cat_itemreg_gsm"])
{  ?>
   <tr>
      <td class="content_rowl">GSM</td>
      <td class="content_row"><?=printPrice($item["item_reg_gsm"])?> gr</td>
   </tr>
   <?php
}
if((int)$pcatdata["cat_itemreg_kg"])
{  ?>
   <tr>
      <td class="content_rowl">Kilogramos</td>
      <td class="content_row"><?=printPrice($item["item_reg_kg"],2)?> kg</td>
   </tr>
   <?php
}
foreach(array_keys($_TRANSCOM) AS $comid)
{  
   $commname = ucwords(strtolower($_TRANSCOM[$comid]["NAME"]));
   ?>
   <tr>
      <td class="content_rowl"><?=$commname?></td>
      <td class="content_row"><?=$_COMVALS[$comid]?>&nbsp;</td>
   </tr>
   <?php
}
?>
</table>
<div style="height:10px"></div>
<table border="0" width="100%" cellpadding="6" cellspacing="0" style="border:1px solid #CCCCCC;">
<colgroup>
   <col width="140">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2" style="color: white;background-color: #0EA9A4;">Opciones de reserva de stock</td>
</tr>
<tr>
   <td class="content_rowl">Opciones</td>
   <td class="content_row">
      <input type="radio" name="stock_opt" id="stock_opt0" value="0" checked
      onclick="$('.trstockopt').hide(0);$('#idx_stock_unit').show(0);">Por unidad

      <span style="<?if(!(int)$pcatdata["cat_itemreg_length"]) echo "display: none"?>">
         <input type="radio" name="stock_opt" id="stock_opt1" value="1"
         onclick="$('.trstockopt').hide(0);$('#idx_stock_length').show(0);">Por metros
      </span>
      <span style="<?if(!(int)$pcatdata["cat_itemreg_gsm"] && !(int)$pcatdata["cat_itemreg_kg"]) echo "display: none"?>">
         <input type="radio" name="stock_opt"id="stock_opt2" value="2"
         onclick="$('.trstockopt').hide(0);$('#idx_stock_kilos').show(0);">Por peso
      </span>
   </td>
</tr>
<tr id="idx_stock_unit" class="trstockopt">
   <td class="content_rowl">Cantidad</td>
   <td class="content_row">
      <input type="text" class="text" name="item_amount" style="width:100px;text-align:center"> c/u
   </td>
</tr>
<tr id="idx_stock_length" class="trstockopt" style="display:none">
   <td class="content_rowl">Metros</td>
   <td class="content_row">
      <input type="text" class="text" name="item_amount_meter" style="width:100px;text-align:center"> m
   </td>
</tr>
<tr id="idx_stock_kilos" class="trstockopt" style="display:none">
   <td class="content_rowl">Kilogramos</td>
   <td class="content_row">
      <input type="text" class="text" name="item_amount_kg" style="width:100px;text-align:center"> kg
   </td>
</tr>
</table> 
<div style="height:10px"></div>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td width="120" style="padding-right:5px">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "javascript: deactivateFormChange()", "parent.$.fancybox.close();", "arrow-180");
      ?>
   </td>
   <td></td>
   <td width="120" style="padding-right:5px">
      <?php
      printButton("Guardar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_inp)", "tick-circle-frame");
      ?>
   </td>
</tr>
</table>

</body>
</html>