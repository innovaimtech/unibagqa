<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "add")
{
   require_once("data.storehouses.edit.php");
}
else
{
   if($_REQUEST["subexec"] == "del" && $_REQUEST["stid"] != "")
   {
      $sql = " update company_shops_storehouses
               set
               st_status = 0
               where
               id          = {$_REQUEST["stid"]} and
               st_shop_id  = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }
   
   $sql = " select *
            from company_shops_storehouses
            where
            st_shop_id = {$_REQUEST["id"]} and
            st_status  = 1
            order by st_name";
   $storehouses = $CON->select($sql);

   printButton("Agregar bodega", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&subexec=add", "", "plus", 150);
   ?>
   <br>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="120">
      <col width="150">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="3">Resumen de bodegas</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader">Creado</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   for($x = 0; $x < count($storehouses) && $storehouses != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$storehouses[$x]["st_name"]?>&nbsp;</td>
         <td class="content_row"><?=displayDate($storehouses[$x]["st_crtdat"])?>&nbsp;</td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=storehouses&id={$_REQUEST["id"]}&subexec=add&stid={$storehouses[$x]["id"]}", "", "pencil");
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="3" align="center" valign="middle" height="30">
            <b class="msg_save_err">No hay bodegas disponibles</b>
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