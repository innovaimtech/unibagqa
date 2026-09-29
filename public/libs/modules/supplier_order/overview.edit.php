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
   $sql = " select sord_number, sord_status
            from supplier_order
            where
            id = {$_REQUEST["id"]} ";
   $sorddata = $CON->select($sql);
   
   $title = "Cambiar orden de compra: {$sorddata[0]["sord_number"]}";
}
else
   $title = "Agregar orden de compra";
?>
<table border="0" cellpadding="0" cellspacing="0" width="1180">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><div id="idx_status_msg"><?=$savemsg?></div></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<input type="hidden" name="idx_redirect_id" value="<?=$_REQUEST["id"]?>">
<?=Nifty_printH("boxopt_t", "1180")?>
<table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td width="20%" style="padding-right:5px">
      <?php
      if($_REQUEST["subcatexec"] == "basic")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Datos básicos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}", "", "cookies");
      ?>
   </td>
   <td class="content_row_clear" width="55%">&nbsp;</td>
   <!--
   <td width="25%" align="right" id="idx_ul_criticalitems" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "criticalitems")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Agregar artículos requeridos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=criticalitems&id={$_REQUEST["id"]}", "", "plus");
      }
      ?>
   </td>
   -->
   <td width="" align="right" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
         printButton("Anexos", "postnav", "javascript:void(0)", "showFancybox('/libs/modules/docs_management/overview.php?mode=supplier_order&id={$_REQUEST["id"]}', 'iframe', 850, 450, 'auto')", "scanner--plus", 110);
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
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subcatexec"] == "basic")
   require_once("data.basic.php");
elseif($_REQUEST["subcatexec"] == "calculate")
   require_once("data.calculate.php");
elseif($_REQUEST["subcatexec"] == "criticalitems")
   require_once("data.criticalitems.php");
   
//----------------------------------------------------------------------------------
/*
if($_REQUEST["id"] != "")
{
   $sql = " select sord_status
            from supplier_order
            where
            id = {$_REQUEST["id"]} ";
   $sord_status = $CON->select($sql);
   $sord_status = (int)$sord_status[0]["sord_status"];
   if($sord_status > 1)
   {  ?>
      <script language="JavaScript">
      document.getElementById('idx_ul_criticalitems').innerHTML = '';
      </script>
      <?php
   }
}
*/
?>