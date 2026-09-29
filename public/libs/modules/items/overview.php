<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "items";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Act." => "1", "Número" => "2", "Nombre" => "3", "Precio (neto)" => "4", "Unidad" => "5,6");

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

if($_REQUEST["exec"] == "edit")
   require_once("overview.edit.php");
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $_SESSION[$_sesmodulename]["sql_company"]    = (int)$_REQUEST["sql_company"];
      $_SESSION[$_sesmodulename]["sql_shop"]       = (int)$_REQUEST["sql_shop"];
      $_SESSION[$_sesmodulename]["sql_supplier"]   = (int)$_REQUEST["sql_supplier"];
      $_SESSION[$_sesmodulename]["sql_pcat"]       = (int)$_REQUEST["sql_pcat"];
      $_SESSION[$_sesmodulename]["sql_storehouse"] = (int)$_REQUEST["sql_storehouse"];
      $_SESSION[$_sesmodulename]["sql_equvals"]    = $_REQUEST["sql_equvals"];
      $_SESSION[$_sesmodulename]["sql_xitemtype"]     = (int)$_REQUEST["sql_xitemtype"];
      $_SESSION[$_sesmodulename]["sql_stext"]      = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
      $_SESSION[$_sesmodulename]["page"]           = 0;
      $_SESSION[$_sesmodulename]["search_active"]  = 1;

      unset($_SESSION[$_sesmodulename]["sql_comvals"]);
      foreach(array_keys($_REQUEST) AS $reqkey)
      {
         if(strpos($reqkey, "sql_comvals_") !== false && strpos($reqkey, "sql_comvals_") == 0)
         {
            $compid = substr($reqkey, strrpos($reqkey, "_") +1);

            foreach($_REQUEST[$reqkey] AS $compvalid)
               $_SESSION[$_sesmodulename]["sql_comvals"][$compid][(int)$compvalid] = 1;
         }
      }
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);
   
   if($_REQUEST["exec"] == "del" && (int)$_REQUEST["id"])
   {
      $sql = " update item
               set item_status = 0
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " LEFT OUTER JOIN item_units t13 ON t1.item_unit = t13.id
               LEFT OUTER JOIN item_suppliers t7 ON t1.id = t7.item_id
               LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item') ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_pcat"])
      $joisql .= " INNER JOIN item_productcats t6 ON t1.id = t6.item_id ";
   if($_SESSION[$_sesmodulename]["sql_company"])
      $joisql .= " INNER JOIN item_shops t8 ON t1.id = t8.item_id
                   INNER JOIN company_shops t9 ON t8.shop_id = t9.id ";
   if($_SESSION[$_sesmodulename]["sql_storehouse"])
      $joisql .= " INNER JOIN item_shops_storehouses t12 ON ( t1.id = t12.item_id and t9.id = t12.shop_id )";

   //----------------------------------------------------------------------------------
   $cntsql = " select count(distinct t1.id) 'cc'
               from item t1
               {$joisql}
               where
               t1.item_status = 1 ";

   $datsql = " select distinct t1.item_released, t1.item_number_prod, t1.item_title, t1.item_sellprice_netto,
                      t13.unit_name, t1.item_unit_amount, t1.id
               from item t1
               {$joisql}
               where
               t1.item_status = 1 ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t9.shop_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t9.id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_storehouse"])
      $seasql .= " and t12.st_id = {$_SESSION[$_sesmodulename]["sql_storehouse"]} ";
   if($_SESSION[$_sesmodulename]["sql_supplier"])
      $seasql .= " and t7.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
   if($_SESSION[$_sesmodulename]["sql_pcat"])
      $seasql .= " and t6.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
      $seasql .= " and t1.item_ventaonline_act = 1 ";
   elseif((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
      $seasql .= " and t1.item_ventaonline_act = 0 ";
   //----------------------------------------------------------------------------------
   $sql_filter_equval = "";
   foreach($_SESSION[$_sesmodulename]["sql_equvals"] AS $sql_equval)
      $sql_filter_equval .= "{$sql_equval},";
   $sql_filter_equval = substr($sql_filter_equval, 0, -1);
   if($sql_filter_equval != "")
   {
      $seasql .= " and
                   (
                      select count(*) 'cc'
                      from item_equipos_rel txx10
                      where
                      txx10.item_id    = t1.id and
                      txx10.equipo_id  IN ({$sql_filter_equval}) 
                   ) > 0 ";
   }

   //----------------------------------------------------------------------------------
   $sql_filter_comval = "";
   foreach(array_keys($_SESSION[$_sesmodulename]["sql_comvals"]) AS $sql_comid)
   {
      $sql_subfilter_comvalids = implode(",", array_keys($_SESSION[$_sesmodulename]["sql_comvals"][$sql_comid]));
      $sql_filter_comval .= " and
                              (
                                 select count(*) 'cc'
                                 from tran_comments_item_vals txx11
                                 where
                                 txx11.item_id = t1.id and
                                 txx11.val_id  IN ({$sql_subfilter_comvalids})
                              ) > 0 ";
   }
   if($sql_filter_comval != "")
      $seasql .= $sql_filter_comval;
   
   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
   {
      $seasql .= " and (t1.item_title        like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t1.item_number_prod  like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t1.item_number       like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t7.item_code         like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        tx.item_barcode      = '{$_SESSION[$_sesmodulename]["sql_stext"]}') ";
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
   $items = $CON->select($datsql);

   //----------------------------------------------------------------------------------
   $suppliers  = getSuppliers($CON);
   $companies  = getCompanies($CON);
   $shops      = getShops($CON);

   //----------------------------------------------------------------------------------
   $sql = " select *
            from company_shops_storehouses
            where
            st_status  = 1
            order by st_name";
   $storehouses = $CON->select($sql);

   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $selshops = Array();
      foreach($shops AS $shop)
         if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
            array_push($selshops, $shop);
   }

   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $selstorehouses = Array();
      foreach($storehouses AS $storehouse)
         if($storehouse["st_shop_id"] == $_SESSION[$_sesmodulename]["sql_shop"])
            array_push($selstorehouses, $storehouse);
   }
   
   //----------------------------------------------------------------------------------
   $pcats = formatFullProductCats(getFullProductCats($CON, 0));

   //----------------------------------------------------------------------------------
   ?>
   <script language="JavaScript">
      function setCompanyShop(companyidx)
      {
         var obj = document.all.sql_shop;
         obj.options.length = 1;
         document.all.sql_storehouse.options.length = 1;
         <?php
         foreach($shops AS $shop)
         {  ?>
            if(companyidx == '<?=$shop["shop_company_id"]?>')
            {
               var newIndex   = obj.options.length;
               var newOpt     = new Option('<?=addslashes($shop["shop_name"])?>');
               newOpt.value   = '<?=$shop["id"]?>';
               obj.options[newIndex] = newOpt;
            }
            <?php
         }
         ?>
      }

      function setCompanyShopStorehouse(shopidx)
      {
         var obj = document.all.sql_storehouse;
         obj.options.length = 1;

         <?php
         foreach($storehouses AS $storehouse)
         {  ?>
            if(shopidx == '<?=$storehouse["st_shop_id"]?>')
            {
               var newIndex   = obj.options.length;
               var newOpt     = new Option('<?=addslashes($storehouse["st_name"])?>');
               newOpt.value   = '<?=$storehouse["id"]?>';
               obj.options[newIndex] = newOpt;
            }
            <?php
         }
         ?>
      }
   </script>
   <?php
   //----------------------------------------------------------------------------------
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Resumen de articulos</b></td>
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
         <?=Nifty_printH("box1", "980")?>
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
            <td class="content_rowl">Palabra</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:375px"
               value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
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
         </tr>
         <tr>
            <td class="content_rowl">Proveedor</td>
            <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
            <td class="content_rowl">Sucursal</td>
            <td class="content_row">
               <select class="text" name="sql_shop" style="width:375px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)"
               onchange="setCompanyShopStorehouse(this.value)">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($selshops AS $selshop)
                  {  ?>
                     <option value="<?=$selshop["id"]?>"
                     <?php if($selshop["id"] == $_SESSION[$_sesmodulename]["sql_shop"]) echo "selected"?>><?=$selshop["shop_name"]?>
                     </option><?php
                  }
                  ?>
               </select>
            </td>
         </tr>
         <tr>
            <td class="content_rowl">Familia</td>
            <td class="content_row">
               <select class="text" name="sql_pcat" style="width:375px"
               onchange="unibLoadSpecCharFilters(this.value)"
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
            <td class="content_rowl">Bodega</td>
            <td class="content_row">
               <select class="text" name="sql_storehouse" style="width:375px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($selstorehouses AS $selstorehouse)
                  {  ?>
                     <option value="<?=$selstorehouse["id"]?>"
                     <?php if($selstorehouse["id"] == $_SESSION[$_sesmodulename]["sql_storehouse"]) echo "selected"?>><?=$selstorehouse["st_name"]?>
                     </option><?php
                  }
                  ?>
               </select>
            </td>
         </tr>
         <tr id="idx_charact_opts" style="<?if(!(int)$_SESSION[$_sesmodulename]["sql_pcat"]) echo "display:none"?>">
            <td class="content_rowl" valign="top">Caracteristicas</td>
            <td class="content_row" colspan="4">
               <div id="idx_charact_jqres">
                  <?php
                  printPcatFilters($CON, $_SESSION[$_sesmodulename]["sql_pcat"], $_sesmodulename)
                  ?>
               </div>
            </td>
         </tr>
         <tr>
            <td class="content_rowl">Tipo producto</td>
            <td class="content_row">
               <input type="radio" name="sql_xitemtype" value="0" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 0) echo "checked"?>> Todos
               <input type="radio" name="sql_xitemtype" value="1" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 1) echo "checked"?>> Solo venta online
               <input type="radio" name="sql_xitemtype" value="2" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 2) echo "checked"?>> Solo otros
            </td>
            <td class="content_rowl">&nbsp;</td>
            <td class="content_row">&nbsp;</td>
         </tr>
         <tr>
            <td class="content_row" align="right" colspan="4">
               <table border="0" cellpadding="0" cellspacing="0" width="100%">
               <tr>
                  <td align="left" width="1">
                     <?php
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     ?>
                  </td>
                  <td align="right" style="padding-right:5px">
                     <?php
                     if((int)$_SESSION[$_sesmodulename]["search_active"])
                        printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                     ?>
                  </td>
                  <td align="right" width="1">
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
   <?php
   //----------------------------------------------------------------------------------
   ?>
   <tr>
      <td class="content_row_clear">
         <?=Nifty_printH("box1", "980")?>
         <?php
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="40">
            <col width="80">
            <col>
            <col>
            <col width="90">
            <col width="90">
            <col width="90">
            <col width="85">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader" align="center"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader">Codigo Proveedor</td>
            <td class="content_tbl_subheader">Costo (neto)</td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         for($x = 0; $x < count($items) && $items != false; $x++)
         {
            if((int)$items[$x]["item_released"] == 0)
               $img_status = "status_red.gif";
            else
               $img_status = "status_green.gif";

            $costdata = getSupplierItemCosts($CON, 0, $items[$x]["id"], "item");
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row" align="center"><img src="./images/content/<?=$img_status?>"></td>
               <td class="content_row"><nobr><?=$items[$x]["item_number_prod"]?></nobr></td>
               <td class="content_row"><?=$items[$x]["item_title"]?>&nbsp;</td>
               <td class="content_row"><nobr><?=$costdata["item_code"]?>&nbsp;</nobr></td>
               <td class="content_row" align="left">$&nbsp;<?=printprice($costdata["item_costprice_netto"])?></td>
               <td class="content_row" align="left">$&nbsp;<?=printprice($items[$x]["item_sellprice_netto"])?></td>
               <td class="content_row"><?=$items[$x]["unit_name"]?>&nbsp;</td>
               <td class="content_row" align="center">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$items[$x]["id"]}", "", "pencil"); 
                  ?>
               </td>
            </tr>
            <?php
         }
         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" colspan="8" align="center" valign="middle" height="30">
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
   if($_REQUEST["printxls"])
      $xlsfile = xls_createStatsItemOverview($CON);
   if($xlsfile != "")
   {
      $doctitle = "Productos-".time().".xls";
      $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
      ?>
      <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
      <?php
   }
}
?>
