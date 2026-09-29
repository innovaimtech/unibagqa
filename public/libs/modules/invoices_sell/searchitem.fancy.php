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

$sql = " select *
         from invoices_sell
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

$_REQUEST["sql_stext"] = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql = " select distinct t1.id, t1.item_title, t3.itemshop_sellprice_netto, t1.item_number_prod, 'item_type' 'I'
            from item t1
            LEFT OUTER JOIN item_suppliers t2     ON t1.id = t2.item_id
            INNER JOIN item_shops t3         ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["invc_shop_id"]} )
            LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item') ";
   if($_REQUEST["sql_pcat"])
      $sql .= " INNER JOIN item_productcats t6 ON ( t1.id = t6.item_id and t6.cat_id = {$_REQUEST["sql_pcat"]}) ";
   $sql .= "where
            t1.item_status       = 1 and
            t1.item_released     = 1 and
            t1.item_sellable     = 1 and
            t3.itemshop_sellprice_netto > 0.00 and
            (
               t1.item_title        like '%{$_REQUEST["sql_stext"]}%' or
               t1.item_number       like '%{$_REQUEST["sql_stext"]}%' or
               t1.item_number_prod  like '%{$_REQUEST["sql_stext"]}%' or
               t2.item_code         like '%{$_REQUEST["sql_stext"]}%' or
               tx.item_barcode      = '{$_REQUEST["sql_stext"]}'
            )
            UNION ALL
            select distinct t1.id, t1.item_title, t3.itemshop_sellprice_netto, t1.item_number_prod, 'item_type' 'L'
            from itemlist t1
            LEFT OUTER JOIN itemlist_suppliers t2    ON t1.id = t2.item_id
            INNER JOIN itemlist_shops t3        ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["invc_shop_id"]} ) ";
   if($_REQUEST["sql_pcat"])
      $sql .= " INNER JOIN item_productcats_itemlist t6 ON ( t1.id = t6.item_id and t6.cat_id = {$_REQUEST["sql_pcat"]}) ";
   $sql .= "where
            t1.item_status       = 1 and
            t1.item_released     = 1 and
            t1.item_sellable     = 1 and
            t3.itemshop_sellprice_netto > 0.00 and
            (
               t1.item_title        like '%{$_REQUEST["sql_stext"]}%' or
               t1.item_number       like '%{$_REQUEST["sql_stext"]}%' or
               t1.item_number_prod  like '%{$_REQUEST["sql_stext"]}%' or
               t2.item_code         like '%{$_REQUEST["sql_stext"]}%'
            )
            order by 2
            LIMIT 0, 200";
   $items = $CON->select($sql);
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
      function setSelData(idx, type)
      {
parent.document.getElementsByName('xf_search_<?=$_REQUEST["rowcount"]?>')[0].value='';
parent.document.getElementById('idxifrsrc').src='./libs/modules/invoices_sell/searchitem.php?id=<?=$_REQUEST["id"]?>&storehousemode=<?=$_REQUEST["storehousemode"]?>&rowcount=<?=$_REQUEST["rowcount"]?>&itemid=' +idx +'&itemtype=' +type;
         parent.$.fancybox.close();
parent.document.getElementsByName('item_id_<?=$_REQUEST["rowcount"]?>')[0].focus();
      }
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
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="searchitem.fancy.php" method="post" name="xform_itemsearch" class="fokusfirst"
      onsubmit="return checkform(new Array(this.sql_stext))">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="rowcount" value="<?=$_REQUEST["rowcount"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="storehousemode" value="<?=$_REQUEST["storehousemode"]?>">
      <?=Nifty_printH("box1", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
         <col width="100">
         <col width="385">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Palabra</td>
         <td class="content_row">
            <input name="sql_stext" type="text" class="text" style="width:375px"
            value="<?=str_replace("%","*",$_REQUEST["sql_stext"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
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
               <td align="right">&nbsp;</td>
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
      <?=Nifty_printF()?>
      </form>
   </td>
</tr>
</table>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="80">
   <col>
   <col width="90">
   <col width="90">
   <col width="85">
</colgroup>
<thead>
<tr>
   <td class="content_tbl_subheader">Número</td>
   <td class="content_tbl_subheader">Nombre</td>
   <td class="content_tbl_subheader">Precio (neto)</td>
   <td class="content_tbl_subheader">Unidad</td>
   <td class="content_tbl_subheader" align="center">Opciones</td>
</tr>
</thead>
<tbody>
<?php
for($x = 0; $x < count($items) && $items != false; $x++)
{
   if($items[$x]["item_type"] == "item_typeI")
      $items[$x]["item_type"] = "item";
   else
      $items[$x]["item_type"] = "itemlist";
      
   $unitdesc      = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
   $custprice     = getCustomerPLPrice($CON, $headdata["invc_cust_id"], $items[$x]["id"], $items[$x]["item_type"]);

   if((int)$custprice["item_id"])
   {
      $items[$x]["itemshop_sellprice_netto"]       = (float)$custprice["item_sellprice_netto"];
      $items[$x]["itemshop_sellprice_taxes_perc"]  = (float)$custprice["item_sellprice_taxes_perc"];
   }
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" class="viewable_records">
      <td class="content_row"><nobr><?=$items[$x]["item_number_prod"]?></nobr></td>
      <td class="content_row"><?=$items[$x]["item_title"]?>&nbsp;</td>
      <td class="content_row" align="left">$&nbsp;<?=printprice($items[$x]["itemshop_sellprice_netto"])?></td>
      <td class="content_row"><?=$unitdesc?>&nbsp;</td>
      <td class="content_row" align="center">
         <?php
         printButton("Seleccionar", "postnav_save", "javascript: deactivateFormChange()\" class=\"activation", "setSelData('{$items[$x]["id"]}', '{$items[$x]["item_type"]}')", "tick-circle-frame");
         ?>
      </td>
   </tr>
   <?php
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="6" align="center" valign="middle" height="30">
         <br>
         <b class="msg_save_err">No hay datos disponibles.</b>
         <br><br>
      </td>
   </tr>
   <?php
}
?>
</tbody>
</table>
<?=Nifty_printF()?>
<script type="text/javascript">
jQuery.tableNavigation({
   table_selector: 'table.navigateable',
   row_selector: 'table.navigateable tbody tr.viewable_records',
   selected_class: 'selected',
   activation_selector: 'a.activation',
   bind_key_events_to_links: true,
   focus_links_on_select: true,
   select_event: 'click',
   activate_event: 'dblclick',
   activation_element_activate_event: 'click',
   scroll_overlap: 20,
   cookie_name: null,
   focus_tables: true,
   focused_table_class: 'focused',
   jump_between_tables: false,
   disabled: false,
   on_activate: null,
   on_select: null
});
</script>
</body>
</html>