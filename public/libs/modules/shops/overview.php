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
      
      $sql = " update company_shops
               set
               shop_status = 0
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.id 'company_id', t2.company_short
            from company_shops t1
            LEFT OUTER JOIN company_data t2 ON t1.shop_company_id = t2.id
            where
            t1.shop_status = 1
            order by t2.company_short, t1.shop_name";
   $shops = $CON->select($sql);

   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Resumen de sucursales</b></td>
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
      <td class="content_tbl_header" colspan="5">Resumen de sucursales</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Número</td>
      <td class="content_tbl_subheader">Empresa</td>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader">Dirección</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($shops) && $shops != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$shops[$x]["id"]?>&nbsp;</td>
         <td class="content_row"><?=$shops[$x]["company_short"]?>&nbsp;</td>
         <td class="content_row"><?=$shops[$x]["shop_name"]?>&nbsp;</td>
         <td class="content_row"><?=$shops[$x]["shop_street"]?>&nbsp;</td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$shops[$x]["id"]}", "", "pencil");
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