<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       03.02.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subcatexec"] == "")
   $_REQUEST["subcatexec"] = "basic";

if($_REQUEST["uid"] != "")
   $title = "Cambiar personal";
else
   $title = "Agregar personal";
?>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right" style="padding-right:5px"><div id="idx_status_msg"><?=$savemsg?></div></td>
   <td width="110" align="right">
      <table border="0" cellpadding="0" cellspacing="0">
      <tr>
      <?php
      if($_REQUEST["uid"] != "")
      {  ?>
         <td class="content_row_clear" width="53" style="padding-right:5px">
         <?php
         printButton("Anexos", "postnav", "javascript:void(0)", "showFancybox('/libs/modules/docs_management/overview.php?mode=users&id={$_REQUEST["uid"]}', 'iframe', 850, 450, 'auto')", "scanner--plus", 110);
         ?>
         </td>
         <?php
      }
      ?>
      </tr>
      </table>

   </td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<br>
<input type="hidden" name="idx_redirect_id" value="<?=$_REQUEST["id"]?>">
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subcatexec"] == "basic")
   require_once("edit.php");
?>