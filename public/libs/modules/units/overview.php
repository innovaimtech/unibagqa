<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------


if($_REQUEST["exec"] == "edit")
{
   require_once("data.basic.php");
}
else
{
   if($_REQUEST["exec"] == "del" && (int)$_REQUEST["id"])
   {
      $currtme = time();

      $id = $_REQUEST["id"];

      $sql = " update item_units
               set
               unit_status = 0,
               unit_crtusr = {$_SESSION["user_id"]},
               unit_crtdat = {$currtme}
               where
               id = {$id}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   $sql = " select t1.*, 
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from item_units t1
            LEFT OUTER JOIN user t2 ON t1.unit_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.unit_crtusr = t3.id
            where
            t1.unit_status = 1
            order by t1.unit_name";
   $units = $CON->select($sql);
   
   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header">Resumen de unidades</b></td>
      <td align="right"><?=$savemsg?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?=Nifty_printH("box1", "822")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col width="120">
      <col width="120">
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="5">Resumen de unidades</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader">Descripción</td>
      <td class="content_tbl_subheader">Creado por</td>
      <td class="content_tbl_subheader">Creado</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($units) && $units != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$units[$x]["unit_name"]?></td>
         <td class="content_row"><?=$units[$x]["unit_desc"]?></td>
         <td class="content_row"><?=$units[$x]["crt_lastname"]?></td>
         <td class="content_row"><?=displayDate($units[$x]["unit_crtdat"])?></td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$units[$x]["id"]}", "", "pencil");
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row_clear" colspan="5" align="center">
            <br>
            <b class="msg_save_err">No hay datos disponibles.</b>
            <br><br>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <?php
}