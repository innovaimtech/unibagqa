<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Updated:       22.01.2010
// Copyright:     2010 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["exec"] == "createLayout")
{
   require_once("layouts.create.php");
}
elseif($_REQUEST["exec"] == "activateLayout")
{
   require_once("layouts.activate.php");
}
else
{ 
   if($_REQUEST["exec"] == "del" && (int)$_REQUEST["id"])
   {
      $sql = " select layout_filename
               from design_layouts
               where
               id = {$_REQUEST["id"]}";
      $layout_filename = $CON->select($sql);
      $layout_filename = $layout_filename[0]["layout_filename"];

      unlink("./layouts/{$layout_filename}");

      $sql = " delete from design_layouts
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);
   }
   if($_REQUEST["savemsg"] == "1")
      $savemsg = getSaveMessage(true);
      
   $sql = " select t1.*, t2.user_firstname 'crt_firstname', t2.user_lastname 'crt_lastname'
            from design_layouts t1
            LEFT OUTER JOIN user t2 ON t1.layout_crtusr = t2.id
            order by t1.layout_filename asc";
   $layouts  = $CON->select($sql);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header">Layouts</b></td>
      <td align="right"><?=$savemsg?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?=Nifty_printH("box1", "822")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="30">
      <col>
      <col>
      <col width="100">
      <col width="100">
      <col width="75">
      <col width="75">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="7"><?=$_LANG["MODULE"]["DESIGN"][33]?></td>
   </tr>
   <tr>
      <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["DESIGN"][34]?></td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["DESIGN"][35]?></td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["DESIGN"][36]?></td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["DESIGN"][37]?></td>
      <td class="content_tbl_subheader"><?=$_LANG["MODULE"]["DESIGN"][38]?></td>
      <td class="content_tbl_subheader" align="center" colspan="2"><?=$_LANG["MODULE"]["DESIGN"][39]?></td>
   </tr>
   <?php
   for($x = 0; $x < count($layouts) && $layouts != false; $x++)
   {
      if((int)$layouts[$x]["layout_active"] == 0)
         $img_status = "status_red.gif";
      else
         $img_status = "status_green.gif";
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row" align="center"><img src="./images/content/<?=$img_status?>"></td>
         <td class="content_row"><nobr><?=$layouts[$x]["layout_name"]?>&nbsp;</nobr></td>
         <td class="content_row"><?=displaySize($layouts[$x]["layout_filesize"])?>&nbsp;</td>
         <td class="content_row"><nobr><?=$layouts[$x]["crt_lastname"]?></nobr></td>
         <td class="content_row"><nobr><?=displayDate($layouts[$x]["layout_crtdat"])?></nobr></td>
         <td class="content_row" align="center">
            <ul class="postnav_save">
               <a href="javascript: deactivateFormChange()"
               onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&catexec=<?=$_REQUEST["catexec"]?>&exec=activateLayout&id=<?=$layouts[$x]["id"]?>')">Activar</a>
            </ul>
         </td>
         <td class="content_row" align="center">
            <ul class="postnav_del">
               <a href="javascript: deactivateFormChange()"
               onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&catexec=<?=$_REQUEST["catexec"]?>&exec=del&id=<?=$layouts[$x]["id"]?>')"><?=$_LANG["FORM"]["BUTTON"][2]?></a>
            </ul>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row_clear" colspan="7" align="center">
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
   <br>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td>&nbsp;</td>
      <td align="center" width="200">
         <ul class="postnav_save">
            <a href="index.php?mid=<?=$_REQUEST["mid"]?>&catexec=<?=$_REQUEST["catexec"]?>&exec=createLayout"><?=$_LANG["MODULE"]["DESIGN"][32]?></a>
         </ul>
      </td>
      <td>&nbsp;</td>
   </tr>
   </table>
   <br>
   <?php
}
?>
