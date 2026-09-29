<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stock_revalue";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Codigo proveedor" => "2", "Artículo" => "2");

$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]      = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_supplier"]  = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]   = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_stockmode"] = (int)$_REQUEST["sql_stockmode"];
   $_SESSION[$_sesmodulename]["sql_docmode"]   = (int)$_REQUEST["sql_docmode"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
if(!(int)$_SESSION[$_sesmodulename]["sql_stockmode"])
   $_SESSION[$_sesmodulename]["sql_stockmode"] = 1;
if(!(int)$_SESSION[$_sesmodulename]["sql_docmode"])
   $_SESSION[$_sesmodulename]["sql_docmode"] = 1;
   
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m') -1;
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');

   if($_SESSION[$_sesmodulename]["sql_month1"] == 0)
   {
      $_SESSION[$_sesmodulename]["sql_month1"] = 12;
      $_SESSION[$_sesmodulename]["sql_year1"]--;
   }
   
   $_SESSION[$_sesmodulename]["sql_month2"]  = $_SESSION[$_sesmodulename]["sql_month1"];
   $_SESSION[$_sesmodulename]["sql_year2"]   = $_SESSION[$_sesmodulename]["sql_year1"];
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
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON, true);
$shops      = getShops($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;
if(!(int)$_SESSION[$_sesmodulename]["sql_stockmode"])
   $_SESSION[$_sesmodulename]["sql_stockmode"] = 1;

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
$seasql = "";
$joisql = " INNER JOIN item_shops      t2 ON t1.id = t2.item_id
            INNER JOIN company_shops   t3 ON ( t2.shop_id = t3.id and t3.shop_status = 1 )
            INNER JOIN company_data    t4 ON ( t3.shop_company_id = t4.id and t4.company_status = 1 )
            INNER JOIN item_suppliers  t5 ON ( t1.id = t5.item_id and ";

if($_SESSION[$_sesmodulename]["sql_supplier"])
   $joisql .= " t5.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ) ";
else
   $joisql .= " t5.item_supp_act = 1 ) ";
   
$joisql .= " INNER JOIN supplier t6           ON ( t5.supplier_id = t6.id )
             INNER JOIN item_productcats t7   ON t1.id = t7.item_id
             LEFT OUTER JOIN item_units t8    ON t1.item_unit = t8.id
             LEFT OUTER JOIN productcats t9   ON t7.cat_id = t9.id
             INNER JOIN item_shops_storehouses     t10 ON ( t2.item_id = t10.item_id and t2.shop_id = t10.shop_id )
             INNER JOIN company_shops_storehouses  t11 ON ( t2.shop_id = t11.st_shop_id and t11.st_status > 0 )";

//----------------------------------------------------------------------------------
$cntsql = " select count(distinct t1.id) 'cc'
            from item t1
            {$joisql}
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";

$datsql = " select distinct t1.item_number_prod, t1.item_title, t1.id, t7.cat_id, t9.cat_title, t8.unit_name, t5.item_code
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
if($_SESSION[$_sesmodulename]["sql_stockmode"] == 1)
   $seasql .= " and t10.iss_inventory > 0 ";

//----------------------------------------------------------------------------------
$cntsql   .= $seasql;
$datsql   .= $seasql;

$itemcount = $CON->select($cntsql);
$itemcount = (int)$itemcount[0]["cc"];

//----------------------------------------------------------------------------------
$_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
{
   $_SESSION[$_sesmodulename]["orderBy"] = "4";
   $_SESSION[$_sesmodulename]["orderSort"] = "asc";
}

$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";

//----------------------------------------------------------------------------------
$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
      {
         if(!(int)$_SESSION[$_sesmodulename]["sql_shop"] || ((int)$_SESSION[$_sesmodulename]["sql_shop"] && $_SESSION[$_sesmodulename]["sql_shop"] == $shop["id"]))
            array_push($selshops, $shop);
      }
}

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

$_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"] = $selshops;
//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

printJSsetCompanyShop($shops);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<script language="JavaScript">
function detectEvent (event, mode)
{
   var xurl = './libs/modules/items/searchitem.fancy.php?mode=' +mode;
   var keyCode = ('which' in event) ? event.which : event.keyCode;
   if(keyCode == 112)
      showFancybox(xurl, 'iframe', 1000, 450, 'auto');
}
function detectEventENT(event, itemid, lineges)
{
   var keyCode = ('which' in event) ? event.which : event.keyCode;
   if(keyCode == 13)
   {
      var objbtn = document.getElementById('avgcost_' +itemid);
      var objfld = document.getElementById('idx_btnavg_' +itemid);
      objbtn.style.backgroundColor='#C6FFCC';
      objfld.style.backgroundColor='#C6FFCC';
      setNewAvgCost(itemid, lineges);
   }
}
function setNewAvgCost(itemid, stamt)
{
   var newval = $('#avgcost_' +itemid).val();
   var newamt = $('#idx_btnamt_' +itemid).val();

   $.get('/libs/modules/stockvalues/set.avgcost.php?company_id=<?=$_SESSION[$_sesmodulename]["sql_company"]?>&itemid=' +itemid +'&newcostprc=' +newval +'&stamt=' +stamt +'&newamt=' +newamt,
      function(data) {
         $('#sumcost_' +itemid).html(data);
      });
}
</script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Valorización inventario</b></td>
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
      onsubmit="return checkform(new Array(this.sql_company))">
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
         <td class="content_rowl">Proveedor</td>
         <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
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
      </tr>
      <tr>
         <td class="content_rowl">Modo</td>
         <td class="content_row">
            <input type="radio" name="sql_stockmode" value="1"
            <?php if($_SESSION[$_sesmodulename]["sql_stockmode"] == 1) echo "checked"?>> Solamente existencias
            <input type="radio" name="sql_stockmode" value="2"
            <?php if($_SESSION[$_sesmodulename]["sql_stockmode"] == 2) echo "checked"?>> Todos productos
         </td>
         <td class="content_rowl">Documentos</td>
         <td class="content_row">
            <input type="radio" name="sql_docmode" value="1"
            <?php if($_SESSION[$_sesmodulename]["sql_docmode"] == 1) echo "checked"?>> Mostrar documentos
            <input type="radio" name="sql_docmode" value="2"
            <?php if($_SESSION[$_sesmodulename]["sql_docmode"] == 2) echo "checked"?>> No mostrar documentos
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
<tr>
   <td>
      <?=Nifty_printH("box1", "980")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col width="120">
         <col>
         <col width="80">
         <col width="90">
         <col width="90">
         <col width="90">
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os" ><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os" ><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_subheader content_row_os" ><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_tbl_subheader content_row_os"  style="border-right:3px double black">Unidad</td>
         <td class="content_tbl_subheader content_row_os" align="right">Costo Ø / Cantidad</td>
         <td class="content_tbl_subheader content_row_os" align="right">Stock/Act</td>
         <td class="content_tbl_subheader content_row_os" align="right"><nobr>$ Stock</nobr></td>
      </tr>
      <?php
      unset($_TOTAL);
      unset($_TOTALCOST);
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         if((int)$_SESSION[$_sesmodulename]["sql_docmode"] == 1)
            $frowcolor = getRowColor(1);
         else
            $frowcolor = getRowColor($x);
         ?>
         <tr bgcolor="<?=$frowcolor?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><b><?=$items[$x]["item_number_prod"]?></b></td>
            <td class="content_row_os"><?=$items[$x]["item_code"]?>&nbsp;</td>
            <td class="content_row_os"><?=$items[$x]["item_title"]?></td>
            <td class="content_row_os" style="border-right:3px double black"><?=$items[$x]["unit_name"]?>&nbsp;</td>
            <?php
            $lineges = 0;
            $itemgescost = 0.00;
            foreach($selshops AS $selshop)
            {
               unset($cost);
               unset($stock);
               $stock = getItemShopCurrentStock($CON, $selshop["id"], $items[$x]["id"], "item", true);
               $cost["item_costprice_netto"] = getItemAverageCost($CON, $selshop["shop_company_id"], $items[$x]["id"]);

               if($cost["item_costprice_netto"] == false)
                  $cost["item_costprice_netto"] = getSupplierFinalCostNetto($CON, 0, $items[$x]["id"]);

               $avgcost = $cost["item_costprice_netto"];
               $cost    = $cost["item_costprice_netto"] * $stock;
               
               $lineges       += $stock;
               $itemgescost   += $cost;
            }

            if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "avgcost_{$items[$x]["id"]}";
            ?>
            <td class="content_row_os" align="right">
               <nobr>
               <input type="text" class="text" value="<?=printPrice($avgcost,4)?>"
               name="avgcost_<?=$items[$x]["id"]?>" id="avgcost_<?=$items[$x]["id"]?>"
               style="text-align:right;width:90px"
               onkeyup="detectEventENT(event, '<?=$items[$x]["id"]?>', '<?=$lineges?>')">
               <input type="text" class="text" value="<?=printPrice($lineges,2, true)?>" style="width:50px;text-align:center"
               id="idx_btnamt_<?=$items[$x]["id"]?>" onkeyup="detectEventENT(event, '<?=$items[$x]["id"]?>', '<?=$lineges?>')">
               <input type="button" class="button" value="OK" style="width:30px" id="idx_btnavg_<?=$items[$x]["id"]?>"
               onclick="this.style.backgroundColor='#C6FFCC';setNewAvgCost('<?=$items[$x]["id"]?>', '<?=$lineges?>');
                        document.getElementById('avgcost_<?=$items[$x]["id"]?>').style.backgroundColor='#C6FFCC';">

               </nobr>
            </td>
            <td class="content_row_os" align="right"><?=printPrice($lineges,2, true)?></td>
            <td class="content_row_os" align="right" id="sumcost_<?=$items[$x]["id"]?>"><?=printPrice($itemgescost,0, true)?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["HEAD"][$x]["item_number_prod"] = $items[$x]["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["HEAD"][$x]["item_code"]        = $items[$x]["item_code"];
         $_SESSION["STATS"][$_sesmodulename]["HEAD"][$x]["item_title"]       = $items[$x]["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["HEAD"][$x]["unit_name"]        = $items[$x]["unit_name"];
         $_SESSION["STATS"][$_sesmodulename]["HEAD"][$x]["avgcost"]          = printPrice($avgcost,4);
         $_SESSION["STATS"][$_sesmodulename]["HEAD"][$x]["lineges"]          = printPrice($lineges,2);
         $_SESSION["STATS"][$_sesmodulename]["HEAD"][$x]["itemgescost"]      = printPrice($itemgescost,2);

         if((int)$_SESSION[$_sesmodulename]["sql_docmode"] == 1)
         {
            $sql = " select distinct t1.id
                     from itemlist t1
                     INNER JOIN itemlist_pos t2 ON t1.id = t2.itemlist_id
                     where
                     t1.item_simple_set = 1 and
                     t2.item_id = {$items[$x]["id"]}";
            $simplepacks = $CON->select($sql);

            //----------------------------------------------------------------------------------
            $itemliststr = "";
            for($y = 0; $y < count($simplepacks) && $simplepacks != false; $y++)
               $itemliststr .= "{$simplepacks[$y]["id"]},";
            $itemliststr = substr($itemliststr, 0, -1);

            //----------------------------------------------------------------------------------
            $datsql = " select t1.invc_importation, t1.invc_date, t1.invc_docnumber, t1.id,
                               t3.item_costprice_netto, t3.item_amount, t1.invc_exc_rate,
                               t6.supp_short,
                               (t3.item_costprice_netto_dsc2 / t3.item_amount) 'item_cost_pricedsc',
                               (t3.item_costprice_import_total / t3.item_amount) 'item_cost_pricedsc_import'
                        from invoices_buy t1
                        INNER JOIN invoices_buy_parts t2       ON t1.id = t2.part_invc_id
                        INNER JOIN invoices_buy_parts_items t3 ON ( t1.id = t3.invc_id and t2.id = t3.part_id )
                        INNER JOIN company_data t4             ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
                        LEFT OUTER JOIN supplier t6            ON ( t1.invc_supplier_id = t6.id )
                        where
                        t1.invc_status    > 1 and
                        t1.invc_date      between {$sql_datefrom} and {$sql_dateto} and
                        t3.item_id        = {$items[$x]["id"]} and
                        t3.item_type      = 'item'";
            //----------------------------------------------------------------------------------
            if($_SESSION[$_sesmodulename]["sql_company"])
               $datsql .= " and t1.invc_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
            if($_SESSION[$_sesmodulename]["sql_shop"])
               $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
            if($_SESSION[$_sesmodulename]["sql_supplier"])
               $datsql .= " and t1.invc_supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";

            //----------------------------------------------------------------------------------
            if($itemliststr != "")
            {
               $datsql .= " UNION ALL
                            select t1.invc_importation, t1.invc_date, t1.invc_docnumber, t1.id,
                                  t3.item_costprice_netto / t8.item_amount 'item_costprice_netto',
                                  (t3.item_amount * t8.item_amount) 'item_amount',
                                  t1.invc_exc_rate, t6.supp_short,
                                  (t3.item_costprice_netto_dsc2 / t3.item_amount / t8.item_amount) 'item_cost_pricedsc',
                                  (t3.item_costprice_import_total / t3.item_amount / t8.item_amount) 'item_cost_pricedsc_import'
                           from invoices_buy t1
                           INNER JOIN invoices_buy_parts t2       ON t1.id = t2.part_invc_id
                           INNER JOIN invoices_buy_parts_items t3 ON ( t1.id = t3.invc_id and t2.id = t3.part_id )
                           INNER JOIN company_data t4             ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
                           LEFT OUTER JOIN supplier t6            ON ( t1.invc_supplier_id = t6.id )
                           INNER JOIN itemlist t7                 ON t3.item_id = t7.id
                           INNER JOIN itemlist_pos t8             ON ( t7.id = t8.itemlist_id and t8.item_id = {$items[$x]["id"]} )
                           where
                           t1.invc_status    > 1 and
                           t1.invc_date      between {$sql_datefrom} and {$sql_dateto} and
                           t3.item_id        IN ({$itemliststr}) and
                           t3.item_type      = 'itemlist'";
               //----------------------------------------------------------------------------------
               if($_SESSION[$_sesmodulename]["sql_company"])
                  $datsql .= " and t1.invc_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
               if($_SESSION[$_sesmodulename]["sql_shop"])
                  $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
               if($_SESSION[$_sesmodulename]["sql_supplier"])
                  $datsql .= " and t1.invc_supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";

                            
            }
            $datsql .= " order by 2 desc, 3 desc, 4 desc";
            $invoices = $CON->select($datsql);
            for($y = 0; $y < count($invoices) && $invoices != false; $y++)
            {
               //----------------------------------------------------------------------------------
               $datsql = " select  t1.note_discount_perc
                           from invoices_notes_buy t1
                           where
                           t1.note_status          > 1 and
                           t1.note_parent_invcid   = {$invoices[$y]["id"]} and
                           t1.note_is_discount     = 1 and
                           t1.note_discount_perc   > 0.00";
               $item_dsc_nc = $CON->select($datsql);
               $item_dsc_nc = (float)$item_dsc_nc[0]["note_discount_perc"];

               if((int)$invoices[$y]["invc_importation"])
               {
                  $invoices[$y]["item_costprice_netto"]  = $invoices[$y]["item_costprice_netto"] * $invoices[$y]["invc_exc_rate"];
                  $invoices[$y]["item_cost_pricedsc"]    = $invoices[$y]["item_cost_pricedsc_import"];
               }
               
               $item_dsc = round(($invoices[$y]["item_costprice_netto"] - $invoices[$y]["item_cost_pricedsc"]) / $invoices[$y]["item_costprice_netto"] * 100,2);
               if($item_dsc_nc > 0.00)
               {
                  $invoices[$y]["item_cost_pricedsc"] -= $invoices[$y]["item_cost_pricedsc"] / 100 * $item_dsc_nc;
               }
               ?>
               <tr bgcolor="<?=getRowColor(0)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os">
                     <nobr>
                     <img src="./images/menu/icons/arrow-turn-000-left.png" style="vertical-align:bottom">
                     <?=date('d.m.Y', $invoices[$y]["invc_date"])?>
                     </nobr>
                  </td>
                  <td class="content_row_os"><?=$invoices[$y]["invc_docnumber"]?></td>
                  <td class="content_row_os" colspan="5">
                     <table border="0" cellpadding="0" cellspacing="0" width="100%">
                     <colgroup>
                        <col>
                        <col width="110">
                        <col width="130">
                        <col width="100">
                        <col width="100">
                        <col width="130">
                     </colgroup>
                     <tr>
                        <td class="content_row_clear"><?=$invoices[$y]["supp_short"]?></td>
                        <td class="content_row_clear"><nobr><b>Cantidad:</b> <?=printPrice($invoices[$y]["item_amount"],2)?></nobr></td>
                        <td class="content_row_clear"><nobr><b>Precio/C:</b> $ <?=printPrice($invoices[$y]["item_costprice_netto"],2)?></nobr></td>
                        <td class="content_row_clear"><nobr><b>Desc/F:</b> <?=printPrice($item_dsc,2)?>%</nobr></td>
                        <td class="content_row_clear"><nobr><b>Desc/N:</b> <?=printPrice($item_dsc_nc,2)?>%</nobr></td>
                        <td class="content_row_clear"><nobr><b>Precio/F:</b> $ <?=printPrice($invoices[$y]["item_cost_pricedsc"])?></nobr></td>
                     </tr>
                     </table>
                  </td>
               </tr>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x][$y]["invc_date"]            = date('d.m.Y', $invoices[$y]["invc_date"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x][$y]["invc_docnumber"]       = $invoices[$y]["invc_docnumber"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x][$y]["supp_short"]           = $invoices[$y]["supp_short"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x][$y]["item_amount"]          = "Cantidad: ".printPrice($invoices[$y]["item_amount"],2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x][$y]["item_costprice_netto"] = "Precio/C: $ ".printPrice($invoices[$y]["item_costprice_netto"],2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x][$y]["item_dsc"]             = "Desc/Fact: ".printPrice($item_dsc,2)."%";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x][$y]["item_dsc_nc"]          = "Desc/NC: ".printPrice($item_dsc_nc,2)."%";
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x][$y]["item_cost_pricedsc"]   = "Precio/Final: $ ".printPrice($invoices[$y]["item_cost_pricedsc"]);
            }
         }
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
      else
      {
         /*
         ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_totals content_row_os" colspan="3" style="border-right:3px double black">Total</td>
            <?php
            foreach($selshops AS $selshop)
            {  ?>
               <td class="content_row_totals content_row_os" align="center"><?=printPrice($_TOTALSHP[$selshop["id"]],2, true)?></td>
               <td class="content_row_totals content_row_os" align="center" style="border-right:3px double black"><?=printPrice($_TOTALSHPCOST[$selshop["id"]],2, true)?></td>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["SHOP"][$selshop["shop_name"]]["curr"] = printPrice($_TOTALSHP[$selshop["id"]],2);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["SHOP"][$selshop["shop_name"]]["cost"] = printPrice($_TOTALSHPCOST[$selshop["id"]],2);
            }
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"] = "Total";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["total"] = $_TOTAL;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["totalcost"] = $_TOTALCOST;
            ?>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($_TOTAL,2, true)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($_TOTALCOST,2, true)?></td>
         </tr>
         <?php
         */
      }
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsStockValues($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsStockValues($CON);

if($pdffile != "")
{
   $doctitle = "Stock-por-sucursal-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Stock-por-sucursal-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>