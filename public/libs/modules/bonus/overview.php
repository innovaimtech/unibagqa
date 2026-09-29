<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "bonus";
$_sesbasefilterstatus   = $_REQUEST["setstatus"];
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Empresa" => "1", "Surcusal" => "2", "Persona" => "7,8", "Fecha" => "4", "Creado" => "5");

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);
   
//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "edit")
   require_once("overview.edit.php");
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $_SESSION[$_sesmodulename]["sql_company"]    = (int)$_REQUEST["sql_company"];
      $_SESSION[$_sesmodulename]["sql_shop"]       = (int)$_REQUEST["sql_shop"];
      $_SESSION[$_sesmodulename]["sql_user"]       = (int)$_REQUEST["sql_user"];
      $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
      $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

   //----------------------------------------------------------------------------------
   if($_REQUEST["exec"] == "del")
   {
      $sql = " update bonus
               set
               bon_status = 0
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);

      $sql = " delete from bonus_pos
               where
               bon_id = {$_REQUEST["id"]}";
      $CON->no_result($sql);

      $savemsg = getSaveMessage(true);
   }

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = "LEFT OUTER JOIN user t2   ON t1.bon_crtusr  = t2.id
              LEFT OUTER JOIN user t3   ON t1.bon_updusr  = t3.id
              LEFT OUTER JOIN user t4   ON t1.bon_user_id = t4.id
              LEFT OUTER JOIN company_data  t5 ON t1.bon_company_id = t5.id
              LEFT OUTER JOIN company_shops t6 ON t1.bon_shop_id    = t6.id ";

   $cntsql = " select count(distinct t1.id) 'cc'
               from bonus t1
               {$joisql}
               where
               t1.bon_status = {$_SESSION[$_sesmodulename]["filter_status"]}";

   $datsql = " select distinct t5.company_short, t6.shop_name, t1.bon_date, t1.bon_crtdat, t1.id, t1.bon_status,
               t4.user_firstname 'bon_firstname', t4.user_lastname 'bon_lastname',
               t2.user_firstname 'crt_firstname', t2.user_lastname 'crt_lastname',
               t3.user_firstname 'upd_firstname', t3.user_lastname 'upd_lastname'
               from bonus t1
               {$joisql}
               where
               t1.bon_status = {$_SESSION[$_sesmodulename]["filter_status"]} ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" || $_SESSION[$_sesmodulename]["sql_dateto"] != "")
   {
      if($_SESSION[$_sesmodulename]["sql_dateto"] == "")
         $_SESSION[$_sesmodulename]["sql_dateto"] = date('d.m.Y');
      if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
         $_SESSION[$_sesmodulename]["sql_datefrom"] = "01.01.".date('Y');

      $sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_datefrom"]);
      $sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_dateto"], false);

      $seasql .= " and t1.bon_date between {$sqldate_from} and {$sqldate_to} ";
   }

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t1.bon_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t1.bon_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_user"])
      $seasql .= " and t1.bon_user_id = {$_SESSION[$_sesmodulename]["sql_user"]} ";

   //----------------------------------------------------------------------------------
   $cntsql   .= $seasql; 
   $datsql   .= $seasql;
   $boncount = $CON->select($cntsql);
   $boncount = (int)$boncount[0]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
   $datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";

   //----------------------------------------------------------------------------------
   $bonus = $CON->select($datsql);

   //----------------------------------------------------------------------------------
   $companies  = getCompanies($CON);
   $shops      = getShops($CON);

   $sql = " select *
            from user
            where
            user_status = 1
            order by user_firstname, user_lastname";
   $users = $CON->select($sql);

   //----------------------------------------------------------------------------------
   ?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <?php
   printJSsetCompanyShop($shops);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Resumen de abonos</b></td>
      <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults($boncount); else echo $savemsg;?></td>
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
            <col width="385">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Empresa</td>
            <td class="content_row">
               <select class="text" name="sql_company" style="width:375px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)"
               onchange="setCompanyShop(this.value)">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($companies AS $company)
                  {  ?>
                     <option value="<?=$company["id"]?>"
                     <?php if($company["id"] == $_SESSION[$_sesmodulename]["sql_company"]) echo "selected"?>><?=$company["company_short"]?></option><?php
                  }
                  ?>
               </select>
            </td>
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?php printOverviewPeriodSelect($_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Sucursal</td>
            <td class="content_row">
               <select class="text" name="sql_shop" style="width:375px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)"
               onchange="setCompanyShopStorehouse(this.value)">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($shops AS $shop)
                  {  ?>
                     <option value="<?=$shop["id"]?>"
                     <?php if($shop["id"] == $_SESSION[$_sesmodulename]["sql_shop"]) echo "selected"?>><?=$shop["shop_name"]?>
                     </option><?php
                  }
                  ?>
               </select>
            </td>
            <td class="content_rowl">Persona</td>
            <td class="content_row">
               <select class="text" name="sql_user" style="width:375px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($users AS $user)
                  {  ?>
                     <option value="<?=$user["id"]?>" <?php if($user["id"] == $_SESSION[$_sesmodulename]["sql_user"]) echo "selected"?>>
                        <?=$user["user_firstname"]?> <?=$user["user_lastname"]?>
                     </option><?php
                  }
                  ?>
               </select>
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
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $boncount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col>
            <col>
            <col>
            <col width="80">
            <col width="80">
            <col width="25">
            <col width="85">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
            <td class="content_tbl_subheader" align="center">Estado</td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>

         <?php
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($bonus) && $bonus != false; $x++)
         {
            $statimg = "";
            switch((int)$bonus[$x]["bon_status"])
            {
               case 1: $statimg = "red_active.gif";
                       $stattr  = "Pendiente";
                       break;
               case 2: $statimg = "green_active.gif";
                       $stattr  = "Aprobado";
                       break;
            }
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=$bonus[$x]["company_short"]?></td>
               <td class="content_row"><?=$bonus[$x]["shop_name"]?></td>
               <td class="content_row"><?=nameTo($bonus[$x]["bon_firstname"])?> <?=nameTo($bonus[$x]["bon_lastname"])?></td>
               <td class="content_row"><?=date('d.m.Y', $bonus[$x]["bon_date"])?></td>
               <td class="content_row"><?=date('d.m.Y', $bonus[$x]["bon_crtdat"])?></td>
               <td class="content_row" align="center">
                  <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=$stattr?>">
               </td>
               <td class="content_row" align="center">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$bonus[$x]["id"]}", "", "pencil");
                  ?>
               </td>
            </tr>
            <?php
         }

         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" colspan="8" align="center">
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
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $boncount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <?=Nifty_printF()?>
      </td>
   </tr>
   </table>
   <?php
}