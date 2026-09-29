<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2021 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "prod_clasify";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Número" => "2", "Activado" => "3", "Cliente" => "7", "Sucursal" => "6",
                                "Código" => "11", "Producto" => "12", "Cantidad" => "13", "Nº OT" => "14");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

if($_REQUEST["exec"] == "edit")
   require_once("overview.edit.php");
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $sql_item = explode("#", $_REQUEST["item_id"]);
      
      $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
      $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
      $_SESSION[$_sesmodulename]["sql_customer"]  = (int)$_REQUEST["sql_customer"];
      $_SESSION[$_sesmodulename]["sql_xstate"]    = (int)$_REQUEST["sql_xstate"];
      $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
      $_SESSION[$_sesmodulename]["sql_otnum"]     = trim(addslashes($_REQUEST["sql_otnum"]));
      $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
      $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
      $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
      $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);
      $_SESSION[$_sesmodulename]["sql_status"]    = $_REQUEST["sql_status"];
      $_SESSION[$_sesmodulename]["sql_mode"]      = (int)$_REQUEST["sql_mode"];
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
   }

   if($_REQUEST["exec"] == "del" && (int)$_REQUEST["id"])
   {
      $currtme = time();

      $sql = " update prod_header
               set
               prd_status = 0,
               prd_updusr = {$_SESSION["user_id"]},
               prd_upddat = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

   if($_SESSION[$_sesmodulename]["filter_status"] != 4)
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]))
         $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>3);

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " LEFT OUTER JOIN company_data t2  ON t1.req_company_id = t2.id
               LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id    = t3.id
               LEFT OUTER JOIN customer t4      ON t1.req_cust_id    = t4.id
               INNER JOIN orders_items t1x      ON t1.id = t1x.req_id
               INNER JOIN item t2x              ON t1x.item_id = t2x.id
               LEFT OUTER JOIN prod_header t3x  ON t1.id = t3x.prd_reqid and t3x.prd_status >= 2 ";

   if($_SESSION[$_sesmodulename]["sql_item_id"] != "")
      $joisql .= " INNER JOIN orders_items t6 ON t1.id = t6.req_id ";

   $cntsql = " select count(distinct t1.id) 'cc'
               from orders t1
               {$joisql}
               where
               t1.req_status > 1 and
               (
                  t1.req_solic_supp_gesttype = 'Maqueta' or
                  t1.req_prod_checklist_term = 1
               ) and
               t1.req_production_act = 1 ";

   $datsql = " select distinct t1.id, t1.req_number, t1.req_production_initdate, t1.req_status, t2.company_short,
                      t3.shop_name, t4.cust_name, t1.req_isreserva, t1.req_isfabricate,
                      t1.req_hash, t2x.item_number_prod, t2x.item_title, t1x.item_amount,
                      t3x.prd_number, t1.req_cliche_peli_solic_dat, t1.req_cliche_peli_recep_dat,
                      t1.req_prod_adjfile_0, t1.req_prod_adjfile_1, t1.req_prod_adjfile_2, t1.req_prod_adjfile_3, 
                      t1.req_prod_adjfile_4
               from orders t1
               {$joisql}
               where
               t1.req_status > 1 and
               (
                  t1.req_solic_supp_gesttype = 'Maqueta' or
                  t1.req_prod_checklist_term = 1
               ) and
               t1.req_production_act = 1 ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t1.req_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t1.req_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_customer"])
      $seasql .= " and t1.req_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if($_SESSION[$_sesmodulename]["sql_otnum"] != "")
      $seasql .= " and t3x.prd_number = '{$_SESSION[$_sesmodulename]["sql_otnum"]}' ";
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
   {
      $seasql .= " and
                     (
                        t1.req_number     like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t4.cust_name      like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t4.cust_company   like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%'
                     ) ";
   }

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" || $_SESSION[$_sesmodulename]["sql_dateto"] != "")
   {
      if($_SESSION[$_sesmodulename]["sql_dateto"] == "")
         $_SESSION[$_sesmodulename]["sql_dateto"] = date('d.m.Y');
      if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
         $_SESSION[$_sesmodulename]["sql_datefrom"] = "01.01.".date('Y');

      $sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_datefrom"]);
      $sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_dateto"], false);

      $seasql .= " and t1.req_production_initdate between {$sqldate_from} and {$sqldate_to} ";
   }

   if($_SESSION[$_sesmodulename]["sql_xstate"] == 0)
   {
      $seasql .= " and
                     (
                        select count(*) 'cc'
                        from prod_header ph
                        where
                        ph.prd_reqid   = t1.id and
                        ph.prd_status  >= 2 
                     ) = 0 ";
   }
   elseif($_SESSION[$_sesmodulename]["sql_xstate"] == 1)
   {
      $seasql .= " and
                     (
                        select count(*) 'cc'
                        from prod_header ph
                        where
                        ph.prd_reqid   = t1.id and
                        ph.prd_status  >= 2 
                     ) > 0 ";
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
   $orders = $CON->select($datsql);

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
      <td height="30"><b class="content_header">Resumen OT's</b></td>
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
            <col width="300">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Número/Nombre</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:375px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Empresa</td>
            <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Cliente</td>
            <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
            <td class="content_rowl">Sucursal</td>
            <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">OT</td>
            <td class="content_row">
               <input name="sql_otnum" type="text" class="text" style="width:375px"
               value="<?=$_SESSION[$_sesmodulename]["sql_otnum"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Estado</td>
            <td class="content_row">
               <input type="radio" name="sql_xstate" value="0"
               <?if((int)$_SESSION[$_sesmodulename]["sql_xstate"] == 0) echo "checked"?>> Pendiente
               <input type="radio" name="sql_xstate" value="1"
               <?if((int)$_SESSION[$_sesmodulename]["sql_xstate"] == 1) echo "checked"?>> Clasificado
               <input type="radio" name="sql_xstate" value="3"
               <?if((int)$_SESSION[$_sesmodulename]["sql_xstate"] == 3) echo "checked"?>> Todo
            </td>
         </tr>
         <tr>
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?php printOverviewPeriodSelect($_sesmodulename) ?></td>
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
         <?=Nifty_printH("box1", "99%")?>
         <?php
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="85">
            <col width="80">
            <col>
            <col>
            <col width="100">
            <col>
            <col width="85">
            <col width="85">
            <col width="85">
            <col width="120">
         </colgroup>
         <tr>
            <td class="content_tbl_header "><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
            <td class="content_tbl_header"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
            <td class="content_tbl_header" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
            <td class="content_tbl_header" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
            <td class="content_tbl_header" align="center">Estado</td>
            <td class="content_tbl_header" align="center">Opciones</td>
         </tr>
         <?php

         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($orders) && $orders != false; $x++)
         {
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><?=$orders[$x]["req_number"]?></td>
               <td class="content_row_os"><?=date('d.m.Y',$orders[$x]["req_production_initdate"])?></td>
               <td class="content_row_os"><?=$orders[$x]["cust_name"]?></td>
               <td class="content_row_os"><?=$orders[$x]["shop_name"]?></td>
               <td class="content_row_os"><?=$orders[$x]["item_number_prod"]?></td>
               <td class="content_row_os"><?=$orders[$x]["item_title"]?></td>
               <td class="content_row_os" align="center"><?=printPrice($orders[$x]["item_amount"])?></td>
               <td class="content_row_os" align="center"><?=$orders[$x]["prd_number"]?>&nbsp;</td>
               <td class="content_row_os" align="center">
                  <?php
                  if(((int)$orders[$x]["req_cliche_peli_solic_dat"] || (int)$orders[$x]["req_cliche_peli_solic_repeat_act"]) &&
                     (int)$orders[$x]["req_cliche_peli_recep_dat"] &&
                     ($orders[$x]["req_prod_adjfile_0"] != "" || $orders[$x]["req_prod_adjfile_1"] != "" || $orders[$x]["req_prod_adjfile_2"] != "" ||
                      $orders[$x]["req_prod_adjfile_3"] != "" || $orders[$x]["req_prod_adjfile_4"] != ""))
                  {
                     echo "<b class=msg_save_ok>Completo</b>";
                  }
                  else
                     echo "<b class=msg_save_err>Incompleto</b>";
                  ?>
               </td>
               <td class="content_row_os" align="center">
                  <?php
                  printButton("Clasificar", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$orders[$x]["id"]}", "", "pencil");
                  ?>
               </td>
            </tr>
            <?php
         }

         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row_os" colspan="10" align="center">
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
   $_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';
}