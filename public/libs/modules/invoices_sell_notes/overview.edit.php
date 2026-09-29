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
            from invoices_notes_sell
            where
            id = {$_REQUEST["id"]} ";
   $note = $CON->select($sql);
   
   $title = "Cambiar ".getInvoiceBuyNoteType($note[0]["note_type"]).": {$note[0]["note_number"]}";
}
?>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><div id="idx_status_msg"><?=$savemsg?></div></td>
   <td width="110" align="right">
      <?php
      if($_REQUEST["id"] != "")
      {  ?>
         <td class="content_row_clear" width="53" style="padding-right:5px">
         <?php
         printButton("Anexos", "postnav", "javascript:void(0)", "showFancybox('/libs/modules/docs_management/overview.php?mode=invoices_sell_notes&id={$_REQUEST["id"]}', 'iframe', 850, 450, 'auto')", "scanner--plus", 110);
         ?>
         </td>
         <?php
      }
      if((int)$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["L"] && $_REQUEST["from"] != "report")
      {  ?>
         <td class="content_row_clear" width="53" style="padding-right:5px">
         <?php
         printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["L"]}", "", "arrow-180", 53);
         ?>
         </td>
         <?php
      }
      if((int)$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["N"] && $_REQUEST["from"] != "report")
      {  ?>
         <td class="content_row_clear" width="53" style="padding-right:2px">
         <?php
         printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["N"]}", "", "arrow", 53);
         ?>
         </td>
         <?php
      }
      ?>
   </td>
</tr>
<tr>
   <td class="content_headerline" colspan="3">&nbsp;</td>
</tr>
</table>
<input type="hidden" name="idx_redirect_id" value="<?=$_REQUEST["id"]?>">
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subcatexec"] == "basic")
   require_once("data.basic.php");
elseif($_REQUEST["subcatexec"] == "payment")
   require_once("./libs/modules/invoices_buy/data.payment.php");
?>
