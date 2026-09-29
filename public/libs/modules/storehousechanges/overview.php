<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "storehousechanges";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "8 desc,9";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Número" => "9", "Origen" => "14,16", "Destino" => "15,17", "Fecha" => "8", "Estado" => "7");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);
   
//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "start")
   require_once("start.php");
elseif($_REQUEST["exec"] == "edit")
   require_once("edit.php");
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
      $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
      $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
      $_SESSION[$_sesmodulename]["sql_item"]      = trim(addslashes(str_replace("*","%",$_REQUEST["sql_item"])));
      $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
      $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
      $_SESSION[$_sesmodulename]["sql_status"]    = $_REQUEST["sql_status"];
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

   if($_SESSION[$_sesmodulename]["filter_status"] != 2)
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]))
         $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>999);

   //----------------------------------------------------------------------------------
   if($_REQUEST["exec"] == "del")
   {
      $sql = " update storehousechanges
               set
               strc_status = 0
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);

      $savemsg = getSaveMessage(true);
   }

   //----------------------------------------------------------------------------------
   $_RSPSTHIDRET     = getUserRespSthids($CON);
   $_RSPSTHIDS       = $_RSPSTHIDRET["_RSPSTHIDS"];
   $_RSPSTHIDS_SQL   = $_RSPSTHIDRET["_RSPSTHIDS_SQL"];

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = "LEFT OUTER JOIN company_data t2   ON t1.strc_company_id = t2.id
              LEFT OUTER JOIN company_data t3   ON t1.strc_company_dest_id = t3.id
              LEFT OUTER JOIN company_shops t4  ON t1.strc_shop_id = t4.id
              LEFT OUTER JOIN company_shops t5  ON t1.strc_shop_dest_id = t5.id ";
              
   $joisql .= " LEFT OUTER JOIN storehousechanges_items t6 ON t1.id = t6.strc_id
                LEFT OUTER JOIN item t7               ON (t6.item_id = t7.id and t6.item_type = 'item')
                LEFT OUTER JOIN itemlist t8           ON (t6.item_id = t8.id and t6.item_type = 'itemlist') ";
                        
   $cntsql = " select count(distinct t1.id) 'cc'
               from storehousechanges t1
               {$joisql}
               where
               t1.strc_status          > 0 and
               t1.strc_bodegero        = 0 and
               t1.strc_isshoporder     = 0 and
               t1.strc_isshoptraspaso  = 0 and
               (
                  t6.item_st_id IN ({$_RSPSTHIDS_SQL}) or
                  t6.item_st_id IS NULL
               ) ";
               
   $datsql = " select distinct t1.*, t2.company_short, t3.company_short 'company_dest_name',
                      t4.shop_name, t5.shop_name 'shop_dest_name', t1.strc_poraprobar
               from storehousechanges t1
               {$joisql}
               where
               t1.strc_status          > 0 and
               t1.strc_bodegero        = 0 and
               t1.strc_isshoporder     = 0 and
               t1.strc_isshoptraspaso  = 0 and
               (
                  t6.item_st_id IN ({$_RSPSTHIDS_SQL}) or
                  t6.item_st_id IS NULL
               ) ";

   /* t1.strc_status   = {$_SESSION[$_sesmodulename]["filter_status"]} and */
   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["filter_status"] != 2)
   {
      $seastatstr = "";
      foreach($_SESSION[$_sesmodulename]["sql_status"] AS $seastat)
         $seastatstr .= $seastat.",";
      $seastatstr = substr($seastatstr, 0, -1);

      $showaprobs = false;
      foreach($_SESSION[$_sesmodulename]["sql_status"] AS $seastat)
      {
         if($seastat == 999)
         {
            $showaprobs = true;

         }
      }

      if(!$showaprobs)
      {
         $datsql .= " and ( t1.strc_status IN ({$seastatstr}) and t1.strc_poraprobar = 0) ";
         $cntsql .= " and ( t1.strc_status IN ({$seastatstr}) and t1.strc_poraprobar = 0) ";
      }
      else
      {
         $datsql .= " and ( t1.strc_status IN ({$seastatstr}) or t1.strc_poraprobar = 1) ";
         $cntsql .= " and ( t1.strc_status IN ({$seastatstr}) or t1.strc_poraprobar = 1) ";
      }
   }              

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and (t1.strc_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} or
                        t1.strc_company_dest_id = {$_SESSION[$_sesmodulename]["sql_company"]}) ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and (t1.strc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} or
                        t1.strc_shop_dest_id = {$_SESSION[$_sesmodulename]["sql_shop"]}) ";
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and t1.strc_number like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' ";

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

      $seasql .= " and t1.strc_date between {$sqldate_from} and {$sqldate_to} ";
   }

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
   $storehousechanges = $CON->select($datsql);
   
   //----------------------------------------------------------------------------------
   $companies  = getCompanies($CON);
   $shops      = getShops($CON);

   //----------------------------------------------------------------------------------
   function getStoreHouseChangeStatus($stat, $formated = false)
   {
      if($formated)
      {
         switch($stat)
         {
            case 1: return "<b class='msg_save_err'>En proceso</b>"; break;
            case 2: return "<b class='msg_save_ok'>Finalizado</b>"; break;
         }
      }
     else
      {
         switch($stat)
         {
            case 1: return "En proceso"; break;
            case 2: return "Finalizado"; break;
         }
      }
   }
   

   //----------------------------------------------------------------------------------
   ?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <?php
   printJSsetCompanyShop($shops);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Resumen de traspasos</b></td>
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
         <?=Nifty_printH("box2", "980",0)?>
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
            <td class="content_rowl">Estado</td>
            <td class="content_row">
                  <?php
                  for($x = 1; $x <= 2; $x++)
                  {  ?>
                     <input type="checkbox" name="sql_status[]" value="<?=$x?>"
                     <?php if(array_search($x, $_SESSION[$_sesmodulename]["sql_status"]) !== false) echo "checked"?>><?=getStoreHouseChangeStatus($x, true)?>
                     <?php
                  }
                  ?>
                  <input type="checkbox" name="sql_status[]" value="999"
                  <?php if(array_search(999, $_SESSION[$_sesmodulename]["sql_status"]) !== false) echo "checked"?>>
                     <b style="color:darkorange">Por aprobar</b>
               </td>
            </td>
         </tr>
         <tr>
            <td class="content_row"></td>
            <td class="content_row"></td>
            <td class="content_row" align="right" colspan="2">
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
            <col>
            <col>
            <col width="80">
            <col width="25">
            <col width="85">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>

         <?php
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($storehousechanges) && $storehousechanges != false; $x++)
         {
            $statimg = "";
            switch((int)$storehousechanges[$x]["strc_status"])
            {
               case 1: $statimg = "red_active.gif"; break;
               case 2: $statimg = "green_active.gif"; break;
            }
            $xstate = getShipmentStatus($storehousechanges[$x]["strc_status"]);

            if((int)$storehousechanges[$x]["strc_poraprobar"])
            {
               $statimg = "orange_active.gif";
               $xstate = "Por aprobar";
            }
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=$storehousechanges[$x]["strc_number"]?></td>
               <td class="content_row"><?=$storehousechanges[$x]["company_short"]?>: <?=$storehousechanges[$x]["shop_name"]?></td>
               <td class="content_row"><?=$storehousechanges[$x]["company_dest_name"]?>: <?=$storehousechanges[$x]["shop_dest_name"]?></td>
               <td class="content_row"><?=date('d.m.Y', $storehousechanges[$x]["strc_date"])?></td>
               <td class="content_row" align="center">
                  <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=$xstate?>">
               </td>
               <td class="content_row" align="center">
                  <?php
                  if((int)$storehousechanges[$x]["strc_poraprobar"])
                  {  ?>
                     <img src='/images/content/loading.gif' style='border-radius:50%;opacity:0.3;width:20px;height:20px'>
                     <img src='/images/menu/icons/document-pdf.png' style="cursor:pointer;float:right"
                     onclick="document.all.idxifrsrc.src = './libs/modules/storehousechanges/data.storehouse.pdf.php?id=<?=$storehousechanges[$x]["id"]?>'">
                     <?php
                  }
                  else
                     printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$storehousechanges[$x]["id"]}", "", "pencil");
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