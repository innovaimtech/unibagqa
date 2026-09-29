<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2022 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "importacion";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo/Familia" => "2");
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
   $_SESSION[$_sesmodulename]["sql_storehouse"]    = (int)$_REQUEST["sql_storehouse"];
   $_SESSION[$_sesmodulename]["sql_equvals"]       = $_REQUEST["sql_equvals"];

   $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
   $_SESSION[$_sesmodulename]["sql_buque"]      = trim(addslashes($_REQUEST["sql_buque"]));
   $_SESSION[$_sesmodulename]["sql_forward"]    = trim(addslashes($_REQUEST["sql_forward"]));
   $_SESSION[$_sesmodulename]["sql_bill"]       = trim(addslashes($_REQUEST["sql_bill"]));
   $_SESSION[$_sesmodulename]["sql_contenedor"] = trim(addslashes($_REQUEST["sql_contenedor"]));
   $_SESSION[$_sesmodulename]["sql_ocs"]        = trim(addslashes($_REQUEST["sql_ocs"]));

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

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
      {
            array_push($selshops, $shop);
      }
}

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from supplier_contenedor t1
         LEFT OUTER JOIN company_data t2  ON t1.sord_company_id   = t2.id
         LEFT OUTER JOIN company_shops t3 ON t1.sord_shop_id      = t3.id
         where
         t1.sord_status > 1 and
         (
            t1.sord_crtdat between {$sql_datefrom} and {$sql_dateto} or
            t1.sord_eta_puerto between {$sql_datefrom} and {$sql_dateto} or
            t1.sord_eta_puertounibag between {$sql_datefrom} and {$sql_dateto}
         ) ";

if($_SESSION[$_sesmodulename]["sql_stext"] != "")
   $sql .= " and t1.id = ".(int)$_SESSION[$_sesmodulename]["sql_stext"]." ";
if($_SESSION[$_sesmodulename]["sql_buque"] != "")
   $sql .= " and t1.sord_buque like '%{$_SESSION[$_sesmodulename]["sql_buque"]}%' ";
if($_SESSION[$_sesmodulename]["sql_forward"] != "")
   $sql .= " and t1.sord_forward like '%{$_SESSION[$_sesmodulename]["sql_forward"]}%' ";
if($_SESSION[$_sesmodulename]["sql_bill"] != "")
   $sql .= " and t1.sord_billoflanding like '%{$_SESSION[$_sesmodulename]["sql_bill"]}%' ";
if($_SESSION[$_sesmodulename]["sql_contenedor"] != "")
   $sql .= " and t1.sord_contenedor like '%{$_SESSION[$_sesmodulename]["sql_contenedor"]}%' ";
if($_SESSION[$_sesmodulename]["sql_ocs"] != "")
   $sql .= " and t1.sord_ocs like '%{$_SESSION[$_sesmodulename]["sql_ocs"]}%' ";

$sql .= " order by t1.id ";
$conts = $CON->select($sql);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Arribo de contenedores</b></td>
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
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
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
         <td class="content_rowl">Número</td>
         <td class="content_row">
            <input name="sql_stext" type="text" class="text" style="width:375px"
            value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_rowl">OC</td>
         <td class="content_row">
            <input name="sql_ocs" type="text" class="text" style="width:375px"
            value="<?=$_SESSION[$_sesmodulename]["sql_ocs"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Buque</td>
         <td class="content_row">
            <input name="sql_buque" type="text" class="text" style="width:375px"
            value="<?=$_SESSION[$_sesmodulename]["sql_buque"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_rowl">Forward</td>
         <td class="content_row">
            <input name="sql_forward" type="text" class="text" style="width:375px"
            value="<?=$_SESSION[$_sesmodulename]["sql_forward"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Bill of Landing</td>
         <td class="content_row">
            <input name="sql_bill" type="text" class="text" style="width:375px"
            value="<?=$_SESSION[$_sesmodulename]["sql_bill"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_rowl">Contenedor</td>
         <td class="content_row">
            <input name="sql_contenedor" type="text" class="text" style="width:375px"
            value="<?=$_SESSION[$_sesmodulename]["sql_contenedor"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">ETA Unibag</td>
         <td class="content_row">
            <table border="0" class="content_table" cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="70">
                  <nobr>
                  <input type="radio" name="sql_selmode" value="2"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 2) echo "checked"?>> Meses
                  </nobr>
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
                     $startyear  = date('Y') -10;
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
                     $startyear  = date('Y') -10;
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
                  <nobr>
                  <input type="radio" name="sql_selmode" value="1"
                  onclick="document.getElementById('idx_selmode1').style.display='';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 1) echo "checked"?>> Dia
                  </nobr>
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
                  <nobr>
                  <input type="radio" name="sql_selmode" value="3"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 3) echo "checked"?>> Periodo
                  </nobr>
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
               <col width="135">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if(count($conts) > 0 && $conts != false)
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  /*
                  if(count($_ITEMS) > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  */
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
      foreach($conts AS $cont)
      {
         //----------------------------------------------------------------------------------
         $sql = " select t2.*, t4.supp_short
                  from invoices_buy t2
                  LEFT OUTER JOIN invoices_buy_contenedores t1 ON t1.invc_id = t2.id
                  LEFT OUTER JOIN supplier t4   ON t2.invc_supplier_id = t4.id
                  where
                  t1.cont_id     = {$cont["id"]} and
                  t2.invc_status > 1
                  order by t2.invc_date, t2.id";
         $gastosinvoices = $CON->select($sql);

         //----------------------------------------------------------------------------------
         $sql = " select t1.*
                  from supplier_contenedor_items t1
                  INNER JOIN supplier_order_items t2 ON t1.sord_pos_id = t2.id
                  where
                  t1.sord_id = {$cont["id"]}
                  order by t1.id asc";
         $posdata = $CON->select($sql);

         $_CONTCOSTS = getContainerCosts($CON, $cont["id"]);
         unset($_MATERIAL_INVC);
         ?>
         <?=Nifty_printH("box1", "99%")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="130">
            <col width="120">
            <col width="90">
            <col width="90">
            <col width="160">
            <col width="110">
            <col width="85">
            <col width="200">
            <col width="85">
            <col>
            <col width="85">
            <col width="85">
            <col width="85">
            <col width="110">
            <col width="110">
            <col width="110">
            <col width="110">
         </colgroup>
         <!--
         <tr>
            <td class="content_tbl_header" colspan="12" style="padding:0px">
               <table border="0" cellpadding="3" cellspacing="0" width="100%">
               <colgroup>
                  <col width="1">
                  <col width="30%">
                  <col width="1">
                  <col width="30%">
                  <col width="1">
                  <col width="30%">
               </colgroup>
               <tr>
                  <td class="content_row" style="padding-right:6px">ID</td>
                  <td class="content_row"><?=sprintf("%05s", $cont["id"])?></td>
                  <td class="content_row" style="padding-right:6px">Contenedores</td>
                  <td class="content_row"><?=$cont["sord_contenedor"]?></td>
                  <td class="content_row" style="padding-right:6px">Buque</td>
                  <td class="content_row"><?=$cont["sord_buque"]?></td>
               </tr>
               <tr>
                  <td class="content_row" style="padding-right:6px">Forward</td>
                  <td class="content_row"><?=$cont["sord_forward"]?></td>
                  <td class="content_row" style="padding-right:6px"><nobr>Bill of Landing</nobr></td>
                  <td class="content_row"><?=$cont["sord_billoflanding"]?></td>
                  <td class="content_row" style="padding-right:6px"><nobr>ETA Puerto</nobr></td>
                  <td class="content_row"><?=date("d.m.Y", $cont["sord_eta_puerto"])?></td>
               </tr>
               <tr>
                  <td class="content_row" style="padding-right:6px">Incoterm</td>
                  <td class="content_row"><?=$cont["sord_incoterm"]?></td>
                  <td class="content_row" style="padding-right:6px"><nobr>Dias de viaje</nobr></td>
                  <td class="content_row"><?=$cont["sord_diasviaje"]?></td>
                  <td class="content_row" style="padding-right:6px"><nobr>ETA Unibag</nobr></td>
                  <td class="content_row"><?=date("d.m.Y", $cont["sord_eta_puertounibag"])?></td>
               </tr>
               </table>
            </td>
         </tr>
         -->
         <tr>
            <td class="content_tbl_subheader content_row_os" style="background-color:#AED6B3" colspan="17">Contenido contenedor</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os" style="font-weight:bold">N° Contenedor</td>
            <td class="content_tbl_subheader content_row_os" style="font-weight:bold">Origen</td>
            <td class="content_tbl_subheader content_row_os" style="font-weight:bold">ETA Puerto</td>
            <td class="content_tbl_subheader content_row_os" style="font-weight:bold">ETA Unibag</td>
            <td class="content_tbl_subheader content_row_os" style="font-weight:bold">Forward</td>
            <td class="content_tbl_subheader content_row_os" style="font-weight:bold">OC</td>
            <td class="content_tbl_subheader content_row_os" style="font-weight:bold">Fecha</td>
            <td class="content_tbl_subheader content_row_os" style="font-weight:bold">Proveedor</td>
            <td class="content_tbl_subheader content_row_os" style="font-weight:bold">Código</td>
            <td class="content_tbl_subheader content_row_os" style="font-weight:bold">Producto</td>
            <td class="content_tbl_subheader content_row_os" align="center" style="font-weight:bold">Cant.OC</td>
            <td class="content_tbl_subheader content_row_os" align="center" style="font-weight:bold">Cant.Cont.</td>
            <td class="content_tbl_subheader content_row_os" align="center" style="font-weight:bold">Cant.Recep</td>
            <td class="content_tbl_subheader content_row_os" align="right" style="font-weight:bold">$/Unit</td>
            <td class="content_tbl_subheader content_row_os" align="right" style="font-weight:bold">$/Total</td>
            <td class="content_tbl_subheader content_row_os" align="right" style="font-weight:bold">Costo/Adic.</td>
            <td class="content_tbl_subheader content_row_os" align="right" style="font-weight:bold">Costo/Import.</td>
         </tr>
         <?php
         for($x = 0; $x < count($posdata) && $posdata != false; $x++)
         {
            //----------------------------------------------------------------------------------
            $sql = " select *
                     from supplier_order_items
                     where
                     id = {$posdata[$x]["sord_pos_id"]}";
            $supporderpos = $CON->select($sql);
            $supporderpos = $supporderpos[0];

            //----------------------------------------------------------------------------------
            $sql = " select t1.*, t2.supp_short, t3.country_name
                     from supplier_order t1
                     LEFT OUTER JOIN supplier t2 ON t1.sord_supplier_id  = t2.id
                     LEFT OUTER JOIN country t3 ON t2.supp_countryid = t3.id
                     where
                     t1.id = {$supporderpos["sord_id"]}";
            $sorddata = $CON->select($sql);
            $sorddata = $sorddata[0];

            $fullpos = getSupplierOrderPos($CON, $supporderpos["sord_id"], "", $posdata[$x]["sord_pos_id"]);
            $fullpos = $fullpos[0];

            //----------------------------------------------------------------------------------
            $sql = " select SUM(t3.item_amount) 'cfmamt'
                     from supplier_contenedor_items t1
                     INNER JOIN supplier_order_items t2        ON t1.sord_pos_id = t2.id
                     INNER JOIN stockchanges ta                ON ta.sth_supporder_contenedorid = t1.sord_id
                     INNER JOIN stockchanges_items t3          ON t3.stk_id = ta.id and t3.item_id = t2.item_id and t3.item_contenedor_refid = t1.id
                     INNER JOIN company_shops_storehouses t4   ON t3.item_st_id = t4.id
                     where
                     t1.id          = {$posdata[$x]["id"]} and
                     t1.sord_id     = {$cont["id"]} and
                     ta.stk_status  > 1";
            $rcvsamt = $CON->select($sql);
            $_RECEIVEAMT = (float)$rcvsamt[0]["cfmamt"];

            //----------------------------------------------------------------------------------
            $_PRICE_UNIT = 0.00;
            $_PRICE_UNIT_TOTAL = 0.00;
            $sql = " select distinct t1.id, t1.invc_supplier_id, t1.invc_date, t2.item_amount,
                            t2.item_costprice_netto_dsc2 'pricetotal', t1.invc_importation, t1.invc_taxes,
                            t2.item_costprice_import_total, t1x.part_sord_id, t2.item_supporder_pos,
                            t3.supp_short, t1.invc_docnumber,
                            t1.invc_total_netto, t1.invc_total_taxes, t1.invc_total_taxes_add, t1.invc_total_brutto,
                            t1.invc_import_total
                     from invoices_buy t1
                     INNER JOIN invoices_buy_parts t1x      ON t1.id = t1x.part_invc_id
                     INNER JOIN invoices_buy_parts_items t2 ON t1.id = t2.invc_id
                     INNER JOIN supplier t3                 ON t1.invc_supplier_id = t3.id
                     where
                     t1x.part_sord_id     = {$supporderpos["sord_id"]} and
                     t1.invc_status       > 0 and
                     t1.invc_status       < 4 and
                     t2.item_id           = {$fullpos["item_id"]} and
                     t2.item_type         = 'item'
                     order by t1.invc_date desc, t1.id";
            $buyinvcs = $CON->select($sql);
            foreach($buyinvcs AS $buyinvc)
            {
               $itemprice = 0.00;
               if(!(int)$buyinvc["invc_importation"])
               {
                  $itemprice = $buyinvc["pricetotal"] / $buyinvc["item_amount"];
               }
               else
               {
                  $itemprice = $buyinvc["item_costprice_import_total"] / $buyinvc["item_amount"];
               }
               if($itemprice > 0.00)
                  $_PRICE_UNIT = $itemprice;


               $_MATERIAL_INVC[$buyinvc["id"]] = $buyinvc;
            }
            $_PRICE_UNIT_TOTAL = round($_PRICE_UNIT * $posdata[$x]["sord_amount"]);

            $bcss = "";
            if($_PRICE_UNIT_TOTAL == 0.00)
               $bcss = "color:red";

            //----------------------------------------------------------------------------------
            $_COST_UNIT = 0.00;
            $_COST_UNIT_TOTAL = 0.00;

            foreach($_CONTCOSTS["_ITEMS"] AS $costitem)
            {
               if($costitem["supporder_itemid"] == $supporderpos["item_id"] &&
                  $costitem["supporder_id"] == $supporderpos["sord_id"] &&
                  $costitem["supporder_itempos"] == $supporderpos["item_pos"])
               {
                  $_COST_UNIT = $costitem["line_itemunit_costs"];
               }
            }
            $_COST_UNIT_TOTAL = round($_COST_UNIT * $posdata[$x]["sord_amount"]);

            $ccss = "";
            if($_COST_UNIT_TOTAL == 0.00)
               $ccss = "color:red";
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><?=$cont["sord_contenedor"]?></td>
               <td class="content_row_os"><?=$sorddata["country_name"]?></td>
               <td class="content_row_os"><?=date("d.m.Y", $cont["sord_eta_puerto"])?></td>
               <td class="content_row_os"><?=date("d.m.Y", $cont["sord_eta_puertounibag"])?></td>
               <td class="content_row_os"><?=$cont["sord_forward"]?></td>
               <td class="content_row_os"><?=$sorddata["sord_number"]?></td>
               <td class="content_row_os"><?=date('d.m.Y', $sorddata["sord_crtdat"])?></td>
               <td class="content_row_os"><?=$sorddata["supp_short"]?></td>
               <td class="content_row_os">
                  <?=$fullpos["item_number_prod"]?>
                  <?php
                  if($fullpos["item_type"] == "manual")
                     echo "<b class=msg_save_err>Manual</b>";
                  ?>
               </td>
               <td class="content_row_os"><?=$fullpos["item_title"]?></td>
               <td class="content_row_os" align="center"><?=printPrice($fullpos["item_amount"], 2)?></td>
               <td class="content_row_os" align="center"><?=printPrice($posdata[$x]["sord_amount"], 2)?></td>
               <td class="content_row_os" align="center"><?=printPrice($_RECEIVEAMT, 2)?></td>
               <td class="content_row_os" align="right" style="<?=$bcss?>"><?=printPrice($_PRICE_UNIT)?></td>
               <td class="content_row_os" align="right" style="<?=$bcss?>"><?=printPrice($_PRICE_UNIT_TOTAL)?></td>
               <td class="content_row_os" align="right" style="<?=$ccss?>"><?=printPrice($_COST_UNIT_TOTAL)?></td>
               <td class="content_row_os" align="right" style="<?=$ccss?>"><?=printPrice($_PRICE_UNIT_TOTAL + $_COST_UNIT_TOTAL)?></td>
            </tr>
            <?php

            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["sord_contenedor"]         = $cont["sord_contenedor"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["country_name"]            = $sorddata["country_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["sord_eta_puerto"]         = date("d.m.Y", $cont["sord_eta_puerto"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["sord_eta_puertounibag"]   = date("d.m.Y", $cont["sord_eta_puertounibag"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["sord_forward"]            = $cont["sord_forward"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["sord_number"]             = $sorddata["sord_number"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["sord_crtdat"]             = date('d.m.Y', $sorddata["sord_crtdat"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["supp_short"]              = $sorddata["supp_short"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["item_number_prod"]        = $fullpos["item_number_prod"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["item_title"]              = $fullpos["item_title"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["item_amount"]             = printPrice($fullpos["item_amount"], 2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["sord_amount"]             = printPrice($posdata[$x]["sord_amount"], 2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["_RECEIVEAMT"]             = printPrice($_RECEIVEAMT, 2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["_PRICE_UNIT"]             = printPrice($_PRICE_UNIT);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["_PRICE_UNIT_TOTAL"]       = printPrice($_PRICE_UNIT_TOTAL);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["_COST_UNIT_TOTAL"]        = printPrice($_COST_UNIT_TOTAL);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cont["id"]][$x]["_XTOTAL"]                 = printPrice($_PRICE_UNIT_TOTAL + $_COST_UNIT_TOTAL);
         }
         if(count($_MATERIAL_INVC) && $_MATERIAL_INVC != false)
         {  ?>
            <tr>
               <td class="content_tbl_subheader content_row_os" style="background-color:#FFE4A8" colspan="17">Facturas consideradas como compra de productos</td>
            </tr>
            <tr>
               <td class="content_tbl_subheader content_row_os" style="font-weight:bold">Factura</td>
               <td class="content_tbl_subheader content_row_os" style="font-weight:bold">Fecha</td>
               <td class="content_tbl_subheader content_row_os" style="font-weight:bold" colspan="12">Proveedor</td>
               <td class="content_tbl_subheader content_row_os" align="right" style="font-weight:bold">$/Neto</td>
               <td class="content_tbl_subheader content_row_os" align="right" style="font-weight:bold">$/IVA</td>
               <td class="content_tbl_subheader content_row_os" align="right" style="font-weight:bold">$/Bruto</td>
            </tr>
            <?php
            $subx = 0;
            $_INVC_NETTO_TOTAL   = 0.00;
            $_INVC_TAXES_TOTAL   = 0.00;
            $_INVC_BRUTTO_TOTAL  = 0.00;
            foreach($_MATERIAL_INVC AS $_MATERIAL_INVCROW)
            {
               $_INVC_NETTO   = 0.00;
               $_INVC_TAXES   = 0.00;
               $_INVC_BRUTTO  = 0.00;

               if((int)$_MATERIAL_INVCROW["invc_importation"])
               {
                  $_INVC_NETTO   = $_MATERIAL_INVCROW["invc_import_total"];
                  $_INVC_TAXES   = 0.00;
                  $_INVC_BRUTTO  = $_MATERIAL_INVCROW["invc_import_total"];

               }
               else
               {
                  $_INVC_NETTO   = $_MATERIAL_INVCROW["invc_total_netto"];
                  $_INVC_TAXES   = $_MATERIAL_INVCROW["invc_total_taxes"] + $_MATERIAL_INVCROW["invc_total_taxes_add"];
                  $_INVC_BRUTTO  = $_MATERIAL_INVCROW["invc_total_brutto"];
               }
               ?>
               <tr bgcolor="<?=getRowColor($subx)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os"><?=$_MATERIAL_INVCROW["invc_docnumber"]?></td>
                  <td class="content_row_os"><?=date("d.m.Y", $_MATERIAL_INVCROW["invc_date"])?></td>
                  <td class="content_row_os" colspan="12"><?=$_MATERIAL_INVCROW["supp_short"]?></td>
                  <td class="content_row_os" align="right"><?=printPrice($_INVC_NETTO)?></td>
                  <td class="content_row_os" align="right"><?=printPrice($_INVC_TAXES)?></td>
                  <td class="content_row_os" align="right"><?=printPrice($_INVC_BRUTTO)?></td>
               </tr>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["_COMPRAS"][$cont["id"]][$subx]["invc_docnumber"]   = $_MATERIAL_INVCROW["invc_docnumber"];
               $_SESSION["STATS"][$_sesmodulename]["_COMPRAS"][$cont["id"]][$subx]["invc_date"]        = date("d.m.Y", $_MATERIAL_INVCROW["invc_date"]);
               $_SESSION["STATS"][$_sesmodulename]["_COMPRAS"][$cont["id"]][$subx]["supp_short"]       = $_MATERIAL_INVCROW["supp_short"];
               $_SESSION["STATS"][$_sesmodulename]["_COMPRAS"][$cont["id"]][$subx]["_INVC_NETTO"]      = printPrice($_INVC_NETTO);
               $_SESSION["STATS"][$_sesmodulename]["_COMPRAS"][$cont["id"]][$subx]["_INVC_TAXES"]      = printPrice($_INVC_TAXES);
               $_SESSION["STATS"][$_sesmodulename]["_COMPRAS"][$cont["id"]][$subx]["_INVC_BRUTTO"]     = printPrice($_INVC_BRUTTO);

               $_INVC_NETTO_TOTAL   += $_INVC_NETTO;
               $_INVC_TAXES_TOTAL   += $_INVC_TAXES;
               $_INVC_BRUTTO_TOTAL  += $_INVC_BRUTTO;
               $subx++;
            }
            ?>
            <tr>
               <td class="content_row_os content_row_totals" colspan="14">Total compras</td>
               <td class="content_row_os content_row_totals" align="right"><?=printPrice($_INVC_NETTO_TOTAL)?></td>
               <td class="content_row_os content_row_totals" align="right"><?=printPrice($_INVC_TAXES_TOTAL)?></td>
               <td class="content_row_os content_row_totals" align="right"><?=printPrice($_INVC_BRUTTO_TOTAL)?></td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["_COMPRAS"][$cont["id"]][$subx]["invc_docnumber"]   = "Total compras";
            $_SESSION["STATS"][$_sesmodulename]["_COMPRAS"][$cont["id"]][$subx]["_INVC_NETTO"]      = printPrice($_INVC_NETTO_TOTAL);
            $_SESSION["STATS"][$_sesmodulename]["_COMPRAS"][$cont["id"]][$subx]["_INVC_TAXES"]      = printPrice($_INVC_TAXES_TOTAL);
            $_SESSION["STATS"][$_sesmodulename]["_COMPRAS"][$cont["id"]][$subx]["_INVC_BRUTTO"]     = printPrice($_INVC_BRUTTO_TOTAL);
         }
         if(count($gastosinvoices) && $gastosinvoices != false)
         {  ?>
            <tr>
               <td class="content_tbl_subheader content_row_os" style="background-color:#F5C4CA" colspan="17">Facturas consideradas como gastos</td>
            </tr>
            <tr>
               <td class="content_tbl_subheader content_row_os" style="font-weight:bold">Factura</td>
               <td class="content_tbl_subheader content_row_os" style="font-weight:bold">Fecha</td>
               <td class="content_tbl_subheader content_row_os" style="font-weight:bold" colspan="12">Proveedor</td>
               <td class="content_tbl_subheader content_row_os" align="right" style="font-weight:bold">$/Neto</td>
               <td class="content_tbl_subheader content_row_os" align="right" style="font-weight:bold">$/IVA</td>
               <td class="content_tbl_subheader content_row_os" align="right" style="font-weight:bold">$/Bruto</td>
            </tr>
            <?php
            $subx = 0;
            $_INVC_NETTO_TOTAL   = 0.00;
            $_INVC_TAXES_TOTAL   = 0.00;
            $_INVC_BRUTTO_TOTAL  = 0.00;
            foreach($gastosinvoices AS $gastosinvoice)
            {
               $_INVC_NETTO   = 0.00;
               $_INVC_TAXES   = 0.00;
               $_INVC_BRUTTO  = 0.00;

               if((int)$gastosinvoice["invc_importation"])
               {
                  $_INVC_NETTO   = $gastosinvoice["invc_import_total"];
                  $_INVC_TAXES   = 0.00;
                  $_INVC_BRUTTO  = $gastosinvoice["invc_import_total"];

               }
               else
               {
                  $_INVC_NETTO   = $gastosinvoice["invc_total_netto"];
                  $_INVC_TAXES   = $gastosinvoice["invc_total_taxes"] + $gastosinvoice["invc_total_taxes_add"];
                  $_INVC_BRUTTO  = $gastosinvoice["invc_total_brutto"];
               }
               ?>
               <tr bgcolor="<?=getRowColor($subx)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os"><?=$gastosinvoice["invc_docnumber"]?></td>
                  <td class="content_row_os"><?=date("d.m.Y", $gastosinvoice["invc_date"])?></td>
                  <td class="content_row_os" colspan="12"><?=$gastosinvoice["supp_short"]?></td>
                  <td class="content_row_os" align="right"><?=printPrice($_INVC_NETTO)?></td>
                  <td class="content_row_os" align="right"><?=printPrice($_INVC_TAXES)?></td>
                  <td class="content_row_os" align="right"><?=printPrice($_INVC_BRUTTO)?></td>
               </tr>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["_GASTOS"][$cont["id"]][$subx]["invc_docnumber"]   = $gastosinvoice["invc_docnumber"];
               $_SESSION["STATS"][$_sesmodulename]["_GASTOS"][$cont["id"]][$subx]["invc_date"]        = date("d.m.Y", $gastosinvoice["invc_date"]);
               $_SESSION["STATS"][$_sesmodulename]["_GASTOS"][$cont["id"]][$subx]["supp_short"]       = $gastosinvoice["supp_short"];
               $_SESSION["STATS"][$_sesmodulename]["_GASTOS"][$cont["id"]][$subx]["_INVC_NETTO"]      = printPrice($_INVC_NETTO);
               $_SESSION["STATS"][$_sesmodulename]["_GASTOS"][$cont["id"]][$subx]["_INVC_TAXES"]      = printPrice($_INVC_TAXES);
               $_SESSION["STATS"][$_sesmodulename]["_GASTOS"][$cont["id"]][$subx]["_INVC_BRUTTO"]     = printPrice($_INVC_BRUTTO);

               $_INVC_NETTO_TOTAL   += $_INVC_NETTO;
               $_INVC_TAXES_TOTAL   += $_INVC_TAXES;
               $_INVC_BRUTTO_TOTAL  += $_INVC_BRUTTO;
               $subx++;
            }
            ?>
            <tr>
               <td class="content_row_os content_row_totals" colspan="14">Total gastos</td>
               <td class="content_row_os content_row_totals" align="right"><?=printPrice($_INVC_NETTO_TOTAL)?></td>
               <td class="content_row_os content_row_totals" align="right"><?=printPrice($_INVC_TAXES_TOTAL)?></td>
               <td class="content_row_os content_row_totals" align="right"><?=printPrice($_INVC_BRUTTO_TOTAL)?></td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["_GASTOS"][$cont["id"]][$subx]["invc_docnumber"]   = "Total gastos";
            $_SESSION["STATS"][$_sesmodulename]["_GASTOS"][$cont["id"]][$subx]["_INVC_NETTO"]      = printPrice($_INVC_NETTO_TOTAL);
            $_SESSION["STATS"][$_sesmodulename]["_GASTOS"][$cont["id"]][$subx]["_INVC_TAXES"]      = printPrice($_INVC_TAXES_TOTAL);
            $_SESSION["STATS"][$_sesmodulename]["_GASTOS"][$cont["id"]][$subx]["_INVC_BRUTTO"]     = printPrice($_INVC_BRUTTO_TOTAL);
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?php
      }
      ?>
   </td>
</tr>
</table>
<?php
$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';

//----------------------------------------------------------------------------------
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsImportacion($CON);

if($xlsfile != "")
{
   $doctitle = "Arribo-de-contenedores-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>