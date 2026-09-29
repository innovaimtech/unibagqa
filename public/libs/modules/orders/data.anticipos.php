<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "add")
{
   require_once("data.anticipos.form.php");
}
else
{
   if($_REQUEST["clearData"] != "")
   {
      $sql = " update orders_anticipos
               set
               ant_status = 0
               where
               id = {$_REQUEST["clearData"]} ";
      $CON->no_result($sql);

      $savemsg = getSaveMessage(true);
   }

   $sql = " select count(*) 'cc'
            from invoices_sell_parts t1
            INNER JOIN invoices_sell t2 ON t1.part_invc_id = t2.id
            where
            t1.part_req_id = {$_REQUEST["id"]} and
            t2.invc_status > 0";
   $hasinvcrel = $CON->select($sql);
   $hasinvcrel = (int)$hasinvcrel[0]["cc"];

   $_CANEDIT = true;
   if($hasinvcrel)
      $_CANEDIT = false;

   $sql = " select t1.*
            from orders_anticipos t1
            where
            t1.ant_req_id      = {$_REQUEST["id"]} and
            t1.ant_status  = 1
            order by t1.ant_recepdate";
   $ants = $CON->select($sql);

   if($_CANEDIT)
   {
      printButton("Agregar abono", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&subexec=add", "", "plus", 200);
      ?>
      <br>
      <?php
   }
   ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="100">
      <col width="100">
      <col>
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="4">Resumen de abonos</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Monto</td>
      <td class="content_tbl_subheader">Fecha</td>
      <td class="content_tbl_subheader">Descripción</td>
      <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["CUST"][40]?></td>
   </tr>
   <?php
   for($x = 0; $x < count($ants) && $ants != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row">$ <?=printPrice($ants[$x]["ant_amount"])?>&nbsp;</td>
         <td class="content_row"><?=date("d.m.Y", $ants[$x]["ant_recepdate"])?>&nbsp;</td>
         <td class="content_row"><?=$ants[$x]["ant_desc"]?>&nbsp;</td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=anticipos&id={$_REQUEST["id"]}&subexec=add&cid={$ants[$x]["id"]}", "", "pencil");
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="4" align="center" valign="middle" height="30">
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