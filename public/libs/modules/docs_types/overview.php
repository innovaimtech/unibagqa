<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       16.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
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

      $sql = " update tran_docs_types
               set
               docto_status = 0,
               docto_updusr = {$_SESSION["user_id"]},
               docto_upddat = {$currtme}
               where
               id = {$id}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);
   }

   $sql = " select t1.*, 
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from tran_docs_types t1
            LEFT OUTER JOIN user t2 ON t1.docto_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.docto_crtusr = t3.id
            where
            t1.docto_status = 1
            order by t1.docto_title";
   $types = $CON->select($sql);
   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header">Resumen de tipos de documentos</b></td>
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
      <col width="150">
      <col width="130">
      <col width="85">
      <col width="85">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="5">Resumen de tipos de documentos</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader">Creado por</td>
      <td class="content_tbl_subheader">Creado</td>
      <td class="content_tbl_subheader" colspan="2" align="center">Opciones</td>
   </tr>
   <?php
   
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($types) && $types != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$types[$x]["docto_title"]?></td>
         <td class="content_row"><?=$types[$x]["crt_firstname"]." ".$types[$x]["crt_lastname"]?></td>
         <td class="content_row"><?=date('d.m.Y',$types[$x]["docto_crtdat"])?></td>
         <td class="content_row" align="center">
            <ul class="postnav">
               <a href="index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&id=<?=$types[$x]["id"]?>"><?=$_LANG["FORM"]["BUTTON"][3]?></a>
            </ul>
         </td>
         <td class="content_row" align="center">
            <ul class="postnav_del">
               <a href="javascript: deactivateFormChange()"
               onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&exec=del&id=<?=$types[$x]["id"]?>')"><?=$_LANG["FORM"]["BUTTON"][2]?></a>
            </ul>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row_clear" colspan="6" align="center">
            <br>
            <b class="msg_save_err">No se han encontrado datos.</b>
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