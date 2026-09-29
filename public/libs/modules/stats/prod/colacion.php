<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2021 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
// 
//----------------------------------------------------------------------------------
// Informe Generado por Fernando Garrido, tomando origen aprobacion.php
// Fecha: 06/10/2023
//----------------------------------------------------------------------------------

$_sesmodulename         = "stats_prodcolacion";
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

if((int)$_SESSION[$_sesmodulename]["sql_plantaid"])
{
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
               equipo_planta_id  = {$_SESSION[$_sesmodulename]["sql_plantaid"]} and
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
//wok_status 1 = En curso
/*
$sql = " select t1.*, t9.req_number, t12.cust_name, t10.item_amount,
                t11.item_number_prod, t11.item_title, t4.prd_number,
                t7.equipo_name, t4.id 'prod_header_id', t10.fab_design_name
         from prod_worker_ot t1
         INNER JOIN prod_agenda t2        ON t1.wok_ag_id = t2.id
         INNER JOIN prod_worker_init t3   ON t1.wok_init_id = t3.id
         INNER JOIN prod_header t4        ON t2.ag_prdid = t4.id
         LEFT OUTER JOIN equipo t7        ON t3.win_equipoid = t7.id
         LEFT OUTER JOIN equipo_type t8   ON    t7.equipo_type_id = t8.id
         INNER JOIN orders t9             ON t2.ag_reqid = t9.id
         INNER JOIN orders_items t10      ON t9.id = t10.req_id
         INNER JOIN item t11              ON t10.item_id = t11.id
         LEFT OUTER JOIN customer t12     ON t9.req_cust_id = t12.id
         where
         t1.wok_crtdat     between {$sql_datefrom} and {$sql_dateto} and
         t4.prd_plantaid   = {$_SESSION[$_sesmodulename]["sql_plantaid"]} and
         t1.wok_status     > 0 and
         (
            select count(*) 'cc'
            from prod_worker_ot_autocontrol t99
            where
            t99.ctr_init_id = t1.id
         ) > 0 ";
if((int)$_SESSION[$_sesmodulename]["sql_equipotypeid"])
   $sql .= " and t7.equipo_type_id = {$_SESSION[$_sesmodulename]["sql_equipotypeid"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_equipoid"])
   $sql .= " and t3.win_equipoid = {$_SESSION[$_sesmodulename]["sql_equipoid"]} ";
if($_SESSION[$_sesmodulename]["sql_number"] != "")
   $sql .= " and t9.req_number = '{$_SESSION[$_sesmodulename]["sql_number"]}' ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $sql .= " and t12.id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if($_SESSION[$_sesmodulename]["sql_fab_design_name"] != "")
   $sql .= " and t10.fab_design_name like '%{$_SESSION[$_sesmodulename]["sql_fab_design_name"]}%' ";
*/

$sql = " select t3.wok_crtdat as 'FechaInicioTurno',
         t3.wok_enddat as 'FechaFinTurno',
         's/informacion' as 'CodigoTurno',
         te.equipo_name as 'NombreMaquina',
         t9.user_rut as 'RutColaborador',
         t2.ag_crtusr as 'CodigoColaborador',
         concat(t9.user_firstname,t9.user_lastname) as 'NombreColaborador',
         t4.evt_crtdat as 'FechaInicioColacion',
         t4.evt_enddat as 'FechaTerminoColacon'
         from prod_header t1
            INNER JOIN prod_agenda t2 ON t1.id = t2.ag_prdid
            INNER JOIN prod_worker_ot t3 ON t3.wok_ag_id = t2.id
            INNER JOIN prod_worker_ot_events t4 ON t4.evt_prod_worker_otid = t3.id
            LEFT OUTER JOIN equipo_manttype t5 ON t4.evt_equipo_mantid = t5.id
            INNER JOIN prod_worker_init t6 ON t3.wok_init_id = t6.id
            INNER JOIN orders t7 ON t2.ag_reqid = t7.id
            LEFT OUTER JOIN customer t8 ON t7.req_cust_id = t8.id
            INNER JOIN orders_items t1x ON t7.id = t1x.req_id
            INNER JOIN item t2x ON t1x.item_id = t2x.id
            LEFT OUTER JOIN prod_pause_types t5x ON t4.evt_pause_id = t5x.id
            INNER JOIN user t9 on t9.id = t2.ag_crtusr
            LEFT OUTER JOIN equipo t10        ON t6.win_equipoid = t7.id
            INNER JOIN equipo te on t2.ag_equipo_id = te.id
         WHERE t3.wok_crtdat between {$sql_datefrom} and {$sql_dateto} 
               AND t1.prd_plantaid   = {$_SESSION[$_sesmodulename]["sql_plantaid"]} 
               AND t1.prd_status = 2 AND t2.ag_status > 0
               AND t3.wok_status > 0
               AND t4.evt_status > 0
               AND t6.win_wrkid = 21
               AND t6.win_status > 0
               AND t6.win_plantaid = 1
               AND t5x.pause_code = '2200'
      ";
      
      if((int)$_SESSION[$_sesmodulename]["sql_equipotypeid"])
         $sql .= " and te.equipo_type_id = {$_SESSION[$_sesmodulename]["sql_equipotypeid"]} ";
      /*if((int)$_SESSION[$_sesmodulename]["sql_equipoid"])
         $sql .= " and t6.win_equipoid = {$_SESSION[$_sesmodulename]["sql_equipoid"]} ";*/
      if($_SESSION[$_sesmodulename]["sql_number"] != "")
         $sql .= " and t7.req_number = '{$_SESSION[$_sesmodulename]["sql_number"]}' ";
      if((int)$_SESSION[$_sesmodulename]["sql_customer"])
         $sql .= " and t8.id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
      
      $sql .= "ORDER BY t4.evt_crtdat DESC
      LIMIT 0 , 100";
      $data = $CON->select($sql);

//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Informe de Colaciones Ingresadas</b></td>
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
         <td class="content_tbl_header" colspan="4">Opciones de busqueda</td>
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
         <td class="content_rowl">Planta</td>
         <td class="content_row">
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
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo maquina</td>
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
         <td class="content_rowl">Maquina</td>
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
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
         <td class="content_rowl">Numero CC</td>
         <td class="content_row">
            <input type="text" class="text" style="width:100%"
            name="sql_number" value="<?=$_SESSION[$_sesmodulename]["sql_number"]?>"
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
         <td class="content_tbl_header content_row_os">Fecha Hora inicio</td>
         <td class="content_tbl_header content_row_os">Fecha Hora Fin Turno</td>
         <td class="content_tbl_header content_row_os">Codigo Turno</td>
         <td class="content_tbl_header content_row_os">Nombre Maquina</td>
         <td class="content_tbl_header content_row_os">Rut Colaborador</td>
         <td class="content_tbl_header content_row_os">Codigo Colaborador</td>
         <td class="content_tbl_header content_row_os">Nombre Colaborador</td>
         <td class="content_tbl_header content_row_os">Fecha Hora Inicio Colacion</td>
         <td class="content_tbl_header content_row_os">Fecha Fora Fin Colocion</td>
         <td class="content_tbl_header content_row_os">Total Tiempo Colocacion (min)</td>
      </tr>
      <?php
      for($x = 0; $x < count($data) && $data != false; $x++)
      {
         /*
         $sql = " select t2.user_lastname, MAX(ctr_ctrdat) 'ctr_ctrdat'
                  from prod_worker_ot_autocontrol t1
                  INNER JOIN user t2 ON t1.ctr_ctrusr = t2.id
                  where
                  t1.ctr_init_id = {$data[$x]["id"]} and
                  t1.ctr_type    = 'worker'
                  group by 1
                  LIMIT 0,1";
         $wdata = $CON->select($sql);
         $wdata = $wdata[0];
         */
         /*
         $sql = " select t2.user_lastname, MAX(ctr_ctrdat) 'ctr_ctrdat'
                  from prod_worker_ot_autocontrol t1
                  INNER JOIN user t2 ON t1.ctr_ctrusr = t2.id
                  where
                  t1.ctr_init_id = {$data[$x]["id"]} and
                  t1.ctr_type    = 'supervisor'
                  group by 1
                  LIMIT 0,1";
         $sdata = $CON->select($sql);
         $sdata = $sdata[0];
         */
         /*
            $_PRODSTATS = getProdStats($CON, $data[$x]["prod_header_id"], $data[$x]["wok_ag_id"], $data[$x]["id"], $_SESSION[$_sesmodulename]["sql_plantaid"]);
         */
         $orig_enddat = (int)$data[$x]["FechaTerminoColacon"];
         if(!(int)$data[$x]["FechaTerminoColacon"])
            $data[$x]["FechaTerminoColacon"] = time();
 
         $time_diff  = $data[$x]["FechaTerminoColacon"] - $data[$x]["FechaInicioColacion"];
         $time_diffx = $time_diff / 60;
         $hours_diff = (int)($time_diffx / 60);
         $min_diff   = (int)($time_diffx - ($hours_diff * 60));
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os" align="center"><?=date('d.m.Y H:i:s',$data[$x]["FechaInicioTurno"])?></td>
            <td class="content_row_os" align="center"><?=date('d.m.Y H:i:s',$data[$x]["FechaFinTurno"])?></td>
            <td class="content_row_os"><?=$data[$x]["CodigoTurno"]?></td>
            <td class="content_row_os"><?=$data[$x]["NombreMaquina"]?></td>
            <td class="content_row_os"><?=$data[$x]["RutColaborador"]?></td>
            <td class="content_row_os"><?=$data[$x]["CodigoColaborador"]?></td>
            <td class="content_row_os"><?=$data[$x]["NombreColaborador"]?></td>           
            <td class="content_row_os" align="center"><?=date('d.m.Y H:i:s',$data[$x]["FechaInicioColacion"])?></td>
            <td class="content_row_os" align="center"><?=date('d.m.Y H:i:s',$data[$x]["FechaTerminoColacon"])?></td>
            <td class="content_row_os" align="center"><?="{$hours_diff}h {$min_diff}m"?></td>
         </tr>
         <?php
         
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FechaInicioTurno"]         = date('d.m.Y H:i:s',$data[$x]["FechaInicioTurno"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FechaFinTurno"]            = date('d.m.Y H:i:s',$data[$x]["FechaFinTurno"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CodigoTurno"]              = $data[$x]["CodigoTurno"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NombreMaquina"]            = $data[$x]["NombreMaquina"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["RutColaborador"]           = $data[$x]["RutColaborador"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["CodigoColaborador"]        = $data[$x]["CodigoColaborador"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["NombreColaborador"]        = $data[$x]["NombreColaborador"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FechaInicioColacion"]      = date('d.m.Y H:i:s',$data[$x]["FechaInicioColacion"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["FechaTerminoColacon"]      = date('d.m.Y H:i:s',$data[$x]["FechaTerminoColacon"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["TiempoReal"]          = "{$hours_diff}h {$min_diff}m";
      }
      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="20" align="center">
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
  $pdffile = doc_createStatsColcacion($CON);

if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsColcaciones($CON);

if($pdffile != "")
{
   $doctitle = "Colaciones-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
?>
<?php
if($xlsfile != "")
{
   $doctitle = "Colaciones-".time().".xls";
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