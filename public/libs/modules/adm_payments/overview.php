<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "adm_payments";
$_sesbasefilterstatus   = "1";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "desc";
$_sortlinks             = Array("Proveedor" => "1", "Item" => "8", "Monto" => "4", "Fecha Documento" => "5", "Observaciones" => "10");

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
      $_SESSION[$_sesmodulename]["sql_items"]      = (int)$_REQUEST["sql_items"];
      $_SESSION[$_sesmodulename]["sql_shop"]       = (int)$_REQUEST["sql_shop"];
      $_SESSION[$_sesmodulename]["sql_customer"]   = (int)$_REQUEST["sql_customer"];
      $_SESSION[$_sesmodulename]["sql_stext"]      = (int)$_REQUEST["sql_stext"];
      $_SESSION[$_sesmodulename]["sql_selmode"]    = (int)$_REQUEST["sql_selmode"];
      $_SESSION[$_sesmodulename]["sql_dspmode"]    = (int)$_REQUEST["sql_dspmode"];
      $_SESSION[$_sesmodulename]["sql_month1"]     = trim($_REQUEST["sql_month1"]);
      $_SESSION[$_sesmodulename]["sql_month2"]     = trim($_REQUEST["sql_month2"]);
      $_SESSION[$_sesmodulename]["sql_year1"]      = trim($_REQUEST["sql_year1"]);
      $_SESSION[$_sesmodulename]["sql_year2"]      = trim($_REQUEST["sql_year2"]);
      $_SESSION[$_sesmodulename]["sql_date"]       = trim($_REQUEST["sql_date"]);
      $_SESSION[$_sesmodulename]["sql_date_pfrom"] = trim($_REQUEST["sql_date_pfrom"]);
      $_SESSION[$_sesmodulename]["sql_date_pto"]   = trim($_REQUEST["sql_date_pto"]);
      $_SESSION[$_sesmodulename]["page"]           = 0;
      $_SESSION[$_sesmodulename]["search_active"]  = 1;
   }

   //----------------------------------------------------------------------------------
   prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

   if($_SESSION[$_sesmodulename]["filter_status"] != 4)
      if(!is_array($_SESSION[$_sesmodulename]["sql_status"]))
         $_SESSION[$_sesmodulename]["sql_status"] = Array(0=>1);

   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "del")
   {
      $sql = " update adm_payment
               set
               adm_status = 0
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
   }

   //---------------------------------------------------------------------------------------------------------------------
   $companies  = getCompanies($CON);
   $shops      = getShops($CON);

   //---------------------------------------------------------------------------------------------------------------------
   if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
      $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
   if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
      $_SESSION[$_sesmodulename]["sql_selmode"] = 1;
   if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
      $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;

   //---------------------------------------------------------------------------------------------------------------------
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
   //---------------------------------------------------------------------------------------------------------------------
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
   $seasql = "";
   $joisql = "  LEFT OUTER JOIN company_data t2  ON t1.adm_company_id = t2.id
                LEFT OUTER JOIN company_shops t3 ON t1.adm_shop_id    = t3.id
                LEFT OUTER JOIN payments t8      ON t1.adm_payment_id = t8.id
                LEFT OUTER JOIN adm_payitems t9  ON t1.adm_account_id = t9.id
                ";

   $cntsql = " select count(distinct t1.id) 'cc'
               from adm_payment t1
               {$joisql}
               where
               t1.adm_status IN ({$_SESSION[$_sesmodulename]["filter_status"]}) ";

   $datsql = " select distinct t1.adm_supplier, t1.adm_payment_type, t1.adm_doc_number, t1.adm_amount, t1.adm_date, t1.adm_payment_status, t1.id,
               t9.item_name 'acc_name', t1.adm_payment_status, t1.adm_notes
               from adm_payment t1
               {$joisql}
               where
               t1.adm_status IN ({$_SESSION[$_sesmodulename]["filter_status"]}) ";

   $seasql .= " and t1.adm_date between {$sql_datefrom} and {$sql_dateto} ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t1.adm_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t1.adm_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
      $seasql .= " and t1.adm_amount = {$_SESSION[$_sesmodulename]["sql_stext"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_items"])
      $seasql .= " and t9.id = {$_SESSION[$_sesmodulename]["sql_items"]} ";


   //----------------------------------------------------------------------------------
   $cntsql   .= $seasql;
   $datsql   .= $seasql;
   $itemcount = $CON->select($cntsql);
   $itemcount = (int)$itemcount[0]["cc"];

   //----------------------------------------------------------------------------------
   $_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
   $repsql = $datsql;
   $datsql .= " LIMIT {$_SESSION[$_sesmodulename]["startrow"]}, {$_SESSION[$_sesmodulename]["rows_per_page"]}";

   //----------------------------------------------------------------------------------
   $headpayment = $CON->select($datsql);

   //----------------------------------------------------------------------------------
   if($_REQUEST["printpdf"])
     $pdffile = doc_createAdminPayments($CON, $repsql);

   if($_REQUEST["printxls"])
     $xlsfile = xls_createAdminPayments($CON, $repsql);

   $sql = " select *
            from adm_payitems
            where
            item_status > 0
            order by item_name";
   $accounts = $CON->select($sql);

   //----------------------------------------------------------------------------------
   ?>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <?php
   printJSsetCompanyShop($shops);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Resumen pagos</b></td>
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
         <input type="hidden" name="printpdf" value="0">
         <input type="hidden" name="printxls" value="0">
         <?=Nifty_printH("box2", "980")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="80">
            <col width="500">
            <col width="80">
            <col>
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Item</td>
            <td class="content_row">
               <select name="sql_items" class="text" style="width:375px">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($accounts AS $account)
                  {  ?>
                     <option value="<?=$account["id"]?>" <?if($account["id"] == $_SESSION[$_sesmodulename]["sql_items"]) echo "selected"?>>
                        <?=$account["item_name"]?>
                     </option>
                     <?php
                  }
                  ?>
               </select>
            </td>
            <td class="content_rowl">Monto</td>
            <td class="content_row">
               <input name="sql_stext" type="text" class="text" style="width:375px"
               value="<?if($_SESSION[$_sesmodulename]["sql_stext"] == "0") echo ""; else echo $_SESSION[$_sesmodulename]["sql_stext"];  ?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
         </tr>
         <tr>
            <td class="content_rowl">Empresa</td>
            <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
            <td class="content_rowl">Sucursal</td>
            <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
         </tr>
         <tr>
            <td class="content_rowl">Periodo</td>
            <td class="content_row" colspan="3">
               <table border="0" class="content_table" cellpadding="0" cellspacing="0">
               <tr>
                  <td class="content_row_clear" width="70">
                     <input type="radio" name="sql_selmode" value="2"
                     onclick="document.getElementById('idx_selmode1').style.display='none';
                              document.getElementById('idx_selmode2').style.display='';
                              document.getElementById('idx_selmode3').style.display='none';"
                     <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 2) echo "checked"?>> Meses
                  </td>
                  <td class="content_row_clear" width="185" id="idx_selmode2" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 2) echo "style='display:none'"?>>
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
         </tr>
         <tr>
            <td class="content_row" align="right" colspan="4">
               <table border="0" cellpadding="0" cellspacing="0" width="100%">
               <colgroup>
               <col width='132'>
               <col width='132'>
               <col >
               <col width='132'>
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
                        printButton("Generar XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
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
         <?php
         printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
         ?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col>
            <col>
            <col>
            <col>
            <col width="85">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
            <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         $total = 0;
         for($x = 0; $x < count($headpayment) && $headpayment != false; $x++)
         {  ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=$headpayment[$x]["acc_name"]?>&nbsp;</td>
               <td class="content_row">$ <?=printPrice($headpayment[$x]["adm_amount"])?></td>
               <td class="content_row"><?=date('d.m.Y',$headpayment[$x]["adm_date"])?></td>
               <td class="content_row"><?=$headpayment[$x]["adm_notes"]?>&nbsp;</td>
               <td class="content_row" align="center">
                  <?php
                  printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$headpayment[$x]["id"]}", "", "pencil");
                  ?>
               </td>
            </tr>
            <?php
            $total += $headpayment[$x]["adm_amount"];
         }
         if($x)
         {  ?>
            <tr style="border-width: 10px">
               <td class="content_row_os content_row_totals"><b>TOTAL</b></td>
               <td class="content_row_os content_row_totals" colspan="5"><?="$ ".printPrice($total)?></td>
            </tr>
         <?php
         }
         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" colspan="6" align="center">
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
         <br>
      </td>
   </tr>
   </table>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?php
   if($pdffile != "")
   {
      $doctitle = "Lista-pagos.pdf";
      $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
      ?>
      <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
      <?php
   }
   ?>
   <?php
   if($xlsfile != "")
   {
      $doctitle = "Lista-pagos.xls";
      $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
      ?>
      <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
      <?php
   }
   $_SESSION["JSEXEC"] .= "; setCompanyShop({$_SESSION[$_sesmodulename]["sql_company"]}); ";
}