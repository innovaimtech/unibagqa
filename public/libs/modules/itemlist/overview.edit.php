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
   $sql = " select item_title
            from itemlist
            where
            id = {$_REQUEST["id"]} ";
   $title = $CON->select($sql);
   
   $title = "Cambiar pack: {$title[0]["item_title"]}";

   
}
else
   $title = "Agregar pack";
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
<?=Nifty_printH("boxopt_t", "980")?>
<table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td width="14%" style="padding-right:5px">
      <?php
      if($_REQUEST["subcatexec"] == "basic" || $_REQUEST["subcatexec"] == "supplierdiscounts")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Datos básicos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}", "", "inbox-image");
      ?>
   </td>
   <td width="14%" style="padding-right:5px">
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
   <td width="14%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "discounts")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Condiciones de venta", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=discounts&id={$_REQUEST["id"]}&itemtype=itemlist", "", "calculator");
      }
      ?>
   </td>
   <td width="16%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "improve")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Modificación externa", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=improve&id={$_REQUEST["id"]}&itemtype=item", "", "drill");
      }
      ?>
   </td>
   <td width="16%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "pricehist")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Historia de precios", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=pricehist&id={$_REQUEST["id"]}&itemtype=itemlist", "", "currency");
      }
      ?>
   </td>
   <td width="13%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "trans")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Movimientos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=trans&id={$_REQUEST["id"]}&itemtype=itemlist", "", "document");
      }
      ?>
   </td>
   <td width="14%">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "pix")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Imagenes", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=pix&id={$_REQUEST["id"]}", "", "image");
      }
      ?>
   </td>
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
elseif($_REQUEST["subcatexec"] == "shops")
   require_once("data.shops.php");
elseif($_REQUEST["subcatexec"] == "pricehist")
   require_once("./libs/modules/items/data.history.php");
elseif($_REQUEST["subcatexec"] == "discounts")
   require_once("./libs/modules/items/data.discounts.php");
elseif($_REQUEST["subcatexec"] == "trans")
   require_once("./libs/modules/items/data.trans.php");
elseif($_REQUEST["subcatexec"] == "supplierdiscounts")
   require_once("./libs/modules/items/data.suppliers.discounts.php");
elseif($_REQUEST["subcatexec"] == "improve")
   require_once("data.improve.php");
//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{  ?>
   <script language="JavaScript">
   <?php

   //----------------------------------------------------------------------------------
   $sql = " select count(*) 'cc'
            from itemlist_shops
            where
            item_id = {$_REQUEST["id"]}";
   $shopcount = $CON->select($sql);
   if(!(int)$shopcount[0]["cc"] && $_REQUEST["subcatexec"] != "shops")
   {  ?>
      document.all.idx_ul_shops.className = 'postnav_del';
      <?php
   }
   ?>
   </script>
   <?php
}
?>