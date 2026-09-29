<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "add")
{
   require_once("data.contact.php");
}
else
{
   if($_REQUEST["clearData"] != "")
   {
      $sql = " delete from supplier_contacts
               where
               id          = {$_REQUEST["clearData"]} and
               add_supplier_id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   
      $sql = " update supplier
               set
               supp_updusr = {$_SESSION["user_id"]},
               supp_upddat = {$currtme}
               where
               id          = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      
      $savemsg = getSaveMessage(true);
   }

   $sql = " select id, add_pos, add_firstname, add_lastname, add_email, add_phone
            from supplier_contacts
            where
            add_supplier_id = {$_REQUEST["id"]}
            order by add_firstname, add_lastname";
   $asps = $CON->select($sql);

   printButton("Agregar contacto", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&subexec=add", "", "plus", 150);
   ?>
   <br>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col>
      <col width="85">
      <col width="85">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="5">Resumen de contactos</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader">E-Mail</td>
      <td class="content_tbl_subheader">Teléfono</td>
      <td class="content_tbl_subheader" align="center" colspan="2"><?=$_LANG["MODULE"]["CUST"][40]?></td>
   </tr>
   <?php
   for($x = 0; $x < count($asps) && $asps != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$asps[$x]["add_firstname"]?>&nbsp;<?=$asps[$x]["add_lastname"]?></td>
         <td class="content_row"><?=$asps[$x]["add_email"]?>&nbsp;</td>
         <td class="content_row"><?=$asps[$x]["add_phone"]?>&nbsp;</td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=additional1&id={$_REQUEST["id"]}&subexec=add&cid={$asps[$x]["id"]}", "", "pencil"); 
            ?>
         </td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=additional1&id={$_REQUEST["id"]}&clearData={$asps[$x]["id"]}')", "cross-circle-frame");
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="5" align="center" valign="middle" height="30">
            <b class="msg_save_err">No hay contactos disponibles</b>
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