<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       16.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "add")
   require_once("data.cashing.php");
else
{
   if((int)$_REQUEST["delcid"])
   {
      $currtme = time();

      $sql = " update company_shops_cashings
               set
               ca_status = 0,
               ca_updusr = {$_SESSION["user_id"]},
               ca_upddat = {$currtme}
               where
               id = {$_REQUEST["delcid"]}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);
   }

   $sql = " select t1.*,
                  t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
                  t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from company_shops_cashings t1
            LEFT OUTER JOIN user t2 ON t1.ca_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.ca_crtusr = t3.id
            where
            t1.ca_status  = 1 and
            t1.ca_shop_id = {$_REQUEST["id"]}
            order by t1.ca_name asc, t1.ca_ip asc";
   $cashings = $CON->select($sql);
   
   $sql = " select t1.*
            from company_shops t1
            where
            t1.id = {$_REQUEST["id"]}";
   $shop = $CON->select($sql);
   $shop = $shop[0];
   
   //----------------------------------------------------------------------------------
   if(!(int)$_SESSION["LIMITPRIVS"]["user_priv_shop_cajas"])
   {  ?>
      <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="980">
      <tr>
         <td class="content_row_clear">
            <?php
            printButton("Agregar Caja", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=cashing&id={$_REQUEST["id"]}&subexec=add", "", "plus", 150);
            ?>
         </td>
      </tr>
      </table>
      <br>
      <?php
   }
   ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="300">
      <col width="200">
      <col width="110">
      <col width="150">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="5">Resumen de cajas</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader">Codigo acceso</td>
      <td class="content_tbl_subheader">Creado por</td>
      <td class="content_tbl_subheader">Creado</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($cashings) && $cashings != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$cashings[$x]["ca_name"]?></td>
         <td class="content_row"><?=$cashings[$x]["ca_ip"]?></td>
         <td class="content_row"><?=$cashings[$x]["crt_firstname"]." ".$cashings[$x]["crt_lastname"]?></td>
         <td class="content_row"><?=date('d.m.Y',$cashings[$x]["ca_crtdat"])?></td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&subexec=add&id={$_REQUEST["id"]}&cid={$cashings[$x]["id"]}", "", "pencil");
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row_clear" colspan="5" align="center">
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
?>