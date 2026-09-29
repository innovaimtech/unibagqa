<?php
if($_REQUEST["subexec"] == "add")
{
   require_once("data.vehiculos.edit.php");
}
else
{
   
   if($_REQUEST["subexec"] == "del")
   {
      $sql = " update transports_vehiculo
               set
               transports_vh_status = 0
               where id = {$_REQUEST["vhid"]}" ;
      $CON->no_result($sql);
   }
   
   $sql = " select transports_vehiculo.*, parametros.descripcion
            from transports_vehiculo
               inner join parametros on tabla = 'VEHICULOS' and codigo = transports_vh_marca
            where
            idtransports_vh = {$_REQUEST["id"]} and transports_vh_status  = 1 ";
   $vehiculos = $CON->select($sql);

   printButton("Agregar Vehiculos", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&subexec=add", "", "plus", 150);
   ?>
   <br>
  
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="120">
      <col width="120">
      <col width="150">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="4">Resumen de Vehiculos</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Patente</td>
      <td class="content_tbl_subheader">Marca</td>
      <td class="content_tbl_subheader">Creado</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   for($x = 0; $x < count($vehiculos) && $vehiculos != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$vehiculos[$x]["transports_vh_patente"]?>&nbsp;</td>
         <td class="content_row"><?=$vehiculos[$x]["descripcion"]?>&nbsp;</td>
         <td class="content_row"><?=displayDate($vehiculos[$x]["transports_vh_cr_date"])?>&nbsp;</td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=vehiculos&id={$_REQUEST["id"]}&subexec=add&vhid={$vehiculos[$x]["id"]}", "", "pencil");
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="4" align="center" valign="middle" height="30">
            <b class="msg_save_err">No hay Vehiculos disponibles</b>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
}