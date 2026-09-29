<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$sql = " select *
         from user
         where
         id = {$_SESSION["user_id"]}";
$userdata = $CON->select($sql);
$userdata = $userdata[0];

//----------------------------------------------------------------------------------
$_sesmodulename         = "offers";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "3";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Número" => "2","Rut"=>"13", "Cliente" => "7","Cartera De"=>"12", "Vendedor" => "10", "Monto Neto Cotizacion" => "11",
                               "Creado" => "3", "Alerta" => "", "Estado" => "4");
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
      $_SESSION[$_sesmodulename]["sql_seguim"]    = (int)$_REQUEST["sql_seguim"];
      $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
      $_SESSION[$_sesmodulename]["sql_stext2"]    = trim(addslashes($_REQUEST["sql_stext2"]));
      $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
      $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
      $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
      $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);
      $_SESSION[$_sesmodulename]["sql_status"]    = $_REQUEST["sql_status"];
      $_SESSION[$_sesmodulename]["cust_sellerid"]  = (int)$_REQUEST["cust_sellerid"];
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

   if($_SESSION[$_sesmodulename]["filter_status"] == "1,2,3,4")
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]) ||
         (array_search(1,$_SESSION[$_sesmodulename]["sql_status"]) === false &&
          array_search(2,$_SESSION[$_sesmodulename]["sql_status"]) === false &&
          array_search(3,$_SESSION[$_sesmodulename]["sql_status"]) === false &&
          array_search(4,$_SESSION[$_sesmodulename]["sql_status"]) === false))
      $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1,1=>2,2=>3,3=>4);
   /*
   if($_SESSION[$_sesmodulename]["filter_status"] == "3,4")
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]) ||
         (array_search(3,$_SESSION[$_sesmodulename]["sql_status"]) === false &&
          array_search(4,$_SESSION[$_sesmodulename]["sql_status"]) === false))
         $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>3,1=>4);
   */
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "del")
   {
      $sql = " update offers
               set
               req_status = 0
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_seguim"])
   {
      $seasql = "";
      $joisql = " LEFT OUTER JOIN company_data t2  ON t1.req_company_id = t2.id
                  LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id    = t3.id
                  LEFT OUTER JOIN orders t4        ON t1.id = t4.req_offerid and t4.req_status > 0 
                  LEFT OUTER JOIN user t5 ON t1.req_crtusr = t5.id 
                  left outer join customer on req_cust_rut = customer.cust_rut and cust_status > 0
                  LEFT OUTER JOIN user t6 ON customer.cust_sellerid = t6.id  ";

      if($_SESSION[$_sesmodulename]["sql_item_id"] != "")
         $joisql .= " INNER JOIN offers_items t6 ON t1.id = t6.req_id ";

      $currtme = time();
      $cntsql = " select count(distinct t1.id) 'cc'
                  from offers t1
                  {$joisql}
                  where
                  t1.req_status = 2 and
                  t1.req_alert_dat > 0 and
                  t1.req_alert_dat <= {$currtme} ";

      $datsql = " select distinct t1.id
                         , t1.req_number
                         , t1.req_crtdat
                         , t1.req_status
                         , t2.company_short
                         , t3.shop_name
                         , t1.req_cust_company
                         , t1.req_hash
                         , t1.req_alert_dat
                         , concat(t5.user_firstname,' ',t5.user_lastname) as vendedor
                         , t1.req_total_netto
                         , t1.req_total_taxes
                         , t1.req_total_brutto
                         , concat(t6.user_firstname,' ',t6.user_lastname) as cartera
                         , cust_rut
                         , GROUP_CONCAT(t4.req_number ORDER BY t4.req_number SEPARATOR ',<nobr> ') 'order_number'
                  from offers t1
                  {$joisql}
                  where
                  t1.req_status = 2 and
                  t1.req_alert_dat > 0 and
                  t1.req_alert_dat <= {$currtme} ";

      //----------------------------------------------------------------------------------
      if($_SESSION[$_sesmodulename]["sql_company"])
         $seasql .= " and t1.req_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
      if($_SESSION[$_sesmodulename]["sql_shop"])
         $seasql .= " and t1.req_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
      if($_SESSION[$_sesmodulename]["sql_stext"] != "")
         $seasql .= " and ( t1.req_number       like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                            t1.req_cust_company like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%') ";
      if($_SESSION[$_sesmodulename]["sql_stext2"] != "")
         $seasql .= " and t4.req_number like '%{$_SESSION[$_sesmodulename]["sql_stext2"]}%' ";
      if($_SESSION[$_sesmodulename]["cust_sellerid"])
         $seasql .= " and t1.req_crtusr = {$_SESSION[$_sesmodulename]["cust_sellerid"]} ";
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
   }
   else
   {
      $seasql = "";
      $joisql = " LEFT OUTER JOIN company_data t2  ON t1.req_company_id = t2.id
                  LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id    = t3.id
                  LEFT OUTER JOIN orders t4        ON t1.id = t4.req_offerid and t4.req_status > 0 
                  LEFT OUTER JOIN user t5 ON t1.req_crtusr = t5.id
                  left outer join customer on req_cust_rut = customer.cust_rut and cust_status > 0 
                  LEFT OUTER JOIN user t6 ON customer.cust_sellerid = t6.id  ";

      if($_SESSION[$_sesmodulename]["sql_item_id"] != "")
         $joisql .= " INNER JOIN offers_items t6 ON t1.id = t6.req_id ";

      $cntsql = " select count(distinct t1.id) 'cc'
                  from offers t1
                  {$joisql}
                  where
                  t1.req_status IN ({$_SESSION[$_sesmodulename]["filter_status"]})";

      $datsql = " select distinct t1.id
                         , t1.req_number
                         , t1.req_crtdat
                         , t1.req_status
                         , t2.company_short
                         , t3.shop_name
                         , t1.req_cust_company
                         , t1.req_hash
                         , t1.req_alert_dat 
                         , concat(t5.user_firstname,' ',t5.user_lastname) as vendedor
                         , t1.req_total_netto
                         , t1.req_total_taxes
                         , t1.req_total_brutto
                         , concat(t6.user_firstname,' ',t6.user_lastname) as cartera
                         , cust_rut
                         , GROUP_CONCAT(t4.req_number ORDER BY t4.req_number SEPARATOR ',<nobr> ') 'order_number'
                  from offers t1
                  {$joisql}
                  where
                  t1.req_status IN ({$_SESSION[$_sesmodulename]["filter_status"]}) ";

      //----------------------------------------------------------------------------------
      if($_SESSION[$_sesmodulename]["sql_company"])
         $seasql .= " and t1.req_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
      if($_SESSION[$_sesmodulename]["sql_shop"])
         $seasql .= " and t1.req_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
      if($_SESSION[$_sesmodulename]["sql_stext"] != "")
         $seasql .= " and ( t1.req_number       like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                            t1.req_cust_company like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%') ";
      if($_SESSION[$_sesmodulename]["sql_stext2"] != "")
         $seasql .= " and t4.req_number like '%{$_SESSION[$_sesmodulename]["sql_stext2"]}%' ";
      if($_SESSION[$_sesmodulename]["cust_sellerid"])
         $seasql .= " and t1.req_crtusr = {$_SESSION[$_sesmodulename]["cust_sellerid"]} ";
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
      if($_SESSION[$_sesmodulename]["filter_status"] != 3)
      {
         $seastatstr = "";
         foreach($_SESSION[$_sesmodulename]["sql_status"] AS $seastat)
            $seastatstr .= $seastat.",";
         $seastatstr = substr($seastatstr, 0, -1);
         $seasql .= " and t1.req_status IN ({$seastatstr}) ";
      }
   }
   
   if((int)$userdata["user_orderlimit_perm"])
      $seasql .= " and ( t1.req_crtusr = {$_SESSION["user_id"]} or customer.cust_sellerid = {$_SESSION["user_id"]})";

   //----------------------------------------------------------------------------------
   $cntsql   .= $seasql;
   $datsql   .= $seasql;
   $itemcount = $CON->select($cntsql);
   $itemcount = (int)$itemcount[0]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

   $datsql .= " group by t1.id
                order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
   $datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";

   //----------------------------------------------------------------------------------

   $orders = $CON->select($datsql);
   $companies  = getCompanies($CON);
   $shops      = getShops($CON);
   $sellers    = getSellers($CON);

   //----------------------------------------------------------------------------------
   ?>
   <script language="JavaScript">
      function generateNV(id)
      {
         var xurl = './libs/modules/orders_offers/verordenes.fancy.php?id='+id;
         showFancybox(xurl, 'iframe', 400, 300, 'auto');
      }

   </script>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <?php
   printJSsetCompanyShop($shops);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="1200">
   <tr>
      <td height="30"><b class="content_header">Resumen de cotizaciones</b></td>
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
         <?=Nifty_printH("box2", "1200")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="155">
            <col>
            <col width="155">
            <col width="380">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Número/Nombre</td>
            <td class="content_row">
               <nobr>
               <input name="sql_stext" type="text" class="text" style="width:187px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               
               <input name="sql_stext2" type="text" class="text" style="width:185px"
               placeholder="Número CC"
               value="<?=$_SESSION[$_sesmodulename]["sql_stext2"]?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               </nobr>
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
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?php printOverviewPeriodSelect($_sesmodulename) ?></td>
            <td class="content_rowl" valign="top">Vendedor</td>
            <td class="content_row" valign="top">
               <select class="text" style="width:375px" name="cust_sellerid" id="cust_sellerid" onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($sellers as $seller)
                  {  ?>
                     <option value="<?=$seller["id"]?>"
                     <?php if($seller["id"] == $_SESSION[$_sesmodulename]["cust_sellerid"]) echo "selected"?>><?=$seller["user_firstname"]?> <?=$seller["user_lastname"]?></option>
                     <?php
                  }
                  ?>
               </select>
            </td>
         </tr>
         <tr>
            <?php
            // if($_SESSION[$_sesmodulename]["filter_status"] == "1,2,3,4")
            {  ?>
               <td class="content_rowl">Estado</td>
               <td class="content_row">
                  <?php
                  for($x = 1; $x <= 4; $x++)
                  {  
                     // if($x != 3)
                     {
                     ?>
                        <input type="checkbox" name="sql_status[]" value="<?=$x?>"
                        <?php if(array_search($x, $_SESSION[$_sesmodulename]["sql_status"]) !== false) echo "checked"?>><?=getOfferStatus($x, true)?>
                     <?php
                     }
                  }
                  ?>
               </td>
               <?php
            }
            /*
            else
            {  ?>
               <td class="content_rowl">Estado</td>
               <td class="content_row">
                  <?php
                  for($x = 3; $x <= 4; $x++)
                  {  ?>
                     <input type="checkbox" name="sql_status[]" value="<?=$x?>"
                     <?php if(array_search($x, $_SESSION[$_sesmodulename]["sql_status"]) !== false) echo "checked"?>><?=getOfferStatus($x, true)?>
                     <?php
                  }
                  ?>
               </td>
               <?php
            }
            */
            ?>
            <td class="content_rowl">Seguimiento</td>
            <td class="content_row">
               <input type="checkbox" name="sql_seguim" value="1" <?if((int)$_SESSION[$_sesmodulename]["sql_seguim"]) echo "checked"?>>
               Mostrar cotizaciones con alerta de seguimiento
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
         <?=Nifty_printH("box1", "1200")?>
         <?php
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="90">
            <col width="90">
            <col>
            <col>
            <col>
            <col>
            <col>
            <col width="75">
            <col width="75">
            <col width="25">
            <col width="75">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader">Nº CC</td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
            <td class="content_tbl_subheader" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($orders) && $orders != false; $x++)
         {
            $_SESSION[$_sesmodulename]["FLW"][$orders[$x]["id"]]["L"] = (int)$orders[($x -1)]["id"];
            $_SESSION[$_sesmodulename]["FLW"][$orders[$x]["id"]]["N"] = (int)$orders[($x +1)]["id"];
            
            $statimg = "";
            switch((int)$orders[$x]["req_status"])
            {
               case 1: $statimg = "red_active.gif"; break;
               case 2: $statimg = "green_active.gif"; break;
               case 3: $statimg = "purple_active.gif"; break;
               case 4: $statimg = "blue_active.gif"; break;
            }
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row">
                  <?php
                  if($orders[$x]["req_status"] > 1 && $orders[$x]["req_hash"] != "")
                  {  ?>
                     <a href="javascript:void(0);" class="link"
                     onclick="document.all.idxifrsrc.src = './libs/modules/structure/document_file.php?type=0&id=<?=$orders[$x]["id"]?>&hash=<?=$orders[$x]["req_hash"]?>.pdf&name=<?=$orders[$x]["req_number"]?>.pdf&path=../../../docs.offer/'"><?=$orders[$x]["req_number"]?></a>
                     <?php
                  }
                  else
                     echo $orders[$x]["req_number"];
                  ?>
               </td>

               <td class="content_row">
                  <?php
                  if($orders[$x]["order_number"] != "")
                  {  ?>
                     <a href="javascript:void(0);" class="link" onclick="generateNV(<?=$orders[$x]["id"]?>);">Ver Numero(s)</a>
                     <?php
                  }
                  else
                     echo '';
                  ?>
               </td>
               <?php
                   $sql = "select count(1) as contador_cust from customer where cust_rut = '{$orders[$x]["cust_rut"]}' and cust_status > 0 ";
                   $con_rut = $CON->select($sql);
                   $con_rut = $con_rut[0];
                   $k_rut = "";
                   if((int)$con_rut["contador_cust"]>1)
                      $k_rut = " (".$con_rut["contador_cust"].")";
               ?>
               <td class="content_row"><nobr><?=$orders[$x]["cust_rut"].$k_rut?></nobr></td>
               <td class="content_row"><?=$orders[$x]["req_cust_company"]?></td>
               <td class="content_row"><?=$orders[$x]["cartera"]?></td>
               <td class="content_row"><?=$orders[$x]["vendedor"]?></td>
               <td class="content_row">$ <?=printPrice($orders[$x]["req_total_netto"],0)?></td>
               <td class="content_row"><?=date('d.m.Y',$orders[$x]["req_crtdat"])?></td>
               <td class="content_row">
                  <?php
                  if((int)$orders[$x]["req_alert_dat"] && time() >= $orders[$x]["req_alert_dat"])
                  {  ?>
                     <span style="border-radius:3px;background-color:#DB5959;color:white;text-shadow:none;margin-left:4px;padding:3px;padding-left:4px;padding-right:4px"
                     title="Fecha alerta seguimiento">
                     <img src="/images/menu/icons/exclamation.png" style="border:0px;vertical-align:bottom">&nbsp;<?=date('d.m.Y',$orders[$x]["req_alert_dat"])?>
                     </span>
                     <?php
                  }
                  elseif((int)$orders[$x]["req_alert_dat"])
                  {  ?>
                     <span style="border-radius:3px;background-color:#729AE2;color:white;text-shadow:none;margin-left:4px;padding:3px;padding-left:4px;padding-right:4px"
                     title="Fecha alerta seguimiento">
                     <img src="/images/menu/icons/calendar.png" style="border:0px;vertical-align:bottom">&nbsp;<?=date('d.m.Y',$orders[$x]["req_alert_dat"])?>
                     </span>
                     <?php
                  }
                  else
                     echo "&nbsp;";
                  ?>
               </td>
               <td class="content_row" align="center">
                  <img class="select" src="./images/content/<?=$statimg?>" title="Estado: <?=getOfferStatus($orders[$x]["req_status"])?>">
               </td>
               <td class="content_row" align="center">
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
               <td class="content_row" colspan="7" align="center">
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