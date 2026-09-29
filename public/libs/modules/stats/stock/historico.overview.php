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
$_sesmodulename         = "HistorialProductos";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array( "Histórico" => "1", "Fecha de Generación" => "11", "Articulo" => "2", "Proveedor" => "4", "Cliente" => "6", "Empresa" => "8", "Familia" => "5", "Tipo Producto" => "3");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

unset($_SESSION["STATS"][$_sesmodulename]);
//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

if($_REQUEST["exec"] == "edit")
   require_once("historico.overview.edit.php");
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      $sql_item = explode("#", $_REQUEST["item_id"]);
      
      $_SESSION[$_sesmodulename]["sql_company"]    = (int)20010;
      $_SESSION[$_sesmodulename]["sql_shop"]       = (int)30010;
      $_SESSION[$_sesmodulename]["sql_item_id"]    = null;
      $_SESSION[$_sesmodulename]["sql_item_type"]  = "";
      $_SESSION[$_sesmodulename]["sql_pcat"]       = (int)0;
      $_SESSION[$_sesmodulename]["sql_supplier"]   = (int)0;
      $_SESSION[$_sesmodulename]["sql_dspmode"]    = (int)1;
      $_SESSION[$_sesmodulename]["sql_stockmode"]  = (int)0;
      $_SESSION[$_sesmodulename]["sql_storehouse"] = (int)0;
      $_SESSION[$_sesmodulename]["sql_xitemtype"]     = (int)0;
      $_SESSION[$_sesmodulename]["sql_xhasstock"]     = (int)0;
      $_SESSION[$_sesmodulename]["sql_equvals"]    = null;
      $_SESSION[$_sesmodulename]["sql_customer"]      = (int)0;
      $_SESSION[$_sesmodulename]["sql_ccnum"]      = (int)0;
      $_SESSION[$_sesmodulename]["sql_fab_design_name"] = null;
      $_SESSION[$_sesmodulename]["page"]           = 0;
      $_SESSION[$_sesmodulename]["search_active"]  = 1;
      $_SESSION[$_sesmodulename]["sql_fab_start_date"] = date('Y-m-d H:i:s', strtotime($_REQUEST["sql_fab_start_date"]));
      $_SESSION[$_sesmodulename]["sql_fab_end_date"] = date('Y-m-d H:i:s', strtotime($_REQUEST["sql_fab_end_date"]));
      $_SESSION[$_sesmodulename]["sql_date_pfrom"] = date('Y-m-d H:i:s', strtotime($_REQUEST["sql_date_pfrom"]));
      $_SESSION[$_sesmodulename]["sql_date_pto"] = date('Y-m-d H:i:s', strtotime($_REQUEST["sql_date_pto"]));

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
$cntsql = " select count(distinct t1.id) 'cc'
         from item t1
         {$joisql}
         where
         t1.item_status    = 1 and
         t1.item_released  = 1 ";

$datsql = " select distinct t1.item_number_prod, t1.item_title, t1.id, t4.company_short, t3.shop_name, 'item_type' 'I',
         t7.cat_id, t8.cat_title, t9.unit_name, t11a.ubi_name
         from item t1
         {$joisql}
         where
         t1.item_status    = 1 and
         t1.item_released  = 1 ";


$itemcount = $CON->select($cntsql);
$itemcount = (int)$itemcount[0]["cc"] + (int)$itemcount[1]["cc"];

//----------------------------------------------------------------------------------
$_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

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
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <?php
   printJSsetCompanyShop($shops);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Resumen cierre de stock</b></td>
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
            <select class="text" name="sql_company" style="display:none"
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
            <select class="text" name="sql_shop" style="display:none"
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

            <tr>
               <td class="content_rowl">Periodo</td>
               <td class="content_row" colspan="3">
                  <table border="0" class="content_table" cellpadding="0" cellspacing="0">
                  <tr>
                     <td class="content_row_clear" width="180" id="idx_selmode3">
                        <nobr>
                        <input type="text" style="width:150px" id="sql_date_pfrom" name="sql_date_pfrom"
                        class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                        onfocus="markfield(this,0)" onblur="markfield(this,1)"
                        value="<?=$_SESSION[$_sesmodulename]["sql_date_pfrom"]?>">
                        -
                        <input type="text" style="width:150px" id="sql_date_pto" name="sql_date_pto"
                              class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                              onfocus="markfield(this,0)" onblur="markfield(this,1)"
                              value="<?= isset($_SESSION[$_sesmodulename]["sql_date_pto"]) && !empty($_SESSION[$_sesmodulename]["sql_date_pto"]) 
                                       ? $_SESSION[$_sesmodulename]["sql_date_pto"] 
                                       : date('d-m-Y', strtotime('+1 day')) ?>">
                        </nobr>
                     </td>
                  </tr>
                  </table>
               </td>
            </tr>


            <select class="text" name="sql_pcat" style="display:none"
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

                     </td>
                     <td align="left">

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
         <?=Nifty_printH("box1", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col>
            <col width="155">
            <col>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col width="65">
         </colgroup>
         <tr>
            <td class="content_tbl_header content_row_os" align="center">ID</td>
            <td class="content_tbl_header content_row_os" align="left">Fecha Creación</td>
            <td class="content_tbl_header content_row_os" align="center">Artículo</td>
            <td class="content_tbl_header content_row_os" align="center">Proveedor</td>
            <td class="content_tbl_header content_row_os" align="center">Cliente</td>
            <td class="content_tbl_header content_row_os" align="center">Empresa</td>
            <td class="content_tbl_header content_row_os" align="center">Familia</td>
            <td class="content_tbl_header content_row_os" align="center">Tipo Producto</td>
            <td class="content_tbl_header content_row_os" align="center">Opciones</td>
         </tr>
         <?php
         

         

         $sqlhistory = "select * from HistorialProductos where id_historico >1";

         if (!empty($_SESSION[$_sesmodulename]["sql_date_pfrom"])) {
            $sqlhistory .= " AND fecha_registro >= '{$_SESSION[$_sesmodulename]["sql_date_pfrom"]}'";
         }
         if (!empty($_SESSION[$_sesmodulename]["sql_date_pto"])) {
            $sqlhistory .= " AND fecha_registro <= '{$_SESSION[$_sesmodulename]["sql_date_pto"]}'";
         }

            //----------------------------------------------------------------------------------

         $sqlhistory .= " order by id_historico DESC";



         $historials = $CON->select($sqlhistory);
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($historials) && $historials != false; $x++)
         {
            ?>

            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=$historials[$x]["id_historico"]?></td>
               <td class="content_row"><?= date('Y-m-d', strtotime($historials[$x]["fecha_registro"])) ?></td>
               <td class="content_row">
               <?= empty($historials[$x]["articulo"]) ? 'Todos' : $historials[$x]["articulo"] ?>
               </td>
               <td class="content_row">
                  <?= empty($historials[$x]["proveedor"]) || $historials[$x]["proveedor"] == 0 ? 'Todos' : $historials[$x]["proveedor"] ?>
               </td>
               <td class="content_row">
                  <?= empty($historials[$x]["cliente"]) || $historials[$x]["cliente"] == 0 ? 'Todos' : $historials[$x]["cliente"] ?>
               </td>
               <td class="content_row"><?=$historials[$x]["empresa"]?></td>
               <td class="content_row">
                  <?= empty($historials[$x]["familia"]) || $historials[$x]["familia"] == 0 ? 'Todos' : $historials[$x]["familia"] ?>
               </td>
               <td class="content_row">
                  <?= empty($historials[$x]["tipo_producto"]) || $historials[$x]["tipo_producto"] == 0 ? 'Todos' : $historials[$x]["tipo_producto"] ?>
               </td>
               <td class="content_row" align="center">
                  <?php
                  printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='". $historials[$x]["id_historico"] ."';submitForm(document.xform_itemsearch)", "document-excel", 130);
//                  printButton("Imprimir XLS", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$historials[$x]["id_historico"]}", "button-error", "document-excell",130);
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
         <?=Nifty_printF()?>
         <br>
      </td>
   </tr>
   </table>
   <?php
      $printxls = isset($_REQUEST["printxls"]) ? $_REQUEST["printxls"] : null;
      if($_REQUEST["printxls"])
      {
         $px = 0;
         $sqlxls = "select * from detalle_historial_combinado dhc  WHERE id_historico =". $printxls;
         $detallexls = $CON->select($sqlxls);

         foreach ($detallexls AS $detalle)
         {
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["num"]   = $detalle["item_number_prod"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["title"]     = $detalle["item_title"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["unit"]    = $detalle["unit_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["sth"]   = $detalle["st_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["inv"]   = $detalle["iss_inventory"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["fab_type"]   = $detalle["fab_type"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["fab_mat_gramms"]   = $detalle["fab_mat_gramms"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["fab_med_width"]   = $detalle["fab_med_width"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["cust_name"]   = $detalle["cust_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["fab_design_name"]   = $detalle["fab_design_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["price"]   = $detalle["price"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["amount"]   = $detalle["amount"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["totalprice"]   = $detalle["totalprice"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["transstock"]   = $detalle["transstock"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$px]["dispostock"]   = $detalle["dispostock"];
            $px++;
         }
         $xlsfile = xls_createStatsStockItems($CON);
      }
      if($xlsfile != "")
      {
         $doctitle = "Stock-por-producto-historico".time().".xls";
         $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
         ?>
         <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
         <?php
      }
   ?>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?php
}