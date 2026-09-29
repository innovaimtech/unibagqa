<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2025 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "item_sths_unibag";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo/Familia" => "2");

unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_company_orig"]       = (int)$_REQUEST["sql_company_orig"];
   $_SESSION[$_sesmodulename]["sql_company_dest"]       = (int)$_REQUEST["sql_company_dest"];
   $_SESSION[$_sesmodulename]["sql_shop_orig"]          = (int)$_REQUEST["sql_shop_orig"];
   $_SESSION[$_sesmodulename]["sql_shop_dest"]          = (int)$_REQUEST["sql_shop_dest"];
   $_SESSION[$_sesmodulename]["sql_item_id"]       = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"]     = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]          = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]       = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_folio"]         = trim($_REQUEST["sql_folio"]);
   $_SESSION[$_sesmodulename]["sql_xitemtype"]     = (int)$_REQUEST["sql_xitemtype"];
   $_SESSION[$_sesmodulename]["sql_equvals"]       = $_REQUEST["sql_equvals"];
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;

}

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON, true);
$shops      = getShops($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;


//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m');
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

$sql_year_init    = (int)date('Y', $sql_datefrom);
$sql_month_init   = (int)date('m', $sql_datefrom);
$sql_day_init     = (int)date('d', $sql_datefrom);
$sql_year_end     = (int)date('Y', $sql_dateto);
$sql_month_end    = (int)date('m', $sql_dateto);
$sql_day_end      = (int)date('d', $sql_dateto);

//----------------------------------------------------------------------------------


if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t3.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if($_SESSION[$_sesmodulename]["sql_item_id"])
   $seasql .= " and t1b.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t1b.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";

if($_SESSION[$_sesmodulename]["sql_folio"] != "")
   $seasql .= " and t1.strc_number = '{$_SESSION[$_sesmodulename]["sql_folio"]}' ";

if($_SESSION[$_sesmodulename]["sql_company_orig"])
   $seasql .= " and t1.strc_company_id = {$_SESSION[$_sesmodulename]["sql_company_orig"]} ";
if($_SESSION[$_sesmodulename]["sql_shop_orig"])
   $seasql .= " and t1.strc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop_orig"]} ";

if($_SESSION[$_sesmodulename]["sql_company_dest"])
   $seasql .= " and t1.strc_company_dest_id = {$_SESSION[$_sesmodulename]["sql_company_dest"]} ";
if($_SESSION[$_sesmodulename]["sql_shop_dest"])
   $seasql .= " and t1.strc_shop_dest_id = {$_SESSION[$_sesmodulename]["sql_shop_dest"]} ";

//----------------------------------------------------------------------------------
$datsql = " select t1.id, t1.strc_date, t1.strc_shop_dest_id, t1b.item_st_dest_id 'strc_shop_stid_dest',
                   t1b.item_id, t1b.item_type, t2.item_title, t2.item_number_prod,
                   t4.unit_name, t1.strc_number, t1b.item_amount, t1.strc_desc,
                   t7.st_name 'st_name_dest',
                   t8.shop_name 'shop_name_dest',
                   t9.company_short 'comp_name_dest',
                   t7x.st_name 'st_name_orig',
                   t8x.shop_name 'shop_name_orig',
                   t9x.company_short 'comp_name_orig',
                   t6z.user_lastname 'user_aprob',
                   t7z.user_lastname 'user_crt'
            from storehousechanges t1
            INNER JOIN storehousechanges_items t1b ON t1.id = t1b.strc_id
            INNER JOIN item t2               ON t1b.item_id = t2.id
            INNER JOIN item_productcats t3   ON t1b.item_id = t3.item_id
            LEFT OUTER JOIN item_units t4    ON t2.item_unit = t4.id
            LEFT OUTER JOIN productcats t6   ON t3.cat_id = t6.id
            INNER JOIN company_shops t8x              ON t1.strc_shop_id = t8x.id
            INNER JOIN company_data t9x               ON t8x.shop_company_id = t9x.id
            INNER JOIN company_shops_storehouses t7x  ON t1b.item_st_id = t7x.id
            INNER JOIN company_shops t8               ON t1.strc_shop_dest_id = t8.id
            INNER JOIN company_data t9                ON t8.shop_company_id = t9.id
            INNER JOIN company_shops_storehouses t7   ON t1b.item_st_dest_id = t7.id

            LEFT OUTER JOIN user t6z ON t1.strc_updusr = t6z.id
            LEFT OUTER JOIN user t7z ON t1.strc_crtusr = t7z.id
            where
            t1.strc_status = 2 and
            t1b.item_type  = 'item' and
            t1.strc_date between {$sql_datefrom} and {$sql_dateto} ";
$datsql .= $seasql;

//----------------------------------------------------------------------------------
$datsql .= " order by t1.strc_date desc, t1.strc_number desc, t1b.item_pos asc ";

//----------------------------------------------------------------------------------
$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

?>
<script language="Javascript">
function setCompanyShopOrigen(companyidx)
{
   var obj = document.all.sql_shop_orig;
   obj.options.length = 1;
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
function setCompanyShopDest(companyidx)
{
   var obj = document.all.sql_shop_dest;
   obj.options.length = 1;
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
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="1080">
<tr>
   <td height="30"><b class="content_header">Traspasos</b></td>
   <td align="right" class="content_row_clear"></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <?=Nifty_printH("box2", "1080")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="110">
         <col>
         <col width="110">
         <col width="400">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Empresa origen</td>
         <td class="content_row">
            <select class="text" name="sql_company_orig" style="width:100%"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setCompanyShopOrigen(this.value)">
               <option value="">Seleccione</option>
               <?php
               foreach($companies AS $company)
               {  ?>
                  <option value="<?=$company["id"]?>"
                  <?php if($company["id"] == $_SESSION[$_sesmodulename]["sql_company_orig"]) echo "selected"?>><?=$company["company_short"]?></option><?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Empresa destino</td>
         <td class="content_row">
            <select class="text" name="sql_company_dest" style="width:100%"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setCompanyShopDest(this.value)">
               <option value="">Seleccione</option>
               <?php
               foreach($companies AS $company)
               {  ?>
                  <option value="<?=$company["id"]?>"
                  <?php if($company["id"] == $_SESSION[$_sesmodulename]["sql_company_dest"]) echo "selected"?>><?=$company["company_short"]?></option><?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Sucursal origen</td>
         <td class="content_row">
            <?php
            $selshops = Array();
            if($_SESSION[$_sesmodulename]["sql_company_orig"])
            {
               foreach($shops AS $shop)
                  if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company_orig"])
                     array_push($selshops, $shop);
            }
            ?>
            <select class="text" name="sql_shop_orig" style="width:100%"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">Seleccione</option>
               <?php
               foreach($selshops AS $selshop)
               {  ?>
                  <option value="<?=$selshop["id"]?>"
                  <?php if($selshop["id"] == $_SESSION[$_sesmodulename]["sql_shop_orig"]) echo "selected"?>><?=$selshop["shop_name"]?>
                  </option><?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Sucursal destino</td>
         <td class="content_row">
            <?php
            $selshops = Array();
            if($_SESSION[$_sesmodulename]["sql_company_dest"])
            {
               foreach($shops AS $shop)
                  if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company_dest"])
                     array_push($selshops, $shop);
            }
            ?>
            <select class="text" name="sql_shop_dest" style="width:100%"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">Seleccione</option>
               <?php
               foreach($selshops AS $selshop)
               {  ?>
                  <option value="<?=$selshop["id"]?>"
                  <?php if($selshop["id"] == $_SESSION[$_sesmodulename]["sql_shop_dest"]) echo "selected"?>><?=$selshop["shop_name"]?>
                  </option><?php
               }
               ?>
            </select>
         </td>
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
               <td class="content_row_clear" width="195" id="idx_selmode2" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 2) echo "style='display:none'"?>>
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
                     $endyear    = date('Y') +1;

                     for($x = $startyear; $x <= $endyear; $x++)
                     {
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_year1"]) echo "selected" ?>><?=$x?></option>
                        <?php
                     }
                     ?>
                  </select>
                  -
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
                     $endyear    = date('Y') +1;

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
         <td class="content_rowl">Artículo</td>
         <td class="content_row"><?php printOverviewItemSelect($_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Familia</td>
         <td class="content_row">
            <select class="text" name="sql_pcat" style="width:100%"
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
         <td class="content_rowl">Folio</td>
         <td class="content_row">
            <input type="text" style="width:100%" id="sql_folio" name="sql_folio" class="text"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_folio"])?>">
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="135">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if(count($items) > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($items) > 0)
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
      <?=Nifty_printH("box1", "99%")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>

      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="12">Traspasos recibidos</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader content_row_os">Folio</td>
         <td class="content_tbl_subheader content_row_os">Fecha</td>
         <td class="content_tbl_subheader content_row_os" style="border-left:3px double #666666">Sucursal origen</td>
         <td class="content_tbl_subheader content_row_os">Bodega origen</td>
         <td class="content_tbl_subheader content_row_os" style="border-left:3px double #666666">Sucursal destino</td>
         <td class="content_tbl_subheader content_row_os">Bodega destino</td>
         <td class="content_tbl_subheader content_row_os" style="border-left:3px double #666666">Código</td>
         <td class="content_tbl_subheader content_row_os">Producto</td>
         <td class="content_tbl_subheader content_row_os">Cantidad</td>
         <td class="content_tbl_subheader content_row_os" style="border-left:3px double #666666">Creador</td>
         <td class="content_tbl_subheader content_row_os">Aprobador</td>
         <td class="content_tbl_subheader content_row_os">Observaciones</td>
      </tr>
      <?php
      $x = 0;
      foreach($items AS $item)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$item["strc_number"]?></td>
            <td class="content_row_os"><?=date('d.m.Y', $item["strc_date"])?></td>
            <td class="content_row_os" style="border-left:3px double #666666"><nobr><?=$item["shop_name_orig"]?></nobr></td>
            <td class="content_row_os"><?=$item["st_name_orig"]?></td>
            <td class="content_row_os" style="border-left:3px double #666666"><nobr><?=$item["shop_name_dest"]?></nobr></td>
            <td class="content_row_os"><?=$item["st_name_dest"]?></td>
            <td class="content_row_os" style="border-left:3px double #666666"><?=$item["item_number_prod"]?></td>
            <td class="content_row_os"><?=$item["item_title"]?></td>
            <td class="content_row_os"><?=printPrice($item["item_amount"],2)?></td>
            <td class="content_row_os" style="border-left:3px double #666666"><?=$item["user_crt"]?></td>
            <td class="content_row_os"><?=$item["user_aprob"]?></td>
            <td class="content_row_os"><?=$item["strc_desc"]?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["strc_number"]         = $item["strc_number"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["strc_date"]           = date('d.m.Y', $item["strc_date"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["shop_name_orig"]      = $item["shop_name_orig"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["st_name_orig"]        = $item["st_name_orig"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["shop_name_dest"]      = $item["shop_name_dest"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["st_name_dest"]        = $item["st_name_dest"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]    = $item["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]          = $item["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_amount"]         = printPrice($item["item_amount"],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["user_crt"]            = $item["user_crt"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["user_aprob"]          = $item["user_aprob"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["strc_desc"]           = $item["strc_desc"];

         $x++;
      }
      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="12" align="center">
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
      <br>
   </td>
</tr>
</table>
<?php
$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';


//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsStockchangesUnibag($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsStockchangesUnibag($CON);

if($pdffile != "")
{
   $doctitle = "Traspasos-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Traspasos-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>