<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2023 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_prodsolicscc";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "";
$_sortlinks             = Array();

unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_xstate"]        = (int)$_REQUEST["sql_xstate"];
   $_SESSION[$_sesmodulename]["sql_plantaid"]      = (int)$_REQUEST["sql_plantaid"];
   $_SESSION[$_sesmodulename]["sql_equipotypeid"]  = (int)$_REQUEST["sql_equipotypeid"];
   $_SESSION[$_sesmodulename]["sql_equipoid"]      = (int)$_REQUEST["sql_equipoid"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_number"]        = trim(addslashes($_REQUEST["sql_number"]));
   $_SESSION[$_sesmodulename]["sql_fab_design_name"] = trim(addslashes($_REQUEST["sql_fab_design_name"]));
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
}

//----------------------------------------------------------------------------------
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 1;

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m',time());
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y',time());
   $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m',time());
   $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y',time());
}
if($_SESSION[$_sesmodulename]["sql_date"] == "")
   $_SESSION[$_sesmodulename]["sql_date"] = date('d.m.Y');
if($_SESSION[$_sesmodulename]["sql_date_pfrom"] == "")
{
   $_SESSION[$_sesmodulename]["sql_date_pfrom"] = date('d.m.Y', time());
   $_SESSION[$_sesmodulename]["sql_date_pto"]   = date('d.m.Y', time() + (86400 * 7));
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
$plantas = getPlantas($CON);
if(!(int)$_SESSION[$_sesmodulename]["sql_plantaid"])
   $_SESSION[$_sesmodulename]["sql_plantaid"] = $plantas[0]["id"];


$sql = " select t1.id, t1.req_number, t1.req_production_initdate, t2.user_lastname, t1.req_solic_supp_seudonimo,
                t2x.item_title, t6.fab_med_width, t6.fab_med_height, t6.fab_med_fuelle, t6.fab_type,
                t6.fab_printtype, t7.supp_short, t6.fab_mat_fabric_color, t6.fab_mat_manilla_color,
                t6.fab_print_colors_front_1, t6.fab_print_colors_back_1, t6.fab_print_colors_front_2, t6.fab_print_colors_back_2,
                t6.fab_print_colors_front_3, t6.fab_print_colors_back_3, t6.fab_print_colors_front_4, t6.fab_print_colors_back_4,
                t6.fab_print_colordesc_1, t6.fab_print_colordesc_2, t6.fab_print_colordesc_3, t6.fab_print_colordesc_4,
                t6.fab_manilla_length, t1.req_dsgnchk_pieimprenta_act, t1.req_dsgnchk_barcode_act, t8.user_lastname 'designername'
         from orders t1
         LEFT OUTER JOIN user t2          ON t1.req_userid_seller = t2.id
         INNER JOIN orders_items t6       ON t1.id = t6.req_id
         INNER JOIN item t2x              ON t6.item_id = t2x.id
         LEFT OUTER JOIN supplier t7      ON t1.req_solic_supp_id = t7.id
         LEFT OUTER JOIN user t8          ON t1.req_design_blocked_uid = t8.id
         where
         t1.req_status                 > 1 and
         t1.req_isfabricate            = 1 and
         t1.req_production_act         > 0 and
         t1.req_production_initdate    between {$sql_datefrom} and {$sql_dateto} ";

if($_SESSION[$_sesmodulename]["sql_number"] != "")
   $sql .= " and t1.req_number = '{$_SESSION[$_sesmodulename]["sql_number"]}' ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $sql .= " and t1.req_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if($_SESSION[$_sesmodulename]["sql_fab_design_name"] != "")
   $sql .= " and t6.fab_design_name like '%{$_SESSION[$_sesmodulename]["sql_fab_design_name"]}%' ";

$sql .=" order by t1.req_production_initdate, t1.req_number";

$data = $CON->select($sql);

//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Informe de solicitudes de CC</b></td>
   <td align="right" class="content_row_clear">&nbsp;</td>
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
      <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="80">
         <col>
         <col width="80">
         <col width="400">
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
                     $startyear  = date('Y') -20;
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
                     $startyear  = date('Y') -20;
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
                  <input type="text" style="width:75px" id="sql_date_pfrom" name="sql_date_pfrom"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pfrom"]?>">
                  -
                  <input type="text" style="width:75px" id="sql_date_pto" name="sql_date_pto"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pto"]?>">
                  </nobr>
               </td>
            </tr>
            </table>
         </td>
         <td class="content_rowl">Nº CC</td>
         <td class="content_row">
            <input type="text" class="text" style="width:100%"
            name="sql_number" value="<?=$_SESSION[$_sesmodulename]["sql_number"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
         <td class="content_rowl">Diseño</td>
         <td class="content_row">
            <input type="text" class="text" style="width:375px"
            name="sql_fab_design_name" value="<?=$_SESSION[$_sesmodulename]["sql_fab_design_name"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width='132'>
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if(count($data) > 0 && $data != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($data) > 0 && $data != false)
                  {
                     printButton("Generar XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="right" style="padding-right:5px" width="1">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["search_active"])
                  {
                     printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                  }
                  ?>
               </td>
               <td align="right" width="1">
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
         <td class="content_tbl_header content_row_os" align="center">Nº CC</td>
         <td class="content_tbl_header content_row_os" align="center">Ingreso</td>
         <td class="content_tbl_header content_row_os">Vendedor</td>
         <td class="content_tbl_header content_row_os">Seudónimo</td>
         <td class="content_tbl_header content_row_os">Tipo bolsa</td>
         <td class="content_tbl_header content_row_os">Medida bolsa</td>
         <td class="content_tbl_header content_row_os">Tipo tela</td>
         <td class="content_tbl_header content_row_os">Tipo Impresión</td>
         <td class="content_tbl_header content_row_os">Impreso en</td>
         <td class="content_tbl_header content_row_os">Lados impresión</td>
         <td class="content_tbl_header content_row_os">Color tela</td>
         <td class="content_tbl_header content_row_os">Color manillas</td>
         <td class="content_tbl_header content_row_os">Color 1</td>
         <td class="content_tbl_header content_row_os">Color 2</td>
         <td class="content_tbl_header content_row_os">Color 3</td>
         <td class="content_tbl_header content_row_os">Color 4</td>
         <td class="content_tbl_header content_row_os">Largo manillas</td>
         <td class="content_tbl_header content_row_os">Pie de imprenta</td>
         <td class="content_tbl_header content_row_os">Código de barras</td>
         <td class="content_tbl_header content_row_os">Diseñador responsable</td>
      </tr>
      <?php
      for($x = 0; $x < count($data) && $data != false; $x++)
      {
         if($data[$x]["fab_printtype"] == "FLEX")
            $fab_printtype = "Flexografia";
         elseif($data[$x]["fab_printtype"] == "SERI")
            $fab_printtype = "Serigrafia";

         //----------------------------------------------------------------------------------
         $sql = " select add_name
                  from tran_comments_vals
                  where
                  id = {$data[$x]["fab_mat_fabric_color"]}";
         $fabric_color = $CON->select($sql);
         $fabric_color = $fabric_color[0]["add_name"];

         $sql = " select add_name
                  from tran_comments_vals
                  where
                  id = {$data[$x]["fab_mat_manilla_color"]}";
         $manilla_color = $CON->select($sql);
         $manilla_color = $manilla_color[0]["add_name"];

         unset($colors);
         $colors[1]  = "";
         $colors[2]  = "";
         $colors[3]  = "";
         $colors[4]  = "";
         $printsides = "";

         for($xx = 1; $xx <= 4; $xx++)
         {
            if((int)$data[$x]["fab_print_colors_front_{$xx}"] || (int)$data[$x]["fab_print_colors_back_{$xx}"])
            {
               $colors[$xx] = $data[$x]["fab_print_colordesc_{$xx}"];

               $thismode = "";
               if((int)$data[$x]["fab_print_colors_front_{$xx}"] && !(int)$data[$x]["fab_print_colors_back_{$xx}"])
                  $thismode = "Frente";
               elseif(!(int)$data[$x]["fab_print_colors_front_{$xx}"] && (int)$data[$x]["fab_print_colors_back_{$xx}"])
                  $thismode = "Dorso";
               elseif((int)$data[$x]["fab_print_colors_front_{$xx}"] && (int)$data[$x]["fab_print_colors_back_{$xx}"])
                  $thismode = "Frente/Dorso";

               if($printsides == "")
                  $printsides = $thismode;
               elseif(($thismode == "Frente" || $thismode == "Dorso") && $thismode == "Frente/Dorso")
                     $printsides = $thismode;
            }
         }
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os" align="center"><nobr><?=$data[$x]["req_number"]?></nobr></td>
            <td class="content_row_os" align="center"><?=date('d.m.Y',$data[$x]["req_production_initdate"])?></td>
            <td class="content_row_os"><?=$data[$x]["user_lastname"]?></td>
            <td class="content_row_os"><?=$data[$x]["req_solic_supp_seudonimo"]?>&nbsp;</td>
            <td class="content_row_os"><?=$data[$x]["item_title"]?>&nbsp;</td>
            <td class="content_row_os"><?=(int)$data[$x]["fab_med_width"]?>x<?=(int)$data[$x]["fab_med_height"]?> (<?=(int)$data[$x]["fab_med_fuelle"]?>)</td>
            <td class="content_row_os"><?=$data[$x]["fab_type"]?>&nbsp;</td>
            <td class="content_row_os"><?=$fab_printtype?>&nbsp;</td>
            <td class="content_row_os"><?=$data[$x]["supp_short"]?>&nbsp;</td>
            <td class="content_row_os"><?=$printsides?></td>
            <td class="content_row_os"><?=$fabric_color?>&nbsp;</td>
            <td class="content_row_os"><?=$manilla_color?>&nbsp;</td>
            <td class="content_row_os"><?=$colors[1]?>&nbsp;</td>
            <td class="content_row_os"><?=$colors[2]?>&nbsp;</td>
            <td class="content_row_os"><?=$colors[3]?>&nbsp;</td>
            <td class="content_row_os"><?=$colors[4]?>&nbsp;</td>
            <td class="content_row_os">
               <?php
               if((int)$data[$x]["fab_manilla_length"])
               {
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fab_manilla_length"] = (int)$data[$x]["fab_manilla_length"];
                  echo (int)$data[$x]["fab_manilla_length"];
               }
               else
                  echo "&nbsp;";
               ?>
            </td>
            <td class="content_row_os">
               <?php
               if((int)$data[$x]["req_dsgnchk_pieimprenta_act"])
               {
                  echo "Si";
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["req_dsgnchk_pieimprenta_act"] = "Si";
               }
               else
               {
                  echo "No";
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["req_dsgnchk_pieimprenta_act"] = "No";
               }
               ?>
            </td>
            <td class="content_row_os">
               <?php
               if((int)$data[$x]["req_dsgnchk_barcode_act"])
               {
                  echo "Si";
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["req_dsgnchk_barcode_act"] = "Si";
               }
               else
               {
                  echo "No";
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["req_dsgnchk_barcode_act"] = "No";
               }
               ?>
            </td>
            <td class="content_row_os"><?=$data[$x]["designername"]?>&nbsp;</td>
         </tr>
         <?php

         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["req_number"]                 = $data[$x]["req_number"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["date"]                       = date('d.m.Y',$data[$x]["req_production_initdate"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["user_lastname"]              = $data[$x]["user_lastname"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["req_solic_supp_seudonimo"]   = $data[$x]["req_solic_supp_seudonimo"]." ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]                 = $data[$x]["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fab_med_width"]              = (int)$data[$x]["fab_med_width"]."x".(int)$data[$x]["fab_med_height"]." (".(int)$data[$x]["fab_med_fuelle"].")";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fab_type"]                   = $data[$x]["fab_type"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fab_printtype"]              = $fab_printtype;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["supp_short"]                 = $data[$x]["supp_short"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["printsides"]                 = $printsides;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fabric_color"]               = $fabric_color;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["manilla_color"]              = $manilla_color;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["colors1"]                    = $colors[1]." ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["colors2"]                    = $colors[2]." ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["colors3"]                    = $colors[3]." ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["colors4"]                    = $colors[4]." ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["designername"]               = $data[$x]["designername"]." ";
      }
      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="21" align="center">
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
</form>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsSolicsCC($CON);

if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsSolicsCC($CON);

if($pdffile != "")
{
   $doctitle = "Solicitudes-CC-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Solicitudes-CC-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php
$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';
?>