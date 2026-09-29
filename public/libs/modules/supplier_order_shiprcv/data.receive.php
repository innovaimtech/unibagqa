<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2018 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "add")
{
   require_once("data.sth.form.php");
}
else
{
   $sql = " select t1.*
            from stockchanges t1
            where
            t1.sth_supporder_contenedorid = {$_REQUEST["id"]} and
            t1.stk_status > 1
            order by t1.stk_num";
   $sths = $CON->select($sql);

   printButton("Agregar recepción", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&subexec=add", "", "plus", 200);
   ?>
   <br>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="120">
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="3">Resumen de despachos recibidos</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">ID Transacción</td>
      <td class="content_tbl_subheader">Fecha</td>
      <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["CUST"][40]?></td>
   </tr>
   <?php
   for($x = 0; $x < count($sths) && $sths != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$sths[$x]["stk_num"]?>&nbsp;</td>
         <td class="content_row"><?=date("d.m.Y", $sths[$x]["stk_bookdate"])?>&nbsp;</td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=receive&id={$_REQUEST["id"]}&subexec=add&cid={$sths[$x]["id"]}", "", "pencil");
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="3" align="center" valign="middle" height="30">
            <b class="msg_save_err">No hay datos disponibles</b>
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