<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "buy_discounts";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Fecha"               => 2);

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
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
}

//----------------------------------------------------------------------------------
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;

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
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 100);

//----------------------------------------------------------------------------------
$datsql = " select t1.id, t1.invc_date, t1.invc_docnumber, t6.supp_short, t6.supp_rut,
                    t1.invc_supplier_id, t1.invc_total_netto_dsc
            from invoices_buy t1
            INNER JOIN company_data t4    ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN supplier t6   ON ( t1.invc_supplier_id = t6.id )
            where
            t1.invc_status             > 1 and
            t1.invc_status             < 4 and
            t1.invc_date               between {$sql_datefrom} and {$sql_dateto} and
            t6.supp_dsc_finance_calc   = 'NC' and
            t6.supp_dsc_finance        > 0.00 ";
         
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.invc_shop_id   = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   $datsql .= " and t1.invc_supplier_id  = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
   
//----------------------------------------------------------------------------------
$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}";
$trans = $CON->select($datsql);

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON);
$shops      = getShops($CON);

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
   
//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
printJSsetCompanyShop($shops);
?>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Productos / Facturas con descuento financiero</b></td>
   <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults(count($trans)); else echo $savemsg;?></td>
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
                  if(count($trans) > 0 && $trans != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($trans) > 0 && $trans != false)
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
   <td class="content_row_clear">
      <?php

      //----------------------------------------------------------------------------------
      $px = 0;
      for($x = 0; $x < count($trans) && $trans != false; $x++)
      {
         $tran = $trans[$x];

         //----------------------------------------------------------------------------------
         $rows       = Array();
         $invcparts  = getInvoiceBuyParts($CON, $tran["id"]);
         for($y = 0; $y < count($invcparts) && $invcparts != false; $y++)
         {
            $part       = $invcparts[$y];
            $posdata    = getInvoiceBuyPartsItems($CON, $tran["id"], $part["id"]);
            for($z = 0; $z < count($posdata) && $posdata != false; $z++)
            {
               $rows[] = $posdata[$z];
            }
         }

         //----------------------------------------------------------------------------------
         $baseinvcprc = 0.00;
         $gesnoteaval = 0.00;
         $gesinvcval  = 0.00;
         $gesnumbers  = "";

         unset($_NOTES);
         unset($_NUMBE);
         $sql = " select distinct id, note_total_netto, note_docnumber
                  from invoices_notes_buy
                  where
                  note_is_discount     = 1 and
                  note_parent_invcid   = {$tran["id"]} and
                  note_status          > 1
                  UNION ALL
                  select distinct t1.id, t1.note_total_netto, note_docnumber
                  from invoices_notes_buy t1
                  INNER JOIN invoices_notes_buy_otherdscinvc t2 ON t1.id = t2.note_id
                  where
                  t1.note_is_discount     = 1 and
                  t1.note_status          > 1 and
                  t2.note_parent_invcid   = {$tran["id"]}";
         $notes = $CON->select($sql);
         foreach($notes AS $note)
         {
            $_NOTES[$note["id"]] = $note["note_total_netto"];
            $_NUMBE[$note["id"]] = $note["note_docnumber"];
         }

         //----------------------------------------------------------------------------------
         foreach(array_keys($_NOTES) AS $noteid)
         {
            $sql = " select *
                     from invoices_notes_buy_otherdscinvc
                     where
                     note_id = {$note["id"]}";
            $othersinvcs = $CON->select($sql);
            if(count($othersinvcs) && $othersinvcs != false)
            {
               $sql = " select note_parent_invcid
                        from invoices_notes_buy
                        where
                        id = {$noteid}";
               $note_parent_invcid = $CON->select($sql);
               $note_parent_invcid = (int)$note_parent_invcid[0]["note_parent_invcid"];
               
               $sql = " select invc_total_netto_dsc
                        from invoices_buy
                        where
                        id = {$note_parent_invcid}";
               $invc_total_netto_dsc = $CON->select($sql);
               $invc_total_netto_dsc = (float)$invc_total_netto_dsc[0]["invc_total_netto_dsc"];
               
               $baseinvcprc = $invc_total_netto_dsc;
               foreach($othersinvcs AS $othersinvc)
                  $baseinvcprc += $othersinvc["note_parent_invcamt"];
            }
            else
               $baseinvcprc = $tran["invc_total_netto_dsc"];

            $gesnoteaval += $_NOTES[$noteid];
            $gesinvcval  += $baseinvcprc;
            $gesnumbers   = $gesnumbers.$_NUMBE[$noteid].",";
         }
         $gesnumbers = substr($gesnumbers, 0, -1);
         $dscpercent = round($gesnoteaval / $gesinvcval * 100,2);
         ?>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="65">
            <col>
            <col width="90">
            <col width="70">
            <col width="70">
            <col width="70">
            <col width="60">
            <col width="60">
            <col width="60">
            <col width="40">
            <col width="60">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="11"><?=$tran["supp_short"]?>, Factura <?=$tran["invc_docnumber"]?>, <?=date('d.m.Y', $tran["invc_date"])?></td>
         <tr>
            <td class="content_tbl_subheader content_row_os">Número</td>
            <td class="content_tbl_subheader content_row_os">Artículo</td>
            <td class="content_tbl_subheader content_row_os">Cod/Prov</td>
            <td class="content_tbl_subheader content_row_os">Unidad</td>
            <td class="content_tbl_subheader content_row_os" align="right">Cantidad</td>
            <td class="content_tbl_subheader content_row_os" align="right">Precio<br>Factura<br>Basico</td>
            <td class="content_tbl_subheader content_row_os" align="right">Precio<br>Factura<br>Final</td>
            <td class="content_tbl_subheader content_row_os">Nota de Credito</td>
            <td class="content_tbl_subheader content_row_os" align="right">Desc.<br>$</td>
            <td class="content_tbl_subheader content_row_os" align="right">Desc.<br>%</td>
            <td class="content_tbl_subheader content_row_os" align="right">Precio<br>Final</td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA1"][$x]["val1"] = "{$tran["supp_short"]}, Factura {$tran["invc_docnumber"]}, ".date('d.m.Y', $tran["invc_date"]);
         $zzz = 0;
         foreach($rows AS $row)
         {
            $unitdesc = getItemUnitDesc($CON, $row["item_id"], $row["item_type"]);
            $dscvalue = round($row["item_costprice_netto_dsc2"] / 100 * $dscpercent,0);
            $prcfinal = round($row["item_costprice_netto_dsc2"] - $dscvalue,0);
            ?>
            <tr bgcolor="<?=getRowColor($zzz)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><?=$row["item_number_prod"]?></td>
               <td class="content_row_os"><?=$row["item_title"]?></td>
               <td class="content_row_os"><?=$row["item_code"]?>&nbsp;</td>
               <td class="content_row_os"><?=$unitdesc?>&nbsp;</td>
               <td class="content_row_os" align="right"><?=printPrice($row["item_amount"],2)?></td>
               <td class="content_row_os" align="right"><?=printPrice($row["item_costprice_netto"] * $row["item_amount"],2)?></td>
               <td class="content_row_os" align="right"><?=printPrice($row["item_costprice_netto_dsc2"],2)?></td>
               <td class="content_row_os"><?=$gesnumbers?>&nbsp;</td>
               <td class="content_row_os" align="right"><?=printPrice($dscvalue,0)?></td>
               <td class="content_row_os" align="right"><?=printPrice($dscpercent,2)?></td>
               <td class="content_row_os" align="right"><?=printPrice($prcfinal,0)?></td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x][$zzz]["val1"]  = $row["item_number_prod"];
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x][$zzz]["val2"]  = $row["item_title"];
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x][$zzz]["val3"]  = $row["item_code"];
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x][$zzz]["val4"]  = $unitdesc;
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x][$zzz]["val5"]  = printPrice($row["item_amount"],2);
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x][$zzz]["val6"]  = printPrice($row["item_costprice_netto"] * $row["item_amount"],2);
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x][$zzz]["val7"]  = printPrice($item["item_costprice_netto_dsc2"],2);
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x][$zzz]["val8"]  = $gesnumbers;
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x][$zzz]["val9"]  = printPrice($dscvalue,0);
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x][$zzz]["val10"] = printPrice($dscpercent,2);
            $_SESSION["STATS"][$_sesmodulename]["DATA2"][$x][$zzz]["val11"] = printPrice($prcfinal,0);
            $zzz++;
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?php
      }
      ?>
      <br>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsBuyNoteDiscounts($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsBuyNoteDiscounts($CON);

if($pdffile != "")
{
   $doctitle = "Productos-Facturas-Con-Descuento-Financiero-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Productos-Facturas-Con-Descuento-Financiero-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>