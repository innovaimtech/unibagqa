<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "orders";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Número" => "1", "Cliente" => "7", "Empresa" => "5,6", "Sucursal" => "6", "Creado" => "3", "Estado" => "4");
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
      $_SESSION[$_sesmodulename]["sql_assign"]    = (int)$_REQUEST["sql_assign"];
      $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
      $_SESSION[$_sesmodulename]["sql_seudonimo"] = trim(addslashes($_REQUEST["sql_seudonimo"]));
      $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
      $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
      $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
      $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);
      $_SESSION[$_sesmodulename]["sql_status"]    = $_REQUEST["sql_status"];
      $_SESSION[$_sesmodulename]["sql_mode"]      = (int)$_REQUEST["sql_mode"];
      $_SESSION[$_sesmodulename]["sql_custprov"]  = trim($_REQUEST["sql_custprov"]);
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
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
               INNER JOIN orders_items t6       ON t1.id = t6.req_id
               INNER JOIN item t2x              ON t6.item_id = t2x.id ";

   $cntsql = " select count(distinct t1.id) 'cc'
               from orders t1
               {$joisql}
               where
               t1.req_status           > 1 and
               t1.req_isfabricate      = 1 and
               t1.req_production_act   > 0 ";

   $datsql = " select distinct t1.id, t1.req_number, t1.req_crtdat, t1.req_status, t2.company_short,
                      t3.shop_name, t4.cust_name, t1.req_isreserva, t1.req_isfabricate,
                      t1.req_hash, t2x.item_title, t2x.item_number_prod, t6.fab_printtype, t6.fab_type,
                      t6.item_amount, t1.req_cliche_peli_solic_dat, t1.req_cliche_peli_recep_dat,
                      t1.req_prod_adjfile_0, t1.req_prod_adjfile_1, t1.req_prod_adjfile_2, t1.req_prod_adjfile_3,
                      t1.req_prod_adjfile_4, t1.req_prod_checklist_term, t1.req_design_assign_uid,
                      t1.req_solic_supp_seudonimo, t1.req_cliche_peli_solic_repeat_act
               from orders t1
               {$joisql}
               where
               t1.req_status        > 1 and
               t1.req_isfabricate   = 1 and
               t1.req_production_act > 0  ";

   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["filter_status"] == 1)
      $seasql .= " and t1.req_prod_checklist_term = 0 and t1.req_cliche_peli_solic_dat = 0 and t1.req_cliche_peli_solic_repeat_act = 0 ";
   elseif((int)$_SESSION[$_sesmodulename]["filter_status"] == 2)
      $seasql .= " and t1.req_prod_checklist_term = 0 and ( t1.req_cliche_peli_solic_dat > 0 or t1.req_cliche_peli_solic_repeat_act = 1 )";
   else
      $seasql .= " and
                     (
                        t1.req_prod_checklist_term = 1 or
                        (
                           t1.req_cliche_peli_solic_repeat_act = 1 and
                           t1.req_solic_supp_gesttype = 'Maqueta'
                        ) or
                        (
                           t1.req_cliche_peli_solic_dat > 0 and
                           t1.req_solic_supp_gesttype = 'Maqueta'
                        )
                     ) ";

   if($_SESSION[$_sesmodulename]["sql_assign"] == 1)
      $seasql .= " and t1.req_design_assign_uid = 0 ";
   elseif($_SESSION[$_sesmodulename]["sql_assign"] == 2)
      $seasql .= " and t1.req_design_assign_uid = {$_SESSION["user_id"]} ";
   elseif($_SESSION[$_sesmodulename]["sql_assign"] == 3)
      $seasql .= " and t1.req_design_assign_uid != {$_SESSION["user_id"]} and t1.req_design_assign_uid > 0 ";

   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t1.req_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t1.req_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_customer"])
      $seasql .= " and t1.req_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if($_SESSION[$_sesmodulename]["sql_seudonimo"] != "")
      $seasql .= " and t1.req_solic_supp_seudonimo like '%{$_SESSION[$_sesmodulename]["sql_seudonimo"]}%' ";

   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
   {
      $seasql .= " and
                     (
                        t1.req_number     like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t4.cust_name      like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t4.cust_company   like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%'
                     ) ";
   }
   if($_SESSION[$_sesmodulename]["sql_custprov"] != "")
      $seasql .= " and t1.req_custnameprov  like '%{$_SESSION[$_sesmodulename]["sql_custprov"]}%' ";
   if((int)$_SESSION[$_sesmodulename]["sql_mode"] == 1 )
      $seasql .= " and t1.req_isreserva  = 0 ";
   elseif((int)$_SESSION[$_sesmodulename]["sql_mode"] == 2 )
      $seasql .= " and t1.req_isreserva  = 1 ";

   if($_SESSION[$_sesmodulename]["sql_item_id"] != "")
      $seasql .= " and t6.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                   and t6.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" || $_SESSION[$_sesmodulename]["sql_dateto"] != "")
   {
      if($_SESSION[$_sesmodulename]["sql_dateto"] == "")
         $_SESSION[$_sesmodulename]["sql_dateto"] = date('d.m.Y');
      if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
         $_SESSION[$_sesmodulename]["sql_datefrom"] = "01.01.".date('Y');

      $sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_datefrom"]);
      $sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_dateto"], false);

      $seasql .= " and t1.req_crtdat between {$sqldate_from} and {$sqldate_to} ";
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
      <td height="30"><b class="content_header">Confirmaciones de compra pendientes</b></td>
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
         <?=Nifty_printH("box2", "1020")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="100">
            <col>
            <col width="100">
            <col width="430">
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
            <td class="content_rowl">Artículo</td>
            <td class="content_row"><?php printOverviewItemSelect($_sesmodulename) ?></td>
            <td class="content_rowl">Sucursal</td>
            <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Cliente</td>
            <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?php printOverviewPeriodSelect($_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Seudónimo</td>
            <td class="content_row">
               <input name="sql_seudonimo" type="text" class="text" style="width:375px"
               value="<?=$_SESSION[$_sesmodulename]["sql_seudonimo"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Asignación</td>
            <td class="content_row">
               <input type="radio" name="sql_assign" value="0" <?if((int)$_SESSION[$_sesmodulename]["sql_assign"] == 0) echo "checked"?>> <b>Todos</b>
               <input type="radio" name="sql_assign" value="1" <?if((int)$_SESSION[$_sesmodulename]["sql_assign"] == 1) echo "checked"?>> <b style="color:#D14B4B">No asignado</b>
               <input type="radio" name="sql_assign" value="2" <?if((int)$_SESSION[$_sesmodulename]["sql_assign"] == 2) echo "checked"?>> <b style="color:#42A14F">Asignado a mi</b>
               <input type="radio" name="sql_assign" value="3" <?if((int)$_SESSION[$_sesmodulename]["sql_assign"] == 3) echo "checked"?>> <b style="color:#2B8EBE">Asignado a otros</b>
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
         <?=Nifty_printH("box1", "99%")?>
         <?php
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="85">
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col width="80">
            <col width="100">
            <col width="120">
            <col>
            <col width="80">
            <col width="120">
         </colgroup>
         <tr>
            <td class="content_tbl_header">Número</td>
            <td class="content_tbl_header">Cliente</td>
            <td class="content_tbl_header">Empresa</td>
            <td class="content_tbl_header">Sucursal</td>
            <td class="content_tbl_header">Creado</td>
            <td class="content_tbl_header">Código</td>
            <td class="content_tbl_header">Producto</td>
            <td class="content_tbl_header" align="center">Cantidad</td>
            <td class="content_tbl_header" align="center">Tipo</td>
            <td class="content_tbl_header" align="center">Solicitud</td>
            <td class="content_tbl_header" align="center">Recepción</td>
            <td class="content_tbl_header">Seudónimo</td>
            <td class="content_tbl_header" align="center">Imagenes</td>
            <td class="content_tbl_header" align="center">Opciones</td>
         </tr>
         <?php

         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($orders) && $orders != false; $x++)
         {
            $bgcss = "#D14B4B";
            if($orders[$x]["req_design_assign_uid"] == $_SESSION["user_id"])
               $bgcss = "#42A14F";
            if($orders[$x]["req_design_assign_uid"] != $_SESSION["user_id"] && $orders[$x]["req_design_assign_uid"] > 0)
               $bgcss = "#2B8EBE";
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os" style="background-color:<?=$bgcss?>;color:#FFFFFF;text-shadow:none"><?=$orders[$x]["req_number"]?></td>
               <td class="content_row_os"><?=$orders[$x]["cust_name"]?></td>
               <td class="content_row_os"><?=$orders[$x]["company_short"]?></td>
               <td class="content_row_os"><?=$orders[$x]["shop_name"]?></td>
               <td class="content_row_os"><?=date('d.m.Y',$orders[$x]["req_crtdat"])?></td>
               <td class="content_row_os" align="left"><?=$orders[$x]["item_number_prod"]?></td>
               <td class="content_row_os" align="left"><?=$orders[$x]["item_title"]?></td>
               <td class="content_row_os" align="center"><?=printPrice($orders[$x]["item_amount"])?></td>
               <td class="content_row_os" align="center"><?=$orders[$x]["fab_printtype"]?>-<?=$orders[$x]["fab_type"]?></td>
               <td class="content_row_os" align="center"><nobr><?if((int)$orders[$x]["req_cliche_peli_solic_dat"]) echo date('d.m.Y H:i',$orders[$x]["req_cliche_peli_solic_dat"])?></nobr></td>
               <td class="content_row_os" align="center"><nobr><?if((int)$orders[$x]["req_cliche_peli_recep_dat"]) echo date('d.m.Y H:i',$orders[$x]["req_cliche_peli_recep_dat"])?></nobr></td>
               <td class="content_row_os"><?=$orders[$x]["req_solic_supp_seudonimo"]?>&nbsp;</td>
               <td class="content_row_os" align="center">
                  <?php
                  if($orders[$x]["req_prod_adjfile_0"] != "" || $orders[$x]["req_prod_adjfile_1"] != "" || $orders[$x]["req_prod_adjfile_2"] != "" ||
                     $orders[$x]["req_prod_adjfile_3"] != "" || $orders[$x]["req_prod_adjfile_4"] != "")
                     echo "<b class=msg_save_ok>Si</b>";
                  else
                     echo "<b class=msg_save_err>No</b>";
                  ?>
               </td>
               <td class="content_row_os" align="center">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$orders[$x]["id"]}", "", "pencil");
                  ?>
               </td>
            </tr>
            <?php
         }

         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row_os" colspan="17" align="center">
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
