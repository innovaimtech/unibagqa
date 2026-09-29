<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "add")
{
   require_once("data.delivaddr.edit.php");
}
else
{
   if($_REQUEST["clearData"] != "")
   {
      $sql = " update customer_deliveryaddr
               set
               delivery_status = 0
               where
               id       = {$_REQUEST["clearData"]} and
               cust_id  = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   
      $sql = " update customer
               set
               cust_updusr = {$_SESSION["user_id"]},
               cust_upddat = {$currtme}
               where
               id          = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      
      $savemsg = getSaveMessage(true);
   }
   
   $sql = " select *
            from customer_deliveryaddr
            where
            delivery_status = 1 and
            cust_id  = {$_REQUEST["id"]}
            order by id asc";
   $deliveries = $CON->select($sql);

   printButton("Agregar dirección", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&subexec=add", "", "plus", 150);
   ?>
   <br>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col width="85">
      <col width="85">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="4">Resumen de direcciones del despacho</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader">Dirección</td>
      <td class="content_tbl_subheader" align="center" colspan="2"><?=$_LANG["MODULE"]["CUST"][40]?></td>
   </tr>
   <?php
   for($x = 0; $x < count($deliveries) && $deliveries != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$deliveries[$x]["delivery_name"]?>&nbsp;</td>
         <td class="content_row"><?=$deliveries[$x]["delivery_street"]?>&nbsp;</td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=delivaddr&id={$_REQUEST["id"]}&subexec=add&cid={$deliveries[$x]["id"]}", "", "pencil");
            ?>
         </td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=delivaddr&id={$_REQUEST["id"]}&clearData={$deliveries[$x]["id"]}')", "cross-circle-frame");
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="5" align="center" valign="middle" height="30">
            <b class="msg_save_err">No hay datos disponibles.</b>
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