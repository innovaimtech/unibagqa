<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2023 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_prodtraza";
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
                t6.fab_manilla_length, t1.req_dsgnchk_pieimprenta_act, t1.req_dsgnchk_barcode_act, t8.user_lastname 'designername',
                t1.req_cliche_peli_recep_dat, t1.req_solic_devprints_cc, t1.req_operador_tela_width, t1.req_operador_mermaperc,
                t2x.item_prodcalc_fuelle_act, t1.req_solic_devprints_poltype
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

$resdata = Array();
for($x = 0; $x < count($data) && $data != false; $x++)
{
   $row = $data[$x];

   if((int)$row["item_prodcalc_fuelle_act"])
      $param_medida = (int)$row["fab_med_width"] + (int)$row["fab_med_fuelle"];
   else
      $param_medida = (int)$row["fab_med_width"];

   $events = getProdEvents($CON, 0, 0, 0, 0, $row["id"], (int)$_SESSION[$_sesmodulename]["sql_equipotypeid"], (int)$_SESSION[$_sesmodulename]["sql_equipoid"]);

   for($y = 0; $y < count($events) && $events != false; $y++)
   {
      if($events[$y]["evt_type"] == "prod" && (int)$events[$y]["evt_amount"] &&
         ((int)$events[$y]["equipo_prod_isprinter_seri"] || (int)$events[$y]["equipo_prod_isprinter_flexo"]))
      {
         // echo "<pre>";
         // print_r($row);
         // print_r($events[$y]);

         $sql = " select *
                  from equipo_params
                  where
                  param_equipo_id = {$events[$y]["ag_equipo_id"]} and
                  param_medida    >= {$param_medida}
                  order by param_medida asc
                  LIMIT 0,1";
         $equipo_params = $CON->select($sql);
         $equipo_params = $equipo_params[0];

         // print_r($equipo_params);
         // echo "<hr>";

         $corte_m2 = 0;
         $corte_z = 0;
         if((int)$events[$y]["equipo_prod_isprinter_seri"])
         {
            $corte_m2 = (float)$equipo_params["param_corte"];
            $corte_z  = (int)$equipo_params["param_z"];
         }
         elseif((int)$events[$y]["equipo_prod_isprinter_flexo"])
         {
            if($row["req_solic_devprints_poltype"] == "pol284")
            {
               $corte_m2 = (float)$equipo_params["param_poly28"];
               $corte_z  = (int)$equipo_params["param_z"];
            }
            elseif($row["req_solic_devprints_poltype"] == "pol170")
            {
               $corte_m2 = (float)$equipo_params["param_poly17"];
               $corte_z  = (int)$equipo_params["param_z"];
            }
         }

         // $corte_m2      = (float)$equipo_params["param_corte"];
         // $corte_z       = (int)$equipo_params["param_z"];
         $mlin_prog     = round($events[$y]["ag_amount"] * $corte_m2);
         $mlin_impresos = round($events[$y]["evt_amount"] * $corte_m2);
         $mlin_user     = $events[$y]["wrk_lastname"];

         if(!(int)$events[$y]["equipo_prod_isprinter_flexo"])
            $corte_z = 0;

         $baserow                   = $row;
         $baserow["corte_z"]        = $corte_z;
         $baserow["mlin_prog"]      = $mlin_prog;
         $baserow["mlin_impresos"]  = $mlin_impresos;
         $baserow["mlin_user"]      = $mlin_user;
         $resdata[]                 = $baserow;
      }
   }
}
$data = $resdata;

//----------------------------------------------------------------------------------
$sql = " select *
         from equipo_type
         where
         type_ant_status > 0 and
         type_ant_prod_dabl = 0
         order by type_ant_title";
$selequipotypes = $CON->select($sql);
$temp = Array();
for($x = 0; $x < count($selequipotypes) && $selequipotypes != false; $x++)
{
   $sql = " select *
            from equipo
            where
            equipo_status     > 0 and
            equipo_type_id    = {$selequipotypes[$x]["id"]} and
            equipo_prod_dabl = 0
            order by equipo_name";
   $equipos = $CON->select($sql);
   if(count($equipos) && $equipos != false)
   {
      $_SELEQUIPOTYPES[$selequipotypes[$x]["id"]] = $selequipotypes[$x]["type_ant_title"];

      foreach($equipos AS $equipo)
      {
         if((int)$_SESSION[$_sesmodulename]["sql_equipotypeid"] && (int)$_SESSION[$_sesmodulename]["sql_equipotypeid"] == $equipo["equipo_type_id"])
         {
            $_SELEQUIPOS[$equipo["id"]] = $equipo["equipo_name"];
         }
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.id, t1.equipo_name
         from equipo t1
         where
         t1.equipo_status > 0 and
         t1.equipo_planta_id = {$_SESSION[$_sesmodulename]["sql_plantaid"]} and
         t1.equipo_prod_dabl = 0 ";
if((int)$_SESSION[$_sesmodulename]["sql_equipotypeid"])
   $sql .= " and t1.equipo_type_id = {$_SESSION[$_sesmodulename]["sql_equipotypeid"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_equipoid"])
   $sql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_equipoid"]} ";
$sql .= " order by 2";
$equipos = $CON->select($sql);

//----------------------------------------------------------------------------------
$plantas = getPlantas($CON);
if(!(int)$_SESSION[$_sesmodulename]["sql_plantaid"])
   $_SESSION[$_sesmodulename]["sql_plantaid"] = $plantas[0]["id"];

//----------------------------------------------------------------------------------
?>
<div style="display:none">
   <select class="text" name="sql_plantaid" id="sql_plantaid" style="width:100%"
   onmousedown="markfield(this,0)" onblur="markfield(this,1)"
   onchange="jqLoadPlantaEquipoTypes(this.value)">
      <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
      <?php
      foreach ($plantas as $planta)
      {  ?>
         <option value="<?=$planta["id"]?>" <?php if($planta["id"] == $_SESSION[$_sesmodulename]["sql_plantaid"]) echo "selected"?>>
            <?=$planta["planta_name"]?>
         </option>
         <?php
      }
      ?>
   </select>
</div>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Informe de trazabilidad de CC</b></td>
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
         <td class="content_rowl">Tipo máquina</td>
         <td class="content_row">
            <select class="text" style="width:375px" name="sql_equipotypeid" id="sql_equipotypeid"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="jqLoadPlantaEquipos(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach(array_keys($_SELEQUIPOTYPES) AS $etypeid)
               {  ?>
                  <option value="<?=$etypeid?>"
                  <?php if($etypeid == $_SESSION[$_sesmodulename]["sql_equipotypeid"]) echo "selected"?>><?=$_SELEQUIPOTYPES[$etypeid]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Máquina</td>
         <td class="content_row">
            <select class="text" style="width:100%" name="sql_equipoid" id="sql_equipoid"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach(array_keys($_SELEQUIPOS) AS $eqid)
               {  ?>
                  <option value="<?=$eqid?>"
                  <?php if($eqid == $_SESSION[$_sesmodulename]["sql_equipoid"]) echo "selected"?>><?=$_SELEQUIPOS[$eqid]?></option>
                  <?php
               }
               ?>
            </select>
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
         <td class="content_tbl_header content_row_os">Impresor</td>
         <td class="content_tbl_header content_row_os">Total mts/lineales programados</td>
         <td class="content_tbl_header content_row_os">Seudónimo clisés</td>
         <td class="content_tbl_header content_row_os">Fecha creación montaje</td>
         <td class="content_tbl_header content_row_os">Cilindro (z)</td>
         <td class="content_tbl_header content_row_os">Desarrollo de impresión</td>
         <td class="content_tbl_header content_row_os">Ancho rollo tela</td>
         <td class="content_tbl_header content_row_os">Metros lineales impresos</td>
         <td class="content_tbl_header content_row_os">% Merma</td>
         <td class="content_tbl_header content_row_os">Diseñador responsable</td>
      </tr>
      <?php
      for($x = 0; $x < count($data) && $data != false; $x++)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os" align="center"><nobr><?=$data[$x]["req_number"]?></nobr></td>
            <td class="content_row_os"><?=$data[$x]["mlin_user"]?>&nbsp;</td>
            <td class="content_row_os"><?=printPrice($data[$x]["mlin_prog"])?></td>
            <td class="content_row_os"><?=$data[$x]["req_solic_supp_seudonimo"]?>&nbsp;</td>
            <td class="content_row_os">
               <?php
               if((int)$data[$x]["req_cliche_peli_recep_dat"])
               {
                  echo date("d.m.Y", $data[$x]["req_cliche_peli_recep_dat"]);
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["recep_dat"] = date("d.m.Y", $data[$x]["req_cliche_peli_recep_dat"]);
               }
               else
                  echo "&nbsp;";
               ?>
            </td>
            <td class="content_row_os"><?=$data[$x]["corte_z"]?></td>
            <td class="content_row_os"><?=$data[$x]["req_solic_devprints_cc"]?></td>
            <td class="content_row_os"><?=(int)$data[$x]["req_operador_tela_width"]?></td>
            <td class="content_row_os"><?=printPrice($data[$x]["mlin_impresos"])?></td>
            <td class="content_row_os"><?=printPrice($data[$x]["req_operador_mermaperc"],2)?></td>
            <td class="content_row_os"><?=$data[$x]["designername"]?>&nbsp;</td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["req_number"]        = $data[$x]["req_number"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["mlin_user"]         = $data[$x]["mlin_user"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["mlin_prog"]         = printPrice($data[$x]["mlin_prog"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["seudonimo"]         = $data[$x]["req_solic_supp_seudonimo"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["corte_z"]           = $data[$x]["corte_z"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["devprints_cc"]      = $data[$x]["req_solic_devprints_cc"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["tela_width"]        = (int)$data[$x]["req_operador_tela_width"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["mlin_impresos"]     = printPrice($data[$x]["mlin_impresos"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["mermaperc"]         = printPrice($data[$x]["req_operador_mermaperc"],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["designername"]      = $data[$x]["designername"];
      }
      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="11" align="center">
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
  $pdffile = doc_createStatsTrazaCC($CON);

if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsTrazaCC($CON);

if($pdffile != "")
{
   $doctitle = "Trazabilidad-CC-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Trazabilidad-CC-".time().".xls";
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