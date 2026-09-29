<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2021 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../classes/page.php");
require_once("../../../classes/mysql.php");
require_once("../../../config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../../lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../functions.php");

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
$_posidx    = explode("_", $_REQUEST["posidx"]);
$_DLVID     = (int)$_posidx[0];
$_ITEMID    = (int)$_posidx[1];
$_POSID     = (int)$_posidx[2];

//----------------------------------------------------------------------------------
$datsql = " select t1.*, t2.item_amount_shipped, t3.item_title, t3.item_number_prod, t6.supp_rut, t6.supp_company,
                   CONCAT(t2.dlv_id, '_', t2.item_id, '_', t2.item_pos) 'posidx', t7.cat_id
            from orders_delivery t1
            INNER JOIN orders_delivery_items t2       ON t1.id = t2.dlv_id
            INNER JOIN item t3                        ON t2.item_id = t3.id
            INNER JOIN company_data t4                ON ( t1.dlv_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN supplier t6               ON ( t1.dlv_supplier_id = t6.id )
            LEFT OUTER JOIN item_productcats t7       ON t7.item_id = t3.id
            where
            t1.id          = {$_DLVID} and
            t2.item_id     = {$_ITEMID} and
            t2.item_pos    = {$_POSID}";
$dlv = $CON->select($datsql);
$dlv = $dlv[0];

if(!(int)$_REQUEST["sql_family"])
{
   $_REQUEST["sql_family"] = $dlv["cat_id"];
   $_REQUEST["sql_itemid"] = $_ITEMID;
}

$pcats = formatFullProductCats(getFullProductCats($CON, 0));

//----------------------------------------------------------------------------------
$sql = " select *
         from company_shops_storehouses
         where
         st_shop_id  = {$dlv["dlv_shop_id"]} and
         st_status   > 0
         order by st_name asc";
$sths = $CON->select($sql);

//----------------------------------------------------------------------------------
if((int)$_REQUEST["sql_family"])
{
   $sql = " select t1.id, t1.item_number_prod, t1.item_title
            from item t1
            INNER JOIN item_productcats t2 ON t1.id = t2.item_id
            where
            t1.item_status       > 0 and
            t1.item_released     > 0 and
            t2.cat_id            = {$_REQUEST["sql_family"]}
            order by t1.item_title, t1.item_number_prod";
   $selitems = $CON->select($sql);
}

if($_REQUEST["sql_date"] == "")
   $_REQUEST["sql_date"] = date('d.m.Y');

if(!(int)$_REQUEST["sql_amt"])
   $_REQUEST["sql_amt"] = (int)$dlv["item_amount_shipped"];


if((int)$_REQUEST["genajuste"])
{
   // $stk_num = createTransactionNumber($CON, $dlv["dlv_company_id"], "stockchange");
   $stk_num = "";

   $sql_proveedor = trim(addslashes($dlv["supp_company"]));
   $sql_invcnum   = trim(addslashes($_REQUEST["sql_invcnum"]));
   $stk_bookdate  = explode(".", $_REQUEST["sql_date"]);
   $stk_bookdate  = mktime(15, 0, 0, $stk_bookdate[1], $stk_bookdate[0], $stk_bookdate[2]);
   $currtme       = time();
   $_REQUEST["sql_amt"] = getPrice($_REQUEST["sql_amt"],2);
   $_REQUEST["sql_sthid"] = 0;

   $sql = " insert into stockchanges
            (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate,
             stk_negative, stk_crtdat, stk_crtusr, stk_isventainterna, stk_fixedsthid)
            VALUES
            ('{$stk_num}', 'Generado por recepción de procductos terminados, Guia: {$dlv["dlv_docnum"]}, Proveedor: {$sql_proveedor}',
              2, {$dlv["dlv_company_id"]}, {$dlv["dlv_shop_id"]}, {$stk_bookdate},
              0, {$currtme}, {$_SESSION["user_id"]}, 0,
              {$_REQUEST["sql_sthid"]})";
   $res = $CON->no_result($sql);
   if($res)
   {
      $stkid = mysql_insert_id();

      $sql = " insert into stockchanges_items
               (stk_id, item_id, item_pos, item_amount, item_type, item_st_id, item_costprice_brutto,
                item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes, item_charges_act,
                item_sellprice_brutto)
               VALUES
               ({$stkid}, {$_REQUEST["sql_itemid"]}, 0, {$_REQUEST["sql_amt"]}, 'item',
                {$_REQUEST["sql_sthid"]}, 0.00, 0.00, 0.00, 0.00, 0, 0)";
      $CON->no_result($sql);
      // bookStockChange($CON, $stkid);

      $sql = " update stockchanges
               set
               stk_status = 0
               where
               id = {$stkid}";
      $CON->no_result($sql);

      /*
      $sql = " update orders_delivery_items
               set
               item_dlv_externprod_adjrefid = {$stkid},
               item_dlv_externprod_invcnum  = '{$sql_invcnum}'
               where
               dlv_id      = {$_DLVID} and
               item_id     = {$_ITEMID} and
               item_pos    = {$_POSID}";
      $CON->no_result($sql);
      */
      $sql = " insert into orders_delivery_items_fabext
               (dlv_id, dlv_item_id, dlv_item_pos, adjrefid, invcnum)
               VALUES
               ({$_DLVID}, {$_ITEMID}, {$_POSID}, {$stkid}, '{$sql_invcnum}')";
      $CON->no_result($sql);
      ?>
      <script language="Javascript">
         parent.document.xform_itemsearch.submit();
      </script>
      <?php
      exit;
   }
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
      require_once("../../../jscripts/sourcen.php");
      ?>
   </script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <script type="text/javascript" src="/libs/jscripts/jquery.table_navigation.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<style type="text/css"><!-- @import url(/libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="/libs/jscripts/datepicker/datepicker.js"></script>
<script language="Javascript">
   function genAjuste()
   {
      if(checkform(new Array(document.xform_itemsearch.sql_family, document.xform_itemsearch.sql_itemid, document.xform_itemsearch.sql_amt,
                             document.xform_itemsearch.sql_date, document.xform_itemsearch.sql_invcnum)))
      {
         document.xform_itemsearch.genajuste.value = '1';
         document.xform_itemsearch.submit();
      }
   }
</script>
<div style="height:3px"></div>
<form action="fabricacion.externa.fancy.php" method="post" name="xform_itemsearch" class="fokusfirst">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="genajuste" value="">
<input type="hidden" name="execsave" value="">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="module" value="<?=$_REQUEST["module"]?>">
<input type="hidden" name="posidx" value="<?=$_REQUEST["posidx"]?>">
<?=Nifty_printH("box2", "100%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="120">
   <col width="">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de la guia enviada</td>
</tr>
<tr>
   <td class="content_rowl">Proveedor</td>
   <td class="content_row"><?=$dlv["supp_company"]?></td>
</tr>
<tr>
   <td class="content_rowl">Guia</td>
   <td class="content_row"><?=$dlv["dlv_docnum"]?></td>
</tr>
<tr>
   <td class="content_rowl">Fecha Guia</td>
   <td class="content_row"><?=date("d.m.Y", $dlv["dlv_delivery_date"])?></td>
</tr>
<tr>
   <td class="content_rowl">Producto enviado</td>
   <td class="content_row"><?=$dlv["item_number_prod"]?> - <?=$dlv["item_title"]?></td>
</tr>
<tr>
   <td class="content_rowl">Cantidad enviado</td>
   <td class="content_row"><?=printPrice($dlv["item_amount_shipped"],2)?></td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("box2", "100%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="120">
   <col width="">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Ingreso del producto terminado</td>
</tr>
<tr>
   <td class="content_rowl">Familia</td>
   <td class="content_row">
      <select class="text" style="width:100%" name="sql_family"
      onchange="document.xform_itemsearch.submit();">
         <option value="">Seleccione</option>
         <?php
         foreach($pcats AS $pcat)
         {  ?>
            <option value="<?=$pcat["id"]?>" <?if($pcat["id"] == $_REQUEST["sql_family"]) echo "selected"?>><?=$pcat["cat_title"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<?php
if((int)$_REQUEST["sql_family"])
{  ?>
   <tr>
      <td class="content_rowl">Producto</td>
      <td class="content_row">
         <select class="text" style="width:100%" name="sql_itemid"
         onchange="document.xform_itemsearch.submit();">
            <option value="">Seleccione</option>
            <?php
            foreach($selitems AS $selitem)
            {  ?>
               <option value="<?=$selitem["id"]?>" <?if($selitem["id"] == $_REQUEST["sql_itemid"]) echo "selected"?>><?=$selitem["item_title"]?> | <?=$selitem["item_number_prod"]?></option>
               <?php
            }
            ?>
         </select>
      </td>
   </tr>
   <?php

}
if((int)$_REQUEST["sql_itemid"])
{  ?>
   <tr>
      <td class="content_rowl">Cantidad</td>
      <td class="content_row">
         <input type="text" class="text" style="width:80px;text-align:center" name="sql_amt" value="<?=$_REQUEST["sql_amt"]?>">
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Fecha</td>
      <td class="content_row">
         <input type="text" style="width:80px" id="sql_date" name="sql_date"
         class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
         value="<?=$_REQUEST["sql_date"]?>">
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Nº Factura</td>
      <td class="content_row">
         <input type="text" style="width:80px" id="sql_invcnum" name="sql_invcnum" class="text"
         value="<?=$_REQUEST["sql_invcnum"]?>">
      </td>
   </tr>
   <tr style="display:none">
      <td class="content_rowl">Bodega</td>
      <td class="content_row">
         <select class="text" style="width:100%" name="sql_sthid">
            <option value="">Seleccione</option>
            <?php
            foreach($sths AS $sth)
            {  ?>
               <option value="<?=$sth["id"]?>" <?if($sth["id"] == $_REQUEST["sql_sthid"]) echo "selected"?>><?=$sth["st_name"]?></option>
               <?php
            }
            ?>
         </select>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
if((int)$_REQUEST["sql_itemid"])
{  ?>
   <?=Nifty_printH("boxopt_b", "100%")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td align="right">
         <?php
         printButton("Guardar", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) genAjuste()", "gear");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <?php
}
?>
</form>
</body>
</html>