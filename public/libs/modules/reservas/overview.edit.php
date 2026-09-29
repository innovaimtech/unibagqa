<table border="0" cellpadding="0" cellspacing="0" width="1020">
<tr>
   <td height="30"><b class="content_header">Editar Despacho</b></td>
   <td align="right"><div id="idx_status_msg"><?=$savemsg?></div></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<input type="hidden" name="idx_redirect_id" value="<?=$_REQUEST["id"]?>">
<?php
//----------------------------------------------------------------------------------
require_once("data.basic.php");