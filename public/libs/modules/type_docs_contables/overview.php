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

      $sql = " update typedoc_contables
               set
               typedoc_cont_status  = 0,
               typedoc_cont_updusr  =  {$_SESSION["user_id"]},
               typedoc_cont_upddat  =  {$currtme}
               where
               id = {$id}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);
   }

   $sql = " select t1.*, 
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from typedoc_contables t1
            LEFT OUTER JOIN user t2 ON t1.typedoc_cont_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.typedoc_cont_crtusr = t3.id
            where
            t1.typedoc_cont_status = 1
            order by t1.typedoc_cont_nameid";
   $typedoc = $CON->select($sql); 
   
   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header">Resumen de tipo de documentos contables</b></td>
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
      <col width="85">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="6">Resumen de tipo de documentos contables</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader">Codigo</td>
      <td class="content_tbl_subheader">Creado por</td>
      <td class="content_tbl_subheader">Creado</td>
      <td class="content_tbl_subheader" colspan="2" align="center">Opciones</td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($typedoc) && $typedoc != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row">
            <?php
            switch((int)$typedoc[$x]["typedoc_cont_nameid"])
            {
               case 1: echo "Factura (manual)"; break;
               case 2: echo "Factura exenta"; break;
               case 3: echo "Factura (electr.)"; break;
               case 4: echo "Factura exenta (electr.)"; break;
               case 5: echo "Nota de credito (manual)"; break;
               case 6: echo "Nota de debito (manual)"; break;
               case 7: echo "Nota de credito (electr.)"; break;
               case 8: echo "Nota de debito (electr.)"; break;
               case 9: echo "Factura mixta"; break;
               default: echo "&nbsp;"; break;
            }
            ?>
         </td>
         <td class="content_row"><?=$typedoc[$x]["typedoc_cont_code"]?></td>
         <td class="content_row"><?=$typedoc[$x]["crt_firstname"]." ".$typedoc[$x]["crt_lastname"]?></td>
         <td class="content_row"><?=date('d.m.Y',$typedoc[$x]["typedoc_cont_crtdat"])?></td>
         <td class="content_row" align="center">
            <ul class="postnav">
               <a href="index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&id=<?=$typedoc[$x]["id"]?>"><?=$_LANG["FORM"]["BUTTON"][3]?></a>
            </ul>
         </td>
         <td class="content_row" align="center">
            <ul class="postnav_del">
               <a href="javascript: deactivateFormChange()"
               onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&exec=del&id=<?=$typedoc[$x]["id"]?>')"><?=$_LANG["FORM"]["BUTTON"][2]?></a>
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