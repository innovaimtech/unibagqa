<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
// item.stock.php
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stock_products";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Numero" => "1", "Articulo/Familia" => "2");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_company"]    = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]       = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_item_id"]    = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"]  = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]       = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_supplier"]   = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]    = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_stockmode"]  = (int)$_REQUEST["sql_stockmode"];
   $_SESSION[$_sesmodulename]["sql_storehouse"] = (int)$_REQUEST["sql_storehouse"];
   $_SESSION[$_sesmodulename]["sql_xitemtype"]     = (int)$_REQUEST["sql_xitemtype"];
   $_SESSION[$_sesmodulename]["sql_xhasstock"]     = (int)$_REQUEST["sql_xhasstock"];
   $_SESSION[$_sesmodulename]["sql_equvals"]    = $_REQUEST["sql_equvals"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_ccnum"]      = trim(addslashes($_REQUEST["sql_ccnum"]));
   $_SESSION[$_sesmodulename]["sql_fab_design_name"] = trim(addslashes($_REQUEST["sql_fab_design_name"]));
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
$sql = " select t7.id, SUM(t2.item_amount - t2.item_amount_shipped) 'transstock'
         from supplier_order t1
         INNER JOIN supplier_order_items  t2 ON t1.id       = t2.sord_id
         LEFT OUTER JOIN item_suppliers        t4 ON ( t2.item_id = t4.item_id and t4.supplier_id = t1.sord_supplier_id )
         LEFT OUTER JOIN supplier              t5 ON ( t4.supplier_id = t5.id )
         LEFT OUTER JOIN item_productcats t6 ON t2.item_id = t6.item_id
         INNER JOIN item                  t7 ON t2.item_id = t7.id and t7.item_status > 0
         LEFT OUTER JOIN item_units       t8 ON t7.item_unit = t8.id
         where
         t1.sord_order_shipped   = 0 and
         t1.sord_status          IN (2,3) and
         t2.item_type            = 'item' and
         t2.item_amount          > t2.item_amount_shipped
         group by 1";
$transitems = $CON->select($sql);
foreach($transitems AS $transitem)
   $_TRANSSTOCK[$transitem["id"]] += $transitem["transstock"];

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON, true);
$shops      = getShops($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
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
// if((int)$_SESSION[$_sesmodulename]["sql_shop"] == 30010 && !(int)$_SESSION[$_sesmodulename]["sql_storehouse"])
   // $_SESSION[$_sesmodulename]["sql_storehouse"] = $_CONFIG["_REPORTS_DEFAULT_STHID"];

if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;
// if(!(int)$_SESSION[$_sesmodulename]["sql_stockmode"])
   // $_SESSION[$_sesmodulename]["sql_stockmode"] = 1;

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{

   //----------------------------------------------------------------------------------
   $joisql = " INNER JOIN item_shops      t2 ON t1.id = t2.item_id
               INNER JOIN company_shops   t3 ON ( t2.shop_id = t3.id and t3.shop_status = 1 )
               INNER JOIN company_data    t4 ON ( t3.shop_company_id = t4.id and t4.company_status = 1 )
               LEFT OUTER JOIN ubicacion t11a     ON t1.item_ubicacion = t11a.id
               LEFT OUTER JOIN item_suppliers  t5 ON ( t1.id = t5.item_id and ";

   if($_SESSION[$_sesmodulename]["sql_supplier"])
      $joisql .= " t5.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ) ";
   else
      $joisql .= " t5.item_supp_act = 1 ) ";

   $joisql .= " LEFT OUTER JOIN supplier t6 ON ( t5.supplier_id = t6.id )
                LEFT OUTER JOIN item_productcats t7 ON t1.id = t7.item_id
                LEFT OUTER JOIN productcats t8   ON t7.cat_id = t8.id
                LEFT OUTER JOIN item_units t9    ON t1.item_unit = t9.id
                LEFT OUTER JOIN item_shops_storehouses     t10 ON ( t2.item_id = t10.item_id and t2.shop_id = t10.shop_id )
                LEFT OUTER JOIN company_shops_storehouses  t11 ON ( t11.id = t10.st_id and t2.shop_id = t11.st_shop_id AND t11.st_status >0 ) ";

   //----------------------------------------------------------------------------------
   $cntsql = " select count(distinct t1.id) 'cc'
               from item t1
               {$joisql}
               where
               t1.item_status    = 1 and
               t1.item_released  = 1 ";

   $datsql = " select distinct t1.item_number_prod, t1.item_title, t1.id, t4.company_short, t3.shop_name, 'item_type' 'I',
               t7.cat_id, t8.cat_title, t9.unit_name, t11a.ubi_name, t1.item_sellprice_netto
               from item t1
               {$joisql}
               where
               t1.item_status    = 1 and
               t1.item_released  = 1 ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_item_type"] == "itemlist")
   {
      $datsql .= " and 1 = 2 ";
      $cntsql .= " and 1 = 2 ";
   }
   elseif($_SESSION[$_sesmodulename]["sql_item_type"] == "item")
   {
      $datsql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
      $cntsql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
   }

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t3.shop_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t2.shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_supplier"])
      $seasql .= " and t5.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
   if($_SESSION[$_sesmodulename]["sql_pcat"])
      $seasql .= " and t7.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
   // if($_SESSION[$_sesmodulename]["sql_stockmode"] == 1)
      // $seasql .= " and t10.iss_inventory > 0 ";
   if($_SESSION[$_sesmodulename]["sql_storehouse"])
      $seasql .= " and t10.st_id = {$_SESSION[$_sesmodulename]["sql_storehouse"]}  ";
   if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
      $seasql .= " and t1.item_ventaonline_act = 1 ";
   elseif((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
      $seasql .= " and t1.item_ventaonline_act = 0 ";

   if((int)$_SESSION[$_sesmodulename]["sql_xhasstock"] == 1)
      $seasql .= " and t10.iss_inventory > 0 ";
   elseif((int)$_SESSION[$_sesmodulename]["sql_xhasstock"] == 2)
      $seasql .= " and t10.iss_inventory <= 0 ";

   if($_SESSION[$_sesmodulename]["sql_ccnum"] != "")
   {
      $sql = " select t1.*, t4.cust_name, t2.*, t1.id 'req_id'
               from orders t1
               INNER JOIN orders_items t2 ON t1.id = t2.req_id
               INNER JOIN customer t4     ON t1.req_cust_id = t4.id
               where
               t1.req_status > 0 and
               t1.req_number = '{$_SESSION[$_sesmodulename]["sql_ccnum"]}' and
               t1.req_isfabricate = 1";
      $orderinfo = $CON->select($sql);
      $orderinfo = $orderinfo[0];
      $seasql .= " and t10.iss_order_id = {$orderinfo["req_id"]} ";
   }
   if($_SESSION[$_sesmodulename]["sql_fab_design_name"] != "")
   {
      $sql = " select t1.*, t4.cust_name, t2.*, t1.id 'req_id'
               from orders t1
               INNER JOIN orders_items t2 ON t1.id = t2.req_id
               INNER JOIN customer t4     ON t1.req_cust_id = t4.id
               where
               t1.req_status        > 0 and
               t2.fab_design_name   like '%{$_SESSION[$_sesmodulename]["sql_fab_design_name"]}%' and
               t1.req_isfabricate   = 1";
      $orderinfos = $CON->select($sql);
      $sql_reqids = "";

      foreach($orderinfos AS $orderinfo)
         $sql_reqids .= "{$orderinfo["id"]},";
      $sql_reqids = substr($sql_reqids, 0, -1);

      $seasql .= " and t10.iss_order_id IN ({$sql_reqids}) ";

   }
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   {
       $seasql .= " and
                    (
                       select count(*)
                       from orders suba
                       where
                       suba.id               = t10.iss_order_id and
                       suba.req_status       > 0 and
                       suba.req_isfabricate  = 1 and
                       suba.req_cust_id      = {$_SESSION[$_sesmodulename]["sql_customer"]}
                    ) > 0 ";
   }

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
   $cntsql   .= $seasql;
   $datsql   .= $seasql;

   $itemcount = $CON->select($cntsql);
   $itemcount = (int)$itemcount[0]["cc"] + (int)$itemcount[1]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
   {
      $_SESSION[$_sesmodulename]["orderBy"] = "7";
      $_SESSION[$_sesmodulename]["orderSort"] = "asc";
   }

   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";

   //----------------------------------------------------------------------------------
   
   $items = $CON->select($datsql);
}
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
   $sql = " select *
            from company_shops_storehouses
            where
            st_status  = 1
            order by st_name";
   $storehouses = $CON->select($sql);

   $selstorehouses = Array();
   foreach($storehouses AS $storehouse)
      if($storehouse["st_shop_id"] == $_SESSION[$_sesmodulename]["sql_shop"])
         array_push($selstorehouses, $storehouse);

}

$_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"] = $selstorehouses;

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
{
   $sql = " select supp_company
            from supplier
            where
            id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
   $suppdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"] = $suppdata[0]["supp_company"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"] = "TODO";


$stylehead = "border-bottom: 3px double #666666";

printJSsetCompanyShop($shops);
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
<script language="JavaScript">
function detectEvent (event, mode)
{
   var xurl = './libs/modules/items/searchitem.fancy.php?mode=' +mode;
   var keyCode = ('which' in event) ? event.which : event.keyCode;
   if(keyCode == 112)
      showFancybox(xurl, 'iframe', 1000, 450, 'auto');
}
</script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Stock por producto</b></td>
   <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults($itemcount); else echo $savemsg;?></td>
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
         <td class="content_tbl_header" colspan="4">Opciones de busqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Articulo</td>
         <td class="content_row"><?php printOverviewItemSelect($_sesmodulename) ?></td>
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
      <tr>
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
         <td class="content_rowl">Nro CC</td>
         <td class="content_row">
            <input type="text" class="text" style="width:100%" name="sql_ccnum" value="<?=$_SESSION[$_sesmodulename]["sql_ccnum"]?>">
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
         <td class="content_rowl">Diseño</td>
         <td class="content_row">
            <input type="text" class="text" style="width:100%"
            name="sql_fab_design_name" value="<?=$_SESSION[$_sesmodulename]["sql_fab_design_name"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_rowl">Stock</td>
         <td class="content_row">
            <input type="radio" name="sql_xhasstock" value="0" <?php if($_SESSION[$_sesmodulename]["sql_xhasstock"] == 0) echo "checked"?>> Todos
            <input type="radio" name="sql_xhasstock" value="1" <?php if($_SESSION[$_sesmodulename]["sql_xhasstock"] == 1) echo "checked"?>> Solo con stock
            <input type="radio" name="sql_xhasstock" value="2" <?php if($_SESSION[$_sesmodulename]["sql_xhasstock"] == 2) echo "checked"?>> Solo sin stock
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo producto</td>
         <td class="content_row">
            <input type="radio" name="sql_xitemtype" value="0" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 0) echo "checked"?>> Todos
            <input type="radio" name="sql_xitemtype" value="1" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 1) echo "checked"?>> Solo venta online
            <input type="radio" name="sql_xitemtype" value="2" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 2) echo "checked"?>> Solo otros
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
                  if($itemcount > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if($itemcount > 0)
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
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{  ?>
   <tr>
      <td>
         <?=Nifty_printH("box1", "99%")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="70">
            <col>
            <col width="40">
            <col>
            <col width="100">
            <?php
            if((int)$_REQUEST["mid"] != 1215)
            {  ?>
               <col width="80">
               <col width="80">
               <col width="80">
               <?php
            }
            ?>
            <col width="85">
            <col width="80">
         </colgroup>
         <tr>
            <td class="content_tbl_header content_row_os" style="<?=$stylehead?>" rowspan="2"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_header content_row_os" style="<?=$stylehead?>" rowspan="2"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_header content_row_os" style="border-right: 3px double #666666; <?=$stylehead?>" rowspan="2"><b>Unidad</b></td>
            <td class="content_tbl_header content_row_os" style="border-right: 3px double #666666" align="center" colspan="2"><b>Ubicaciones</b></td>
            <td class="content_tbl_header content_row_os" style="border-right: 3px double #666666" align="center" colspan="5"><b>Produccion</b></td>
            <?php
            if((int)$_REQUEST["mid"] != 1215)
            {  
               ?>
               <td class="content_tbl_header content_row_os" style="border-right: 3px double #666666" align="center"><b></b></td>
               <td class="content_tbl_header content_row_os" style="border-right: 3px double #666666" align="center" colspan="2"><b>Compras</b></td>
               <td class="content_tbl_header content_row_os" style="border-right: 3px double #666666" align="center" colspan="2"><b>Ventas</b></td>
               <?php
            }
            ?>
            <td class="content_tbl_header content_row_os" style="<?=$stylehead?>" align="right" rowspan="2"><b>Cant./Transito</b></td>
            <td class="content_tbl_header content_row_os" style="<?=$stylehead?>" align="right" rowspan="2"><b>Cant./Dispo</b></td>
            <td class="content_tbl_header content_row_os" style="<?=$stylehead?>" align="right" rowspan="2"><b>Facturado</b></td>
            <td class="content_tbl_header content_row_os" style="<?=$stylehead?>" align="right" rowspan="2"><b>DOH</b></td>
         </tr>
         <tr>
            <td class="content_tbl_header content_row_os" style="<?=$stylehead?>"><b>Bodega</b></td>
            <td class="content_tbl_header content_row_os" align="center" style="border-right: 3px double #666666; <?=$stylehead?>"><b>Stock fisico</b></td>

            <td class="content_tbl_header content_row_os" style="<?=$stylehead?>" align="center"><b>Tipo</b></td>
            <td class="content_tbl_header content_row_os" style="<?=$stylehead?>" align="center"><b>Gramaje</b></td>
            <td class="content_tbl_header content_row_os" style="<?=$stylehead?>" align="center"><b>Medidas</b></td>
            <td class="content_tbl_header content_row_os" style="<?=$stylehead?>"><b>Cliente</b></td>
            <td class="content_tbl_header content_row_os" style="border-right: 3px double #666666; <?=$stylehead?>"><b>Diseño</b></td>
            <?php
            if((int)$_REQUEST["mid"] != 1215)
            {  
               ?>
               <td class="content_tbl_header content_row_os" style="border-right: 3px double #666666; <?=$stylehead?>" align="right"><b>Cant./Total</b></td>
               <td class="content_tbl_header content_row_os" style="<?=$stylehead?>" align="right"><b>Valor/Unit.</b></td>
               <td class="content_tbl_header content_row_os" style="border-right: 3px double #666666; <?=$stylehead?>" align="right"><b>Valor/Total</b></td>
               <td class="content_tbl_header content_row_os" style="<?=$stylehead?>" align="right"><b>Valor/Unit.</b></td>
               <td class="content_tbl_header content_row_os" style="border-right: 3px double #666666; <?=$stylehead?>" align="right"><b>Valor/Total</b></td>
               <?php
            }
            
            ?>
         </tr>
         <?php
         $x = 0;
         $ges_price  = 0;
         $ges_amount = 0;
         $ges_ventas = 0;
         $px = 0;
         foreach($items AS $item)
         {
                  $sql = " select t1.*, t2.st_name, tx.req_number 'ubi_name', tx2.fab_type,
                                 tx2.fab_mat_gramms, tx2.fab_med_width, tx2.fab_med_height, tx2.fab_med_fuelle,
                                 tx4.cust_name, tx2.fab_design_name as fab_design_name
                           from item_shops_storehouses t1
                           LEFT OUTER JOIN company_shops_storehouses      t2 ON t1.st_id = t2.id
                           LEFT OUTER JOIN orders tx ON t1.iss_order_id = tx.id
                           LEFT OUTER JOIN orders_items tx2 ON tx.id = tx2.req_id
                           LEFT OUTER JOIN customer tx4     ON tx.req_cust_id = tx4.id
                           where
                           t2.st_shop_id    = {$_SESSION[$_sesmodulename]["sql_shop"]} and
                           t2.st_status     = 1 and
                           t1.item_id       = {$item["id"]} and
                           t1.iss_inventory != 0 ";
                  if($_SESSION[$_sesmodulename]["sql_storehouse"])
                     $sql .= " and t1.st_id = {$_SESSION[$_sesmodulename]["sql_storehouse"]}  ";
                  if($_SESSION[$_sesmodulename]["sql_ccnum"] != "" && (int)$orderinfo["req_id"])
                     $sql .= " and t1.iss_order_id = {$orderinfo["req_id"]} ";
                  if($_SESSION[$_sesmodulename]["sql_fab_design_name"] != "")
                     $sql .= " and t1.iss_order_id IN ({$sql_reqids}) ";
                  if((int)$_SESSION[$_sesmodulename]["sql_customer"])
                  {
                     $sql .= " and
                              (
                                 select count(*)
                                 from orders suba
                                 where
                                 suba.id               = t1.iss_order_id and
                                 suba.req_status       > 0 and
                                 suba.req_isfabricate  = 1 and
                                 suba.req_cust_id      = {$_SESSION[$_sesmodulename]["sql_customer"]}
                              ) > 0 ";
                  }
                  $sql .= " order by t2.st_name";
        
            /*
            $sql2 = " select t2.cat_prefix as fab_type 
                           , t1.item_reg_gsm as fab_mat_gramms 
                           , t1.item_reg_width as fab_med_width 
                           , t1.item_reg_length as fab_med_height 
                           , '.' as fab_med_fuelle 
                           , '.' as fab_design_name 
                      from item t1 
                          INNER JOIN item_productcats t3   ON t1.id = t3.item_id
                          LEFT OUTER JOIN productcats t2        ON t3.cat_id = t2.id
                      where t1.id       = {$item["id"]}";
            */

            $sql2 = "select add_name as fab_type  
                         , i.item_reg_gsm as fab_mat_gramms 
                         , i.item_reg_width as fab_med_width 
                         , i.item_reg_length as fab_med_height 
                         , '.' as fab_med_fuelle 
                         , '.' as fab_design_name 
                     from item i 
                        inner join tran_comments_item_vals tciv on tciv.item_id = i.id
                        inner join tran_comments tc on tciv.com_id = tc.id and tc.id = 18
                        inner join tran_comments_vals t3 on t3.id = tciv.val_id 
                     where i.id = {$item["id"]}";
            
            $tabla_item = $CON->select($sql2);

            $storehouses = $CON->select($sql);

            $rowspan = count($storehouses);

            $totalamount = 0;
            foreach($storehouses AS $sth)
               $totalamount += $sth["iss_inventory"];

            if((int)$_REQUEST["mid"] == 1215)
            {
               $stockcomp = getItemStockComp($CON, $_SESSION[$_sesmodulename]["sql_shop"], $item["id"], "item");
               $totalamount -= $stockcomp;
               $storehouses[0]["iss_inventory"] -= $stockcomp;
            }

            if($storehouses[0]["ubi_name"] != "")
               $storehouses[0]["st_name"] .= ": ".$storehouses[0]["ubi_name"];

            $itemcostprice = getItemFiFoCostV2Unibag($CON, $_SESSION[$_sesmodulename]["sql_company"], $item["id"], $totalamount);
            $totalprice    = round($totalamount * $itemcostprice,2);

            $sql = "select sum(item_amount) as facturado from invoices_sell_parts_items where item_id = {$item["id"]}";
            $item_facturacion = $CON->select($sql);
            $item_facturacion = $item_facturacion[0];

            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os" valign="top" rowspan="<?=$rowspan?>"><?=$item["item_number_prod"]?></td>
               <td class="content_row_os" valign="top" rowspan="<?=$rowspan?>"><?=$item["item_title"]?></td>
               <td class="content_row_os" valign="top" style="border-right: 3px double #666666; <?=$style1?>" rowspan="<?=$rowspan?>"><?=$item["unit_name"]?></td>

               <td class="content_row_os" valign="top"><?=$storehouses[0]["st_name"]?>&nbsp;</td>
               <td class="content_row_os" valign="top" align="center" style="border-right: 3px double #666666;"><?=printPrice($storehouses[0]["iss_inventory"],2)?></td>

               <td class="content_row_os" valign="top" align="center"><?=$tabla_item[0]["fab_type"]?>&nbsp;</td>
               <td class="content_row_os" valign="top" align="center"><?if((int)$storehouses[0]["fab_mat_gramms"]) echo (int)$storehouses[0]["fab_mat_gramms"]?>&nbsp;</td>
               <td class="content_row_os" valign="top" align="center"><?if((int)$storehouses[0]["fab_med_width"]) echo (int)$storehouses[0]["fab_med_width"]."x".(int)$storehouses[0]["fab_med_height"]."x".(int)$storehouses[0]["fab_med_fuelle"]?>&nbsp;</td>
               <td class="content_row_os" valign="top"><?=$storehouses[0]["cust_name"]?>&nbsp;</td>
               <td class="content_row_os" valign="top" style="border-right: 3px double #666666;"><?=$storehouses[0]["fab_design_name"]?>&nbsp;</td>
               <?php
               if((int)$_REQUEST["mid"] != 1215)
               {  ?>
                  <td class="content_row_os" valign="top" align="right" style="border-right: 3px double #666666;" rowspan="<?=$rowspan?>"><?=printPrice($totalamount,2)?></td>
                  <td class="content_row_os" valign="top" align="right" rowspan="<?=$rowspan?>"><?=printPrice($itemcostprice,2)?></td>
                  <td class="content_row_os" valign="top" style="border-right: 3px double #666666;" align="right" rowspan="<?=$rowspan?>"><?=printPrice($totalprice,2)?></td>
                  <td class="content_row_os" valign="top" align="right" rowspan="<?=$rowspan?>"><?=printPrice($item["item_sellprice_netto"],2)?></td>
                  <td class="content_row_os" valign="top" style="border-right: 3px double #666666;" align="right" rowspan="<?=$rowspan?>"><?=printPrice($item["item_sellprice_netto"]*$totalamount,2)?></td>
                  <?php
               }
               ?>
               <td class="content_row_os" valign="top" align="right" rowspan="<?=$rowspan?>"><?=printPrice($_TRANSSTOCK[$item["id"]],2)?></td>
               <td class="content_row_os" valign="top" align="right" rowspan="<?=$rowspan?>"><?=printPrice($totalamount + $_TRANSSTOCK[$item["id"]],2)?></td>

               <td class="content_row_os" valign="top" align="right" rowspan="<?=$rowspan?>"><?=printPrice($item_facturacion["facturado"],2)?></td>
               <td class="content_row_os" valign="top" align="right" rowspan="<?=$rowspan?>"><?=printPrice(($totalamount + $_TRANSSTOCK[$item["id"]])/$item_facturacion["facturado"],2)?></td>

            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["num"]   = $item["item_number_prod"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["title"] = $item["item_title"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["unit"]  = $item["unit_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["sthh"]  = $storehouses[0]["st_name_head"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["sthi"]  = $storehouses[0]["st_name_intern"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["sth"]   = $storehouses[0]["st_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["inv"]   = printPrice($storehouses[0]["iss_inventory"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["amount"]= printPrice($totalamount,2);

            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["price"] = printPrice($itemcostprice,2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["totalprice"] = printPrice($totalprice,2);
            
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["_TRANSSTOCK"] = printPrice($_TRANSSTOCK[$item["id"]],2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["_DISPOSTOCK"] = printPrice($totalamount + $_TRANSSTOCK[$item["id"]],2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["fab_type"] = $tabla_item[0]["fab_type"];
            if((int)$tabla_item[0]["fab_mat_gramms"])
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["fab_mat_gramms"] = (int)$tabla_item[0]["fab_mat_gramms"];
            if((int)$tabla_item[0]["fab_med_width"])
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["fab_med_width"] = (int)$tabla_item[0]["fab_med_width"]."x".(int)$tabla_item[0]["fab_med_height"]."x".(int)$tabla_item[0]["fab_med_fuelle"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["cust_name"] = $storehouses[0]["cust_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["fab_design_name"] = $storehouses[0]["fab_design_name"];

            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["valor_unitario_vta"] = printPrice($item["item_sellprice_netto"],2);
            $tt =$item["item_sellprice_netto"]*$totalamount;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["valor_total_vta"] = printPrice($tt,2);

            $px++;

            for($y = 1; $y < count($storehouses); $y++)
            {
               if($storehouses[$y]["ubi_name"] != "")
                  $storehouses[$y]["st_name"] .= ": ".$storehouses[$y]["ubi_name"];
               ?>
               <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os" valign="top"><?=$storehouses[$y]["st_name"]?></td>
                  <td class="content_row_os" valign="top" align="center" style="border-right: 3px double #666666;"><?=printPrice($storehouses[$y]["iss_inventory"],2)?></td>
                  <td class="content_row_os" valign="top" align="center"><?=$storehouses[$y]["fab_type"]?>&nbsp;</td>
                  <td class="content_row_os" valign="top" align="center"><?if((int)$storehouses[$y]["fab_mat_gramms"]) echo (int)$storehouses[$y]["fab_mat_gramms"]?>&nbsp;</td>
                  <td class="content_row_os" valign="top" align="center"><?if((int)$storehouses[$y]["fab_med_width"]) echo (int)$storehouses[$y]["fab_med_width"]."x".(int)$storehouses[$y]["fab_med_height"]."x".(int)$storehouses[$y]["fab_med_fuelle"]?>&nbsp;</td>
                  <td class="content_row_os" valign="top"><?=$storehouses[$y]["cust_name"]?>&nbsp;</td>
                  <td class="content_row_os" valign="top" style="border-right: 3px double #666666;"><?=$storehouses[$y]["fab_design_name"]?>&nbsp;</td>
               </tr>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["num"]   = $item["item_number_prod"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["title"] = $item["item_title"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["unit"]  = $item["unit_name"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["sth"]  .= $storehouses[$y]["st_name"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["inv"]  .= printPrice($storehouses[$y]["iss_inventory"],2);

               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["fab_type"] = $storehouses[$y]["fab_type"];
               if((int)$storehouses[$y]["fab_mat_gramms"])
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["fab_mat_gramms"] = (int)$storehouses[$y]["fab_mat_gramms"];
               if((int)$storehouses[$y]["fab_med_width"])
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["fab_med_width"] = (int)$storehouses[$y]["fab_med_width"]."x".(int)$storehouses[$y]["fab_med_height"]."x".(int)$storehouses[$y]["fab_med_fuelle"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["cust_name"] = $storehouses[$y]["cust_name"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["fab_design_name"] = $storehouses[$y]["fab_design_name"];
               $px++;
            }
            $ges_price  += (float)$totalprice;
            $ges_amount += (float)$totalamount;
            $ges_ventas += (float)($tt);
            $x++;
         }

         if((int)$_REQUEST["mid"] != 1215)
         {
            if($x)
            {  ?>
               <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_totals content_row_os" colspan="3" style="border-right: 3px double #666666;">TOTAL</td>
                  <td class="content_row_totals content_row_os">&nbsp;</td>
                  <td class="content_row_totals content_row_os" style="border-right: 3px double #666666;">&nbsp;</td>
                  <td class="content_row_totals content_row_os" colspan="5" style="border-right: 3px double #666666;">&nbsp;</td>
                  <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_amount,2)?></td>
                  <td class="content_row_totals content_row_os">&nbsp;</td>
                  <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_price,2)?></td>
                  <td class="content_row_totals content_row_os">&nbsp;</td>
                  <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_ventas,2)?></td>
                  <td class="content_row_totals content_row_os">&nbsp;</td>
                  <td class="content_row_totals content_row_os">&nbsp;</td>
                  <td class="content_row_totals content_row_os">&nbsp;</td>
                  <td class="content_row_totals content_row_os">&nbsp;</td>
               </tr>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["num"]   = "TOTAL";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["title"] = " ";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["unit"]  = " ";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["sthh"]  = " ";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["sth"]   = " ";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["inv"]   = " ";

               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["amount"]= printPrice($ges_amount,2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["totalprice"] = printPrice($ges_price,2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["valor_total_vta"] = printPrice($ges_ventas,2);
            }
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
      </td>
   </tr>
   <?php
}
?>
</table>
<?php
$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';

//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsStockItems($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsStockItems($CON);

if($pdffile != "")
{
   $doctitle = "Stock-por-producto-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Stock-por-producto-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>