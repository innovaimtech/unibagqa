<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_buying_supdetail";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Proveedor" => "1", "Nombre" => "2", "RUT" => "3", "Factura" => "0", "Fecha" => "0", "Extento" => "4", "Neto" => "5", "IVA" => "6", "Total" => "7");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
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
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]       = (int)$_REQUEST["sql_dspmode"];
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
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;

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
$datsql = " select t1.invc_supplier_id, t6.supp_company, t6.supp_short, t6.supp_rut, t1.invc_total_netto 'invc_total_netto',
                   t1.invc_total_taxes 'invc_total_taxes', t1.invc_total_taxes_exclude 'invc_total_taxes_exclude',
                   t1.invc_total_brutto 'invc_total_brutto', 'note_type' 'invoice' , t1.invc_date, t1.invc_docnumber, t1.id
            from invoices_buy t1
            INNER JOIN company_data t4    ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN supplier t6   ON ( t1.invc_supplier_id = t6.id )
            where
            t1.invc_status       > 1 and
            t1.invc_date between {$sql_datefrom} and {$sql_dateto}";
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   $datsql .= " and t1.invc_supplier_id  = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";

$datsql .= " UNION ALL
             select t1.note_supplier_id 'invc_supplier_id', t6.supp_company, t6.supp_short, t6.supp_rut, t1.note_total_netto 'invc_total_netto',
                   t1.note_total_taxes 'invc_total_taxes', t1.note_total_taxes_exclude 'invc_total_taxes_exclude',
                   t1.note_total_brutto 'invc_total_brutto', t1.note_type, t1.note_date 'invc_date', t1.note_docnumber 'invc_docnumber', t1.id
            from invoices_notes_buy t1
            INNER JOIN company_data t4    ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN supplier t6   ON ( t1.note_supplier_id = t6.id )
            where
            t1.note_status       > 1 and
            t1.note_date between {$sql_datefrom} and {$sql_dateto}";
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.note_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   $datsql .= " and t1.note_supplier_id  = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
$datsql .= " order by 3, 10";
$noteitems = $CON->select($datsql);

for($x = 0; $x < count($noteitems) && $noteitems != false; $x++)
{
   $row = $noteitems[$x];

   $idx = $row["invc_supplier_id"];

   if(!is_array($_RES[$idx]))
      $_RES[$idx] = Array();
   $_RES[$idx][] = $row;
   $_SUP[$idx]["NAME"] = $row["supp_short"];
   $_SUP[$idx]["COMP"] = $row["supp_company"];
   $_SUP[$idx]["RUT"]  = $row["supp_rut"];
}

$_SESSION["STATS"][$_sesmodulename]["HEADER"]["SUPS"] = $_SUP;

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
         array_push($selshops, $shop);
}

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
{
   $sql = " select supp_short
            from supplier
            where
            id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
   $custdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = $custdata[0]["supp_short"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = "TODO";

$_SESSION["HEADER"][$_sesmodulename]["FROM"] = date('d.m.Y', $sql_datefrom);
$_SESSION["HEADER"][$_sesmodulename]["TO"]   = date('d.m.Y', $sql_dateto);

printJSsetCompanyShop($shops);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Compras por proveedor / Detalle</b></td>
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
                  if(count($_SUP) > 0 && $_SUP != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($_SUP) > 0 && $_SUP != false)
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
      <?php
      foreach(array_keys($_SUP) AS $supid)
      {  ?>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="20">
            <col width="150">
            <col width="150">
            <col>
            <col width="110">
            <col width="110">
            <col width="110">
            <col width="110">
         </colgroup>
         <tr>
            <td class="content_tbl_header" valign="top" colspan="8">
               <?=$_SUP[$supid]["NAME"]?>, RUT: <?=$_SUP[$supid]["RUT"]?>
            </td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os" align="center">PDF</td>
            <td class="content_tbl_subheader content_row_os">Tipo</td>
            <td class="content_tbl_subheader content_row_os">Número DOCTO</td>
            <td class="content_tbl_subheader content_row_os">Fecha</td>
            <td class="content_tbl_subheader content_row_os" align="right">Exento</td>
            <td class="content_tbl_subheader content_row_os" align="right">Neto</td>
            <td class="content_tbl_subheader content_row_os" align="right">IVA</td>
            <td class="content_tbl_subheader content_row_os" align="right">Bruto</td>
         </tr>
         <?php
         $x = 0;
         $ges_invc_total_taxes_exclude = 0.00;
         $ges_invc_total_netto         = 0.00;
         $ges_invc_total_taxes         = 0.00;
         $ges_invc_total_brutto        = 0.00;
         foreach($_RES[$supid] AS $row)
         {
            $pdflnk = "";
            if($row["note_type"] == "note_typeinvoice")
            {
               $row["note_type"] = "Factura";
               $ges_invc_total_taxes_exclude += $row["invc_total_taxes_exclude"];
               $ges_invc_total_netto         += $row["invc_total_netto"];
               $ges_invc_total_taxes         += $row["invc_total_taxes"];
               $ges_invc_total_brutto        += $row["invc_total_brutto"];
               $pdflnk = "document.all.idxifrsrc.src='index.php?mid=723&exec=edit&subexec=edit&id={$row["id"]}&printpdf=1'";
            }
            elseif($row["note_type"] == "1")
            {
               $row["note_type"] = "Nota de Credito";
               $ges_invc_total_taxes_exclude -= $row["invc_total_taxes_exclude"];
               $ges_invc_total_netto         -= $row["invc_total_netto"];
               $ges_invc_total_taxes         -= $row["invc_total_taxes"];
               $ges_invc_total_brutto        -= $row["invc_total_brutto"];
               $pdflnk = "document.all.idxifrsrc.src='index.php?mid=730&exec=edit&subexec=edit&id={$row["id"]}&printpdf=1'";
            }
            else
            {
               $row["note_type"] = "Nota de Debito";
               $ges_invc_total_taxes_exclude += $row["invc_total_taxes_exclude"];
               $ges_invc_total_netto         += $row["invc_total_netto"];
               $ges_invc_total_taxes         += $row["invc_total_taxes"];
               $ges_invc_total_brutto        += $row["invc_total_brutto"];
               $pdflnk = "document.all.idxifrsrc.src='index.php?mid=730&exec=edit&subexec=edit&id={$row["id"]}&printpdf=1'";
            }
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os" align="center">
                  <a class="link" style="color:#FFE3BA" href="javascript: deactivateFormChange()"
                  onclick="<?=$pdflnk?>"><img
                  src="./images/menu/icons/document-pdf.png" style="vertical-align:bottom"></a>
               </td>
               <td class="content_row_os"><?=$row["note_type"]?></td>
               <td class="content_row_os"><?=$row["invc_docnumber"]?></td>
               <td class="content_row_os"><?=date('d.m.Y', $row["invc_date"])?></td>
               <td class="content_row_os" align="right"><?=printPrice($row["invc_total_taxes_exclude"],2)?></td>
               <td class="content_row_os" align="right"><?=printPrice($row["invc_total_netto"],2)?></td>
               <td class="content_row_os" align="right"><?=printPrice($row["invc_total_taxes"],2)?></td>
               <td class="content_row_os" align="right"><?=printPrice($row["invc_total_brutto"],2)?></td>
            </tr>
            <?php
            //----------------------------------------------------------------------------------
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$supid][$x]["note_type"]                  = $row["note_type"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$supid][$x]["invc_docnumber"]             = $row["invc_docnumber"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$supid][$x]["invc_date"]                  = date('d.m.Y', $row["invc_date"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$supid][$x]["invc_total_taxes_exclude"]   = printPrice($row["invc_total_taxes_exclude"],2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$supid][$x]["invc_total_netto"]           = printPrice($row["invc_total_netto"],2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$supid][$x]["invc_total_taxes"]           = printPrice($row["invc_total_taxes"],2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$supid][$x]["invc_total_brutto"]          = printPrice($row["invc_total_brutto"],2);
            $x++;
         }
         ?>
         <tr>
            <td class="content_row_totals content_row_os" colspan="4">TOTAL</td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_invc_total_taxes_exclude,2)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_invc_total_netto,2)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_invc_total_taxes,2)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_invc_total_brutto,2)?></td>
         </tr>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?php
         //----------------------------------------------------------------------------------
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$supid][$x]["note_type"]                  = "<b>TOTAL</b>";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$supid][$x]["invc_total_taxes_exclude"]   = "<b>".printPrice($ges_invc_total_taxes_exclude,2)."</b>";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$supid][$x]["invc_total_netto"]           = "<b>".printPrice($ges_invc_total_netto,2)."</b>";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$supid][$x]["invc_total_taxes"]           = "<b>".printPrice($ges_invc_total_taxes,2)."</b>";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$supid][$x]["invc_total_brutto"]          = "<b>".printPrice($ges_invc_total_brutto,2)."</b>";
      }
      ?>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsItemSuppliersDetails($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsItemSuppliersDetails($CON);
  
if($pdffile != "")
{
   $doctitle = "Compras-proveedor-detalles-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Compras-proveedor-detalles-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>