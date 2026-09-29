<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["exec"] == "edit")
{
   require_once("overview.edit.php");
}
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["exec"] == "del" && (int)$_REQUEST["id"])
   {
      $currtme = time();

      $shops   = getShops($CON, true);
      
      $sql = " update company_data
               set
               company_status = 0
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $sql = " select *
            from company_data
            where
            company_status = 1
            order by company_name";
   $companies = $CON->select($sql);

   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header">Resumen de empresas</b></td>
      <td align="right"><?=$savemsg?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="60">
      <col>
      <col>
      <col>
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="5">Resumen de empresas</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Número</td>
      <td class="content_tbl_subheader">RUT</td>
      <td class="content_tbl_subheader">Razón Social</td>
      <td class="content_tbl_subheader">Dirección</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($companies) && $companies != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$companies[$x]["id"]?>&nbsp;</td>
         <td class="content_row"><?=$companies[$x]["company_rut"]?>&nbsp;</td>
         <td class="content_row"><?=$companies[$x]["company_name"]?>&nbsp;</td>
         <td class="content_row"><?=$companies[$x]["company_street"]?>&nbsp;</td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$companies[$x]["id"]}", "", "pencil");
            ?>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <?php
}