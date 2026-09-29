<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2017 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "cfi_inc";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

if($_REQUEST["exec"] == "edit")
   require_once("edit.php");
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $_SESSION[$_sesmodulename]["sql_stext"]      = trim(addslashes($_REQUEST["sql_stext"]));
      $_SESSION[$_sesmodulename]["sql_month1"]     = trim($_REQUEST["sql_month1"]);
      $_SESSION[$_sesmodulename]["sql_year1"]      = trim($_REQUEST["sql_year1"]);
      $_SESSION[$_sesmodulename]["page"]           = 0;
      $_SESSION[$_sesmodulename]["search_active"]  = 1;
   }
   
   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_month1"] == "")
   {
      $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m');
      $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');
   }


   if($_REQUEST["exec"] == "del" && (int)$_REQUEST["id"])
   {
      $currtme = time();

      $sql = " update turnos_config_incidencias
               set
               cfi_status = 0,
               cfi_crtusr = {$_SESSION["user_id"]},
               cfi_crtdat = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $cntsql = " select count(t1.id) 'cc'
               from turnos_config_incidencias t1
               INNER JOIN workers t2      ON t1.cfi_workerid = t2.id
               INNER JOIN incidencias t3  ON t1.cfi_inc_id = t3.id
               where
               t1.cfi_status > 0 and
               t3.inc_type   = 1 ";
   
   //----------------------------------------------------------------------------------
   $datsql = " select t1.id, t1.cfi_startdate, t1.cfi_enddate, t2.wrk_firstname, t2.wrk_lastname,
                      t3.inc_name, t2.wrk_folio, t2.wrk_rut, t3.inc_color, t1.cfi_status
               from turnos_config_incidencias t1
               INNER JOIN workers t2      ON t1.cfi_workerid = t2.id
               INNER JOIN incidencias t3  ON t1.cfi_inc_id = t3.id
               where
               t1.cfi_status > 0 and
               t3.inc_type   = 1 ";

   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and t1.cfi_name like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' ";

   //----------------------------------------------------------------------------------
   $cntsql   .= $seasql;
   $datsql   .= $seasql;
   $itemcount = $CON->select($cntsql);
   $itemcount = (int)$itemcount[0]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

   $datsql .= " order by t1.cfi_status asc, t1.cfi_startdate desc, t2.wrk_lastname asc ";
   $datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";

   //----------------------------------------------------------------------------------
   $data = $CON->select($datsql);
   
   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Resumen de Cambios de Sucursal</b></td>
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
            <col width="100">
            <col>
            <col width="100">
            <col width="600">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Mes</td>
            <td class="content_row">
               <select class="text" name="sql_month1" id="sql_month1"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <?php
                  for($x = 1; $x <= 12; $x++)
                  {
                     $dsp_month = $x;
                     if($dsp_month < 10)
                        $dsp_month = "0{$dsp_month}";
                     ?>
                     <option value="<?=$x?>"
                     <?php if($x == $_SESSION[$_sesmodulename]["sql_month1"]) echo "selected" ?>><?=$dsp_month?></option>
                     <?php
                  }
                  ?>
               </select>
               <select class="text" name="sql_year1" id="sql_year1"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <?php
                  $startyear  = date('Y') -3;
                  $endyear    = date('Y');

                  for($x = $startyear; $x <= $endyear; $x++)
                  {
                     ?>
                     <option value="<?=$x?>"
                     <?php if($x == $_SESSION[$_sesmodulename]["sql_year1"]) echo "selected" ?>><?=$x?></option>
                     <?php
                  }
                  ?>
               </select>
            </td>
            <td class="content_rowl">Trabajador</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:100%"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
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
            <col width="90">
            <col width="100">
            <col>
            <col width="100">
            <col>
            <col width="1">
            <col width="120">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader">Ficha</td>
            <td class="content_tbl_subheader">RUT</td>
            <td class="content_tbl_subheader">Nombre Trabajador</td>
            <td class="content_tbl_subheader">Fecha</td>
            <td class="content_tbl_subheader" align="center">Tipo</td>
            <td class="content_tbl_subheader" align="center">Estado</td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($data) && $data != false; $x++)
         {
            $statimg = "";
            switch((int)$data[$x]["cfi_status"])
            {
               case 1: $statimg = "red_active.gif"; break;
               case 2: $statimg = "green_active.gif"; break;
            }

            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=$data[$x]["wrk_folio"]?>&nbsp;</td>
               <td class="content_row"><?=$data[$x]["wrk_rut"]?>&nbsp;</td>
               <td class="content_row"><?=$data[$x]["wrk_lastname"]?>, <?=$data[$x]["wrk_firstname"]?></td>
               <td class="content_row"><?=date("d.m.Y", $data[$x]["cfi_startdate"])?></td>
               <td class="content_row" align="center" style="background-color:<?=$data[$x]["inc_color"]?>"><?=$data[$x]["inc_name"]?>&nbsp;</td>
               <td class="content_row" align="center">
                  <img class="select" src="./images/content/<?=$statimg?>">
               </td>
               <td class="content_row" align="center">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$data[$x]["id"]}", "", "pencil");
                  ?>
               </td>
            </tr>
            <?php
         }
         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" align="center" colspan="7">
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
         <br>
      </td>
   </tr>
   </table>
   <?php
}