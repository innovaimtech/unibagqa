<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["exec"] == "edit")
   require_once("edit.php");
else
{
   if($_REQUEST["exec"] == "delete")
   {
      $sql = " update country
               set
               country_status = -1
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*,
                   t2.user_lastname 'crtusr_name'
            from country t1
            LEFT OUTER JOIN user t2 ON t1.country_crtusr = t2.id
            where
            country_status >= 0
            order by t1.country_status desc, t1.country_name";
   $countries = $CON->select($sql);
   
   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <form action="index.php" method="post" name="xform_countries">
   <input type="hidden" name="exec" value="save">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <tr>
      <td height="30"><b class="content_header">Resumen de países</b></td>
      <td align="right"><?=$savemsg?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="30">
      <col>
      <col width="90">
      <col width="90">
      <col width="90">
      <col width="120">
      <col width="120">
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="8">Resumen de países</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader" align="center">Act</td>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader">Estado</td>
      <td class="content_tbl_subheader">IVA</td>
      <td class="content_tbl_subheader">Moneda</td>
      <td class="content_tbl_subheader">Creado por</td>
      <td class="content_tbl_subheader">Creado</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($countries) && $countries != false; $x++)
   {
      if((int)$countries[$x]["country_status"] == 0)
         $img_status = "status_red.gif";
      else
         $img_status = "status_green.gif";
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row" align="center"><img src="./images/content/<?=$img_status?>"></td>
         <td class="content_row"><?=$countries[$x]["country_name"]?></td>
         <td class="content_row">
            <?php
            if((int)$countries[$x]["country_status"])
               echo "<font color=green>Activado</font>";
            else
               echo "<font color=red>Desactivado</font>";
            ?>
         </td>
         <td class="content_row">
            <?php
            if((int)$countries[$x]["taxes_active"])
               echo "Con IVA";
            else
               echo "Sin IVA";
            ?>
         </td>
         <td class="content_row"><?=$countries[$x]["country_money_type"]?></td>
         <td class="content_row"><?=$countries[$x]["crtusr_name"]?></td>
         <td class="content_row"><?=displayDate($countries[$x]["country_crtdat"])?></td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$countries[$x]["id"]}", "", "pencil");
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
