<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "book_buying";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc, 15 asc, 1 asc";
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
$_sortlinks             = Array("Correlativo N"    => 3,
                                "Comprob. Prov."   => 4,
                                "Fecha"            => 2,
                                "Proveedor"        => 12,
                                "RUT"              => 13,
                                "Exento"           => 7,
                                "Neto"             => 5,
                                "IVA"              => 6,
                                "Total"            => 8);
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_supplier"]      = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_importation"]   = (int)$_REQUEST["sql_importation"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_money"]         = $_REQUEST["sql_money"];
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
}

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

//----------------------------------------------------------------------------------
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
   
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
if($_SESSION[$_sesmodulename]["sql_supplier"] != "")
   $supptaxes  = getSupplierTaxes($CON, $_SESSION[$_sesmodulename]["sql_supplier"]);
   
if($supptaxes && $_SESSION[$_sesmodulename]["sql_money"] == "USD")
   $_SESSION[$_sesmodulename]["sql_money"] = "CLP";
if(!(int)$_SESSION[$_sesmodulename]["sql_importation"])
   $_SESSION[$_sesmodulename]["sql_money"] = "CLP";
if($_SESSION[$_sesmodulename]["sql_money"] == "")
   $_SESSION[$_sesmodulename]["sql_money"] = "CLP";
$_SESSION[$_sesmodulename]["sql_importation"] = (int)$_SESSION[$_sesmodulename]["sql_importation"];

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 100);

//----------------------------------------------------------------------------------
$datsql = " select distinct t1.id, t1.invc_date, t1.invc_number, t1.invc_docnumber,
                   t1.invc_total_netto, t1.invc_total_taxes, t1.invc_total_taxes_exclude,
                   t1.invc_total_brutto, t1.invc_import_total, t1.invc_exc_rate,
                   t4.company_short, t6.supp_company, t6.supp_rut, t1.invc_importation,
                   t1.invc_intnumber, t7.cc_title, t7.cc_code, t1.invc_contdoctype_id,
                   t8.typedoc_cont_nameid, t8.typedoc_cont_code, 
                   t1.invc_total_netto_dsc, t1.invc_taxes
         from invoices_buy t1
         INNER JOIN company_data t4    ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
         LEFT OUTER JOIN supplier t6   ON ( t1.invc_supplier_id = t6.id )
         LEFT OUTER JOIN codigo_contable t7        ON t1.invc_code_cont_id = t7.id
         LEFT OUTER JOIN typedoc_contables t8      ON t1.invc_contdoctype_id = t8.id
         INNER JOIN invoices_buy_parts t9          ON t1.id = t9.part_invc_id
         LEFT OUTER JOIN company_shops t11 ON t11.id = t1.invc_shop_id
         WHERE
         t1.invc_status       > 1 and
         t1.invc_contab_date  between {$sql_datefrom} and {$sql_dateto} and
         t1.invc_importation  = {$_SESSION[$_sesmodulename]["sql_importation"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_company"])
  $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
  $datsql .= " and t1.invc_supplier_id  = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_shop"])
  $datsql .= " and t1.invc_shop_id  = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
$datsql .= " order by t1.invc_contdoctype_id, t1.invc_date asc, t1.id asc ";
$trans = $CON->select($datsql);

$xtrans = $trans;
$trans = array();
foreach($xtrans AS $tran)
   $trans[$tran["typedoc_cont_nameid"]][] = $tran;
//----------------------------------------------------------------------------------
function getStatsResultOrderCallbackRes($a, $b)
{
   return ($a["invc_intnumber"] > $b["invc_intnumber"]);
   /*
   if((int)$a["invc_date"] == (int)$b["invc_date"])
      return ($a["invc_intnumber"] > $b["invc_intnumber"]);
   else
      return ((int)$a["invc_date"] > (int)$b["invc_date"]);
   */
}
//usort($trans, "getStatsResultOrderCallbackRes");

//----------------------------------------------------------------------------------
$datsql = " select distinct t1.id, t1.note_date 'invc_date', t1.note_number 'invc_number', t1.note_docnumber 'invc_docnumber',
                   t1.note_total_netto 'invc_total_netto', t1.note_total_taxes 'invc_total_taxes',
                   t1.note_total_taxes_exclude 'invc_total_taxes_exclude',
                   t1.note_total_brutto 'invc_total_brutto', t1.note_import_total 'invc_import_total',
                   t1.note_exc_rate 'invc_exc_rate', t4.company_short, t6.supp_company, t6.supp_rut,
                   t1.note_type, t1.note_importation 'invc_importation', t1.note_intnumber 'invc_intnumber',
                   t7.cc_title, t7.cc_code, t1.note_contdoctype_id, t8.typedoc_cont_nameid, t8.typedoc_cont_code
            from invoices_notes_buy t1
            INNER JOIN company_data t4    ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN supplier t6   ON ( t1.note_supplier_id = t6.id )
            LEFT OUTER JOIN codigo_contable     t7  ON t1.note_code_cont_id = t7.id
            LEFT OUTER JOIN typedoc_contables   t8  ON t1.note_contdoctype_id = t8.id
            LEFT OUTER JOIN company_shops t11 ON t11.id = t1.note_shop_id
            WHERE
            t1.note_status       > 1 and
            t1.note_receipt_date between {$sql_datefrom} and {$sql_dateto} and
            t1.note_importation  = {$_SESSION[$_sesmodulename]["sql_importation"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_company"])
  $datsql .= " and t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
  $datsql .= " and t1.note_supplier_id  = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_shop"])
  $datsql .= " and t1.note_shop_id  = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
$datsql .= " order by t1.note_contdoctype_id, t1.note_date asc, t1.id asc";
$notes = $CON->select($datsql);

for($x = 0; $x < count($notes) && $notes != false; $x++)
{
   if(!is_array($resnotes[$notes[$x]["note_type"]][$notes[$x]["typedoc_cont_nameid"]]))
      $resnotes[$notes[$x]["note_type"]][$notes[$x]["typedoc_cont_nameid"]] = Array();

   $resnotes[$notes[$x]["note_type"]][$notes[$x]["typedoc_cont_nameid"]][] = $notes[$x];
}

/*
foreach(array_keys($resnotes) AS $notetype)
{
   $temp = $resnotes[$notetype];
   usort($temp, "getStatsResultOrderCallbackRes");
   $resnotes[$notetype] = $temp;
}
*/

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON);
$shops      = getShops($CON);

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

?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
printJSsetCompanyShop($shops);
?>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Libro de compra</b></td>
   <td align="right" class="content_row_clear"></td>
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
         <td class="content_rowl">Tipo</td>
         <td class="content_row">
            <input type="radio" name="sql_importation" value="0" <?php if(!(int)$_SESSION[$_sesmodulename]["sql_importation"]) echo "checked"?>> Nacional
            <input type="radio" name="sql_importation" value="1" <?php if( (int)$_SESSION[$_sesmodulename]["sql_importation"]) echo "checked"?>> Internacional
         </td>
         <td class="content_rowl">Moneda</td>
         <td class="content_row">
            <input type="radio" name="sql_money" value="CLP" <?php if($_SESSION[$_sesmodulename]["sql_money"] == "CLP") echo "checked"?>> Pesos
            <input type="radio" name="sql_money" value="USD" <?php if($_SESSION[$_sesmodulename]["sql_money"] == "USD") echo "checked"?> <?php if($supptaxes) echo "disabled"?>> US Dollar
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="135">
               <col width="132">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if((count($trans) > 0 && $trans != false) || count($resnotes) > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if((count($trans) > 0 && $trans != false) || count($resnotes) > 0)
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left" style="display:none">
               <?php
               if((count($trans) > 0 && $trans != false) || count($resnotes) > 0)
               {
                  if($_SESSION[$_sesmodulename]["sql_month1"] == $_SESSION[$_sesmodulename]["sql_month2"] &&
                     $_SESSION[$_sesmodulename]["sql_year1"] == $_SESSION[$_sesmodulename]["sql_year2"])
                  {
                     if(count($trans[""]) == 0 && count($resnotes[1][""]) == 0 && count($resnotes[2][""]) == 0)
                        printButton("Sincronizar Online", "postnav_save", "javascript: deactivateFormChange()", "showFancybox('/iframe.fancy.php?module=buyingsync', 'iframe', 600, 400, 'auto');", "gear", 130);
                     else
                        echo "&nbsp;&nbsp;<b class=msg_save_err>Libro de compra incompleto, por favor asignar todos los documentos.</b>";
                  }
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
   <td class="content_row_clear">
      <?php
      $res_total_taxes_exclude   = 0.00;
      $res_total_netto           = 0.00;
      $res_total_taxes           = 0.00;
      $res_total_brutto          = 0.00;

      if(count($trans) && $trans != false)
      {
         foreach(array_keys($trans) AS $tdcc)
         {
            if((int)$tdcc)
               $factitle = getTypeDocContName($tdcc);
            else
               $factitle = "NO TIPO FACTURA ASIGNADO";
            $data = $trans[$tdcc];
            ?>
            <?=Nifty_printH("box1", "1200")?>
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="90">
               <col width="90">
               <col width="150">
               <col width="90">
               <col>
               <col width="85">
               <col width="85">
               <col width="85">
               <col width="85">
               <col width="85">
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="10"><img src="./images/menu/icons/money.png" style="vertical-align:bottom">&nbsp;&nbsp;<?=$factitle?></td>
            </tr>
            <tr>
               <td class="content_tbl_subheader content_row_os">Nº Correlativo</td>
               <td class="content_tbl_subheader content_row_os">Nº Docto.</td>
               <td class="content_tbl_subheader content_row_os">Cod. Cont.</td>
               <td class="content_tbl_subheader content_row_os">Fecha</td>
               <td class="content_tbl_subheader content_row_os">Proveedor</td>
               <td class="content_tbl_subheader content_row_os">RUT</td>
               <td class="content_tbl_subheader content_row_os" align="right">Exento</td>
               <td class="content_tbl_subheader content_row_os" align="right">Neto</td>
               <td class="content_tbl_subheader content_row_os" align="right">IVA</td>
               <td class="content_tbl_subheader content_row_os" align="right">Bruto</td>
            </tr>
            <?php
            $ges_total_taxes_exclude   = 0.00;
            $ges_total_netto           = 0.00;
            $ges_total_taxes           = 0.00;
            $ges_total_brutto          = 0.00;
            //----------------------------------------------------------------------------------
            for($x = 0; $x < count($data) && $data != false; $x++)
            {
               $tran       = $data[$x];
               $tran_netto = $tran["invc_total_netto"];
               $gesnum     = ++$_NUMS[date('m/Y', $data["invc_date"])];
               $gesnum     = sprintf("%02s", $gesnum).'/'.date('m',$data["invc_date"]);

               //----------------------------------------------------------------------------------
               if(!(int)$tran["invc_taxes"])
               {
                  $tran_netto = 0.00;
                  $tran["invc_total_taxes_exclude"] = $tran["invc_total_netto"];
               }
               if((int)$data["invc_importation"] && $_SESSION[$_sesmodulename]["sql_money"] == "CLP")
               {
                  $numberlim = 0;
                  $moneystr  = "";
                  $tran["invc_total_taxes_exclude"]   = $tran["invc_import_total"];
                  $tran["invc_total_brutto"]          = $tran["invc_import_total"];
               }
               elseif((int)$data["invc_importation"] && $_SESSION[$_sesmodulename]["sql_money"] == "USD")
               {
                  $numberlim = 2;
                  $moneystr  = "";
               }
               elseif(!(int)$data["invc_importation"])
               {
                  $numberlim = 0;
                  $moneystr  = "";
               }
               if((int)$tran["invc_taxes"] && $tran["invc_total_taxes_exclude"] > 0.00)
               {
                  $tran["invc_total_netto"] = $tran["invc_total_netto"] - $tran["invc_total_taxes_exclude"];
                  $tran_netto = $tran["invc_total_netto"];
               }
               ?>
               <tr bgcolor="<?=getRowColor($x +1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os"><?=$tran["invc_intnumber"]?>&nbsp;</td>
                  <td class="content_row_os"><?=$tran["invc_docnumber"]?></td>
                  <td class="content_row_os"><?=$tran["cc_code"]." ".$tran["cc_title"]?>&nbsp;</td>
                  <td class="content_row_os"><?=date('d.m.Y', $tran["invc_date"])?></td>
                  <td class="content_row_os"><?=$tran["supp_company"]?>&nbsp;</td>
                  <td class="content_row_os"><?=$tran["supp_rut"]?>&nbsp;</td>
                  <td class="content_row_os" align="right"><nobr><?=printPrice($tran["invc_total_taxes_exclude"], $numberlim)?><?=$moneystr?></nobr></td>
                  <td class="content_row_os" align="right"><nobr><?=printPrice($tran_netto, $numberlim)?><?=$moneystr?></nobr></td>
                  <td class="content_row_os" align="right"><nobr><?=printPrice($tran["invc_total_taxes"], $numberlim)?><?=$moneystr?></nobr></td>
                  <td class="content_row_os" align="right"><nobr><?=printPrice($tran["invc_total_brutto"], $numberlim)?><?=$moneystr?></nobr></td>
               </tr>
               <?php
               
               $ges_total_taxes_exclude   += $tran["invc_total_taxes_exclude"];
               $ges_total_netto           += $tran_netto;
               $ges_total_taxes           += $tran["invc_total_taxes"];
               $ges_total_brutto          += $tran["invc_total_brutto"];

               //----------------------------------------------------------------------------------
               $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$factitle][$x]                              = $tran;
               $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$factitle][$x]["gesnum"]                    = $tran["invc_intnumber"];
               $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$factitle][$x]["cc_code"]                   = $tran["cc_code"]." ".$tran["cc_title"];
               $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$factitle][$x]["tran_type"]                 = $tran_type;
               $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$factitle][$x]["invc_date"]                 = date('d.m.Y', $tran["invc_date"]);
               $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$factitle][$x]["invc_total_taxes_exclude"]  = printPrice($tran["invc_total_taxes_exclude"], $numberlim);
               $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$factitle][$x]["tran_netto"]                = printPrice($tran_netto, $numberlim);
               $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$factitle][$x]["invc_total_taxes"]          = printPrice($tran["invc_total_taxes"], $numberlim);
               $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$factitle][$x]["invc_total_brutto"]         = printPrice($tran["invc_total_brutto"], $numberlim);

               //----------------------------------------------------------------------------------
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["TpoDoc"]     = $tran["typedoc_cont_code"];
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["NroDoc"]     = $tran["invc_docnumber"];
               if($tran["invc_total_taxes"] > 0.00)
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["TasaImp"]    = (int)$_SESSION["_CONF"]["conf_taxes"];
               else
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["TasaImp"]    = 0;
                  
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["FchDoc"]     = date('Y-m-d', $tran["invc_date"]);
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["RUTDoc"]     = str_replace(".", "", $tran["supp_rut"]);
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["RznSoc"]     = $tran["supp_company"];
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["MntNeto"]    = (int)$tran_netto;
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["MntExe"]     = (int)$tran["invc_total_taxes_exclude"];
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["MntIVA"]     = (int)$tran["invc_total_taxes"];
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["MntTotal"]   = (int)$tran["invc_total_brutto"];

               //----------------------------------------------------------------------------------
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TotDoc"]++;
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TpoDoc"]          = $tran["typedoc_cont_code"];
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TotMntExe"]      += (int)$tran["invc_total_taxes_exclude"];
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TotMntNeto"]     += (int)$tran_netto;
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TotMntIVA"]      += (int)$tran["invc_total_taxes"];
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TotIVAProp"]      = 0;
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TotIVATerc"]      = 0;
               $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TotMntTotal"]    += (int)$tran["invc_total_brutto"];
            }
            ?>
            <tr>
               <td class="content_row_totals" colspan="6">TOTAL FACTURAS</td>
               <td class="content_row_totals" align="right"><nobr><?=printPrice($ges_total_taxes_exclude, $numberlim)?><?=$moneystr?></nobr></td>
               <td class="content_row_totals" align="right"><nobr><?=printPrice($ges_total_netto, $numberlim)?><?=$moneystr?></nobr></td>
               <td class="content_row_totals" align="right"><nobr><?=printPrice($ges_total_taxes, $numberlim)?><?=$moneystr?></nobr></td>
               <td class="content_row_totals" align="right"><nobr><?=printPrice($ges_total_brutto, $numberlim)?><?=$moneystr?></nobr></td>
            </tr>
            </table>
            <?=Nifty_printF()?>
            <br>
            <?php
            //----------------------------------------------------------------------------------
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$factitle][$x]["gesnum"]                    = "<b>TOTAL</b>";
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$factitle][$x]["invc_total_taxes_exclude"]  = "<b>".printPrice($ges_total_taxes_exclude, $numberlim)."</b>";
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$factitle][$x]["tran_netto"]                = "<b>".printPrice($ges_total_netto, $numberlim)."</b>";
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$factitle][$x]["invc_total_taxes"]          = "<b>".printPrice($ges_total_taxes, $numberlim)."</b>";
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$factitle][$x]["invc_total_brutto"]         = "<b>".printPrice($ges_total_brutto, $numberlim)."</b>";

            $res_total_taxes_exclude   += $ges_total_taxes_exclude;
            $res_total_netto           += $ges_total_netto;
            $res_total_taxes           += $ges_total_taxes;
            $res_total_brutto          += $ges_total_brutto;

            $_TOTAL["INVC"][$factitle]["res_total_taxes_exclude"]   += $ges_total_taxes_exclude;
            $_TOTAL["INVC"][$factitle]["res_total_netto"]           += $ges_total_netto;
            $_TOTAL["INVC"][$factitle]["res_total_taxes"]           += $ges_total_taxes;
            $_TOTAL["INVC"][$factitle]["res_total_brutto"]          += $ges_total_brutto;
         }
      }
      ?>
   </td>
</tr>
<?php
for($xnotetype = 1; $xnotetype <=2; $xnotetype++)
{
   if($xnotetype == 1)
   {
      $sectitle = "NOTAS DE CREDITO";
      $secinit  = "MENOS";
      $prcsign  = "-";
   }
   else
   {
      $sectitle = "NOTAS DE DEBITO";
      $secinit  = "MAS";
      $prcsign  = "";
   }

   $trans = $resnotes[$xnotetype];


   if(count($trans) && $trans != false)
   {
      foreach(array_keys($trans) AS $tdcc)
      {
         if((int)$tdcc)
            $notatitle = getTypeDocContName($tdcc);
         else
            $notatitle = "NO TIPO NOTA ASIGNADO";
         $data = $trans[$tdcc];
         ?>
         <tr>
            <td class="content_row_clear">
               <?=Nifty_printH("box1", "1200")?>
               <table border="0" cellpadding="3" cellspacing="0" width="100%">
               <colgroup>
                  <col width="90">
                  <col width="90">
                  <col width="150">
                  <col width="90">
                  <col>
                  <col width="85">
                  <col width="85">
                  <col width="85">
                  <col width="85">
                  <col width="85">
               </colgroup>
               <tr>
                  <td class="content_tbl_header" colspan="10"><img src="./images/menu/icons/documents.png" style="vertical-align:bottom">&nbsp;&nbsp;<?=$notatitle?></td>
               </tr>
               <tr>
                  <td class="content_tbl_subheader content_row_os">Nº Correlativo</td>
                  <td class="content_tbl_subheader content_row_os">Nº Docto.</td>
                  <td class="content_tbl_subheader content_row_os">Cod. Cont.</td>
                  <td class="content_tbl_subheader content_row_os">Fecha</td>
                  <td class="content_tbl_subheader content_row_os">Proveedor</td>
                  <td class="content_tbl_subheader content_row_os">RUT</td>
                  <td class="content_tbl_subheader content_row_os" align="right">Exento</td>
                  <td class="content_tbl_subheader content_row_os" align="right">Neto</td>
                  <td class="content_tbl_subheader content_row_os" align="right">IVA</td>
                  <td class="content_tbl_subheader content_row_os" align="right">Bruto</td>
               </tr>
               <?php
               $ges_total_taxes_exclude   = 0.00;
               $ges_total_netto           = 0.00;
               $ges_total_taxes           = 0.00;
               $ges_total_brutto          = 0.00;
               
               //----------------------------------------------------------------------------------
               for($x = 0; $x < count($data) && $data != false; $x++)
               {
                  $tran       = $data[$x];
                  $tran_netto = $tran["invc_total_netto"] - $tran["invc_total_taxes_exclude"];
                  $gesnum     = ++$_NNUMS[$xnotetype."_".date('m/Y', $tran["invc_date"])];
                  $gesnum     = sprintf("%02s", $gesnum).'/'.date('m',$tran["invc_date"]);

                  //----------------------------------------------------------------------------------
                  if((int)$tran["invc_importation"] && $_SESSION[$_sesmodulename]["sql_money"] == "CLP")
                  {
                     $numberlim = 0;
                     $moneystr  = "";
                     $tran["invc_total_taxes_exclude"]   = $tran["invc_import_total"];
                     $tran["invc_total_brutto"]          = $tran["invc_import_total"];
                  }
                  elseif((int)$tran["invc_importation"] && $_SESSION[$_sesmodulename]["sql_money"] == "USD")
                  {
                     $numberlim = 2;
                     $moneystr  = "";
                  }
                  elseif(!(int)$tran["invc_importation"])
                  {
                     $numberlim = 0;
                     $moneystr  = "";
                  }

                  //----------------------------------------------------------------------------------
                  ?>
                  <tr bgcolor="<?=getRowColor($x +1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                     <td class="content_row_os"><?=$tran["invc_intnumber"]?>&nbsp;</td>
                     <td class="content_row_os"><?=$tran["invc_docnumber"]?></td>
                     <td class="content_row_os"><?=$tran["cc_code"]." ".$tran["cc_title"]?>&nbsp;</td>
                     <td class="content_row_os"><?=date('d.m.Y', $tran["invc_date"])?></td>
                     <td class="content_row_os"><?=$tran["supp_company"]?>&nbsp;</td>
                     <td class="content_row_os"><?=$tran["supp_rut"]?>&nbsp;</td>
                     <td class="content_row_os" align="right"><nobr><?=$prcsign?><?=printPrice($tran["invc_total_taxes_exclude"], $numberlim)?><?=$moneystr?></nobr></td>
                     <td class="content_row_os" align="right"><nobr><?=$prcsign?><?=printPrice($tran_netto, $numberlim)?><?=$moneystr?></nobr></td>
                     <td class="content_row_os" align="right"><nobr><?=$prcsign?><?=printPrice($tran["invc_total_taxes"], $numberlim)?><?=$moneystr?></nobr></td>
                     <td class="content_row_os" align="right"><nobr><?=$prcsign?><?=printPrice($tran["invc_total_brutto"], $numberlim)?><?=$moneystr?></nobr></td>
                  </tr>
                  <?php
                  //----------------------------------------------------------------------------------
                  $ges_total_taxes_exclude   += $tran["invc_total_taxes_exclude"];
                  $ges_total_netto           += $tran_netto;
                  $ges_total_taxes           += $tran["invc_total_taxes"];
                  $ges_total_brutto          += $tran["invc_total_brutto"];

                  //----------------------------------------------------------------------------------
                  $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]                              = $tran;
                  $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]["sectitle"]                  = $sectitle;
                  $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]["tran_type"]                 = $tran_type;
                  $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]["gesnum"]                    = $tran["invc_intnumber"];
                  $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]["cc_code"]                   = $tran["cc_code"]." ".$tran["cc_title"];
                  $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]["invc_date"]                 = date('d.m.Y', $tran["invc_date"]);
                  $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]["invc_total_taxes_exclude"]  = printPrice($prcsign.$tran["invc_total_taxes_exclude"], $numberlim);
                  $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]["tran_netto"]                = printPrice($prcsign.$tran_netto, $numberlim);
                  $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]["invc_total_taxes"]          = printPrice($prcsign.$tran["invc_total_taxes"], $numberlim);
                  $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]["invc_total_brutto"]         = printPrice($prcsign.$tran["invc_total_brutto"], $numberlim);

                  //----------------------------------------------------------------------------------
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["TpoDoc"]     = $tran["typedoc_cont_code"];
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["NroDoc"]     = $tran["invc_docnumber"];

                  if($tran["invc_total_taxes"] > 0.00)
                     $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["TasaImp"]    = (int)$_SESSION["_CONF"]["conf_taxes"];
                  else
                     $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["TasaImp"]    = 0;
                  
                  //$_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["TasaImp"]    = (int)$tran["item_costprice_taxes_perc"];
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["FchDoc"]     = date('Y-m-d', $tran["invc_date"]);
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["RUTDoc"]     = str_replace(".", "", $tran["supp_rut"]);
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["RznSoc"]     = $tran["supp_company"];
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["MntNeto"]    = (int)$tran_netto;
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["MntExe"]     = (int)$tran["invc_total_taxes_exclude"];
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["MntIVA"]     = (int)$tran["invc_total_taxes"];
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_DETAILS"][$tdcc][$x]["MntTotal"]   = (int)$tran["invc_total_brutto"];

                  //----------------------------------------------------------------------------------
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TotDoc"]++;
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TpoDoc"]          = $tran["typedoc_cont_code"];
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TotMntExe"]      += (int)$tran["invc_total_taxes_exclude"];
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TotMntNeto"]     += (int)$tran_netto;
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TotMntIVA"]      += (int)$tran["invc_total_taxes"];
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TotIVAProp"]      = 0;
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TotIVATerc"]      = 0;
                  $_SESSION["STATS"][$_sesmodulename]["_SYNC_TOTALS"][$tdcc]["TotMntTotal"]    += (int)$tran["invc_total_brutto"];
               }

               //----------------------------------------------------------------------------------
               if($xnotetype == 1)
               {
                  $res_total_taxes_exclude   -= $ges_total_taxes_exclude;
                  $res_total_netto           -= $ges_total_netto;
                  $res_total_taxes           -= $ges_total_taxes;
                  $res_total_brutto          -= $ges_total_brutto;
               }
               else
               {
                  $res_total_taxes_exclude   += $ges_total_taxes_exclude;
                  $res_total_netto           += $ges_total_netto;
                  $res_total_taxes           += $ges_total_taxes;
                  $res_total_brutto          += $ges_total_brutto;
               }
               //----------------------------------------------------------------------------------
               $_TOTAL["NOTE"][$xnotetype][$notatitle]["res_total_taxes_exclude"]   = $ges_total_taxes_exclude;
               $_TOTAL["NOTE"][$xnotetype][$notatitle]["res_total_netto"]           = $ges_total_netto;
               $_TOTAL["NOTE"][$xnotetype][$notatitle]["res_total_taxes"]           = $ges_total_taxes;
               $_TOTAL["NOTE"][$xnotetype][$notatitle]["res_total_brutto"]          = $ges_total_brutto;

               //----------------------------------------------------------------------------------
               ?>
               <tr>
                  <td class="content_row_totals" colspan="6">TOTAL <?=$sectitle?></td>
                  <td class="content_row_totals" align="right"><nobr><?=$prcsign?><?=printPrice($ges_total_taxes_exclude, $numberlim)?><?=$moneystr?></nobr></td>
                  <td class="content_row_totals" align="right"><nobr><?=$prcsign?><?=printPrice($ges_total_netto, $numberlim)?><?=$moneystr?></nobr></td>
                  <td class="content_row_totals" align="right"><nobr><?=$prcsign?><?=printPrice($ges_total_taxes, $numberlim)?><?=$moneystr?></nobr></td>
                  <td class="content_row_totals" align="right"><nobr><?=$prcsign?><?=printPrice($ges_total_brutto, $numberlim)?><?=$moneystr?></nobr></td>
               </tr>
               </table>
               <?=Nifty_printF()?>
               <br>
               <?php
               //----------------------------------------------------------------------------------
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]["gesnum"]                    = "<b>TOTAL</b>";
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]["invc_total_taxes_exclude"]  = "<b>".printPrice($prcsign.$ges_total_taxes_exclude, $numberlim)."</b>";
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]["tran_netto"]                = "<b>".printPrice($prcsign.$ges_total_netto, $numberlim)."</b>";
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]["invc_total_taxes"]          = "<b>".printPrice($prcsign.$ges_total_taxes, $numberlim)."</b>";
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$notatitle][$x]["invc_total_brutto"]         = "<b>".printPrice($prcsign.$ges_total_brutto, $numberlim)."</b>";
               ?>
            </td>
         </tr>
         <?php
      }
   }
}
?>
<tr>
   <td>
      <?=Nifty_printH("box2", "1200")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col width="85">
         <col width="85">
         <col width="85">
         <col width="85">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="5"><img src="./images/menu/icons/balance.png" style="vertical-align:bottom">&nbsp;&nbsp;TOTALES</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader content_row_os">&nbsp;</td>
         <td class="content_tbl_subheader content_row_os" align="right">Extento</td>
         <td class="content_tbl_subheader content_row_os" align="right">Neto</td>
         <td class="content_tbl_subheader content_row_os" align="right">IVA</td>
         <td class="content_tbl_subheader content_row_os" align="right">Total</td>
      </tr>
      <tr>
         <td class="content_row_totals" style="border-top:0px;border-bottom-style:solid;border-bottom-color:#BA8637;border-bottom-width:2px;">TOTAL GENERAL</td>
         <td class="content_row_totals" align="right" style="border-top:0px;border-bottom-style:solid;border-bottom-color:#BA8637;border-bottom-width:2px;"><nobr><?=printPrice($res_total_taxes_exclude, $numberlim)?><?=$moneystr?></nobr></td>
         <td class="content_row_totals" align="right" style="border-top:0px;border-bottom-style:solid;border-bottom-color:#BA8637;border-bottom-width:2px;"><nobr><?=printPrice($res_total_netto, $numberlim)?><?=$moneystr?></nobr></td>
         <td class="content_row_totals" align="right" style="border-top:0px;border-bottom-style:solid;border-bottom-color:#BA8637;border-bottom-width:2px;"><nobr><?=printPrice($res_total_taxes, $numberlim)?><?=$moneystr?></nobr></td>
         <td class="content_row_totals" align="right" style="border-top:0px;border-bottom-style:solid;border-bottom-color:#BA8637;border-bottom-width:2px;"><nobr><?=printPrice($res_total_brutto, $numberlim)?><?=$moneystr?></nobr></td>
      </tr>
      <?php
      foreach(array_keys($_TOTAL["INVC"]) AS $factitle)
      {  ?>
         <tr>
            <td class="content_row_os"><?=$factitle?></td>
            <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["INVC"][$factitle]["res_total_taxes_exclude"], $numberlim)?><?=$moneystr?></nobr></td>
            <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["INVC"][$factitle]["res_total_netto"], $numberlim)?><?=$moneystr?></nobr></td>
            <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["INVC"][$factitle]["res_total_taxes"], $numberlim)?><?=$moneystr?></nobr></td>
            <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["INVC"][$factitle]["res_total_brutto"], $numberlim)?><?=$moneystr?></nobr></td>
         </tr>
         <?php
      }
      for($xnotetype = 1; $xnotetype <=2; $xnotetype++)
      {
         foreach(array_keys($_TOTAL["NOTE"][$xnotetype]) AS $notatitle)
         {  ?>
            <tr>
               <td class="content_row_os"><?=$notatitle?></td>
               <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["NOTE"][$xnotetype][$notatitle]["res_total_taxes_exclude"], $numberlim)?><?=$moneystr?></nobr></td>
               <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["NOTE"][$xnotetype][$notatitle]["res_total_netto"], $numberlim)?><?=$moneystr?></nobr></td>
               <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["NOTE"][$xnotetype][$notatitle]["res_total_taxes"], $numberlim)?><?=$moneystr?></nobr></td>
               <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["NOTE"][$xnotetype][$notatitle]["res_total_brutto"], $numberlim)?><?=$moneystr?></nobr></td>
            </tr>
            <?php
         }
      }
      ?>
      <tr>
         <td class="content_row_totals">TOTAL GENERAL</td>
         <td class="content_row_totals" align="right"><nobr><?=printPrice($res_total_taxes_exclude, $numberlim)?><?=$moneystr?></nobr></td>
         <td class="content_row_totals" align="right"><nobr><?=printPrice($res_total_netto, $numberlim)?><?=$moneystr?></nobr></td>
         <td class="content_row_totals" align="right"><nobr><?=printPrice($res_total_taxes, $numberlim)?><?=$moneystr?></nobr></td>
         <td class="content_row_totals" align="right"><nobr><?=printPrice($res_total_brutto, $numberlim)?><?=$moneystr?></nobr></td>
      </tr>
      </table>
      <?=Nifty_printF()?>
      <br>
   </td>
</tr>
</table>
<?php
$_SESSION["STATS"][$_sesmodulename]["TOTAL"]["numberlim"]   = $numberlim;
$_SESSION["STATS"][$_sesmodulename]["TOTAL"]["INVC"]        = $_TOTAL["INVC"];
$_SESSION["STATS"][$_sesmodulename]["TOTAL"]["NOTE"]        = $_TOTAL["NOTE"];
$_SESSION["STATS"][$_sesmodulename]["GENERAL"]["res_total_taxes_exclude"]  = $res_total_taxes_exclude;
$_SESSION["STATS"][$_sesmodulename]["GENERAL"]["res_total_netto"]          = $res_total_netto;
$_SESSION["STATS"][$_sesmodulename]["GENERAL"]["res_total_taxes"]          = $res_total_taxes;
$_SESSION["STATS"][$_sesmodulename]["GENERAL"]["res_total_brutto"]         = $res_total_brutto;

//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsBooksBuying($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsBooksBuying($CON);

if($pdffile != "")
{
   $doctitle = "Libro-de-compra-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Libro-de-compra-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php