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
   $sql = " select dlv_num
            from orders_delivery 
            where
            id = {$_REQUEST["id"]} ";
   $title = $CON->select($sql);

   $title = "Cambiar guia de despacho: {$title[0]["dlv_num"]}";
}
else
   $title = "Agregar guia de despacho";
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
<?=Nifty_printH("boxopt_t", "980")?>
<table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td width="20%" style="padding-right:5px">
      <?php
      if($_REQUEST["subcatexec"] == "basic")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Datos básicos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}&user_pricesell_perm={$_REQUEST["user_pricesell_perm"]}", "", "cookies");
      ?>
   </td>
   <td class="content_row_clear">&nbsp;</td>
   <td width="110" align="right" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
         printButton("Anexos", "postnav", "javascript:void(0)", "showFancybox('/libs/modules/docs_management/overview.php?mode=orders_delivery&id={$_REQUEST["id"]}', 'iframe', 850, 450, 'auto')", "scanner--plus", 110);
      ?>
   </td>
   <?php
   if((int)$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["L"])
   {  ?>
      <td class="content_row_clear" width="53" style="padding-right:5px">
      <?php
      printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["L"]}", "", "arrow-180", 53);
      ?>
      </td>
      <?php
   }
   if((int)$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["N"])
   {  ?>
      <td class="content_row_clear" width="53" style="padding-right:2px">
      <?php
      printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["N"]}", "", "arrow", 53);
      ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<input type="hidden" name="idx_redirect_id" value="<?=$_REQUEST["id"]?>">
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subcatexec"] == "basic")
   require_once("data.basic.php");
?>