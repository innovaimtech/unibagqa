<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "itemlists";
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
      $_SESSION[$_sesmodulename]["sql_stext"]      = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
      $_SESSION[$_sesmodulename]["page"]           = 0;
      $_SESSION[$_sesmodulename]["search_active"]  = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);
   
   if($_REQUEST["exec"] == "del" && (int)$_REQUEST["id"])
   {
      $sql = " update itemlist
               set item_status = 0
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);
   }

   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " LEFT OUTER JOIN item_units t13 ON t1.item_unit = t13.id
               LEFT OUTER JOIN itemlist_suppliers t7 ON t1.id = t7.item_id";

   if($_SESSION[$_sesmodulename]["sql_pcat"])
      $joisql .= " INNER JOIN item_productcats_itemlist t6 ON t1.id = t6.item_id ";
   if($_SESSION[$_sesmodulename]["sql_company"])
      $joisql .= " INNER JOIN itemlist_shops t8 ON t1.id = t8.item_id
                   INNER JOIN company_shops t9 ON t8.shop_id = t9.id ";

   //----------------------------------------------------------------------------------
   $cntsql = " select count(distinct t1.id) 'cc'
               from itemlist t1
               {$joisql}
               where
               t1.item_status = 1 ";

   $datsql = " select distinct t1.item_released, t1.item_number_prod, t1.item_title, t1.item_sellprice_netto,
                      t13.unit_name, t1.item_unit_amount, t1.id
               from itemlist t1
               {$joisql}
               where
               t1.item_status = 1 ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t9.shop_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t9.id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_supplier"])
      $seasql .= " and t7.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
   if($_SESSION[$_sesmodulename]["sql_pcat"])
      $seasql .= " and t6.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";

   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
   {
      $seasql .= " and (t1.item_title        like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t1.item_number_prod  like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t1.item_number       like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t7.item_code         like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%') ";
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
   if($_SESSION[$_sesmodulename]["sql_company"])
   {
      $selshops = Array();
      foreach($shops AS $shop)
         if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
            array_push($selshops, $shop);
   }

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_shop"])
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
      <td height="30"><b class="content_header">Resumen de packs</b></td>
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
            <td class="content_rowl">&nbsp;</td>
            <td class="content_row">
               <select class="text" name="sql_storehouse" style="width:280px;display:none"
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
               &nbsp;
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
   <?php
   //----------------------------------------------------------------------------------
   ?>
   <tr>
      <td>
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
            <td class="content_tbl_subheader">Contenido</td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         for($x = 0; $x < count($items) && $items != false; $x++)
         {
            if((int)$items[$x]["item_released"] == 0)
               $img_status = "status_red.gif";
            else
               $img_status = "status_green.gif";

            $posamt = 0;
            $itemlistpos = getItemListContent($CON, $items[$x]["id"]);
            foreach($itemlistpos AS $itemlistrow)
            {
               $posamt += $itemlistrow["item_amount"];
               $pouname = $itemlistrow["unit_name"];
            }

            $costdata = getSupplierItemCosts($CON, 0, $items[$x]["id"], "itemlist");
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row" align="center"><img src="./images/content/<?=$img_status?>"></td>
               <td class="content_row"><nobr><?=$items[$x]["item_number_prod"]?></nobr></td>
               <td class="content_row"><?=$items[$x]["item_title"]?>&nbsp;</td>
               <td class="content_row"><nobr><?=$costdata["item_code"]?>&nbsp;</nobr></td>
               <td class="content_row" align="left">$&nbsp;<?=printprice($costdata["item_costprice_netto"])?></td>
               <td class="content_row" align="left">$&nbsp;<?=printPrice($items[$x]["item_sellprice_netto"])?></td>
               <td class="content_row"><?=printPrice($items[$x]["item_unit_amount"],2)?>&nbsp;<?=$items[$x]["unit_name"]?></td>
               <td class="content_row"><?=printPrice($posamt,2)?>&nbsp;<?=$pouname?></td>
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
               <td class="content_row" colspan="9" align="center" valign="middle" height="30">
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
?>
