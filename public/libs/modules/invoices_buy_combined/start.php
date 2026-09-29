<?php
if($_REQUEST["seldoctype"] == "")
   $_REQUEST["seldoctype"] = "shp";
?>
<form action="index.php" method="post" name="xform_comb">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="create">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<table border="0" cellpadding="0" cellspacing="0" width="822">
<tr>
   <td height="30"><b class="content_header">Agregar guia o factura</b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de factura</td>
</tr>
<tr>
   <td class="content_rowl">Tipo documento *</td>
   <td class="content_row">
      <input type="radio" name="seldoctype" value="shp"  <?if($_REQUEST["seldoctype"] == "shp") echo "checked"?>
      onclick="location.href = 'index.php?mid=<?=$_REQUEST["mid"]?>&seldoctype=' +this.value;"> Guia de despacho
      <input type="radio" name="seldoctype" value="invc" <?if($_REQUEST["seldoctype"] == "invc") echo "checked"?>
      onclick="location.href = 'index.php?mid=<?=$_REQUEST["mid"]?>&seldoctype=' +this.value;"> Factura
   </td>
</tr>
</table>
<?=Nifty_printF()?>
</form>
<?php
if($_REQUEST["seldoctype"] == "shp")
{
   require_once("./libs/modules/shipments/start.php");
}
elseif($_REQUEST["seldoctype"] == "invc")
{
   require_once("./libs/modules/invoices_buy/start.php");
}