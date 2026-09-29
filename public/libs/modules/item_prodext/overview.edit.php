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
            from prod_item_ext
            where
            id = {$_REQUEST["id"]} ";
   $invoice = $CON->select($sql);
   
   $title = "Cambiar modificación: ".sprintf("%07s",$invoice[0]["id"]);
}
else
   $title = "Agregar modificación";
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
   <?php
   if($_REQUEST["from"] != "report")
   {  ?>
      <td width="20%" style="padding-right:5px;">
         <?php
         if($_REQUEST["subcatexec"] == "basic")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Datos básicos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}", "", "cookies");
         ?>
      </td>
      <td width="20%" style="padding-right:5px;">
         <?php
         if($_REQUEST["subcatexec"] == "generate")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Regreso de Productos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=generate&id={$_REQUEST["id"]}", "", "gear");
         ?>
      </td>
      <td class="content_row_clear">&nbsp;</td>
      <?php
   }
   ?>
   <td width="20%" style="padding-right:5px" id="idx_ulovw_orders_delivery_show">
      <?php
      if($_REQUEST["subcatexec"] == "orders_delivery_show")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Guia de despacho", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=orders_delivery_show&id={$_REQUEST["id"]}", "", "folder-share");
      ?>
   </td>
   <td width="110" align="right">
      <?php
      if($_REQUEST["from"] != "report")
         $windowheight = "450";
      else
         $windowheight = "380";
      if($_REQUEST["id"] != "")
         printButton("Anexos", "postnav", "javascript:void(0)", "showFancybox('/libs/modules/docs_management/overview.php?mode=item_prodext&id={$_REQUEST["id"]}', 'iframe', 850, {$windowheight}, 'auto')", "scanner--plus", 110);
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
if($_REQUEST["subcatexec"] == "generate")
   require_once("data.generate.php");
elseif($_REQUEST["subcatexec"] == "orders_delivery_show")
{
   $sql = " select id
            from orders_delivery
            where
            dlv_prodext_generated = {$_REQUEST["id"]}";
   $dlvid = $CON->select($sql);
   $dlvid = (int)$dlvid[0]["id"];

   $thisid           = $_REQUEST["id"];
   $_REQUEST["id"]   = $dlvid;
   require_once("./libs/modules/orders_delivery/data.basic.php");
   $_REQUEST["id"]   = $thisid;
}
?>
<script language="JavaScript">
<?php
//----------------------------------------------------------------------------------
$sql = " select req_status
         from prod_item_ext
         where
         id = {$_REQUEST["id"]} ";
$prod = $CON->select($sql);

//----------------------------------------------------------------------------------
if((int)$prod[0]["req_status"] < 2)
{  ?>
   document.getElementById('idx_ulovw_orders_delivery_show').style.display = 'none';
   <?php
}
?>
</script>