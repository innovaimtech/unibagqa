<?php
if($_REQUEST["subcatexec"] == "")
   $_REQUEST["subcatexec"] = "basic";

$sql = " select * from despacho where id = {$_REQUEST["id_despacho"]} ";
$detalle_ingreso = $CON->select($sql);
$detalle_ingreso = $detalle_ingreso[0];

if($detalle_ingreso["id_ingreso"] == 1)
{
   $title = "Ingresando Despacho ";
}
else
{
   $title = "Ingresando Rechazo";
}

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
<input type="hidden" name="id_despacho" value="<?=$_REQUEST["id_despacho"]?>">
<?=Nifty_printH("boxopt_t", "980", 0)?>
   <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
   <tr>
      <td width="20%" style="padding-right:5px">
         <?php
            printButton("Datos básicos", "postnav_del", "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}&id_despacho={$_REQUEST["id_despacho"]}", "", "cookies");
         ?>
      </td>
      <td width="20%" style="padding-right:5px">
      <td width="20%" style="padding-right:5px">
      <td width="20%" style="padding-right:5px">
      <td width="20%" style="padding-right:5px">
         <?php
            if($detalle_ingreso["id_ingreso"] == 1)
               printButton("Crear Rechazo", "postnav_del", "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}&id_despacho={$_REQUEST["id_despacho"]}&clonexec=1", "", "cookies");
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
?>

