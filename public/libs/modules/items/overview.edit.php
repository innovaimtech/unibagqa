<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subcatexec"] == "")
   $_REQUEST["subcatexec"] = "basic";

if($_REQUEST["id"] != "")
{
   $sql = " select *
            from item 
            where
            id = {$_REQUEST["id"]} ";
   $item = $CON->select($sql);
   
   $title = "Cambiar articulo: {$item[0]["item_title"]}";
}
else
   $title = "Agregar articulo";
?>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><div id="idx_status_msg"><?=$savemsg?></div></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<input type="hidden" name="idx_redirect_id" value="<?=$_REQUEST["id"]?>">
<?=Nifty_printH("boxoptx_t", "980")?>
<table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td width="12%" style="padding-right:5px">
      <?php
      if($_REQUEST["subcatexec"] == "basic" || $_REQUEST["subcatexec"] == "supplierdiscounts")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Datos básicos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}", "", "cookies");
      ?>
   </td>
   <td width="12%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "shops")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Sucursales", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=shops&id={$_REQUEST["id"]}", "", "home", "", "idx_ul_shops");
      }
      ?>
   </td>
   <!--
   <td width="12%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "discounts")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Condiciones/Venta", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=discounts&id={$_REQUEST["id"]}&itemtype=item", "", "calculator");
      }
      ?>
   </td>

   <td width="12%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "improve")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Unidad de Compra", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=improve&id={$_REQUEST["id"]}&itemtype=item", "", "drill");
      }
      ?>
   </td>
   
   <td width="12%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "packs")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Configuración Pack", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=packs&id={$_REQUEST["id"]}&itemtype=item", "", "drill");
      }
      ?>
   </td>
   -->
   <td width="12%" style="padding-right:5px" id="idx_volprices">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "volprices")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("\$ por cantidad", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=volprices&id={$_REQUEST["id"]}&itemtype=item", "", "balance");
      }
      ?>
   </td>
   <td width="12%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "pricehist")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Historia/Precios", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=pricehist&id={$_REQUEST["id"]}&itemtype=item", "", "currency");
      }
      ?>
   </td>
   <td width="12%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "trans")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Movimientos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=trans&id={$_REQUEST["id"]}&itemtype=item", "", "document");
      }
      ?>
   </td>
   <td width="12%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "pix")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Imagen", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=pix&id={$_REQUEST["id"]}", "", "image");
      }
      ?>
   </td>
   <?php
   if($_REQUEST["id"] != "" and $item[0]["item_fabricate_act"])
   {
      ?>   
      <td width="12%" style="padding-right:5px" id="idx_prec_fab">
         <?php
        if($_REQUEST["subcatexec"] == "prec_fab")
            $btype = "postnav_act";
         else
            $btype = "postnav";
         printButton("Precio Fabricación", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=prec_fab&id={$_REQUEST["id"]}&itemtype=item", "", "drill");
      ?>
      </td>   
      <?php
   }
   ?>
   <td width="12%" >
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "woocommerce")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Woocommerce", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=woocommerce&id={$_REQUEST["id"]}", "", "globe");
      }
      ?>
   </td>
   
   <!--
   <td width="10%">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "filter")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Filtros", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=filter&id={$_REQUEST["id"]}", "", "image");
      }
      ?>
   </td>
   -->
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subcatexec"] == "basic")
   require_once("data.basic.php");
elseif($_REQUEST["subcatexec"] == "pix")
   require_once("data.pix.php");
elseif($_REQUEST["subcatexec"] == "suppliers")
   require_once("data.suppliers.php");
elseif($_REQUEST["subcatexec"] == "supplierdiscounts")
   require_once("data.suppliers.discounts.php");
elseif($_REQUEST["subcatexec"] == "shops")
   require_once("data.shops.php");
elseif($_REQUEST["subcatexec"] == "storehouses")
   require_once("data.storehouses.php");
elseif($_REQUEST["subcatexec"] == "pricehist")
   require_once("data.history.php");
elseif($_REQUEST["subcatexec"] == "discounts")
   require_once("data.discounts.php");
elseif($_REQUEST["subcatexec"] == "improve")
   require_once("data.improve.php");
elseif($_REQUEST["subcatexec"] == "itemsrel")
   require_once("data.itemsrel.php");
elseif($_REQUEST["subcatexec"] == "trans")
   require_once("data.trans.php");
elseif($_REQUEST["subcatexec"] == "filter")
   require_once("data.filter.php");
elseif($_REQUEST["subcatexec"] == "packs")
   require_once("data.packs.php");
elseif($_REQUEST["subcatexec"] == "volprices")
   require_once("data.volprices.php");
elseif($_REQUEST["subcatexec"] == "woocommerce")
   require_once("data.woocommerce.php");
elseif($_REQUEST["subcatexec"] == "prec_fab")
   require_once("data.resumen.php");

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{  ?>
   <script language="JavaScript">
   <?php

   //----------------------------------------------------------------------------------
   $sql = " select count(*) 'cc'
            from item_shops
            where
            item_id = {$_REQUEST["id"]}";
   $shopcount = $CON->select($sql);
   if(!(int)$shopcount[0]["cc"] && $_REQUEST["subcatexec"] != "shops")
   {  ?>
      document.all.idx_ul_shops.className = 'postnav_del';
      <?php
   }

   $sql = " select item_fabricate_act
            from item
            where
            id = {$_REQUEST["id"]}";
   $itemdata = $CON->select($sql);
   $itemdata = $itemdata[0];

   if((int)$itemdata["item_fabricate_act"])
   {  ?>
      document.getElementById('idx_volprices').style.display = 'none';
      <?php
   }
   ?>
   </script>
   <?php
}
?>