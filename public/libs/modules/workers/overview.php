<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2017 by 1BIT LTDA. All Rights Reserved.
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
   $_sesmodulename         = "workers";
   $_sesbasefilterstatus   = "1";
   $_sesbaseorderby        = "4";
   $_sesbaseordersort      = "asc";

   //----------------------------------------------------------------------------------
   resetOverviewSession($_sesmodulename);
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $_SESSION[$_sesmodulename]["sql_stext"]      = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
      $_SESSION[$_sesmodulename]["sql_stext2"]     = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext2"])));
      $_SESSION[$_sesmodulename]["sql_rut"]        = trim(addslashes($_REQUEST["sql_rut"]));
      $_SESSION[$_sesmodulename]["sql_folio"]      = trim(addslashes($_REQUEST["sql_folio"]));
      $_SESSION[$_sesmodulename]["page"]           = 0;
      $_SESSION[$_sesmodulename]["search_active"]  = 1;
   }
   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

   if($_REQUEST["exec"] == "del" && (int)$_REQUEST["id"])
   {
      $currtme = time();
      $sql = "UPDATE workers
	           SET
	           wrk_status = 0,
	           wrk_updusr = {$_SESSION["user_id"]},
	           wrk_upddat = {$currtme}
	           WHERE
	           id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $cntsql = " SELECT count(distinct t1.id) 'cc'
               FROM workers t1
               WHERE
               t1.wrk_status > 0 ";
   //----------------------------------------------------------------------------------
   $datsql = " SELECT t1.id, t1.wrk_firstname, t1.wrk_crtdat, t1.wrk_lastname, t1.wrk_rut, t1.wrk_folio, t1.wrk_turno_state
               FROM workers t1
               WHERE
               t1.wrk_status > 0 ";

   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and t1.wrk_firstname like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' ";
   if($_SESSION[$_sesmodulename]["sql_stext2"] != "")
      $seasql .= " and t1.wrk_lastname like '%{$_SESSION[$_sesmodulename]["sql_stext2"]}%' ";
   if($_SESSION[$_sesmodulename]["sql_rut"] != "")
      $seasql .= " and REPLACE(t1.wrk_rut,'.','') like '{$_SESSION[$_sesmodulename]["sql_rut"]}%' ";
   if($_SESSION[$_sesmodulename]["sql_folio"] != "")
      $seasql .= " and t1.wrk_folio like '{$_SESSION[$_sesmodulename]["sql_folio"]}%' ";
   if((int)$_SESSION[$_sesmodulename]["filter_status"] == 1)
      $seasql .= " and t1.wrk_turno_state = 1 ";
   if((int)$_SESSION[$_sesmodulename]["filter_status"] == 2)
      $seasql .= " and t1.wrk_turno_state = 0 ";
   if((int)$_SESSION[$_sesmodulename]["filter_status"] == 3)
      $seasql .= " and t1.wrk_turno_state = 2 ";
         
   //----------------------------------------------------------------------------------
   $cntsql   .= $seasql;
   $datsql   .= $seasql;
   $itemcount = $CON->select($cntsql);
   $itemcount = (int)$itemcount[0]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
   $datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";

   //----------------------------------------------------------------------------------
   $workers = $CON->select($datsql);

   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Resumen Trabajadores</b></td>
      <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults($itemcount); else echo $savemsg;?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <table border="0" cellpadding="0" cellspacing="0" width="100%">
   <tr>
      <td>
         <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
         <input type="hidden" name="subexec" value="search">
         <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
         <?=Nifty_printH("box2", "980")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="60">
            <col>
            <col width="60">
            <col width="450">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Nombre</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:100%"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">RUT</td>
            <td class="content_row">
               <input name="sql_rut" type="text" class="text" style="width:100%"
               value="<?=$_SESSION[$_sesmodulename]["sql_rut"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr>
            <td class="content_rowl">Apellido</td>
            <td class="content_row">
               <input name="sql_stext2" type="text" class="text" style="width:100%"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext2"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">&nbsp;</td>
            <td class="content_row">&nbsp;</td>
         </tr>
         <tr>
            <td class="content_row" align="right" colspan="4">
               <table border="0" cellpadding="0" cellspacing="0" width="270">
               <tr>
                  <td align="right">
                     <?php
                     if((int)$_SESSION[$_sesmodulename]["search_active"])
                        printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                     ?>
                  </td>
                  <td align="right">
                     <?php
                     printButton("Buscar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                     ?>
                  </td>
               </tr>
               </table>
            </td>
         </tr>
         </table>
         <?=Nifty_printF(false)?>
         </form>
      </td>
   </tr>
   <tr>
      <td>
         <?=Nifty_printH("box1", "980")?>
         <?php
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="100">
            <col>
            <col width="160">
            <col width="120">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader">Rut</td>
            <td class="content_tbl_subheader">Nombre</td>
            <td class="content_tbl_subheader">Estado</td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($workers) && $workers != false; $x++)
         {  ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=$workers[$x]["wrk_rut"]?></td>
               <td class="content_row"><?=$workers[$x]["wrk_lastname"].", ".$workers[$x]["wrk_firstname"]?></td>
               <td class="content_row"><?=getWorkerStatus($workers[$x]["wrk_turno_state"], true)?></td>
               <td class="content_row" align="center" width="120">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?tipo_persona=0&mid={$_REQUEST["mid"]}&exec=edit&id={$workers[$x]["id"]}", "", "pencil");
                  ?>
               </td>
            </tr>
            <?php
         }
         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" align="center" colspan="4">
                  <br>
                  <b class="msg_save_err">No hay datos disponibles.</b>
                  <br><br>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
         <?php
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <?=Nifty_printF()?>
      </td>
   </tr>
   </table>
   <?php
}
?>

