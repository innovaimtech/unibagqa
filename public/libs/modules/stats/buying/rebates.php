<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "rebates";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo" => "2", "Unidad" => "3", "Número OC" => "4",
                                "Proveedor" => "5", "Fecha" => "6", "Pendiente" => "7", "Solicitado" => "10",
                                "Recibido" => "11");

$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_headid"]    = (int)$_REQUEST["sql_headid"];
   $_SESSION[$_sesmodulename]["sql_month1"]    = (int)$_REQUEST["sql_month1"];
   $_SESSION[$_sesmodulename]["sql_format"]    = (int)$_REQUEST["sql_format"];
   $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",str_replace(".","",$_REQUEST["sql_stext"]))));
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON, true);
$shops      = getShops($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];

//----------------------------------------------------------------------------------
if($items != false && count($items))
   $itemcount = count($items);
else
   $itemcount = 0;
//----------------------------------------------------------------------------------

printJSsetCompanyShop($shops);

$sql = " select t1.*, t2.supp_short
         from supplier_marketing_head t1
         INNER JOIN supplier t2 ON t1.supp_id = t2.id
         where
         t1.mark_status = 1 and
         t2.supp_status = 1
         order by t2.supp_short";
$rebates = $CON->select($sql);

//----------------------------------------------------------------------------------
if((int)$_REQUEST["saveinvc"])
{
   $_REQUEST["rebate_value"]  = (float)$_REQUEST["rebate_value"];
   $_REQUEST["rebate_invc"]   = trim(addslashes($_REQUEST["rebate_invc"]));
   
   $sql = " delete from rebates_invoices
            where
            head_id  = {$_SESSION[$_sesmodulename]["sql_headid"]} and
            month    = {$_SESSION[$_sesmodulename]["sql_month1"]}";
   $CON->no_result($sql);

   if($_REQUEST["rebate_invc"] != "")
   {
      $sql = " insert into rebates_invoices
               (head_id, month, rebate_value, rebate_invc)
               VALUES
               ({$_SESSION[$_sesmodulename]["sql_headid"]},
                {$_SESSION[$_sesmodulename]["sql_month1"]},
                {$_REQUEST["rebate_value"]}, '{$_REQUEST["rebate_invc"]}')";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
$sql = " select *
         from rebates_invoices
         where
         head_id  = {$_SESSION[$_sesmodulename]["sql_headid"]} and
         month    = {$_SESSION[$_sesmodulename]["sql_month1"]}";
$invcdata = $CON->select($sql);
$invcdata = $invcdata[0];

//----------------------------------------------------------------------------------
?>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Rebates</b></td>
   <td align="right" class="content_row_clear">&nbsp;</td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst"
      onsubmit="return checkform(new Array(this.sql_headid, this.sql_company))">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <input type="hidden" name="saveinvc" value="0">
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
         <td class="content_rowl">Rebate</td>
         <td class="content_row">
            <select class="text" name="sql_headid" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($rebates AS $rebate)
               {
                  if($rebate["mark_type"] == "SIMPLE")
                     $type = "Porcentaje";
                  else
                     $type = "Meta";
                  ?>
                  <option value="<?=$rebate["id"]?>"
                  <?php if($rebate["id"] == $_SESSION[$_sesmodulename]["sql_headid"]) echo "selected"?>>
                     <?=$rebate["supp_short"]?> - <?=$rebate["mark_name"]?> - <?=$type?> - <?=$rebate["mark_year"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Mes inicial</td>
         <td class="content_row">
            <select class="text" name="sql_month1" id="sql_month1"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <?php
               for($x = 1; $x <= 12; $x++)
               {
                  ?>
                  <option value="<?=$x?>"
                  <?php if($x == $_SESSION[$_sesmodulename]["sql_month1"]) echo "selected" ?>><?=$_LANG["MODULE"]["CAL"][($x-1)]?></option>
                  <?php
               }
               ?>
            </select>
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
                  if($itemcount > 0)
                  {
                     //printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if($itemcount > 0)
                  {
                     //printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
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
   </td>
</tr>
<tr>
   <td>
      <?php
      if($_REQUEST["subexec"] == "search")
      {  ?>
         <table cellpadding="0" cellspacing="0" width="980" style="table-layout:fixed" border="0">
         <colgroup>
            <col width="450" valign="top">
            <col width="15">
            <col width="515">
         </colgroup>
         <tr>
            <td valign="top">
            <?php
            $sql = " select t1.*
                     from supplier_marketing_head t1
                     where
                     t1.id = {$_SESSION[$_sesmodulename]["sql_headid"]}";
            $data = $CON->select($sql);
            $data = $data[0];

            if($data["mark_type"] == "SIMPLE")
               $type = "Porcentaje";
            else
               $type = "Meta";
            ?>
            <?=Nifty_printH("box1", "450")?>
            <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="120">
               <col>
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="2">Configuración de rebate</td>
            </tr>
            <tr>
               <td class="content_rowl">Nombre</td>
               <td class="content_row"><?=$data["mark_name"]?></td>
            </tr>
            <tr>
               <td class="content_rowl">Tipo</td>
               <td class="content_row"><?=$type?></td>
            </tr>
            <tr>
               <td class="content_rowl">Periodo</td>
               <td class="content_row"><?=$data["mark_period"]?></td>
            </tr>
            <tr>
               <td class="content_rowl">Año</td>
               <td class="content_row"><?=$data["mark_year"]?></td>
            </tr>
            <?php
            if($data["mark_type"] == "SIMPLE")
            {  ?>
               <tr>
                  <td class="content_rowl">Porcentaje</td>
                  <td class="content_row"><?=printPrice($data["mark_perc"],4)?> %</td>
               </tr>
               <?php
            }
            ?>
            <tr>
               <td class="content_rowl">Notas de credito</td>
               <td class="content_row">
                  <?php
                  if((int)$data["mark_notes_check"])
                     echo "Descontar";
                  else
                     echo "Ignorar";
                  ?>
               </td>
            </tr>
            <tr>
               <td class="content_rowl">Número Factura</td>
               <td class="content_row">
                  <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
                  <tr>
                     <td>
                        <input type="text" class="text" name="rebate_invc" value="<?=$invcdata["rebate_invc"]?>"
                        onfocus="markfield(this,0)" onblur="markfield(this,1)">
                     </td>
                     <td align="right">
                        <?php
                        printButton("Guardar", "postnav_save", "javascript: deactivateFormChange()", "document.xform_itemsearch.saveinvc.value='1';submitForm(document.xform_itemsearch)", "disk-black", 130);
                        ?>
                     </td>
                  </tr>
                  </table>
               </td>
            </tr>
            </table>
            <?=Nifty_printF(false)?>
            <?php
            if($data["mark_type"] == "META")
            {
               $sql = " select *
                        from supplier_marketing_metas
                        where
                        dct_head_id  = {$data["id"]}
                        order by dct_pos asc";
               $volpos = $CON->select($sql);
               ?>
               <br>
               <?=Nifty_printH("box1", "450")?>
               <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
               <colgroup>
                  <col width="100">
                  <col width="110">
                  <col width="110">
                  <col>
               </colgroup>
               <tr>
                  <td class="content_tbl_header" colspan="5">Configuración rangos</td>
               </tr>
               <tr>
                  <td class="content_tbl_subheader">Rango</td>
                  <td class="content_tbl_subheader">De %</td>
                  <td class="content_tbl_subheader">Hasta %</td>
                  <td class="content_tbl_subheader">Rebate %</td>
               </tr>
               <?php
               for($x = 0; $x < 12; $x++)
               {
                  if((float)$volpos[$x]["dct_scale_amtfrom"] > 0.00 || (float)$volpos[$x]["dct_scale_amtto"] > 0.00)
                  {
                     $dsp_pricefrom    = printPrice($volpos[$x]["dct_scale_amtfrom"],2);
                     $dsp_priceto      = printPrice($volpos[$x]["dct_scale_amtto"],2);
                     $dsp_discount     = printPrice($volpos[$x]["dct_scale_discount"],2);
                     ?>
                     <tr bgcolor="<?=getRowColor($x)?>">
                        <td class="content_row">#<?=($x + 1)?></td>
                        <td class="content_row"><?=$dsp_pricefrom?></td>
                        <td class="content_row"><?=$dsp_priceto?></td>
                        <td class="content_row"><?=$dsp_discount?></td>
                     </tr>
                     <?php
                     $_RANGES[$x]["dsp_pricefrom"]   = $dsp_pricefrom;
                     $_RANGES[$x]["dsp_priceto"]     = $dsp_priceto;
                     $_RANGES[$x]["dsp_discount"]    = $dsp_discount;
                  }
               }
               ?>
               </table>
               <?=Nifty_printF(false)?>
               <?php
            }
            ?>
            <td class="content_row_clear">&nbsp;</td>
            <td valign="top">
               <?php
               $suppid     = $data["supp_id"];
               $init_year  = $data["mark_year"];
               $init_month = $_SESSION[$_sesmodulename]["sql_month1"];

               $sql_datefrom = mktime(0,0,0,$init_month,1,$init_year);

               switch($data["mark_period"])
               {
                  case "MENSUAL":      $steps = 1; break;
                  case "TRIMENSUAL":   $steps = 3; break;
                  case "SEMESTRAL":    $steps = 6; break;
                  case "ANUAL":        $steps = 12; break;
               }

               for($x = 0; $x < $steps; $x++)
               {
                  $_DATES[$init_year][$init_month] = 1;
                  $sql_dateto = mktime(23,59,59,$init_month,1,$init_year);
                  $sql_dateto = mktime(23,59,59,$init_month,date('t',$sql_dateto),$init_year);
                  
                  $init_month++;
                  if($init_month == 13)
                  {
                     $init_month = 1;
                     $init_year++;
                  }
               }
               $_TOT["INVC"] = Array();
               $_TOT["NOTE"] = Array();

               //----------------------------------------------------------------------------------
               $datsql = " select t1.id, t1.invc_date, t1.invc_number, t1.invc_docnumber,
                                  t1.invc_total_netto, t1.invc_total_taxes, t1.invc_total_taxes_exclude,
                                  t1.invc_total_brutto, t1.invc_import_total, t1.invc_exc_rate,
                                  t4.company_short, t6.supp_company, t6.supp_rut, t1.invc_importation,
                                  t1.invc_intnumber
                        from invoices_buy t1
                        INNER JOIN company_data t4    ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
                        LEFT OUTER JOIN supplier t6   ON ( t1.invc_supplier_id = t6.id )
                        where
                        t1.invc_status       > 1 and
                        t1.invc_status       < 4 and
                        t1.invc_date         between {$sql_datefrom} and {$sql_dateto} and
                        t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} and
                        t1.invc_supplier_id  = {$suppid}
                        order by t1.invc_date asc";
               $trans = $CON->select($datsql);
               foreach($trans AS $tran)
               {
                  $idx_month = (int)date('m', $tran["invc_date"]);
                  $idx_year = (int)date('Y', $tran["invc_date"]);
                  $_RES[$idx_year][$idx_month] += $tran["invc_total_netto"];

                  if(!is_array($_DOC[$idx_year][$idx_month]["INVC"]))
                     $_DOC[$idx_year][$idx_month]["INVC"] = Array();
                  $_DOC[$idx_year][$idx_month]["INVC"][] = $tran;
                  $_TOT["INVC"][] = $tran;
               }

               //----------------------------------------------------------------------------------
               $datsql = " select t1.id, t1.note_date 'invc_date', t1.note_number 'invc_number', t1.note_docnumber 'invc_docnumber',
                                  t1.note_total_netto 'invc_total_netto', t1.note_total_taxes 'invc_total_taxes',
                                  t1.note_total_taxes_exclude 'invc_total_taxes_exclude',
                                  t1.note_total_brutto 'invc_total_brutto', t1.note_import_total 'invc_import_total',
                                  t1.note_exc_rate 'invc_exc_rate', t4.company_short, t6.supp_company, t6.supp_rut,
                                  t1.note_type, t1.note_importation 'invc_importation', t1.note_intnumber 'invc_intnumber'
                           from invoices_notes_buy t1
                           INNER JOIN company_data t4    ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
                           LEFT OUTER JOIN supplier t6   ON ( t1.note_supplier_id = t6.id )
                           where
                           t1.note_status       > 1 and
                           t1.note_status       < 4 and
                           t1.note_date         between {$sql_datefrom} and {$sql_dateto} and
                           t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} and
                           t1.note_supplier_id  = {$suppid}
                           order by t1.note_date asc";
               $trans = $CON->select($datsql);
               foreach($trans AS $tran)
               {
                  $idx_month = (int)date('m', $tran["invc_date"]);
                  $idx_year = (int)date('Y', $tran["invc_date"]);
                  if($tran["note_type"] == 1)
                     $_NOT[$idx_year][$idx_month] -= $tran["invc_total_netto"];
                  else
                     $_NOT[$idx_year][$idx_month] += $tran["invc_total_netto"];

                  if(!is_array($_DOC[$idx_year][$idx_month]["NOTE"]))
                     $_DOC[$idx_year][$idx_month]["NOTE"] = Array();
                  $_DOC[$idx_year][$idx_month]["NOTE"][] = $tran;
                  $_TOT["NOTE"][] = $tran;
               }

               $_SESSION["_REBATES"]["DOCS"]    = $_DOC;
               $_SESSION["_REBATES"]["DATES"]   = $_DATES;
               $_SESSION["_REBATES"]["TOTAL"]   = $_TOT;

               if($data["mark_type"] == "SIMPLE")
               {  ?>
                  <?=Nifty_printH("box1", "515")?>
                  <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
                  <colgroup>
                     <col width="100">
                     <col width="100">
                     <col>
                     <col width="150">
                  </colgroup>
                  <tr>
                     <td class="content_tbl_header" colspan="4">Calculo factura</td>
                  </tr>
                  <tr>
                     <td class="content_tbl_subheader">Mes</td>
                     <td class="content_tbl_subheader">Año</td>
                     <td class="content_tbl_subheader">Compras</td>
                     <td class="content_tbl_subheader">Notas</td>
                  </tr>
                  <?php
                  foreach(array_keys($_DATES) AS $selyear)
                  {
                     foreach(array_keys($_DATES[$selyear]) AS $selmonth)
                     {  ?>
                        <tr>
                           <td class="content_rowl"><?=$_LANG["MODULE"]["CAL"][($selmonth-1)]?></td>
                           <td class="content_rowl"><?=$selyear?></td>
                           <td class="content_row">
                              <nobr>
                              <img src="./images/menu/icons/document-pdf.png" style="vertical-align:bottom;cursor:pointer"
                              onclick="showFancybox('/libs/modules/stats/buying/rebates.detail.fancy.php?type=INVC&month=<?=$selmonth?>&year=<?=$selyear?>', 'iframe', 650, 400, 'auto')">
                              &nbsp;
                              $ <?=printPrice($_RES[$selyear][$selmonth])?>
                              </nobr>
                           </td>
                           <td class="content_row">
                              <nobr>
                              <img src="./images/menu/icons/document-pdf.png" style="vertical-align:bottom;cursor:pointer"
                              onclick="showFancybox('/libs/modules/stats/buying/rebates.detail.fancy.php?type=NOTE&month=<?=$selmonth?>&year=<?=$selyear?>', 'iframe', 650, 400, 'auto')">
                              &nbsp;
                              $ <?=printPrice($_NOT[$selyear][$selmonth])?>
                              </nobr>
                           </td>
                        </tr>
                        <?php
                        $_TOTAL += round($_RES[$selyear][$selmonth],0);
                        $_NOTAL += round($_NOT[$selyear][$selmonth],0);
                     }
                  }
                  ?>
                  <tr>
                     <td class="content_row_totals">Subtotal</td>
                     <td class="content_row_totals">&nbsp;</td>
                     <td class="content_row_totals">
                        <nobr>
                        <img src="./images/menu/icons/document-pdf.png" style="vertical-align:bottom;cursor:pointer"
                        onclick="showFancybox('/libs/modules/stats/buying/rebates.detail.fancy.php?type=INVC_TOTAL, 'iframe', 650, 400, 'auto')">
                        &nbsp;
                        $ <?=printPrice($_TOTAL)?>
                        </nobr>
                     </td>
                     <td class="content_row_totals">
                        <nobr>
                        <img src="./images/menu/icons/document-pdf.png" style="vertical-align:bottom;cursor:pointer"
                        onclick="showFancybox('/libs/modules/stats/buying/rebates.detail.fancy.php?type=NOTE_TOTAL, 'iframe', 650, 400, 'auto')">
                        &nbsp;
                        $ <?=printPrice($_NOTAL)?>
                        </nobr>
                     </td>
                  </tr>
                  <?php
                  if((int)$data["mark_notes_check"])
                     $_TOTAL += $_NOTAL;
                  ?>
                  <tr>
                     <td class="content_row_totals">Total</td>
                     <td class="content_row_totals">&nbsp;</td>
                     <td class="content_row_totals">$ <?=printPrice($_TOTAL)?></td>
                     <td class="content_row_totals">&nbsp;</td>
                  </tr>
                  <tr>
                     <td class="content_row_totals">Factura</td>
                     <td class="content_row_totals">&nbsp;</td>
                     <td class="content_row_totals">$ <?=printPrice($_TOTAL/100*$data["mark_perc"],0)?></td>
                     <td class="content_row_totals">&nbsp;</td>
                  </tr>
                  </table>
                  <?=Nifty_printF(false)?>
                  <input type="hidden" name="rebate_value" value="<?=(float)round($_TOTAL/100*$data["mark_perc"],0)?>">
                  <?php
               }
               else
               {
                  $sql = " select *
                           from supplier_marketing_limits
                           where
                           dct_head_id  = {$data["id"]}
                           order by dct_month asc";
                  $lpos = $CON->select($sql);
                  foreach($lpos AS $lpo)
                     $_LIMITS[$lpo["dct_month"]] = $lpo["dct_scale_amt"];
                  ?>
                  <?=Nifty_printH("box1", "515")?>
                  <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
                  <colgroup>
                     <col width="100">
                     <col width="100">
                     <col>
                     <col width="100">
                     <col width="100">
                  </colgroup>
                  <tr>
                     <td class="content_tbl_header" colspan="5">Calculo factura</td>
                  </tr>
                  <tr>
                     <td class="content_tbl_subheader">Mes</td>
                     <td class="content_tbl_subheader">Año</td>
                     <td class="content_tbl_subheader">Meta</td>
                     <td class="content_tbl_subheader">Compras</td>
                     <td class="content_tbl_subheader">Notas</td>
                  </tr>
                  <?php
                  foreach(array_keys($_DATES) AS $selyear)
                  {
                     foreach(array_keys($_DATES[$selyear]) AS $selmonth)
                     {  ?>
                        <tr>
                           <td class="content_rowl"><?=$_LANG["MODULE"]["CAL"][($selmonth-1)]?></td>
                           <td class="content_rowl"><?=$selyear?></td>
                           <td class="content_row">$ <?=printPrice($_LIMITS[$selmonth])?></td>
                           <td class="content_row">
                              <nobr>
                              <img src="./images/menu/icons/document-pdf.png" style="vertical-align:bottom;cursor:pointer"
                              onclick="showFancybox('/libs/modules/stats/buying/rebates.detail.fancy.php?type=INVC&month=<?=$selmonth?>&year=<?=$selyear?>', 'iframe', 650, 400, 'auto')">
                              &nbsp;
                              $ <?=printPrice($_RES[$selyear][$selmonth])?>
                              </nobr>
                           </td>
                           <td class="content_row">
                              <nobr>
                              <img src="./images/menu/icons/document-pdf.png" style="vertical-align:bottom;cursor:pointer"
                              onclick="showFancybox('/libs/modules/stats/buying/rebates.detail.fancy.php?type=NOTE&month=<?=$selmonth?>&year=<?=$selyear?>', 'iframe', 650, 400, 'auto')">
                              &nbsp;
                              $ <?=printPrice($_NOT[$selyear][$selmonth])?>
                              </nobr>
                           </td>
                        </tr>
                        <?php
                        $_TOTAL += round($_RES[$selyear][$selmonth],0);
                        $_NOTAL += round($_NOT[$selyear][$selmonth],0);
                        $_LOTAL += round($_LIMITS[$selmonth],0);
                     }
                  }
                  ?>
                  <tr>
                     <td class="content_row_totals">Subtotal</td>
                     <td class="content_row_totals">&nbsp;</td>
                     <td class="content_row_totals">$ <?=printPrice($_LOTAL)?></td>
                     <td class="content_row_totals">
                        <nobr>
                        <img src="./images/menu/icons/document-pdf.png" style="vertical-align:bottom;cursor:pointer"
                        onclick="showFancybox('/libs/modules/stats/buying/rebates.detail.fancy.php?type=INVC_TOTAL', 'iframe', 650, 400, 'auto')">
                        &nbsp;
                        $ <?=printPrice($_TOTAL)?>
                        </nobr>
                     </td>
                     <td class="content_row_totals">
                        <nobr>
                        <img src="./images/menu/icons/document-pdf.png" style="vertical-align:bottom;cursor:pointer"
                        onclick="showFancybox('/libs/modules/stats/buying/rebates.detail.fancy.php?type=NOTE_TOTAL', 'iframe', 650, 400, 'auto')">
                        &nbsp;
                        $ <?=printPrice($_NOTAL)?>
                        </nobr>
                     </td>
                  </tr>
                  <?php
                  if((int)$data["mark_notes_check"])
                     $_TOTAL += $_NOTAL;

                  $_PERCAPPLY = 0.00;
                  $_PERCPOS  = 0;
                  $_CALCDIFF = $_TOTAL - $_LOTAL;
                  $_CALCPERC = round($_CALCDIFF / $_LOTAL * 100,2);

                  foreach(array_keys($_RANGES) AS $rpos)
                  {
                     if($_CALCPERC >= $_RANGES[$rpos]["dsp_pricefrom"] &&
                        $_CALCPERC <= $_RANGES[$rpos]["dsp_priceto"])
                     {
                        $_PERCAPPLY = $_RANGES[$rpos]["dsp_discount"];
                        $_PERCPOS   = $rpos+1;
                        $found = true;
                     }
                  }
                  if(!$found)
                     $_PERCPOS = "-";
                  ?>
                  <tr>
                     <td class="content_row_totals">Total</td>
                     <td class="content_row_totals">&nbsp;</td>
                     <td class="content_row_totals">&nbsp;</td>
                     <td class="content_row_totals">$ <?=printPrice($_TOTAL)?></td>
                     <td class="content_row_totals">&nbsp;</td>
                  </tr>
                  <tr>
                     <td class="content_row_totals" colspan="3">Alcanzado</td>
                     <td class="content_row_totals" colspan="2"><?=printPrice($_CALCPERC,2)?>%</td>
                  </tr>
                  <tr>
                     <td class="content_row_totals" colspan="3">Rango</td>
                     <td class="content_row_totals" colspan="2">#<?=$_PERCPOS?></td>
                  </tr>
                  <tr>
                     <td class="content_row_totals" colspan="2">Factura</td>
                     <td class="content_row_totals">&nbsp;</td>
                     <td class="content_row_totals">$ <?=printPrice($_TOTAL/100*$_PERCAPPLY,0)?></td>
                     <td class="content_row_totals">&nbsp;</td>
                  </tr>
                  </table>
                  <?=Nifty_printF(false)?>
                  <input type="hidden" name="rebate_value" value="<?=(float)round($_TOTAL/100*$_PERCAPPLY,0)?>">
                  <?php
                  
               }
               ?>
            </td>
         </tr>
         </table>
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
  $pdffile = doc_createStatsRebates($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsRebates($CON);

if($pdffile != "")
{
   $doctitle = "Rebates-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Rebates-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
</form>