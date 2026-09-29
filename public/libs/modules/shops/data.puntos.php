<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "add")
{
   require_once("data.puntos.edit.php");
}
else
{
   if($_REQUEST["subexec"] == "del" && $_REQUEST["puntosid"] != "")
   {
      $sql = " update company_shops_puntos
               set
               puntos_status = 0
               where
               id              = {$_REQUEST["puntosid"]} and
               puntos_shop_id  = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }
   
   $sql = " select *
            from company_shops_puntos
            where
            puntos_shop_id = {$_REQUEST["id"]} and
            puntos_status  = 1
            order by puntos_name";
   $puntos = $CON->select($sql); 

   printButton("Agregar puntos de ventas", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&subexec=add", "", "plus", 150);
   ?>
   <br>
   <?=Nifty_printH("box1", "822")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="120">
      <col width="120">
      <col width="120">
      <col width="130">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="6">Resumen de puntos de ventas</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader">IP</td>
      <td class="content_tbl_subheader">Conexion</td>
      <td class="content_tbl_subheader">Creado</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   for($x = 0; $x < count($puntos) && $puntos != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$puntos[$x]["puntos_name"]?>&nbsp;</td>
         <td class="content_row"><?=$puntos[$x]["puntos_ip"]?>&nbsp;</td>
         <td class="content_row">
            <?php
            if($puntos[$x]["puntos_conexion"] == 0)
               echo "USB";
            elseif($puntos[$x]["puntos_conexion"] == 1)
               echo "COM1";
            elseif($puntos[$x]["puntos_conexion"] == 2)
               echo "LPT1";         
            ?>
         </td>
         <td class="content_row"><?=displayDate($puntos[$x]["puntos_crtdat"])?>&nbsp;</td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=puntos&id={$_REQUEST["id"]}&subexec=add&puntosid={$puntos[$x]["id"]}", "", "pencil");
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="6" align="center" valign="middle" height="30">
            <b class="msg_save_err">No hay puntos de ventas disponibles</b>
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