<?php
if($_REQUEST["subexec"] == "add")
{
   require_once("data.rango.edit.php");
}
else
{
   if($_REQUEST["subexec"] == "del")
   {
      $sql = " update transports_rango
               set
               transports_rango_status = 0
               where id = {$_REQUEST["vhid"]} " ;
      $CON->no_result($sql);
   }
   
   $sql = " select transports_rango.*
            from transports_rango
            where
            transports_rango_trans_id = {$_REQUEST["id"]} 
            and transports_rango_status  > 0 ";

   $rangos = $CON->select($sql);

   printButton("Agregar Rangos", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&subexec=add", "", "plus", 150);
   ?>
   <br>
  
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="4" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col>
      <col width="150">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="4">Resumen de Choferes</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Codigo</td>
      <td class="content_tbl_subheader">Monto</td>
      <td class="content_tbl_subheader">Creado</td>
      <td class="content_tbl_subheader" align="center">Opción</td>
   </tr>
   <?php
   for($x = 0; $x < count($rangos) && $rangos != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$rangos[$x]["transports_rango_codigo"]?>&nbsp;</td>
         <td class="content_row"><norb><?=printPrice($rangos[$x]["transports_rango_monto"],2)?></norb></td>
         <td class="content_row"><?=displayDate($rangos[$x]["transports_rango_cr_date"])?>&nbsp;</td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=rango&id={$_REQUEST["id"]}&subexec=add&vhid={$rangos[$x]["id"]}", "", "pencil");
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="4" align="center" valign="middle" height="30">
            <b class="msg_save_err">No hay Rango de Montos disponibles</b>
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