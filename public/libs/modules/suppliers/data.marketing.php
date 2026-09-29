<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "addtype1")
   require_once("data.marketing.edit1.php");
elseif($_REQUEST["subexec"] == "addtype2")
   require_once("data.marketing.edit2.php");
else
{
   if($_REQUEST["clearData"] != "")
   {
      $sql = " update supplier_marketing_head
               set
               mark_status = 0
               where
               id       = {$_REQUEST["clearData"]} and
               supp_id  = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      
      $savemsg = getSaveMessage(true);
   }

   $sql = " select *
            from supplier_marketing_head
            where
            mark_status > 0 and
            supp_id = {$_REQUEST["id"]}
            order by mark_crtdat";
   $asps = $CON->select($sql);

   ?>
   <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td class="content_row_clear" width="200" style="padding-right:5px">
         <?php
         printButton("Agregar Rebate: %", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&subexec=addtype1", "", "plus", 200);
         ?>
      </td>
      <td class="content_row_clear" width="200" style="padding-right:5px">
         <?php
         printButton("Agregar Rebate: Metas", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&subexec=addtype2", "", "plus", 200);
         ?>
      </td>
      <td class="content_row_clear">&nbsp;</td>
   </tr>
   </table>
   <br>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="150">
      <col width="150">
      <col width="85">
      <col width="85">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="5">Resumen de rebates</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader">Año</td>
      <td class="content_tbl_subheader">Tipo</td>
      <td class="content_tbl_subheader" align="center" colspan="2"><?=$_LANG["MODULE"]["CUST"][40]?></td>
   </tr>
   <?php
   for($x = 0; $x < count($asps) && $asps != false; $x++)
   {
      if($asps[$x]["mark_type"] == "SIMPLE")
      {
         $type = "Porcentaje";
         $mod  = "addtype1";
      }
      else
      {
         $type = "Meta";
         $mod  = "addtype2";
      }
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$asps[$x]["mark_name"]?>&nbsp;</td>
         <td class="content_row"><?=$asps[$x]["mark_year"]?>&nbsp;</td>
         <td class="content_row"><?=$type?>&nbsp;</td>
         <td class="content_row" align="center">
            <?php
            
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=marketing&id={$_REQUEST["id"]}&subexec={$mod}&cid={$asps[$x]["id"]}", "", "pencil");
            ?>
         </td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=marketing&id={$_REQUEST["id"]}&clearData={$asps[$x]["id"]}')", "cross-circle-frame");
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