<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2019 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stockpacks";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Número" => "2", "Motivo" => "7", "Fecha" => "3", "Creado" => "4", "Estado" => "5", "Sucursal" => "8");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);
   
if($_REQUEST["exec"] == "edit")
   require_once("edit.php");
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
      $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
      $_SESSION[$_sesmodulename]["sql_issueid"]   = (int)$_REQUEST["sql_issueid"];
      $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
      $_SESSION[$_sesmodulename]["sql_item"]      = trim(addslashes(str_replace("*","%",$_REQUEST["sql_item"])));
      $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
      $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);
   
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "del")
   {
      $currtme = time();
      $sql = " update stockpacks
               set
               stk_status = 0,
               stk_upddat = {$currtme},
               stk_updusr = {$_SESSION["user_id"]}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }
   $sql_filter_status = $_SESSION[$_sesmodulename]["filter_status"];
   if((int)$_SESSION[$_sesmodulename]["filter_status"] == 10)
      $sql_filter_status = 0;

   //----------------------------------------------------------------------------------
   $seasql = "";

   $joisql = " LEFT OUTER JOIN stockchanges_issues t4 ON t1.stk_issueid = t4.id
               LEFT OUTER JOIN user t2                ON t1.stk_updusr = t2.id
               LEFT OUTER JOIN user t3                ON t1.stk_crtusr = t3.id
               LEFT OUTER JOIN company_shops t9       ON t1.stk_shopid = t9.id ";

   if($_SESSION[$_sesmodulename]["sql_item"] != "")
      $joisql .= " INNER JOIN stockpacks_items t6   ON t1.id = t6.stk_id
                   LEFT OUTER JOIN item t7            ON (t6.item_id = t7.id and t6.item_type = 'item')
                   LEFT OUTER JOIN itemlist t8        ON (t6.item_id = t8.id and t6.item_type = 'itemlist')";

   $cntsql = " select count(distinct t1.id) 'cc'
               from stockpacks t1
               {$joisql}
               where
               t1.id >= 0 and
               t1.stk_status = {$sql_filter_status} ";

   $datsql = " select distinct t1.id, t1.stk_num, t1.stk_bookdate, t1.stk_crtdat, t1.stk_status,
                      t1.stk_annotation, t4.stkis_title, t9.shop_name
               from stockpacks t1
               {$joisql}
               where
               t1.id >= 0 and
               t1.stk_status = {$sql_filter_status} ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t1.stk_companyid = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t1.stk_shopid = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_issueid"])
      $seasql .= " and t1.stk_issueid = {$_SESSION[$_sesmodulename]["sql_issueid"]} ";
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and t1.stk_num like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' ";

   if($_SESSION[$_sesmodulename]["sql_item"] != "")
      $seasql .= " and (
                   t7.item_title        like '%{$_SESSION[$_sesmodulename]["sql_item"]}%' or
                   t7.item_number       like '%{$_SESSION[$_sesmodulename]["sql_item"]}%' or
                   t7.item_number_prod  like '%{$_SESSION[$_sesmodulename]["sql_item"]}%' or
                   t8.item_title        like '%{$_SESSION[$_sesmodulename]["sql_item"]}%' or
                   t8.item_number       like '%{$_SESSION[$_sesmodulename]["sql_item"]}%' or
                   t8.item_number_prod  like '%{$_SESSION[$_sesmodulename]["sql_item"]}%' ) ";
                   
   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" || $_SESSION[$_sesmodulename]["sql_dateto"] != "")
   {
      if($_SESSION[$_sesmodulename]["sql_dateto"] == "")
         $_SESSION[$_sesmodulename]["sql_dateto"] = date('d.m.Y');
      if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
         $_SESSION[$_sesmodulename]["sql_datefrom"] = "01.01.".date('Y');

      $sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_datefrom"]);
      $sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_dateto"], false);

      $seasql .= " and t1.stk_bookdate between {$sqldate_from} and {$sqldate_to} ";
   }

   //----------------------------------------------------------------------------------
   $cntsql   .= $seasql;
   $datsql   .= $seasql;
   $itemcount = $CON->select($cntsql);
   $itemcount = (int)$itemcount[0]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
   $repsql  = $datsql;
   $datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";

   //----------------------------------------------------------------------------------
   $stockpacks = $CON->select($datsql);

   //----------------------------------------------------------------------------------
   $sql = " select *
            from stockchanges_issues
            where
            id IN(15,16)
            order by stkis_title";
   $issues = $CON->select($sql);
   
   //----------------------------------------------------------------------------------
   $companies  = getCompanies($CON);
   $shops      = getShops($CON);
   
   //----------------------------------------------------------------------------------
   ?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <?php
   printJSsetCompanyShop($shops);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Resumen de packs armados/desarmados</b></td>
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
         <input type="hidden" name="printxls" value="">
         <?=Nifty_printH("box2", "980")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="100">
            <col>
            <col width="100">
            <col width="300">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Número</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:375px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Empresa</td>
            <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Artículo</td>
            <td class="content_row">
               <input type="text" style="width:375px" id="sql_item" name="sql_item" class="text"
               onfocus="markfield(this,0)" onblur="markfield(this,1)"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_item"])?>">
            </td>
            <td class="content_rowl">Sucursal</td>
            <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?php printOverviewPeriodSelect($_sesmodulename) ?></td>
            <td class="content_rowl">Motivo</td>
            <td class="content_row">
               <select class="text" name="sql_issueid" style="width:375px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($issues AS $issue)
                  {  ?>
                     <option value="<?=$issue["id"]?>"
                     <?php if($issue["id"] == $_SESSION[$_sesmodulename]["sql_issueid"]) echo "selected"?>>
                        <?=$issue["stkis_title"]?>
                     </option>
                     <?php
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
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="65">
            <col width="120">
            <col width="160">
            <col>
            <col width="85">
            <col width="85">
            <col width="25">
            <col width="120">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
            <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>            
            <td class="content_tbl_subheader content_row_os">Observaciones</td>
            <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader content_row_os" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
            <td class="content_tbl_subheader content_row_os" align="center">Opciones</td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($stockpacks) && $stockpacks != false; $x++)
         {
            $statimg = "";
            switch((int)$stockpacks[$x]["stk_status"])
            {
               case 0: $statimg = "gray_active.gif"; break;
               case 1: $statimg = "red_active.gif"; break;
               case 2: $statimg = "green_active.gif"; break;
            }
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><?=$stockpacks[$x]["stk_num"]?></td>
               <td class="content_row_os"><?=$stockpacks[$x]["shop_name"]?></td>
               <td class="content_row_os"><?=$stockpacks[$x]["stkis_title"]?></td>
               <td class="content_row_os"><?=$stockpacks[$x]["stk_annotation"]?>&nbsp;</td>
               <td class="content_row_os"><?php if($stockpacks[$x]["stk_bookdate"] > 0) echo date('d.m.Y', $stockpacks[$x]["stk_bookdate"]); else echo "&nbsp;";?></td>
               <td class="content_row_os"><?php if($stockpacks[$x]["stk_crtdat"] > 0) echo date('d.m.Y', $stockpacks[$x]["stk_crtdat"]); else echo "&nbsp;";?></td>
               <td class="content_row_os" align="center">
                  <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getShipmentStatus($stockpacks[$x]["stk_status"])?>">
               </td>
               <td class="content_row_os" align="center">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&id={$stockpacks[$x]["id"]}", "", "pencil");
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
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <?=Nifty_printF()?>
      </td>
   </tr>
   </table>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?php
}
