<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["exec"] == "edit")
{
   require_once("edit.php");
}
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["exec"] == "del" && (int)$_REQUEST["id"])
   {
      $currtme = time();
      
      $sql = " update stockchanges_issues
               set
               stkis_status = 0,
               stkis_updusr = {$_SESSION["user_id"]},
               stkis_upddat = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from stockchanges_issues t1
            LEFT OUTER JOIN user t2 ON t1.stkis_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.stkis_crtusr = t3.id
            where
            t1.stkis_status = 1
            order by t1.stkis_title";
   $issues = $CON->select($sql);

   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header">Resumen de tipos de ajuste de stock</b></td>
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
      <col width="50">
      <col width="160">
      <col width="120">
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="5">Resumen de tipos de ajuste de stock</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader" align="center">Tipo</td>
      <td class="content_tbl_subheader">Creado por</td>
      <td class="content_tbl_subheader">Creado</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($issues) && $issues != false; $x++)
   {
      if((int)$issues[$x]["stkis_negative"])
         $sicon = "minus-circle-frame.png";
      else
         $sicon = "plus-circle-frame.png";
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$issues[$x]["stkis_title"]?>&nbsp;</td>
         <td class="content_row" align="center"><img src="./images/menu/icons/<?=$sicon?>"></td>
         <td class="content_row"><?=$issues[$x]["crt_lastname"]?>&nbsp;</td>
         <td class="content_row"><?=displayDate($issues[$x]["stkis_crtdat"])?></td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$issues[$x]["id"]}", "", "pencil");
            ?>
         </td>
      </tr><?php
   }
   if($x == 0)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="6" align="center">
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