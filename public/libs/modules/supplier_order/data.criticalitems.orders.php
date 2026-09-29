<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$sql = " select *
         from supplier_order
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
if($_REQUEST["subsubexec"] == "save")
{
   $currtme       = time();
   $suppliertaxes = $headdata["sord_taxes"];

   //----------------------------------------------------------------------------------
   $sql = " select MAX(item_pos) 'item_pos'
            from supplier_order_items
            where
            sord_id  = {$_REQUEST["id"]}";
   $poscounter = $CON->select($sql);
   $poscounter = $poscounter[0]["item_pos"];

   //----------------------------------------------------------------------------------
   if($poscounter != "")
      $poscounter = (int)$poscounter +1;
   else
      $poscounter = 0;

   //----------------------------------------------------------------------------------
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_id_") !== false && strpos($reqkey, "item_id_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
         $_REQUEST["item_amount_{$idx}"]  = getPrice($_REQUEST["item_amount_{$idx}"],2);

         if($_REQUEST["item_id_{$idx}"] != "" && $_REQUEST["item_amount_{$idx}"] > 0.00)
         {
            $sql_arr       = explode("#",$_REQUEST["item_id_{$idx}"]);
            $sql_id        = (int)$sql_arr[0];
            $sql_type      = $sql_arr[1];

            //----------------------------------------------------------------------------------
            $sql = " select item_costprice_netto, item_costprice_usd, item_costprice_taxes_perc
                     from {$sql_type}_suppliers
                     where
                     item_id = {$sql_id} and
                     supplier_id = {$headdata["sord_supplier_id"]}";
            $costdata = $CON->select($sql);

            //----------------------------------------------------------------------------------
            if($suppliertaxes)
               $sql_costnetto = (float)$costdata[0]["item_costprice_netto"];
            else
               $sql_costnetto = (float)$costdata[0]["item_costprice_usd"];

            //----------------------------------------------------------------------------------
            $sql_taxesperc = (float)$costdata[0]["item_costprice_taxes_perc"];
            $sql_taxes     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_costnetto / 100 * $sql_taxesperc);
            $sql_costprice = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_costnetto + $sql_taxes);

            //----------------------------------------------------------------------------------
            $sql = " insert into supplier_order_items
                     (sord_id, item_id, item_pos, item_amount, item_type, item_costprice_brutto,
                      item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes)
                     VALUES
                     ({$_REQUEST["id"]}, {$sql_id}, {$poscounter}, {$_REQUEST["item_amount_{$idx}"]}, '{$sql_type}',
                      {$sql_costprice}, {$sql_taxesperc}, {$sql_costnetto}, {$sql_taxes} )";
            $CON->no_result($sql);

            recalcSupplierOrderItem($CON, $_REQUEST["id"], $sql_id, $poscounter);
            
            $poscounter++;
         }
      }
   }
   
   $sql = " update supplier_order
            set
            sord_updusr = {$_SESSION["user_id"]},
            sord_upddat = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   recalcSupplierOrder($CON, $_REQUEST["id"]);
   ?>
   <script language="Javascript">
      location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=basic&id=<?=$_REQUEST["id"]?>';
   </script>
   <?php
   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["sql_datefrom"] == "")
   $_REQUEST["sql_datefrom"] = date('d.m.Y', time() - (7 * 86400));
if($_REQUEST["sql_dateto"] == "")
   $_REQUEST["sql_dateto"] = date('d.m.Y');

//----------------------------------------------------------------------------------
if($_REQUEST["subsubexec"] == "search")
{
   $sqldate_from           = getDateFromString($_REQUEST["sql_datefrom"]);
   $sqldate_to             = getDateFromString($_REQUEST["sql_dateto"], false);
   $_REQUEST["sql_item"]   = trim(addslashes(str_replace("*","%",$_REQUEST["sql_item"])));
   $_REQUEST["sql_pcat"]   = (int)$_REQUEST["sql_pcat"];

   //----------------------------------------------------------------------------------
   if($_REQUEST["sql_item"] != "")
      $sqladd .= " and ( t3.item_title like '%{$_REQUEST["sql_item"]}%' or t3.item_number_prod like '%{$_REQUEST["sql_item"]}%' ) ";
   if($_REQUEST["sql_pcat"] > 0)
      $sqladd .= " and t6.cat_id = {$_REQUEST["sql_pcat"]} ";

   //----------------------------------------------------------------------------------
   $sql = " select t2.item_id, t2.item_type, t3.item_title, t3.item_number_prod, t4.item_costprice_netto,
                   t4.item_costprice_taxes_perc, SUM(t2.item_amount) 'amount'
            from orders t1
            INNER JOIN orders_items t2          ON t1.id = t2.req_id
            INNER JOIN item t3                  ON t2.item_id = t3.id
            INNER JOIN item_suppliers t4        ON ( t2.item_id = t4.item_id and t4.supplier_id = {$headdata["sord_supplier_id"]} )
            INNER JOIN item_shops t5            ON ( t2.item_id = t5.item_id and t5.shop_id = {$headdata["sord_shop_id"]} )
            LEFT OUTER JOIN item_productcats t6 ON ( t2.item_id = t6.item_id )
            where
            t1.req_status        > 1 and
            t2.item_type         = 'item' and
            t3.item_status       = 1 and
            t3.item_released     = 1 and
            t3.item_purchasable  = 1 and
            t1.req_crtdat between {$sqldate_from} and {$sqldate_to}
            {$sqladd}
            group by 1, 2 ";

   //----------------------------------------------------------------------------------
   $sql .= "UNION ALL
            select t2.item_id, t2.item_type, t3.item_title, t3.item_number_prod, t4.item_costprice_netto,
                   t4.item_costprice_taxes_perc, SUM(t2.item_amount) 'amount'
            from orders t1
            INNER JOIN orders_items t2                   ON t1.id = t2.req_id
            INNER JOIN itemlist t3                       ON t2.item_id = t3.id
            INNER JOIN itemlist_suppliers t4             ON ( t2.item_id = t4.item_id and t4.supplier_id = {$headdata["sord_supplier_id"]} )
            INNER JOIN itemlist_shops t5                 ON ( t2.item_id = t5.item_id and t5.shop_id = {$headdata["sord_shop_id"]} )
            LEFT OUTER JOIN item_productcats_itemlist t6 ON ( t2.item_id = t6.item_id )
            where
            t1.req_status        > 1 and
            t2.item_type         = 'itemlist' and
            t3.item_status       = 1 and
            t3.item_released     = 1 and
            t3.item_purchasable  = 1 and
            t1.req_crtdat between {$sqldate_from} and {$sqldate_to}
            {$sqladd}
            group by 1, 2
            order by 4,3,7 desc";
   $orders = $CON->select($sql);
}
//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst"
      onsubmit="return checkform(new Array(this.sql_datefrom, this.sql_dateto))">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="exec" value="edit">
      <input type="hidden" name="subcatexec" value="criticalitems">
      <input type="hidden" name="subsubexec" value="search">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="crtitemsmode" value="<?=$_REQUEST["crtitemsmode"]?>">
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
         <col width="100">
         <col width="385">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="5">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Artículo</td>
         <td class="content_row">
            <input type="text" style="width:375px" id="sql_item" name="sql_item" class="text"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?=str_replace("%","*",$_REQUEST["sql_item"])?>">
         </td>
         <td class="content_rowl">Periodo *</td>
         <td class="content_row">
            <input type="text" style="width:70px" id="sql_datefrom" name="sql_datefrom"
            class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$_REQUEST["sql_datefrom"]?>">
            &nbsp;-&nbsp;
            <input type="text" style="width:70px" id="sql_dateto" name="sql_dateto"
            class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$_REQUEST["sql_dateto"]?>">
         </td>
      </tr>
      <tr>
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
         <td class="content_row" align="right" colspan="2">
            <table border="0" cellpadding="0" cellspacing="0" width="270">
            <tr>
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
      <?=Nifty_printF(false)?>
      </form>
   </td>
</tr>
<tr>
   <td>
      <?=Nifty_printH("box1", "980")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <form action="index.php" method="post" name="form_shppos">
      <input type="hidden" name="exec" value="edit">
      <input type="hidden" name="subcatexec" value="criticalitems">
      <input type="hidden" name="subsubexec" value="save">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="crtitemsmode" value="<?=$_REQUEST["crtitemsmode"]?>">
      <colgroup>
         <col>
         <col width="100">
         <col width="70">
         <col width="70">
         <col width="120">
         <col width="60">
         <col width="60">
      </colgroup>
      <tr>
         <td class="content_tbl_subheader">Artículo</td>
         <td class="content_tbl_subheader">Unidad</td>
         <td class="content_tbl_subheader" align="center">Vendido</td>
         <td class="content_tbl_subheader" align="left">Compra</td>
         <td class="content_tbl_subheader" align="left">Unidad de compra</td>
         <td class="content_tbl_subheader" align="center">Stock<br>actual</td>
         <td class="content_tbl_subheader" align="center">Stock<br>transito</td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($orders) && $orders != false; $x++)
      {
         $unitdesc         = getItemUnitDesc($CON, $orders[$x]["item_id"], $orders[$x]["item_type"]);
         $stock_current    = getItemShopCurrentStock($CON, $headdata["sord_shop_id"], $orders[$x]["item_id"], $orders[$x]["item_type"], true);
         $transtock        = getItemShopTransStock($CON, $headdata["sord_shop_id"], $orders[$x]["item_id"], $orders[$x]["item_type"], true);
         $itemalternatives = getItemOrderAlternativeUnits($CON, $orders[$x]["item_id"], $orders[$x]["item_type"], $headdata["sord_shop_id"], $headdata["sord_supplier_id"]);
         ?>
         <tr bgcolor="<?=getRowColor($x)?>">
            <td class="content_row"><?=$orders[$x]["item_number_prod"]?> - <?=$orders[$x]["item_title"]?></td>
            <td class="content_row"><nobr><?=$unitdesc?></nobr></td>
            <td class="content_row" align="center">
               <?php
               $urlparams  = "sord_supplier_id={$headdata["sord_supplier_id"]}&sord_shop_id={$headdata["sord_shop_id"]}&";
               $urlparams .= "sql_itemid={$orders[$x]["item_id"]}&sql_item_type={$orders[$x]["item_type"]}&sql_datefrom={$_REQUEST["sql_datefrom"]}&sql_dateto={$_REQUEST["sql_dateto"]}";
               ?>
               <div style="cursor:pointer" onclick="showFancybox('/libs/modules/supplier_order/show.itemorders.php?<?=$urlparams?>', 'iframe', 800, 450, 'auto')">
                  <?=printPrice($orders[$x]["amount"],2)?>
               </div>
            </td>
            <td class="content_row" align="left">
               <input type="text" class="text" style="width:50px;text-align:right"
               onfocus="markfield(this,0)" onblur="markfield(this,1)"
               name="item_amount_<?=$x?>" id="item_amount_<?=$x?>"
               value="">
            </td>
            <td class="content_row" align="left">
               <?php
               if(count($itemalternatives) && $itemalternatives != false)
               {  ?>
                  <select name="item_id_<?=$x?>" id="item_id_<?=$x?>" class="text" style="width:115px">
                     <option value="<?=$orders[$x]["item_id"]?>#<?=$orders[$x]["item_type"]?>"><?=$unitdesc?></option>
                     <?php
                     for($y = 0; $y < count($itemalternatives) && $itemalternatives != false; $y++)
                     {  ?>
                        <option value="<?=$itemalternatives[$y]["item_id"]?>#<?=$itemalternatives[$y]["item_type"]?>">
                           <?=$itemalternatives[$y]["unit_name"]?>
                        </option>
                        <?php
                     }
                     ?>
                  </select>
                  <?php
               }
               else
               {  ?>
                  <input type="hidden" name="item_id_<?=$x?>" value="<?=$orders[$x]["item_id"]?>#<?=$orders[$x]["item_type"]?>">
                  <?=$unitdesc?>
                  <?php
               }
               ?>
            </td>
            <td class="content_row" align="center">
               <?php
               printFancyBoxStock($CON, $headdata["sord_shop_id"], $orders[$x]["item_id"], $orders[$x]["item_type"], "storehousestock");
               ?>
            </td>
            <td class="content_row" align="center">
               <?php
               printFancyBoxStock($CON, $headdata["sord_shop_id"], $orders[$x]["item_id"], $orders[$x]["item_type"], "transstock");
               ?>
            </td>
         </tr>
         <?php
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="10" align="center">
               <br>
               <?php
               if($_REQUEST["subsubexec"] == "search")
                  echo "<b class='msg_save_err'>No hay datos disponibles.</b>";
               else
                  echo "<b class='msg_save_ok'>Por favor ejecuta la búsqueda.</b>";
               ?>
               <br><br>
            </td>
         </tr>
         <?php
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
      <?=Nifty_printH("boxopt_b", "980")?>
      <table border="0" cellspacing="0" cellpadding="0" width="100%">
      <tr>
         <td width="130">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&id={$_REQUEST["id"]}", "", "arrow-180");
            ?>
         </td>
         <td>&nbsp;</td>
         <?php
         if($x)
         {  ?>
            <td align="right" width="130">
               <?php
               printButton("Agregar artículos", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "plus", 150);
               ?>
            </td>
            <?php
         }
         ?>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      </form>
   </td>
</tr>
</table>
