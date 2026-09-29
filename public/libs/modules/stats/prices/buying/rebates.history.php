<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "rebates_hist";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array();

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
   $_SESSION[$_sesmodulename]["sql_year1"]     = (int)$_REQUEST["sql_year1"];
   $_SESSION[$_sesmodulename]["sql_format"]    = (int)$_REQUEST["sql_format"];
   $_SESSION[$_sesmodulename]["sql_supplier"]      = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}


//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON, true);
$shops      = getShops($CON);

//----------------------------------------------------------------------------------
if(!(int)$_SESSION[$_sesmodulename]["sql_year1"])
   $_SESSION[$_sesmodulename]["sql_year1"]= (int)date('Y');

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];


//----------------------------------------------------------------------------------

printJSsetCompanyShop($shops);

$sql = " select t1.*, t2.supp_short, t3.*
         from supplier_marketing_head t1
         INNER JOIN supplier t2           ON t1.supp_id = t2.id
         INNER JOIN rebates_invoices t3   ON t1.id = t3.head_id
         where
         t1.mark_status = 1 and
         t2.supp_status = 1 and
         t1.mark_year   = {$_SESSION[$_sesmodulename]["sql_year1"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   $sql .= " and t1.supp_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
$sql .= " order by t2.supp_short";
$rebates = $CON->select($sql);

//----------------------------------------------------------------------------------
?>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Historia de Rebates</b></td>
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
      onsubmit="return checkform(new Array(this.sql_company))">
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
         <td class="content_rowl">Proveedor</td>
         <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Año</td>
         <td class="content_row">
            <select class="text" name="sql_year1" id="sql_year1"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <?php
               $startyear  = date('Y') -5;
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
                  if($_REQUEST["subexec"] == "search")
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if($_REQUEST["subexec"] == "search")
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
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="9">Historia</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader content_row_os">Proveedor</td>
         <td class="content_tbl_subheader content_row_os">Nombre Rebate</td>
         <td class="content_tbl_subheader content_row_os">Tipo</td>
         <td class="content_tbl_subheader content_row_os">Mes</td>
         <td class="content_tbl_subheader content_row_os">Año</td>
         <td class="content_tbl_subheader content_row_os">Rebate Calculado</td>
         <td class="content_tbl_subheader content_row_os">Factura</td>
         <td class="content_tbl_subheader content_row_os">Fecha Factura</td>
         <td class="content_tbl_subheader content_row_os">Monto Factura</td>
      </tr>
      <?php
      $x = 0;
      foreach($rebates AS $rebate)
      {
         if($rebate["mark_type"] == "SIMPLE")
            $type = "Porcentaje";
         else
            $type = "Meta";

         $sql = " select invc_date, invc_total_netto
                  from invoices_sell
                  where
                  invc_docnumber = '{$rebate["rebate_invc"]}' and
                  invc_status    > 1 and
                  invc_status    < 4";
         $relinvc = $CON->select($sql);
         $relinvc = $relinvc[0];
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$rebate["supp_short"]?></td>
            <td class="content_row_os"><?=$rebate["mark_name"]?></td>
            <td class="content_row_os"><?=$type?></td>
            <td class="content_row_os"><?=sprintf("%02s", $rebate["month"])?></td>
            <td class="content_row_os"><?=$rebate["mark_year"]?></td>
            <td class="content_row_os"><?=printPrice($rebate["rebate_value"])?></td>
            <td class="content_row_os"><?=$rebate["rebate_invc"]?></td>
            <td class="content_row_os"><?php if((int)$relinvc["invc_date"]) echo date('d.m.Y', $relinvc["invc_date"]); else echo "No encontrado"?></td>
            <td class="content_row_os"><?php if((int)$relinvc["invc_date"]) echo printPrice($relinvc["invc_total_netto"]); else echo "No encontrado"?></td>
         <tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["supp_short"]     = $rebate["supp_short"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["mark_name"]      = $rebate["mark_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["type"]           = $type;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["month"]          = sprintf("%02s", $rebate["month"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["mark_year"]      = $rebate["mark_year"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["rebate_value"]   = printPrice($rebate["rebate_value"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["rebate_invc"]    = $rebate["rebate_invc"];

         if((int)$relinvc["invc_date"])
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_date"] = date('d.m.Y', $relinvc["invc_date"]);
         else
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_date"] = "No encontrado";

         if((int)$relinvc["invc_date"])
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_netto"] = printPrice($relinvc["invc_total_netto"]);
         else
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_netto"] = "No encontrado";
         $x++;
      }
      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="9" align="center">
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
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsRebatesHistory($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsRebatesHistory($CON);

if($pdffile != "")
{
   $doctitle = "Rebates-Historia-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Rebates-Historia-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
