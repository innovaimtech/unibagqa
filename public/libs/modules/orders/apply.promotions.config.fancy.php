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
$sql = " select t1.*, t2.id 'company_id', t2.company_short
         from company_shops t1
         LEFT OUTER JOIN company_data t2 ON t1.shop_company_id = t2.id
         where
         t1.id = {$_REQUEST["shopid"]}";
$shop = $CON->select($sql);
$shop = $shop[0];

$sql = " select t1.*,
         t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
         t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
         from item_promotions t1
         LEFT OUTER JOIN user t2 ON t1.prom_updusr = t2.id
         LEFT OUTER JOIN user t3 ON t1.prom_crtusr = t3.id
         where
         t1.id = {$_REQUEST["promid"]} ";
$prom = $CON->select($sql);
$prom = $prom[0];

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "save")
{
   if($_REQUEST["mode"] == "OFFER")
   {
      $tblname = "offers_items";
      $idxname = "req_id";
      $amtname = "item_amount";
   }
   if($_REQUEST["mode"] == "DELIVERY")
   {
      $tblname = "orders_delivery_items";
      $idxname = "dlv_id";
      $amtname = "item_amount_shipped";
   }
   if($_REQUEST["mode"] == "")
   {
      $tblname = "orders_items";
      $idxname = "req_id";
      $amtname = "item_amount";
   }
   if($_REQUEST["mode"] == "INVOICE")
   {
      $tblname = "invoices_sell_parts_items";
      $idxname = "invc_id";
      $amtname = "item_amount";
      $addfld  = ", part_id";
      $addval  = ", ".$_REQUEST["partid"];
   }

   $sql = " select count(item_pos) 'poscount'
            from {$tblname}
            where
            {$idxname} = {$_REQUEST["id"]}";
   if((int)$_REQUEST["partid"])
      $sql .= " and part_id = {$_REQUEST["partid"]} ";
   $poscount = $CON->select($sql);
   $poscount = (int)$poscount[0]["poscount"];

   $sql = " select MAX(item_pos) 'item_pos'
            from {$tblname}
            where
            {$idxname} = {$_REQUEST["id"]}";
   if((int)$_REQUEST["partid"])
      $sql .= " and part_id = {$_REQUEST["partid"]} ";
   $max_pos = $CON->select($sql);
   $max_pos = (int)$max_pos[0]["item_pos"];

   if($poscount > 0)
      $poscounter = $max_pos + 1;
   else
      $poscounter = 0;
   
   //----------------------------------------------------------------------------------
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_amount_") !== false && strpos($reqkey, "item_amount_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
         $item_amount      = getPrice($_REQUEST["item_amount_{$idx}"],2);
         $item_price_dsc   = getPrice($_REQUEST["item_price_dsc_{$idx}"]);
         $item_price_new   = getPrice($_REQUEST["itemshop_sellprice_netto_{$idx}"]);
         $item_taxes       = getPrice($_REQUEST["itemshop_sellprice_taxes_perc_{$idx}"]);
         $item_type        = $_REQUEST["item_type_{$idx}"];

         if($_REQUEST["item_amount_{$idx}"] > 0.00)
         {
            $sql = " insert into {$tblname}
                     ({$idxname}, item_id, item_pos, item_type, {$amtname}, item_sellprice_netto,
                      item_sellprice_taxes_perc, item_promid, item_promdsc, item_pcat_dsc_act, item_vol_act,
                      item_value_act, item_discount, item_discount_type {$addfld})
                     VALUES
                     ({$_REQUEST["id"]}, {$idx}, {$poscounter}, '{$item_type}', {$item_amount},
                      {$item_price_new}, {$item_taxes}, {$_REQUEST["promid"]}, {$prom["prom_dsc"]},
                      1, 1, 1, {$prom["prom_dsc"]}, 0 {$addval})";
            $CON->no_result($sql);

            recalcOrderItem($CON, $_REQUEST["id"], $idx, $poscounter, true, $_REQUEST["mode"]);

            $sql = " update {$tblname}
                     set
                     item_discount        = {$prom["prom_dsc"]},
                     item_discount_type   = 0
                     where
                     {$idxname}  = {$_REQUEST["id"]} and
                     item_id     = {$idx} and
                     item_pos    = {$poscounter}";
            $CON->no_result($sql);
            
            $poscounter++;
         }
      }
   }
   recalcOrder($CON, $_REQUEST["id"], $_REQUEST["mode"]);
   ?>
   <script language="Javascript">
      parent.submitForm(parent.document.<?=$_REQUEST["frmname"]?>);
   </script>
   <?php
   exit;
}

$sql = " select t1.*, t2.item_number_prod, t2.item_title, t3.itemshop_sellprice_netto, t3.itemshop_sellprice_taxes_perc
         from item_promotions_items t1
         INNER JOIN item t2 ON (t1.item_id = t2.id and t1.item_type = 'item')
         INNER JOIN item_shops t3            ON ( t1.item_id = t3.item_id and t3.shop_id = {$_REQUEST["shopid"]} )
         where
         t1.prom_id = {$_REQUEST["promid"]}
         UNION ALL
         select t1.*, t2.item_number_prod, t2.item_title, t3.itemshop_sellprice_netto, t3.itemshop_sellprice_taxes_perc
         from item_promotions_items t1
         INNER JOIN itemlist t2 ON (t1.item_id = t2.id and t1.item_type = 'itemlist')
         INNER JOIN itemlist_shops t3        ON ( t1.item_id = t3.item_id and t3.shop_id = {$_REQUEST["shopid"]} )
         where
         t1.prom_id = {$_REQUEST["promid"]}
         order by 4";
$posdata = $CON->select($sql);
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
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="120">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de promoción</td>
</tr>
<tr>
   <td class="content_rowl">Promoción</td>
   <td class="content_row"><?=$prom["prom_name"]?></td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripción</td>
   <td class="content_row"><?=$prom["prom_desc"]?></td>
</tr>
<tr>
   <td class="content_rowl">Cantidad</td>
   <td class="content_row"><?=printPrice($prom["prom_item_amount"],2)?></td>
</tr>
<tr>
   <td class="content_rowl">Descuento</td>
   <td class="content_row"><?=printPrice($prom["prom_dsc"])?> %</td>
</tr>
<tr>
   <td class="content_rowl">Valido</td>
   <td class="content_row"><?=date('d.m.Y', $prom["prom_datefrom"])?> - <?=date('d.m.Y', $prom["prom_dateto"])?></td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
if($_REQUEST["exec"] == "calc")
{  ?>
   <form action="apply.promotions.config.fancy.php" method="post" name="xform_prom">
   <input type="hidden" name="shopid" value="<?=$_REQUEST["shopid"]?>">
   <input type="hidden" name="mode" value="<?=$_REQUEST["mode"]?>">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="promid" value="<?=$_REQUEST["promid"]?>">
   <input type="hidden" name="partid" value="<?=$_REQUEST["partid"]?>">
   <input type="hidden" name="frmname" value="<?=$_REQUEST["frmname"]?>">
   <input type="hidden" name="exec" value="save">
   <?=Nifty_printH("box2", "99%")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="90">
      <col width="90">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="7">Combinación de artículos</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Artículo</td>
      <td class="content_tbl_subheader">Unidad</td>
      <td class="content_tbl_subheader">Cantidad</td>
      <td class="content_tbl_subheader">Precio base</td>
      <td class="content_tbl_subheader">Descuento</td>
      <td class="content_tbl_subheader">Precio Promo</td>
      <td class="content_tbl_subheader">Precio Total</td>
   </tr>
   <?php
   $gc = 0;
   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      $desc = trim(addslashes($posdata[$x]["item_title"]));
      $udsc = trim(addslashes(getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"])));

      $item_amount      = getPrice($_REQUEST["item_amount_{$posdata[$x]["item_id"]}"],2);
      $item_price_orig  = $posdata[$x]["itemshop_sellprice_netto"];
      $item_price_dsc   = round($item_price_orig / 100 * $prom["prom_dsc"],0);
      $item_price_new   = $item_price_orig - $item_price_dsc;
      $item_price_total = $item_price_new * $item_amount;
      $item_gesamount   += $item_amount;
      if($item_amount > 0.00)
      {
         ?>
         <tr bgcolor="<?=getRowColor($gc)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row"><?=$posdata[$x]["item_number_prod"]?> - <?=$desc?></td>
            <td class="content_row"><?=$udsc?></td>
            <td class="content_row"><?=printPrice($item_amount,2)?></td>
            <td class="content_row"><?=printPrice($posdata[$x]["itemshop_sellprice_netto"])?></td>
            <td class="content_row"><?=printPrice($item_price_dsc)?></td>
            <td class="content_row"><?=printPrice($item_price_new)?></td>
            <td class="content_row"><?=printPrice($item_price_total)?></td>
         </tr>
         <input type="hidden" name="item_amount_<?=$posdata[$x]["item_id"]?>" value="<?=printPrice($item_amount,2)?>">
         <input type="hidden" name="itemshop_sellprice_netto_<?=$posdata[$x]["item_id"]?>" value="<?=printPrice($posdata[$x]["itemshop_sellprice_netto"],2)?>">
         <input type="hidden" name="itemshop_sellprice_taxes_perc_<?=$posdata[$x]["item_id"]?>" value="<?=printPrice($posdata[$x]["itemshop_sellprice_taxes_perc"],2)?>">
         <input type="hidden" name="item_price_dsc_<?=$posdata[$x]["item_id"]?>" value="<?=printPrice($item_price_dsc,2)?>">
         <input type="hidden" name="item_price_new_<?=$posdata[$x]["item_id"]?>" value="<?=printPrice($item_price_new,2)?>">
         <input type="hidden" name="item_prom_dsc_<?=$posdata[$x]["item_id"]?>" value="<?=printPrice($prom["prom_dsc"],2)?>">
         <input type="hidden" name="item_type_<?=$posdata[$x]["item_id"]?>" value="<?=$posdata[$x]["item_type"]?>">
         <?php
         $gc++;
      }
   }
   ?>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?=Nifty_printH("boxopt_b", "99%")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td>&nbsp;</td>
      <td width="600" style="padding-right:5px" align="right">
         <?php
         if($item_gesamount < $prom["prom_item_amount"])
         {  ?>
            <b class="msg_save_err">DEBES INGRESAR LA CANTIDAD MINIMA DE <u><?=printPrice($prom["prom_item_amount"], 2)?></u></b>
            <?php
         }
         else
         {  
            printButton("Guardar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_prom)", "calculator", 120);
            $_SESSION["_SUBMITBTN"] = 1;
         }
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
   <form action="apply.promotions.config.fancy.php" method="post" name="xform_prom">
   <input type="hidden" name="shopid" value="<?=$_REQUEST["shopid"]?>">
   <input type="hidden" name="promid" value="<?=$_REQUEST["promid"]?>">
   <input type="hidden" name="partid" value="<?=$_REQUEST["partid"]?>">
   <input type="hidden" name="frmname" value="<?=$_REQUEST["frmname"]?>">
   <input type="hidden" name="mode" value="<?=$_REQUEST["mode"]?>">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="exec" value="calc">
   <?=Nifty_printH("box2", "99%")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="90">
      <col width="90">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="3">Combinación de artículos</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Artículo</td>
      <td class="content_tbl_subheader">Unidad</td>
      <td class="content_tbl_subheader">Cantidad</td>
   </tr>
   <?php
   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      $desc = trim(addslashes($posdata[$x]["item_title"]));
      $udsc = trim(addslashes(getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"])));
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$posdata[$x]["item_number_prod"]?> - <?=$desc?></td>
         <td class="content_row"><?=$udsc?></td>
         <td class="content_row">
            <input type="text" class="text" style="width:50px;text-align:right"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" autocomplete="off"
            name="item_amount_<?=$posdata[$x]["item_id"]?>" id="item_amount_<?=$posdata[$x]["item_id"]?>">
         </td>
      </tr>
      <?php
      if(!$x)
      {  ?>
         <script language="JavaScript">
            document.all.item_amount_<?=$posdata[$x]["item_id"]?>.focus();
         </script>
         <?php
      }
   }
   ?>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?=Nifty_printH("boxopt_b", "99%")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td>&nbsp;</td>
      <td width="130" style="padding-right:5px">
         <?php
         printButton("Calular", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_prom)", "calculator");
         $_SESSION["_SUBMITBTN"] = 1;
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   </form>
   <?php
}
?>
</body>
</html>