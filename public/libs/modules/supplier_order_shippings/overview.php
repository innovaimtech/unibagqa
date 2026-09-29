<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "suppcont";
$_sesbasefilterstatus   = "1,2,3,4";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Numero" => "1", "OC's" => "9", "Buque" => "6", "Forward" => "7", "Bill of Landing" => "8", "Empresa" => "4,5", "Sucursal" => "5", "Estado" => "3");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);
   
if($_REQUEST["exec"] == "edit")
{
   require_once("overview.edit.php");
}
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
      $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
      $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
      $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
      $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);
      $_SESSION[$_sesmodulename]["sql_buque"]      = trim(addslashes($_REQUEST["sql_buque"]));
      $_SESSION[$_sesmodulename]["sql_forward"]    = trim(addslashes($_REQUEST["sql_forward"]));
      $_SESSION[$_sesmodulename]["sql_bill"]       = trim(addslashes($_REQUEST["sql_bill"]));
      $_SESSION[$_sesmodulename]["sql_contenedor"] = trim(addslashes($_REQUEST["sql_contenedor"]));
      $_SESSION[$_sesmodulename]["sql_ocs"]        = trim(addslashes($_REQUEST["sql_ocs"]));
      $_SESSION[$_sesmodulename]["sql_status"]    = $_REQUEST["sql_status"];
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

   if($_SESSION[$_sesmodulename]["filter_status"] != 2)
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]))
         $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>3,3=>4);

   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "del")
   {
      $sql = " update supplier_contenedor
               set
               sord_status = 0
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " LEFT OUTER JOIN company_data t2  ON t1.sord_company_id   = t2.id
               LEFT OUTER JOIN company_shops t3 ON t1.sord_shop_id      = t3.id ";

   $cntsql = " select count(distinct t1.id) 'cc'
               from supplier_contenedor t1
               {$joisql}
               where
               1 = 1 ";

   $datsql = " select distinct t1.id, t1.sord_crtdat, t1.sord_status, t2.company_short, t3.shop_name,
                      t1.sord_buque, t1.sord_forward, t1.sord_billoflanding, t1.sord_ocs
               from supplier_contenedor t1
               {$joisql}
               where
               1 = 1 ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t1.sord_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t1.sord_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and t1.id = ".(int)$_SESSION[$_sesmodulename]["sql_stext"]." ";

   if($_SESSION[$_sesmodulename]["sql_buque"] != "")
      $seasql .= " and t1.sord_buque like '%{$_SESSION[$_sesmodulename]["sql_buque"]}%' ";
   if($_SESSION[$_sesmodulename]["sql_forward"] != "")
      $seasql .= " and t1.sord_forward like '%{$_SESSION[$_sesmodulename]["sql_forward"]}%' ";
   if($_SESSION[$_sesmodulename]["sql_bill"] != "")
      $seasql .= " and t1.sord_billoflanding like '%{$_SESSION[$_sesmodulename]["sql_bill"]}%' ";
   if($_SESSION[$_sesmodulename]["sql_contenedor"] != "")
      $seasql .= " and t1.sord_contenedor like '%{$_SESSION[$_sesmodulename]["sql_contenedor"]}%' ";
   if($_SESSION[$_sesmodulename]["sql_ocs"] != "")
      $seasql .= " and t1.sord_ocs like '%{$_SESSION[$_sesmodulename]["sql_ocs"]}%' ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" || $_SESSION[$_sesmodulename]["sql_dateto"] != "")
   {
      if($_SESSION[$_sesmodulename]["sql_dateto"] == "")
         $_SESSION[$_sesmodulename]["sql_dateto"] = date('d.m.Y');
      if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
         $_SESSION[$_sesmodulename]["sql_datefrom"] = "01.01.".date('Y');

      $sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_datefrom"]);
      $sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_dateto"], false);

      $seasql .= " and t1.sord_crtdat between {$sqldate_from} and {$sqldate_to} ";
   }

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["filter_status"] != 2)
   {
      $seastatstr = "";
      foreach($_SESSION[$_sesmodulename]["sql_status"] AS $seastat)
         $seastatstr .= $seastat.",";
      $seastatstr = substr($seastatstr, 0, -1);
      $seasql .= " and sord_status IN ({$seastatstr}) ";
   }
   else
   {
      $seasql .= " and sord_status IN (4) ";
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
   $sords = $CON->select($datsql);

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
      <td height="30"><b class="content_header">Resumen de orden de compra</b></td>
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
            <col width="385">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4">Opciones de b�squeda</td>
         </tr>
         <tr>
            <td class="content_rowl">N�mero</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:375px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Empresa</td>
            <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?php printOverviewPeriodSelect($_sesmodulename) ?></td>
            <td class="content_rowl">Sucursal</td>
            <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Buque</td>
            <td class="content_row">
               <input name="sql_buque" type="text" class="text" style="width:375px"
               value="<?=$_SESSION[$_sesmodulename]["sql_buque"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Forward</td>
            <td class="content_row">
               <input name="sql_forward" type="text" class="text" style="width:375px"
               value="<?=$_SESSION[$_sesmodulename]["sql_forward"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr>
            <td class="content_rowl">Bill of Landing</td>
            <td class="content_row">
               <input name="sql_bill" type="text" class="text" style="width:375px"
               value="<?=$_SESSION[$_sesmodulename]["sql_bill"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Contenedor</td>
            <td class="content_row">
               <input name="sql_contenedor" type="text" class="text" style="width:375px"
               value="<?=$_SESSION[$_sesmodulename]["sql_contenedor"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr>
            <td class="content_rowl">OC</td>
            <td class="content_row">
               <input name="sql_ocs" type="text" class="text" style="width:375px"
               value="<?=$_SESSION[$_sesmodulename]["sql_ocs"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">&nbsp;</td>
            <td class="content_row">&nbsp;</td>
         </tr>
         <tr>
            <?php
            if($_SESSION[$_sesmodulename]["filter_status"] != 2)
            {  ?>
               <td class="content_rowl">Estado</td>
               <td class="content_row">
                  <?php
                  for($x = 1; $x <= 4; $x++)
                  {  ?>
                     <input type="checkbox" name="sql_status[]" value="<?=$x?>"
                     <?php if(array_search($x, $_SESSION[$_sesmodulename]["sql_status"]) !== false) echo "checked"?>><?=getSupplierContentdorStatus($x, true)?>
                     <?php
                  }
                  ?>
               </td>
               <?php
            }
            else
            {  ?>
               <td class="content_row" colspan="2">&nbsp;</td>
               <?php
            }
            ?>
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
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="65">
            <col>
            <col>
            <col>
            <col>
            <col width="80">
            <col width="110">
            <col width="25">
            <col width="120">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
            <td class="content_tbl_subheader" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($sords) && $sords != false; $x++)
         {
            $statimg = "";
            switch((int)$sords[$x]["sord_status"])
            {
               case 1: $statimg = "red_active.gif"; break;
               case 2: $statimg = "purple_active.gif"; break;
               case 3: $statimg = "gray_active.gif"; break;
               case 4: $statimg = "green_active.gif"; break;
            }
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=sprintf("%05s", $sords[$x]["id"])?></td>
               <td class="content_row"><?=$sords[$x]["sord_ocs"]?>&nbsp;</td>
               <td class="content_row"><?=$sords[$x]["sord_buque"]?>&nbsp;</td>
               <td class="content_row"><?=$sords[$x]["sord_forward"]?>&nbsp;</td>
               <td class="content_row"><?=$sords[$x]["sord_billoflanding"]?>&nbsp;</td>
               <td class="content_row"><?=$sords[$x]["company_short"]?></td>
               <td class="content_row"><?=$sords[$x]["shop_name"]?></td>
               <td class="content_row" align="center">
                  <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getSupplierContentdorStatus($sords[$x]["sord_status"])?>">
               </td>
               <td class="content_row" align="center">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&id={$sords[$x]["id"]}", "", "pencil");
                  ?>
               </td>
            </tr>
            <?php
         }

         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" colspan="9" align="center">
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
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?php
}