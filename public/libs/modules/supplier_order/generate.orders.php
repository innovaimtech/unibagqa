<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "supporder_gen";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "7,4";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Numero" => "4", "Artículo" => "3", "Unidad" => "5", "Marcado" => "7", "Stock/Act" => "5",
                                "Stock/Tran" => "6", "Stock/Min" => "5", "Proveedor/Compra" => "7", "Compra" => "7");
if($_REQUEST["exec"] == "save")
{
   require_once("generate.order.preview.php");
}
else
{
   unset($_SESSION["STATS"][$_sesmodulename]);
   //----------------------------------------------------------------------------------
   resetOverviewSession($_sesmodulename);

   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $sql_item = explode("#", $_REQUEST["item_id"]);

      $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
      $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
      $_SESSION[$_sesmodulename]["sql_supplier"]      = (int)$_REQUEST["sql_supplier"];
      $_SESSION[$_sesmodulename]["sql_stockmin"]      = (int)$_REQUEST["sql_stockmin"];
      $_SESSION[$_sesmodulename]["sql_item_id"]       = $sql_item[0];
      $_SESSION[$_sesmodulename]["sql_item_type"]     = $sql_item[1];
      $_SESSION[$_sesmodulename]["sql_pcat"]          = (int)$_REQUEST["sql_pcat"];
      $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
      $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
      $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
      $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
      $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
      $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
      $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
      $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
      $_SESSION[$_sesmodulename]["page"]          = 0;
      $_SESSION[$_sesmodulename]["search_active"] = 1;
   }

   //----------------------------------------------------------------------------------
   $suppliers  = getSuppliers($CON);
   $companies  = getCompanies($CON);
   $shops      = getShops($CON);

   if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
      $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
   if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
      $_SESSION[$_sesmodulename]["sql_selmode"] = 3;

   if(!(int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $first = false;
      foreach($shops AS $shop)
         if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"] && !$first)
         {
            $_SESSION[$_sesmodulename]["sql_shop"] = $shop["id"];
            $first = true;
         }
   }

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_month1"] == "")
   {
      $_SESSION[$_sesmodulename]["sql_month1"]  = 1;
      $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');
      $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m');
      $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y');
   }
   if($_SESSION[$_sesmodulename]["sql_date"] == "")
      $_SESSION[$_sesmodulename]["sql_date"] = date('d.m.Y');
   if($_SESSION[$_sesmodulename]["sql_date_pfrom"] == "")
   {
      $_SESSION[$_sesmodulename]["sql_date_pfrom"] = date('d.m.Y', time() - (86400 * 7));
      $_SESSION[$_sesmodulename]["sql_date_pto"]   = date('d.m.Y');
   }

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_selmode"] == 1)
   {
      $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date"]);
      $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
      $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
   }
   elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 2)
   {
      $sql_datefrom  = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
      $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], 1, $_SESSION[$_sesmodulename]["sql_year2"]);
      $datedays      = date('t', $sql_dateto);
      $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], $datedays, $_SESSION[$_sesmodulename]["sql_year2"]);
   }
   elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 3)
   {
      $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pfrom"]);
      $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
      $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pto"]);
      $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

   //----------------------------------------------------------------------------------
   $datsql = " select t2.item_id, t2.item_type, t3.item_title, t3.item_number_prod,
                      t7.unit_name, t1.req_shop_id, t9.supp_short, t8.item_code,
                      SUM(t2.item_order_genamount) 'amount',
                      SUM(t2.item_amount - t2.item_amount_shipped) 'vamount'
               from orders t1
               INNER JOIN orders_items t2          ON t1.id = t2.req_id
               INNER JOIN item t3                  ON t2.item_id = t3.id
               INNER JOIN item_shops t5            ON ( t2.item_id = t5.item_id and t5.shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} )
               LEFT OUTER JOIN item_productcats t6 ON ( t2.item_id = t6.item_id )
               LEFT OUTER JOIN item_units t7       ON t3.item_unit = t7.id
               INNER JOIN item_suppliers t8        ON ( t2.item_id = t8.item_id and t8.item_supp_act = 1 )
               INNER JOIN supplier t9              ON ( t8.supplier_id = t9.id )
               where
               t1.req_status        > 1 and
               t2.item_type         = 'item' and
               t3.item_status       = 1 and
               t3.item_released     = 1 and
               t3.item_purchasable  = 1 and
               t1.req_order_shipped = 0 and
               t2.item_amount_shipped_stop = 0 and
               t2.item_amount > t2.item_amount_shipped and
               t1.req_crtdat between {$sql_datefrom} and {$sql_dateto} ";
            
   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t1.req_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t1.req_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_pcat"])
      $seasql .= " and t6.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
   if($_SESSION[$_sesmodulename]["sql_item_id"])
      $seasql .= " and t2.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                   and t2.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
   $datsql .= $seasql;
   $datsql .= " group by 1, 2 ";

   //----------------------------------------------------------------------------------
   $datsql .= "UNION ALL
               select t2.item_id, t2.item_type, t3.item_title, t3.item_number_prod,
                      t7.unit_name, t1.req_shop_id, t9.supp_short, t8.item_code,
                      SUM(t2.item_order_genamount) 'amount',
                      SUM(t2.item_amount - t2.item_amount_shipped) 'vamount'
               from orders t1
               INNER JOIN orders_items t2                   ON t1.id = t2.req_id
               INNER JOIN itemlist t3                       ON t2.item_id = t3.id
               INNER JOIN itemlist_shops t5                 ON ( t2.item_id = t5.item_id and t5.shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} )
               LEFT OUTER JOIN item_productcats_itemlist t6 ON ( t2.item_id = t6.item_id )
               LEFT OUTER JOIN item_units t7                ON t3.item_unit = t7.id
               INNER JOIN itemlist_suppliers t8             ON ( t2.item_id = t8.item_id and t8.item_supp_act = 1 )
               INNER JOIN supplier t9                       ON ( t8.supplier_id = t9.id )
               where
               t1.req_status        > 1 and
               t2.item_type         = 'itemlist' and
               t3.item_status       = 1 and
               t3.item_released     = 1 and
               t3.item_purchasable  = 1 and
               t1.req_order_shipped = 0 and
               t2.item_amount_shipped_stop = 0 and
               t2.item_amount > t2.item_amount_shipped and
               t1.req_crtdat between {$sql_datefrom} and {$sql_dateto} ";
   $datsql .= $seasql;
   $datsql .= " group by 1, 2
                order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}  ";

   $items = $CON->select($datsql);
   //----------------------------------------------------------------------------------

   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $selshops = Array();
      foreach($shops AS $shop)
         if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
            array_push($selshops, $shop);
   }

   //----------------------------------------------------------------------------------
   $pcats = formatFullProductCats(getFullProductCats($CON, 0));

   printJSsetCompanyShop($shops);
   ?>
   <script language="Javascript" src="./libs/jscripts/overlib/overlib.js"></script>
   <div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Generar ordenes de compra: Paso 1</b></td>
      <td align="right" class="content_row_clear"></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <table border="0" cellpadding="0" cellspacing="0" width="100%">
   <tr>
      <td>
         <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst"
         onsubmit="return checkform(new Array(this.sql_company, this.sql_shop))">
         <input type="hidden" name="subexec" value="search">
         <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
         <input type="hidden" name="printpdf" value="0">
         <input type="hidden" name="printxls" value="0">
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
            <td class="content_rowl">Proveedor</td>
            <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
            <td class="content_rowl">Empresa</td>
            <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Periodo</td>
            <td class="content_row">
               <table border="0" class="content_table" cellpadding="0" cellspacing="0">
               <tr>
                  <td class="content_row_clear" width="70">
                     <input type="radio" name="sql_selmode" value="2"
                     onclick="document.getElementById('idx_selmode1').style.display='none';
                              document.getElementById('idx_selmode2').style.display='';
                              document.getElementById('idx_selmode3').style.display='none';"
                     <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 2) echo "checked"?>> Meses
                  </td>
                  <td class="content_row_clear" width="205" id="idx_selmode2" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 2) echo "style='display:none'"?>>
                     <nobr>
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
                     &nbsp;-&nbsp;
                     <select class="text" name="sql_month2" id="sql_month2"
                     onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                        <?php
                        for($x = 1; $x <= 12; $x++)
                        {
                           $dsp_month = $x;
                           if($dsp_month < 10)
                              $dsp_month = "0{$dsp_month}";
                           ?>
                           <option value="<?=$x?>"
                           <?php if($x == $_SESSION[$_sesmodulename]["sql_month2"]) echo "selected" ?>><?=$dsp_month?></option>
                           <?php
                        }
                        ?>
                     </select>
                     <select class="text" name="sql_year2" id="sql_year2"
                     onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                        <?php
                        $startyear  = date('Y') -3;
                        $endyear    = date('Y');

                        for($x = $startyear; $x <= $endyear; $x++)
                        {
                           ?>
                           <option value="<?=$x?>"
                           <?php if($x == $_SESSION[$_sesmodulename]["sql_year2"]) echo "selected" ?>><?=$x?></option>
                           <?php
                        }
                        ?>
                     </select>
                     </nobr>
                  </td>
                  <td class="content_row_clear" width="50">
                     <input type="radio" name="sql_selmode" value="1"
                     onclick="document.getElementById('idx_selmode1').style.display='';
                              document.getElementById('idx_selmode2').style.display='none';
                              document.getElementById('idx_selmode3').style.display='none';"
                     <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 1) echo "checked"?>> Dia
                  </td>
                  <td class="content_row_clear" width="110" id="idx_selmode1" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 1) echo "style='display:none'"?>>
                     <nobr>
                     <input type="text" style="width:80px" id="sql_date" name="sql_date"
                     class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                     onfocus="markfield(this,0)" onblur="markfield(this,1)"
                     value="<?=$_SESSION[$_sesmodulename]["sql_date"]?>">
                     </nobr>
                  </td>
                  <td class="content_row_clear" width="75">
                     <input type="radio" name="sql_selmode" value="3"
                     onclick="document.getElementById('idx_selmode1').style.display='none';
                              document.getElementById('idx_selmode2').style.display='none';
                              document.getElementById('idx_selmode3').style.display='';"
                     <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 3) echo "checked"?>> Periodo
                  </td>
                  <td class="content_row_clear" width="180" id="idx_selmode3" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 3) echo "style='display:none'"?>>
                     <nobr>
                     <input type="text" style="width:65px" id="sql_date_pfrom" name="sql_date_pfrom"
                     class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                     onfocus="markfield(this,0)" onblur="markfield(this,1)"
                     value="<?=$_SESSION[$_sesmodulename]["sql_date_pfrom"]?>">
                     -
                     <input type="text" style="width:65px" id="sql_date_pto" name="sql_date_pto"
                     class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                     onfocus="markfield(this,0)" onblur="markfield(this,1)"
                     value="<?=$_SESSION[$_sesmodulename]["sql_date_pto"]?>">
                     </nobr>
                  </td>
               </tr>
               </table>
            </td>
            <td class="content_rowl">Sucursal</td>
            <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Familia</td>
            <td class="content_row">
               <select class="text" name="sql_pcat" style="width:375px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($pcats AS $pcat)
                  {  ?>
                     <option value="<?=$pcat["id"]?>"
                     <?php if($pcat["id"] == $_SESSION[$_sesmodulename]["sql_pcat"]) echo "selected"?>><?=sprintf("%03s", $pcat["id"])?> - <?=$pcat["cat_title"]?>
                     </option>
                     <?php
                  }
                  ?>
               </select>
            </td>
            <td class="content_rowl">Artículo</td>
            <td class="content_row"><?php printOverviewItemSelect($_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Stock minimo</td>
            <td class="content_row">
               <input type="checkbox" name="sql_stockmin" value="1" <?php if($_SESSION[$_sesmodulename]["sql_stockmin"]) echo "checked"?>> Incluir articulos bajo stock minimo/sin ventas
            </td>
         </tr>
         <tr>
            <td class="content_row" align="right" colspan="4">
               <table border="0" cellpadding="0" cellspacing="0" width="100%">
               <colgroup>
                  <col width="132">
                  <col>
                  <col width="132">
                  <col width="132">
               </colgroup>
               <tr>
                  <td align="left">
                     <?php
                     if($items)
                     {
                        printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                        $_SESSION["_SUBMITBTN"] = 1;
                     }
                     ?>
                  </td>
                  <td align="left">
                     <?php
                     if($items)
                     {
                        printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                        $_SESSION["_SUBMITBTN"] = 1;
                     }
                     ?>
                  </td>
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
         <form action="index.php" method="post" name="xform_ordgen">
         <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
         <input type="hidden" name="exec" value="save">
         <?=Nifty_printH("box1", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="70">
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col width="60">
            <col width="200">
            <col width="50">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader content_row_os" valign="top" align="center" style="border-right:3px double black"><nobr>Codigo Prov.</nobr></td>
            <td class="content_tbl_subheader content_row_os" valign="top" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader content_row_os" valign="top" align="right">Venta</td>
            <td class="content_tbl_subheader content_row_os" valign="top" align="center">Act</td>
            <td class="content_tbl_subheader content_row_os" valign="top" align="center">Tran</td>
            <td class="content_tbl_subheader content_row_os" valign="top" align="center">Min</td>
            <td class="content_tbl_subheader content_row_os" valign="top" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
            <td class="content_tbl_subheader content_row_os" valign="top" align="center">Compra</td>
         </tr>
         <?php

         //----------------------------------------------------------------------------------
         $x = 0;
         foreach($items AS $row)
         {
            $stcontent  = "OK";
            $stcss      = "#D5FFD7";
            $shwItem    = false;
                     
            $suppliers  = getItemSuppliers($CON, $row["item_id"], $row["item_type"]);
            $itemsts    = getItemStorehouses($CON, $row["req_shop_id"], $row["item_id"], $row["item_type"]);
            if(count($itemsts))
            {
               foreach(array_keys($itemsts) AS $stid)
               {
                  $stcurrstock = getItemShopStorehouseCurrentStock($CON, $row["req_shop_id"], $stid, $row["item_id"], $row["item_type"], true);
                  $stminstock  = getItemShopStorehouseMinStock($CON, $row["req_shop_id"], $stid, $row["item_id"], $row["item_type"], true);

                  if(($stcurrstock <= $stminstock && $stminstock > 0) || $stcurrstock < 0)
                  {
                     $stcontent  = "CRITICO";
                     $stcss      = "#FFE1E7";
                  }
               }
            }

            if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
            {
               foreach($suppliers AS $supplier)
                  if($supplier["supplier_id"] == $_SESSION[$_sesmodulename]["sql_supplier"])
                     $shwItem = true;
            }
            else
               $shwItem = true;

            if($shwItem)
            {
               if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_amount_{$x}";
               ?>
               <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os"><?=$row["item_number_prod"]?></td>
                  <td class="content_row_os"><?=$row["item_title"]?></td>
                  <td class="content_row_os"><?=$row["unit_name"]?></td>
                  <td class="content_row_os" style="border-right:3px double black"><?=$row["item_code"]?>&nbsp;</td>
                  <td class="content_row_os" align="center"><?=printPrice($row["amount"], 2, true)?></td>
                  <td class="content_row_os" align="center">
                     <div style="cursor:pointer" onclick="showFancybox('/libs/modules/supplier_order/show.customerbuy.fancy.php?item_id=<?=$row["item_id"]?>&item_type=<?=$row["item_type"]?>', 'iframe', 600, 400, 'auto')">
                        <?=printPrice($row["vamount"], 2, true)?>
                     </div>
                  </td>
                  <td class="content_row_os" align="center">
                     <?php
                     $storehousestock = printFancyBoxStock($CON, $row["req_shop_id"], $row["item_id"], $row["item_type"], "storehousestock");
                     ?>
                  </td>
                  <td class="content_row_os" align="center">
                     <?php
                     $transstock = printFancyBoxStock($CON, $row["req_shop_id"], $row["item_id"], $row["item_type"], "transstock");
                     ?>
                  </td>
                  <td class="content_row_os" align="center" style="background-color:<?=$stcss?>"><?=$stcontent?></td>
                  <td class="content_row_os" align="right">
                     <nobr>
                     <img src="./images/menu/icons/information.png" style="vertical-align:bottom;cursor:pointer"
                     onmouseover="return overlib('<iframe src=\'/libs/modules/supplier_order/generate.showinfo.php?item_id=<?=$row["item_id"]?>&item_type=<?=$row["item_type"]?>\' scrolling=\'no\' frameborder=\'0\' style=\'width:600px;height:230px\'></iframe>', WIDTH, 350, FGCOLOR, '#FFFFFF', BGCOLOR, '#698988', HAUTO, VAUTO)"
                     onmouseout="return nd()">
                     <select class="text" name="item_supplier_<?=$x?>"
                     style="width:200px;<?php if(count($suppliers) == 1 && $suppliers != false) echo "background-color:#EEEEEE"?>"
                     <?php if(count($suppliers) > 1 && $suppliers != false) { ?> onmousedown="markfield(this,0)" onblur="markfield(this,1)" <?}?>>
                        <?php
                        foreach($suppliers AS $supplier)
                        {  ?>
                           <option value="<?=$supplier["supplier_id"]?>"
                           <?php if(((int)$supplier["item_supp_act"] && !$_SESSION[$_sesmodulename]["sql_supplier"]) ||
                                 ($_SESSION[$_sesmodulename]["sql_supplier"] && $supplier["supplier_id"] == $_SESSION[$_sesmodulename]["sql_supplier"]))
                              {
                                 echo "selected";
                                 $thissuppname = $supplier["supp_short"];
                              }
                              ?>><?=$supplier["supp_short"]?></option>
                           <?php
                        }
                        ?>
                     </select>
                     </nobr>
                  </td>
                  <td class="content_row_os" align="right">
                     <input type="text" class="text" style="width:50px;text-align:right" autocomplete="off"
                     onfocus="markfield(this,0)" onblur="markfield(this,1)"
                     name="item_amount_<?=$x?>" id="item_amount_<?=$x?>"
                     value="<?php if($row["amount"] > 0.00) echo printPrice($row["amount"], 2)?>">
                     <input type="hidden" name="item_id_<?=$x?>" value="<?=$row["item_id"]?>">
                     <input type="hidden" name="item_type_<?=$x?>" value="<?=$row["item_type"]?>">
                     <input type="hidden" name="item_shopid_<?=$x?>" value="<?=$row["req_shop_id"]?>">
                  </td>
               </tr>
               <?php
               //----------------------------------------------------------------------------------
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]  = $row["item_number_prod"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]        = $row["item_title"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unit_name"]         = $row["unit_name"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_code"]         = $row["item_code"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["amount"]            = printPrice($row["amount"], 2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["vamount"]           = printPrice($row["vamount"], 2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["storehousestock"]   = printPrice($storehousestock, 2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["transstock"]        = printPrice($transstock, 2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["stcontent"]         = $stcontent;
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["thissuppname"]      = $thissuppname;
               $x++;
            }
         }

         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" colspan="10" align="center">
                  <br>
                  <b class="msg_save_err">No hay datos disponibles.</b>
                  <br><br>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?php
         if($x)
         {  ?>
            <?=Nifty_printH("boxopt_b", "980")?>
            <table border="0" cellspacing="0" cellpadding="0" width="100%">
            <tr>
               <td>&nbsp;</td>
               <td align="right" width="130">
                  <?php
                  printButton("Prevista OC", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_ordgen)", "tick-circle-frame");
                  ?>
               </td>
            </tr>
            </table>
            <?=Nifty_printF(false)?>
            <?php
         }
         ?>
         </form>
      </td>
   </tr>
   </table>
   <?php
}
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createSupplierOrderGen($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createSupplierOrderGen($CON);
  
if($pdffile != "")
{
   $doctitle = "Generar-Ordenes-de-Compra-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Generar-Ordenes-de-Compra-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>